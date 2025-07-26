<?php
/**
 * File: interface-analytics.php
 * Location: /includes/interfaces/interface-analytics.php
 * 
 * Analytics interface for future analytics implementations
 * Defines contract for tracking events, metrics, and user interactions
 */

namespace Nexus\Translator\Interfaces;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Analytics Interface
 * 
 * Contract for analytics implementations
 * Defines methods for tracking events, metrics, and generating reports
 * 
 * @since 0.0.1
 * @package Nexus\Translator\Interfaces
 */
interface Analytics_Interface {
    
    /**
     * Track an event
     * 
     * @param string $event_name Event name/type
     * @param array $properties Event properties and metadata
     * @param int|null $user_id User ID (null for current user)
     * @return bool True on success, false on failure
     */
    public function track_event($event_name, $properties = array(), $user_id = null);
    
    /**
     * Track a metric/measurement
     * 
     * @param string $metric_name Metric name
     * @param mixed $value Metric value
     * @param array $tags Additional tags/dimensions
     * @return bool True on success, false on failure
     */
    public function track_metric($metric_name, $value, $tags = array());
    
    /**
     * Track page/screen view
     * 
     * @param string $page_name Page name or path
     * @param array $properties Additional page properties
     * @return bool True on success, false on failure
     */
    public function track_page_view($page_name, $properties = array());
    
    /**
     * Track user action/interaction
     * 
     * @param string $action Action name
     * @param string $object Object being acted upon
     * @param array $context Additional context
     * @return bool True on success, false on failure
     */
    public function track_user_action($action, $object, $context = array());
    
    /**
     * Track error or exception
     * 
     * @param string $error_type Error type/category
     * @param string $message Error message
     * @param array $context Error context and metadata
     * @return bool True on success, false on failure
     */
    public function track_error($error_type, $message, $context = array());
    
    /**
     * Get analytics data for a specific metric
     * 
     * @param string $metric_name Metric name
     * @param array $filters Filters to apply
     * @param string $date_range Date range (e.g., '7days', '30days', 'month')
     * @return array|false Analytics data or false on failure
     */
    public function get_metric_data($metric_name, $filters = array(), $date_range = '7days');
    
    /**
     * Get event data
     * 
     * @param string $event_name Event name
     * @param array $filters Filters to apply
     * @param string $date_range Date range
     * @param int $limit Maximum number of results
     * @return array|false Event data or false on failure
     */
    public function get_event_data($event_name, $filters = array(), $date_range = '7days', $limit = 100);
    
    /**
     * Generate analytics report
     * 
     * @param string $report_type Report type (e.g., 'summary', 'detailed', 'usage')
     * @param array $options Report options and parameters
     * @return array|false Report data or false on failure
     */
    public function generate_report($report_type, $options = array());
    
    /**
     * Get analytics summary/dashboard data
     * 
     * @param string $date_range Date range for summary
     * @return array|false Summary data or false on failure
     */
    public function get_summary($date_range = '7days');
    
    /**
     * Clean up old analytics data
     * 
     * @param string $retention_period How long to keep data (e.g., '90days', '1year')
     * @return bool True on success, false on failure
     */
    public function cleanup_old_data($retention_period = '90days');
    
    /**
     * Check if analytics is properly configured and working
     * 
     * @return bool True if analytics is working
     */
    public function is_configured();
    
    /**
     * Get analytics configuration status
     * 
     * @return array Configuration status and settings
     */
    public function get_status();
    
    /**
     * Set analytics configuration
     * 
     * @param array $config Configuration options
     * @return bool True on success, false on failure
     */
    public function set_config($config);
    
    /**
     * Enable or disable analytics tracking
     * 
     * @param bool $enabled True to enable, false to disable
     * @return bool True on success, false on failure
     */
    public function set_enabled($enabled);
    
    /**
     * Track custom conversion or goal
     * 
     * @param string $goal_name Goal/conversion name
     * @param mixed $value Optional value associated with goal
     * @param array $properties Additional properties
     * @return bool True on success, false on failure
     */
    public function track_conversion($goal_name, $value = null, $properties = array());
    
    /**
     * Export analytics data
     * 
     * @param string $format Export format ('csv', 'json', 'xml')
     * @param array $filters Data filters
     * @param string $date_range Date range for export
     * @return string|false Exported data or false on failure
     */
    public function export_data($format, $filters = array(), $date_range = '30days');
}