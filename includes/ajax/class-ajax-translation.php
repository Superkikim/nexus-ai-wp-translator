<?php
/**
 * File: class-ajax-translation.php
 * Location: /includes/ajax/class-ajax-translation.php
 * 
 * AJAX Translation Handler - Spécialisé traductions
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
        add_action('wp_ajax_nexus_translate_post', array($this, 'handle_translate_post'));
        add_action('wp_ajax_nexus_update_translation', array($this, 'handle_update_translation'));
        add_action('wp_ajax_nexus_get_translation_status', array($this, 'handle_get_translation_status'));
        add_action('wp_ajax_nexus_delete_translation', array($this, 'handle_delete_translation'));
    }
    
    /**
     * 🔒 HANDLER PRINCIPAL : Traduction de post avec protection complète
     */
    public function handle_translate_post() {
        error_log('Nexus Translation AJAX: Translation request received');
        
        // Validation de sécurité
        $this->validate_ajax_request('edit_posts');
        
        // Récupérer et valider les paramètres
        $post_id = (int) $_POST['post_id'];
        $target_language = sanitize_text_field($_POST['target_language']);
        
        if (!$post_id || !$target_language) {
            $this->send_error('Invalid parameters', 'INVALID_PARAMS');
        }
        
        // Valider le post
        $post = $this->validate_post_id($post_id, 'edit_post');
        
        // 🔒 PROTECTION : Éviter requêtes simultanées
        $request_key = "translate_{$post_id}_{$target_language}_" . get_current_user_id();
        $this->check_duplicate_request($request_key);
        
        try {
            // Vérifier l'état d'urgence
            if (get_option('nexus_translator_emergency_stop', false)) {
                $this->send_error('Translation disabled (Emergency Stop active)', 'EMERGENCY_STOP');
            }
            
            // Vérifier si traduction autorisée
            $api = new Translator_API();
            $translation_check = $api->can_translate_post($post_id);
            
            if (!$translation_check['can_translate']) {
                $this->send_error($translation_check['reason'], 'TRANSLATION_NOT_ALLOWED');
            }
            
            // Vérifier si traduction existe déjà
            $post_linker = new Post_Linker();
            if ($post_linker->has_translation($post_id, $target_language)) {
                $this->send_error('Translation already exists for this language', 'TRANSLATION_EXISTS');
            }
            
            // Effectuer la traduction
            $translator = new Nexus_Translator();
            $result = $translator->translate_post($post_id, $target_language);
            
            if ($result['success']) {
                // Logger le succès
                $this->log_usage('translate_post', array(
                    'post_id' => $post_id,
                    'target_language' => $target_language,
                    'translated_post_id' => $result['translated_post_id'],
                    'success' => true
                ));
                
                $this->send_success(array(
                    'translated_post_id' => $result['translated_post_id'],
                    'edit_link' => $result['edit_link'],
                    'view_link' => $result['view_link'],
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
     * Handle update translation request
     */
    public function handle_update_translation() {
        $this->validate_ajax_request('edit_posts');
        
        $original_post_id = (int) $_POST['original_post_id'];
        $translated_post_id = (int) $_POST['translated_post_id'];
        
        if (!$original_post_id || !$translated_post_id) {
            $this->send_error('Invalid parameters', 'INVALID_PARAMS');
        }
        
        // Valider les deux posts
        $this->validate_post_id($original_post_id, 'edit_post');
        $this->validate_post_id($translated_post_id, 'edit_post');
        
        // 🔒 PROTECTION : Éviter mises à jour simultanées
        $request_key = "update_{$original_post_id}_{$translated_post_id}_" . get_current_user_id();
        $this->check_duplicate_request($request_key);
        
        try {
            // Vérifier si traduction autorisée
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
            
            // Mettre à jour le statut
            $post_linker->update_translation_status($translated_post_id, Post_Linker::STATUS_PENDING);
            
            // Re-traduire
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
     * Effectuer une mise à jour de traduction
     */
    private function perform_update_translation($original_post_id, $translated_post_id, $target_language) {
        $original_post = get_post($original_post_id);
        $post_linker = new Post_Linker();
        
        // Préparer le contenu à traduire
        $content_to_translate = $this->prepare_content_for_translation($original_post);
        
        // Obtenir la langue source
        $source_language = $post_linker->get_post_language($original_post_id);
        if (!$source_language) {
            $settings = get_option('nexus_translator_language_settings', array());
            $source_language = $settings['source_language'] ?? 'fr';
        }
        
        // Traduire le contenu
        $api = new Translator_API();
        $translation_result = $api->translate_content(
            $content_to_translate,
            $source_language,
            $target_language,
            $original_post->post_type
        );
        
        if (!$translation_result['success']) {
            return $translation_result;
        }
        
        // Parser le contenu traduit
        $content_parts = $this->parse_translated_content($translation_result['translated_content']);
        
        // Mettre à jour le post traduit
        $update_data = array(
            'ID' => $translated_post_id,
            'post_title' => $content_parts['title'],
            'post_content' => $content_parts['content'],
            'post_excerpt' => $content_parts['excerpt']
        );
        
        $result = wp_update_post($update_data);
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'error' => 'Failed to update translated post'
            );
        }
        
        return array(
            'success' => true,
            'usage' => $translation_result['usage'] ?? null
        );
    }
    
    /**
     * Préparer le contenu pour traduction
     */
    private function prepare_content_for_translation($post) {
        $content = sprintf("TITLE: %s\n\nCONTENT:\n%s", $post->post_title, $post->post_content);
        
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
     * Parser le contenu traduit
     */
    private function parse_translated_content($content) {
        $parts = array(
            'title' => '',
            'excerpt' => '',
            'content' => $content
        );
        
        // Extraire le titre
        if (preg_match('/^TITLE:\s*(.+?)(?:\n|$)/m', $content, $matches)) {
            $parts['title'] = trim($matches[1]);
            $content = preg_replace('/^TITLE:\s*.+?(?:\n|$)/m', '', $content);
        }
        
        // Extraire l'extrait
        if (preg_match('/EXCERPT:\s*(.+?)(?:\n\n|\nCONTENT:)/s', $content, $matches)) {
            $parts['excerpt'] = trim($matches[1]);
            $content = preg_replace('/EXCERPT:\s*.+?(?:\n\n|\nCONTENT:)/s', '', $content);
        }
        
        // Extraire le contenu
        if (preg_match('/CONTENT:\s*(.+)/s', $content, $matches)) {
            $parts['content'] = trim($matches[1]);
        } else {
            $parts['content'] = trim($content);
        }
        
        return $parts;
    }
}