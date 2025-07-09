<?php
/**
 * File: class-nexus-translator.php
 * Location: /includes/class-nexus-translator.php
 * 
 * Nexus Translator Main Class
 * 
 * Main functionality and coordination class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Nexus_Translator {
    
    /**
     * API instance
     */
    private $api;
    
    /**
     * Post linker instance
     */
    private $post_linker;
    
    /**
     * Language manager instance
     */
    private $language_manager;
    
    /**
     * Translation panel instance
     */
    private $translation_panel;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_components();
        $this->init_hooks();
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        $this->api = new Translator_API();
        $this->post_linker = new Post_Linker();
        $this->language_manager = new Language_Manager();
        
        // Only initialize translation panel in admin
        if (is_admin()) {
            $this->translation_panel = new Translation_Panel();
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Add meta box
        add_action('add_meta_boxes', array($this, 'add_translation_meta_box'));
        
        // Plugin action links
        add_filter('plugin_action_links_' . plugin_basename(NEXUS_TRANSLATOR_PLUGIN_FILE), array($this, 'plugin_action_links'));
        
        // Save post hooks
        add_action('save_post', array($this, 'handle_post_save'), 10, 2);
        
        // Admin notices
        add_action('admin_notices', array($this, 'show_admin_notices'));
    }
    
    /**
     * Add translation meta box
     */
    public function add_translation_meta_box() {
        $post_types = array('post', 'page');
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'nexus-translation-meta-box',
                __('Translation', 'nexus-ai-wp-translator'),
                array($this, 'render_translation_meta_box'),
                $post_type,
                'side',
                'high'
            );
        }
    }
    
    /**
     * Render translation meta box
     */
    public function render_translation_meta_box($post) {
        // Get required instances
        $language_manager = $this->language_manager;
        $api = $this->api;
        $post_linker = $this->post_linker;
        
        // Get current language and translations
        $current_language = $post_linker->get_post_language($post->ID);
        $translations = $post_linker->get_all_translations($post->ID);
        
        // If no language set, set default
        if (!$current_language) {
            $settings = get_option('nexus_translator_language_settings', array());
            $source_language = $settings['source_language'] ?? 'fr';
            update_post_meta($post->ID, '_nexus_language', $source_language);
            $current_language = $source_language;
        }
        
        // Include meta box view
        include NEXUS_TRANSLATOR_ADMIN_DIR . 'views/translation-meta-box.php';
    }
    
    /**
     * Handle post save
     */
    public function handle_post_save($post_id, $post) {
        // Skip autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        // Skip if not our post types
        if (!in_array($post->post_type, array('post', 'page'))) {
            return;
        }
        
        // Check if this was a publish action with translation intent
        if (isset($_POST['nexus_auto_translate']) && $_POST['nexus_auto_translate'] === '1') {
            $this->handle_auto_translation($post_id);
        }
    }
    
    /**
     * Handle automatic translation
     */
    private function handle_auto_translation($post_id) {
        if (!isset($_POST['nexus_target_languages']) || !is_array($_POST['nexus_target_languages'])) {
            return;
        }
        
        $target_languages = $_POST['nexus_target_languages'];
        $results = array();
        
        foreach ($target_languages as $target_lang) {
            // Skip if translation already exists
            if ($this->post_linker->has_translation($post_id, $target_lang)) {
                continue;
            }
            
            $result = $this->translate_post($post_id, $target_lang);
            $results[] = array(
                'language' => $target_lang,
                'success' => $result['success'],
                'message' => $result['success'] ? 'Translation created' : $result['error'],
                'post_id' => $result['success'] ? $result['translated_post_id'] : null
            );
        }
        
        // Store results for display
        if (!empty($results)) {
            update_post_meta($post_id, '_nexus_last_translation_results', $results);
            update_post_meta($post_id, '_nexus_translation_timestamp', current_time('timestamp'));
        }
    }
    
    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        global $post;
        
        if (!$post || !in_array($post->post_type, array('post', 'page'))) {
            return;
        }
        
        $results = get_post_meta($post->ID, '_nexus_last_translation_results', true);
        $timestamp = get_post_meta($post->ID, '_nexus_translation_timestamp', true);
        
        if (!$results || !$timestamp) {
            return;
        }
        
        // Only show recent results (last 5 minutes)
        if ((current_time('timestamp') - $timestamp) > 300) {
            delete_post_meta($post->ID, '_nexus_last_translation_results');
            delete_post_meta($post->ID, '_nexus_translation_timestamp');
            return;
        }
        
        $success_count = count(array_filter($results, function($r) { return $r['success']; }));
        $total_count = count($results);
        
        if ($success_count > 0) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>' . sprintf(__('Translation Results: %d of %d completed successfully', 'nexus-ai-wp-translator'), $success_count, $total_count) . '</strong></p>';
            
            foreach ($results as $result) {
                if ($result['success']) {
                    $edit_link = get_edit_post_link($result['post_id']);
                    echo '<p>✅ ' . $this->language_manager->get_language_name($result['language']) . ': ';
                    echo '<a href="' . $edit_link . '" target="_blank">' . __('Edit Translation', 'nexus-ai-wp-translator') . '</a></p>';
                } else {
                    echo '<p>❌ ' . $this->language_manager->get_language_name($result['language']) . ': ' . esc_html($result['message']) . '</p>';
                }
            }
            
            echo '</div>';
        }
        
        // Clear results after showing
        delete_post_meta($post->ID, '_nexus_last_translation_results');
        delete_post_meta($post->ID, '_nexus_translation_timestamp');
    }
    
    /**
     * Translate a post
     * 
     * @param int $post_id Post ID to translate
     * @param string $target_language Target language code
     * @return array Translation result
     */
    public function translate_post($post_id, $target_language) {
        $post = get_post($post_id);
        if (!$post) {
            return array(
                'success' => false,
                'error' => __('Post not found', 'nexus-ai-wp-translator')
            );
        }
        
        // Check if translation already exists
        if ($this->post_linker->has_translation($post_id, $target_language)) {
            return array(
                'success' => false,
                'error' => __('Translation already exists', 'nexus-ai-wp-translator')
            );
        }
        
        // Get source language
        $source_language = $this->post_linker->get_post_language($post_id);
        if (!$source_language) {
            $settings = get_option('nexus_translator_language_settings', array());
            $source_language = $settings['source_language'] ?? 'fr';
        }
        
        // Prepare content for translation
        $content_to_translate = $this->prepare_content_for_translation($post);
        
        // Translate content
        $translation_result = $this->api->translate_content(
            $content_to_translate,
            $source_language,
            $target_language,
            $post->post_type
        );
        
        if (!$translation_result['success']) {
            return $translation_result;
        }
        
        // Create translated post
        $translated_post_id = $this->create_translated_post($post, $translation_result['translated_content'], $target_language);
        
        if (!$translated_post_id) {
            return array(
                'success' => false,
                'error' => __('Failed to create translated post', 'nexus-ai-wp-translator')
            );
        }
        
        // Link posts
        $this->post_linker->create_translation_link($post_id, $translated_post_id, $target_language);
        
        return array(
            'success' => true,
            'translated_post_id' => $translated_post_id,
            'edit_link' => get_edit_post_link($translated_post_id),
            'view_link' => get_permalink($translated_post_id),
            'usage' => $translation_result['usage'] ?? null
        );
    }
    
    /**
     * Prepare content for translation
     */
    private function prepare_content_for_translation($post) {
        $content = sprintf(
            "TITLE: %s\n\nCONTENT:\n%s",
            $post->post_title,
            $post->post_content
        );
        
        // Add excerpt if exists
        if (!empty($post->post_excerpt)) {
            $content = sprintf(
                "TITLE: %s\n\nEXCERPT: %s\n\nCONTENT:\n%s",
                $post->post_title,
                $post->post_excerpt,
                $post->post_content
            );
        }
        
        return $content;
    }
    
    /**
     * Create translated post
     */
    private function create_translated_post($original_post, $translated_content, $target_language) {
        // Parse translated content
        $content_parts = $this->parse_translated_content($translated_content);
        
        // Prepare post data - Default to draft for manual review
        $post_data = array(
            'post_title' => $content_parts['title'],
            'post_content' => $content_parts['content'],
            'post_excerpt' => $content_parts['excerpt'],
            'post_status' => 'draft',
            'post_type' => $original_post->post_type,
            'post_author' => $original_post->post_author,
            'menu_order' => $original_post->menu_order,
            'comment_status' => $original_post->comment_status,
            'ping_status' => $original_post->ping_status
        );
        
        // Create post
        $translated_post_id = wp_insert_post($post_data);
        
        if (is_wp_error($translated_post_id)) {
            return false;
        }
        
        // Copy categories and tags
        $this->copy_post_taxonomies($original_post->ID, $translated_post_id);
        
        // Copy featured image
        $this->copy_featured_image($original_post->ID, $translated_post_id);
        
        return $translated_post_id;
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
     * Copy post taxonomies
     */
    private function copy_post_taxonomies($source_id, $target_id) {
        $taxonomies = get_object_taxonomies(get_post_type($source_id));
        
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_post_terms($source_id, $taxonomy, array('fields' => 'ids'));
            if (!empty($terms) && !is_wp_error($terms)) {
                wp_set_post_terms($target_id, $terms, $taxonomy);
            }
        }
    }
    
    /**
     * Copy featured image
     */
    private function copy_featured_image($source_id, $target_id) {
        $featured_image_id = get_post_thumbnail_id($source_id);
        if ($featured_image_id) {
            set_post_thumbnail($target_id, $featured_image_id);
        }
    }
    
    /**
     * Enqueue admin scripts - UPDATED TO USE MODULAR SYSTEM
     */
    public function enqueue_admin_scripts($hook) {
        // Load on post edit screens AND settings page
        if (!in_array($hook, array('post.php', 'post-new.php', 'edit.php', 'settings_page_nexus-translator-settings'))) {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            'nexus-translator-admin',
            NEXUS_TRANSLATOR_PLUGIN_URL . 'admin/css/admin-style.css',
            array(),
            NEXUS_TRANSLATOR_VERSION
        );
        
        // Enqueue MODULAR JavaScript system
        wp_enqueue_script(
            'nexus-translator-core',
            NEXUS_TRANSLATOR_PLUGIN_URL . 'admin/js/admin-core.js',
            array('jquery'),
            NEXUS_TRANSLATOR_VERSION,
            true
        );
        
        wp_enqueue_script(
            'nexus-translator-modules',
            NEXUS_TRANSLATOR_PLUGIN_URL . 'admin/js/admin-modules.js',
            array('nexus-translator-core'),
            NEXUS_TRANSLATOR_VERSION,
            true
        );
        
        // Localize script for core
        wp_localize_script('nexus-translator-core', 'nexusTranslator', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nexus_translator_nonce'),
            'settingsUrl' => admin_url('admin.php?page=nexus-translator-settings'),
            'debug' => $this->is_debug_mode(),
            'strings' => array(
                'translating' => __('Translating...', 'nexus-ai-wp-translator'),
                'success' => __('Translation completed successfully!', 'nexus-ai-wp-translator'),
                'error' => __('Translation failed. Please try again.', 'nexus-ai-wp-translator'),
                'confirmTranslate' => __('Are you sure you want to translate this post?', 'nexus-ai-wp-translator'),
                'selectLanguages' => __('Please select at least one target language.', 'nexus-ai-wp-translator'),
                'translateNow' => __('Translate Now', 'nexus-ai-wp-translator'),
                'testing' => __('Testing...', 'nexus-ai-wp-translator'),
                'processing' => __('Processing...', 'nexus-ai-wp-translator')
            )
        ));
    }
    
    /**
     * Check if debug mode is enabled
     */
    private function is_debug_mode() {
        $options = get_option('nexus_translator_options', array());
        return !empty($options['debug_mode']);
    }
    
    /**
     * Plugin action links
     */
    public function plugin_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=nexus-translator-settings'),
            __('Settings', 'nexus-ai-wp-translator')
        );
        
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Get API instance
     */
    public function get_api() {
        return $this->api;
    }
    
    /**
     * Get post linker instance
     */
    public function get_post_linker() {
        return $this->post_linker;
    }
    
    /**
     * Get language manager instance
     */
    public function get_language_manager() {
        return $this->language_manager;
    }
    
    /**
     * Get translation panel instance
     */
    public function get_translation_panel() {
        return $this->translation_panel;
    }
}