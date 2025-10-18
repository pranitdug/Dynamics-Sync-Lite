<?php
/**
 * OAuth-Independent Profile Handler - PRODUCTION READY (FIXED)
 * 
 * Users sign in with Microsoft, edit Dynamics data WITHOUT WordPress account
 */

if (!defined('ABSPATH')) {
    exit;
}

class DSL_OAuth_Independent_Profile {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_shortcode('dynamics_profile_oauth', array($this, 'render_profile_form'));
        
        // AJAX - works for both logged-in and non-logged-in
        add_action('wp_ajax_nopriv_dsl_oauth_get_profile', array($this, 'ajax_get_profile'));
        add_action('wp_ajax_nopriv_dsl_oauth_update_profile', array($this, 'ajax_update_profile'));
        add_action('wp_ajax_dsl_oauth_get_profile', array($this, 'ajax_get_profile'));
        add_action('wp_ajax_dsl_oauth_update_profile', array($this, 'ajax_update_profile'));
    }
    
    public function render_profile_form($atts) {
        $atts = shortcode_atts(array(
            'title' => 'My Profile'
        ), $atts);
        
        ob_start();
        ?>
        <div class="dsl-oauth-profile-container">
            <div class="dsl-oauth-profile-header">
                <h2><?php echo esc_html($atts['title']); ?></h2>
                <p class="dsl-oauth-subtitle">Sign in with Microsoft to manage your information</p>
            </div>
            
            <!-- Login Section -->
            <div id="dsl-oauth-login-section" class="dsl-oauth-login-section">
                <?php echo do_shortcode('[dynamics_login_independent text="Sign In with Microsoft"]'); ?>
            </div>
            
            <!-- Profile Section -->
            <div id="dsl-oauth-profile-section" class="dsl-oauth-profile-section" style="display: none;">
                <div id="dsl-oauth-message-container"></div>
                
                <div id="dsl-oauth-loading" class="dsl-oauth-loading" style="display: none;">
                    <div class="dsl-spinner"></div>
                    <p>Loading your profile...</p>
                </div>
                
                <form id="dsl-oauth-profile-form" class="dsl-oauth-form" style="display: none;">
                    <div class="dsl-oauth-form-row">
                        <div class="dsl-oauth-form-group">
                            <label for="dsl-oauth-firstname">
                                First Name <span class="required">*</span>
                            </label>
                            <input type="text" id="dsl-oauth-firstname" name="firstname" 
                                   class="dsl-oauth-input" required autocomplete="given-name" />
                        </div>
                        
                        <div class="dsl-oauth-form-group">
                            <label for="dsl-oauth-lastname">
                                Last Name <span class="required">*</span>
                            </label>
                            <input type="text" id="dsl-oauth-lastname" name="lastname" 
                                   class="dsl-oauth-input" required autocomplete="family-name" />
                        </div>
                    </div>
                    
                    <div class="dsl-oauth-form-row">
                        <div class="dsl-oauth-form-group">
                            <label for="dsl-oauth-email">
                                Email Address <span class="required">*</span>
                            </label>
                            <input type="email" id="dsl-oauth-email" name="email" 
                                   class="dsl-oauth-input" required readonly 
                                   title="Email cannot be changed" />
                        </div>
                        
                        <div class="dsl-oauth-form-group">
                            <label for="dsl-oauth-phone">Phone Number</label>
                            <input type="tel" id="dsl-oauth-phone" name="phone" 
                                   class="dsl-oauth-input" autocomplete="tel" />
                        </div>
                    </div>
                    
                    <div class="dsl-oauth-form-section">
                        <h3>Address Information</h3>
                        
                        <div class="dsl-oauth-form-group">
                            <label for="dsl-oauth-address">Street Address</label>
                            <input type="text" id="dsl-oauth-address" name="address" 
                                   class="dsl-oauth-input" autocomplete="address-line1" />
                        </div>
                        
                        <div class="dsl-oauth-form-row">
                            <div class="dsl-oauth-form-group">
                                <label for="dsl-oauth-city">City</label>
                                <input type="text" id="dsl-oauth-city" name="city" 
                                       class="dsl-oauth-input" autocomplete="address-level2" />
                            </div>
                            
                            <div class="dsl-oauth-form-group">
                                <label for="dsl-oauth-state">State/Province</label>
                                <input type="text" id="dsl-oauth-state" name="state" 
                                       class="dsl-oauth-input" autocomplete="address-level1" />
                            </div>
                        </div>
                        
                        <div class="dsl-oauth-form-row">
                            <div class="dsl-oauth-form-group">
                                <label for="dsl-oauth-postal-code">Postal Code</label>
                                <input type="text" id="dsl-oauth-postal-code" name="postal_code" 
                                       class="dsl-oauth-input" autocomplete="postal-code" />
                            </div>
                            
                            <div class="dsl-oauth-form-group">
                                <label for="dsl-oauth-country">Country</label>
                                <input type="text" id="dsl-oauth-country" name="country" 
                                       class="dsl-oauth-input" autocomplete="country-name" />
                            </div>
                        </div>
                    </div>
                    
                    <div class="dsl-oauth-form-actions">
                        <button type="submit" class="dsl-oauth-button dsl-oauth-button-primary" id="dsl-oauth-submit-btn">
                            Save Changes
                        </button>
                        <button type="button" class="dsl-oauth-button dsl-oauth-button-secondary" id="dsl-oauth-logout-btn" style="margin-left: 10px;">
                            Logout
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function get_oauth_session() {
        if (!session_id() && !headers_sent()) {
            session_start();
        }
        
        $token = isset($_SESSION['dsl_oauth_token']) ? $_SESSION['dsl_oauth_token'] : '';
        $email = isset($_SESSION['dsl_oauth_email']) ? $_SESSION['dsl_oauth_email'] : '';
        $user_info = isset($_SESSION['dsl_oauth_user_info']) ? $_SESSION['dsl_oauth_user_info'] : array();
        $expires = isset($_SESSION['dsl_oauth_expires']) ? intval($_SESSION['dsl_oauth_expires']) : 0;
        
        $is_auth = !empty($token) && !empty($email) && ($expires === 0 || $expires > time());
        
        return array(
            'token' => $token,
            'email' => $email,
            'user_info' => $user_info,
            'is_authenticated' => $is_auth
        );
    }
    
    public function ajax_get_profile() {
        check_ajax_referer('dsl_ajax_nonce', 'nonce');
        
        $session = $this->get_oauth_session();
        
        if (!$session['is_authenticated']) {
            wp_send_json_error(array(
                'message' => 'Please sign in first',
                'code' => 'not_authenticated'
            ));
        }
        
        $email = $session['email'];
        $api = DSL_Dynamics_API::get_instance();
        
        if (!$api->is_configured()) {
            wp_send_json_error(array(
                'message' => 'API is not configured'
            ));
        }
        
        // Get contact from Dynamics
        $contact = $api->get_contact_by_email($email);
        
        if (is_wp_error($contact)) {
            if ($contact->get_error_code() === 'not_found') {
                // New user - return empty profile
                $contact = array(
                    'firstname' => $session['user_info']['first_name'] ?? '',
                    'lastname' => $session['user_info']['last_name'] ?? '',
                    'emailaddress1' => $email,
                    'telephone1' => '',
                    'address1_line1' => '',
                    'address1_city' => '',
                    'address1_stateorprovince' => '',
                    'address1_postalcode' => '',
                    'address1_country' => '',
                    'is_new' => true
                );
                
                wp_send_json_success(array(
                    'contact' => $contact,
                    'message' => 'No profile found. Fill in your information to create one.'
                ));
            }
            
            wp_send_json_error(array(
                'message' => $contact->get_error_message()
            ));
        }
        
        DSL_Logger::log('success', 'OAuth profile loaded', array(
            'email' => $email
        ));
        
        wp_send_json_success(array(
            'contact' => $contact
        ));
    }
    
    public function ajax_update_profile() {
        check_ajax_referer('dsl_ajax_nonce', 'nonce');
        
        $session = $this->get_oauth_session();
        
        if (!$session['is_authenticated']) {
            wp_send_json_error(array(
                'message' => 'Please sign in first',
                'code' => 'not_authenticated'
            ));
        }
        
        $email = $session['email'];
        $api = DSL_Dynamics_API::get_instance();
        
        if (!$api->is_configured()) {
            wp_send_json_error(array(
                'message' => 'API is not configured'
            ));
        }
        
        // Validate and sanitize
        $data = array(
            'firstname' => sanitize_text_field($_POST['firstname'] ?? ''),
            'lastname' => sanitize_text_field($_POST['lastname'] ?? ''),
            'emailaddress1' => $email, // Use OAuth email
            'telephone1' => sanitize_text_field($_POST['phone'] ?? ''),
            'address1_line1' => sanitize_text_field($_POST['address'] ?? ''),
            'address1_city' => sanitize_text_field($_POST['city'] ?? ''),
            'address1_stateorprovince' => sanitize_text_field($_POST['state'] ?? ''),
            'address1_postalcode' => sanitize_text_field($_POST['postal_code'] ?? ''),
            'address1_country' => sanitize_text_field($_POST['country'] ?? '')
        );
        
        if (empty($data['firstname']) || empty($data['lastname'])) {
            wp_send_json_error(array(
                'message' => 'First name and last name are required'
            ));
        }
        
        // Check if contact exists
        $existing = $api->get_contact_by_email($email);
        
        if (is_wp_error($existing)) {
            // Create new contact
            $result = $api->create_contact($data);
            
            if (is_wp_error($result)) {
                DSL_Logger::log('error', 'Profile creation failed', array(
                    'error' => $result->get_error_message()
                ));
                
                wp_send_json_error(array(
                    'message' => $result->get_error_message()
                ));
            }
            
            DSL_Logger::log('success', 'Profile created', array(
                'email' => $email
            ));
            
            wp_send_json_success(array(
                'message' => 'Profile created successfully!'
            ));
        } else {
            // Update existing contact
            $contact_id = $existing['contactid'];
            $result = $api->update_contact($contact_id, $data);
            
            if (is_wp_error($result)) {
                DSL_Logger::log('error', 'Profile update failed', array(
                    'error' => $result->get_error_message()
                ));
                
                wp_send_json_error(array(
                    'message' => $result->get_error_message()
                ));
            }
            
            DSL_Logger::log('success', 'Profile updated', array(
                'email' => $email
            ));
            
            wp_send_json_success(array(
                'message' => 'Profile updated successfully!'
            ));
        }
    }
}

DSL_OAuth_Independent_Profile::get_instance();