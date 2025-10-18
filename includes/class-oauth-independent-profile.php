<?php
/**
 * OAuth-Independent User Profile Handler - PRODUCTION READY
 * 
 * Users sign in with Microsoft OAuth, edit Dynamics data WITHOUT WordPress account
 * This allows external users to manage their Dynamics contact info
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
        // Add shortcode for independent profile
        add_shortcode('dynamics_profile_oauth', array($this, 'render_profile_form'));
        
        // AJAX handlers - work for both logged-in and non-logged-in users
        add_action('wp_ajax_nopriv_dsl_oauth_get_profile', array($this, 'ajax_get_profile'));
        add_action('wp_ajax_nopriv_dsl_oauth_update_profile', array($this, 'ajax_update_profile'));
        add_action('wp_ajax_dsl_oauth_get_profile', array($this, 'ajax_get_profile'));
        add_action('wp_ajax_dsl_oauth_update_profile', array($this, 'ajax_update_profile'));
    }
    
    /**
     * Render profile form for OAuth users
     */
    public function render_profile_form($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Edit Your Profile', 'dynamics-sync-lite')
        ), $atts);
        
        ob_start();
        ?>
        <div class="dsl-oauth-profile-container">
            <div class="dsl-oauth-profile-header">
                <h2><?php echo esc_html($atts['title']); ?></h2>
                <p class="dsl-oauth-subtitle"><?php _e('Sign in with Microsoft to edit your information', 'dynamics-sync-lite'); ?></p>
            </div>
            
            <!-- Login Section -->
            <div id="dsl-oauth-login-section" class="dsl-oauth-login-section">
                <?php echo do_shortcode('[dynamics_login_independent text="Sign In with Microsoft"]'); ?>
            </div>
            
            <!-- Profile Section (hidden until logged in) -->
            <div id="dsl-oauth-profile-section" class="dsl-oauth-profile-section" style="display: none;">
                <div id="dsl-oauth-message-container"></div>
                
                <div id="dsl-oauth-loading" class="dsl-oauth-loading" style="display: none;">
                    <div class="dsl-spinner"></div>
                    <p><?php _e('Loading your profile...', 'dynamics-sync-lite'); ?></p>
                </div>
                
                <form id="dsl-oauth-profile-form" class="dsl-oauth-form" style="display: none;">
                    <div class="dsl-oauth-form-row">
                        <div class="dsl-oauth-form-group">
                            <label for="dsl-oauth-firstname">
                                <?php _e('First Name', 'dynamics-sync-lite'); ?>
                                <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="dsl-oauth-firstname" 
                                   name="firstname" 
                                   class="dsl-oauth-input" 
                                   required 
                                   autocomplete="given-name" />
                        </div>
                        
                        <div class="dsl-oauth-form-group">
                            <label for="dsl-oauth-lastname">
                                <?php _e('Last Name', 'dynamics-sync-lite'); ?>
                                <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="dsl-oauth-lastname" 
                                   name="lastname" 
                                   class="dsl-oauth-input" 
                                   required 
                                   autocomplete="family-name" />
                        </div>
                    </div>
                    
                    <div class="dsl-oauth-form-row">
                        <div class="dsl-oauth-form-group">
                            <label for="dsl-oauth-email">
                                <?php _e('Email Address', 'dynamics-sync-lite'); ?>
                                <span class="required">*</span>
                            </label>
                            <input type="email" 
                                   id="dsl-oauth-email" 
                                   name="email" 
                                   class="dsl-oauth-input" 
                                   required 
                                   autocomplete="email"
                                   readonly 
                                   title="<?php esc_attr_e('Email cannot be changed', 'dynamics-sync-lite'); ?>" />
                        </div>
                        
                        <div class="dsl-oauth-form-group">
                            <label for="dsl-oauth-phone">
                                <?php _e('Phone Number', 'dynamics-sync-lite'); ?>
                            </label>
                            <input type="tel" 
                                   id="dsl-oauth-phone" 
                                   name="phone" 
                                   class="dsl-oauth-input" 
                                   autocomplete="tel" />
                        </div>
                    </div>
                    
                    <div class="dsl-oauth-form-section">
                        <h3><?php _e('Address Information', 'dynamics-sync-lite'); ?></h3>
                        
                        <div class="dsl-oauth-form-group">
                            <label for="dsl-oauth-address">
                                <?php _e('Street Address', 'dynamics-sync-lite'); ?>
                            </label>
                            <input type="text" 
                                   id="dsl-oauth-address" 
                                   name="address" 
                                   class="dsl-oauth-input" 
                                   autocomplete="address-line1" />
                        </div>
                        
                        <div class="dsl-oauth-form-row">
                            <div class="dsl-oauth-form-group">
                                <label for="dsl-oauth-city">
                                    <?php _e('City', 'dynamics-sync-lite'); ?>
                                </label>
                                <input type="text" 
                                       id="dsl-oauth-city" 
                                       name="city" 
                                       class="dsl-oauth-input" 
                                       autocomplete="address-level2" />
                            </div>
                            
                            <div class="dsl-oauth-form-group">
                                <label for="dsl-oauth-state">
                                    <?php _e('State/Province', 'dynamics-sync-lite'); ?>
                                </label>
                                <input type="text" 
                                       id="dsl-oauth-state" 
                                       name="state" 
                                       class="dsl-oauth-input" 
                                       autocomplete="address-level1" />
                            </div>
                        </div>
                        
                        <div class="dsl-oauth-form-row">
                            <div class="dsl-oauth-form-group">
                                <label for="dsl-oauth-postal-code">
                                    <?php _e('Postal Code', 'dynamics-sync-lite'); ?>
                                </label>
                                <input type="text" 
                                       id="dsl-oauth-postal-code" 
                                       name="postal_code" 
                                       class="dsl-oauth-input" 
                                       autocomplete="postal-code" />
                            </div>
                            
                            <div class="dsl-oauth-form-group">
                                <label for="dsl-oauth-country">
                                    <?php _e('Country', 'dynamics-sync-lite'); ?>
                                </label>
                                <input type="text" 
                                       id="dsl-oauth-country" 
                                       name="country" 
                                       class="dsl-oauth-input" 
                                       autocomplete="country-name" />
                            </div>
                        </div>
                    </div>
                    
                    <div class="dsl-oauth-form-actions">
                        <button type="submit" class="dsl-oauth-button dsl-oauth-button-primary" id="dsl-oauth-submit-btn">
                            <?php _e('Save Changes', 'dynamics-sync-lite'); ?>
                        </button>
                        <span class="dsl-oauth-sync-status" id="dsl-oauth-sync-status"></span>
                        <button type="button" class="dsl-oauth-button dsl-oauth-button-secondary" id="dsl-oauth-logout-btn" style="margin-left: 10px;">
                            <?php _e('Logout', 'dynamics-sync-lite'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get user's OAuth session info
     */
    private function get_oauth_session() {
        // Ensure session is started
        if (!session_id() && !headers_sent()) {
            session_start();
        }
        
        $token = isset($_SESSION['dsl_oauth_token']) ? $_SESSION['dsl_oauth_token'] : '';
        $user_email = isset($_SESSION['dsl_oauth_email']) ? $_SESSION['dsl_oauth_email'] : '';
        $user_info = isset($_SESSION['dsl_oauth_user_info']) ? $_SESSION['dsl_oauth_user_info'] : array();
        $expires = isset($_SESSION['dsl_oauth_expires']) ? intval($_SESSION['dsl_oauth_expires']) : 0;
        
        $is_authenticated = !empty($token) && !empty($user_email) && ($expires === 0 || $expires > time());
        
        return array(
            'token' => $token,
            'email' => $user_email,
            'user_info' => $user_info,
            'expires' => $expires,
            'is_authenticated' => $is_authenticated
        );
    }
    
    /**
     * AJAX: Get profile (no WordPress auth required)
     */
    public function ajax_get_profile() {
        check_ajax_referer('dsl_ajax_nonce', 'nonce');
        
        $session = $this->get_oauth_session();
        
        DSL_Logger::log('info', 'OAuth profile get attempt', array(
            'is_authenticated' => $session['is_authenticated'],
            'has_token' => !empty($session['token']),
            'has_email' => !empty($session['email']),
            'expires' => $session['expires'],
            'current_time' => time()
        ));
        
        if (!$session['is_authenticated']) {
            wp_send_json_error(array(
                'message' => __('You must sign in first to view your profile.', 'dynamics-sync-lite'),
                'code' => 'not_authenticated'
            ));
        }
        
        $email = $session['email'];
        $api = DSL_Dynamics_API::get_instance();
        
        // Check if API is configured
        if (!$api->is_configured()) {
            wp_send_json_error(array(
                'message' => __('Dynamics 365 API is not configured. Please contact the administrator.', 'dynamics-sync-lite')
            ));
        }
        
        // Get contact from Dynamics using OAuth user's email
        $contact = $api->get_contact_by_email($email);
        
        if (is_wp_error($contact)) {
            if ($contact->get_error_code() === 'not_found') {
                // User not found in Dynamics - show empty form
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
                    'dynamics_sync_status' => 'not_synced'
                );
                
                wp_send_json_success(array(
                    'contact' => $contact,
                    'message' => __('No existing profile found. Fill in your information to create one.', 'dynamics-sync-lite')
                ));
            }
            
            wp_send_json_error(array(
                'message' => $contact->get_error_message()
            ));
        }
        
        DSL_Logger::log('success', 'OAuth profile loaded', array(
            'email' => $email,
            'contact_id' => $contact['contactid'] ?? 'unknown'
        ));
        
        wp_send_json_success(array(
            'contact' => $contact,
            'message' => __('Profile loaded successfully.', 'dynamics-sync-lite')
        ));
    }
    
    /**
     * AJAX: Update profile (no WordPress auth required)
     */
    public function ajax_update_profile() {
        check_ajax_referer('dsl_ajax_nonce', 'nonce');
        
        $session = $this->get_oauth_session();
        
        if (!$session['is_authenticated']) {
            wp_send_json_error(array(
                'message' => __('You must sign in first to update your profile.', 'dynamics-sync-lite'),
                'code' => 'not_authenticated'
            ));
        }
        
        $email = $session['email'];
        $api = DSL_Dynamics_API::get_instance();
        
        // Check if API is configured
        if (!$api->is_configured()) {
            wp_send_json_error(array(
                'message' => __('Dynamics 365 API is not configured. Please contact the administrator.', 'dynamics-sync-lite')
            ));
        }
        
        // Validate and sanitize input
        $data = array(
            'firstname' => sanitize_text_field($_POST['firstname'] ?? ''),
            'lastname' => sanitize_text_field($_POST['lastname'] ?? ''),
            'emailaddress1' => $email, // Use OAuth email (cannot be changed)
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
                'message' => __('First name and last name are required fields.', 'dynamics-sync-lite')
            ));
        }
        
        // Try to get existing contact
        $existing = $api->get_contact_by_email($email);
        
        if (is_wp_error($existing)) {
            // Create new contact
            $result = $api->create_contact($data);
            
            if (is_wp_error($result)) {
                DSL_Logger::log('error', 'OAuth profile creation failed', array(
                    'email' => $email,
                    'error' => $result->get_error_message()
                ));
                
                wp_send_json_error(array(
                    'message' => $result->get_error_message()
                ));
            }
            
            DSL_Logger::log('success', 'OAuth: New profile created', array(
                'email' => $email
            ));
            
            wp_send_json_success(array(
                'message' => __('Profile created successfully!', 'dynamics-sync-lite'),
                'contact' => $data
            ));
        } else {
            // Update existing contact
            $contact_id = $existing['contactid'];
            $result = $api->update_contact($contact_id, $data);
            
            if (is_wp_error($result)) {
                DSL_Logger::log('error', 'OAuth profile update failed', array(
                    'email' => $email,
                    'contact_id' => $contact_id,
                    'error' => $result->get_error_message()
                ));
                
                wp_send_json_error(array(
                    'message' => $result->get_error_message()
                ));
            }
            
            DSL_Logger::log('success', 'OAuth: Profile updated', array(
                'email' => $email,
                'contact_id' => $contact_id
            ));
            
            wp_send_json_success(array(
                'message' => __('Profile updated successfully!', 'dynamics-sync-lite'),
                'contact' => $data
            ));
        }
    }
}

// Initialize
DSL_OAuth_Independent_Profile::get_instance();