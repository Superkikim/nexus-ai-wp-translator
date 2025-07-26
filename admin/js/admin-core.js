/**
 * File: admin-core.js
 * Location: /admin/js/admin-core.js
 * 
 * Nexus AI WP Translator - Enhanced Admin Core with Emergency Button Support
 */

(function($, window) {
    'use strict';

    /**
     * Core Nexus Translator object
     */
    window.NexusTranslator = window.NexusTranslator || {};

    const NexusCore = {
        
        /**
         * Configuration
         */
        config: {
            version: '1.0.0',
            debug: false,
            modules: {},
            hooks: {},
            activeRequests: new Set(),
            emergencyMode: false
        },

        /**
         * Initialize core with emergency support
         */
        init: function(settings) {
            console.log('Nexus Translator Core: Initializing with emergency button support');
            
            // Merge configuration
            this.config = $.extend(true, this.config, settings);
            this.config.debug = this.config.debug || window.nexusTranslator?.debug;
            
            // Check emergency state
            this.checkEmergencyState();
            
            // Initialize base functionality
            this.initBase();
            
            // Auto-load modules based on page context
            this.autoLoadModules();
            
            console.log('Nexus Translator Core: Ready with emergency support active');
        },

        /**
         * Check for emergency state
         */
        checkEmergencyState: function() {
            if (window.nexusTranslator?.emergencyStop) {
                this.config.emergencyMode = true;
                this.handleEmergencyState();
            }
        },

        /**
         * Initialize base functionality
         */
        initBase: function() {
            // Common event bindings with emergency support
            this.bindCommonEvents();
            this.bindEmergencyEvents();
            
            // Set up AJAX defaults
            this.setupAjax();
            
            // Start request cleanup
            this.startRequestCleanup();
            
            // Initialize emergency monitoring
            this.initEmergencyMonitoring();
        },

        /**
         * Bind emergency-specific events
         */
        bindEmergencyEvents: function() {
            // Emergency cleanup buttons
            $(document).on('click', '#emergency-cleanup', this.handleEmergencyCleanup.bind(this));
            $(document).on('click', '#reset-emergency-stop', this.handleResetEmergency.bind(this));
            $(document).on('click', '#reset-rate-limits', this.handleResetRateLimits.bind(this));
            $(document).on('click', '#cleanup-locks', this.handleCleanupLocks.bind(this));
            $(document).on('click', '#export-config', this.handleExportConfig.bind(this));
            $(document).on('click', '#validate-config', this.handleValidateConfig.bind(this));
            
            // Advanced emergency handlers
            $(document).on('click', '#reset-all-limits', this.handleResetAllLimits.bind(this));
            $(document).on('click', '#test-api-advanced', this.handleAdvancedApiTest.bind(this));
            $(document).on('click', '#emergency-cleanup-direct', this.handleDirectEmergencyCleanup.bind(this));
            
            this.log('Emergency event handlers bound');
        },

        /**
         * Auto-load modules based on page context
         */
        autoLoadModules: function() {
            const screen = this.getCurrentScreen();
            
            switch (screen) {
                case 'post-edit':
                    this.loadModule('translation');
                    break;
                case 'settings':
                    this.loadModule('settings');
                    this.loadModule('monitoring');
                    this.loadModule('emergency');
                    break;
                case 'post-list':
                    this.loadModule('bulk');
                    break;
            }
            
            // Always load common utilities
            this.loadModule('utils');
        },

        /**
         * Get current screen context
         */
        getCurrentScreen: function() {
            const $body = $('body');
            
            if ($body.hasClass('settings_page_nexus-translator-settings')) {
                return 'settings';
            }
            if ($body.hasClass('post-php') || $body.hasClass('post-new-php')) {
                return 'post-edit';
            }
            if ($body.hasClass('edit-php')) {
                return 'post-list';
            }
            
            return 'unknown';
        },

        /**
         * Module loader
         */
        loadModule: function(moduleName) {
            if (this.config.modules[moduleName]) {
                this.log(`Module ${moduleName} already loaded`);
                return;
            }
            
            if (typeof window.NexusModules !== 'undefined' && window.NexusModules[moduleName]) {
                this.log(`Loading module: ${moduleName}`);
                
                const module = window.NexusModules[moduleName];
                
                // Initialize module
                if (typeof module.init === 'function') {
                    module.init(this);
                }
                
                // Store module reference
                this.config.modules[moduleName] = module;
                
                // Trigger module loaded hook
                this.trigger('moduleLoaded', moduleName, module);
            } else {
                this.log(`Module ${moduleName} not found`, 'warn');
            }
        },

        /**
         * Common event bindings
         */
        bindCommonEvents: function() {
            // Test API connection
            $(document).on('click', '#test-api-connection', this.testApiConnection.bind(this));
            
            // Common form handling
            $(document).on('submit', '.nexus-form', this.handleFormSubmit.bind(this));
            
            // Monitor API key field changes
            $(document).on('input paste keyup', '#claude_api_key', this.handleApiKeyChange.bind(this));
            
            // Global AJAX error handling
            $(document).ajaxError(this.handleAjaxError.bind(this));
        },

        /**
         * Setup AJAX defaults
         */
        setupAjax: function() {
            // Add nonce to all AJAX requests
            $.ajaxSetup({
                beforeSend: function(xhr, settings) {
                    if (settings.type === 'POST' && window.nexusTranslator?.nonce) {
                        if (settings.data && settings.data.indexOf('nonce=') === -1) {
                            settings.data += '&nonce=' + window.nexusTranslator.nonce;
                        }
                    }
                }
            });
        },

        /**
         * Start request cleanup monitoring
         */
        startRequestCleanup: function() {
            setInterval(() => {
                this.cleanupExpiredRequests();
            }, 30000);
        },

        /**
         * Initialize emergency monitoring
         */
        initEmergencyMonitoring: function() {
            // Check for emergency state every 30 seconds
            setInterval(() => {
                this.checkEmergencyStatus();
            }, 30000);
            
            // Monitor for stuck requests
            setInterval(() => {
                this.monitorStuckRequests();
            }, 60000);
        },

        /**
         * Emergency cleanup handler - ENHANCED
         */
        handleEmergencyCleanup: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            
            if (!confirm('🚨 EMERGENCY CLEANUP\n\nThis will:\n- Clear all active requests\n- Remove translation locks\n- Reset rate limits\n- Clear emergency stop\n\nContinue?')) {
                return;
            }
            
            this.setButtonState($button, 'loading', 'Cleaning...');
            
            this.ajax('nexus_emergency_cleanup', {})
                .done((response) => {
                    if (response.success) {
                        this.showNotice('success', '✅ Emergency cleanup completed: ' + response.data.actions.join(', '));
                        this.setButtonState($button, 'success', 'Cleaned!');
                        
                        // Reload page after delay
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        this.showNotice('error', '❌ Emergency cleanup failed: ' + (response.data?.error || 'Unknown error'));
                        this.setButtonState($button, 'error', 'Failed');
                    }
                })
                .fail((xhr, status, error) => {
                    console.error('Emergency cleanup AJAX failed:', status, error);
                    this.showNotice('error', '❌ Emergency cleanup failed: ' + error);
                    this.setButtonState($button, 'error', 'Failed');
                })
                .always(() => {
                    setTimeout(() => {
                        this.setButtonState($button, 'normal');
                    }, 3000);
                });
        },

        /**
         * Reset emergency stop handler - ENHANCED
         */
        handleResetEmergency: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            
            if (!confirm('Reset emergency stop? This will re-enable all translation functionality.')) {
                return;
            }
            
            this.setButtonState($button, 'loading', 'Resetting...');
            
            this.ajax('nexus_reset_emergency', {})
                .done((response) => {
                    if (response.success) {
                        this.showNotice('success', '✅ Emergency stop reset successfully');
                        this.setButtonState($button, 'success', 'Reset!');
                        this.config.emergencyMode = false;
                        
                        // Reload page after delay
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        this.showNotice('error', '❌ Reset failed: ' + (response.data?.error || 'Unknown error'));
                        this.setButtonState($button, 'error', 'Failed');
                    }
                })
                .fail((xhr, status, error) => {
                    console.error('Emergency reset AJAX failed:', status, error);
                    this.showNotice('error', '❌ Reset failed: ' + error);
                    this.setButtonState($button, 'error', 'Failed');
                })
                .always(() => {
                    setTimeout(() => {
                        this.setButtonState($button, 'normal');
                    }, 3000);
                });
        },

        /**
         * Reset rate limits handler - ENHANCED
         */
        handleResetRateLimits: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            
            if (!confirm('Reset all rate limits? This will clear current usage counters.')) {
                return;
            }
            
            this.setButtonState($button, 'loading', 'Resetting...');
            
            this.ajax('nexus_reset_rate_limits', {})
                .done((response) => {
                    if (response.success) {
                        this.showNotice('success', '✅ Rate limits reset successfully');
                        this.setButtonState($button, 'success', 'Reset!');
                        
                        // Update usage display if monitoring module is loaded
                        const monitoring = this.getModule('monitoring');
                        if (monitoring && monitoring.updateUsage) {
                            monitoring.updateUsage();
                        }
                    } else {
                        this.showNotice('error', '❌ Rate limit reset failed: ' + (response.data?.error || 'Unknown error'));
                        this.setButtonState($button, 'error', 'Failed');
                    }
                })
                .fail((xhr, status, error) => {
                    console.error('Rate limit reset AJAX failed:', status, error);
                    this.showNotice('error', '❌ Rate limit reset failed: ' + error);
                    this.setButtonState($button, 'error', 'Failed');
                })
                .always(() => {
                    setTimeout(() => {
                        this.setButtonState($button, 'normal');
                    }, 3000);
                });
        },

        /**
         * Cleanup locks handler - NEW
         */
        handleCleanupLocks: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            
            this.setButtonState($button, 'loading', 'Cleaning...');
            
            this.ajax('nexus_cleanup_locks', {})
                .done((response) => {
                    if (response.success) {
                        this.showNotice('success', `✅ Locks cleaned: ${response.data.deleted_locks} removed, ${response.data.reset_status} reset`);
                        this.setButtonState($button, 'success', 'Cleaned!');
                    } else {
                        this.showNotice('error', '❌ Lock cleanup failed: ' + (response.data?.error || 'Unknown error'));
                        this.setButtonState($button, 'error', 'Failed');
                    }
                })
                .fail((xhr, status, error) => {
                    console.error('Lock cleanup AJAX failed:', status, error);
                    this.showNotice('error', '❌ Lock cleanup failed: ' + error);
                    this.setButtonState($button, 'error', 'Failed');
                })
                .always(() => {
                    setTimeout(() => {
                        this.setButtonState($button, 'normal');
                    }, 3000);
                });
        },

        /**
         * Export config handler - NEW
         */
        handleExportConfig: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            
            this.setButtonState($button, 'loading', 'Exporting...');
            
            this.ajax('nexus_export_config', {})
                .done((response) => {
                    if (response.success) {
                        // Download the config file
                        const blob = new Blob([JSON.stringify(response.data.config, null, 2)], {
                            type: 'application/json'
                        });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = response.data.filename;
                        a.click();
                        window.URL.revokeObjectURL(url);
                        
                        this.showNotice('success', '✅ Configuration exported successfully');
                        this.setButtonState($button, 'success', 'Exported!');
                    } else {
                        this.showNotice('error', '❌ Export failed: ' + (response.data?.error || 'Unknown error'));
                        this.setButtonState($button, 'error', 'Failed');
                    }
                })
                .fail((xhr, status, error) => {
                    console.error('Config export AJAX failed:', status, error);
                    this.showNotice('error', '❌ Export failed: ' + error);
                    this.setButtonState($button, 'error', 'Failed');
                })
                .always(() => {
                    setTimeout(() => {
                        this.setButtonState($button, 'normal');
                    }, 3000);
                });
        },

        /**
         * Validate config handler - NEW
         */
        handleValidateConfig: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            
            this.setButtonState($button, 'loading', 'Validating...');
            
            this.ajax('nexus_validate_config', {})
                .done((response) => {
                    if (response.success) {
                        const data = response.data;
                        
                        if (data.valid) {
                            this.showNotice('success', '✅ Configuration is valid');
                            this.setButtonState($button, 'success', 'Valid!');
                        } else {
                            let message = '⚠️ Configuration issues found:\n';
                            data.issues.forEach(issue => {
                                message += '• ' + issue + '\n';
                            });
                            this.showNotice('warning', message);
                            this.setButtonState($button, 'error', 'Issues Found');
                        }
                    } else {
                        this.showNotice('error', '❌ Validation failed: ' + (response.data?.error || 'Unknown error'));
                        this.setButtonState($button, 'error', 'Failed');
                    }
                })
                .fail((xhr, status, error) => {
                    console.error('Config validation AJAX failed:', status, error);
                    this.showNotice('error', '❌ Validation failed: ' + error);
                    this.setButtonState($button, 'error', 'Failed');
                })
                .always(() => {
                    setTimeout(() => {
                        this.setButtonState($button, 'normal');
                    }, 3000);
                });
        },

        /**
         * Direct emergency cleanup - NUCLEAR OPTION
         */
        handleDirectEmergencyCleanup: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            
            if (!confirm('🆘 DIRECT EMERGENCY CLEANUP\n\nThis is the nuclear option that will:\n- Force reset ALL plugin data\n- Clear everything aggressively\n- May require reconfiguration\n\nOnly use if normal cleanup fails!\n\nContinue?')) {
                return;
            }
            
            this.setButtonState($button, 'loading', 'Nuclear Cleanup...');
            
            this.ajax('nexus_emergency_cleanup_direct', {})
                .done((response) => {
                    if (response.success) {
                        this.showNotice('success', '🆘 Direct emergency cleanup completed');
                        this.setButtonState($button, 'success', 'Nuked!');
                        
                        // Reload page after delay
                        setTimeout(() => {
                            window.location.reload();
                        }, 3000);
                    } else {
                        this.showNotice('error', '❌ Direct cleanup failed: ' + (response.data?.error || 'Unknown error'));
                        this.setButtonState($button, 'error', 'Failed');
                    }
                })
                .fail((xhr, status, error) => {
                    console.error('Direct emergency cleanup AJAX failed:', status, error);
                    this.showNotice('error', '❌ Direct cleanup failed: ' + error);
                    this.setButtonState($button, 'error', 'Failed');
                })
                .always(() => {
                    setTimeout(() => {
                        this.setButtonState($button, 'normal');
                    }, 5000);
                });
        },

        /**
         * Monitor for stuck requests
         */
        monitorStuckRequests: function() {
            const now = Date.now();
            const timeout = 120000; // 2 minutes
            
            this.config.activeRequests.forEach(request => {
                if (now - request.timestamp > timeout) {
                    this.log(`Detected stuck request: ${request.id}`, 'warn');
                    this.config.activeRequests.delete(request);
                    
                    // Show warning to user
                    this.showNotice('warning', '⚠️ Detected and cleaned stuck request. Consider using emergency cleanup if issues persist.', false);
                }
            });
        },

        /**
         * Check emergency status periodically
         */
        checkEmergencyStatus: function() {
            // Simple check via AJAX without triggering handlers
            $.ajax({
                url: window.nexusTranslator?.ajaxUrl || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'nexus_validate_config',
                    nonce: window.nexusTranslator?.nonce
                },
                timeout: 5000,
                success: (response) => {
                    if (response.success && response.data.issues) {
                        const hasEmergencyIssue = response.data.issues.some(issue => 
                            issue.includes('Emergency stop') || issue.includes('emergency')
                        );
                        
                        if (hasEmergencyIssue && !this.config.emergencyMode) {
                            this.config.emergencyMode = true;
                            this.handleEmergencyState();
                        }
                    }
                }
            });
        },

        /**
         * Test API connection - ENHANCED
         */
        testApiConnection: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const $result = $('#api-test-result');
            
            // Protection: Avoid double-click
            if ($button.prop('disabled')) {
                return;
            }
            
            // Protection: Check if test in progress
            const testRequestId = 'api_test_' + Date.now();
            if (this.isRequestActive('api_test')) {
                this.showResult($result, 'error', 'API test already in progress');
                return;
            }
            
            // Get API key from field
            const apiKey = $('#claude_api_key').val().trim();
            
            if (!apiKey) {
                this.showResult($result, 'error', 'Please enter your Claude API key first');
                return;
            }
            
            // Mark request as active
            this.addActiveRequest(testRequestId, 'api_test');
            
            this.setButtonState($button, 'loading', 'Testing...');
            $result.empty();
            
            this.ajax('nexus_test_api_connection', {
                api_key: apiKey
            })
            .done((response) => {
                if (response.success) {
                    this.showResult($result, 'success', response.data.message, response.data.test_translation);
                    this.setButtonState($button, 'success', 'Connection OK');
                } else {
                    this.showResult($result, 'error', response.data.error || 'Connection failed');
                    this.setButtonState($button, 'error', 'Test Failed');
                }
            })
            .fail((xhr, status, error) => {
                this.showResult($result, 'error', 'Connection failed: ' + error);
                this.setButtonState($button, 'error', 'Connection Failed');
            })
            .always(() => {
                // Cleanup active request
                this.removeActiveRequest(testRequestId);
                
                // Reset button after delay
                setTimeout(() => {
                    this.setButtonState($button, 'normal');
                }, 3000);
            });
        },

        /**
         * Handle emergency state
         */
        handleEmergencyState: function() {
            console.warn('Nexus Translator: Emergency state detected');
            
            // Disable all action buttons
            $('.nexus-translate-btn, .nexus-update-translation').prop('disabled', true);
            
            // Show emergency notice
            this.showNotice('error', '🚨 Emergency mode active - Translation functionality disabled for safety. Use emergency reset button.', true);
            
            // Add emergency styling
            $('body').addClass('nexus-emergency-mode');
            
            // Highlight emergency buttons
            $('#reset-emergency-stop, #emergency-cleanup').addClass('nexus-emergency-highlight');
        },

        /**
         * Handle form submission
         */
        handleFormSubmit: function(e) {
            const $form = $(e.target);
            
            // Validate form if validator exists
            if (this.config.modules.utils && this.config.modules.utils.validateForm) {
                if (!this.config.modules.utils.validateForm($form)) {
                    e.preventDefault();
                    return false;
                }
            }
            
            this.trigger('formSubmit', $form);
        },

        /**
         * Global AJAX error handling - ENHANCED
         */
        handleAjaxError: function(event, xhr, settings, thrownError) {
            this.log(`Global AJAX Error: ${settings.url} - ${xhr.status} ${thrownError}`, 'error');
            
            // Security error - reload page
            if (xhr.status === 403 || xhr.responseText.includes('nonce')) {
                this.showNotice('error', '🔒 Security error detected. Page will reload in 3 seconds.', true);
                setTimeout(() => window.location.reload(), 3000);
                return;
            }
            
            // Server error - suggest emergency cleanup
            if (xhr.status >= 500) {
                this.showNotice('error', '🚨 Server error detected. Consider using emergency cleanup if issues persist.', false);
                return;
            }
            
            // Timeout - suggest cleanup
            if (thrownError === 'timeout') {
                this.showNotice('warning', '⏰ Request timed out. Consider using emergency cleanup if this happens frequently.', false);
            }
        },

        /**
         * Request management - ENHANCED
         */
        addActiveRequest: function(id, type = 'generic') {
            const request = {
                id: id,
                type: type,
                timestamp: Date.now()
            };
            this.config.activeRequests.add(request);
            this.log(`Added active request: ${id} (${type})`);
        },

        removeActiveRequest: function(id) {
            this.config.activeRequests.forEach(request => {
                if (request.id === id) {
                    this.config.activeRequests.delete(request);
                    this.log(`Removed active request: ${id}`);
                }
            });
        },

        isRequestActive: function(type) {
            let found = false;
            this.config.activeRequests.forEach(request => {
                if (request.type === type) {
                    found = true;
                }
            });
            return found;
        },

        cleanupExpiredRequests: function() {
            const now = Date.now();
            const timeout = 60000; // 60 seconds timeout
            
            this.config.activeRequests.forEach(request => {
                if (now - request.timestamp > timeout) {
                    this.config.activeRequests.delete(request);
                    this.log(`Cleaned up expired request: ${request.id}`, 'warn');
                }
            });
        },

        /**
         * AJAX wrapper with enhanced protection
         */
        ajax: function(action, data, options) {
            const defaults = {
                url: window.nexusTranslator?.ajaxUrl || '/wp-admin/admin-ajax.php',
                type: 'POST',
                dataType: 'json',
                timeout: 30000,
                data: $.extend({
                    action: action,
                    nonce: window.nexusTranslator?.nonce
                }, data)
            };
            
            const settings = $.extend(defaults, options);
            
            this.log(`AJAX: ${action}`, settings.data);
            
            return $.ajax(settings)
                .fail((xhr, status, error) => {
                    this.log(`AJAX Error: ${action} - ${status} ${error}`, 'error');
                    
                    if (status === 'timeout') {
                        this.showNotice('error', '⏰ Request timed out. Please try again.');
                    }
                });
        },

        /**
         * Button state management - ENHANCED
         */
        setButtonState: function($button, state, text) {
            const originalText = $button.data('original-text') || $button.text();
            
            if (!$button.data('original-text')) {
                $button.data('original-text', originalText);
            }
            
            $button.data('previous-state', $button.data('current-state') || 'normal');
            $button.data('current-state', state);
            
            switch (state) {
                case 'loading':
                    $button.prop('disabled', true)
                           .removeClass('button-success button-danger nexus-emergency-highlight')
                           .addClass('button-primary')
                           .text(text || 'Loading...');
                    break;
                case 'success':
                    $button.prop('disabled', false)
                           .removeClass('button-primary button-danger nexus-emergency-highlight')
                           .addClass('button-success')
                           .text(text || 'Success');
                    break;
                case 'error':
                    $button.prop('disabled', false)
                           .removeClass('button-primary button-success')
                           .addClass('button-danger')
                           .text(text || 'Error');
                    break;
                case 'normal':
                default:
                    $button.prop('disabled', false)
                           .removeClass('button-success button-danger')
                           .addClass('button-primary')
                           .text($button.data('original-text') || text);
                    
                    // Re-add emergency highlight if in emergency mode
                    if (this.config.emergencyMode && ($button.attr('id') === 'reset-emergency-stop' || $button.attr('id') === 'emergency-cleanup')) {
                        $button.addClass('nexus-emergency-highlight');
                    }
                    break;
            }
        },

        /**
         * Show result in container - ENHANCED
         */
        showResult: function($container, type, message, extra) {
            const typeClass = type === 'success' ? 'notice-success' : 'notice-error';
            const icon = type === 'success' ? '✅' : '❌';
            
            let html = `<div class="notice ${typeClass} inline">
                <p><strong>${icon} ${type === 'success' ? 'Success!' : 'Error:'}</strong> ${message}</p>`;
            
            if (extra) {
                html += `<p><small><strong>Test translation:</strong> ${extra}</small></p>`;
            }
            
            html += '</div>';
            
            $container.html(html);
            
            // Auto-hide success messages after 5 seconds
            if (type === 'success') {
                setTimeout(() => {
                    $container.fadeOut();
                }, 5000);
            }
        },

        /**
         * Show admin notice - ENHANCED
         */
        showNotice: function(type, message, persistent) {
            const noticeClass = type === 'success' ? 'notice-success' : 
                               type === 'warning' ? 'notice-warning' : 'notice-error';
            const dismissible = persistent ? '' : 'is-dismissible';
            const icon = type === 'success' ? '✅' : type === 'warning' ? '⚠️' : '❌';
            
            const $notice = $(`<div class="notice ${noticeClass} ${dismissible}">
                <p>${icon} ${message}</p>
            </div>`);
            
            if ($('.wrap h1').length) {
                $('.wrap h1').after($notice);
            } else {
                $('body').prepend($notice);
            }
            
            if (!persistent) {
                setTimeout(() => $notice.fadeOut(() => $notice.remove()), 5000);
            }
            
            return $notice;
        },

        /**
         * Handle API key field changes
         */
        handleApiKeyChange: function(e) {
            const $field = $(e.target);
            const $button = $('#test-api-connection');
            const currentValue = $field.val().trim();
            const originalValue = $field.data('original-value') || '';
            
            if (currentValue !== originalValue) {
                this.setButtonState($button, 'normal', 'Save & Test');
            } else if (originalValue === '') {
                this.setButtonState($button, 'normal', 'Save & Test');
            } else {
                this.setButtonState($button, 'normal', 'Test Connection');
            }
        },

        /**
         * Event system
         */
        on: function(event, callback) {
            if (!this.config.hooks[event]) {
                this.config.hooks[event] = [];
            }
            this.config.hooks[event].push(callback);
        },

        trigger: function(event, ...args) {
            if (this.config.hooks[event]) {
                this.config.hooks[event].forEach(callback => {
                    try {
                        callback.apply(this, args);
                    } catch (error) {
                        this.log(`Hook error: ${event}`, 'error', error);
                    }
                });
            }
        },

        /**
         * Logging - ENHANCED
         */
        log: function(message, level, data) {
            if (!this.config.debug && level !== 'error') return;
            
            const prefix = 'Nexus Translator:';
            const timestamp = new Date().toISOString();
            
            switch (level) {
                case 'error':
                    console.error(`${prefix} [${timestamp}]`, message, data);
                    break;
                case 'warn':
                    console.warn(`${prefix} [${timestamp}]`, message, data);
                    break;
                case 'info':
                default:
                    console.log(`${prefix} [${timestamp}]`, message, data);
                    break;
            }
        },

        /**
         * Utility methods
         */
        getModule: function(name) {
            return this.config.modules[name] || null;
        },

        hasModule: function(name) {
            return !!this.config.modules[name];
        },

        /**
         * Diagnostic methods
         */
        getActiveRequests: function() {
            return Array.from(this.config.activeRequests);
        },

        forceCleanupRequests: function() {
            this.config.activeRequests.clear();
            this.log('Force cleanup of all active requests completed', 'warn');
        },

        getSystemStatus: function() {
            return {
                version: this.config.version,
                debug: this.config.debug,
                emergencyMode: this.config.emergencyMode,
                modulesLoaded: Object.keys(this.config.modules),
                activeRequests: this.getActiveRequests().length,
                screen: this.getCurrentScreen()
            };
        }
    };

    // Expose core to global scope
    window.NexusTranslator.Core = NexusCore;

    // Auto-initialize when DOM is ready
    $(document).ready(function() {
        if (window.nexusTranslator) {
            NexusCore.init({
                debug: window.nexusTranslator.debug || false
            });
        }
    });

    // Enhanced debugging interface
    window.NexusTranslator.Debug = {
        getStatus: () => NexusCore.getSystemStatus(),
        getActiveRequests: () => NexusCore.getActiveRequests(),
        forceCleanup: () => NexusCore.forceCleanupRequests(),
        enableDebug: () => { NexusCore.config.debug = true; },
        disableDebug: () => { NexusCore.config.debug = false; },
        triggerEmergency: () => NexusCore.handleEmergencyState(),
        testEmergencyButtons: () => {
            console.log('🧪 Emergency buttons test available via console');
            console.log('Available tests: testApiConnection, resetEmergency, emergencyCleanup, etc.');
        }
    };

    // Add emergency mode CSS
    const emergencyCSS = `
        <style id="nexus-emergency-styles">
        .nexus-emergency-mode .nexus-translate-btn,
        .nexus-emergency-mode .nexus-update-translation {
            opacity: 0.5 !important;
            cursor: not-allowed !important;
        }
        .nexus-emergency-highlight {
            animation: nexus-emergency-pulse 2s infinite !important;
            box-shadow: 0 0 15px rgba(220, 53, 69, 0.5) !important;
        }
        @keyframes nexus-emergency-pulse {
            0% { box-shadow: 0 0 5px rgba(220, 53, 69, 0.5); }
            50% { box-shadow: 0 0 20px rgba(220, 53, 69, 0.8); }
            100% { box-shadow: 0 0 5px rgba(220, 53, 69, 0.5); }
        }
        .button-success { background: #46b450 !important; border-color: #46b450 !important; }
        .button-danger { background: #dc3545 !important; border-color: #dc3545 !important; }
        </style>
    `;
    
    if (!$('#nexus-emergency-styles').length) {
        $('head').append(emergencyCSS);
    }

})(jQuery, window);