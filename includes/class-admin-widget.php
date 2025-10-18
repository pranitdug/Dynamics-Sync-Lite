<?php
/**
 * Admin Dashboard Widget
 * 
 * Displays recent sync activity and statistics
 */

if (!defined('ABSPATH')) {
    exit;
}

class DSL_Admin_Widget {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
    }
    
    /**
     * Add dashboard widget
     */
    public function add_dashboard_widget() {
        if (current_user_can('manage_options')) {
            wp_add_dashboard_widget(
                'dsl_activity_widget',
                __('Dynamics Sync Activity', 'dynamics-sync-lite'),
                array($this, 'render_widget')
            );
        }
    }
    
    /**
     * Render widget content
     */
    public function render_widget() {
        $stats = $this->get_sync_stats();
        $recent_logs = DSL_Logger::get_logs(array('limit' => 10));
        
        include DSL_PLUGIN_DIR . 'templates/admin-widget.php';
    }
    
    /**
     * Get sync statistics
     */
    private function get_sync_stats() {
        global $wpdb;
        
        // Get synced users count
        $synced_users = $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) 
             FROM {$wpdb->usermeta} 
             WHERE meta_key = 'dsl_contact_id' 
             AND meta_value != ''"
        );
        
        // Get total users
        $total_users = count_users();
        $total_count = $total_users['total_users'];
        
        // Get log counts by level
        $success_count = DSL_Logger::get_log_count('success');
        $error_count = DSL_Logger::get_log_count('error');
        $info_count = DSL_Logger::get_log_count('info');
        
        // Get recent sync time
        $last_sync = $wpdb->get_var(
            "SELECT meta_value 
             FROM {$wpdb->usermeta} 
             WHERE meta_key = 'dsl_last_sync' 
             ORDER BY meta_value DESC 
             LIMIT 1"
        );
        
        return array(
            'synced_users' => (int) $synced_users,
            'total_users' => $total_count,
            'sync_percentage' => $total_count > 0 ? round(($synced_users / $total_count) * 100) : 0,
            'success_count' => (int) $success_count,
            'error_count' => (int) $error_count,
            'info_count' => (int) $info_count,
            'last_sync' => $last_sync
        );
    }
}