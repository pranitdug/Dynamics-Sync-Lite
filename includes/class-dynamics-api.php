<?php
/**
 * Dynamics 365 API Handler (FIXED - No 500 Errors)
 * 
 * Handles all communication with Microsoft Dynamics 365 API
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
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_config();
    }
    
    private function load_config() {
        $this->client_id = trim(get_option('dsl_client_id', ''));
        $this->client_secret = trim(get_option('dsl_client_secret', ''));
        $this->tenant_id = trim(get_option('dsl_tenant_id', ''));
        $this->resource_url = trim(get_option('dsl_resource_url', ''));
        $this->api_version = get_option('dsl_api_version', '9.2');
        
        // Ensure trailing slash
        if (!empty($this->resource_url) && substr($this->resource_url, -1) !== '/') {
            $this->resource_url .= '/';
        }
    }
    
    public function is_configured() {
        if (DSL_Demo_Mode::is_enabled()) {
            return true;
        }
        
        return !empty($this->client_id) && 
               !empty($this->client_secret) && 
               !empty($this->tenant_id) && 
               !empty($this->resource_url);
    }
    
    /**
     * Get OAuth 2.0 access token - FIXED
     */
    private function get_access_token() {
        // Return cached token if valid
        if ($this->access_token && time() < $this->token_expires) {
            return $this->access_token;
        }
        
        // Check transient
        $cached = get_transient('dsl_access_token');
        if ($cached && is_array($cached) && isset($cached['token'], $cached['expires'])) {
            if (time() < $cached['expires']) {
                $this->access_token = $cached['token'];
                $this->token_expires = $cached['expires'];
                return $this->access_token;
            }
        }
        
        // Request new token
        $token_url = "https://login.microsoftonline.com/{$this->tenant_id}/oauth2/v2.0/token";
        $scope = rtrim($this->resource_url, '/') . '/.default';
        
        $response = wp_remote_post($token_url, array(
            'body' => array(
                'grant_type' => 'client_credentials',
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'scope' => $scope
            ),
            'timeout' => 30,
            'sslverify' => true,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));
        
        if (is_wp_error($response)) {
            DSL_Logger::log('error', 'Token request failed: ' . $response->get_error_message());
            return false;
        }
        
        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($status === 200 && isset($body['access_token'])) {
            $this->access_token = $body['access_token'];
            $this->token_expires = time() + ($body['expires_in'] - 300);
            
            set_transient('dsl_access_token', array(
                'token' => $this->access_token,
                'expires' => $this->token_expires
            ), $body['expires_in'] - 300);
            
            DSL_Logger::log('success', 'Access token obtained', array(
                'expires_in' => $body['expires_in']
            ));
            return $this->access_token;
        }
        
        // Enhanced error logging
        $error = $body['error_description'] ?? $body['error'] ?? 'Unknown error';
        DSL_Logger::log('error', 'Token error: ' . $error, array(
            'status' => $status,
            'error_code' => $body['error'] ?? 'unknown',
            'full_response' => $body
        ));
        return false;
    }
    
    /**
     * Make API request - FIXED for 500 errors
     */
    private function request($method, $endpoint, $data = null) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', 'API not configured');
        }
        
        $token = $this->get_access_token();
        if (!$token) {
            return new WP_Error('auth_failed', 'Authentication failed');
        }
        
        // Build URL properly
        $base_url = rtrim($this->resource_url, '/');
        $endpoint = ltrim($endpoint, '/');
        $url = "{$base_url}/api/data/v{$this->api_version}/{$endpoint}";
        
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
            'sslverify' => true
        );
        
        if ($data !== null && in_array($method, array('POST', 'PATCH', 'PUT'))) {
            $args['body'] = wp_json_encode($data);
        }
        
        DSL_Logger::log('info', "API {$method} {$endpoint}");
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            DSL_Logger::log('error', 'Request failed: ' . $response->get_error_message());
            return new WP_Error('request_failed', $response->get_error_message());
        }
        
        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Success codes
        if ($status >= 200 && $status < 300) {
            DSL_Logger::log('success', "API success: {$status}");
            $decoded = json_decode($body, true);
            return $decoded !== null ? $decoded : array('success' => true);
        }
        
        // Error handling
        DSL_Logger::log('error', "API error {$status}", array(
            'body' => substr($body, 0, 500)
        ));
        
        $error_body = json_decode($body, true);
        $error_msg = 'Unknown error';
        
        if (is_array($error_body) && isset($error_body['error'])) {
            if (is_array($error_body['error'])) {
                $error_msg = $error_body['error']['message'] ?? 'API error';
            } else {
                $error_msg = $error_body['error'];
            }
        }
        
        return new WP_Error('api_error', "API Error ({$status}): {$error_msg}");
    }
    
    /**
     * Get contact by email - FIXED
     */
    public function get_contact_by_email($email) {
        if (DSL_Demo_Mode::is_enabled()) {
            return DSL_Demo_Mode::get_contact_by_email($email);
        }
        
        $email = sanitize_email($email);
        
        // Use proper OData filter encoding
        $filter = rawurlencode("emailaddress1 eq '{$email}'");
        $select = 'contactid,firstname,lastname,emailaddress1,telephone1,address1_line1,address1_city,address1_stateorprovince,address1_postalcode,address1_country';
        $endpoint = "contacts?\$filter={$filter}&\$select={$select}&\$top=1";
        
        $result = $this->request('GET', $endpoint);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        if (isset($result['value']) && !empty($result['value'])) {
            return $result['value'][0];
        }
        
        return new WP_Error('not_found', 'Contact not found');
    }
    
    /**
     * Update contact - FIXED
     */
    public function update_contact($contact_id, $data) {
        if (DSL_Demo_Mode::is_enabled()) {
            return DSL_Demo_Mode::update_contact($contact_id, $data);
        }
        
        $contact_id = sanitize_text_field($contact_id);
        
        // Prepare update data - only include non-empty fields
        $update_data = array();
        
        $field_map = array(
            'firstname' => 'firstname',
            'lastname' => 'lastname',
            'emailaddress1' => 'emailaddress1',
            'telephone1' => 'telephone1',
            'address1_line1' => 'address1_line1',
            'address1_city' => 'address1_city',
            'address1_stateorprovince' => 'address1_stateorprovince',
            'address1_postalcode' => 'address1_postalcode',
            'address1_country' => 'address1_country'
        );
        
        foreach ($field_map as $input_key => $api_key) {
            if (isset($data[$input_key])) {
                $value = sanitize_text_field($data[$input_key]);
                if (!empty($value) || $value === '') {
                    $update_data[$api_key] = $value;
                }
            }
        }
        
        if (empty($update_data)) {
            return new WP_Error('no_data', 'No data to update');
        }
        
        $endpoint = "contacts({$contact_id})";
        
        DSL_Logger::log('info', "Updating contact {$contact_id}", array(
            'fields' => array_keys($update_data)
        ));
        
        return $this->request('PATCH', $endpoint, $update_data);
    }
    
    /**
     * Create contact - FIXED
     */
    public function create_contact($data) {
        if (DSL_Demo_Mode::is_enabled()) {
            return DSL_Demo_Mode::create_contact($data);
        }
        
        $create_data = array(
            'firstname' => sanitize_text_field($data['firstname'] ?? ''),
            'lastname' => sanitize_text_field($data['lastname'] ?? ''),
            'emailaddress1' => sanitize_email($data['emailaddress1'] ?? '')
        );
        
        // Add optional fields if provided
        $optional_fields = array(
            'telephone1', 'address1_line1', 'address1_city',
            'address1_stateorprovince', 'address1_postalcode', 'address1_country'
        );
        
        foreach ($optional_fields as $field) {
            if (!empty($data[$field])) {
                $create_data[$field] = sanitize_text_field($data[$field]);
            }
        }
        
        DSL_Logger::log('info', 'Creating contact', array(
            'email' => $create_data['emailaddress1']
        ));
        
        return $this->request('POST', 'contacts', $create_data);
    }
    
    /**
     * Test connection - FIXED
     */
    public function test_connection() {
        if (DSL_Demo_Mode::is_enabled()) {
            return DSL_Demo_Mode::test_connection();
        }
        
        if (!$this->is_configured()) {
            return array(
                'success' => false,
                'message' => 'API is not configured. Please fill in all required fields.'
            );
        }
        
        // Test token
        $token = $this->get_access_token();
        if (!$token) {
            return array(
                'success' => false,
                'message' => 'Failed to obtain access token. Check your credentials.'
            );
        }
        
        // Test API call
        $result = $this->request('GET', 'contacts?\$top=1&\$select=contactid');
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'message' => 'API call failed: ' . $result->get_error_message()
            );
        }
        
        return array(
            'success' => true,
            'message' => 'Connection successful! API is working correctly.'
        );
    }
}