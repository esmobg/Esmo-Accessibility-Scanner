<?php
/**
 * Normalizes raw axe-core results into storable issue rows.
 *
 * @package EsmoAccessibilityScanner
 */

declare(strict_types=1);

namespace EsmoAccessibilityScanner\Scan;

use EsmoAccessibilityScanner\Rules\RuleCatalog;

defined( 'ABSPATH' ) || exit;

/**
 * Converts the JSON payload produced by the browser scanner into the
 * canonical issue schema used by the storage layer.
 */
final class ResultNormalizer {

	/**
	 * Most axe results kept per group (violations, needs review) for one page.
	 */
	public const MAX_RESULTS = 150;

	/**
	 * Most elements kept per rule for one page (the scanner sends at most 25).
	 */
	public const MAX_NODES = 25;

	/**
	 * Most issue rows stored for one page.
	 */
	public const MAX_ROWS = 2500;

	/**
	 * Impact values axe-core reports.
	 *
	 * @var array<int, string>
	 */
	private const IMPACTS = array( 'critical', 'serious', 'moderate', 'minor' );

	/**
	 * Rule metadata catalog.
	 */
	private RuleCatalog $catalog;

	/**
	 * Constructor.
	 *
	 * @param RuleCatalog $catalog Rule metadata catalog.
	 */
	public function __construct( RuleCatalog $catalog ) {
		$this->catalog = $catalog;
	}

	/**
	 * Normalize a single URL's scan payload into issue rows.
	 *
	 * The payload comes from the browser, so every field is type-checked and
	 * the number of rows is capped (MAX_RESULTS, MAX_NODES, MAX_ROWS).
	 *
	 * @param array<string, mixed> $payload Decoded payload for one URL.
	 * @return array<int, array<string, mixed>>
	 */
	public function normalize( array $payload ): array {
		$url     = esc_url_raw( $this->text( $payload['url'] ?? '' ), array( 'http', 'https' ) );
		$post_id = absint( $this->text( $payload['post_id'] ?? 0 ) );

		$issues = array();

		foreach ( array( 'violations' => false, 'incomplete' => true ) as $group => $is_incomplete ) {
			foreach ( $this->extract_results( $payload, $group ) as $result ) {
				foreach ( $this->build_rows( $result, $url, $post_id, $is_incomplete ) as $row ) {
					if ( count( $issues ) >= self::MAX_ROWS ) {
						return $issues;
					}
					$issues[] = $row;
				}
			}
		}

		return $issues;
	}

	/**
	 * Build issue rows from a single axe result group.
	 *
	 * @param array<string, mixed> $result        One axe result (rule with nodes).
	 * @param string               $url           Page URL.
	 * @param int                  $post_id       Post id.
	 * @param bool                 $is_incomplete Whether this is a "needs review" item.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_rows( array $result, string $url, int $post_id, bool $is_incomplete ): array {
		$rule_id = substr( sanitize_key( $this->text( $result['id'] ?? '' ) ), 0, 100 );
		if ( '' === $rule_id ) {
			return array();
		}

		$impact = strtolower( $this->text( $result['impact'] ?? '' ) );
		if ( ! in_array( $impact, self::IMPACTS, true ) ) {
			$impact = '';
		}

		$help = $this->truncate( $this->readable_text( $this->text( $result['help'] ?? '' ) ), 500 );

		// Best-practice rules (headings, landmarks, ...) are advisory, not WCAG failures.
		$best_practice = true === ( $result['bestPractice'] ?? false );

		$meta     = $this->catalog->get( $rule_id, $impact );
		$severity = ( $is_incomplete || $best_practice ) ? 'warning' : $meta['severity'];

		$nodes = isset( $result['nodes'] ) && is_array( $result['nodes'] ) ? array_slice( $result['nodes'], 0, self::MAX_NODES ) : array();

		// A result with no nodes still represents a page-level issue.
		if ( empty( $nodes ) ) {
			$nodes = array( array() );
		}

		$rows = array();

		foreach ( $nodes as $node ) {
			$node    = is_array( $node ) ? $node : array();
			$snippet = $this->text( $node['html'] ?? '' );
			$target  = $this->target_to_path( $node['target'] ?? array() );
			$summary = $this->truncate( $this->readable_text( $this->text( $node['failureSummary'] ?? '' ) ), 1000 );

			$message = '' !== $help ? $help : $summary;
			if ( $is_incomplete ) {
				$message = sprintf(
					/* translators: %s: rule description. */
					__( 'Needs manual review: %s', 'esmo-accessibility-scanner' ),
					$message
				);
			}

			$rows[] = array(
				'url'            => $url,
				'post_id'        => $post_id,
				'rule_id'        => $rule_id,
				'wcag_ref'       => $meta['wcag'],
				'severity'       => $severity,
				'category'       => $meta['category'],
				'impact'         => $impact,
				'message'        => $message,
				'html_snippet'   => $this->truncate( $snippet, 2000 ),
				'dom_path'       => $this->truncate( $target, 1000 ),
				'fix_suggestion' => $meta['fix'],
				'doc_link'       => $meta['doc'],
				'status'         => 'open',
			);
		}

		return $rows;
	}

	/**
	 * Aggregate severity counts for a set of normalized issue rows.
	 *
	 * @param array<int, array<string, mixed>> $issues Normalized rows.
	 * @return array<string, int>
	 */
	public function severity_counts( array $issues ): array {
		$counts = array(
			'critical' => 0,
			'major'    => 0,
			'minor'    => 0,
			'warning'  => 0,
		);

		foreach ( $issues as $issue ) {
			$severity = (string) ( $issue['severity'] ?? 'minor' );
			if ( isset( $counts[ $severity ] ) ) {
				++$counts[ $severity ];
			}
		}

		return $counts;
	}

	/**
	 * Extract a results group from the payload.
	 *
	 * @param array<string, mixed> $payload Decoded payload.
	 * @param string               $key     Group key (violations|incomplete).
	 * @return array<int, array<string, mixed>>
	 */
	private function extract_results( array $payload, string $key ): array {
		if ( ! isset( $payload[ $key ] ) || ! is_array( $payload[ $key ] ) ) {
			return array();
		}

		$results = array_filter(
			$payload[ $key ],
			static fn ( $item ): bool => is_array( $item )
		);

		return array_slice( array_values( $results ), 0, self::MAX_RESULTS );
	}

	/**
	 * Convert an axe target (array of selectors) into a readable DOM path.
	 *
	 * @param mixed $target axe target value.
	 */
	private function target_to_path( mixed $target ): string {
		$parts = array();

		foreach ( is_array( $target ) ? $target : array( $target ) as $part ) {
			// Targets inside shadow DOM are lists of selectors.
			$parts[] = is_array( $part ) ? implode( ' ', array_map( fn ( $selector ): string => $this->text( $selector ), $part ) ) : $this->text( $part );
		}

		return sanitize_text_field( implode( ' > ', array_filter( $parts, 'strlen' ) ) );
	}

	/**
	 * A scalar payload value as a string; arrays and objects become ''.
	 *
	 * @param mixed $value Decoded JSON value.
	 */
	private function text( mixed $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Sanitize rule text while keeping element names such as "<ul>" readable.
	 *
	 * The angle brackets are stored as entities so sanitize_text_field() does
	 * not strip them as tags; esc_html() renders them as text on output.
	 *
	 * @param string $value Raw text from axe-core.
	 */
	private function readable_text( string $value ): string {
		return sanitize_text_field( str_replace( array( '<', '>' ), array( '&lt;', '&gt;' ), $value ) );
	}

	/**
	 * Truncate a string to a maximum character length without splitting
	 * multibyte characters (which would produce invalid UTF-8 that strict
	 * MySQL modes reject, silently dropping the whole issue row).
	 *
	 * @param string $value  Source string.
	 * @param int    $length Maximum length in characters.
	 */
	private function truncate( string $value, int $length ): string {
		// WordPress core polyfills mb_substr(), so it is always available.
		if ( mb_strlen( $value, 'UTF-8' ) <= $length ) {
			return $value;
		}

		return mb_substr( $value, 0, $length, 'UTF-8' ) . '...';
	}
}
