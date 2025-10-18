<?php

/**
 * OAuth Login Handler - Session Based
 * Stores OAuth token in session without creating WordPress user
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

    public function check_session()
    {
        check_ajax_referer('dsl_ajax_nonce', 'nonce');

        $this->start_session();
        $is_auth = $this->is_session_authenticated();

        wp_send_json_success(array(
            'authenticated' => $is_auth
        ));
    }
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->client_id = get_option('dsl_client_id', '');
        $this->tenant_id = get_option('dsl_tenant_id', '');
        $this->client_secret = get_option('dsl_client_secret', '');
        $this->redirect_uri = home_url('/dynamics-oauth-callback/');

        add_action('init', array($this, 'register_endpoints'));
        add_action('template_redirect', array($this, 'handle_oauth_callback'));
        add_shortcode('dynamics_login_independent', array($this, 'render_login_button'));
        add_action('wp_ajax_dsl_oauth_logout', array($this, 'handle_logout'));
        add_action('wp_ajax_nopriv_dsl_oauth_logout', array($this, 'handle_logout'));
        add_action('wp_ajax_dsl_check_oauth_session', array($this, 'check_session'));
        add_action('wp_ajax_nopriv_dsl_check_oauth_session', array($this, 'check_session'));
    }

    public function register_endpoints()
    {
        // Add query variable
        add_filter('query_vars', array($this, 'add_query_vars'));

        // Register rewrite rule
        add_action('init', array($this, 'flush_rewrite_rules_callback'));
    }

    public function add_query_vars($vars)
    {
        $vars[] = 'dynamics_oauth_callback';
        return $vars;
    }

    public function flush_rewrite_rules_callback()
    {
        add_rewrite_rule(
            '^dynamics-oauth-callback/?$',
            'index.php?dynamics_oauth_callback=1',
            'top'
        );
    }

    private function start_session()
    {
        if (!isset($_SESSION)) {
            session_start();
        }
    }

    private function is_session_authenticated()
    {
        $this->start_session();
        return !empty($_SESSION['dsl_oauth_token']) && !empty($_SESSION['dsl_oauth_email']);
    }

    public function get_authorization_url($state = '')
    {
        if (empty($state)) {
            $state = wp_create_nonce('dsl_oauth_state');
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
            $error = sanitize_text_field($_GET['error_description'] ?? 'Authentication failed');
            DSL_Logger::log('error', 'OAuth callback error: ' . $error);
            wp_redirect(add_query_arg('login', 'failed', home_url()));
            exit;
        }

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
        delete_transient('dsl_oauth_state_' . $state);

        // Exchange code for token
        $token_data = $this->exchange_code_for_token($code);
        if (is_wp_error($token_data)) {
            DSL_Logger::log('error', 'Token exchange failed: ' . $token_data->get_error_message());
            wp_redirect(add_query_arg('login', 'failed', home_url()));
            exit;
        }

        // Get user info
        $user_info = $this->get_user_info_from_token($token_data['access_token']);
        if (is_wp_error($user_info)) {
            DSL_Logger::log('error', 'Get user info failed: ' . $user_info->get_error_message());
            wp_redirect(add_query_arg('login', 'failed', home_url()));
            exit;
        }

        // Store in session (NOT WordPress)
        $this->start_session();
        $_SESSION['dsl_oauth_token'] = $token_data['access_token'];
        $_SESSION['dsl_oauth_email'] = $user_info['email'];
        $_SESSION['dsl_oauth_user_info'] = $user_info;
        $_SESSION['dsl_oauth_expires'] = time() + ($token_data['expires_in'] ?? 3600);

        DSL_Logger::log('success', 'OAuth: User authenticated (session)', array(
            'email' => $user_info['email']
        ));

        // Redirect to profile page
        $redirect_to = get_option('dsl_oauth_redirect_url', home_url());
        wp_redirect($redirect_to);
        exit;
    }

    private function handle_demo_login()
    {
        $this->start_session();
        $_SESSION['dsl_oauth_token'] = 'demo_token_' . wp_generate_password(32);
        $_SESSION['dsl_oauth_email'] = 'demo@example.com';
        $_SESSION['dsl_oauth_user_info'] = array(
            'email' => 'demo@example.com',
            'first_name' => 'Demo',
            'last_name' => 'User',
            'display_name' => 'Demo User'
        );

        DSL_Logger::log('success', 'Demo mode: OAuth session created');
        wp_redirect(get_option('dsl_oauth_redirect_url', home_url()));
        exit;
    }

    public function handle_logout()
    {
        check_ajax_referer('dsl_ajax_nonce', 'nonce');

        $this->start_session();

        // Clear session
        $_SESSION['dsl_oauth_token'] = '';
        $_SESSION['dsl_oauth_email'] = '';
        $_SESSION['dsl_oauth_user_info'] = array();
        session_destroy();

        DSL_Logger::log('info', 'OAuth: User logged out');

        wp_send_json_success(array(
            'message' => __('Logged out successfully', 'dynamics-sync-lite'),
            'redirect' => home_url()
        ));
    }

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

        $user_data = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($user_data['mail']) && !isset($user_data['userPrincipalName'])) {
            return new WP_Error('no_email', __('No email in user data', 'dynamics-sync-lite'));
        }

        return array(
            'email' => $user_data['mail'] ?? $user_data['userPrincipalName'],
            'first_name' => $user_data['givenName'] ?? '',
            'last_name' => $user_data['surname'] ?? '',
            'display_name' => $user_data['displayName'] ?? '',
            'id' => $user_data['id'] ?? ''
        );
    }

    public function render_login_button($atts)
    {
        $this->start_session();

        // Already authenticated
        if ($this->is_session_authenticated()) {
            $email = $_SESSION['dsl_oauth_email'];
            $name = $_SESSION['dsl_oauth_user_info']['display_name'] ?? 'User';

            return '<div class="dsl-oauth-logged-in">
                <p>✅ ' . sprintf(__('Signed in as: %s (%s)', 'dynamics-sync-lite'), esc_html($name), esc_html($email)) . '</p>
                <button type="button" class="dsl-oauth-button dsl-oauth-button-secondary" id="dsl-logout-btn">
                    ' . __('Sign Out', 'dynamics-sync-lite') . '
                </button>
                <script>
                jQuery(document).ready(function($) {
                    $("#dsl-logout-btn").on("click", function() {
                        $.ajax({
                            url: dslAjax.ajaxurl,
                            type: "POST",
                            data: {
                                action: "dsl_oauth_logout",
                                nonce: dslAjax.nonce
                            },
                            success: function(res) {
                                if (res.success) {
                                    location.reload();
                                }
                            }
                        });
                    });
                });
                </script>
            </div>';
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
                    <rect x="1" y="1" width="9" height="9" fill="#f25022" />
                    <rect x="1" y="11" width="9" height="9" fill="#00a4ef" />
                    <rect x="11" y="1" width="9" height="9" fill="#7fba00" />
                    <rect x="11" y="11" width="9" height="9" fill="#ffb900" />
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
