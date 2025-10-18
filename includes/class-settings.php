<?php
/**
 * Settings Page - Production Ready
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
        add_action('wp_ajax_dsl_clear_logs', array($this, 'ajax_clear_logs'));
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
        // API Configuration Section
        add_settings_section(
            'dsl_api_settings',
            '🔧 API Configuration',
            array($this, 'render_api_section'),
            'dynamics-sync-lite'
        );
        
        $api_fields = array(
            'dsl_client_id' => array(
                'label' => 'Client ID',
                'type' => 'text',
                'placeholder' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
                'description' => 'Azure AD Application (Client) ID'
            ),
            'dsl_client_secret' => array(
                'label' => 'Client Secret',
                'type' => 'password',
                'placeholder' => 'Your client secret value',
                'description' => 'Azure AD Client Secret (never share this)'
            ),
            'dsl_tenant_id' => array(
                'label' => 'Tenant ID',
                'type' => 'text',
                'placeholder' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
                'description' => 'Azure AD Directory (Tenant) ID'
            ),
            'dsl_resource_url' => array(
                'label' => 'Resource URL',
                'type' => 'url',
                'placeholder' => 'https://yourorg.crm.dynamics.com/',
                'description' => 'Your Dynamics 365 instance URL (must end with /)'
            ),
            'dsl_api_version' => array(
                'label' => 'API Version',
                'type' => 'text',
                'placeholder' => '9.2',
                'description' => 'Dynamics 365 Web API version (default: 9.2)'
            )
        );
        
        foreach ($api_fields as $key => $field) {
            register_setting('dsl_settings', $key, array(
                'sanitize_callback' => $field['type'] === 'url' ? 'esc_url_raw' : 'sanitize_text_field'
            ));
            
            add_settings_field(
                $key,
                $field['label'],
                array($this, 'render_text_field'),
                'dynamics-sync-lite',
                'dsl_api_settings',
                array(
                    'key' => $key,
                    'type' => $field['type'],
                    'placeholder' => $field['placeholder'],
                    'description' => $field['description']
                )
            );
        }
        
        // OAuth Settings Section
        add_settings_section(
            'dsl_oauth_settings',
            '🔐 OAuth Settings',
            array($this, 'render_oauth_section'),
            'dynamics-sync-lite'
        );
        
        register_setting('dsl_settings', 'dsl_oauth_redirect_url', array(
            'sanitize_callback' => 'esc_url_raw'
        ));
        
        add_settings_field(
            'dsl_oauth_redirect_url',
            'Redirect After Login',
            array($this, 'render_text_field'),
            'dynamics-sync-lite',
            'dsl_oauth_settings',
            array(
                'key' => 'dsl_oauth_redirect_url',
                'type' => 'url',
                'placeholder' => home_url(),
                'description' => 'Where to redirect users after successful OAuth login'
            )
        );
        
        // Advanced Settings Section
        add_settings_section(
            'dsl_advanced_settings',
            '⚙️ Advanced Settings',
            array($this, 'render_advanced_section'),
            'dynamics-sync-lite'
        );
        
        register_setting('dsl_settings', 'dsl_enable_logging', array(
            'sanitize_callback' => array($this, 'sanitize_checkbox')
        ));
        
        add_settings_field(
            'dsl_enable_logging',
            'Enable Logging',
            array($this, 'render_checkbox_field'),
            'dynamics-sync-lite',
            'dsl_advanced_settings',
            array(
                'key' => 'dsl_enable_logging',
                'description' => 'Log API calls and user actions for debugging'
            )
        );
    }
    
    public function render_api_section() {
        echo '<p>Configure your Microsoft Dynamics 365 and Azure AD credentials.</p>';
        
        echo '<div style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; margin-top: 15px;">';
        echo '<strong>📋 OAuth Callback URL:</strong><br/>';
        echo '<code style="background: #fff; padding: 5px 10px; display: inline-block; margin-top: 5px;">' . home_url('/dynamics-oauth-callback/') . '</code><br/>';
        echo '<small style="color: #666;">Add this URL to your Azure AD app registration as a redirect URI.</small>';
        echo '</div>';
    }
    
    public function render_oauth_section() {
        echo '<p>Configure OAuth redirect behavior.</p>';
    }
    
    public function render_advanced_section() {
        $log_count = DSL_Logger::get_log_count();
        echo '<p>Advanced plugin settings and maintenance options.</p>';
        
        if ($log_count > 0) {
            echo '<div style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; margin-top: 15px;">';
            echo '<strong>📊 Log Statistics:</strong><br/>';
            echo 'Total logs: <strong>' . number_format($log_count) . '</strong><br/>';
            echo 'Success: <strong>' . DSL_Logger::get_log_count('success') . '</strong> | ';
            echo 'Errors: <strong>' . DSL_Logger::get_log_count('error') . '</strong> | ';
            echo 'Info: <strong>' . DSL_Logger::get_log_count('info') . '</strong><br/>';
            echo '<button type="button" class="button button-secondary" id="dsl-clear-logs" style="margin-top: 10px;">Clear All Logs</button>';
            echo '</div>';
        }
    }
    
    public function render_text_field($args) {
        $key = $args['key'];
        $type = $args['type'] ?? 'text';
        $placeholder = $args['placeholder'] ?? '';
        $description = $args['description'] ?? '';
        $value = get_option($key, '');
        
        echo '<input type="' . esc_attr($type) . '" 
                     id="' . esc_attr($key) . '" 
                     name="' . esc_attr($key) . '" 
                     value="' . esc_attr($value) . '" 
                     placeholder="' . esc_attr($placeholder) . '"
                     class="regular-text" />';
        
        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
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
                '✅ Settings saved successfully!',
                'success'
            );
        }
        
        $api = DSL_Dynamics_API::get_instance();
        $is_configured = $api->is_configured();
        
        ?>
        <div class="wrap">
            <h1>⚙️ Dynamics Sync Lite Settings</h1>
            
            <?php settings_errors('dsl_messages'); ?>
            
            <?php if (!$is_configured): ?>
            <div class="notice notice-warning inline" style="margin: 20px 0; padding: 15px;">
                <p style="margin: 0;">
                    <strong>⚠️ Configuration Required</strong> - Please fill in all API credentials below to connect to Dynamics 365.
                </p>
            </div>
            <?php else: ?>
            <div class="notice notice-success inline" style="margin: 20px 0; padding: 15px;">
                <p style="margin: 0;">
                    <strong>✅ Plugin Configured</strong> - API credentials are set. Test your connection below.
                </p>
            </div>
            <?php endif; ?>
            
            <form action="options.php" method="post">
                <?php
                settings_fields('dsl_settings');
                do_settings_sections('dynamics-sync-lite');
                submit_button('💾 Save Settings');
                ?>
            </form>
            
            <!-- Connection Test -->
            <div class="dsl-test-connection" style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h2>🔗 Test Connection</h2>
                <p>Test your API configuration and verify connectivity with Dynamics 365.</p>
                <button type="button" class="button button-secondary" id="dsl-test-connection">
                    🔍 Test Connection
                </button>
                <div id="dsl-test-result" style="margin-top: 15px;"></div>
            </div>
            
            <!-- Quick Start Guide -->
            <div class="dsl-info-box" style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-left: 4px solid #2271b1; border-radius: 4px;">
                <h3>📚 Quick Start Guide</h3>
                
                <h4>🚀 Setup Steps</h4>
                <ol>
                    <li>Register an application in Azure Active Directory</li>
                    <li>Configure API permissions for Dynamics 365</li>
                    <li>Add the OAuth callback URL to your Azure app</li>
                    <li>Enter Client ID, Client Secret, and Tenant ID above</li>
                    <li>Enter your Dynamics 365 Resource URL</li>
                    <li>Save settings and test the connection</li>
                </ol>
                
                <hr style="margin: 20px 0;">
                
                <h4>📝 How to Use</h4>
                <p><strong>Step 1:</strong> Create a page for login</p>
                <p>Add this shortcode: <code>[dynamics_profile_oauth]</code></p>
                <p><strong>Step 2:</strong> Users can:</p>
                <ul style="margin-left: 20px;">
                    <li>Click "Sign In with Microsoft"</li>
                    <li>Authenticate with their Microsoft account</li>
                    <li>View and edit their Dynamics 365 contact information</li>
                    <li>Changes sync automatically to Dynamics</li>
                    <li>No WordPress account needed!</li>
                </ul>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Test Connection
            $('#dsl-test-connection').on('click', function() {
                var btn = $(this);
                var result = $('#dsl-test-result');
                
                btn.prop('disabled', true).text('🔄 Testing...');
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
                            result.html('<div class="notice notice-error inline"><p>❌ ' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        result.html('<div class="notice notice-error inline"><p>❌ Connection test failed - network error</p></div>');
                    },
                    complete: function() {
                        btn.prop('disabled', false).text('🔍 Test Connection');
                    }
                });
            });
            
            // Clear Logs
            $('#dsl-clear-logs').on('click', function() {
                if (!confirm('Are you sure you want to clear all logs? This cannot be undone.')) {
                    return;
                }
                
                var btn = $(this);
                btn.prop('disabled', true).text('Clearing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dsl_clear_logs',
                        nonce: '<?php echo wp_create_nonce('dsl_clear_logs'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('✅ Logs cleared successfully!');
                            location.reload();
                        } else {
                            alert('❌ Failed to clear logs');
                        }
                    },
                    error: function() {
                        alert('❌ Network error');
                    },
                    complete: function() {
                        btn.prop('disabled', false).text('Clear All Logs');
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
    
    public function ajax_clear_logs() {
        check_ajax_referer('dsl_clear_logs', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        DSL_Logger::clear_logs();
        
        wp_send_json_success(array(
            'message' => 'All logs cleared successfully'
        ));
    }
}