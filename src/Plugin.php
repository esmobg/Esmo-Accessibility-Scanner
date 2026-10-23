<?php
/**
 * Main plugin bootstrap and service container.
 *
 * @package EsmoAccessibilityScanner
 */

declare(strict_types=1);

namespace EsmoAccessibilityScanner;

use EsmoAccessibilityScanner\Activation\Installer;
use EsmoAccessibilityScanner\Admin\AdminMenu;
use EsmoAccessibilityScanner\Admin\AssetManager;
use EsmoAccessibilityScanner\Export\ExportController;
use EsmoAccessibilityScanner\Fixes\AutoFixer;
use EsmoAccessibilityScanner\Rules\RuleCatalog;
use EsmoAccessibilityScanner\Scan\ResultNormalizer;
use EsmoAccessibilityScanner\Scan\ScanController;
use EsmoAccessibilityScanner\Scan\ScoreCalculator;
use EsmoAccessibilityScanner\Scan\UrlProvider;
use EsmoAccessibilityScanner\Storage\IssueRepository;
use EsmoAccessibilityScanner\Storage\ScanRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Lightweight service registry that wires the plugin together.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Resolved service instances keyed by id.
	 *
	 * @var array<string, object>
	 */
	private array $services = array();

	/**
	 * Whether init() has already run.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Private constructor to enforce the singleton.
	 */
	private function __construct() {}

	/**
	 * Retrieve the shared plugin instance.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register the services and hooks.
	 */
	public function init(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		$this->register_services();

		add_action( 'admin_init', array( Installer::class, 'maybe_upgrade' ) );
		add_action( 'wp_initialize_site', array( Installer::class, 'install_for_new_site' ), 200 );
		add_filter( 'wpmu_drop_tables', array( Installer::class, 'drop_tables_for_site' ), 10, 2 );

		$this->service( AdminMenu::class )->register();
		$this->service( AssetManager::class )->register();
		$this->service( ScanController::class )->register();
		$this->service( ExportController::class )->register();
		$this->service( AutoFixer::class )->register();
	}

	/**
	 * Hook names used up to 1.2.0 ("Accessibility Guardian"), still honoured as deprecated aliases.
	 *
	 * @var array<string, string>
	 */
	private const LEGACY_HOOKS = array(
		'esmoas_scan_urls'           => 'accg_scan_urls',
		'esmoas_scan_url_limit'      => 'accg_scan_url_limit',
		'esmoas_axe_options'         => 'accg_axe_options',
		'esmoas_required_capability' => 'accg_required_capability',
		'esmoas_skip_link_target'    => 'accg_skip_link_target',
		'esmoas_scan_completed'      => 'accg_scan_completed',
	);

	/**
	 * Run a filter, first through its deprecated pre-1.3.0 name if anything uses it.
	 *
	 * @param string $hook  Current filter name.
	 * @param mixed  $value Value to filter.
	 * @param mixed  ...$args Extra arguments.
	 * @return mixed
	 */
	public static function filter( string $hook, $value, ...$args ) {
		$legacy = self::LEGACY_HOOKS[ $hook ] ?? '';
		if ( '' !== $legacy && false !== has_filter( $legacy ) ) {
			$value = apply_filters_deprecated( $legacy, array_merge( array( $value ), $args ), '1.3.0', $hook );
		}

		return apply_filters( $hook, $value, ...$args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- $hook is always an esmoas_ hook from LEGACY_HOOKS.
	}

	/**
	 * Fire an action, also through its deprecated pre-1.3.0 name if anything uses it.
	 *
	 * @param string $hook    Current action name.
	 * @param mixed  ...$args Arguments.
	 */
	public static function action( string $hook, ...$args ): void {
		$legacy = self::LEGACY_HOOKS[ $hook ] ?? '';
		if ( '' !== $legacy && false !== has_action( $legacy ) ) {
			do_action_deprecated( $legacy, $args, '1.3.0', $hook );
		}

		do_action( $hook, ...$args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- $hook is always an esmoas_ hook from LEGACY_HOOKS.
	}

	/**
	 * Capability required to view reports, run scans, export and change settings.
	 *
	 * Filterable through `esmoas_required_capability`; falls back to
	 * `manage_options` when a filter returns something unusable.
	 */
	public static function capability(): string {
		$capability = self::filter( 'esmoas_required_capability', 'manage_options' );

		return is_string( $capability ) && '' !== $capability ? $capability : 'manage_options';
	}

	/**
	 * Capability required to change the settings, including the site-wide automatic fixes.
	 *
	 * Kept separate from capability() so that opening the reports to other roles
	 * does not also hand out control over front-end output.
	 */
	public static function settings_capability(): string {
		/**
		 * Filters the capability required to change the plugin settings.
		 *
		 * @since 1.3.0
		 *
		 * @param string $capability Capability name. Default 'manage_options'.
		 */
		$capability = apply_filters( 'esmoas_settings_capability', 'manage_options' );

		return is_string( $capability ) && '' !== $capability ? $capability : 'manage_options';
	}

	/**
	 * Resolve a registered service by class name.
	 *
	 * @template T of object
	 * @param class-string<T> $id Service class name.
	 * @return T
	 */
	public function service( string $id ): object {
		if ( ! isset( $this->services[ $id ] ) ) {
			throw new \RuntimeException(
				esc_html( sprintf( 'Service "%s" is not registered.', $id ) )
			);
		}

		/** @var T */
		return $this->services[ $id ];
	}

	/**
	 * Instantiate and store every service.
	 */
	private function register_services(): void {
		$scan_repository  = new ScanRepository();
		$issue_repository = new IssueRepository();
		$rule_catalog     = new RuleCatalog();
		$score_calculator = new ScoreCalculator();
		$url_provider     = new UrlProvider();
		$normalizer       = new ResultNormalizer( $rule_catalog );

		$admin_menu       = new AdminMenu( $scan_repository, $issue_repository, $score_calculator, $rule_catalog );

		$this->services = array(
			ScanRepository::class   => $scan_repository,
			IssueRepository::class  => $issue_repository,
			RuleCatalog::class      => $rule_catalog,
			ScoreCalculator::class  => $score_calculator,
			UrlProvider::class      => $url_provider,
			ResultNormalizer::class => $normalizer,
			AdminMenu::class        => $admin_menu,
			AssetManager::class     => new AssetManager( $admin_menu ),
			ScanController::class   => new ScanController(
				$url_provider,
				$scan_repository,
				$issue_repository,
				$normalizer,
				$score_calculator
			),
			ExportController::class => new ExportController( $issue_repository, $scan_repository ),
			AutoFixer::class        => new AutoFixer(),
		);
	}
}
