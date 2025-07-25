<?php
/**
 * File: class-ajax-analytics.php
 * Location: /includes/ajax/class-ajax-analytics.php
 * 
 * AJAX Analytics Handler - Analytics & Monitoring
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
        add_action('wp_ajax_nexus_get_analytics', array($this, 'handle_get_analytics'));
        add_action('wp_ajax_nexus_cleanup_locks', array($this, 'handle_cleanup_locks'));
        add_action('wp_ajax_nexus_emergency_cleanup', array($this, 'handle_emergency_cleanup'));
        add_action('wp_ajax_nexus_get_usage_stats', array($this, 'handle_get_usage_stats'));
    }
    
    /**
     * Get analytics data
     */
    public function handle_get_analytics() {
        $this->validate_ajax_request('manage_options');
        
        $days = isset($_POST['days']) ? (int) $_POST['days'] : 7;
        $days = max(1, min(90, $days)); // Limit between 1 and 90 days
        
        $analytics = $this->get_translation_analytics($days);
        
        $this->send_success($analytics);
    }
    
    /**
     * Cleanup old translation locks
     */
    public function handle_cleanup_locks() {
        $this->validate_ajax_request('manage_options');
        
        $cleaned = $this->cleanup_old_translation_locks();
        
        $this->log_usage('cleanup_locks', array(
            'success' => true,
            'cleaned_count' => $cleaned
        ));
        
        $this->send_success(array(
            'cleaned_count' => $cleaned
        ), sprintf('Cleaned up %d old translation locks', $cleaned));
    }
    
    /**
     * Emergency cleanup
     */
    public function handle_emergency_cleanup() {
        $this->validate_ajax_request('manage_options');
        
        global $wpdb;
        
        $cleaned = array();
        
        // Clear all locks
        $locks_deleted = $wpdb->query(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_nexus_translation_lock'"
        );
        $cleaned[] = sprintf('%d translation locks removed', $locks_deleted);
        
        // Reset processing translations to error
        $processing_reset = $wpdb->query(
            "UPDATE {$wpdb->postmeta} 
             SET meta_value = 'error' 
             WHERE meta_key = '_nexus_translation_status' 
             AND meta_value = 'processing'"
        );
        $cleaned[] = sprintf('%d stuck translations reset', $processing_reset);
        
        // Clear rate limits
        delete_transient('nexus_translator_rate_limit_hour');
        delete_transient('nexus_translator_rate_limit_day');
        delete_transient('nexus_translator_last_request');
        $cleaned[] = 'Rate limits reset';
        
        // Clear emergency stop
        delete_option('nexus_translator_emergency_stop');
        delete_option('nexus_translator_emergency_reason');
        delete_option('nexus_translator_emergency_time');
        $cleaned[] = 'Emergency stop cleared';
        
        // Clear active requests
        self::force_cleanup_requests();
        $cleaned[] = 'Active requests cleared';
        
        // Clear any cached errors
        wp_cache_flush();
        
        $this->log_usage('emergency_cleanup', array(
            'success' => true,
            'actions_count' => count($cleaned)
        ));
        
        $this->send_success(array(
            'actions' => $cleaned
        ), 'Emergency cleanup completed');
    }
    
    /**
     * Get usage statistics
     */
    public function handle_get_usage_stats() {
        $this->validate_ajax_request('edit_posts');
        
        if (!class_exists('Translator_API')) {
            $this->send_error('API class not available', 'API_CLASS_MISSING');
        }
        
        $api = new Translator_API();
        $usage_stats = $api->get_usage_stats();
        
        $this->send_success($usage_stats);
    }
    
    /**
     * Get translation analytics data
     */
    private function get_translation_analytics($days = 7) {
        $daily_stats = get_option('nexus_translator_daily_stats', array());
        $usage_log = get_option('nexus_translator_usage_log', array());
        
        // Calculate date range
        $end_date = current_time('Y-m-d');
        $start_date = date('Y-m-d', strtotime("-$days days"));
        
        $analytics = array(
            'date_range' => array(
                'start' => $start_date,
                'end' => $end_date,
                'days' => $days
            ),
            'totals' => array(
                'requests' => 0,
                'successful' => 0,
                'failed' => 0,
                'tokens' => 0
            ),
            'daily_breakdown' => array(),
            'language_breakdown' => array(),
            'user_breakdown' => array(),
            'recent_activity' => array()
        );
        
        // Process daily stats
        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("$start_date +$i days"));
            $stats = $daily_stats[$date] ?? array(
                'total_requests' => 0,
                'successful_translations' => 0,
                'failed_translations' => 0,
                'total_tokens' => 0
            );
            
            $analytics['daily_breakdown'][$date] = $stats;
            $analytics['totals']['requests'] += $stats['total_requests'];
            $analytics['totals']['successful'] += $stats['successful_translations'];
            $analytics['totals']['failed'] += $stats['failed_translations'];
            $analytics['totals']['tokens'] += $stats['total_tokens'];
        }
        
        // Process usage log for detailed breakdowns
        $cutoff_timestamp = strtotime($start_date);
        foreach ($usage_log as $entry) {
            if ($entry['timestamp'] >= $cutoff_timestamp) {
                // Language breakdown
                if (isset($entry['target_language'])) {
                    $lang = $entry['target_language'];
                    if (!isset($analytics['language_breakdown'][$lang])) {
                        $analytics['language_breakdown'][$lang] = array(
                            'total' => 0,
                            'successful' => 0,
                            'failed' => 0
                        );
                    }
                    $analytics['language_breakdown'][$lang]['total']++;
                    if ($entry['success']) {
                        $analytics['language_breakdown'][$lang]['successful']++;
                    } else {
                        $analytics['language_breakdown'][$lang]['failed']++;
                    }
                }
                
                // User breakdown
                $user = $entry['user_login'] ?? 'Unknown';
                if (!isset($analytics['user_breakdown'][$user])) {
                    $analytics['user_breakdown'][$user] = array(
                        'total' => 0,
                        'successful' => 0,
                        'failed' => 0
                    );
                }
                $analytics['user_breakdown'][$user]['total']++;
                if ($entry['success']) {
                    $analytics['user_breakdown'][$user]['successful']++;
                } else {
                    $analytics['user_breakdown'][$user]['failed']++;
                }
            }
        }
        
        // Recent activity (last 10 entries)
        $analytics['recent_activity'] = array_slice(array_reverse($usage_log), 0, 10);
        
        return $analytics;
    }
    
    /**
     * Cleanup old translation locks
     */
    private function cleanup_old_translation_locks() {
        $active_translations = get_option('nexus_translator_active_translations', array());
        $current_time = current_time('timestamp');
        $lock_timeout = 600; // 10 minutes timeout
        $cleaned = 0;
        
        foreach ($active_translations as $lock_key => $lock_time) {
            if (($current_time - $lock_time) > $lock_timeout) {
                unset($active_translations[$lock_key]);
                $cleaned++;
            }
        }
        
        if ($cleaned > 0) {
            update_option('nexus_translator_active_translations', $active_translations);
        }
        
        // Also cleanup expired requests from base class
        $base_cleaned = self::cleanup_expired_requests();
        
        return $cleaned + $base_cleaned;
    }
}