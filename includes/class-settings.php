<?php
/**
 * Settings page handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class DSL_Settings {
    
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
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_dsl_test_connection', array($this, 'ajax_test_connection'));
        add_action('admin_notices', array($this, 'show_demo_mode_notice'));
    }
    
    /**
     * Show demo mode notice
     */
    public function show_demo_mode_notice() {
        if (DSL_Demo_Mode::is_enabled()) {
            $screen = get_current_screen();
            if ($screen && $screen->id === 'settings_page_dynamics-sync-lite') {
                echo '<div class="notice notice-warning is-dismissible">
                    <p><strong>' . __('Demo Mode is Active', 'dynamics-sync-lite') . '</strong> - ' . 
                    __('The plugin is using simulated data. Real API calls will not be made.', 'dynamics-sync-lite') . 
                    '</p>
                </div>';
            }
        }
    }
    
    /**
     * Add settings menu
     */
    public function add_menu() {
        add_options_page(
            __('Dynamics Sync Lite', 'dynamics-sync-lite'),
            __('Dynamics Sync', 'dynamics-sync-lite'),
            'manage_options',
            'dynamics-sync-lite',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // General Settings Section (moved to top)
        add_settings_section(
            'dsl_general_settings',
            __('General Settings', 'dynamics-sync-lite'),
            array($this, 'render_general_section'),
            'dynamics-sync-lite'
        );
        
        register_setting('dsl_settings', 'dsl_demo_mode', array(
            'sanitize_callback' => array($this, 'sanitize_checkbox')
        ));
        
        add_settings_field(
            'dsl_demo_mode',
            __('Demo Mode', 'dynamics-sync-lite'),
            array($this, 'render_checkbox_field'),
            'dynamics-sync-lite',
            'dsl_general_settings',
            array(
                'key' => 'dsl_demo_mode', 
                'description' => __('Enable demo mode to test without real Dynamics 365 credentials. The plugin will use simulated data.', 'dynamics-sync-lite')
            )
        );
        
        register_setting('dsl_settings', 'dsl_enable_logging', array(
            'sanitize_callback' => array($this, 'sanitize_checkbox')
        ));
        
        add_settings_field(
            'dsl_enable_logging',
            __('Enable Logging', 'dynamics-sync-lite'),
            array($this, 'render_checkbox_field'),
            'dynamics-sync-lite',
            'dsl_general_settings',
            array(
                'key' => 'dsl_enable_logging', 
                'description' => __('Enable activity logging for debugging', 'dynamics-sync-lite')
            )
        );
        
        // OAuth Settings Section
        add_settings_section(
            'dsl_oauth_settings',
            __('OAuth Login Settings', 'dynamics-sync-lite'),
            array($this, 'render_oauth_section'),
            'dynamics-sync-lite'
        );
        
        register_setting('dsl_settings', 'dsl_enable_oauth_login', array(
            'sanitize_callback' => array($this, 'sanitize_checkbox')
        ));
        
        add_settings_field(
            'dsl_enable_oauth_login',
            __('Enable OAuth Login', 'dynamics-sync-lite'),
            array($this, 'render_checkbox_field'),
            'dynamics-sync-lite',
            'dsl_oauth_settings',
            array(
                'key' => 'dsl_enable_oauth_login', 
                'description' => __('Allow users to log in with Microsoft/Dynamics 365 credentials', 'dynamics-sync-lite')
            )
        );
        
        register_setting('dsl_settings', 'dsl_oauth_auto_register', array(
            'sanitize_callback' => array($this, 'sanitize_checkbox')
        ));
        
        add_settings_field(
            'dsl_oauth_auto_register',
            __('Auto-Register Users', 'dynamics-sync-lite'),
            array($this, 'render_checkbox_field'),
            'dynamics-sync-lite',
            'dsl_oauth_settings',
            array(
                'key' => 'dsl_oauth_auto_register', 
                'description' => __('Automatically create WordPress accounts for new OAuth users', 'dynamics-sync-lite')
            )
        );
        
        register_setting('dsl_settings', 'dsl_oauth_redirect_url', array(
            'sanitize_callback' => 'esc_url_raw'
        ));
        
        add_settings_field(
            'dsl_oauth_redirect_url',
            __('Redirect After Login', 'dynamics-sync-lite'),
            array($this, 'render_url_field'),
            'dynamics-sync-lite',
            'dsl_oauth_settings',
            array('key' => 'dsl_oauth_redirect_url')
        );
        
        // API Settings Section
        add_settings_section(
            'dsl_api_settings',
            __('API Configuration', 'dynamics-sync-lite'),
            array($this, 'render_api_section'),
            'dynamics-sync-lite'
        );
        
        // Register settings
        $settings = array(
            'dsl_client_id' => __('Client ID', 'dynamics-sync-lite'),
            'dsl_client_secret' => __('Client Secret', 'dynamics-sync-lite'),
            'dsl_tenant_id' => __('Tenant ID', 'dynamics-sync-lite'),
            'dsl_resource_url' => __('Resource URL', 'dynamics-sync-lite'),
            'dsl_api_version' => __('API Version', 'dynamics-sync-lite'),
        );
        
        foreach ($settings as $key => $label) {
            register_setting('dsl_settings', $key, array(
                'sanitize_callback' => 'sanitize_text_field'
            ));
            
            add_settings_field(
                $key,
                $label,
                array($this, 'render_field'),
                'dynamics-sync-lite',
                'dsl_api_settings',
                array('key' => $key, 'label' => $label)
            );
        }
    }
    
    /**
     * Render general section description
     */
    public function render_general_section() {
        echo '<p>' . __('Configure general plugin settings.', 'dynamics-sync-lite') . '</p>';
        if (DSL_Demo_Mode::is_enabled()) {
            echo '<p style="color: #d63638; font-weight: bold;">⚠️ ' . 
                 __('Demo Mode is currently enabled. The plugin will use simulated data instead of real API calls.', 'dynamics-sync-lite') . 
                 '</p>';
        }
    }
    
    /**
     * Render OAuth section description
     */
    public function render_oauth_section() {
        echo '<p>' . __('Configure OAuth login to allow users to sign in with their Microsoft/Dynamics 365 accounts.', 'dynamics-sync-lite') . '</p>';
        echo '<p><strong>' . __('Callback URL:', 'dynamics-sync-lite') . '</strong> <code>' . home_url('/dynamics-oauth-callback/') . '</code></p>';
        echo '<p class="description">' . __('Add this callback URL to your Azure AD app registration.', 'dynamics-sync-lite') . '</p>';
        echo '<p><strong>' . __('Login Shortcode:', 'dynamics-sync-lite') . '</strong> <code>[dynamics_login]</code></p>';
    }
    
    /**
     * Render API section description
     */
    public function render_api_section() {
        if (DSL_Demo_Mode::is_enabled()) {
            echo '<p style="color: #666; font-style: italic;">' . 
                 __('API configuration is not required when Demo Mode is enabled.', 'dynamics-sync-lite') . 
                 '</p>';
        } else {
            echo '<p>' . __('Configure your Microsoft Dynamics 365 API credentials. You need to register an application in Azure AD to obtain these credentials.', 'dynamics-sync-lite') . '</p>';
            echo '<p><a href="https://docs.microsoft.com/en-us/power-apps/developer/data-platform/walkthrough-register-app-azure-active-directory" target="_blank">' . __('Learn how to register an app in Azure AD', 'dynamics-sync-lite') . '</a></p>';
        }
    }
    
    /**
     * Render text field
     */
    public function render_field($args) {
        $key = $args['key'];
        $value = get_option($key, '');
        $type = ($key === 'dsl_client_secret') ? 'password' : 'text';
        $placeholder = $this->get_placeholder($key);
        $disabled = DSL_Demo_Mode::is_enabled() ? 'disabled' : '';
        
        echo '<input type="' . esc_attr($type) . '" 
                     id="' . esc_attr($key) . '" 
                     name="' . esc_attr($key) . '" 
                     value="' . esc_attr($value) . '" 
                     placeholder="' . esc_attr($placeholder) . '"
                     class="regular-text" ' . $disabled . ' />';
        
        if ($key === 'dsl_resource_url') {
            echo '<p class="description">' . __('Example: https://yourorg.crm.dynamics.com/', 'dynamics-sync-lite') . '</p>';
        } elseif ($key === 'dsl_api_version') {
            echo '<p class="description">' . __('Default: 9.2', 'dynamics-sync-lite') . '</p>';
        }
        
        if (DSL_Demo_Mode::is_enabled()) {
            echo '<p class="description" style="color: #666; font-style: italic;">' . 
                 __('Disabled in Demo Mode', 'dynamics-sync-lite') . 
                 '</p>';
        }
    }
    
    /**
     * Render checkbox field
     */
    public function render_checkbox_field($args) {
        $key = $args['key'];
        $description = $args['description'] ?? '';
        $value = get_option($key, '1');
        $checked = checked('1', $value, false);
        
        echo '<label>
                <input type="checkbox" 
                       id="' . esc_attr($key) . '" 
                       name="' . esc_attr($key) . '" 
                       value="1" ' . $checked . ' />
                ' . esc_html($description) . '
              </label>';
    }
    
    /**
     * Render URL field
     */
    public function render_url_field($args) {
        $key = $args['key'];
        $value = get_option($key, home_url());
        
        echo '<input type="url" 
                     id="' . esc_attr($key) . '" 
                     name="' . esc_attr($key) . '" 
                     value="' . esc_attr($value) . '" 
                     placeholder="' . esc_attr(home_url()) . '"
                     class="regular-text" />';
        echo '<p class="description">' . __('Where to redirect users after successful OAuth login', 'dynamics-sync-lite') . '</p>';
    }
    
    /**
     * Get placeholder text
     */
    private function get_placeholder($key) {
        $placeholders = array(
            'dsl_client_id' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
            'dsl_client_secret' => 'Your client secret',
            'dsl_tenant_id' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
            'dsl_resource_url' => 'https://yourorg.crm.dynamics.com/',
            'dsl_api_version' => '9.2'
        );
        
        return $placeholders[$key] ?? '';
    }
    
    /**
     * Sanitize checkbox
     */
    public function sanitize_checkbox($value) {
        return ($value === '1') ? '1' : '0';
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Check if settings were saved
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'dsl_messages',
                'dsl_message',
                __('Settings saved successfully!', 'dynamics-sync-lite'),
                'success'
            );
        }
        
        $is_demo = DSL_Demo_Mode::is_enabled();
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php settings_errors('dsl_messages'); ?>
            
            <?php if ($is_demo): ?>
            <div class="notice notice-info inline" style="margin: 20px 0; padding: 15px;">
                <p style="margin: 0;">
                    <span class="dashicons dashicons-info" style="color: #2271b1;"></span>
                    <strong><?php _e('Demo Mode Active', 'dynamics-sync-lite'); ?></strong> - 
                    <?php _e('The plugin is currently running in demo mode with simulated data. No real API calls will be made to Dynamics 365.', 'dynamics-sync-lite'); ?>
                </p>
            </div>
            <?php endif; ?>
            
            <div class="dsl-settings-container">
                <form action="options.php" method="post">
                    <?php
                    settings_fields('dsl_settings');
                    do_settings_sections('dynamics-sync-lite');
                    submit_button(__('Save Settings', 'dynamics-sync-lite'));
                    ?>
                </form>
                
                <div class="dsl-test-connection" style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">
                    <h2><?php _e('Test Connection', 'dynamics-sync-lite'); ?></h2>
                    <?php if ($is_demo): ?>
                    <p><?php _e('Test the demo mode connection (simulated).', 'dynamics-sync-lite'); ?></p>
                    <?php else: ?>
                    <p><?php _e('Test your API credentials to ensure everything is configured correctly.', 'dynamics-sync-lite'); ?></p>
                    <?php endif; ?>
                    <button type="button" class="button button-secondary" id="dsl-test-connection">
                        <?php _e('Test Connection', 'dynamics-sync-lite'); ?>
                    </button>
                    <div id="dsl-test-result" style="margin-top: 15px;"></div>
                </div>
                
                <div class="dsl-info-box" style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-left: 4px solid #2271b1;">
                    <h3><?php _e('Setup Instructions', 'dynamics-sync-lite'); ?></h3>
                    
                    <?php if ($is_demo): ?>
                    <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 20px;">
                        <h4 style="margin-top: 0;"><?php _e('Quick Start with Demo Mode', 'dynamics-sync-lite'); ?></h4>
                        <ol style="margin-bottom: 0;">
                            <li><?php _e('Demo mode is already enabled - no API configuration needed!', 'dynamics-sync-lite'); ?></li>
                            <li><?php _e('Create a page and add the shortcode: <code>[dynamics_user_profile]</code>', 'dynamics-sync-lite'); ?></li>
                            <li><?php _e('Log in as a user and visit the page to test the profile form', 'dynamics-sync-lite'); ?></li>
                            <li><?php _e('All data will be simulated - perfect for testing!', 'dynamics-sync-lite'); ?></li>
                        </ol>
                    </div>
                    <?php endif; ?>
                    
                    <h4><?php _e('Production Setup (with real Dynamics 365)', 'dynamics-sync-lite'); ?></h4>
                    <ol>
                        <li><?php _e('Disable Demo Mode above', 'dynamics-sync-lite'); ?></li>
                        <li><?php _e('Register an application in Azure Active Directory', 'dynamics-sync-lite'); ?></li>
                        <li><?php _e('Grant the application API permissions for Dynamics 365', 'dynamics-sync-lite'); ?></li>
                        <li><?php _e('Generate a client secret for the application', 'dynamics-sync-lite'); ?></li>
                        <li><?php _e('Copy the Application (client) ID, Directory (tenant) ID, and client secret', 'dynamics-sync-lite'); ?></li>
                        <li><?php _e('Enter your Dynamics 365 resource URL (e.g., https://yourorg.crm.dynamics.com/)', 'dynamics-sync-lite'); ?></li>
                        <li><?php _e('Save settings and test the connection', 'dynamics-sync-lite'); ?></li>
                    </ol>
                    
                    <hr style="margin: 20px 0;">
                    
                    <p><strong><?php _e('Shortcodes:', 'dynamics-sync-lite'); ?></strong></p>
                    <ul>
                        <li><code>[dynamics_user_profile]</code> - <?php _e('Display user profile form', 'dynamics-sync-lite'); ?></li>
                        <li><code>[dynamics_login]</code> - <?php _e('Display OAuth login button', 'dynamics-sync-lite'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#dsl-test-connection').on('click', function() {
                var button = $(this);
                var result = $('#dsl-test-result');
                
                button.prop('disabled', true).text('<?php _e('Testing...', 'dynamics-sync-lite'); ?>');
                result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dsl_test_connection',
                        nonce: '<?php echo wp_create_nonce('dsl_test_connection'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                        } else {
                            result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        result.html('<div class="notice notice-error inline"><p><?php _e('Connection test failed', 'dynamics-sync-lite'); ?></p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('Test Connection', 'dynamics-sync-lite'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX test connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('dsl_test_connection', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'dynamics-sync-lite')));
        }
        
        $api = DSL_Dynamics_API::get_instance();
        $result = $api->test_connection();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}