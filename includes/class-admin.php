<?php
/**
 * File: class-admin.php
 * Location: /includes/class-admin.php
 * 
 * Core Admin Interface Class
 * Coordinates admin functionality and delegates to helper classes.
 */

namespace Nexus\Translator;

use Nexus\Translator\Abstracts\Abstract_Module;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Core admin interface class
 * 
 * Coordinates admin functionality and delegates to helper classes.
 * Keeps the main class focused on orchestration.
 * 
 * @since 0.0.1
 */
class Admin extends Abstract_Module {
    
    /**
     * Settings page hook suffix
     * 
     * @since 0.0.1
     * @var string
     */
    private $page_hook;
    
    /**
     * API instance for testing
     * 
     * @since 0.0.1
     * @var Api
     */
    private $api;
    
    /**
     * Languages instance for validation
     * 
     * @since 0.0.1
     * @var Languages
     */
    private $languages;
    
    /**
     * Settings handler instance
     * 
     * @since 0.0.1
     * @var Admin_Settings
     */
    private $settings_handler;
    
    /**
     * Status handler instance
     * 
     * @since 0.0.1
     * @var Admin_Status
     */
    private $status_handler;
    
    /**
     * AJAX handler instance
     * 
     * @since 0.0.1
     * @var Admin_Ajax
     */
    private $ajax_handler;
    
    /**
     * Get module name/identifier
     * 
     * @since 0.0.1
     * @return string Module name
     */
    protected function get_module_name() {
        return 'admin';
    }
    
    /**
     * Module-specific initialization
     * 
     * @since 0.0.1
     * @return void
     */
    protected function module_init() {
        // Get component instances
        $main = \Nexus\Translator\Main::get_instance();
        $this->api = $main->get_component('api');
        $this->languages = $main->get_component('languages');
        
        // Load helper classes
        $this->load_helper_classes();
        
        // Initialize helper components
        $this->init_helper_components();
    }
    
    /**
     * Register WordPress hooks
     * 
     * @since 0.0.1
     * @return void
     */
    protected function register_hooks() {
        // Admin menu
        $this->add_hook('admin_menu', array($this, 'add_admin_menu'));
        
        // Let helper classes register their own hooks
        if ($this->settings_handler) {
            $this->settings_handler->register_hooks();
        }
        
        if ($this->status_handler) {
            $this->status_handler->register_hooks();
        }
        
        if ($this->ajax_handler) {
            $this->ajax_handler->register_hooks();
        }
    }
    
    /**
     * Load helper class files
     * 
     * @since 0.0.1
     * @return void
     */
    private function load_helper_classes() {
        $helper_files = array(
            'class-admin-settings.php',
            'class-admin-status.php',
            'class-admin-ajax.php',
        );
        
        foreach ($helper_files as $file) {
            $file_path = NEXUS_AI_TRANSLATOR_INCLUDES_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }
    
    /**
     * Initialize helper components
     * 
     * @since 0.0.1
     * @return void
     */
    private function init_helper_components() {
        // Initialize settings handler
        if (class_exists('Nexus\\Translator\\Admin_Settings')) {
            $this->settings_handler = new Admin_Settings($this);
        }
        
        // Initialize status handler
        if (class_exists('Nexus\\Translator\\Admin_Status')) {
            $this->status_handler = new Admin_Status($this);
        }
        
        // Initialize AJAX handler
        if (class_exists('Nexus\\Translator\\Admin_Ajax')) {
            $this->ajax_handler = new Admin_Ajax($this);
        }
    }
    
    /**
     * Add admin menu
     * 
     * @since 0.0.1
     * @return void
     */
    public function add_admin_menu() {
        $this->page_hook = add_options_page(
            __('Nexus AI Translator Settings', 'nexus-ai-wp-translator'),
            __('AI Translator', 'nexus-ai-wp-translator'),
            'manage_options',
            'nexus-ai-translator',
            array($this, 'render_settings_page')
        );
        
        // Add help tab when page loads
        $this->add_hook('load-' . $this->page_hook, array($this, 'add_help_tabs'));
        
        // Enqueue assets for this page
        $this->add_hook('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Render settings page
     * 
     * @since 0.0.1
     * @return void
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'nexus-ai-wp-translator'));
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="nexus-admin-header">
                <div class="nexus-status-cards">
                    <?php 
                    if ($this->status_handler) {
                        $this->status_handler->render_status_cards();
                    }
                    ?>
                </div>
            </div>
            
            <div class="nexus-admin-content">
                <div class="nexus-settings-form">
                    <?php 
                    if ($this->settings_handler) {
                        $this->settings_handler->render_settings_form();
                    }
                    ?>
                </div>
                
                <div class="nexus-sidebar">
                    <?php $this->render_sidebar(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render sidebar
     * 
     * @since 0.0.1
     * @return void
     */
    private function render_sidebar() {
        ?>
        <div class="nexus-sidebar-section">
            <h3><?php esc_html_e('Quick Actions', 'nexus-ai-wp-translator'); ?></h3>
            <p>
                <button type="button" class="button" id="nexus-test-connection">
                    <?php esc_html_e('Test API Connection', 'nexus-ai-wp-translator'); ?>
                </button>
            </p>
            <p>
                <button type="button" class="button" id="nexus-refresh-status">
                    <?php esc_html_e('Refresh System Status', 'nexus-ai-wp-translator'); ?>
                </button>
            </p>
            <p>
                <button type="button" class="button button-secondary" id="nexus-reset-settings">
                    <?php esc_html_e('Reset to Defaults', 'nexus-ai-wp-translator'); ?>
                </button>
            </p>
        </div>
        
        <div class="nexus-sidebar-section">
            <h3><?php esc_html_e('System Information', 'nexus-ai-wp-translator'); ?></h3>
            <?php 
            if ($this->status_handler) {
                $this->status_handler->render_system_info();
            }
            ?>
        </div>
        
        <div class="nexus-sidebar-section">
            <h3><?php esc_html_e('Support', 'nexus-ai-wp-translator'); ?></h3>
            <p><?php esc_html_e('Need help? Check out our documentation or contact support.', 'nexus-ai-wp-translator'); ?></p>
            <p>
                <a href="https://github.com/superkikim/nexus-ai-wp-translator" target="_blank" class="button button-secondary">
                    <?php esc_html_e('Documentation', 'nexus-ai-wp-translator'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Enqueue admin assets
     * 
     * @since 0.0.1
     * @param string $hook_suffix Current page hook suffix
     * @return void
     */
    public function enqueue_admin_assets($hook_suffix) {
        // Only load on our settings page
        if ($hook_suffix !== $this->page_hook) {
            return;
        }
        
        // Enqueue admin CSS
        wp_enqueue_style(
            'nexus-ai-translator-admin',
            NEXUS_AI_TRANSLATOR_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            NEXUS_AI_TRANSLATOR_VERSION
        );
        
        // Enqueue admin JavaScript
        wp_enqueue_script(
            'nexus-ai-translator-admin',
            NEXUS_AI_TRANSLATOR_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery'),
            NEXUS_AI_TRANSLATOR_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('nexus-ai-translator-admin', 'nexusAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nexus_admin_ajax'),
            'strings' => array(
                'testing' => __('Testing connection...', 'nexus-ai-wp-translator'),
                'connected' => __('Connected successfully!', 'nexus-ai-wp-translator'),
                'failed' => __('Connection failed', 'nexus-ai-wp-translator'),
                'refreshing' => __('Refreshing status...', 'nexus-ai-wp-translator'),
                'resetting' => __('Resetting settings...', 'nexus-ai-wp-translator'),
                'confirmReset' => __('Are you sure you want to reset all settings to defaults?', 'nexus-ai-wp-translator'),
                'show' => __('Show', 'nexus-ai-wp-translator'),
                'hide' => __('Hide', 'nexus-ai-wp-translator'),
            ),
        ));
    }
    
    /**
     * Add help tabs
     * 
     * @since 0.0.1
     * @return void
     */
    public function add_help_tabs() {
        $screen = get_current_screen();
        
        // API Configuration tab
        $screen->add_help_tab(array(
            'id' => 'nexus-api-help',
            'title' => __('API Configuration', 'nexus-ai-wp-translator'),
            'content' => $this->get_api_help_content(),
        ));
        
        // Language Configuration tab
        $screen->add_help_tab(array(
            'id' => 'nexus-languages-help',
            'title' => __('Languages', 'nexus-ai-wp-translator'),
            'content' => $this->get_languages_help_content(),
        ));
        
        // Troubleshooting tab
        $screen->add_help_tab(array(
            'id' => 'nexus-troubleshooting-help',
            'title' => __('Troubleshooting', 'nexus-ai-wp-translator'),
            'content' => $this->get_troubleshooting_help_content(),
        ));
        
        // Set help sidebar
        $screen->set_help_sidebar($this->get_help_sidebar_content());
    }
    
    /**
     * Get API help content
     * 
     * @since 0.0.1
     * @return string Help content
     */
    private function get_api_help_content() {
        return '<h4>' . __('Setting up Claude AI API', 'nexus-ai-wp-translator') . '</h4>' .
               '<p>' . __('To use this plugin, you need a Claude AI API key:', 'nexus-ai-wp-translator') . '</p>' .
               '<ol>' .
               '<li>' . sprintf(__('Visit %s and create an account', 'nexus-ai-wp-translator'), '<a href="https://console.anthropic.com/" target="_blank">Claude AI Console</a>') . '</li>' .
               '<li>' . __('Navigate to API Keys section', 'nexus-ai-wp-translator') . '</li>' .
               '<li>' . __('Create a new API key', 'nexus-ai-wp-translator') . '</li>' .
               '<li>' . __('Copy the key and paste it in the API Key field above', 'nexus-ai-wp-translator') . '</li>' .
               '</ol>';
    }
    
    /**
     * Get languages help content
     * 
     * @since 0.0.1
     * @return string Help content
     */
    private function get_languages_help_content() {
        return '<h4>' . __('Language Configuration', 'nexus-ai-wp-translator') . '</h4>' .
               '<p>' . __('Configure which languages to translate your content:', 'nexus-ai-wp-translator') . '</p>' .
               '<ul>' .
               '<li><strong>' . __('Source Language:', 'nexus-ai-wp-translator') . '</strong> ' . __('The primary language of your content', 'nexus-ai-wp-translator') . '</li>' .
               '<li><strong>' . __('Target Languages:', 'nexus-ai-wp-translator') . '</strong> ' . __('Languages to translate your content into', 'nexus-ai-wp-translator') . '</li>' .
               '</ul>';
    }
    
    /**
     * Get troubleshooting help content
     * 
     * @since 0.0.1
     * @return string Help content
     */
    private function get_troubleshooting_help_content() {
        return '<h4>' . __('Common Issues', 'nexus-ai-wp-translator') . '</h4>' .
               '<ul>' .
               '<li><strong>' . __('API Connection Failed:', 'nexus-ai-wp-translator') . '</strong> ' . __('Check your API key and internet connection', 'nexus-ai-wp-translator') . '</li>' .
               '<li><strong>' . __('Translation Not Working:', 'nexus-ai-wp-translator') . '</strong> ' . __('Verify language pair is supported', 'nexus-ai-wp-translator') . '</li>' .
               '<li><strong>' . __('Emergency Mode Active:', 'nexus-ai-wp-translator') . '</strong> ' . __('Reset emergency mode or check API status', 'nexus-ai-wp-translator') . '</li>' .
               '</ul>';
    }
    
    /**
     * Get help sidebar content
     * 
     * @since 0.0.1
     * @return string Sidebar content
     */
    private function get_help_sidebar_content() {
        return '<p><strong>' . __('For more information:', 'nexus-ai-wp-translator') . '</strong></p>' .
               '<p><a href="https://github.com/superkikim/nexus-ai-wp-translator" target="_blank">' . __('Plugin Documentation', 'nexus-ai-wp-translator') . '</a></p>' .
               '<p><a href="https://console.anthropic.com/" target="_blank">' . __('Claude AI Console', 'nexus-ai-wp-translator') . '</a></p>';
    }
    
    /**
     * Get API instance
     * 
     * @since 0.0.1
     * @return Api|null API instance
     */
    public function get_api() {
        return $this->api;
    }
    
    /**
     * Get languages instance
     * 
     * @since 0.0.1
     * @return Languages|null Languages instance
     */
    public function get_languages() {
        return $this->languages;
    }
    
    /**
     * Get settings handler instance
     * 
     * @since 0.0.1
     * @return Admin_Settings|null Settings handler instance
     */
    public function get_settings_handler() {
        return $this->settings_handler;
    }
    
    /**
     * Get status handler instance
     * 
     * @since 0.0.1
     * @return Admin_Status|null Status handler instance
     */
    public function get_status_handler() {
        return $this->status_handler;
    }
    
    /**
     * Get AJAX handler instance
     * 
     * @since 0.0.1
     * @return Admin_Ajax|null AJAX handler instance
     */
    public function get_ajax_handler() {
        return $this->ajax_handler;
    }
    
    /**
     * Get settings page hook
     * 
     * @since 0.0.1
     * @return string Page hook
     */
    public function get_page_hook() {
        return $this->page_hook;
    }
}