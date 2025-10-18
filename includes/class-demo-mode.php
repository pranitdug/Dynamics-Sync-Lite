<?php
/**
 * Demo Mode Handler
 * 
 * Simulates Dynamics 365 API responses for demonstration purposes
 */

if (!defined('ABSPATH')) {
    exit;
}

class DSL_Demo_Mode {
    
    private static $instance = null;
    
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
     * Check if demo mode is enabled
     */
    public static function is_enabled() {
        return get_option('dsl_demo_mode', '0') === '1';
    }
    
    /**
     * Simulate getting contact by email
     */
    public static function get_contact_by_email($email) {
        // Simulate API delay
        usleep(500000); // 0.5 second delay
        
        $current_user = wp_get_current_user();
        
        // Generate a fake contact ID
        $contact_id = 'demo-' . md5($email);
        
        // Return mock contact data
        return array(
            'contactid' => $contact_id,
            'firstname' => $current_user->user_firstname ?: 'John',
            'lastname' => $current_user->user_lastname ?: 'Doe',
            'emailaddress1' => $email,
            'telephone1' => '+1 (555) 123-4567',
            'address1_line1' => '123 Demo Street',
            'address1_city' => 'San Francisco',
            'address1_stateorprovince' => 'California',
            'address1_postalcode' => '94102',
            'address1_country' => 'United States',
            '_demo_mode' => true
        );
    }
    
    /**
     * Simulate updating contact
     */
    public static function update_contact($contact_id, $data) {
        // Simulate API delay
        usleep(800000); // 0.8 second delay
        
        // Log the update
        DSL_Logger::log('success', 'Demo Mode: Contact updated', array(
            'contact_id' => $contact_id,
            'data' => $data
        ));
        
        // Return success response
        return array(
            'success' => true,
            'contactid' => $contact_id,
            '_demo_mode' => true
        );
    }
    
    /**
     * Simulate creating contact
     */
    public static function create_contact($data) {
        // Simulate API delay
        usleep(1000000); // 1 second delay
        
        // Generate a fake contact ID
        $contact_id = 'demo-' . md5($data['emailaddress1'] . time());
        
        // Log the creation
        DSL_Logger::log('success', 'Demo Mode: New contact created', array(
            'contact_id' => $contact_id,
            'email' => $data['emailaddress1']
        ));
        
        // Return success response
        return array(
            'success' => true,
            'contactid' => $contact_id,
            '_demo_mode' => true
        );
    }
    
    /**
     * Simulate connection test
     */
    public static function test_connection() {
        // Simulate API delay
        usleep(600000); // 0.6 second delay
        
        return array(
            'success' => true,
            'message' => __('Demo Mode: Connection successful! (Using simulated data)', 'dynamics-sync-lite')
        );
    }
    
    /**
     * Generate random stats for dashboard
     */
    public static function get_demo_stats() {
        return array(
            'total_contacts' => rand(150, 300),
            'synced_today' => rand(5, 25),
            'pending_sync' => rand(0, 10)
        );
    }
}