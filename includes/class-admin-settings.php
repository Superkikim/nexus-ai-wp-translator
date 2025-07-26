<?php
/**
 * File: class-admin-settings.php
 * Location: /includes/class-admin-settings.php
 * 
 * Admin Settings Handler
 * Handles settings form, validation, and WordPress Settings API integration.
 */

namespace Nexus\Translator;

class Admin_Settings {
    
    private $admin;
    private $settings = array();
    
    public function __construct($admin) {
        $this->admin = $admin;
        $this->load_settings();
    }
    
    public function register_hooks() {
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    private function load_settings() {
        $defaults = array(
            'api_key' => '',
            'source_language' => 'en',
            'target_languages' => array('fr', 'es', 'de'),
            'auto_translate' => false,
            'translation_quality' => 'standard',
            'enable_analytics' => true,
            'enable_emergency_mode' => true,
        );
        
        $saved = get_option('nexus_ai_translator_settings', array());
        $this->settings = wp_parse_args($saved, $defaults);
    }
    
    public function register_settings() {
        register_setting(
            'nexus_ai_translator_settings',
            'nexus_ai_translator_settings',
            array('sanitize_callback' => array($this, 'sanitize_settings'))
        );
        
        // API Section
        add_settings_section(
            'nexus_api_section',
            __('API Configuration', 'nexus-ai-wp-translator'),
            array($this, 'render_api_section'),
            'nexus-ai-translator'
        );
        
        add_settings_field(
            'api_key',
            __('Claude AI API Key', 'nexus-ai-wp-translator'),
            array($this, 'render_api_key_field'),
            'nexus-ai-translator',
            'nexus_api_section'
        );
        
        // Language Section
        add_settings_section(
            'nexus_language_section',
            __('Language Configuration', 'nexus-ai-wp-translator'),
            array($this, 'render_language_section'),
            'nexus-ai-translator'
        );
        
        add_settings_field(
            'source_language',
            __('Source Language', 'nexus-ai-wp-translator'),
            array($this, 'render_source_language_field'),
            'nexus-ai-translator',
            'nexus_language_section'
        );
        
        add_settings_field(
            'target_languages',
            __('Target Languages', 'nexus-ai-wp-translator'),
            array($this, 'render_target_languages_field'),
            'nexus-ai-translator',
            'nexus_language_section'
        );
        
        // Options Section
        add_settings_section(
            'nexus_options_section',
            __('Translation Options', 'nexus-ai-wp-translator'),
            array($this, 'render_options_section'),
            'nexus-ai-translator'
        );
        
        add_settings_field(
            'auto_translate',
            __('Auto Translate', 'nexus-ai-wp-translator'),
            array($this, 'render_auto_translate_field'),
            'nexus-ai-translator',
            'nexus_options_section'
        );
        
        add_settings_field(
            'translation_quality',
            __('Translation Quality', 'nexus-ai-wp-translator'),
            array($this, 'render_quality_field'),
            'nexus-ai-translator',
            'nexus_options_section'
        );
        
        // Advanced Section
        add_settings_section(
            'nexus_advanced_section',
            __('Advanced Options', 'nexus-ai-wp-translator'),
            array($this, 'render_advanced_section'),
            'nexus-ai-translator'
        );
        
        add_settings_field(
            'enable_analytics',
            __('Enable Analytics', 'nexus-ai-wp-translator'),
            array($this, 'render_analytics_field'),
            'nexus-ai-translator',
            'nexus_advanced_section'
        );
        
        add_settings_field(
            'enable_emergency_mode',
            __('Enable Emergency Mode', 'nexus-ai-wp-translator'),
            array($this, 'render_emergency_field'),
            'nexus-ai-translator',
            'nexus_advanced_section'
        );
    }
    
    public function sanitize_settings($input) {
        $sanitized = array();
        
        $sanitized['api_key'] = sanitize_text_field($input['api_key'] ?? '');
        $sanitized['source_language'] = sanitize_text_field($input['source_language'] ?? 'en');
        
        $target_languages = $input['target_languages'] ?? array();
        if (is_array($target_languages)) {
            $sanitized['target_languages'] = array_map('sanitize_text_field', $target_languages);
        } else {
            $sanitized['target_languages'] = array('fr', 'es', 'de');
        }
        
        $sanitized['auto_translate'] = isset($input['auto_translate']) ? (bool) $input['auto_translate'] : false;
        $sanitized['enable_analytics'] = isset($input['enable_analytics']) ? (bool) $input['enable_analytics'] : true;
        $sanitized['enable_emergency_mode'] = isset($input['enable_emergency_mode']) ? (bool) $input['enable_emergency_mode'] : true;
        
        $valid_qualities = array('fast', 'standard', 'premium');
        $sanitized['translation_quality'] = in_array($input['translation_quality'] ?? '', $valid_qualities) ? 
            $input['translation_quality'] : 'standard';
        
        // Validate using language module
        $languages = $this->admin->get_languages();
        if ($languages && method_exists($languages, 'get_validator')) {
            $validator = $languages->get_validator();
            if ($validator) {
                $validation = $validator->validate_settings($sanitized);
                if (!$validation['valid']) {
                    foreach ($validation['errors'] as $field => $error) {
                        add_settings_error('nexus_ai_translator_settings', $field, $error, 'error');
                    }
                } else {
                    $sanitized = $validation['cleaned'];
                }
            }
        }
        
        // Test API connection if key changed
        if (!empty($sanitized['api_key']) && $sanitized['api_key'] !== $this->settings['api_key']) {
            $this->test_api_connection($sanitized['api_key']);
        }
        
        do_action('nexus_analytics_event', 'settings_updated', array(
            'changes' => array_diff_assoc($sanitized, $this->settings),
            'timestamp' => current_time('mysql'),
        ));
        
        return $sanitized;
    }
    
    public function render_settings_form() {
        ?>
        <form action="options.php" method="post">
            <?php
            settings_fields('nexus_ai_translator_settings');
            do_settings_sections('nexus-ai-translator');
            submit_button(__('Save Settings', 'nexus-ai-wp-translator'));
            ?>
        </form>
        <?php
    }
    
    public function render_api_section() {
        echo '<p>' . esc_html__('Configure your Claude AI API key to enable translation services.', 'nexus-ai-wp-translator') . '</p>';
    }
    
    public function render_api_key_field() {
        $value = $this->settings['api_key'];
        $masked = !empty($value) ? str_repeat('*', strlen($value) - 4) . substr($value, -4) : '';
        ?>
        <input type="password" 
               id="api_key" 
               name="nexus_ai_translator_settings[api_key]" 
               value="<?php echo esc_attr($value); ?>"
               placeholder="<?php echo esc_attr($masked); ?>"
               class="regular-text nexus-api-key" />
        <button type="button" class="button button-secondary nexus-toggle-api-key">
            <?php esc_html_e('Show', 'nexus-ai-wp-translator'); ?>
        </button>
        <p class="description">
            <?php 
            printf(
                esc_html__('Get your API key from %s', 'nexus-ai-wp-translator'),
                '<a href="https://console.anthropic.com/" target="_blank">Claude AI Console</a>'
            );
            ?>
        </p>
        <?php
    }
    
    public function render_language_section() {
        echo '<p>' . esc_html__('Configure source and target languages for translation.', 'nexus-ai-wp-translator') . '</p>';
    }
    
    public function render_source_language_field() {
        $value = $this->settings['source_language'];
        $languages = $this->admin->get_languages() ? $this->admin->get_languages()->get_language_dropdown_options() : array();
        ?>
        <select id="source_language" name="nexus_ai_translator_settings[source_language]" class="regular-text">
            <?php foreach ($languages as $code => $name) : ?>
                <option value="<?php echo esc_attr($code); ?>" <?php selected($value, $code); ?>>
                    <?php echo esc_html($name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php esc_html_e('The primary language of your content.', 'nexus-ai-wp-translator'); ?></p>
        <?php
    }
    
    public function render_target_languages_field() {
        $selected = $this->settings['target_languages'];
        $languages = $this->admin->get_languages() ? $this->admin->get_languages()->get_language_dropdown_options() : array();
        ?>
        <div class="nexus-target-languages">
            <?php foreach ($languages as $code => $name) : ?>
                <?php if ($code !== $this->settings['source_language']) : ?>
                    <label>
                        <input type="checkbox" 
                               name="nexus_ai_translator_settings[target_languages][]" 
                               value="<?php echo esc_attr($code); ?>"
                               <?php checked(in_array($code, $selected)); ?> />
                        <?php echo esc_html($name); ?>
                    </label>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <p class="description"><?php esc_html_e('Select languages to translate your content into.', 'nexus-ai-wp-translator'); ?></p>
        <?php
    }
    
    public function render_options_section() {
        echo '<p>' . esc_html__('Configure translation behavior and quality settings.', 'nexus-ai-wp-translator') . '</p>';
    }
    
    public function render_auto_translate_field() {
        $value = $this->settings['auto_translate'];
        ?>
        <label>
            <input type="checkbox" 
                   id="auto_translate" 
                   name="nexus_ai_translator_settings[auto_translate]" 
                   value="1" 
                   <?php checked($value); ?> />
            <?php esc_html_e('Automatically translate new posts', 'nexus-ai-wp-translator'); ?>
        </label>
        <p class="description"><?php esc_html_e('When enabled, new posts will be automatically translated to all target languages.', 'nexus-ai-wp-translator'); ?></p>
        <?php
    }
    
    public function render_quality_field() {
        $value = $this->settings['translation_quality'];
        $qualities = array(
            'fast' => __('Fast (Claude Haiku)', 'nexus-ai-wp-translator'),
            'standard' => __('Standard (Claude Sonnet)', 'nexus-ai-wp-translator'),
            'premium' => __('Premium (Claude Opus)', 'nexus-ai-wp-translator'),
        );
        ?>
        <select id="translation_quality" name="nexus_ai_translator_settings[translation_quality]" class="regular-text">
            <?php foreach ($qualities as $quality_value => $quality_name) : ?>
                <option value="<?php echo esc_attr($quality_value); ?>" <?php selected($value, $quality_value); ?>>
                    <?php echo esc_html($quality_name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php esc_html_e('Higher quality uses more advanced AI models but costs more.', 'nexus-ai-wp-translator'); ?></p>
        <?php
    }
    
    public function render_advanced_section() {
        echo '<p>' . esc_html__('Advanced options for power users.', 'nexus-ai-wp-translator') . '</p>';
    }
    
    public function render_analytics_field() {
        $value = $this->settings['enable_analytics'];
        ?>
        <label>
            <input type="checkbox" 
                   id="enable_analytics" 
                   name="nexus_ai_translator_settings[enable_analytics]" 
                   value="1" 
                   <?php checked($value); ?> />
            <?php esc_html_e('Enable usage analytics', 'nexus-ai-wp-translator'); ?>
        </label>
        <p class="description"><?php esc_html_e('Collect anonymous usage statistics to improve the plugin.', 'nexus-ai-wp-translator'); ?></p>
        <?php
    }
    
    public function render_emergency_field() {
        $value = $this->settings['enable_emergency_mode'];
        ?>
        <label>
            <input type="checkbox" 
                   id="enable_emergency_mode" 
                   name="nexus_ai_translator_settings[enable_emergency_mode]" 
                   value="1" 
                   <?php checked($value); ?> />
            <?php esc_html_e('Enable emergency mode protection', 'nexus-ai-wp-translator'); ?>
        </label>
        <p class="description"><?php esc_html_e('Automatically disable translations during API errors to prevent issues.', 'nexus-ai-wp-translator'); ?></p>
        <?php
    }
    
    private function test_api_connection($api_key) {
        $api = $this->admin->get_api();
        if ($api) {
            $result = $api->authenticate($api_key);
            
            if ($result['success']) {
                add_settings_error(
                    'nexus_ai_translator_settings',
                    'api_connected',
                    __('API connection successful!', 'nexus-ai-wp-translator'),
                    'updated'
                );
            } else {
                add_settings_error(
                    'nexus_ai_translator_settings',
                    'api_failed',
                    sprintf(__('API connection failed: %s', 'nexus-ai-wp-translator'), $result['message']),
                    'error'
                );
            }
        }
    }
    
    public function get_settings() {
        return $this->settings;
    }
}