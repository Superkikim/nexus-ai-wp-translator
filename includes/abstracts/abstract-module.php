<?php
/**
 * File: abstract-module.php
 * Location: /includes/abstracts/abstract-module.php
 * 
 * Abstract base class for all plugin modules
 * Provides common functionality and enforces consistent architecture
 */

namespace Nexus\Translator\Abstracts;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract Module Class
 * 
 * Base class that all plugin modules should extend
 * Provides common functionality like initialization, configuration, 
 * error handling, and hook management
 * 
 * @since 0.0.1
 * @package Nexus\Translator\Abstracts
 */
abstract class Abstract_Module {
    
    /**
     * Module version
     * 
     * @var string
     */
    protected $version = '0.0.1';
    
    /**
     * Module name/identifier
     * 
     * @var string
     */
    protected $module_name = '';
    
    /**
     * Module dependencies
     * 
     * @var array
     */
    protected $dependencies = array();
    
    /**
     * Module configuration
     * 
     * @var array
     */
    protected $config = array();
    
    /**
     * Module status
     * 
     * @var bool
     */
    protected $is_initialized = false;
    
    /**
     * Error count for this module
     * 
     * @var int
     */
    protected $error_count = 0;
    
    /**
     * Module options
     * 
     * @var array
     */
    protected $options = array();
    
    /**
     * Hooks registered by this module
     * 
     * @var array
     */
    protected $registered_hooks = array();
    
    /**
     * Constructor
     * 
     * @param array $config Module configuration
     */
    public function __construct($config = array()) {
        $this->config = wp_parse_args($config, $this->get_default_config());
        $this->module_name = $this->get_module_name();
        
        // Load module options
        $this->load_options();
        
        // Initialize if dependencies are met
        if ($this->check_dependencies()) {
            $this->init();
        }
    }
    
    /**
     * Initialize the module
     * Called automatically if dependencies are met
     * 
     * @return bool True on success, false on failure
     */
    public function init() {
        if ($this->is_initialized) {
            return true;
        }
        
        try {
            // Module-specific initialization
            $this->module_init();
            
            // Register hooks
            $this->register_hooks();
            
            // Load text domain if needed
            $this->load_textdomain();
            
            // Mark as initialized
            $this->is_initialized = true;
            
            // Fire initialization hook
            do_action('nexus_ai_translator_module_initialized', $this->module_name, $this);
            
            return true;
            
        } catch (Exception $e) {
            $this->handle_error('initialization_failed', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get module name/identifier
     * Must be implemented by child classes
     * 
     * @return string Module name
     */
    abstract protected function get_module_name();
    
    /**
     * Module-specific initialization
     * Should be implemented by child classes
     * 
     * @return void
     */
    abstract protected function module_init();
    
    /**
     * Register WordPress hooks
     * Should be implemented by child classes
     * 
     * @return void
     */
    abstract protected function register_hooks();
    
    /**
     * Get default configuration
     * Can be overridden by child classes
     * 
     * @return array Default configuration
     */
    protected function get_default_config() {
        return array(
            'enabled' => true,
            'debug' => false,
            'auto_init' => true,
            'priority' => 10
        );
    }
    
    /**
     * Check if module dependencies are met
     * 
     * @return bool True if dependencies are met
     */
    protected function check_dependencies() {
        foreach ($this->dependencies as $dependency) {
            if (!$this->is_dependency_available($dependency)) {
                $this->handle_error(
                    'dependency_missing',
                    sprintf(
                        /* translators: 1: Module name, 2: Dependency name */
                        __('Module %1$s requires %2$s but it is not available.', 'nexus-ai-wp-translator'),
                        $this->module_name,
                        $dependency
                    )
                );
                return false;
            }
        }
        return true;
    }
    
    /**
     * Check if a specific dependency is available
     * 
     * @param string $dependency Dependency name
     * @return bool True if available
     */
    protected function is_dependency_available($dependency) {
        // Check for class existence
        if (class_exists($dependency)) {
            return true;
        }
        
        // Check for function existence
        if (function_exists($dependency)) {
            return true;
        }
        
        // Check for WordPress plugin
        if (is_plugin_active($dependency)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Load module options from database
     * 
     * @return void
     */
    protected function load_options() {
        $option_name = 'nexus_ai_translator_' . $this->module_name . '_options';
        $this->options = get_option($option_name, array());
    }
    
    /**
     * Save module options to database
     * 
     * @return bool True on success
     */
    protected function save_options() {
        $option_name = 'nexus_ai_translator_' . $this->module_name . '_options';
        return update_option($option_name, $this->options);
    }
    
    /**
     * Get module option value
     * 
     * @param string $key Option key
     * @param mixed $default Default value
     * @return mixed Option value
     */
    protected function get_option($key, $default = null) {
        return isset($this->options[$key]) ? $this->options[$key] : $default;
    }
    
    /**
     * Set module option value
     * 
     * @param string $key Option key
     * @param mixed $value Option value
     * @return void
     */
    protected function set_option($key, $value) {
        $this->options[$key] = $value;
    }
    
    /**
     * Handle module errors
     * 
     * @param string $error_code Error code
     * @param string $message Error message
     * @return void
     */
    protected function handle_error($error_code, $message) {
        $this->error_count++;
        
        // Log error
        error_log(sprintf(
            '[Nexus AI Translator] Module %s Error (%s): %s',
            $this->module_name,
            $error_code,
            $message
        ));
        
        // Fire error hook
        do_action('nexus_ai_translator_module_error', $this->module_name, $error_code, $message);
        
        // Check for emergency mode trigger
        if ($this->error_count >= 3) {
            do_action('nexus_emergency_trigger', 'module_errors', array(
                'module' => $this->module_name,
                'error_count' => $this->error_count
            ));
        }
    }
    
    /**
     * Load text domain for translations
     * 
     * @return void
     */
    protected function load_textdomain() {
        // Text domain is loaded globally, but modules can override if needed
        // This is here for future module-specific translations
    }
    
    /**
     * Add WordPress hook and track it
     * 
     * @param string $hook Hook name
     * @param callable $callback Callback function
     * @param int $priority Priority
     * @param int $accepted_args Number of accepted arguments
     * @return void
     */
    protected function add_hook($hook, $callback, $priority = 10, $accepted_args = 1) {
        add_action($hook, $callback, $priority, $accepted_args);
        
        $this->registered_hooks[] = array(
            'hook' => $hook,
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $accepted_args,
            'type' => 'action'
        );
    }
    
    /**
     * Add WordPress filter and track it
     * 
     * @param string $hook Filter name
     * @param callable $callback Callback function
     * @param int $priority Priority
     * @param int $accepted_args Number of accepted arguments
     * @return void
     */
    protected function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        add_filter($hook, $callback, $priority, $accepted_args);
        
        $this->registered_hooks[] = array(
            'hook' => $hook,
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $accepted_args,
            'type' => 'filter'
        );
    }
    
    /**
     * Get module status information
     * 
     * @return array Status information
     */
    public function get_status() {
        return array(
            'name' => $this->module_name,
            'version' => $this->version,
            'initialized' => $this->is_initialized,
            'error_count' => $this->error_count,
            'dependencies_met' => $this->check_dependencies(),
            'hook_count' => count($this->registered_hooks),
            'config' => $this->config
        );
    }
    
    /**
     * Clean up module resources
     * Called during plugin deactivation
     * 
     * @return void
     */
    public function cleanup() {
        // Remove registered hooks
        foreach ($this->registered_hooks as $hook_info) {
            if ($hook_info['type'] === 'action') {
                remove_action($hook_info['hook'], $hook_info['callback'], $hook_info['priority']);
            } else {
                remove_filter($hook_info['hook'], $hook_info['callback'], $hook_info['priority']);
            }
        }
        
        // Clear hooks array
        $this->registered_hooks = array();
        
        // Fire cleanup hook
        do_action('nexus_ai_translator_module_cleanup', $this->module_name, $this);
        
        // Module-specific cleanup
        $this->module_cleanup();
    }
    
    /**
     * Module-specific cleanup
     * Can be overridden by child classes
     * 
     * @return void
     */
    protected function module_cleanup() {
        // Override in child classes if needed
    }
    
    /**
     * Check if module is enabled
     * 
     * @return bool True if enabled
     */
    public function is_enabled() {
        return $this->config['enabled'] && $this->is_initialized;
    }
    
    /**
     * Get module configuration
     * 
     * @return array Configuration
     */
    public function get_config() {
        return $this->config;
    }
    
    /**
     * Update module configuration
     * 
     * @param array $new_config New configuration
     * @return void
     */
    public function update_config($new_config) {
        $this->config = wp_parse_args($new_config, $this->config);
    }
}