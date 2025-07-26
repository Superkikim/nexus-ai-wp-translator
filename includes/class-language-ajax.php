<?php
/**
 * File: class-language-ajax.php
 * Location: /includes/class-language-ajax.php
 * 
 * Language AJAX Handler Class
 * Responsible for: AJAX endpoints, admin interactions, real-time validation
 * 
 * @package Nexus\Translator
 * @since 0.0.1
 */

namespace Nexus\Translator;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Language AJAX handler class
 * 
 * Handles all AJAX endpoints for language operations, real-time validation,
 * and admin interface interactions.
 * 
 * @since 0.0.1
 */
class Language_Ajax {
    
    /**
     * Languages instance reference
     * 
     * @since 0.0.1
     * @var Languages
     */
    private $languages;
    
    /**
     * Constructor
     * 
     * @since 0.0.1
     * @param Languages $languages Languages instance
     */
    public function __construct($languages) {
        $this->languages = $languages;
    }
    
    /**
     * Register WordPress hooks
     * 
     * @since 0.0.1
     * @return void
     */
    public function register_hooks() {
        // AJAX endpoints for logged-in users
        add_action('wp_ajax_nexus_validate_language_pair', array($this, 'ajax_validate_language_pair'));
        add_action('wp_ajax_nexus_get_language_info', array($this, 'ajax_get_language_info'));
        add_action('wp_ajax_nexus_get_available_targets', array($this, 'ajax_get_available_targets'));
        add_action('wp_ajax_nexus_validate_language_settings', array($this, 'ajax_validate_language_settings'));
        add_action('wp_ajax_nexus_get_language_dropdown', array($this, 'ajax_get_language_dropdown'));
        add_action('wp_ajax_nexus_check_pair_complexity', array($this, 'ajax_check_pair_complexity'));
        
        // Admin enqueue scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * AJAX handler for language pair validation
     * 
     * @since 0.0.1
     * @return void
     */
    public function ajax_validate_language_pair() {
        // Security check
        if (!$this->verify_ajax_nonce('nexus_language_ajax')) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'nexus-ai-wp-translator'),
                'code' => 'invalid_nonce'
            ));
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'nexus-ai-wp-translator'),
                'code' => 'insufficient_permissions'
            ));
        }
        
        // Get and sanitize input
        $source = sanitize_text_field($_POST['source'] ?? '');
        $target = sanitize_text_field($_POST['target'] ?? '');
        
        if (empty($source) || empty($target)) {
            wp_send_json_error(array(
                'message' => __('Source and target languages are required.', 'nexus-ai-wp-translator'),
                'code' => 'missing_parameters'
            ));
        }
        
        // Validate using the validator
        $validator = $this->languages->get_validator();
        if ($validator) {
            $validation = $validator->validate_language_pair($source, $target);
        } else {
            $validation = $this->languages->validate_language_pair($source, $target);
        }
        
        // Prepare response
        $response = array(
            'valid' => $validation['valid'],
            'source' => $source,
            'target' => $target,
            'errors' => $validation['errors'] ?? array(),
            'warnings' => $validation['warnings'] ?? array(),
        );
        
        if ($validation['valid'] && isset($validation['pair'])) {
            $response['pair_info'] = $validation['pair'];
            $response['complexity'] = $validation['pair']['complexity'] ?? 'medium';
            $response['accuracy'] = $validation['pair']['accuracy'] ?? 'good';
        }
        
        if ($validation['valid']) {
            wp_send_json_success($response);
        } else {
            wp_send_json_error($response);
        }
    }
    
    /**
     * AJAX handler for language information
     * 
     * @since 0.0.1
     * @return void
     */
    public function ajax_get_language_info() {
        // Security check
        if (!$this->verify_ajax_nonce('nexus_language_ajax')) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'nexus-ai-wp-translator'),
                'code' => 'invalid_nonce'
            ));
        }
        
        // Get and sanitize input
        $language_code = sanitize_text_field($_POST['language'] ?? '');
        
        if (empty($language_code)) {
            wp_send_json_error(array(
                'message' => __('Language code is required.', 'nexus-ai-wp-translator'),
                'code' => 'missing_parameter'
            ));
        }
        
        // Get language information
        $language_info = $this->languages->get_language($language_code);
        
        if ($language_info) {
            $response = array(
                'language' => $language_code,
                'info' => $language_info,
                'supported' => true,
                'available_targets' => $this->languages->get_available_targets($language_code),
                'direction' => $language_info['direction'],
                'family' => $language_info['family'] ?? 'unknown',
                'script' => $language_info['script'] ?? 'unknown',
            );
            
            wp_send_json_success($response);
        } else {
            wp_send_json_error(array(
                'message' => __('Language not found.', 'nexus-ai-wp-translator'),
                'code' => 'language_not_found',
                'language' => $language_code,
                'supported' => false
            ));
        }
    }
    
    /**
     * AJAX handler for available target languages
     * 
     * @since 0.0.1
     * @return void
     */
    public function ajax_get_available_targets() {
        // Security check
        if (!$this->verify_ajax_nonce('nexus_language_ajax')) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'nexus-ai-wp-translator'),
                'code' => 'invalid_nonce'
            ));
        }
        
        // Get and sanitize input
        $source_code = sanitize_text_field($_POST['source'] ?? '');
        
        if (empty($source_code)) {
            wp_send_json_error(array(
                'message' => __('Source language is required.', 'nexus-ai-wp-translator'),
                'code' => 'missing_parameter'
            ));
        }
        
        // Check if source language is supported
        if (!$this->languages->is_language_supported($source_code)) {
            wp_send_json_error(array(
                'message' => __('Source language is not supported.', 'nexus-ai-wp-translator'),
                'code' => 'unsupported_language',
                'source' => $source_code
            ));
        }
        
        // Get available targets
        $available_targets = $this->languages->get_available_targets($source_code);
        $target_pairs = $this->languages->get_translation_pairs($source_code);
        
        $response = array(
            'source' => $source_code,
            'targets' => $available_targets,
            'pairs' => $target_pairs,
            'count' => count($available_targets),
        );
        
        wp_send_json_success($response);
    }
    
    /**
     * AJAX handler for language settings validation
     * 
     * @since 0.0.1
     * @return void
     */
    public function ajax_validate_language_settings() {
        // Security check
        if (!$this->verify_ajax_nonce('nexus_language_ajax')) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'nexus-ai-wp-translator'),
                'code' => 'invalid_nonce'
            ));
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'nexus-ai-wp-translator'),
                'code' => 'insufficient_permissions'
            ));
        }
        
        // Get and sanitize settings
        $settings = array(
            'source_language' => sanitize_text_field($_POST['source_language'] ?? ''),
            'target_languages' => array_map('sanitize_text_field', $_POST['target_languages'] ?? array()),
        );
        
        // Validate using the validator
        $validator = $this->languages->get_validator();
        if ($validator) {
            $validation = $validator->validate_settings($settings);
            
            $response = array(
                'valid' => $validation['valid'],
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings'] ?? array(),
                'cleaned' => $validation['cleaned'],
                'changes' => $validation['changes'] ?? array(),
            );
            
            if ($validation['valid']) {
                wp_send_json_success($response);
            } else {
                wp_send_json_error($response);
            }
        } else {
            wp_send_json_error(array(
                'message' => __('Validator not available.', 'nexus-ai-wp-translator'),
                'code' => 'validator_unavailable'
            ));
        }
    }
    
    /**
     * AJAX handler for language dropdown options
     * 
     * @since 0.0.1
     * @return void
     */
    public function ajax_get_language_dropdown() {
        // Security check
        if (!$this->verify_ajax_nonce('nexus_language_ajax')) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'nexus-ai-wp-translator'),
                'code' => 'invalid_nonce'
            ));
        }
        
        // Get options
        $include_native = filter_var($_POST['include_native'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $filter_family = sanitize_text_field($_POST['filter_family'] ?? '');
        
        // Get dropdown options
        $options = $this->languages->get_language_dropdown_options($include_native, $filter_family);
        
        $response = array(
            'options' => $options,
            'count' => count($options),
            'include_native' => $include_native,
            'filter_family' => $filter_family,
        );
        
        wp_send_json_success($response);
    }
    
    /**
     * AJAX handler for pair complexity check
     * 
     * @since 0.0.1
     * @return void
     */
    public function ajax_check_pair_complexity() {
        // Security check
        if (!$this->verify_ajax_nonce('nexus_language_ajax')) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'nexus-ai-wp-translator'),
                'code' => 'invalid_nonce'
            ));
        }
        
        // Get and sanitize input
        $source = sanitize_text_field($_POST['source'] ?? '');
        $target = sanitize_text_field($_POST['target'] ?? '');
        
        if (empty($source) || empty($target)) {
            wp_send_json_error(array(
                'message' => __('Source and target languages are required.', 'nexus-ai-wp-translator'),
                'code' => 'missing_parameters'
            ));
        }
        
        // Get pair information
        $pair_info = $this->languages->get_pair_info($source, $target);
        
        if ($pair_info) {
            $analytics = $this->languages->get_analytics();
            $complexity_details = array();
            
            if ($analytics) {
                $complexity_details = $analytics->get_complexity_details($source, $target);
            }
            
            $response = array(
                'source' => $source,
                'target' => $target,
                'complexity' => $pair_info['complexity'],
                'accuracy' => $pair_info['accuracy'],
                'family_match' => $pair_info['family_match'] ?? false,
                'script_match' => $pair_info['script_match'] ?? false,
                'details' => $complexity_details,
            );
            
            wp_send_json_success($response);
        } else {
            wp_send_json_error(array(
                'message' => __('Language pair not supported.', 'nexus-ai-wp-translator'),
                'code' => 'pair_not_supported',
                'source' => $source,
                'target' => $target
            ));
        }
    }
    
    /**
     * Enqueue admin scripts and styles
     * 
     * @since 0.0.1
     * @param string $hook_suffix Current admin page hook suffix
     * @return void
     */
    public function enqueue_admin_scripts($hook_suffix) {
        // Only load on relevant admin pages
        $relevant_pages = array(
            'post.php',
            'post-new.php',
            'settings_page_nexus-ai-translator',
        );
        
        if (!in_array($hook_suffix, $relevant_pages)) {
            return;
        }
        
        // Enqueue JavaScript for AJAX functionality
        wp_enqueue_script(
            'nexus-language-ajax',
            NEXUS_AI_TRANSLATOR_PLUGIN_URL . 'admin/js/language-ajax.js',
            array('jquery'),
            NEXUS_AI_TRANSLATOR_VERSION,
            true
        );
        
        // Localize script with AJAX data
        wp_localize_script('nexus-language-ajax', 'nexusLanguageAjax', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nexus_language_ajax'),
            'strings' => array(
                'validating' => __('Validating...', 'nexus-ai-wp-translator'),
                'valid' => __('Valid', 'nexus-ai-wp-translator'),
                'invalid' => __('Invalid', 'nexus-ai-wp-translator'),
                'loading' => __('Loading...', 'nexus-ai-wp-translator'),
                'error' => __('Error', 'nexus-ai-wp-translator'),
                'highComplexity' => __('High complexity translation', 'nexus-ai-wp-translator'),
                'mediumComplexity' => __('Medium complexity translation', 'nexus-ai-wp-translator'),
                'lowComplexity' => __('Low complexity translation', 'nexus-ai-wp-translator'),
                'excellentAccuracy' => __('Excellent accuracy expected', 'nexus-ai-wp-translator'),
                'goodAccuracy' => __('Good accuracy expected', 'nexus-ai-wp-translator'),
                'fairAccuracy' => __('Fair accuracy expected', 'nexus-ai-wp-translator'),
            ),
        ));
    }
    
    /**
     * Verify AJAX nonce
     * 
     * @since 0.0.1
     * @param string $action Nonce action
     * @return bool True if valid
     */
    private function verify_ajax_nonce($action) {
        $nonce = $_POST['_nonce'] ?? $_POST['nonce'] ?? '';
        return wp_verify_nonce($nonce, $action);
    }
    
    /**
     * Send standardized AJAX error response
     * 
     * @since 0.0.1
     * @param string $message Error message
     * @param string $code Error code
     * @param array $data Additional data
     * @return void
     */
    private function send_ajax_error($message, $code = 'general_error', $data = array()) {
        $response = array_merge(array(
            'message' => $message,
            'code' => $code,
            'timestamp' => current_time('mysql'),
        ), $data);
        
        wp_send_json_error($response);
    }
    
    /**
     * Send standardized AJAX success response
     * 
     * @since 0.0.1
     * @param array $data Response data
     * @param string $message Success message
     * @return void
     */
    private function send_ajax_success($data = array(), $message = '') {
        $response = array_merge(array(
            'success' => true,
            'timestamp' => current_time('mysql'),
        ), $data);
        
        if (!empty($message)) {
            $response['message'] = $message;
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * Log AJAX request for analytics
     * 
     * @since 0.0.1
     * @param string $endpoint Endpoint name
     * @param array $request_data Request data
     * @param bool $success Whether request was successful
     * @return void
     */
    private function log_ajax_request($endpoint, $request_data, $success) {
        $analytics = $this->languages->get_analytics();
        
        if ($analytics) {
            $analytics->track_ajax_request($endpoint, $request_data, $success);
        }
        
        // Fire analytics hook
        do_action('nexus_analytics_event', 'language_ajax_request', array(
            'endpoint' => $endpoint,
            'success' => $success,
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql'),
        ));
    }
    
    /**
     * Get formatted error message for user display
     * 
     * @since 0.0.1
     * @param string $error_code Error code
     * @param array $context Error context
     * @return string Formatted error message
     */
    private function get_user_error_message($error_code, $context = array()) {
        $messages = array(
            'invalid_nonce' => __('Security verification failed. Please refresh the page and try again.', 'nexus-ai-wp-translator'),
            'insufficient_permissions' => __('You do not have permission to perform this action.', 'nexus-ai-wp-translator'),
            'missing_parameters' => __('Required information is missing. Please check your input and try again.', 'nexus-ai-wp-translator'),
            'language_not_found' => __('The specified language is not supported.', 'nexus-ai-wp-translator'),
            'unsupported_language' => __('This language is not currently supported.', 'nexus-ai-wp-translator'),
            'pair_not_supported' => __('This language pair is not supported for translation.', 'nexus-ai-wp-translator'),
            'validator_unavailable' => __('Validation service is temporarily unavailable.', 'nexus-ai-wp-translator'),
            'general_error' => __('An unexpected error occurred. Please try again.', 'nexus-ai-wp-translator'),
        );
        
        $message = isset($messages[$error_code]) ? $messages[$error_code] : $messages['general_error'];
        
        // Add context information if available
        if (!empty($context['language'])) {
            $message .= ' ' . sprintf(
                /* translators: %s: Language code */
                __('Language: %s', 'nexus-ai-wp-translator'),
                $context['language']
            );
        }
        
        if (!empty($context['source']) && !empty($context['target'])) {
            $message .= ' ' . sprintf(
                /* translators: 1: Source language, 2: Target language */
                __('Pair: %1$s → %2$s', 'nexus-ai-wp-translator'),
                $context['source'],
                $context['target']
            );
        }
        
        return $message;
    }
    
    /**
     * Handle AJAX request with error logging
     * 
     * @since 0.0.1
     * @param string $endpoint Endpoint name
     * @param callable $callback Callback function
     * @return void
     */
    private function handle_ajax_request($endpoint, $callback) {
        try {
            $start_time = microtime(true);
            
            // Execute callback
            $result = call_user_func($callback);
            
            // Log successful request
            $this->log_ajax_request($endpoint, $_POST, true);
            
            // Track execution time
            $execution_time = microtime(true) - $start_time;
            do_action('nexus_analytics_event', 'ajax_performance', array(
                'endpoint' => $endpoint,
                'execution_time' => $execution_time,
                'timestamp' => current_time('mysql'),
            ));
            
            return $result;
            
        } catch (Exception $e) {
            // Log failed request
            $this->log_ajax_request($endpoint, $_POST, false);
            
            // Log error
            error_log(sprintf(
                '[Nexus AI Translator - Language AJAX] %s Error: %s',
                $endpoint,
                $e->getMessage()
            ));
            
            // Send error response
            $this->send_ajax_error(
                $this->get_user_error_message('general_error'),
                'exception',
                array('endpoint' => $endpoint)
            );
        }
    }
    
    /**
     * Get AJAX endpoint statistics
     * 
     * @since 0.0.1
     * @return array Endpoint usage statistics
     */
    public function get_ajax_stats() {
        $analytics = $this->languages->get_analytics();
        
        if (!$analytics) {
            return array();
        }
        
        $analytics_data = get_option('nexus_language_analytics_data', array());
        $ajax_data = $analytics_data['ajax_requests'] ?? array();
        
        $stats = array(
            'total_requests' => count($ajax_data),
            'success_rate' => 0,
            'endpoints' => array(),
            'recent_requests' => array(),
        );
        
        if (!empty($ajax_data)) {
            $successful = array_filter($ajax_data, function($request) {
                return $request['success'] ?? false;
            });
            
            $stats['success_rate'] = round((count($successful) / count($ajax_data)) * 100, 2);
            
            // Count by endpoint
            foreach ($ajax_data as $request) {
                $endpoint = $request['endpoint'] ?? 'unknown';
                if (!isset($stats['endpoints'][$endpoint])) {
                    $stats['endpoints'][$endpoint] = array('total' => 0, 'success' => 0);
                }
                $stats['endpoints'][$endpoint]['total']++;
                if ($request['success'] ?? false) {
                    $stats['endpoints'][$endpoint]['success']++;
                }
            }
            
            // Get recent requests (last 10)
            $stats['recent_requests'] = array_slice($ajax_data, -10);
        }
        
        return $stats;
    }
    
    /**
     * Clear AJAX analytics data
     * 
     * @since 0.0.1
     * @return bool True on success
     */
    public function clear_ajax_analytics() {
        $analytics_data = get_option('nexus_language_analytics_data', array());
        unset($analytics_data['ajax_requests']);
        unset($analytics_data['ajax_performance']);
        
        return update_option('nexus_language_analytics_data', $analytics_data);
    }
    
    /**
     * Test AJAX endpoint connectivity
     * 
     * @since 0.0.1
     * @return array Test results
     */
    public function test_ajax_connectivity() {
        $test_results = array();
        
        $endpoints = array(
            'nexus_validate_language_pair',
            'nexus_get_language_info',
            'nexus_get_available_targets',
            'nexus_validate_language_settings',
            'nexus_get_language_dropdown',
            'nexus_check_pair_complexity',
        );
        
        foreach ($endpoints as $endpoint) {
            $test_results[$endpoint] = array(
                'exists' => has_action('wp_ajax_' . $endpoint),
                'callable' => method_exists($this, 'ajax_' . str_replace('nexus_', '', $endpoint)),
            );
        }
        
        return $test_results;
    }
}
}