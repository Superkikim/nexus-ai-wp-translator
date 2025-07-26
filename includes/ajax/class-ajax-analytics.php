<?php
/**
 * File: class-ajax-analytics.php
 * Location: /includes/ajax/class-ajax-analytics.php
 * 
 * AJAX Analytics Handler - Complete analytics and monitoring functionality
 * Extends Ajax_Base for security and protection features
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once NEXUS_TRANSLATOR_INCLUDES_DIR . 'ajax/class-ajax-base.php';

class Ajax_Analytics extends Ajax_Base {
    
    /**
     * Initialize analytics-specific hooks
     */
    protected function init_hooks() {
        // Core analytics handlers
        add_action('wp_ajax_nexus_get_analytics_data', array($this, 'handle_get_analytics_data'));
        add_action('wp_ajax_nexus_get_system_status', array($this, 'handle_get_system_status'));
        add_action('wp_ajax_nexus_get_usage_stats', array($this, 'handle_get_usage_stats'));
        add_action('wp_ajax_nexus_export_analytics', array($this, 'handle_export_analytics'));
        
        // System monitoring handlers
        add_action('wp_ajax_nexus_get_api_status', array($this, 'handle_get_api_status'));
        add_action('wp_ajax_nexus_get_rate_limit_status', array($this, 'handle_get_rate_limit_status'));
        add_action('wp_ajax_nexus_monitor_performance', array($this, 'handle_monitor_performance'));
        
        // Cleanup and maintenance handlers
        add_action('wp_ajax_nexus_cleanup_analytics', array($this, 'handle_cleanup_analytics'));
        add_action('wp_ajax_nexus_reset_analytics', array($this, 'handle_reset_analytics'));
        add_action('wp_ajax_nexus_cleanup_locks', array($this, 'handle_cleanup_locks'));
        
        // Advanced analytics handlers
        add_action('wp_ajax_nexus_get_translation_trends', array($this, 'handle_get_translation_trends'));
        add_action('wp_ajax_nexus_get_error_analysis', array($this, 'handle_get_error_analysis'));
        add_action('wp_ajax_nexus_get_user_activity', array($this, 'handle_get_user_activity'));
        
        error_log('Nexus AJAX Analytics: All handlers registered');
    }
    
    /**
     * Handle get analytics data
     */
    public function handle_get_analytics_data() {
        $this->validate_ajax_request('manage_options');
        
        $period = sanitize_text_field($_POST['period'] ?? '30days');
        $type = sanitize_text_field($_POST['type'] ?? 'overview');
        
        $analytics_data = $this->get_analytics_data($period, $type);
        
        $this->log_usage('view_analytics', array(
            'period' => $period,
            'type' => $type
        ));
        
        $this->send_success($analytics_data, 'Analytics data retrieved');
    }
    
    /**
     * Handle get system status
     */
    public function handle_get_system_status() {
        $this->validate_ajax_request('manage_options');
        
        $system_status = $this->get_comprehensive_system_status();
        
        $this->log_usage('view_system_status');
        
        $this->send_success($system_status, 'System status retrieved');
    }
    
    /**
     * Handle get usage statistics
     */
    public function handle_get_usage_stats() {
        $this->validate_ajax_request('manage_options');
        
        $period = sanitize_text_field($_POST['period'] ?? '7days');
        $breakdown = sanitize_text_field($_POST['breakdown'] ?? 'daily');
        
        $usage_stats = $this->get_usage_statistics($period, $breakdown);
        
        $this->send_success($usage_stats, 'Usage statistics retrieved');
    }
    
    /**
     * Handle export analytics
     */
    public function handle_export_analytics() {
        $this->validate_ajax_request('manage_options');
        
        $format = sanitize_text_field($_POST['format'] ?? 'json');
        $period = sanitize_text_field($_POST['period'] ?? '30days');
        
        $export_data = $this->export_analytics_data($format, $period);
        
        if ($export_data['success']) {
            $this->log_usage('export_analytics', array(
                'format' => $format,
                'period' => $period,
                'size' => strlen($export_data['data'])
            ));
            
            $this->send_success($export_data, 'Analytics exported successfully');
        } else {
            $this->send_error($export_data['error'], 'EXPORT_FAILED');
        }
    }
    
    /**
     * Handle get API status
     */
    public function handle_get_api_status() {
        $this->validate_ajax_request('manage_options');
        
        $api = new Translator_API();
        $api_status = array(
            'connection_status' => $api->test_connection(),
            'rate_limit_status' => $api->get_rate_limit_status(),
            'recent_errors' => $this->get_recent_api_errors(),
            'api_usage_today' => $this->get_api_usage_today(),
            'api_health_score' => $this->calculate_api_health_score()
        );
        
        $this->send_success($api_status, 'API status retrieved');
    }
    
    /**
     * Handle get rate limit status
     */
    public function handle_get_rate_limit_status() {
        $this->validate_ajax_request('edit_posts');
        
        $api = new Translator_API();
        $rate_status = $api->get_rate_limit_status();
        
        $this->send_success($rate_status, 'Rate limit status retrieved');
    }
    
    /**
     * Handle monitor performance
     */
    public function handle_monitor_performance() {
        $this->validate_ajax_request('manage_options');
        
        $performance_data = $this->get_performance_metrics();
        
        $this->send_success($performance_data, 'Performance metrics retrieved');
    }
    
    /**
     * Handle cleanup analytics
     */
    public function handle_cleanup_analytics() {
        $this->validate_ajax_request('manage_options');
        
        $retention_days = (int) ($_POST['retention_days'] ?? 30);
        
        if ($retention_days < 1 || $retention_days > 365) {
            $this->send_error('Invalid retention period (1-365 days)', 'INVALID_RETENTION');
        }
        
        $cleanup_result = $this->cleanup_old_analytics($retention_days);
        
        $this->log_usage('cleanup_analytics', array(
            'retention_days' => $retention_days,
            'records_cleaned' => $cleanup_result['cleaned_count']
        ));
        
        $this->send_success($cleanup_result, 'Analytics cleanup completed');
    }
    
    /**
     * Handle reset analytics
     */
    public function handle_reset_analytics() {
        $this->validate_ajax_request('manage_options');
        
        if (!isset($_POST['confirm']) || $_POST['confirm'] !== 'yes') {
            $this->send_error('Confirmation required', 'CONFIRMATION_REQUIRED');
        }
        
        $reset_result = $this->reset_all_analytics();
        
        $this->log_usage('reset_analytics', array(
            'reset_items' => $reset_result['reset_items']
        ));
        
        $this->send_success($reset_result, 'Analytics reset completed');
    }
    
    /**
     * Handle cleanup locks
     */
    public function handle_cleanup_locks() {
        $this->validate_ajax_request('manage_options');
        
        $cleanup_result = $this->cleanup_translation_locks();
        
        $this->log_usage('cleanup_locks', array(
            'locks_cleaned' => $cleanup_result['cleaned_count']
        ));
        
        $this->send_success($cleanup_result, 'Translation locks cleaned');
    }
    
    /**
     * Handle get translation trends
     */
    public function handle_get_translation_trends() {
        $this->validate_ajax_request('manage_options');
        
        $period = sanitize_text_field($_POST['period'] ?? '30days');
        $group_by = sanitize_text_field($_POST['group_by'] ?? 'day');
        
        $trends_data = $this->get_translation_trends($period, $group_by);
        
        $this->send_success($trends_data, 'Translation trends retrieved');
    }
    
    /**
     * Handle get error analysis
     */
    public function handle_get_error_analysis() {
        $this->validate_ajax_request('manage_options');
        
        $period = sanitize_text_field($_POST['period'] ?? '7days');
        
        $error_analysis = $this->get_error_analysis($period);
        
        $this->send_success($error_analysis, 'Error analysis retrieved');
    }
    
    /**
     * Handle get user activity
     */
    public function handle_get_user_activity() {
        $this->validate_ajax_request('manage_options');
        
        $period = sanitize_text_field($_POST['period'] ?? '7days');
        $user_id = (int) ($_POST['user_id'] ?? 0);
        
        $activity_data = $this->get_user_activity($period, $user_id);
        
        $this->send_success($activity_data, 'User activity retrieved');
    }
    
    /**
     * Get comprehensive analytics data
     */
    private function get_analytics_data($period, $type) {
        $data = array();
        
        switch ($type) {
            case 'overview':
                $data = $this->get_overview_analytics($period);
                break;
            case 'detailed':
                $data = $this->get_detailed_analytics($period);
                break;
            case 'performance':
                $data = $this->get_performance_analytics($period);
                break;
            default:
                $data = $this->get_overview_analytics($period);
        }
        
        return $data;
    }
    
    /**
     * Get overview analytics
     */
    private function get_overview_analytics($period) {
        $usage_log = get_option('nexus_translator_usage_log', array());
        $cutoff_time = $this->get_period_cutoff($period);
        
        $filtered_log = array_filter($usage_log, function($entry) use ($cutoff_time) {
            return $entry['timestamp'] >= $cutoff_time;
        });
        
        // Calculate statistics
        $total_translations = 0;
        $successful_translations = 0;
        $failed_translations = 0;
        $languages_used = array();
        $daily_counts = array();
        
        foreach ($filtered_log as $entry) {
            if ($entry['action'] === 'translate_post') {
                $total_translations++;
                if (isset($entry['success']) && $entry['success']) {
                    $successful_translations++;
                } else {
                    $failed_translations++;
                }
                
                if (isset($entry['target_language'])) {
                    $languages_used[$entry['target_language']] = ($languages_used[$entry['target_language']] ?? 0) + 1;
                }
                
                $date = date('Y-m-d', $entry['timestamp']);
                $daily_counts[$date] = ($daily_counts[$date] ?? 0) + 1;
            }
        }
        
        $success_rate = $total_translations > 0 ? ($successful_translations / $total_translations) * 100 : 0;
        
        return array(
            'period' => $period,
            'total_translations' => $total_translations,
            'successful_translations' => $successful_translations,
            'failed_translations' => $failed_translations,
            'success_rate' => round($success_rate, 2),
            'languages_used' => $languages_used,
            'daily_counts' => $daily_counts,
            'average_per_day' => count($daily_counts) > 0 ? round($total_translations / count($daily_counts), 2) : 0
        );
    }
    
    /**
     * Get detailed analytics
     */
    private function get_detailed_analytics($period) {
        $overview = $this->get_overview_analytics($period);
        
        // Add detailed metrics
        $overview['user_breakdown'] = $this->get_user_breakdown($period);
        $overview['post_type_breakdown'] = $this->get_post_type_breakdown($period);
        $overview['error_breakdown'] = $this->get_error_breakdown($period);
        $overview['timing_analysis'] = $this->get_timing_analysis($period);
        
        return $overview;
    }
    
    /**
     * Get performance analytics
     */
    private function get_performance_analytics($period) {
        return array(
            'system_performance' => $this->get_performance_metrics(),
            'api_performance' => $this->get_api_performance_metrics($period),
            'memory_usage' => $this->get_memory_usage_stats($period),
            'response_times' => $this->get_response_time_analysis($period)
        );
    }
    
    /**
     * Get comprehensive system status
     */
    private function get_comprehensive_system_status() {
        // Get base system status
        $status = array(
            'wordpress' => array(
                'version' => get_bloginfo('version'),
                'multisite' => is_multisite(),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize')
            ),
            'php' => array(
                'version' => PHP_VERSION,
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
                'extensions' => array(
                    'curl' => extension_loaded('curl'),
                    'json' => extension_loaded('json'),
                    'mbstring' => extension_loaded('mbstring')
                )
            ),
            'plugin' => array(
                'version' => defined('NEXUS_TRANSLATOR_VERSION') ? NEXUS_TRANSLATOR_VERSION : 'Unknown',
                'emergency_stop' => get_option('nexus_translator_emergency_stop', false),
                'active_translations' => count(get_option('nexus_translator_active_translations', array())),
                'scheduled_events' => array(
                    'daily_cleanup' => wp_next_scheduled('nexus_daily_cleanup'),
                    'analytics_cleanup' => wp_next_scheduled('nexus_translator_cleanup_analytics')
                )
            ),
            'api' => array(),
            'database' => array()
        );
        
        // Get API status
        if (class_exists('Translator_API')) {
            $api = new Translator_API();
            $status['api'] = array(
                'configured' => $api->is_configured(),
                'connection' => $api->test_connection(),
                'rate_limits' => $api->get_rate_limit_status()
            );
        }
        
        // Get database status
        global $wpdb;
        $status['database'] = array(
            'version' => $wpdb->db_version(),
            'charset' => $wpdb->charset,
            'collate' => $wpdb->collate,
            'tables_exist' => $this->check_required_tables()
        );
        
        // Get AJAX handler status
        if (class_exists('Ajax_Base')) {
            $status['ajax'] = Ajax_Base::get_system_status();
        }
        
        return $status;
    }
    
    /**
     * Get usage statistics
     */
    private function get_usage_statistics($period, $breakdown) {
        $usage_log = get_option('nexus_translator_usage_log', array());
        $cutoff_time = $this->get_period_cutoff($period);
        
        $filtered_log = array_filter($usage_log, function($entry) use ($cutoff_time) {
            return $entry['timestamp'] >= $cutoff_time;
        });
        
        $stats = array();
        
        foreach ($filtered_log as $entry) {
            $key = $this->get_breakdown_key($entry['timestamp'], $breakdown);
            
            if (!isset($stats[$key])) {
                $stats[$key] = array(
                    'translations' => 0,
                    'successes' => 0,
                    'failures' => 0,
                    'users' => array(),
                    'languages' => array()
                );
            }
            
            if ($entry['action'] === 'translate_post') {
                $stats[$key]['translations']++;
                
                if (isset($entry['success']) && $entry['success']) {
                    $stats[$key]['successes']++;
                } else {
                    $stats[$key]['failures']++;
                }
                
                $stats[$key]['users'][$entry['user_id']] = true;
                
                if (isset($entry['target_language'])) {
                    $stats[$key]['languages'][$entry['target_language']] = ($stats[$key]['languages'][$entry['target_language']] ?? 0) + 1;
                }
            }
        }
        
        // Convert user arrays to counts
        foreach ($stats as &$stat) {
            $stat['unique_users'] = count($stat['users']);
            unset($stat['users']);
        }
        
        return array(
            'period' => $period,
            'breakdown' => $breakdown,
            'data' => $stats
        );
    }
    
    /**
     * Export analytics data
     */
    private function export_analytics_data($format, $period) {
        $analytics = $this->get_detailed_analytics($period);
        $usage_stats = $this->get_usage_statistics($period, 'daily');
        $system_status = $this->get_comprehensive_system_status();
        
        $export_data = array(
            'exported_at' => current_time('mysql'),
            'period' => $period,
            'analytics' => $analytics,
            'usage_statistics' => $usage_stats,
            'system_status' => $system_status
        );
        
        switch ($format) {
            case 'json':
                return array(
                    'success' => true,
                    'data' => json_encode($export_data, JSON_PRETTY_PRINT),
                    'filename' => 'nexus-analytics-' . date('Y-m-d-H-i-s') . '.json',
                    'mime_type' => 'application/json'
                );
                
            case 'csv':
                $csv_data = $this->convert_analytics_to_csv($analytics);
                return array(
                    'success' => true,
                    'data' => $csv_data,
                    'filename' => 'nexus-analytics-' . date('Y-m-d-H-i-s') . '.csv',
                    'mime_type' => 'text/csv'
                );
                
            default:
                return array(
                    'success' => false,
                    'error' => 'Unsupported export format'
                );
        }
    }
    
    /**
     * Cleanup old analytics data
     */
    private function cleanup_old_analytics($retention_days) {
        $cutoff_timestamp = strtotime("-{$retention_days} days");
        $cleaned_count = 0;
        
        // Cleanup usage log
        $usage_log = get_option('nexus_translator_usage_log', array());
        $original_count = count($usage_log);
        
        $filtered_log = array_filter($usage_log, function($entry) use ($cutoff_timestamp) {
            return $entry['timestamp'] >= $cutoff_timestamp;
        });
        
        update_option('nexus_translator_usage_log', array_values($filtered_log));
        $cleaned_count += $original_count - count($filtered_log);
        
        // Cleanup daily stats
        $daily_stats = get_option('nexus_translator_daily_stats', array());
        $cutoff_date = date('Y-m-d', $cutoff_timestamp);
        
        $original_days = count($daily_stats);
        $filtered_stats = array_filter($daily_stats, function($date) use ($cutoff_date) {
            return $date >= $cutoff_date;
        }, ARRAY_FILTER_USE_KEY);
        
        update_option('nexus_translator_daily_stats', $filtered_stats);
        $cleaned_count += $original_days - count($filtered_stats);
        
        // Cleanup postmeta
        global $wpdb;
        $meta_cleaned = $wpdb->query($wpdb->prepare(
            "DELETE pm FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id
             WHERE pm2.meta_key = '_nexus_translation_timestamp'
             AND pm2.meta_value < %d
             AND pm.meta_key IN ('_nexus_translation_timestamp', '_nexus_translation_error', '_nexus_translation_usage')",
            $cutoff_timestamp
        ));
        
        $cleaned_count += $meta_cleaned;
        
        return array(
            'cleaned_count' => $cleaned_count,
            'retention_days' => $retention_days,
            'cutoff_date' => date('Y-m-d H:i:s', $cutoff_timestamp)
        );
    }
    
    /**
     * Reset all analytics
     */
    private function reset_all_analytics() {
        $reset_items = array();
        
        // Reset usage log
        if (delete_option('nexus_translator_usage_log')) {
            $reset_items[] = 'usage_log';
        }
        
        // Reset daily stats
        if (delete_option('nexus_translator_daily_stats')) {
            $reset_items[] = 'daily_stats';
        }
        
        // Reset analytics options
        $analytics_options = array(
            'nexus_translator_analytics_cache',
            'nexus_translator_performance_log',
            'nexus_translator_error_log'
        );
        
        foreach ($analytics_options as $option) {
            if (delete_option($option)) {
                $reset_items[] = $option;
            }
        }
        
        // Clear analytics-related postmeta
        global $wpdb;
        $meta_deleted = $wpdb->query(
            "DELETE FROM {$wpdb->postmeta} 
             WHERE meta_key IN ('_nexus_translation_timestamp', '_nexus_translation_error', '_nexus_translation_usage')"
        );
        
        if ($meta_deleted > 0) {
            $reset_items[] = "postmeta ({$meta_deleted} records)";
        }
        
        return array(
            'reset_items' => $reset_items,
            'total_items' => count($reset_items)
        );
    }
    
    /**
     * Cleanup translation locks
     */
    private function cleanup_translation_locks() {
        $cleaned_count = 0;
        
        // Clear active translations
        $active_translations = get_option('nexus_translator_active_translations', array());
        $original_count = count($active_translations);
        
        if ($original_count > 0) {
            delete_option('nexus_translator_active_translations');
            $cleaned_count += $original_count;
        }
        
        // Clear AJAX base locks
        if (class_exists('Ajax_Base')) {
            $ajax_cleaned = Ajax_Base::force_cleanup_requests();
            $cleaned_count += $ajax_cleaned;
        }
        
        // Clear transients
        $transients_cleared = 0;
        global $wpdb;
        $transients = $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_nexus_%' 
             OR option_name LIKE '_transient_timeout_nexus_%'"
        );
        $cleaned_count += $transients;
        
        return array(
            'cleaned_count' => $cleaned_count,
            'active_translations_cleared' => $original_count,
            'ajax_locks_cleared' => $ajax_cleaned ?? 0,
            'transients_cleared' => $transients
        );
    }
    
    /**
     * Helper methods
     */
    private function get_period_cutoff($period) {
        switch ($period) {
            case '1day':
                return strtotime('-1 day');
            case '7days':
                return strtotime('-7 days');
            case '30days':
                return strtotime('-30 days');
            case '90days':
                return strtotime('-90 days');
            case '1year':
                return strtotime('-1 year');
            default:
                return strtotime('-30 days');
        }
    }
    
    private function get_breakdown_key($timestamp, $breakdown) {
        switch ($breakdown) {
            case 'hourly':
                return date('Y-m-d H:00', $timestamp);
            case 'daily':
                return date('Y-m-d', $timestamp);
            case 'weekly':
                return date('Y-W', $timestamp);
            case 'monthly':
                return date('Y-m', $timestamp);
            default:
                return date('Y-m-d', $timestamp);
        }
    }
    
    private function get_recent_api_errors() {
        $usage_log = get_option('nexus_translator_usage_log', array());
        $recent_errors = array();
        
        $cutoff_time = strtotime('-24 hours');
        
        foreach ($usage_log as $entry) {
            if ($entry['timestamp'] >= $cutoff_time && isset($entry['error'])) {
                $recent_errors[] = array(
                    'timestamp' => $entry['timestamp'],
                    'error' => $entry['error'],
                    'action' => $entry['action']
                );
            }
        }
        
        return array_slice($recent_errors, -10); // Last 10 errors
    }
    
    private function get_api_usage_today() {
        $usage_log = get_option('nexus_translator_usage_log', array());
        $today_start = strtotime('today');
        $count = 0;
        
        foreach ($usage_log as $entry) {
            if ($entry['timestamp'] >= $today_start && 
                in_array($entry['action'], array('translate_post', 'update_translation'))) {
                $count++;
            }
        }
        
        return $count;
    }
    
    private function calculate_api_health_score() {
        $usage_log = get_option('nexus_translator_usage_log', array());
        $recent_cutoff = strtotime('-24 hours');
        
        $total_requests = 0;
        $successful_requests = 0;
        
        foreach ($usage_log as $entry) {
            if ($entry['timestamp'] >= $recent_cutoff && 
                in_array($entry['action'], array('translate_post', 'update_translation'))) {
                $total_requests++;
                if (isset($entry['success']) && $entry['success']) {
                    $successful_requests++;
                }
            }
        }
        
        if ($total_requests === 0) {
            return 100; // No requests = perfect health
        }
        
        return round(($successful_requests / $total_requests) * 100, 1);
    }
    
    private function get_performance_metrics() {
        return array(
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'memory_limit' => ini_get('memory_limit'),
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            'max_execution_time' => ini_get('max_execution_time'),
            'database_queries' => get_num_queries(),
            'active_plugins' => count(get_option('active_plugins', array()))
        );
    }
    
    private function check_required_tables() {
        global $wpdb;
        
        $required_tables = array(
            $wpdb->posts,
            $wpdb->postmeta,
            $wpdb->options
        );
        
        $existing_tables = array();
        foreach ($required_tables as $table) {
            $result = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
            $existing_tables[$table] = ($result === $table);
        }
        
        return $existing_tables;
    }
    
    private function convert_analytics_to_csv($analytics) {
        $csv_data = "Metric,Value\n";
        $csv_data .= "Total Translations,{$analytics['total_translations']}\n";
        $csv_data .= "Successful Translations,{$analytics['successful_translations']}\n";
        $csv_data .= "Failed Translations,{$analytics['failed_translations']}\n";
        $csv_data .= "Success Rate,{$analytics['success_rate']}%\n";
        $csv_data .= "Average Per Day,{$analytics['average_per_day']}\n";
        
        return $csv_data;
    }
    
    // Additional helper methods for detailed analytics would go here...
    private function get_user_breakdown($period) {
        // Implementation for user breakdown
        return array();
    }
    
    private function get_post_type_breakdown($period) {
        // Implementation for post type breakdown
        return array();
    }
    
    private function get_error_breakdown($period) {
        // Implementation for error breakdown
        return array();
    }
    
    private function get_timing_analysis($period) {
        // Implementation for timing analysis
        return array();
    }
    
    private function get_api_performance_metrics($period) {
        // Implementation for API performance metrics
        return array();
    }
    
    private function get_memory_usage_stats($period) {
        // Implementation for memory usage stats
        return array();
    }
    
    private function get_response_time_analysis($period) {
        // Implementation for response time analysis
        return array();
    }
    
    private function get_translation_trends($period, $group_by) {
        // Implementation for translation trends
        return array();
    }
    
    private function get_error_analysis($period) {
        // Implementation for error analysis
        return array();
    }
    
    private function get_user_activity($period, $user_id) {
        // Implementation for user activity
        return array();
    }
}