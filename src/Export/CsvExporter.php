<?php
/**
 * CSV report exporter.
 *
 * @package EsmoAccessibilityScanner
 */

declare(strict_types=1);

namespace EsmoAccessibilityScanner\Export;

defined( 'ABSPATH' ) || exit;

/**
 * Streams issue rows as a CSV download.
 */
final class CsvExporter {

	/**
	 * Column headers in output order.
	 *
	 * @var array<int, string>
	 */
	private const COLUMNS = array(
		'id',
		'rule_id',
		'wcag_ref',
		'url',
		'severity',
		'category',
		'message',
		'html_snippet',
		'dom_path',
		'fix_suggestion',
		'doc_link',
		'status',
	);

	/**
	 * Characters that make spreadsheet applications treat a cell as a formula.
	 *
	 * @var array<int, string>
	 */
	private const FORMULA_TRIGGERS = array( '=', '+', '-', '@', "\t", "\r" );

	/**
	 * Build the CSV body for a set of issue rows.
	 *
	 * @param array<int, array<string, mixed>> $issues Issue rows.
	 * @return string CSV content.
	 */
	public function build( array $issues ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- In-memory CSV buffer, not a disk file.
		$stream = fopen( 'php://temp', 'r+' );

		// Pass every fputcsv() argument explicitly: omitting $escape is deprecated since PHP 8.4.
		fputcsv( $stream, self::COLUMNS, ',', '"', '\\' );

		foreach ( $issues as $issue ) {
			$row = array();
			foreach ( self::COLUMNS as $column ) {
				$value = (string) ( $issue[ $column ] ?? '' );
				if ( 'message' === $column || 'fix_suggestion' === $column ) {
					// These keep element names as entities (e.g. "&lt;ul&gt;"); export plain text.
					$value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				}
				$row[] = self::neutralize_formula( $value );
			}
			fputcsv( $stream, $row, ',', '"', '\\' );
		}

		rewind( $stream );
		$content = (string) stream_get_contents( $stream );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Matching php://temp handle from fopen above.
		fclose( $stream );

		return $content;
	}

	/**
	 * Prevent spreadsheet apps from evaluating scanned page content as a formula.
	 *
	 * @param string $value Cell value.
	 */
	public static function neutralize_formula( string $value ): string {
		if ( '' !== $value && in_array( $value[0], self::FORMULA_TRIGGERS, true ) ) {
			return "'" . $value;
		}

		return $value;
	}

	/**
	 * Suggested download filename for a scan.
	 *
	 * @param int $scan_id Scan id.
	 */
	public function filename( int $scan_id ): string {
		return sprintf( 'esmo-accessibility-scanner-scan-%d-%s.csv', $scan_id, gmdate( 'Ymd-His' ) );
	}
}
