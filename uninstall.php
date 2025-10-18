<?php
/**
 * Uninstall script
 * 
 * Fired when the plugin is uninstalled
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Delete plugin options
$options = array(
    'dsl_client_id',
    'dsl_client_secret',
    'dsl_tenant_id',
    'dsl_resource_url',
    'dsl_api_version',
    'dsl_enable_logging'
);

foreach ($options as $option) {
    delete_option($option);
}

// Delete user meta
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'dsl_%'");

// Delete transients
delete_transient('dsl_access_token');
delete_transient('dsl_last_log_clean');

// Drop log table
$table_name = $wpdb->prefix . 'dsl_logs';
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// Clear any cached data
wp_cache_flush();