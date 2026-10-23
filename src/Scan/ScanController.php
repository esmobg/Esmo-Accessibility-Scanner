<?php
/**
 * AJAX endpoints that orchestrate scanning from the browser.
 *
 * @package EsmoAccessibilityScanner
 */

declare(strict_types=1);

namespace EsmoAccessibilityScanner\Scan;

use EsmoAccessibilityScanner\Activation\Installer;
use EsmoAccessibilityScanner\Plugin;
use EsmoAccessibilityScanner\Storage\IssueRepository;
use EsmoAccessibilityScanner\Storage\ScanRepository;

defined( 'ABSPATH' ) || exit;

// Nonce is verified in guard() via check_ajax_referer( 'esmoas_scan' ) before any request read.
// phpcs:disable WordPress.Security.NonceVerification.Missing
// phpcs:disable WordPress.Security.NonceVerification.Recommended

/**
 * Handles start/save/finish AJAX requests for scans.
 */
final class ScanController {

	/**
	 * Nonce action used by every scan AJAX request.
	 */
	public const NONCE_ACTION = 'esmoas_scan';

	/**
	 * URL provider.
	 */
	private UrlProvider $url_provider;

	/**
	 * Scan repository.
	 */
	private ScanRepository $scans;

	/**
	 * Issue repository.
	 */
	private IssueRepository $issues;

	/**
	 * Result normalizer.
	 */
	private ResultNormalizer $normalizer;

	/**
	 * Score calculator.
	 */
	private ScoreCalculator $score;

	/**
	 * Constructor.
	 *
	 * @param UrlProvider      $url_provider URL provider.
	 * @param ScanRepository   $scans        Scan repository.
	 * @param IssueRepository  $issues       Issue repository.
	 * @param ResultNormalizer $normalizer   Result normalizer.
	 * @param ScoreCalculator  $score        Score calculator.
	 */
	public function __construct(
		UrlProvider $url_provider,
		ScanRepository $scans,
		IssueRepository $issues,
		ResultNormalizer $normalizer,
		ScoreCalculator $score
	) {
		$this->url_provider = $url_provider;
		$this->scans        = $scans;
		$this->issues       = $issues;
		$this->normalizer   = $normalizer;
		$this->score        = $score;
	}

	/**
	 * Register AJAX hooks.
	 */
	public function register(): void {
		add_action( 'wp_ajax_esmoas_start_scan', array( $this, 'handle_start' ) );
		add_action( 'wp_ajax_esmoas_save_results', array( $this, 'handle_save' ) );
		add_action( 'wp_ajax_esmoas_finish_scan', array( $this, 'handle_finish' ) );
	}

	/**
	 * Start a scan: build the URL queue and create the scan record.
	 */
	public function handle_start(): void {
		$this->guard();

		// New scans wait until the data of Accessibility Guardian is imported, so new rows never
		// take ids that imported rows still need. Starting a scan retries a pending import at once.
		if ( Installer::legacy_import_pending() ) {
			Installer::import_legacy_data();

			if ( Installer::legacy_import_pending() ) {
				wp_send_json_error(
					array( 'message' => __( 'The scans of Accessibility Guardian are still being imported. Please try again in a few minutes.', 'esmo-accessibility-scanner' ) ),
					503
				);
			}
		}

		// Recover from scans abandoned mid-run (e.g. the admin closed the tab).
		$this->scans->fail_stale();

		$scan_type = isset( $_POST['scan_type'] ) ? sanitize_key( wp_unslash( $_POST['scan_type'] ) ) : 'full';
		$post_id   = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;

		if ( 'single' === $scan_type && $post_id > 0 ) {
			$queue = $this->url_provider->get_single_post_urls( $post_id );
		} else {
			$scan_type = 'full';
			$queue     = $this->url_provider->get_full_site_urls( UrlProvider::resolve_url_limit() );
		}

		if ( empty( $queue ) ) {
			wp_send_json_error(
				array( 'message' => __( 'No scannable URLs were found.', 'esmo-accessibility-scanner' ) ),
				400
			);
		}

		$scan_id = $this->scans->create( $scan_type, count( $queue ) );

		if ( 0 === $scan_id ) {
			wp_send_json_error(
				array( 'message' => __( 'Unable to create a scan record.', 'esmo-accessibility-scanner' ) ),
				500
			);
		}

		$this->scans->queue_pages( $scan_id, $queue );

		wp_send_json_success(
			array(
				'scan_id' => $scan_id,
				'queue'   => array_map(
					static fn ( array $entry ): array => array(
						'url'     => $entry['url'],
						'post_id' => $entry['post_id'],
						'label'   => $entry['label'],
					),
					$queue
				),
			)
		);
	}

	/**
	 * Save the results for a single scanned URL.
	 */
	public function handle_save(): void {
		$this->guard();

		$scan_id = isset( $_POST['scan_id'] ) ? absint( wp_unslash( $_POST['scan_id'] ) ) : 0;
		$raw     = isset( $_POST['payload'] ) && is_string( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload is decoded and type-checked below.

		if ( 0 === $scan_id || '' === $raw ) {
			wp_send_json_error( array( 'message' => __( 'Invalid scan payload.', 'esmo-accessibility-scanner' ) ), 400 );
		}

		if ( strlen( $raw ) > 524288 ) {
			wp_send_json_error( array( 'message' => __( 'Scan payload is too large.', 'esmo-accessibility-scanner' ) ), 413 );
		}

		$scan = $this->scans->find( $scan_id );

		if ( null === $scan || 'running' !== $scan['status'] ) {
			wp_send_json_error( array( 'message' => __( 'Scan is not running.', 'esmo-accessibility-scanner' ) ), 409 );
		}

		$payload = json_decode( $raw, true );

		if ( ! is_array( $payload ) ) {
			wp_send_json_error( array( 'message' => __( 'Malformed scan payload.', 'esmo-accessibility-scanner' ) ), 400 );
		}

		if ( ! isset( $payload['url'] ) || ! is_string( $payload['url'] ) || ! UrlProvider::is_site_url( $payload['url'] ) ) {
			wp_send_json_error( array( 'message' => __( 'The scanned page is not on this site.', 'esmo-accessibility-scanner' ) ), 400 );
		}

		// Only pages this scan queued, each once, and none once the scan has finished.
		$post_id = $this->scans->claim_page( $scan_id, $payload['url'] );
		if ( null === $post_id ) {
			wp_send_json_error( array( 'message' => __( 'This page is not waiting for results in this scan.', 'esmo-accessibility-scanner' ) ), 409 );
		}
		$payload['post_id'] = $post_id;

		$issues   = $this->normalizer->normalize( $payload );
		$inserted = $this->issues->insert_batch( $scan_id, $issues );

		wp_send_json_success(
			array(
				'inserted' => $inserted,
				'counts'   => $this->normalizer->severity_counts( $issues ),
			)
		);
	}

	/**
	 * Finalize a scan and compute the aggregate score.
	 */
	public function handle_finish(): void {
		$this->guard();

		$scan_id   = isset( $_POST['scan_id'] ) ? absint( wp_unslash( $_POST['scan_id'] ) ) : 0;
		// Sent by the browser; clamped to fit the INT column.
		$passes    = isset( $_POST['passes'] ) ? min( absint( wp_unslash( $_POST['passes'] ) ), 1000000000 ) : 0;
		$cancelled = ! empty( $_POST['cancelled'] );

		$scan = $this->scans->find( $scan_id );
		if ( 0 === $scan_id || null === $scan ) {
			wp_send_json_error( array( 'message' => __( 'Invalid scan id.', 'esmo-accessibility-scanner' ) ), 400 );
		}

		if ( 'running' !== $scan['status'] ) {
			$this->send_finished_scan( $scan_id );
		}

		$counts_by_url = $this->issues->severity_counts_by_url( $scan_id );
		$scanned_urls  = (int) $scan['scanned_urls'];
		$counts        = $this->issues->severity_counts( $scan_id );
		$score         = $this->score->calculate_site_score( $counts_by_url, $scanned_urls );

		$totals = array(
			'score'    => $score,
			'errors'   => $this->score->error_count( $counts ),
			'warnings' => $this->score->warning_count( $counts ),
			'passes'   => $passes,
		);

		// A cancelled scan keeps its partial results but never becomes the "latest" report.
		$finished = $cancelled ? $this->scans->cancel( $scan_id, $totals ) : $this->scans->complete( $scan_id, $totals );

		if ( ! $finished ) {
			// Another request finished this scan first; report what it stored.
			$this->send_finished_scan( $scan_id );
		}

		if ( ! $cancelled ) {
			/**
			 * Fires after a scan has finished and its score has been stored.
			 *
			 * @since 1.2.0
			 *
			 * @param int                                                 $scan_id Scan id.
			 * @param array{score:int,errors:int,warnings:int,passes:int} $totals  Aggregate results.
			 */
			Plugin::action( 'esmoas_scan_completed', $scan_id, $totals );
		}

		wp_send_json_success(
			array(
				'score'  => $score,
				'band'   => $this->score->band( $score ),
				'totals' => $totals,
				'counts' => $counts,
			)
		);
	}

	/**
	 * Send the stored result of a scan that is already complete or cancelled.
	 *
	 * @param int $scan_id Scan id.
	 */
	private function send_finished_scan( int $scan_id ): void {
		$scan = $this->scans->find( $scan_id );

		if ( null === $scan || ! in_array( $scan['status'], array( 'complete', 'cancelled' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Scan is not running.', 'esmo-accessibility-scanner' ) ), 409 );
		}

		wp_send_json_success(
			array(
				'score'  => (int) $scan['score'],
				'band'   => $this->score->band( (int) $scan['score'] ),
				'totals' => array(
					'score'    => (int) $scan['score'],
					'errors'   => (int) $scan['errors'],
					'warnings' => (int) $scan['warnings'],
					'passes'   => (int) $scan['passes'],
				),
				'counts' => $this->issues->severity_counts( $scan_id ),
			)
		);
	}

	/**
	 * Verify nonce and capability for every request.
	 */
	private function guard(): void {
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to run scans.', 'esmo-accessibility-scanner' ) ), 403 );
		}

		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
	}
}
