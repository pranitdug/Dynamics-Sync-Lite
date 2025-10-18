<?php
/**
 * Plugin Name: Dynamics Sync Lite - OAuth Edition
 * Description: Microsoft OAuth login with Dynamics 365 profile management (No WordPress users required)
 * Version: 1.1.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: dynamics-sync-lite
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('DSL_VERSION', '1.1.0');
define('DSL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DSL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DSL_PLUGIN_BASENAME', plugin_basename(__FILE__));

class Dynamics_Sync_Lite {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    private function load_dependencies() {
        require_once DSL_PLUGIN_DIR . 'includes/class-logger.php';
        require_once DSL_PLUGIN_DIR . 'includes/class-demo-mode.php';
        require_once DSL_PLUGIN_DIR . 'includes/class-dynamics-api.php';
        require_once DSL_PLUGIN_DIR . 'includes/class-oauth-login-session.php';
        require_once DSL_PLUGIN_DIR . 'includes/class-oauth-independent-profile.php';
        require_once DSL_PLUGIN_DIR . 'includes/class-settings.php';
    }
    
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'init'), 5);
        
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('admin_notices', array($this, 'configuration_notice'));
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('dynamics-sync-lite', false, dirname(DSL_PLUGIN_BASENAME) . '/languages');
    }
    
    public function init() {
        // Start session early
        if (!session_id() && !headers_sent()) {
            session_start();
        }
        
        // Initialize components
        DSL_Settings::get_instance();
        DSL_OAuth_Login_Session::get_instance();
        DSL_OAuth_Independent_Profile::get_instance();
        
        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    public function configuration_notice() {
        $api = DSL_Dynamics_API::get_instance();
        
        if (DSL_Demo_Mode::is_enabled() || $api->is_configured()) {
            return;
        }
        
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, array('dashboard', 'plugins', 'settings_page_dynamics-sync-lite'))) {
            return;
        }
        
        $settings_url = admin_url('options-general.php?page=dynamics-sync-lite');
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong>Dynamics Sync Lite:</strong>
                Plugin is not configured yet.
                <a href="<?php echo esc_url($settings_url); ?>">Configure now</a>
                or enable Demo Mode for testing.
            </p>
        </div>
        <?php
    }
    
    public function enqueue_public_assets() {
        wp_enqueue_style(
            'dsl-oauth-profile-style',
            DSL_PLUGIN_URL . 'public/css/oauth-profile-style.css',
            array(),
            DSL_VERSION
        );
        
        wp_enqueue_script(
            'dsl-oauth-profile-script',
            DSL_PLUGIN_URL . 'public/js/oauth-profile-script.js',
            array('jquery'),
            DSL_VERSION,
            true
        );
        
        wp_localize_script('dsl-oauth-profile-script', 'dslAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dsl_ajax_nonce'),
            'strings' => array(
                'updating' => 'Updating...',
                'loading' => 'Loading...',
                'error' => 'An error occurred. Please try again.'
            )
        ));
    }
    
    public function enqueue_admin_assets($hook) {
        wp_enqueue_style(
            'dsl-admin-style',
            DSL_PLUGIN_URL . 'admin/css/admin-style.css',
            array(),
            DSL_VERSION
        );
        
        wp_enqueue_script(
            'dsl-admin-script',
            DSL_PLUGIN_URL . 'admin/js/admin-script.js',
            array('jquery'),
            DSL_VERSION,
            true
        );
    }
    
    public function activate() {
        DSL_Logger::create_table();
        
        $defaults = array(
            'dsl_client_id' => '',
            'dsl_client_secret' => '',
            'dsl_tenant_id' => '',
            'dsl_resource_url' => '',
            'dsl_api_version' => '9.2',
            'dsl_enable_logging' => '1',
            'dsl_demo_mode' => '0',
            'dsl_oauth_redirect_url' => home_url()
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
        
        // Register OAuth endpoint
        add_rewrite_rule(
            '^dynamics-oauth-callback/?$',
            'index.php?dynamics_oauth_callback=1',
            'top'
        );
        
        flush_rewrite_rules();
        DSL_Logger::log('info', 'Plugin activated');
    }
    
    public function deactivate() {
        flush_rewrite_rules();
        DSL_Logger::log('info', 'Plugin deactivated');
    }
}

function dsl_init() {
    return Dynamics_Sync_Lite::get_instance();
}

add_action('plugins_loaded', 'dsl_init', 0);