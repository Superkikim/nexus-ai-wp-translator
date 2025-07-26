<?php
/**
 * File: class-translation-metadata.php
 * Location: /includes/class-translation-metadata.php
 * 
 * Translation Metadata Manager Class
 * Responsible for: Post metadata, relationships, cleanup operations
 */

namespace Nexus\Translator;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Translation metadata manager class
 * 
 * Handles post relationships, metadata management, and data integrity operations.
 * Manages bidirectional links between source and translated posts.
 */
class Translation_Metadata {
    
    private $translator;
    
    public function __construct($translator) {
        $this->translator = $translator;
    }
    
    public function register_hooks() {
        // Daily cleanup
        $this->add_hook('nexus_daily_cleanup', array($this, 'cleanup_orphaned_translations'));
        
        // Post status changes
        $this->add_hook('transition_post_status', array($this, 'handle_post_status_change'), 10, 3);
    }
    
    /**
     * Set up translation relationship between posts
     * 
     * @param int $source_post_id Source post ID
     * @param int $translated_post_id Translated post ID
     * @param string $source_language Source language
     * @param string $target_language Target language
     * @return void
     */
    public function setup_translation_relationship($source_post_id, $translated_post_id, $source_language, $target_language) {
        // Mark translated post as translation
        update_post_meta($translated_post_id, '_nexus_source_post_id', $source_post_id);
        update_post_meta($translated_post_id, '_nexus_language', $target_language);
        update_post_meta($translated_post_id, '_nexus_translation_date', current_time('mysql'));
        update_post_meta($translated_post_id, '_nexus_translation_version', '1.0');
        
        // Update source post translations list
        $translations = get_post_meta($source_post_id, '_nexus_translations', true);
        if (!is_array($translations)) {
            $translations = array();
        }
        $translations[$target_language] = $translated_post_id;
        update_post_meta($source_post_id, '_nexus_translations', $translations);
        
        // Set source post language if not set
        if (!get_post_meta($source_post_id, '_nexus_language', true)) {
            update_post_meta($source_post_id, '_nexus_language', $source_language);
        }
        
        // Create reverse lookup for quick source finding
        $this->update_translation_index($source_post_id, $translated_post_id, $target_language);
        
        // Fire relationship setup hook
        do_action('nexus_translation_relationship_setup', $source_post_id, $translated_post_id, $source_language, $target_language);
    }
    
    /**
     * Copy post metadata and taxonomies
     * 
     * @param int $source_post_id Source post ID
     * @param int $translated_post_id Translated post ID
     * @param array $options Copy options
     * @return void
     */
    public function copy_post_data($source_post_id, $translated_post_id, $options = array()) {
        // Copy metadata
        $this->copy_post_metadata($source_post_id, $translated_post_id, $options);
        
        // Copy taxonomies
        $this->copy_post_taxonomies($source_post_id, $translated_post_id, $options);
        
        // Copy featured image
        if ($options['copy_featured_image'] ?? true) {
            $this->copy_featured_image($source_post_id, $translated_post_id);
        }
        
        // Copy custom fields
        if ($options['copy_custom_fields'] ?? true) {
            $this->copy_custom_fields($source_post_id, $translated_post_id, $options);
        }
        
        // Fire copy complete hook
        do_action('nexus_post_data_copied', $source_post_id, $translated_post_id, $options);
    }
    
    /**
     * Get all translations of a post
     * 
     * @param int $post_id Post ID
     * @return array Array of translation data
     */
    public function get_post_translations($post_id) {
        $translations = get_post_meta($post_id, '_nexus_translations', true);
        
        if (empty($translations) || !is_array($translations)) {
            return array();
        }
        
        $translation_data = array();
        $languages = $this->translator->get_languages();
        
        foreach ($translations as $language => $translated_post_id) {
            $translated_post = get_post($translated_post_id);
            
            if ($translated_post && $translated_post->post_status !== 'trash') {
                $language_info = $languages ? $languages->get_language($language) : null;
                
                $translation_data[$language] = array(
                    'post_id' => $translated_post_id,
                    'language' => $language,
                    'language_name' => $language_info['name'] ?? $language,
                    'post_title' => $translated_post->post_title,
                    'post_status' => $translated_post->post_status,
                    'edit_link' => get_edit_post_link($translated_post_id),
                    'view_link' => get_permalink($translated_post_id),
                    'last_modified' => $translated_post->post_modified,
                    'translation_date' => get_post_meta($translated_post_id, '_nexus_translation_date', true),
                    'translation_version' => get_post_meta($translated_post_id, '_nexus_translation_version', true) ?: '1.0'
                );
            }
        }
        
        return $translation_data;
    }
    
    /**
     * Get translation statistics
     * 
     * @return array Translation statistics
     */
    public function get_translation_stats() {
        global $wpdb;
        
        $stats = array(
            'total_translations' => 0,
            'by_language' => array(),
            'by_post_type' => array(),
            'recent_translations' => array()
        );
        
        // Get all translation metadata
        $translation_meta = $wpdb->get_results(
            "SELECT pm.post_id, pm.meta_value as language, p.post_type, p.post_title 
             FROM {$wpdb->postmeta} pm 
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE pm.meta_key = '_nexus_language' 
             AND p.post_status != 'trash'"
        );
        
        if (!empty($translation_meta)) {
            $stats['total_translations'] = count($translation_meta);
            
            foreach ($translation_meta as $meta) {
                // By language
                if (!isset($stats['by_language'][$meta->language])) {
                    $stats['by_language'][$meta->language] = 0;
                }
                $stats['by_language'][$meta->language]++;
                
                // By post type
                if (!isset($stats['by_post_type'][$meta->post_type])) {
                    $stats['by_post_type'][$meta->post_type] = 0;
                }
                $stats['by_post_type'][$meta->post_type]++;
            }
        }
        
        // Get recent translations
        $recent_translations = $wpdb->get_results(
            "SELECT pm.post_id, pm.meta_value as translation_date, p.post_title, p2.post_title as source_title
             FROM {$wpdb->postmeta} pm 
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             LEFT JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id AND pm2.meta_key = '_nexus_source_post_id'
             LEFT JOIN {$wpdb->posts} p2 ON pm2.meta_value = p2.ID
             WHERE pm.meta_key = '_nexus_translation_date' 
             AND p.post_status != 'trash' 
             ORDER BY pm.meta_value DESC 
             LIMIT 10"
        );
        
        if (!empty($recent_translations)) {
            foreach ($recent_translations as $translation) {
                $stats['recent_translations'][] = array(
                    'post_id' => $translation->post_id,
                    'post_title' => $translation->post_title,
                    'source_title' => $translation->source_title,
                    'translation_date' => $translation->translation_date,
                    'edit_link' => get_edit_post_link($translation->post_id)
                );
            }
        }
        
        return $stats;
    }
    
    /**
     * Clean up orphaned translations
     * 
     * @return array Cleanup results
     */
    public function cleanup_orphaned_translations() {
        global $wpdb;
        
        $cleanup_results = array(
            'orphaned_translations' => 0,
            'broken_relationships' => 0,
            'cleaned_metadata' => 0
        );
        
        // Find translations whose source posts no longer exist
        $orphaned_translations = $wpdb->get_results(
            "SELECT pm.post_id, pm.meta_value as source_post_id 
             FROM {$wpdb->postmeta} pm 
             LEFT JOIN {$wpdb->posts} p ON pm.meta_value = p.ID 
             WHERE pm.meta_key = '_nexus_source_post_id' 
             AND (p.ID IS NULL OR p.post_status = 'trash')"
        );
        
        foreach ($orphaned_translations as $orphan) {
            // Delete the orphaned translation
            wp_delete_post($orphan->post_id, true);
            $cleanup_results['orphaned_translations']++;
            
            // Remove from translation index
            $this->remove_from_translation_index($orphan->post_id);
        }
        
        // Find source posts with broken translation references
        $broken_references = $wpdb->get_results(
            "SELECT pm.post_id, pm.meta_value as translations 
             FROM {$wpdb->postmeta} pm 
             WHERE pm.meta_key = '_nexus_translations'"
        );
        
        foreach ($broken_references as $reference) {
            $translations = maybe_unserialize($reference->translations);
            
            if (is_array($translations)) {
                $cleaned_translations = array();
                
                foreach ($translations as $language => $translated_post_id) {
                    $translated_post = get_post($translated_post_id);
                    
                    if ($translated_post && $translated_post->post_status !== 'trash') {
                        $cleaned_translations[$language] = $translated_post_id;
                    } else {
                        $cleanup_results['broken_relationships']++;
                    }
                }
                
                if (count($cleaned_translations) !== count($translations)) {
                    if (empty($cleaned_translations)) {
                        delete_post_meta($reference->post_id, '_nexus_translations');
                    } else {
                        update_post_meta($reference->post_id, '_nexus_translations', $cleaned_translations);
                    }
                    $cleanup_results['cleaned_metadata']++;
                }
            }
        }
        
        // Clean up translation index
        $this->cleanup_translation_index();
        
        // Fire cleanup event
        do_action('nexus_analytics_event', 'translation_cleanup', $cleanup_results);
        
        return $cleanup_results;
    }
    
    /**
     * Handle post deletion
     * 
     * @param int $post_id Post being deleted
     * @return void
     */
    public function handle_post_deletion($post_id) {
        // If this is a source post, delete all translations
        $translations = $this->get_post_translations($post_id);
        
        foreach ($translations as $translation) {
            wp_delete_post($translation['post_id'], true);
        }
        
        // If this is a translation, remove from source post's translations list
        $source_post_id = get_post_meta($post_id, '_nexus_source_post_id', true);
        if ($source_post_id) {
            $source_translations = get_post_meta($source_post_id, '_nexus_translations', true);
            if (is_array($source_translations)) {
                $source_translations = array_filter($source_translations, function($translated_id) use ($post_id) {
                    return $translated_id != $post_id;
                });
                
                if (empty($source_translations)) {
                    delete_post_meta($source_post_id, '_nexus_translations');
                } else {
                    update_post_meta($source_post_id, '_nexus_translations', $source_translations);
                }
            }
        }
        
        // Remove from translation index
        $this->remove_from_translation_index($post_id);
        
        // Fire analytics event
        do_action('nexus_analytics_event', 'translation_group_deleted', array(
            'source_post_id' => $post_id,
            'deleted_translations' => count($translations)
        ));
    }
    
    /**
     * Handle post trash
     * 
     * @param int $post_id Post being trashed
     * @return void
     */
    public function handle_post_trash($post_id) {
        // If this is a source post, trash all translations
        $translations = $this->get_post_translations($post_id);
        
        foreach ($translations as $translation) {
            wp_trash_post($translation['post_id']);
        }
        
        // Fire analytics event
        do_action('nexus_analytics_event', 'translation_group_trashed', array(
            'source_post_id' => $post_id,
            'trashed_translations' => count($translations)
        ));
    }
    
    /**
     * Handle post untrash
     * 
     * @param int $post_id Post being untrashed
     * @return void
     */
    public function handle_post_untrash($post_id) {
        // If this is a source post, untrash all translations
        $translations = get_post_meta($post_id, '_nexus_translations', true);
        
        if (!empty($translations) && is_array($translations)) {
            foreach ($translations as $translated_post_id) {
                $translated_post = get_post($translated_post_id);
                if ($translated_post && $translated_post->post_status === 'trash') {
                    wp_untrash_post($translated_post_id);
                }
            }
        }
    }
    
    /**
     * Handle post status changes
     * 
     * @param string $new_status New post status
     * @param string $old_status Old post status
     * @param WP_Post $post Post object
     * @return void
     */
    public function handle_post_status_change($new_status, $old_status, $post) {
        // Only handle status changes for source posts
        if ($this->translator->is_translation($post->ID)) {
            return;
        }
        
        $translations = $this->get_post_translations($post->ID);
        
        if (empty($translations)) {
            return;
        }
        
        // Sync certain status changes
        $sync_statuses = apply_filters('nexus_sync_post_statuses', array(
            'publish' => 'publish',
            'private' => 'private',
            'draft' => 'draft'
        ));
        
        if (isset($sync_statuses[$new_status])) {
            foreach ($translations as $translation) {
                wp_update_post(array(
                    'ID' => $translation['post_id'],
                    'post_status' => $sync_statuses[$new_status]
                ));
            }
            
            // Fire sync event
            do_action('nexus_translation_status_synced', $post->ID, $new_status, array_column($translations, 'post_id'));
        }
    }
    
    private function copy_post_metadata($source_post_id, $translated_post_id, $options = array()) {
        $excluded_meta = apply_filters('nexus_excluded_meta_keys', array(
            '_nexus_translations',
            '_nexus_source_post_id', 
            '_nexus_language',
            '_nexus_translation_date',
            '_nexus_translation_version',
            '_edit_lock',
            '_edit_last',
            '_wp_old_slug',
            '_wp_old_date'
        ));
        
        $all_meta = get_post_meta($source_post_id);
        
        foreach ($all_meta as $meta_key => $meta_values) {
            if (in_array($meta_key, $excluded_meta)) {
                continue;
            }
            
            // Skip private meta unless explicitly included
            if (substr($meta_key, 0, 1) === '_' && !($options['copy_private_meta'] ?? false)) {
                continue;
            }
            
            foreach ($meta_values as $meta_value) {
                add_post_meta($translated_post_id, $meta_key, maybe_unserialize($meta_value));
            }
        }
    }
    
    private function copy_post_taxonomies($source_post_id, $translated_post_id, $options = array()) {
        $post_type = get_post_type($source_post_id);
        $taxonomies = get_object_taxonomies($post_type);
        
        $excluded_taxonomies = apply_filters('nexus_excluded_taxonomies', array());
        
        foreach ($taxonomies as $taxonomy) {
            if (in_array($taxonomy, $excluded_taxonomies)) {
                continue;
            }
            
            $terms = wp_get_object_terms($source_post_id, $taxonomy, array('fields' => 'ids'));
            
            if (!empty($terms) && !is_wp_error($terms)) {
                wp_set_object_terms($translated_post_id, $terms, $taxonomy);
            }
        }
    }
    
    private function copy_featured_image($source_post_id, $translated_post_id) {
        $featured_image_id = get_post_thumbnail_id($source_post_id);
        
        if ($featured_image_id) {
            set_post_thumbnail($translated_post_id, $featured_image_id);
        }
    }
    
    private function copy_custom_fields($source_post_id, $translated_post_id, $options = array()) {
        $content_processor = $this->translator->get_content_processor();
        
        if (!$content_processor) {
            return;
        }
        
        // Get custom fields that were translated
        $translated_custom_fields = $options['translated_custom_fields'] ?? array();
        
        if (!empty($translated_custom_fields)) {
            $content_processor->apply_translated_custom_fields($translated_post_id, $translated_custom_fields);
        }
        
        // Get SEO fields that were translated
        $translated_seo_fields = $options['translated_seo_fields'] ?? array();
        
        if (!empty($translated_seo_fields)) {
            $content_processor->apply_translated_seo_fields($translated_post_id, $translated_seo_fields);
        }
    }
    
    private function update_translation_index($source_post_id, $translated_post_id, $target_language) {
        $index = get_option('nexus_translation_index', array());
        
        $index[$translated_post_id] = array(
            'source_post_id' => $source_post_id,
            'language' => $target_language,
            'created' => current_time('mysql')
        );
        
        update_option('nexus_translation_index', $index);
    }
    
    private function remove_from_translation_index($post_id) {
        $index = get_option('nexus_translation_index', array());
        
        if (isset($index[$post_id])) {
            unset($index[$post_id]);
            update_option('nexus_translation_index', $index);
        }
    }
    
    private function cleanup_translation_index() {
        $index = get_option('nexus_translation_index', array());
        $cleaned_index = array();
        
        foreach ($index as $translated_post_id => $data) {
            $translated_post = get_post($translated_post_id);
            $source_post = get_post($data['source_post_id']);
            
            // Keep entry if both posts exist and are not trashed
            if ($translated_post && $source_post && 
                $translated_post->post_status !== 'trash' && 
                $source_post->post_status !== 'trash') {
                $cleaned_index[$translated_post_id] = $data;
            }
        }
        
        if (count($cleaned_index) !== count($index)) {
            update_option('nexus_translation_index', $cleaned_index);
        }
    }
    
    // Helper method to add hooks (since this isn't extending Abstract_Module)
    private function add_hook($hook, $callback, $priority = 10, $accepted_args = 1) {
        add_action($hook, $callback, $priority, $accepted_args);
    }
    
    /**
     * Get translation relationship data
     * 
     * @param int $post_id Post ID
     * @return array Relationship data
     */
    public function get_translation_relationships($post_id) {
        $relationships = array(
            'is_source' => false,
            'is_translation' => false,
            'source_post_id' => null,
            'translations' => array(),
            'total_translations' => 0
        );
        
        // Check if this is a translation
        $source_post_id = get_post_meta($post_id, '_nexus_source_post_id', true);
        if ($source_post_id) {
            $relationships['is_translation'] = true;
            $relationships['source_post_id'] = $source_post_id;
        } else {
            // This is a source post
            $relationships['is_source'] = true;
            $relationships['translations'] = $this->get_post_translations($post_id);
            $relationships['total_translations'] = count($relationships['translations']);
        }
        
        return $relationships;
    }
    
    /**
     * Export translation data
     * 
     * @param array $options Export options
     * @return array Export data
     */
    public function export_translation_data($options = array()) {
        global $wpdb;
        
        $export_data = array(
            'exported_at' => current_time('mysql'),
            'plugin_version' => NEXUS_AI_TRANSLATOR_VERSION,
            'statistics' => $this->get_translation_stats(),
            'relationships' => array(),
            'metadata' => array()
        );
        
        // Get all translation relationships
        $relationships = $wpdb->get_results(
            "SELECT pm1.post_id as source_id, pm1.meta_value as translations,
                    pm2.post_id as translation_id, pm2.meta_value as source_id_ref,
                    pm3.meta_value as language
             FROM {$wpdb->postmeta} pm1
             LEFT JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.meta_value
             LEFT JOIN {$wpdb->postmeta} pm3 ON pm2.post_id = pm3.post_id
             WHERE pm1.meta_key = '_nexus_translations'
             AND pm2.meta_key = '_nexus_source_post_id'
             AND pm3.meta_key = '_nexus_language'"
        );
        
        foreach ($relationships as $rel) {
            $export_data['relationships'][] = array(
                'source_post_id' => $rel->source_id,
                'translated_post_id' => $rel->translation_id,
                'language' => $rel->language
            );
        }
        
        return apply_filters('nexus_export_translation_data', $export_data, $options);
    }
}
}