<?php
/**
 * User Profile Handler
 * 
 * Handles user profile form display and updates
 */

if (!defined('ABSPATH')) {
    exit;
}

class DSL_User_Profile {
    
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
        add_shortcode('dynamics_user_profile', array($this, 'render_profile_form'));
        add_action('wp_ajax_dsl_get_profile', array($this, 'ajax_get_profile'));
        add_action('wp_ajax_dsl_update_profile', array($this, 'ajax_update_profile'));
    }
    
    /**
     * Render profile form shortcode
     */
    public function render_profile_form($atts) {
        if (!is_user_logged_in()) {
            $login_button = '';
            if (get_option('dsl_enable_oauth_login', '0') === '1' || DSL_Demo_Mode::is_enabled()) {
                $login_button = do_shortcode('[dynamics_login text="Sign in to view your profile"]');
            } else {
                $login_button = '<p><a href="' . wp_login_url(get_permalink()) . '">' . __('Log in', 'dynamics-sync-lite') . '</a></p>';
            }
            
            return '<div class="dsl-notice dsl-notice-info">' . 
                   '<p>' . __('Please log in to view your profile.', 'dynamics-sync-lite') . '</p>' .
                   $login_button .
                   '</div>';
        }
        
        $atts = shortcode_atts(array(
            'title' => __('My Dynamics Profile', 'dynamics-sync-lite')
        ), $atts);
        
        ob_start();
        include DSL_PLUGIN_DIR . 'templates/user-profile-form.php';
        return ob_get_clean();
    }
    
    /**
     * AJAX get profile data
     */
    public function ajax_get_profile() {
        check_ajax_referer('dsl_ajax_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array(
                'message' => __('You must be logged in', 'dynamics-sync-lite')
            ));
        }
        
        $current_user = wp_get_current_user();
        $api = DSL_Dynamics_API::get_instance();
        
        // Check if demo mode is enabled
        $is_demo = DSL_Demo_Mode::is_enabled();
        
        // Try to get contact from Dynamics (or demo mode)
        $contact = $api->get_contact_by_email($current_user->user_email);
        
        if (is_wp_error($contact)) {
            // If not found in Dynamics, use WordPress user data as fallback
            if ($contact->get_error_code() === 'not_found') {
                $contact = array(
                    'firstname' => $current_user->user_firstname ?: '',
                    'lastname' => $current_user->user_lastname ?: '',
                    'emailaddress1' => $current_user->user_email,
                    'telephone1' => get_user_meta($current_user->ID, 'phone', true) ?: '',
                    'address1_line1' => '',
                    'address1_city' => '',
                    'address1_stateorprovince' => '',
                    'address1_postalcode' => '',
                    'address1_country' => '',
                    'dynamics_sync_status' => 'not_synced'
                );
                
                $message = __('Profile not yet synced with Dynamics. Fill in your information below.', 'dynamics-sync-lite');
                if ($is_demo) {
                    $message = __('[DEMO MODE] Profile not yet synced. Fill in your information below.', 'dynamics-sync-lite');
                }
                
                wp_send_json_success(array(
                    'contact' => $contact,
                    'message' => $message,
                    'demo_mode' => $is_demo
                ));
            }
            
            wp_send_json_error(array(
                'message' => $contact->get_error_message()
            ));
        }
        
        // Store contact ID in user meta for future updates
        if (isset($contact['contactid'])) {
            update_user_meta($current_user->ID, 'dsl_contact_id', $contact['contactid']);
        }
        
        DSL_Logger::log('info', 'Profile retrieved', array(
            'user_id' => $current_user->ID,
            'contact_id' => $contact['contactid'] ?? 'unknown',
            'demo_mode' => $is_demo
        ));
        
        $message = __('Profile loaded successfully', 'dynamics-sync-lite');
        if ($is_demo) {
            $message = __('[DEMO MODE] Profile loaded successfully (simulated data)', 'dynamics-sync-lite');
        }
        
        wp_send_json_success(array(
            'contact' => $contact,
            'message' => $message,
            'demo_mode' => $is_demo
        ));
    }
    
    /**
     * AJAX update profile
     */
    public function ajax_update_profile() {
        check_ajax_referer('dsl_ajax_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array(
                'message' => __('You must be logged in', 'dynamics-sync-lite')
            ));
        }
        
        $current_user = wp_get_current_user();
        $api = DSL_Dynamics_API::get_instance();
        $is_demo = DSL_Demo_Mode::is_enabled();
        
        // Validate and sanitize input
        $data = array(
            'firstname' => sanitize_text_field($_POST['firstname'] ?? ''),
            'lastname' => sanitize_text_field($_POST['lastname'] ?? ''),
            'emailaddress1' => sanitize_email($_POST['email'] ?? ''),
            'telephone1' => sanitize_text_field($_POST['phone'] ?? ''),
            'address1_line1' => sanitize_text_field($_POST['address'] ?? ''),
            'address1_city' => sanitize_text_field($_POST['city'] ?? ''),
            'address1_stateorprovince' => sanitize_text_field($_POST['state'] ?? ''),
            'address1_postalcode' => sanitize_text_field($_POST['postal_code'] ?? ''),
            'address1_country' => sanitize_text_field($_POST['country'] ?? '')
        );
        
        // Validation
        if (empty($data['firstname']) || empty($data['lastname'])) {
            wp_send_json_error(array(
                'message' => __('First name and last name are required', 'dynamics-sync-lite')
            ));
        }
        
        if (empty($data['emailaddress1']) || !is_email($data['emailaddress1'])) {
            wp_send_json_error(array(
                'message' => __('Valid email address is required', 'dynamics-sync-lite')
            ));
        }
        
        // Get contact ID from user meta
        $contact_id = get_user_meta($current_user->ID, 'dsl_contact_id', true);
        
        if ($contact_id) {
            // Update existing contact
            $result = $api->update_contact($contact_id, $data);
            
            if (is_wp_error($result)) {
                wp_send_json_error(array(
                    'message' => $result->get_error_message()
                ));
            }
            
            // Update WordPress user data
            $this->sync_to_wordpress($current_user->ID, $data);
            
            DSL_Logger::log('success', 'Profile updated successfully', array(
                'user_id' => $current_user->ID,
                'contact_id' => $contact_id,
                'demo_mode' => $is_demo
            ));
            
            $message = __('Your profile has been updated successfully!', 'dynamics-sync-lite');
            if ($is_demo) {
                $message = __('[DEMO MODE] Your profile has been updated successfully! (simulated)', 'dynamics-sync-lite');
            }
            
            wp_send_json_success(array(
                'message' => $message,
                'contact' => $data,
                'demo_mode' => $is_demo
            ));
        } else {
            // Create new contact
            $result = $api->create_contact($data);
            
            if (is_wp_error($result)) {
                wp_send_json_error(array(
                    'message' => $result->get_error_message()
                ));
            }
            
            // Store new contact ID
            if (isset($result['contactid'])) {
                update_user_meta($current_user->ID, 'dsl_contact_id', $result['contactid']);
            }
            
            // Update WordPress user data
            $this->sync_to_wordpress($current_user->ID, $data);
            
            DSL_Logger::log('success', 'New contact created', array(
                'user_id' => $current_user->ID,
                'contact_id' => $result['contactid'] ?? 'unknown',
                'demo_mode' => $is_demo
            ));
            
            $message = __('Your profile has been created and synced successfully!', 'dynamics-sync-lite');
            if ($is_demo) {
                $message = __('[DEMO MODE] Your profile has been created and synced successfully! (simulated)', 'dynamics-sync-lite');
            }
            
            wp_send_json_success(array(
                'message' => $message,
                'contact' => $data,
                'demo_mode' => $is_demo
            ));
        }
    }
    
    /**
     * Sync data to WordPress user
     */
    private function sync_to_wordpress($user_id, $data) {
        // Update user data
        $user_data = array(
            'ID' => $user_id,
            'first_name' => $data['firstname'],
            'last_name' => $data['lastname']
        );
        
        // Only update email if it's different
        $user = get_userdata($user_id);
        if ($user && $user->user_email !== $data['emailaddress1']) {
            $user_data['user_email'] = $data['emailaddress1'];
        }
        
        wp_update_user($user_data);
        
        // Update user meta
        update_user_meta($user_id, 'phone', $data['telephone1']);
        update_user_meta($user_id, 'dsl_last_sync', current_time('mysql'));
    }
    
    /**
     * Get user's Dynamics contact ID
     */
    public static function get_user_contact_id($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return get_user_meta($user_id, 'dsl_contact_id', true);
    }
    
    /**
     * Check if user is synced with Dynamics
     */
    public static function is_user_synced($user_id = null) {
        return !empty(self::get_user_contact_id($user_id));
    }
}