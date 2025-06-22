<?php
/**
 * File: admin-page.php
 * Location: /admin/views/admin-page.php
 * 
 * Admin Settings Page View
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
        <a href="?page=nexus-translator-settings&tab=stats" class="nav-tab <?php echo $active_tab == 'stats' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-chart-bar" style="margin-right: 5px;"></span>
            <?php _e('Statistics', 'nexus-ai-wp-translator'); ?>
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
                    <dt><strong><?php _e('Translation Popup', 'nexus-ai-wp-translator'); ?></strong></dt>
                    <dd><?php _e('Shows a popup asking if you want to translate when saving posts', 'nexus-ai-wp-translator'); ?></dd>
                    
                    <dt><strong><?php _e('Debug Mode', 'nexus-ai-wp-translator'); ?></strong></dt>
                    <dd><?php _e('Logs API requests for troubleshooting. Only enable when needed.', 'nexus-ai-wp-translator'); ?></dd>
                </dl>
            </div>
            
        <?php elseif ($active_tab == 'stats'): ?>
            <!-- Statistics Tab -->
            <div class="nexus-stats-grid">
                <?php
                $language_manager = new Language_Manager();
                $stats = $language_manager->get_translation_statistics();
                $api = new Translator_API();
                $usage_stats = $api->get_usage_stats();
                ?>
                
                <div class="nexus-stat-card">
                    <h3><?php _e('Translation Statistics', 'nexus-ai-wp-translator'); ?></h3>
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
                </div>
            </div>
            
            <div class="nexus-info-box">
                <h3><?php _e('About Nexus AI WP Translator', 'nexus-ai-wp-translator'); ?></h3>
                <p><?php printf(__('Version %s', 'nexus-ai-wp-translator'), NEXUS_TRANSLATOR_VERSION); ?></p>
                <p><?php _e('A modern WordPress translation plugin powered by Claude AI, designed for ease of use and high-quality translations.', 'nexus-ai-wp-translator'); ?></p>
                
                <h4><?php _e('Support & Documentation', 'nexus-ai-wp-translator'); ?></h4>
                <ul>
                    <li><a href="https://github.com/your-username/nexus-ai-wp-translator" target="_blank"><?php _e('GitHub Repository', 'nexus-ai-wp-translator'); ?></a></li>
                    <li><a href="https://github.com/your-username/nexus-ai-wp-translator/issues" target="_blank"><?php _e('Report Issues', 'nexus-ai-wp-translator'); ?></a></li>
                    <li><a href="https://github.com/your-username/nexus-ai-wp-translator/wiki" target="_blank"><?php _e('Documentation', 'nexus-ai-wp-translator'); ?></a></li>
                </ul>
            </div>
            
        <?php endif; ?>
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

@media (max-width: 768px) {
    .nexus-stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>