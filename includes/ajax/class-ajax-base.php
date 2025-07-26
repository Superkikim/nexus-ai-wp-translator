<?php
/**
 * File: class-ajax-base.php
 * Location: /includes/ajax/class-ajax-base.php
 * 
 * AJAX Base Class - Common functionality with enhanced protection
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class Ajax_Base {
    
    /**
     * Protection: Active requests tracking
     */
    protected static $active_requests = array();
    
    /**
     * Debug mode flag
     */
    protected $debug_mode = false;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->debug_mode = $this->is_debug_enabled();
        $this->init_hooks();
        $this->log_debug('AJAX Handler initialized: ' . get_class($this));
    }
    
    /**
     * Initialize hooks - Must be implemented in child classes
     */
    abstract protected function init_hooks();
    
    /**
     * Enhanced security validation for AJAX requests
     */
    protected function validate_ajax_request($required_capability = 'edit_posts') {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce')) {
            $this->log_error('Security check failed: Invalid nonce');
            $this->send_error('Security check failed', 'NONCE_FAILED');
        }
        
        // Check user permissions
        if (!current_user_can($required_capability)) {
            $this->log_error('Permission denied for capability: ' . $required_capability);
            $this->send_error('Insufficient permissions', 'PERMISSION_DENIED');
        }
        
        // Check if emergency stop is active for translation actions
        if (strpos($required_capability, 'edit') !== false && get_option('nexus_translator_emergency_stop', false)) {
            $this->log_error('Request blocked by emergency stop');
            $this->send_error('Emergency stop is active - translation disabled', 'EMERGENCY_STOP_ACTIVE');
        }
        
        $this->log_debug('AJAX request validation passed for capability: ' . $required_capability);
        return true;
    }
    
    /**
     * Protection: Prevent duplicate requests
     */
    protected function check_duplicate_request($request_key) {
        $current_time = time();
        
        // Clean expired requests first (older than 5 minutes)
        foreach (self::$active_requests as $key => $timestamp) {
            if (($current_time - $timestamp) > 300) {
                unset(self::$active_requests[$key]);
            }
        }
        
        // Check if this request is already active
        if (isset(self::$active_requests[$request_key])) {
            $this->log_error('Duplicate request detected: ' . $request_key);
            $this->send_error('Request already in progress', 'DUPLICATE_REQUEST');
        }
        
        // Register this request
        self::$active_requests[$request_key] = $current_time;
        $this->log_debug('Registered active request: ' . $request_key);
        
        return $request_key;
    }
    
    /**
     * Protection: Clean up active request
     */
    protected function cleanup_request($request_key) {
        if (isset(self::$active_requests[$request_key])) {
            unset(self::$active_requests[$request_key]);
            $this->log_debug('Cleaned up request: ' . $request_key);
        }
    }
    
    /**
     * Send success response with enhanced logging
     */
    protected function send_success($data = array(), $message = '') {
        if ($message) {
            $data['message'] = $message;
        }
        
        $this->log_debug('AJAX Success: ' . $message, $data);
        wp_send_json_success($data);
    }
    
    /**
     * Send error response with enhanced logging
     */
    protected function send_error($message, $error_code = 'GENERIC_ERROR', $data = array()) {
        $this->log_error("AJAX Error [$error_code]: $message", $data);
        
        $error_data = array_merge($data, array(
            'error' => $message,
            'error_code' => $error_code,
            'timestamp' => current_time('mysql')
        ));
        
        wp_send_json_error($error_data);
    }
    
    /**
     * Validate post ID with enhanced checks
     */
    protected function validate_post_id($post_id, $required_capability = 'edit_post') {
        $post_id = (int) $post_id;
        
        if (!$post_id) {
            $this->send_error('Invalid post ID provided', 'INVALID_POST_ID');
        }
        
        $post = get_post($post_id);
        if (!$post) {
            $this->send_error('Post not found with ID: ' . $post_id, 'POST_NOT_FOUND');
        }
        
        if (!current_user_can($required_capability, $post_id)) {
            $this->send_error('No permission for this post', 'POST_PERMISSION_DENIED');
        }
        
        $this->log_debug("Post validation passed for ID: $post_id");
        return $post;
    }
    
    /**
     * Enhanced usage logging for analytics
     */
    protected function log_usage($action, $data = array()) {
        $log_entry = array_merge($data, array(
            'timestamp' => current_time('timestamp'),
            'date' => current_time('Y-m-d H:i:s'),
            'action' => $action,
            'user_id' => get_current_user_id(),
            'user_login' => wp_get_current_user()->user_login,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ));
        
        $usage_log = get_option('nexus_translator_usage_log', array());
        $usage_log[] = $log_entry;
        
        // Keep only last 500 entries to prevent database bloat
        if (count($usage_log) > 500) {
            $usage_log = array_slice($usage_log, -500);
        }
        
        update_option('nexus_translator_usage_log', $usage_log);
        $this->log_debug("Usage logged for action: $action");
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (forwarded)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
    
    /**
     * Check if debug mode is enabled
     */
    private function is_debug_enabled() {
        $options = get_option('nexus_translator_options', array());
        return !empty($options['debug_mode']);
    }
    
    /**
     * Enhanced debug logging
     */
    protected function log_debug($message, $data = null) {
        if (!$this->debug_mode) {
            return;
        }
        
        $log_message = '[Nexus AJAX] ' . get_class($this) . ': ' . $message;
        
        if ($data) {
            $log_message .= ' | Data: ' . json_encode($data);
        }
        
        error_log($log_message);
    }
    
    /**
     * Error logging (always enabled)
     */
    protected function log_error($message, $data = null) {
        $log_message = '[Nexus AJAX ERROR] ' . get_class($this) . ': ' . $message;
        
        if ($data) {
            $log_message .= ' | Data: ' . json_encode($data);
        }
        
        error_log($log_message);
    }
    
    /**
     * Static methods for system management
     */
    public static function get_active_requests() {
        return self::$active_requests;
    }
    
    public static function force_cleanup_requests() {
        self::$active_requests = array();
        error_log('[Nexus AJAX] Force cleanup completed - all active requests cleared');
    }
    
    public static function cleanup_expired_requests() {
        $current_time = time();
        $timeout = 300; // 5 minutes
        $cleaned = 0;
        
        foreach (self::$active_requests as $key => $timestamp) {
            if (($current_time - $timestamp) > $timeout) {
                unset(self::$active_requests[$key]);
                $cleaned++;
            }
        }
        
        if ($cleaned > 0) {
            error_log("[Nexus AJAX] Cleaned up $cleaned expired requests");
        }
        
        return $cleaned;
    }
    
    /**
     * System status for debugging
     */
    public static function get_system_status() {
        return array(
            'active_requests_count' => count(self::$active_requests),
            'active_requests' => self::$active_requests,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        );
    }
}