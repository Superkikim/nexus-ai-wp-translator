<?php
/**
 * File: class-translator-ajax.php
 * Location: /includes/class-translator-ajax.php
 * 
 * Translator AJAX Class
 * 
 * Handles AJAX requests for translation functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translator_AJAX {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // AJAX handlers for logged-in users
        add_action('wp_ajax_nexus_translate_post', array($this, 'handle_translate_post'));
        add_action('wp_ajax_nexus_test_api_connection', array($this, 'handle_test_api_connection'));
        add_action('wp_ajax_nexus_get_translation_status', array($this, 'handle_get_translation_status'));
        add_action('wp_ajax_nexus_delete_translation', array($this, 'handle_delete_translation'));
        add_action('wp_ajax_nexus_update_translation', array($this, 'handle_update_translation'));
    }
    
    /**
     * Handle post translation request
     */
    public function handle_translate_post() {
        error_log('Nexus Translator: Translation request received');
        error_log('Nexus Translator: POST data: ' . print_r($_POST, true));
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce')) {
            error_log('Nexus Translator: Nonce verification failed');
            wp_die(__('Security check failed', 'nexus-ai-wp-translator'));
        }
        
        // Check capabilities
        if (!current_user_can('edit_posts')) {
            error_log('Nexus Translator: Insufficient permissions');
            wp_send_json_error(__('Insufficient permissions', 'nexus-ai-wp-translator'));
        }
        
        // Get and validate parameters
        $post_id = (int) $_POST['post_id'];
        $target_language = sanitize_text_field($_POST['target_language']);
        
        error_log("Nexus Translator: Processing translation - Post ID: $post_id, Target: $target_language");
        
        if (!$post_id || !$target_language) {
            error_log('Nexus Translator: Invalid parameters provided');
            wp_send_json_error(__('Invalid parameters', 'nexus-ai-wp-translator'));
        }
        
        // Verify post exists and user can edit it
        $post = get_post($post_id);
        if (!$post || !current_user_can('edit_post', $post_id)) {
            error_log('Nexus Translator: Post not found or no permission');
            wp_send_json_error(__('Post not found or no permission', 'nexus-ai-wp-translator'));
        }
        
        error_log("Nexus Translator: Post found: {$post->post_title}");
        
        // Initialize translator
        $translator = new Nexus_Translator();
        
        error_log('Nexus Translator: Starting translation process');
        
        // Perform translation
        $result = $translator->translate_post($post_id, $target_language);
        
        error_log('Nexus Translator: Translation result: ' . print_r($result, true));
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => __('Translation completed successfully!', 'nexus-ai-wp-translator'),
                'translated_post_id' => $result['translated_post_id'],
                'edit_link' => $result['edit_link'],
                'view_link' => $result['view_link'],
                'usage' => $result['usage']
            ));
        } else {
            error_log('Nexus Translator: Translation failed: ' . $result['error']);
            wp_send_json_error($result['error']);
        }
    }
    
    /**
     * Handle API connection test
     */
    public function handle_test_api_connection() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce')) {
            wp_die(__('Security check failed', 'nexus-ai-wp-translator'));
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'nexus-ai-wp-translator'));
        }
        
        // Test API connection
        $api = new Translator_API();
        $result = $api->test_api_connection();
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'test_translation' => $result['test_translation']
            ));
        } else {
            wp_send_json_error($result['error']);
        }
    }
    
    /**
     * Handle get translation status request
     */
    public function handle_get_translation_status() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce')) {
            wp_die(__('Security check failed', 'nexus-ai-wp-translator'));
        }
        
        // Check capabilities
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'nexus-ai-wp-translator'));
        }
        
        $post_id = (int) $_POST['post_id'];
        if (!$post_id) {
            wp_send_json_error(__('Invalid post ID', 'nexus-ai-wp-translator'));
        }
        
        $post_linker = new Post_Linker();
        
        // Get all translations
        $translations = $post_linker->get_all_translations($post_id);
        $current_language = $post_linker->get_post_language($post_id);
        
        $translation_data = array();
        foreach ($translations as $language => $translated_post_id) {
            $status = $post_linker->get_translation_status($translated_post_id);
            $post = get_post($translated_post_id);
            
            $translation_data[] = array(
                'language' => $language,
                'post_id' => $translated_post_id,
                'status' => $status,
                'title' => $post ? $post->post_title : '',
                'edit_link' => get_edit_post_link($translated_post_id),
                'view_link' => get_permalink($translated_post_id),
                'is_current' => $translated_post_id === $post_id
            );
        }
        
        wp_send_json_success(array(
            'current_language' => $current_language,
            'translations' => $translation_data
        ));
    }
    
    /**
     * Handle delete translation request
     */
    public function handle_delete_translation() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce')) {
            wp_die(__('Security check failed', 'nexus-ai-wp-translator'));
        }
        
        // Check capabilities
        if (!current_user_can('delete_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'nexus-ai-wp-translator'));
        }
        
        $post_id = (int) $_POST['post_id'];
        if (!$post_id) {
            wp_send_json_error(__('Invalid post ID', 'nexus-ai-wp-translator'));
        }
        
        // Verify user can delete this post
        if (!current_user_can('delete_post', $post_id)) {
            wp_send_json_error(__('No permission to delete this post', 'nexus-ai-wp-translator'));
        }
        
        $post_linker = new Post_Linker();
        
        // Remove translation relationships
        $post_linker->delete_translation_relationship($post_id);
        
        // Move post to trash (don't permanently delete)
        $result = wp_trash_post($post_id);
        
        if ($result) {
            wp_send_json_success(__('Translation deleted successfully', 'nexus-ai-wp-translator'));
        } else {
            wp_send_json_error(__('Failed to delete translation', 'nexus-ai-wp-translator'));
        }
    }
    
    /**
     * Handle update translation request
     */
    public function handle_update_translation() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce')) {
            wp_die(__('Security check failed', 'nexus-ai-wp-translator'));
        }
        
        // Check capabilities
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'nexus-ai-wp-translator'));
        }
        
        $original_post_id = (int) $_POST['original_post_id'];
        $translated_post_id = (int) $_POST['translated_post_id'];
        
        if (!$original_post_id || !$translated_post_id) {
            wp_send_json_error(__('Invalid parameters', 'nexus-ai-wp-translator'));
        }
        
        // Verify posts exist and user can edit them
        if (!current_user_can('edit_post', $original_post_id) || !current_user_can('edit_post', $translated_post_id)) {
            wp_send_json_error(__('Insufficient permissions for these posts', 'nexus-ai-wp-translator'));
        }
        
        $post_linker = new Post_Linker();
        $target_language = $post_linker->get_post_language($translated_post_id);
        
        if (!$target_language) {
            wp_send_json_error(__('Could not determine target language', 'nexus-ai-wp-translator'));
        }
        
        // Set status to pending
        $post_linker->update_translation_status($translated_post_id, Post_Linker::STATUS_PENDING);
        
        // Initialize translator and re-translate
        $translator = new Nexus_Translator();
        $original_post = get_post($original_post_id);
        
        // Get original content
        $content_to_translate = sprintf(
            "TITLE: %s\n\nCONTENT:\n%s",
            $original_post->post_title,
            $original_post->post_content
        );
        
        if (!empty($original_post->post_excerpt)) {
            $content_to_translate = sprintf(
                "TITLE: %s\n\nEXCERPT: %s\n\nCONTENT:\n%s",
                $original_post->post_title,
                $original_post->post_excerpt,
                $original_post->post_content
            );
        }
        
        // Get source language
        $source_language = $post_linker->get_post_language($original_post_id);
        if (!$source_language) {
            $settings = get_option('nexus_translator_language_settings', array());
            $source_language = $settings['source_language'] ?? 'fr';
        }
        
        // Translate content
        $api = new Translator_API();
        $translation_result = $api->translate_content(
            $content_to_translate,
            $source_language,
            $target_language,
            $original_post->post_type
        );
        
        if (!$translation_result['success']) {
            $post_linker->update_translation_status($translated_post_id, Post_Linker::STATUS_ERROR);
            wp_send_json_error($translation_result['error']);
        }
        
        // Parse translated content
        $content_parts = $this->parse_translated_content($translation_result['translated_content']);
        
        // Update translated post
        $update_data = array(
            'ID' => $translated_post_id,
            'post_title' => $content_parts['title'],
            'post_content' => $content_parts['content'],
            'post_excerpt' => $content_parts['excerpt']
        );
        
        $result = wp_update_post($update_data);
        
        if (is_wp_error($result)) {
            $post_linker->update_translation_status($translated_post_id, Post_Linker::STATUS_ERROR);
            wp_send_json_error(__('Failed to update translated post', 'nexus-ai-wp-translator'));
        }
        
        // Update status to completed
        $post_linker->update_translation_status($translated_post_id, Post_Linker::STATUS_COMPLETED);
        
        wp_send_json_success(array(
            'message' => __('Translation updated successfully!', 'nexus-ai-wp-translator'),
            'edit_link' => get_edit_post_link($translated_post_id),
            'view_link' => get_permalink($translated_post_id),
            'usage' => $translation_result['usage']
        ));
    }
    
    /**
     * Parse translated content
     */
    private function parse_translated_content($content) {
        $parts = array(
            'title' => '',
            'excerpt' => '',
            'content' => $content
        );
        
        // Extract title
        if (preg_match('/^TITLE:\s*(.+?)(?:\n|$)/m', $content, $matches)) {
            $parts['title'] = trim($matches[1]);
            $content = preg_replace('/^TITLE:\s*.+?(?:\n|$)/m', '', $content);
        }
        
        // Extract excerpt
        if (preg_match('/EXCERPT:\s*(.+?)(?:\n\n|\nCONTENT:)/s', $content, $matches)) {
            $parts['excerpt'] = trim($matches[1]);
            $content = preg_replace('/EXCERPT:\s*.+?(?:\n\n|\nCONTENT:)/s', '', $content);
        }
        
        // Extract content
        if (preg_match('/CONTENT:\s*(.+)/s', $content, $matches)) {
            $parts['content'] = trim($matches[1]);
        } else {
            // If no CONTENT: marker, use everything after title/excerpt
            $parts['content'] = trim($content);
        }
        
        return $parts;
    }
}