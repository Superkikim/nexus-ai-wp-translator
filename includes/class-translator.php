<?php
/**
 * File: class-translator.php
 * Location: /includes/class-translator.php
 * 
 * Core Translation Engine Class
 * Responsible for: Main translation workflow, post creation, component coordination
 */

namespace Nexus\Translator;

use Nexus\Translator\Abstracts\Abstract_Module;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Core translation engine class
 * 
 * Coordinates translation workflow and delegates to specialized handlers.
 * Keeps main class focused on orchestration and core operations.
 */
class Translator extends Abstract_Module {
    
    private $api;
    private $languages;
    private $content_processor;
    private $batch_handler;
    private $metadata_manager;
    
    protected function get_module_name() {
        return 'translator';
    }
    
    protected function module_init() {
        // Get component instances
        $main = \Nexus\Translator\Main::get_instance();
        $this->api = $main->get_component('api');
        $this->languages = $main->get_component('languages');
        
        // Load helper classes
        $this->load_helper_classes();
        
        // Initialize helper components
        $this->init_helper_components();
    }
    
    protected function register_hooks() {
        // Core translation hooks
        $this->add_hook('wp_loaded', array($this, 'wp_loaded'));
        $this->add_hook('save_post', array($this, 'handle_auto_translate'), 20, 2);
        
        // Post management hooks
        $this->add_hook('before_delete_post', array($this, 'handle_post_deletion'));
        $this->add_hook('wp_trash_post', array($this, 'handle_post_trash'));
        $this->add_hook('untrash_post', array($this, 'handle_post_untrash'));
        
        // Admin hooks
        $this->add_hook('admin_init', array($this, 'admin_init'));
        $this->add_hook('add_meta_boxes', array($this, 'add_translation_meta_boxes'));
        
        // Let helper classes register their hooks
        if ($this->batch_handler) {
            $this->batch_handler->register_hooks();
        }
        
        if ($this->metadata_manager) {
            $this->metadata_manager->register_hooks();
        }
    }
    
    /**
     * Translate a post to target language
     * 
     * @param int $post_id Source post ID
     * @param string $target_language Target language code
     * @param array $options Translation options
     * @return array Translation result
     */
    public function translate_post($post_id, $target_language, $options = array()) {
        $start_time = microtime(true);
        
        // Validate inputs
        $validation = $this->validate_translation_request($post_id, $target_language);
        if (!$validation['valid']) {
            return $this->create_error_response('Validation failed', 'validation_error', $validation['errors']);
        }
        
        $source_post = get_post($post_id);
        $source_language = $this->get_post_language($post_id);
        
        // Check if translation already exists
        $existing_translation = $this->get_existing_translation($post_id, $target_language);
        if ($existing_translation && !($options['force_retranslate'] ?? false)) {
            return $this->create_success_response(array(
                'translated_post_id' => $existing_translation,
                'status' => 'already_exists'
            ), __('Translation already exists', 'nexus-ai-wp-translator'));
        }
        
        // Fire before translation hook
        do_action('nexus_before_translate', $post_id, $source_language, $target_language, $options);
        
        try {
            // Prepare content for translation
            $content_data = $this->content_processor->prepare_content($source_post, $options);
            
            // Translate content
            $translation_result = $this->translate_content($content_data, $source_language, $target_language, $options);
            
            if (!$translation_result['success']) {
                throw new \Exception($translation_result['message']);
            }
            
            // Create translated post
            $translated_post_id = $this->create_translated_post($source_post, $translation_result['data'], $target_language, $options);
            
            if (!$translated_post_id) {
                throw new \Exception(__('Failed to create translated post', 'nexus-ai-wp-translator'));
            }
            
            // Set up relationships and metadata
            $this->metadata_manager->setup_translation_relationship($post_id, $translated_post_id, $source_language, $target_language);
            $this->metadata_manager->copy_post_data($post_id, $translated_post_id, $options);
            
            $execution_time = microtime(true) - $start_time;
            
            // Fire after translation hook
            do_action('nexus_after_translate', $translated_post_id, $post_id, $source_language, $target_language, array(
                'execution_time' => $execution_time,
                'word_count' => str_word_count($source_post->post_content),
                'character_count' => strlen($source_post->post_content)
            ));
            
            // Fire analytics event
            do_action('nexus_analytics_event', 'translation_completed', array(
                'source_post_id' => $post_id,
                'translated_post_id' => $translated_post_id,
                'source_language' => $source_language,
                'target_language' => $target_language,
                'execution_time' => $execution_time
            ));
            
            return $this->create_success_response(array(
                'translated_post_id' => $translated_post_id,
                'source_post_id' => $post_id,
                'source_language' => $source_language,
                'target_language' => $target_language,
                'execution_time' => $execution_time,
                'status' => 'completed'
            ), __('Translation completed successfully', 'nexus-ai-wp-translator'));
            
        } catch (\Exception $e) {
            // Fire analytics event for failure
            do_action('nexus_analytics_event', 'translation_failed', array(
                'source_post_id' => $post_id,
                'source_language' => $source_language,
                'target_language' => $target_language,
                'error_message' => $e->getMessage()
            ));
            
            return $this->create_error_response($e->getMessage(), 'translation_failed');
        }
    }
    
    /**
     * Translate multiple posts in batch
     * 
     * @param array $post_ids Array of post IDs
     * @param array $target_languages Array of target language codes
     * @param array $options Batch options
     * @return array Batch translation results
     */
    public function translate_batch($post_ids, $target_languages, $options = array()) {
        if (!$this->batch_handler) {
            return $this->create_error_response(__('Batch handler not available', 'nexus-ai-wp-translator'));
        }
        
        return $this->batch_handler->process_batch($post_ids, $target_languages, $options);
    }
    
    /**
     * Get all translations of a post
     * 
     * @param int $post_id Post ID
     * @return array Array of translation data
     */
    public function get_post_translations($post_id) {
        if (!$this->metadata_manager) {
            return array();
        }
        
        return $this->metadata_manager->get_post_translations($post_id);
    }
    
    /**
     * Get source post for a translation
     * 
     * @param int $translated_post_id Translated post ID
     * @return int|false Source post ID or false if not found
     */
    public function get_source_post($translated_post_id) {
        return get_post_meta($translated_post_id, '_nexus_source_post_id', true) ?: false;
    }
    
    /**
     * Check if post is a translation
     * 
     * @param int $post_id Post ID
     * @return bool True if post is a translation
     */
    public function is_translation($post_id) {
        return !empty(get_post_meta($post_id, '_nexus_source_post_id', true));
    }
    
    /**
     * Get post language
     * 
     * @param int $post_id Post ID
     * @return string Language code
     */
    public function get_post_language($post_id) {
        $language = get_post_meta($post_id, '_nexus_language', true);
        
        if (empty($language)) {
            $settings = get_option('nexus_ai_translator_settings', array());
            $language = $settings['source_language'] ?? 'en';
            update_post_meta($post_id, '_nexus_language', $language);
        }
        
        return $language;
    }
    
    /**
     * Get translation workflow status
     * 
     * @param int $post_id Post ID
     * @return array Workflow status
     */
    public function get_translation_workflow_status($post_id) {
        $status = array(
            'is_source' => !$this->is_translation($post_id),
            'is_translation' => $this->is_translation($post_id),
            'source_post_id' => null,
            'source_language' => $this->get_post_language($post_id),
            'translations' => array(),
            'available_targets' => array(),
            'can_translate' => false
        );
        
        if ($status['is_translation']) {
            $status['source_post_id'] = $this->get_source_post($post_id);
        } else {
            $status['translations'] = $this->get_post_translations($post_id);
        }
        
        // Get available target languages
        $settings = get_option('nexus_ai_translator_settings', array());
        $target_languages = $settings['target_languages'] ?? array();
        
        foreach ($target_languages as $language_code) {
            if ($language_code === $status['source_language']) {
                continue;
            }
            
            $is_translated = isset($status['translations'][$language_code]);
            
            if (!$is_translated) {
                $language_info = $this->languages ? $this->languages->get_language($language_code) : null;
                
                $status['available_targets'][] = array(
                    'code' => $language_code,
                    'name' => $language_info['name'] ?? $language_code,
                    'can_translate' => true
                );
            }
        }
        
        $status['can_translate'] = !empty($status['available_targets']) && !empty($settings['api_key']);
        
        return $status;
    }
    
    public function wp_loaded() {
        $supported_post_types = apply_filters('nexus_supported_post_types', array('post', 'page'));
        
        foreach ($supported_post_types as $post_type) {
            add_post_type_support($post_type, 'nexus-translation');
        }
    }
    
    public function admin_init() {
        register_post_status('nexus-translating', array(
            'label' => __('Translating', 'nexus-ai-wp-translator'),
            'public' => false,
            'exclude_from_search' => true,
            'show_in_admin_all_list' => false,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop(
                'Translating <span class="count">(%s)</span>',
                'Translating <span class="count">(%s)</span>',
                'nexus-ai-wp-translator'
            ),
        ));
    }
    
    public function add_translation_meta_boxes() {
        $supported_post_types = apply_filters('nexus_supported_post_types', array('post', 'page'));
        
        foreach ($supported_post_types as $post_type) {
            add_meta_box(
                'nexus-translation-metabox',
                __('AI Translation', 'nexus-ai-wp-translator'),
                array($this, 'render_translation_metabox'),
                $post_type,
                'side',
                'default'
            );
        }
    }
    
    public function render_translation_metabox($post) {
        if (!$this->languages) {
            echo '<p>' . __('Language module not available.', 'nexus-ai-wp-translator') . '</p>';
            return;
        }
        
        $workflow_status = $this->get_translation_workflow_status($post->ID);
        
        wp_nonce_field('nexus_translation_metabox', 'nexus_translation_nonce');
        
        echo '<div class="nexus-translation-metabox">';
        
        // Source language display
        $source_info = $this->languages->get_language($workflow_status['source_language']);
        echo '<p><strong>' . __('Source Language:', 'nexus-ai-wp-translator') . '</strong> ';
        echo esc_html($source_info['name'] ?? $workflow_status['source_language']) . '</p>';
        
        // Existing translations
        if (!empty($workflow_status['translations'])) {
            echo '<h4>' . __('Existing Translations', 'nexus-ai-wp-translator') . '</h4>';
            echo '<ul class="nexus-existing-translations">';
            
            foreach ($workflow_status['translations'] as $translation) {
                echo '<li>';
                echo '<strong>' . esc_html($translation['language_name']) . '</strong><br>';
                echo '<a href="' . esc_url($translation['edit_link']) . '">' . esc_html($translation['post_title']) . '</a>';
                echo ' <small>(' . esc_html($translation['post_status']) . ')</small>';
                echo '</li>';
            }
            
            echo '</ul>';
        }
        
        // Available target languages
        if (!empty($workflow_status['available_targets'])) {
            echo '<h4>' . __('Translate To', 'nexus-ai-wp-translator') . '</h4>';
            
            foreach ($workflow_status['available_targets'] as $target) {
                echo '<p>';
                echo '<label>';
                echo '<input type="checkbox" name="nexus_translate_to[]" value="' . esc_attr($target['code']) . '"> ';
                echo esc_html($target['name']);
                echo '</label>';
                echo '</p>';
            }
            
            echo '<p>';
            echo '<button type="button" class="button button-primary" id="nexus-translate-now" data-post-id="' . esc_attr($post->ID) . '">';
            echo __('Translate Now', 'nexus-ai-wp-translator');
            echo '</button>';
            echo ' <span class="nexus-translation-status"></span>';
            echo '</p>';
        } else {
            if (!$workflow_status['can_translate']) {
                echo '<p>' . __('Configure API key and target languages in settings.', 'nexus-ai-wp-translator') . '</p>';
            } else {
                echo '<p>' . __('All available translations completed.', 'nexus-ai-wp-translator') . '</p>';
            }
        }
        
        echo '</div>';
    }
    
    public function handle_auto_translate($post_id, $post) {
        // Skip autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        // Skip if already a translation
        if ($this->is_translation($post_id)) {
            return;
        }
        
        // Check if auto-translate is enabled
        $settings = get_option('nexus_ai_translator_settings', array());
        if (empty($settings['auto_translate'])) {
            return;
        }
        
        // Only translate published posts
        if ($post->post_status !== 'publish') {
            return;
        }
        
        // Check supported post types
        $supported_post_types = apply_filters('nexus_supported_post_types', array('post', 'page'));
        if (!in_array($post->post_type, $supported_post_types)) {
            return;
        }
        
        // Queue for background translation
        if ($this->batch_handler) {
            $this->batch_handler->queue_auto_translation($post_id);
        }
    }
    
    public function handle_post_deletion($post_id) {
        if ($this->metadata_manager) {
            $this->metadata_manager->handle_post_deletion($post_id);
        }
    }
    
    public function handle_post_trash($post_id) {
        if ($this->metadata_manager) {
            $this->metadata_manager->handle_post_trash($post_id);
        }
    }
    
    public function handle_post_untrash($post_id) {
        if ($this->metadata_manager) {
            $this->metadata_manager->handle_post_untrash($post_id);
        }
    }
    
    private function load_helper_classes() {
        $helper_files = array(
            'class-translation-content.php',
            'class-translation-batch.php',
            'class-translation-metadata.php',
        );
        
        foreach ($helper_files as $file) {
            $file_path = NEXUS_AI_TRANSLATOR_INCLUDES_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }
    
    private function init_helper_components() {
        // Initialize content processor
        if (class_exists('Nexus\\Translator\\Translation_Content')) {
            $this->content_processor = new Translation_Content($this);
        }
        
        // Initialize batch handler
        if (class_exists('Nexus\\Translator\\Translation_Batch')) {
            $this->batch_handler = new Translation_Batch($this);
        }
        
        // Initialize metadata manager
        if (class_exists('Nexus\\Translator\\Translation_Metadata')) {
            $this->metadata_manager = new Translation_Metadata($this);
        }
    }
    
    private function validate_translation_request($post_id, $target_language) {
        $errors = array();
        
        // Validate post
        $post = get_post($post_id);
        if (!$post) {
            $errors['post'] = __('Post not found', 'nexus-ai-wp-translator');
        } elseif ($post->post_status === 'trash') {
            $errors['post'] = __('Cannot translate trashed posts', 'nexus-ai-wp-translator');
        }
        
        // Validate language
        if ($this->languages) {
            $source_language = $this->get_post_language($post_id);
            $validation = $this->languages->validate_language_pair($source_language, $target_language);
            
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        } else {
            $errors['languages'] = __('Language validation not available', 'nexus-ai-wp-translator');
        }
        
        // Check API availability
        if (!$this->api) {
            $errors['api'] = __('Translation API not available', 'nexus-ai-wp-translator');
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }
    
    private function get_existing_translation($post_id, $target_language) {
        $translations = get_post_meta($post_id, '_nexus_translations', true);
        
        if (empty($translations) || !is_array($translations)) {
            return false;
        }
        
        if (!isset($translations[$target_language])) {
            return false;
        }
        
        $translated_post = get_post($translations[$target_language]);
        
        return ($translated_post && $translated_post->post_status !== 'trash') ? $translated_post->ID : false;
    }
    
    private function translate_content($content_data, $source_language, $target_language, $options = array()) {
        $translated_parts = array();
        
        foreach ($content_data as $part_name => $content) {
            if (empty($content)) {
                $translated_parts[$part_name] = $content;
                continue;
            }
            
            // Single string translation
            $translation_result = $this->api->translate($content, $source_language, $target_language, $options);
            
            if ($translation_result['success']) {
                $translated_parts[$part_name] = $translation_result['data']['translated_text'];
            } else {
                throw new \Exception(sprintf(
                    __('Failed to translate %s: %s', 'nexus-ai-wp-translator'),
                    $part_name,
                    $translation_result['message']
                ));
            }
        }
        
        return $this->create_success_response($translated_parts);
    }
    
    private function create_translated_post($source_post, $translated_content, $target_language, $options = array()) {
        $post_data = array(
            'post_title' => $translated_content['title'] ?? $source_post->post_title,
            'post_content' => $translated_content['content'] ?? $source_post->post_content,
            'post_excerpt' => $translated_content['excerpt'] ?? $source_post->post_excerpt,
            'post_status' => $options['post_status'] ?? 'draft',
            'post_type' => $source_post->post_type,
            'post_parent' => $source_post->post_parent,
            'menu_order' => $source_post->menu_order,
            'comment_status' => $source_post->comment_status,
            'ping_status' => $source_post->ping_status,
            'post_author' => $options['post_author'] ?? $source_post->post_author
        );
        
        // Add language suffix to title if enabled
        if ($options['add_language_suffix'] ?? true) {
            $language_info = $this->languages ? $this->languages->get_language($target_language) : null;
            $language_name = $language_info['name'] ?? $target_language;
            $post_data['post_title'] .= ' (' . $language_name . ')';
        }
        
        $translated_post_id = wp_insert_post($post_data);
        
        if (is_wp_error($translated_post_id)) {
            throw new \Exception($translated_post_id->get_error_message());
        }
        
        return $translated_post_id;
    }
    
    private function create_success_response($data = array(), $message = '') {
        return array(
            'success' => true,
            'data' => $data,
            'message' => $message,
            'timestamp' => current_time('mysql')
        );
    }
    
    private function create_error_response($message, $code = 'general_error', $details = array()) {
        return array(
            'success' => false,
            'message' => $message,
            'code' => $code,
            'errors' => $details,
            'timestamp' => current_time('mysql')
        );
    }
    
    // Getter methods for helper components
    public function get_content_processor() {
        return $this->content_processor;
    }
    
    public function get_batch_handler() {
        return $this->batch_handler;
    }
    
    public function get_metadata_manager() {
        return $this->metadata_manager;
    }
    
    public function get_api() {
        return $this->api;
    }
    
    public function get_languages() {
        return $this->languages;
    }
}