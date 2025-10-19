<?php
/**
 * Dynamics 365 API Handler
 *
 * @package DynamicsSyncLite
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class DSL_API {
    
    private static $instance = null;
    private $client_id;
    private $client_secret;
    private $tenant_id;
    private $resource_url;
    private $access_token = null;
    private $token_expiry = 0;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->client_id = get_option('dsl_client_id', '');
        $this->client_secret = get_option('dsl_client_secret', '');
        $this->tenant_id = get_option('dsl_tenant_id', '');
        $this->resource_url = get_option('dsl_resource_url', '');
    }
    
    /**
     * Get OAuth 2.0 access token
     */
    private function get_access_token() {
        // Check if we have a valid cached token
        $cached_token = get_transient('dsl_access_token');
        if ($cached_token) {
            return $cached_token;
        }
        
        $token_url = "https://login.microsoftonline.com/{$this->tenant_id}/oauth2/v2.0/token";
        
        $body = array(
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'scope' => $this->resource_url . '/.default',
            'grant_type' => 'client_credentials'
        );
        
        $response = wp_remote_post($token_url, array(
            'body' => $body,
            'timeout' => 30,
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            DSL_Logger::log_error('Token request failed: ' . $response->get_error_message());
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['access_token'])) {
            $expires_in = isset($body['expires_in']) ? intval($body['expires_in']) - 300 : 3300; // 5 min buffer
            set_transient('dsl_access_token', $body['access_token'], $expires_in);
            return $body['access_token'];
        }
        
        DSL_Logger::log_error('Token response error: ' . print_r($body, true));
        return false;
    }
    
    /**
     * Make API request
     */
    private function make_request($endpoint, $method = 'GET', $data = null) {
        $token = $this->get_access_token();
        
        if (!$token) {
            return new WP_Error('auth_failed', __('Authentication failed', 'dynamics-sync-lite'));
        }
        
        $url = trailingslashit($this->resource_url) . 'api/data/v9.2/' . ltrim($endpoint, '/');
        
        $args = array(
            'method' => $method,
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'OData-MaxVersion' => '4.0',
                'OData-Version' => '4.0',
                'Prefer' => 'return=representation'
            ),
            'sslverify' => true
        );
        
        if ($data && in_array($method, array('POST', 'PATCH', 'PUT'))) {
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            DSL_Logger::log_error("API request failed: {$method} {$endpoint} - " . $response->get_error_message());
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        
        if ($code >= 200 && $code < 300) {
            return $decoded;
        }
        
        $error_message = isset($decoded['error']['message']) ? $decoded['error']['message'] : 'Unknown error';
        DSL_Logger::log_error("API error ({$code}): {$error_message}");
        
        return new WP_Error('api_error', $error_message, array('status' => $code));
    }
    
    /**
     * Get contact by email
     */
    public function get_contact_by_email($email) {
        if (!is_email($email)) {
            return new WP_Error('invalid_email', __('Invalid email address', 'dynamics-sync-lite'));
        }
        
        $email = sanitize_email($email);
        $filter = "\$filter=emailaddress1 eq '" . addslashes($email) . "'";
        $select = "\$select=contactid,firstname,lastname,emailaddress1,telephone1,address1_line1,address1_city,address1_stateorprovince,address1_postalcode,address1_country";
        
        $endpoint = "contacts?{$filter}&{$select}";
        $result = $this->make_request($endpoint);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        if (isset($result['value']) && count($result['value']) > 0) {
            return $result['value'][0];
        }
        
        return new WP_Error('contact_not_found', __('Contact not found in Dynamics', 'dynamics-sync-lite'));
    }
    
    /**
     * Create contact
     */
    public function create_contact($data) {
        $contact_data = $this->sanitize_contact_data($data);
        return $this->make_request('contacts', 'POST', $contact_data);
    }
    
    /**
     * Update contact
     */
    public function update_contact($contact_id, $data) {
        if (empty($contact_id)) {
            return new WP_Error('invalid_id', __('Invalid contact ID', 'dynamics-sync-lite'));
        }
        
        $contact_data = $this->sanitize_contact_data($data);
        $endpoint = "contacts({$contact_id})";
        
        return $this->make_request($endpoint, 'PATCH', $contact_data);
    }
    
    /**
     * Sanitize contact data
     */
    private function sanitize_contact_data($data) {
        $sanitized = array();
        
        if (isset($data['firstname'])) {
            $sanitized['firstname'] = sanitize_text_field($data['firstname']);
        }
        
        if (isset($data['lastname'])) {
            $sanitized['lastname'] = sanitize_text_field($data['lastname']);
        }
        
        if (isset($data['emailaddress1'])) {
            $sanitized['emailaddress1'] = sanitize_email($data['emailaddress1']);
        }
        
        if (isset($data['telephone1'])) {
            $sanitized['telephone1'] = sanitize_text_field($data['telephone1']);
        }
        
        if (isset($data['address1_line1'])) {
            $sanitized['address1_line1'] = sanitize_text_field($data['address1_line1']);
        }
        
        if (isset($data['address1_city'])) {
            $sanitized['address1_city'] = sanitize_text_field($data['address1_city']);
        }
        
        if (isset($data['address1_stateorprovince'])) {
            $sanitized['address1_stateorprovince'] = sanitize_text_field($data['address1_stateorprovince']);
        }
        
        if (isset($data['address1_postalcode'])) {
            $sanitized['address1_postalcode'] = sanitize_text_field($data['address1_postalcode']);
        }
        
        if (isset($data['address1_country'])) {
            $sanitized['address1_country'] = sanitize_text_field($data['address1_country']);
        }
        
        return $sanitized;
    }
    
    /**
     * Test connection
     */
    public function test_connection() {
        // Simple request without query parameters to avoid errors
        $result = $this->make_request('contacts');
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'message' => $result->get_error_message()
            );
        }
        
        // Check if we got a valid response with value array
        if (isset($result['value'])) {
            return array(
                'success' => true,
                'message' => __('Successfully connected to Dynamics 365! Found ' . count($result['value']) . ' contacts.', 'dynamics-sync-lite')
            );
        }
        
        return array(
            'success' => true,
            'message' => __('Successfully connected to Dynamics 365', 'dynamics-sync-lite')
        );
    }
}