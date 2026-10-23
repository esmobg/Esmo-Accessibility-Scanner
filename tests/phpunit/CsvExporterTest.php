<?php
/**
 * Tests for the CSV exporter.
 *
 * @package EsmoAccessibilityScanner
 */

declare(strict_types=1);

namespace EsmoAccessibilityScanner\Tests;

use EsmoAccessibilityScanner\Export\CsvExporter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \EsmoAccessibilityScanner\Export\CsvExporter
 */
final class CsvExporterTest extends TestCase {

	public function test_build_writes_header_and_rows(): void {
		$csv = ( new CsvExporter() )->build(
			array(
				array(
					'id'           => 7,
					'rule_id'      => 'image-alt',
					'url'          => 'https://example.test/',
					'severity'     => 'critical',
					'message'      => 'Images must have "alternate" text, too',
					'html_snippet' => '<img src="a.jpg">',
				),
			)
		);

		$lines = array_values( array_filter( explode( "\n", $csv ) ) );

		$this->assertCount( 2, $lines );
		$this->assertStringStartsWith( 'id,rule_id,wcag_ref,url,severity', $lines[0] );

		$row = str_getcsv( $lines[1], ',', '"', '\\' );
		$this->assertSame( '7', $row[0] );
		$this->assertSame( 'image-alt', $row[1] );
		$this->assertSame( 'Images must have "alternate" text, too', $row[6] );
		$this->assertSame( '<img src="a.jpg">', $row[7] );
	}

	/**
	 * @dataProvider formula_values
	 */
	public function test_formula_like_values_are_neutralized( string $value ): void {
		$this->assertSame( "'" . $value, CsvExporter::neutralize_formula( $value ) );
	}

	/**
	 * @return array<string, array{0:string}>
	 */
	public static function formula_values(): array {
		return array(
			'equals' => array( '=HYPERLINK("https://evil.test","x")' ),
			'plus'   => array( '+1+2' ),
			'minus'  => array( '-cmd' ),
			'at'     => array( '@SUM(A1:A2)' ),
			'tab'    => array( "\t=1" ),
		);
	}

	public function test_message_entities_are_exported_as_text(): void {
		$csv  = ( new CsvExporter() )->build( array( array( 'message' => '&lt;ul&gt; must only contain &lt;li&gt;' ) ) );
		$rows = array_values( array_filter( explode( "\n", $csv ) ) );
		$row  = str_getcsv( $rows[1], ',', '"', '\\' );

		$this->assertSame( '<ul> must only contain <li>', $row[6] );
	}

	public function test_regular_values_are_unchanged(): void {
		$this->assertSame( 'link-name', CsvExporter::neutralize_formula( 'link-name' ) );
		$this->assertSame( '', CsvExporter::neutralize_formula( '' ) );
	}
}
