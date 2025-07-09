/**
 * File: admin-script.js
 * Location: /admin/js/admin-script.js
 * 
 * Complete Enhanced Admin JavaScript for Nexus AI Translator
 */

(function($) {
    'use strict';
    
    const NexusAdmin = {
        
        // Configuration
        config: {
            refreshInterval: 60000, // 1 minute
            maxRetries: 3,
            retryDelay: 1000
        },
        
        // State management
        state: {
            isRefreshing: false,
            activeRequests: 0,
            lastAnalyticsUpdate: null
        },
        
        init: function() {
            console.log('Nexus Admin: Initializing enhanced admin interface');
            
            this.bindEvents();
            this.initTabs();
            this.initValidation();
            this.loadAnalytics();
            this.initFileImport();
            this.initRealTimeUpdates();
            
            console.log('Nexus Admin: Initialization complete');
        },
        
        bindEvents: function() {
            // API Testing
            $('#test-api-connection').on('click', this.testApiConnection.bind(this));
            
            // Rate Limit Management
            $('#reset-rate-limits, #reset-all-limits').on('click', this.resetRateLimits.bind(this));
            $('#reset-emergency-stop, #reset-emergency, #deactivate-emergency').on('click', this.resetEmergencyStop.bind(this));
            
            // Analytics
            $('#refresh-analytics').on('click', this.refreshAnalytics.bind(this));
            $('#export-analytics').on('click', this.exportAnalytics.bind(this));
            $('#clear-analytics').on('click', this.clearAnalytics.bind(this));
            
            // Advanced Controls
            $('#test-api-advanced').on('click', this.testApiConnection.bind(this));
            $('#export-config').on('click', this.exportConfig.bind(this));
            $('#import-config').on('click', this.importConfig.bind(this));
            $('#cleanup-locks').on('click', this.cleanupLocks.bind(this));
            $('#validate-config').on('click', this.validateConfig.bind(this));
            $('#emergency-cleanup').on('click', this.emergencyCleanup.bind(this));
            
            // Translation Actions (from meta box)
            $(document).on('click', '.nexus-translate-btn', this.handleTranslation.bind(this));
            $(document).on('click', '.nexus-update-translation', this.handleUpdateTranslation.bind(this));
            $(document).on('click', '#nexus-manual-translate', this.handleManualTranslation.bind(this));
            
            // Bulk Operations
            $('#nexus-bulk-translate').on('click', this.handleBulkTranslation.bind(this));
            $(document).on('click', '.nexus-translate-link', this.handleRowTranslation.bind(this));
            
            // File handling
            $('#config-file').on('change', this.handleFileSelect.bind(this));
            
            // Form validation
            $('input[name*="nexus_translator_api_settings"]').on('input', this.validateField.bind(this));
            
            // Settings form submission
            $('form').on('submit', this.handleFormSubmit.bind(this));
        },
        
        initTabs: function() {
            // Handle tab switching with hash
            if (window.location.hash) {
                const tab = window.location.hash.replace('#', '');
                const tabLink = $('.nav-tab[href*="tab=' + tab + '"]');
                if (tabLink.length) {
                    tabLink.addClass('nav-tab-active').siblings().removeClass('nav-tab-active');
                }
            }
            
            // Update hash on tab click
            $('.nav-tab').on('click', function() {
                const href = $(this).attr('href');
                const tab = href.split('tab=')[1];
                if (tab) {
                    window.location.hash = tab;
                }
                
                // Update active state
                $(this).addClass('nav-tab-active').siblings().removeClass('nav-tab-active');
            });
        },
        
        initValidation: function() {
            // Add validation indicators to API settings
            $('input[name*="nexus_translator_api_settings"]').each(function() {
                if (!$(this).next('.nexus-validation').length) {
                    $(this).after('<span class="nexus-validation"></span>');
                }
            });
            
            // Initial validation
            this.validateAllFields();
        },
        
        initRealTimeUpdates: function() {
            // Auto-refresh analytics if on analytics tab
            if ($('.nexus-analytics-dashboard').length) {
                setInterval(() => {
                    if (!this.state.isRefreshing) {
                        this.refreshAnalytics(true);
                    }
                }, this.config.refreshInterval);
            }
            
            // Monitor rate limits on API settings page
            if ($('.nexus-rate-limiting-info').length) {
                setInterval(() => {
                    this.updateRateLimitDisplay();
                }, 30000); // Every 30 seconds
            }
        },
        
        // API Testing
        testApiConnection: function(e) {
            e.preventDefault();
            
            const button = $(e.target);
            const originalText = button.text();
            const resultDiv = $('#api-test-result, #nexus-emergency-result').first();
            
            this.setButtonState(button, 'loading', nexusTranslator.strings.processing);
            resultDiv.empty();
            
            const apiKey = $('#claude_api_key').val();
            if (!apiKey) {
                this.showResult(resultDiv, 'error', 'Please enter an API key first');
                this.setButtonState(button, 'normal', originalText);
                return;
            }
            
            this.makeRequest('nexus_test_api_connection', {
                api_key: apiKey
            })
            .done((response) => {
                if (response.success) {
                    let message = 'API connection successful!';
                    if (response.data.model) {
                        message += '\nModel: ' + response.data.model;
                    }
                    if (response.data.test_translation) {
                        message += '\nTest translation: ' + response.data.test_translation.substring(0, 100) + '...';
                    }
                    this.showResult(resultDiv, 'success', message);
                } else {
                    this.showResult(resultDiv, 'error', 'API connection failed: ' + response.data);
                }
            })
            .fail(() => {
                this.showResult(resultDiv, 'error', 'Connection test failed');
            })
            .always(() => {
                this.setButtonState(button, 'normal', originalText);
            });
        },
        
        // Rate Limit Management
        resetRateLimits: function(e) {
            e.preventDefault();
            
            if (!confirm(nexusTranslator.strings.confirmReset || 'Are you sure you want to reset rate limits?')) {
                return;
            }
            
            const button = $(e.target);
            const originalText = button.text();
            
            this.setButtonState(button, 'loading', nexusTranslator.strings.processing);
            
            this.makeRequest('nexus_reset_rate_limits', {})
            .done((response) => {
                if (response.success) {
                    this.showNotice('success', response.data.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    this.showNotice('error', response.data);
                }
            })
            .fail(() => {
                this.showNotice('error', 'Failed to reset rate limits');
            })
            .always(() => {
                this.setButtonState(button, 'normal', originalText);
            });
        },
        
        resetEmergencyStop: function(e) {
            e.preventDefault();
            
            if (!confirm(nexusTranslator.strings.confirmEmergency || 'Are you sure you want to reset emergency stop?')) {
                return;
            }
            
            const button = $(e.target);
            const originalText = button.text();
            
            this.setButtonState(button, 'loading', nexusTranslator.strings.processing);
            
            this.makeRequest('nexus_reset_emergency', {})
            .done((response) => {
                if (response.success) {
                    this.showNotice('success', response.data.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    this.showNotice('error', response.data);
                }
            })
            .fail(() => {
                this.showNotice('error', 'Failed to reset emergency stop');
            })
            .always(() => {
                this.setButtonState(button, 'normal', originalText);
            });
        },
        
        // Analytics
        loadAnalytics: function() {
            if (!$('.nexus-analytics-dashboard').length) {
                return;
            }
            
            this.refreshAnalytics(true);
        },
        
        refreshAnalytics: function(silent = false) {
            if (this.state.isRefreshing) {
                return;
            }
            
            this.state.isRefreshing = true;
            
            if (!silent) {
                const button = $('#refresh-analytics');
                this.setButtonState(button, 'loading', nexusTranslator.strings.processing);
            }
            
            this.makeRequest('nexus_get_translation_analytics', {
                days: 30
            })
            .done((response) => {
                if (response.success) {
                    this.updateAnalyticsDisplay(response.data);
                    this.state.lastAnalyticsUpdate = Date.now();
                    
                    if (!silent) {
                        this.showNotice('success', 'Analytics refreshed');
                    }
                }
            })
            .fail(() => {
                if (!silent) {
                    this.showNotice('error', 'Failed to refresh analytics');
                }
            })
            .always(() => {
                this.state.isRefreshing = false;
                
                if (!silent) {
                    const button = $('#refresh-analytics');
                    this.setButtonState(button, 'normal', 'Refresh Analytics');
                }
            });
        },
        
        updateAnalyticsDisplay: function(data) {
            // Update summary cards
            $('.nexus-summary-number').each(function() {
                const card = $(this).closest('.nexus-summary-card');
                const cardTitle = card.find('h3').text().toLowerCase();
                
                if (cardTitle.includes('total')) {
                    $(this).text(NexusAdmin.formatNumber(data.totals.requests));
                } else if (cardTitle.includes('success')) {
                    const rate = data.totals.requests > 0 ? 
                        ((data.totals.successful / data.totals.requests) * 100).toFixed(1) : 0;
                    $(this).text(rate + '%');
                    card.find('.nexus-summary-subtitle').text(
                        NexusAdmin.formatNumber(data.totals.successful) + ' / ' + NexusAdmin.formatNumber(data.totals.requests)
                    );
                } else if (cardTitle.includes('tokens')) {
                    $(this).text(NexusAdmin.formatNumber(data.totals.tokens));
                } else if (cardTitle.includes('failed')) {
                    $(this).text(NexusAdmin.formatNumber(data.totals.failed));
                    const errorRate = data.totals.requests > 0 ? 
                        ((data.totals.failed / data.totals.requests) * 100).toFixed(1) : 0;
                    card.find('.nexus-summary-subtitle').text('Error rate: ' + errorRate + '%');
                }
            });
            
            // Update language breakdown
            if (data.language_breakdown) {
                this.updateLanguageBreakdown(data.language_breakdown);
            }
            
            // Update recent activity
            if (data.recent_activity && data.recent_activity.length > 0) {
                this.updateRecentActivity(data.recent_activity);
            }
        },
        
        updateLanguageBreakdown: function(breakdown) {
            const container = $('.nexus-language-stats');
            if (!container.length) return;
            
            container.empty();
            
            Object.keys(breakdown).forEach(function(lang) {
                const stats = breakdown[lang];
                const successRate = stats.total > 0 ? ((stats.successful / stats.total) * 100).toFixed(1) : 0;
                
                const itemHtml = `
                    <div class="nexus-lang-stat-item">
                        <div class="nexus-lang-header">
                            <span class="nexus-lang-flag">${NexusAdmin.getLanguageFlag(lang)}</span>
                            <span class="nexus-lang-name">${NexusAdmin.getLanguageName(lang)}</span>
                            <span class="nexus-lang-total">${NexusAdmin.formatNumber(stats.total)} translations</span>
                        </div>
                        <div class="nexus-lang-breakdown">
                            <div class="nexus-lang-success">✅ ${NexusAdmin.formatNumber(stats.successful)} successful (${successRate}%)</div>
                            <div class="nexus-lang-failed">❌ ${NexusAdmin.formatNumber(stats.failed)} failed</div>
                        </div>
                    </div>
                `;
                
                container.append(itemHtml);
            });
        },
        
        updateRecentActivity: function(activities) {
            const container = $('.nexus-recent-activity');
            if (!container.length) return;
            
            container.empty();
            
            activities.slice(0, 10).forEach(function(activity) {
                const timeAgo = NexusAdmin.timeAgo(activity.timestamp * 1000);
                const statusIcon = activity.success ? '✅' : '❌';
                const statusClass = activity.success ? 'success' : 'failed';
                const actionText = activity.success ? 'Translated' : 'Failed to translate';
                
                const activityHtml = `
                    <div class="nexus-activity-item ${statusClass}">
                        <div class="nexus-activity-icon">${statusIcon}</div>
                        <div class="nexus-activity-details">
                            <div class="nexus-activity-main">
                                ${actionText} post #${activity.post_id} to ${NexusAdmin.getLanguageName(activity.target_language)}
                            </div>
                            <div class="nexus-activity-meta">
                                <span class="nexus-activity-user">${activity.user_login}</span>
                                <span class="nexus-activity-time">${timeAgo}</span>
                                ${activity.tokens_input ? `<span class="nexus-activity-tokens">${activity.tokens_input + activity.tokens_output} tokens</span>` : ''}
                            </div>
                        </div>
                    </div>
                `;
                
                container.append(activityHtml);
            });
        },
        
        exportAnalytics: function(e) {
            e.preventDefault();
            
            const button = $(e.target);
            const originalText = button.text();
            
            this.setButtonState(button, 'loading', nexusTranslator.strings.processing);
            
            this.makeRequest('nexus_export_analytics', {
                format: 'json',
                days: 30
            })
            .done((response) => {
                if (response.success) {
                    this.downloadFile(response.data.data, response.data.filename, response.data.mime_type);
                    this.showNotice('success', 'Analytics exported successfully');
                } else {
                    this.showNotice('error', response.data);
                }
            })
            .fail(() => {
                this.showNotice('error', 'Failed to export analytics');
            })
            .always(() => {
                this.setButtonState(button, 'normal', originalText);
            });
        },
        
        clearAnalytics: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to clear old analytics data? This cannot be undone.')) {
                return;
            }
            
            const button = $(e.target);
            const originalText = button.text();
            
            this.setButtonState(button, 'loading', nexusTranslator.strings.processing);
            
            this.makeRequest('nexus_clear_analytics', {})
            .done((response) => {
                if (response.success) {
                    this.showNotice('success', response.data.message);
                    this.refreshAnalytics();
                } else {
                    this.showNotice('error', response.data);
                }
            })
            .fail(() => {
                this.showNotice('error', 'Failed to clear analytics');
            })
            .always(() => {
                this.setButtonState(button, 'normal', originalText);
            });
        },
        
        // Configuration Management
        exportConfig: function(e) {
            e.preventDefault();
            
            const button = $(e.target);
            const originalText = button.text();
            
            this.setButtonState(button, 'loading', nexusTranslator.strings.processing);
            
            this.makeRequest('nexus_export_config', {})
            .done((response) => {
                if (response.success) {
                    const configData = JSON.stringify(response.data.config, null, 2);
                    this.downloadFile(configData, response.data.filename, 'application/json');
                    this.showNotice('success', 'Configuration exported successfully');
                } else {
                    this.showNotice('error', response.data);
                }
            })
            .fail(() => {
                this.showNotice('error', 'Failed to export configuration');
            })
            .always(() => {
                this.setButtonState(button, 'normal', originalText);
            });
        },
        
        initFileImport: function() {
            $('#config-file').on('change', function() {
                const importButton = $('#import-config');
                const file = this.files[0];
                
                if (file) {
                    if (file.type !== 'application/json' && !file.name.endsWith('.json')) {
                        NexusAdmin.showNotice('error', 'Please select a JSON configuration file');
                        importButton.prop('disabled', true);
                        return;
                    }
                    importButton.prop('disabled', false);
                } else {
                    importButton.prop('disabled', true);
                }
            });
        },
        
        handleFileSelect: function(e) {
            const file = e.target.files[0];
            const importButton = $('#import-config');
            
            if (file) {
                if (file.type !== 'application/json' && !file.name.endsWith('.json')) {
                    this.showNotice('error', 'Please select a JSON configuration file');
                    importButton.prop('disabled', true);
                    return;
                }
                importButton.prop('disabled', false);
            } else {
                importButton.prop('disabled', true);
            }
        },
        
        importConfig: function(e) {
            e.preventDefault();
            
            const fileInput = $('#config-file')[0];
            const file = fileInput.files[0];
            
            if (!file) {
                this.showNotice('error', 'Please select a configuration file');
                return;
            }
            
            const button = $(e.target);
            const originalText = button.text();
            const resultDiv = $('#nexus-config-result');
            
            this.setButtonState(button, 'loading', nexusTranslator.strings.processing);
            resultDiv.empty();
            
            const reader = new FileReader();
            reader.onload = (e) => {
                try {
                    const configData = JSON.parse(e.target.result);
                    
                    this.makeRequest('nexus_import_config', {
                        config_data: JSON.stringify(configData)
                    })
                    .done((response) => {
                        if (response.success) {
                            this.showResult(resultDiv, 'success', response.data.message);
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            this.showResult(resultDiv, 'error', response.data);
                        }
                    })
                    .fail(() => {
                        this.showResult(resultDiv, 'error', 'Failed to import configuration');
                    })
                    .always(() => {
                        this.setButtonState(button, 'normal', originalText);
                    });
                } catch (error) {
                    this.showResult(resultDiv, 'error', 'Invalid JSON file');
                    this.setButtonState(button, 'normal', originalText);
                }
            };
            
            reader.readAsText(file);
        },
        
        // Advanced Management
        cleanupLocks: function(e) {
            e.preventDefault();
            
            if (!confirm('Remove all stale translation locks? This will reset stuck translations.')) {
                return;
            }
            
            const button = $(e.target);
            const originalText = button.text();
            
            this.setButtonState(button, 'loading', nexusTranslator.strings.processing);
            
            this.makeRequest('nexus_cleanup_locks', {})
            .done((response) => {
                if (response.success) {
                    this.showNotice('success', response.data.message);
                } else {
                    this.showNotice('error', response.data);
                }
            })
            .fail(() => {
                this.showNotice('error', 'Failed to cleanup locks');
            })
            .always(() => {
                this.setButtonState(button, 'normal', originalText);
            });
        },
        
        validateConfig: function(e) {
            e.preventDefault();
            
            const button = $(e.target);
            const originalText = button.text();
            const resultDiv = $('#nexus-config-result, #nexus-emergency-result').first();
            
            this.setButtonState(button, 'loading', 'Validating...');
            resultDiv.empty();
            
            this.makeRequest('nexus_validate_config', {})
            .done((response) => {
                if (response.success) {
                    const validation = response.data;
                    let message = '';
                    let type = 'success';
                    
                    if (validation.valid) {
                        message = '✅ Configuration is valid!';
                        if (validation.api_status === 'connected') {
                            message += '\n🔗 API connection verified';
                        }
                    } else {
                        type = 'error';
                        message = '❌ Configuration issues found:\n• ';
                        message += validation.issues.join('\n• ');
                    }
                    
                    if (validation.warnings && validation.warnings.length > 0) {
                        message += '\n\n⚠️ Warnings:\n• ' + validation.warnings.join('\n• ');
                        if (type === 'success') type = 'warning';
                    }
                    
                    this.showResult(resultDiv, type, message);
                } else {
                    this.showResult(resultDiv, 'error', response.data);
                }
            })
            .fail(() => {
                this.showResult(resultDiv, 'error', 'Failed to validate configuration');
            })
            .always(() => {
                this.setButtonState(button, 'normal', originalText);
            });
        },
        
        emergencyCleanup: function(e) {
            e.preventDefault();
            
            const confirmText = '⚠️ EMERGENCY CLEANUP\n\n' +
                'This will:\n' +
                '• Remove all translation locks\n' +
                '• Reset stuck translations\n' +
                '• Clear rate limits\n' +
                '• Reset emergency stop\n\n' +
                'Continue?';
                
            if (!confirm(confirmText)) {
                return;
            }
            
            const button = $(e.target);
            const originalText = button.text();
            const resultDiv = $('#nexus-emergency-result');
            
            this.setButtonState(button, 'loading', 'Cleaning up...');
            resultDiv.empty();
            
            this.makeRequest('nexus_emergency_cleanup', {})
            .done((response) => {
                if (response.success) {
                    let message = response.data.message + '\n\nActions taken:\n• ';
                    message += response.data.actions.join('\n• ');
                    this.showResult(resultDiv, 'success', message);
                    
                    setTimeout(() => {
                        if (confirm('Emergency cleanup completed. Reload page to see changes?')) {
                            location.reload();
                        }
                    }, 2000);
                } else {
                    this.showResult(resultDiv, 'error', response.data);
                }
            })
            .fail(() => {
                this.showResult(resultDiv, 'error', 'Emergency cleanup failed');
            })
            .always(() => {
                this.setButtonState(button, 'normal', originalText);
            });
        },
        
        // Translation Handling
        handleTranslation: function(e) {
            e.preventDefault();
            
            const button = $(e.target);
            const postId = button.data('post-id');
            const targetLang = button.data('target-lang');
            
            if (!postId || !targetLang) {
                this.showNotice('error', 'Invalid parameters for translation');
                return;
            }
            
            if (!confirm(`Translate this post to ${this.getLanguageName(targetLang)}?`)) {
                return;
            }
            
            this.startTranslation(postId, targetLang, button);
        },
        
        handleUpdateTranslation: function(e) {
            e.preventDefault();
            
            const button = $(e.target);
            const originalId = button.data('original-id');
            const translatedId = button.data('translated-id');
            
            if (!confirm('This will update the existing translation with new content. Continue?')) {
                return;
            }
            
            this.updateTranslation(originalId, translatedId, button);
        },
        
        handleManualTranslation: function(e) {
            e.preventDefault();
            
            const selectedLanguages = $('input[name="nexus_target_languages[]"]:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (selectedLanguages.length === 0) {
                this.showNotice('error', 'Please select at least one target language');
                return;
            }
            
            const postId = $('#post_ID').val() || $('input[name="post_ID"]').val();
            if (!postId) {
                this.showNotice('error', 'Post ID not found');
                return;
            }
            
            const button = $(e.target);
            const confirmText = `Translate to ${selectedLanguages.length} language(s): ${selectedLanguages.map(lang => this.getLanguageName(lang)).join(', ')}?`;
            
            if (!confirm(confirmText)) {
                return;
            }
            
            this.startBulkTranslation(postId, selectedLanguages, button);
        },
        
        startTranslation: function(postId, targetLang, button) {
            const originalText = button.text();
            
            this.setButtonState(button, 'loading', 'Translating...');
            this.showProgress('Preparing translation...');
            
            this.makeRequest('nexus_translate_post', {
                post_id: postId,
                target_language: targetLang
            })
            .done((response) => {
                if (response.success) {
                    this.showTranslationSuccess(response.data);
                    this.refreshMetaBox();
                } else {
                    this.showTranslationError(response.data);
                }
            })
            .fail(() => {
                this.showTranslationError('Server error occurred');
            })
            .always(() => {
                this.setButtonState(button, 'normal', originalText);
                this.hideProgress();
            });
        },
        
        updateTranslation: function(originalId, translatedId, button) {
            const originalText = button.text();
            
            this.setButtonState(button, 'loading', 'Updating...');
            this.showProgress('Updating translation...');
            
            this.makeRequest('nexus_update_translation', {
                original_post_id: originalId,
                translated_post_id: translatedId
            })
            .done((response) => {
                if (response.success) {
                    this.showTranslationSuccess(response.data);
                    this.updateTranslationStatus(button, 'completed');
                } else {
                    this.showTranslationError(response.data);
                }
            })
            .fail(() => {
                this.showTranslationError('Update failed');
            })
            .always(() => {
                this.setButtonState(button, 'normal', originalText);
                this.hideProgress();
            });
        },
        
        startBulkTranslation: function(postId, languages, button) {
            const originalText = button.text();
            
            this.setButtonState(button, 'loading', 'Starting...');
            
            this.makeRequest('nexus_bulk_translate', {
                post_ids: [postId],
                languages: languages
            })
            .done((response) => {
                if (response.success) {
                    this.showNotice('success', response.data.message);
                    this.monitorBulkProgress(response.data.batch_id);
                } else {
                    this.showNotice('error', response.data);
                }
            })
            .fail(() => {
                this.showNotice('error', 'Failed to start bulk translation');
            })
            .always(() => {
                this.setButtonState(button, 'normal', originalText);
            });
        },
        
        monitorBulkProgress: function(batchId) {
            const checkProgress = () => {
                this.makeRequest('nexus_get_bulk_status', {
                    batch_id: batchId
                })
                .done((response) => {
                    if (response.success) {
                        const status = response.data;
                        const progress = (status.progress / status.total) * 100;
                        
                        this.showProgress(`Progress: ${status.completed}/${status.total} completed (${status.failed} failed)`, progress);
                        
                        if (status.status === 'completed') {
                            this.hideProgress();
                            this.showNotice('success', `Bulk translation completed: ${status.completed} successful, ${status.failed} failed`);
                            this.refreshMetaBox();
                        } else if (status.status === 'processing') {
                            setTimeout(checkProgress, 2000);
                        }
                    }
                });
            };
            
            setTimeout(checkProgress, 1000);
        },
        
        // Form Validation
        validateField: function(e) {
            const input = $(e.target);
            const name = input.attr('name');
            const value = input.val();
            const indicator = input.next('.nexus-validation');
            
            if (!indicator.length) return;
            
            const validation = this.getFieldValidation(name, value);
            
            if (validation.valid) {
                indicator.removeClass('invalid').addClass('valid').text('✓').attr('title', 'Valid');
                input.removeClass('error');
            } else {
                indicator.removeClass('valid').addClass('invalid').text('✗').attr('title', validation.message);
                input.addClass('error');
            }
        },
        
        getFieldValidation: function(name, value) {
            const val = parseFloat(value);
            
            if (name.includes('max_calls_per_hour')) {
                const dayLimit = parseInt($('input[name*="max_calls_per_day"]').val()) || 200;
                if (val > dayLimit) return { valid: false, message: 'Cannot exceed daily limit' };
                if (val > 1000) return { valid: false, message: 'Very high - check costs' };
                if (val < 1) return { valid: false, message: 'Must be at least 1' };
            }
            
            if (name.includes('max_calls_per_day')) {
                if (val > 5000) return { valid: false, message: 'Very high - check costs!' };
                if (val < 1) return { valid: false, message: 'Must be at least 1' };
            }
            
            if (name.includes('min_request_interval')) {
                if (val > 60) return { valid: false, message: 'Too long' };
                if (val < 0) return { valid: false, message: 'Cannot be negative' };
            }
            
            if (name.includes('request_timeout')) {
                if (val > 300) return { valid: false, message: 'Very long timeout' };
                if (val < 10) return { valid: false, message: 'Too short' };
            }
            
            if (name.includes('temperature')) {
                if (val > 1 || val < 0) return { valid: false, message: 'Must be 0-1' };
            }
            
            if (name.includes('max_tokens')) {
                if (val > 8000 || val < 100) return { valid: false, message: 'Must be 100-8000' };
            }
            
            if (name.includes('claude_api_key')) {
                if (value.length > 0 && value.length < 50) return { valid: false, message: 'API key seems too short' };
                if (value.length > 200) return { valid: false, message: 'API key too long' };
            }
            
            return { valid: true };
        },
        
        validateAllFields: function() {
            $('input[name*="nexus_translator_api_settings"]').each((i, el) => {
                this.validateField({ target: el });
            });
        },
        
        handleFormSubmit: function(e) {
            const form = $(e.target);
            
            // Check for validation errors
            const hasErrors = form.find('input.error').length > 0;
            if (hasErrors) {
                e.preventDefault();
                this.showNotice('error', 'Please fix validation errors before saving');
                return false;
            }
            
            // Show saving indicator
            const submitButton = form.find('input[type="submit"]');
            this.setButtonState(submitButton, 'loading', 'Saving...');
        },
        
        // Rate Limit Display Updates
        updateRateLimitDisplay: function() {
            this.makeRequest('nexus_get_usage_stats', {})
            .done((response) => {
                if (response.success) {
                    this.refreshRateLimitUI(response.data);
                }
            })
            .fail(() => {
                console.log('Failed to update rate limit display');
            });
        },
        
        refreshRateLimitUI: function(stats) {
            // Update usage values
            $('.nexus-usage-value[data-type="today"]').text(`${stats.calls_today} / ${stats.limit_day}`);
            $('.nexus-usage-value[data-type="hour"]').text(`${stats.calls_hour} / ${stats.limit_hour}`);
            
            // Update progress bars if they exist
            this.updateProgressBars(stats);
            
            // Check for warnings
            this.checkUsageWarnings(stats);
        },
        
        updateProgressBars: function(stats) {
            const hourPercent = Math.min(100, (stats.calls_hour / stats.limit_hour) * 100);
            const dayPercent = Math.min(100, (stats.calls_today / stats.limit_day) * 100);
            
            $('.nexus-usage-item').each(function() {
                const item = $(this);
                const progressBar = item.find('.nexus-progress-bar');
                if (!progressBar.length) return;
                
                const type = item.find('.nexus-usage-value').data('type');
                const percent = type === 'today' ? dayPercent : hourPercent;
                const color = percent >= 90 ? '#dc3545' : percent >= 75 ? '#ffc107' : '#007cba';
                
                progressBar.find('.nexus-progress-fill').css({
                    width: percent + '%',
                    'background-color': color
                });
            });
        },
        
        checkUsageWarnings: function(stats) {
            // Remove existing warnings
            $('.nexus-usage-warning').remove();
            
            const dayPercent = (stats.calls_today / stats.limit_day) * 100;
            const hourPercent = (stats.calls_hour / stats.limit_hour) * 100;
            
            if (dayPercent >= 90) {
                this.showUsageWarning('critical', `Daily limit almost reached (${Math.round(dayPercent)}%)`);
            } else if (dayPercent >= 75) {
                this.showUsageWarning('warning', `Daily usage is high (${Math.round(dayPercent)}%)`);
            }
            
            if (hourPercent >= 90) {
                this.showUsageWarning('critical', `Hourly limit almost reached (${Math.round(hourPercent)}%)`);
            }
        },
        
        showUsageWarning: function(level, message) {
            const className = level === 'critical' ? 'nexus-critical' : 'nexus-warning';
            const icon = level === 'critical' ? '🚨' : '⚠️';
            
            const warning = $(`<div class="nexus-usage-warning ${className}">${icon} ${message}</div>`);
            $('.nexus-current-usage').append(warning);
        },
        
        // Progress and Status Display
        showProgress: function(message, percent = null) {
            let progressHtml = '';
            
            if ($('#nexus-translation-progress').length) {
                const progressDiv = $('#nexus-translation-progress');
                progressDiv.find('.nexus-progress-text').text(message);
                
                if (percent !== null) {
                    progressDiv.find('.nexus-progress-fill').css('width', percent + '%');
                }
                
                progressDiv.show();
            } else if ($('#nexus-panel-progress').length) {
                const progressDiv = $('#nexus-panel-progress');
                progressDiv.find('.nexus-progress-text').text(message);
                progressDiv.show();
            }
        },
        
        hideProgress: function() {
            $('#nexus-translation-progress, #nexus-panel-progress').hide();
        },
        
        showTranslationSuccess: function(data) {
            const resultDiv = $('#nexus-translation-result, #nexus-panel-results').first();
            
            let html = `<p>${data.message || 'Translation completed successfully!'}</p>`;
            
            if (data.edit_link || data.view_link) {
                html += '<p>';
                if (data.edit_link) {
                    html += `<a href="${data.edit_link}" target="_blank" class="button button-small">Edit Translation</a> `;
                }
                if (data.view_link) {
                    html += `<a href="${data.view_link}" target="_blank" class="button button-small">View Translation</a>`;
                }
                html += '</p>';
            }
            
            if (data.usage) {
                html += `<p class="nexus-usage-info">Tokens used: ${data.usage.input_tokens || 0} input, ${data.usage.output_tokens || 0} output</p>`;
            }
            
            resultDiv.removeClass('error').addClass('success').html(html).show();
        },
        
        showTranslationError: function(message) {
            const resultDiv = $('#nexus-translation-result, #nexus-panel-results').first();
            resultDiv.removeClass('success').addClass('error').html(`<p>${message}</p>`).show();
        },
        
        updateTranslationStatus: function(button, status) {
            const item = button.closest('.nexus-translation-item');
            if (item.length) {
                item.removeClass('nexus-status-outdated nexus-status-pending nexus-status-error')
                    .addClass(`nexus-status-${status}`);
                
                item.find('.nexus-status')
                    .removeClass('nexus-status-outdated nexus-status-pending nexus-status-error')
                    .addClass(`nexus-status-${status}`)
                    .text(status.charAt(0).toUpperCase() + status.slice(1));
                
                if (status === 'completed') {
                    button.remove();
                }
            }
        },
        
        refreshMetaBox: function() {
            // Refresh translation meta box after successful translation
            setTimeout(() => {
                if ($('#nexus-translation-meta-box, #nexus-translation-panel').length) {
                    location.reload();
                }
            }, 2000);
        },
        
        // Utility Functions
        makeRequest: function(action, data, options = {}) {
            const defaults = {
                url: nexusTranslator.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                timeout: 30000,
                data: $.extend({
                    action: action,
                    nonce: nexusTranslator.nonce
                }, data)
            };
            
            const settings = $.extend(defaults, options);
            
            // Track active requests
            this.state.activeRequests++;
            
            return $.ajax(settings)
                .always(() => {
                    this.state.activeRequests--;
                })
                .fail((xhr, status, error) => {
                    console.error(`AJAX Error [${action}]:`, status, error);
                    
                    if (status === 'timeout') {
                        this.showNotice('error', 'Request timed out. Please try again.');
                    } else if (xhr.status === 0) {
                        this.showNotice('error', 'Network error. Please check your connection.');
                    }
                });
        },
        
        setButtonState: function(button, state, text = null) {
            if (!button.length) return;
            
            const originalText = button.data('original-text') || button.text();
            
            if (!button.data('original-text')) {
                button.data('original-text', originalText);
            }
            
            switch (state) {
                case 'loading':
                    button.prop('disabled', true)
                          .removeClass('button-success button-danger')
                          .addClass('nexus-loading')
                          .text(text || 'Loading...');
                    break;
                    
                case 'success':
                    button.removeClass('nexus-loading button-danger')
                          .addClass('button-success')
                          .text(text || 'Success!');
                    setTimeout(() => this.setButtonState(button, 'normal'), 2000);
                    break;
                    
                case 'error':
                    button.removeClass('nexus-loading button-success')
                          .addClass('button-danger')
                          .text(text || 'Error');
                    setTimeout(() => this.setButtonState(button, 'normal'), 3000);
                    break;
                    
                case 'normal':
                default:
                    button.prop('disabled', false)
                          .removeClass('nexus-loading button-success button-danger')
                          .text(text || button.data('original-text') || originalText);
                    break;
            }
        },
        
        showResult: function(container, type, message) {
            if (!container.length) return;
            
            const alertClass = type === 'success' ? 'notice-success' : 
                             type === 'warning' ? 'notice-warning' : 'notice-error';
            
            const html = `
                <div class="notice ${alertClass} inline">
                    <p style="white-space: pre-line;">${this.escapeHtml(message)}</p>
                </div>
            `;
            
            container.html(html);
        },
        
        showNotice: function(type, message, persistent = false) {
            const alertClass = type === 'success' ? 'notice-success' : 
                             type === 'warning' ? 'notice-warning' : 'notice-error';
            
            const dismissible = persistent ? '' : 'is-dismissible';
            
            const notice = $(`
                <div class="notice ${alertClass} ${dismissible}">
                    <p>${this.escapeHtml(message)}</p>
                    ${!persistent ? '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>' : ''}
                </div>
            `);
            
            // Insert after page title
            const insertPoint = $('.wrap h1').first();
            if (insertPoint.length) {
                insertPoint.after(notice);
            } else {
                $('body').prepend(notice);
            }
            
            // Auto-dismiss success notices
            if (type === 'success' && !persistent) {
                setTimeout(() => notice.fadeOut(() => notice.remove()), 5000);
            }
            
            // Handle dismiss button
            notice.find('.notice-dismiss').on('click', function() {
                notice.fadeOut(() => notice.remove());
            });
            
            // Scroll to notice
            $('html, body').animate({
                scrollTop: notice.offset().top - 100
            }, 300);
        },
        
        downloadFile: function(content, filename, mimeType) {
            try {
                const blob = new Blob([content], { type: mimeType });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                
                a.href = url;
                a.download = filename;
                a.style.display = 'none';
                
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                
                window.URL.revokeObjectURL(url);
            } catch (error) {
                console.error('Download failed:', error);
                this.showNotice('error', 'Download failed. Please try again.');
            }
        },
        
        // Language Helpers
        getLanguageName: function(code) {
            const languages = {
                'fr': 'Français', 'en': 'English', 'es': 'Español', 'de': 'Deutsch',
                'it': 'Italiano', 'pt': 'Português', 'nl': 'Nederlands', 'ru': 'Русский',
                'ja': '日本語', 'zh': '中文', 'ar': 'العربية', 'hi': 'हिन्दी',
                'ko': '한국어', 'sv': 'Svenska', 'da': 'Dansk', 'no': 'Norsk',
                'fi': 'Suomi', 'pl': 'Polski', 'tr': 'Türkçe', 'he': 'עברית'
            };
            return languages[code] || code.toUpperCase();
        },
        
        getLanguageFlag: function(code) {
            const flags = {
                'fr': '🇫🇷', 'en': '🇺🇸', 'es': '🇪🇸', 'de': '🇩🇪',
                'it': '🇮🇹', 'pt': '🇵🇹', 'nl': '🇳🇱', 'ru': '🇷🇺',
                'ja': '🇯🇵', 'zh': '🇨🇳', 'ar': '🇸🇦', 'hi': '🇮🇳',
                'ko': '🇰🇷', 'sv': '🇸🇪', 'da': '🇩🇰', 'no': '🇳🇴',
                'fi': '🇫🇮', 'pl': '🇵🇱', 'tr': '🇹🇷', 'he': '🇮🇱'
            };
            return flags[code] || '🌍';
        },
        
        // Time and Number Formatting
        timeAgo: function(timestamp) {
            const now = Date.now();
            const diff = now - timestamp;
            const seconds = Math.floor(diff / 1000);
            const minutes = Math.floor(seconds / 60);
            const hours = Math.floor(minutes / 60);
            const days = Math.floor(hours / 24);
            
            if (days > 0) {
                return days + ' day' + (days !== 1 ? 's' : '') + ' ago';
            } else if (hours > 0) {
                return hours + ' hour' + (hours !== 1 ? 's' : '') + ' ago';
            } else if (minutes > 0) {
                return minutes + ' minute' + (minutes !== 1 ? 's' : '') + ' ago';
            } else {
                return 'Just now';
            }
        },
        
        formatNumber: function(num) {
            if (typeof num !== 'number') return num;
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },
        
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        // Debug and Monitoring
        getStatus: function() {
            return {
                activeRequests: this.state.activeRequests,
                isRefreshing: this.state.isRefreshing,
                lastAnalyticsUpdate: this.state.lastAnalyticsUpdate,
                config: this.config
            };
        },
        
        // Cleanup
        destroy: function() {
            // Clear intervals
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
            }
            
            // Remove event listeners
            $(document).off('.nexusAdmin');
            
            console.log('Nexus Admin: Cleanup completed');
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        // Check if we have the required global object
        if (typeof nexusTranslator === 'undefined') {
            console.error('Nexus Admin: nexusTranslator object not found');
            return;
        }
        
        NexusAdmin.init();
        
        // Global error handler for unhandled AJAX errors
        $(document).ajaxError(function(event, xhr, settings, error) {
            if (settings.data && settings.data.indexOf('nexus_') !== -1) {
                console.error('Nexus AJAX Error:', {
                    url: settings.url,
                    data: settings.data,
                    status: xhr.status,
                    error: error
                });
            }
        });
    });
    
    // Handle page unload
    $(window).on('beforeunload', function() {
        if (NexusAdmin.state.activeRequests > 0) {
            return 'Translation operations are still in progress. Are you sure you want to leave?';
        }
    });
    
    // Global access for debugging and external integration
    window.NexusAdmin = NexusAdmin;
    
    // Expose status for monitoring
    window.getNexusAdminStatus = function() {
        return NexusAdmin.getStatus();
    };
    
})(jQuery);