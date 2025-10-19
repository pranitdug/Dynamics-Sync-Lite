<?php
/**
 * Webhook Handler
 *
 * @package DynamicsSyncLite
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class DSL_Webhook {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        register_rest_route('dynamics-sync-lite/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => array($this, 'verify_webhook')
        ));
    }
    
    /**
     * Verify webhook authenticity
     */
    public function verify_webhook($request) {
        $secret = get_option('dsl_webhook_secret', '');
        
        if (empty($secret)) {
            return new WP_Error('no_secret', __('Webhook secret not configured', 'dynamics-sync-lite'), array('status' => 500));
        }
        
        $provided_secret = $request->get_header('X-Webhook-Secret');
        
        if (empty($provided_secret)) {
            DSL_Logger::log_error('Webhook request missing secret header');
            return false;
        }
        
        if (!hash_equals($secret, $provided_secret)) {
            DSL_Logger::log_error('Webhook secret mismatch');
            return false;
        }
        
        return true;
    }
    
    /**
     * Handle webhook
     */
    public function handle_webhook($request) {
        $body = $request->get_json_params();
        
        if (empty($body)) {
            DSL_Logger::log_error('Webhook received empty payload');
            return new WP_Error('empty_payload', __('Empty payload', 'dynamics-sync-lite'), array('status' => 400));
        }
        
        DSL_Logger::log_action(0, 'webhook_received', 'Webhook payload: ' . json_encode($body));
        
        // Extract contact information
        $email = isset($body['emailaddress1']) ? sanitize_email($body['emailaddress1']) : '';
        
        if (empty($email)) {
            DSL_Logger::log_error('Webhook missing email address');
            return new WP_Error('missing_email', __('Missing email address', 'dynamics-sync-lite'), array('status' => 400));
        }
        
        // Find WordPress user by email
        $user = get_user_by('email', $email);
        
        if (!$user) {
            DSL_Logger::log_action(0, 'webhook_user_not_found', "No WordPress user found for email: {$email}");
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'User not found in WordPress'
            ), 200);
        }
        
        // Update user meta with contact ID
        if (isset($body['contactid'])) {
            update_user_meta($user->ID, 'dsl_contact_id', sanitize_text_field($body['contactid']));
        }
        
        // Store last sync time
        update_user_meta($user->ID, 'dsl_last_sync', current_time('mysql'));
        
        DSL_Logger::log_action($user->ID, 'webhook_processed', "Contact updated from Dynamics for user: {$user->user_login}");
        
        // Fire action for other plugins to hook into
        do_action('dsl_webhook_processed', $user->ID, $body);
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Webhook processed successfully'
        ), 200);
    }
}