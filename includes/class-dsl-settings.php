<?php
/**
 * Settings Page Handler
 *
 * @package DynamicsSyncLite
 */

// Exit if accessed directly
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_dsl_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_dsl_regenerate_webhook_secret', array($this, 'ajax_regenerate_webhook_secret'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Dynamics Sync Lite', 'dynamics-sync-lite'),
            __('Dynamics Sync', 'dynamics-sync-lite'),
            'manage_options',
            'dynamics-sync-lite',
            array($this, 'render_settings_page'),
            'dashicons-update',
            80
        );
        
        add_submenu_page(
            'dynamics-sync-lite',
            __('Settings', 'dynamics-sync-lite'),
            __('Settings', 'dynamics-sync-lite'),
            'manage_options',
            'dynamics-sync-lite',
            array($this, 'render_settings_page')
        );
        
        add_submenu_page(
            'dynamics-sync-lite',
            __('Logs', 'dynamics-sync-lite'),
            __('Logs', 'dynamics-sync-lite'),
            'manage_options',
            'dynamics-sync-lite-logs',
            array($this, 'render_logs_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('dsl_settings', 'dsl_client_id', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));
        
        register_setting('dsl_settings', 'dsl_client_secret', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));
        
        register_setting('dsl_settings', 'dsl_tenant_id', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));
        
        register_setting('dsl_settings', 'dsl_resource_url', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => ''
        ));
        
        register_setting('dsl_settings', 'dsl_enable_logging', array(
            'type' => 'boolean',
            'default' => true
        ));
        
        // Generate webhook secret if it doesn't exist
        if (!get_option('dsl_webhook_secret')) {
            add_option('dsl_webhook_secret', wp_generate_password(32, false));
        }
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Save settings
        if (isset($_POST['dsl_save_settings']) && check_admin_referer('dsl_settings_nonce')) {
            update_option('dsl_client_id', sanitize_text_field($_POST['dsl_client_id']));
            update_option('dsl_client_secret', sanitize_text_field($_POST['dsl_client_secret']));
            update_option('dsl_tenant_id', sanitize_text_field($_POST['dsl_tenant_id']));
            update_option('dsl_resource_url', esc_url_raw($_POST['dsl_resource_url']));
            update_option('dsl_enable_logging', isset($_POST['dsl_enable_logging']) ? 1 : 0);
            
            echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'dynamics-sync-lite') . '</p></div>';
        }
        
        $client_id = get_option('dsl_client_id', '');
        $client_secret = get_option('dsl_client_secret', '');
        $tenant_id = get_option('dsl_tenant_id', '');
        $resource_url = get_option('dsl_resource_url', '');
        $enable_logging = get_option('dsl_enable_logging', true);
        $webhook_secret = get_option('dsl_webhook_secret', '');
        $webhook_url = rest_url('dynamics-sync-lite/v1/webhook');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('dsl_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="dsl_client_id"><?php _e('Client ID', 'dynamics-sync-lite'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="dsl_client_id" name="dsl_client_id" 
                                   value="<?php echo esc_attr($client_id); ?>" class="regular-text" required>
                            <p class="description">
                                <?php _e('Azure AD Application Client ID', 'dynamics-sync-lite'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="dsl_client_secret"><?php _e('Client Secret', 'dynamics-sync-lite'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="dsl_client_secret" name="dsl_client_secret" 
                                   value="<?php echo esc_attr($client_secret); ?>" class="regular-text" required>
                            <p class="description">
                                <?php _e('Azure AD Application Client Secret', 'dynamics-sync-lite'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="dsl_tenant_id"><?php _e('Tenant ID', 'dynamics-sync-lite'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="dsl_tenant_id" name="dsl_tenant_id" 
                                   value="<?php echo esc_attr($tenant_id); ?>" class="regular-text" required>
                            <p class="description">
                                <?php _e('Azure AD Tenant ID', 'dynamics-sync-lite'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="dsl_resource_url"><?php _e('Dynamics 365 URL', 'dynamics-sync-lite'); ?></label>
                        </th>
                        <td>
                            <input type="url" id="dsl_resource_url" name="dsl_resource_url" 
                                   value="<?php echo esc_attr($resource_url); ?>" class="regular-text" 
                                   placeholder="https://yourorg.crm.dynamics.com" required>
                            <p class="description">
                                <?php _e('Your Dynamics 365 instance URL', 'dynamics-sync-lite'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="dsl_enable_logging"><?php _e('Enable Logging', 'dynamics-sync-lite'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="dsl_enable_logging" name="dsl_enable_logging" 
                                       value="1" <?php checked($enable_logging, 1); ?>>
                                <?php _e('Log API calls and user actions', 'dynamics-sync-lite'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label><?php _e('Webhook URL', 'dynamics-sync-lite'); ?></label>
                        </th>
                        <td>
                            <input type="text" value="<?php echo esc_url($webhook_url); ?>" 
                                   class="regular-text" readonly>
                            <button type="button" class="button" onclick="navigator.clipboard.writeText('<?php echo esc_url($webhook_url); ?>')">
                                <?php _e('Copy', 'dynamics-sync-lite'); ?>
                            </button>
                            <p class="description">
                                <?php _e('Use this URL in Dynamics 365 webhook configuration', 'dynamics-sync-lite'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label><?php _e('Webhook Secret', 'dynamics-sync-lite'); ?></label>
                        </th>
                        <td>
                            <input type="text" value="<?php echo esc_attr($webhook_secret); ?>" 
                                   class="regular-text" readonly>
                            <button type="button" class="button" onclick="navigator.clipboard.writeText('<?php echo esc_attr($webhook_secret); ?>')">
                                <?php _e('Copy', 'dynamics-sync-lite'); ?>
                            </button>
                            <button type="button" id="dsl-regenerate-secret" class="button">
                                <?php _e('Regenerate', 'dynamics-sync-lite'); ?>
                            </button>
                            <p class="description">
                                <?php _e('Use this secret to secure webhook requests', 'dynamics-sync-lite'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="dsl_save_settings" class="button button-primary" 
                           value="<?php _e('Save Settings', 'dynamics-sync-lite'); ?>">
                    <button type="button" id="dsl_test_connection" class="button">
                        <?php _e('Test Connection', 'dynamics-sync-lite'); ?>
                    </button>
                    <span id="dsl_connection_status" style="margin-left: 10px;"></span>
                </p>
            </form>
            
            <hr>
            
            <h2><?php _e('Usage Instructions', 'dynamics-sync-lite'); ?></h2>
            <div class="card">
                <h3><?php _e('Shortcode', 'dynamics-sync-lite'); ?></h3>
                <p><?php _e('Use this shortcode to display the contact form:', 'dynamics-sync-lite'); ?></p>
                <code>[dynamics_contact_form]</code>
                <p><?php _e('Place it on any page or post where logged-in users can update their information.', 'dynamics-sync-lite'); ?></p>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#dsl_test_connection').on('click', function() {
                var button = $(this);
                var status = $('#dsl_connection_status');
                
                button.prop('disabled', true).text('<?php _e('Testing...', 'dynamics-sync-lite'); ?>');
                status.html('');
                
                $.post(ajaxurl, {
                    action: 'dsl_test_connection',
                    nonce: '<?php echo wp_create_nonce('dsl_test_connection'); ?>'
                }, function(response) {
                    button.prop('disabled', false).text('<?php _e('Test Connection', 'dynamics-sync-lite'); ?>');
                    
                    if (response.success) {
                        status.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                    } else {
                        status.html('<span style="color: red;">✗ ' + response.data + '</span>');
                    }
                });
            });
            
            $('#dsl-regenerate-secret').on('click', function() {
                if (!confirm('<?php _e('Are you sure? This will invalidate the current webhook secret.', 'dynamics-sync-lite'); ?>')) {
                    return;
                }
                
                var button = $(this);
                button.prop('disabled', true).text('<?php _e('Regenerating...', 'dynamics-sync-lite'); ?>');
                
                $.post(ajaxurl, {
                    action: 'dsl_regenerate_webhook_secret',
                    nonce: '<?php echo wp_create_nonce('dsl_regenerate_secret'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('<?php _e('Failed to regenerate secret', 'dynamics-sync-lite'); ?>');
                        button.prop('disabled', false).text('<?php _e('Regenerate', 'dynamics-sync-lite'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX: Regenerate webhook secret
     */
    public function ajax_regenerate_webhook_secret() {
        check_ajax_referer('dsl_regenerate_secret', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'dynamics-sync-lite'));
        }
        
        $new_secret = wp_generate_password(32, false);
        update_option('dsl_webhook_secret', $new_secret);
        
        DSL_Logger::log_action(get_current_user_id(), 'webhook_secret_regenerated', 'Webhook secret was regenerated');
        
        wp_send_json_success(array(
            'message' => __('Webhook secret regenerated successfully', 'dynamics-sync-lite'),
            'secret' => $new_secret
        ));
    }
    
    /**
     * Render logs page
     */
    public function render_logs_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'dsl_logs';
        
        // Handle log clear
        if (isset($_POST['dsl_clear_logs']) && check_admin_referer('dsl_clear_logs_nonce')) {
            $wpdb->query("TRUNCATE TABLE $table_name");
            echo '<div class="notice notice-success"><p>' . __('Logs cleared successfully!', 'dynamics-sync-lite') . '</p></div>';
        }
        
        $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 100");
        ?>
        <div class="wrap">
            <h1><?php _e('Activity Logs', 'dynamics-sync-lite'); ?></h1>
            
            <form method="post" style="margin-bottom: 20px;">
                <?php wp_nonce_field('dsl_clear_logs_nonce'); ?>
                <input type="submit" name="dsl_clear_logs" class="button" 
                       value="<?php _e('Clear All Logs', 'dynamics-sync-lite'); ?>"
                       onclick="return confirm('<?php _e('Are you sure you want to clear all logs?', 'dynamics-sync-lite'); ?>');">
            </form>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'dynamics-sync-lite'); ?></th>
                        <th><?php _e('User', 'dynamics-sync-lite'); ?></th>
                        <th><?php _e('Action', 'dynamics-sync-lite'); ?></th>
                        <th><?php _e('Message', 'dynamics-sync-lite'); ?></th>
                        <th><?php _e('Date', 'dynamics-sync-lite'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)) : ?>
                        <tr>
                            <td colspan="5"><?php _e('No logs found.', 'dynamics-sync-lite'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($logs as $log) : ?>
                            <tr>
                                <td><?php echo esc_html($log->id); ?></td>
                                <td>
                                    <?php 
                                    $user = get_userdata($log->user_id);
                                    echo $user ? esc_html($user->user_login) : __('Unknown', 'dynamics-sync-lite');
                                    ?>
                                </td>
                                <td><?php echo esc_html($log->action); ?></td>
                                <td><?php echo esc_html($log->message); ?></td>
                                <td><?php echo esc_html($log->created_at); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * AJAX: Test connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('dsl_test_connection', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'dynamics-sync-lite'));
        }
        
        $api = DSL_API::get_instance();
        $result = $api->test_connection();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
}