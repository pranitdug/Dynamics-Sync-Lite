<?php
/**
 * Dashboard Widget
 *
 * @package DynamicsSyncLite
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class DSL_Dashboard {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
    }
    
    /**
     * Add dashboard widget
     */
    public function add_dashboard_widget() {
        if (current_user_can('manage_options')) {
            wp_add_dashboard_widget(
                'dsl_recent_updates',
                __('Dynamics Sync - Recent Updates', 'dynamics-sync-lite'),
                array($this, 'render_dashboard_widget')
            );
        }
    }
    
    /**
     * Render dashboard widget
     */
    public function render_dashboard_widget() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dsl_logs';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            echo '<p>' . __('Log table not found. Please deactivate and reactivate the plugin.', 'dynamics-sync-lite') . '</p>';
            return;
        }
        
        // Get recent successful updates
        $recent_updates = $wpdb->get_results(
            "SELECT l.*, u.user_login, u.user_email 
             FROM {$table_name} l
             LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
             WHERE l.action = 'update_contact_success'
             ORDER BY l.created_at DESC
             LIMIT 10"
        );
        
        // Get stats for today
        $total_updates_today = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE action = 'update_contact_success' 
             AND DATE(created_at) = CURDATE()"
        );
        
        // Get stats for week
        $total_updates_week = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE action = 'update_contact_success' 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        // Get error count for today
        $total_errors_today = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE (action LIKE '%failed%' OR action = 'error')
             AND DATE(created_at) = CURDATE()"
        );
        
        // Ensure we have numbers
        $total_updates_today = $total_updates_today ? intval($total_updates_today) : 0;
        $total_updates_week = $total_updates_week ? intval($total_updates_week) : 0;
        $total_errors_today = $total_errors_today ? intval($total_errors_today) : 0;
        ?>
        <div class="dsl-dashboard-widget">
            <div class="dsl-stats">
                <div class="dsl-stat-box">
                    <div class="dsl-stat-number"><?php echo esc_html($total_updates_today); ?></div>
                    <div class="dsl-stat-label"><?php _e('Updates Today', 'dynamics-sync-lite'); ?></div>
                </div>
                <div class="dsl-stat-box">
                    <div class="dsl-stat-number"><?php echo esc_html($total_updates_week); ?></div>
                    <div class="dsl-stat-label"><?php _e('Updates This Week', 'dynamics-sync-lite'); ?></div>
                </div>
                <div class="dsl-stat-box <?php echo $total_errors_today > 0 ? 'error' : ''; ?>">
                    <div class="dsl-stat-number"><?php echo esc_html($total_errors_today); ?></div>
                    <div class="dsl-stat-label"><?php _e('Errors Today', 'dynamics-sync-lite'); ?></div>
                </div>
            </div>
            
            <h4><?php _e('Recent Updates', 'dynamics-sync-lite'); ?></h4>
            
            <?php if (empty($recent_updates)) : ?>
                <p><?php _e('No recent updates found. Updates will appear here when users modify their contact information.', 'dynamics-sync-lite'); ?></p>
            <?php else : ?>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php _e('User', 'dynamics-sync-lite'); ?></th>
                            <th><?php _e('Date', 'dynamics-sync-lite'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_updates as $update) : ?>
                            <tr>
                                <td>
                                    <?php if ($update->user_login) : ?>
                                        <strong><?php echo esc_html($update->user_login); ?></strong><br>
                                        <small><?php echo esc_html($update->user_email); ?></small>
                                    <?php else : ?>
                                        <em><?php _e('System', 'dynamics-sync-lite'); ?></em>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(human_time_diff(strtotime($update->created_at), current_time('timestamp'))); ?> <?php _e('ago', 'dynamics-sync-lite'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <p style="text-align: center; margin-top: 15px;">
                <a href="<?php echo admin_url('admin.php?page=dynamics-sync-lite-logs'); ?>" class="button button-primary">
                    <?php _e('View All Logs', 'dynamics-sync-lite'); ?>
                </a>
            </p>
        </div>
        
        <style>
        .dsl-stats {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .dsl-stat-box {
            flex: 1;
            text-align: center;
            padding: 15px;
            background: #f0f0f1;
            border-radius: 4px;
        }
        .dsl-stat-box.error {
            background: #fee;
        }
        .dsl-stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #2271b1;
        }
        .dsl-stat-box.error .dsl-stat-number {
            color: #d63638;
        }
        .dsl-stat-label {
            font-size: 12px;
            color: #646970;
            margin-top: 5px;
        }
        .dsl-dashboard-widget table {
            margin-top: 10px;
        }
        </style>
        <?php
    }
}