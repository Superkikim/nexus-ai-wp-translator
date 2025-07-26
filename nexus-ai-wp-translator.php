<?php
/**
 * File: nexus-ai-wp-translator.php
 * Location: /nexus-ai-wp-translator.php (plugin root)
 * 
 * Plugin Name: Nexus AI WP Translator
 * Plugin URI: https://github.com/superkikim/nexus-ai-wp-translator
 * Description: Modern automatic translation plugin with Claude AI, no multilingual plugin dependency. Intuitive interface with enhanced analytics and real-time feedback.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://your-website.com
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: nexus-ai-wp-translator
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.6
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Prevent multiple initializations
if (defined('NEXUS_TRANSLATOR_VERSION')) {
    return;
}

// Define plugin constants
define('NEXUS_TRANSLATOR_VERSION', '1.0.0');
define('NEXUS_TRANSLATOR_PLUGIN_FILE', __FILE__);
define('NEXUS_TRANSLATOR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NEXUS_TRANSLATOR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NEXUS_TRANSLATOR_INCLUDES_DIR', NEXUS_TRANSLATOR_PLUGIN_DIR . 'includes/');
define('NEXUS_TRANSLATOR_ADMIN_DIR', NEXUS_TRANSLATOR_PLUGIN_DIR . 'admin/');
define('NEXUS_TRANSLATOR_PUBLIC_DIR', NEXUS_TRANSLATOR_PLUGIN_DIR . 'public/');

/**
 * Main Plugin Class - Enhanced Version with Modular AJAX
 */
final class Nexus_AI_WP_Translator {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Component instances
     */
    private $components = array();
    
    /**
     * Initialization flag
     */
    private static $initialized = false;
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Prevent multiple initialization
        if (self::$initialized) {
            return;
        }
        
        self::$initialized = true;
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Plugin activation/deactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init'), 10);
        add_action('init', array($this, 'load_textdomain'));
        
        // Schedule analytics cleanup
        add_action('nexus_translator_cleanup_analytics', array($this, 'cleanup_old_analytics'));
        
        // Handle bulk translation processing
        add_action('nexus_process_bulk_translation', array($this, 'process_bulk_translation'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Prevent multiple calls
        static $init_called = false;
        if ($init_called) {
            return;
        }
        $init_called = true;
        
        error_log('Nexus Translator: Starting initialization with modular AJAX system...');
        
        // Check requirements
        if (!$this->check_requirements()) {
            return;
        }
        
        // Load includes
        $this->load_includes();
        
        // Initialize components
        $this->init_components();
        
        // Schedule cleanup if not already scheduled
        if (!wp_next_scheduled('nexus_translator_cleanup_analytics')) {
            wp_schedule_event(time(), 'daily', 'nexus_translator_cleanup_analytics');
        }
        
        // Plugin loaded action
        do_action('nexus_translator_loaded');
        
        error_log('Nexus Translator: Plugin initialized successfully with modular AJAX system');
    }
    
    /**
     * Check plugin requirements
     */
    private function check_requirements() {
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            add_action('admin_notices', array($this, 'php_version_notice'));
            return false;
        }
        
        // Check WordPress version
        if (version_compare($GLOBALS['wp_version'], '5.0', '<')) {
            add_action('admin_notices', array($this, 'wp_version_notice'));
            return false;
        }
        
        // Check for required functions
        if (!function_exists('wp_remote_post')) {
            add_action('admin_notices', array($this, 'wp_remote_notice'));
            return false;
        }
        
        return true;
    }
    
    /**
     * Load includes - UPDATED FOR MODULAR AJAX SYSTEM
     */
    private function load_includes() {
        // Core classes - Load in dependency order
        $core_classes = array(
            'class-language-manager.php',
            'class-translator-api.php',
            'class-post-linker.php',
            'class-nexus-translator.php',
            'class-translation-panel.php'
        );
        
        foreach ($core_classes as $class_file) {
            $file_path = NEXUS_TRANSLATOR_INCLUDES_DIR . $class_file;
            if (file_exists($file_path)) {
                require_once $file_path;
                error_log("Nexus Translator: Loaded core class: {$class_file}");
            } else {
                error_log("Nexus Translator: MISSING core file: {$class_file}");
            }
        }
        
        // Admin classes - UPDATED TO LOAD NEW MODULAR AJAX SYSTEM
        if (is_admin()) {
            $admin_classes = array(
                'class-translator-admin.php'
            );
            
            foreach ($admin_classes as $class_file) {
                $file_path = NEXUS_TRANSLATOR_INCLUDES_DIR . $class_file;
                if (file_exists($file_path)) {
                    require_once $file_path;
                    error_log("Nexus Translator: Loaded admin class: {$class_file}");
                } else {
                    error_log("Nexus Translator: MISSING admin file: {$class_file}");
                }
            }
            
            // Load NEW MODULAR AJAX SYSTEM
            $this->load_modular_ajax_system();
        }
        
        error_log('Nexus Translator: All includes loaded successfully with modular AJAX');
    }
    
    /**
     * Load the new modular AJAX system
     */
    private function load_modular_ajax_system() {
        $ajax_base_file = NEXUS_TRANSLATOR_INCLUDES_DIR . 'ajax/class-ajax-base.php';
        
        if (file_exists($ajax_base_file)) {
            require_once $ajax_base_file;
            error_log('Nexus Translator: AJAX base class loaded');
            
            // Load modular AJAX handlers
            $ajax_handlers = array(
                'ajax/class-ajax-translation.php',
                'ajax/class-ajax-admin.php',
                'ajax/class-ajax-analytics.php'
            );
            
            foreach ($ajax_handlers as $handler_file) {
                $file_path = NEXUS_TRANSLATOR_INCLUDES_DIR . $handler_file;
                if (file_exists($file_path)) {
                    require_once $file_path;
                    error_log("Nexus Translator: Loaded AJAX handler: {$handler_file}");
                } else {
                    error_log("Nexus Translator: MISSING AJAX handler: {$handler_file}");
                }
            }
            
            // Load AJAX coordinator last
            $coordinator_file = NEXUS_TRANSLATOR_INCLUDES_DIR . 'class-translator-ajax-coordinator.php';
            if (file_exists($coordinator_file)) {
                require_once $coordinator_file;
                error_log('Nexus Translator: AJAX coordinator loaded');
            } else {
                error_log('Nexus Translator: MISSING AJAX coordinator');
            }
        } else {
            error_log('Nexus Translator: CRITICAL - AJAX base class not found');
        }
    }
    
    /**
     * Initialize components - UPDATED FOR MODULAR AJAX
     */
    private function init_components() {
        try {
            // Initialize main translator class FIRST
            if (class_exists('Nexus_Translator')) {
                $this->components['translator'] = new Nexus_Translator();
                error_log('Nexus Translator: Main translator component loaded');
            } else {
                error_log('Nexus Translator: ERROR - Nexus_Translator class not found');
            }
            
            // Initialize admin components if in admin
            if (is_admin()) {
                if (class_exists('Translator_Admin')) {
                    $this->components['admin'] = new Translator_Admin();
                    error_log('Nexus Translator: Admin component loaded');
                } else {
                    error_log('Nexus Translator: WARNING - Translator_Admin class not found');
                }
                
                // Initialize NEW MODULAR AJAX COORDINATOR
                if (class_exists('Translator_AJAX_Coordinator')) {
                    $this->components['ajax_coordinator'] = new Translator_AJAX_Coordinator();
                    error_log('Nexus Translator: Modular AJAX coordinator loaded');
                } else {
                    error_log('Nexus Translator: WARNING - Translator_AJAX_Coordinator class not found');
                }
            }
            
            // Log component initialization summary
            $loaded_components = array_keys($this->components);
            error_log('Nexus Translator: Successfully loaded components: ' . implode(', ', $loaded_components));
            
        } catch (Exception $e) {
            error_log('Nexus Translator: CRITICAL - Component initialization error: ' . $e->getMessage());
            add_action('admin_notices', array($this, 'component_error_notice'));
        }
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'nexus-ai-wp-translator',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        error_log('Nexus Translator: Starting activation process');
        
        // Create database tables if needed
        $this->create_options();
        
        // Set default options
        $this->set_default_options();
        
        // Create necessary database indexes for performance
        $this->create_database_indexes();
        
        // Schedule analytics cleanup
        if (!wp_next_scheduled('nexus_translator_cleanup_analytics')) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'nexus_translator_cleanup_analytics');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set activation flag for welcome message
        set_transient('nexus_translator_activated', true, 60);
        
        // Log successful activation
        error_log('Nexus AI WP Translator activated successfully - Version: ' . NEXUS_TRANSLATOR_VERSION);
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        error_log('Nexus Translator: Starting deactivation process');
        
        // Clear scheduled events
        wp_clear_scheduled_hook('nexus_translator_cleanup_analytics');
        wp_clear_scheduled_hook('nexus_process_bulk_translation');
        
        // Clean up temporary data (keep settings)
        delete_transient('nexus_translator_cache');
        delete_transient('nexus_translator_activated');
        
        // Clear any active translation locks
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_nexus_translation_lock'");
        
        // Reset emergency stop
        delete_option('nexus_translator_emergency_stop');
        delete_option('nexus_translator_emergency_reason');
        delete_option('nexus_translator_emergency_time');
        
        // Force cleanup AJAX system
        if (class_exists('Ajax_Base')) {
            Ajax_Base::force_cleanup_requests();
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        error_log('Nexus AI WP Translator deactivated successfully');
    }
    
    /**
     * Create plugin options
     */
    private function create_options() {
        $options = array(
            'nexus_translator_options' => array(),
            'nexus_translator_api_settings' => array(),
            'nexus_translator_language_settings' => array(),
            'nexus_translator_analytics_retention' => 30,
            'nexus_translator_preserve_on_uninstall' => false
        );
        
        foreach ($options as $option_name => $default_value) {
            if (!get_option($option_name)) {
                add_option($option_name, $default_value);
            }
        }
    }
    
    /**
     * Set default options
     */
    private function set_default_options() {
        // Only set defaults if options are empty (fresh install)
        $api_settings = get_option('nexus_translator_api_settings', array());
        if (empty($api_settings)) {
            $default_api = array(
                'claude_api_key' => '',
                'model' => 'claude-sonnet-4-20250514',
                'max_tokens' => 4000,
                'temperature' => 0.3,
                'max_calls_per_hour' => 50,
                'max_calls_per_day' => 200,
                'min_request_interval' => 2,
                'request_timeout' => 60,
                'emergency_stop_threshold' => 10,
                'translation_cooldown' => 300
            );
            update_option('nexus_translator_api_settings', $default_api);
        }
        
        $language_settings = get_option('nexus_translator_language_settings', array());
        if (empty($language_settings)) {
            $default_languages = array(
                'source_language' => 'fr',
                'target_languages' => array('en', 'es', 'de', 'it'),
                'auto_translate' => false,
                'show_popup' => false
            );
            update_option('nexus_translator_language_settings', $default_languages);
        }
        
        $general_options = get_option('nexus_translator_options', array());
        if (empty($general_options)) {
            $default_options = array(
                'version' => NEXUS_TRANSLATOR_VERSION,
                'debug_mode' => false,
                'cache_translations' => true,
                'show_language_switcher' => true
            );
            update_option('nexus_translator_options', $default_options);
        }
    }
    
    /**
     * Create database indexes for better performance
     */
    private function create_database_indexes() {
        global $wpdb;
        
        // Simplified index creation without IF NOT EXISTS (MySQL compatibility)
        $indexes = array(
            "CREATE INDEX idx_nexus_translation_of ON {$wpdb->postmeta} (meta_key(20), meta_value(20))",
            "CREATE INDEX idx_nexus_language ON {$wpdb->postmeta} (meta_key(20), meta_value(10))",
            "CREATE INDEX idx_nexus_status ON {$wpdb->postmeta} (meta_key(30), meta_value(20))",
            "CREATE INDEX idx_nexus_timestamp ON {$wpdb->postmeta} (meta_key(30), meta_value(20))"
        );
        
        foreach ($indexes as $index_query) {
            // Silent execution - don't break if index exists
            $wpdb->query($index_query);
        }
    }
    
    /**
     * Process bulk translation (scheduled task)
     */
    public function process_bulk_translation($batch_id) {
        if (!$batch_id) {
            return;
        }
        
        $batch_data = get_option('nexus_bulk_translation_' . $batch_id, false);
        if (!$batch_data || $batch_data['status'] !== 'queued') {
            return;
        }
        
        // Update status to processing
        $batch_data['status'] = 'processing';
        $batch_data['started_processing'] = current_time('mysql');
        update_option('nexus_bulk_translation_' . $batch_id, $batch_data, false);
        
        // Initialize translator
        if (!isset($this->components['translator'])) {
            return;
        }
        
        $translator = $this->components['translator'];
        $completed = 0;
        $failed = 0;
        
        // Process each post-language combination
        foreach ($batch_data['post_ids'] as $post_id) {
            foreach ($batch_data['languages'] as $target_lang) {
                try {
                    $result = $translator->translate_post($post_id, $target_lang);
                    
                    if ($result['success']) {
                        $completed++;
                    } else {
                        $failed++;
                        error_log("Nexus Bulk Translation: Failed to translate post {$post_id} to {$target_lang}: " . $result['error']);
                    }
                } catch (Exception $e) {
                    $failed++;
                    error_log("Nexus Bulk Translation: Exception translating post {$post_id} to {$target_lang}: " . $e->getMessage());
                }
                
                // Small delay to prevent API overload
                sleep(2);
                
                // Update progress
                $progress = $completed + $failed;
                $batch_data['progress'] = $progress;
                $batch_data['completed'] = $completed;
                $batch_data['failed'] = $failed;
                update_option('nexus_bulk_translation_' . $batch_id, $batch_data, false);
            }
        }
        
        // Mark as completed
        $batch_data['status'] = 'completed';
        $batch_data['completed_at'] = current_time('mysql');
        update_option('nexus_bulk_translation_' . $batch_id, $batch_data, false);
        
        // Schedule cleanup after 24 hours
        wp_schedule_single_event(time() + DAY_IN_SECONDS, 'nexus_cleanup_bulk_batch', array($batch_id));
    }
    
    /**
     * Cleanup old analytics data
     */
    public function cleanup_old_analytics() {
        $retention_days = get_option('nexus_translator_analytics_retention', 30);
        $cutoff_timestamp = strtotime("-{$retention_days} days");
        
        global $wpdb;
        
        // Remove old translation metadata
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE pm FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id
             WHERE pm2.meta_key = '_nexus_translation_timestamp'
             AND pm2.meta_value < %d
             AND pm.meta_key IN ('_nexus_translation_timestamp', '_nexus_translation_error', '_nexus_translation_usage')",
            $cutoff_timestamp
        ));
        
        if ($deleted > 0) {
            error_log("Nexus Translator: Cleaned up {$deleted} old analytics records");
        }
    }
    
    /**
     * Get component instance
     */
    public function get_component($name) {
        return isset($this->components[$name]) ? $this->components[$name] : null;
    }
    
    /**
     * Check if debug mode is enabled
     */
    private function is_debug_mode() {
        $options = get_option('nexus_translator_options', array());
        return !empty($options['debug_mode']);
    }
    
    /**
     * Get system status for debugging
     */
    public function get_system_status() {
        $ajax_status = array();
        if (class_exists('Ajax_Base')) {
            $ajax_status = Ajax_Base::get_system_status();
        }
        
        return array(
            'version' => NEXUS_TRANSLATOR_VERSION,
            'php_version' => PHP_VERSION,
            'wp_version' => $GLOBALS['wp_version'],
            'components_loaded' => array_keys($this->components),
            'debug_mode' => $this->is_debug_mode(),
            'initialized' => self::$initialized,
            'ajax_system' => $ajax_status,
            'scheduled_events' => array(
                'cleanup_analytics' => wp_next_scheduled('nexus_translator_cleanup_analytics'),
                'bulk_translation' => wp_next_scheduled('nexus_process_bulk_translation')
            ),
            'options_exist' => array(
                'api_settings' => (bool) get_option('nexus_translator_api_settings'),
                'language_settings' => (bool) get_option('nexus_translator_language_settings'),
                'general_options' => (bool) get_option('nexus_translator_options')
            )
        );
    }
    
    /**
     * Admin notices
     */
    public function php_version_notice() {
        echo '<div class="notice notice-error"><p>';
        printf(
            esc_html__('Nexus AI WP Translator requires PHP version 7.4 or higher. You are running PHP %s.', 'nexus-ai-wp-translator'),
            PHP_VERSION
        );
        echo '</p></div>';
    }
    
    public function wp_version_notice() {
        echo '<div class="notice notice-error"><p>';
        printf(
            esc_html__('Nexus AI WP Translator requires WordPress version 5.0 or higher. You are running WordPress %s.', 'nexus-ai-wp-translator'),
            $GLOBALS['wp_version']
        );
        echo '</p></div>';
    }
    
    public function wp_remote_notice() {
        echo '<div class="notice notice-error"><p>';
        esc_html_e('Nexus AI WP Translator requires the WordPress HTTP API (wp_remote_post function). Please contact your hosting provider.', 'nexus-ai-wp-translator');
        echo '</p></div>';
    }
    
    public function component_error_notice() {
        echo '<div class="notice notice-error"><p>';
        esc_html_e('Nexus AI WP Translator: Some components failed to initialize. Please check the error log for details.', 'nexus-ai-wp-translator');
        echo '</p></div>';
    }
}

/**
 * Get main plugin instance
 */
function nexus_translator() {
    return Nexus_AI_WP_Translator::get_instance();
}

/**
 * Get specific component
 */
function nexus_translator_get_component($name) {
    return nexus_translator()->get_component($name);
}

/**
 * Get system status for debugging
 */
function nexus_translator_system_status() {
    return nexus_translator()->get_system_status();
}

// Initialize plugin - with protection against multiple calls
if (!function_exists('nexus_translator_init')) {
    function nexus_translator_init() {
        nexus_translator();
    }
    add_action('plugins_loaded', 'nexus_translator_init', 5);
}

/**
 * Plugin uninstall cleanup - Enhanced Version
 */
if (!function_exists('nexus_translator_uninstall')) {
    function nexus_translator_uninstall() {
        // Clear scheduled events
        wp_clear_scheduled_hook('nexus_translator_cleanup_analytics');
        wp_clear_scheduled_hook('nexus_process_bulk_translation');
        
        // Force cleanup AJAX system
        if (class_exists('Ajax_Base')) {
            Ajax_Base::force_cleanup_requests();
        }
        
        // Remove plugin options
        $options_to_remove = array(
            'nexus_translator_options',
            'nexus_translator_api_settings',
            'nexus_translator_language_settings',
            'nexus_translator_analytics_retention',
            'nexus_translator_emergency_stop',
            'nexus_translator_emergency_reason',
            'nexus_translator_emergency_time',
            'nexus_translator_total_tokens',
            'nexus_translator_estimated_cost',
            'nexus_translator_version'
        );
        
        foreach ($options_to_remove as $option) {
            delete_option($option);
        }
        
        // Remove all transients
        delete_transient('nexus_translator_cache');
        delete_transient('nexus_translator_activated');
        
        // Remove bulk translation batches
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'nexus_bulk_translation_%'");
        
        // Check if user wants to preserve translation data
        $preserve_data = get_option('nexus_translator_preserve_on_uninstall', false);
        
        if (!$preserve_data) {
            // Complete cleanup: remove all translation metadata
            $meta_keys_to_remove = array(
                '_nexus_translation_of',
                '_nexus_language',
                '_nexus_translation_status',
                '_nexus_auto_translate',
                '_nexus_target_languages',
                '_nexus_last_translation_results',
                '_nexus_translation_timestamp',
                '_nexus_translation_error',
                '_nexus_translation_usage',
                '_nexus_translation_lock',
                '_nexus_published_before'
            );
            
            foreach ($meta_keys_to_remove as $meta_key) {
                $wpdb->delete($wpdb->postmeta, array('meta_key' => $meta_key));
            }
            
            // Remove translation links (dynamic meta keys)
            $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_nexus_has_translation_%'");
            
            // Remove custom indexes
            $indexes_to_remove = array(
                'idx_nexus_translation_of',
                'idx_nexus_language', 
                'idx_nexus_status',
                'idx_nexus_timestamp'
            );
            
            foreach ($indexes_to_remove as $index) {
                $wpdb->query("DROP INDEX IF EXISTS {$index} ON {$wpdb->postmeta}");
            }
            
            error_log('Nexus AI WP Translator: Complete cleanup performed during uninstall');
        } else {
            error_log('Nexus AI WP Translator: Settings removed, translation data preserved during uninstall');
        }
        
        // Always remove the preservation setting itself
        delete_option('nexus_translator_preserve_on_uninstall');
        
        // Clear any remaining caches
        wp_cache_flush();
    }
}

register_uninstall_hook(__FILE__, 'nexus_translator_uninstall');

/**
 * Schedule bulk batch cleanup
 */
add_action('nexus_cleanup_bulk_batch', function($batch_id) {
    delete_option('nexus_bulk_translation_' . $batch_id);
    error_log("Nexus Translator: Cleaned up bulk translation batch: {$batch_id}");
});

/**
 * Plugin upgrade handler
 */
add_action('upgrader_process_complete', function($upgrader, $options) {
    if ($options['type'] === 'plugin' && isset($options['plugins'])) {
        foreach ($options['plugins'] as $plugin) {
            if ($plugin === plugin_basename(__FILE__)) {
                // Plugin was updated, run upgrade routine
                do_action('nexus_translator_upgraded');
                break;
            }
        }
    }
}, 10, 2);

/**
 * Handle plugin upgrades
 */
add_action('nexus_translator_upgraded', function() {
    $current_version = get_option('nexus_translator_version', '0.0.0');
    
    if (version_compare($current_version, NEXUS_TRANSLATOR_VERSION, '<')) {
        // Update version
        update_option('nexus_translator_version', NEXUS_TRANSLATOR_VERSION);
        error_log("Nexus Translator: Upgraded from {$current_version} to " . NEXUS_TRANSLATOR_VERSION);
    }
});

/**
 * Add action links to plugin page
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        admin_url('admin.php?page=nexus-translator-settings'),
        __('Settings', 'nexus-ai-wp-translator')
    );
    
    $support_link = sprintf(
        '<a href="%s" target="_blank">%s</a>',
        'https://github.com/superkikim/nexus-ai-wp-translator/issues',
        __('Support', 'nexus-ai-wp-translator')
    );
    
    array_unshift($links, $settings_link);
    $links[] = $support_link;
    
    return $links;
});

/**
 * Add plugin meta links
 */
add_filter('plugin_row_meta', function($links, $file) {
    if ($file === plugin_basename(__FILE__)) {
        $meta_links = array(
            'docs' => sprintf(
                '<a href="%s" target="_blank">%s</a>',
                'https://github.com/superkikim/nexus-ai-wp-translator/wiki',
                __('Documentation', 'nexus-ai-wp-translator')
            ),
            'github' => sprintf(
                '<a href="%s" target="_blank">%s</a>',
                'https://github.com/superkikim/nexus-ai-wp-translator',
                __('GitHub', 'nexus-ai-wp-translator')
            )
        );
        
        $links = array_merge($links, $meta_links);
    }
    
    return $links;
}, 10, 2);