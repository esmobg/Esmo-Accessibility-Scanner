<?php
/**
 * Registers and enqueues admin assets for the plugin pages.
 *
 * @package EsmoAccessibilityScanner
 */

declare(strict_types=1);

namespace EsmoAccessibilityScanner\Admin;

use EsmoAccessibilityScanner\Plugin;
use EsmoAccessibilityScanner\Scan\ScanController;

defined( 'ABSPATH' ) || exit;

/**
 * Handles wp_enqueue_script/style for Esmo Accessibility Scanner screens.
 */
final class AssetManager {

	/**
	 * Version of the bundled axe-core build in assets/js/axe.min.js.
	 */
	public const AXE_VERSION = '4.10.2';

	/**
	 * Admin menu, used to recognise this plugin's screens.
	 */
	private AdminMenu $menu;

	/**
	 * Constructor.
	 *
	 * @param AdminMenu $menu Admin menu that registered the plugin pages.
	 */
	public function __construct( AdminMenu $menu ) {
		$this->menu = $menu;
	}

	/**
	 * Register the enqueue hook.
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue scripts and styles on plugin pages only.
	 *
	 * @param mixed $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue( $hook_suffix ): void {
		if ( ! is_string( $hook_suffix ) || ! $this->menu->is_plugin_screen( $hook_suffix ) ) {
			return;
		}

		$script_args = array(
			'in_footer' => true,
			'strategy'  => 'defer',
		);

		wp_enqueue_style(
			'esmoas-admin',
			ESMOAS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			ESMOAS_VERSION
		);

		wp_enqueue_script(
			'esmoas-custom-rules',
			ESMOAS_PLUGIN_URL . 'assets/js/custom-rules.js',
			array(),
			ESMOAS_VERSION,
			$script_args
		);

		wp_localize_script(
			'esmoas-custom-rules',
			'esmoasCustomRulesI18n',
			array(
				'genericLinkHelp'      => __( 'Links must use descriptive text', 'esmo-accessibility-scanner' ),
				'genericLinkSummary'   => __( 'Link text does not describe its destination.', 'esmo-accessibility-scanner' ),
				'placeholderHelp'      => __( 'Placeholder is not a substitute for a label', 'esmo-accessibility-scanner' ),
				'placeholderSummary'   => __( 'Field relies on placeholder text instead of a persistent label.', 'esmo-accessibility-scanner' ),
				'newWindowHelp'        => __( 'Warn users when links open new windows', 'esmo-accessibility-scanner' ),
				'newWindowSummary'     => __( 'Link opens in a new window without warning the user.', 'esmo-accessibility-scanner' ),
				'pdfHelp'              => __( 'Verify accessibility of linked PDF documents', 'esmo-accessibility-scanner' ),
				'pdfSummary'           => __( 'Linked PDF detected; verify it is tagged and accessible.', 'esmo-accessibility-scanner' ),
			)
		);

		wp_enqueue_script(
			'esmoas-scanner',
			ESMOAS_PLUGIN_URL . 'assets/js/scanner.js',
			array( 'esmoas-custom-rules' ),
			ESMOAS_VERSION,
			$script_args
		);

		$settings   = (array) get_option( 'esmoas_settings', array() );
		$wcag_level = isset( $settings['wcag_level'] ) ? sanitize_key( (string) $settings['wcag_level'] ) : 'aa';
		if ( ! in_array( $wcag_level, array( 'a', 'aa' ), true ) ) {
			$wcag_level = 'aa';
		}

		$tags = array( 'wcag2a', 'wcag21a' );
		if ( 'aa' === $wcag_level ) {
			$tags = array_merge( $tags, array( 'wcag2aa', 'wcag21aa', 'wcag22aa' ) );
		}

		$best_practice = ! isset( $settings['best_practice'] ) || ! empty( $settings['best_practice'] );
		if ( $best_practice ) {
			$tags[] = 'best-practice';
		}

		/**
		 * Filters the options passed to axe.run() for every scanned page.
		 *
		 * @since 1.2.0
		 *
		 * @param array<string, mixed> $options axe-core run options (runOnly, rules, ...).
		 */
		$axe_options = Plugin::filter(
			'esmoas_axe_options',
			array(
				'runOnly' => array(
					'type'   => 'tag',
					'values' => $tags,
				),
			)
		);
		if ( ! is_array( $axe_options ) ) {
			$axe_options = array();
		}

		wp_localize_script(
			'esmoas-scanner',
			'esmoasScanner',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( ScanController::NONCE_ACTION ),
				'axeUrl'       => add_query_arg( 'ver', self::AXE_VERSION . '-' . ESMOAS_VERSION, ESMOAS_PLUGIN_URL . 'assets/js/axe.min.js' ),
				'axeOptions'   => $axe_options,
				'i18n'         => array(
					'preparing'    => __( 'Preparing scan…', 'esmo-accessibility-scanner' ),
					'scanning'     => __( 'Scanning', 'esmo-accessibility-scanner' ),
					'queued'       => __( 'Queued URLs', 'esmo-accessibility-scanner' ),
					'issues'       => __( 'issues', 'esmo-accessibility-scanner' ),
					'failed'       => __( 'could not be scanned', 'esmo-accessibility-scanner' ),
					'saveFailed'   => __( 'results could not be saved', 'esmo-accessibility-scanner' ),
					'complete'     => __( 'Scan complete', 'esmo-accessibility-scanner' ),
					'score'        => __( 'Score', 'esmo-accessibility-scanner' ),
					'cancelling'   => __( 'Cancelling after current page…', 'esmo-accessibility-scanner' ),
					'cancelled'    => __( 'Scan cancelled', 'esmo-accessibility-scanner' ),
					'startError'   => __( 'Could not start scan. Please reload the page and try again.', 'esmo-accessibility-scanner' ),
					'finishError'  => __( 'Scan could not be finalized.', 'esmo-accessibility-scanner' ),
					'networkError' => __( 'Connection problem while talking to the server.', 'esmo-accessibility-scanner' ),
					'pagesFailed'  => __( 'pages could not be scanned', 'esmo-accessibility-scanner' ),
				),
			)
		);

		wp_enqueue_script(
			'esmoas-dashboard',
			ESMOAS_PLUGIN_URL . 'assets/js/dashboard.js',
			array(),
			ESMOAS_VERSION,
			$script_args
		);

		wp_localize_script(
			'esmoas-dashboard',
			'esmoasDashboard',
			array(
				'i18n' => array(
					'noHistory'  => __( 'No history yet.', 'esmo-accessibility-scanner' ),
					/* translators: %d: accessibility score. */
					'scoreLabel' => __( 'Latest accessibility score %d out of 100', 'esmo-accessibility-scanner' ),
					/* translators: 1: first score, 2: latest score, 3: number of scans. */
					'trendLabel' => __( 'Accessibility score changed from %1$d to %2$d over the last %3$d scans', 'esmo-accessibility-scanner' ),
					/* translators: %d: number of scans. */
					'scansLabel' => __( '%d scans', 'esmo-accessibility-scanner' ),
				),
			)
		);
	}
}
