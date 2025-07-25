/**
 * File: admin-core.js
 * Location: /admin/js/admin-core.js
 * 
 * Nexus AI WP Translator - Modular Admin Core CORRIGÉ
 * Base functionality and module loader avec protection
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
            activeRequests: new Set() // 🔒 PROTECTION : Suivi des requêtes actives
        },

        /**
         * Initialize core
         */
        init: function(settings) {
            console.log('Nexus Translator Core: Initializing with protection');
            
            // Merge configuration
            this.config = $.extend(true, this.config, settings);
            this.config.debug = this.config.debug || window.nexusTranslator?.debug;
            
            // Initialize base functionality
            this.initBase();
            
            // Auto-load modules based on page context
            this.autoLoadModules();
            
            console.log('Nexus Translator Core: Ready with protection active');
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
            
            // 🔒 PROTECTION : Cleanup périodique des requêtes expirées
            this.startRequestCleanup();
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
        
            // Monitor API key field changes
            $(document).on('input paste keyup', '#claude_api_key', this.handleApiKeyChange.bind(this));
            
            // 🔒 PROTECTION : Gestion globale des erreurs AJAX
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
         * 🔒 NOUVELLE MÉTHODE : Démarrer le nettoyage périodique
         */
        startRequestCleanup: function() {
            // Nettoyer les requêtes actives toutes les 30 secondes
            setInterval(() => {
                this.cleanupExpiredRequests();
            }, 30000);
        },

        /**
         * 🔒 NOUVELLE MÉTHODE : Nettoyer les requêtes expirées
         */
        cleanupExpiredRequests: function() {
            const now = Date.now();
            const timeout = 60000; // 60 secondes timeout
            
            this.config.activeRequests.forEach(request => {
                if (now - request.timestamp > timeout) {
                    this.config.activeRequests.delete(request);
                    this.log(`Cleaned up expired request: ${request.id}`, 'warn');
                }
            });
        },

        /**
         * 🔒 MÉTHODE AMÉLIORÉE : Test API connection avec protection
         */
        testApiConnection: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const $result = $('#api-test-result');
            
            // 🔒 PROTECTION 1 : Éviter double-clic
            if ($button.prop('disabled')) {
                this.log('API test button already disabled');
                return;
            }
            
            // 🔒 PROTECTION 2 : Vérifier si test en cours
            const testRequestId = 'api_test_' + Date.now();
            if (this.isRequestActive('api_test')) {
                this.showResult($result, 'error', 'API test already in progress');
                return;
            }
            
            // Récupérer la clé du champ
            const apiKey = $('#claude_api_key').val().trim();
            
            if (!apiKey) {
                this.showResult($result, 'error', 'Please enter your Claude API key first');
                return;
            }
            
            // 🔒 PROTECTION 3 : Marquer la requête comme active
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
                // 🔒 PROTECTION 4 : Nettoyer la requête active
                this.removeActiveRequest(testRequestId);
                
                // Reset button after delay
                setTimeout(() => {
                    this.setButtonState($button, 'normal');
                }, 3000);
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
            
            const $button = $(e.target);
            this.setButtonState($button, 'loading', 'Cleaning...');
            
            this.ajax('nexus_emergency_cleanup', {})
                .done((response) => {
                    if (response.success) {
                        this.showNotice('success', 'Emergency cleanup completed');
                        setTimeout(() => window.location.reload(), 2000);
                    } else {
                        this.showNotice('error', response.data || 'Cleanup failed');
                    }
                })
                .always(() => {
                    this.setButtonState($button, 'normal');
                });
        },

        /**
         * 🔒 NOUVELLE MÉTHODE : Gestion des erreurs AJAX globales
         */
        handleAjaxError: function(event, xhr, settings, thrownError) {
            this.log(`Global AJAX Error: ${settings.url} - ${xhr.status} ${thrownError}`, 'error');
            
            // Si c'est une erreur de sécurité, recharger la page
            if (xhr.status === 403 || xhr.responseText.includes('nonce')) {
                this.showNotice('error', 'Security error. Page will reload.', true);
                setTimeout(() => window.location.reload(), 2000);
            }
            
            // Si c'est une erreur serveur, proposer un retry
            if (xhr.status >= 500) {
                this.showNotice('error', 'Server error. Please try again.', false);
            }
        },

        /**
         * 🔒 NOUVELLES MÉTHODES : Gestion des requêtes actives
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

        /**
         * Utility: AJAX wrapper with protection
         */
        ajax: function(action, data, options) {
            const defaults = {
                url: window.nexusTranslator?.ajaxUrl || '/wp-admin/admin-ajax.php',
                type: 'POST',
                dataType: 'json',
                timeout: 30000, // 🔒 PROTECTION : Timeout de 30s
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
                    
                    // 🔒 PROTECTION : Gestion spécifique des erreurs
                    if (status === 'timeout') {
                        this.showNotice('error', 'Request timed out. Please try again.');
                    }
                });
        },

        /**
         * Utility: Button state management - AMÉLIORÉ
         */
        setButtonState: function($button, state, text) {
            const originalText = $button.data('original-text') || $button.text();
            
            if (!$button.data('original-text')) {
                $button.data('original-text', originalText);
            }
            
            // 🔒 PROTECTION : Sauvegarder l'état précédent
            $button.data('previous-state', $button.data('current-state') || 'normal');
            $button.data('current-state', state);
            
            switch (state) {
                case 'loading':
                    $button.prop('disabled', true)
                           .removeClass('button-success button-danger')
                           .addClass('button-primary')
                           .text(text || 'Loading...');
                    break;
                case 'success':
                    $button.prop('disabled', false)
                           .removeClass('button-primary button-danger')
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
                    break;
            }
        },

        /**
         * Utility: Show result in container
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
         * Utility: Show admin notice
         */
        showNotice: function(type, message, persistent) {
            const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            const dismissible = persistent ? '' : 'is-dismissible';
            const icon = type === 'success' ? '✅' : '❌';
            
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

        // Handle API key field changes to update button label dynamically
        handleApiKeyChange: function(e) {
            const $field = $(e.target);
            const $button = $('#test-api-connection');
            const currentValue = $field.val().trim();
            const originalValue = $field.data('original-value') || '';
            
            // Update button label based on comparison
            if (currentValue !== originalValue) {
                this.setButtonState($button, 'normal', 'Save & Test');
            } else if (originalValue === '') {
                this.setButtonState($button, 'normal', 'Save & Test');
            } else {
                this.setButtonState($button, 'normal', 'Test Connection');
            }
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
        },

        /**
         * 🔒 MÉTHODES DE DIAGNOSTIC
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

    // 🔒 PROTECTION : Exposer méthodes de diagnostic globalement
    window.NexusTranslator.Debug = {
        getStatus: () => NexusCore.getSystemStatus(),
        getActiveRequests: () => NexusCore.getActiveRequests(),
        forceCleanup: () => NexusCore.forceCleanupRequests(),
        enableDebug: () => { NexusCore.config.debug = true; },
        disableDebug: () => { NexusCore.config.debug = false; }
    };

})(jQuery, window);