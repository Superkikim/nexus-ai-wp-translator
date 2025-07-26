/**
 * File: admin-modules.js
 * Location: /admin/js/admin-modules.js
 * 
 * Nexus AI WP Translator - Modular Components COMPLETE VERSION
 * FIXED: Removed duplicate emergency handlers only, preserved all other functionality
 */

(function($, window) {
    'use strict';

    // Module container
    window.NexusModules = {};

    /**
     * TRANSLATION MODULE - Enhanced with protection
     * Handles post translation functionality with loop protection
     */
    window.NexusModules.translation = {
        
        // Protection: Active translations tracking
        activeTranslations: new Set(),
        
        init: function(core) {
            this.core = core;
            this.bindEvents();
            core.log('Translation module loaded with protection');
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
            
            // Protection 1: Check if translation in progress
            const translationKey = `${postId}_${targetLang}`;
            if (this.activeTranslations.has(translationKey)) {
                this.core.showNotice('warning', 'Translation already in progress for this language');
                return;
            }
            
            // Protection 2: Check if button already disabled
            if ($button.prop('disabled')) {
                return;
            }
            
            this.startTranslation(postId, targetLang, $button);
        },

        handleUpdateTranslation: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const originalId = $button.data('original-id');
            const translatedId = $button.data('translated-id');
            
            // Protection: Check if button already disabled
            if ($button.prop('disabled')) {
                return;
            }
            
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
            
            // Protection: Check if translation in progress
            const translationKey = `${postId}_${targetLang}`;
            if (this.activeTranslations.has(translationKey)) {
                this.core.showNotice('warning', 'Translation already in progress');
                return;
            }
            
            if (!confirm('Are you sure you want to translate this post?')) {
                return;
            }
            
            this.startTranslation(postId, targetLang, $link);
        },

        startTranslation: function(postId, targetLang, $trigger) {
            const translationKey = `${postId}_${targetLang}`;
            
            this.core.log(`Starting PROTECTED translation: Post ${postId} to ${targetLang}`);
            
            // Protection 3: Add to active translations list
            this.activeTranslations.add(translationKey);
            
            // Protection 4: Disable ALL translation buttons for this post
            this.disableTranslationButtons(postId);
            
            this.showProgress('Preparing translation...');
            
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
                // Protection 5: Always cleanup
                this.activeTranslations.delete(translationKey);
                this.enableTranslationButtons(postId);
                this.core.log(`Translation cleanup completed for ${translationKey}`);
            });
        },

        updateTranslation: function(originalId, translatedId, $trigger) {
            this.core.setButtonState($trigger, 'loading', 'Updating...');
            
            this.core.ajax('nexus_update_translation', {
                original_id: originalId,
                translated_id: translatedId
            })
            .done((response) => {
                if (response.success) {
                    this.core.showNotice('success', 'Translation updated successfully');
                    this.core.setButtonState($trigger, 'success', 'Updated!');
                    this.updateTranslationStatus($trigger, 'completed');
                } else {
                    this.core.showNotice('error', response.data || 'Update failed');
                    this.core.setButtonState($trigger, 'error', 'Failed');
                }
            })
            .always(() => {
                setTimeout(() => {
                    this.core.setButtonState($trigger, 'normal');
                }, 3000);
            });
        },

        disableTranslationButtons: function(postId) {
            $(`.nexus-translate-btn[data-post-id="${postId}"]`).prop('disabled', true);
            $(`.nexus-translate-link[data-post-id="${postId}"]`).addClass('disabled');
        },

        enableTranslationButtons: function(postId) {
            $(`.nexus-translate-btn[data-post-id="${postId}"]`).prop('disabled', false);
            $(`.nexus-translate-link[data-post-id="${postId}"]`).removeClass('disabled');
        },

        showProgress: function(message) {
            const $result = $('#nexus-translation-result');
            if ($result.length) {
                $result.removeClass('error success').addClass('info').html(`<p>${message}</p>`).show();
            }
        },

        showSuccess: function(data) {
            const $result = $('#nexus-translation-result');
            if ($result.length) {
                let html = `<p>✅ Translation completed successfully!</p>`;
                
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
        },

        // Diagnostic method
        getActiveTranslations: function() {
            return Array.from(this.activeTranslations);
        }
    };

    /**
     * SETTINGS MODULE - FIXED: Removed emergency handlers only
     */
    window.NexusModules.settings = {
        
        init: function(core) {
            this.core = core;
            this.bindEvents();
            this.initValidation();
            core.log('Settings module loaded');
        },

        bindEvents: function() {
            // REMOVED: Emergency button handlers (now handled in admin-core.js)
            // $(document).on('click', '#reset-rate-limits', this.resetRateLimits.bind(this));
            // $(document).on('click', '#reset-emergency-stop', this.resetEmergencyStop.bind(this));
            
            // Keep only settings-specific events
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

        validateField: function(e) {
            const $field = $(e.target);
            const value = $field.val().trim();
            const name = $field.attr('name');
            
            const validation = this.getFieldValidation(name, value);
            const $indicator = $field.next('.nexus-validation');
            
            if ($indicator.length) {
                $indicator.removeClass('valid invalid')
                         .addClass(validation.valid ? 'valid' : 'invalid')
                         .attr('title', validation.message || '')
                         .html(validation.valid ? '✓' : '✗');
            }
            
            return validation.valid;
        },

        getFieldValidation: function(name, value) {
            if (!name) return { valid: true };
            
            if (name.includes('claude_api_key')) {
                if (!value) return { valid: false, message: 'API key required' };
                if (value.length < 10) return { valid: false, message: 'API key too short' };
                if (!value.startsWith('sk-')) return { valid: false, message: 'Invalid format' };
            }
            
            if (name.includes('max_tokens')) {
                const val = parseInt(value);
                if (isNaN(val)) return { valid: false, message: 'Must be a number' };
                if (val < 100 || val > 8000) return { valid: false, message: 'Must be 100-8000' };
            }
            
            if (name.includes('max_calls')) {
                const val = parseInt(value);
                if (isNaN(val)) return { valid: false, message: 'Must be a number' };
                if (val < 1) return { valid: false, message: 'Must be at least 1' };
            }
            
            if (name.includes('min_request_interval')) {
                const val = parseInt(value);
                if (isNaN(val)) return { valid: false, message: 'Must be a number' };
                if (val > 60) return { valid: false, message: 'Too long' };
                if (val < 0) return { valid: false, message: 'Cannot be negative' };
            }
            
            if (name.includes('request_timeout')) {
                const val = parseInt(value);
                if (isNaN(val)) return { valid: false, message: 'Must be a number' };
                if (val > 300) return { valid: false, message: 'Very long timeout' };
                if (val < 10) return { valid: false, message: 'Too short' };
            }
            
            if (name.includes('temperature')) {
                const val = parseFloat(value);
                if (isNaN(val)) return { valid: false, message: 'Must be a number' };
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
     * MONITORING MODULE - Usage and performance tracking
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
                    this.updateUsageDisplay(response.data);
                    this.checkUsageLimits(response.data);
                }
            });
        },
        
        updateUsageDisplay: function(data) {
            // Update text displays
            $('.nexus-usage-today').text(`${data.calls_today} / ${data.limit_day}`);
            $('.nexus-usage-hour').text(`${data.calls_hour} / ${data.limit_hour}`);
            
            // Update progress bars
            const dayPercent = (data.calls_today / data.limit_day) * 100;
            const hourPercent = (data.calls_hour / data.limit_hour) * 100;
            
            $('.nexus-progress-fill').eq(0).css('width', `${Math.min(dayPercent, 100)}%`);
            $('.nexus-progress-fill').eq(1).css('width', `${Math.min(hourPercent, 100)}%`);
        },
        
        checkUsageLimits: function(data) {
            const dayPercent = (data.calls_today / data.limit_day) * 100;
            const hourPercent = (data.calls_hour / data.limit_hour) * 100;
            
            // Remove existing warnings
            $('.nexus-usage-warning').remove();
            
            if (dayPercent >= 90) {
                this.showUsageWarning('Daily limit almost reached!', 'critical');
            } else if (dayPercent >= 75) {
                this.showUsageWarning('High daily usage detected', 'warning');
            }
            
            if (hourPercent >= 90) {
                this.showUsageWarning('Hourly limit almost reached!', 'critical');
            }
        },
        
        showUsageWarning: function(message, level) {
            const className = level === 'critical' ? 'nexus-critical' : 'nexus-warning';
            const icon = level === 'critical' ? '🚨' : '⚠️';
            
            const $warning = $(`<div class="nexus-usage-warning ${className}">${icon} ${message}</div>`);
            $('.nexus-current-usage').append($warning);
        }
    };

    /**
     * UTILS MODULE - Common utilities
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
                .disabled { opacity: 0.5; pointer-events: none; }
            `).appendTo('head');
        }
    };

    /**
     * BULK MODULE - Bulk operations functionality
     */
    window.NexusModules.bulk = {
        init: function(core) {
            this.core = core;
            this.bindEvents();
            core.log('Bulk module loaded');
        },
        
        bindEvents: function() {
            $(document).on('click', '.nexus-bulk-translate', this.handleBulkTranslation.bind(this));
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
                // Continue with next post after a short delay
                setTimeout(() => {
                    this.translatePostSequentially(postIds, targetLang, index + 1, callback);
                }, 1000);
            });
        },
        
        translateSinglePost: function(postId, targetLang, $trigger) {
            this.core.setButtonState($trigger, 'loading', 'Translating...');
            
            this.core.ajax('nexus_translate_post', {
                post_id: postId,
                target_language: targetLang
            })
            .done((response) => {
                if (response.success) {
                    this.core.setButtonState($trigger, 'success', 'Translated!');
                    this.core.showNotice('success', 'Translation completed');
                } else {
                    this.core.setButtonState($trigger, 'error', 'Failed');
                    this.core.showNotice('error', response.data || 'Translation failed');
                }
            })
            .fail(() => {
                this.core.setButtonState($trigger, 'error', 'Failed');
                this.core.showNotice('error', 'Server error occurred');
            })
            .always(() => {
                setTimeout(() => {
                    this.core.setButtonState($trigger, 'normal');
                }, 3000);
            });
        },
        
        showBulkProgress: function(current, total) {
            const $container = $('.nexus-bulk-progress');
            if (!$container.length) {
                $('body').append('<div class="nexus-bulk-progress" style="position:fixed;top:50px;right:20px;background:white;border:1px solid #ddd;padding:15px;border-radius:5px;z-index:9999;"></div>');
            }
            
            $('.nexus-bulk-progress').html(`
                <div><strong>Bulk Translation Progress</strong></div>
                <div>Processing: ${current} / ${total}</div>
                <div class="nexus-progress-bar" style="width:200px;height:10px;background:#eee;margin:10px 0;">
                    <div class="nexus-progress-fill" style="width:0%;height:100%;background:#007cba;"></div>
                </div>
            `);
        },
        
        updateBulkProgress: function(percentage, completed, failed, total) {
            $('.nexus-bulk-progress .nexus-progress-fill').css('width', `${percentage}%`);
            $('.nexus-bulk-progress').find('div').eq(1).text(`Completed: ${completed}, Failed: ${failed} / ${total}`);
        },
        
        completeBulkTranslation: function(completed, failed) {
            setTimeout(() => {
                $('.nexus-bulk-progress').fadeOut(() => {
                    $(this).remove();
                });
                
                this.core.showNotice('success', `Bulk translation completed: ${completed} successful, ${failed} failed`);
                
                // Reload page to show updated statuses
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            }, 1000);
        }
    };

    /**
     * ANALYTICS MODULE - Analytics and reporting
     */
    window.NexusModules.analytics = {
        init: function(core) {
            this.core = core;
            this.bindEvents();
            this.loadAnalytics();
            core.log('Analytics module loaded');
        },
        
        bindEvents: function() {
            $(document).on('click', '#refresh-analytics', this.loadAnalytics.bind(this));
            $(document).on('click', '#export-analytics', this.exportAnalytics.bind(this));
            $(document).on('click', '#clear-analytics', this.clearAnalytics.bind(this));
        },
        
        loadAnalytics: function() {
            this.core.ajax('nexus_get_analytics', {})
            .done((response) => {
                if (response.success) {
                    this.displayAnalytics(response.data);
                }
            });
        },
        
        displayAnalytics: function(data) {
            // Update analytics display
            $('.nexus-analytics-total-requests').text(data.totals.requests || 0);
            $('.nexus-analytics-successful').text(data.totals.successful || 0);
            $('.nexus-analytics-failed').text(data.totals.failed || 0);
            $('.nexus-analytics-success-rate').text(data.success_rate || '0%');
            
            // Update charts if they exist
            if (typeof this.updateCharts === 'function') {
                this.updateCharts(data);
            }
        },
        
        exportAnalytics: function() {
            this.core.ajax('nexus_export_analytics', {})
            .done((response) => {
                if (response.success) {
                    // Create download link
                    const blob = new Blob([response.data.csv], { type: 'text/csv' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = response.data.filename;
                    a.click();
                    window.URL.revokeObjectURL(url);
                    
                    this.core.showNotice('success', 'Analytics exported successfully');
                }
            });
        },
        
        clearAnalytics: function() {
            if (!confirm('Clear all analytics data? This cannot be undone.')) {
                return;
            }
            
            this.core.ajax('nexus_clear_analytics', {})
            .done((response) => {
                if (response.success) {
                    this.loadAnalytics(); // Refresh display
                    this.core.showNotice('success', 'Analytics data cleared');
                }
            });
        }
    };

})(jQuery, window);