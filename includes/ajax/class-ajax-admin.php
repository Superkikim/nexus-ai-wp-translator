<?php
/**
 * File: class-ajax-admin.php
 * Location: /includes/ajax/class-ajax-admin.php
 * 
 * AJAX Admin Handler - Administration & Settings
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once NEXUS_TRANSLATOR_INCLUDES_DIR . 'ajax/class-ajax-base.php';

class Ajax_Admin extends Ajax_Base {
    
    /**
     * Initialize admin-specific hooks
     */
    protected function init_hooks() {
        add_action('wp_ajax_nexus_test_api_connection', array($this, 'handle_test_api_connection'));
        add_action('wp_ajax_nexus_reset_rate_limits', array($this, 'handle_reset_rate_limits'));
        add_action('wp_ajax_nexus_reset_emergency', array($this, 'handle_reset_emergency'));
        add_action('wp_ajax_nexus_export_config', array($this, 'handle_export_config'));
        add_action('wp_ajax_nexus_import_config', array($this, 'handle_import_config'));
        add_action('wp_ajax_nexus_validate_config', array($this, 'handle_validate_config'));
    }
    
    /**
     * Test API connection
     */
    public function handle_test_api_connection() {
        $this->validate_ajax_request('manage_options');
        
        // 🔒 PROTECTION : Éviter tests simultanés
        $request_key = "test_api_" . get_current_user_id();
        $this->check_duplicate_request($request_key);
        
        try {
            // Récupérer la clé API
            $api_key = null;
            if (isset($_POST['api_key']) && !empty($_POST['api_key'])) {
                $api_key = sanitize_text_field($_POST['api_key']);
            } else {
                $api_settings = get_option('nexus_translator_api_settings', array());
                $api_key = $api_settings['claude_api_key'] ?? '';
            }
            
            if (empty($api_key)) {
                $this->send_error('No API key provided', 'NO_API_KEY');
            }
            
            // Tester la connexion
            $api = new Translator_API();
            $result = $api->test_api_key_direct($api_key);
            
            if ($result['success']) {
                $this->log_usage('test_api_connection', array('success' => true));
                
                $this->send_success(array(
                    'test_translation' => $result['test_translation'],
                    'model_used' => $result['model_used'] ?? 'claude-sonnet-4-20250514',
                    'usage' => $result['usage'] ?? null
                ), $result['message']);
            } else {
                $this->log_usage('test_api_connection', array(
                    'success' => false,
                    'error' => $result['error']
                ));
                
                $this->send_error($result['error'], 'API_TEST_FAILED');
            }
            
        } finally {
            $this->cleanup_request($request_key);
        }
    }
    
    /**
     * Reset rate limits
     */
    public function handle_reset_rate_limits() {
        $this->validate_ajax_request('manage_options');
        
        $request_key = "reset_limits_" . get_current_user_id();
        $this->check_duplicate_request($request_key);
        
        try {
            $api = new Translator_API();
            $result = $api->reset_rate_limits();
            
            if ($result) {
                $this->log_usage('reset_rate_limits', array('success' => true));
                
                $this->send_success(array(
                    'status' => $api->get_rate_limit_status()
                ), 'Rate limits reset successfully');
            } else {
                $this->send_error('Failed to reset rate limits', 'RESET_FAILED');
            }
            
        } finally {
            $this->cleanup_request($request_key);
        }
    }
    
    /**
     * Reset emergency stop
     */
    public function handle_reset_emergency() {
        $this->validate_ajax_request('manage_options');
        
        delete_option('nexus_translator_emergency_stop');
        delete_option('nexus_translator_emergency_reason');
        delete_option('nexus_translator_emergency_time');
        
        $this->log_usage('reset_emergency_stop', array('success' => true));
        
        $this->send_success(array(), 'Emergency stop reset successfully');
    }
    
    /**
     * Export configuration
     */
    public function handle_export_config() {
        $this->validate_ajax_request('manage_options');
        
        $config = array(
            'api_settings' => get_option('nexus_translator_api_settings', array()),
            'language_settings' => get_option('nexus_translator_language_settings', array()),
            'general_options' => get_option('nexus_translator_options', array()),
            'exported_at' => current_time('mysql'),
            'plugin_version' => defined('NEXUS_TRANSLATOR_VERSION') ? NEXUS_TRANSLATOR_VERSION : '1.0.0',
            'site_url' => get_site_url()
        );
        
        // Supprimer les données sensibles
        if (isset($config['api_settings']['claude_api_key'])) {
            $config['api_settings']['claude_api_key'] = '[API_KEY_REMOVED]';
        }
        
        $this->log_usage('export_config', array('success' => true));
        
        $this->send_success(array(
            'config' => $config,
            'filename' => 'nexus-translator-config-' . date('Y-m-d-H-i-s') . '.json'
        ));
    }
    
    /**
     * Import configuration
     */
    public function handle_import_config() {
        $this->validate_ajax_request('manage_options');
        
        if (!isset($_POST['config_data'])) {
            $this->send_error('No configuration data provided', 'NO_CONFIG_DATA');
        }
        
        $config_data = json_decode(stripslashes($_POST['config_data']), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->send_error('Invalid JSON configuration', 'INVALID_JSON');
        }
        
        $imported = 0;
        $warnings = array();
        
        try {
            // Import API settings (excluding API key for security)
            if (isset($config_data['api_settings']) && is_array($config_data['api_settings'])) {
                $api_settings = $config_data['api_settings'];
                
                // Remove API key if present (security)
                unset($api_settings['claude_api_key']);
                
                $current_api_settings = get_option('nexus_translator_api_settings', array());
                $new_api_settings = array_merge($current_api_settings, $api_settings);
                
                if (update_option('nexus_translator_api_settings', $new_api_settings)) {
                    $imported++;
                } else {
                    $warnings[] = 'Failed to import API settings';
                }
            }
            
            // Import language settings
            if (isset($config_data['language_settings']) && is_array($config_data['language_settings'])) {
                $language_settings = $config_data['language_settings'];
                
                // Validate language codes
                $language_manager = new Language_Manager();
                $valid_languages = array_keys($language_manager->get_supported_languages());
                
                if (isset($language_settings['source_language'])) {
                    if (!in_array($language_settings['source_language'], $valid_languages)) {
                        unset($language_settings['source_language']);
                        $warnings[] = 'Invalid source language removed';
                    }
                }
                
                if (isset($language_settings['target_languages']) && is_array($language_settings['target_languages'])) {
                    $language_settings['target_languages'] = array_filter(
                        $language_settings['target_languages'],
                        function($lang) use ($valid_languages) {
                            return in_array($lang, $valid_languages);
                        }
                    );
                }
                
                if (update_option('nexus_translator_language_settings', $language_settings)) {
                    $imported++;
                } else {
                    $warnings[] = 'Failed to import language settings';
                }
            }
            
            // Import general options
            if (isset($config_data['general_options']) && is_array($config_data['general_options'])) {
                if (update_option('nexus_translator_options', $config_data['general_options'])) {
                    $imported++;
                } else {
                    $warnings[] = 'Failed to import general options';
                }
            }
            
            $this->log_usage('import_config', array(
                'success' => true,
                'imported_count' => $imported,
                'warnings_count' => count($warnings)
            ));
            
            $message = sprintf('%d settings sections imported successfully', $imported);
            if (!empty($warnings)) {
                $message .= '. Warnings: ' . implode(', ', $warnings);
            }
            
            $this->send_success(array(
                'imported_count' => $imported,
                'warnings' => $warnings
            ), $message);
            
        } catch (Exception $e) {
            $this->send_error('Import failed: ' . $e->getMessage(), 'IMPORT_FAILED');
        }
    }
    
    /**
     * Validate configuration
     */
    public function handle_validate_config() {
        $this->validate_ajax_request('manage_options');
        
        if (!class_exists('Translator_API')) {
            $this->send_error('API class not available', 'API_CLASS_MISSING');
        }
        
        $api = new Translator_API();
        $validation = $api->validate_configuration();
        
        // Additional validation checks
        $issues = $validation['issues'];
        $warnings = array();
        
        // Check if API is actually configured
        if (!$api->is_api_configured()) {
            $issues[] = 'Claude API key is not configured';
        }
        
        // Check language settings
        $lang_settings = get_option('nexus_translator_language_settings', array());
        if (empty($lang_settings['source_language'])) {
            $issues[] = 'Source language is not configured';
        }
        if (empty($lang_settings['target_languages'])) {
            $issues[] = 'No target languages selected';
        }
        
        // Check emergency status
        if (get_option('nexus_translator_emergency_stop', false)) {
            $issues[] = 'Emergency stop is currently active';
        }
        
        // Test API connection if possible
        $api_status = 'unknown';
        if ($api->is_api_configured()) {
            $test_result = $api->test_api_connection();
            $api_status = $test_result['success'] ? 'connected' : 'error';
            
            if (!$test_result['success']) {
                $issues[] = sprintf('API connection failed: %s', $test_result['error']);
            }
        }
        
        $this->log_usage('validate_config', array(
            'success' => true,
            'issues_count' => count($issues),
            'api_status' => $api_status
        ));
        
        $this->send_success(array(
            'valid' => empty($issues),
            'issues' => $issues,
            'warnings' => $warnings,
            'api_status' => $api_status
        ));
    }
}