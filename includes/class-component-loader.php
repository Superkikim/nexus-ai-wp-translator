<?php
/**
 * File: class-component-loader.php
 * Location: /includes/class-component-loader.php
 * 
 * Component Loader Class
 * Responsible for: Loading components, initializing instances, module registry
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
 * Component loader class for managing plugin components
 * 
 * Handles the loading, initialization, and management of all plugin components
 * including future extensibility through module registration.
 * 
 * @since 0.0.1
 */
class Component_Loader {
    
    /**
     * Component registry for loaded modules
     * 
     * @since 0.0.1
     * @var array
     */
    private $components = array();
    
    /**
     * Module registry for extensibility
     * 
     * @since 0.0.1
     * @var array
     */
    private $modules = array();
    
    /**
     * Constructor
     * 
     * @since 0.0.1
     */
    public function __construct() {
        // Load dependencies first
        $this->load_dependencies();
    }
    
    /**
     * Load plugin dependencies
     * 
     * @since 0.0.1
     */
    private function load_dependencies() {
        // Load abstract classes first
        $this->load_file('abstracts/abstract-module.php');
        $this->load_file('abstracts/abstract-handler.php');
        
        // Load interfaces
        $this->load_file('interfaces/interface-analytics.php');
        $this->load_file('interfaces/interface-cache.php');
        $this->load_file('interfaces/interface-queue.php');
    }
    
    /**
     * Load all core components
     * 
     * @since 0.0.1
     */
    public function load_all() {
        // Component loading order is important
        $components = array(
            'languages'  => 'class-languages.php',
            'api'        => 'class-api.php',
            'admin'      => 'class-admin.php',
            'translator' => 'class-translator.php',
            'metabox'    => 'class-metabox.php',
            'ajax'       => 'class-ajax.php',
        );
        
        foreach ($components as $component => $file) {
            if ($this->load_file($file)) {
                $this->register_component($component, $file);
            }
        }
        
        // Allow modules to register additional components
        do_action('nexus_ai_translator_load_components', $this);
    }
    
    /**
     * Initialize all loaded components
     * 
     * @since 0.0.1
     */
    public function init_all() {
        foreach ($this->components as $component => $data) {
            $this->init_component($component, $data);
        }
        
        // Fire components initialized hook
        do_action('nexus_ai_translator_components_initialized', $this->components);
    }
    
    /**
     * Load a single file from includes directory
     * 
     * @since 0.0.1
     * @param string $file File path relative to includes directory
     * @return bool True on success, false on failure
     */
    private function load_file($file) {
        $file_path = NEXUS_AI_TRANSLATOR_INCLUDES_DIR . $file;
        
        if (!file_exists($file_path)) {
            return false;
        }
        
        try {
            require_once $file_path;
            return true;
        } catch (Exception $e) {
            $this->log_error('Failed to load file: ' . $file, $e);
            return false;
        }
    }
    
    /**
     * Initialize a single component
     * 
     * @since 0.0.1
     * @param string $component Component name
     * @param array $data Component data
     */
    private function init_component($component, $data) {
        // Convert component name to class name
        $class_name = $this->get_component_class_name($component);
        
        if (!class_exists($class_name)) {
            return;
        }
        
        try {
            // Create component instance
            $instance = new $class_name();
            
            // Store instance in components array
            $this->components[$component]['instance'] = $instance;
            
            // Initialize if method exists
            if (method_exists($instance, 'init')) {
                $instance->init();
            }
            
        } catch (Exception $e) {
            $this->log_error('Failed to initialize component: ' . $component, $e);
        }
    }
    
    /**
     * Get component class name from component key
     * 
     * @since 0.0.1
     * @param string $component Component name
     * @return string Full class name
     */
    private function get_component_class_name($component) {
        // Convert snake_case to PascalCase
        $class_name = str_replace('_', '', ucwords($component, '_'));
        return 'Nexus\\Translator\\' . $class_name;
    }
    
    /**
     * Register a component in the registry
     * 
     * @since 0.0.1
     * @param string $component Component name
     * @param string $file Component file
     */
    private function register_component($component, $file) {
        $this->components[$component] = array(
            'file'     => $file,
            'loaded'   => true,
            'instance' => null,
        );
    }
    
    /**
     * Register a module for extensibility
     * 
     * @since 0.0.1
     * @param string $module_name Module name
     * @param array $module_data Module configuration
     * @return bool True on success, false on failure
     */
    public function register_module($module_name, $module_data = array()) {
        if (isset($this->modules[$module_name])) {
            return false; // Module already registered
        }
        
        $defaults = array(
            'version'      => '1.0.0',
            'dependencies' => array(),
            'priority'     => 10,
            'callback'     => null,
        );
        
        $this->modules[$module_name] = wp_parse_args($module_data, $defaults);
        
        do_action('nexus_ai_translator_module_registered', $module_name, $this->modules[$module_name]);
        
        return true;
    }
    
    /**
     * Get a component instance
     * 
     * @since 0.0.1
     * @param string $component Component name
     * @return object|null Component instance or null if not found
     */
    public function get_component($component) {
        if (!isset($this->components[$component]['instance'])) {
            return null;
        }
        
        return $this->components[$component]['instance'];
    }
    
    /**
     * Get all loaded components
     * 
     * @since 0.0.1
     * @return array Array of loaded components
     */
    public function get_all_components() {
        return $this->components;
    }
    
    /**
     * Get all registered modules
     * 
     * @since 0.0.1
     * @return array Array of registered modules
     */
    public function get_all_modules() {
        return $this->modules;
    }
    
    /**
     * Check if component is loaded
     * 
     * @since 0.0.1
     * @param string $component Component name
     * @return bool True if loaded, false otherwise
     */
    public function is_component_loaded($component) {
        return isset($this->components[$component]) && $this->components[$component]['loaded'];
    }
    
    /**
     * Check if component is initialized
     * 
     * @since 0.0.1
     * @param string $component Component name
     * @return bool True if initialized, false otherwise
     */
    public function is_component_initialized($component) {
        return isset($this->components[$component]['instance']) && 
               $this->components[$component]['instance'] !== null;
    }
    
    /**
     * Load external module from file
     * 
     * @since 0.0.1
     * @param string $module_file Full path to module file
     * @param string $module_name Module name for registration
     * @return bool True on success, false on failure
     */
    public function load_external_module($module_file, $module_name) {
        if (!file_exists($module_file)) {
            return false;
        }
        
        try {
            require_once $module_file;
            
            // Register as external module
            $this->register_module($module_name, array(
                'type'    => 'external',
                'file'    => $module_file,
                'loaded'  => true,
            ));
            
            return true;
            
        } catch (Exception $e) {
            $this->log_error('Failed to load external module: ' . $module_name, $e);
            return false;
        }
    }
    
    /**
     * Unload a component
     * 
     * @since 0.0.1
     * @param string $component Component name
     * @return bool True on success, false on failure
     */
    public function unload_component($component) {
        if (!isset($this->components[$component])) {
            return false;
        }
        
        // Call cleanup method if exists
        if (isset($this->components[$component]['instance'])) {
            $instance = $this->components[$component]['instance'];
            if (method_exists($instance, 'cleanup')) {
                $instance->cleanup();
            }
        }
        
        // Remove from registry
        unset($this->components[$component]);
        
        do_action('nexus_ai_translator_component_unloaded', $component);
        
        return true;
    }
    
    /**
     * Get component loading statistics
     * 
     * @since 0.0.1
     * @return array Loading statistics
     */
    public function get_loading_stats() {
        $total = count($this->components);
        $initialized = 0;
        $failed = 0;
        
        foreach ($this->components as $component => $data) {
            if (isset($data['instance']) && $data['instance'] !== null) {
                $initialized++;
            } else {
                $failed++;
            }
        }
        
        return array(
            'total'       => $total,
            'initialized' => $initialized,
            'failed'      => $failed,
            'success_rate' => $total > 0 ? round(($initialized / $total) * 100, 2) : 0,
        );
    }
    
    /**
     * Log error message
     * 
     * @since 0.0.1
     * @param string $message Error message
     * @param Exception $exception Optional exception object
     */
    private function log_error($message, $exception = null) {
        $log_message = '[Nexus AI Translator - Component Loader] ' . $message;
        
        if ($exception) {
            $log_message .= ' | Exception: ' . $exception->getMessage();
        }
        
        error_log($log_message);
        
        // Increment error count for emergency mode detection
        $error_count = get_transient('nexus_ai_translator_error_count') ?: 0;
        set_transient('nexus_ai_translator_error_count', $error_count + 1, HOUR_IN_SECONDS);
        
        // Fire error hook for analytics
        do_action('nexus_ai_translator_error', $message, $exception);
    }
}