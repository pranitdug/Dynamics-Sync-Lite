<?php
/**
 * OAuth Login Handler
 * 
 * Allows users to log in with Microsoft/Dynamics 365 credentials
 */

if (!defined('ABSPATH')) {
    exit;
}

class DSL_OAuth_Login {
    
    private static $instance = null;
    private $client_id;
    private $client_secret;
    private $tenant_id;
    private $redirect_uri;
    
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
        $this->client_id = get_option('dsl_client_id', '');
        $this->tenant_id = get_option('dsl_tenant_id', '');
        $this->client_secret = get_option('dsl_client_secret', '');
        $this->redirect_uri = home_url('/dynamics-oauth-callback/');
        
        add_action('init', array($this, 'register_endpoints'));
        add_action('template_redirect', array($this, 'handle_oauth_callback'));
        add_shortcode('dynamics_login', array($this, 'render_login_button'));
    }
    
    /**
     * Register custom endpoints
     */
    public function register_endpoints() {
        add_rewrite_rule(
            '^dynamics-oauth-callback/?$',
            'index.php?dynamics_oauth_callback=1',
            'top'
        );
        
        add_filter('query_vars', function($vars) {
            $vars[] = 'dynamics_oauth_callback';
            return $vars;
        });
    }
    
    /**
     * Check if OAuth login is enabled
     */
    public function is_oauth_enabled() {
        return get_option('dsl_enable_oauth_login', '0') === '1';
    }
    
    /**
     * Get OAuth authorization URL
     */
    public function get_authorization_url($state = '') {
        if (DSL_Demo_Mode::is_enabled()) {
            return add_query_arg(array(
                'dynamics_demo_login' => '1',
                'state' => $state
            ), home_url('/'));
        }
        
        if (empty($state)) {
            $state = wp_create_nonce('dsl_oauth_state');
            set_transient('dsl_oauth_state_' . $state, time(), 600); // 10 minutes
        }
        
        $params = array(
            'client_id' => $this->client_id,
            'response_type' => 'code',
            'redirect_uri' => $this->redirect_uri,
            'response_mode' => 'query',
            'scope' => 'openid profile email User.Read',
            'state' => $state
        );
        
        $auth_url = "https://login.microsoftonline.com/{$this->tenant_id}/oauth2/v2.0/authorize";
        
        return add_query_arg($params, $auth_url);
    }
    
    /**
     * Handle OAuth callback
     */
    public function handle_oauth_callback() {
        // Handle demo mode login
        if (isset($_GET['dynamics_demo_login'])) {
            $this->handle_demo_login();
            return;
        }
        
        // Check if this is OAuth callback
        if (!get_query_var('dynamics_oauth_callback')) {
            return;
        }
        
        // Check for error
        if (isset($_GET['error'])) {
            $error_description = isset($_GET['error_description']) ? 
                sanitize_text_field($_GET['error_description']) : 
                __('Authentication failed', 'dynamics-sync-lite');
            
            DSL_Logger::log('error', 'OAuth login failed: ' . $error_description);
            
            wp_redirect(add_query_arg('login', 'failed', home_url()));
            exit;
        }
        
        // Get authorization code
        $code = isset($_GET['code']) ? sanitize_text_field($_GET['code']) : '';
        $state = isset($_GET['state']) ? sanitize_text_field($_GET['state']) : '';
        
        if (empty($code)) {
            wp_redirect(add_query_arg('login', 'failed', home_url()));
            exit;
        }
        
        // Verify state
        $stored_state = get_transient('dsl_oauth_state_' . $state);
        if ($stored_state === false) {
            DSL_Logger::log('error', 'Invalid OAuth state');
            wp_redirect(add_query_arg('login', 'failed', home_url()));
            exit;
        }
        
        // Delete used state
        delete_transient('dsl_oauth_state_' . $state);
        
        // Exchange code for token
        $token_data = $this->exchange_code_for_token($code);
        
        if (is_wp_error($token_data)) {
            DSL_Logger::log('error', 'Token exchange failed: ' . $token_data->get_error_message());
            wp_redirect(add_query_arg('login', 'failed', home_url()));
            exit;
        }
        
        // Get user info from token
        $user_info = $this->get_user_info_from_token($token_data['access_token']);
        
        if (is_wp_error($user_info)) {
            DSL_Logger::log('error', 'Failed to get user info: ' . $user_info->get_error_message());
            wp_redirect(add_query_arg('login', 'failed', home_url()));
            exit;
        }
        
        // Login or create WordPress user
        $wp_user = $this->login_or_create_user($user_info);
        
        if (is_wp_error($wp_user)) {
            DSL_Logger::log('error', 'User creation failed: ' . $wp_user->get_error_message());
            wp_redirect(add_query_arg('login', 'failed', home_url()));
            exit;
        }
        
        // Log the user in
        wp_set_auth_cookie($wp_user->ID, true);
        
        DSL_Logger::log('success', 'User logged in via OAuth', array(
            'user_id' => $wp_user->ID,
            'email' => $user_info['email']
        ));
        
        // Redirect to profile page or home
        $redirect_to = get_option('dsl_oauth_redirect_url', home_url());
        wp_redirect($redirect_to);
        exit;
    }
    
    /**
     * Handle demo mode login
     */
    private function handle_demo_login() {
        // In demo mode, create or login as demo user
        $demo_email = 'demo@example.com';
        $wp_user = get_user_by('email', $demo_email);
        
        if (!$wp_user) {
            // Create demo user
            $user_id = wp_create_user(
                'demouser',
                wp_generate_password(),
                $demo_email
            );
            
            if (is_wp_error($user_id)) {
                wp_redirect(add_query_arg('login', 'failed', home_url()));
                exit;
            }
            
            wp_update_user(array(
                'ID' => $user_id,
                'first_name' => 'Demo',
                'last_name' => 'User',
                'display_name' => 'Demo User'
            ));
            
            $wp_user = get_user_by('id', $user_id);
        }
        
        wp_set_auth_cookie($wp_user->ID, true);
        
        DSL_Logger::log('success', 'Demo user logged in', array(
            'user_id' => $wp_user->ID
        ));
        
        $redirect_to = get_option('dsl_oauth_redirect_url', home_url());
        wp_redirect($redirect_to);
        exit;
    }
    
    /**
     * Exchange authorization code for access token
     */
    private function exchange_code_for_token($code) {
        $token_url = "https://login.microsoftonline.com/{$this->tenant_id}/oauth2/v2.0/token";
        
        $body = array(
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'code' => $code,
            'redirect_uri' => $this->redirect_uri,
            'grant_type' => 'authorization_code',
            'scope' => 'openid profile email User.Read'
        );
        
        $response = wp_remote_post($token_url, array(
            'body' => $body,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($data['access_token'])) {
            return new WP_Error('token_error', __('Failed to get access token', 'dynamics-sync-lite'));
        }
        
        return $data;
    }
    
    /**
     * Get user info from Microsoft Graph API
     */
    private function get_user_info_from_token($access_token) {
        $response = wp_remote_get('https://graph.microsoft.com/v1.0/me', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Accept' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $user_data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($user_data['mail']) && !isset($user_data['userPrincipalName'])) {
            return new WP_Error('no_email', __('No email found in user data', 'dynamics-sync-lite'));
        }
        
        return array(
            'email' => $user_data['mail'] ?? $user_data['userPrincipalName'],
            'first_name' => $user_data['givenName'] ?? '',
            'last_name' => $user_data['surname'] ?? '',
            'display_name' => $user_data['displayName'] ?? '',
            'id' => $user_data['id'] ?? ''
        );
    }
    
    /**
     * Login or create WordPress user
     */
    private function login_or_create_user($user_info) {
        $email = sanitize_email($user_info['email']);
        
        // Check if user exists
        $wp_user = get_user_by('email', $email);
        
        if ($wp_user) {
            // Update user meta with Microsoft ID
            update_user_meta($wp_user->ID, 'dsl_microsoft_id', $user_info['id']);
            return $wp_user;
        }
        
        // Check if auto-registration is enabled
        if (get_option('dsl_oauth_auto_register', '1') !== '1') {
            return new WP_Error('registration_disabled', __('User registration is disabled', 'dynamics-sync-lite'));
        }
        
        // Create new user
        $username = sanitize_user($user_info['email']);
        $username = str_replace('@', '_', $username);
        
        // Make sure username is unique
        $base_username = $username;
        $counter = 1;
        while (username_exists($username)) {
            $username = $base_username . $counter;
            $counter++;
        }
        
        $user_id = wp_create_user(
            $username,
            wp_generate_password(),
            $email
        );
        
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        // Update user data
        wp_update_user(array(
            'ID' => $user_id,
            'first_name' => $user_info['first_name'],
            'last_name' => $user_info['last_name'],
            'display_name' => $user_info['display_name']
        ));
        
        // Store Microsoft ID
        update_user_meta($user_id, 'dsl_microsoft_id', $user_info['id']);
        
        DSL_Logger::log('success', 'New user registered via OAuth', array(
            'user_id' => $user_id,
            'email' => $email
        ));
        
        return get_user_by('id', $user_id);
    }
    
    /**
     * Render login button shortcode
     */
    public function render_login_button($atts) {
        if (is_user_logged_in()) {
            return '<div class="dsl-oauth-logged-in">' . 
                   sprintf(__('Welcome, %s! <a href="%s">Logout</a>', 'dynamics-sync-lite'), 
                   wp_get_current_user()->display_name,
                   wp_logout_url()) . 
                   '</div>';
        }
        
        if (!$this->is_oauth_enabled() && !DSL_Demo_Mode::is_enabled()) {
            return '<div class="dsl-notice dsl-notice-warning">' . 
                   __('OAuth login is not enabled.', 'dynamics-sync-lite') . 
                   '</div>';
        }
        
        $atts = shortcode_atts(array(
            'text' => __('Sign in with Microsoft', 'dynamics-sync-lite'),
            'class' => 'dsl-oauth-button'
        ), $atts);
        
        $auth_url = $this->get_authorization_url();
        
        ob_start();
        ?>
        <div class="dsl-oauth-login">
            <a href="<?php echo esc_url($auth_url); ?>" class="<?php echo esc_attr($atts['class']); ?>">
                <svg width="21" height="21" viewBox="0 0 21 21" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 8px;">
                    <rect x="1" y="1" width="9" height="9" fill="#f25022"/>
                    <rect x="1" y="11" width="9" height="9" fill="#00a4ef"/>
                    <rect x="11" y="1" width="9" height="9" fill="#7fba00"/>
                    <rect x="11" y="11" width="9" height="9" fill="#ffb900"/>
                </svg>
                <?php echo esc_html($atts['text']); ?>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }
}