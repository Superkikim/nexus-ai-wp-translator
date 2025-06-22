<?php
/**
 * File: nexus-ai-wp-translator.php
 * Location: /nexus-ai-wp-translator.php (plugin root)
 * 
 * Plugin Name: Nexus AI WP Translator
 * Plugin URI: https://github.com/superkikim/nexus-ai-wp-translator
 * Description: Modern automatic translation plugin with Claude AI, no multilingual plugin dependency. Intuitive interface with popups and real-time feedback.
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

// Define plugin constants
define('NEXUS_TRANSLATOR_VERSION', '1.0.0');
define('NEXUS_TRANSLATOR_PLUGIN_FILE', __FILE__);
define('NEXUS_TRANSLATOR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NEXUS_TRANSLATOR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NEXUS_TRANSLATOR_INCLUDES_DIR', NEXUS_TRANSLATOR_PLUGIN_DIR . 'includes/');
define('NEXUS_TRANSLATOR_ADMIN_DIR', NEXUS_TRANSLATOR_PLUGIN_DIR . 'admin/');
define('NEXUS_TRANSLATOR_PUBLIC_DIR', NEXUS_TRANSLATOR_PLUGIN_DIR . 'public/');

/**
 * Main Plugin Class
 */
final class Nexus_AI_WP_Translator {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
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
        add_action('plugins_loaded', array($this, 'init'));
        add_action('init', array($this, 'load_textdomain'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Check requirements
        if (!$this->check_requirements()) {
            return;
        }
        
        // Load includes
        $this->load_includes();
        
        // Initialize components
        $this->init_components();
        
        // Plugin loaded action
        do_action('nexus_translator_loaded');
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
        
        return true;
    }
    
    /**
     * Load plugin includes
     */
    private function load_includes() {
        // Core classes
        require_once NEXUS_TRANSLATOR_INCLUDES_DIR . 'class-nexus-translator.php';
        require_once NEXUS_TRANSLATOR_INCLUDES_DIR . 'class-translator-api.php';
        require_once NEXUS_TRANSLATOR_INCLUDES_DIR . 'class-post-linker.php';
        require_once NEXUS_TRANSLATOR_INCLUDES_DIR . 'class-language-manager.php';
        
        // Admin classes
        if (is_admin()) {
            require_once NEXUS_TRANSLATOR_INCLUDES_DIR . 'class-translator-admin.php';
            require_once NEXUS_TRANSLATOR_INCLUDES_DIR . 'class-translator-ajax.php';
        }
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        // Initialize main translator class
        new Nexus_Translator();
        
        // Initialize admin if in admin
        if (is_admin()) {
            new Translator_Admin();
            new Translator_AJAX();
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
        // Create database tables if needed
        $this->create_options();
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log activation
        error_log('Nexus AI WP Translator activated');
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up temporary data (keep settings)
        delete_transient('nexus_translator_cache');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log deactivation
        error_log('Nexus AI WP Translator deactivated');
    }
    
    /**
     * Create plugin options
     */
    private function create_options() {
        // Main plugin options
        if (!get_option('nexus_translator_options')) {
            add_option('nexus_translator_options', array());
        }
        
        // API settings
        if (!get_option('nexus_translator_api_settings')) {
            add_option('nexus_translator_api_settings', array());
        }
        
        // Language settings
        if (!get_option('nexus_translator_language_settings')) {
            add_option('nexus_translator_language_settings', array());
        }
    }
    
    /**
     * Set default options
     */
    private function set_default_options() {
        // Default language settings
        $default_languages = array(
            'source_language' => 'fr',
            'target_languages' => array('en'),
            'auto_translate' => false,
            'show_popup' => true
        );
        
        update_option('nexus_translator_language_settings', $default_languages);
        
        // Default API settings
        $default_api = array(
            'claude_api_key' => '',
            'model' => 'claude-sonnet-4-20250514',
            'max_tokens' => 4000,
            'temperature' => 0.3
        );
        
        update_option('nexus_translator_api_settings', $default_api);
        
        // Default general options
        $default_options = array(
            'version' => NEXUS_TRANSLATOR_VERSION,
            'debug_mode' => false,
            'cache_translations' => true,
            'show_language_switcher' => true
        );
        
        update_option('nexus_translator_options', $default_options);
    }
    
    /**
     * PHP version notice
     */
    public function php_version_notice() {
        echo '<div class="notice notice-error"><p>';
        printf(
            esc_html__('Nexus AI WP Translator requires PHP version 7.4 or higher. You are running PHP %s.', 'nexus-ai-wp-translator'),
            PHP_VERSION
        );
        echo '</p></div>';
    }
    
    /**
     * WordPress version notice
     */
    public function wp_version_notice() {
        echo '<div class="notice notice-error"><p>';
        printf(
            esc_html__('Nexus AI WP Translator requires WordPress version 5.0 or higher. You are running WordPress %s.', 'nexus-ai-wp-translator'),
            $GLOBALS['wp_version']
        );
        echo '</p></div>';
    }
}

/**
 * Get main plugin instance
 */
function nexus_translator() {
    return Nexus_AI_WP_Translator::get_instance();
}

// Initialize plugin
nexus_translator();

/**
 * Plugin uninstall cleanup
 */
if (!function_exists('nexus_translator_uninstall')) {
    function nexus_translator_uninstall() {
        // Remove all plugin options
        delete_option('nexus_translator_options');
        delete_option('nexus_translator_api_settings');
        delete_option('nexus_translator_language_settings');
        
        // Remove all transients
        delete_transient('nexus_translator_cache');
        
        // Check if user wants to preserve translation data
        $preserve_data = get_option('nexus_translator_preserve_on_uninstall', false);
        
        if (!$preserve_data) {
            // Complete cleanup: remove all translation metadata
            global $wpdb;
            
            // Remove translation relationships
            $wpdb->delete($wpdb->postmeta, array('meta_key' => '_nexus_translation_of'));
            $wpdb->delete($wpdb->postmeta, array('meta_key' => '_nexus_language'));
            $wpdb->delete($wpdb->postmeta, array('meta_key' => '_nexus_translation_status'));
            
            // Remove translation links (dynamic meta keys)
            $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_nexus_has_translation_%'");
            
            error_log('Nexus AI WP Translator: Complete cleanup performed during uninstall');
        } else {
            error_log('Nexus AI WP Translator: Settings removed, translation data preserved during uninstall');
        }
        
        // Always remove the preservation setting itself
        delete_option('nexus_translator_preserve_on_uninstall');
    }
}

register_uninstall_hook(__FILE__, 'nexus_translator_uninstall');