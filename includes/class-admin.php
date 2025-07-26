<?php
/**
 * File: class-admin.php
 * Location: /includes/class-admin.php
 * 
 * Settings Admin Interface Class
 * Responsible for: Settings page, forms, validation, options, system status
 * 
 * @package Nexus\Translator
 * @since 0.0.1
 */

namespace Nexus\Translator;

use Nexus\Translator\Abstracts\Abstract_Module;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings administration class
 * 
 * Handles WordPress admin settings page, form processing, validation,
 * options management, and system status display.
 * 
 * @since 0.0.1
 */
class Admin extends Abstract_Module {
    
    /**
     * Settings page hook suffix
     * 
     * @since 0.0.1
     * @var string
     */
    private $page_hook;
    
    /**
     * API instance for connection testing
     * 
     * @since 0.0.1
     * @var Api
     */
    private $api;
    
    /**
     * Languages instance for validation
     * 
     * @since 0.0.1
     * @var Languages
     */
    private $languages;
    
    /**
     * Plugin settings
     * 
     * @since 0.0.1
     * @var array
     */
    private $settings = array();
    
    /**
     * System status cache
     * 
     * @since 0.0.1
     * @var array
     */
    private $system_status = array();
    
    /**
     * Get module name/identifier
     * 
     * @since 0.0.1
     * @return string Module name
     */
    protected function get_module_name() {
        return 'admin';
    }
    
    /**
     * Module-specific initialization
     * 
     * @since 0.0.1
     * @return void
     */
    protected function module_init() {
        // Get component instances
        $main = \Nexus\Translator\Main::get_instance();
        $this->api = $main->get_component('api');
        $this->languages = $main->get_component('languages');
        
        // Load current settings
        $this->load_settings();
        
        // Initialize system status
        $this->init_system_status();
    }
    
    /**
     * Register WordPress hooks
     * 
     * @since 0.0.1
     * @return void
     */
    protected function register_hooks() {
        // Admin menu
        $this->add_hook('admin_menu', array($this, 'add_admin_menu'));
        
        // Settings registration
        $this->add_hook('admin_init', array($this, 'register_settings'));
        
        // Admin notices
        $this->add_hook('admin_notices', array($this, 'show_admin_notices'));
        
        // Enqueue scripts and styles
        $this->add_hook('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX handlers for admin
        $this->add_hook('wp_ajax_nexus_test_connection', array($this, 'ajax_test_connection'));
        $this->add_hook('wp_ajax_nexus_get_system_status', array($this, 'ajax_get_system_status'));
        $this->add_hook('wp_ajax_nexus_reset_settings', array($this, 'ajax_reset_settings'));
    }
    
    /**
     * Load plugin settings
     * 
     * @since 0.0.1
     * @return void
     */
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
        
        $saved_settings = get_option('nexus_ai_translator_settings', array());
        $this->settings = wp_parse_args($saved_settings, $defaults);
    }
    
    /**
     * Initialize system status
     * 
     * @since 0.0.1
     * @return void
     */
    private function init_system_status() {
        $this->system_status = array(
            'plugin_version' => NEXUS_AI_TRANSLATOR_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'api_connected' => false,
            'api_key_valid' => false,
            'languages_loaded' => false,
            'emergency_mode' => false,
            'last_check' => '',
        );
    }
    
    /**
     * Add admin menu
     * 
     * @since 0.0.1
     * @return void
     */
    public function add_admin_menu() {
        $this->page_hook = add_options_page(
            __('Nexus AI Translator Settings', 'nexus-ai-wp-translator'),
            __('AI Translator', 'nexus-ai-wp-translator'),
            'manage_options',
            'nexus-ai-translator',
            array($this, 'render_settings_page')
        );
        
        // Add help tab when page loads
        $this->add_hook('load-' . $this->page_hook, array($this, 'add_help_tabs'));
    }
    
    /**
     * Register plugin settings
     * 
     * @since 0.0.1
     * @return void
     */
    public function register_settings() {
        // Register main settings group
        register_setting(
            'nexus_ai_translator_settings',
            'nexus_ai_translator_settings',
            array(
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default' => $this->settings,
            )
        );
        
        // API Configuration Section
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
            'nexus_api_section',
            array('label_for' => 'api_key')
        );
        
        // Language Configuration Section
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
            'nexus_language_section',
            array('label_for' => 'source_language')
        );
        
        add_settings_field(
            'target_languages',
            __('Target Languages', 'nexus-ai-wp-translator'),
            array($this, 'render_target_languages_field'),
            'nexus-ai-translator',
            'nexus_language_section',
            array('label_for' => 'target_languages')
        );
        
        // Translation Options Section
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
            'nexus_options_section',
            array('label_for' => 'auto_translate')
        );
        
        add_settings_field(
            'translation_quality',
            __('Translation Quality', 'nexus-ai-wp-translator'),
            array($this, 'render_quality_field'),
            'nexus-ai-translator',
            'nexus_options_section',
            array('label_for' => 'translation_quality')
        );
        
        // Advanced Options Section
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
            'nexus_advanced_section',
            array('label_for' => 'enable_analytics')
        );
        
        add_settings_field(
            'enable_emergency_mode',
            __('Enable Emergency Mode', 'nexus-ai-wp-translator'),
            array($this, 'render_emergency_field'),
            'nexus-ai-translator',
            'nexus_advanced_section',
            array('label_for' => 'enable_emergency_mode')
        );
    }
    
    /**
     * Sanitize settings before saving
     * 
     * @since 0.0.1
     * @param array $input Raw input settings
     * @return array Sanitized settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Sanitize API key
        $sanitized['api_key'] = sanitize_text_field($input['api_key'] ?? '');
        
        // Sanitize source language
        $sanitized['source_language'] = sanitize_text_field($input['source_language'] ?? 'en');
        
        // Sanitize target languages
        $target_languages = $input['target_languages'] ?? array();
        if (is_array($target_languages)) {
            $sanitized['target_languages'] = array_map('sanitize_text_field', $target_languages);
        } else {
            $sanitized['target_languages'] = array('fr', 'es', 'de');
        }
        
        // Sanitize boolean options
        $sanitized['auto_translate'] = isset($input['auto_translate']) ? (bool) $input['auto_translate'] : false;
        $sanitized['enable_analytics'] = isset($input['enable_analytics']) ? (bool) $input['enable_analytics'] : true;
        $sanitized['enable_emergency_mode'] = isset($input['enable_emergency_mode']) ? (bool) $input['enable_emergency_mode'] : true;
        
        // Sanitize translation quality
        $valid_qualities = array('fast', 'standard', 'premium');
        $sanitized['translation_quality'] = in_array($input['translation_quality'] ?? '', $valid_qualities) ? 
            $input['translation_quality'] : 'standard';
        
        // Validate settings using language module
        if ($this->languages && method_exists($this->languages, 'get_validator')) {
            $validator = $this->languages->get_validator();
            if ($validator) {
                $validation = $validator->validate_settings($sanitized);
                if (!$validation['valid']) {
                    // Add admin notices for validation errors
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
        
        // Fire analytics event
        do_action('nexus_analytics_event', 'settings_updated', array(
            'changes' => array_diff_assoc($sanitized, $this->settings),
            'timestamp' => current_time('mysql'),
        ));
        
        return $sanitized;
    }
    
    /**
     * Render settings page
     * 
     * @since 0.0.1
     * @return void
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'nexus-ai-wp-translator'));
        }
        
        // Update system status
        $this->update_system_status();
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="nexus-admin-header">
                <div class="nexus-status-cards">
                    <?php $this->render_status_cards(); ?>
                </div>
            </div>
            
            <div class="nexus-admin-content">
                <div class="nexus-settings-form">
                    <form action="options.php" method="post">
                        <?php
                        settings_fields('nexus_ai_translator_settings');
                        do_settings_sections('nexus-ai-translator');
                        submit_button(__('Save Settings', 'nexus-ai-wp-translator'));
                        ?>
                    </form>
                </div>
                
                <div class="nexus-sidebar">
                    <?php $this->render_sidebar(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render status cards
     * 
     * @since 0.0.1
     * @return void
     */
    private function render_status_cards() {
        $cards = array(
            'api' => array(
                'title' => __('API Status', 'nexus-ai-wp-translator'),
                'status' => $this->system_status['api_connected'] ? 'connected' : 'disconnected',
                'icon' => $this->system_status['api_connected'] ? 'yes-alt' : 'dismiss',
                'description' => $this->system_status['api_connected'] ? 
                    __('Connected to Claude AI', 'nexus-ai-wp-translator') : 
                    __('Not connected', 'nexus-ai-wp-translator'),
            ),
            'languages' => array(
                'title' => __('Languages', 'nexus-ai-wp-translator'),
                'status' => count($this->settings['target_languages']) > 0 ? 'configured' : 'not_configured',
                'icon' => count($this->settings['target_languages']) > 0 ? 'translation' : 'warning',
                'description' => sprintf(
                    /* translators: %d: Number of target languages */
                    _n('%d language configured', '%d languages configured', count($this->settings['target_languages']), 'nexus-ai-wp-translator'),
                    count($this->settings['target_languages'])
                ),
            ),
            'emergency' => array(
                'title' => __('System Status', 'nexus-ai-wp-translator'),
                'status' => $this->system_status['emergency_mode'] ? 'emergency' : 'normal',
                'icon' => $this->system_status['emergency_mode'] ? 'warning' : 'yes-alt',
                'description' => $this->system_status['emergency_mode'] ? 
                    __('Emergency mode active', 'nexus-ai-wp-translator') : 
                    __('All systems normal', 'nexus-ai-wp-translator'),
            ),
        );
        
        foreach ($cards as $card_id => $card) {
            ?>
            <div class="nexus-status-card nexus-status-<?php echo esc_attr($card['status']); ?>">
                <div class="nexus-status-icon">
                    <span class="dashicons dashicons-<?php echo esc_attr($card['icon']); ?>"></span>
                </div>
                <div class="nexus-status-content">
                    <h3><?php echo esc_html($card['title']); ?></h3>
                    <p class="description">
            <?php esc_html_e('Automatically disable translations during API errors to prevent issues.', 'nexus-ai-wp-translator'); ?>
        </p>
        <?php
    }
    
    /**
     * Show admin notices
     * 
     * @since 0.0.1
     * @return void
     */
    public function show_admin_notices() {
        // Only show on our settings page
        $screen = get_current_screen();
        if (!$screen || $screen->id !== $this->page_hook) {
            return;
        }
        
        // Check for missing API key
        if (empty($this->settings['api_key'])) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php esc_html_e('Nexus AI Translator:', 'nexus-ai-wp-translator'); ?></strong>
                    <?php esc_html_e('Please enter your Claude AI API key to start translating.', 'nexus-ai-wp-translator'); ?>
                </p>
            </div>
            <?php
        }
        
        // Check for emergency mode
        if ($this->system_status['emergency_mode']) {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php esc_html_e('Emergency Mode Active:', 'nexus-ai-wp-translator'); ?></strong>
                    <?php esc_html_e('Translation services are temporarily disabled due to API errors.', 'nexus-ai-wp-translator'); ?>
                    <a href="#" id="nexus-reset-emergency" class="button button-small">
                        <?php esc_html_e('Reset Emergency Mode', 'nexus-ai-wp-translator'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Enqueue admin assets
     * 
     * @since 0.0.1
     * @param string $hook_suffix Current page hook suffix
     * @return void
     */
    public function enqueue_admin_assets($hook_suffix) {
        // Only load on our settings page
        if ($hook_suffix !== $this->page_hook) {
            return;
        }
        
        // Enqueue admin CSS
        wp_enqueue_style(
            'nexus-ai-translator-admin',
            NEXUS_AI_TRANSLATOR_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            NEXUS_AI_TRANSLATOR_VERSION
        );
        
        // Enqueue admin JavaScript
        wp_enqueue_script(
            'nexus-ai-translator-admin',
            NEXUS_AI_TRANSLATOR_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery'),
            NEXUS_AI_TRANSLATOR_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('nexus-ai-translator-admin', 'nexusAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nexus_admin_ajax'),
            'strings' => array(
                'testing' => __('Testing connection...', 'nexus-ai-wp-translator'),
                'connected' => __('Connected successfully!', 'nexus-ai-wp-translator'),
                'failed' => __('Connection failed', 'nexus-ai-wp-translator'),
                'refreshing' => __('Refreshing status...', 'nexus-ai-wp-translator'),
                'resetting' => __('Resetting settings...', 'nexus-ai-wp-translator'),
                'confirmReset' => __('Are you sure you want to reset all settings to defaults?', 'nexus-ai-wp-translator'),
                'show' => __('Show', 'nexus-ai-wp-translator'),
                'hide' => __('Hide', 'nexus-ai-wp-translator'),
            ),
        ));
    }
    
    /**
     * Add help tabs
     * 
     * @since 0.0.1
     * @return void
     */
    public function add_help_tabs() {
        $screen = get_current_screen();
        
        // API Configuration tab
        $screen->add_help_tab(array(
            'id' => 'nexus-api-help',
            'title' => __('API Configuration', 'nexus-ai-wp-translator'),
            'content' => $this->get_api_help_content(),
        ));
        
        // Language Configuration tab
        $screen->add_help_tab(array(
            'id' => 'nexus-languages-help',
            'title' => __('Languages', 'nexus-ai-wp-translator'),
            'content' => $this->get_languages_help_content(),
        ));
        
        // Troubleshooting tab
        $screen->add_help_tab(array(
            'id' => 'nexus-troubleshooting-help',
            'title' => __('Troubleshooting', 'nexus-ai-wp-translator'),
            'content' => $this->get_troubleshooting_help_content(),
        ));
        
        // Set help sidebar
        $screen->set_help_sidebar($this->get_help_sidebar_content());
    }
    
    /**
     * AJAX: Test API connection
     * 
     * @since 0.0.1
     * @return void
     */
    public function ajax_test_connection() {
        check_ajax_referer('nexus_admin_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'nexus-ai-wp-translator')
            ));
        }
        
        $api_key = sanitize_text_field($_POST['api_key'] ?? $this->settings['api_key']);
        
        if (empty($api_key)) {
            wp_send_json_error(array(
                'message' => __('No API key provided.', 'nexus-ai-wp-translator')
            ));
        }
        
        if ($this->api) {
            $result = $this->api->authenticate($api_key);
            
            if ($result['success']) {
                wp_send_json_success(array(
                    'message' => __('API connection successful!', 'nexus-ai-wp-translator'),
                    'response_time' => $result['data']['response_time'] ?? 0,
                ));
            } else {
                wp_send_json_error(array(
                    'message' => $result['message']
                ));
            }
        } else {
            wp_send_json_error(array(
                'message' => __('API component not available.', 'nexus-ai-wp-translator')
            ));
        }
    }
    
    /**
     * AJAX: Get system status
     * 
     * @since 0.0.1
     * @return void
     */
    public function ajax_get_system_status() {
        check_ajax_referer('nexus_admin_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'nexus-ai-wp-translator')
            ));
        }
        
        $this->update_system_status();
        
        wp_send_json_success(array(
            'status' => $this->system_status,
            'message' => __('System status updated.', 'nexus-ai-wp-translator'),
        ));
    }
    
    /**
     * AJAX: Reset settings
     * 
     * @since 0.0.1
     * @return void
     */
    public function ajax_reset_settings() {
        check_ajax_referer('nexus_admin_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'nexus-ai-wp-translator')
            ));
        }
        
        // Reset to defaults
        $defaults = array(
            'api_key' => '',
            'source_language' => 'en',
            'target_languages' => array('fr', 'es', 'de'),
            'auto_translate' => false,
            'translation_quality' => 'standard',
            'enable_analytics' => true,
            'enable_emergency_mode' => true,
        );
        
        update_option('nexus_ai_translator_settings', $defaults);
        $this->settings = $defaults;
        
        // Fire analytics event
        do_action('nexus_analytics_event', 'settings_reset', array(
            'reset_by' => get_current_user_id(),
            'timestamp' => current_time('mysql'),
        ));
        
        wp_send_json_success(array(
            'message' => __('Settings reset to defaults.', 'nexus-ai-wp-translator'),
            'redirect' => admin_url('options-general.php?page=nexus-ai-translator'),
        ));
    }
    
    /**
     * Test API connection
     * 
     * @since 0.0.1
     * @param string $api_key API key to test
     * @return void
     */
    private function test_api_connection($api_key) {
        if ($this->api) {
            $result = $this->api->authenticate($api_key);
            
            if ($result['success']) {
                add_settings_error(
                    'nexus_ai_translator_settings',
                    'api_connected',
                    __('API connection successful!', 'nexus-ai-wp-translator'),
                    'updated'
                );
                
                $this->system_status['api_connected'] = true;
                $this->system_status['api_key_valid'] = true;
            } else {
                add_settings_error(
                    'nexus_ai_translator_settings',
                    'api_failed',
                    sprintf(
                        /* translators: %s: Error message */
                        __('API connection failed: %s', 'nexus-ai-wp-translator'),
                        $result['message']
                    ),
                    'error'
                );
                
                $this->system_status['api_connected'] = false;
                $this->system_status['api_key_valid'] = false;
            }
        }
    }
    
    /**
     * Update system status
     * 
     * @since 0.0.1
     * @return void
     */
    private function update_system_status() {
        // Check API status
        if ($this->api && !empty($this->settings['api_key'])) {
            if (method_exists($this->api, 'get_performance')) {
                $performance = $this->api->get_performance();
                if ($performance) {
                    $api_status = $performance->get_api_status();
                    $this->system_status['api_connected'] = $api_status['connected'] ?? false;
                    $this->system_status['api_key_valid'] = $api_status['api_key_valid'] ?? false;
                }
            }
        }
        
        // Check languages status
        if ($this->languages) {
            $this->system_status['languages_loaded'] = count($this->languages->get_supported_languages()) > 0;
        }
        
        // Check emergency mode
        $this->system_status['emergency_mode'] = apply_filters('nexus_emergency_mode_active', false);
        
        // Update last check time
        $this->system_status['last_check'] = current_time('mysql');
        
        // Cache status
        set_transient('nexus_ai_translator_system_status', $this->system_status, HOUR_IN_SECONDS);
    }
    
    /**
     * Get API help content
     * 
     * @since 0.0.1
     * @return string Help content
     */
    private function get_api_help_content() {
        return '<h4>' . __('Setting up Claude AI API', 'nexus-ai-wp-translator') . '</h4>' .
               '<p>' . __('To use this plugin, you need a Claude AI API key:', 'nexus-ai-wp-translator') . '</p>' .
               '<ol>' .
               '<li>' . sprintf(__('Visit %s and create an account', 'nexus-ai-wp-translator'), '<a href="https://console.anthropic.com/" target="_blank">Claude AI Console</a>') . '</li>' .
               '<li>' . __('Navigate to API Keys section', 'nexus-ai-wp-translator') . '</li>' .
               '<li>' . __('Create a new API key', 'nexus-ai-wp-translator') . '</li>' .
               '<li>' . __('Copy the key and paste it in the API Key field above', 'nexus-ai-wp-translator') . '</li>' .
               '</ol>' .
               '<p>' . __('The plugin will automatically test the connection when you save the settings.', 'nexus-ai-wp-translator') . '</p>';
    }
    
    /**
     * Get languages help content
     * 
     * @since 0.0.1
     * @return string Help content
     */
    private function get_languages_help_content() {
        return '<h4>' . __('Language Configuration', 'nexus-ai-wp-translator') . '</h4>' .
               '<p>' . __('Configure which languages to translate your content:', 'nexus-ai-wp-translator') . '</p>' .
               '<ul>' .
               '<li><strong>' . __('Source Language:', 'nexus-ai-wp-translator') . '</strong> ' . __('The primary language of your content', 'nexus-ai-wp-translator') . '</li>' .
               '<li><strong>' . __('Target Languages:', 'nexus-ai-wp-translator') . '</strong> ' . __('Languages to translate your content into', 'nexus-ai-wp-translator') . '</li>' .
               '</ul>' .
               '<p>' . __('Supported language pairs are optimized for translation quality and accuracy.', 'nexus-ai-wp-translator') . '</p>';
    }
    
    /**
     * Get troubleshooting help content
     * 
     * @since 0.0.1
     * @return string Help content
     */
    private function get_troubleshooting_help_content() {
        return '<h4>' . __('Common Issues', 'nexus-ai-wp-translator') . '</h4>' .
               '<ul>' .
               '<li><strong>' . __('API Connection Failed:', 'nexus-ai-wp-translator') . '</strong> ' . __('Check your API key and internet connection', 'nexus-ai-wp-translator') . '</li>' .
               '<li><strong>' . __('Translation Not Working:', 'nexus-ai-wp-translator') . '</strong> ' . __('Verify language pair is supported', 'nexus-ai-wp-translator') . '</li>' .
               '<li><strong>' . __('Emergency Mode Active:', 'nexus-ai-wp-translator') . '</strong> ' . __('Reset emergency mode or check API status', 'nexus-ai-wp-translator') . '</li>' .
               '</ul>' .
               '<p>' . __('For more help, check the system status and error logs.', 'nexus-ai-wp-translator') . '</p>';
    }
    
    /**
     * Get help sidebar content
     * 
     * @since 0.0.1
     * @return string Sidebar content
     */
    private function get_help_sidebar_content() {
        return '<p><strong>' . __('For more information:', 'nexus-ai-wp-translator') . '</strong></p>' .
               '<p><a href="https://github.com/superkikim/nexus-ai-wp-translator" target="_blank">' . __('Plugin Documentation', 'nexus-ai-wp-translator') . '</a></p>' .
               '<p><a href="https://console.anthropic.com/" target="_blank">' . __('Claude AI Console', 'nexus-ai-wp-translator') . '</a></p>';
    }
    
    /**
     * Get current settings
     * 
     * @since 0.0.1
     * @return array Current settings
     */
    public function get_settings() {
        return $this->settings;
    }
    
    /**
     * Get system status
     * 
     * @since 0.0.1
     * @return array System status
     */
    public function get_system_status() {
        return $this->system_status;
    }
    
    /**
     * Get settings page hook
     * 
     * @since 0.0.1
     * @return string Page hook
     */
    public function get_page_hook() {
        return $this->page_hook;
    }
}><?php echo esc_html($card['description']); ?></p>
                </div>
            </div>
            <?php
        }
    }
    
    /**
     * Render sidebar
     * 
     * @since 0.0.1
     * @return void
     */
    private function render_sidebar() {
        ?>
        <div class="nexus-sidebar-section">
            <h3><?php esc_html_e('Quick Actions', 'nexus-ai-wp-translator'); ?></h3>
            <p>
                <button type="button" class="button" id="nexus-test-connection">
                    <?php esc_html_e('Test API Connection', 'nexus-ai-wp-translator'); ?>
                </button>
            </p>
            <p>
                <button type="button" class="button" id="nexus-refresh-status">
                    <?php esc_html_e('Refresh System Status', 'nexus-ai-wp-translator'); ?>
                </button>
            </p>
            <p>
                <button type="button" class="button button-secondary" id="nexus-reset-settings">
                    <?php esc_html_e('Reset to Defaults', 'nexus-ai-wp-translator'); ?>
                </button>
            </p>
        </div>
        
        <div class="nexus-sidebar-section">
            <h3><?php esc_html_e('System Information', 'nexus-ai-wp-translator'); ?></h3>
            <ul class="nexus-system-info">
                <li>
                    <strong><?php esc_html_e('Plugin Version:', 'nexus-ai-wp-translator'); ?></strong>
                    <?php echo esc_html($this->system_status['plugin_version']); ?>
                </li>
                <li>
                    <strong><?php esc_html_e('WordPress Version:', 'nexus-ai-wp-translator'); ?></strong>
                    <?php echo esc_html($this->system_status['wordpress_version']); ?>
                </li>
                <li>
                    <strong><?php esc_html_e('PHP Version:', 'nexus-ai-wp-translator'); ?></strong>
                    <?php echo esc_html($this->system_status['php_version']); ?>
                </li>
            </ul>
        </div>
        
        <div class="nexus-sidebar-section">
            <h3><?php esc_html_e('Support', 'nexus-ai-wp-translator'); ?></h3>
            <p><?php esc_html_e('Need help? Check out our documentation or contact support.', 'nexus-ai-wp-translator'); ?></p>
            <p>
                <a href="https://github.com/superkikim/nexus-ai-wp-translator" target="_blank" class="button button-secondary">
                    <?php esc_html_e('Documentation', 'nexus-ai-wp-translator'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Render API section
     * 
     * @since 0.0.1
     * @return void
     */
    public function render_api_section() {
        ?>
        <p><?php esc_html_e('Configure your Claude AI API key to enable translation services.', 'nexus-ai-wp-translator'); ?></p>
        <?php
    }
    
    /**
     * Render API key field
     * 
     * @since 0.0.1
     * @return void
     */
    public function render_api_key_field() {
        $value = $this->settings['api_key'];
        $masked_value = !empty($value) ? str_repeat('*', strlen($value) - 4) . substr($value, -4) : '';
        ?>
        <input type="password" 
               id="api_key" 
               name="nexus_ai_translator_settings[api_key]" 
               value="<?php echo esc_attr($value); ?>"
               placeholder="<?php echo esc_attr($masked_value); ?>"
               class="regular-text nexus-api-key" />
        <button type="button" class="button button-secondary nexus-toggle-api-key">
            <?php esc_html_e('Show', 'nexus-ai-wp-translator'); ?>
        </button>
        <p class="description">
            <?php 
            printf(
                /* translators: %s: Link to Claude AI */
                esc_html__('Get your API key from %s', 'nexus-ai-wp-translator'),
                '<a href="https://console.anthropic.com/" target="_blank">Claude AI Console</a>'
            );
            ?>
        </p>
        <?php
    }
    
    /**
     * Render language section
     * 
     * @since 0.0.1
     * @return void
     */
    public function render_language_section() {
        ?>
        <p><?php esc_html_e('Configure source and target languages for translation.', 'nexus-ai-wp-translator'); ?></p>
        <?php
    }
    
    /**
     * Render source language field
     * 
     * @since 0.0.1
     * @return void
     */
    public function render_source_language_field() {
        $value = $this->settings['source_language'];
        $languages = $this->languages ? $this->languages->get_language_dropdown_options() : array();
        ?>
        <select id="source_language" name="nexus_ai_translator_settings[source_language]" class="regular-text">
            <?php foreach ($languages as $code => $name) : ?>
                <option value="<?php echo esc_attr($code); ?>" <?php selected($value, $code); ?>>
                    <?php echo esc_html($name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e('The primary language of your content.', 'nexus-ai-wp-translator'); ?>
        </p>
        <?php
    }
    
    /**
     * Render target languages field
     * 
     * @since 0.0.1
     * @return void
     */
    public function render_target_languages_field() {
        $selected = $this->settings['target_languages'];
        $languages = $this->languages ? $this->languages->get_language_dropdown_options() : array();
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
        <p class="description">
            <?php esc_html_e('Select languages to translate your content into.', 'nexus-ai-wp-translator'); ?>
        </p>
        <?php
    }
    
    /**
     * Render options section
     * 
     * @since 0.0.1
     * @return void
     */
    public function render_options_section() {
        ?>
        <p><?php esc_html_e('Configure translation behavior and quality settings.', 'nexus-ai-wp-translator'); ?></p>
        <?php
    }
    
    /**
     * Render auto translate field
     * 
     * @since 0.0.1
     * @return void
     */
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
        <p class="description">
            <?php esc_html_e('When enabled, new posts will be automatically translated to all target languages.', 'nexus-ai-wp-translator'); ?>
        </p>
        <?php
    }
    
    /**
     * Render quality field
     * 
     * @since 0.0.1
     * @return void
     */
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
        <p class="description">
            <?php esc_html_e('Higher quality uses more advanced AI models but costs more.', 'nexus-ai-wp-translator'); ?>
        </p>
        <?php
    }
    
    /**
     * Render advanced section
     * 
     * @since 0.0.1
     * @return void
     */
    public function render_advanced_section() {
        ?>
        <p><?php esc_html_e('Advanced options for power users.', 'nexus-ai-wp-translator'); ?></p>
        <?php
    }
    
    /**
     * Render analytics field
     * 
     * @since 0.0.1
     * @return void
     */
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
        <p class="description">
            <?php esc_html_e('Collect anonymous usage statistics to improve the plugin.', 'nexus-ai-wp-translator'); ?>
        </p>
        <?php
    }
    
    /**
     * Render emergency field
     * 
     * @since 0.0.1
     * @return void
     */
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
        <p