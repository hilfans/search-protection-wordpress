<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package   Search_Protection
 * @author    Hilfan, Telkom University
 */

// If uninstall.php is not called by WordPress, die.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$option_name = 'telu_search_protection_settings';
$settings = get_option($option_name);

// Check if the user has opted in to delete data on uninstall.
if (!empty($settings['delete_on_uninstall']) && $settings['delete_on_uninstall'] === '1') {
    
    // Delete the plugin's settings from the options table.
    delete_option($option_name);

    // Drop the custom database log table.
    global $wpdb;
    $log_table = $wpdb->prefix . 'telu_search_protection_logs';
    $wpdb->query("DROP TABLE IF EXISTS {$log_table}");
}
