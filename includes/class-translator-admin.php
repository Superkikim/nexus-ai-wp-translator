<?php
/**
 * File: class-translator-admin.php
 * Location: /includes/class-translator-admin.php
 * 
 * Translator Admin Class - With Configurable API Limits
 * 
 * Handles admin interface and settings with API rate limiting configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translator_Admin {
    
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
        
        // AJAX handlers for admin
        add_action('wp_ajax_nexus_reset_rate_limits', array($this, 'handle_reset_rate_limits'));
        add_action('wp_ajax_nexus_reset_emergency', array($this, 'handle_reset_emergency'));
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
        
        add_settings_field(
            'max_tokens',
            __('Max Tokens', 'nexus-ai-wp-translator'),
            array($this, 'render_max_tokens_field'),
            'nexus_translator_api_settings',
            'nexus_translator_api_section'
        );
        
        add_settings_field(
            'temperature',
            __('Temperature', 'nexus-ai-wp-translator'),
            array($this, 'render_temperature_field'),
            'nexus_translator_api_settings',
            'nexus_translator_api_section'
        );
        
        // API Rate Limiting Section
        add_settings_section(
            'nexus_translator_rate_limiting_section',
            __('API Rate Limiting & Safety', 'nexus-ai-wp-translator'),
            array($this, 'render_rate_limiting_section_description'),
            'nexus_translator_api_settings'
        );
        
        add_settings_field(
            'max_calls_per_hour',
            __('Max API Calls per Hour', 'nexus-ai-wp-translator'),
            array($this, 'render_max_calls_hour_field'),
            'nexus_translator_api_settings',
            'nexus_translator_rate_limiting_section'
        );
        
        add_settings_field(
            'max_calls_per_day',
            __('Max API Calls per Day', 'nexus-ai-wp-translator'),
            array($this, 'render_max_calls_day_field'),
            'nexus_translator_api_settings',
            'nexus_translator_rate_limiting_section'
        );
        
        add_settings_field(
            'min_request_interval',
            __('Minimum Interval Between Requests', 'nexus-ai-wp-translator'),
            array($this, 'render_min_interval_field'),
            'nexus_translator_api_settings',
            'nexus_translator_rate_limiting_section'
        );
        
        add_settings_field(
            'request_timeout',
            __('Request Timeout', 'nexus-ai-wp-translator'),
            array($this, 'render_timeout_field'),
            'nexus_translator_api_settings',
            'nexus_translator_rate_limiting_section'
        );
        
        add_settings_field(
            'emergency_stop_threshold',
            __('Emergency Stop Threshold', 'nexus-ai-wp-translator'),
            array($this, 'render_emergency_threshold_field'),
            'nexus_translator_api_settings',
            'nexus_translator_rate_limiting_section'
        );
        
        add_settings_field(
            'translation_cooldown',
            __('Translation Cooldown', 'nexus-ai-wp-translator'),
            array($this, 'render_cooldown_field'),
            'nexus_translator_api_settings',
            'nexus_translator_rate_limiting_section'
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
     * Render rate limiting section description
     */
    public function render_rate_limiting_section_description() {
        echo '<div class="nexus-rate-limiting-info">';
        echo '<p>' . __('Configure API rate limiting and safety features to prevent infinite loops and excessive API usage.', 'nexus-ai-wp-translator') . '</p>';
        
        // Show current usage if available
        if (class_exists('Translator_API')) {
            $api = new Translator_API();
            $usage_stats = $api->get_usage_stats();
            
            echo '<div class="nexus-current-usage">';
            echo '<h4>' . __('Current Usage', 'nexus-ai-wp-translator') . '</h4>';
            echo '<div class="nexus-usage-grid">';
            echo '<div class="nexus-usage-item">';
            echo '<span class="nexus-usage-label">' . __('Calls Today:', 'nexus-ai-wp-translator') . '</span>';
            echo '<span class="nexus-usage-value">' . $usage_stats['calls_today'] . ' / ' . $usage_stats['limit_day'] . '</span>';
            echo '</div>';
            echo '<div class="nexus-usage-item">';
            echo '<span class="nexus-usage-label">' . __('Calls This Hour:', 'nexus-ai-wp-translator') . '</span>';
            echo '<span class="nexus-usage-value">' . $usage_stats['calls_hour'] . ' / ' . $usage_stats['limit_hour'] . '</span>';
            echo '</div>';
            
            if ($usage_stats['emergency_stop']) {
                echo '<div class="nexus-usage-item nexus-emergency">';
                echo '<span class="nexus-usage-label">' . __('Status:', 'nexus-ai-wp-translator') . '</span>';
                echo '<span class="nexus-usage-value">🚨 ' . __('Emergency Stop Active', 'nexus-ai-wp-translator') . '</span>';
                echo '</div>';
            }
            
            echo '</div>';
            
            echo '<div class="nexus-admin-actions">';
            echo '<button type="button" id="reset-rate-limits" class="button">' . __('Reset Rate Limits', 'nexus-ai-wp-translator') . '</button>';
            if ($usage_stats['emergency_stop']) {
                echo '<button type="button" id="reset-emergency-stop" class="button button-primary">' . __('Reset Emergency Stop', 'nexus-ai-wp-translator') . '</button>';
            }
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        
        echo '<style>
        .nexus-rate-limiting-info { background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .nexus-current-usage { margin-top: 15px; }
        .nexus-usage-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin: 10px 0; }
        .nexus-usage-item { display: flex; justify-content: space-between; padding: 8px 12px; background: white; border-radius: 3px; border-left: 3px solid #007cba; }
        .nexus-usage-item.nexus-emergency { border-left-color: #dc3545; background: #ffe6e6; }
        .nexus-usage-label { font-weight: 500; }
        .nexus-usage-value { font-weight: bold; color: #007cba; }
        .nexus-emergency .nexus-usage-value { color: #dc3545; }
        .nexus-admin-actions { margin-top: 15px; }
        .nexus-admin-actions button { margin-right: 10px; }
        </style>';
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
     * Render max tokens field
     */
    public function render_max_tokens_field() {
        $settings = get_option('nexus_translator_api_settings', array());
        $max_tokens = $settings['max_tokens'] ?? 4000;
        
        echo '<input type="number" id="max_tokens" name="nexus_translator_api_settings[max_tokens]" value="' . esc_attr($max_tokens) . '" min="100" max="8000" step="100" />';
        echo '<p class="description">' . __('Maximum number of tokens for API response (100-8000). Higher values allow longer translations but cost more.', 'nexus-ai-wp-translator') . '</p>';
    }
    
    /**
     * Render temperature field
     */
    public function render_temperature_field() {
        $settings = get_option('nexus_translator_api_settings', array());
        $temperature = $settings['temperature'] ?? 0.3;
        
        echo '<input type="number" id="temperature" name="nexus_translator_api_settings[temperature]" value="' . esc_attr($temperature) . '" min="0" max="1" step="0.1" />';
        echo '<p class="description">' . __('Controls randomness in translations (0.0-1.0). Lower values = more consistent, higher = more creative. Recommended: 0.3', 'nexus-ai-wp-translator') . '</p>';
    }
    
    /**
     * Render max calls per hour field
     */
    public function render_max_calls_hour_field() {
        $settings = get_option('nexus_translator_api_settings', array());
        $max_calls_hour = $settings['max_calls_per_hour'] ?? 50;
        
        echo '<input type="number" id="max_calls_per_hour" name="nexus_translator_api_settings[max_calls_per_hour]" value="' . esc_attr($max_calls_hour) . '" min="1" max="1000" />';
        echo '<p class="description">' . __('Maximum API calls allowed per hour. Prevents accidental overuse. Default: 50', 'nexus-ai-wp-translator') . '</p>';
    }
    
    /**
     * Render max calls per day field
     */
    public function render_max_calls_day_field() {
        $settings = get_option('nexus_translator_api_settings', array());
        $max_calls_day = $settings['max_calls_per_day'] ?? 200;
        
        echo '<input type="number" id="max_calls_per_day" name="nexus_translator_api_settings[max_calls_per_day]" value="' . esc_attr($max_calls_day) . '" min="1" max="10000" />';
        echo '<p class="description">' . __('Maximum API calls allowed per day. Important safety limit to prevent unexpected charges. Default: 200', 'nexus-ai-wp-translator') . '</p>';
    }
    
    /**
     * Render minimum interval field
     */
    public function render_min_interval_field() {
        $settings = get_option('nexus_translator_api_settings', array());
        $min_interval = $settings['min_request_interval'] ?? 2;
        
        echo '<input type="number" id="min_request_interval" name="nexus_translator_api_settings[min_request_interval]" value="' . esc_attr($min_interval) . '" min="0" max="60" />';
        echo ' ' . __('seconds', 'nexus-ai-wp-translator');
        echo '<p class="description">' . __('Minimum time between API requests. Prevents rapid-fire calls. Default: 2 seconds', 'nexus-ai-wp-translator') . '</p>';
    }
    
    /**
     * Render timeout field
     */
    public function render_timeout_field() {
        $settings = get_option('nexus_translator_api_settings', array());
        $timeout = $settings['request_timeout'] ?? 60;
        
        echo '<input type="number" id="request_timeout" name="nexus_translator_api_settings[request_timeout]" value="' . esc_attr($timeout) . '" min="10" max="300" />';
        echo ' ' . __('seconds', 'nexus-ai-wp-translator');
        echo '<p class="description">' . __('Maximum time to wait for API response. Default: 60 seconds', 'nexus-ai-wp-translator') . '</p>';
    }
    
    /**
     * Render emergency threshold field
     */
    public function render_emergency_threshold_field() {
        $settings = get_option('nexus_translator_api_settings', array());
        $threshold = $settings['emergency_stop_threshold'] ?? 10;
        
        echo '<input type="number" id="emergency_stop_threshold" name="nexus_translator_api_settings[emergency_stop_threshold]" value="' . esc_attr($threshold) . '" min="1" max="100" />';
        echo ' ' . __('calls', 'nexus-ai-wp-translator');
        echo '<p class="description">' . __('Number of API calls in short time that triggers emergency stop. Prevents infinite loops. Default: 10', 'nexus-ai-wp-translator') . '</p>';
    }
    
    /**
     * Render cooldown field
     */
    public function render_cooldown_field() {
        $settings = get_option('nexus_translator_api_settings', array());
        $cooldown = $settings['translation_cooldown'] ?? 300;
        
        echo '<input type="number" id="translation_cooldown" name="nexus_translator_api_settings[translation_cooldown]" value="' . esc_attr($cooldown) . '" min="60" max="3600" />';
        echo ' ' . __('seconds', 'nexus-ai-wp-translator');
        echo '<p class="description">' . __('Minimum time between translations of the same post. Prevents accidental re-translations. Default: 300 seconds (5 minutes)', 'nexus-ai-wp-translator') . '</p>';
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
     * Render preserve data field
     */
    public function render_preserve_data_field() {
        $preserve_data = get_option('nexus_translator_preserve_on_uninstall', false);
        
        echo '<input type="checkbox" id="preserve_on_uninstall" name="nexus_translator_preserve_on_uninstall" value="1"' . checked($preserve_data, true, false) . '> ';
        echo '<label for="preserve_on_uninstall">' . __('Keep translation data when uninstalling plugin', 'nexus-ai-wp-translator') . '</label>';
        echo '<p class="description">' . __('If checked, translation relationships will be preserved when the plugin is uninstalled.', 'nexus-ai-wp-translator') . '</p>';
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
        
        // Rate limiting settings
        if (isset($input['max_calls_per_hour'])) {
            $sanitized['max_calls_per_hour'] = max(1, min(1000, (int) $input['max_calls_per_hour']));
        }
        
        if (isset($input['max_calls_per_day'])) {
            $sanitized['max_calls_per_day'] = max(1, min(10000, (int) $input['max_calls_per_day']));
        }
        
        if (isset($input['min_request_interval'])) {
            $sanitized['min_request_interval'] = max(0, min(60, (int) $input['min_request_interval']));
        }
        
        if (isset($input['request_timeout'])) {
            $sanitized['request_timeout'] = max(10, min(300, (int) $input['request_timeout']));
        }
        
        if (isset($input['emergency_stop_threshold'])) {
            $sanitized['emergency_stop_threshold'] = max(1, min(100, (int) $input['emergency_stop_threshold']));
        }
        
        if (isset($input['translation_cooldown'])) {
            $sanitized['translation_cooldown'] = max(60, min(3600, (int) $input['translation_cooldown']));
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
     * Handle reset rate limits AJAX
     */
    public function handle_reset_rate_limits() {
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_admin_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }
        
        if (class_exists('Translator_API')) {
            $api = new Translator_API();
            $result = $api->reset_rate_limits();
            
            if ($result) {
                wp_send_json_success(array(
                    'message' => __('Rate limits reset successfully', 'nexus-ai-wp-translator')
                ));
            } else {
                wp_send_json_error(__('Failed to reset rate limits', 'nexus-ai-wp-translator'));
            }
        } else {
            wp_send_json_error(__('API class not available', 'nexus-ai-wp-translator'));
        }
    }
    
    /**
     * Handle reset emergency stop AJAX
     */
    public function handle_reset_emergency() {
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_admin_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }
        
        delete_option('nexus_translator_emergency_stop');
        delete_option('nexus_translator_emergency_reason');
        delete_option('nexus_translator_emergency_time');
        
        wp_send_json_success(array(
            'message' => __('Emergency stop reset successfully', 'nexus-ai-wp-translator')
        ));
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
        
        // Check emergency stop
        if (get_option('nexus_translator_emergency_stop', false)) {
            $reason = get_option('nexus_translator_emergency_reason', 'Unknown');
            $time = get_option('nexus_translator_emergency_time', time());
            
            echo '<div class="notice notice-error">';
            echo '<h3>🚨 ' . __('Nexus Translator Emergency Stop Active', 'nexus-ai-wp-translator') . '</h3>';
            echo '<p><strong>' . __('All translation functionality has been disabled for safety.', 'nexus-ai-wp-translator') . '</strong></p>';
            echo '<p>' . __('Reason:', 'nexus-ai-wp-translator') . ' ' . esc_html($reason) . '</p>';
            echo '<p>' . __('Time:', 'nexus-ai-wp-translator') . ' ' . date('Y-m-d H:i:s', $time) . '</p>';
            echo '<p><a href="' . admin_url('admin.php?page=nexus-translator-settings') . '" class="button button-primary">' . __('Go to Settings to Reset', 'nexus-ai-wp-translator') . '</a></p>';
            echo '</div>';
        }
        
        // Show rate limit warnings
        if (class_exists('Translator_API')) {
            $api = new Translator_API();
            $usage_stats = $api->get_usage_stats();
            
            // Warning at 80% of daily limit
            $daily_percentage = ($usage_stats['calls_today'] / $usage_stats['limit_day']) * 100;
            if ($daily_percentage >= 80 && $daily_percentage < 100) {
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p>' . sprintf(
                    __('Nexus Translator: You have used %d%% of your daily API limit (%d/%d calls).', 'nexus-ai-wp-translator'),
                    round($daily_percentage),
                    $usage_stats['calls_today'],
                    $usage_stats['limit_day']
                ) . '</p>';
                echo '</div>';
            }
            
            // Critical warning at 95% of daily limit
            if ($daily_percentage >= 95 && $daily_percentage < 100) {
                echo '<div class="notice notice-error">';
                echo '<p>' . sprintf(
                    __('Nexus Translator: CRITICAL - You have used %d%% of your daily API limit (%d/%d calls). Consider increasing the limit or reducing usage.', 'nexus-ai-wp-translator'),
                    round($daily_percentage),
                    $usage_stats['calls_today'],
                    $usage_stats['limit_day']
                ) . '</p>';
                echo '</div>';
            }
        }
        
        // Show success message after emergency reset
        if (isset($_GET['emergency_reset']) && $_GET['emergency_reset'] === '1') {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . __('Emergency stop has been reset. Translation functionality is now re-enabled.', 'nexus-ai-wp-translator') . '</p>';
            echo '</div>';
        }
        
        // Show success message after settings save
        if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . __('Settings saved successfully! Rate limiting configuration updated.', 'nexus-ai-wp-translator') . '</p>';
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
     * Add admin scripts for enhanced functionality
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_nexus-translator-settings') {
            return;
        }
        
        wp_enqueue_script(
            'nexus-translator-admin-enhanced',
            NEXUS_TRANSLATOR_PLUGIN_URL . 'admin/js/admin-enhanced.js',
            array('jquery'),
            NEXUS_TRANSLATOR_VERSION,
            true
        );
        
        wp_localize_script('nexus-translator-admin-enhanced', 'nexusAdminAjax', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nexus_admin_nonce'),
            'strings' => array(
                'resetLimits' => __('Are you sure you want to reset rate limits?', 'nexus-ai-wp-translator'),
                'resetEmergency' => __('Are you sure you want to reset emergency stop?', 'nexus-ai-wp-translator'),
                'processing' => __('Processing...', 'nexus-ai-wp-translator'),
                'success' => __('Success!', 'nexus-ai-wp-translator'),
                'error' => __('Error occurred', 'nexus-ai-wp-translator')
            )
        ));
    }
}