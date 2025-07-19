<?php
/**
 * Security Interface for Dependency Injection and Testing
 */

if (!defined('ABSPATH')) {
    exit;
}

interface WP_Employee_Leaves_Security_Interface {
    
    /**
     * Generate cryptographically secure token
     */
    public function generate_secure_token($length = 64);
    
    /**
     * Hash token for storage
     */
    public function hash_token($token);
    
    /**
     * Verify token against hash
     */
    public function verify_token($token, $hash);
    
    /**
     * Get client IP address
     */
    public function get_client_ip();
    
    /**
     * Get user agent string
     */
    public function get_user_agent();
    
    /**
     * Generate WordPress nonce
     */
    public function create_nonce($action);
    
    /**
     * Verify WordPress nonce
     */
    public function verify_nonce($nonce, $action);
    
    /**
     * Sanitize email address
     */
    public function sanitize_email($email);
    
    /**
     * Validate email address
     */
    public function is_valid_email($email);
}