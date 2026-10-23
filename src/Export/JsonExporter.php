<?php
/**
 * JSON report exporter.
 *
 * @package EsmoAccessibilityScanner
 */

declare(strict_types=1);

namespace EsmoAccessibilityScanner\Export;

defined( 'ABSPATH' ) || exit;

/**
 * Builds a structured JSON report payload.
 */
final class JsonExporter {

	/**
	 * Build the JSON body for a scan and its issues.
	 *
	 * @param array<string, mixed>             $scan   Scan record.
	 * @param array<int, array<string, mixed>> $issues Issue rows.
	 * @return string JSON content.
	 */
	public function build( array $scan, array $issues ): string {
		$payload = array(
			'generator' => 'Esmo Accessibility Scanner',
			'version'   => ESMOAS_VERSION,
			'generated' => gmdate( 'c' ),
			'scan'      => array(
				'id'           => (int) ( $scan['id'] ?? 0 ),
				'type'         => (string) ( $scan['scan_type'] ?? '' ),
				'score'        => (int) ( $scan['score'] ?? 0 ),
				'errors'       => (int) ( $scan['errors'] ?? 0 ),
				'warnings'     => (int) ( $scan['warnings'] ?? 0 ),
				'passes'       => (int) ( $scan['passes'] ?? 0 ),
				'total_urls'   => (int) ( $scan['total_urls'] ?? 0 ),
				'started_at'   => (string) ( $scan['started_at'] ?? '' ),
				'finished_at'  => (string) ( $scan['finished_at'] ?? '' ),
			),
			'issues'    => array_map(
				static fn ( array $issue ): array => array(
					'id'             => (int) ( $issue['id'] ?? 0 ),
					'rule_id'        => (string) ( $issue['rule_id'] ?? '' ),
					'wcag_ref'       => (string) ( $issue['wcag_ref'] ?? '' ),
					'url'            => (string) ( $issue['url'] ?? '' ),
					'severity'       => (string) ( $issue['severity'] ?? '' ),
					'category'       => (string) ( $issue['category'] ?? '' ),
					'message'        => html_entity_decode( (string) ( $issue['message'] ?? '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
					'html_snippet'   => (string) ( $issue['html_snippet'] ?? '' ),
					'dom_path'       => (string) ( $issue['dom_path'] ?? '' ),
					'fix_suggestion' => html_entity_decode( (string) ( $issue['fix_suggestion'] ?? '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
					'doc_link'       => (string) ( $issue['doc_link'] ?? '' ),
					'status'         => (string) ( $issue['status'] ?? '' ),
				),
				$issues
			),
		);

		return (string) wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Suggested download filename for a scan.
	 *
	 * @param int $scan_id Scan id.
	 */
	public function filename( int $scan_id ): string {
		return sprintf( 'esmo-accessibility-scanner-scan-%d-%s.json', $scan_id, gmdate( 'Ymd-His' ) );
	}
}
