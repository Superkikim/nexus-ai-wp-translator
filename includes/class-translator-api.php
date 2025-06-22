<?php
/**
 * File: class-translator-api.php
 * Location: /includes/class-translator-api.php
 * 
 * Translator API Class
 * 
 * Handles communication with Claude AI API
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translator_API {
    
    /**
     * API endpoint
     */
    private const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';
    
    /**
     * API settings
     */
    private $api_settings;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_settings = get_option('nexus_translator_api_settings', array());
    }
    
    /**
     * Translate content using Claude AI
     * 
     * @param string $content Content to translate
     * @param string $source_lang Source language code
     * @param string $target_lang Target language code
     * @param string $post_type Post type (post, page, etc.)
     * @return array Translation result
     */
    public function translate_content($content, $source_lang, $target_lang, $post_type = 'post') {
        // Validate API key
        if (!$this->is_api_configured()) {
            return $this->error_response(__('Claude API not configured', 'nexus-ai-wp-translator'));
        }
        
        // Prepare translation data
        $translation_data = $this->prepare_translation_data($content, $source_lang, $target_lang, $post_type);
        
        // Make API request
        $response = $this->make_api_request($translation_data);
        
        // Process response
        return $this->process_api_response($response);
    }
    
    /**
     * Check if API is properly configured
     */
    public function is_api_configured() {
        return !empty($this->api_settings['claude_api_key']);
    }
    
    /**
     * Prepare translation data for API request
     */
    private function prepare_translation_data($content, $source_lang, $target_lang, $post_type) {
        $source_language = $this->get_language_name($source_lang);
        $target_language = $this->get_language_name($target_lang);
        
        // Create translation prompt
        $prompt = $this->build_translation_prompt($content, $source_language, $target_language, $post_type);
        
        return array(
            'model' => $this->get_model(),
            'max_tokens' => $this->get_max_tokens(),
            'temperature' => $this->get_temperature(),
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            )
        );
    }
    
    /**
     * Build translation prompt
     */
    private function build_translation_prompt($content, $source_language, $target_language, $post_type) {
        $post_type_instruction = $this->get_post_type_instruction($post_type);
        
        $prompt = sprintf(
            "You are a professional translator specialized in WordPress web content translation.\n\n" .
            "TASK: Translate the following content from %s to %s.\n\n" .
            "INSTRUCTIONS:\n" .
            "- %s\n" .
            "- Preserve HTML formatting and WordPress shortcodes\n" .
            "- Translate only textual content, not HTML tags\n" .
            "- Maintain the style and tone of the original content\n" .
            "- Adapt cultural references if necessary\n" .
            "- Return only the translation, without comments\n\n" .
            "CONTENT TO TRANSLATE:\n%s",
            $source_language,
            $target_language,
            $post_type_instruction,
            $content
        );
        
        return $prompt;
    }
    
    /**
     * Get post type specific instruction
     */
    private function get_post_type_instruction($post_type) {
        $instructions = array(
            'post' => 'Translate this blog article while maintaining its informative and engaging character',
            'page' => 'Translate this web page while maintaining its structure and purpose',
            'product' => 'Translate this product sheet while maintaining its commercial and persuasive aspect'
        );
        
        return $instructions[$post_type] ?? 'Translate this web content professionally';
    }
    
    /**
     * Make API request to Claude
     */
    private function make_api_request($data) {
        $headers = array(
            'Content-Type' => 'application/json',
            'x-api-key' => $this->api_settings['claude_api_key'],
            'anthropic-version' => '2023-06-01'
        );
        
        $args = array(
            'method' => 'POST',
            'headers' => $headers,
            'body' => json_encode($data),
            'timeout' => 60,
            'sslverify' => true
        );
        
        // Add debug logging
        if ($this->is_debug_mode()) {
            error_log('Nexus Translator API Request: ' . json_encode($data));
        }
        
        $response = wp_remote_request(self::API_ENDPOINT, $args);
        
        // Log response for debugging
        if ($this->is_debug_mode()) {
            error_log('Nexus Translator API Response: ' . wp_remote_retrieve_body($response));
        }
        
        return $response;
    }
    
    /**
     * Process API response
     */
    private function process_api_response($response) {
        // Check for WordPress HTTP errors
        if (is_wp_error($response)) {
            return $this->error_response($response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Check HTTP status
        if ($response_code !== 200) {
            return $this->handle_api_error($response_code, $response_body);
        }
        
        // Parse JSON response
        $data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->error_response(__('JSON parsing error', 'nexus-ai-wp-translator'));
        }
        
        // Extract translated content
        if (isset($data['content'][0]['text'])) {
            return array(
                'success' => true,
                'translated_content' => trim($data['content'][0]['text']),
                'usage' => $data['usage'] ?? null
            );
        }
        
        return $this->error_response(__('Invalid API response', 'nexus-ai-wp-translator'));
    }
    
    /**
     * Handle API errors
     */
    private function handle_api_error($status_code, $response_body) {
        $error_data = json_decode($response_body, true);
        $error_message = $error_data['error']['message'] ?? __('Unknown API error', 'nexus-ai-wp-translator');
        
        // Map common error codes to user-friendly messages
        $error_messages = array(
            400 => __('Invalid request', 'nexus-ai-wp-translator'),
            401 => __('Invalid or missing API key', 'nexus-ai-wp-translator'),
            403 => __('Access forbidden - check your API permissions', 'nexus-ai-wp-translator'),
            429 => __('Rate limit exceeded - try again later', 'nexus-ai-wp-translator'),
            500 => __('Claude AI server error', 'nexus-ai-wp-translator'),
            529 => __('Claude service temporarily overloaded', 'nexus-ai-wp-translator')
        );
        
        $user_message = $error_messages[$status_code] ?? $error_message;
        
        return array(
            'success' => false,
            'error' => $user_message,
            'error_code' => $status_code,
            'raw_error' => $error_message
        );
    }
    
    /**
     * Create error response
     */
    private function error_response($message) {
        return array(
            'success' => false,
            'error' => $message
        );
    }
    
    /**
     * Get language name from code
     */
    private function get_language_name($code) {
        $languages = array(
            'fr' => 'French',
            'en' => 'English',
            'es' => 'Spanish',
            'de' => 'German',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'nl' => 'Dutch',
            'ru' => 'Russian',
            'ja' => 'Japanese',
            'zh' => 'Chinese'
        );
        
        return $languages[$code] ?? $code;
    }
    
    /**
     * Get configured model
     */
    private function get_model() {
        return $this->api_settings['model'] ?? 'claude-sonnet-4-20250514';
    }
    
    /**
     * Get max tokens
     */
    private function get_max_tokens() {
        return $this->api_settings['max_tokens'] ?? 4000;
    }
    
    /**
     * Get temperature
     */
    private function get_temperature() {
        return $this->api_settings['temperature'] ?? 0.3;
    }
    
    /**
     * Check if debug mode is enabled
     */
    private function is_debug_mode() {
        $options = get_option('nexus_translator_options', array());
        $debug = !empty($options['debug_mode']);
        error_log('NEXUS DEBUG: Debug mode check = ' . ($debug ? 'TRUE' : 'FALSE'));
        return $debug;
    }
    
    /**
     * Test API connection
     */
    public function test_api_connection() {
        if (!$this->is_api_configured()) {
            return $this->error_response(__('API key not configured', 'nexus-ai-wp-translator'));
        }
        
        // Simple test translation
        $test_content = "Hello, this is a test.";
        $result = $this->translate_content($test_content, 'en', 'fr', 'post');
        
        if ($result['success']) {
            return array(
                'success' => true,
                'message' => __('API connection successful', 'nexus-ai-wp-translator'),
                'test_translation' => $result['translated_content']
            );
        }
        
        return $result;
    }
    
    /**
     * Get API usage statistics (if available)
     */
    public function get_usage_stats() {
        // This would require storing usage data
        // For now, return placeholder
        return array(
            'translations_today' => 0,
            'translations_month' => 0,
            'tokens_used' => 0
        );
    }
}