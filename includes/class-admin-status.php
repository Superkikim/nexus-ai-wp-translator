<?php
/**
 * File: class-admin-status.php
 * Location: /includes/class-admin-status.php
 * 
 * Admin Status Handler
 * Handles system monitoring, status cards, and health checks.
 */

namespace Nexus\Translator;

class Admin_Status {
    
    private $admin;
    private $system_status = array();
    
    public function __construct($admin) {
        $this->admin = $admin;
        $this->init_system_status();
    }
    
    public function register_hooks() {
        add_action('admin_notices', array($this, 'show_admin_notices'));
    }
    
    private function init_system_status() {
        $this->system_status = array(
            'plugin_version' => NEXUS_AI_TRANSLATOR_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'api_connected' => false,
            'api_key_valid' => false,
            'languages_loaded' => false,
            'emergency_mode' => false,
            'last_check' => '',
        );
    }
    
    public function render_status_cards() {
        $this->update_system_status();
        
        $settings = $this->admin->get_settings_handler() ? $this->admin->get_settings_handler()->get_settings() : array();
        
        $cards = array(
            'api' => array(
                'title' => __('API Status', 'nexus-ai-wp-translator'),
                'status' => $this->system_status['api_connected'] ? 'connected' : 'disconnected',
                'icon' => $this->system_status['api_connected'] ? 'yes-alt' : 'dismiss',
                'description' => $this->system_status['api_connected'] ? 
                    __('Connected to Claude AI', 'nexus-ai-wp-translator') : 
                    __('Not connected', 'nexus-ai-wp-translator'),
            ),
            'languages' => array(
                'title' => __('Languages', 'nexus-ai-wp-translator'),
                'status' => count($settings['target_languages'] ?? array()) > 0 ? 'configured' : 'not_configured',
                'icon' => count($settings['target_languages'] ?? array()) > 0 ? 'translation' : 'warning',
                'description' => sprintf(
                    _n('%d language configured', '%d languages configured', count($settings['target_languages'] ?? array()), 'nexus-ai-wp-translator'),
                    count($settings['target_languages'] ?? array())
                ),
            ),
            'emergency' => array(
                'title' => __('System Status', 'nexus-ai-wp-translator'),
                'status' => $this->system_status['emergency_mode'] ? 'emergency' : 'normal',
                'icon' => $this->system_status['emergency_mode'] ? 'warning' : 'yes-alt',
                'description' => $this->system_status['emergency_mode'] ? 
                    __('Emergency mode active', 'nexus-ai-wp-translator') : 
                    __('All systems normal', 'nexus-ai-wp-translator'),
            ),
        );
        
        foreach ($cards as $card_id => $card) {
            ?>
            <div class="nexus-status-card nexus-status-<?php echo esc_attr($card['status']); ?>">
                <div class="nexus-status-icon">
                    <span class="dashicons dashicons-<?php echo esc_attr($card['icon']); ?>"></span>
                </div>
                <div class="nexus-status-content">
                    <h3><?php echo esc_html($card['title']); ?></h3>
                    <p><?php echo esc_html($card['description']); ?></p>
                </div>
            </div>
            <?php
        }
    }
    
    public function render_system_info() {
        ?>
        <ul class="nexus-system-info">
            <li>
                <strong><?php esc_html_e('Plugin Version:', 'nexus-ai-wp-translator'); ?></strong>
                <?php echo esc_html($this->system_status['plugin_version']); ?>
            </li>
            <li>
                <strong><?php esc_html_e('WordPress Version:', 'nexus-ai-wp-translator'); ?></strong>
                <?php echo esc_html($this->system_status['wordpress_version']); ?>
            </li>
            <li>
                <strong><?php esc_html_e('PHP Version:', 'nexus-ai-wp-translator'); ?></strong>
                <?php echo esc_html($this->system_status['php_version']); ?>
            </li>
        </ul>
        <?php
    }
    
    public function show_admin_notices() {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== $this->admin->get_page_hook()) {
            return;
        }
        
        $settings = $this->admin->get_settings_handler() ? $this->admin->get_settings_handler()->get_settings() : array();
        
        // Check for missing API key
        if (empty($settings['api_key'])) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php esc_html_e('Nexus AI Translator:', 'nexus-ai-wp-translator'); ?></strong>
                    <?php esc_html_e('Please enter your Claude AI API key to start translating.', 'nexus-ai-wp-translator'); ?>
                </p>
            </div>
            <?php
        }
        
        // Check for emergency mode
        if ($this->system_status['emergency_mode']) {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php esc_html_e('Emergency Mode Active:', 'nexus-ai-wp-translator'); ?></strong>
                    <?php esc_html_e('Translation services are temporarily disabled due to API errors.', 'nexus-ai-wp-translator'); ?>
                    <a href="#" id="nexus-reset-emergency" class="button button-small">
                        <?php esc_html_e('Reset Emergency Mode', 'nexus-ai-wp-translator'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }
    
    public function update_system_status() {
        // Check API status
        $api = $this->admin->get_api();
        $settings = $this->admin->get_settings_handler() ? $this->admin->get_settings_handler()->get_settings() : array();
        
        if ($api && !empty($settings['api_key'])) {
            if (method_exists($api, 'get_performance')) {
                $performance = $api->get_performance();
                if ($performance) {
                    $api_status = $performance->get_api_status();
                    $this->system_status['api_connected'] = $api_status['connected'] ?? false;
                    $this->system_status['api_key_valid'] = $api_status['api_key_valid'] ?? false;
                }
            }
        }
        
        // Check languages status
        $languages = $this->admin->get_languages();
        if ($languages) {
            $this->system_status['languages_loaded'] = count($languages->get_supported_languages()) > 0;
        }
        
        // Check emergency mode
        $this->system_status['emergency_mode'] = apply_filters('nexus_emergency_mode_active', false);
        
        // Update last check time
        $this->system_status['last_check'] = current_time('mysql');
        
        // Cache status
        set_transient('nexus_ai_translator_system_status', $this->system_status, HOUR_IN_SECONDS);
    }
    
    public function get_system_status() {
        return $this->system_status;
    }
    
    public function get_api_status() {
        $api = $this->admin->get_api();
        if (!$api) {
            return array(
                'connected' => false,
                'message' => __('API component not available', 'nexus-ai-wp-translator')
            );
        }
        
        if (method_exists($api, 'get_performance')) {
            $performance = $api->get_performance();
            if ($performance) {
                return $performance->get_api_status();
            }
        }
        
        return array(
            'connected' => false,
            'message' => __('Unable to determine API status', 'nexus-ai-wp-translator')
        );
    }
    
    public function get_performance_metrics() {
        $api = $this->admin->get_api();
        if (!$api || !method_exists($api, 'get_performance')) {
            return array();
        }
        
        $performance = $api->get_performance();
        if (!$performance) {
            return array();
        }
        
        return $performance->get_usage_statistics();
    }
    
    public function check_system_health() {
        $health = array(
            'overall' => 'good',
            'issues' => array(),
            'warnings' => array(),
        );
        
        $settings = $this->admin->get_settings_handler() ? $this->admin->get_settings_handler()->get_settings() : array();
        
        // Check API key
        if (empty($settings['api_key'])) {
            $health['issues'][] = __('No API key configured', 'nexus-ai-wp-translator');
            $health['overall'] = 'critical';
        }
        
        // Check API connection
        if (!$this->system_status['api_connected']) {
            $health['issues'][] = __('API not connected', 'nexus-ai-wp-translator');
            $health['overall'] = 'critical';
        }
        
        // Check target languages
        if (empty($settings['target_languages'])) {
            $health['warnings'][] = __('No target languages configured', 'nexus-ai-wp-translator');
            if ($health['overall'] === 'good') {
                $health['overall'] = 'warning';
            }
        }
        
        // Check emergency mode
        if ($this->system_status['emergency_mode']) {
            $health['issues'][] = __('Emergency mode is active', 'nexus-ai-wp-translator');
            $health['overall'] = 'critical';
        }
        
        return $health;
    }
    
    public function get_system_requirements() {
        return array(
            'php_version' => array(
                'required' => NEXUS_AI_TRANSLATOR_MIN_PHP,
                'current' => PHP_VERSION,
                'met' => version_compare(PHP_VERSION, NEXUS_AI_TRANSLATOR_MIN_PHP, '>='),
            ),
            'wordpress_version' => array(
                'required' => NEXUS_AI_TRANSLATOR_MIN_WP,
                'current' => get_bloginfo('version'),
                'met' => version_compare(get_bloginfo('version'), NEXUS_AI_TRANSLATOR_MIN_WP, '>='),
            ),
            'extensions' => array(
                'curl' => extension_loaded('curl'),
                'json' => extension_loaded('json'),
            ),
        );
    }
}