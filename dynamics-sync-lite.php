<?php

/**
 * Plugin Name: Dynamics Sync Lite
 * Description: Microsoft OAuth login with Dynamics 365 contact management
 * Version: 1.3.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: dynamics-sync-lite
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('DSL_VERSION', '1.3.0');
define('DSL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DSL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DSL_PLUGIN_BASENAME', plugin_basename(__FILE__));

class Dynamics_Sync_Lite
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies()
    {
        // Core classes
        require_once DSL_PLUGIN_DIR . 'includes/class-logger.php';
        require_once DSL_PLUGIN_DIR . 'includes/class-dynamics-api.php';

        // OAuth and profile management
        require_once DSL_PLUGIN_DIR . 'includes/class-oauth-login-session.php';
        require_once DSL_PLUGIN_DIR . 'includes/class-oauth-independent-profile.php';

        // Admin
        require_once DSL_PLUGIN_DIR . 'includes/class-settings.php';
        require_once DSL_PLUGIN_DIR . 'includes/class-admin-widget.php';
    }

    private function init_hooks()
    {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'init'), 5);

        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        add_action('admin_notices', array($this, 'configuration_notice'));
    }

    public function load_textdomain()
    {
        load_plugin_textdomain('dynamics-sync-lite', false, dirname(DSL_PLUGIN_BASENAME) . '/languages');
    }

    public function init()
    {
        // Start session early for OAuth
        if (!session_id() && !headers_sent()) {
            session_start();
        }

        // Initialize all components
        DSL_Settings::get_instance();
        DSL_Admin_Widget::get_instance();
        DSL_OAuth_Login_Session::get_instance();
        DSL_OAuth_Independent_Profile::get_instance();

        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    public function configuration_notice()
    {
        $api = DSL_Dynamics_API::get_instance();

        if ($api->is_configured()) {
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
                <strong>⚠️ Dynamics Sync Lite:</strong>
                Plugin is not configured yet.
                <a href="<?php echo esc_url($settings_url); ?>">Configure now</a> to connect to Dynamics 365.
            </p>
        </div>
<?php
    }

    public function enqueue_public_assets()
    {
        // OAuth profile styles
        wp_enqueue_style(
            'dsl-oauth-profile-style',
            DSL_PLUGIN_URL . 'public/css/oauth-profile-style.css',
            array(),
            DSL_VERSION
        );

        // OAuth profile script
        wp_enqueue_script(
            'dsl-oauth-profile-script',
            DSL_PLUGIN_URL . 'public/js/oauth-profile-script.js',
            array('jquery'),
            DSL_VERSION,
            true
        );

        // Localize scripts
        wp_localize_script('dsl-oauth-profile-script', 'dslAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dsl_ajax_nonce'),
            'strings' => array(
                'updating' => __('Updating...', 'dynamics-sync-lite'),
                'loading' => __('Loading...', 'dynamics-sync-lite'),
                'error' => __('An error occurred. Please try again.', 'dynamics-sync-lite'),
                'success' => __('Changes saved successfully!', 'dynamics-sync-lite')
            )
        ));
    }

    public function enqueue_admin_assets($hook)
    {
        // Admin styles
        wp_enqueue_style(
            'dsl-admin-style',
            DSL_PLUGIN_URL . 'admin/css/admin-style.css',
            array(),
            DSL_VERSION
        );

        // Admin script
        wp_enqueue_script(
            'dsl-admin-script',
            DSL_PLUGIN_URL . 'admin/js/admin-script.js',
            array('jquery'),
            DSL_VERSION,
            true
        );

        // Localize admin script
        wp_localize_script('dsl-admin-script', 'dslAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dsl_admin_nonce')
        ));
    }

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
            'dsl_oauth_redirect_url' => home_url()
        );

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }

        // Register OAuth endpoint
        add_rewrite_rule(
            '^dynamics-oauth-callback/?',
            'index.php?dynamics_oauth_callback=1',
            'top'
        );

        flush_rewrite_rules();

        DSL_Logger::log('info', 'Plugin activated', array(
            'version' => DSL_VERSION
        ));
    }

    public function deactivate()
    {
        flush_rewrite_rules();
        DSL_Logger::log('info', 'Plugin deactivated');
    }

    /**
     * Get plugin version
     */
    public static function get_version()
    {
        return DSL_VERSION;
    }

    /**
     * Check if plugin is fully configured
     */
    public static function is_configured()
    {
        $api = DSL_Dynamics_API::get_instance();
        return $api->is_configured();
    }
}

/**
 * Initialize the plugin
 */
function dsl_init()
{
    return Dynamics_Sync_Lite::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'dsl_init', 0);

/**
 * Helper function to check if user is authenticated via OAuth
 */
function dsl_is_oauth_authenticated()
{
    if (!session_id() && !headers_sent()) {
        session_start();
    }

    return !empty($_SESSION['dsl_oauth_token']) && !empty($_SESSION['dsl_oauth_email']);
}

/**
 * Helper function to get OAuth user email
 */
function dsl_get_oauth_user_email()
{
    if (!session_id() && !headers_sent()) {
        session_start();
    }

    return $_SESSION['dsl_oauth_email'] ?? '';
}
