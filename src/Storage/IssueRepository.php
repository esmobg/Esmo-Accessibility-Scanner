<?php
/**
 * Data access layer for accessibility issue records.
 *
 * @package EsmoAccessibilityScanner
 */

declare(strict_types=1);

namespace EsmoAccessibilityScanner\Storage;

defined( 'ABSPATH' ) || exit;

// Custom esmoas_issues table; identifiers are $wpdb->prefix plus a hardcoded suffix.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

/**
 * Reads and writes issue rows in the esmoas_issues table.
 */
final class IssueRepository {

	/**
	 * Issues table of the current site (resolved per call, so switch_to_blog() is respected).
	 */
	private function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'esmoas_issues';
	}

	/**
	 * Insert a batch of normalized issues for a scan.
	 *
	 * @param int                                   $scan_id Scan id.
	 * @param array<int, array<string, mixed>>      $issues  Normalized issue rows.
	 * @return array<int, int> Ids of the rows inserted.
	 */
	public function insert_batch( int $scan_id, array $issues ): array {
		global $wpdb;

		$ids = array();
		$now = current_time( 'mysql' );

		foreach ( $issues as $issue ) {
			$inserted = $wpdb->insert(
				$this->table(),
				array(
					'scan_id'        => $scan_id,
					'url'            => esc_url_raw( (string) ( $issue['url'] ?? '' ) ),
					'post_id'        => (int) ( $issue['post_id'] ?? 0 ),
					'rule_id'        => sanitize_key( (string) ( $issue['rule_id'] ?? '' ) ),
					'wcag_ref'       => sanitize_text_field( (string) ( $issue['wcag_ref'] ?? '' ) ),
					'severity'       => sanitize_key( (string) ( $issue['severity'] ?? 'minor' ) ),
					'category'       => sanitize_key( (string) ( $issue['category'] ?? '' ) ),
					'impact'         => sanitize_text_field( (string) ( $issue['impact'] ?? '' ) ),
					'message'        => self::readable_text( (string) ( $issue['message'] ?? '' ) ),
					// Stored as plain text (never rendered as HTML); escaped on output and in exports.
					'html_snippet'   => self::plain_text( (string) ( $issue['html_snippet'] ?? '' ) ),
					'dom_path'       => sanitize_text_field( (string) ( $issue['dom_path'] ?? '' ) ),
					'fix_suggestion' => self::readable_text( (string) ( $issue['fix_suggestion'] ?? '' ) ),
					'doc_link'       => esc_url_raw( (string) ( $issue['doc_link'] ?? '' ) ),
					'status'         => sanitize_key( (string) ( $issue['status'] ?? 'open' ) ),
					'created_at'     => $now,
				),
				array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);

			if ( $inserted ) {
				$ids[] = (int) $wpdb->insert_id;
			}
		}

		return $ids;
	}

	/**
	 * Remove rows of a scan again (used when a page's results cannot be stored completely).
	 *
	 * @param int             $scan_id Scan id.
	 * @param array<int, int> $ids     Row ids returned by insert_batch().
	 */
	public function delete_ids( int $scan_id, array $ids ): void {
		global $wpdb;

		foreach ( $ids as $id ) {
			$wpdb->delete(
				$this->table(),
				array(
					'id'      => (int) $id,
					'scan_id' => $scan_id,
				),
				array( '%d', '%d' )
			);
		}
	}

	/**
	 * Sanitize human-readable text while keeping element names such as "<main>".
	 *
	 * Angle brackets are stored as entities so sanitize_textarea_field() does
	 * not strip them as tags; esc_html() renders them as text on output.
	 *
	 * @param string $value Message or fix suggestion.
	 */
	private static function readable_text( string $value ): string {
		return sanitize_textarea_field( str_replace( array( '<', '>' ), array( '&lt;', '&gt;' ), $value ) );
	}

	/**
	 * Normalise a snippet of page markup for storage as text.
	 *
	 * @param string $value Raw snippet.
	 */
	private static function plain_text( string $value ): string {
		$value = wp_check_invalid_utf8( $value );

		return str_replace( chr( 0 ), '', $value );
	}

	/**
	 * Count issues for a scan grouped by severity.
	 *
	 * @param int $scan_id Scan id.
	 * @return array<string, int>
	 */
	public function severity_counts( int $scan_id ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT severity, COUNT(*) AS total FROM {$this->table()} WHERE scan_id = %d GROUP BY severity", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$scan_id
			),
			ARRAY_A
		);

		$counts = array(
			'critical' => 0,
			'major'    => 0,
			'minor'    => 0,
			'warning'  => 0,
		);

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$counts[ (string) $row['severity'] ] = (int) $row['total'];
			}
		}

		return $counts;
	}

	/**
	 * Count issues for a scan grouped by URL and severity.
	 *
	 * @param int $scan_id Scan id.
	 * @return array<string, array<string, int>>
	 */
	public function severity_counts_by_url( int $scan_id ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT url, severity, COUNT(*) AS total FROM {$this->table()} WHERE scan_id = %d GROUP BY url, severity",
				$scan_id
			),
			ARRAY_A
		);

		$by_url = array();

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$url = (string) $row['url'];

				if ( ! isset( $by_url[ $url ] ) ) {
					$by_url[ $url ] = array(
						'critical' => 0,
						'major'    => 0,
						'minor'    => 0,
						'warning'  => 0,
					);
				}

				$severity = (string) $row['severity'];
				if ( isset( $by_url[ $url ][ $severity ] ) ) {
					$by_url[ $url ][ $severity ] = (int) $row['total'];
				}
			}
		}

		return $by_url;
	}

	/**
	 * Count issues for a scan grouped by category.
	 *
	 * @param int $scan_id Scan id.
	 * @return array<string, int>
	 */
	public function category_counts( int $scan_id ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT category, COUNT(*) AS total FROM {$this->table()} WHERE scan_id = %d GROUP BY category ORDER BY total DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$scan_id
			),
			ARRAY_A
		);

		$counts = array();

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$counts[ (string) $row['category'] ] = (int) $row['total'];
			}
		}

		return $counts;
	}

	/**
	 * Get the most common rules across a scan.
	 *
	 * @param int $scan_id Scan id.
	 * @param int $limit   Maximum rules.
	 * @return array<int, array<string, mixed>>
	 */
	public function top_rules( int $scan_id, int $limit = 10 ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT rule_id, wcag_ref, severity, COUNT(*) AS total FROM {$this->table()} WHERE scan_id = %d GROUP BY rule_id, wcag_ref, severity ORDER BY total DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$scan_id,
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Retrieve issues for a scan with pagination.
	 *
	 * @param int    $scan_id  Scan id.
	 * @param int    $per_page Items per page.
	 * @param int    $page     Page number (1-based).
	 * @param string $severity Optional severity filter.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_scan( int $scan_id, int $per_page = 50, int $page = 1, string $severity = '' ): array {
		global $wpdb;

		$offset = max( 0, ( $page - 1 ) * $per_page );

		if ( '' !== $severity ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$this->table()} WHERE scan_id = %d AND severity = %s ORDER BY FIELD(severity,'critical','major','minor','warning'), id ASC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$scan_id,
					$severity,
					$per_page,
					$offset
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$this->table()} WHERE scan_id = %d ORDER BY FIELD(severity,'critical','major','minor','warning'), id ASC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$scan_id,
					$per_page,
					$offset
				),
				ARRAY_A
			);
		}

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Total issue count for a scan.
	 *
	 * @param int $scan_id Scan id.
	 */
	public function count_for_scan( int $scan_id ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table()} WHERE scan_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$scan_id
			)
		);
	}
}
