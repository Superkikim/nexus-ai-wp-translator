<?php
/**
 * File: class-admin-ajax.php
 * Location: /includes/class-admin-ajax.php
 * 
 * Admin AJAX Handler
 * Handles AJAX endpoints, security validation, and responses.
 */

namespace Nexus\Translator;

class Admin_Ajax {
    
    private $admin;
    
    public function __construct($admin) {
        $this->admin = $admin;
    }
    
    public function register_hooks() {
        add_action('wp_ajax_nexus_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_nexus_get_system_status', array($this, 'ajax_get_system_status'));
        add_action('wp_ajax_nexus_reset_settings', array($this, 'ajax_reset_settings'));
        add_action('wp_ajax_nexus_reset_emergency', array($this, 'ajax_reset_emergency'));
    }
    
    public function ajax_test_connection() {
        check_ajax_referer('nexus_admin_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'nexus-ai-wp-translator')
            ));
        }
        
        $settings = $this->admin->get_settings_handler() ? $this->admin->get_settings_handler()->get_settings() : array();
        $api_key = sanitize_text_field($_POST['api_key'] ?? $settings['api_key'] ?? '');
        
        if (empty($api_key)) {
            wp_send_json_error(array(
                'message' => __('No API key provided.', 'nexus-ai-wp-translator')
            ));
        }
        
        $api = $this->admin->get_api();
        if ($api) {
            $result = $api->authenticate($api_key);
            
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
    
    public function ajax_get_system_status() {
        check_ajax_referer('nexus_admin_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'nexus-ai-wp-translator')
            ));
        }
        
        $status_handler = $this->admin->get_status_handler();
        if ($status_handler) {
            $status_handler->update_system_status();
            $status = $status_handler->get_system_status();
            
            wp_send_json_success(array(
                'status' => $status,
                'message' => __('System status updated.', 'nexus-ai-wp-translator'),
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Status handler not available.', 'nexus-ai-wp-translator')
            ));
        }
    }
    
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
        
        do_action('nexus_analytics_event', 'settings_reset', array(
            'reset_by' => get_current_user_id(),
            'timestamp' => current_time('mysql'),
        ));
        
        wp_send_json_success(array(
            'message' => __('Settings reset to defaults.', 'nexus-ai-wp-translator'),
            'redirect' => admin_url('options-general.php?page=nexus-ai-translator'),
        ));
    }
    
    public function ajax_reset_emergency() {
        check_ajax_referer('nexus_admin_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'nexus-ai-wp-translator')
            ));
        }
        
        // Reset emergency mode
        do_action('nexus_emergency_reset');
        
        // Clear error counters
        delete_transient('nexus_ai_translator_error_count');
        delete_transient('nexus_ai_translator_api_error_count');
        
        do_action('nexus_analytics_event', 'emergency_reset', array(
            'reset_by' => get_current_user_id(),
            'timestamp' => current_time('mysql'),
        ));
        
        wp_send_json_success(array(
            'message' => __('Emergency mode reset.', 'nexus-ai-wp-translator'),
        ));
    }
    
    public function ajax_validate_language_pair() {
        check_ajax_referer('nexus_admin_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'nexus-ai-wp-translator')
            ));
        }
        
        $source = sanitize_text_field($_POST['source'] ?? '');
        $target = sanitize_text_field($_POST['target'] ?? '');
        
        if (empty($source) || empty($target)) {
            wp_send_json_error(array(
                'message' => __('Source and target languages are required.', 'nexus-ai-wp-translator')
            ));
        }
        
        $languages = $this->admin->get_languages();
        if ($languages) {
            $validation = $languages->validate_language_pair($source, $target);
            
            if ($validation['valid']) {
                wp_send_json_success(array(
                    'valid' => true,
                    'pair_info' => $validation['pair'],
                    'message' => __('Language pair is valid.', 'nexus-ai-wp-translator'),
                ));
            } else {
                wp_send_json_error(array(
                    'valid' => false,
                    'errors' => $validation['errors'],
                    'message' => __('Language pair validation failed.', 'nexus-ai-wp-translator'),
                ));
            }
        } else {
            wp_send_json_error(array(
                'message' => __('Language validation not available.', 'nexus-ai-wp-translator')
            ));
        }
    }
    
    public function ajax_get_performance_metrics() {
        check_ajax_referer('nexus_admin_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'nexus-ai-wp-translator')
            ));
        }
        
        $status_handler = $this->admin->get_status_handler();
        if ($status_handler) {
            $metrics = $status_handler->get_performance_metrics();
            
            wp_send_json_success(array(
                'metrics' => $metrics,
                'message' => __('Performance metrics retrieved.', 'nexus-ai-wp-translator'),
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Performance metrics not available.', 'nexus-ai-wp-translator')
            ));
        }
    }
    
    public function ajax_export_settings() {
        check_ajax_referer('nexus_admin_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'nexus-ai-wp-translator')
            ));
        }
        
        $settings_handler = $this->admin->get_settings_handler();
        $status_handler = $this->admin->get_status_handler();
        
        $export_data = array(
            'settings' => $settings_handler ? $settings_handler->get_settings() : array(),
            'system_status' => $status_handler ? $status_handler->get_system_status() : array(),
            'export_date' => current_time('mysql'),
            'plugin_version' => NEXUS_AI_TRANSLATOR_VERSION,
        );
        
        // Remove sensitive data
        if (isset($export_data['settings']['api_key'])) {
            $export_data['settings']['api_key'] = '[REDACTED]';
        }
        
        wp_send_json_success(array(
            'data' => $export_data,
            'filename' => 'nexus-ai-translator-export-' . date('Y-m-d-H-i-s') . '.json',
            'message' => __('Settings exported successfully.', 'nexus-ai-wp-translator'),
        ));
    }
    
    public function ajax_import_settings() {
        check_ajax_referer('nexus_admin_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'nexus-ai-wp-translator')
            ));
        }
        
        $import_data = $_POST['import_data'] ?? '';
        if (empty($import_data)) {
            wp_send_json_error(array(
                'message' => __('No import data provided.', 'nexus-ai-wp-translator')
            ));
        }
        
        $data = json_decode(stripslashes($import_data), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array(
                'message' => __('Invalid JSON data.', 'nexus-ai-wp-translator')
            ));
        }
        
        if (!isset($data['settings']) || !is_array($data['settings'])) {
            wp_send_json_error(array(
                'message' => __('Invalid settings data.', 'nexus-ai-wp-translator')
            ));
        }
        
        // Validate and sanitize imported settings
        $settings_handler = $this->admin->get_settings_handler();
        if ($settings_handler) {
            $sanitized = $settings_handler->sanitize_settings($data['settings']);
            update_option('nexus_ai_translator_settings', $sanitized);
            
            do_action('nexus_analytics_event', 'settings_imported', array(
                'imported_by' => get_current_user_id(),
                'timestamp' => current_time('mysql'),
            ));
            
            wp_send_json_success(array(
                'message' => __('Settings imported successfully.', 'nexus-ai-wp-translator'),
                'redirect' => admin_url('options-general.php?page=nexus-ai-translator'),
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Settings handler not available.', 'nexus-ai-wp-translator')
            ));
        }
    }
    
    private function send_json_response($success, $data = array(), $message = '') {
        $response = array_merge($data, array(
            'success' => $success,
            'timestamp' => current_time('mysql'),
        ));
        
        if (!empty($message)) {
            $response['message'] = $message;
        }
        
        if ($success) {
            wp_send_json_success($response);
        } else {
            wp_send_json_error($response);
        }
    }
    
    private function validate_ajax_request($required_capability = 'manage_options') {
        if (!check_ajax_referer('nexus_admin_ajax', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'nexus-ai-wp-translator')
            ));
        }
        
        if (!current_user_can($required_capability)) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'nexus-ai-wp-translator')
            ));
        }
        
        return true;
    }
}