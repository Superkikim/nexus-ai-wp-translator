<?php
/**
 * File: admin-page.php
 * Location: /admin/views/admin-page.php
 * 
 * Admin Settings Page View - Unified with Advanced Features
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php
    // Show success message after saving
    if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'nexus-ai-wp-translator') . '</p></div>';
    }
    ?>
    
    <nav class="nav-tab-wrapper">
        <a href="?page=nexus-translator-settings&tab=api" class="nav-tab <?php echo $active_tab == 'api' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-admin-network" style="margin-right: 5px;"></span>
            <?php _e('API Settings', 'nexus-ai-wp-translator'); ?>
        </a>
        <a href="?page=nexus-translator-settings&tab=languages" class="nav-tab <?php echo $active_tab == 'languages' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-translation" style="margin-right: 5px;"></span>
            <?php _e('Languages', 'nexus-ai-wp-translator'); ?>
        </a>
        <a href="?page=nexus-translator-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-admin-generic" style="margin-right: 5px;"></span>
            <?php _e('General', 'nexus-ai-wp-translator'); ?>
        </a>
        <a href="?page=nexus-translator-settings&tab=analytics" class="nav-tab <?php echo $active_tab == 'analytics' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-chart-bar" style="margin-right: 5px;"></span>
            <?php _e('Analytics', 'nexus-ai-wp-translator'); ?>
        </a>
        <a href="?page=nexus-translator-settings&tab=advanced" class="nav-tab <?php echo $active_tab == 'advanced' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-admin-tools" style="margin-right: 5px;"></span>
            <?php _e('Advanced', 'nexus-ai-wp-translator'); ?>
        </a>
    </nav>
    
    <div class="tab-content">
        <?php if ($active_tab == 'api'): ?>
            <!-- API Settings Tab -->
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
                
                <h4><?php _e('Model Comparison', 'nexus-ai-wp-translator'); ?></h4>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Model', 'nexus-ai-wp-translator'); ?></th>
                            <th><?php _e('Speed', 'nexus-ai-wp-translator'); ?></th>
                            <th><?php _e('Quality', 'nexus-ai-wp-translator'); ?></th>
                            <th><?php _e('Cost', 'nexus-ai-wp-translator'); ?></th>
                            <th><?php _e('Best For', 'nexus-ai-wp-translator'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Claude Sonnet 4</strong></td>
                            <td><?php _e('Fast', 'nexus-ai-wp-translator'); ?></td>
                            <td><?php _e('High', 'nexus-ai-wp-translator'); ?></td>
                            <td><?php _e('Moderate', 'nexus-ai-wp-translator'); ?></td>
                            <td><?php _e('Most translations', 'nexus-ai-wp-translator'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Claude Opus 4</strong></td>
                            <td><?php _e('Slower', 'nexus-ai-wp-translator'); ?></td>
                            <td><?php _e('Highest', 'nexus-ai-wp-translator'); ?></td>
                            <td><?php _e('Higher', 'nexus-ai-wp-translator'); ?></td>
                            <td><?php _e('Complex content', 'nexus-ai-wp-translator'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
        <?php elseif ($active_tab == 'languages'): ?>
            <!-- Language Settings Tab -->
            <form method="post" action="options.php">
                <?php
                settings_fields('nexus_translator_language_settings');
                do_settings_sections('nexus_translator_language_settings');
                submit_button();
                ?>
            </form>
            
            <div class="nexus-info-box">
                <h3><?php _e('Language Configuration Tips', 'nexus-ai-wp-translator'); ?></h3>
                <ul>
                    <li><?php _e('Set your primary content language as the source language', 'nexus-ai-wp-translator'); ?></li>
                    <li><?php _e('Select all languages you want to translate content into', 'nexus-ai-wp-translator'); ?></li>
                    <li><?php _e('You can always add more languages later', 'nexus-ai-wp-translator'); ?></li>
                    <li><?php _e('Translation quality is best between major language pairs', 'nexus-ai-wp-translator'); ?></li>
                </ul>
            </div>
            
        <?php elseif ($active_tab == 'general'): ?>
            <!-- General Settings Tab -->
            <form method="post" action="options.php">
                <?php
                settings_fields('nexus_translator_options');
                do_settings_sections('nexus_translator_general_settings');
                submit_button();
                ?>
            </form>
            
            <div class="nexus-info-box">
                <h3><?php _e('Feature Overview', 'nexus-ai-wp-translator'); ?></h3>
                <dl>
                    <dt><strong><?php _e('Debug Mode', 'nexus-ai-wp-translator'); ?></strong></dt>
                    <dd><?php _e('Logs API requests for troubleshooting. Only enable when needed.', 'nexus-ai-wp-translator'); ?></dd>
                    
                    <dt><strong><?php _e('Data Preservation', 'nexus-ai-wp-translator'); ?></strong></dt>
                    <dd><?php _e('Keep translation data when uninstalling the plugin for future use.', 'nexus-ai-wp-translator'); ?></dd>
                    
                    <dt><strong><?php _e('Analytics Retention', 'nexus-ai-wp-translator'); ?></strong></dt>
                    <dd><?php _e('How long to keep translation analytics and usage data.', 'nexus-ai-wp-translator'); ?></dd>
                </dl>
            </div>
            
        <?php elseif ($active_tab == 'analytics'): ?>
            <!-- Analytics Tab -->
            <div class="nexus-analytics-dashboard">
                <?php
                $language_manager = new Language_Manager();
                $stats = $language_manager->get_translation_statistics();
                if (class_exists('Translator_API')) {
                    $api = new Translator_API();
                    $usage_stats = $api->get_usage_stats();
                } else {
                    $usage_stats = array('translations_today' => 0, 'translations_month' => 0, 'tokens_used' => 0);
                }
                ?>
                
                <div class="nexus-stats-grid">
                    <div class="nexus-stat-card">
                        <h3><?php _e('Translation Statistics', 'nexus-ai-wp-translator'); ?></h3>
                        <?php if (!empty($stats)): ?>
                            <table class="widefat">
                                <thead>
                                    <tr>
                                        <th><?php _e('Language', 'nexus-ai-wp-translator'); ?></th>
                                        <th><?php _e('Posts', 'nexus-ai-wp-translator'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats as $code => $data): ?>
                                        <tr>
                                            <td><?php echo $data['flag'] . ' ' . esc_html($data['name']); ?></td>
                                            <td><?php echo number_format($data['count']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p><?php _e('No translation data available yet.', 'nexus-ai-wp-translator'); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="nexus-stat-card">
                        <h3><?php _e('API Usage', 'nexus-ai-wp-translator'); ?></h3>
                        <div class="nexus-usage-stats">
                            <div class="nexus-usage-item">
                                <span class="nexus-usage-label"><?php _e('Today', 'nexus-ai-wp-translator'); ?></span>
                                <span class="nexus-usage-value"><?php echo number_format($usage_stats['translations_today']); ?></span>
                            </div>
                            <div class="nexus-usage-item">
                                <span class="nexus-usage-label"><?php _e('This Month', 'nexus-ai-wp-translator'); ?></span>
                                <span class="nexus-usage-value"><?php echo number_format($usage_stats['translations_month']); ?></span>
                            </div>
                            <div class="nexus-usage-item">
                                <span class="nexus-usage-label"><?php _e('Tokens Used', 'nexus-ai-wp-translator'); ?></span>
                                <span class="nexus-usage-value"><?php echo number_format($usage_stats['tokens_used']); ?></span>
                            </div>
                        </div>
                        
                        <?php if (class_exists('Translator_API')): ?>
                            <div class="nexus-analytics-controls">
                                <button type="button" id="refresh-analytics" class="button"><?php _e('Refresh Analytics', 'nexus-ai-wp-translator'); ?></button>
                                <button type="button" id="export-analytics" class="button"><?php _e('Export Data', 'nexus-ai-wp-translator'); ?></button>
                                <button type="button" id="clear-analytics" class="button button-secondary"><?php _e('Clear Old Data', 'nexus-ai-wp-translator'); ?></button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
        <?php elseif ($active_tab == 'advanced'): ?>
            <!-- Advanced Settings Tab -->
            <?php 
            if (!class_exists('Translator_API')) {
                echo '<div class="notice notice-error"><p>' . __('Advanced features require API class.', 'nexus-ai-wp-translator') . '</p></div>';
            } else {
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
            <?php } ?>
            
        <?php endif; ?>
    </div>
    
    <div class="nexus-info-box">
        <h3><?php _e('About Nexus AI WP Translator', 'nexus-ai-wp-translator'); ?></h3>
        <p><?php printf(__('Version %s', 'nexus-ai-wp-translator'), defined('NEXUS_TRANSLATOR_VERSION') ? NEXUS_TRANSLATOR_VERSION : '1.0.0'); ?></p>
        <p><?php _e('A modern WordPress translation plugin powered by Claude AI, designed for ease of use and high-quality translations.', 'nexus-ai-wp-translator'); ?></p>
        
        <h4><?php _e('Support & Documentation', 'nexus-ai-wp-translator'); ?></h4>
        <ul>
            <li><a href="https://github.com/superkikim/nexus-ai-wp-translator" target="_blank"><?php _e('GitHub Repository', 'nexus-ai-wp-translator'); ?></a></li>
            <li><a href="https://github.com/superkikim/nexus-ai-wp-translator/issues" target="_blank"><?php _e('Report Issues', 'nexus-ai-wp-translator'); ?></a></li>
            <li><a href="https://github.com/superkikim/nexus-ai-wp-translator/wiki" target="_blank"><?php _e('Documentation', 'nexus-ai-wp-translator'); ?></a></li>
        </ul>
    </div>
</div>

<style>
.nexus-info-box {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 20px;
    margin-top: 20px;
}

.nexus-info-box h3 {
    margin-top: 0;
    color: #333;
}

.nexus-info-box h4 {
    color: #666;
    margin-top: 20px;
    margin-bottom: 10px;
}

.nexus-stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.nexus-stat-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 20px;
}

.nexus-stat-card h3 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #333;
}

.nexus-usage-stats {
    display: grid;
    gap: 10px;
}

.nexus-usage-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    background: #f7f7f7;
    border-radius: 3px;
}

.nexus-usage-label {
    font-weight: 500;
    color: #666;
}

.nexus-usage-value {
    font-weight: bold;
    color: #0073aa;
    font-size: 1.2em;
}

.nexus-analytics-controls {
    margin-top: 15px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* Advanced Settings Styles */
.nexus-advanced-settings { 
    max-width: 1200px; 
}

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
    transition: background-color 0.2s;
}

.nexus-status-item:hover {
    background: #f0f0f0;
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

.button-danger {
    background: #dc3545 !important;
    border-color: #dc3545 !important;
    color: white !important;
}

.button-danger:hover {
    background: #c82333 !important;
    border-color: #bd2130 !important;
}

@media (max-width: 768px) {
    .nexus-stats-grid {
        grid-template-columns: 1fr;
    }
    
    .nexus-config-controls {
        grid-template-columns: 1fr;
    }
    
    .nexus-control-buttons {
        flex-direction: column;
    }
    
    .nexus-analytics-controls {
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .nexus-control-buttons .button,
    .nexus-analytics-controls .button {
        width: 100%;
        text-align: center;
    }
}
</style>