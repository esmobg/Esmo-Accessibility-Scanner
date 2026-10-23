<?php
/**
 * Registers admin menu pages and renders their templates.
 *
 * @package EsmoAccessibilityScanner
 */

declare(strict_types=1);

namespace EsmoAccessibilityScanner\Admin;

use EsmoAccessibilityScanner\Activation\Installer;
use EsmoAccessibilityScanner\Plugin;
use EsmoAccessibilityScanner\Rules\RuleCatalog;
use EsmoAccessibilityScanner\Scan\ScoreCalculator;
use EsmoAccessibilityScanner\Scan\UrlProvider;
use EsmoAccessibilityScanner\Storage\IssueRepository;
use EsmoAccessibilityScanner\Storage\ScanRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the Esmo Accessibility Scanner admin section.
 */
final class AdminMenu {

	/**
	 * Dashboard page slug (also the top-level slug).
	 */
	public const SLUG_DASHBOARD = 'esmo-accessibility-scanner';

	/**
	 * Scan page slug.
	 */
	public const SLUG_SCAN = 'esmo-accessibility-scanner-scan';

	/**
	 * Issues page slug.
	 */
	public const SLUG_ISSUES = 'esmo-accessibility-scanner-issues';

	/**
	 * Settings page slug.
	 */
	public const SLUG_SETTINGS = 'esmo-accessibility-scanner-settings';

	/**
	 * Scan repository.
	 */
	private ScanRepository $scans;

	/**
	 * Issue repository.
	 */
	private IssueRepository $issues;

	/**
	 * Score calculator.
	 */
	private ScoreCalculator $score;

	/**
	 * Rule metadata catalog.
	 */
	private RuleCatalog $catalog;

	/**
	 * Admin page hook suffixes returned by add_menu_page()/add_submenu_page().
	 *
	 * @var array<int, string>
	 */
	private array $hook_suffixes = array();

	/**
	 * Constructor.
	 *
	 * @param ScanRepository  $scans   Scan repository.
	 * @param IssueRepository $issues  Issue repository.
	 * @param ScoreCalculator $score   Score calculator.
	 * @param RuleCatalog     $catalog Rule metadata catalog.
	 */
	public function __construct( ScanRepository $scans, IssueRepository $issues, ScoreCalculator $score, RuleCatalog $catalog ) {
		$this->scans   = $scans;
		$this->issues  = $issues;
		$this->score   = $score;
		$this->catalog = $catalog;
	}

	/**
	 * Register admin hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_save_settings' ) );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_scan_link' ), 100 );
		add_action( 'admin_notices', array( $this, 'maybe_show_legacy_plugin_notice' ) );
	}

	/**
	 * On the Plugins screen and this plugin's pages, remind administrators to
	 * delete the pre-1.3.0 plugin ("Accessibility Guardian") once its data is
	 * imported, and warn them not to while the import is still pending: deleting
	 * the old plugin removes the data that has not been copied yet.
	 */
	public function maybe_show_legacy_plugin_notice(): void {
		global $hook_suffix;

		if ( ! current_user_can( 'delete_plugins' ) ) {
			return;
		}

		if ( 'plugins.php' !== $hook_suffix && ! $this->is_plugin_screen( (string) $hook_suffix ) ) {
			return;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Ask WordPress rather than file_exists(), which OPcache can answer for deleted files.
		if ( ! isset( get_plugins( '/' . dirname( Installer::LEGACY_PLUGIN ) )[ basename( Installer::LEGACY_PLUGIN ) ] ) ) {
			return;
		}

		if ( Installer::legacy_import_pending() ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html__( 'Esmo Accessibility Scanner has not finished importing the scans of Accessibility Guardian yet and will try again automatically. Do not delete Accessibility Guardian until this message is gone, or its remaining data will be lost.', 'esmo-accessibility-scanner' )
			);
			return;
		}

		$message = is_plugin_active( Installer::LEGACY_PLUGIN )
			? __( 'Esmo Accessibility Scanner is the new name of Accessibility Guardian and has imported its scans and settings. While Accessibility Guardian is active, only its automatic fixes are applied. Please deactivate and delete it.', 'esmo-accessibility-scanner' )
			: __( 'Esmo Accessibility Scanner is the new name of Accessibility Guardian. Its scans and settings were imported and the old plugin was deactivated, so you can now delete Accessibility Guardian.', 'esmo-accessibility-scanner' );

		printf( '<div class="notice notice-info"><p>%s</p></div>', esc_html( $message ) );
	}

	/**
	 * Add a "Scan accessibility" link to the toolbar while viewing published content.
	 *
	 * @param mixed $wp_admin_bar Toolbar instance.
	 */
	public function add_admin_bar_scan_link( $wp_admin_bar ): void {
		if ( ! $wp_admin_bar instanceof \WP_Admin_Bar || is_admin() || ! is_singular() ) {
			return;
		}

		if ( ! current_user_can( Plugin::capability() ) ) {
			return;
		}

		$post_id = (int) get_queried_object_id();
		if ( $post_id < 1 || 'publish' !== get_post_status( $post_id ) ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'esmoas-scan-page',
				'title' => esc_html__( 'Scan accessibility', 'esmo-accessibility-scanner' ),
				'href'  => add_query_arg(
					array(
						'page'    => self::SLUG_SCAN,
						'post_id' => $post_id,
					),
					admin_url( 'admin.php' )
				),
			)
		);
	}

	/**
	 * Register the menu and subpages.
	 */
	public function add_menu(): void {
		$capability = Plugin::capability();

		$this->hook_suffixes[] = (string) add_menu_page(
			__( 'Esmo Accessibility Scanner', 'esmo-accessibility-scanner' ),
			__( 'Accessibility Scanner', 'esmo-accessibility-scanner' ),
			$capability,
			self::SLUG_DASHBOARD,
			array( $this, 'render_dashboard' ),
			'dashicons-universal-access-alt',
			81
		);

		$this->hook_suffixes[] = (string) add_submenu_page(
			self::SLUG_DASHBOARD,
			__( 'Dashboard', 'esmo-accessibility-scanner' ),
			__( 'Dashboard', 'esmo-accessibility-scanner' ),
			$capability,
			self::SLUG_DASHBOARD,
			array( $this, 'render_dashboard' )
		);

		$this->hook_suffixes[] = (string) add_submenu_page(
			self::SLUG_DASHBOARD,
			__( 'Run Scan', 'esmo-accessibility-scanner' ),
			__( 'Run Scan', 'esmo-accessibility-scanner' ),
			$capability,
			self::SLUG_SCAN,
			array( $this, 'render_scan' )
		);

		$this->hook_suffixes[] = (string) add_submenu_page(
			self::SLUG_DASHBOARD,
			__( 'Issues', 'esmo-accessibility-scanner' ),
			__( 'Issues', 'esmo-accessibility-scanner' ),
			$capability,
			self::SLUG_ISSUES,
			array( $this, 'render_issues' )
		);

		$this->hook_suffixes[] = (string) add_submenu_page(
			self::SLUG_DASHBOARD,
			__( 'Settings', 'esmo-accessibility-scanner' ),
			__( 'Settings', 'esmo-accessibility-scanner' ),
			Plugin::settings_capability(),
			self::SLUG_SETTINGS,
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Whether a hook suffix belongs to one of this plugin's admin pages.
	 *
	 * @param string $hook_suffix Hook suffix passed to admin_enqueue_scripts.
	 */
	public function is_plugin_screen( string $hook_suffix ): bool {
		return '' !== $hook_suffix && in_array( $hook_suffix, $this->hook_suffixes, true );
	}

	/**
	 * Render the dashboard page.
	 */
	public function render_dashboard(): void {
		$this->guard();

		$latest = $this->scans->latest();
		$recent = $this->scans->recent( 10 );
		$history = $this->scans->history( 20 );

		$severity_counts = array();
		$category_counts = array();
		$top_rules       = array();
		$band            = $this->score->band( 0 );

		if ( null !== $latest ) {
			$scan_id         = (int) $latest['id'];
			$severity_counts = $this->issues->severity_counts( $scan_id );
			$category_counts = $this->issues->category_counts( $scan_id );
			$top_rules       = $this->issues->top_rules( $scan_id, 8 );
			$band            = $this->score->band( (int) $latest['score'] );
		}

		$this->render(
			'dashboard',
			array(
				'latest'          => $latest,
				'recent'          => $recent,
				'history'         => $history,
				'severity_counts' => $severity_counts,
				'category_counts' => $category_counts,
				'top_rules'       => $top_rules,
				'band'            => $band,
				'scan_url'        => admin_url( 'admin.php?page=' . self::SLUG_SCAN ),
			)
		);
	}

	/**
	 * Render the run-scan page.
	 */
	public function render_scan(): void {
		$this->guard();

		// Reading filter params for display only; posts the user cannot read fall back to a full scan.
		$post_id = isset( $_GET['post_id'] ) ? absint( wp_unslash( $_GET['post_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post    = UrlProvider::scannable_post( $post_id );

		$this->render(
			'scan',
			array(
				'post_id'    => null !== $post ? $post->ID : 0,
				'scan_type'  => null !== $post ? 'single' : 'full',
				'post_title' => null !== $post ? get_the_title( $post ) : '',
			)
		);
	}

	/**
	 * Render the issues listing page, categorized by severity.
	 */
	public function render_issues(): void {
		$this->guard();

		// Read-only filters for display; no state change.
		$scan_id  = isset( $_GET['scan_id'] ) ? absint( wp_unslash( $_GET['scan_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$severity = isset( $_GET['severity'] ) ? sanitize_key( wp_unslash( $_GET['severity'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 0 === $scan_id ) {
			$latest  = $this->scans->latest();
			$scan_id = null !== $latest ? (int) $latest['id'] : 0;
		}

		$valid_sev = array( 'critical', 'major', 'minor', 'warning' );
		if ( '' !== $severity && ! in_array( $severity, $valid_sev, true ) ) {
			$severity = '';
		}

		$per_page        = 25;
		$severity_counts = $scan_id > 0 ? $this->issues->severity_counts( $scan_id ) : array();
		$issues          = $scan_id > 0 ? $this->issues->for_scan( $scan_id, $per_page, $paged, $severity ) : array();

		$this->render(
			'issues',
			array(
				'scan_id'         => $scan_id,
				'severity'        => $severity,
				'paged'           => $paged,
				'per_page'        => $per_page,
				'severity_counts' => $severity_counts,
				'issues'          => $issues,
				'catalog'         => $this->catalog,
				'base_url'        => admin_url( 'admin.php?page=' . self::SLUG_ISSUES ),
				'settings_url'    => current_user_can( Plugin::settings_capability() ) ? admin_url( 'admin.php?page=' . self::SLUG_SETTINGS ) : '',
			)
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings(): void {
		$this->guard( Plugin::settings_capability() );

		// Display-only flag added by the redirect after saving.
		if ( ! empty( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_settings_error( 'esmoas_settings', 'esmoas_settings_saved', __( 'Settings saved.', 'esmo-accessibility-scanner' ), 'success' );
		}

		$settings   = (array) get_option( 'esmoas_settings', array() );
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $post_types['attachment'] );

		$this->render(
			'settings',
			array(
				'settings'    => $settings,
				'post_types'  => $post_types,
				'fix_catalog' => $this->catalog->fixes(),
			)
		);
	}

	/**
	 * Persist settings when the settings form is submitted.
	 */
	public function maybe_save_settings(): void {
		if ( ! isset( $_POST['esmoas_settings_submit'] ) ) {
			return;
		}

		if ( ! current_user_can( Plugin::settings_capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to manage these settings.', 'esmo-accessibility-scanner' ) );
		}

		check_admin_referer( 'esmoas_save_settings' );

		$post_types = array();
		if ( isset( $_POST['include_post_types'] ) && is_array( $_POST['include_post_types'] ) ) {
			$post_types = array_map( 'sanitize_key', wp_unslash( $_POST['include_post_types'] ) );
		}

		$fixes     = array();
		$available = array_keys( $this->catalog->fixes() );
		$submitted = isset( $_POST['fixes'] ) && is_array( $_POST['fixes'] )
			? array_map( 'sanitize_key', wp_unslash( $_POST['fixes'] ) )
			: array();
		foreach ( $available as $fix_key ) {
			$fixes[ $fix_key ] = in_array( $fix_key, $submitted, true );
		}

		$wcag_level = isset( $_POST['wcag_level'] ) ? sanitize_key( wp_unslash( $_POST['wcag_level'] ) ) : 'aa';
		if ( ! in_array( $wcag_level, array( 'a', 'aa' ), true ) ) {
			$wcag_level = 'aa';
		}

		$settings = array(
			'include_post_types' => $post_types,
			'include_terms'      => ! empty( $_POST['include_terms'] ),
			'wcag_level'         => $wcag_level,
			'best_practice'      => ! empty( $_POST['best_practice'] ),
			'fixes'              => $fixes,
		);

		update_option( 'esmoas_settings', $settings );

		wp_safe_redirect( add_query_arg( 'settings-updated', '1', admin_url( 'admin.php?page=' . self::SLUG_SETTINGS ) ) );
		exit;
	}

	/**
	 * Capability gate shared by all pages.
	 *
	 * @param string $capability Required capability; defaults to Plugin::capability().
	 */
	private function guard( string $capability = '' ): void {
		if ( ! current_user_can( '' !== $capability ? $capability : Plugin::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'esmo-accessibility-scanner' ) );
		}
	}

	/**
	 * Render a template. The context is available to it as `$args`.
	 *
	 * @param string               $template Template filename without extension.
	 * @param array<string, mixed> $context  Variables exposed to the template.
	 */
	private function render( string $template, array $context ): void {
		$file = ESMOAS_PLUGIN_DIR . 'templates/' . $template . '.php';

		if ( ! is_readable( $file ) ) {
			return;
		}

		load_template( $file, false, $context );
	}
}
