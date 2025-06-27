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
        
        // Prevent same-language translation
        if ($source_lang === $target_lang) {
            return $this->error_response(sprintf(
                __('Cannot translate from %s to %s (same language)', 'nexus-ai-wp-translator'),
                $source_lang,
                $target_lang
            ));
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
            "- Preserve HTML formatting and WordPress shortcodes exactly\n" .
            "- Translate only textual content, never HTML tags or attributes\n" .
            "- Maintain the style and tone of the original content\n" .
            "- Adapt cultural references if necessary\n" .
            "- Return only the translation, without comments or explanations\n" .
            "- Keep the exact same structure (TITLE: ... CONTENT: ...)\n\n" .
            "CONTENT TO TRANSLATE:\n%s",
            $source_language,
            $target_