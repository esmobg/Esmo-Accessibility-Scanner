<?php
/**
 * Tests for the URL provider post-type resolution.
 *
 * @package EsmoAccessibilityScanner
 */

declare(strict_types=1);

namespace EsmoAccessibilityScanner\Tests;

use EsmoAccessibilityScanner\Scan\UrlProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \EsmoAccessibilityScanner\Scan\UrlProvider
 */
final class UrlProviderTest extends TestCase {

	private UrlProvider $provider;

	protected function setUp(): void {
		$this->provider                    = new UrlProvider();
		$GLOBALS['esmoas_test_options']    = array();
		$GLOBALS['esmoas_test_filters']    = array();
		$GLOBALS['esmoas_test_post_types'] = array( 'post', 'page', 'product' );
	}

	protected function tearDown(): void {
		$GLOBALS['esmoas_test_filters'] = array();
	}

	public function test_returns_all_public_types_when_unconfigured(): void {
		$types = $this->provider->scannable_post_types();

		$this->assertContains( 'post', $types );
		$this->assertContains( 'page', $types );
		$this->assertContains( 'product', $types );
	}

	public function test_respects_configured_post_types(): void {
		$GLOBALS['esmoas_test_options']['esmoas_settings'] = array(
			'include_post_types' => array( 'page', 'product' ),
		);

		$types = $this->provider->scannable_post_types();

		$this->assertEqualsCanonicalizing( array( 'page', 'product' ), $types );
		$this->assertNotContains( 'post', $types );
	}

	public function test_ignores_configured_types_that_are_not_public(): void {
		$GLOBALS['esmoas_test_options']['esmoas_settings'] = array(
			'include_post_types' => array( 'page', 'nonexistent' ),
		);

		$types = $this->provider->scannable_post_types();

		$this->assertSame( array( 'page' ), $types );
	}

	public function test_resolve_url_limit_defaults_to_one_thousand(): void {
		$this->assertSame( 1000, UrlProvider::resolve_url_limit() );
	}

	public function test_full_site_urls_are_sliced_to_the_requested_limit(): void {
		$GLOBALS['esmoas_test_post_ids']                   = array( 11, 12, 13, 14, 15 );
		$GLOBALS['esmoas_test_options']['esmoas_settings'] = array(
			'include_post_types' => array( 'post' ),
			'include_terms'      => false,
		);

		$urls = $this->provider->get_full_site_urls( 2 );

		$this->assertCount( 2, $urls );
		$this->assertSame( 'https://example.test/', $urls[0]['url'] );
	}

	/**
	 * @dataProvider site_urls
	 */
	public function test_is_site_url( string $url, bool $expected ): void {
		$this->assertSame( $expected, UrlProvider::is_site_url( $url ) );
	}

	/**
	 * @return array<string, array{0:string,1:bool}>
	 */
	public static function site_urls(): array {
		return array(
			'home'             => array( 'https://example.test/', true ),
			'page'             => array( 'https://example.test/about/?a=1', true ),
			'http on the host' => array( 'http://example.test/x/', true ),
			'upper-case host'  => array( 'https://EXAMPLE.test/', true ),
			'other host'       => array( 'https://evil.example/', false ),
			'subdomain'        => array( 'https://shop.example.test/', false ),
			'other port'       => array( 'https://example.test:8080/', false ),
			'javascript'       => array( 'javascript:alert(1)', false ),
			'protocol-less'    => array( '//example.test/', false ),
			'relative'         => array( '/about/', false ),
		);
	}

	public function test_filtered_queue_drops_foreign_and_unsafe_urls(): void {
		$GLOBALS['esmoas_test_post_types']                  = array();
		$GLOBALS['esmoas_test_filters']['esmoas_scan_urls'] = static fn ( array $urls ): array => array_merge(
			$urls,
			array(
				array( 'url' => 'https://evil.example/', 'post_id' => 0, 'label' => 'Elsewhere' ),
				array( 'url' => 'javascript:alert(1)', 'post_id' => 0, 'label' => 'Script' ),
				array( 'url' => 'https://example.test/landing/', 'post_id' => '7', 'label' => '<b>Landing</b> &amp; more' ),
				array( 'url' => array( 'https://example.test/' ) ),
				'junk',
			)
		);

		$urls = $this->provider->get_full_site_urls( 10 );

		$this->assertSame( array( 'https://example.test/', 'https://example.test/landing/' ), array_column( $urls, 'url' ) );
		$this->assertSame( 7, $urls[1]['post_id'] );
		$this->assertSame( 'Landing & more', $urls[1]['label'] );
	}
}
