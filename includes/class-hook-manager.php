<?php
/**
 * File: class-hook-manager.php
 * Location: /includes/class-hook-manager.php
 * 
 * Hook Manager Class
 * Responsible for: WordPress hook registration, component hook coordination
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
 * Hook manager class for managing WordPress hooks
 * 
 * Centralizes all hook registration for the plugin and coordinates
 * hook registration between different components.
 * 
 * @since 0.0.1
 */
class Hook_Manager {
    
    /**
     * Component loader instance
     * 
     * @since 0.0.1
     * @var Component_Loader
     */
    private $component_loader;
    
    /**
     * Registered hooks registry
     * 
     * @since 0.0.1
     * @var array
     */
    private $hooks_registry = array();
    
    /**
     * Constructor
     * 
     * @since 0.0.1
     * @param Component_Loader $component_loader Component loader instance
     */
    public function __construct($component_loader) {
        $this->component_loader = $component_loader;
    }
    
    /**
     * Register all WordPress hooks
     * 
     * @since 0.0.1
     */
    public function register_all() {
        // Register core WordPress hooks
        $this->register_core_hooks();
        
        // Register plugin-specific hooks
        $this->register_plugin_hooks();
        
        // Register component hooks
        $this->register_component_hooks();
        
        // Allow external registration
        do_action('nexus_ai_translator_register_hooks', $this);
    }
    
    /**
     * Register core WordPress hooks
     * 
     * @since 0.0.1
     */
    private function register_core_hooks() {
        $main_instance = \Nexus\Translator\Main::get_instance();
        
        // WordPress core hooks
        add_action('wp_loaded', array($this, 'wp_loaded'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('init', array($this, 'init'));
        
        // Plugin lifecycle hooks
        add_action('nexus_ai_translator_activated', array($main_instance, 'plugin_activated'));
        add_action('nexus_ai_translator_deactivated', array($main_instance, 'plugin_deactivated'));
        
        $this->register_hook('core', 'wp_loaded', array($this, 'wp_loaded'));
        $this->register_hook('core', 'admin_init', array($this, 'admin_init'));
        $this->register_hook('core', 'init', array($this, 'init'));
    }
    
    /**
     * Register plugin-specific hooks
     * 
     * @since 0.0.1
     */
    private function register_plugin_hooks() {
        // Translation hooks (for future use)
        $this->register_hook('plugin', 'nexus_before_translate', '__return_true');
        $this->register_hook('plugin', 'nexus_after_translate', '__return_true');
        
        // Analytics hooks (for future use)
        $this->register_hook('plugin', 'nexus_analytics_event', '__return_true');
        
        // Bulk operations hooks (for future use)
        $this->register_hook('plugin', 'nexus_bulk_start', '__return_true');
        $this->register_hook('plugin', 'nexus_bulk_complete', '__return_true');
        
        // Emergency system hooks (for future use)
        $this->register_hook('plugin', 'nexus_emergency_trigger', '__return_true');
        $this->register_hook('plugin', 'nexus_emergency_reset', '__return_true');
        
        // Cache integration hooks (for future use)
        $this->register_hook('plugin', 'nexus_cache_set', '__return_true');
        $this->register_hook('plugin', 'nexus_cache_get', '__return_true');
        $this->register_hook('plugin', 'nexus_cache_clear', '__return_true');
    }
    
    /**
     * Register component-specific hooks
     * 
     * @since 0.0.1
     */
    private function register_component_hooks() {
        $components = $this->component_loader->get_all_components();
        
        foreach ($components as $component_name => $component_data) {
            $this->register_component_hook($component_name);
        }
    }
    
    /**
     * Register hooks for a specific component
     * 
     * @since 0.0.1
     * @param string $component_name Component name
     */
    private function register_component_hook($component_name) {
        $component = $this->component_loader->get_component($component_name);
        
        if (!$component || !method_exists($component, 'register_hooks')) {
            return;
        }
        
        try {
            $component->register_hooks();
            $this->register_hook('component', $component_name . '_hooks_registered', $component_name);
        } catch (Exception $e) {
            error_log('[Nexus AI Translator - Hook Manager] Failed to register hooks for component: ' . $component_name . ' | Exception: ' . $e->getMessage());
        }
    }
    
    /**
     * Register a hook in the registry
     * 
     * @since 0.0.1
     * @param string $type Hook type (core, plugin, component)
     * @param string $hook Hook name
     * @param mixed $callback Callback function
     * @param int $priority Hook priority
     */
    private function register_hook($type, $hook, $callback, $priority = 10) {
        if (!isset($this->hooks_registry[$type])) {
            $this->hooks_registry[$type] = array();
        }
        
        $this->hooks_registry[$type][] = array(
            'hook'     => $hook,
            'callback' => $callback,
            'priority' => $priority,
            'registered_at' => current_time('mysql'),
        );
    }
    
    /**
     * WordPress init hook handler
     * 
     * @since 0.0.1
     */
    public function init() {
        // Check if main instance is initialized
        $main = \Nexus\Translator\Main::get_instance();
        if (!$main->is_initialized()) {
            return;
        }
        
        do_action('nexus_ai_translator_init', $main);
    }
    
    /**
     * WordPress loaded hook handler
     * 
     * @since 0.0.1
     */
    public function wp_loaded() {
        // Check if main instance is initialized
        $main = \Nexus\Translator\Main::get_instance();
        if (!$main->is_initialized()) {
            return;
        }
        
        do_action('nexus_ai_translator_wp_loaded', $main);
    }
    
    /**
     * WordPress admin init hook handler
     * 
     * @since 0.0.1
     */
    public function admin_init() {
        // Check if main instance is initialized
        $main = \Nexus\Translator\Main::get_instance();
        if (!$main->is_initialized()) {
            return;
        }
        
        do_action('nexus_ai_translator_admin_init', $main);
    }
    
    /**
     * Get hooks registry
     * 
     * @since 0.0.1
     * @return array Hooks registry
     */
    public function get_hooks_registry() {
        return $this->hooks_registry;
    }
    
    /**
     * Get hooks by type
     * 
     * @since 0.0.1
     * @param string $type Hook type
     * @return array Hooks of specified type
     */
    public function get_hooks_by_type($type) {
        return isset($this->hooks_registry[$type]) ? $this->hooks_registry[$type] : array();
    }
    
    /**
     * Get hook registration statistics
     * 
     * @since 0.0.1
     * @return array Hook statistics
     */
    public function get_hook_stats() {
        $stats = array(
            'total' => 0,
            'by_type' => array(),
        );
        
        foreach ($this->hooks_registry as $type => $hooks) {
            $count = count($hooks);
            $stats['by_type'][$type] = $count;
            $stats['total'] += $count;
        }
        
        return $stats;
    }
    
    /**
     * Remove all hooks for a component
     * 
     * @since 0.0.1
     * @param string $component_name Component name
     * @return bool True on success, false on failure
     */
    public function remove_component_hooks($component_name) {
        $component = $this->component_loader->get_component($component_name);
        
        if (!$component || !method_exists($component, 'remove_hooks')) {
            return false;
        }
        
        try {
            $component->remove_hooks();
            
            // Remove from registry
            if (isset($this->hooks_registry['component'])) {
                foreach ($this->hooks_registry['component'] as $key => $hook_data) {
                    if (strpos($hook_data['hook'], $component_name) === 0) {
                        unset($this->hooks_registry['component'][$key]);
                    }
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log('[Nexus AI Translator - Hook Manager] Failed to remove hooks for component: ' . $component_name . ' | Exception: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add custom hook with registration tracking
     * 
     * @since 0.0.1
     * @param string $hook_name Hook name
     * @param callable $callback Callback function
     * @param int $priority Hook priority
     * @param int $accepted_args Number of accepted arguments
     * @return bool True on success, false on failure
     */
    public function add_custom_hook($hook_name, $callback, $priority = 10, $accepted_args = 1) {
        if (!is_callable($callback)) {
            return false;
        }
        
        add_action($hook_name, $callback, $priority, $accepted_args);
        
        $this->register_hook('custom', $hook_name, $callback, $priority);
        
        return true;
    }
    
    /**
     * Add custom filter with registration tracking
     * 
     * @since 0.0.1
     * @param string $filter_name Filter name
     * @param callable $callback Callback function
     * @param int $priority Filter priority
     * @param int $accepted_args Number of accepted arguments
     * @return bool True on success, false on failure
     */
    public function add_custom_filter($filter_name, $callback, $priority = 10, $accepted_args = 1) {
        if (!is_callable($callback)) {
            return false;
        }
        
        add_filter($filter_name, $callback, $priority, $accepted_args);
        
        $this->register_hook('filter', $filter_name, $callback, $priority);
        
        return true;
    }
}