<?php
/**
 * Standalone PHPUnit bootstrap.
 *
 * Provides minimal WordPress function stubs so the plugin's pure logic
 * classes can be unit tested without a full WordPress install.
 *
 * @package EsmoAccessibilityScanner
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! defined( 'ESMOAS_VERSION' ) ) {
	define( 'ESMOAS_VERSION', 'test' );
}

// PSR-4 autoloader for the plugin source.
spl_autoload_register(
	static function ( string $class ): void {
		$prefix   = 'EsmoAccessibilityScanner\\';
		$base_dir = dirname( __DIR__, 2 ) . '/src/';
		$len      = strlen( $prefix );

		if ( 0 !== strncmp( $prefix, $class, $len ) ) {
			return;
		}

		$relative = substr( $class, $len );
		$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

if ( ! function_exists( '__' ) ) {
	/**
	 * Stubbed translation function.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain (ignored).
	 */
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( string $url ): string {
		return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ): int {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( string $key ): string {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ) ?? '';
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $value ): string {
		return trim( preg_replace( '/[\r\n\t ]+/', ' ', wp_strip_all_tags( $value ) ) ?? '' );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( string $value ): string {
		return trim( wp_strip_all_tags( $value ) );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( string $value ): string {
		return $value;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( string $value ): string {
		return trim( (string) preg_replace( '/<[^>]*>/', '', $value ) );
	}
}

$GLOBALS['esmoas_test_options']    = array();
$GLOBALS['esmoas_test_post_types'] = array( 'post', 'page' );

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $name, $default = false ) {
		return $GLOBALS['esmoas_test_options'][ $name ] ?? $default;
	}
}

if ( ! function_exists( 'get_post_types' ) ) {
	function get_post_types( array $args = array(), string $output = 'names' ): array {
		$types = array();
		foreach ( $GLOBALS['esmoas_test_post_types'] as $type ) {
			$types[ $type ] = $type;
		}
		return $types;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook, $value, ...$args ) {
		$callback = $GLOBALS['esmoas_test_filters'][ $hook ] ?? null;

		return null !== $callback ? $callback( $value, ...$args ) : $value;
	}
}

if ( ! function_exists( 'has_filter' ) ) {
	function has_filter( string $hook ): bool {
		return false;
	}
}

if ( ! function_exists( 'has_action' ) ) {
	function has_action( string $hook ): bool {
		return false;
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( string $url, int $component = -1 ) {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( string $path = '/' ): string {
		return 'https://example.test' . $path;
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( int $post_id ) {
		return 'https://example.test/?p=' . $post_id;
	}
}

if ( ! function_exists( 'get_the_title' ) ) {
	function get_the_title( int $post_id ): string {
		return 'Post ' . $post_id;
	}
}

if ( ! function_exists( 'get_taxonomies' ) ) {
	function get_taxonomies( array $args = array(), string $output = 'names' ): array {
		return array();
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ): bool {
		return false;
	}
}

if ( ! class_exists( 'WP_Query' ) ) {
	class WP_Query {
		/** @var array<int, int> */
		public $posts = array();

		/**
		 * @param array<string, mixed> $args Query args.
		 */
		public function __construct( array $args = array() ) {
			$limit = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : 0;
			$ids   = $GLOBALS['esmoas_test_post_ids'] ?? array( 11, 12, 13, 14, 15 );
			$this->posts = $limit > 0 ? array_slice( $ids, 0, $limit ) : $ids;
		}
	}
}
