<?php
/**
 * Data access layer for scan records.
 *
 * @package EsmoAccessibilityScanner
 */

declare(strict_types=1);

namespace EsmoAccessibilityScanner\Storage;

defined( 'ABSPATH' ) || exit;

// Custom esmoas_scans / esmoas_history tables; identifiers are $wpdb->prefix plus a hardcoded suffix.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter

/**
 * Reads and writes scan rows in the esmoas_scans table.
 */
final class ScanRepository {

	/**
	 * Scans table of the current site (resolved per call, so switch_to_blog() is respected).
	 */
	private function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'esmoas_scans';
	}

	/**
	 * Score history table of the current site.
	 */
	private function history_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'esmoas_history';
	}

	/**
	 * Create a new scan record.
	 *
	 * @param string $scan_type  Scan type (single|full).
	 * @param int    $total_urls Number of URLs queued.
	 * @return int New scan id (0 on failure).
	 */
	public function create( string $scan_type, int $total_urls ): int {
		global $wpdb;

		$now      = current_time( 'mysql' );
		$inserted = $wpdb->insert(
			$this->table(),
			array(
				'scan_type'  => $scan_type,
				'status'     => 'running',
				'started_at' => $now,
				'updated_at' => $now,
				'total_urls' => $total_urls,
			),
			array( '%s', '%s', '%s', '%s', '%d' )
		);

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Count one more scanned page, if the scan is running and has pages left.
	 *
	 * A scan never accepts more results than the pages it queued, so neither
	 * repeated requests nor saves after the scan finished can inflate it.
	 *
	 * @param int $scan_id Scan id.
	 * @return bool Whether a page slot was claimed.
	 */
	public function claim_page( int $scan_id ): bool {
		global $wpdb;

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table()} SET scanned_urls = scanned_urls + 1, updated_at = %s WHERE id = %d AND status = 'running' AND scanned_urls < total_urls", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				current_time( 'mysql' ),
				$scan_id
			)
		);

		return 1 === $updated;
	}

	/**
	 * Finalize a running scan with aggregate metrics and add it to the score history.
	 *
	 * @param int   $scan_id Scan id.
	 * @param array{score:int,errors:int,warnings:int,passes:int} $totals Aggregate counts.
	 * @return bool False when the scan was no longer running (for example finished by another request).
	 */
	public function complete( int $scan_id, array $totals ): bool {
		global $wpdb;

		if ( ! $this->finish( $scan_id, 'complete', $totals ) ) {
			return false;
		}

		$wpdb->insert(
			$this->history_table(),
			array(
				'scan_id'    => $scan_id,
				'score'      => (int) $totals['score'],
				'errors'     => (int) $totals['errors'],
				'warnings'   => (int) $totals['warnings'],
				'passes'     => (int) $totals['passes'],
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%d', '%s' )
		);

		return true;
	}

	/**
	 * Mark a running scan as cancelled, keeping its partial totals.
	 *
	 * Cancelled scans are not added to the score history and are never
	 * returned by latest().
	 *
	 * @param int   $scan_id Scan id.
	 * @param array{score:int,errors:int,warnings:int,passes:int} $totals Aggregate counts so far.
	 * @return bool False when the scan was no longer running.
	 */
	public function cancel( int $scan_id, array $totals ): bool {
		return $this->finish( $scan_id, 'cancelled', $totals );
	}

	/**
	 * Move a scan from "running" to a final status in one atomic update.
	 *
	 * @param int    $scan_id Scan id.
	 * @param string $status  Final status.
	 * @param array{score:int,errors:int,warnings:int,passes:int} $totals Aggregate counts.
	 * @return bool Whether this call finished the scan.
	 */
	private function finish( int $scan_id, string $status, array $totals ): bool {
		global $wpdb;

		$updated = $wpdb->update(
			$this->table(),
			array(
				'status'      => $status,
				'finished_at' => current_time( 'mysql' ),
				'score'       => (int) $totals['score'],
				'errors'      => (int) $totals['errors'],
				'warnings'    => (int) $totals['warnings'],
				'passes'      => (int) $totals['passes'],
			),
			array(
				'id'     => $scan_id,
				'status' => 'running',
			),
			array( '%s', '%s', '%d', '%d', '%d', '%d' ),
			array( '%d', '%s' )
		);

		return 1 === $updated;
	}

	/**
	 * Mark running scans without progress for too long as failed.
	 *
	 * A scan only progresses while the admin keeps the Run Scan tab open, so
	 * a closed tab would otherwise leave the row "running" forever. Long scans
	 * that are still saving pages are never touched.
	 *
	 * @param int $max_idle_seconds Inactivity after which a running scan is stale.
	 * @return int Number of scans marked failed.
	 */
	public function fail_stale( int $max_idle_seconds = 900 ): int {
		global $wpdb;

		// Times are stored in site-local time (current_time( 'mysql' )), so compare in the same zone.
		$cutoff = wp_date( 'Y-m-d H:i:s', time() - $max_idle_seconds );

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table()} SET status = 'failed', finished_at = %s WHERE status = 'running' AND COALESCE( updated_at, started_at ) < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				current_time( 'mysql' ),
				$cutoff
			)
		);

		return is_numeric( $updated ) ? (int) $updated : 0;
	}

	/**
	 * Fetch a single scan row.
	 *
	 * @param int $scan_id Scan id.
	 * @return array<string, mixed>|null
	 */
	public function find( int $scan_id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id = %d", $scan_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get the most recent completed scan.
	 *
	 * @return array<string, mixed>|null
	 */
	public function latest(): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			"SELECT * FROM {$this->table()} WHERE status = 'complete' ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get recent scans for the dashboard listing.
	 *
	 * @param int $limit Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function recent( int $limit = 10 ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$this->table()} ORDER BY id DESC LIMIT %d", $limit ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get score history points ordered chronologically.
	 *
	 * @param int $limit Maximum points.
	 * @return array<int, array<string, mixed>>
	 */
	public function history( int $limit = 30 ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$this->history_table()} ORDER BY id DESC LIMIT %d", $limit ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_reverse( $rows );
	}
}
