<?php
/**
 * File: class-api-performance.php
 * Location: /includes/class-api-performance.php
 * 
 * API Performance Class
 * Responsible for: Performance tracking, metrics collection, cost estimation, optimization
 * 
 * @package Nexus\Translator
 * @since 0.0.1
 */

namespace Nexus\Translator;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * API performance tracking class
 * 
 * Handles performance metrics, cost estimation, optimization recommendations,
 * and integration with analytics systems.
 * 
 * @since 0.0.1
 */
class Api_Performance {
    
    /**
     * Core API instance
     * 
     * @since 0.0.1
     * @var Api
     */
    private $api;
    
    /**
     * Languages instance
     * 
     * @since 0.0.1
     * @var Languages
     */
    private $languages;
    
    /**
     * Performance metrics cache
     * 
     * @since 0.0.1
     * @var array
     */
    private $metrics_cache = array();
    
    /**
     * API status data
     * 
     * @since 0.0.1
     * @var array
     */
    private $api_status = array();
    
    /**
     * Constructor
     * 
     * @since 0.0.1
     * @param Api $api Core API instance
     */
    public function __construct($api) {
        $this->api = $api;
        
        // Get Languages instance
        $main = \Nexus\Translator\Main::get_instance();
        $this->languages = $main->get_component('languages');
        
        // Initialize status tracking
        $this->init_api_status();
    }
    
    /**
     * Register WordPress hooks
     * 
     * @since 0.0.1
     * @return void
     */
    public function register_hooks() {
        // Performance tracking hooks
        add_action('nexus_after_api_translate', array($this, 'track_translation_performance'), 10, 4);
        add_action('nexus_analytics_event', array($this, 'handle_analytics_event'), 10, 2);
        
        // Daily health check
        add_action('nexus_daily_cleanup', array($this, 'daily_health_check'));
        
        // Status updates
        add_action('nexus_after_api_translate', array($this, 'update_api_status'));
    }
    
    /**
     * Initialize API status tracking
     * 
     * @since 0.0.1
     * @return void
     */
    private function init_api_status() {
        $this->api_status = get_transient('nexus_ai_translator_api_status') ?: array(
            'connected' => false,
            'last_check' => '',
            'response_time' => 0,
            'api_key_valid' => false,
            'total_requests' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
        );
    }
    
    /**
     * Track translation performance
     * 
     * @since 0.0.1
     * @param array $result Translation result
     * @param string $text Original text
     * @param string $source_lang Source language
     * @param string $target_lang Target language
     * @return void
     */
    public function track_translation_performance($result, $text, $source_lang, $target_lang) {
        if (!isset($result['data']['metadata']['api_time'])) {
            return;
        }
        
        $execution_time = $result['data']['metadata']['api_time'];
        $success = $result['success'];
        
        // Track in local metrics
        $pair_key = $source_lang . '_' . $target_lang;
        
        if (!isset($this->metrics_cache[$pair_key])) {
            $this->metrics_cache[$pair_key] = array(
                'total_requests' => 0,
                'successful_requests' => 0,
                'total_time' => 0,
                'average_time' => 0,
                'character_count' => 0,
                'word_count' => 0,
            );
        }
        
        $metrics = &$this->metrics_cache[$pair_key];
        $metrics['total_requests']++;
        $metrics['total_time'] += $execution_time;
        $metrics['average_time'] = $metrics['total_time'] / $metrics['total_requests'];
        $metrics['character_count'] += strlen($text);
        $metrics['word_count'] += str_word_count($text);
        
        if ($success) {
            $metrics['successful_requests']++;
        }
        
        // Update API status
        $this->api_status['total_requests']++;
        if ($success) {
            $this->api_status['successful_requests']++;
        } else {
            $this->api_status['failed_requests']++;
        }
        
        // Integrate with Language_Analytics if available
        if ($this->languages && method_exists($this->languages, 'get_analytics')) {
            $analytics = $this->languages->get_analytics();
            if ($analytics) {
                if ($success) {
                    $analytics->track_translation_completed($source_lang, $target_lang, array(
                        'execution_time' => $execution_time,
                        'character_count' => strlen($text),
                        'word_count' => str_word_count($text),
                        'api_calls' => 1,
                    ));
                } else {
                    $analytics->track_translation_failed($source_lang, $target_lang, array(
                        'type' => 'api_error',
                        'message' => $result['message'] ?? 'API request failed',
                    ));
                }
            }
        }
    }
    
    /**
     * Handle analytics events
     * 
     * @since 0.0.1
     * @param string $event_name Event name
     * @param array $event_data Event data
     * @return void
     */
    public function handle_analytics_event($event_name, $event_data) {
        switch ($event_name) {
            case 'api_authentication_success':
                $this->api_status['connected'] = true;
                $this->api_status['api_key_valid'] = true;
                $this->api_status['last_check'] = current_time('mysql');
                $this->api_status['response_time'] = $event_data['response_time'] ?? 0;
                break;
                
            case 'api_authentication_failed':
                $this->api_status['connected'] = false;
                $this->api_status['api_key_valid'] = false;
                $this->api_status['last_error'] = $event_data['error'] ?? 'Authentication failed';
                break;
                
            case 'emergency_mode_triggered':
                $this->api_status['emergency_mode'] = true;
                $this->api_status['emergency_triggered_at'] = current_time('mysql');
                break;
        }
        
        // Update stored API status
        set_transient('nexus_ai_translator_api_status', $this->api_status, HOUR_IN_SECONDS);
    }
    
    /**
     * Get performance metrics
     * 
     * @since 0.0.1
     * @param string $language_pair Optional language pair filter
     * @return array Performance metrics
     */
    public function get_performance_metrics($language_pair = '') {
        if (!empty($language_pair)) {
            return isset($this->metrics_cache[$language_pair]) ? $this->metrics_cache[$language_pair] : array();
        }
        
        return $this->metrics_cache;
    }
    
    /**
     * Get API status
     * 
     * @since 0.0.1
     * @return array API status information
     */
    public function get_api_status() {
        return $this->api_status;
    }
    
    /**
     * Update API status
     * 
     * @since 0.0.1
     * @return void
     */
    public function update_api_status() {
        set_transient('nexus_ai_translator_api_status', $this->api_status, HOUR_IN_SECONDS);
    }
    
    /**
     * Get usage statistics
     * 
     * @since 0.0.1
     * @param string $period Time period ('hour', 'day', 'week', 'month')
     * @return array Usage statistics
     */
    public function get_usage_statistics($period = 'day') {
        $stats = array(
            'period' => $period,
            'total_requests' => $this->api_status['total_requests'],
            'successful_requests' => $this->api_status['successful_requests'],
            'failed_requests' => $this->api_status['failed_requests'],
            'success_rate' => 0,
            'average_response_time' => 0,
            'performance_metrics' => $this->metrics_cache,
        );
        
        // Calculate success rate
        if ($stats['total_requests'] > 0) {
            $stats['success_rate'] = round(($stats['successful_requests'] / $stats['total_requests']) * 100, 2);
        }
        
        // Calculate average response time
        $total_time = 0;
        $total_requests = 0;
        
        foreach ($this->metrics_cache as $pair_metrics) {
            $total_time += $pair_metrics['total_time'];
            $total_requests += $pair_metrics['total_requests'];
        }
        
        if ($total_requests > 0) {
            $stats['average_response_time'] = round($total_time / $total_requests, 2);
        }
        
        return apply_filters('nexus_api_usage_statistics', $stats, $period);
    }
    
    /**
     * Estimate translation cost
     * 
     * @since 0.0.1
     * @param string $text Text to translate
     * @param string $model Model to use
     * @return array Cost estimation
     */
    public function estimate_cost($text, $model = 'claude-3-haiku-20240307') {
        // Rough token estimation (1 token ≈ 4 characters for English)
        $input_tokens = ceil(strlen($text) / 4);
        $estimated_output_tokens = ceil($input_tokens * 1.2); // Assume 20% expansion
        
        // Claude pricing (approximate - should be configurable)
        $pricing = array(
            'claude-3-haiku-20240307' => array('input' => 0.00025, 'output' => 0.00125),
            'claude-3-sonnet-20240229' => array('input' => 0.003, 'output' => 0.015),
            'claude-3-opus-20240229' => array('input' => 0.015, 'output' => 0.075),
        );
        
        $model_pricing = $pricing[$model] ?? $pricing['claude-3-haiku-20240307'];
        
        $input_cost = ($input_tokens / 1000) * $model_pricing['input'];
        $output_cost = ($estimated_output_tokens / 1000) * $model_pricing['output'];
        $total_cost = $input_cost + $output_cost;
        
        return array(
            'input_tokens' => $input_tokens,
            'estimated_output_tokens' => $estimated_output_tokens,
            'input_cost' => $input_cost,
            'output_cost' => $output_cost,
            'total_cost' => $total_cost,
            'currency' => 'USD',
            'model' => $model,
        );
    }
    
    /**
     * Get recommended model for language pair
     * 
     * @since 0.0.1
     * @param string $source_lang Source language
     * @param string $target_lang Target language
     * @return string Recommended model
     */
    public function get_recommended_model($source_lang, $target_lang) {
        $default_model = 'claude-3-haiku-20240307';
        
        // Get language complexity from Module 4
        if ($this->languages) {
            $pair_info = $this->languages->get_pair_info($source_lang, $target_lang);
            
            if ($pair_info) {
                switch ($pair_info['complexity']) {
                    case 'high':
                        return 'claude-3-opus-20240229'; // Most capable for complex translations
                    case 'medium':
                        return 'claude-3-sonnet-20240229'; // Balanced performance
                    case 'low':
                    default:
                        return 'claude-3-haiku-20240307'; // Fast and efficient
                }
            }
        }
        
        return apply_filters('nexus_recommended_model', $default_model, $source_lang, $target_lang);
    }
    
    /**
     * Test available models
     * 
     * @since 0.0.1
     * @return array Model test results
     */
    public function test_models() {
        $models = array(
            'claude-3-haiku-20240307',
            'claude-3-sonnet-20240229',
            'claude-3-opus-20240229',
        );
        
        $test_results = array();
        $test_text = 'Hello, world!';
        
        foreach ($models as $model) {
            $start_time = microtime(true);
            
            $test_data = array(
                'model' => $model,
                'max_tokens' => 10,
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => 'Translate "' . $test_text . '" to French.'
                    )
                )
            );
            
            $response = $this->api->make_basic_request($test_data);
            $execution_time = microtime(true) - $start_time;
            
            $test_results[$model] = array(
                'success' => $response['success'],
                'response_time' => $execution_time,
                'error' => $response['success'] ? null : $response['message'],
                'available' => $response['success'],
            );
        }
        
        return $test_results;
    }
    
    /**
     * Daily health check
     * 
     * @since 0.0.1
     * @return void
     */
    public function daily_health_check() {
        // Test connection if API key is available
        $api_key = $this->api->get_api_key();
        
        if (!empty($api_key)) {
            $connection_test = $this->api->test_connection();
            
            // Update API status
            $this->api_status['last_health_check'] = current_time('mysql');
            $this->api_status['health_check_result'] = $connection_test['success'];
            
            if ($connection_test['success']) {
                $this->api_status['connected'] = true;
                $this->api_status['response_time'] = $connection_test['data']['response_time'] ?? 0;
            } else {
                $this->api_status['connected'] = false;
                $this->api_status['last_error'] = $connection_test['message'];
            }
            
            // Fire analytics event
            do_action('nexus_analytics_event', 'daily_health_check', array(
                'connected' => $connection_test['success'],
                'response_time' => $connection_test['data']['response_time'] ?? 0,
                'timestamp' => current_time('mysql'),
            ));
        }
        
        // Save updated status
        $this->update_api_status();
        
        // Clean up old metrics cache
        $this->cleanup_old_metrics();
    }
    
    /**
     * Clean up old metrics
     * 
     * @since 0.0.1
     * @return void
     */
    private function cleanup_old_metrics() {
        // Keep only recent performance data to prevent memory bloat
        // This is a simple implementation - could be enhanced with proper time-based cleanup
        
        if (count($this->metrics_cache) > 100) {
            // Keep only the most frequently used language pairs
            uasort($this->metrics_cache, function($a, $b) {
                return $b['total_requests'] - $a['total_requests'];
            });
            
            $this->metrics_cache = array_slice($this->metrics_cache, 0, 50, true);
        }
    }
    
    /**
     * Export performance logs
     * 
     * @since 0.0.1
     * @param array $filters Export filters
     * @return string Exported log data
     */
    public function export_logs($filters = array()) {
        $logs = array(
            'api_status' => $this->api_status,
            'performance_metrics' => $this->metrics_cache,
            'usage_statistics' => $this->get_usage_statistics(),
            'export_timestamp' => current_time('mysql'),
        );
        
        // Add analytics data if available
        if ($this->languages && method_exists($this->languages, 'get_analytics')) {
            $analytics = $this->languages->get_analytics();
            if ($analytics) {
                $logs['analytics_summary'] = $analytics->get_usage_stats('day');
            }
        }
        
        return json_encode($logs, JSON_PRETTY_PRINT);
    }
    
    /**
     * Get optimization recommendations
     * 
     * @since 0.0.1
     * @return array Optimization recommendations
     */
    public function get_optimization_recommendations() {
        $recommendations = array();
        
        // Analyze performance metrics
        $avg_response_time = 0;
        $total_requests = 0;
        $slow_pairs = array();
        
        foreach ($this->metrics_cache as $pair_key => $metrics) {
            $avg_response_time += $metrics['average_time'] * $metrics['total_requests'];
            $total_requests += $metrics['total_requests'];
            
            if ($metrics['average_time'] > 5.0) { // 5 seconds threshold
                $slow_pairs[] = $pair_key;
            }
        }
        
        if ($total_requests > 0) {
            $overall_avg = $avg_response_time / $total_requests;
            
            if ($overall_avg > 3.0) {
                $recommendations[] = array(
                    'type' => 'performance',
                    'severity' => 'medium',
                    'message' => __('Average response time is high. Consider optimizing API requests.', 'nexus-ai-wp-translator'),
                    'suggestion' => __('Review rate limiting settings and consider using faster models for simple translations.', 'nexus-ai-wp-translator'),
                );
            }
        }
        
        // Check error rate
        $success_rate = $this->api_status['total_requests'] > 0 ? 
            ($this->api_status['successful_requests'] / $this->api_status['total_requests']) * 100 : 100;
        
        if ($success_rate < 95) {
            $recommendations[] = array(
                'type' => 'reliability',
                'severity' => 'high',
                'message' => sprintf(
                    /* translators: %s: Success rate percentage */
                    __('API success rate is %s%%. This indicates potential issues.', 'nexus-ai-wp-translator'),
                    round($success_rate, 1)
                ),
                'suggestion' => __('Check API key validity and network connectivity. Consider enabling emergency mode protection.', 'nexus-ai-wp-translator'),
            );
        }
        
        // Check for slow language pairs
        if (!empty($slow_pairs)) {
            $recommendations[] = array(
                'type' => 'optimization',
                'severity' => 'low',
                'message' => sprintf(
                    /* translators: %s: Number of slow language pairs */
                    __('%d language pairs have slow response times.', 'nexus-ai-wp-translator'),
                    count($slow_pairs)
                ),
                'suggestion' => __('Consider using faster models for these language pairs to improve performance.', 'nexus-ai-wp-translator'),
                'details' => $slow_pairs,
            );
        }
        
        // Check rate limiting
        if ($this->api && method_exists($this->api, 'get_handler')) {
            $handler = $this->api->get_handler();
            if ($handler) {
                $rate_status = $handler->get_rate_limit_status();
                $usage_percentage = ($rate_status['current_requests'] / $rate_status['limit_per_minute']) * 100;
                
                if ($usage_percentage > 80) {
                    $recommendations[] = array(
                        'type' => 'rate_limit',
                        'severity' => 'medium',
                        'message' => __('API rate limit usage is high.', 'nexus-ai-wp-translator'),
                        'suggestion' => __('Consider increasing rate limits or implementing request queuing.', 'nexus-ai-wp-translator'),
                    );
                }
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Get cost analysis
     * 
     * @since 0.0.1
     * @param string $period Analysis period
     * @return array Cost analysis
     */
    public function get_cost_analysis($period = 'month') {
        $total_cost = 0;
        $total_characters = 0;
        $model_usage = array();
        
        foreach ($this->metrics_cache as $pair_key => $metrics) {
            // Estimate cost for this pair
            $avg_text_length = $metrics['character_count'] > 0 ? 
                $metrics['character_count'] / $metrics['total_requests'] : 100;
            
            // Assume most requests use haiku model (cheapest)
            $cost_estimate = $this->estimate_cost(str_repeat('x', $avg_text_length));
            $pair_cost = $cost_estimate['total_cost'] * $metrics['total_requests'];
            
            $total_cost += $pair_cost;
            $total_characters += $metrics['character_count'];
            
            // Track model usage (simplified)
            $model_usage['claude-3-haiku-20240307'] = 
                ($model_usage['claude-3-haiku-20240307'] ?? 0) + $metrics['total_requests'];
        }
        
        return array(
            'period' => $period,
            'total_estimated_cost' => round($total_cost, 4),
            'total_characters_processed' => $total_characters,
            'cost_per_character' => $total_characters > 0 ? round($total_cost / $total_characters, 6) : 0,
            'model_usage' => $model_usage,
            'currency' => 'USD',
            'note' => __('Cost estimates are approximate and based on current pricing.', 'nexus-ai-wp-translator'),
        );
    }
    
    /**
     * Clear performance cache
     * 
     * @since 0.0.1
     * @return void
     */
    public function clear_cache() {
        $this->metrics_cache = array();
        
        // Reset API status counters
        $this->api_status['total_requests'] = 0;
        $this->api_status['successful_requests'] = 0;
        $this->api_status['failed_requests'] = 0;
        
        $this->update_api_status();
        
        // Fire analytics event
        do_action('nexus_analytics_event', 'performance_cache_cleared', array(
            'timestamp' => current_time('mysql'),
        ));
    }
    
    /**
     * Get performance trends
     * 
     * @since 0.0.1
     * @param int $days Number of days to analyze
     * @return array Performance trends
     */
    public function get_performance_trends($days = 7) {
        // This would integrate with stored historical data
        // For now, return current state as baseline
        
        $trends = array(
            'period_days' => $days,
            'response_time_trend' => 'stable', // up, down, stable
            'success_rate_trend' => 'stable',
            'volume_trend' => 'stable',
            'recommendations' => array(),
        );
        
        // Analyze current metrics for basic trends
        $current_stats = $this->get_usage_statistics();
        
        if ($current_stats['success_rate'] < 90) {
            $trends['success_rate_trend'] = 'down';
            $trends['recommendations'][] = __('Success rate is declining. Monitor API connectivity.', 'nexus-ai-wp-translator');
        }
        
        if ($current_stats['average_response_time'] > 4.0) {
            $trends['response_time_trend'] = 'up';
            $trends['recommendations'][] = __('Response times are increasing. Consider optimization.', 'nexus-ai-wp-translator');
        }
        
        return $trends;
    }
    
    /**
     * Generate performance report
     * 
     * @since 0.0.1
     * @param array $options Report options
     * @return array Performance report
     */
    public function generate_performance_report($options = array()) {
        $defaults = array(
            'include_metrics' => true,
            'include_costs' => true,
            'include_recommendations' => true,
            'include_trends' => true,
            'period' => 'week',
        );
        
        $options = wp_parse_args($options, $defaults);
        
        $report = array(
            'generated_at' => current_time('mysql'),
            'period' => $options['period'],
            'summary' => $this->get_usage_statistics($options['period']),
        );
        
        if ($options['include_metrics']) {
            $report['detailed_metrics'] = $this->get_performance_metrics();
        }
        
        if ($options['include_costs']) {
            $report['cost_analysis'] = $this->get_cost_analysis($options['period']);
        }
        
        if ($options['include_recommendations']) {
            $report['optimization_recommendations'] = $this->get_optimization_recommendations();
        }
        
        if ($options['include_trends']) {
            $report['performance_trends'] = $this->get_performance_trends(7);
        }
        
        $report['api_status'] = $this->get_api_status();
        
        return apply_filters('nexus_performance_report', $report, $options);
    }
    
    /**
     * Update performance configuration
     * 
     * @since 0.0.1
     * @param array $new_config New configuration
     * @return bool True on success
     */
    public function update_config($new_config) {
        $current_config = get_option('nexus_ai_translator_performance_config', array());
        $updated_config = wp_parse_args($new_config, $current_config);
        
        // Validate configuration
        $validated_config = $this->validate_config($updated_config);
        
        if ($validated_config['valid']) {
            update_option('nexus_ai_translator_performance_config', $validated_config['config']);
            
            do_action('nexus_analytics_event', 'performance_config_updated', array(
                'changes' => array_diff_assoc($validated_config['config'], $current_config),
                'timestamp' => current_time('mysql'),
            ));
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Validate performance configuration
     * 
     * @since 0.0.1
     * @param array $config Configuration to validate
     * @return array Validation result
     */
    private function validate_config($config) {
        $errors = array();
        $cleaned_config = array();
        
        // Validate metrics_retention_days
        if (isset($config['metrics_retention_days'])) {
            $retention = intval($config['metrics_retention_days']);
            if ($retention < 1 || $retention > 365) {
                $errors['metrics_retention_days'] = __('Metrics retention must be between 1 and 365 days.', 'nexus-ai-wp-translator');
            } else {
                $cleaned_config['metrics_retention_days'] = $retention;
            }
        }
        
        // Validate performance_threshold
        if (isset($config['performance_threshold'])) {
            $threshold = floatval($config['performance_threshold']);
            if ($threshold < 0.1 || $threshold > 60) {
                $errors['performance_threshold'] = __('Performance threshold must be between 0.1 and 60 seconds.', 'nexus-ai-wp-translator');
            } else {
                $cleaned_config['performance_threshold'] = $threshold;
            }
        }
        
        return array(
            'valid' => empty($errors),
            'config' => $cleaned_config,
            'errors' => $errors,
        );
    }
}