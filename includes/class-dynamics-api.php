<?php
/**
 * Dynamics 365 API Handler (FIXED VERSION)
 * 
 * Handles all communication with Microsoft Dynamics 365 API
 * With improved error handling and 500 error fixes
 */

if (!defined('ABSPATH')) {
    exit;
}

class DSL_Dynamics_API {
    
    private static $instance = null;
    private $access_token = null;
    private $token_expires = 0;
    
    // API Configuration
    private $client_id;
    private $client_secret;
    private $tenant_id;
    private $resource_url;
    private $api_version;
    
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
        $this->load_config();
    }
    
    /**
     * Load API configuration from WordPress options
     */
    private function load_config() {
        $this->client_id = get_option('dsl_client_id', '');
        $this->client_secret = get_option('dsl_client_secret', '');
        $this->tenant_id = get_option('dsl_tenant_id', '');
        $this->resource_url = get_option('dsl_resource_url', '');
        $this->api_version = get_option('dsl_api_version', '9.2');
        
        // Ensure trailing slash on resource URL
        if (!empty($this->resource_url) && substr($this->resource_url, -1) !== '/') {
            $this->resource_url .= '/';
        }
    }
    
    /**
     * Check if API is configured
     */
    public function is_configured() {
        // Demo mode is always "configured"
        if (DSL_Demo_Mode::is_enabled()) {
            return true;
        }
        
        return !empty($this->client_id) && 
               !empty($this->client_secret) && 
               !empty($this->tenant_id) && 
               !empty($this->resource_url);
    }
    
    /**
     * Get OAuth 2.0 access token with enhanced error handling
     */
    private function get_access_token() {
        // Return cached token if still valid
        if ($this->access_token && time() < $this->token_expires) {
            return $this->access_token;
        }
        
        // Check transient for stored token
        $cached_token = get_transient('dsl_access_token');
        if ($cached_token && is_array($cached_token)) {
            $this->access_token = $cached_token['token'];
            $this->token_expires = $cached_token['expires'];
            if (time() < $this->token_expires) {
                return $this->access_token;
            }
        }
        
        // Request new token
        $token_url = "https://login.microsoftonline.com/{$this->tenant_id}/oauth2/v2.0/token";
        
        // Build scope correctly
        $scope = rtrim($this->resource_url, '/') . '/.default';
        
        $body = array(
            'grant_type' => 'client_credentials',
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'scope' => $scope
        );
        
        DSL_Logger::log('info', 'Requesting access token', array(
            'token_url' => $token_url,
            'scope' => $scope,
            'client_id_length' => strlen($this->client_id),
            'tenant_id_length' => strlen($this->tenant_id)
        ));
        
        $response = wp_remote_post($token_url, array(
            'body' => $body,
            'timeout' => 30,
            'sslverify' => true,
            'user-agent' => 'Dynamics-Sync-Lite/1.0',
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));
        
        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            DSL_Logger::log('error', 'Token request failed: ' . $error_msg, array(
                'error_code' => $response->get_error_code()
            ));
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        DSL_Logger::log('info', 'Token response received', array(
            'status' => $status_code,
            'body_length' => strlen($response_body)
        ));
        
        $body = json_decode($response_body, true);
        
        if ($status_code === 200 && isset($body['access_token'])) {
            $this->access_token = $body['access_token'];
            $this->token_expires = time() + ($body['expires_in'] - 300); // 5 min buffer
            
            // Cache token
            set_transient('dsl_access_token', array(
                'token' => $this->access_token,
                'expires' => $this->token_expires
            ), $body['expires_in'] - 300);
            
            DSL_Logger::log('success', 'Access token obtained successfully');
            return $this->access_token;
        }
        
        $error_desc = $body['error_description'] ?? $body['error'] ?? 'Unknown error';
        DSL_Logger::log('error', 'Failed to obtain access token: ' . $error_desc, array(
            'status' => $status_code,
            'response' => $body
        ));
        return false;
    }
    
    /**
     * Make API request with enhanced error handling
     */
    private function request($method, $endpoint, $data = null) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Dynamics API is not configured', 'dynamics-sync-lite'));
        }
        
        $token = $this->get_access_token();
        if (!$token) {
            return new WP_Error('auth_failed', __('Failed to authenticate with Dynamics. Check your credentials.', 'dynamics-sync-lite'));
        }
        
        // Build URL - ensure proper formatting
        $url = $this->resource_url . 'api/data/v' . $this->api_version . '/' . ltrim($endpoint, '/');
        
        $args = array(
            'method' => strtoupper($method),
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'OData-MaxVersion' => '4.0',
                'OData-Version' => '4.0',
                'Prefer' => 'return=representation'
            ),
            'timeout' => 30,
            'sslverify' => true,
            'user-agent' => 'Dynamics-Sync-Lite/1.0'
        );
        
        if ($data !== null && in_array($method, array('POST', 'PATCH', 'PUT'))) {
            $args['body'] = json_encode($data);
        }
        
        DSL_Logger::log('info', "API Request: {$method} {$endpoint}", array(
            'url' => $url,
            'method' => $method,
            'has_data' => ($data !== null)
        ));
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            DSL_Logger::log('error', 'API request failed: ' . $error_msg, array(
                'error_code' => $response->get_error_code(),
                'url' => $url
            ));
            return new WP_Error('request_failed', 'API request failed: ' . $error_msg);
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        DSL_Logger::log('info', "API Response: Status {$status_code}", array(
            'status' => $status_code,
            'body_length' => strlen($body)
        ));
        
        if ($status_code >= 200 && $status_code < 300) {
            DSL_Logger::log('success', "API request successful: {$status_code}");
            $decoded = json_decode($body, true);
            return $decoded !== null ? $decoded : array('success' => true);
        }
        
        // Enhanced error logging
        $error_details = array(
            'status' => $status_code,
            'url' => $url,
            'method' => $method,
            'body_preview' => substr($body, 0, 500)
        );
        
        DSL_Logger::log('error', "API request failed with status {$status_code}", $error_details);
        
        // Parse error message
        $error_body = json_decode($body, true);
        $error_msg = 'Unknown error';
        
        if (is_array($error_body) && isset($error_body['error'])) {
            if (is_array($error_body['error'])) {
                $error_msg = $error_body['error']['message'] ?? json_encode($error_body['error']);
            } else {
                $error_msg = $error_body['error'];
            }
        } elseif (!empty($body)) {
            $error_msg = substr($body, 0, 200);
        }
        
        return new WP_Error('api_error', __('Dynamics API Error: ', 'dynamics-sync-lite') . $error_msg, array(
            'status' => $status_code,
            'body' => $body
        ));
    }
    
    /**
     * Get contact by email
     */
    public function get_contact_by_email($email) {
        // Use demo mode if enabled
        if (DSL_Demo_Mode::is_enabled()) {
            return DSL_Demo_Mode::get_contact_by_email($email);
        }
        
        $email = sanitize_email($email);
        $endpoint = "contacts?\$filter=emailaddress1 eq '{$email}'&\$select=contactid,firstname,lastname,emailaddress1,telephone1,address1_line1,address1_city,address1_stateorprovince,address1_postalcode,address1_country";
        
        $result = $this->request('GET', $endpoint);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        if (isset($result['value']) && !empty($result['value'])) {
            return $result['value'][0];
        }
        
        return new WP_Error('not_found', __('Contact not found in Dynamics', 'dynamics-sync-lite'));
    }
    
    /**
     * Update contact
     */
    public function update_contact($contact_id, $data) {
        // Use demo mode if enabled
        if (DSL_Demo_Mode::is_enabled()) {
            return DSL_Demo_Mode::update_contact($contact_id, $data);
        }
        
        $contact_id = sanitize_text_field($contact_id);
        
        // Sanitize and prepare data
        $update_data = array();
        
        if (isset($data['firstname'])) {
            $update_data['firstname'] = sanitize_text_field($data['firstname']);
        }
        if (isset($data['lastname'])) {
            $update_data['lastname'] = sanitize_text_field($data['lastname']);
        }
        if (isset($data['emailaddress1'])) {
            $update_data['emailaddress1'] = sanitize_email($data['emailaddress1']);
        }
        if (isset($data['telephone1'])) {
            $update_data['telephone1'] = sanitize_text_field($data['telephone1']);
        }
        if (isset($data['address1_line1'])) {
            $update_data['address1_line1'] = sanitize_text_field($data['address1_line1']);
        }
        if (isset($data['address1_city'])) {
            $update_data['address1_city'] = sanitize_text_field($data['address1_city']);
        }
        if (isset($data['address1_stateorprovince'])) {
            $update_data['address1_stateorprovince'] = sanitize_text_field($data['address1_stateorprovince']);
        }
        if (isset($data['address1_postalcode'])) {
            $update_data['address1_postalcode'] = sanitize_text_field($data['address1_postalcode']);
        }
        if (isset($data['address1_country'])) {
            $update_data['address1_country'] = sanitize_text_field($data['address1_country']);
        }
        
        $endpoint = "contacts({$contact_id})";
        
        DSL_Logger::log('info', 'Updating contact: ' . $contact_id, array(
            'data' => $update_data
        ));
        
        return $this->request('PATCH', $endpoint, $update_data);
    }
    
    /**
     * Create contact
     */
    public function create_contact($data) {
        // Use demo mode if enabled
        if (DSL_Demo_Mode::is_enabled()) {
            return DSL_Demo_Mode::create_contact($data);
        }
        
        // Sanitize data
        $create_data = array(
            'firstname' => sanitize_text_field($data['firstname'] ?? ''),
            'lastname' => sanitize_text_field($data['lastname'] ?? ''),
            'emailaddress1' => sanitize_email($data['emailaddress1'] ?? ''),
            'telephone1' => sanitize_text_field($data['telephone1'] ?? ''),
            'address1_line1' => sanitize_text_field($data['address1_line1'] ?? ''),
            'address1_city' => sanitize_text_field($data['address1_city'] ?? ''),
            'address1_stateorprovince' => sanitize_text_field($data['address1_stateorprovince'] ?? ''),
            'address1_postalcode' => sanitize_text_field($data['address1_postalcode'] ?? ''),
            'address1_country' => sanitize_text_field($data['address1_country'] ?? '')
        );
        
        DSL_Logger::log('info', 'Creating new contact', array(
            'email' => $create_data['emailaddress1']
        ));
        
        return $this->request('POST', 'contacts', $create_data);
    }
    
    /**
     * Test API connection with detailed diagnostics
     */
    public function test_connection() {
        // Use demo mode if enabled
        if (DSL_Demo_Mode::is_enabled()) {
            return DSL_Demo_Mode::test_connection();
        }
        
        // Validate configuration
        if (empty($this->client_id)) {
            return array(
                'success' => false,
                'message' => __('Client ID is not configured', 'dynamics-sync-lite')
            );
        }
        
        if (empty($this->client_secret)) {
            return array(
                'success' => false,
                'message' => __('Client Secret is not configured', 'dynamics-sync-lite')
            );
        }
        
        if (empty($this->tenant_id)) {
            return array(
                'success' => false,
                'message' => __('Tenant ID is not configured', 'dynamics-sync-lite')
            );
        }
        
        if (empty($this->resource_url)) {
            return array(
                'success' => false,
                'message' => __('Resource URL is not configured', 'dynamics-sync-lite')
            );
        }
        
        // Verify Resource URL format
        if (strpos($this->resource_url, 'https://') !== 0) {
            return array(
                'success' => false,
                'message' => __('Resource URL must start with https://', 'dynamics-sync-lite')
            );
        }
        
        // Test token retrieval
        $token = $this->get_access_token();
        if (!$token) {
            return array(
                'success' => false,
                'message' => __('Failed to obtain access token. Check Client ID, Client Secret, and Tenant ID.', 'dynamics-sync-lite')
            );
        }
        
        // Test API endpoint
        $endpoint = 'contacts?\$top=1';
        $result = $this->request('GET', $endpoint);
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'message' => __('API request failed: ', 'dynamics-sync-lite') . $result->get_error_message()
            );
        }
        
        return array(
            'success' => true,
            'message' => __('Connection successful! API is working correctly.', 'dynamics-sync-lite')
        );
    }
}