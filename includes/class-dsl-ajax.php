<?php
/**
 * AJAX Handler
 *
 * @package DynamicsSyncLite
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class DSL_Ajax {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_dsl_get_contact', array($this, 'get_contact'));
        add_action('wp_ajax_dsl_update_contact', array($this, 'update_contact'));
    }
    
    /**
     * AJAX: Get contact data
     */
    public function get_contact() {
        check_ajax_referer('dsl_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in', 'dynamics-sync-lite'));
        }
        
        $current_user = wp_get_current_user();
        $email = $current_user->user_email;
        
        $api = DSL_API::get_instance();
        $contact = $api->get_contact_by_email($email);
        
        if (is_wp_error($contact)) {
            DSL_Logger::log_action(get_current_user_id(), 'get_contact_failed', $contact->get_error_message());
            wp_send_json_error($contact->get_error_message());
        }
        
        DSL_Logger::log_action(get_current_user_id(), 'get_contact_success', 'Contact data retrieved');
        wp_send_json_success($contact);
    }
    
    /**
     * AJAX: Update contact data
     */
    public function update_contact() {
        check_ajax_referer('dsl_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in', 'dynamics-sync-lite'));
        }
        
        // Validate required fields
        if (empty($_POST['firstname']) || empty($_POST['lastname'])) {
            wp_send_json_error(__('First name and last name are required', 'dynamics-sync-lite'));
        }
        
        $contact_id = isset($_POST['contact_id']) ? sanitize_text_field($_POST['contact_id']) : '';
        
        if (empty($contact_id)) {
            wp_send_json_error(__('Contact ID is missing', 'dynamics-sync-lite'));
        }
        
        // Prepare data
        $data = array(
            'firstname' => sanitize_text_field($_POST['firstname']),
            'lastname' => sanitize_text_field($_POST['lastname']),
            'telephone1' => isset($_POST['telephone1']) ? sanitize_text_field($_POST['telephone1']) : '',
            'address1_line1' => isset($_POST['address1_line1']) ? sanitize_text_field($_POST['address1_line1']) : '',
            'address1_city' => isset($_POST['address1_city']) ? sanitize_text_field($_POST['address1_city']) : '',
            'address1_stateorprovince' => isset($_POST['address1_stateorprovince']) ? sanitize_text_field($_POST['address1_stateorprovince']) : '',
            'address1_postalcode' => isset($_POST['address1_postalcode']) ? sanitize_text_field($_POST['address1_postalcode']) : '',
            'address1_country' => isset($_POST['address1_country']) ? sanitize_text_field($_POST['address1_country']) : ''
        );
        
        // Remove empty fields
        $data = array_filter($data, function($value) {
            return $value !== '';
        });
        
        $api = DSL_API::get_instance();
        $result = $api->update_contact($contact_id, $data);
        
        if (is_wp_error($result)) {
            DSL_Logger::log_action(get_current_user_id(), 'update_contact_failed', $result->get_error_message());
            wp_send_json_error($result->get_error_message());
        }
        
        DSL_Logger::log_action(get_current_user_id(), 'update_contact_success', 'Contact updated: ' . json_encode($data));
        
        // Return updated contact data
        $current_user = wp_get_current_user();
        $updated_contact = $api->get_contact_by_email($current_user->user_email);
        
        if (is_wp_error($updated_contact)) {
            wp_send_json_success(array(
                'message' => __('Contact updated successfully', 'dynamics-sync-lite')
            ));
        }
        
        wp_send_json_success(array(
            'message' => __('Contact updated successfully', 'dynamics-sync-lite'),
            'contact' => $updated_contact
        ));
    }
}