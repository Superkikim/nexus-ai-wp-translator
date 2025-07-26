<?php
/**
 * File: nexus-ai-wp-translator.php
 * Location: /nexus-ai-wp-translator.php (plugin root)
 * 
 * Plugin Name: Nexus AI WP Translator
 * Plugin URI: https://github.com/superkikim/nexus-ai-wp-translator
 * Description: Modern automatic translation plugin for WordPress powered by Claude AI. Translate your content seamlessly with AI-powered accuracy.
 * Version: 0.0.1
 * Author: superkikim
 * Author URI: https://your-website.com
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: nexus-ai-wp-translator
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 * Network: false
 * 
 * Extensibility Metadata:
 * Hooks: nexus_before_translate, nexus_after_translate, nexus_analytics_event
 * Interfaces: Nexus_Analytics_Interface, Nexus_Cache_Interface, Nexus_Queue_Interface
 * Architecture: Modular, PSR-4, Future-ready
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('NEXUS_AI_TRANSLATOR_VERSION', '0.0.1');
define('NEXUS_AI_TRANSLATOR_PLUGIN_FILE', __FILE__);
define('NEXUS_AI_TRANSLATOR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NEXUS_AI_TRANSLATOR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NEXUS_AI_TRANSLATOR_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('NEXUS_AI_TRANSLATOR_TEXT_DOMAIN', 'nexus-ai-wp-translator');
define('NEXUS_AI_TRANSLATOR_INCLUDES_DIR', NEXUS_AI_TRANSLATOR_PLUGIN_DIR . 'includes/');
define('NEXUS_AI_TRANSLATOR_ADMIN_DIR', NEXUS_AI_TRANSLATOR_PLUGIN_DIR . 'admin/');
define('NEXUS_AI_TRANSLATOR_LANGUAGES_DIR', NEXUS_AI_TRANSLATOR_PLUGIN_DIR . 'languages/');

// Define minimum requirements
define('NEXUS_AI_TRANSLATOR_MIN_PHP', '7.4');
define('NEXUS_AI_TRANSLATOR_MIN_WP', '5.0');

/**
 * Check system requirements before activation
 * 
 * @return bool True if requirements are met, false otherwise
 */
function nexus_ai_translator_check_requirements() {
    global $wp_version;
    
    // Check PHP version
    if (version_compare(PHP_VERSION, NEXUS_AI_TRANSLATOR_MIN_PHP, '<')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>';
            printf(
                /* translators: 1: Current PHP version, 2: Required PHP version */
                __('Nexus AI WP Translator requires PHP %2$s or higher. You are running PHP %1$s.', NEXUS_AI_TRANSLATOR_TEXT_DOMAIN),
                PHP_VERSION,
                NEXUS_AI_TRANSLATOR_MIN_PHP
            );
            echo '</p></div>';
        });
        return false;
    }
    
    // Check WordPress version
    if (version_compare($wp_version, NEXUS_AI_TRANSLATOR_MIN_WP, '<')) {
        add_action('admin_notices', function() use ($wp_version) {
            echo '<div class="error"><p>';
            printf(
                /* translators: 1: Current WordPress version, 2: Required WordPress version */
                __('Nexus AI WP Translator requires WordPress %2$s or higher. You are running WordPress %1$s.', NEXUS_AI_TRANSLATOR_TEXT_DOMAIN),
                $wp_version,
                NEXUS_AI_TRANSLATOR_MIN_WP
            );
            echo '</p></div>';
        });
        return false;
    }
    
    // Check if required PHP extensions are available
    $required_extensions = ['curl', 'json'];
    foreach ($required_extensions as $extension) {
        if (!extension_loaded($extension)) {
            add_action('admin_notices', function() use ($extension) {
                echo '<div class="error"><p>';
                printf(
                    /* translators: %s: Required PHP extension name */
                    __('Nexus AI WP Translator requires the PHP %s extension to be installed.', NEXUS_AI_TRANSLATOR_TEXT_DOMAIN),
                    $extension
                );
                echo '</p></div>';
            });
            return false;
        }
    }
    
    return true;
}

/**
 * Initialize internationalization
 * Load plugin text domain for translations
 */
function nexus_ai_translator_load_textdomain() {
    load_plugin_textdomain(
        NEXUS_AI_TRANSLATOR_TEXT_DOMAIN,
        false,
        dirname(NEXUS_AI_TRANSLATOR_PLUGIN_BASENAME) . '/languages/'
    );
}

/**
 * Load the main plugin class
 * Only if requirements are met
 */
function nexus_ai_translator_init() {
    // Load text domain first
    nexus_ai_translator_load_textdomain();
    
    // Check requirements
    if (!nexus_ai_translator_check_requirements()) {
        return;
    }
    
    // Load Composer autoloader if available
    $autoloader = NEXUS_AI_TRANSLATOR_PLUGIN_DIR . 'vendor/autoload.php';
    if (file_exists($autoloader)) {
        require_once $autoloader;
    }
    
    // Load main class
    require_once NEXUS_AI_TRANSLATOR_INCLUDES_DIR . 'class-main.php';
    
    // Initialize the plugin
    if (class_exists('Nexus\\Translator\\Main')) {
        Nexus\Translator\Main::get_instance();
    }
}

// Hook into WordPress initialization
add_action('plugins_loaded', 'nexus_ai_translator_init', 10);

// Hook for text domain loading (early)
add_action('init', 'nexus_ai_translator_load_textdomain');

/**
 * Plugin activation hook
 * Set up initial configuration and check requirements
 */
function nexus_ai_translator_activate() {
    // Load text domain for activation messages
    nexus_ai_translator_load_textdomain();
    
    // Final requirements check on activation
    if (!nexus_ai_translator_check_requirements()) {
        deactivate_plugins(NEXUS_AI_TRANSLATOR_PLUGIN_BASENAME);
        wp_die(
            __('Nexus AI WP Translator cannot be activated due to unmet system requirements.', NEXUS_AI_TRANSLATOR_TEXT_DOMAIN),
            __('Plugin Activation Error', NEXUS_AI_TRANSLATOR_TEXT_DOMAIN),
            array('back_link' => true)
        );
    }
    
    // Store activation timestamp and version
    update_option('nexus_ai_translator_activated_time', time());
    update_option('nexus_ai_translator_version', NEXUS_AI_TRANSLATOR_VERSION);
    
    // Trigger activation hook for future modules
    do_action('nexus_ai_translator_activated');
}

register_activation_hook(__FILE__, 'nexus_ai_translator_activate');

/**
 * Plugin deactivation hook
 * Clean up temporary data but preserve settings
 */
function nexus_ai_translator_deactivate() {
    // Trigger deactivation hook for future modules
    do_action('nexus_ai_translator_deactivated');
    
    // Clean up temporary data (but keep settings)
    delete_transient('nexus_ai_translator_api_status');
    delete_transient('nexus_ai_translator_rate_limit');
}

register_deactivation_hook(__FILE__, 'nexus_ai_translator_deactivate');