<?php
/**
 * File: class-post-linker.php
 * Location: /includes/class-post-linker.php
 * 
 * Post Linker Class
 * 
 * Manages relationships between original and translated posts
 */

if (!defined('ABSPATH')) {
    exit;
}

class Post_Linker {
    
    /**
     * Meta key for translation relationship
     */
    const TRANSLATION_OF_META = '_nexus_translation_of';
    const LANGUAGE_META = '_nexus_language';
    const TRANSLATION_STATUS_META = '_nexus_translation_status';
    const HAS_TRANSLATION_META = '_nexus_has_translation_';
    
    /**
     * Translation statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_ERROR = 'error';
    const STATUS_OUTDATED = 'outdated';
    
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
        // Add language column to posts list
        add_filter('manage_posts_columns', array($this, 'add_language_column'));
        add_filter('manage_pages_columns', array($this, 'add_language_column'));
        add_action('manage_posts_custom_column', array($this, 'display_language_column'), 10, 2);
        add_action('manage_pages_custom_column', array($this, 'display_language_column'), 10, 2);
        
        // Add translation row actions
        add_filter('post_row_actions', array($this, 'add_translation_row_actions'), 10, 2);
        add_filter('page_row_actions', array($this, 'add_translation_row_actions'), 10, 2);
        
        // Monitor post updates
        add_action('post_updated', array($this, 'handle_post_update'), 10, 3);
    }
    
    /**
     * Create translation relationship
     * 
     * @param int $original_post_id Original post ID
     * @param int $translated_post_id Translated post ID
     * @param string $target_language Target language code
     * @return bool Success
     */
    public function create_translation_link($original_post_id, $translated_post_id, $target_language) {
        // Set translation relationship on translated post
        update_post_meta($translated_post_id, self::TRANSLATION_OF_META, $original_post_id);
        update_post_meta($translated_post_id, self::LANGUAGE_META, $target_language);
        update_post_meta($translated_post_id, self::TRANSLATION_STATUS_META, self::STATUS_COMPLETED);
        
        // Set reverse relationship on original post
        update_post_meta($original_post_id, self::HAS_TRANSLATION_META . $target_language, $translated_post_id);
        
        // Get original post language
        $source_language = $this->get_post_language($original_post_id);
        if (!$source_language) {
            // Set default source language if not set
            $settings = get_option('nexus_translator_language_settings', array());
            $source_language = $settings['source_language'] ?? 'fr';
            update_post_meta($original_post_id, self::LANGUAGE_META, $source_language);
        }
        
        return true;
    }
    
    /**
     * Get post language
     * 
     * @param int $post_id Post ID
     * @return string|false Language code or false if not set
     */
    public function get_post_language($post_id) {
        return get_post_meta($post_id, self::LANGUAGE_META, true) ?: false;
    }
    
    /**
     * Get original post ID
     * 
     * @param int $post_id Post ID
     * @return int|false Original post ID or false if not a translation
     */
    public function get_original_post_id($post_id) {
        $original_id = get_post_meta($post_id, self::TRANSLATION_OF_META, true);
        return $original_id ? (int) $original_id : false;
    }
    
    /**
     * Get translated post ID for a specific language
     * 
     * @param int $post_id Original post ID
     * @param string $language Language code
     * @return int|false Translated post ID or false if doesn't exist
     */
    public function get_translated_post_id($post_id, $language) {
        $translated_id = get_post_meta($post_id, self::HAS_TRANSLATION_META . $language, true);
        return $translated_id ? (int) $translated_id : false;
    }
    
    /**
     * Get all translations for a post
     * 
     * @param int $post_id Post ID
     * @return array Array of translations with language codes as keys
     */
    public function get_all_translations($post_id) {
        $translations = array();
        
        // If this is a translation, get the original first
        $original_id = $this->get_original_post_id($post_id);
        if ($original_id) {
            $post_id = $original_id;
        }
        
        // Add the original post
        $original_language = $this->get_post_language($post_id);
        if ($original_language) {
            $translations[$original_language] = $post_id;
        }
        
        // Get all translation meta keys
        $meta_keys = get_post_meta($post_id);
        foreach ($meta_keys as $key => $value) {
            if (strpos($key, self::HAS_TRANSLATION_META) === 0) {
                $language = str_replace(self::HAS_TRANSLATION_META, '', $key);
                $translated_post_id = (int) $value[0];
                
                // Verify the translated post exists and is published
                $translated_post = get_post($translated_post_id);
                if ($translated_post && $translated_post->post_status !== 'trash') {
                    $translations[$language] = $translated_post_id;
                }
            }
        }
        
        return $translations;
    }
    
    /**
     * Check if post has translation in specific language
     * 
     * @param int $post_id Post ID
     * @param string $language Language code
     * @return bool
     */
    public function has_translation($post_id, $language) {
        return (bool) $this->get_translated_post_id($post_id, $language);
    }
    
    /**
     * Get translation status
     * 
     * @param int $post_id Post ID
     * @return string Translation status
     */
    public function get_translation_status($post_id) {
        return get_post_meta($post_id, self::TRANSLATION_STATUS_META, true) ?: self::STATUS_PENDING;
    }
    
    /**
     * Update translation status
     * 
     * @param int $post_id Post ID
     * @param string $status Status
     */
    public function update_translation_status($post_id, $status) {
        update_post_meta($post_id, self::TRANSLATION_STATUS_META, $status);
    }
    
    /**
     * Delete translation relationship
     * 
     * @param int $post_id Post ID (can be original or translation)
     */
    public function delete_translation_relationship($post_id) {
        $original_id = $this->get_original_post_id($post_id);
        
        if ($original_id) {
            // This is a translation - remove from original
            $language = $this->get_post_language($post_id);
            if ($language) {
                delete_post_meta($original_id, self::HAS_TRANSLATION_META . $language);
            }
            
            // Remove translation meta from this post
            delete_post_meta($post_id, self::TRANSLATION_OF_META);
            delete_post_meta($post_id, self::LANGUAGE_META);
            delete_post_meta($post_id, self::TRANSLATION_STATUS_META);
        } else {
            // This is an original - remove all translation relationships
            $translations = $this->get_all_translations($post_id);
            foreach ($translations as $language => $translated_id) {
                if ($translated_id !== $post_id) {
                    delete_post_meta($translated_id, self::TRANSLATION_OF_META);
                    delete_post_meta($translated_id, self::LANGUAGE_META);
                    delete_post_meta($translated_id, self::TRANSLATION_STATUS_META);
                    delete_post_meta($post_id, self::HAS_TRANSLATION_META . $language);
                }
            }
        }
    }
    
    /**
     * Add language column to posts list
     */
    public function add_language_column($columns) {
        $columns['nexus_language'] = __('Language', 'nexus-ai-wp-translator');
        return $columns;
    }
    
    /**
     * Display language column content
     */
    public function display_language_column($column, $post_id) {
        if ($column === 'nexus_language') {
            $language = $this->get_post_language($post_id);
            if ($language) {
                $language_name = $this->get_language_name($language);
                $flag = $this->get_language_flag($language);
                echo sprintf('<span class="nexus-language-badge">%s %s</span>', $flag, $language_name);
                
                // Show translation links
                $translations = $this->get_all_translations($post_id);
                if (count($translations) > 1) {
                    echo '<br><small>';
                    foreach ($translations as $lang => $trans_id) {
                        if ($trans_id !== $post_id) {
                            $edit_link = get_edit_post_link($trans_id);
                            $flag = $this->get_language_flag($lang);
                            echo sprintf(' <a href="%s" title="%s">%s</a>', $edit_link, $this->get_language_name($lang), $flag);
                        }
                    }
                    echo '</small>';
                }
            } else {
                echo '<span class="nexus-no-language">—</span>';
            }
        }
    }
    
    /**
     * Add translation row actions
     */
    public function add_translation_row_actions($actions, $post) {
        if (!current_user_can('edit_post', $post->ID)) {
            return $actions;
        }
        
        $language_settings = get_option('nexus_translator_language_settings', array());
        $target_languages = $language_settings['target_languages'] ?? array('en');
        
        foreach ($target_languages as $target_lang) {
            if (!$this->has_translation($post->ID, $target_lang)) {
                $language_name = $this->get_language_name($target_lang);
                $actions['translate_to_' . $target_lang] = sprintf(
                    '<a href="#" class="nexus-translate-link" data-post-id="%d" data-target-lang="%s">%s</a>',
                    $post->ID,
                    $target_lang,
                    sprintf(__('Translate to %s', 'nexus-ai-wp-translator'), $language_name)
                );
            }
        }
        
        return $actions;
    }
    
    /**
     * Handle post update
     */
    public function handle_post_update($post_id, $post_after, $post_before) {
        // Skip auto-saves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        // Check if content changed
        if ($post_after->post_content !== $post_before->post_content || 
            $post_after->post_title !== $post_before->post_title) {
            
            // Mark translations as outdated
            $translations = $this->get_all_translations($post_id);
            foreach ($translations as $language => $translated_id) {
                if ($translated_id !== $post_id) {
                    $this->update_translation_status($translated_id, self::STATUS_OUTDATED);
                }
            }
        }
    }
    
    /**
     * Get language name
     */
    private function get_language_name($code) {
        $languages = array(
            'fr' => __('French', 'nexus-ai-wp-translator'),
            'en' => __('English', 'nexus-ai-wp-translator'),
            'es' => __('Spanish', 'nexus-ai-wp-translator'),
            'de' => __('German', 'nexus-ai-wp-translator'),
            'it' => __('Italian', 'nexus-ai-wp-translator'),
            'pt' => __('Portuguese', 'nexus-ai-wp-translator'),
            'nl' => __('Dutch', 'nexus-ai-wp-translator'),
            'ru' => __('Russian', 'nexus-ai-wp-translator'),
            'ja' => __('Japanese', 'nexus-ai-wp-translator'),
            'zh' => __('Chinese', 'nexus-ai-wp-translator')
        );
        
        return $languages[$code] ?? strtoupper($code);
    }
    
    /**
     * Get language flag emoji
     */
    private function get_language_flag($code) {
        $flags = array(
            'fr' => '🇫🇷',
            'en' => '🇺🇸',
            'es' => '🇪🇸',
            'de' => '🇩🇪',
            'it' => '🇮🇹',
            'pt' => '🇵🇹',
            'nl' => '🇳🇱',
            'ru' => '🇷🇺',
            'ja' => '🇯🇵',
            'zh' => '🇨🇳'
        );
        
        return $flags[$code] ?? '🌍';
    }
}