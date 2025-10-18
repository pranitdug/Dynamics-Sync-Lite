<?php
/**
 * Settings Page - OAuth Edition
 */

if (!defined('ABSPATH')) {
    exit;
}

class DSL_Settings {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_dsl_test_connection', array($this, 'ajax_test_connection'));
    }
    
    public function add_menu() {
        add_options_page(
            'Dynamics Sync Lite',
            'Dynamics Sync',
            'manage_options',
            'dynamics-sync-lite',
            array($this, 'render_settings_page')
        );
    }
    
    public function register_settings() {
        // Demo Mode Section
        add_settings_section(
            'dsl_demo_settings',
            'Demo Mode',
            array($this, 'render_demo_section'),
            'dynamics-sync-lite'
        );
        
        register_setting('dsl_settings', 'dsl_demo_mode', array(
            'sanitize_callback' => array($this, 'sanitize_checkbox')
        ));
        
        add_settings_field(
            'dsl_demo_mode',
            'Enable Demo Mode',
            array($this, 'render_checkbox_field'),
            'dynamics-sync-lite',
            'dsl_demo_settings',
            array(
                'key' => 'dsl_demo_mode',
                'description' => 'Test the plugin without real Dynamics credentials'
            )
        );
        
        // API Configuration Section
        add_settings_section(
            'dsl_api_settings',
            'API Configuration',
            array($this, 'render_api_section'),
            'dynamics-sync-lite'
        );
        
        $api_fields = array(
            'dsl_client_id' => 'Client ID',
            'dsl_client_secret' => 'Client Secret',
            'dsl_tenant_id' => 'Tenant ID',
            'dsl_resource_url' => 'Resource URL',
            'dsl_api_version' => 'API Version'
        );
        
        foreach ($api_fields as $key => $label) {
            register_setting('dsl_settings', $key, array(
                'sanitize_callback' => 'sanitize_text_field'
            ));
            
            add_settings_field(
                $key,
                $label,
                array($this, 'render_field'),
                'dynamics-sync-lite',
                'dsl_api_settings',
                array('key' => $key)
            );
        }
        
        // Redirect URL
        register_setting('dsl_settings', 'dsl_oauth_redirect_url', array(
            'sanitize_callback' => 'esc_url_raw'
        ));
        
        add_settings_field(
            'dsl_oauth_redirect_url',
            'Redirect After Login',
            array($this, 'render_url_field'),
            'dynamics-sync-lite',
            'dsl_api_settings',
            array('key' => 'dsl_oauth_redirect_url')
        );
        
        // Logging
        register_setting('dsl_settings', 'dsl_enable_logging', array(
            'sanitize_callback' => array($this, 'sanitize_checkbox')
        ));
        
        add_settings_field(
            'dsl_enable_logging',
            'Enable Logging',
            array($this, 'render_checkbox_field'),
            'dynamics-sync-lite',
            'dsl_api_settings',
            array(
                'key' => 'dsl_enable_logging',
                'description' => 'Log API calls for debugging'
            )
        );
    }
    
    public function render_demo_section() {
        if (DSL_Demo_Mode::is_enabled()) {
            echo '<p style="color: #d63638; font-weight: bold;">⚠️ Demo Mode is ACTIVE - using simulated data</p>';
        } else {
            echo '<p>Enable demo mode to test without real API credentials.</p>';
        }
    }
    
    public function render_api_section() {
        $is_demo = DSL_Demo_Mode::is_enabled();
        
        if ($is_demo) {
            echo '<p style="color: #666; font-style: italic;">API configuration not required in Demo Mode.</p>';
        } else {
            echo '<p>Configure your Microsoft Dynamics 365 API credentials.</p>';
            echo '<p><strong>OAuth Callback URL:</strong> <code>' . home_url('/dynamics-oauth-callback/') . '</code></p>';
            echo '<p class="description">Add this URL to your Azure AD app registration.</p>';
        }
    }
    
    public function render_field($args) {
        $key = $args['key'];
        $value = get_option($key, '');
        $type = ($key === 'dsl_client_secret') ? 'password' : 'text';
        $disabled = DSL_Demo_Mode::is_enabled() ? 'disabled' : '';
        
        $placeholder = '';
        if ($key === 'dsl_resource_url') {
            $placeholder = 'https://yourorg.crm.dynamics.com/';
        } elseif ($key === 'dsl_api_version') {
            $placeholder = '9.2';
        }
        
        echo '<input type="' . esc_attr($type) . '" 
                     id="' . esc_attr($key) . '" 
                     name="' . esc_attr($key) . '" 
                     value="' . esc_attr($value) . '" 
                     placeholder="' . esc_attr($placeholder) . '"
                     class="regular-text" ' . $disabled . ' />';
        
        if ($key === 'dsl_resource_url') {
            echo '<p class="description">Example: https://yourorg.crm.dynamics.com/</p>';
        } elseif ($key === 'dsl_api_version') {
            echo '<p class="description">Default: 9.2</p>';
        }
    }
    
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
    
    public function render_url_field($args) {
        $key = $args['key'];
        $value = get_option($key, home_url());
        
        echo '<input type="url" 
                     id="' . esc_attr($key) . '" 
                     name="' . esc_attr($key) . '" 
                     value="' . esc_attr($value) . '" 
                     class="regular-text" />';
        echo '<p class="description">Where to redirect users after OAuth login</p>';
    }
    
    public function sanitize_checkbox($value) {
        return ($value === '1') ? '1' : '0';
    }
    
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'dsl_messages',
                'dsl_message',
                'Settings saved successfully!',
                'success'
            );
        }
        
        $is_demo = DSL_Demo_Mode::is_enabled();
        
        ?>
        <div class="wrap">
            <h1>Dynamics Sync Lite - OAuth Edition</h1>
            
            <?php settings_errors('dsl_messages'); ?>
            
            <?php if ($is_demo): ?>
            <div class="notice notice-info inline" style="margin: 20px 0; padding: 15px;">
                <p style="margin: 0;">
                    <strong>Demo Mode Active</strong> - Using simulated data. No real API calls.
                </p>
            </div>
            <?php endif; ?>
            
            <form action="options.php" method="post">
                <?php
                settings_fields('dsl_settings');
                do_settings_sections('dynamics-sync-lite');
                submit_button('Save Settings');
                ?>
            </form>
            
            <div class="dsl-test-connection" style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">
                <h2>Test Connection</h2>
                <p>Test your API configuration.</p>
                <button type="button" class="button button-secondary" id="dsl-test-connection">
                    Test Connection
                </button>
                <div id="dsl-test-result" style="margin-top: 15px;"></div>
            </div>
            
            <div class="dsl-info-box" style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-left: 4px solid #2271b1;">
                <h3>Quick Start Guide</h3>
                
                <?php if ($is_demo): ?>
                <div style="background: #fff3cd; padding: 15px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
                    <h4 style="margin-top: 0;">Demo Mode Steps</h4>
                    <ol style="margin-bottom: 0;">
                        <li>Demo mode is enabled - no API setup needed!</li>
                        <li>Create a page and add: <code>[dynamics_profile_oauth]</code></li>
                        <li>Visit the page and click "Sign In with Microsoft"</li>
                        <li>You'll be logged in as a demo user automatically</li>
                    </ol>
                </div>
                <?php endif; ?>
                
                <h4>Production Setup</h4>
                <ol>
                    <li>Disable Demo Mode</li>
                    <li>Register an app in Azure Active Directory</li>
                    <li>Add OAuth callback URL: <code><?php echo home_url('/dynamics-oauth-callback/'); ?></code></li>
                    <li>Grant Dynamics 365 API permissions</li>
                    <li>Enter Client ID, Client Secret, Tenant ID above</li>
                    <li>Enter your Dynamics 365 Resource URL</li>
                    <li>Save and test connection</li>
                </ol>
                
                <hr style="margin: 20px 0;">
                
                <h4>Usage</h4>
                <p><strong>Login Button:</strong> <code>[dynamics_login_independent]</code></p>
                <p><strong>Profile Form:</strong> <code>[dynamics_profile_oauth]</code></p>
                
                <p class="description">Users can sign in with Microsoft and manage their Dynamics 365 contact info without needing a WordPress account.</p>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#dsl-test-connection').on('click', function() {
                var btn = $(this);
                var result = $('#dsl-test-result');
                
                btn.prop('disabled', true).text('Testing...');
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
                        result.html('<div class="notice notice-error inline"><p>Connection test failed</p></div>');
                    },
                    complete: function() {
                        btn.prop('disabled', false).text('Test Connection');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    public function ajax_test_connection() {
        check_ajax_referer('dsl_test_connection', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
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