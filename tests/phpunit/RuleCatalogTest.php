<?php
/**
 * Tests for the WCAG rule catalog.
 *
 * @package EsmoAccessibilityScanner
 */

declare(strict_types=1);

namespace EsmoAccessibilityScanner\Tests;

use EsmoAccessibilityScanner\Rules\RuleCatalog;
use PHPUnit\Framework\TestCase;

/**
 * @covers \EsmoAccessibilityScanner\Rules\RuleCatalog
 */
final class RuleCatalogTest extends TestCase {

	public function test_axe_serious_impact_maps_to_major_severity(): void {
		$catalog = new RuleCatalog();

		$this->assertSame( 'major', $catalog->severity_from_impact( 'serious' ) );
	}

	public function test_catalog_entries_do_not_use_axe_serious_as_severity(): void {
		$catalog = new RuleCatalog();

		foreach ( $catalog->all() as $rule_id => $meta ) {
			$this->assertNotSame(
				'serious',
				$meta['severity'],
				sprintf( 'Rule %s still uses axe impact "serious" as plugin severity.', $rule_id )
			);
			$this->assertContains( $meta['severity'], array( 'critical', 'major', 'minor', 'warning' ) );
		}
	}

	public function test_known_serious_axe_rule_is_major(): void {
		$catalog = new RuleCatalog();
		$meta    = $catalog->get( 'html-has-lang' );

		$this->assertSame( 'major', $meta['severity'] );
		$this->assertStringContainsString( '3.1.1', $meta['wcag'] );
	}
}
