<?php
/**
 * File: class-translator-admin.php
 * Location: /includes/class-translator-admin.php
 * 
 * Translator Admin Class - Modular Architecture
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translator_Admin {
    
    private $language_manager;
    
    public function __construct() {
        $this->language_manager = new Language_Manager();
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_notices', array($this, 'admin_notices'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_nexus_reset_rate_limits', array($this, 'handle_reset_rate_limits'));
        add_action('wp_ajax_nexus_reset_emergency', array($this, 'handle_reset_emergency'));
        add_action('wp_ajax_nexus_get_analytics', array($this, 'handle_get_analytics'));
        add_action('wp_ajax_nexus_export_config', array($this, 'handle_export_config'));
        add_action('wp_ajax_nexus_import_config', array($this, 'handle_import_config'));
        add_action('wp_ajax_nexus_validate_config', array($this, 'handle_validate_config'));
        add_action('wp_ajax_nexus_cleanup_locks', array($this, 'handle_cleanup_locks'));
        add_action('wp_ajax_nexus_emergency_cleanup', array($this, 'handle_emergency_cleanup'));
        
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
    }
    
    public function add_admin_menu() {
        add_options_page(
            __('Nexus AI Translator Settings', 'nexus-ai-wp-translator'),
            __('AI Translator', 'nexus-ai-wp-translator'),
            'manage_options',
            'nexus-translator-settings',
            array($this, 'render_settings_page')
        );
    }
    
    public function register_settings() {
        // API Settings
        add_settings_section(
            'nexus_translator_api_section',
            __('Claude AI API Settings', 'nexus-ai-wp-translator'),
            array($this, 'render_api_section_description'),
            'nexus_translator_api_settings'
        );
        
        register_setting('nexus_translator_api_settings', 'nexus_translator_api_settings', array(
            'sanitize_callback' => array($this, 'sanitize_api_settings')
        ));
        
        $api_fields = array(
            'claude_api_key' => __('Claude API Key', 'nexus-ai-wp-translator'),
            'model' => __('Claude Model', 'nexus-ai-wp-translator'),
            'max_tokens' => __('Max Tokens', 'nexus-ai-wp-translator'),
            'temperature' => __('Temperature', 'nexus-ai-wp-translator')
        );
        
        foreach ($api_fields as $field => $label) {
            add_settings_field(
                $field,
                $label,
                array($this, "render_{$field}_field"),
                'nexus_translator_api_settings',
                'nexus_translator_api_section'
            );
        }
        
        // Rate Limiting Section
        add_settings_section(
            'nexus_translator_rate_limiting_section',
            __('API Rate Limiting & Safety', 'nexus-ai-wp-translator'),
            array($this, 'render_rate_limiting_section_description'),
            'nexus_translator_api_settings'
        );
        
        $rate_fields = array(
            'max_calls_per_hour' => __('Max API Calls per Hour', 'nexus-ai-wp-translator'),
            'max_calls_per_day' => __('Max API Calls per Day', 'nexus-ai-wp-translator'),
            'min_request_interval' => __('Minimum Interval Between Requests', 'nexus-ai-wp-translator'),
            'request_timeout' => __('Request Timeout', 'nexus-ai-wp-translator'),
            'emergency_stop_threshold' => __('Emergency Stop Threshold', 'nexus-ai-wp-translator'),
            'translation_cooldown' => __('Translation Cooldown', 'nexus-ai-wp-translator')
        );
        
        foreach ($rate_fields as $field => $label) {
            add_settings_field(
                $field,
                $label,
                array($this, "render_{$field}_field"),
                'nexus_translator_api_settings',
                'nexus_translator_rate_limiting_section'
            );
        }
        
        // Language Settings
        add_settings_section(
            'nexus_translator_language_section',
            __('Language Settings', 'nexus-ai-wp-translator'),
            array($this, 'render_language_section_description'),
            'nexus_translator_language_settings'
        );
        
        register_setting('nexus_translator_language_settings', 'nexus_translator_language_settings', array(
            'sanitize_callback' => array($this, 'sanitize_language_settings')
        ));
        
        add_settings_field('source_language', __('Source Language', 'nexus-ai-wp-translator'), 
            array($this, 'render_source_language_field'), 'nexus_translator_language_settings', 'nexus_translator_language_section');
        add_settings_field('target_languages', __('Target Languages', 'nexus-ai-wp-translator'), 
            array($this, 'render_target_languages_field'), 'nexus_translator_language_settings', 'nexus_translator_language_section');
        
        // General Settings
        add_settings_section(
            'nexus_translator_general_section',
            __('General Settings', 'nexus-ai-wp-translator'),
            array($this, 'render_general_section_description'),
            'nexus_translator_general_settings'
        );
        
        register_setting('nexus_translator_options', 'nexus_translator_options', array(
            'sanitize_callback' => array($this, 'sanitize_general_settings')
        ));
        
        add_settings_field('debug_mode', __('Debug Mode', 'nexus-ai-wp-translator'), 
            array($this, 'render_debug_mode_field'), 'nexus_translator_general_settings', 'nexus_translator_general_section');
        add_settings_field('preserve_on_uninstall', __('Preserve Data on Uninstall', 'nexus-ai-wp-translator'), 
            array($this, 'render_preserve_data_field'), 'nexus_translator_general_settings', 'nexus_translator_general_section');
        add_settings_field('analytics_retention', __('Analytics Data Retention', 'nexus-ai-wp-translator'), 
            array($this, 'render_analytics_retention_field'), 'nexus_translator_general_settings', 'nexus_translator_general_section');
    }
    
    public function render_settings_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'api';
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Settings saved successfully!', 'nexus-ai-wp-translator'); ?></p>
                </div>
            <?php endif; ?>
            
            <nav class="nav-tab-wrapper">
                <?php
                $tabs = array(
                    'api' => array('label' => __('API Settings', 'nexus-ai-wp-translator'), 'icon' => 'admin-network'),
                    'languages' => array('label' => __('Languages', 'nexus-ai-wp-translator'), 'icon' => 'translation'),
                    'general' => array('label' => __('General', 'nexus-ai-wp-translator'), 'icon' => 'admin-generic'),
                    'analytics' => array('label' => __('Analytics', 'nexus-ai-wp-translator'), 'icon' => 'chart-bar'),
                    'advanced' => array('label' => __('Advanced', 'nexus-ai-wp-translator'), 'icon' => 'admin-tools')
                );
                
                foreach ($tabs as $tab_key => $tab_data):
                    $active_class = $active_tab == $tab_key ? 'nav-tab-active' : '';
                ?>
                    <a href="?page=nexus-translator-settings&tab=<?php echo $tab_key; ?>" 
                       class="nav-tab <?php echo $active_class; ?>">
                        <span class="dashicons dashicons-<?php echo $tab_data['icon']; ?>" style="margin-right: 5px;"></span>
                        <?php echo $tab_data['label']; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            
            <div class="tab-content">
                <?php
                switch ($active_tab) {
                    case 'api':
                        $this->render_api_tab();
                        break;
                    case 'languages':
                        $this->render_languages_tab();
                        break;
                    case 'general':
                        $this->render_general_tab();
                        break;
                    case 'analytics':
                        $this->render_analytics_tab();
                        break;
                    case 'advanced':
                        $this->render_advanced_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    private function render_api_tab() {
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('nexus_translator_api_settings');
            do_settings_sections('nexus_translator_api_settings');
            submit_button();
            ?>
        </form>
        
        <div class="nexus-info-box">
            <h3><?php _e('Getting Started with Claude AI', 'nexus-ai-wp-translator'); ?></h3>
            <ol>
                <li><?php _e('Sign up for an account at', 'nexus-ai-wp-translator'); ?> <a href="https://console.anthropic.com/" target="_blank">console.anthropic.com</a></li>
                <li><?php _e('Generate an API key from your dashboard', 'nexus-ai-wp-translator'); ?></li>
                <li><?php _e('Paste the API key above and test the connection', 'nexus-ai-wp-translator'); ?></li>
                <li><?php _e('Configure your languages and start translating!', 'nexus-ai-wp-translator'); ?></li>
            </ol>
        </div>
        <?php
    }
    
    private function render_languages_tab() {
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('nexus_translator_language_settings');
            do_settings_sections('nexus_translator_language_settings');
            submit_button();
            ?>
        </form>
        <?php
    }
    
    private function render_general_tab() {
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('nexus_translator_options');
            do_settings_sections('nexus_translator_general_settings');
            submit_button();
            ?>
        </form>
        <?php
    }
    
    private function render_analytics_tab() {
        $analytics = $this->get_analytics_data();
        ?>
        <div class="nexus-analytics-dashboard">
            <h2><?php _e('Translation Analytics', 'nexus-ai-wp-translator'); ?></h2>
            
            <div class="nexus-analytics-summary">
                <div class="nexus-summary-card">
                    <h3><?php _e('Total Translations', 'nexus-ai-wp-translator'); ?></h3>
                    <div class="nexus-summary-number"><?php echo number_format($analytics['totals']['requests']); ?></div>
                    <div class="nexus-summary-subtitle"><?php _e('Last 30 days', 'nexus-ai-wp-translator'); ?></div>
                </div>
                <div class="nexus-summary-card">
                    <h3><?php _e('Success Rate', 'nexus-ai-wp-translator'); ?></h3>
                    <div class="nexus-summary-number">
                        <?php echo $analytics['totals']['requests'] > 0 ? round(($analytics['totals']['successful'] / $analytics['totals']['requests']) * 100, 1) : 0; ?>%
                    </div>
                    <div class="nexus-summary-subtitle"><?php echo $analytics['totals']['successful']; ?> / <?php echo $analytics['totals']['requests']; ?></div>
                </div>
                <div class="nexus-summary-card">
                    <h3><?php _e('Tokens Used', 'nexus-ai-wp-translator'); ?></h3>
                    <div class="nexus-summary-number"><?php echo number_format($analytics['totals']['tokens']); ?></div>
                    <div class="nexus-summary-subtitle"><?php _e('Total API tokens', 'nexus-ai-wp-translator'); ?></div>
                </div>
                <div class="nexus-summary-card">
                    <h3><?php _e('Failed Translations', 'nexus-ai-wp-translator'); ?></h3>
                    <div class="nexus-summary-number"><?php echo number_format($analytics['totals']['failed']); ?></div>
                    <div class="nexus-summary-subtitle"><?php _e('Error rate', 'nexus-ai-wp-translator'); ?>: <?php echo $analytics['totals']['requests'] > 0 ? round(($analytics['totals']['failed'] / $analytics['totals']['requests']) * 100, 1) : 0; ?>%</div>
                </div>
            </div>
            
            <div class="nexus-analytics-controls">
                <button type="button" id="refresh-analytics" class="button"><?php _e('Refresh Analytics', 'nexus-ai-wp-translator'); ?></button>
                <button type="button" id="export-analytics" class="button"><?php _e('Export Data', 'nexus-ai-wp-translator'); ?></button>
                <button type="button" id="clear-analytics" class="button button-secondary"><?php _e('Clear Analytics', 'nexus-ai-wp-translator'); ?></button>
            </div>
        </div>
        <?php
        $this->add_analytics_styles();
    }
    
    private function render_advanced_tab() {
        if (!class_exists('Translator_API')) {
            echo '<p>' . __('Advanced features require API class.', 'nexus-ai-wp-translator') . '</p>';
            return;
        }
        
        $api = new Translator_API();
        $config_summary = $api->get_configuration_summary();
        $rate_status = $api->get_rate_limit_status();
        $validation = $api->validate_configuration();
        
        ?>
        <div class="nexus-advanced-settings">
            <h2><?php _e('Advanced Settings & Management', 'nexus-ai-wp-translator'); ?></h2>
            
            <!-- Current Status Overview -->
            <div class="nexus-status-overview">
                <h3><?php _e('System Status', 'nexus-ai-wp-translator'); ?></h3>
                <div class="nexus-status-grid">
                    <div class="nexus-status-item">
                        <span class="nexus-status-label"><?php _e('API Status:', 'nexus-ai-wp-translator'); ?></span>
                        <span class="nexus-status-value <?php echo $config_summary['api_configured'] ? 'success' : 'error'; ?>">
                            <?php echo $config_summary['api_configured'] ? __('Connected', 'nexus-ai-wp-translator') : __('Not Configured', 'nexus-ai-wp-translator'); ?>
                        </span>
                    </div>
                    <div class="nexus-status-item">
                        <span class="nexus-status-label"><?php _e('Emergency Stop:', 'nexus-ai-wp-translator'); ?></span>
                        <span class="nexus-status-value <?php echo $config_summary['safety']['emergency_stop'] ? 'error' : 'success'; ?>">
                            <?php echo $config_summary['safety']['emergency_stop'] ? __('ACTIVE', 'nexus-ai-wp-translator') : __('Inactive', 'nexus-ai-wp-translator'); ?>
                        </span>
                    </div>
                    <div class="nexus-status-item">
                        <span class="nexus-status-label"><?php _e('Daily Usage:', 'nexus-ai-wp-translator'); ?></span>
                        <span class="nexus-status-value">
                            <?php echo $rate_status['day_calls']; ?> / <?php echo $rate_status['day_limit']; ?>
                            (<?php echo $rate_status['percentages']['day']; ?>%)
                        </span>
                    </div>
                    <div class="nexus-status-item">
                        <span class="nexus-status-label"><?php _e('Model:', 'nexus-ai-wp-translator'); ?></span>
                        <span class="nexus-status-value"><?php echo esc_html($config_summary['model']); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Configuration Validation -->
            <?php if (!$validation['valid']): ?>
            <div class="nexus-validation-warnings">
                <h3><?php _e('⚠️ Configuration Issues', 'nexus-ai-wp-translator'); ?></h3>
                <ul>
                    <?php foreach ($validation['issues'] as $issue): ?>
                        <li><?php echo esc_html($issue); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <!-- Emergency Controls -->
            <div class="nexus-emergency-controls">
                <h3><?php _e('Emergency Controls', 'nexus-ai-wp-translator'); ?></h3>
                <div class="nexus-control-buttons">
                    <button type="button" id="reset-all-limits" class="button"><?php _e('Reset All Rate Limits', 'nexus-ai-wp-translator'); ?></button>
                    <button type="button" id="test-api-advanced" class="button"><?php _e('Test API Connection', 'nexus-ai-wp-translator'); ?></button>
                    <button type="button" id="validate-config" class="button"><?php _e('Validate Configuration', 'nexus-ai-wp-translator'); ?></button>
                    <button type="button" id="cleanup-locks" class="button button-secondary"><?php _e('Cleanup Translation Locks', 'nexus-ai-wp-translator'); ?></button>
                    <button type="button" id="emergency-cleanup" class="button button-danger"><?php _e('Emergency Cleanup', 'nexus-ai-wp-translator'); ?></button>
                </div>
                <div id="nexus-emergency-result"></div>
            </div>
            
            <!-- Configuration Management -->
            <div class="nexus-config-management">
                <h3><?php _e('Configuration Management', 'nexus-ai-wp-translator'); ?></h3>
                <div class="nexus-config-controls">
                    <div class="nexus-config-export">
                        <h4><?php _e('Export Configuration', 'nexus-ai-wp-translator'); ?></h4>
                        <p><?php _e('Export your current settings for backup or migration.', 'nexus-ai-wp-translator'); ?></p>
                        <button type="button" id="export-config" class="button"><?php _e('Export Configuration', 'nexus-ai-wp-translator'); ?></button>
                    </div>
                    
                    <div class="nexus-config-import">
                        <h4><?php _e('Import Configuration', 'nexus-ai-wp-translator'); ?></h4>
                        <p><?php _e('Import previously exported settings.', 'nexus-ai-wp-translator'); ?></p>
                        <input type="file" id="config-file" accept=".json" />
                        <button type="button" id="import-config" class="button" disabled><?php _e('Import Configuration', 'nexus-ai-wp-translator'); ?></button>
                    </div>
                </div>
                <div id="nexus-config-result"></div>
            </div>
            
            <!-- Current Configuration Display -->
            <div class="nexus-config-display">
                <h3><?php _e('Current Configuration', 'nexus-ai-wp-translator'); ?></h3>
                <textarea readonly class="widefat" rows="15"><?php echo esc_textarea(json_encode($config_summary, JSON_PRETTY_PRINT)); ?></textarea>
            </div>
        </div>
        <?php
        $this->add_advanced_styles();
    }
    
    // Field Renderers
    public function render_claude_api_key_field() {
        $settings = get_option('nexus_translator_api_settings', array());
        $api_key = $settings['claude_api_key'] ?? '';
        
        // Determine initial button label
        $button_label = empty($api_key) ? __('Save & Test', 'nexus-ai-wp-translator') : __('Test Connection', 'nexus-ai-wp-translator');
        
        echo '<input type="password" id="claude_api_key" name="nexus_translator_api_settings[claude_api_key]" value="' . esc_attr($api_key) . '" class="regular-text" data-original-value="' . esc_attr($api_key) . '" />';
        echo '<button type="button" id="test-api-connection" class="button" style="margin-left: 10px;">' . $button_label . '</button>';
        echo '<div id="api-test-result"></div>';
        
        if (!empty($api_key)) {
            echo '<p class="description">' . __('API key is configured. Click "Test Connection" to verify.', 'nexus-ai-wp-translator') . '</p>';
        } else {
            echo '<p class="description">' . __('Enter your Claude API key from Anthropic Console.', 'nexus-ai-wp-translator') . '</p>';
        }
    }    
    public function render_model_field() {
        $settings = get_option('nexus_translator_api_settings', array());
        $model = $settings['model'] ?? 'claude-sonnet-4-20250514';
        
        $models = array(
            'claude-sonnet-4-20250514' => 'Claude Sonnet 4 (Recommended)',
            'claude-opus-4-20250514' => 'Claude Opus 4 (Most Capable)',
        );
        
        echo '<select id="model" name="nexus_translator_api_settings[model]">';
        foreach ($models as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($model, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Choose the Claude model to use for translations.', 'nexus-ai-wp-translator') . '</p>';
    }
    
    public function render_max_tokens_field() {
        $settings = get_option('nexus_translator_api_settings', array());
        $max_tokens = $settings['max_tokens'] ?? 4000;
        
        echo '<input type="number" id="max_tokens" name="nexus_translator_api_settings[max_tokens]" value="' . esc_attr($max_tokens) . '" min="100" max="8000" step="100" />';
        echo '<p class="description">' . __('Maximum tokens for API response (100-8000).', 'nexus-ai-wp-translator') . '</p>';
    }
    
    public function render_temperature_field() {
        $settings = get_option('nexus_translator_api_settings', array());
        $temperature = $settings['temperature'] ?? 0.3;
        
        echo '<input type="number" id="temperature" name="nexus_translator_api_settings[temperature]" value="' . esc_attr($temperature) . '" min="0" max="1" step="0.1" />';
        echo '<p class="description">' . __('Controls randomness (0.0-1.0). Recommended: 0.3', 'nexus-ai-wp-translator') . '</p>';
    }
    
    public function render_max_calls_per_hour_field() {
        $settings = get_option('nexus_translator_api_settings', array());
        $max_calls_hour = $settings['max_calls_per_hour'] ?? 50;
        
        echo '<input type="number" id="max_calls_per_hour" name="nexus_translator_api_settings[max_calls_per_hour]" value="' . esc_attr($max_calls_hour) . '" min="1" max="1000" />';
        echo '<p class="description">' . __('Maximum API calls per hour. Default: 50', 'nexus-ai-wp-translator') . '</p>';
    }
    
    public function render_max_calls_per_day_field() {
        $settings = get_option('nexus_translator_api_settings', array());
        $max_calls_day = $settings['max_calls_per_day'] ?? 200;
        
        echo '<input type="number" id="max_calls_per_day" name="nexus_translator_api_settings[max_calls_per_day]" value="' . esc_attr($max_calls_day) . '" min="1" max="10000" />';
        echo '<p class="description">' . __('Maximum API calls per day. Default: 200', 'nexus-ai-wp-translator') . '</p>';
    }
    
    public function render_min_request_interval_field() {
        $settings = get_option('nexus_translator_api_settings', array());
        $min_interval = $settings['min_request_interval'] ?? 2;
        
        echo '<input type="number" id="min_request_interval" name="nexus_translator_api_settings[min_request_interval]" value="' . esc_attr($min_interval) . '" min="0" max="60" />';
        echo ' ' . __('seconds', 'nexus-ai-wp-translator');
        echo '<p class="description">' . __('Minimum time between requests. Default: 2 seconds', 'nexus-ai-wp-translator') . '</p>';
    }
    
    public function render_request_timeout_field() {
        $settings = get_option('nexus_translator_api_settings', array());
        $timeout = $settings['request_timeout'] ?? 60;
        
        echo '<input type="number" id="request_timeout" name="nexus_translator_api_settings[request_timeout]" value="' . esc_attr($timeout) . '" min="10" max="300" />';
        echo ' ' . __('seconds', 'nexus-ai-wp-translator');
        echo '<p class="description">' . __('Maximum time to wait for API response. Default: 60 seconds', 'nexus-ai-wp-translator') . '</p>';
    }
    
    public function render_emergency_stop_threshold_field() {
        $settings = get_option('nexus_translator_api_settings', array());
        $threshold = $settings['emergency_stop_threshold'] ?? 10;
        
        echo '<input type="number" id="emergency_stop_threshold" name="nexus_translator_api_settings[emergency_stop_threshold]" value="' . esc_attr($threshold) . '" min="1" max="100" />';
        echo ' ' . __('calls', 'nexus-ai-wp-translator');
        echo '<p class="description">' . __('Triggers emergency stop. Default: 10', 'nexus-ai-wp-translator') . '</p>';
    }
    
    public function render_translation_cooldown_field() {
        $settings = get_option('nexus_translator_api_settings', array());
        $cooldown = $settings['translation_cooldown'] ?? 300;
        
        echo '<input type="number" id="translation_cooldown" name="nexus_translator_api_settings[translation_cooldown]" value="' . esc_attr($cooldown) . '" min="60" max="3600" />';
        echo ' ' . __('seconds', 'nexus-ai-wp-translator');
        echo '<p class="description">' . __('Minimum time between translations. Default: 300 seconds', 'nexus-ai-wp-translator') . '</p>';
    }
    
    public function render_source_language_field() {
        $settings = get_option('nexus_translator_language_settings', array());
        $source_language = $settings['source_language'] ?? 'fr';
        $languages = $this->language_manager->get_languages_for_select();
        
        echo '<select id="source_language" name="nexus_translator_language_settings[source_language]">';
        foreach ($languages as $code => $name) {
            echo '<option value="' . esc_attr($code) . '"' . selected($source_language, $code, false) . '>' . esc_html($name) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('The primary language of your content.', 'nexus-ai-wp-translator') . '</p>';
    }
    
    public function render_target_languages_field() {
        $settings = get_option('nexus_translator_language_settings', array());
        $target_languages = $settings['target_languages'] ?? array('en');
        $languages = $this->language_manager->get_languages_for_select();
        
        echo '<div class="nexus-target-languages">';
        foreach ($languages as $code => $name) {
            $checked = in_array($code, $target_languages) ? 'checked' : '';
            echo '<label><input type="checkbox" name="nexus_translator_language_settings[target_languages][]" value="' . esc_attr($code) . '" ' . $checked . '> ' . esc_html($name) . '</label>';
        }
        echo '</div>';
        echo '<p class="description">' . __('Languages to translate content into.', 'nexus-ai-wp-translator') . '</p>';
    }
    
    public function render_debug_mode_field() {
        $settings = get_option('nexus_translator_options', array());
        $debug_mode = $settings['debug_mode'] ?? false;
        
        echo '<input type="checkbox" id="debug_mode" name="nexus_translator_options[debug_mode]" value="1"' . checked($debug_mode, true, false) . '> ';
        echo '<label for="debug_mode">' . __('Enable debug mode', 'nexus-ai-wp-translator') . '</label>';
        echo '<p class="description">' . __('Log API requests for debugging.', 'nexus-ai-wp-translator') . '</p>';
    }
    
    public function render_preserve_data_field() {
        $preserve_data = get_option('nexus_translator_preserve_on_uninstall', false);
        
        echo '<input type="checkbox" id="preserve_on_uninstall" name="nexus_translator_preserve_on_uninstall" value="1"' . checked($preserve_data, true, false) . '> ';
        echo '<label for="preserve_on_uninstall">' . __('Keep translation data when uninstalling plugin', 'nexus-ai-wp-translator') . '</label>';
    }
    
    public function render_analytics_retention_field() {
        $retention = get_option('nexus_translator_analytics_retention', 30);
        
        echo '<input type="number" id="analytics_retention" name="nexus_translator_analytics_retention" value="' . esc_attr($retention) . '" min="7" max="365" /> days';
        echo '<p class="description">' . __('How long to keep analytics data. Default: 30 days', 'nexus-ai-wp-translator') . '</p>';
    }
    
    // Section descriptions
    public function render_api_section_description() {
        echo '<p>' . __('Configure your Claude AI API settings.', 'nexus-ai-wp-translator') . '</p>';
        echo '<p><a href="https://console.anthropic.com/" target="_blank">' . __('Get your API key from Anthropic Console', 'nexus-ai-wp-translator') . '</a></p>';
    }
    
    public function render_rate_limiting_section_description() {
        echo '<div class="nexus-rate-limiting-info">';
        echo '<p>' . __('Configure API rate limiting and safety features.', 'nexus-ai-wp-translator') . '</p>';
        
        if (class_exists('Translator_API')) {
            $api = new Translator_API();
            $usage_stats = $api->get_usage_stats();
            
            echo '<div class="nexus-current-usage">';
            echo '<h4>' . __('Current Usage', 'nexus-ai-wp-translator') . '</h4>';
            echo '<div class="nexus-usage-grid">';
            echo '<div class="nexus-usage-item">';
            echo '<span class="nexus-usage-label">' . __('Calls Today:', 'nexus-ai-wp-translator') . '</span>';
            echo '<span class="nexus-usage-value">' . $usage_stats['calls_today'] . ' / ' . $usage_stats['limit_day'] . '</span>';
            echo '</div>';
            echo '<div class="nexus-usage-item">';
            echo '<span class="nexus-usage-label">' . __('Calls This Hour:', 'nexus-ai-wp-translator') . '</span>';
            echo '<span class="nexus-usage-value">' . $usage_stats['calls_hour'] . ' / ' . $usage_stats['limit_hour'] . '</span>';
            echo '</div>';
            echo '</div>';
            
            echo '<div class="nexus-admin-actions">';
            echo '<button type="button" id="reset-rate-limits" class="button">' . __('Reset Rate Limits', 'nexus-ai-wp-translator') . '</button>';
            if ($usage_stats['emergency_stop']) {
                echo '<button type="button" id="reset-emergency-stop" class="button button-primary">' . __('Reset Emergency Stop', 'nexus-ai-wp-translator') . '</button>';
            }
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    }
    
    public function render_language_section_description() {
        echo '<p>' . __('Configure the source and target languages for translation.', 'nexus-ai-wp-translator') . '</p>';
    }
    
    public function render_general_section_description() {
        echo '<p>' . __('General plugin settings and behavior options.', 'nexus-ai-wp-translator') . '</p>';
    }
    
    // Sanitization methods
    public function sanitize_api_settings($input) {

        error_log('DEBUG sanitize_api_settings: Input = ' . print_r($input, true));
        
        $sanitized = array();
        
        if (isset($input['claude_api_key'])) {
            error_log('DEBUG: Raw API key length = ' . strlen($input['claude_api_key']));
            $sanitized['claude_api_key'] = sanitize_text_field($input['claude_api_key']);
            error_log('DEBUG: Sanitized API key length = ' . strlen($sanitized['claude_api_key']));
        }


        $sanitized = array();
        
        if (isset($input['claude_api_key'])) {
            $sanitized['claude_api_key'] = sanitize_text_field($input['claude_api_key']);
        }
        
        if (isset($input['model'])) {
            $allowed_models = array('claude-sonnet-4-20250514', 'claude-opus-4-20250514');
            if (in_array($input['model'], $allowed_models)) {
                $sanitized['model'] = $input['model'];
            }
        }
        
        if (isset($input['max_tokens'])) {
            $sanitized['max_tokens'] = max(100, min(8000, (int) $input['max_tokens']));
        }
        
        if (isset($input['temperature'])) {
            $sanitized['temperature'] = max(0, min(1, (float) $input['temperature']));
        }
        
        // Rate limiting settings
        if (isset($input['max_calls_per_hour'])) {
            $sanitized['max_calls_per_hour'] = max(1, min(1000, (int) $input['max_calls_per_hour']));
        }
        
        if (isset($input['max_calls_per_day'])) {
            $sanitized['max_calls_per_day'] = max(1, min(10000, (int) $input['max_calls_per_day']));
        }
        
        if (isset($input['min_request_interval'])) {
            $sanitized['min_request_interval'] = max(0, min(60, (int) $input['min_request_interval']));
        }
        
        if (isset($input['request_timeout'])) {
            $sanitized['request_timeout'] = max(10, min(300, (int) $input['request_timeout']));
        }
        
        if (isset($input['emergency_stop_threshold'])) {
            $sanitized['emergency_stop_threshold'] = max(1, min(100, (int) $input['emergency_stop_threshold']));
        }
        
        if (isset($input['translation_cooldown'])) {
            $sanitized['translation_cooldown'] = max(60, min(3600, (int) $input['translation_cooldown']));
        }
        
        error_log('DEBUG: Final sanitized = ' . print_r($sanitized, true));
        return $sanitized;
    }
    
    public function sanitize_language_settings($input) {
        $sanitized = array();
        
        if (isset($input['source_language'])) {
            if ($this->language_manager->is_valid_language_code($input['source_language'])) {
                $sanitized['source_language'] = $input['source_language'];
            }
        }
        
        if (isset($input['target_languages']) && is_array($input['target_languages'])) {
            $valid_targets = array();
            foreach ($input['target_languages'] as $lang) {
                if ($this->language_manager->is_valid_language_code($lang)) {
                    $valid_targets[] = $lang;
                }
            }
            $sanitized['target_languages'] = $valid_targets;
        }
        
        return $sanitized;
    }
    
    public function sanitize_general_settings($input) {
        $sanitized = array();
        
        if (isset($input['debug_mode'])) {
            $sanitized['debug_mode'] = (bool) $input['debug_mode'];
        }
        
        if (isset($input['cache_translations'])) {
            $sanitized['cache_translations'] = (bool) $input['cache_translations'];
        }
        
        // Handle preserve data setting separately
        if (isset($_POST['nexus_translator_preserve_on_uninstall'])) {
            update_option('nexus_translator_preserve_on_uninstall', true);
        } else {
            update_option('nexus_translator_preserve_on_uninstall', false);
        }
        
        // Handle analytics retention separately
        if (isset($_POST['nexus_translator_analytics_retention'])) {
            $retention = max(7, min(365, (int) $_POST['nexus_translator_analytics_retention']));
            update_option('nexus_translator_analytics_retention', $retention);
        }
        
        return $sanitized;
    }
    
    // AJAX handlers
    public function handle_reset_rate_limits() {
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }
        
        if (class_exists('Translator_API')) {
            $api = new Translator_API();
            $result = $api->reset_rate_limits();
            
            if ($result) {
                wp_send_json_success(array('message' => __('Rate limits reset successfully', 'nexus-ai-wp-translator')));
            } else {
                wp_send_json_error(__('Failed to reset rate limits', 'nexus-ai-wp-translator'));
            }
        } else {
            wp_send_json_error(__('API class not available', 'nexus-ai-wp-translator'));
        }
    }
    
    public function handle_reset_emergency() {
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }
        
        delete_option('nexus_translator_emergency_stop');
        delete_option('nexus_translator_emergency_reason');
        delete_option('nexus_translator_emergency_time');
        
        wp_send_json_success(array('message' => __('Emergency stop reset successfully', 'nexus-ai-wp-translator')));
    }
    
    public function handle_get_analytics() {
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }
        
        $analytics = $this->get_analytics_data();
        wp_send_json_success($analytics);
    }
    
    public function handle_export_config() {
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }
        
        $config = array(
            'api_settings' => get_option('nexus_translator_api_settings', array()),
            'language_settings' => get_option('nexus_translator_language_settings', array()),
            'general_options' => get_option('nexus_translator_options', array()),
            'exported_at' => current_time('mysql'),
            'plugin_version' => defined('NEXUS_TRANSLATOR_VERSION') ? NEXUS_TRANSLATOR_VERSION : '1.0.0',
            'site_url' => get_site_url()
        );
        
        // Remove sensitive data
        if (isset($config['api_settings']['claude_api_key'])) {
            $config['api_settings']['claude_api_key'] = '[API_KEY_REMOVED]';
        }
        
        wp_send_json_success(array(
            'config' => $config,
            'filename' => 'nexus-translator-config-' . date('Y-m-d-H-i-s') . '.json'
        ));
    }
    
    public function handle_import_config() {
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }
        
        if (!isset($_POST['config_data'])) {
            wp_send_json_error('No configuration data provided');
        }
        
        $config_data = json_decode(stripslashes($_POST['config_data']), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Invalid JSON configuration');
        }
        
        $imported = 0;
        $warnings = array();
        
        // Import API settings (excluding API key for security)
        if (isset($config_data['api_settings']) && is_array($config_data['api_settings'])) {
            $api_settings = $config_data['api_settings'];
            
            // Remove API key if present (security)
            unset($api_settings['claude_api_key']);
            
            $current_api_settings = get_option('nexus_translator_api_settings', array());
            $new_api_settings = array_merge($current_api_settings, $api_settings);
            
            if (update_option('nexus_translator_api_settings', $new_api_settings)) {
                $imported++;
            } else {
                $warnings[] = 'Failed to import API settings';
            }
        }
        
        // Import language settings
        if (isset($config_data['language_settings']) && is_array($config_data['language_settings'])) {
            $language_settings = $config_data['language_settings'];
            
            // Validate language codes
            $valid_languages = array_keys($this->language_manager->get_supported_languages());
            
            if (isset($language_settings['source_language'])) {
                if (!in_array($language_settings['source_language'], $valid_languages)) {
                    unset($language_settings['source_language']);
                    $warnings[] = 'Invalid source language removed';
                }
            }
            
            if (isset($language_settings['target_languages']) && is_array($language_settings['target_languages'])) {
                $language_settings['target_languages'] = array_filter(
                    $language_settings['target_languages'],
                    function($lang) use ($valid_languages) {
                        return in_array($lang, $valid_languages);
                    }
                );
            }
            
            if (update_option('nexus_translator_language_settings', $language_settings)) {
                $imported++;
            } else {
                $warnings[] = 'Failed to import language settings';
            }
        }
        
        // Import general options
        if (isset($config_data['general_options']) && is_array($config_data['general_options'])) {
            if (update_option('nexus_translator_options', $config_data['general_options'])) {
                $imported++;
            } else {
                $warnings[] = 'Failed to import general options';
            }
        }
        
        $message = sprintf(
            __('%d settings sections imported successfully', 'nexus-ai-wp-translator'),
            $imported
        );
        
        if (!empty($warnings)) {
            $message .= '. ' . __('Warnings: ', 'nexus-ai-wp-translator') . implode(', ', $warnings);
        }
        
        wp_send_json_success(array(
            'message' => $message,
            'imported_count' => $imported,
            'warnings' => $warnings
        ));
    }
    
    public function handle_validate_config() {
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }
        
        if (!class_exists('Translator_API')) {
            wp_send_json_error('API class not available');
        }
        
        $api = new Translator_API();
        $validation = $api->validate_configuration();
        
        // Additional validation checks
        $issues = $validation['issues'];
        $warnings = array();
        
        // Check if API is actually configured
        if (!$api->is_api_configured()) {
            $issues[] = __('Claude API key is not configured', 'nexus-ai-wp-translator');
        }
        
        // Check language settings
        $lang_settings = get_option('nexus_translator_language_settings', array());
        if (empty($lang_settings['source_language'])) {
            $issues[] = __('Source language is not configured', 'nexus-ai-wp-translator');
        }
        if (empty($lang_settings['target_languages'])) {
            $issues[] = __('No target languages selected', 'nexus-ai-wp-translator');
        }
        
        // Check emergency status
        if (get_option('nexus_translator_emergency_stop', false)) {
            $issues[] = __('Emergency stop is currently active', 'nexus-ai-wp-translator');
        }
        
        // Test API connection if possible
        $api_status = 'unknown';
        if ($api->is_api_configured()) {
            $test_result = $api->test_api_connection();
            $api_status = $test_result['success'] ? 'connected' : 'error';
            
            if (!$test_result['success']) {
                $issues[] = sprintf(__('API connection failed: %s', 'nexus-ai-wp-translator'), $test_result['error']);
            }
        }
        
        wp_send_json_success(array(
            'valid' => empty($issues),
            'issues' => $issues,
            'warnings' => $warnings,
            'api_status' => $api_status
        ));
    }
    
    public function handle_cleanup_locks() {
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }
        
        global $wpdb;
        
        // Remove locks older than 1 hour
        $cutoff = time() - 3600;
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} 
             WHERE meta_key = '_nexus_translation_lock' 
             AND meta_value < %d",
            $cutoff
        ));
        
        // Reset stale processing status
        $wpdb->query(
            "UPDATE {$wpdb->postmeta} 
             SET meta_value = 'error' 
             WHERE meta_key = '_nexus_translation_status' 
             AND meta_value = 'processing'"
        );
        
        wp_send_json_success(array(
            'message' => sprintf(__('Cleaned up %d stale translation locks', 'nexus-ai-wp-translator'), $deleted)
        ));
    }
    
    public function handle_emergency_cleanup() {
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }
        
        global $wpdb;
        
        $cleaned = array();
        
        // Clear all locks
        $locks_deleted = $wpdb->query(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_nexus_translation_lock'"
        );
        $cleaned[] = sprintf(__('%d translation locks removed', 'nexus-ai-wp-translator'), $locks_deleted);
        
        // Reset processing translations to error
        $processing_reset = $wpdb->query(
            "UPDATE {$wpdb->postmeta} 
             SET meta_value = 'error' 
             WHERE meta_key = '_nexus_translation_status' 
             AND meta_value = 'processing'"
        );
        $cleaned[] = sprintf(__('%d stuck translations reset', 'nexus-ai-wp-translator'), $processing_reset);
        
        // Clear rate limits
        delete_transient('nexus_translator_rate_limit_hour');
        delete_transient('nexus_translator_rate_limit_day');
        delete_transient('nexus_translator_last_request');
        $cleaned[] = __('Rate limits reset', 'nexus-ai-wp-translator');
        
        // Clear emergency stop
        delete_option('nexus_translator_emergency_stop');
        delete_option('nexus_translator_emergency_reason');
        delete_option('nexus_translator_emergency_time');
        $cleaned[] = __('Emergency stop cleared', 'nexus-ai-wp-translator');
        
        // Clear any cached errors
        wp_cache_flush();
        
        wp_send_json_success(array(
            'message' => __('Emergency cleanup completed', 'nexus-ai-wp-translator'),
            'actions' => $cleaned
        ));
    }
    
    // Helper methods
    private function get_analytics_data() {
        global $wpdb;
        
        $retention_days = get_option('nexus_translator_analytics_retention', 30);
        $since_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        // Get translation statistics
        $requests = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_nexus_translation_timestamp' AND meta_value > %s",
            strtotime($since_date)
        ));
        
        $successful = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm1 
             JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id 
             WHERE pm1.meta_key = '_nexus_translation_timestamp' 
             AND pm1.meta_value > %s 
             AND pm2.meta_key = '_nexus_translation_status' 
             AND pm2.meta_value = 'completed'",
            strtotime($since_date)
        ));
        
        return array(
            'totals' => array(
                'requests' => (int) $requests,
                'successful' => (int) $successful,
                'failed' => (int) $requests - (int) $successful,
                'tokens' => get_option('nexus_translator_total_tokens', 0)
            ),
            'language_breakdown' => $this->get_language_breakdown(),
            'recent_activity' => $this->get_recent_activity()
        );
    }
    
    private function get_language_breakdown() {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT meta_value as language, COUNT(*) as total 
             FROM {$wpdb->postmeta} 
             WHERE meta_key = '_nexus_language' 
             GROUP BY meta_value"
        );
        
        $breakdown = array();
        foreach ($results as $result) {
            $breakdown[$result->language] = array(
                'total' => (int) $result->total,
                'successful' => (int) $result->total, // Simplified for now
                'failed' => 0
            );
        }
        
        return $breakdown;
    }
    
    private function get_recent_activity() {
        global $wpdb;
        
        $activities = $wpdb->get_results(
            "SELECT p.ID as post_id, pm1.meta_value as timestamp, pm2.meta_value as language, pm3.meta_value as status
             FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_nexus_translation_timestamp'
             JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_nexus_language'
             JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_nexus_translation_status'
             ORDER BY pm1.meta_value DESC
             LIMIT 20"
        );
        
        $recent = array();
        foreach ($activities as $activity) {
            $recent[] = array(
                'post_id' => (int) $activity->post_id,
                'timestamp' => (int) $activity->timestamp,
                'target_language' => $activity->language,
                'success' => $activity->status === 'completed',
                'user_login' => 'System' // Simplified for now
            );
        }
        
        return $recent;
    }
    
    public function add_dashboard_widget() {
        if (current_user_can('manage_options')) {
            wp_add_dashboard_widget(
                'nexus_translator_widget',
                __('Nexus AI Translator Status', 'nexus-ai-wp-translator'),
                array($this, 'render_dashboard_widget')
            );
        }
    }
    
    public function render_dashboard_widget() {
        if (!class_exists('Translator_API')) {
            echo '<p>' . __('API class not available', 'nexus-ai-wp-translator') . '</p>';
            return;
        }
        
        $api = new Translator_API();
        $usage_stats = $api->get_usage_stats();
        $analytics = $this->get_analytics_data();
        
        ?>
        <div class="nexus-dashboard-widget">
            <div class="nexus-widget-stats">
                <div class="nexus-widget-stat">
                    <span class="nexus-stat-number"><?php echo $usage_stats['calls_today']; ?></span>
                    <span class="nexus-stat-label"><?php _e('API Calls Today', 'nexus-ai-wp-translator'); ?></span>
                </div>
                <div class="nexus-widget-stat">
                    <span class="nexus-stat-number"><?php echo $analytics['totals']['requests']; ?></span>
                    <span class="nexus-stat-label"><?php _e('Translations (30d)', 'nexus-ai-wp-translator'); ?></span>
                </div>
            </div>
            
            <?php if ($usage_stats['emergency_stop']): ?>
                <div class="nexus-widget-alert">
                    <span class="dashicons dashicons-warning"></span>
                    <?php _e('Emergency stop is active!', 'nexus-ai-wp-translator'); ?>
                    <a href="<?php echo admin_url('admin.php?page=nexus-translator-settings&tab=advanced'); ?>"><?php _e('Fix now', 'nexus-ai-wp-translator'); ?></a>
                </div>
            <?php endif; ?>
            
            <div class="nexus-widget-actions">
                <a href="<?php echo admin_url('admin.php?page=nexus-translator-settings'); ?>" class="button button-primary">
                    <?php _e('Manage Settings', 'nexus-ai-wp-translator'); ?>
                </a>
            </div>
        </div>
        
        <style>
        .nexus-dashboard-widget .nexus-widget-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        .nexus-widget-stat {
            text-align: center;
            flex: 1;
        }
        .nexus-stat-number {
            display: block;
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
        }
        .nexus-stat-label {
            font-size: 12px;
            color: #666;
        }
        .nexus-widget-alert {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            color: #856404;
        }
        .nexus-widget-alert .dashicons {
            color: #856404;
            vertical-align: middle;
        }
        .nexus-widget-actions {
            text-align: center;
        }
        </style>
        <?php
    }
    
    public function admin_notices() {
        // Check if API is configured
        $api_settings = get_option('nexus_translator_api_settings', array());
        if (empty($api_settings['claude_api_key']) && $this->is_translator_page()) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p>' . sprintf(
                __('Nexus AI Translator: Please configure your Claude API key in <a href="%s">settings</a> to start translating.', 'nexus-ai-wp-translator'),
                admin_url('admin.php?page=nexus-translator-settings')
            ) . '</p>';
            echo '</div>';
        }
        
        // Check emergency stop
        if (get_option('nexus_translator_emergency_stop', false)) {
            $reason = get_option('nexus_translator_emergency_reason', 'Unknown');
            
            echo '<div class="notice notice-error">';
            echo '<h3>🚨 ' . __('Nexus Translator Emergency Stop Active', 'nexus-ai-wp-translator') . '</h3>';
            echo '<p><strong>' . __('All translation functionality has been disabled for safety.', 'nexus-ai-wp-translator') . '</strong></p>';
            echo '<p>' . __('Reason:', 'nexus-ai-wp-translator') . ' ' . esc_html($reason) . '</p>';
            echo '<p><a href="' . admin_url('admin.php?page=nexus-translator-settings&tab=advanced') . '" class="button button-primary">' . __('Go to Settings to Reset', 'nexus-ai-wp-translator') . '</a></p>';
            echo '</div>';
        }
        
        // Show rate limit warnings
        if (class_exists('Translator_API')) {
            $api = new Translator_API();
            $usage_stats = $api->get_usage_stats();
            
            $daily_percentage = ($usage_stats['calls_today'] / $usage_stats['limit_day']) * 100;
            
            if ($daily_percentage >= 80 && $daily_percentage < 100) {
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p>' . sprintf(
                    __('Nexus Translator: You have used %d%% of your daily API limit (%d/%d calls).', 'nexus-ai-wp-translator'),
                    round($daily_percentage),
                    $usage_stats['calls_today'],
                    $usage_stats['limit_day']
                ) . '</p>';
                echo '</div>';
            }
        }
    }
    
    private function is_translator_page() {
        $screen = get_current_screen();
        return $screen && (
            $screen->id === 'settings_page_nexus-translator-settings' ||
            in_array($screen->base, array('post', 'edit'))
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_nexus-translator-settings') {
            return;
        }
        
        // Enqueue modular JavaScript system
        wp_enqueue_script(
            'nexus-translator-admin-core',
            NEXUS_TRANSLATOR_PLUGIN_URL . 'admin/js/admin-core.js',
            array('jquery'),
            NEXUS_TRANSLATOR_VERSION,
            true
        );
        
        wp_enqueue_script(
            'nexus-translator-admin-modules',
            NEXUS_TRANSLATOR_PLUGIN_URL . 'admin/js/admin-modules.js',
            array('nexus-translator-admin-core'),
            NEXUS_TRANSLATOR_VERSION,
            true
        );
        
        wp_localize_script('nexus-translator-admin-core', 'nexusTranslator', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nexus_translator_nonce'),
            'strings' => array(
                'processing' => __('Processing...', 'nexus-ai-wp-translator'),
                'success' => __('Success!', 'nexus-ai-wp-translator'),
                'error' => __('Error occurred', 'nexus-ai-wp-translator'),
                'confirmReset' => __('Are you sure you want to reset rate limits?', 'nexus-ai-wp-translator'),
                'confirmEmergency' => __('Are you sure you want to reset emergency stop?', 'nexus-ai-wp-translator'),
                'confirmCleanup' => __('Are you sure you want to perform emergency cleanup?', 'nexus-ai-wp-translator'),
                'testing' => __('Testing...', 'nexus-ai-wp-translator'),
                'validating' => __('Validating...', 'nexus-ai-wp-translator'),
                'cleaning' => __('Cleaning...', 'nexus-ai-wp-translator')
            ),
            'debug' => get_option('nexus_translator_options', array())['debug_mode'] ?? false
        ));
        
        wp_enqueue_style(
            'nexus-translator-admin',
            NEXUS_TRANSLATOR_PLUGIN_URL . 'admin/css/admin-style.css',
            array(),
            NEXUS_TRANSLATOR_VERSION
        );
    }
    
    private function add_analytics_styles() {
        ?>
        <style>
        .nexus-analytics-dashboard { max-width: 1200px; }
        .nexus-analytics-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .nexus-summary-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .nexus-summary-card h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
        }
        .nexus-summary-number {
            font-size: 32px;
            font-weight: bold;
            color: #0073aa;
            margin-bottom: 5px;
        }
        .nexus-summary-subtitle {
            color: #888;
            font-size: 12px;
        }
        .nexus-analytics-controls {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
        </style>
        <?php
    }
    
    private function add_advanced_styles() {
        ?>
        <style>
        .nexus-advanced-settings { max-width: 1200px; }
        .nexus-status-overview,
        .nexus-validation-warnings,
        .nexus-emergency-controls,
        .nexus-config-management,
        .nexus-config-display {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .nexus-status-overview h3,
        .nexus-validation-warnings h3,
        .nexus-emergency-controls h3,
        .nexus-config-management h3,
        .nexus-config-display h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
            font-size: 18px;
        }
        .nexus-status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .nexus-status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: #f9f9f9;
            border-radius: 6px;
            border-left: 4px solid #ddd;
        }
        .nexus-status-label {
            font-weight: 500;
            color: #666;
        }
        .nexus-status-value {
            font-weight: bold;
        }
        .nexus-status-value.success {
            color: #46b450;
        }
        .nexus-status-value.error {
            color: #dc3232;
        }
        .nexus-validation-warnings {
            background: #fff8e1;
            border-color: #ffb900;
        }
        .nexus-validation-warnings h3 {
            color: #e65100;
            border-bottom-color: #ffb900;
        }
        .nexus-emergency-controls {
            background: #fff8e1;
            border-color: #ffb900;
        }
        .nexus-emergency-controls h3 {
            color: #e65100;
            border-bottom-color: #ffb900;
        }
        .nexus-control-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        .nexus-config-controls {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 20px;
        }
        .nexus-config-export,
        .nexus-config-import {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }
        .nexus-config-export h4,
        .nexus-config-import h4 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #555;
            font-size: 16px;
        }
        .nexus-config-export p,
        .nexus-config-import p {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .nexus-config-display textarea {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
        }
        @media (max-width: 768px) {
            .nexus-config-controls {
                grid-template-columns: 1fr;
            }
            .nexus-control-buttons {
                flex-direction: column;
            }
        }
        </style>
        <?php
    }
}