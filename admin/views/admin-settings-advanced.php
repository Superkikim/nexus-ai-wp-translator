<?php
/**
 * File: admin-settings-advanced.php
 * Location: /admin/views/admin-settings-advanced.php
 * 
 * Advanced API Settings View
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current API instance for status
$api = new Translator_API();
$rate_status = $api->get_rate_limit_status();
$usage_stats = $api->get_usage_stats();
$config_summary = $api->get_configuration_summary();
$validation = $api->validate_configuration();
?>

<div class="nexus-advanced-settings">
    
    <!-- Current Status Overview -->
    <div class="nexus-status-cards">
        <div class="nexus-status-card nexus-status-api">
            <h3>🔌 API Status</h3>
            <div class="nexus-status-item">
                <span class="nexus-status-label">Connection:</span>
                <span class="nexus-status-value <?php echo $config_summary['api_configured'] ? 'success' : 'error'; ?>">
                    <?php echo $config_summary['api_configured'] ? 'Connected' : 'Not Configured'; ?>
                </span>
            </div>
            <div class="nexus-status-item">
                <span class="nexus-status-label">Emergency Stop:</span>
                <span class="nexus-status-value <?php echo $config_summary['safety']['emergency_stop'] ? 'error' : 'success'; ?>">
                    <?php echo $config_summary['safety']['emergency_stop'] ? 'ACTIVE' : 'Inactive'; ?>
                </span>
            </div>
            <div class="nexus-status-item">
                <span class="nexus-status-label">Model:</span>
                <span class="nexus-status-value"><?php echo esc_html($config_summary['model']); ?></span>
            </div>
        </div>
        
        <div class="nexus-status-card nexus-status-usage">
            <h3>📊 Usage Today</h3>
            <div class="nexus-usage-bar">
                <div class="nexus-usage-progress">
                    <div class="nexus-usage-fill" style="width: <?php echo $rate_status['percentages']['day']; ?>%"></div>
                </div>
                <span class="nexus-usage-text">
                    <?php echo $rate_status['day_calls']; ?> / <?php echo $rate_status['day_limit']; ?> calls
                    (<?php echo $rate_status['percentages']['day']; ?>%)
                </span>
            </div>
            
            <div class="nexus-usage-bar">
                <div class="nexus-usage-progress">
                    <div class="nexus-usage-fill" style="width: <?php echo $rate_status['percentages']['hour']; ?>%"></div>
                </div>
                <span class="nexus-usage-text">
                    <?php echo $rate_status['hour_calls']; ?> / <?php echo $rate_status['hour_limit']; ?> calls this hour
                    (<?php echo $rate_status['percentages']['hour']; ?>%)
                </span>
            </div>
        </div>
        
        <div class="nexus-status-card nexus-status-limits">
            <h3>⚡ Rate Limits</h3>
            <div class="nexus-status-item">
                <span class="nexus-status-label">Can Request:</span>
                <span class="nexus-status-value <?php echo $rate_status['can_make_request'] ? 'success' : 'error'; ?>">
                    <?php echo $rate_status['can_make_request'] ? 'Yes' : 'No'; ?>
                </span>
            </div>
            <div class="nexus-status-item">
                <span class="nexus-status-label">Next Request:</span>
                <span class="nexus-status-value">
                    <?php 
                    $next_time = $rate_status['time_until_next'];
                    if ($next_time <= 0) {
                        echo 'Now';
                    } elseif ($next_time == -1) {
                        echo 'Emergency Stop';
                    } else {
                        echo $next_time . 's';
                    }
                    ?>
                </span>
            </div>
            <div class="nexus-status-item">
                <span class="nexus-status-label">Min Interval:</span>
                <span class="nexus-status-value"><?php echo $config_summary['limits']['min_interval']; ?>s</span>
            </div>
        </div>
    </div>
    
    <!-- Configuration Validation -->
    <?php if (!$validation['valid']): ?>
    <div class="notice notice-warning">
        <h4>⚠️ Configuration Issues</h4>
        <ul>
            <?php foreach ($validation['issues'] as $issue): ?>
                <li><?php echo esc_html($issue); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <!-- Advanced API Settings Form -->
    <form method="post" action="options.php" class="nexus-advanced-form">
        <?php settings_fields('nexus_translator_api_settings'); ?>
        
        <div class="nexus-settings-section">
            <h3>🔧 API Configuration</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">Max Tokens</th>
                    <td>
                        <input type="number" 
                               name="nexus_translator_api_settings[max_tokens]" 
                               value="<?php echo esc_attr($config_summary['max_tokens']); ?>" 
                               min="100" 
                               max="8000" 
                               class="small-text" />
                        <p class="description">Maximum tokens per API request (100-8000). Higher = longer responses, more cost.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Temperature</th>
                    <td>
                        <input type="number" 
                               name="nexus_translator_api_settings[temperature]" 
                               value="<?php echo esc_attr($config_summary['temperature']); ?>" 
                               min="0" 
                               max="1" 
                               step="0.1" 
                               class="small-text" />
                        <p class="description">Translation creativity (0.0-1.0). Lower = more consistent, Higher = more creative.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Request Timeout</th>
                    <td>
                        <input type="number" 
                               name="nexus_translator_api_settings[request_timeout]" 
                               value="<?php echo esc_attr($config_summary['limits']['timeout']); ?>" 
                               min="30" 
                               max="300" 
                               class="small-text" />
                        <span> seconds</span>
                        <p class="description">How long to wait for API response (30-300s). Longer content needs more time.</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="nexus-settings-section">
            <h3>🛡️ Rate Limiting & Protection</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">Max Calls Per Hour</th>
                    <td>
                        <input type="number" 
                               name="nexus_translator_api_settings[max_calls_per_hour]" 
                               value="<?php echo esc_attr($config_summary['limits']['calls_per_hour']); ?>" 
                               min="1" 
                               max="1000" 
                               class="small-text" />
                        <p class="description">Maximum API calls allowed per hour. Prevents unexpected charges.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Max Calls Per Day</th>
                    <td>
                        <input type="number" 
                               name="nexus_translator_api_settings[max_calls_per_day]" 
                               value="<?php echo esc_attr($config_summary['limits']['calls_per_day']); ?>" 
                               min="1" 
                               max="10000" 
                               class="small-text" />
                        <p class="description">Maximum API calls allowed per day. Should be higher than hourly limit.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Minimum Request Interval</th>
                    <td>
                        <input type="number" 
                               name="nexus_translator_api_settings[min_request_interval]" 
                               value="<?php echo esc_attr($config_summary['limits']['min_interval']); ?>" 
                               min="1" 
                               max="60" 
                               class="small-text" />
                        <span> seconds</span>
                        <p class="description">Minimum time between API requests. Prevents rate limiting.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Emergency Stop Threshold</th>
                    <td>
                        <input type="number" 
                               name="nexus_translator_api_settings[emergency_stop_threshold]" 
                               value="<?php echo esc_attr($config_summary['limits']['emergency_threshold']); ?>" 
                               min="5" 
                               max="100" 
                               class="small-text" />
                        <span> calls per hour</span>
                        <p class="description">Automatically stop translations if this many calls made in one hour.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Translation Cooldown</th>
                    <td>
                        <input type="number" 
                               name="nexus_translator_api_settings[translation_cooldown]" 
                               value="<?php echo esc_attr($config_summary['limits']['translation_cooldown']); ?>" 
                               min="60" 
                               max="3600" 
                               class="small-text" />
                        <span> seconds</span>
                        <p class="description">Prevent re-translating same post for this duration (60-3600s).</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <?php submit_button('Save Advanced Settings'); ?>
    </form>
    
    <!-- Emergency Controls -->
    <div class="nexus-emergency-section">
        <h3>🚨 Emergency Controls</h3>
        <div class="nexus-emergency-controls">
            <?php if ($config_summary['safety']['emergency_stop']): ?>
                <div class="nexus-emergency-active">
                    <p><strong>🚨 Emergency Stop is ACTIVE</strong></p>
                    <p>All translations are currently disabled.</p>
                    <button type="button" id="nexus-reset-emergency" class="button button-primary">
                        Deactivate Emergency Stop
                    </button>
                </div>
            <?php else: ?>
                <button type="button" id="nexus-trigger-emergency" class="button button-secondary">
                    Activate Emergency Stop
                </button>
            <?php endif; ?>
            
            <button type="button" id="nexus-reset-limits" class="button button-secondary">
                Reset All Rate Limits
            </button>
            
            <button type="button" id="nexus-test-api-advanced" class="button button-secondary">
                Test API Connection
            </button>
        </div>
        
        <div id="nexus-emergency-result" style="margin-top: 15px;"></div>
    </div>
    
    <!-- Configuration Export/Import -->
    <div class="nexus-config-section">
        <h3>💾 Configuration Backup</h3>
        <div class="nexus-config-controls">
            <button type="button" id="nexus-export-config" class="button">
                Export Configuration
            </button>
            
            <div class="nexus-import-section">
                <label for="nexus-config-file">Import Configuration:</label>
                <input type="file" id="nexus-config-file" accept=".json" />
                <button type="button" id="nexus-import-config" class="button" disabled>
                    Import Configuration
                </button>
            </div>
        </div>
        
        <div id="nexus-config-result" style="margin-top: 15px;"></div>
    </div>
    
    <!-- Current Configuration Display -->
    <div class="nexus-config-display">
        <h3>📋 Current Configuration</h3>
        <textarea readonly class="widefat" rows="15"><?php echo esc_textarea(json_encode($config_summary, JSON_PRETTY_PRINT)); ?></textarea>
    </div>
</div>

<style>
.nexus-advanced-settings {
    max-width: 1200px;
}

.nexus-status-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.nexus-status-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.nexus-status-card h3 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #333;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

.nexus-status-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    padding: 8px 0;
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

.nexus-usage-bar {
    margin-bottom: 15px;
}

.nexus-usage-progress {
    width: 100%;
    height: 8px;
    background: #f0f0f0;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 5px;
}

.nexus-usage-fill {
    height: 100%;
    background: linear-gradient(90deg, #46b450, #0073aa);
    border-radius: 4px;
    transition: width 0.3s ease;
}

.nexus-usage-text {
    font-size: 12px;
    color: #666;
}

.nexus-settings-section {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.nexus-settings-section h3 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #333;
    border-bottom: 2px solid #0073aa;
    padding-bottom: 10px;
}

.nexus-emergency-section {
    background: #fff8e1;
    border: 2px solid #ffb900;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.nexus-emergency-active {
    background: #ffeaa7;
    border: 1px solid #fdcb6e;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 15px;
}

.nexus-emergency-controls {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.nexus-config-section {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.nexus-config-controls {
    display: flex;
    gap: 15px;
    align-items: end;
    flex-wrap: wrap;
    margin-bottom: 15px;
}

.nexus-import-section {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.nexus-config-display {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.nexus-config-display textarea {
    font-family: monospace;
    font-size: 12px;
    background: #f8f9fa;
}

@media (max-width: 768px) {
    .nexus-status-cards {
        grid-template-columns: 1fr;
    }
    
    .nexus-emergency-controls {
        flex-direction: column;
    }
    
    .nexus-config-controls {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    
    // Emergency stop toggle
    $('#nexus-trigger-emergency').on('click', function() {
        if (confirm('This will immediately stop all translations. Continue?')) {
            $.post(ajaxurl, {
                action: 'nexus_emergency_stop',
                activate: true,
                nonce: nexusTranslator.nonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Failed to activate emergency stop: ' + response.data);
                }
            });
        }
    });
    
    $('#nexus-reset-emergency').on('click', function() {
        if (confirm('This will reactivate translations. Continue?')) {
            $.post(ajaxurl, {
                action: 'nexus_emergency_stop',
                activate: false,
                nonce: nexusTranslator.nonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Failed to deactivate emergency stop: ' + response.data);
                }
            });
        }
    });
    
    // Reset rate limits
    $('#nexus-reset-limits').on('click', function() {
        if (confirm('This will reset all rate limiting counters. Continue?')) {
            $.post(ajaxurl, {
                action: 'nexus_reset_rate_limits',
                nonce: nexusTranslator.nonce
            }, function(response) {
                if (response.success) {
                    $('#nexus-emergency-result').html('<div class="notice notice-success inline"><p>Rate limits reset successfully!</p></div>');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    $('#nexus-emergency-result').html('<div class="notice notice-error inline"><p>Failed to reset rate limits: ' + response.data + '</p></div>');
                }
            });
        }
    });
    
    // Advanced API test
    $('#nexus-test-api-advanced').on('click', function() {
        var $btn = $(this);
        var $result = $('#nexus-emergency-result');
        
        $btn.prop('disabled', true).text('Testing...');
        $result.empty();
        
        $.post(ajaxurl, {
            action: 'nexus_test_api_connection',
            nonce: nexusTranslator.nonce
        }, function(response) {
            if (response.success) {
                var html = '<div class="notice notice-success inline">';
                html += '<p><strong>API Test Successful!</strong></p>';
                html += '<p><strong>Test Translation:</strong> ' + response.data.test_translation + '</p>';
                if (response.data.usage) {
                    html += '<p><small>Tokens used: ' + response.data.usage.input_tokens + ' input, ' + response.data.usage.output_tokens + ' output</small></p>';
                }
                html += '</div>';
                $result.html(html);
            } else {
                $result.html('<div class="notice notice-error inline"><p><strong>API Test Failed:</strong> ' + response.data + '</p></div>');
            }
        }).always(function() {
            $btn.prop('disabled', false).text('Test API Connection');
        });
    });
    
    // Configuration export
    $('#nexus-export-config').on('click', function() {
        $.post(ajaxurl, {
            action: 'nexus_export_config',
            nonce: nexusTranslator.nonce
        }, function(response) {
            if (response.success) {
                var dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(response.data, null, 2));
                var downloadAnchorNode = document.createElement('a');
                downloadAnchorNode.setAttribute("href", dataStr);
                downloadAnchorNode.setAttribute("download", "nexus-translator-config-" + new Date().toISOString().split('T')[0] + ".json");
                document.body.appendChild(downloadAnchorNode);
                downloadAnchorNode.click();
                downloadAnchorNode.remove();
                
                $('#nexus-config-result').html('<div class="notice notice-success inline"><p>Configuration exported successfully!</p></div>');
            } else {
                $('#nexus-config-result').html('<div class="notice notice-error inline"><p>Export failed: ' + response.data + '</p></div>');
            }
        });
    });
    
    // Configuration import
    $('#nexus-config-file').on('change', function() {
        $('#nexus-import-config').prop('disabled', !this.files.length);
    });
    
    $('#nexus-import-config').on('click', function() {
        var fileInput = $('#nexus-config-file')[0];
        if (!fileInput.files.length) return;
        
        var file = fileInput.files[0];
        var reader = new FileReader();
        
        reader.onload = function(e) {
            try {
                var configData = JSON.parse(e.target.result);
                
                $.post(ajaxurl, {
                    action: 'nexus_import_config',
                    config_data: configData,
                    nonce: nexusTranslator.nonce
                }, function(response) {
                    if (response.success) {
                        $('#nexus-config-result').html('<div class="notice notice-success inline"><p>Configuration imported successfully! Refreshing page...</p></div>');
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $('#nexus-config-result').html('<div class="notice notice-error inline"><p>Import failed: ' + response.data + '</p></div>');
                    }
                });
            } catch (err) {
                $('#nexus-config-result').html('<div class="notice notice-error inline"><p>Invalid JSON file: ' + err.message + '</p></div>');
            }
        };
        
        reader.readAsText(file);
    });
});
</script>