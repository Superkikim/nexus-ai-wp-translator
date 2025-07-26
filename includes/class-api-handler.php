<?php
/**
 * File: class-api-handler.php
 * Location: /includes/class-api-handler.php
 * 
 * API Handler Class
 * Responsible for: Error handling, retry logic, validation, emergency integration
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
 * API handler class for complex operations
 * 
 * Handles error management, retry logic, validation, and emergency system integration.
 * Works with the core Api class to provide robust translation processing.
 * 
 * @since 0.0.1
 */
class Api_Handler {
    
    /**
     * Core API instance
     * 
     * @since 0.0.1
     * @var Api
     */
    private $api;
    
    /**
     * Languages instance
     * 
     * @since 0.0.1
     * @var Languages
     */
    private $languages;
    
    /**
     * Handler configuration
     * 
     * @since 0.0.1
     * @var array
     */
    private $config = array();
    
    /**
     * Constructor
     * 
     * @since 0.0.1
     * @param Api $api Core API instance
     */
    public function __construct($api) {
        $this->api = $api;
        $this->load_config();
        
        // Get Languages instance
        $main = \Nexus\Translator\Main::get_instance();
        $this->languages = $main->get_component('languages');
    }
    
    /**
     * Register WordPress hooks
     * 
     * @since 0.0.1
     * @return void
     */
    public function register_hooks() {
        // Emergency mode integration
        add_action('nexus_emergency_trigger', array($this, 'handle_emergency_trigger'), 10, 2);
        
        // Daily cleanup
        add_action('nexus_daily_cleanup', array($this, 'daily_cleanup'));
    }
    
    /**
     * Load handler configuration
     * 
     * @since 0.0.1
     * @return void
     */
    private function load_config() {
        $api_config = get_option('nexus_ai_translator_api_config', array());
        $defaults = array(
            'max_retries' => 3,
            'retry_delay' => 2,
            'rate_limit_per_minute' => 50,
            'emergency_threshold' => 10,
        );
        
        $this->config = wp_parse_args($api_config, $defaults);
    }
    
    /**
     * Handle translation with full error handling
     * 
     * @since 0.0.1
     * @param string $text Text to translate
     * @param string $source_language Source language
     * @param string $target_language Target language
     * @param array $options Translation options
     * @return array Translation result
     */
    public function handle_translation($text, $source_language, $target_language, $options = array()) {
        $start_time = microtime(true);
        
        // Validate translation request
        $validation = $this->validate_translation_request($text, $source_language, $target_language);
        if (!$validation['valid']) {
            return $this->create_error_response(
                __('Translation request validation failed.', 'nexus-ai-wp-translator'),
                'validation_failed',
                $validation['errors']
            );
        }
        
        // Check rate limits
        $rate_check = $this->check_rate_limit();
        if (!$rate_check['allowed']) {
            return $this->create_error_response(
                __('Rate limit exceeded. Please wait before making another request.', 'nexus-ai-wp-translator'),
                'rate_limit_exceeded',
                array('retry_after' => $rate_check['retry_after'])
            );
        }
        
        // Check emergency mode
        if (apply_filters('nexus_emergency_mode_active', false)) {
            return $this->create_emergency_response();
        }
        
        // Fire before translation hook
        do_action('nexus_before_api_translate', $text, $source_language, $target_language, $options);
        
        // Build translation prompt
        $prompt = $this->build_translation_prompt($text, $source_language, $target_language, $options);
        
        // Prepare API request
        $api_data = array(
            'model' => $this->get_recommended_model($source_language, $target_language),
            'max_tokens' => $options['max_tokens'] ?? 4000,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            )
        );
        
        // Make request with retry logic
        $response = $this->make_request_with_retry($api_data);
        
        $execution_time = microtime(true) - $start_time;
        
        if ($response['success']) {
            // Extract translated text
            $translated_text = $this->extract_translation($response['data']);
            
            // Create success response
            $result = $this->create_success_response(array(
                'translated_text' => $translated_text,
                'source_language' => $source_language,
                'target_language' => $target_language,
                'original_text' => $text,
                'metadata' => array(
                    'api_time' => $execution_time,
                    'model_used' => $api_data['model'],
                    'timestamp' => current_time('mysql'),
                )
            ), __('Translation completed successfully.', 'nexus-ai-wp-translator'));
            
            // Fire after translation hook
            do_action('nexus_after_api_translate', $result, $text, $source_language, $target_language);
            
            // Fire analytics events
            do_action('nexus_analytics_event', 'translation_completed', array(
                'source' => $source_language,
                'target' => $target_language,
                'execution_time' => $execution_time,
                'character_count' => strlen($text),
                'timestamp' => current_time('mysql'),
            ));
            
            return $result;
            
        } else {
            // Fire analytics events
            do_action('nexus_analytics_event', 'translation_failed', array(
                'source' => $source_language,
                'target' => $target_language,
                'error_type' => $response['code'] ?? 'unknown',
                'error_message' => $response['message'],
                'timestamp' => current_time('mysql'),
            ));
            
            // Check if this should trigger emergency mode
            $this->check_emergency_trigger($response);
            
            return $response;
        }
    }
    
    /**
     * Make API request with retry logic
     * 
     * @since 0.0.1
     * @param array $data Request data
     * @return array API response
     */
    private function make_request_with_retry($data) {
        $max_retries = $this->config['max_retries'];
        $retry_count = 0;
        
        while ($retry_count <= $max_retries) {
            $response = $this->api->make_basic_request($data);
            
            // If successful, return immediately
            if ($response['success']) {
                return $response;
            }
            
            // Check if error is retryable
            if (!$this->is_retryable_error($response) || $retry_count >= $max_retries) {
                return $response;
            }
            
            // Calculate retry delay with exponential backoff
            $delay = $this->config['retry_delay'] * pow(2, $retry_count);
            $jitter = rand(0, 1000) / 1000; // Add jitter
            $total_delay = $delay + $jitter;
            
            // Log retry attempt
            $this->log_retry_attempt($retry_count + 1, $total_delay, $response);
            
            // Wait before retry
            sleep($total_delay);
            
            $retry_count++;
        }
        
        return $response;
    }
    
    /**
     * Check if error is retryable
     * 
     * @since 0.0.1
     * @param array $error_response Error response
     * @return bool True if retryable
     */
    private function is_retryable_error($error_response) {
        $retryable_codes = array(
            'rate_limit_exceeded',
            'server_error',
            'timeout',
            'http_error',
            'network_error',
            'service_unavailable',
        );
        
        $error_code = $error_response['code'] ?? '';
        return in_array($error_code, $retryable_codes, true);
    }
    
    /**
     * Validate translation request
     * 
     * @since 0.0.1
     * @param string $text Text to translate
     * @param string $source_language Source language
     * @param string $target_language Target language
     * @return array Validation result
     */
    private function validate_translation_request($text, $source_language, $target_language) {
        $errors = array();
        
        // Validate text
        if (empty($text) || !is_string($text)) {
            $errors['text'] = __('Text to translate is required and must be a string.', 'nexus-ai-wp-translator');
        } elseif (strlen($text) > 100000) {
            $errors['text'] = __('Text is too long. Maximum 100,000 characters allowed.', 'nexus-ai-wp-translator');
        }
        
        // Validate languages using Module 4
        if ($this->languages) {
            $lang_validation = $this->languages->validate_language_pair($source_language, $target_language);
            if (!$lang_validation['valid']) {
                $errors = array_merge($errors, $lang_validation['errors']);
            }
        } else {
            $errors['languages'] = __('Language validation service unavailable.', 'nexus-ai-wp-translator');
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors,
        );
    }
    
    /**
     * Check rate limits
     * 
     * @since 0.0.1
     * @return array Rate limit check result
     */
    private function check_rate_limit() {
        $current_minute = floor(time() / 60);
        $rate_limit_key = 'nexus_api_rate_limit_' . $current_minute;
        
        $current_count = get_transient($rate_limit_key) ?: 0;
        $limit = $this->config['rate_limit_per_minute'];
        
        if ($current_count >= $limit) {
            return array(
                'allowed' => false,
                'current_count' => $current_count,
                'limit' => $limit,
                'retry_after' => 60 - (time() % 60),
            );
        }
        
        // Increment counter
        set_transient($rate_limit_key, $current_count + 1, 120);
        
        return array(
            'allowed' => true,
            'current_count' => $current_count + 1,
            'limit' => $limit,
            'remaining' => $limit - ($current_count + 1),
        );
    }
    
    /**
     * Build translation prompt
     * 
     * @since 0.0.1
     * @param string $text Text to translate
     * @param string $source_lang Source language
     * @param string $target_lang Target language
     * @param array $options Options
     * @return string Translation prompt
     */
    private function build_translation_prompt($text, $source_lang, $target_lang, $options = array()) {
        $source_name = $source_lang;
        $target_name = $target_lang;
        
        // Get language names if available
        if ($this->languages) {
            $source_info = $this->languages->get_language($source_lang);
            $target_info = $this->languages->get_language($target_lang);
            
            $source_name = $source_info['name'] ?? $source_lang;
            $target_name = $target_info['name'] ?? $target_lang;
        }
        
        $prompt = sprintf(
            /* translators: 1: Source language, 2: Target language */
            __('Translate the following text from %1$s to %2$s. Preserve the original meaning, tone, and formatting. Only return the translated text without any additional commentary.', 'nexus-ai-wp-translator'),
            $source_name,
            $target_name
        );
        
        $prompt .= "\n\nText to translate:\n" . $text;
        
        // Add context if provided
        if (!empty($options['context'])) {
            $prompt .= "\n\nContext: " . $options['context'];
        }
        
        return apply_filters('nexus_translation_prompt', $prompt, $text, $source_lang, $target_lang, $options);
    }
    
    /**
     * Extract translation from API response
     * 
     * @since 0.0.1
     * @param array $response_data API response
     * @return string Translated text
     */
    private function extract_translation($response_data) {
        if (isset($response_data['content'][0]['text'])) {
            return trim($response_data['content'][0]['text']);
        }
        
        return '';
    }
    
    /**
     * Get recommended model for language pair
     * 
     * @since 0.0.1
     * @param string $source_lang Source language
     * @param string $target_lang Target language
     * @return string Recommended model
     */
    private function get_recommended_model($source_lang, $target_lang) {
        $default_model = 'claude-3-haiku-20240307';
        
        // Get language complexity from Module 4
        if ($this->languages) {
            $pair_info = $this->languages->get_pair_info($source_lang, $target_lang);
            
            if ($pair_info) {
                switch ($pair_info['complexity']) {
                    case 'high':
                        return 'claude-3-opus-20240229';
                    case 'medium':
                        return 'claude-3-sonnet-20240229';
                    case 'low':
                    default:
                        return 'claude-3-haiku-20240307';
                }
            }
        }
        
        return apply_filters('nexus_recommended_model', $default_model, $source_lang, $target_lang);
    }
    
    /**
     * Log retry attempt
     * 
     * @since 0.0.1
     * @param int $retry_count Current retry count
     * @param float $delay Delay before retry
     * @param array $error_response Previous error response
     * @return void
     */
    private function log_retry_attempt($retry_count, $delay, $error_response) {
        do_action('nexus_analytics_event', 'api_retry_attempt', array(
            'retry_count' => $retry_count,
            'delay' => $delay,
            'error_code' => $error_response['code'] ?? 'unknown',
            'error_message' => $error_response['message'] ?? '',
            'timestamp' => current_time('mysql'),
        ));
    }
    
    /**
     * Check if error should trigger emergency mode
     * 
     * @since 0.0.1
     * @param array $error_response Error response
     * @return void
     */
    private function check_emergency_trigger($error_response) {
        $error_count = get_transient('nexus_ai_translator_api_error_count') ?: 0;
        $error_count++;
        
        set_transient('nexus_ai_translator_api_error_count', $error_count, HOUR_IN_SECONDS);
        
        // Trigger emergency mode if too many errors
        if ($error_count >= $this->config['emergency_threshold']) {
            do_action('nexus_emergency_trigger', 'api_errors', array(
                'error_count' => $error_count,
                'last_error' => $error_response,
                'timestamp' => current_time('mysql'),
            ));
        }
    }
    
    /**
     * Handle emergency trigger
     * 
     * @since 0.0.1
     * @param string $trigger_type Trigger type
     * @param array $trigger_data Trigger data
     * @return void
     */
    public function handle_emergency_trigger($trigger_type, $trigger_data) {
        if ($trigger_type === 'api_errors') {
            // Log emergency mode activation
            error_log('[Nexus AI Translator] Emergency mode triggered due to API errors: ' . $trigger_data['error_count']);
            
            // Fire analytics event
            do_action('nexus_analytics_event', 'emergency_mode_triggered', array(
                'trigger_type' => $trigger_type,
                'error_count' => $trigger_data['error_count'],
                'timestamp' => current_time('mysql'),
            ));
        }
    }
    
    /**
     * Daily cleanup
     * 
     * @since 0.0.1
     * @return void
     */
    public function daily_cleanup() {
        // Reset error counters
        delete_transient('nexus_ai_translator_api_error_count');
        
        // Clean up old rate limit counters
        $current_minute = floor(time() / 60);
        for ($i = 5; $i < 60; $i++) {
            delete_transient('nexus_api_rate_limit_' . ($current_minute - $i));
        }
        
        // Fire analytics event
        do_action('nexus_analytics_event', 'api_handler_cleanup', array(
            'timestamp' => current_time('mysql'),
        ));
    }
    
    /**
     * Get rate limit status
     * 
     * @since 0.0.1
     * @return array Rate limit information
     */
    public function get_rate_limit_status() {
        $current_minute = floor(time() / 60);
        $rate_limit_key = 'nexus_api_rate_limit_' . $current_minute;
        
        $current_count = get_transient($rate_limit_key) ?: 0;
        $limit = $this->config['rate_limit_per_minute'];
        
        return array(
            'current_requests' => $current_count,
            'limit_per_minute' => $limit,
            'remaining' => max(0, $limit - $current_count),
            'reset_time' => ($current_minute + 1) * 60,
        );
    }
    
    /**
     * Get error statistics
     * 
     * @since 0.0.1
     * @return array Error statistics
     */
    public function get_error_stats() {
        $error_count = get_transient('nexus_ai_translator_api_error_count') ?: 0;
        
        return array(
            'current_error_count' => $error_count,
            'emergency_threshold' => $this->config['emergency_threshold'],
            'emergency_risk' => $error_count / $this->config['emergency_threshold'],
            'emergency_mode_active' => apply_filters('nexus_emergency_mode_active', false),
        );
    }
    
    /**
     * Update handler configuration
     * 
     * @since 0.0.1
     * @param array $new_config New configuration
     * @return bool True on success
     */
    public function update_config($new_config) {
        $current_config = get_option('nexus_ai_translator_api_config', array());
        $updated_config = wp_parse_args($new_config, $current_config);
        
        // Validate configuration
        $validated_config = $this->validate_config($updated_config);
        
        if ($validated_config['valid']) {
            update_option('nexus_ai_translator_api_config', $validated_config['config']);
            $this->config = wp_parse_args($validated_config['config'], $this->get_default_config());
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Validate handler configuration
     * 
     * @since 0.0.1
     * @param array $config Configuration to validate
     * @return array Validation result
     */
    private function validate_config($config) {
        $errors = array();
        $cleaned_config = array();
        
        // Validate max_retries
        if (isset($config['max_retries'])) {
            $retries = intval($config['max_retries']);
            if ($retries < 0 || $retries > 10) {
                $errors['max_retries'] = __('Max retries must be between 0 and 10.', 'nexus-ai-wp-translator');
            } else {
                $cleaned_config['max_retries'] = $retries;
            }
        }
        
        // Validate retry_delay
        if (isset($config['retry_delay'])) {
            $delay = floatval($config['retry_delay']);
            if ($delay < 0.1 || $delay > 60) {
                $errors['retry_delay'] = __('Retry delay must be between 0.1 and 60 seconds.', 'nexus-ai-wp-translator');
            } else {
                $cleaned_config['retry_delay'] = $delay;
            }
        }
        
        // Validate rate_limit_per_minute
        if (isset($config['rate_limit_per_minute'])) {
            $rate_limit = intval($config['rate_limit_per_minute']);
            if ($rate_limit < 1 || $rate_limit > 1000) {
                $errors['rate_limit_per_minute'] = __('Rate limit must be between 1 and 1000 requests per minute.', 'nexus-ai-wp-translator');
            } else {
                $cleaned_config['rate_limit_per_minute'] = $rate_limit;
            }
        }
        
        // Validate emergency_threshold
        if (isset($config['emergency_threshold'])) {
            $threshold = intval($config['emergency_threshold']);
            if ($threshold < 1 || $threshold > 100) {
                $errors['emergency_threshold'] = __('Emergency threshold must be between 1 and 100.', 'nexus-ai-wp-translator');
            } else {
                $cleaned_config['emergency_threshold'] = $threshold;
            }
        }
        
        return array(
            'valid' => empty($errors),
            'config' => $cleaned_config,
            'errors' => $errors,
        );
    }
    
    /**
     * Get default configuration
     * 
     * @since 0.0.1
     * @return array Default configuration
     */
    private function get_default_config() {
        return array(
            'max_retries' => 3,
            'retry_delay' => 2,
            'rate_limit_per_minute' => 50,
            'emergency_threshold' => 10,
        );
    }
    
    /**
     * Create success response
     * 
     * @since 0.0.1
     * @param array $data Response data
     * @param string $message Success message
     * @return array Success response
     */
    private function create_success_response($data = array(), $message = '') {
        return array(
            'success' => true,
            'data' => $data,
            'message' => $message,
            'timestamp' => current_time('mysql'),
        );
    }
    
    /**
     * Create error response
     * 
     * @since 0.0.1
     * @param string $message Error message
     * @param string $code Error code
     * @param array $details Additional details
     * @return array Error response
     */
    private function create_error_response($message, $code = 'general_error', $details = array()) {
        return array(
            'success' => false,
            'message' => $message,
            'code' => $code,
            'errors' => $details,
            'timestamp' => current_time('mysql'),
        );
    }
    
    /**
     * Create emergency mode response
     * 
     * @since 0.0.1
     * @return array Emergency response
     */
    private function create_emergency_response() {
        return $this->create_error_response(
            __('Translation service is temporarily unavailable due to system maintenance.', 'nexus-ai-wp-translator'),
            'emergency_mode'
        );
    }