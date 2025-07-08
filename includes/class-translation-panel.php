<?php
/**
 * File: class-translation-panel.php
 * Location: /includes/class-translation-panel.php
 * 
 * Translation Panel Class
 * 
 * Manages the translation panel in post editor
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translation_Panel {
    
    private $post_linker;
    private $language_manager;
    private $api;
    
    public function __construct() {
        $this->post_linker = new Post_Linker();
        $this->language_manager = new Language_Manager();
        $this->api = new Translator_API();
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('add_meta_boxes', array($this, 'add_translation_panel'));
        add_action('save_post', array($this, 'handle_auto_translation'), 20, 2);
        add_action('admin_notices', array($this, 'show_translation_results'));
        add_action('admin_footer', array($this, 'add_publish_screen_info'));
        
        // Note: Script loading is now handled by the main Nexus_Translator class
        // using the modular architecture (admin-core.js + admin-modules.js)
    }
    
    public function add_translation_panel() {
        $post_types = array('post', 'page');
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'nexus-translation-panel',
                __('Nexus AI Translation Panel', 'nexus-ai-wp-translator'),
                array($this, 'render_translation_panel'),
                $post_type,
                'side',
                'high'
            );
        }
    }
    
    public function render_translation_panel($post) {
        if (!$this->api->is_api_configured()) {
            $this->render_api_not_configured();
            return;
        }
        
        wp_nonce_field('nexus_translation_panel', 'nexus_translation_panel_nonce');
        
        $auto_translate = get_post_meta($post->ID, '_nexus_auto_translate', true);
        $selected_languages = get_post_meta($post->ID, '_nexus_target_languages', true) ?: array();
        
        $language_settings = get_option('nexus_translator_language_settings', array());
        $target_languages = $language_settings['target_languages'] ?? array('en');
        $source_language = $language_settings['source_language'] ?? 'fr';
        
        // Ensure post has source language set
        $current_language = $this->post_linker->get_post_language($post->ID);
        if (!$current_language) {
            update_post_meta($post->ID, '_nexus_language', $source_language);
            $current_language = $source_language;
        }
        
        $translations = $this->post_linker->get_all_translations($post->ID);
        
        ?>
        <div id="nexus-translation-panel">
            
            <!-- Current Language Display -->
            <div class="nexus-panel-section nexus-current-lang">
                <strong><?php _e('Post Language:', 'nexus-ai-wp-translator'); ?></strong>
                <span class="nexus-language-badge">
                    <?php echo $this->language_manager->get_language_flag($current_language); ?>
                    <?php echo esc_html($this->language_manager->get_language_name($current_language)); ?>
                </span>
            </div>
            
            <!-- Auto Translation Setting -->
            <div class="nexus-panel-section">
                <label class="nexus-auto-translate-label">
                    <input type="checkbox" 
                           name="nexus_auto_translate" 
                           value="1" 
                           <?php checked($auto_translate, '1'); ?>>
                    <strong><?php _e('Translate automatically on publish', 'nexus-ai-wp-translator'); ?></strong>
                </label>
                <p class="nexus-help-text">
                    <?php _e('When enabled, this post will be automatically translated to selected languages when published.', 'nexus-ai-wp-translator'); ?>
                </p>
            </div>
            
            <!-- Language Selection -->
            <div class="nexus-panel-section">
                <h4><?php _e('Target Languages', 'nexus-ai-wp-translator'); ?></h4>
                <div class="nexus-language-checkboxes">
                    <?php foreach ($target_languages as $lang_code): ?>
                        <?php if ($lang_code !== $current_language): ?>
                            <?php
                            $is_translated = isset($translations[$lang_code]);
                            $is_selected = in_array($lang_code, $selected_languages);
                            $status = $is_translated ? $this->post_linker->get_translation_status($translations[$lang_code]) : '';
                            ?>
                            <label class="nexus-language-option <?php echo $is_translated ? 'nexus-translated' : ''; ?>">
                                <input type="checkbox" 
                                       name="nexus_target_languages[]" 
                                       value="<?php echo esc_attr($lang_code); ?>"
                                       <?php checked($is_selected, true); ?>
                                       <?php echo $is_translated && $status === 'completed' ? 'data-retranslate="true"' : ''; ?>>
                                
                                <span class="nexus-lang-display">
                                    <span class="nexus-flag"><?php echo $this->language_manager->get_language_flag($lang_code); ?></span>
                                    <span class="nexus-lang-name"><?php echo $this->language_manager->get_language_name($lang_code); ?></span>
                                    
                                    <?php if ($is_translated): ?>
                                        <span class="nexus-status nexus-status-<?php echo esc_attr($status); ?>">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                        <a href="<?php echo get_edit_post_link($translations[$lang_code]); ?>" 
                                           target="_blank" 
                                           class="nexus-edit-link"
                                           title="<?php _e('Edit translation', 'nexus-ai-wp-translator'); ?>">
                                            ✏️
                                        </a>
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Manual Translation Button -->
            <div class="nexus-panel-section">
                <button type="button" 
                        id="nexus-manual-translate" 
                        class="button button-primary nexus-full-width">
                    <?php _e('Translate Now', 'nexus-ai-wp-translator'); ?>
                </button>
                <p class="nexus-help-text">
                    <?php _e('Manually translate to selected languages without saving the post.', 'nexus-ai-wp-translator'); ?>
                </p>
            </div>
            
            <!-- Translation Statistics -->
            <?php if (!empty($translations) && count($translations) > 1): ?>
            <div class="nexus-panel-section nexus-stats-section">
                <h4><?php _e('Translation Status', 'nexus-ai-wp-translator'); ?></h4>
                <div class="nexus-translation-stats">
                    <?php foreach ($translations as $lang => $trans_id): ?>
                        <?php if ($trans_id !== $post->ID): ?>
                            <?php
                            $trans_post = get_post($trans_id);
                            $status = $this->post_linker->get_translation_status($trans_id);
                            ?>
                            <div class="nexus-stat-item nexus-status-<?php echo esc_attr($status); ?>">
                                <span class="nexus-stat-lang">
                                    <?php echo $this->language_manager->get_language_flag($lang); ?>
                                    <?php echo $this->language_manager->get_language_name($lang); ?>
                                </span>
                                <span class="nexus-stat-status"><?php echo ucfirst($status); ?></span>
                                <div class="nexus-stat-actions">
                                    <a href="<?php echo get_edit_post_link($trans_id); ?>" target="_blank">Edit</a>
                                    <a href="<?php echo get_permalink($trans_id); ?>" target="_blank">View</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Progress Indicator -->
            <div id="nexus-panel-progress" style="display: none;">
                <div class="nexus-progress-bar">
                    <div class="nexus-progress-fill"></div>
                </div>
                <p class="nexus-progress-text"><?php _e('Translating...', 'nexus-ai-wp-translator'); ?></p>
            </div>
            
            <!-- Results -->
            <div id="nexus-panel-results" style="display: none;"></div>
        </div>
        
        <style>
        #nexus-translation-panel {
            font-size: 13px;
        }
        
        .nexus-panel-section {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .nexus-panel-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .nexus-current-lang {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            border-left: 3px solid #0073aa;
        }
        
        .nexus-auto-translate-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            margin-bottom: 8px;
        }
        
        .nexus-auto-translate-label input[type="checkbox"] {
            margin: 0;
        }
        
        .nexus-help-text {
            font-size: 12px;
            color: #666;
            margin: 5px 0 0 0;
            line-height: 1.4;
        }
        
        .nexus-language-checkboxes {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .nexus-language-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            background: #f9f9f9;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .nexus-language-option:hover {
            background: #f0f8ff;
        }
        
        .nexus-language-option.nexus-translated {
            background: #f0f8f0;
            border-left: 3px solid #46b450;
        }
        
        .nexus-language-option input[type="checkbox"] {
            margin: 0;
        }
        
        .nexus-lang-display {
            display: flex;
            align-items: center;
            gap: 6px;
            flex: 1;
        }
        
        .nexus-flag {
            font-size: 16px;
        }
        
        .nexus-lang-name {
            flex: 1;
            font-weight: 500;
        }
        
        .nexus-status {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 8px;
            font-weight: 600;
            text-transform: uppercase;
            color: white;
        }
        
        .nexus-status-completed {
            background: #46b450;
        }
        
        .nexus-status-outdated {
            background: #ffb900;
        }
        
        .nexus-status-error {
            background: #dc3232;
        }
        
        .nexus-status-pending {
            background: #0073aa;
        }
        
        .nexus-edit-link {
            text-decoration: none;
            font-size: 12px;
        }
        
        .nexus-full-width {
            width: 100%;
            text-align: center;
            padding: 8px;
        }
        
        .nexus-stats-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin: 15px -15px 0 -15px;
        }
        
        .nexus-translation-stats {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .nexus-stat-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px;
            background: white;
            border-radius: 3px;
            border-left: 3px solid #ddd;
        }
        
        .nexus-stat-item.nexus-status-completed {
            border-left-color: #46b450;
        }
        
        .nexus-stat-item.nexus-status-outdated {
            border-left-color: #ffb900;
        }
        
        .nexus-stat-item.nexus-status-error {
            border-left-color: #dc3232;
        }
        
        .nexus-stat-lang {
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }
        
        .nexus-stat-status {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
        }
        
        .nexus-stat-actions {
            display: flex;
            gap: 8px;
        }
        
        .nexus-stat-actions a {
            font-size: 11px;
            text-decoration: none;
            padding: 2px 6px;
            background: #f0f0f0;
            border-radius: 3px;
        }
        
        #nexus-panel-progress {
            padding: 15px;
            background: #f0f8ff;
            border-radius: 4px;
            margin-top: 15px;
        }
        
        .nexus-progress-bar {
            width: 100%;
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 8px;
        }
        
        .nexus-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #0073aa, #005177);
            border-radius: 3px;
            animation: nexus-progress 2s linear infinite;
            width: 30%;
        }
        
        @keyframes nexus-progress {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(350%); }
        }
        
        .nexus-progress-text {
            margin: 0;
            font-size: 12px;
            color: #0073aa;
            text-align: center;
        }
        
        #nexus-panel-results {
            padding: 12px;
            border-radius: 4px;
            margin-top: 15px;
        }
        
        #nexus-panel-results.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        #nexus-panel-results.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        </style>
        <?php
    }
    
    private function render_api_not_configured() {
        ?>
        <div class="nexus-panel-warning">
            <p><strong><?php _e('API Not Configured', 'nexus-ai-wp-translator'); ?></strong></p>
            <p><?php _e('Please configure your Claude API key in', 'nexus-ai-wp-translator'); ?> 
               <a href="<?php echo admin_url('admin.php?page=nexus-translator-settings'); ?>" target="_blank">
                   <?php _e('settings', 'nexus-ai-wp-translator'); ?>
               </a>
            </p>
        </div>
        <style>
        .nexus-panel-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 15px;
            color: #856404;
        }
        .nexus-panel-warning p {
            margin: 0 0 8px 0;
        }
        .nexus-panel-warning p:last-child {
            margin-bottom: 0;
        }
        </style>
        <?php
    }
    
    public function handle_auto_translation($post_id, $post) {
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        if (!isset($_POST['nexus_translation_panel_nonce']) || 
            !wp_verify_nonce($_POST['nexus_translation_panel_nonce'], 'nexus_translation_panel')) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        $auto_translate = isset($_POST['nexus_auto_translate']) ? '1' : '0';
        $target_languages = isset($_POST['nexus_target_languages']) ? $_POST['nexus_target_languages'] : array();
        
        update_post_meta($post_id, '_nexus_auto_translate', $auto_translate);
        update_post_meta($post_id, '_nexus_target_languages', $target_languages);
        
        // Only auto-translate when publishing for the first time
        if ($auto_translate === '1' && !empty($target_languages) && $post->post_status === 'publish') {
            // Check if this is a new publish (not an update)
            $is_new_publish = !get_post_meta($post_id, '_nexus_published_before', true);
            
            if ($is_new_publish) {
                update_post_meta($post_id, '_nexus_published_before', '1');
                $this->perform_auto_translation($post_id, $target_languages);
            }
        }
    }
    
    private function perform_auto_translation($post_id, $target_languages) {
        $translator = new Nexus_Translator();
        $results = array();
        
        // Get current language
        $current_language = $this->post_linker->get_post_language($post_id);
        
        foreach ($target_languages as $target_lang) {
            // Skip if target language is same as source
            if ($target_lang === $current_language) {
                continue;
            }
            
            // Skip if translation already exists
            if ($this->post_linker->has_translation($post_id, $target_lang)) {
                continue;
            }
            
            $result = $translator->translate_post($post_id, $target_lang);
            
            if ($result['success']) {
                // Publish the translation immediately for auto-translate
                wp_update_post(array(
                    'ID' => $result['translated_post_id'],
                    'post_status' => 'publish'
                ));
                
                $results[] = array(
                    'success' => true,
                    'language' => $target_lang,
                    'post_id' => $result['translated_post_id'],
                    'edit_link' => $result['edit_link'],
                    'view_link' => $result['view_link']
                );
            } else {
                $results[] = array(
                    'success' => false,
                    'language' => $target_lang,
                    'error' => $result['error']
                );
            }
        }
        
        if (!empty($results)) {
            update_post_meta($post_id, '_nexus_last_translation_results', $results);
            update_post_meta($post_id, '_nexus_translation_timestamp', current_time('timestamp'));
        }
    }
    
    public function show_translation_results() {
        global $post;
        
        if (!$post || !in_array($post->post_type, array('post', 'page'))) {
            return;
        }
        
        $results = get_post_meta($post->ID, '_nexus_last_translation_results', true);
        $timestamp = get_post_meta($post->ID, '_nexus_translation_timestamp', true);
        
        if (!$results || !$timestamp) {
            return;
        }
        
        // Only show results for recent translations (last 5 minutes)
        if ((current_time('timestamp') - $timestamp) > 300) {
            delete_post_meta($post->ID, '_nexus_last_translation_results');
            delete_post_meta($post->ID, '_nexus_translation_timestamp');
            return;
        }
        
        $success_count = count(array_filter($results, function($r) { return $r['success']; }));
        $total_count = count($results);
        
        ?>
        <div class="notice notice-success is-dismissible nexus-translation-notice">
            <h3><?php _e('Translation Results', 'nexus-ai-wp-translator'); ?></h3>
            <p><strong><?php printf(__('%d of %d translations completed successfully', 'nexus-ai-wp-translator'), $success_count, $total_count); ?></strong></p>
            
            <?php foreach ($results as $result): ?>
                <div class="nexus-result-item">
                    <?php if ($result['success']): ?>
                        <span class="nexus-result-success">✅</span>
                        <strong><?php echo $this->language_manager->get_language_name($result['language']); ?>:</strong>
                        <a href="<?php echo $result['edit_link']; ?>" target="_blank"><?php _e('Edit', 'nexus-ai-wp-translator'); ?></a> |
                        <a href="<?php echo $result['view_link']; ?>" target="_blank"><?php _e('View', 'nexus-ai-wp-translator'); ?></a>
                    <?php else: ?>
                        <span class="nexus-result-error">❌</span>
                        <strong><?php echo $this->language_manager->get_language_name($result['language']); ?>:</strong>
                        <span class="nexus-error-msg"><?php echo esc_html($result['error']); ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <style>
            .nexus-translation-notice {
                border-left: 4px solid #46b450;
            }
            .nexus-result-item {
                display: flex;
                align-items: center;
                gap: 8px;
                margin: 5px 0;
                font-size: 13px;
            }
            .nexus-result-success, .nexus-result-error {
                font-size: 16px;
            }
            .nexus-error-msg {
                color: #dc3232;
                font-style: italic;
            }
            </style>
        </div>
        
        <?php
        // Clear the results after showing
        delete_post_meta($post->ID, '_nexus_last_translation_results');
        delete_post_meta($post->ID, '_nexus_translation_timestamp');
    }
    
    public function add_publish_screen_info() {
        global $post;
        
        if (!$post || !in_array($post->post_type, array('post', 'page'))) {
            return;
        }
        
        $auto_translate = get_post_meta($post->ID, '_nexus_auto_translate', true);
        $selected_languages = get_post_meta($post->ID, '_nexus_target_languages', true) ?: array();
        
        if ($auto_translate && !empty($selected_languages)) {
            ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Add translation info to publish confirmation screen
                const publishButton = document.querySelector('#publish');
                const prePublishPanel = document.querySelector('.editor-post-publish-panel');
                
                if (prePublishPanel) {
                    const translationInfo = document.createElement('div');
                    translationInfo.style.cssText = 'background: #f0f8ff; border: 1px solid #0073aa; border-radius: 4px; padding: 12px; margin: 12px 0;';
                    translationInfo.innerHTML = `
                        <h4 style="margin: 0 0 8px 0; color: #0073aa;">🌍 Auto-Translation Enabled</h4>
                        <p style="margin: 0; font-size: 13px;">This post will be automatically translated to: <strong><?php echo implode(', ', array_map(array($this->language_manager, 'get_language_name'), $selected_languages)); ?></strong></p>
                        <p style="margin: 5px 0 0 0; font-size: 12px; color: #666;">Translations will be created as drafts for review after publishing.</p>
                    `;
                    
                    // Insert before publish button
                    const publishSection = prePublishPanel.querySelector('.editor-post-publish-panel__header');
                    if (publishSection) {
                        publishSection.parentNode.insertBefore(translationInfo, publishSection.nextSibling);
                    }
                }
            });
            </script>
            <?php
        }
    }
}