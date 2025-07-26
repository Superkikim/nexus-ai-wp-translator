<?php
/**
 * File: class-language-analytics.php
 * Location: /includes/class-language-analytics.php
 * 
 * Language Analytics Class
 * Responsible for: Usage tracking, metrics collection, performance analytics
 * 
 * @package Nexus\Translator
 */

namespace Nexus\Translator;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Language analytics class
 * 
 * Handles tracking of language usage, translation metrics, performance data,
 * and provides analytics insights for optimization.
 * 
 */
class Language_Analytics {
    
    /**
     * Languages instance reference
     * 
     * @var Languages
     */
    private $languages;
    
    /**
     * Analytics data cache
     * 
     * @var array
     */
    private $analytics_cache = array();
    
    /**
     * Constructor
     * 
     * @param Languages $languages Languages instance
     */
    public function __construct($languages) {
        $this->languages = $languages;
    }
    
    /**
     * Register WordPress hooks
     * 
     * @return void
     */
    public function register_hooks() {
        // Analytics event hooks
        add_action('nexus_analytics_event', array($this, 'handle_analytics_event'), 10, 2);
        add_action('nexus_translation_completed', array($this, 'track_translation_completed'), 10, 3);
        add_action('nexus_translation_failed', array($this, 'track_translation_failed'), 10, 3);
        
        // Performance tracking
        add_action('nexus_ai_translator_wp_loaded', array($this, 'track_plugin_load_time'));
        
        // Settings tracking
        add_action('update_option_nexus_ai_translator_settings', array($this, 'track_settings_update'), 10, 3);
        
        // Clean up old data daily
        add_action('nexus_daily_cleanup', array($this, 'cleanup_old_analytics_data'));
    }
    
    /**
     * Track language pair usage
     * 
     * @param string $source_code Source language code
     * @param string $target_code Target language code
     * @param array $context Additional context
     * @return void
     */
    public function track_language_usage($source_code, $target_code, $context = array()) {
        $usage_data = array(
            'source' => $source_code,
            'target' => $target_code,
            'pair_key' => $source_code . '_' . $target_code,
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql'),
            'context' => $context,
        );
        
        // Store in database
        $this->store_analytics_data('language_usage', $usage_data);
        
        // Update usage counters
        $this->increment_usage_counter($source_code, $target_code);
        
        // Fire analytics event
        do_action('nexus_analytics_event', 'language_pair_used', $usage_data);
    }
    
    /**
     * Track translation completion
     * 
     * @param string $source_code Source language
     * @param string $target_code Target language
     * @param array $metrics Translation metrics
     * @return void
     */
    public function track_translation_completed($source_code, $target_code, $metrics = array()) {
        $pair_info = $this->languages->get_pair_info($source_code, $target_code);
        
        $completion_data = array(
            'source' => $source_code,
            'target' => $target_code,
            'pair_key' => $source_code . '_' . $target_code,
            'complexity' => $pair_info['complexity'] ?? 'medium',
            'accuracy' => $pair_info['accuracy'] ?? 'good',
            'execution_time' => $metrics['execution_time'] ?? 0,
            'word_count' => $metrics['word_count'] ?? 0,
            'character_count' => $metrics['character_count'] ?? 0,
            'api_calls' => $metrics['api_calls'] ?? 1,
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql'),
        );
        
        // Store completion data
        $this->store_analytics_data('translation_completed', $completion_data);
        
        // Update success counters
        $this->increment_success_counter($source_code, $target_code);
        
        // Track usage
        $this->track_language_usage($source_code, $target_code, array('type' => 'translation_success'));
    }
    
    /**
     * Track translation failure
     * 
     * @param string $source_code Source language
     * @param string $target_code Target language
     * @param array $error_data Error information
     * @return void
     */
    public function track_translation_failed($source_code, $target_code, $error_data = array()) {
        $failure_data = array(
            'source' => $source_code,
            'target' => $target_code,
            'pair_key' => $source_code . '_' . $target_code,
            'error_type' => $error_data['type'] ?? 'unknown',
            'error_message' => $error_data['message'] ?? '',
            'error_code' => $error_data['code'] ?? 'general',
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql'),
        );
        
        // Store failure data
        $this->store_analytics_data('translation_failed', $failure_data);
        
        // Update failure counters
        $this->increment_failure_counter($source_code, $target_code);
        
        // Track usage
        $this->track_language_usage($source_code, $target_code, array('type' => 'translation_failure'));
    }
    
    /**
     * Track AJAX request performance
     * 
     * @param string $endpoint Endpoint name
     * @param array $request_data Request data
     * @param bool $success Whether request was successful
     * @return void
     */
    public function track_ajax_request($endpoint, $request_data, $success) {
        $ajax_data = array(
            'endpoint' => $endpoint,
            'success' => $success,
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql'),
            'request_size' => strlen(json_encode($request_data)),
        );
        
        // Store AJAX analytics
        $this->store_analytics_data('ajax_requests', $ajax_data);
    }
    
    /**
     * Get language usage statistics
     * 
     * @param string $period Time period ('day', 'week', 'month', 'year')
     * @return array Usage statistics
     */
    public function get_usage_stats($period = 'month') {
        $cache_key = 'usage_stats_' . $period;
        
        if (isset($this->analytics_cache[$cache_key])) {
            return $this->analytics_cache[$cache_key];
        }
        
        $date_condition = $this->get_date_condition($period);
        $usage_counters = get_option('nexus_language_usage_counters', array());
        
        $stats = array(
            'total_translations' => $this->get_total_translations($date_condition),
            'most_used_pairs' => $this->get_most_used_pairs($date_condition),
            'success_rate' => $this->get_success_rate($date_condition),
            'average_execution_time' => $this->get_average_execution_time($date_condition),
            'language_distribution' => $this->get_language_distribution($date_condition),
            'complexity_breakdown' => $this->get_complexity_breakdown($date_condition),
            'period' => $period,
            'generated_at' => current_time('mysql'),
        );
        
        // Cache for 1 hour
        $this->analytics_cache[$cache_key] = $stats;
        set_transient('nexus_language_analytics_' . $cache_key, $stats, HOUR_IN_SECONDS);
        
        return $stats;
    }
    
    /**
     * Get complexity details for a language pair
     * 
     * @param string $source_code Source language
     * @param string $target_code Target language
     * @return array Complexity details
     */
    public function get_complexity_details($source_code, $target_code) {
        $source_lang = $this->languages->get_language($source_code);
        $target_lang = $this->languages->get_language($target_code);
        
        if (!$source_lang || !$target_lang) {
            return array();
        }
        
        $complexity_factors = array();
        
        // Check script differences
        if ($source_lang['script'] !== $target_lang['script']) {
            $complexity_factors[] = array(
                'type' => 'script_difference',
                'description' => sprintf(
                    /* translators: 1: Source script, 2: Target script */
                    __('Different writing systems: %1$s → %2$s', 'nexus-ai-wp-translator'),
                    $source_lang['script'],
                    $target_lang['script']
                ),
                'impact' => 'medium',
            );
        }
        
        // Check language family differences
        if ($source_lang['family'] !== $target_lang['family']) {
            $complexity_factors[] = array(
                'type' => 'family_difference',
                'description' => sprintf(
                    /* translators: 1: Source family, 2: Target family */
                    __('Different language families: %1$s → %2$s', 'nexus-ai-wp-translator'),
                    $source_lang['family'],
                    $target_lang['family']
                ),
                'impact' => 'low',
            );
        }
        
        // Check RTL complexity
        if ($source_lang['direction'] === 'rtl' || $target_lang['direction'] === 'rtl') {
            $complexity_factors[] = array(
                'type' => 'rtl_complexity',
                'description' => __('Right-to-left text direction adds formatting complexity', 'nexus-ai-wp-translator'),
                'impact' => 'high',
            );
        }
        
        // Historical performance data
        $historical_data = $this->get_pair_performance_history($source_code, $target_code);
        
        return array(
            'factors' => $complexity_factors,
            'historical_performance' => $historical_data,
            'estimated_difficulty' => $this->calculate_difficulty_score($complexity_factors),
        );
    }
    
    /**
     * Handle analytics events
     * 
     * @param string $event_name Event name
     * @param array $event_data Event data
     * @return void
     */
    public function handle_analytics_event($event_name, $event_data) {
        $analytics_event = array(
            'event' => $event_name,
            'data' => $event_data,
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql'),
        );
        
        // Store event
        $this->store_analytics_data('events', $analytics_event);
        
        // Handle specific events
        switch ($event_name) {
            case 'language_settings_reset':
                $this->handle_settings_reset_event($event_data);
                break;
                
            case 'ajax_performance':
                $this->handle_ajax_performance_event($event_data);
                break;
                
            case 'language_settings_auto_corrected':
                $this->handle_settings_correction_event($event_data);
                break;
        }
    }
    
    /**
     * Track plugin load time
     * 
     * @return void
     */
    public function track_plugin_load_time() {
        $load_time = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
        
        $performance_data = array(
            'type' => 'plugin_load',
            'load_time' => $load_time,
            'memory_usage' => memory_get_usage(true),
            'timestamp' => current_time('mysql'),
        );
        
        $this->store_analytics_data('performance', $performance_data);
    }
    
    /**
     * Track settings updates
     * 
     * @param mixed $old_value Old value
     * @param mixed $new_value New value
     * @param string $option Option name
     * @return void
     */
    public function track_settings_update($old_value, $new_value, $option) {
        $changes = array();
        
        // Compare old and new values
        foreach ($new_value as $key => $value) {
            if (!isset($old_value[$key]) || $old_value[$key] !== $value) {
                $changes[$key] = array(
                    'old' => $old_value[$key] ?? null,
                    'new' => $value,
                );
            }
        }
        
        if (!empty($changes)) {
            $settings_data = array(
                'option' => $option,
                'changes' => $changes,
                'user_id' => get_current_user_id(),
                'timestamp' => current_time('mysql'),
            );
            
            $this->store_analytics_data('settings_updates', $settings_data);
        }
    }
    
    /**
     * Clean up old analytics data
     * 
     * @param int $days_to_keep Number of days to keep data
     * @return void
     */
    public function cleanup_old_analytics_data($days_to_keep = 90) {
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-' . $days_to_keep . ' days'));
        
        // Clean up stored analytics data
        $analytics_data = get_option('nexus_language_analytics_data', array());
        
        foreach ($analytics_data as $type => $entries) {
            $analytics_data[$type] = array_filter($entries, function($entry) use ($cutoff_date) {
                return isset($entry['timestamp']) && $entry['timestamp'] > $cutoff_date;
            });
        }
        
        update_option('nexus_language_analytics_data', $analytics_data);
        
        // Clear analytics cache
        $this->analytics_cache = array();
        
        // Fire cleanup event
        do_action('nexus_analytics_event', 'analytics_cleanup', array(
            'cutoff_date' => $cutoff_date,
            'days_kept' => $days_to_keep,
        ));
    }
    
    /**
     * Store analytics data
     * 
     * @param string $type Data type
     * @param array $data Data to store
     * @return void
     */
    private function store_analytics_data($type, $data) {
        $analytics_data = get_option('nexus_language_analytics_data', array());
        
        if (!isset($analytics_data[$type])) {
            $analytics_data[$type] = array();
        }
        
        $analytics_data[$type][] = $data;
        
        // Limit stored entries to prevent database bloat
        if (count($analytics_data[$type]) > 1000) {
            $analytics_data[$type] = array_slice($analytics_data[$type], -1000);
        }
        
        update_option('nexus_language_analytics_data', $analytics_data);
    }
    
    /**
     * Increment usage counter
     * 
     * @param string $source_code Source language
     * @param string $target_code Target language
     * @return void
     */
    private function increment_usage_counter($source_code, $target_code) {
        $counters = get_option('nexus_language_usage_counters', array());
        $pair_key = $source_code . '_' . $target_code;
        
        if (!isset($counters[$pair_key])) {
            $counters[$pair_key] = array('usage' => 0, 'success' => 0, 'failure' => 0);
        }
        
        $counters[$pair_key]['usage']++;
        
        update_option('nexus_language_usage_counters', $counters);
    }
    
    /**
     * Increment success counter
     * 
     * @param string $source_code Source language
     * @param string $target_code Target language
     * @return void
     */
    private function increment_success_counter($source_code, $target_code) {
        $counters = get_option('nexus_language_usage_counters', array());
        $pair_key = $source_code . '_' . $target_code;
        
        if (!isset($counters[$pair_key])) {
            $counters[$pair_key] = array('usage' => 0, 'success' => 0, 'failure' => 0);
        }
        
        $counters[$pair_key]['success']++;
        
        update_option('nexus_language_usage_counters', $counters);
    }
    
    /**
     * Increment failure counter
     * 
     * @param string $source_code Source language
     * @param string $target_code Target language
     * @return void
     */
    private function increment_failure_counter($source_code, $target_code) {
        $counters = get_option('nexus_language_usage_counters', array());
        $pair_key = $source_code . '_' . $target_code;
        
        if (!isset($counters[$pair_key])) {
            $counters[$pair_key] = array('usage' => 0, 'success' => 0, 'failure' => 0);
        }
        
        $counters[$pair_key]['failure']++;
        
        update_option('nexus_language_usage_counters', $counters);
    }
    
    /**
     * Get date condition for queries
     * 
     * @param string $period Time period
     * @return string Date condition
     */
    private function get_date_condition($period) {
        switch ($period) {
            case 'day':
                return date('Y-m-d', strtotime('-1 day'));
            case 'week':
                return date('Y-m-d', strtotime('-1 week'));
            case 'month':
                return date('Y-m-d', strtotime('-1 month'));
            case 'year':
                return date('Y-m-d', strtotime('-1 year'));
            default:
                return date('Y-m-d', strtotime('-1 month'));
        }
    }
    
    /**
     * Get total translations for period
     * 
     * @param string $date_condition Date condition
     * @return int Total translations
     */
    private function get_total_translations($date_condition) {
        $analytics_data = get_option('nexus_language_analytics_data', array());
        $completed = $analytics_data['translation_completed'] ?? array();
        
        return count(array_filter($completed, function($entry) use ($date_condition) {
            return $entry['timestamp'] >= $date_condition;
        }));
    }
    
    /**
     * Get most used language pairs
     * 
     * @param string $date_condition Date condition
     * @param int $limit Number of pairs to return
     * @return array Most used pairs
     */
    private function get_most_used_pairs($date_condition, $limit = 5) {
        $analytics_data = get_option('nexus_language_analytics_data', array());
        $usage_data = $analytics_data['language_usage'] ?? array();
        
        $pair_counts = array();
        
        foreach ($usage_data as $entry) {
            if ($entry['timestamp'] >= $date_condition) {
                $pair_key = $entry['pair_key'];
                $pair_counts[$pair_key] = ($pair_counts[$pair_key] ?? 0) + 1;
            }
        }
        
        arsort($pair_counts);
        
        return array_slice($pair_counts, 0, $limit, true);
    }
    
    /**
     * Get success rate for period
     * 
     * @param string $date_condition Date condition
     * @return float Success rate percentage
     */
    private function get_success_rate($date_condition) {
        $analytics_data = get_option('nexus_language_analytics_data', array());
        $completed = $analytics_data['translation_completed'] ?? array();
        $failed = $analytics_data['translation_failed'] ?? array();
        
        $completed_count = count(array_filter($completed, function($entry) use ($date_condition) {
            return $entry['timestamp'] >= $date_condition;
        }));
        
        $failed_count = count(array_filter($failed, function($entry) use ($date_condition) {
            return $entry['timestamp'] >= $date_condition;
        }));
        
        $total = $completed_count + $failed_count;
        
        return $total > 0 ? round(($completed_count / $total) * 100, 2) : 0;
    }
    
    /**
     * Get average execution time
     * 
     * @param string $date_condition Date condition
     * @return float Average execution time in seconds
     */
    private function get_average_execution_time($date_condition) {
        $analytics_data = get_option('nexus_language_analytics_data', array());
        $completed = $analytics_data['translation_completed'] ?? array();
        
        $relevant_entries = array_filter($completed, function($entry) use ($date_condition) {
            return $entry['timestamp'] >= $date_condition && isset($entry['execution_time']);
        });
        
        if (empty($relevant_entries)) {
            return 0;
        }
        
        $total_time = array_sum(array_column($relevant_entries, 'execution_time'));
        
        return round($total_time / count($relevant_entries), 2);
    }
    
    /**
     * Get language distribution
     * 
     * @param string $date_condition Date condition
     * @return array Language usage distribution
     */
    private function get_language_distribution($date_condition) {
        $analytics_data = get_option('nexus_language_analytics_data', array());
        $usage_data = $analytics_data['language_usage'] ?? array();
        
        $source_counts = array();
        $target_counts = array();
        
        foreach ($usage_data as $entry) {
            if ($entry['timestamp'] >= $date_condition) {
                $source_counts[$entry['source']] = ($source_counts[$entry['source']] ?? 0) + 1;
                $target_counts[$entry['target']] = ($target_counts[$entry['target']] ?? 0) + 1;
            }
        }
        
        return array(
            'source_languages' => $source_counts,
            'target_languages' => $target_counts,
        );
    }
    
    /**
     * Get complexity breakdown
     * 
     * @param string $date_condition Date condition
     * @return array Complexity distribution
     */
    private function get_complexity_breakdown($date_condition) {
        $analytics_data = get_option('nexus_language_analytics_data', array());
        $completed = $analytics_data['translation_completed'] ?? array();
        
        $complexity_counts = array(
            'low' => 0,
            'medium' => 0,
            'high' => 0,
        );
        
        foreach ($completed as $entry) {
            if ($entry['timestamp'] >= $date_condition && isset($entry['complexity'])) {
                $complexity = $entry['complexity'];
                if (isset($complexity_counts[$complexity])) {
                    $complexity_counts[$complexity]++;
                }
            }
        }
        
        return $complexity_counts;
    }
    
    /**
     * Get pair performance history
     * 
     * @param string $source_code Source language
     * @param string $target_code Target language
     * @return array Performance history
     */
    private function get_pair_performance_history($source_code, $target_code) {
        $counters = get_option('nexus_language_usage_counters', array());
        $pair_key = $source_code . '_' . $target_code;
        
        if (!isset($counters[$pair_key])) {
            return array(
                'total_usage' => 0,
                'success_count' => 0,
                'failure_count' => 0,
                'success_rate' => 0,
            );
        }
        
        $data = $counters[$pair_key];
        $success_rate = $data['usage'] > 0 ? round(($data['success'] / $data['usage']) * 100, 2) : 0;
        
        return array(
            'total_usage' => $data['usage'],
            'success_count' => $data['success'],
            'failure_count' => $data['failure'],
            'success_rate' => $success_rate,
        );
    }
    
    /**
     * Calculate difficulty score
     * 
     * @param array $complexity_factors Complexity factors
     * @return string Difficulty level
     */
    private function calculate_difficulty_score($complexity_factors) {
        $score = 0;
        
        foreach ($complexity_factors as $factor) {
            switch ($factor['impact']) {
                case 'high':
                    $score += 3;
                    break;
                case 'medium':
                    $score += 2;
                    break;
                case 'low':
                    $score += 1;
                    break;
            }
        }
        
        if ($score >= 5) {
            return 'very_high';
        } elseif ($score >= 3) {
            return 'high';
        } elseif ($score >= 1) {
            return 'medium';
        } else {
            return 'low';
        }
    }
    
    /**
     * Handle settings reset event
     * 
     * @param array $event_data Event data
     * @return void
     */
    private function handle_settings_reset_event($event_data) {
        $reset_data = array(
            'reason' => $event_data['reason'] ?? 'unknown',
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql'),
        );
        
        $this->store_analytics_data('settings_resets', $reset_data);
    }
    
    /**
     * Handle AJAX performance event
     * 
     * @param array $event_data Event data
     * @return void
     */
    private function handle_ajax_performance_event($event_data) {
        $performance_data = array(
            'endpoint' => $event_data['endpoint'] ?? 'unknown',
            'execution_time' => $event_data['execution_time'] ?? 0,
            'timestamp' => current_time('mysql'),
        );
        
        $this->store_analytics_data('ajax_performance', $performance_data);
    }
    
    /**
     * Handle settings correction event
     * 
     * @param array $event_data Event data
     * @return void
     */
    private function handle_settings_correction_event($event_data) {
        $correction_data = array(
            'changes' => $event_data['changes'] ?? array(),
            'errors' => $event_data['errors'] ?? array(),
            'warnings' => $event_data['warnings'] ?? array(),
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql'),
        );
        
        $this->store_analytics_data('settings_corrections', $correction_data);
    }
}