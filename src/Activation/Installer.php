<?php
/**
 * Database installation and lifecycle handling.
 *
 * @package EsmoAccessibilityScanner
 */

declare(strict_types=1);

namespace EsmoAccessibilityScanner\Activation;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and maintains the plugin database schema.
 */
final class Installer {

	/**
	 * Current schema version. Bump to trigger dbDelta migrations.
	 */
	private const DB_VERSION = '1.3.0';

	/**
	 * Option key that stores the installed schema version.
	 */
	private const DB_VERSION_OPTION = 'esmoas_db_version';

	/**
	 * Table name suffixes (appended to the site's table prefix).
	 *
	 * @var array<int, string>
	 */
	public const TABLES = array( 'esmoas_scans', 'esmoas_issues', 'esmoas_history', 'esmoas_scan_pages' );

	/**
	 * Table/option prefix used up to 1.2.0, when the plugin was called "Accessibility Guardian".
	 */
	public const LEGACY_PREFIX = 'accg_';

	/**
	 * Plugin file of 1.2.0 and earlier.
	 */
	public const LEGACY_PLUGIN = 'accessibility-guardian/accessibility-guardian.php';

	/**
	 * Progress of the import from 1.2.0: "settings" once the settings are copied,
	 * "done" once every table is copied.
	 */
	public const IMPORT_OPTION = 'esmoas_legacy_import';

	/**
	 * Transient that delays the next attempt after a failed import.
	 */
	public const IMPORT_RETRY_TRANSIENT = 'esmoas_legacy_import_retry';

	/**
	 * Tables ("scans", "issues", "history") whose 1.2.0 rows are fully copied.
	 */
	public const IMPORT_TABLES_OPTION = 'esmoas_legacy_import_tables';

	/**
	 * Columns of the 1.2.0 tables, copied as they are into the new tables.
	 *
	 * @var array<string, array<int, string>>
	 */
	private const LEGACY_COLUMNS = array(
		'scans'   => array( 'id', 'scan_type', 'status', 'started_at', 'finished_at', 'total_urls', 'scanned_urls', 'score', 'errors', 'warnings', 'passes' ),
		'issues'  => array( 'id', 'scan_id', 'url', 'post_id', 'rule_id', 'wcag_ref', 'severity', 'category', 'impact', 'message', 'html_snippet', 'dom_path', 'fix_suggestion', 'doc_link', 'status', 'created_at' ),
		'history' => array( 'id', 'scan_id', 'score', 'errors', 'warnings', 'passes', 'created_at' ),
	);

	/**
	 * Run on plugin activation.
	 *
	 * @param bool $network_wide Whether the plugin is being network-activated.
	 */
	public static function activate( $network_wide = false ): void {
		$network_wide = is_multisite() && $network_wide;

		// Next to a network-active Accessibility Guardian, its screens would keep writing
		// scans that are never imported. Network activation imports every site and switches it off.
		if ( is_multisite() && ! $network_wide ) {
			if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			if ( is_plugin_active_for_network( self::LEGACY_PLUGIN ) ) {
				wp_die(
					esc_html__( 'Accessibility Guardian, the previous version of this plugin, is active on the whole network. Network-activate Esmo Accessibility Scanner instead: it imports the data of every site and deactivates Accessibility Guardian.', 'esmo-accessibility-scanner' ),
					esc_html__( 'Plugin not activated', 'esmo-accessibility-scanner' ),
					array( 'back_link' => true )
				);
			}
		}

		self::deactivate_legacy_plugin( $network_wide );

		if ( $network_wide ) {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::deactivate_legacy_plugin( false );
				self::install_site();
				restore_current_blog();
			}

			return;
		}

		self::install_site();
	}

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate(): void {
		// Intentionally keep data on deactivation; cleanup happens on uninstall.
	}

	/**
	 * Ensure the schema and default settings exist. Cheap when up to date.
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::install_site();
			return;
		}

		// An import that failed (for example a database timeout) is retried, at most once an hour.
		if ( self::legacy_import_pending() && false === get_transient( self::IMPORT_RETRY_TRANSIENT ) ) {
			self::import_legacy_data();
		}
	}

	/**
	 * Whether this site still has data from 1.2.0 that has not been fully imported.
	 */
	public static function legacy_import_pending(): bool {
		return false !== get_option( self::LEGACY_PREFIX . 'db_version', false ) && 'done' !== get_option( self::IMPORT_OPTION, '' );
	}

	/**
	 * Create tables for a site added to a network where the plugin is network-active.
	 *
	 * @param \WP_Site $site New site object.
	 */
	public static function install_for_new_site( $site ): void {
		if ( ! $site instanceof \WP_Site ) {
			return;
		}

		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! is_plugin_active_for_network( plugin_basename( ESMOAS_PLUGIN_FILE ) ) ) {
			return;
		}

		switch_to_blog( (int) $site->blog_id );
		self::install_site();
		restore_current_blog();
	}

	/**
	 * Drop this plugin's tables when a network site is deleted.
	 *
	 * @param mixed $tables  Table names core is about to drop.
	 * @param int   $site_id Site being deleted.
	 * @return mixed
	 */
	public static function drop_tables_for_site( $tables, $site_id = 0 ) {
		global $wpdb;

		if ( ! is_array( $tables ) ) {
			return $tables;
		}

		$prefix = $wpdb->get_blog_prefix( (int) $site_id );
		foreach ( self::TABLES as $suffix ) {
			$tables[] = $prefix . $suffix;
		}

		return $tables;
	}

	/**
	 * Install tables and default settings for the current site.
	 */
	private static function install_site(): void {
		self::install_tables();

		if ( self::legacy_import_pending() ) {
			self::import_legacy_data();
		}

		self::seed_default_settings();
	}

	/**
	 * Copy data from version 1.2.0 and earlier (plugin "Accessibility Guardian", prefix accg_).
	 *
	 * Runs per site, right after the new tables are created, until it succeeds
	 * (IMPORT_OPTION). The settings are copied once; rows are copied with plain
	 * INSERT ... SELECT (portable to every database WordPress supports), and a
	 * table counts as done only once its copy succeeded (IMPORT_TABLES_OPTION),
	 * so a retry copies whatever is still missing. Custom rule ids move from
	 * "ag-" to "esmoas-". New scans wait until the import is done (see
	 * ScanController), so imported and new rows never share ids. The old
	 * tables and options are left alone: deleting the old plugin removes them,
	 * and until then it can still be reactivated.
	 */
	public static function import_legacy_data(): void {
		global $wpdb;

		$first_attempt = false === get_option( self::IMPORT_OPTION, false );
		if ( $first_attempt ) {
			$settings = get_option( self::LEGACY_PREFIX . 'settings', false );
			if ( is_array( $settings ) ) {
				update_option( 'esmoas_settings', $settings );
			}
			update_option( self::IMPORT_OPTION, 'settings' );
		}

		$done     = array_values( array_filter( (array) get_option( self::IMPORT_TABLES_OPTION, array() ), 'is_string' ) );
		$complete = true;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		foreach ( self::LEGACY_COLUMNS as $name => $columns ) {
			if ( in_array( $name, $done, true ) ) {
				continue;
			}

			$old = $wpdb->prefix . self::LEGACY_PREFIX . $name;
			$new = $wpdb->prefix . 'esmoas_' . $name;

			if ( $old !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $old ) ) ) ) {
				$done[] = $name;
				continue;
			}

			// Data the new table held before the first attempt is never mixed with the old rows.
			if ( $first_attempt && 0 < (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $new ) ) ) {
				$done[] = $name;
				continue;
			}

			// Rows that an earlier, interrupted attempt already copied are skipped by id.
			$list   = implode( ', ', $columns );
			$copied = $wpdb->query( $wpdb->prepare( "INSERT INTO %i ( {$list} ) SELECT {$list} FROM %i WHERE id NOT IN ( SELECT id FROM %i )", $new, $old, $new ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- $list is a fixed column list from LEGACY_COLUMNS.
			if ( false === $copied ) {
				$complete = false;
			} else {
				$done[] = $name;
			}
		}

		update_option( self::IMPORT_TABLES_OPTION, $done );

		// Supplemental rule ids were prefixed "ag-" before 1.3.0.
		$renamed = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET rule_id = CONCAT( %s, SUBSTRING( rule_id, 4 ) ) WHERE rule_id LIKE %s',
				$wpdb->prefix . 'esmoas_issues',
				'esmoas-',
				$wpdb->esc_like( 'ag-' ) . '%'
			)
		);
		// phpcs:enable

		if ( $complete && false !== $renamed ) {
			update_option( self::IMPORT_OPTION, 'done' );
			delete_transient( self::IMPORT_RETRY_TRANSIENT );
		} else {
			set_transient( self::IMPORT_RETRY_TRANSIENT, 1, HOUR_IN_SECONDS );
		}
	}

	/**
	 * Deactivate "Accessibility Guardian" (1.2.0 and earlier). It is this plugin
	 * under its old name; running both would apply the automatic fixes twice.
	 *
	 * @param bool $network_wide Whether this plugin is being network-activated.
	 */
	private static function deactivate_legacy_plugin( bool $network_wide ): void {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( is_multisite() && is_plugin_active_for_network( self::LEGACY_PLUGIN ) ) {
			// Only a network activation may switch off a network-wide plugin.
			if ( $network_wide ) {
				deactivate_plugins( self::LEGACY_PLUGIN, true, true );
			}
			return;
		}

		if ( is_plugin_active( self::LEGACY_PLUGIN ) ) {
			deactivate_plugins( self::LEGACY_PLUGIN, true, false );
		}
	}

	/**
	 * Create or update the custom tables via dbDelta().
	 */
	private static function install_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$scans           = $wpdb->prefix . 'esmoas_scans';
		$issues          = $wpdb->prefix . 'esmoas_issues';
		$history         = $wpdb->prefix . 'esmoas_history';
		$pages           = $wpdb->prefix . 'esmoas_scan_pages';

		$schema = array();

		$schema[] = "CREATE TABLE {$scans} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			scan_type VARCHAR(20) NOT NULL DEFAULT 'single',
			status VARCHAR(20) NOT NULL DEFAULT 'running',
			started_at DATETIME NULL DEFAULT NULL,
			finished_at DATETIME NULL DEFAULT NULL,
			updated_at DATETIME NULL DEFAULT NULL,
			total_urls INT(11) NOT NULL DEFAULT 0,
			scanned_urls INT(11) NOT NULL DEFAULT 0,
			score TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
			errors INT(11) NOT NULL DEFAULT 0,
			warnings INT(11) NOT NULL DEFAULT 0,
			passes INT(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY started_at (started_at)
		) {$charset_collate};";

		$schema[] = "CREATE TABLE {$issues} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			scan_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			url TEXT NOT NULL,
			post_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			rule_id VARCHAR(100) NOT NULL DEFAULT '',
			wcag_ref VARCHAR(100) NOT NULL DEFAULT '',
			severity VARCHAR(20) NOT NULL DEFAULT 'minor',
			category VARCHAR(50) NOT NULL DEFAULT '',
			impact VARCHAR(20) NOT NULL DEFAULT '',
			message TEXT NOT NULL,
			html_snippet LONGTEXT NOT NULL,
			dom_path TEXT NOT NULL,
			fix_suggestion TEXT NOT NULL,
			doc_link TEXT NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'open',
			created_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY scan_id (scan_id),
			KEY severity (severity),
			KEY rule_id (rule_id),
			KEY post_id (post_id)
		) {$charset_collate};";

		$schema[] = "CREATE TABLE {$history} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			scan_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			score TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
			errors INT(11) NOT NULL DEFAULT 0,
			warnings INT(11) NOT NULL DEFAULT 0,
			passes INT(11) NOT NULL DEFAULT 0,
			created_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at)
		) {$charset_collate};";

		// Pages queued by running scans; each can be saved once. Rows are removed when a scan ends.
		$schema[] = "CREATE TABLE {$pages} (
			scan_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			url_hash CHAR(32) NOT NULL DEFAULT '',
			post_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			saved TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (scan_id,url_hash)
		) {$charset_collate};";

		foreach ( $schema as $statement ) {
			dbDelta( $statement );
		}

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Store default settings if none exist yet.
	 */
	private static function seed_default_settings(): void {
		if ( false === get_option( 'esmoas_settings' ) ) {
			add_option( 'esmoas_settings', self::default_settings() );
		}
	}

	/**
	 * Default plugin settings for a fresh install.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'include_post_types' => array( 'post', 'page' ),
			'include_terms'      => false,
			'wcag_level'         => 'aa',
			'best_practice'      => true,
			'fixes'              => array(),
		);
	}
}
