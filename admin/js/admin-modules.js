/**
 * File: admin-modules.js
 * Location: /admin/js/admin-modules.js
 * 
 * Nexus AI WP Translator - Modular Components
 * All modules in one file for efficient loading
 */

(function($, window) {
    'use strict';

    // Module container
    window.NexusModules = {};

    /**
     * TRANSLATION MODULE
     * Handles post translation functionality
     */
    window.NexusModules.translation = {
        
        init: function(core) {
            this.core = core;
            this.bindEvents();
            core.log('Translation module loaded');
        },

        bindEvents: function() {
            $(document).on('click', '.nexus-translate-btn', this.handleTranslation.bind(this));
            $(document).on('click', '.nexus-update-translation', this.handleUpdateTranslation.bind(this));
            $(document).on('click', '.nexus-translate-link', this.handleRowActionTranslation.bind(this));
        },

        handleTranslation: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const postId = $button.data('post-id');
            const targetLang = $button.data('target-lang');
            
            if (!postId || !targetLang) {
                this.core.showNotice('error', 'Invalid parameters');
                return;
            }
            
            this.startTranslation(postId, targetLang, $button);
        },

        handleUpdateTranslation: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const originalId = $button.data('original-id');
            const translatedId = $button.data('translated-id');
            
            if (!confirm('This will update the existing translation. Continue?')) {
                return;
            }
            
            this.updateTranslation(originalId, translatedId, $button);
        },

        handleRowActionTranslation: function(e) {
            e.preventDefault();
            
            const $link = $(e.target);
            const postId = $link.data('post-id');
            const targetLang = $link.data('target-lang');
            
            if (!confirm('Are you sure you want to translate this post?')) {
                return;
            }
            
            this.startTranslation(postId, targetLang, $link);
        },

        startTranslation: function(postId, targetLang, $trigger) {
            this.core.log(`Starting translation: Post ${postId} to ${targetLang}`);
            
            this.showProgress('Preparing translation...');
            this.core.setButtonState($trigger, 'loading');
            
            this.core.ajax('nexus_translate_post', {
                post_id: postId,
                target_language: targetLang
            })
            .done((response) => {
                if (response.success) {
                    this.showSuccess(response.data);
                    this.refreshMetaBox();
                } else {
                    this.showError(response.data || 'Translation failed');
                }
            })
            .fail(() => {
                this.showError('Server error occurred');
            })
            .always(() => {
                this.core.setButtonState($trigger, 'normal');
                this.hideProgress();
            });
        },

        updateTranslation: function(originalId, translatedId, $trigger) {
            this.showProgress('Updating translation...');
            this.core.setButtonState($trigger, 'loading');
            
            this.core.ajax('nexus_update_translation', {
                original_post_id: originalId,
                translated_post_id: translatedId
            })
            .done((response) => {
                if (response.success) {
                    this.showSuccess(response.data);
                    this.updateTranslationStatus($trigger, 'completed');
                } else {
                    this.showError(response.data || 'Update failed');
                }
            })
            .always(() => {
                this.core.setButtonState($trigger, 'normal');
                this.hideProgress();
            });
        },

        showProgress: function(message) {
            const $progress = $('#nexus-translation-progress');
            if ($progress.length) {
                $progress.find('.nexus-progress-text').text(message);
                $progress.show();
            }
        },

        hideProgress: function() {
            $('#nexus-translation-progress').hide();
        },

        showSuccess: function(data) {
            const $result = $('#nexus-translation-result');
            if ($result.length) {
                let html = `<p>${data.message || 'Translation completed!'}</p>`;
                
                if (data.edit_link || data.view_link) {
                    html += '<p>';
                    if (data.edit_link) {
                        html += `<a href="${data.edit_link}" target="_blank" class="button button-small">Edit</a> `;
                    }
                    if (data.view_link) {
                        html += `<a href="${data.view_link}" target="_blank" class="button button-small">View</a>`;
                    }
                    html += '</p>';
                }
                
                $result.removeClass('error').addClass('success').html(html).show();
            }
        },

        showError: function(message) {
            const $result = $('#nexus-translation-result');
            if ($result.length) {
                $result.removeClass('success').addClass('error').html(`<p>${message}</p>`).show();
            }
        },

        updateTranslationStatus: function($trigger, status) {
            const $item = $trigger.closest('.nexus-translation-item');
            $item.removeClass('nexus-status-outdated nexus-status-pending')
                 .addClass(`nexus-status-${status}`);
            
            $item.find('.nexus-status')
                 .removeClass('nexus-status-outdated nexus-status-pending')
                 .addClass(`nexus-status-${status}`)
                 .text(status.charAt(0).toUpperCase() + status.slice(1));
            
            if (status === 'completed') {
                $trigger.remove();
            }
        },

        refreshMetaBox: function() {
            setTimeout(() => {
                if ($('#nexus-translation-meta-box').length) {
                    location.reload();
                }
            }, 2000);
        }
    };

    /**
     * SETTINGS MODULE
     * Handles settings page functionality
     */
    window.NexusModules.settings = {
        
        init: function(core) {
            this.core = core;
            this.bindEvents();
            this.initValidation();
            core.log('Settings module loaded');
        },

        bindEvents: function() {
            $(document).on('click', '#reset-rate-limits', this.resetRateLimits.bind(this));
            $(document).on('click', '#reset-emergency-stop', this.resetEmergencyStop.bind(this));
            $(document).on('change', 'input[name*="nexus_translator_api_settings"]', this.validateField.bind(this));
        },

        initValidation: function() {
            // Add validation indicators
            $('input[name*="nexus_translator_api_settings"]').each(function() {
                if (!$(this).next('.nexus-validation').length) {
                    $(this).after('<span class="nexus-validation"></span>');
                }
            });
            
            // Validate on load
            this.validateAllFields();
        },

        resetRateLimits: function(e) {
            e.preventDefault();
            
            if (!confirm('Reset all rate limits? This will clear current usage counters.')) {
                return;
            }
            
            const $button = $(e.target);
            this.core.setButtonState($button, 'loading', 'Resetting...');
            
            this.core.ajax('nexus_reset_rate_limits', {})
                .done((response) => {
                    if (response.success) {
                        this.core.showNotice('success', response.data.message);
                        this.refreshUsageDisplay();
                    } else {
                        this.core.showNotice('error', response.data);
                    }
                })
                .always(() => {
                    this.core.setButtonState($button, 'normal');
                });
        },

        resetEmergencyStop: function(e) {
            e.preventDefault();
            
            if (!confirm('Reset emergency stop? This will re-enable all translation functionality.')) {
                return;
            }
            
            const $button = $(e.target);
            this.core.setButtonState($button, 'loading', 'Resetting...');
            
            this.core.ajax('nexus_reset_emergency', {})
                .done((response) => {
                    if (response.success) {
                        this.core.showNotice('success', response.data.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        this.core.showNotice('error', response.data);
                    }
                })
                .always(() => {
                    this.core.setButtonState($button, 'normal');
                });
        },

        validateField: function(e) {
            const $input = $(e.target);
            const name = $input.attr('name');
            const value = $input.val();
            const $indicator = $input.next('.nexus-validation');
            
            const validation = this.getFieldValidation(name, value);
            
            if (validation.valid) {
                $indicator.removeClass('invalid').addClass('valid').text('✓').attr('title', 'Valid');
            } else {
                $indicator.removeClass('valid').addClass('invalid').text('✗').attr('title', validation.message);
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
            
            return { valid: true };
        },

        validateAllFields: function() {
            $('input[name*="nexus_translator_api_settings"]').each((i, el) => {
                this.validateField({ target: el });
            });
        },

        refreshUsageDisplay: function() {
            // Trigger monitoring module update if available
            const monitoring = this.core.getModule('monitoring');
            if (monitoring && monitoring.updateUsage) {
                monitoring.updateUsage();
            }
        }
    };

    /**
     * MONITORING MODULE
     * Handles real-time usage monitoring
     */
    window.NexusModules.monitoring = {
        
        init: function(core) {
            this.core = core;
            this.initUsageDisplay();
            this.startMonitoring();
            core.log('Monitoring module loaded');
        },

        initUsageDisplay: function() {
            this.addProgressBars();
            this.updateUsage();
        },

        startMonitoring: function() {
            // Update every 30 seconds
            setInterval(() => {
                this.updateUsage();
            }, 30000);
        },

        addProgressBars: function() {
            $('.nexus-usage-item').each(function() {
                if (!$(this).find('.nexus-progress-bar').length) {
                    $(this).append('<div class="nexus-progress-bar"><div class="nexus-progress-fill"></div></div>');
                }
            });
        },

        updateUsage: function() {
            this.core.ajax('nexus_get_usage_stats', {})
                .done((response) => {
                    if (response.success) {
                        this.refreshDisplay(response.data);
                    }
                });
        },

        refreshDisplay: function(data) {
            // Update text values
            $('.nexus-usage-value[data-type="today"]').text(`${data.calls_today} / ${data.limit_day}`);
            $('.nexus-usage-value[data-type="hour"]').text(`${data.calls_hour} / ${data.limit_hour}`);
            
            // Update progress bars
            this.updateProgressBars(data);
            
            // Check warnings
            this.checkWarnings(data);
        },

        updateProgressBars: function(data) {
            const hourPercent = Math.min(100, (data.calls_hour / data.limit_hour) * 100);
            const dayPercent = Math.min(100, (data.calls_today / data.limit_day) * 100);
            
            $('.nexus-usage-item').each(function() {
                const $item = $(this);
                const $fill = $item.find('.nexus-progress-fill');
                const type = $item.find('.nexus-usage-value').data('type');
                
                let percent = type === 'today' ? dayPercent : hourPercent;
                let color = percent >= 90 ? '#dc3545' : percent >= 75 ? '#ffc107' : '#007cba';
                
                $fill.css({ width: percent + '%', 'background-color': color });
            });
        },

        checkWarnings: function(data) {
            $('.nexus-usage-warning').remove();
            
            const dayPercent = (data.calls_today / data.limit_day) * 100;
            const hourPercent = (data.calls_hour / data.limit_hour) * 100;
            
            if (dayPercent >= 90) {
                this.showWarning('critical', `Daily limit almost reached (${Math.round(dayPercent)}%)`);
            } else if (dayPercent >= 75) {
                this.showWarning('warning', `Daily usage is high (${Math.round(dayPercent)}%)`);
            }
            
            if (hourPercent >= 90) {
                this.showWarning('critical', `Hourly limit almost reached (${Math.round(hourPercent)}%)`);
            }
        },

        showWarning: function(level, message) {
            const className = level === 'critical' ? 'nexus-critical' : 'nexus-warning';
            const icon = level === 'critical' ? '🚨' : '⚠️';
            
            const $warning = $(`<div class="nexus-usage-warning ${className}">${icon} ${message}</div>`);
            $('.nexus-current-usage').append($warning);
        }
    };

    /**
     * UTILS MODULE
     * Common utilities and helpers
     */
    window.NexusModules.utils = {
        
        init: function(core) {
            this.core = core;
            this.addStyles();
            core.log('Utils module loaded');
        },

        validateForm: function($form) {
            let isValid = true;
            
            $form.find('input[required]').each(function() {
                if (!$(this).val().trim()) {
                    $(this).addClass('error');
                    isValid = false;
                } else {
                    $(this).removeClass('error');
                }
            });
            
            return isValid;
        },

        addStyles: function() {
            if ($('#nexus-dynamic-styles').length) return;
            
            $('<style id="nexus-dynamic-styles">').text(`
                .nexus-validation.valid { color: #46b450; margin-left: 5px; }
                .nexus-validation.invalid { color: #dc3545; margin-left: 5px; }
                .nexus-progress-bar { width: 100%; height: 4px; background: #e0e0e0; border-radius: 2px; margin-top: 5px; }
                .nexus-progress-fill { height: 100%; background: #007cba; transition: all 0.3s ease; border-radius: 2px; }
                .nexus-usage-warning { margin: 10px 0; padding: 8px 12px; border-radius: 4px; font-size: 13px; }
                .nexus-usage-warning.nexus-warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
                .nexus-usage-warning.nexus-critical { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
                .button-success { background: #46b450 !important; border-color: #46b450 !important; }
                .button-danger { background: #dc3545 !important; border-color: #dc3545 !important; }
                input.error { border-color: #dc3545; }
            `).appendTo('head');
        }
    };

    /**
     * BULK MODULE
     * Handles bulk operations on post list
     */
    window.NexusModules.bulk = {
        
        init: function(core) {
            this.core = core;
            this.bindEvents();
            core.log('Bulk module loaded');
        },

        bindEvents: function() {
            // Bulk translation actions
            $(document).on('click', '.nexus-bulk-translate', this.handleBulkTranslation.bind(this));
            
            // Individual row actions
            $(document).on('click', '.nexus-translate-link', this.handleRowTranslation.bind(this));
        },

        handleBulkTranslation: function(e) {
            e.preventDefault();
            
            const selectedPosts = this.getSelectedPosts();
            if (selectedPosts.length === 0) {
                this.core.showNotice('error', 'Please select posts to translate');
                return;
            }
            
            const targetLang = $(e.target).data('target-lang');
            if (!targetLang) {
                this.core.showNotice('error', 'No target language specified');
                return;
            }
            
            if (!confirm(`Translate ${selectedPosts.length} posts to ${targetLang}?`)) {
                return;
            }
            
            this.processBulkTranslation(selectedPosts, targetLang);
        },

        handleRowTranslation: function(e) {
            e.preventDefault();
            
            const $link = $(e.target);
            const postId = $link.data('post-id');
            const targetLang = $link.data('target-lang');
            
            if (!confirm('Translate this post?')) {
                return;
            }
            
            this.translateSinglePost(postId, targetLang, $link);
        },

        getSelectedPosts: function() {
            const selected = [];
            $('#the-list input[name="post[]"]:checked').each(function() {
                selected.push($(this).val());
            });
            return selected;
        },

        processBulkTranslation: function(postIds, targetLang) {
            const total = postIds.length;
            let completed = 0;
            let failed = 0;
            
            this.showBulkProgress(0, total);
            
            // Process posts sequentially to avoid API overload
            this.translatePostSequentially(postIds, targetLang, 0, (success) => {
                if (success) completed++;
                else failed++;
                
                const progress = ((completed + failed) / total) * 100;
                this.updateBulkProgress(progress, completed, failed, total);
                
                if (completed + failed === total) {
                    this.completeBulkTranslation(completed, failed);
                }
            });
        },

        translatePostSequentially: function(postIds, targetLang, index, callback) {
            if (index >= postIds.length) return;
            
            const postId = postIds[index];
            
            this.core.ajax('nexus_translate_post', {
                post_id: postId,
                target_language: targetLang
            })
            .done((response) => {
                callback(response.success);
            })
            .fail(() => {
                callback(false);
            })
            .always(() => {
                // Wait 2 seconds between requests to respect rate limits
                setTimeout(() => {
                    this.translatePostSequentially(postIds, targetLang, index + 1, callback);
                }, 2000);
            });
        },

        translateSinglePost: function(postId, targetLang, $trigger) {
            const $row = $trigger.closest('tr');
            this.showRowProgress($row);
            
            this.core.ajax('nexus_translate_post', {
                post_id: postId,
                target_language: targetLang
            })
            .done((response) => {
                if (response.success) {
                    this.showRowSuccess($row, response.data);
                } else {
                    this.showRowError($row, response.data);
                }
            })
            .fail(() => {
                this.showRowError($row, 'Translation failed');
            });
        },

        showBulkProgress: function(progress, total) {
            const $notice = $(`
                <div id="nexus-bulk-progress" class="notice notice-info">
                    <p><strong>Bulk Translation Progress</strong></p>
                    <div class="nexus-bulk-bar">
                        <div class="nexus-bulk-fill" style="width: ${progress}%"></div>
                    </div>
                    <p class="nexus-bulk-text">Starting translation of ${total} posts...</p>
                </div>
            `);
            
            $('.wp-header-end').after($notice);
        },

        updateBulkProgress: function(progress, completed, failed, total) {
            $('#nexus-bulk-progress .nexus-bulk-fill').css('width', progress + '%');
            $('#nexus-bulk-progress .nexus-bulk-text').text(
                `Progress: ${completed} completed, ${failed} failed, ${total - completed - failed} remaining`
            );
        },

        completeBulkTranslation: function(completed, failed) {
            const $progress = $('#nexus-bulk-progress');
            const message = `Bulk translation completed: ${completed} successful, ${failed} failed`;
            
            $progress.removeClass('notice-info')
                    .addClass(failed === 0 ? 'notice-success' : 'notice-warning')
                    .find('.nexus-bulk-text')
                    .text(message);
            
            setTimeout(() => {
                $progress.fadeOut(() => $progress.remove());
            }, 5000);
        },

        showRowProgress: function($row) {
            $row.find('.row-actions').append(' | <span class="nexus-row-progress">Translating...</span>');
        },

        showRowSuccess: function($row, data) {
            $row.find('.nexus-row-progress').replaceWith(
                `<span class="nexus-row-success">✓ <a href="${data.edit_link}" target="_blank">View Translation</a></span>`
            );
        },

        showRowError: function($row, error) {
            $row.find('.nexus-row-progress').replaceWith(
                `<span class="nexus-row-error">✗ ${error}</span>`
            );
        }
    };

})(jQuery, window);