<?php
/**
 * File: class-ajax-admin.php
 * Location: /includes/ajax/class-ajax-admin.php
 * 
 * AJAX Admin Handler - Enhanced with emergency button support
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once NEXUS_TRANSLATOR_INCLUDES_DIR . 'ajax/class-ajax-base.php';

class Ajax_Admin extends Ajax_Base {
    
    /**
     * Initialize admin-specific hooks with emergency handlers
     */
    protected function init_hooks() {
        // Core admin handlers
        add_action('wp_ajax_nexus_test_api_connection', array($this, 'handle_test_api_connection'));
        add_action('wp_ajax_nexus_reset_rate_limits', array($this, 'handle_reset_rate_limits'));
        add_action('wp_ajax_nexus_reset_emergency', array($this, 'handle_reset_emergency'));
        add_action('wp_ajax_nexus_export_config', array($this, 'handle_export_config'));
        add_action('wp_ajax_nexus_import_config', array($this, 'handle_import_config'));
        add_action('wp_ajax_nexus_validate_config', array($this, 'handle_validate_config'));
        
        // Emergency handlers - Multiple entry points for reliability
        add_action('wp_ajax_nexus_emergency_cleanup', array($this, 'handle_emergency_cleanup'));
        add_action('wp_ajax_nexus_cleanup_locks', array($this, 'handle_cleanup_locks'));
        add_action('wp_ajax_nexus_emergency_stop', array($this, 'handle_emergency_stop'));
        
        $this->log_debug('Admin AJAX handlers registered with emergency support');
    }
    
    /**
     * Test API connection with enhanced error handling
     */
    public function handle_test_api_connection() {
        $this->validate_ajax_request('manage_options');
        
        // Protection: Avoid simultaneous tests
        $request_key = "test_api_" . get_current_user_id();
        $this->check_duplicate_request($request_key);
        
        try {
            // Get API key from request or settings
            $api_key = null;
            if (isset($_POST['api_key']) && !empty($_POST['api_key'])) {
                $api_key = sanitize_text_field($_POST['api_key']);
                $this->log_debug('Using API key from request');
            } else {
                $api_settings = get_option('nexus_translator_api_settings', array());
                $api_key = $api_settings['claude_api_key'] ?? '';
                $this->log_debug('Using API key from settings');
            }
            
            if (empty($api_key)) {
                $this->send_error('No API key provided', 'NO_API_KEY');
            }
            
            // Test connection
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
            
        } catch (Exception $e) {
            $this->log_error('API test exception: ' . $e->getMessage());
            $this->send_error('API test failed: ' . $e->getMessage(), 'EXCEPTION');
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
            
        } catch (Exception $e) {
            $this->log_error('Rate limit reset exception: ' . $e->getMessage());
            $this->send_error('Reset failed: ' . $e->getMessage(), 'EXCEPTION');
        } finally {
            $this->cleanup_request($request_key);
        }
    }
    
    /**
     * Reset emergency stop - Enhanced version
     */
    public function handle_reset_emergency() {
        $this->validate_ajax_request('manage_options');
        
        $this->log_debug('Resetting emergency stop');
        
        // Clear all emergency-related options
        delete_option('nexus_translator_emergency_stop');
        delete_option('nexus_translator_emergency_reason');
        delete_option('nexus_translator_emergency_time');
        
        // Also clear rate limit blocks that might trigger emergency
        delete_transient('nexus_api_rate_limit_hit');
        
        // Clear any stuck rate limit transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%transient%nexus_api_calls%'");
        
        $this->log_usage('reset_emergency_stop', array('success' => true));
        
        $this->send_success(array(
            'emergency_cleared' => true,
            'rate_limits_cleared' => true
        ), 'Emergency stop reset successfully');
    }
    
    /**
     * Emergency cleanup - Comprehensive version
     */
    public function handle_emergency_cleanup() {
        $this->validate_ajax_request('manage_options');
        
        $request_key = "emergency_cleanup_" . get_current_user_id();
        $this->check_duplicate_request($request_key);
        
        try {
            $this->log_debug('Starting comprehensive emergency cleanup');
            
            $cleaned = array();
            
            // 1. Clear all AJAX requests
            if (class_exists('Ajax_Base')) {
                Ajax_Base::force_cleanup_requests();
                $cleaned[] = 'AJAX requests cleared';
            }
            
            // 2. Clear all translation locks
            global $wpdb;
            $locks_deleted = $wpdb->query(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_nexus_translation_lock'"
            );
            $cleaned[] = sprintf('%d translation locks removed', $locks_deleted);
            
            // 3. Reset stuck translations
            $processing_reset = $wpdb->query(
                "UPDATE {$wpdb->postmeta} 
                 SET meta_value = 'error' 
                 WHERE meta_key = '_nexus_translation_status' 
                 AND meta_value = 'processing'"
            );
            $cleaned[] = sprintf('%d stuck translations reset', $processing_reset);
            
            // 4. Clear rate limits completely
            $this->clear_all_rate_limits();
            $cleaned[] = 'Rate limits cleared';
            
            // 5. Clear emergency stop
            delete_option('nexus_translator_emergency_stop');
            delete_option('nexus_translator_emergency_reason');
            delete_option('nexus_translator_emergency_time');
            $cleaned[] = 'Emergency stop cleared';
            
            // 6. Clear active translations
            delete_option('nexus_translator_active_translations');
            $cleaned[] = 'Active translations cleared';
            
            // 7. Clear WordPress caches
            wp_cache_flush();
            $cleaned[] = 'WordPress caches flushed';
            
            $this->log_usage('emergency_cleanup', array(
                'success' => true,
                'actions_count' => count($cleaned)
            ));
            
            $this->send_success(array(
                'actions' => $cleaned,
                'total_actions' => count($cleaned)
            ), 'Emergency cleanup completed successfully');
            
        } catch (Exception $e) {
            $this->log_error('Emergency cleanup exception: ' . $e->getMessage());
            $this->send_error('Emergency cleanup failed: ' . $e->getMessage(), 'CLEANUP_FAILED');
        } finally {
            $this->cleanup_request($request_key);
        }
    }
    
    /**
     * Handle emergency stop toggle
     */
    public function handle_emergency_stop() {
        $this->validate_ajax_request('manage_options');
        
        $activate = isset($_POST['activate']) && $_POST['activate'] === 'true';
        
        if ($activate) {
            // Activate emergency stop
            update_option('nexus_translator_emergency_stop', true);
            update_option('nexus_translator_emergency_reason', 'Manually activated by administrator');
            update_option('nexus_translator_emergency_time', current_time('timestamp'));
            
            $this->log_usage('emergency_stop_activated', array('manual' => true));
            
            $this->send_success(array(
                'status' => 'emergency_active',
                'reason' => 'Manually activated'
            ), 'Emergency stop activated');
        } else {
            // Deactivate emergency stop
            delete_option('nexus_translator_emergency_stop');
            delete_option('nexus_translator_emergency_reason');
            delete_option('nexus_translator_emergency_time');
            
            $this->log_usage('emergency_stop_deactivated', array('manual' => true));
            
            $this->send_success(array(
                'status' => 'emergency_inactive'
            ), 'Emergency stop deactivated');
        }
    }
    
    /**
     * Cleanup locks specifically
     */
    public function handle_cleanup_locks() {
        $this->validate_ajax_request('manage_options');
        
        $request_key = "cleanup_locks_" . get_current_user_id();
        $this->check_duplicate_request($request_key);
        
        try {
            global $wpdb;
            
            // Remove locks older than 1 hour
            $cutoff = time() - 3600;
            
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} 
                 WHERE meta_key = '_nexus_translation_lock' 
                 AND meta_value < %d",
                $cutoff
            ));
            
            // Reset stale processing status
            $reset = $wpdb->query(
                "UPDATE {$wpdb->postmeta} 
                 SET meta_value = 'error' 
                 WHERE meta_key = '_nexus_translation_status' 
                 AND meta_value = 'processing'"
            );
            
            // Clear active translations option
            $active_translations = get_option('nexus_translator_active_translations', array());
            $cleared_active = 0;
            
            foreach ($active_translations as $key => $timestamp) {
                if ((current_time('timestamp') - $timestamp) > 3600) {
                    unset($active_translations[$key]);
                    $cleared_active++;
                }
            }
            
            if ($cleared_active > 0) {
                update_option('nexus_translator_active_translations', $active_translations);
            }
            
            $this->log_usage('cleanup_locks', array(
                'deleted_locks' => $deleted,
                'reset_status' => $reset,
                'cleared_active' => $cleared_active
            ));
            
            $this->send_success(array(
                'deleted_locks' => $deleted,
                'reset_status' => $reset,
                'cleared_active' => $cleared_active
            ), sprintf('Cleaned up %d locks and reset %d statuses', $deleted, $reset));
            
        } catch (Exception $e) {
            $this->log_error('Cleanup locks exception: ' . $e->getMessage());
            $this->send_error('Lock cleanup failed: ' . $e->getMessage(), 'CLEANUP_FAILED');
        } finally {
            $this->cleanup_request($request_key);
        }
    }
    
    /**
     * Export configuration
     */
    public function handle_export_config() {
        $this->validate_ajax_request('manage_options');
        
        try {
            $config = array(
                'api_settings' => get_option('nexus_translator_api_settings', array()),
                'language_settings' => get_option('nexus_translator_language_settings', array()),
                'general_options' => get_option('nexus_translator_options', array()),
                'exported_at' => current_time('mysql'),
                'plugin_version' => defined('NEXUS_TRANSLATOR_VERSION') ? NEXUS_TRANSLATOR_VERSION : '1.0.0',
                'site_url' => get_site_url(),
                'export_type' => 'full_config'
            );
            
            // Remove sensitive data
            if (isset($config['api_settings']['claude_api_key'])) {
                $config['api_settings']['claude_api_key'] = '[API_KEY_REMOVED]';
            }
            
            $this->log_usage('export_config', array('success' => true));
            
            $this->send_success(array(
                'config' => $config,
                'filename' => 'nexus-translator-config-' . date('Y-m-d-H-i-s') . '.json'
            ));
            
        } catch (Exception $e) {
            $this->log_error('Config export exception: ' . $e->getMessage());
            $this->send_error('Export failed: ' . $e->getMessage(), 'EXPORT_FAILED');
        }
    }
    
    /**
     * Import configuration
     */
    public function handle_import_config() {
        $this->validate_ajax_request('manage_options');
        
        if (!isset($_POST['config_data'])) {
            $this->send_error('No configuration data provided', 'NO_CONFIG_DATA');
        }
        
        try {
            $config_data = json_decode(stripslashes($_POST['config_data']), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->send_error('Invalid JSON configuration', 'INVALID_JSON');
            }
            
            $imported = 0;
            $warnings = array();
            
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
            $this->log_error('Config import exception: ' . $e->getMessage());
            $this->send_error('Import failed: ' . $e->getMessage(), 'IMPORT_FAILED');
        }
    }
    
    /**
     * Validate configuration
     */
    public function handle_validate_config() {
        $this->validate_ajax_request('manage_options');
        
        try {
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
            
        } catch (Exception $e) {
            $this->log_error('Config validation exception: ' . $e->getMessage());
            $this->send_error('Validation failed: ' . $e->getMessage(), 'VALIDATION_FAILED');
        }
    }
    
    /**
     * Clear all rate limits helper
     */
    private function clear_all_rate_limits() {
        global $wpdb;
        
        // Clear all API call transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%transient%nexus_api_calls%'");
        
        // Clear specific rate limit transients
        delete_transient('nexus_translator_rate_limit_hour');
        delete_transient('nexus_translator_rate_limit_day');
        delete_transient('nexus_translator_last_request');
        delete_transient('nexus_api_rate_limit_hit');
        
        $this->log_debug('All rate limits cleared');
    }
}