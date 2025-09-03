<?php
/**
 * Uninstall routine for Search Protection (single-site only).
 *
 * Removes options and drops the log table when the user has opted-in
 * via the "delete_on_uninstall" setting. This file intentionally
 * does NOT include multisite handling.
 *
 * @package Search_Protection
 */

// If uninstall.php is not called by WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Helper to run cleanup for the current site only.
function ebmsp_sprotect_uninstall_single_site() {
	global $wpdb;

	$option_name = 'ebmsp_sprotect_settings';
	$settings    = get_option( $option_name );

	// Respect the user's preference.
	if ( empty( $settings['delete_on_uninstall'] ) || '1' !== $settings['delete_on_uninstall'] ) {
		return;
	}

	// Delete plugin options.
	delete_option( $option_name );

	// Clear scheduled cleanup task if any.
	wp_clear_scheduled_hook( 'ebmsp_sprotect_daily_log_cleanup' );

	// Drop custom table.
	$log_table = $wpdb->prefix . 'ebmsp_sprotect_logs';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DROP TABLE IF EXISTS {$log_table}" );

	// Clear plugin cache group just in case anything remains in memory cache.
	wp_cache_delete( 'ebmsp_sprotect_recent_keywords', 'search_protection' );
}

ebmsp_sprotect_uninstall_single_site();
