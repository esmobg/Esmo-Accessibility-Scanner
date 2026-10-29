<?php
/**
 * Tests for the score calculator.
 *
 * @package EsmoAccessibilityScanner
 */

declare(strict_types=1);

namespace EsmoAccessibilityScanner\Tests;

use EsmoAccessibilityScanner\Scan\ScoreCalculator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \EsmoAccessibilityScanner\Scan\ScoreCalculator
 */
final class ScoreCalculatorTest extends TestCase {

	private ScoreCalculator $calculator;

	protected function setUp(): void {
		$this->calculator = new ScoreCalculator();
	}

	public function test_perfect_score_with_no_issues(): void {
		$score = $this->calculator->calculate(
			array(
				'critical' => 0,
				'major'    => 0,
				'minor'    => 0,
				'warning'  => 0,
			)
		);

		$this->assertSame( 100, $score );
	}

	public function test_weighted_penalties_are_applied(): void {
		// 1 critical (-10) + 2 major (-10) + 3 minor (-6) + 4 warning (-4) = -30.
		$score = $this->calculator->calculate(
			array(
				'critical' => 1,
				'major'    => 2,
				'minor'    => 3,
				'warning'  => 4,
			)
		);

		$this->assertSame( 70, $score );
	}

	public function test_needs_review_items_cost_nothing(): void {
		$score = $this->calculator->calculate(
			array(
				'major'  => 1,
				'review' => 40,
			)
		);

		$this->assertSame( 95, $score );
		$this->assertSame( 0, $this->calculator->warning_count( array( 'review' => 40 ) ) );
	}

	public function test_score_is_clamped_to_zero(): void {
		$score = $this->calculator->calculate( array( 'critical' => 50 ) );

		$this->assertSame( 0, $score );
	}

	public function test_error_and_warning_counts(): void {
		$counts = array(
			'critical' => 2,
			'major'    => 3,
			'minor'    => 4,
			'warning'  => 5,
		);

		$this->assertSame( 5, $this->calculator->error_count( $counts ) );
		$this->assertSame( 9, $this->calculator->warning_count( $counts ) );
	}

	public function test_site_score_averages_per_page(): void {
		$counts_by_url = array();

		for ( $i = 0; $i < 10; $i++ ) {
			$counts_by_url[ 'https://example.test/page-' . $i ] = array(
				'critical' => 0,
				'major'    => 1,
				'minor'    => 0,
				'warning'  => 0,
			);
		}

		$this->assertSame( 95, $this->calculator->calculate_site_score( $counts_by_url, 10 ) );
	}

	public function test_site_score_counts_clean_pages_as_perfect(): void {
		$counts_by_url = array(
			'https://example.test/bad' => array(
				'critical' => 5,
				'major'    => 0,
				'minor'    => 0,
				'warning'  => 0,
			),
		);

		// One page at 50, nine clean pages at 100 => (50 + 900) / 10 = 95.
		$this->assertSame( 95, $this->calculator->calculate_site_score( $counts_by_url, 10 ) );
	}

	public function test_site_score_with_mixed_pages_and_clean_urls(): void {
		$counts_by_url = array(
			'https://example.test/a' => array(
				'critical' => 0,
				'major'    => 2,
				'minor'    => 0,
				'warning'  => 0,
			),
			'https://example.test/b' => array(
				'critical' => 0,
				'major'    => 0,
				'minor'    => 1,
				'warning'  => 0,
			),
		);

		// Page scores: 90 and 98. Three clean pages at 100 => (90 + 98 + 300) / 5 = 97.6 => 98.
		$this->assertSame( 98, $this->calculator->calculate_site_score( $counts_by_url, 5 ) );
	}

	public function test_site_score_with_zero_scanned_urls_returns_perfect(): void {
		$this->assertSame( 100, $this->calculator->calculate_site_score( array(), 0 ) );
	}

	public function test_single_page_site_score_matches_calculate(): void {
		$counts = array(
			'critical' => 0,
			'major'    => 1,
			'minor'    => 0,
			'warning'  => 0,
		);

		$by_url = array( 'https://example.test/' => $counts );

		$this->assertSame(
			$this->calculator->calculate( $counts ),
			$this->calculator->calculate_site_score( $by_url, 1 )
		);
	}

	/**
	 * @dataProvider band_provider
	 */
	public function test_band_thresholds( int $score, string $expected_key ): void {
		$this->assertSame( $expected_key, $this->calculator->band( $score )['key'] );
	}

	/**
	 * @return array<string, array{0:int,1:string}>
	 */
	public static function band_provider(): array {
		return array(
			'excellent'         => array( 96, 'excellent' ),
			'excellent edge'    => array( 95, 'excellent' ),
			'good'              => array( 80, 'good' ),
			'needs improvement' => array( 60, 'needs-improvement' ),
			'poor'              => array( 59, 'poor' ),
		);
	}
}
