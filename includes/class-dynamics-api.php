<?php
/**
 * Dynamics 365 API Handler
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
        $this->resource_url = trailingslashit(get_option('dsl_resource_url', ''));
        $this->api_version = get_option('dsl_api_version', '9.2');
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
     * Get OAuth 2.0 access token
     */
    private function get_access_token() {
        // Return cached token if still valid
        if ($this->access_token && time() < $this->token_expires) {
            return $this->access_token;
        }
        
        // Check transient for stored token
        $cached_token = get_transient('dsl_access_token');
        if ($cached_token) {
            $this->access_token = $cached_token['token'];
            $this->token_expires = $cached_token['expires'];
            return $this->access_token;
        }
        
        // Request new token
        $token_url = "https://login.microsoftonline.com/{$this->tenant_id}/oauth2/v2.0/token";
        
        $body = array(
            'grant_type' => 'client_credentials',
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'scope' => $this->resource_url . '.default'
        );
        
        $response = wp_remote_post($token_url, array(
            'body' => $body,
            'timeout' => 30,
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            DSL_Logger::log('error', 'Token request failed: ' . $response->get_error_message());
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['access_token'])) {
            $this->access_token = $body['access_token'];
            $this->token_expires = time() + ($body['expires_in'] - 300); // 5 min buffer
            
            // Cache token
            set_transient('dsl_access_token', array(
                'token' => $this->access_token,
                'expires' => $this->token_expires
            ), $body['expires_in'] - 300);
            
            DSL_Logger::log('info', 'Access token obtained successfully');
            return $this->access_token;
        }
        
        DSL_Logger::log('error', 'Failed to obtain access token: ' . print_r($body, true));
        return false;
    }
    
    /**
     * Make API request
     */
    private function request($method, $endpoint, $data = null) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Dynamics API is not configured', 'dynamics-sync-lite'));
        }
        
        $token = $this->get_access_token();
        if (!$token) {
            return new WP_Error('auth_failed', __('Failed to authenticate with Dynamics', 'dynamics-sync-lite'));
        }
        
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
            'sslverify' => true
        );
        
        if ($data !== null && in_array($method, array('POST', 'PATCH', 'PUT'))) {
            $args['body'] = json_encode($data);
        }
        
        DSL_Logger::log('info', "API Request: {$method} {$endpoint}");
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            DSL_Logger::log('error', 'API request failed: ' . $response->get_error_message());
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code >= 200 && $status_code < 300) {
            DSL_Logger::log('success', "API request successful: {$status_code}");
            return json_decode($body, true);
        }
        
        DSL_Logger::log('error', "API request failed with status {$status_code}: {$body}");
        return new WP_Error('api_error', __('Dynamics API request failed', 'dynamics-sync-lite'), array(
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
            'user_id' => get_current_user_id(),
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
            'user_id' => get_current_user_id(),
            'email' => $create_data['emailaddress1']
        ));
        
        return $this->request('POST', 'contacts', $create_data);
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        // Use demo mode if enabled
        if (DSL_Demo_Mode::is_enabled()) {
            return DSL_Demo_Mode::test_connection();
        }
        
        $endpoint = 'contacts?\$top=1';
        $result = $this->request('GET', $endpoint);
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'message' => $result->get_error_message()
            );
        }
        
        return array(
            'success' => true,
            'message' => __('Connection successful!', 'dynamics-sync-lite')
        );
    }
}