<?php
/**
 * File: class-ajax-translation.php
 * Location: /includes/ajax/class-ajax-translation.php
 * 
 * AJAX Translation Handler - Complete translation functionality
 * Extends Ajax_Base for security and protection features
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once NEXUS_TRANSLATOR_INCLUDES_DIR . 'ajax/class-ajax-base.php';

class Ajax_Translation extends Ajax_Base {
    
    /**
     * Initialize translation-specific hooks
     */
    protected function init_hooks() {
        // Core translation handlers
        add_action('wp_ajax_nexus_translate_post', array($this, 'handle_translate_post'));
        add_action('wp_ajax_nexus_update_translation', array($this, 'handle_update_translation'));
        add_action('wp_ajax_nexus_get_translation_status', array($this, 'handle_get_translation_status'));
        add_action('wp_ajax_nexus_delete_translation', array($this, 'handle_delete_translation'));
        
        // Additional translation utilities
        add_action('wp_ajax_nexus_test_api_connection', array($this, 'handle_test_api_connection'));
        add_action('wp_ajax_nexus_set_post_language', array($this, 'handle_set_post_language'));
        add_action('wp_ajax_nexus_get_post_translations', array($this, 'handle_get_post_translations'));
        add_action('wp_ajax_nexus_bulk_translate', array($this, 'handle_bulk_translate'));
        
        error_log('Nexus AJAX Translation: All handlers registered');
    }
    
    /**
     * 🔒 MAIN HANDLER: Translate post with complete protection
     */
    public function handle_translate_post() {
        error_log('Nexus Translation AJAX: Translation request received');
        
        // Security validation
        $this->validate_ajax_request('edit_posts');
        
        // Get and validate parameters
        $post_id = (int) $_POST['post_id'];
        $target_language = sanitize_text_field($_POST['target_language']);
        
        if (!$post_id || !$target_language) {
            $this->send_error('Invalid parameters provided', 'INVALID_PARAMS');
        }
        
        // Validate post
        $post = $this->validate_post_id($post_id, 'edit_post');
        
        // 🔒 PROTECTION: Prevent simultaneous requests
        $request_key = "translate_{$post_id}_{$target_language}_" . get_current_user_id();
        $this->check_duplicate_request($request_key);
        
        try {
            // Check if translation is allowed
            $api = new Translator_API();
            $translation_check = $api->can_translate_post($post_id);
            
            if (!$translation_check['can_translate']) {
                $this->send_error($translation_check['reason'], 'TRANSLATION_NOT_ALLOWED');
            }
            
            // Check if translation already exists
            $post_linker = new Post_Linker();
            $existing_translation = $post_linker->get_translation($post_id, $target_language);
            
            if ($existing_translation) {
                $this->send_error('Translation already exists for this language', 'TRANSLATION_EXISTS', array(
                    'existing_id' => $existing_translation,
                    'edit_link' => get_edit_post_link($existing_translation)
                ));
            }
            
            // Perform translation
            $result = $this->perform_translation($post, $target_language, $post_linker);
            
            if ($result['success']) {
                $this->log_usage('translate_post', array(
                    'post_id' => $post_id,
                    'target_language' => $target_language,
                    'translated_post_id' => $result['translated_post_id'],
                    'success' => true,
                    'usage' => $result['usage']
                ));
                
                $this->send_success(array(
                    'translated_post_id' => $result['translated_post_id'],
                    'message' => 'Translation completed successfully!',
                    'edit_link' => get_edit_post_link($result['translated_post_id']),
                    'view_link' => get_permalink($result['translated_post_id']),
                    'usage' => $result['usage'],
                    'rate_status' => $api->get_rate_limit_status()
                ), 'Translation completed successfully!');
            } else {
                $this->log_usage('translate_post', array(
                    'post_id' => $post_id,
                    'target_language' => $target_language,
                    'success' => false,
                    'error' => $result['error']
                ));
                
                $this->send_error($result['error'], 'TRANSLATION_FAILED');
            }
            
        } catch (Exception $e) {
            error_log('Nexus Translation AJAX: Exception: ' . $e->getMessage());
            $this->send_error('Translation failed: ' . $e->getMessage(), 'EXCEPTION');
        } finally {
            $this->cleanup_request($request_key);
        }
    }
    
    /**
     * Handle update existing translation
     */
    public function handle_update_translation() {
        $this->validate_ajax_request('edit_posts');
        
        $original_post_id = (int) $_POST['original_post_id'];
        $translated_post_id = (int) $_POST['translated_post_id'];
        
        if (!$original_post_id || !$translated_post_id) {
            $this->send_error('Invalid parameters', 'INVALID_PARAMS');
        }
        
        // Validate both posts
        $this->validate_post_id($original_post_id, 'edit_post');
        $this->validate_post_id($translated_post_id, 'edit_post');
        
        // 🔒 PROTECTION: Prevent simultaneous updates
        $request_key = "update_{$original_post_id}_{$translated_post_id}_" . get_current_user_id();
        $this->check_duplicate_request($request_key);
        
        try {
            // Check if update is allowed
            $api = new Translator_API();
            $translation_check = $api->can_translate_post($original_post_id);
            
            if (!$translation_check['can_translate']) {
                $this->send_error($translation_check['reason'], 'TRANSLATION_NOT_ALLOWED');
            }
            
            $post_linker = new Post_Linker();
            $target_language = $post_linker->get_post_language($translated_post_id);
            
            if (!$target_language) {
                $this->send_error('Could not determine target language', 'LANGUAGE_NOT_FOUND');
            }
            
            // Update translation status to pending
            $post_linker->update_translation_status($translated_post_id, Post_Linker::STATUS_PENDING);
            
            // Re-translate
            $result = $this->perform_update_translation($original_post_id, $translated_post_id, $target_language);
            
            if ($result['success']) {
                $post_linker->update_translation_status($translated_post_id, Post_Linker::STATUS_COMPLETED);
                
                $this->log_usage('update_translation', array(
                    'original_post_id' => $original_post_id,
                    'translated_post_id' => $translated_post_id,
                    'target_language' => $target_language,
                    'success' => true
                ));
                
                $this->send_success(array(
                    'edit_link' => get_edit_post_link($translated_post_id),
                    'view_link' => get_permalink($translated_post_id),
                    'usage' => $result['usage'],
                    'rate_status' => $api->get_rate_limit_status()
                ), 'Translation updated successfully!');
            } else {
                $post_linker->update_translation_status($translated_post_id, Post_Linker::STATUS_ERROR);
                $this->send_error($result['error'], 'UPDATE_FAILED');
            }
            
        } finally {
            $this->cleanup_request($request_key);
        }
    }
    
    /**
     * Handle get translation status
     */
    public function handle_get_translation_status() {
        $this->validate_ajax_request('edit_posts');
        
        $post_id = (int) $_POST['post_id'];
        $this->validate_post_id($post_id, 'edit_post');
        
        $post_linker = new Post_Linker();
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
        
        $this->send_success(array(
            'current_language' => $current_language,
            'translations' => $translation_data
        ));
    }
    
    /**
     * Handle delete translation
     */
    public function handle_delete_translation() {
        $this->validate_ajax_request('delete_posts');
        
        $post_id = (int) $_POST['post_id'];
        $this->validate_post_id($post_id, 'delete_post');
        
        $post_linker = new Post_Linker();
        $post_linker->delete_translation_relationship($post_id);
        
        $result = wp_trash_post($post_id);
        
        if ($result) {
            $this->log_usage('delete_translation', array(
                'post_id' => $post_id,
                'success' => true
            ));
            
            $this->send_success(array(), 'Translation deleted successfully');
        } else {
            $this->send_error('Failed to delete translation', 'DELETE_FAILED');
        }
    }
    
    /**
     * Handle API connection test
     */
    public function handle_test_api_connection() {
        $this->validate_ajax_request('manage_options');
        
        $api = new Translator_API();
        $test_result = $api->test_connection();
        
        if ($test_result['success']) {
            $this->send_success($test_result['data'], 'API connection successful');
        } else {
            $this->send_error($test_result['error'], 'API_CONNECTION_FAILED');
        }
    }
    
    /**
     * Handle set post language
     */
    public function handle_set_post_language() {
        $this->validate_ajax_request('edit_posts');
        
        $post_id = (int) $_POST['post_id'];
        $language = sanitize_text_field($_POST['language']);
        
        if (!$post_id || !$language) {
            $this->send_error('Invalid parameters', 'INVALID_PARAMS');
        }
        
        $this->validate_post_id($post_id, 'edit_post');
        
        $post_linker = new Post_Linker();
        $result = $post_linker->set_post_language($post_id, $language);
        
        if ($result) {
            $this->log_usage('set_language', array(
                'post_id' => $post_id,
                'language' => $language
            ));
            
            $this->send_success(array(
                'language' => $language
            ), 'Language set successfully');
        } else {
            $this->send_error('Failed to set language', 'SET_LANGUAGE_FAILED');
        }
    }
    
    /**
     * Handle get post translations
     */
    public function handle_get_post_translations() {
        $this->validate_ajax_request('edit_posts');
        
        $post_id = (int) $_POST['post_id'];
        $this->validate_post_id($post_id, 'edit_post');
        
        $post_linker = new Post_Linker();
        $translations = $post_linker->get_all_translations($post_id);
        $current_language = $post_linker->get_post_language($post_id);
        
        // Get language manager for available languages
        $language_manager = new Language_Manager();
        $available_languages = $language_manager->get_target_languages();
        
        $translation_info = array();
        foreach ($available_languages as $lang_code => $lang_name) {
            if (isset($translations[$lang_code])) {
                $translated_post = get_post($translations[$lang_code]);
                $translation_info[$lang_code] = array(
                    'exists' => true,
                    'post_id' => $translations[$lang_code],
                    'title' => $translated_post ? $translated_post->post_title : '',
                    'status' => $post_linker->get_translation_status($translations[$lang_code]),
                    'edit_link' => get_edit_post_link($translations[$lang_code]),
                    'view_link' => get_permalink($translations[$lang_code])
                );
            } else {
                $translation_info[$lang_code] = array(
                    'exists' => false,
                    'language_name' => $lang_name
                );
            }
        }
        
        $this->send_success(array(
            'current_language' => $current_language,
            'translations' => $translation_info
        ));
    }
    
    /**
     * Handle bulk translation request
     */
    public function handle_bulk_translate() {
        $this->validate_ajax_request('edit_posts');
        
        $post_ids = isset($_POST['post_ids']) ? array_map('intval', $_POST['post_ids']) : array();
        $target_language = sanitize_text_field($_POST['target_language']);
        
        if (empty($post_ids) || !$target_language) {
            $this->send_error('Invalid parameters', 'INVALID_PARAMS');
        }
        
        if (count($post_ids) > 50) {
            $this->send_error('Too many posts selected (max 50)', 'TOO_MANY_POSTS');
        }
        
        // Start bulk translation process
        $batch_id = uniqid('bulk_', true);
        $batch_data = array(
            'batch_id' => $batch_id,
            'post_ids' => $post_ids,
            'target_language' => $target_language,
            'status' => 'pending',
            'total' => count($post_ids),
            'completed' => 0,
            'failed' => 0,
            'started_at' => current_time('mysql'),
            'user_id' => get_current_user_id()
        );
        
        update_option('nexus_bulk_translation_' . $batch_id, $batch_data, false);
        
        // Schedule the bulk translation processing
        wp_schedule_single_event(time() + 10, 'nexus_process_bulk_translation', array($batch_id));
        
        $this->log_usage('bulk_translate_started', array(
            'batch_id' => $batch_id,
            'post_count' => count($post_ids),
            'target_language' => $target_language
        ));
        
        $this->send_success(array(
            'batch_id' => $batch_id,
            'total_posts' => count($post_ids),
            'estimated_time' => count($post_ids) * 30 // 30 seconds per post estimate
        ), 'Bulk translation started');
    }
    
    /**
     * Perform the actual translation
     */
    private function perform_translation($post, $target_language, $post_linker) {
        // Prepare content for translation
        $content_to_translate = $this->prepare_content_for_translation($post);
        
        // Get source language
        $source_language = $post_linker->get_post_language($post->ID);
        if (!$source_language) {
            $settings = get_option('nexus_translator_language_settings', array());
            $source_language = $settings['source_language'] ?? 'en';
        }
        
        // Call translation API
        $api = new Translator_API();
        $translation_result = $api->translate_content(
            $content_to_translate,
            $source_language,
            $target_language,
            'post'
        );
        
        if (!$translation_result['success']) {
            return array(
                'success' => false,
                'error' => $translation_result['error']
            );
        }
        
        // Create translated post
        $translated_content = $translation_result['data'];
        $translated_post_data = array(
            'post_title' => $translated_content['title'],
            'post_content' => $translated_content['content'],
            'post_excerpt' => $translated_content['excerpt'],
            'post_status' => 'draft', // Always start as draft
            'post_type' => $post->post_type,
            'post_author' => $post->post_author,
            'post_parent' => $post->post_parent,
            'menu_order' => $post->menu_order,
            'comment_status' => $post->comment_status,
            'ping_status' => $post->ping_status
        );
        
        $translated_post_id = wp_insert_post($translated_post_data, true);
        
        if (is_wp_error($translated_post_id)) {
            return array(
                'success' => false,
                'error' => 'Failed to create translated post: ' . $translated_post_id->get_error_message()
            );
        }
        
        // Create translation relationship
        $post_linker->create_translation_link($post->ID, $translated_post_id, $target_language);
        
        // Copy post meta (excluding translation-specific meta)
        $this->copy_post_meta($post->ID, $translated_post_id);
        
        // Copy taxonomies
        $this->copy_post_taxonomies($post->ID, $translated_post_id);
        
        return array(
            'success' => true,
            'translated_post_id' => $translated_post_id,
            'usage' => $translation_result['usage']
        );
    }
    
    /**
     * Perform update translation
     */
    private function perform_update_translation($original_post_id, $translated_post_id, $target_language) {
        $original_post = get_post($original_post_id);
        $post_linker = new Post_Linker();
        
        // Prepare content for translation
        $content_to_translate = $this->prepare_content_for_translation($original_post);
        
        // Get source language
        $source_language = $post_linker->get_post_language($original_post_id);
        if (!$source_language) {
            $settings = get_option('nexus_translator_language_settings', array());
            $source_language = $settings['source_language'] ?? 'en';
        }
        
        // Call translation API
        $api = new Translator_API();
        $translation_result = $api->translate_content(
            $content_to_translate,
            $source_language,
            $target_language,
            'post'
        );
        
        if (!$translation_result['success']) {
            return array(
                'success' => false,
                'error' => $translation_result['error']
            );
        }
        
        // Update translated post
        $translated_content = $translation_result['data'];
        $update_data = array(
            'ID' => $translated_post_id,
            'post_title' => $translated_content['title'],
            'post_content' => $translated_content['content'],
            'post_excerpt' => $translated_content['excerpt'],
            'post_modified' => current_time('mysql'),
            'post_modified_gmt' => current_time('mysql', 1)
        );
        
        $result = wp_update_post($update_data, true);
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'error' => 'Failed to update post: ' . $result->get_error_message()
            );
        }
        
        return array(
            'success' => true,
            'usage' => $translation_result['usage']
        );
    }
    
    /**
     * Prepare content for translation
     */
    private function prepare_content_for_translation($post) {
        return array(
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'post_type' => $post->post_type
        );
    }
    
    /**
     * Copy post meta (excluding translation-specific meta)
     */
    private function copy_post_meta($source_post_id, $target_post_id) {
        $meta_data = get_post_meta($source_post_id);
        
        // Meta keys to exclude from copying
        $excluded_meta = array(
            '_nexus_translation_of',
            '_nexus_language',
            '_nexus_translation_status',
            '_nexus_has_translation_',
            '_edit_lock',
            '_edit_last'
        );
        
        foreach ($meta_data as $meta_key => $meta_values) {
            // Skip excluded meta
            $skip = false;
            foreach ($excluded_meta as $excluded) {
                if (strpos($meta_key, $excluded) === 0) {
                    $skip = true;
                    break;
                }
            }
            
            if (!$skip) {
                foreach ($meta_values as $meta_value) {
                    add_post_meta($target_post_id, $meta_key, maybe_unserialize($meta_value));
                }
            }
        }
    }
    
    /**
     * Copy post taxonomies
     */
    private function copy_post_taxonomies($source_post_id, $target_post_id) {
        $taxonomies = get_post_taxonomies($source_post_id);
        
        foreach ($taxonomies as $taxonomy) {
            $terms = get_the_terms($source_post_id, $taxonomy);
            if ($terms && !is_wp_error($terms)) {
                $term_ids = wp_list_pluck($terms, 'term_id');
                wp_set_post_terms($target_post_id, $term_ids, $taxonomy);
            }
        }
    }
}