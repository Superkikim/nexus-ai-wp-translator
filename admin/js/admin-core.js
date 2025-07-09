/**
 * File: admin-core.js
 * Location: /admin/js/admin-core.js
 * 
 * Nexus AI WP Translator - Modular Admin Core
 * Base functionality and module loader
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
            hooks: {}
        },

        /**
         * Initialize core
         */
        init: function(settings) {
            console.log('Nexus Translator Core: Initializing');
            
            // Merge configuration
            this.config = $.extend(true, this.config, settings);
            this.config.debug = this.config.debug || window.nexusTranslator?.debug;
            
            // Initialize base functionality
            this.initBase();
            
            // Auto-load modules based on page context
            this.autoLoadModules();
            
            console.log('Nexus Translator Core: Ready');
        },

        /**
         * Initialize base functionality
         */
        initBase: function() {
            // Emergency stop check
            if (window.nexusTranslator?.emergencyStop) {
                this.handleEmergencyState();
                return;
            }
            
            // Common event bindings
            this.bindCommonEvents();
            
            // Set up AJAX defaults
            this.setupAjax();
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
            // Test API connection (appears on multiple pages)
            $(document).on('click', '#test-api-connection', this.testApiConnection.bind(this));
            
            // Common form handling
            $(document).on('submit', '.nexus-form', this.handleFormSubmit.bind(this));
            
            // Emergency cleanup button
            $(document).on('click', '.nexus-emergency-cleanup', this.handleEmergencyCleanup.bind(this));
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
         * Test API connection
         */
        testApiConnection: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const $result = $('#api-test-result');
            
            // CRUCIAL: Récupérer la clé API depuis le champ
            const apiKey = $('#claude_api_key').val();
            
            // Vérifier que la clé est présente
            if (!apiKey || apiKey.trim() === '') {
                this.showResult($result, 'error', 'Please enter your Claude API key first');
                return;
            }
            
            this.setButtonState($button, 'loading', 'Testing...');
            $result.empty();
            
            // Envoyer la clé API dans la requête
            this.ajax('nexus_test_api_connection', {
                api_key: apiKey  // <- AJOUTÉ !
            })
            .done((response) => {
                if (response.success) {
                    this.showResult($result, 'success', response.data.message, response.data.test_translation);
                } else {
                    this.showResult($result, 'error', response.data);
                }
            })
            .fail(() => {
                this.showResult($result, 'error', 'Connection failed');
            })
            .always(() => {
                this.setButtonState($button, 'normal', 'Test Connection');
            });
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
         * Handle emergency state
         */
        handleEmergencyState: function() {
            console.warn('Nexus Translator: Emergency stop is active');
            
            // Disable all action buttons
            $('.nexus-translate-btn, .nexus-update-translation').prop('disabled', true);
            
            // Show emergency notice
            this.showNotice('error', 'Translation functionality disabled for safety. Check settings.', true);
        },

        /**
         * Handle emergency cleanup
         */
        handleEmergencyCleanup: function(e) {
            e.preventDefault();
            
            if (!confirm('This will stop all translation processes. Continue?')) {
                return;
            }
            
            this.ajax('nexus_emergency_cleanup', {})
                .done((response) => {
                    if (response.success) {
                        this.showNotice('success', 'Emergency cleanup completed');
                        setTimeout(() => window.location.reload(), 2000);
                    } else {
                        this.showNotice('error', response.data || 'Cleanup failed');
                    }
                });
        },

        /**
         * Utility: AJAX wrapper
         */
        ajax: function(action, data, options) {
            const defaults = {
                url: window.nexusTranslator?.ajaxUrl || '/wp-admin/admin-ajax.php',
                type: 'POST',
                dataType: 'json',
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
                });
        },

        /**
         * Utility: Button state management
         */
        setButtonState: function($button, state, text) {
            const originalText = $button.data('original-text') || $button.text();
            
            if (!$button.data('original-text')) {
                $button.data('original-text', originalText);
            }
            
            switch (state) {
                case 'loading':
                    $button.prop('disabled', true).text(text || 'Loading...');
                    break;
                case 'success':
                    $button.removeClass('button-primary').addClass('button-success').text(text || 'Success');
                    setTimeout(() => this.setButtonState($button, 'normal'), 2000);
                    break;
                case 'error':
                    $button.removeClass('button-primary').addClass('button-danger').text(text || 'Error');
                    setTimeout(() => this.setButtonState($button, 'normal'), 3000);
                    break;
                case 'normal':
                default:
                    $button.prop('disabled', false)
                           .removeClass('button-success button-danger')
                           .addClass('button-primary')
                           .text($button.data('original-text') || text);
                    break;
            }
        },

        /**
         * Utility: Show result in container
         */
        showResult: function($container, type, message, extra) {
            const typeClass = type === 'success' ? 'notice-success' : 'notice-error';
            let html = `<div class="notice ${typeClass} inline"><p><strong>${type === 'success' ? 'Success!' : 'Error:'}</strong> ${message}</p>`;
            
            if (extra) {
                html += `<p><small><strong>Test translation:</strong> ${extra}</small></p>`;
            }
            
            html += '</div>';
            
            $container.html(html);
        },

        /**
         * Utility: Show admin notice
         */
        showNotice: function(type, message, persistent) {
            const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            const dismissible = persistent ? '' : 'is-dismissible';
            
            const $notice = $(`<div class="notice ${noticeClass} ${dismissible}"><p>${message}</p></div>`);
            
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
         * Event system: Add hook
         */
        on: function(event, callback) {
            if (!this.config.hooks[event]) {
                this.config.hooks[event] = [];
            }
            this.config.hooks[event].push(callback);
        },

        /**
         * Event system: Trigger hook
         */
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
         * Utility: Logging
         */
        log: function(message, level, data) {
            if (!this.config.debug && level !== 'error') return;
            
            const prefix = 'Nexus Translator:';
            
            switch (level) {
                case 'error':
                    console.error(prefix, message, data);
                    break;
                case 'warn':
                    console.warn(prefix, message, data);
                    break;
                case 'info':
                default:
                    console.log(prefix, message, data);
                    break;
            }
        },

        /**
         * Utility: Get module
         */
        getModule: function(name) {
            return this.config.modules[name] || null;
        },

        /**
         * Utility: Check if module loaded
         */
        hasModule: function(name) {
            return !!this.config.modules[name];
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

})(jQuery, window);