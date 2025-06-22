<?php
/**
 * File: class-translator-admin.php
 * Location: /includes/class-translator-admin.php
 * 
 * Translator Admin Class
 * 
 * Handles admin interface and settings
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translator_Admin {
    
    /**
     * Render preserve data field
     */
    public function render_preserve_data_field() {
        $preserve_data = get_option('nexus_translator_preserve_on_uninstall', false);
        
        echo '<input type="checkbox" id="preserve_on_uninstall" name="nexus_translator_preserve_on_uninstall" value="1"' . checked($preserve_data, true, false) . '> ';
        echo '<label for="preserve_on_uninstall">' . __('Keep translation data when uninstalling plugin', 'nexus-ai-wp-translator') . '</label>';
        echo '<p class="description">' . __('If checked, translation relationships will be preserved when the plugin is uninstalled. This allows you to reinstall later without losing translation connections. If unchecked, all translation data will be completely removed.', 'nexus-ai-wp-translator') . '</p>';
    }
    
    /**
     * Language manager instance
     */
    private $language_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->language_manager = new Language_Manager();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Settings registration
        add_action('admin_init', array($this, 'register_settings'));
        
        // Admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Post save hook for auto-translation popup
        add_action('save_post', array($this, 'maybe_show_translation_popup'), 10, 2);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('Nexus AI Translator Settings', 'nexus-ai-wp-translator'),
            __('AI Translator', 'nexus-ai-wp-translator'),
            'manage_options',
            'nexus-translator-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // API Settings Section
        add_settings_section(
            'nexus_translator_api_section',
            __('Claude AI API Settings', 'nexus-ai-wp-translator'),
            array($this, 'render_api_section_description'),
            'nexus_translator_api_settings'
        );
        
        register_setting('nexus_translator_api_settings', 'nexus_translator_api_settings', array(
            'sanitize_callback' => array($this, 'sanitize_api_settings')
        ));
        
        add_settings_field(
            'claude_api_key',
            __('Claude API Key', 'nexus-ai-wp-translator'),
            array($this, 'render_api_key_field'),
            'nexus_translator_api_settings',
            'nexus_translator_api_section'
        );
        
        add_settings_field(
            'model',
            __('Claude Model', 'nexus-ai-wp-translator'),
            array($this, 'render_model_field'),
            'nexus_translator_api_settings',
            'nexus_translator_api_section'
        );
        
        // Language Settings Section
        add_settings_section(
            'nexus_translator_language_section',
            __('Language Settings', 'nexus-ai-wp-translator'),
            array($this, 'render_language_section_description'),
            'nexus_translator_language_settings'
        );
        
        register_setting('nexus_translator_language_settings', 'nexus_translator_language_settings', array(
            'sanitize_callback' => array($this, 'sanitize_language_settings')
        ));
        
        add_settings_field(
            'source_language',
            __('Source Language', 'nexus-ai-wp-translator'),
            array($this, 'render_source_language_field'),
            'nexus_translator_language_settings',
            'nexus_translator_language_section'
        );
        
        add_settings_field(
            'target_languages',
            __('Target Languages', 'nexus-ai-wp-translator'),
            array($this, 'render_target_languages_field'),
            'nexus_translator_language_settings',
            'nexus_translator_language_section'
        );
        
        // General Settings Section
        add_settings_section(
            'nexus_translator_general_section',
            __('General Settings', 'nexus-ai-wp-translator'),
            array($this, 'render_general_section_description'),
            'nexus_translator_general_settings'
        );
        
        register_setting('nexus_translator_options', 'nexus_translator_options', array(
            'sanitize_callback' => array($this, 'sanitize_general_settings')
        ));
        
        add_settings_field(
            'show_popup',
            __('Show Translation Popup', 'nexus-ai-wp-translator'),
            array($this, 'render_show_popup_field'),
            'nexus_translator_general_settings',
            'nexus_translator_general_section'
        );
        
        add_settings_field(
            'debug_mode',
            __('Debug Mode', 'nexus-ai-wp-translator'),
            array($this, 'render_debug_mode_field'),
            'nexus_translator_general_settings',
            'nexus_translator_general_section'
        );
        
        add_settings_field(
            'preserve_on_uninstall',
            __('Preserve Data on Uninstall', 'nexus-ai-wp-translator'),
            array($this, 'render_preserve_data_field'),
            'nexus_translator_general_settings',
            'nexus_translator_general_section'
        );
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (isset($_GET['tab'])) {
            $active_tab = sanitize_text_field($_GET['tab']);
        } else {
            $active_tab = 'api';
        }
        
        include NEXUS_TRANSLATOR_ADMIN_DIR . 'views/admin-page.php';
    }
    
    /**
     * Render API section description
     */
    public function render_api_section_description() {
        echo '<p>' . __('Configure your Claude AI API settings. You need a valid API key from Anthropic to use translation features.', 'nexus-ai-wp-translator') . '</p>';
        echo '<p><a href="https://console.anthropic.com/" target="_blank">' . __('Get your API key from Anthropic Console', 'nexus-ai-wp-translator') . '</a></p>';
    }
    
    /**
     * Render language section description
     */
    public function render_language_section_description() {
        echo '<p>' . __('Configure the source and target languages for translation.', 'nexus-ai-wp-translator') . '</p>';
    }
    
    /**
     * Render general section description
     */
    public function render_general_section_description() {
        echo '<p>' . __('General plugin settings and behavior options.', 'nexus-ai-wp-translator') . '</p>';
    }
    
    /**
     * Render API key field
     */
    public function render_api_key_field() {
        $settings = get_option('nexus_translator_api_settings', array());
        $api_key = $settings['claude_api_key'] ?? '';
        
        echo '<input type="password" id="claude_api_key" name="nexus_translator_api_settings[claude_api_key]" value="' . esc_attr($api_key) . '" class="regular-text" />';
        echo '<button type="button" id="test-api-connection" class="button" style="margin-left: 10px;">' . __('Test Connection', 'nexus-ai-wp-translator') . '</button>';
        echo '<div id="api-test-result" style="margin-top: 10px;"></div>';
        
        if (!empty($api_key)) {
            echo '<p class="description">' . __('API key is configured. Click "Test Connection" to verify.', 'nexus-ai-wp-translator') . '</p>';
        } else {
            echo '<p class="description">' . __('Enter your Claude API key from Anthropic Console.', 'nexus-ai-wp-translator') . '</p>';
        }
    }
    
    /**
     * Render model field
     */
    public function render_model_field() {
        $settings = get_option('nexus_translator_api_settings', array());
        $model = $settings['model'] ?? 'claude-sonnet-4-20250514';
        
        $models = array(
            'claude-sonnet-4-20250514' => 'Claude Sonnet 4 (Recommended)',
            'claude-opus-4-20250514' => 'Claude Opus 4 (Most Capable)',
        );
        
        echo '<select id="model" name="nexus_translator_api_settings[model]">';
        foreach ($models as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($model, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Choose the Claude model to use for translations.', 'nexus-ai-wp-translator') . '</p>';
    }
    
    /**
     * Render source language field
     */
    public function render_source_language_field() {
        $settings = get_option('nexus_translator_language_settings', array());
        $source_language = $settings['source_language'] ?? 'fr';
        
        $languages = $this->language_manager->get_languages_for_select();
        
        echo '<select id="source_language" name="nexus_translator_language_settings[source_language]">';
        foreach ($languages as $code => $name) {
            echo '<option value="' . esc_attr($code) . '"' . selected($source_language, $code, false) . '>' . esc_html($name) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('The primary language of your content.', 'nexus-ai-wp-translator') . '</p>';
    }
    
    /**
     * Render target languages field
     */
    public function render_target_languages_field() {
        $settings = get_option('nexus_translator_language_settings', array());
        $target_languages = $settings['target_languages'] ?? array('en');
        
        $languages = $this->language_manager->get_languages_for_select();
        
        echo '<div class="nexus-target-languages">';
        foreach ($languages as $code => $name) {
            $checked = in_array($code, $target_languages) ? 'checked' : '';
            echo '<label style="display: block; margin-bottom: 5px;">';
            echo '<input type="checkbox" name="nexus_translator_language_settings[target_languages][]" value="' . esc_attr($code) . '" ' . $checked . '> ';
            echo esc_html($name);
            echo '</label>';
        }
        echo '</div>';
        echo '<p class="description">' . __('Languages to translate content into.', 'nexus-ai-wp-translator') . '</p>';
    }
    
    /**
     * Render show popup field
     */
    public function render_show_popup_field() {
        $settings = get_option('nexus_translator_language_settings', array());
        $show_popup = $settings['show_popup'] ?? true;
        
        echo '<input type="checkbox" id="show_popup" name="nexus_translator_language_settings[show_popup]" value="1"' . checked($show_popup, true, false) . '> ';
        echo '<label for="show_popup">' . __('Show translation popup when saving posts', 'nexus-ai-wp-translator') . '</label>';
        echo '<p class="description">' . __('Automatically suggest translation when saving new posts or updates.', 'nexus-ai-wp-translator') . '</p>';
    }
    
    /**
     * Render debug mode field
     */
    public function render_debug_mode_field() {
        $settings = get_option('nexus_translator_options', array());
        $debug_mode = $settings['debug_mode'] ?? false;
        
        echo '<input type="checkbox" id="debug_mode" name="nexus_translator_options[debug_mode]" value="1"' . checked($debug_mode, true, false) . '> ';
        echo '<label for="debug_mode">' . __('Enable debug mode', 'nexus-ai-wp-translator') . '</label>';
        echo '<p class="description">' . __('Log API requests and responses for debugging. Only enable if needed.', 'nexus-ai-wp-translator') . '</p>';
    }
    
    /**
     * Sanitize API settings
     */
    public function sanitize_api_settings($input) {
        $sanitized = array();
        
        if (isset($input['claude_api_key'])) {
            $sanitized['claude_api_key'] = sanitize_text_field($input['claude_api_key']);
        }
        
        if (isset($input['model'])) {
            $allowed_models = array('claude-sonnet-4-20250514', 'claude-opus-4-20250514');
            if (in_array($input['model'], $allowed_models)) {
                $sanitized['model'] = $input['model'];
            }
        }
        
        if (isset($input['max_tokens'])) {
            $sanitized['max_tokens'] = max(100, min(8000, (int) $input['max_tokens']));
        }
        
        if (isset($input['temperature'])) {
            $sanitized['temperature'] = max(0, min(1, (float) $input['temperature']));
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize language settings
     */
    public function sanitize_language_settings($input) {
        $sanitized = array();
        
        if (isset($input['source_language'])) {
            if ($this->language_manager->is_valid_language_code($input['source_language'])) {
                $sanitized['source_language'] = $input['source_language'];
            }
        }
        
        if (isset($input['target_languages']) && is_array($input['target_languages'])) {
            $valid_targets = array();
            foreach ($input['target_languages'] as $lang) {
                if ($this->language_manager->is_valid_language_code($lang)) {
                    $valid_targets[] = $lang;
                }
            }
            $sanitized['target_languages'] = $valid_targets;
        }
        
        if (isset($input['show_popup'])) {
            $sanitized['show_popup'] = (bool) $input['show_popup'];
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize general settings
     */
    public function sanitize_general_settings($input) {
        $sanitized = array();
        
        if (isset($input['debug_mode'])) {
            $sanitized['debug_mode'] = (bool) $input['debug_mode'];
        }
        
        if (isset($input['cache_translations'])) {
            $sanitized['cache_translations'] = (bool) $input['cache_translations'];
        }
        
        if (isset($input['show_language_switcher'])) {
            $sanitized['show_language_switcher'] = (bool) $input['show_language_switcher'];
        }
        
        // Handle preserve data setting separately
        if (isset($_POST['nexus_translator_preserve_on_uninstall'])) {
            update_option('nexus_translator_preserve_on_uninstall', true);
        } else {
            update_option('nexus_translator_preserve_on_uninstall', false);
        }
        
        return $sanitized;
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        // Check if API is configured
        $api_settings = get_option('nexus_translator_api_settings', array());
        if (empty($api_settings['claude_api_key']) && $this->is_translator_page()) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p>' . sprintf(
                __('Nexus AI Translator: Please configure your Claude API key in <a href="%s">settings</a> to start translating.', 'nexus-ai-wp-translator'),
                admin_url('admin.php?page=nexus-translator-settings')
            ) . '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Check if current page is translator related
     */
    private function is_translator_page() {
        $screen = get_current_screen();
        return $screen && (
            $screen->id === 'settings_page_nexus-translator-settings' ||
            in_array($screen->base, array('post', 'edit'))
        );
    }
    
    /**
     * Maybe show translation popup after save
     */
    public function maybe_show_translation_popup($post_id, $post) {
        // Skip auto-saves, revisions, and non-main post types
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        if (!in_array($post->post_type, array('post', 'page'))) {
            return;
        }
        
        // Check if popup should be shown
        $language_settings = get_option('nexus_translator_language_settings', array());
        if (empty($language_settings['show_popup'])) {
            return;
        }
        
        // Don't show popup if this is already a translation
        $post_linker = new Post_Linker();
        if ($post_linker->get_original_post_id($post_id)) {
            return; // This is a translation, not an original
        }
        
        // Get available target languages (languages not yet translated)
        $target_languages = $language_settings['target_languages'] ?? array('en');
        $translations = $post_linker->get_all_translations($post_id);
        $available_languages = array();
        
        foreach ($target_languages as $target_lang) {
            if (!isset($translations[$target_lang])) {
                $language_manager = new Language_Manager();
                $available_languages[] = array(
                    'code' => $target_lang,
                    'name' => $language_manager->get_language_name($target_lang),
                    'flag' => $language_manager->get_language_flag($target_lang)
                );
            }
        }
        
        // Only show popup if there are languages to translate to
        if (!empty($available_languages)) {
            add_action('admin_footer', function() use ($post_id, $available_languages) {
                ?>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    if (typeof TranslationPopup !== 'undefined') {
                        setTimeout(function() {
                            TranslationPopup.showPopup(<?php echo $post_id; ?>, <?php echo json_encode($available_languages); ?>);
                        }, 1000);
                    }
                });
                </script>
                <?php
            });
        }
    }
}