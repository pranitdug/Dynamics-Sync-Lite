<?php
/**
 * Shortcode Handler
 *
 * @package DynamicsSyncLite
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class DSL_Shortcodes {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_shortcode('dynamics_contact_form', array($this, 'render_contact_form'));
    }
    
    /**
     * Render contact form shortcode
     */
    public function render_contact_form($atts) {
        $atts = shortcode_atts(array(
            'show_title' => 'yes',
            'title' => __('My Contact Information', 'dynamics-sync-lite')
        ), $atts);
        
        ob_start();
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            ?>
            <div class="dsl-login-wrapper">
                <div class="dsl-notice dsl-notice-warning">
                    <p><?php _e('Please log in to view and update your contact information.', 'dynamics-sync-lite'); ?></p>
                </div>
                
                <?php 
                // Display WordPress login form
                $args = array(
                    'echo' => true,
                    'redirect' => get_permalink(),
                    'form_id' => 'dsl-loginform',
                    'label_username' => __('Username or Email', 'dynamics-sync-lite'),
                    'label_password' => __('Password', 'dynamics-sync-lite'),
                    'label_remember' => __('Remember Me', 'dynamics-sync-lite'),
                    'label_log_in' => __('Log In', 'dynamics-sync-lite'),
                    'remember' => true,
                    'value_remember' => true
                );
                wp_login_form($args);
                ?>
                
                <p class="dsl-login-links">
                    <a href="<?php echo wp_lostpassword_url(get_permalink()); ?>">
                        <?php _e('Lost your password?', 'dynamics-sync-lite'); ?>
                    </a>
                    <?php if (get_option('users_can_register')) : ?>
                        | <a href="<?php echo wp_registration_url(); ?>">
                            <?php _e('Register', 'dynamics-sync-lite'); ?>
                        </a>
                    <?php endif; ?>
                </p>
            </div>
            
            <style>
            .dsl-login-wrapper {
                max-width: 400px;
                margin: 20px auto;
                padding: 30px;
                background: #ffffff;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }
            #dsl-loginform {
                margin-top: 20px;
            }
            #dsl-loginform p {
                margin-bottom: 15px;
            }
            #dsl-loginform label {
                display: block;
                margin-bottom: 5px;
                font-weight: 500;
            }
            #dsl-loginform input[type="text"],
            #dsl-loginform input[type="password"] {
                width: 100%;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
            }
            #dsl-loginform input[type="submit"] {
                width: 100%;
                padding: 12px;
                background: #2271b1;
                color: #fff;
                border: none;
                border-radius: 4px;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
            }
            #dsl-loginform input[type="submit"]:hover {
                background: #135e96;
            }
            .dsl-login-links {
                margin-top: 15px;
                text-align: center;
                font-size: 14px;
            }
            .dsl-login-links a {
                color: #2271b1;
                text-decoration: none;
            }
            .dsl-login-links a:hover {
                text-decoration: underline;
            }
            </style>
            <?php
            return ob_get_clean();
        }
        
        // User is logged in - show contact form with logout option
        ?>
        <div class="dsl-contact-form-wrapper">
            <div class="dsl-header-actions">
                <?php if ($atts['show_title'] === 'yes') : ?>
                    <h3 class="dsl-form-title"><?php echo esc_html($atts['title']); ?></h3>
                <?php endif; ?>
                <div class="dsl-user-info">
                    <span class="dsl-welcome">
                        <?php printf(__('Welcome, %s', 'dynamics-sync-lite'), '<strong>' . esc_html(wp_get_current_user()->display_name) . '</strong>'); ?>
                    </span>
                    <a href="<?php echo wp_logout_url(get_permalink()); ?>" class="dsl-logout-link">
                        <?php _e('Logout', 'dynamics-sync-lite'); ?>
                    </a>
                </div>
            </div>
            
            <div id="dsl-message"></div>
            
            <form id="dsl-contact-form" class="dsl-form">
                <?php wp_nonce_field('dsl_update_contact', 'dsl_nonce'); ?>
                
                <div class="dsl-loading" style="display: none;">
                    <div class="dsl-spinner"></div>
                    <p><?php _e('Loading your information...', 'dynamics-sync-lite'); ?></p>
                </div>
                
                <div id="dsl-form-fields" style="display: none;">
                    <div class="dsl-form-row">
                        <div class="dsl-form-group">
                            <label for="dsl_firstname">
                                <?php _e('First Name', 'dynamics-sync-lite'); ?> 
                                <span class="required">*</span>
                            </label>
                            <input type="text" id="dsl_firstname" name="firstname" required>
                        </div>
                        
                        <div class="dsl-form-group">
                            <label for="dsl_lastname">
                                <?php _e('Last Name', 'dynamics-sync-lite'); ?> 
                                <span class="required">*</span>
                            </label>
                            <input type="text" id="dsl_lastname" name="lastname" required>
                        </div>
                    </div>
                    
                    <div class="dsl-form-row">
                        <div class="dsl-form-group">
                            <label for="dsl_email">
                                <?php _e('Email Address', 'dynamics-sync-lite'); ?> 
                                <span class="required">*</span>
                            </label>
                            <input type="email" id="dsl_email" name="emailaddress1" required readonly>
                            <small class="dsl-help-text">
                                <?php _e('Email cannot be changed here', 'dynamics-sync-lite'); ?>
                            </small>
                        </div>
                        
                        <div class="dsl-form-group">
                            <label for="dsl_phone">
                                <?php _e('Phone Number', 'dynamics-sync-lite'); ?>
                            </label>
                            <input type="tel" id="dsl_phone" name="telephone1">
                        </div>
                    </div>
                    
                    <div class="dsl-form-group">
                        <label for="dsl_address">
                            <?php _e('Street Address', 'dynamics-sync-lite'); ?>
                        </label>
                        <input type="text" id="dsl_address" name="address1_line1">
                    </div>
                    
                    <div class="dsl-form-row">
                        <div class="dsl-form-group">
                            <label for="dsl_city">
                                <?php _e('City', 'dynamics-sync-lite'); ?>
                            </label>
                            <input type="text" id="dsl_city" name="address1_city">
                        </div>
                        
                        <div class="dsl-form-group">
                            <label for="dsl_state">
                                <?php _e('State/Province', 'dynamics-sync-lite'); ?>
                            </label>
                            <input type="text" id="dsl_state" name="address1_stateorprovince">
                        </div>
                    </div>
                    
                    <div class="dsl-form-row">
                        <div class="dsl-form-group">
                            <label for="dsl_postal">
                                <?php _e('Postal Code', 'dynamics-sync-lite'); ?>
                            </label>
                            <input type="text" id="dsl_postal" name="address1_postalcode">
                        </div>
                        
                        <div class="dsl-form-group">
                            <label for="dsl_country">
                                <?php _e('Country', 'dynamics-sync-lite'); ?>
                            </label>
                            <input type="text" id="dsl_country" name="address1_country">
                        </div>
                    </div>
                    
                    <input type="hidden" id="dsl_contact_id" name="contact_id">
                    
                    <div class="dsl-form-actions">
                        <button type="submit" class="dsl-button dsl-button-primary">
                            <?php _e('Save Changes', 'dynamics-sync-lite'); ?>
                        </button>
                        <button type="button" id="dsl-refresh-data" class="dsl-button dsl-button-secondary">
                            <?php _e('Refresh Data', 'dynamics-sync-lite'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}