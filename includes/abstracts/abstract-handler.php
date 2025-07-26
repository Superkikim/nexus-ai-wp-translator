<?php
/**
 * File: abstract-handler.php
 * Location: /includes/abstracts/abstract-handler.php
 * 
 * Abstract base class for all request/event handlers
 * Provides common functionality for processing requests, events, and data
 */

namespace Nexus\Translator\Abstracts;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract Handler Class
 * 
 * Base class for handlers that process requests, events, or data
 * Provides validation, sanitization, response handling, and error management
 * 
 * @since 0.0.1
 * @package Nexus\Translator\Abstracts
 */
abstract class Abstract_Handler {
    
    /**
     * Handler name/identifier
     * 
     * @var string
     */
    protected $handler_name = '';
    
    /**
     * Supported actions/events
     * 
     * @var array
     */
    protected $supported_actions = array();
    
    /**
     * Handler configuration
     * 
     * @var array
     */
    protected $config = array();
    
    /**
     * Request data
     * 
     * @var array
     */
    protected $request_data = array();
    
    /**
     * Response data
     * 
     * @var array
     */
    protected $response_data = array();
    
    /**
     * Validation errors
     * 
     * @var array
     */
    protected $validation_errors = array();
    
    /**
     * Processing start time
     * 
     * @var float
     */
    protected $start_time = 0;
    
    /**
     * Constructor
     * 
     * @param array $config Handler configuration
     */
    public function __construct($config = array()) {
        $this->config = wp_parse_args($config, $this->get_default_config());
        $this->handler_name = $this->get_handler_name();
        $this->supported_actions = $this->get_supported_actions();
        
        // Initialize response data
        $this->init_response();
    }
    
    /**
     * Get handler name/identifier
     * Must be implemented by child classes
     * 
     * @return string Handler name
     */
    abstract protected function get_handler_name();
    
    /**
     * Get supported actions/events
     * Must be implemented by child classes
     * 
     * @return array Supported actions
     */
    abstract protected function get_supported_actions();
    
    /**
     * Process the request/event
     * Must be implemented by child classes
     * 
     * @param string $action Action to process
     * @param array $data Request data
     * @return array Response data
     */
    abstract protected function process_action($action, $data);
    
    /**
     * Get default handler configuration
     * Can be overridden by child classes
     * 
     * @return array Default configuration
     */
    protected function get_default_config() {
        return array(
            'validate_nonce' => true,
            'require_capability' => 'manage_options',
            'sanitize_input' => true,
            'log_requests' => false,
            'rate_limit' => false,
            'cache_responses' => false
        );
    }
    
    /**
     * Handle incoming request
     * Main entry point for processing requests
     * 
     * @param string $action Action to handle
     * @param array $data Request data
     * @return array Response data
     */
    public function handle($action, $data = array()) {
        $this->start_time = microtime(true);
        $this->request_data = $data;
        $this->validation_errors = array();
        
        try {
            // Validate action
            if (!$this->is_action_supported($action)) {
                throw new \Exception(
                    sprintf(
                        /* translators: 1: Action name, 2: Handler name */
                        __('Action %1$s is not supported by handler %2$s.', 'nexus-ai-wp-translator'),
                        $action,
                        $this->handler_name
                    )
                );
            }
            
            // Security validation
            if (!$this->validate_security()) {
                throw new \Exception(__('Security validation failed.', 'nexus-ai-wp-translator'));
            }
            
            // Input validation
            if (!$this->validate_input($action, $data)) {
                throw new \Exception(__('Input validation failed.', 'nexus-ai-wp-translator'));
            }
            
            // Sanitize input data
            $sanitized_data = $this->sanitize_input($data);
            
            // Process the action
            $result = $this->process_action($action, $sanitized_data);
            
            // Set success response
            $this->set_success_response($result);
            
            // Log request if enabled
            if ($this->config['log_requests']) {
                $this->log_request($action, $sanitized_data, true);
            }
            
        } catch (\Exception $e) {
            // Set error response
            $this->set_error_response($e->getMessage());
            
            // Log error
            $this->log_error($action, $e->getMessage());
            
            // Log request if enabled
            if ($this->config['log_requests']) {
                $this->log_request($action, $data, false);
            }
        }
        
        // Add execution time to response
        $this->response_data['execution_time'] = microtime(true) - $this->start_time;
        
        // Fire completion hook
        do_action('nexus_ai_translator_handler_complete', $this->handler_name, $action, $this->response_data);
        
        return $this->response_data;
    }
    
    /**
     * Check if action is supported
     * 
     * @param string $action Action name
     * @return bool True if supported
     */
    protected function is_action_supported($action) {
        return in_array($action, $this->supported_actions, true);
    }
    
    /**
     * Validate security (nonce, capability, etc.)
     * 
     * @return bool True if valid
     */
    protected function validate_security() {
        // Check nonce if required
        if ($this->config['validate_nonce']) {
            $nonce = $this->get_request_value('_nonce', '');
            if (!wp_verify_nonce($nonce, 'nexus_ai_translator_' . $this->handler_name)) {
                return false;
            }
        }
        
        // Check capability if required
        if ($this->config['require_capability']) {
            if (!current_user_can($this->config['require_capability'])) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Validate input data
     * Can be overridden by child classes for specific validation
     * 
     * @param string $action Action being processed
     * @param array $data Input data
     * @return bool True if valid
     */
    protected function validate_input($action, $data) {
        // Base validation - can be extended by child classes
        return true;
    }
    
    /**
     * Sanitize input data
     * 
     * @param array $data Raw input data
     * @return array Sanitized data
     */
    protected function sanitize_input($data) {
        if (!$this->config['sanitize_input']) {
            return $data;
        }
        
        $sanitized = array();
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize_input($value);
            } elseif (is_string($value)) {
                $sanitized[$key] = sanitize_text_field($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Get value from request data
     * 
     * @param string $key Data key
     * @param mixed $default Default value
     * @return mixed Data value
     */
    protected function get_request_value($key, $default = null) {
        return isset($this->request_data[$key]) ? $this->request_data[$key] : $default;
    }
    
    /**
     * Initialize response data structure
     * 
     * @return void
     */
    protected function init_response() {
        $this->response_data = array(
            'success' => false,
            'data' => array(),
            'message' => '',
            'errors' => array(),
            'handler' => $this->handler_name,
            'timestamp' => current_time('timestamp')
        );
    }
    
    /**
     * Set success response
     * 
     * @param mixed $data Response data
     * @param string $message Success message
     * @return void
     */
    protected function set_success_response($data = array(), $message = '') {
        $this->response_data['success'] = true;
        $this->response_data['data'] = $data;
        $this->response_data['message'] = $message ?: __('Request processed successfully.', 'nexus-ai-wp-translator');
        $this->response_data['errors'] = array();
    }
    
    /**
     * Set error response
     * 
     * @param string $message Error message
     * @param array $errors Detailed errors
     * @return void
     */
    protected function set_error_response($message, $errors = array()) {
        $this->response_data['success'] = false;
        $this->response_data['data'] = array();
        $this->response_data['message'] = $message;
        $this->response_data['errors'] = array_merge($this->validation_errors, $errors);
    }
    
    /**
     * Add validation error
     * 
     * @param string $field Field name
     * @param string $message Error message
     * @return void
     */
    protected function add_validation_error($field, $message) {
        $this->validation_errors[$field] = $message;
    }
    
    /**
     * Log request for debugging/analytics
     * 
     * @param string $action Action processed
     * @param array $data Request data
     * @param bool $success Whether request was successful
     * @return void
     */
    protected function log_request($action, $data, $success) {
        $log_data = array(
            'handler' => $this->handler_name,
            'action' => $action,
            'success' => $success,
            'execution_time' => microtime(true) - $this->start_time,
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
            'timestamp' => current_time('mysql')
        );
        
        // Fire logging hook for analytics
        do_action('nexus_analytics_event', 'handler_request', $log_data);
    }
    
    /**
     * Log error
     * 
     * @param string $action Action that failed
     * @param string $message Error message
     * @return void
     */
    protected function log_error($action, $message) {
        error_log(sprintf(
            '[Nexus AI Translator] Handler %s Error (Action: %s): %s',
            $this->handler_name,
            $action,
            $message
        ));
        
        // Fire error hook
        do_action('nexus_ai_translator_handler_error', $this->handler_name, $action, $message);
    }
    
    /**
     * Get client IP address
     * 
     * @return string IP address
     */
    protected function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
    
    /**
     * Send JSON response (for AJAX handlers)
     * 
     * @return void
     */
    protected function send_json_response() {
        wp_send_json($this->response_data);
    }
    
    /**
     * Get handler status information
     * 
     * @return array Status information
     */
    public function get_status() {
        return array(
            'name' => $this->handler_name,
            'supported_actions' => $this->supported_actions,
            'config' => $this->config,
            'last_execution_time' => isset($this->response_data['execution_time']) ? $this->response_data['execution_time'] : 0
        );
    }
    
    /**
     * Get response data
     * 
     * @return array Response data
     */
    public function get_response() {
        return $this->response_data;
    }
    
    /**
     * Check if last operation was successful
     * 
     * @return bool True if successful
     */
    public function is_success() {
        return $this->response_data['success'];
    }
    
    /**
     * Get last error message
     * 
     * @return string Error message
     */
    public function get_error_message() {
        return $this->response_data['message'];
    }
    
    /**
     * Get validation errors
     * 
     * @return array Validation errors
     */
    public function get_validation_errors() {
        return $this->validation_errors;
    }
}