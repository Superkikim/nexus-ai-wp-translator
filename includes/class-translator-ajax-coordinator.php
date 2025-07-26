<?php
/**
 * File: class-translator-ajax-coordinator.php
 * Location: /includes/class-translator-ajax-coordinator.php
 * 
 * AJAX Coordinator - Enhanced version with emergency button support
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translator_AJAX_Coordinator {
    
    /**
     * Instances of specialized handlers
     */
    private $translation_handler;
    private $admin_handler;
    private $analytics_handler;
    
    /**
     * Initialization status
     */
    private $initialized = false;
    
    /**
     * Constructor
     */
    public function __construct() {
        error_log('Nexus AJAX Coordinator: Starting enhanced initialization');
        $this->load_handlers();
        $this->init_cleanup_hooks();
        $this->init_emergency_handlers();
        $this->initialized = true;
        
        // Log final status
        $status = $this->get_handlers_status();
        error_log('Nexus AJAX Coordinator: Enhanced initialization complete - ' . json_encode($status));
    }
    
    /**
     * Load all specialized handlers
     */
    private function load_handlers() {
        // Load base class first
        $base_file = NEXUS_TRANSLATOR_INCLUDES_DIR . 'ajax/class-ajax-base.php';
        if (!file_exists($base_file)) {
            error_log('Nexus AJAX Coordinator: CRITICAL - Base class not found');
            return;
        }
        
        // Load and initialize specialized handlers
        $handlers = array(
            'translation' => 'ajax/class-ajax-translation.php',
            'admin' => 'ajax/class-ajax-admin.php',
            'analytics' => 'ajax/class-ajax-analytics.php'
        );
        
        foreach ($handlers as $type => $file_path) {
            $full_path = NEXUS_TRANSLATOR_INCLUDES_DIR . $file_path;
            if (file_exists($full_path)) {
                require_once $full_path;
                
                $class_name = 'Ajax_' . ucfirst($type);
                if (class_exists($class_name)) {
                    $property_name = $type . '_handler';
                    $this->$property_name = new $class_name();
                    error_log("Nexus AJAX Coordinator: {$class_name} handler loaded successfully");
                } else {
                    error_log("Nexus AJAX Coordinator: WARNING - Class {$class_name} not found");
                }
            } else {
                error_log("Nexus AJAX Coordinator: WARNING - Handler file missing: {$file_path}");
            }
        }
    }
    
    /**
     * Initialize emergency button handlers directly in coordinator
     */
    private function init_emergency_handlers() {
        // Ensure critical emergency handlers are available even if admin handler fails
        add_action('wp_ajax_nexus_emergency_cleanup_direct', array($this, 'handle_emergency_cleanup_direct'));
        add_action('wp_ajax_nexus_reset_emergency_direct', array($this, 'handle_reset_emergency_direct'));
        add_action('wp_ajax_nexus_force_cleanup_system', array($this, 'handle_force_cleanup_system'));
        
        error_log('Nexus AJAX Coordinator: Emergency handlers registered');
    }
    
    /**
     * Initialize cleanup hooks
     */
    private function init_cleanup_hooks() {
        // General force cleanup handler
        add_action('wp_ajax_nexus_force_cleanup', array($this, 'handle_force_cleanup'));
        
        // WordPress daily cleanup hook
        if (!wp_next_scheduled('nexus_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'nexus_daily_cleanup');
        }
        add_action('nexus_daily_cleanup', array($this, 'daily_cleanup'));
        
        // Cleanup on shutdown if needed
        add_action('shutdown', array($this, 'emergency_cleanup_on_shutdown'));
    }
    
    /**
     * Emergency cleanup handler - Direct access
     */
    public function handle_emergency_cleanup_direct() {
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(array('error' => 'Access denied', 'error_code' => 'ACCESS_DENIED'));
        }
        
        error_log('Nexus AJAX Coordinator: Starting direct emergency cleanup');
        
        try {
            $cleaned = array();
            
            // Force cleanup all AJAX requests
            if (class_exists('Ajax_Base')) {
                Ajax_Base::force_cleanup_requests();
                $cleaned[] = 'AJAX requests cleared';
            }
            
            // Clear all translation locks
            global $wpdb;
            $locks_deleted = $wpdb->query(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_nexus_translation_lock'"
            );
            $cleaned[] = sprintf('%d translation locks removed', $locks_deleted);
            
            // Reset stuck translations
            $processing_reset = $wpdb->query(
                "UPDATE {$wpdb->postmeta} 
                 SET meta_value = 'error' 
                 WHERE meta_key = '_nexus_translation_status' 
                 AND meta_value = 'processing'"
            );
            $cleaned[] = sprintf('%d stuck translations reset', $processing_reset);
            
            // Clear all rate limits
            $this->clear_all_rate_limits();
            $cleaned[] = 'Rate limits reset';
            
            // Clear emergency stop
            delete_option('nexus_translator_emergency_stop');
            delete_option('nexus_translator_emergency_reason');
            delete_option('nexus_translator_emergency_time');
            $cleaned[] = 'Emergency stop cleared';
            
            // Clear active translations option
            delete_option('nexus_translator_active_translations');
            $cleaned[] = 'Active translations cleared';
            
            // Clear all transients
            $this->clear_all_transients();
            $cleaned[] = 'Transients cleared';
            
            // Clear WordPress caches
            wp_cache_flush();
            $cleaned[] = 'WordPress caches flushed';
            
            error_log('Nexus AJAX Coordinator: Direct emergency cleanup completed successfully');
            
            wp_send_json_success(array(
                'message' => 'Emergency cleanup completed successfully',
                'actions' => $cleaned,
                'timestamp' => current_time('mysql')
            ));
            
        } catch (Exception $e) {
            error_log('Nexus AJAX Coordinator: Emergency cleanup failed: ' . $e->getMessage());
            wp_send_json_error(array(
                'error' => 'Emergency cleanup failed: ' . $e->getMessage(),
                'error_code' => 'CLEANUP_FAILED'
            ));
        }
    }
    
    /**
     * Reset emergency stop - Direct access
     */
    public function handle_reset_emergency_direct() {
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(array('error' => 'Access denied', 'error_code' => 'ACCESS_DENIED'));
        }
        
        error_log('Nexus AJAX Coordinator: Resetting emergency stop directly');
        
        // Clear all emergency stop related options
        delete_option('nexus_translator_emergency_stop');
        delete_option('nexus_translator_emergency_reason');
        delete_option('nexus_translator_emergency_time');
        
        // Also clear any rate limit blocks
        delete_transient('nexus_api_rate_limit_hit');
        
        wp_send_json_success(array(
            'message' => 'Emergency stop reset successfully',
            'timestamp' => current_time('mysql')
        ));
    }
    
    /**
     * Force system cleanup
     */
    public function handle_force_cleanup_system() {
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(array('error' => 'Access denied', 'error_code' => 'ACCESS_DENIED'));
        }
        
        // This is the most aggressive cleanup - use with caution
        $this->force_system_reset();
        
        wp_send_json_success(array(
            'message' => 'System force cleanup completed',
            'warning' => 'All plugin data has been reset',
            'timestamp' => current_time('mysql')
        ));
    }
    
    /**
     * Daily cleanup routine
     */
    public function daily_cleanup() {
        error_log('Nexus AJAX Coordinator: Starting daily cleanup');
        
        $cleaned = array();
        
        // Cleanup expired requests from base class
        if (class_exists('Ajax_Base')) {
            $cleaned_requests = Ajax_Base::cleanup_expired_requests();
            $cleaned[] = "Cleaned {$cleaned_requests} expired AJAX requests";
        }
        
        // Cleanup old translation locks (older than 1 hour)
        $cleaned_locks = $this->cleanup_old_translation_locks();
        $cleaned[] = "Cleaned {$cleaned_locks} old translation locks";
        
        // Cleanup old logs
        $this->cleanup_old_logs();
        $cleaned[] = "Cleaned old logs";
        
        // Cleanup orphaned metadata
        $cleaned_meta = $this->cleanup_orphaned_metadata();
        $cleaned[] = "Cleaned {$cleaned_meta} orphaned metadata entries";
        
        error_log("Nexus AJAX Coordinator: Daily cleanup completed - " . implode(', ', $cleaned));
    }
    
    /**
     * General force cleanup handler
     */
    public function handle_force_cleanup() {
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(array('error' => 'Access denied', 'error_code' => 'ACCESS_DENIED'));
        }
        
        // Force cleanup on all handlers
        if (class_exists('Ajax_Base')) {
            Ajax_Base::force_cleanup_requests();
        }
        
        // Cleanup translation locks
        delete_option('nexus_translator_active_translations');
        
        // Clear transients
        delete_transient('nexus_translator_cache');
        
        wp_send_json_success(array(
            'message' => 'Force cleanup completed',
            'timestamp' => current_time('mysql')
        ));
    }
    
    /**
     * Clear all rate limits
     */
    private function clear_all_rate_limits() {
        // Clear hourly and daily transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%nexus_api_calls_%'");
        
        // Clear specific transients
        delete_transient('nexus_translator_rate_limit_hour');
        delete_transient('nexus_translator_rate_limit_day');
        delete_transient('nexus_translator_last_request');
        delete_transient('nexus_api_rate_limit_hit');
    }
    
    /**
     * Clear all plugin transients
     */
    private function clear_all_transients() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%transient%nexus%translator%'");
    }
    
    /**
     * Cleanup old translation locks
     */
    private function cleanup_old_translation_locks() {
        global $wpdb;
        
        $current_time = current_time('timestamp');
        $timeout = 3600; // 1 hour
        
        // Remove locks older than 1 hour from postmeta
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} 
             WHERE meta_key = '_nexus_translation_lock' 
             AND meta_value < %d",
            $current_time - $timeout
        ));
        
        // Cleanup active translations option
        $active_translations = get_option('nexus_translator_active_translations', array());
        $cleaned = 0;
        
        foreach ($active_translations as $key => $timestamp) {
            if (($current_time - $timestamp) > $timeout) {
                unset($active_translations[$key]);
                $cleaned++;
            }
        }
        
        if ($cleaned > 0) {
            update_option('nexus_translator_active_translations', $active_translations);
        }
        
        return $deleted + $cleaned;
    }
    
    /**
     * Cleanup old logs
     */
    private function cleanup_old_logs() {
        $retention_days = get_option('nexus_translator_analytics_retention', 30);
        $cutoff_date = date('Y-m-d', strtotime("-{$retention_days} days"));
        
        // Cleanup daily stats
        $daily_stats = get_option('nexus_translator_daily_stats', array());
        foreach ($daily_stats as $date => $stats) {
            if ($date < $cutoff_date) {
                unset($daily_stats[$date]);
            }
        }
        update_option('nexus_translator_daily_stats', $daily_stats);
        
        // Cleanup usage log
        $usage_log = get_option('nexus_translator_usage_log', array());
        $cutoff_timestamp = strtotime("-{$retention_days} days");
        
        $cleaned_log = array_filter($usage_log, function($entry) use ($cutoff_timestamp) {
            return isset($entry['timestamp']) && $entry['timestamp'] >= $cutoff_timestamp;
        });
        
        if (count($cleaned_log) !== count($usage_log)) {
            update_option('nexus_translator_usage_log', array_values($cleaned_log));
            $cleaned_count = count($usage_log) - count($cleaned_log);
            error_log("Nexus AJAX Coordinator: Cleaned {$cleaned_count} old log entries");
        }
    }
    
    /**
     * Cleanup orphaned metadata
     */
    private function cleanup_orphaned_metadata() {
        global $wpdb;
        
        // Remove metadata for non-existent posts
        $deleted = $wpdb->query(
            "DELETE pm FROM {$wpdb->postmeta} pm
             LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.ID IS NULL
             AND pm.meta_key LIKE '_nexus_%'"
        );
        
        return $deleted;
    }
    
    /**
     * Emergency cleanup on shutdown (if system is unstable)
     */
    public function emergency_cleanup_on_shutdown() {
        // Only run if there are active requests that seem stuck
        if (class_exists('Ajax_Base')) {
            $active_requests = Ajax_Base::get_active_requests();
            if (!empty($active_requests)) {
                $oldest_time = min($active_requests);
                // If oldest request is more than 10 minutes old, force cleanup
                if ((time() - $oldest_time) > 600) {
                    Ajax_Base::force_cleanup_requests();
                    error_log('Nexus AJAX Coordinator: Emergency cleanup performed on shutdown');
                }
            }
        }
    }
    
    /**
     * Force system reset (nuclear option)
     */
    private function force_system_reset() {
        global $wpdb;
        
        error_log('Nexus AJAX Coordinator: WARNING - Force system reset initiated');
        
        // Clear all AJAX requests
        if (class_exists('Ajax_Base')) {
            Ajax_Base::force_cleanup_requests();
        }
        
        // Remove ALL nexus-related postmeta
        $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_nexus_%'");
        
        // Remove ALL nexus-related options
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%nexus_translator%'");
        
        // Remove ALL nexus-related transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%transient%nexus%'");
        
        // Clear all caches
        wp_cache_flush();
        
        error_log('Nexus AJAX Coordinator: Force system reset completed');
    }
    
    /**
     * Get handler status for debugging
     */
    public function get_handlers_status() {
        $status = array(
            'coordinator_initialized' => $this->initialized,
            'translation_handler' => $this->translation_handler ? 'loaded' : 'missing',
            'admin_handler' => $this->admin_handler ? 'loaded' : 'missing',
            'analytics_handler' => $this->analytics_handler ? 'loaded' : 'missing',
            'emergency_handlers_registered' => $this->are_emergency_handlers_registered(),
            'scheduled_cleanup' => wp_next_scheduled('nexus_daily_cleanup')
        );
        
        if (class_exists('Ajax_Base')) {
            $status['ajax_base_status'] = Ajax_Base::get_system_status();
        }
        
        return $status;
    }
    
    /**
     * Check if emergency handlers are properly registered
     */
    private function are_emergency_handlers_registered() {
        $emergency_actions = array(
            'nexus_emergency_cleanup_direct',
            'nexus_reset_emergency_direct',
            'nexus_force_cleanup_system',
            'nexus_force_cleanup'
        );
        
        $registered = array();
        foreach ($emergency_actions as $action) {
            $registered[$action] = has_action("wp_ajax_{$action}");
        }
        
        return $registered;
    }
    
    /**
     * Get system diagnostic information
     */
    public function get_system_diagnostics() {
        return array(
            'handlers_status' => $this->get_handlers_status(),
            'emergency_stop_active' => get_option('nexus_translator_emergency_stop', false),
            'active_translations' => get_option('nexus_translator_active_translations', array()),
            'scheduled_events' => array(
                'nexus_daily_cleanup' => wp_next_scheduled('nexus_daily_cleanup'),
                'nexus_translator_cleanup_analytics' => wp_next_scheduled('nexus_translator_cleanup_analytics')
            ),
            'server_info' => array(
                'php_version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true)
            )
        );
    }
    
    /**
     * Public method for emergency cleanup
     */
    public function force_cleanup_all() {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        if (class_exists('Ajax_Base')) {
            Ajax_Base::force_cleanup_requests();
        }
        
        delete_option('nexus_translator_active_translations');
        wp_cache_flush();
        
        error_log('Nexus AJAX Coordinator: Force cleanup all completed by admin');
        return true;
    }
}