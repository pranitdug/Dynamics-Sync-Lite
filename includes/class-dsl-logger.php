<?php
/**
 * Logger Class
 *
 * @package DynamicsSyncLite
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class DSL_Logger {
    
    /**
     * Log action
     */
    public static function log_action($user_id, $action, $message) {
        if (!get_option('dsl_enable_logging', true)) {
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'dsl_logs';
        
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => absint($user_id),
                'action' => sanitize_text_field($action),
                'message' => sanitize_textarea_field($message),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s')
        );
    }
    
    /**
     * Log error
     */
    public static function log_error($message) {
        if (!get_option('dsl_enable_logging', true)) {
            return;
        }
        
        $user_id = is_user_logged_in() ? get_current_user_id() : 0;
        self::log_action($user_id, 'error', $message);
        
        // Also log to PHP error log
        error_log('Dynamics Sync Lite Error: ' . $message);
    }
    
    /**
     * Get logs
     */
    public static function get_logs($limit = 100, $offset = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dsl_logs';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));
    }
    
    /**
     * Clear old logs
     */
    public static function clear_old_logs($days = 30) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dsl_logs';
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }
}