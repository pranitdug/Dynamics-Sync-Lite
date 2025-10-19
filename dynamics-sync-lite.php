<?php
/**
 * Plugin Name: Dynamics Sync Lite
 * Plugin URI: https://github.com/yourusername/dynamics-sync-lite
 * Description: Synchronize WordPress user data with Microsoft Dynamics 365 CRM. Allows users to view and update their contact information.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: dynamics-sync-lite
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package DynamicsSyncLite
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('DSL_VERSION', '1.0.0');
define('DSL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DSL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DSL_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Plugin Class
 */
class Dynamics_Sync_Lite {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Get instance
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
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once DSL_PLUGIN_DIR . 'includes/class-dsl-api.php';
        require_once DSL_PLUGIN_DIR . 'includes/class-dsl-settings.php';
        require_once DSL_PLUGIN_DIR . 'includes/class-dsl-shortcodes.php';
        require_once DSL_PLUGIN_DIR . 'includes/class-dsl-ajax.php';
        require_once DSL_PLUGIN_DIR . 'includes/class-dsl-logger.php';
        require_once DSL_PLUGIN_DIR . 'includes/class-dsl-webhook.php';
        require_once DSL_PLUGIN_DIR . 'includes/class-dsl-dashboard.php';
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('dynamics-sync-lite', false, dirname(DSL_PLUGIN_BASENAME) . '/languages');
    }
    
    /**
     * Initialize plugin components
     */
    public function init() {
        DSL_Settings::get_instance();
        DSL_Shortcodes::get_instance();
        DSL_Ajax::get_instance();
        DSL_Webhook::get_instance();
        DSL_Dashboard::get_instance();
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        if (is_user_logged_in()) {
            wp_enqueue_style('dsl-frontend', DSL_PLUGIN_URL . 'assets/css/frontend.css', array(), DSL_VERSION);
            wp_enqueue_script('dsl-frontend', DSL_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), DSL_VERSION, true);
            
            wp_localize_script('dsl-frontend', 'dslData', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('dsl_nonce'),
                'strings' => array(
                    'loading' => __('Loading...', 'dynamics-sync-lite'),
                    'saving' => __('Saving...', 'dynamics-sync-lite'),
                    'success' => __('Changes saved successfully!', 'dynamics-sync-lite'),
                    'error' => __('An error occurred. Please try again.', 'dynamics-sync-lite')
                )
            ));
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'dynamics-sync-lite') !== false) {
            wp_enqueue_style('dsl-admin', DSL_PLUGIN_URL . 'assets/css/admin.css', array(), DSL_VERSION);
            wp_enqueue_script('dsl-admin', DSL_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), DSL_VERSION, true);
        }
    }
    
    /**
     * Activate plugin
     */
    public function activate() {
        // Create log table
        global $wpdb;
        $table_name = $wpdb->prefix . 'dsl_logs';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            action varchar(50) NOT NULL,
            message text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Set default options
        add_option('dsl_version', DSL_VERSION);
        flush_rewrite_rules();
    }
    
    /**
     * Deactivate plugin
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
}

// Initialize plugin
function dsl_init() {
    return Dynamics_Sync_Lite::get_instance();
}

// Start the plugin
dsl_init();