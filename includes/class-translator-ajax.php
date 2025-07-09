<?php
/**
 * File: class-translator-ajax.php
 * Location: /includes/class-translator-ajax.php
 * 
 * Translator AJAX Class
 * 
 * Handles AJAX requests for translation functionality with advanced rate limiting and protection
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
        $this->init_advanced_hooks();
    }
    
    /**
     * Initialize basic hooks
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
     * Initialize advanced hooks for enhanced features
     */
    private function init_advanced_hooks() {
        // Advanced API management
        add_action('wp_ajax_nexus_emergency_stop', array($this, 'handle_emergency_stop'));
        add_action('wp_ajax_nexus_reset_rate_limits', array($this, 'handle_reset_rate_limits'));
        add_action('wp_ajax_nexus_export_config', array($this, 'handle_export_config'));
        add_action('wp_ajax_nexus_import_config', array($this, 'handle_import_config'));
        add_action('wp_ajax_nexus_get_api_status', array($this, 'handle_get_api_status'));
        add_action('wp_ajax_nexus_translate_post_protected', array($this, 'handle_translate_post_protected'));
    }
    
    /**
     * Handle post translation request (enhanced with rate limiting)
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
        
        // Check if translation is allowed (rate limits, cooldowns, etc.)
        $api = new Translator_API();
        $translation_check = $api->can_translate_post($post_id);
        
        if (!$translation_check['can_translate']) {
            error_log('Nexus Translator: Translation not allowed: ' . $translation_check['reason']);
            wp_send_json_error($translation_check['reason']);
        }
        
        // Set translation lock
        $this->set_translation_lock($post_id);
        
        // Initialize translator
        $translator = new Nexus_Translator();
        
        error_log('Nexus Translator: Starting translation process');
        
        // Perform translation
        $result = $translator->translate_post($post_id, $target_language);
        
        // Clean up translation lock
        $this->clear_translation_lock($post_id);
        
        error_log('Nexus Translator: Translation result: ' . print_r($result, true));
        
        if ($result['success']) {
            // Log successful translation
            $this->log_translation_usage($post_id, $target_language, $result);
            
            wp_send_json_success(array(
                'message' => __('Translation completed successfully!', 'nexus-ai-wp-translator'),
                'translated_post_id' => $result['translated_post_id'],
                'edit_link' => $result['edit_link'],
                'view_link' => $result['view_link'],
                'usage' => $result['usage'],
                'rate_status' => $api->get_rate_limit_status()
            ));
        } else {
            error_log('Nexus Translator: Translation failed: ' . $result['error']);
            wp_send_json_error($result['error']);
        }
    }
    
    /**
     * Handle protected post translation with enhanced rate limiting
     */
    public function handle_translate_post_protected() {
        error_log('Nexus Translator: Protected translation request received');
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
        
        error_log("Nexus Translator: Processing protected translation - Post ID: $post_id, Target: $target_language");
        
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
        
        // Check if translation is allowed (rate limits, cooldowns, etc.)
        $api = new Translator_API();
        $translation_check = $api->can_translate_post($post_id);
        
        if (!$translation_check['can_translate']) {
            error_log('Nexus Translator: Translation not allowed: ' . $translation_check['reason']);
            wp_send_json_error($translation_check['reason']);
        }
        
        // Set translation lock
        $this->set_translation_lock($post_id);
        
        error_log("Nexus Translator: Translation lock set for post $post_id");
        
        // Initialize translator
        $translator = new Nexus_Translator();
        
        error_log('Nexus Translator: Starting protected translation process');
        
        // Perform translation
        $result = $translator->translate_post($post_id, $target_language);
        
        // Clean up translation lock
        $this->clear_translation_lock($post_id);
        
        error_log('Nexus Translator: Protected translation result: ' . print_r($result, true));
        
        if ($result['success']) {
            // Log successful translation
            $this->log_translation_usage($post_id, $target_language, $result);
            
            wp_send_json_success(array(
                'message' => __('Translation completed successfully!', 'nexus-ai-wp-translator'),
                'translated_post_id' => $result['translated_post_id'],
                'edit_link' => $result['edit_link'],
                'view_link' => $result['view_link'],
                'usage' => $result['usage'],
                'rate_status' => $api->get_rate_limit_status()
            ));
        } else {
            error_log('Nexus Translator: Protected translation failed: ' . $result['error']);
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
                'test_translation' => $result['test_translation'],
                'usage' => $result['usage'] ?? null,
                'settings_used' => $result['settings_used'] ?? null
            ));
        } else {
            wp_send_json_error($result['error']);
        }
    }
    
    /**
     * Handle emergency stop toggle
     */
    public function handle_emergency_stop() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce')) {
            wp_die(__('Security check failed', 'nexus-ai-wp-translator'));
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'nexus-ai-wp-translator'));
        }
        
        $activate = isset($_POST['activate']) && $_POST['activate'] === 'true';
        
        if ($activate) {
            // Activate emergency stop
            update_option('nexus_translator_emergency_stop', true);
            update_option('nexus_translator_emergency_reason', 'Manually activated by administrator');
            update_option('nexus_translator_emergency_time', current_time('timestamp'));
            
            error_log('Nexus Translator: Emergency stop manually activated by admin');
            
            wp_send_json_success(array(
                'message' => __('Emergency stop activated', 'nexus-ai-wp-translator'),
                'status' => 'emergency_active'
            ));
        } else {
            // Deactivate emergency stop
            delete_option('nexus_translator_emergency_stop');
            delete_option('nexus_translator_emergency_reason');
            delete_option('nexus_translator_emergency_time');
            
            error_log('Nexus Translator: Emergency stop manually deactivated by admin');
            
            wp_send_json_success(array(
                'message' => __('Emergency stop deactivated', 'nexus-ai-wp-translator'),
                'status' => 'emergency_inactive'
            ));
        }
    }
    
    /**
     * Handle rate limits reset
     */
    public function handle_reset_rate_limits() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce')) {
            wp_die(__('Security check failed', 'nexus-ai-wp-translator'));
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'nexus-ai-wp-translator'));
        }
        
        $api = new Translator_API();
        $result = $api->reset_rate_limits();
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Rate limits reset successfully', 'nexus-ai-wp-translator'),
                'status' => $api->get_rate_limit_status()
            ));
        } else {
            wp_send_json_error(__('Failed to reset rate limits', 'nexus-ai-wp-translator'));
        }
    }
    
    /**
     * Handle configuration export
     */
    public function handle_export_config() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce')) {
            wp_die(__('Security check failed', 'nexus-ai-wp-translator'));
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'nexus-ai-wp-translator'));
        }
        
        $api = new Translator_API();
        $config = $api->export_configuration();
        
        // Add additional plugin settings
        $config['language_settings'] = get_option('nexus_translator_language_settings', array());
        $config['general_options'] = get_option('nexus_translator_options', array());
        
        // Remove sensitive data
        if (isset($config['api_settings']['claude_api_key'])) {
            $config['api_settings']['claude_api_key'] = '[API_KEY_REMOVED]';
        }
        
        wp_send_json_success($config);
    }
    
    /**
     * Handle configuration import
     */
    public function handle_import_config() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce')) {
            wp_die(__('Security check failed', 'nexus-ai-wp-translator'));
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'nexus-ai-wp-translator'));
        }
        
        if (!isset($_POST['config_data']) || !is_array($_POST['config_data'])) {
            wp_send_json_error(__('Invalid configuration data', 'nexus-ai-wp-translator'));
        }
        
        $config_data = $_POST['config_data'];
        
        // Validate configuration structure
        if (!isset($config_data['api_settings']) && !isset($config_data['language_settings'])) {
            wp_send_json_error(__('Invalid configuration format', 'nexus-ai-wp-translator'));
        }
        
        $imported_count = 0;
        $errors = array();
        
        try {
            // Import API settings (excluding API key for security)
            if (isset($config_data['api_settings'])) {
                $api_settings = $config_data['api_settings'];
                
                // Remove API key if present (security)
                unset($api_settings['claude_api_key']);
                
                // Validate and sanitize API settings
                $current_api_settings = get_option('nexus_translator_api_settings', array());
                
                // Merge with current settings, preserving API key
                $new_api_settings = array_merge($current_api_settings, $api_settings);
                
                if (update_option('nexus_translator_api_settings', $new_api_settings)) {
                    $imported_count++;
                } else {
                    $errors[] = 'Failed to import API settings';
                }
            }
            
            // Import language settings
            if (isset($config_data['language_settings'])) {
                $language_settings = $config_data['language_settings'];
                
                // Validate language codes
                $language_manager = new Language_Manager();
                $valid_languages = array_keys($language_manager->get_supported_languages());
                
                // Filter valid source language
                if (isset($language_settings['source_language'])) {
                    if (!in_array($language_settings['source_language'], $valid_languages)) {
                        unset($language_settings['source_language']);
                        $errors[] = 'Invalid source language removed';
                    }
                }
                
                // Filter valid target languages
                if (isset($language_settings['target_languages']) && is_array($language_settings['target_languages'])) {
                    $language_settings['target_languages'] = array_filter(
                        $language_settings['target_languages'],
                        function($lang) use ($valid_languages) {
                            return in_array($lang, $valid_languages);
                        }
                    );
                }
                
                if (update_option('nexus_translator_language_settings', $language_settings)) {
                    $imported_count++;
                } else {
                    $errors[] = 'Failed to import language settings';
                }
            }
            
            // Import general options
            if (isset($config_data['general_options'])) {
                $general_options = $config_data['general_options'];
                
                // Sanitize boolean options
                $boolean_options = array('debug_mode', 'cache_translations', 'show_language_switcher');
                foreach ($boolean_options as $option) {
                    if (isset($general_options[$option])) {
                        $general_options[$option] = (bool) $general_options[$option];
                    }
                }
                
                if (update_option('nexus_translator_options', $general_options)) {
                    $imported_count++;
                } else {
                    $errors[] = 'Failed to import general options';
                }
            }
            
            $message = sprintf(
                __('%d settings sections imported successfully', 'nexus-ai-wp-translator'),
                $imported_count
            );
            
            if (!empty($errors)) {
                $message .= '. ' . __('Warnings: ', 'nexus-ai-wp-translator') . implode(', ', $errors);
            }
            
            wp_send_json_success(array(
                'message' => $message,
                'imported_count' => $imported_count,
                'warnings' => $errors
            ));
            
        } catch (Exception $e) {
            error_log('Nexus Translator: Configuration import error: ' . $e->getMessage());
            wp_send_json_error(__('Import failed: ', 'nexus-ai-wp-translator') . $e->getMessage());
        }
    }
    
    /**
     * Handle get API status request
     */
    public function handle_get_api_status() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce')) {
            wp_die(__('Security check failed', 'nexus-ai-wp-translator'));
        }
        
        // Check capabilities
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'nexus-ai-wp-translator'));
        }
        
        $api = new Translator_API();
        
        $status = array(
            'api_configured' => $api->is_api_configured(),
            'emergency_stop' => get_option('nexus_translator_emergency_stop', false),
            'rate_limits' => $api->get_rate_limit_status(),
            'usage_stats' => $api->get_usage_stats(),
            'can_translate' => $api->can_make_request(),
            'configuration' => $api->get_configuration_summary(),
            'validation' => $api->validate_configuration()
        );
        
        wp_send_json_success($status);
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
        
        // Check if translation is allowed (rate limits, cooldowns, etc.)
        $api = new Translator_API();
        $translation_check = $api->can_translate_post($original_post_id);
        
        if (!$translation_check['can_translate']) {
            wp_send_json_error($translation_check['reason']);
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
        
        // Log the update
        $this->log_translation_usage($original_post_id, $target_language, array(
            'success' => true,
            'translated_post_id' => $translated_post_id,
            'usage' => $translation_result['usage']
        ));
        
        wp_send_json_success(array(
            'message' => __('Translation updated successfully!', 'nexus-ai-wp-translator'),
            'edit_link' => get_edit_post_link($translated_post_id),
            'view_link' => get_permalink($translated_post_id),
            'usage' => $translation_result['usage'],
            'rate_status' => $api->get_rate_limit_status()
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
    
    /**
     * Set translation lock to prevent concurrent translations
     */
    private function set_translation_lock($post_id) {
        $active_translations = get_option('nexus_translator_active_translations', array());
        $lock_key = "post_$post_id";
        $active_translations[$lock_key] = current_time('timestamp');
        update_option('nexus_translator_active_translations', $active_translations);
        
        error_log("Nexus Translator: Translation lock set for post $post_id");
    }
    
    /**
     * Clear translation lock
     */
    private function clear_translation_lock($post_id) {
        $active_translations = get_option('nexus_translator_active_translations', array());
        $lock_key = "post_$post_id";
        unset($active_translations[$lock_key]);
        update_option('nexus_translator_active_translations', $active_translations);
        
        error_log("Nexus Translator: Translation lock cleared for post $post_id");
    }
    
    /**
     * Log translation usage for analytics and monitoring
     */
    private function log_translation_usage($post_id, $target_language, $result) {
        $usage_log = get_option('nexus_translator_usage_log', array());
        
        $log_entry = array(
            'timestamp' => current_time('timestamp'),
            'date' => current_time('Y-m-d H:i:s'),
            'post_id' => $post_id,
            'target_language' => $target_language,
            'success' => $result['success'],
            'user_id' => get_current_user_id(),
            'user_login' => wp_get_current_user()->user_login
        );
        
        // Add success-specific data
        if ($result['success']) {
            $log_entry['translated_post_id'] = $result['translated_post_id'];
            if (isset($result['usage'])) {
                $log_entry['tokens_input'] = $result['usage']['input_tokens'] ?? 0;
                $log_entry['tokens_output'] = $result['usage']['output_tokens'] ?? 0;
            }
        } else {
            $log_entry['error'] = $result['error'] ?? 'Unknown error';
        }
        
        $usage_log[] = $log_entry;
        
        // Keep only last 200 entries to prevent database bloat
        if (count($usage_log) > 200) {
            $usage_log = array_slice($usage_log, -200);
        }
        
        update_option('nexus_translator_usage_log', $usage_log);
        
        // Update daily counters
        $today = current_time('Y-m-d');
        $daily_stats = get_option('nexus_translator_daily_stats', array());
        
        if (!isset($daily_stats[$today])) {
            $daily_stats[$today] = array(
                'total_requests' => 0,
                'successful_translations' => 0,
                'failed_translations' => 0,
                'total_tokens' => 0
            );
        }
        
        $daily_stats[$today]['total_requests']++;
        
        if ($result['success']) {
            $daily_stats[$today]['successful_translations']++;
            if (isset($result['usage'])) {
                $daily_stats[$today]['total_tokens'] += ($result['usage']['input_tokens'] ?? 0) + ($result['usage']['output_tokens'] ?? 0);
            }
        } else {
            $daily_stats[$today]['failed_translations']++;
        }
        
        // Keep only last 30 days of stats
        $cutoff_date = date('Y-m-d', strtotime('-30 days'));
        foreach ($daily_stats as $date => $stats) {
            if ($date < $cutoff_date) {
                unset($daily_stats[$date]);
            }
        }
        
        update_option('nexus_translator_daily_stats', $daily_stats);
        
        error_log("Nexus Translator: Usage logged - Post: $post_id, Language: $target_language, Success: " . ($result['success'] ? 'Yes' : 'No'));
    }
    
    /**
     * Clean up old translation locks (called via cron or manually)
     */
    public function cleanup_old_translation_locks() {
        $active_translations = get_option('nexus_translator_active_translations', array());
        $current_time = current_time('timestamp');
        $lock_timeout = 600; // 10 minutes timeout
        $cleaned = 0;
        
        foreach ($active_translations as $lock_key => $lock_time) {
            if (($current_time - $lock_time) > $lock_timeout) {
                unset($active_translations[$lock_key]);
                $cleaned++;
            }
        }
        
        if ($cleaned > 0) {
            update_option('nexus_translator_active_translations', $active_translations);
            error_log("Nexus Translator: Cleaned up $cleaned old translation locks");
        }
        
        return $cleaned;
    }
    
    /**
     * Get translation analytics data
     */
    public function get_translation_analytics($days = 7) {
        $daily_stats = get_option('nexus_translator_daily_stats', array());
        $usage_log = get_option('nexus_translator_usage_log', array());
        
        // Calculate date range
        $end_date = current_time('Y-m-d');
        $start_date = date('Y-m-d', strtotime("-$days days"));
        
        $analytics = array(
            'date_range' => array(
                'start' => $start_date,
                'end' => $end_date,
                'days' => $days
            ),
            'totals' => array(
                'requests' => 0,
                'successful' => 0,
                'failed' => 0,
                'tokens' => 0
            ),
            'daily_breakdown' => array(),
            'language_breakdown' => array(),
            'user_breakdown' => array(),
            'recent_activity' => array()
        );
        
        // Process daily stats
        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("$start_date +$i days"));
            $stats = $daily_stats[$date] ?? array(
                'total_requests' => 0,
                'successful_translations' => 0,
                'failed_translations' => 0,
                'total_tokens' => 0
            );
            
            $analytics['daily_breakdown'][$date] = $stats;
            $analytics['totals']['requests'] += $stats['total_requests'];
            $analytics['totals']['successful'] += $stats['successful_translations'];
            $analytics['totals']['failed'] += $stats['failed_translations'];
            $analytics['totals']['tokens'] += $stats['total_tokens'];
        }
        
        // Process usage log for detailed breakdowns
        $cutoff_timestamp = strtotime($start_date);
        foreach ($usage_log as $entry) {
            if ($entry['timestamp'] >= $cutoff_timestamp) {
                // Language breakdown
                $lang = $entry['target_language'];
                if (!isset($analytics['language_breakdown'][$lang])) {
                    $analytics['language_breakdown'][$lang] = array(
                        'total' => 0,
                        'successful' => 0,
                        'failed' => 0
                    );
                }
                $analytics['language_breakdown'][$lang]['total']++;
                if ($entry['success']) {
                    $analytics['language_breakdown'][$lang]['successful']++;
                } else {
                    $analytics['language_breakdown'][$lang]['failed']++;
                }
                
                // User breakdown
                $user = $entry['user_login'] ?? 'Unknown';
                if (!isset($analytics['user_breakdown'][$user])) {
                    $analytics['user_breakdown'][$user] = array(
                        'total' => 0,
                        'successful' => 0,
                        'failed' => 0
                    );
                }
                $analytics['user_breakdown'][$user]['total']++;
                if ($entry['success']) {
                    $analytics['user_breakdown'][$user]['successful']++;
                } else {
                    $analytics['user_breakdown'][$user]['failed']++;
                }
            }
        }
        
        // Recent activity (last 10 entries)
        $analytics['recent_activity'] = array_slice(array_reverse($usage_log), 0, 10);
        
        return $analytics;
    }
    
    /**
     * Handle analytics request
     */
    public function handle_get_analytics() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce')) {
            wp_die(__('Security check failed', 'nexus-ai-wp-translator'));
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'nexus-ai-wp-translator'));
        }
        
        $days = isset($_POST['days']) ? (int) $_POST['days'] : 7;
        $days = max(1, min(90, $days)); // Limit between 1 and 90 days
        
        $analytics = $this->get_translation_analytics($days);
        
        wp_send_json_success($analytics);
    }
    
    /**
     * Handle cleanup request
     */
    public function handle_cleanup_locks() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce')) {
            wp_die(__('Security check failed', 'nexus-ai-wp-translator'));
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'nexus-ai-wp-translator'));
        }
        
        $cleaned = $this->cleanup_old_translation_locks();
        
        wp_send_json_success(array(
            'message' => sprintf(__('Cleaned up %d old translation locks', 'nexus-ai-wp-translator'), $cleaned),
            'cleaned_count' => $cleaned
        ));
    }
    
    /**
     * Handle bulk translation request
     */
    public function handle_bulk_translate() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce')) {
            wp_die(__('Security check failed', 'nexus-ai-wp-translator'));
        }
        
        // Check capabilities
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'nexus-ai-wp-translator'));
        }
        
        $post_ids = isset($_POST['post_ids']) ? array_map('intval', $_POST['post_ids']) : array();
        $target_languages = isset($_POST['target_languages']) ? array_map('sanitize_text_field', $_POST['target_languages']) : array();
        
        if (empty($post_ids) || empty($target_languages)) {
            wp_send_json_error(__('Invalid parameters', 'nexus-ai-wp-translator'));
        }
        
        // Limit bulk operations
        if (count($post_ids) > 10) {
            wp_send_json_error(__('Too many posts selected. Maximum 10 posts per batch.', 'nexus-ai-wp-translator'));
        }
        
        $api = new Translator_API();
        $translator = new Nexus_Translator();
        $results = array();
        $total_operations = count($post_ids) * count($target_languages);
        $completed = 0;
        $failed = 0;
        
        foreach ($post_ids as $post_id) {
            // Verify post exists and user can edit it
            $post = get_post($post_id);
            if (!$post || !current_user_can('edit_post', $post_id)) {
                $failed++;
                continue;
            }
            
            foreach ($target_languages as $target_language) {
                // Check if translation is allowed
                $translation_check = $api->can_translate_post($post_id);
                if (!$translation_check['can_translate']) {
                    $results[] = array(
                        'post_id' => $post_id,
                        'language' => $target_language,
                        'success' => false,
                        'error' => $translation_check['reason']
                    );
                    $failed++;
                    continue;
                }
                
                // Perform translation
                $result = $translator->translate_post($post_id, $target_language);
                
                $results[] = array(
                    'post_id' => $post_id,
                    'language' => $target_language,
                    'success' => $result['success'],
                    'translated_post_id' => $result['success'] ? $result['translated_post_id'] : null,
                    'error' => $result['success'] ? null : $result['error']
                );
                
                if ($result['success']) {
                    $completed++;
                    $this->log_translation_usage($post_id, $target_language, $result);
                } else {
                    $failed++;
                }
                
                // Small delay between requests to respect rate limits
                usleep(500000); // 0.5 seconds
            }
        }
        
        wp_send_json_success(array(
            'message' => sprintf(
                __('Bulk translation completed: %d successful, %d failed out of %d total operations', 'nexus-ai-wp-translator'),
                $completed,
                $failed,
                $total_operations
            ),
            'completed' => $completed,
            'failed' => $failed,
            'total' => $total_operations,
            'results' => $results,
            'rate_status' => $api->get_rate_limit_status()
        ));
    }
}