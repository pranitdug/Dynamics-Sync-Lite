<?php
/**
 * OAuth Login Session Handler - PRODUCTION READY
 * 
 * Handles OAuth authentication without creating WordPress users
 * Uses PHP sessions to maintain authentication state
 */

if (!defined('ABSPATH')) {
    exit;
}

class DSL_OAuth_Login_Session
{
    private static $instance = null;
    private $client_id;
    private $client_secret;
    private $tenant_id;
    private $redirect_uri;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->client_id = trim(get_option('dsl_client_id', ''));
        $this->tenant_id = trim(get_option('dsl_tenant_id', ''));
        $this->client_secret = trim(get_option('dsl_client_secret', ''));
        $this->redirect_uri = home_url('/dynamics-oauth-callback/');

        // Critical: Start session very early
        add_action('init', array($this, 'start_session'), 1);
        add_action('init', array($this, 'register_endpoints'), 5);
        add_action('template_redirect', array($this, 'handle_oauth_callback'));
        add_shortcode('dynamics_login_independent', array($this, 'render_login_button'));
        
        // AJAX handlers
        add_action('wp_ajax_dsl_oauth_logout', array($this, 'handle_logout'));
        add_action('wp_ajax_nopriv_dsl_oauth_logout', array($this, 'handle_logout'));
        add_action('wp_ajax_dsl_check_oauth_session', array($this, 'check_session'));
        add_action('wp_ajax_nopriv_dsl_check_oauth_session', array($this, 'check_session'));
    }

    /**
     * Start PHP session early
     */
    public function start_session()
    {
        if (!session_id() && !headers_sent()) {
            session_start();
        }
    }

    /**
     * Register rewrite endpoints
     */
    public function register_endpoints()
    {
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
     * Check if user is authenticated via session
     */
    private function is_session_authenticated()
    {
        $has_token = !empty($_SESSION['dsl_oauth_token']);
        $has_email = !empty($_SESSION['dsl_oauth_email']);
        $not_expired = !isset($_SESSION['dsl_oauth_expires']) || $_SESSION['dsl_oauth_expires'] > time();
        
        return $has_token && $has_email && $not_expired;
    }

    /**
     * Get session data
     */
    private function get_session_data()
    {
        return array(
            'token' => $_SESSION['dsl_oauth_token'] ?? '',
            'email' => $_SESSION['dsl_oauth_email'] ?? '',
            'user_info' => $_SESSION['dsl_oauth_user_info'] ?? array(),
            'expires' => $_SESSION['dsl_oauth_expires'] ?? 0,
            'is_authenticated' => $this->is_session_authenticated()
        );
    }

    /**
     * AJAX: Check session status
     */
    public function check_session()
    {
        check_ajax_referer('dsl_ajax_nonce', 'nonce');

        $session_data = $this->get_session_data();
        $is_auth = $session_data['is_authenticated'];

        DSL_Logger::log('info', 'OAuth session check', array(
            'authenticated' => $is_auth,
            'has_token' => !empty($session_data['token']),
            'has_email' => !empty($session_data['email']),
            'expires' => $session_data['expires'],
            'current_time' => time(),
            'expired' => $session_data['expires'] > 0 && $session_data['expires'] < time()
        ));

        wp_send_json_success(array(
            'authenticated' => $is_auth,
            'email' => $is_auth ? $session_data['email'] : '',
            'expires_in' => $is_auth ? ($session_data['expires'] - time()) : 0
        ));
    }

    /**
     * Get OAuth authorization URL
     */
    public function get_authorization_url($state = '')
    {
        if (empty($this->client_id) || empty($this->tenant_id)) {
            DSL_Logger::log('error', 'OAuth not configured', array(
                'has_client_id' => !empty($this->client_id),
                'has_tenant_id' => !empty($this->tenant_id)
            ));
            return '#oauth-not-configured';
        }

        if (empty($state)) {
            $state = wp_create_nonce('dsl_oauth_state_' . time());
            set_transient('dsl_oauth_state_' . $state, time(), 600);
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
    public function handle_oauth_callback()
    {
        if (!get_query_var('dynamics_oauth_callback')) {
            return;
        }

        // Handle demo mode
        if (isset($_GET['demo'])) {
            $this->handle_demo_login();
            return;
        }

        // Check for error
        if (isset($_GET['error'])) {
            $error = sanitize_text_field($_GET['error_description'] ?? $_GET['error'] ?? 'Authentication failed');
            DSL_Logger::log('error', 'OAuth callback error: ' . $error);
            wp_redirect(add_query_arg('oauth_error', 'failed', home_url()));
            exit;
        }

        $code = isset($_GET['code']) ? sanitize_text_field($_GET['code']) : '';
        $state = isset($_GET['state']) ? sanitize_text_field($_GET['state']) : '';

        if (empty($code)) {
            DSL_Logger::log('error', 'OAuth callback: missing code');
            wp_redirect(add_query_arg('oauth_error', 'no_code', home_url()));
            exit;
        }

        // Verify state
        $stored_state = get_transient('dsl_oauth_state_' . $state);
        if ($stored_state === false) {
            DSL_Logger::log('error', 'OAuth callback: invalid state', array(
                'state' => $state
            ));
            wp_redirect(add_query_arg('oauth_error', 'invalid_state', home_url()));
            exit;
        }
        delete_transient('dsl_oauth_state_' . $state);

        // Exchange code for token
        $token_data = $this->exchange_code_for_token($code);
        if (is_wp_error($token_data)) {
            DSL_Logger::log('error', 'OAuth token exchange failed', array(
                'error' => $token_data->get_error_message()
            ));
            wp_redirect(add_query_arg('oauth_error', 'token_failed', home_url()));
            exit;
        }

        // Get user info
        $user_info = $this->get_user_info_from_token($token_data['access_token']);
        if (is_wp_error($user_info)) {
            DSL_Logger::log('error', 'OAuth get user info failed', array(
                'error' => $user_info->get_error_message()
            ));
            wp_redirect(add_query_arg('oauth_error', 'user_info_failed', home_url()));
            exit;
        }

        // Store in session
        $_SESSION['dsl_oauth_token'] = $token_data['access_token'];
        $_SESSION['dsl_oauth_email'] = $user_info['email'];
        $_SESSION['dsl_oauth_user_info'] = $user_info;
        $_SESSION['dsl_oauth_expires'] = time() + (isset($token_data['expires_in']) ? intval($token_data['expires_in']) : 3600);
        $_SESSION['dsl_oauth_login_time'] = time();

        DSL_Logger::log('success', 'OAuth authentication successful', array(
            'email' => $user_info['email'],
            'session_id' => session_id(),
            'expires' => $_SESSION['dsl_oauth_expires']
        ));

        // Redirect to profile page
        $redirect_to = get_option('dsl_oauth_redirect_url', home_url());
        wp_redirect($redirect_to);
        exit;
    }

    /**
     * Handle demo login
     */
    private function handle_demo_login()
    {
        $_SESSION['dsl_oauth_token'] = 'demo_token_' . wp_generate_password(32, false);
        $_SESSION['dsl_oauth_email'] = 'demo@example.com';
        $_SESSION['dsl_oauth_user_info'] = array(
            'email' => 'demo@example.com',
            'first_name' => 'Demo',
            'last_name' => 'User',
            'display_name' => 'Demo User',
            'id' => 'demo-user-id'
        );
        $_SESSION['dsl_oauth_expires'] = time() + 7200; // 2 hours
        $_SESSION['dsl_oauth_login_time'] = time();

        DSL_Logger::log('success', 'Demo mode: OAuth session created');
        wp_redirect(get_option('dsl_oauth_redirect_url', home_url()));
        exit;
    }

    /**
     * Handle logout
     */
    public function handle_logout()
    {
        check_ajax_referer('dsl_ajax_nonce', 'nonce');

        $email = $_SESSION['dsl_oauth_email'] ?? 'unknown';

        // Clear session
        unset($_SESSION['dsl_oauth_token']);
        unset($_SESSION['dsl_oauth_email']);
        unset($_SESSION['dsl_oauth_user_info']);
        unset($_SESSION['dsl_oauth_expires']);
        unset($_SESSION['dsl_oauth_login_time']);

        DSL_Logger::log('info', 'OAuth user logged out', array(
            'email' => $email
        ));

        wp_send_json_success(array(
            'message' => __('Logged out successfully', 'dynamics-sync-lite'),
            'redirect' => home_url()
        ));
    }

    /**
     * Exchange authorization code for access token
     */
    private function exchange_code_for_token($code)
    {
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
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($status !== 200 || !isset($data['access_token'])) {
            $error_msg = isset($data['error_description']) ? $data['error_description'] : __('Failed to get access token', 'dynamics-sync-lite');
            return new WP_Error('token_error', $error_msg);
        }

        return $data;
    }

    /**
     * Get user info from Microsoft Graph
     */
    private function get_user_info_from_token($access_token)
    {
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

        $status = wp_remote_retrieve_response_code($response);
        $user_data = json_decode(wp_remote_retrieve_body($response), true);

        if ($status !== 200 || !isset($user_data['mail']) && !isset($user_data['userPrincipalName'])) {
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
     * Render login button shortcode
     */
    public function render_login_button($atts)
    {
        // Check if authenticated
        if ($this->is_session_authenticated()) {
            $session = $this->get_session_data();
            $email = $session['email'];
            $name = $session['user_info']['display_name'] ?? 'User';

            ob_start();
            ?>
            <div class="dsl-oauth-logged-in">
                <p>✅ <?php echo sprintf(__('Signed in as: %s (%s)', 'dynamics-sync-lite'), esc_html($name), esc_html($email)); ?></p>
                <button type="button" class="dsl-oauth-button dsl-oauth-button-secondary" id="dsl-logout-btn">
                    <?php _e('Sign Out', 'dynamics-sync-lite'); ?>
                </button>
            </div>
            <script>
            jQuery(document).ready(function($) {
                $("#dsl-logout-btn").on("click", function() {
                    var btn = $(this);
                    btn.prop('disabled', true).text('<?php _e('Logging out...', 'dynamics-sync-lite'); ?>');
                    
                    $.ajax({
                        url: dslAjax.ajaxurl,
                        type: "POST",
                        data: {
                            action: "dsl_oauth_logout",
                            nonce: dslAjax.nonce
                        },
                        success: function(res) {
                            if (res.success) {
                                window.location.reload();
                            }
                        },
                        error: function() {
                            btn.prop('disabled', false).text('<?php _e('Sign Out', 'dynamics-sync-lite'); ?>');
                            alert('<?php _e('Logout failed. Please try again.', 'dynamics-sync-lite'); ?>');
                        }
                    });
                });
            });
            </script>
            <?php
            return ob_get_clean();
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

// Initialize
DSL_OAuth_Login_Session::get_instance();