<?php
/**
 * Uninstall routine for Esmo Accessibility Scanner.
 *
 * Removes custom tables and stored options when the plugin is deleted,
 * on every site of a multisite network.
 *
 * @package EsmoAccessibilityScanner
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove the plugin's tables and options from the current site.
 */
function esmoas_uninstall_site(): void {
	global $wpdb;

	// Current tables plus any left over from 1.2.0 and earlier (prefix accg_, "Accessibility Guardian").
	foreach ( array( 'esmoas_scans', 'esmoas_issues', 'esmoas_history', 'accg_scans', 'accg_issues', 'accg_history' ) as $esmoas_suffix ) {
		$esmoas_table = $wpdb->prefix . $esmoas_suffix;
		// Table name cannot be parameterized; it is the site prefix plus a hardcoded suffix.
		$wpdb->query( "DROP TABLE IF EXISTS {$esmoas_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	delete_option( 'esmoas_settings' );
	delete_option( 'esmoas_db_version' );
	delete_option( 'accg_settings' );
	delete_option( 'accg_db_version' );
	delete_transient( 'accg_settings_notice' );
}

if ( is_multisite() ) {
	$esmoas_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $esmoas_site_ids as $esmoas_site_id ) {
		switch_to_blog( (int) $esmoas_site_id );
		esmoas_uninstall_site();
		restore_current_blog();
	}
} else {
	esmoas_uninstall_site();
}
