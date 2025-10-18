<?php
/**
 * Logger class for tracking API calls and user actions
 */

if (!defined('ABSPATH')) {
    exit;
}

class DSL_Logger {
    
    private static $table_name = null;
    
    /**
     * Get table name
     */
    private static function get_table_name() {
        if (self::$table_name === null) {
            global $wpdb;
            self::$table_name = $wpdb->prefix . 'dsl_logs';
        }
        return self::$table_name;
    }
    
    /**
     * Create log table
     */
    public static function create_table() {
        global $wpdb;
        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            log_time datetime NOT NULL,
            log_level varchar(20) NOT NULL,
            message text NOT NULL,
            context longtext,
            user_id bigint(20),
            ip_address varchar(45),
            PRIMARY KEY  (id),
            KEY log_level (log_level),
            KEY log_time (log_time),
            KEY user_id (user_id)
        ) {$charset_collate};";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
    
    /**
     * Log a message
     */
    public static function log($level, $message, $context = array()) {
        // Check if logging is enabled
        if (!get_option('dsl_enable_logging', '1')) {
            return;
        }
        
        global $wpdb;
        $table_name = self::get_table_name();
        
        $wpdb->insert(
            $table_name,
            array(
                'log_time' => current_time('mysql'),
                'log_level' => sanitize_text_field($level),
                'message' => sanitize_text_field($message),
                'context' => maybe_serialize($context),
                'user_id' => get_current_user_id(),
                'ip_address' => self::get_ip_address()
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s')
        );
        
        // Clean old logs (keep last 30 days)
        self::clean_old_logs();
    }
    
    /**
     * Get logs
     */
    public static function get_logs($args = array()) {
        global $wpdb;
        $table_name = self::get_table_name();
        
        $defaults = array(
            'limit' => 100,
            'offset' => 0,
            'level' => '',
            'user_id' => 0,
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        
        if (!empty($args['level'])) {
            $where[] = $wpdb->prepare('log_level = %s', $args['level']);
        }
        
        if (!empty($args['user_id'])) {
            $where[] = $wpdb->prepare('user_id = %d', $args['user_id']);
        }
        
        $where_clause = implode(' AND ', $where);
        $order = ($args['order'] === 'ASC') ? 'ASC' : 'DESC';
        
        $query = "SELECT * FROM {$table_name} 
                  WHERE {$where_clause} 
                  ORDER BY log_time {$order} 
                  LIMIT %d OFFSET %d";
        
        $results = $wpdb->get_results(
            $wpdb->prepare($query, $args['limit'], $args['offset']),
            ARRAY_A
        );
        
        // Unserialize context
        foreach ($results as &$result) {
            $result['context'] = maybe_unserialize($result['context']);
        }
        
        return $results;
    }
    
    /**
     * Get log count
     */
    public static function get_log_count($level = '') {
        global $wpdb;
        $table_name = self::get_table_name();
        
        if (empty($level)) {
            return $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        }
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE log_level = %s",
            $level
        ));
    }
    
    /**
     * Clean old logs
     */
    private static function clean_old_logs() {
        global $wpdb;
        $table_name = self::get_table_name();
        
        // Only clean once per day
        $last_clean = get_transient('dsl_last_log_clean');
        if ($last_clean) {
            return;
        }
        
        $wpdb->query(
            "DELETE FROM {$table_name} 
             WHERE log_time < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        set_transient('dsl_last_log_clean', time(), DAY_IN_SECONDS);
    }
    
    /**
     * Clear all logs
     */
    public static function clear_logs() {
        global $wpdb;
        $table_name = self::get_table_name();
        $wpdb->query("TRUNCATE TABLE {$table_name}");
    }
    
    /**
     * Get client IP address
     */
    private static function get_ip_address() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return sanitize_text_field($ip);
    }
}