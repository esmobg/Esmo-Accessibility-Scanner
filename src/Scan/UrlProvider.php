<?php
/**
 * Builds the list of URLs that a scan should visit.
 *
 * @package EsmoAccessibilityScanner
 */

declare(strict_types=1);

namespace EsmoAccessibilityScanner\Scan;

use EsmoAccessibilityScanner\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Enumerates scannable front-end URLs from WordPress content.
 */
final class UrlProvider {

	/**
	 * Default full-site URL cap when no filter is applied.
	 */
	public const DEFAULT_URL_LIMIT = 1000;

	/**
	 * Resolve the full-site URL cap (filterable, hard-capped).
	 *
	 * @param int $requested Preferred limit; 0 uses DEFAULT_URL_LIMIT.
	 */
	public static function resolve_url_limit( int $requested = 0 ): int {
		$default = $requested > 0 ? $requested : self::DEFAULT_URL_LIMIT;
		$limit   = (int) Plugin::filter( 'esmoas_scan_url_limit', $default );

		return max( 1, min( 10000, $limit ) );
	}

	/**
	 * Build the list of URLs for a full-site scan.
	 *
	 * @param int $limit Maximum number of URLs to return (safety cap).
	 * @return array<int, array{url:string,post_id:int,label:string}>
	 */
	public function get_full_site_urls( int $limit = 0 ): array {
		if ( $limit < 1 ) {
			$limit = self::resolve_url_limit();
		}
		$urls = array();

		$urls[] = array(
			'url'     => home_url( '/' ),
			'post_id' => 0,
			'label'   => __( 'Home page', 'esmo-accessibility-scanner' ),
		);

		foreach ( $this->scannable_post_types() as $post_type ) {
			foreach ( $this->get_post_type_urls( $post_type, $limit ) as $entry ) {
				$urls[] = $entry;
			}
		}

		if ( $this->include_terms() ) {
			foreach ( $this->get_term_urls( $limit ) as $entry ) {
				$urls[] = $entry;
			}
		}

		/**
		 * Filters the URL queue of a full-site scan before it is capped.
		 *
		 * Each entry is an array with `url` (string), `post_id` (int) and `label` (string).
		 *
		 * @since 1.2.0
		 *
		 * @param array<int, array{url:string,post_id:int,label:string}> $urls  URLs to scan, in order.
		 * @param int                                                    $limit Maximum number of URLs that will be scanned.
		 */
		$filtered = Plugin::filter( 'esmoas_scan_urls', $urls, $limit );
		$urls     = $this->sanitize_entries( is_array( $filtered ) ? $filtered : $urls );

		$urls = $this->deduplicate( $urls );

		return array_slice( $urls, 0, $limit );
	}

	/**
	 * Keep only well-formed http(s) entries on this site (the queue is also
	 * filterable through esmoas_scan_urls and is loaded in an admin session).
	 *
	 * @param array<int|string, mixed> $entries Raw entries.
	 * @return array<int, array{url:string,post_id:int,label:string}>
	 */
	private function sanitize_entries( array $entries ): array {
		$clean = array();

		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['url'] ) || ! is_string( $entry['url'] ) ) {
				continue;
			}

			$url = esc_url_raw( $entry['url'], array( 'http', 'https' ) );
			if ( '' === $url || ! self::is_site_url( $url ) ) {
				continue;
			}

			$label = isset( $entry['label'] ) && is_scalar( $entry['label'] ) ? self::plain_text( (string) $entry['label'] ) : '';

			$clean[] = array(
				'url'     => $url,
				'post_id' => isset( $entry['post_id'] ) && is_scalar( $entry['post_id'] ) ? absint( $entry['post_id'] ) : 0,
				'label'   => '' !== $label ? $label : $url,
			);
		}

		return $clean;
	}

	/**
	 * Whether a URL is an http(s) address on this site's host and port.
	 *
	 * @param string $url URL to check.
	 */
	public static function is_site_url( string $url ): bool {
		$parts = wp_parse_url( $url );
		$home  = wp_parse_url( home_url( '/' ) );

		if ( ! is_array( $parts ) || ! is_array( $home ) || empty( $parts['host'] ) || empty( $home['host'] ) ) {
			return false;
		}

		return in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), array( 'http', 'https' ), true )
			&& strtolower( (string) $parts['host'] ) === strtolower( (string) $home['host'] )
			&& ( $parts['port'] ?? null ) === ( $home['port'] ?? null );
	}

	/**
	 * The post for a single-page scan: it must have a front-end view and be
	 * readable by the current user (drafts and private posts follow core rules).
	 *
	 * @param int $post_id Post identifier.
	 */
	public static function scannable_post( int $post_id ): ?\WP_Post {
		$post = $post_id > 0 ? get_post( $post_id ) : null;

		if ( ! $post instanceof \WP_Post || ! is_post_type_viewable( $post->post_type ) || ! current_user_can( 'read_post', $post->ID ) ) {
			return null;
		}

		return $post;
	}

	/**
	 * Build a single-URL list for a single post/page scan.
	 *
	 * @param int $post_id Post identifier.
	 * @return array<int, array{url:string,post_id:int,label:string}>
	 */
	public function get_single_post_urls( int $post_id ): array {
		$post = self::scannable_post( $post_id );
		if ( null === $post ) {
			return array();
		}

		return $this->sanitize_entries(
			array(
				array(
					'url'     => get_permalink( $post->ID ),
					'post_id' => $post->ID,
					'label'   => get_the_title( $post->ID ),
				),
			)
		);
	}

	/**
	 * Titles and term names as plain text: the scan log shows them with textContent.
	 *
	 * @param string $value Title or label, possibly with markup or entities.
	 */
	private static function plain_text( string $value ): string {
		return html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * Resolve the post types that should be scanned.
	 *
	 * @return array<int, string>
	 */
	public function scannable_post_types(): array {
		$settings   = (array) get_option( 'esmoas_settings', array() );
		$configured = isset( $settings['include_post_types'] ) && is_array( $settings['include_post_types'] )
			? array_map( 'strval', $settings['include_post_types'] )
			: array();

		$public = get_post_types( array( 'public' => true ), 'names' );
		unset( $public['attachment'] );

		if ( empty( $configured ) ) {
			return array_values( $public );
		}

		return array_values( array_intersect( $configured, $public ) );
	}

	/**
	 * Get URLs for a given post type.
	 *
	 * @param string $post_type Post type name.
	 * @param int    $limit     Maximum posts to fetch.
	 * @return array<int, array{url:string,post_id:int,label:string}>
	 */
	private function get_post_type_urls( string $post_type, int $limit ): array {
		$query = new \WP_Query(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => $limit,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
			)
		);

		// Load all posts in one query instead of one query per permalink/title lookup.
		if ( function_exists( '_prime_post_caches' ) && ! empty( $query->posts ) ) {
			_prime_post_caches( array_map( 'intval', $query->posts ), false, false );
		}

		$entries = array();

		foreach ( $query->posts as $post_id ) {
			$post_id   = (int) $post_id;
			$permalink = get_permalink( $post_id );

			if ( false === $permalink ) {
				continue;
			}

			$entries[] = array(
				'url'     => $permalink,
				'post_id' => $post_id,
				'label'   => get_the_title( $post_id ),
			);
		}

		return $entries;
	}

	/**
	 * Get URLs for public taxonomy term archives.
	 *
	 * @param int $limit Maximum terms to fetch.
	 * @return array<int, array{url:string,post_id:int,label:string}>
	 */
	private function get_term_urls( int $limit ): array {
		$taxonomies = get_taxonomies( array( 'public' => true ), 'names' );

		if ( empty( $taxonomies ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => array_values( $taxonomies ),
				'hide_empty' => true,
				'number'     => $limit,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$entries = array();

		foreach ( $terms as $term ) {
			$link = get_term_link( $term );

			if ( is_wp_error( $link ) ) {
				continue;
			}

			$entries[] = array(
				'url'     => $link,
				'post_id' => 0,
				'label'   => sprintf(
					/* translators: 1: taxonomy name, 2: term name. */
					__( '%1$s: %2$s', 'esmo-accessibility-scanner' ),
					$term->taxonomy,
					$term->name
				),
			);
		}

		return $entries;
	}

	/**
	 * Whether term archives should be included, per settings.
	 */
	private function include_terms(): bool {
		$settings = (array) get_option( 'esmoas_settings', array() );

		return ! empty( $settings['include_terms'] );
	}

	/**
	 * Remove duplicate URLs while preserving order.
	 *
	 * @param array<int, array{url:string,post_id:int,label:string}> $urls Raw URL list.
	 * @return array<int, array{url:string,post_id:int,label:string}>
	 */
	private function deduplicate( array $urls ): array {
		$seen   = array();
		$result = array();

		foreach ( $urls as $entry ) {
			$key = $entry['url'];

			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$result[]     = $entry;
		}

		return $result;
	}
}
