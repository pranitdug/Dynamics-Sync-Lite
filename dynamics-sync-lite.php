<?php

/**
 * Plugin Name: Dynamics Sync Lite
 * Plugin URI: https://github.com/yourusername/dynamics-sync-lite
 * Description: Seamlessly sync WordPress user data with Microsoft Dynamics 365. Allows users to view and update their contact information in real-time. Supports OAuth login with Microsoft/Dynamics 365 credentials.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: dynamics-sync-lite
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('DSL_VERSION', '1.0.0');
define('DSL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DSL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DSL_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class Dynamics_Sync_Lite
{

    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies()
    {
        require_once DSL_PLUGIN_DIR . 'includes/class-logger.php';
        require_once DSL_PLUGIN_DIR . 'includes/class-demo-mode.php';
        require_once DSL_PLUGIN_DIR . 'includes/class-dynamics-api.php';
        require_once DSL_PLUGIN_DIR . 'includes/class-oauth-login.php';
        require_once DSL_PLUGIN_DIR . 'includes/class-settings.php';
        require_once DSL_PLUGIN_DIR . 'includes/class-user-profile.php';
        require_once DSL_PLUGIN_DIR . 'includes/class-admin-widget.php';
        require_once DSL_PLUGIN_DIR . 'includes/class-oauth-independent-profile.php';
        require_once DSL_PLUGIN_DIR . 'includes/class-oauth-login-session.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'init'));

        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Load plugin text domain
     */
    public function load_textdomain()
    {
        load_plugin_textdomain(
            'dynamics-sync-lite',
            false,
            dirname(DSL_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Initialize plugin components
     */
    public function init()
    {
        // Enable sessions for OAuth
        if (!session_id()) {
            session_start();
        }
        // Initialize settings
        DSL_Settings::get_instance();

        // Initialize OAuth login
        DSL_OAuth_Login::get_instance();

        // Initialize user profile handler
        DSL_User_Profile::get_instance();

        // Initialize admin widget
        if (is_admin()) {
            DSL_Admin_Widget::get_instance();
        }

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Enqueue public assets
     */
    public function enqueue_public_assets()
    {
        wp_enqueue_style(
            'dsl-public-style',
            DSL_PLUGIN_URL . 'public/css/public-style.css',
            array(),
            DSL_VERSION
        );

        wp_enqueue_script(
            'dsl-public-script',
            DSL_PLUGIN_URL . 'public/js/public-script.js',
            array('jquery'),
            DSL_VERSION,
            true
        );

        wp_localize_script('dsl-public-script', 'dslAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dsl_ajax_nonce'),
            'strings' => array(
                'updating' => __('Updating...', 'dynamics-sync-lite'),
                'loading' => __('Loading...', 'dynamics-sync-lite'),
                'success' => __('Profile updated successfully!', 'dynamics-sync-lite'),
                'error' => __('An error occurred. Please try again.', 'dynamics-sync-lite')
            )
        ));
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
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook)
    {
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

    /**
     * Activation hook
     */
    public function activate()
    {
        // Create log table
        DSL_Logger::create_table();

        // Set default options
        $defaults = array(
            'dsl_client_id' => '',
            'dsl_client_secret' => '',
            'dsl_tenant_id' => '',
            'dsl_resource_url' => '',
            'dsl_api_version' => '9.2',
            'dsl_enable_logging' => '1',
            'dsl_demo_mode' => '0',
            'dsl_enable_oauth_login' => '0',
            'dsl_oauth_auto_register' => '1',
            'dsl_oauth_redirect_url' => home_url()
        );

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }

        // Register OAuth endpoint
        DSL_OAuth_Login::get_instance()->register_endpoints();

        flush_rewrite_rules();

        DSL_Logger::log('info', 'Plugin activated');
    }

    /**
     * Deactivation hook
     */
    public function deactivate()
    {
        flush_rewrite_rules();

        DSL_Logger::log('info', 'Plugin deactivated');
    }
}

// Initialize the plugin
function dsl_init()
{
    return Dynamics_Sync_Lite::get_instance();
}

// Start the plugin
dsl_init();
