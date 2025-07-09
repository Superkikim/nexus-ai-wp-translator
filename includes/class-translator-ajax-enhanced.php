<?php
/**
 * File: class-translator-ajax-enhanced.php
 * Location: /includes/class-translator-ajax-enhanced.php
 * 
 * Enhanced AJAX Handler for Analytics and Advanced Features
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translator_AJAX_Enhanced {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Analytics AJAX endpoints
        add_action('wp_ajax_nexus_get_translation_analytics', array($this, 'get_translation_analytics'));
        add_action('wp_ajax_nexus_export_analytics', array($this, 'export_analytics'));
        add_action('wp_ajax_nexus_clear_analytics', array($this, 'clear_analytics'));
        
        // Advanced management endpoints
        add_action('wp_ajax_nexus_cleanup_locks', array($this, 'cleanup_translation_locks'));
        add_action('wp_ajax_nexus_validate_config', array($this, 'validate_configuration'));
        add_action('wp_ajax_nexus_emergency_cleanup', array($this, 'emergency_cleanup'));
        
        // Bulk operations
        add_action('wp_ajax_nexus_bulk_translate', array($this, 'bulk_translate_posts'));
        add_action('wp_ajax_nexus_bulk_status', array($this, 'get_bulk_status'));
    }
    
    /**
     * Get comprehensive translation analytics
     */
    public function get_translation_analytics($days = 30) {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (isset($_POST['days'])) {
            $days = max(1, min(365, (int) $_POST['days']));
        }
        
        global $wpdb;
        
        $since_timestamp = strtotime("-{$days} days");
        
        // Get basic statistics
        $total_translations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_nexus_translation_timestamp' 
             AND meta_value > %d",
            $since_timestamp
        ));
        
        $successful_translations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm1
             JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id
             WHERE pm1.meta_key = '_nexus_translation_timestamp' 
             AND pm1.meta_value > %d
             AND pm2.meta_key = '_nexus_translation_status'
             AND pm2.meta_value = 'completed'",
            $since_timestamp
        ));
        
        // Language breakdown
        $language_stats = $wpdb->get_results(
            "SELECT pm1.meta_value as language, 
                    COUNT(*) as total,
                    SUM(CASE WHEN pm2.meta_value = 'completed' THEN 1 ELSE 0 END) as successful,
                    SUM(CASE WHEN pm2.meta_value = 'error' THEN 1 ELSE 0 END) as failed
             FROM {$wpdb->postmeta} pm1
             JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id
             WHERE pm1.meta_key = '_nexus_language'
             AND pm2.meta_key = '_nexus_translation_status'
             GROUP BY pm1.meta_value"
        );
        
        $language_breakdown = array();
        foreach ($language_stats as $stat) {
            $language_breakdown[$stat->language] = array(
                'total' => (int) $stat->total,
                'successful' => (int) $stat->successful,
                'failed' => (int) $stat->failed
            );
        }
        
        // Recent activity
        $recent_activity = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID as post_id, p.post_title,
                    pm1.meta_value as timestamp,
                    pm2.meta_value as language,
                    pm3.meta_value as status,
                    pm4.meta_value as original_id,
                    u.user_login
             FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_nexus_translation_timestamp'
             JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_nexus_language'
             JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_nexus_translation_status'
             LEFT JOIN {$wpdb->postmeta} pm4 ON p.ID = pm4.post_id AND pm4.meta_key = '_nexus_translation_of'
             LEFT JOIN {$wpdb->users} u ON p.post_author = u.ID
             WHERE pm1.meta_value > %d
             ORDER BY pm1.meta_value DESC
             LIMIT 50",
            $since_timestamp
        ));
        
        $activity_list = array();
        foreach ($recent_activity as $activity) {
            $activity_list[] = array(
                'post_id' => (int) $activity->post_id,
                'post_title' => $activity->post_title,
                'timestamp' => (int) $activity->timestamp,
                'target_language' => $activity->language,
                'status' => $activity->status,
                'success' => $activity->status === 'completed',
                'user_login' => $activity->user_login ?: 'System',
                'original_id' => $activity->original_id ? (int) $activity->original_id : null
            );
        }
        
        // Daily breakdown for charts
        $daily_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(FROM_UNIXTIME(pm1.meta_value)) as date,
                    COUNT(*) as total,
                    SUM(CASE WHEN pm2.meta_value = 'completed' THEN 1 ELSE 0 END) as successful
             FROM {$wpdb->postmeta} pm1
             JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id
             WHERE pm1.meta_key = '_nexus_translation_timestamp'
             AND pm1.meta_value > %d
             AND pm2.meta_key = '_nexus_translation_status'
             GROUP BY DATE(FROM_UNIXTIME(pm1.meta_value))
             ORDER BY date ASC",
            $since_timestamp
        ));
        
        $daily_breakdown = array();
        foreach ($daily_stats as $day) {
            $daily_breakdown[] = array(
                'date' => $day->date,
                'total' => (int) $day->total,
                'successful' => (int) $day->successful,
                'failed' => (int) $day->total - (int) $day->successful
            );
        }
        
        // Token usage (if tracked)
        $total_tokens = get_option('nexus_translator_total_tokens', 0);
        $token_cost = get_option('nexus_translator_estimated_cost', 0);
        
        // Error analysis
        $error_types = $wpdb->get_results(
            "SELECT pm2.meta_value as error_type, COUNT(*) as count
             FROM {$wpdb->postmeta} pm1
             JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id
             WHERE pm1.meta_key = '_nexus_translation_status'
             AND pm1.meta_value = 'error'
             AND pm2.meta_key = '_nexus_translation_error'
             GROUP BY pm2.meta_value
             ORDER BY count DESC"
        );
        
        $error_breakdown = array();
        foreach ($error_types as $error) {
            $error_breakdown[$error->error_type] = (int) $error->count;
        }
        
        $analytics = array(
            'totals' => array(
                'requests' => (int) $total_translations,
                'successful' => (int) $successful_translations,
                'failed' => (int) $total_translations - (int) $successful_translations,
                'tokens' => (int) $total_tokens,
                'estimated_cost' => (float) $token_cost
            ),
            'language_breakdown' => $language_breakdown,
            'recent_activity' => $activity_list,
            'daily_breakdown' => $daily_breakdown,
            'error_breakdown' => $error_breakdown,
            'period_days' => $days,
            'generated_at' => current_time('mysql')
        );
        
        if (defined('DOING_AJAX') && DOING_AJAX) {
            wp_send_json_success($analytics);
        }
        
        return $analytics;
    }
    
    /**
     * Export analytics data
     */
    public function export_analytics() {
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }
        
        $days = isset($_POST['days']) ? max(1, min(365, (int) $_POST['days'])) : 30;
        $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'json';
        
        $analytics = $this->get_translation_analytics($days);
        
        $filename = 'nexus-translator-analytics-' . date('Y-m-d-H-i-s');
        
        if ($format === 'csv') {
            $csv_data = $this->convert_analytics_to_csv($analytics);
            wp_send_json_success(array(
                'data' => $csv_data,
                'filename' => $filename . '.csv',
                'mime_type' => 'text/csv'
            ));
        } else {
            wp_send_json_success(array(
                'data' => json_encode($analytics, JSON_PRETTY_PRINT),
                'filename' => $filename . '.json',
                'mime_type' => 'application/json'
            ));
        }
    }
    
    /**
     * Clear analytics data
     */
    public function clear_analytics() {
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }
        
        global $wpdb;
        
        // Remove translation metadata older than retention period
        $retention_days = get_option('nexus_translator_analytics_retention', 30);
        $cutoff_timestamp = strtotime("-{$retention_days} days");
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE pm FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id
             WHERE pm2.meta_key = '_nexus_translation_timestamp'
             AND pm2.meta_value < %d
             AND pm.meta_key LIKE '_nexus_%'",
            $cutoff_timestamp
        ));
        
        // Reset counters
        delete_option('nexus_translator_total_tokens');
        delete_option('nexus_translator_estimated_cost');
        
        wp_send_json_success(array(
            'message' => sprintf(__('Cleared %d analytics records older than %d days', 'nexus-ai-wp-translator'), $deleted, $retention_days)
        ));
    }
    
    /**
     * Cleanup translation locks
     */
    public function cleanup_translation_locks() {
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }
        
        global $wpdb;
        
        // Remove locks older than 1 hour
        $cutoff = time() - 3600;
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} 
             WHERE meta_key = '_nexus_translation_lock' 
             AND meta_value < %d",
            $cutoff
        ));
        
        // Remove stale processing status
        $wpdb->query(
            "UPDATE {$wpdb->postmeta} 
             SET meta_value = 'error' 
             WHERE meta_key = '_nexus_translation_status' 
             AND meta_value = 'processing'"
        );
        
        wp_send_json_success(array(
            'message' => sprintf(__('Cleaned up %d stale translation locks', 'nexus-ai-wp-translator'), $deleted)
        ));
    }
    
    /**
     * Validate configuration
     */
    public function validate_configuration() {
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }
        
        $issues = array();
        $warnings = array();
        
        // Check API settings
        $api_settings = get_option('nexus_translator_api_settings', array());
        
        if (empty($api_settings['claude_api_key'])) {
            $issues[] = __('Claude API key is not configured', 'nexus-ai-wp-translator');
        } elseif (strlen($api_settings['claude_api_key']) < 50) {
            $warnings[] = __('API key appears to be too short', 'nexus-ai-wp-translator');
        }
        
        if (empty($api_settings['model'])) {
            $issues[] = __('Claude model is not selected', 'nexus-ai-wp-translator');
        }
        
        // Check language settings
        $lang_settings = get_option('nexus_translator_language_settings', array());
        
        if (empty($lang_settings['source_language'])) {
            $issues[] = __('Source language is not configured', 'nexus-ai-wp-translator');
        }
        
        if (empty($lang_settings['target_languages'])) {
            $issues[] = __('No target languages selected', 'nexus-ai-wp-translator');
        }
        
        // Check rate limits
        if (isset($api_settings['max_calls_per_hour']) && $api_settings['max_calls_per_hour'] > 1000) {
            $warnings[] = __('Hourly rate limit is very high - may cause API throttling', 'nexus-ai-wp-translator');
        }
        
        // Check emergency status
        if (get_option('nexus_translator_emergency_stop', false)) {
            $issues[] = __('Emergency stop is currently active', 'nexus-ai-wp-translator');
        }
        
        // Test API connection if key is present
        $api_status = 'unknown';
        if (!empty($api_settings['claude_api_key'])) {
            if (class_exists('Translator_API')) {
                $api = new Translator_API();
                $test_result = $api->test_connection();
                $api_status = $test_result['success'] ? 'connected' : 'error';
                
                if (!$test_result['success']) {
                    $issues[] = sprintf(__('API connection failed: %s', 'nexus-ai-wp-translator'), $test_result['message']);
                }
            }
        }
        
        $validation = array(
            'valid' => empty($issues),
            'issues' => $issues,
            'warnings' => $warnings,
            'api_status' => $api_status,
            'checked_at' => current_time('mysql')
        );
        
        wp_send_json_success($validation);
    }
    
    /**
     * Emergency cleanup - reset everything
     */
    public function emergency_cleanup() {
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }
        
        global $wpdb;
        
        $cleaned = array();
        
        // Clear all locks
        $locks_deleted = $wpdb->query(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_nexus_translation_lock'"
        );
        $cleaned[] = sprintf(__('%d translation locks removed', 'nexus-ai-wp-translator'), $locks_deleted);
        
        // Reset processing translations to error
        $processing_reset = $wpdb->query(
            "UPDATE {$wpdb->postmeta} 
             SET meta_value = 'error' 
             WHERE meta_key = '_nexus_translation_status' 
             AND meta_value = 'processing'"
        );
        $cleaned[] = sprintf(__('%d stuck translations reset', 'nexus-ai-wp-translator'), $processing_reset);
        
        // Clear rate limits
        delete_transient('nexus_translator_rate_limit_hour');
        delete_transient('nexus_translator_rate_limit_day');
        delete_transient('nexus_translator_last_request');
        $cleaned[] = __('Rate limits reset', 'nexus-ai-wp-translator');
        
        // Clear emergency stop
        delete_option('nexus_translator_emergency_stop');
        delete_option('nexus_translator_emergency_reason');
        delete_option('nexus_translator_emergency_time');
        $cleaned[] = __('Emergency stop cleared', 'nexus-ai-wp-translator');
        
        // Clear any cached errors
        wp_cache_flush();
        
        wp_send_json_success(array(
            'message' => __('Emergency cleanup completed', 'nexus-ai-wp-translator'),
            'actions' => $cleaned
        ));
    }
    
    /**
     * Bulk translate posts
     */
    public function bulk_translate_posts() {
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce') || !current_user_can('edit_posts')) {
            wp_send_json_error('Access denied');
        }
        
        $post_ids = isset($_POST['post_ids']) ? array_map('intval', $_POST['post_ids']) : array();
        $target_languages = isset($_POST['languages']) ? array_map('sanitize_text_field', $_POST['languages']) : array();
        
        if (empty($post_ids) || empty($target_languages)) {
            wp_send_json_error('No posts or languages specified');
        }
        
        // Start background process
        $batch_id = 'bulk_' . time() . '_' . wp_generate_password(8, false);
        
        update_option('nexus_bulk_translation_' . $batch_id, array(
            'post_ids' => $post_ids,
            'languages' => $target_languages,
            'status' => 'queued',
            'progress' => 0,
            'total' => count($post_ids) * count($target_languages),
            'started_at' => current_time('mysql'),
            'completed' => 0,
            'failed' => 0
        ), false);
        
        // Schedule the bulk translation
        wp_schedule_single_event(time() + 5, 'nexus_process_bulk_translation', array($batch_id));
        
        wp_send_json_success(array(
            'batch_id' => $batch_id,
            'message' => sprintf(__('Bulk translation queued: %d posts in %d languages', 'nexus-ai-wp-translator'), 
                count($post_ids), count($target_languages))
        ));
    }
    
    /**
     * Get bulk translation status
     */
    public function get_bulk_status() {
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce') || !current_user_can('edit_posts')) {
            wp_send_json_error('Access denied');
        }
        
        $batch_id = isset($_POST['batch_id']) ? sanitize_text_field($_POST['batch_id']) : '';
        
        if (empty($batch_id)) {
            wp_send_json_error('No batch ID provided');
        }
        
        $batch_data = get_option('nexus_bulk_translation_' . $batch_id, false);
        
        if (!$batch_data) {
            wp_send_json_error('Batch not found');
        }
        
        wp_send_json_success($batch_data);
    }
    
    /**
     * Convert analytics to CSV format
     */
    private function convert_analytics_to_csv($analytics) {
        $csv = '';
        
        // Header
        $csv .= "Nexus AI Translator Analytics Report\n";
        $csv .= "Generated: " . $analytics['generated_at'] . "\n";
        $csv .= "Period: " . $analytics['period_days'] . " days\n\n";
        
        // Summary
        $csv .= "SUMMARY\n";
        $csv .= "Total Translations," . $analytics['totals']['requests'] . "\n";
        $csv .= "Successful," . $analytics['totals']['successful'] . "\n";
        $csv .= "Failed," . $analytics['totals']['failed'] . "\n";
        $csv .= "Total Tokens," . $analytics['totals']['tokens'] . "\n";
        $csv .= "Estimated Cost," . $analytics['totals']['estimated_cost'] . "\n\n";
        
        // Language breakdown
        $csv .= "LANGUAGE BREAKDOWN\n";
        $csv .= "Language,Total,Successful,Failed\n";
        foreach ($analytics['language_breakdown'] as $lang => $stats) {
            $csv .= "{$lang},{$stats['total']},{$stats['successful']},{$stats['failed']}\n";
        }
        $csv .= "\n";
        
        // Daily breakdown
        $csv .= "DAILY BREAKDOWN\n";
        $csv .= "Date,Total,Successful,Failed\n";
        foreach ($analytics['daily_breakdown'] as $day) {
            $csv .= "{$day['date']},{$day['total']},{$day['successful']},{$day['failed']}\n";
        }
        $csv .= "\n";
        
        // Error breakdown
        if (!empty($analytics['error_breakdown'])) {
            $csv .= "ERROR BREAKDOWN\n";
            $csv .= "Error Type,Count\n";
            foreach ($analytics['error_breakdown'] as $error => $count) {
                $csv .= "{$error},{$count}\n";
            }
        }
        
        return $csv;
    }
}