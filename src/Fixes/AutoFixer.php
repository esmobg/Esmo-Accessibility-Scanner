<?php
/**
 * Applies optional one-click accessibility fixes on the front end.
 *
 * @package EsmoAccessibilityScanner
 */

declare(strict_types=1);

namespace EsmoAccessibilityScanner\Fixes;

use EsmoAccessibilityScanner\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end remediation layer. Each fix is opt-in via plugin settings and is
 * applied through WordPress hooks rather than by modifying theme files.
 */
final class AutoFixer {

	/**
	 * Fixes implemented by assets/js/frontend-fixes.js (the rest are CSS or PHP only).
	 *
	 * @var array<int, string>
	 */
	private const JS_FIXES = array(
		'add_html_lang',
		'new_window_warning',
		'fix_viewport',
		'remove_positive_tabindex',
		'remove_title_attr',
	);

	/**
	 * Enabled fix flags keyed by fix id.
	 *
	 * @var array<string, bool>
	 */
	private array $fixes;

	/**
	 * Constructor: read the enabled fixes from settings.
	 */
	public function __construct() {
		$settings    = (array) get_option( 'esmoas_settings', array() );
		$raw         = isset( $settings['fixes'] ) && is_array( $settings['fixes'] ) ? $settings['fixes'] : array();
		$this->fixes = array_map( static fn ( $v ): bool => (bool) $v, $raw );
	}

	/**
	 * Register front-end hooks for the enabled fixes.
	 */
	public function register(): void {
		if ( is_admin() ) {
			return;
		}

		// Accessibility Guardian (1.2.0 and earlier) is this plugin under its old name. While it
		// still runs (for example network-active, or reactivated), it applies the same fixes;
		// applying them twice would add duplicate skip links and notices.
		if ( defined( 'ACCG_VERSION' ) ) {
			return;
		}

		if ( ! $this->any_enabled() ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'body_class', array( $this, 'add_body_classes' ) );

		if ( $this->on( 'add_skip_link' ) ) {
			add_action( 'wp_body_open', array( $this, 'render_skip_link' ), 1 );
		}

		if ( $this->on( 'label_search_form' ) ) {
			add_filter( 'get_search_form', array( $this, 'label_search_form' ) );
		}
	}

	/**
	 * Whether a given fix is enabled.
	 *
	 * @param string $key Fix id.
	 */
	public function on( string $key ): bool {
		return ! empty( $this->fixes[ $key ] );
	}

	/**
	 * Whether at least one fix is enabled.
	 */
	private function any_enabled(): bool {
		foreach ( $this->fixes as $enabled ) {
			if ( $enabled ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether any enabled fix needs the front-end script.
	 */
	private function needs_script(): bool {
		foreach ( self::JS_FIXES as $key ) {
			if ( $this->on( $key ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Enqueue the front-end fix assets and pass the active flags to JS.
	 */
	public function enqueue_assets(): void {
		wp_enqueue_style(
			'esmoas-frontend-fixes',
			ESMOAS_PLUGIN_URL . 'assets/css/frontend-fixes.css',
			array(),
			ESMOAS_VERSION
		);

		if ( ! $this->needs_script() ) {
			return;
		}

		wp_enqueue_script(
			'esmoas-frontend-fixes',
			ESMOAS_PLUGIN_URL . 'assets/js/frontend-fixes.js',
			array(),
			ESMOAS_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_localize_script(
			'esmoas-frontend-fixes',
			'esmoasFixes',
			array(
				'flags' => array(
					'ensureHtmlLang'         => $this->on( 'add_html_lang' ),
					'newWindowWarning'       => $this->on( 'new_window_warning' ),
					'fixViewport'            => $this->on( 'fix_viewport' ),
					'removePositiveTabindex' => $this->on( 'remove_positive_tabindex' ),
					'removeTitleAttr'        => $this->on( 'remove_title_attr' ),
				),
				'lang'  => str_replace( '_', '-', get_bloginfo( 'language' ) ),
				'i18n'  => array(
					'newWindow' => __( '(opens in a new window)', 'esmo-accessibility-scanner' ),
				),
			)
		);
	}

	/**
	 * Add body classes that enable CSS-only fixes.
	 *
	 * @param mixed $classes Existing body classes.
	 * @return mixed
	 */
	public function add_body_classes( $classes ) {
		if ( ! is_array( $classes ) ) {
			return $classes;
		}

		if ( $this->on( 'add_focus_outline' ) ) {
			$classes[] = 'esmoas-fix-focus-outline';
		}

		if ( $this->on( 'underline_links' ) ) {
			$classes[] = 'esmoas-fix-underline-links';
		}

		return $classes;
	}

	/**
	 * Output a skip-to-content link at the top of the page.
	 *
	 * Block themes already receive a skip link from WordPress core, so a second
	 * one is only added for classic themes.
	 */
	public function render_skip_link(): void {
		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			return;
		}

		$target = Plugin::filter( 'esmoas_skip_link_target', '#content' );
		if ( ! is_string( $target ) || '' === $target ) {
			$target = '#content';
		}

		printf(
			'<a class="esmoas-skip-link screen-reader-text" href="%s">%s</a>',
			esc_url( $target ),
			esc_html__( 'Skip to content', 'esmo-accessibility-scanner' )
		);
	}

	/**
	 * Ensure the default search form field has an accessible label.
	 *
	 * @param mixed $form Search form markup (other filters may return null).
	 * @return mixed
	 */
	public function label_search_form( $form ) {
		if ( ! is_string( $form ) || false !== strpos( $form, 'aria-label' ) ) {
			return $form;
		}

		return str_replace(
			'type="search"',
			'type="search" aria-label="' . esc_attr__( 'Search', 'esmo-accessibility-scanner' ) . '"',
			$form
		);
	}
}
