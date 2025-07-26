<?php
/**
 * File: class-api.php
 * Location: /includes/class-api.php
 * 
 * Core Claude API Client Class
 * Responsible for: Basic API communication, authentication, simple requests
 * 
 * @package Nexus\Translator
 */

namespace Nexus\Translator;

use Nexus\Translator\Abstracts\Abstract_Module;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Core Claude API client class
 * 
 * Handles basic Claude AI communication - authentication and simple API calls.
 * Complex error handling and performance features are in separate classes.
 * 
 */
class Api extends Abstract_Module {
    
    /**
     * Claude API base URL
     * 
     * @var string
     */
    private $api_base_url = 'https://api.anthropic.com/v1/messages';
    
    /**
     * API key for authentication
     * 
     * @var string
     */
    private $api_key = '';
    
    /**
     * Languages instance for validation
     * 
     * @var Languages
     */
    private $languages;
    
    /**
     * API handler for complex operations
     * 
     * @var Api_Handler
     */
    private $handler;
    
    /**
     * Performance tracker
     * 
     * @var Api_Performance
     */
    private $performance;
    
    /**
     * Get module name/identifier
     * 
     * @return string Module name
     */
    protected function get_module_name() {
        return 'api';
    }
    
    /**
     * Module-specific initialization
     * 
     * @return void
     */
    protected function module_init() {
        // Load API configuration
        $this->load_api_config();
        
        // Get Languages instance
        $main = \Nexus\Translator\Main::get_instance();
        $this->languages = $main->get_component('languages');
        
        // Load helper classes
        $this->load_helper_classes();
        
        // Initialize helper components
        $this->init_helper_components();
    }
    
    /**
     * Register WordPress hooks
     * 
     * @return void
     */
    protected function register_hooks() {
        // Admin hooks
        $this->add_hook('admin_init', array($this, 'admin_init'));
        
        // Let helper classes register their own hooks
        if ($this->handler) {
            $this->handler->register_hooks();
        }
        
        if ($this->performance) {
            $this->performance->register_hooks();
        }
    }
    
    /**
     * Load helper class files
     * 
     * @return void
     */
    private function load_helper_classes() {
        $helper_files = array(
            'class-api-handler.php',
            'class-api-performance.php',
        );
        
        foreach ($helper_files as $file) {
            $file_path = NEXUS_AI_TRANSLATOR_INCLUDES_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }
    
    /**
     * Initialize helper components
     * 
     * @return void
     */
    private function init_helper_components() {
        // Initialize API handler
        if (class_exists('Nexus\\Translator\\Api_Handler')) {
            $this->handler = new Api_Handler($this);
        }
        
        // Initialize performance tracker
        if (class_exists('Nexus\\Translator\\Api_Performance')) {
            $this->performance = new Api_Performance($this);
        }
    }
    
    /**
     * Load API configuration from database
     * 
     * @return void
     */
    private function load_api_config() {
        $settings = get_option('nexus_ai_translator_settings', array());
        $this->api_key = $settings['api_key'] ?? '';
        
        // Load basic config
        $api_config = get_option('nexus_ai_translator_api_config', array());
        $defaults = array(
            'timeout' => 30,
            'max_tokens' => 4000,
        );
        
        $this->config = wp_parse_args($api_config, $defaults);
    }
    
    /**
     * Authenticate with Claude API
     * 
     * @param string $api_key Optional API key to test
     * @return array Authentication result
     */
    public function authenticate($api_key = '') {
        $test_key = !empty($api_key) ? $api_key : $this->api_key;
        
        if (empty($test_key)) {
            return $this->create_error_response(
                __('API key is required for authentication.', 'nexus-ai-wp-translator'),
                'missing_api_key'
            );
        }
        
        // Simple test request
        $test_data = array(
            'model' => 'claude-3-haiku-20240307',
            'max_tokens' => 10,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => 'Hello'
                )
            )
        );
        
        $start_time = microtime(true);
        $response = $this->make_basic_request($test_data, $test_key);
        $execution_time = microtime(true) - $start_time;
        
        if ($response['success']) {
            // Save API key if test successful
            if (!empty($api_key) && $api_key !== $this->api_key) {
                $settings = get_option('nexus_ai_translator_settings', array());
                $settings['api_key'] = $api_key;
                update_option('nexus_ai_translator_settings', $settings);
                $this->api_key = $api_key;
            }
            
            // Fire analytics event
            do_action('nexus_analytics_event', 'api_authentication_success', array(
                'response_time' => $execution_time,
                'timestamp' => current_time('mysql'),
            ));
            
            return $this->create_success_response(array(
                'authenticated' => true,
                'response_time' => $execution_time,
            ), __('Successfully authenticated with Claude AI.', 'nexus-ai-wp-translator'));
        }
        
        return $response;
    }
    
    /**
     * Translate text using Claude AI
     * 
     * @param string $text Text to translate
     * @param string $source_language Source language code
     * @param string $target_language Target language code
     * @param array $options Translation options
     * @return array Translation result
     */
    public function translate($text, $source_language, $target_language, $options = array()) {
        // Delegate to handler for complex processing
        if ($this->handler) {
            return $this->handler->handle_translation($text, $source_language, $target_language, $options);
        }
        
        // Fallback to basic translation
        return $this->basic_translate($text, $source_language, $target_language, $options);
    }
    
    /**
     * Basic translation without advanced features
     * 
     * @param string $text Text to translate
     * @param string $source_language Source language
     * @param string $target_language Target language
     * @param array $options Options
     * @return array Translation result
     */
    private function basic_translate($text, $source_language, $target_language, $options = array()) {
        // Basic validation
        if (empty($text)) {
            return $this->create_error_response(
                __('Text to translate is required.', 'nexus-ai-wp-translator'),
                'missing_text'
            );
        }
        
        // Build prompt
        $prompt = $this->build_translation_prompt($text, $source_language, $target_language);
        
        // Prepare API request
        $api_data = array(
            'model' => 'claude-3-haiku-20240307',
            'max_tokens' => $this->config['max_tokens'],
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            )
        );
        
        // Make request
        $response = $this->make_basic_request($api_data);
        
        if ($response['success']) {
            $translated_text = $this->extract_translation($response['data']);
            
            return $this->create_success_response(array(
                'translated_text' => $translated_text,
                'source_language' => $source_language,
                'target_language' => $target_language,
            ));
        }
        
        return $response;
    }
    
    /**
     * Test API connection
     * 
     * @return array Connection test result
     */
    public function test_connection() {
        if (empty($this->api_key)) {
            return $this->create_error_response(
                __('No API key configured.', 'nexus-ai-wp-translator'),
                'no_api_key'
            );
        }
        
        return $this->authenticate($this->api_key);
    }
    
    /**
     * Make basic API request
     * 
     * @param array $data Request data
     * @param string $api_key Optional API key override
     * @return array API response
     */
    public function make_basic_request($data, $api_key = '') {
        $key = !empty($api_key) ? $api_key : $this->api_key;
        
        if (empty($key)) {
            return $this->create_error_response(
                __('API key is required.', 'nexus-ai-wp-translator'),
                'missing_api_key'
            );
        }
        
        // Prepare headers
        $headers = array(
            'Content-Type' => 'application/json',
            'x-api-key' => $key,
            'anthropic-version' => '2023-06-01',
        );
        
        // Prepare request
        $args = array(
            'method' => 'POST',
            'headers' => $headers,
            'body' => json_encode($data),
            'timeout' => $this->config['timeout'],
            'user-agent' => 'Nexus-AI-WP-Translator/' . NEXUS_AI_TRANSLATOR_VERSION,
        );
        
        // Make request
        $response = wp_remote_post($this->api_base_url, $args);
        
        // Handle WordPress errors
        if (is_wp_error($response)) {
            return $this->create_error_response(
                sprintf(
                    /* translators: %s: Error message */
                    __('HTTP request failed: %s', 'nexus-ai-wp-translator'),
                    $response->get_error_message()
                ),
                'http_error'
            );
        }
        
        // Parse response
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $response_data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->create_error_response(
                __('Invalid JSON response from API.', 'nexus-ai-wp-translator'),
                'invalid_json'
            );
        }
        
        // Handle API errors
        if ($status_code !== 200) {
            $error_message = $response_data['error']['message'] ?? __('Unknown API error.', 'nexus-ai-wp-translator');
            return $this->create_error_response($error_message, 'api_error');
        }
        
        return $this->create_success_response($response_data);
    }
    
    /**
     * Build translation prompt
     * 
     * @param string $text Text to translate
     * @param string $source_lang Source language
     * @param string $target_lang Target language
     * @return string Translation prompt
     */
    private function build_translation_prompt($text, $source_lang, $target_lang) {
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
            __('Translate the following text from %1$s to %2$s. Return only the translated text.', 'nexus-ai-wp-translator'),
            $source_name,
            $target_name
        );
        
        $prompt .= "\n\n" . $text;
        
        return $prompt;
    }
    
    /**
     * Extract translation from API response
     * 
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
     * Create success response
     * 
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
     * @param string $message Error message
     * @param string $code Error code
     * @return array Error response
     */
    private function create_error_response($message, $code = 'general_error') {
        return array(
            'success' => false,
            'message' => $message,
            'code' => $code,
            'timestamp' => current_time('mysql'),
        );
    }
    
    /**
     * Get API key
     * 
     * @return string API key
     */
    public function get_api_key() {
        return $this->api_key;
    }
    
    /**
     * Get handler instance
     * 
     * @return Api_Handler|null Handler instance
     */
    public function get_handler() {
        return $this->handler;
    }
    
    /**
     * Get performance instance
     * 
     * @return Api_Performance|null Performance instance
     */
    public function get_performance() {
        return $this->performance;
    }
    
    /**
     * Admin initialization
     * 
     * @return void
     */
    public function admin_init() {
        // Test connection if API key was saved
        if (isset($_POST['nexus_ai_translator_settings']) && 
            isset($_POST['nexus_ai_translator_settings']['api_key']) &&
            wp_verify_nonce($_POST['_wpnonce'], 'nexus_ai_translator_settings')) {
            
            $new_key = sanitize_text_field($_POST['nexus_ai_translator_settings']['api_key']);
            if (!empty($new_key) && $new_key !== $this->api_key) {
                $auth_result = $this->authenticate($new_key);
                
                if (!$auth_result['success']) {
                    add_action('admin_notices', function() use ($auth_result) {
                        echo '<div class="notice notice-error"><p>';
                        echo '<strong>' . __('Nexus AI Translator:', 'nexus-ai-wp-translator') . '</strong> ';
                        echo esc_html($auth_result['message']);
                        echo '</p></div>';
                    });
                }
            }
        }
    }
}