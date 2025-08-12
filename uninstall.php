<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package   Search_Protection
 * @author    Hilfan
 */

// If uninstall.php is not called by WordPress, die.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$option_name = 'sph_settings';
$settings    = get_option( $option_name );

// Check if the user has opted in to delete data on uninstall.
if ( ! empty( $settings['delete_on_uninstall'] ) && '1' === $settings['delete_on_uninstall'] ) {

    // Delete the plugin's settings from the options table.
    delete_option( $option_name );

    // Drop the custom database log table.
    global $wpdb;
    $log_table = $wpdb->prefix . 'sph_logs';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query( "DROP TABLE IF EXISTS {$log_table}" );
}
