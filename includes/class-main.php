<?php
/**
 * File: class-main.php
 * Location: /includes/class-main.php
 * 
 * Main Orchestrator Class (Refactored)
 * Responsible for: Core orchestration, singleton pattern, plugin lifecycle
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
 * Main orchestrator class for Nexus AI WP Translator
 * 
 * This class serves as the central hub, delegating specific responsibilities
 * to specialized handlers for maintainability and modularity.
 * 
 * @since 0.0.1
 */
class Main {
    
    /**
     * Single instance of this class
     * 
     * @since 0.0.1
     * @var Main
     */
    private static $instance = null;
    
    /**
     * Plugin version
     * 
     * @since 0.0.1
     * @var string
     */
    private $version;
    
    /**
     * Component loader instance
     * 
     * @since 0.0.1
     * @var Component_Loader
     */
    private $component_loader;
    
    /**
     * Hook manager instance
     * 
     * @since 0.0.1
     * @var Hook_Manager
     */
    private $hook_manager;
    
    /**
     * Emergency handler instance
     * 
     * @since 0.0.1
     * @var Emergency_Handler
     */
    private $emergency_handler;
    
    /**
     * Plugin initialization status
     * 
     * @since 0.0.1
     * @var bool
     */
    private $initialized = false;
    
    /**
     * Constructor - private to enforce singleton
     * 
     * @since 0.0.1
     */
    private function __construct() {
        $this->version = NEXUS_AI_TRANSLATOR_VERSION;
        $this->init();
    }
    
    /**
     * Get singleton instance
     * 
     * @since 0.0.1
     * @return Main Single instance of Main class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Prevent cloning
     * 
     * @since 0.0.1
     */
    public function __clone() {
        _doing_it_wrong(__FUNCTION__, __('Cannot clone singleton instance.', NEXUS_AI_TRANSLATOR_TEXT_DOMAIN), $this->version);
    }
    
    /**
     * Prevent unserialization
     * 
     * @since 0.0.1
     */
    public function __wakeup() {
        _doing_it_wrong(__FUNCTION__, __('Cannot unserialize singleton instance.', NEXUS_AI_TRANSLATOR_TEXT_DOMAIN), $this->version);
    }
    
    /**
     * Initialize the plugin
     * 
     * @since 0.0.1
     */
    private function init() {
        // Load helper classes first
        $this->load_helper_classes();
        
        // Initialize emergency handler
        $this->emergency_handler = new Emergency_Handler();
        
        // Check for emergency mode
        if ($this->emergency_handler->is_emergency_mode()) {
            $this->emergency_handler->init_emergency_mode();
            return;
        }
        
        // Initialize component loader
        $this->component_loader = new Component_Loader();
        
        // Initialize hook manager
        $this->hook_manager = new Hook_Manager($this->component_loader);
        
        // Load and initialize components
        $this->component_loader->load_all();
        
        // Register hooks
        $this->hook_manager->register_all();
        
        // Initialize components
        $this->component_loader->init_all();
        
        // Mark as initialized
        $this->initialized = true;
        
        // Fire initialization complete hook
        do_action('nexus_ai_translator_initialized', $this);
    }
    
    /**
     * Load helper classes
     * 
     * @since 0.0.1
     */
    private function load_helper_classes() {
        $helper_files = array(
            'class-component-loader.php',
            'class-hook-manager.php',
            'class-emergency-handler.php',
        );
        
        foreach ($helper_files as $file) {
            $file_path = NEXUS_AI_TRANSLATOR_INCLUDES_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }
    
    /**
     * Get component loader instance
     * 
     * @since 0.0.1
     * @return Component_Loader|null
     */
    public function get_component_loader() {
        return $this->component_loader;
    }
    
    /**
     * Get hook manager instance
     * 
     * @since 0.0.1
     * @return Hook_Manager|null
     */
    public function get_hook_manager() {
        return $this->hook_manager;
    }
    
    /**
     * Get emergency handler instance
     * 
     * @since 0.0.1
     * @return Emergency_Handler
     */
    public function get_emergency_handler() {
        return $this->emergency_handler;
    }
    
    /**
     * Get a component instance (delegated to component loader)
     * 
     * @since 0.0.1
     * @param string $component Component name
     * @return object|null Component instance or null if not found
     */
    public function get_component($component) {
        if (!$this->component_loader) {
            return null;
        }
        
        return $this->component_loader->get_component($component);
    }
    
    /**
     * Register a module for extensibility (delegated to component loader)
     * 
     * @since 0.0.1
     * @param string $module_name Module name
     * @param array $module_data Module configuration
     * @return bool True on success, false on failure
     */
    public function register_module($module_name, $module_data = array()) {
        if (!$this->component_loader) {
            return false;
        }
        
        return $this->component_loader->register_module($module_name, $module_data);
    }
    
    /**
     * Check if plugin is properly initialized
     * 
     * @since 0.0.1
     * @return bool True if initialized, false otherwise
     */
    public function is_initialized() {
        return $this->initialized;
    }
    
    /**
     * Get plugin version
     * 
     * @since 0.0.1
     * @return string Plugin version
     */
    public function get_version() {
        return $this->version;
    }
    
    /**
     * Plugin activation handler
     * 
     * @since 0.0.1
     */
    public function plugin_activated() {
        // Clear any emergency mode flags on activation
        if ($this->emergency_handler) {
            $this->emergency_handler->reset_emergency_mode();
        }
        
        // Set default options if they don't exist
        $this->set_default_options();
        
        do_action('nexus_ai_translator_after_activation', $this);
    }
    
    /**
     * Plugin deactivation handler
     * 
     * @since 0.0.1
     */
    public function plugin_deactivated() {
        // Clean up transients but preserve settings
        $this->cleanup_transients();
        
        do_action('nexus_ai_translator_after_deactivation', $this);
    }
    
    /**
     * Set default plugin options
     * 
     * @since 0.0.1
     */
    private function set_default_options() {
        $defaults = array(
            'nexus_ai_translator_settings' => array(
                'api_key'             => '',
                'source_language'     => 'en',
                'target_languages'    => array('fr', 'es', 'de'),
                'auto_translate'      => false,
                'translation_quality' => 'standard',
            ),
        );
        
        foreach ($defaults as $option => $value) {
            if (!get_option($option)) {
                add_option($option, $value);
            }
        }
    }
    
    /**
     * Clean up plugin transients
     * 
     * @since 0.0.1
     */
    private function cleanup_transients() {
        $transients = array(
            'nexus_ai_translator_api_status',
            'nexus_ai_translator_rate_limit',
            'nexus_ai_translator_error_count',
        );
        
        foreach ($transients as $transient) {
            delete_transient($transient);
        }
    }
}