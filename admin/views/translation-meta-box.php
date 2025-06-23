<?php
/**
 * File: translation-meta-box.php
 * Location: /admin/views/translation-meta-box.php
 * 
 * Translation Meta Box View
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!isset($language_manager)) {
    $language_manager = new Language_Manager();
}
if (!isset($api)) {
    $api = new Translator_API();
}
if (!isset($post_linker)) {
    $post_linker = new Post_Linker();
}

$is_api_configured = $api->is_api_configured();
?>

<div id="nexus-translation-meta-box">
    
    <?php if (!$is_api_configured): ?>
        <div class="nexus-warning">
            <p><strong><?php _e('API Not Configured', 'nexus-ai-wp-translator'); ?></strong></p>
            <p><?php _e('Please configure your Claude API key in', 'nexus-ai-wp-translator'); ?> 
               <a href="<?php echo admin_url('admin.php?page=nexus-translator-settings'); ?>" target="_blank">
                   <?php _e('settings', 'nexus-ai-wp-translator'); ?>
               </a>
            </p>
        </div>
    <?php else: ?>
        
        <div class="nexus-current-language">
            <strong><?php _e('Current Language:', 'nexus-ai-wp-translator'); ?></strong>
            <?php if ($current_language): ?>
                <span class="nexus-language-badge">
                    <?php echo $language_manager->get_language_flag($current_language); ?>
                    <?php echo esc_html($language_manager->get_language_name($current_language)); ?>
                </span>
            <?php else: ?>
                <span class="nexus-no-language"><?php _e('Not set', 'nexus-ai-wp-translator'); ?></span>
                <button type="button" id="nexus-set-language" class="button button-small">
                    <?php _e('Set Language', 'nexus-ai-wp-translator'); ?>
                </button>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($translations) && count($translations) > 1): ?>
            <div class="nexus-translations-list">
                <strong><?php _e('Translations:', 'nexus-ai-wp-translator'); ?></strong>
                <ul>
                    <?php foreach ($translations as $language => $translated_post_id): ?>
                        <?php if ($translated_post_id !== $post->ID): ?>
                            <?php
                            $translated_post = get_post($translated_post_id);
                            $status = $post_linker->get_translation_status($translated_post_id);
                            $status_class = 'nexus-status-' . $status;
                            ?>
                            <li class="nexus-translation-item <?php echo esc_attr($status_class); ?>">
                                <?php echo $language_manager->get_language_flag($language); ?>
                                <a href="<?php echo get_edit_post_link($translated_post_id); ?>" target="_blank">
                                    <?php echo esc_html($language_manager->get_language_name($language)); ?>
                                </a>
                                <span class="nexus-status nexus-status-<?php echo esc_attr($status); ?>">
                                    <?php echo esc_html(ucfirst($status)); ?>
                                </span>
                                
                                <div class="nexus-translation-actions">
                                    <?php if ($status === 'outdated'): ?>
                                        <button type="button" 
                                                class="button button-small nexus-update-translation" 
                                                data-original-id="<?php echo $post->ID; ?>"
                                                data-translated-id="<?php echo $translated_post_id; ?>">
                                            <?php _e('Update', 'nexus-ai-wp-translator'); ?>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <a href="<?php echo get_permalink($translated_post_id); ?>" 
                                       target="_blank" 
                                       class="button button-small">
                                        <?php _e('View', 'nexus-ai-wp-translator'); ?>
                                    </a>
                                </div>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="nexus-translation-actions-section">
            <?php
            $language_settings = get_option('nexus_translator_language_settings', array());
            $target_languages = $language_settings['target_languages'] ?? array('en');
            $available_translations = array();
            
            foreach ($target_languages as $target_lang) {
                if (!isset($translations[$target_lang])) {
                    $available_translations[$target_lang] = $language_manager->get_language_name($target_lang);
                }
            }
            ?>
            
            <?php if (!empty($available_translations)): ?>
                <strong><?php _e('Translate to:', 'nexus-ai-wp-translator'); ?></strong>
                <div class="nexus-translate-buttons">
                    <?php foreach ($available_translations as $lang_code => $lang_name): ?>
                        <button type="button" 
                                class="button nexus-translate-btn" 
                                data-post-id="<?php echo $post->ID; ?>"
                                data-target-lang="<?php echo esc_attr($lang_code); ?>">
                            <?php echo $language_manager->get_language_flag($lang_code); ?>
                            <?php echo esc_html($lang_name); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="nexus-all-translated">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php _e('All configured languages are translated', 'nexus-ai-wp-translator'); ?>
                </p>
            <?php endif; ?>
        </div>
        
        <div id="nexus-translation-progress" class="nexus-progress" style="display: none;">
            <div class="nexus-progress-bar">
                <div class="nexus-progress-fill"></div>
            </div>
            <p class="nexus-progress-text"><?php _e('Preparing translation...', 'nexus-ai-wp-translator'); ?></p>
        </div>
        
        <div id="nexus-translation-result" class="nexus-result" style="display: none;"></div>
        
    <?php endif; ?>
    
</div>

<style>
#nexus-translation-meta-box {
    line-height: 1.5;
}

.nexus-warning {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 4px;
    padding: 10px;
    margin-bottom: 15px;
}

.nexus-warning p {
    margin: 0;
    color: #856404;
}

.nexus-current-language {
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.nexus-language-badge {
    display: inline-block;
    background: #f1f1f1;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    margin-left: 5px;
}

.nexus-no-language {
    color: #999;
    font-style: italic;
}

.nexus-translations-list {
    margin-bottom: 15px;
}

.nexus-translations-list ul {
    margin: 8px 0 0 0;
    padding: 0;
}

.nexus-translation-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px;
    margin-bottom: 5px;
    background: #f9f9f9;
    border-radius: 4px;
    border-left: 3px solid #ddd;
}

.nexus-translation-item.nexus-status-completed {
    border-left-color: #46b450;
}

.nexus-translation-item.nexus-status-outdated {
    border-left-color: #ffb900;
}

.nexus-translation-item.nexus-status-error {
    border-left-color: #dc3232;
}

.nexus-status {
    font-size: 11px;
    padding: 2px 6px;
    border-radius: 10px;
    font-weight: 500;
    text-transform: uppercase;
}

.nexus-status-completed {
    background: #46b450;
    color: white;
}

.nexus-status-outdated {
    background: #ffb900;
    color: white;
}

.nexus-status-error {
    background: #dc3232;
    color: white;
}

.nexus-status-pending {
    background: #0073aa;
    color: white;
}

.nexus-translation-actions {
    display: flex;
    gap: 5px;
}

.nexus-translation-actions-section {
    margin-top: 15px;
    padding-top: 10px;
    border-top: 1px solid #eee;
}

.nexus-translate-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-top: 8px;
}

.nexus-translate-btn {
    font-size: 12px;
    padding: 6px 12px;
    height: auto;
    line-height: 1.2;
}

.nexus-all-translated {
    color: #46b450;
    font-weight: 500;
    margin: 10px 0;
}

.nexus-all-translated .dashicons {
    color: #46b450;
    vertical-align: middle;
}

.nexus-progress {
    margin-top: 15px;
    padding: 10px;
    background: #f0f8ff;
    border: 1px solid #cce7ff;
    border-radius: 4px;
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
    animation: nexus-progress-animation 2s linear infinite;
    width: 30%;
}

@keyframes nexus-progress-animation {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(350%); }
}

.nexus-progress-text {
    margin: 0;
    font-size: 13px;
    color: #0073aa;
}

.nexus-result {
    margin-top: 15px;
    padding: 10px;
    border-radius: 4px;
}

.nexus-result.success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.nexus-result.error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.nexus-result p {
    margin: 0;
}

.nexus-result a {
    color: inherit;
    text-decoration: underline;
    font-weight: 500;
}
</style>