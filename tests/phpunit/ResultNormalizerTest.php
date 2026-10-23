<?php
/**
 * Tests for the result normalizer.
 *
 * @package EsmoAccessibilityScanner
 */

declare(strict_types=1);

namespace EsmoAccessibilityScanner\Tests;

use EsmoAccessibilityScanner\Rules\RuleCatalog;
use EsmoAccessibilityScanner\Scan\ResultNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \EsmoAccessibilityScanner\Scan\ResultNormalizer
 */
final class ResultNormalizerTest extends TestCase {

	private ResultNormalizer $normalizer;

	protected function setUp(): void {
		$this->normalizer = new ResultNormalizer( new RuleCatalog() );
	}

	public function test_violation_is_normalized_to_issue_row(): void {
		$payload = array(
			'url'        => 'https://example.com/page',
			'post_id'    => 12,
			'violations' => array(
				array(
					'id'     => 'image-alt',
					'impact' => 'critical',
					'help'   => 'Images must have alternate text',
					'nodes'  => array(
						array(
							'html'           => '<img src="banner.jpg">',
							'target'         => array( 'img.banner' ),
							'failureSummary' => 'Fix this',
						),
					),
				),
			),
		);

		$issues = $this->normalizer->normalize( $payload );

		$this->assertCount( 1, $issues );
		$issue = $issues[0];

		$this->assertSame( 'image-alt', $issue['rule_id'] );
		$this->assertSame( 'critical', $issue['severity'] );
		$this->assertSame( 'images', $issue['category'] );
		$this->assertSame( 12, $issue['post_id'] );
		$this->assertStringContainsString( '1.1.1', $issue['wcag_ref'] );
		$this->assertSame( 'img.banner', $issue['dom_path'] );
		$this->assertSame( 'open', $issue['status'] );
	}

	public function test_incomplete_results_are_marked_as_warning(): void {
		$payload = array(
			'url'        => 'https://example.com/',
			'post_id'    => 0,
			'incomplete' => array(
				array(
					'id'     => 'color-contrast',
					'impact' => 'serious',
					'help'   => 'Elements must meet contrast ratio',
					'nodes'  => array(
						array(
							'html'   => '<span>Hi</span>',
							'target' => array( 'span' ),
						),
					),
				),
			),
		);

		$issues = $this->normalizer->normalize( $payload );

		$this->assertCount( 1, $issues );
		$this->assertSame( 'warning', $issues[0]['severity'] );
		$this->assertStringContainsString( 'Needs manual review', $issues[0]['message'] );
	}

	public function test_unknown_rule_falls_back_to_impact_severity(): void {
		$payload = array(
			'url'        => 'https://example.com/',
			'violations' => array(
				array(
					'id'     => 'some-future-rule',
					'impact' => 'serious',
					'help'   => 'Future rule',
					'nodes'  => array(
						array(
							'html'   => '<div></div>',
							'target' => array( 'div' ),
						),
					),
				),
			),
		);

		$issues = $this->normalizer->normalize( $payload );

		$this->assertSame( 'major', $issues[0]['severity'] );
	}

	public function test_long_multibyte_snippet_is_truncated_to_valid_utf8(): void {
		// 3000 Cyrillic characters = 6000 bytes; must be cut on a character
		// boundary so MySQL strict mode does not reject the row.
		$snippet = '<p>' . str_repeat( 'я', 3000 ) . '</p>';

		$payload = array(
			'url'        => 'https://example.com/',
			'violations' => array(
				array(
					'id'     => 'image-alt',
					'impact' => 'critical',
					'help'   => 'Help',
					'nodes'  => array(
						array(
							'html'   => $snippet,
							'target' => array( 'p' ),
						),
					),
				),
			),
		);

		$issues = $this->normalizer->normalize( $payload );
		$stored = $issues[0]['html_snippet'];

		$this->assertSame( 2003, mb_strlen( $stored, 'UTF-8' ) ); // 2000 chars + '...'.
		$this->assertTrue( (bool) mb_check_encoding( $stored, 'UTF-8' ) );
		$this->assertStringEndsWith( 'я...', $stored );
	}

	public function test_best_practice_violations_are_reported_as_warnings(): void {
		$payload = array(
			'url'        => 'https://example.com/',
			'post_id'    => 0,
			'violations' => array(
				array(
					'id'           => 'heading-order',
					'impact'       => 'moderate',
					'help'         => 'Heading levels should only increase by one',
					'bestPractice' => true,
					'nodes'        => array(
						array(
							'html'   => '<h4>Skipped</h4>',
							'target' => array( 'h4' ),
						),
					),
				),
				array(
					'id'     => 'image-alt',
					'impact' => 'critical',
					'help'   => 'Images must have alternate text',
					'nodes'  => array(
						array(
							'html'   => '<img src="a.jpg">',
							'target' => array( 'img' ),
						),
					),
				),
			),
		);

		$issues = $this->normalizer->normalize( $payload );

		$this->assertCount( 2, $issues );
		$this->assertSame( 'heading-order', $issues[0]['rule_id'] );
		$this->assertSame( 'warning', $issues[0]['severity'] );
		$this->assertSame( 'headings', $issues[0]['category'] );
		$this->assertSame( 'critical', $issues[1]['severity'] );
	}

	public function test_messages_keep_element_names(): void {
		$payload = array(
			'url'        => 'https://example.com/',
			'violations' => array(
				array(
					'id'     => 'list',
					'impact' => 'serious',
					'help'   => '<ul> and <ol> must only directly contain <li>, <script> or <template> elements',
					'nodes'  => array( array( 'target' => array( 'ul' ) ) ),
				),
			),
		);

		$issues = $this->normalizer->normalize( $payload );

		$this->assertSame(
			'&lt;ul&gt; and &lt;ol&gt; must only directly contain &lt;li&gt;, &lt;script&gt; or &lt;template&gt; elements',
			$issues[0]['message']
		);
	}

	public function test_rows_per_page_are_capped(): void {
		$violations = array();
		for ( $i = 0; $i < 300; $i++ ) {
			$violations[] = array(
				'id'     => 'image-alt',
				'impact' => 'critical',
				'nodes'  => array_fill( 0, 40, array( 'target' => array( 'img' ) ) ),
			);
		}

		$issues = $this->normalizer->normalize(
			array(
				'url'        => 'https://example.com/',
				'violations' => $violations,
			)
		);

		$this->assertCount( ResultNormalizer::MAX_ROWS, $issues );
	}

	public function test_results_and_nodes_are_capped(): void {
		$issues = $this->normalizer->normalize(
			array(
				'url'        => 'https://example.com/',
				'violations' => array(
					array(
						'id'    => 'image-alt',
						'nodes' => array_fill( 0, 100, array( 'target' => array( 'img' ) ) ),
					),
				),
			)
		);

		$this->assertCount( ResultNormalizer::MAX_NODES, $issues );
	}

	public function test_malformed_results_are_skipped_and_values_checked(): void {
		$issues = $this->normalizer->normalize(
			array(
				'url'        => 'https://example.com/',
				'post_id'    => array( 5 ),
				'violations' => array(
					array( 'id' => array( 'image-alt' ) ),
					array( 'id' => '' ),
					'not-a-result',
					array(
						'id'           => 'color-contrast',
						'impact'       => 'catastrophic-and-much-longer-than-twenty',
						'help'         => array( 'x' ),
						'bestPractice' => 'yes',
						'nodes'        => array(
							array(
								'html'           => array( 'x' ),
								'target'         => array( array( 'my-widget', array( 'nested' ) ), 'button' ),
								'failureSummary' => array(),
							),
						),
					),
				),
			)
		);

		$this->assertCount( 1, $issues );
		$this->assertSame( 'color-contrast', $issues[0]['rule_id'] );
		$this->assertSame( '', $issues[0]['impact'] );
		$this->assertSame( 0, $issues[0]['post_id'] );
		$this->assertSame( '', $issues[0]['html_snippet'] );
		$this->assertSame( 'my-widget > button', $issues[0]['dom_path'] );
		$this->assertNotSame( 'warning', $issues[0]['severity'], 'Only a real boolean marks a best-practice rule.' );
	}

	public function test_severity_counts_aggregate_correctly(): void {
		$issues = array(
			array( 'severity' => 'critical' ),
			array( 'severity' => 'critical' ),
			array( 'severity' => 'minor' ),
			array( 'severity' => 'warning' ),
		);

		$counts = $this->normalizer->severity_counts( $issues );

		$this->assertSame( 2, $counts['critical'] );
		$this->assertSame( 0, $counts['major'] );
		$this->assertSame( 1, $counts['minor'] );
		$this->assertSame( 1, $counts['warning'] );
	}
}
