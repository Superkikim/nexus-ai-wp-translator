/**
 * File: admin-script.js
 * Location: /admin/js/admin-script.js
 * 
 * Nexus AI WP Translator - Admin Scripts
 */

(function($) {
    'use strict';

    const NexusTranslator = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.checkForTranslationPopup();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Test API connection
            $(document).on('click', '#test-api-connection', this.testApiConnection);
            
            // Translation buttons in meta box
            $(document).on('click', '.nexus-translate-btn', this.handleTranslation);
            
            // Update translation buttons
            $(document).on('click', '.nexus-update-translation', this.handleUpdateTranslation);
            
            // Translation row actions
            $(document).on('click', '.nexus-translate-link', this.handleRowActionTranslation);
            
            // Set language button
            $(document).on('click', '#nexus-set-language', this.showLanguageSelector);
        },

        /**
         * Test API connection
         */
        testApiConnection: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $result = $('#api-test-result');
            
            $button.prop('disabled', true).text(nexusTranslator.strings.testing || 'Testing...');
            $result.empty();
            
            $.post(nexusTranslator.ajaxUrl, {
                action: 'nexus_test_api_connection',
                nonce: nexusTranslator.nonce
            })
            .done(function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success inline"><p><strong>Success!</strong> ' + response.data.message + '</p></div>');
                    if (response.data.test_translation) {
                        $result.append('<p><small><strong>Test translation:</strong> ' + response.data.test_translation + '</small></p>');
                    }
                } else {
                    $result.html('<div class="notice notice-error inline"><p><strong>Error:</strong> ' + response.data + '</p></div>');
                }
            })
            .fail(function() {
                $result.html('<div class="notice notice-error inline"><p><strong>Error:</strong> Failed to connect to server.</p></div>');
            })
            .always(function() {
                $button.prop('disabled', false).text('Test Connection');
            });
        },

        /**
         * Handle translation from meta box
         */
        handleTranslation: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const postId = $button.data('post-id');
            const targetLang = $button.data('target-lang');
            
            if (!postId || !targetLang) {
                alert('Invalid parameters');
                return;
            }
            
            NexusTranslator.startTranslation(postId, targetLang, $button);
        },

        /**
         * Handle update translation
         */
        handleUpdateTranslation: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const originalId = $button.data('original-id');
            const translatedId = $button.data('translated-id');
            
            if (!confirm('This will update the existing translation with new content. Continue?')) {
                return;
            }
            
            NexusTranslator.updateTranslation(originalId, translatedId, $button);
        },

        /**
         * Handle row action translation
         */
        handleRowActionTranslation: function(e) {
            e.preventDefault();
            
            const $link = $(this);
            const postId = $link.data('post-id');
            const targetLang = $link.data('target-lang');
            
            if (!confirm(nexusTranslator.strings.confirmTranslate || 'Are you sure you want to translate this post?')) {
                return;
            }
            
            NexusTranslator.startTranslation(postId, targetLang, $link);
        },

        /**
         * Start translation process
         */
        startTranslation: function(postId, targetLang, $trigger) {
            // Show progress
            this.showProgress('Preparing translation...');
            
            // Disable trigger
            $trigger.prop('disabled', true);
            
            $.post(nexusTranslator.ajaxUrl, {
                action: 'nexus_translate_post',
                post_id: postId,
                target_language: targetLang,
                nonce: nexusTranslator.nonce
            })
            .done(function(response) {
                if (response.success) {
                    NexusTranslator.showSuccess(
                        'Translation completed successfully!',
                        response.data.edit_link,
                        response.data.view_link
                    );
                    
                    // Refresh meta box if we're on post edit page
                    if ($('#nexus-translation-meta-box').length) {
                        location.reload();
                    }
                } else {
                    NexusTranslator.showError(response.data || 'Translation failed');
                }
            })
            .fail(function(xhr) {
                let errorMessage = 'Server error occurred';
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMessage = xhr.responseJSON.data;
                }
                NexusTranslator.showError(errorMessage);
            })
            .always(function() {
                $trigger.prop('disabled', false);
                NexusTranslator.hideProgress();
            });
        },

        /**
         * Update existing translation
         */
        updateTranslation: function(originalId, translatedId, $trigger) {
            this.showProgress('Updating translation...');
            $trigger.prop('disabled', true);
            
            $.post(nexusTranslator.ajaxUrl, {
                action: 'nexus_update_translation',
                original_post_id: originalId,
                translated_post_id: translatedId,
                nonce: nexusTranslator.nonce
            })
            .done(function(response) {
                if (response.success) {
                    NexusTranslator.showSuccess(
                        'Translation updated successfully!',
                        response.data.edit_link,
                        response.data.view_link
                    );
                    
                    // Update status in meta box
                    $trigger.closest('.nexus-translation-item')
                        .removeClass('nexus-status-outdated')
                        .addClass('nexus-status-completed')
                        .find('.nexus-status')
                        .removeClass('nexus-status-outdated')
                        .addClass('nexus-status-completed')
                        .text('Completed');
                    
                    $trigger.remove();
                } else {
                    NexusTranslator.showError(response.data || 'Update failed');
                }
            })
            .fail(function() {
                NexusTranslator.showError('Server error occurred');
            })
            .always(function() {
                $trigger.prop('disabled', false);
                NexusTranslator.hideProgress();
            });
        },

        /**
         * Show progress indicator
         */
        showProgress: function(message) {
            const $progress = $('#nexus-translation-progress');
            if ($progress.length) {
                $progress.find('.nexus-progress-text').text(message);
                $progress.show();
            }
            
            this.hideResult();
        },

        /**
         * Hide progress indicator
         */
        hideProgress: function() {
            $('#nexus-translation-progress').hide();
        },

        /**
         * Show success message
         */
        showSuccess: function(message, editLink, viewLink) {
            const $result = $('#nexus-translation-result');
            if ($result.length) {
                let html = '<p>' + message + '</p>';
                if (editLink || viewLink) {
                    html += '<p>';
                    if (editLink) {
                        html += '<a href="' + editLink + '" target="_blank" class="button button-small">Edit Translation</a> ';
                    }
                    if (viewLink) {
                        html += '<a href="' + viewLink + '" target="_blank" class="button button-small">View Translation</a>';
                    }
                    html += '</p>';
                }
                
                $result
                    .removeClass('error')
                    .addClass('success')
                    .html(html)
                    .show();
            } else {
                // Fallback to alert if no result container
                alert(message);
            }
        },

        /**
         * Show error message
         */
        showError: function(message) {
            const $result = $('#nexus-translation-result');
            if ($result.length) {
                $result
                    .removeClass('success')
                    .addClass('error')
                    .html('<p>' + message + '</p>')
                    .show();
            } else {
                // Fallback to alert if no result container
                alert('Error: ' + message);
            }
        },

        /**
         * Hide result message
         */
        hideResult: function() {
            $('#nexus-translation-result').hide();
        },

        /**
         * Show language selector
         */
        showLanguageSelector: function(e) {
            e.preventDefault();
            
            // Simple implementation - could be enhanced with a proper modal
            const languages = {
                'fr': '🇫🇷 French',
                'en': '🇺🇸 English',
                'es': '🇪🇸 Spanish',
                'de': '🇩🇪 German',
                'it': '🇮🇹 Italian'
            };
            
            let options = '';
            for (const [code, name] of Object.entries(languages)) {
                options += code + ': ' + name + '\n';
            }
            
            const selectedLang = prompt('Select language for this post:\n\n' + options + '\nEnter language code:');
            
            if (selectedLang && languages[selectedLang]) {
                // Update post meta (this would need to be implemented)
                alert('Language setting functionality would be implemented here');
            }
        },

        /**
         * Check for translation popup after save
         */
        checkForTranslationPopup: function() {
            // This would check for a transient and show popup if needed
            // Implementation depends on how you want to handle the popup trigger
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('nexus_show_popup') === '1') {
                // Show translation popup
                setTimeout(function() {
                    if (confirm('Would you like to translate this post now?')) {
                        // Trigger translation dialog
                        NexusTranslator.showTranslationDialog();
                    }
                }, 1000);
            }
        },

        /**
         * Show translation dialog
         */
        showTranslationDialog: function() {
            // This would show a more sophisticated dialog
            // For now, just focus on the meta box
            const $metaBox = $('#nexus-translation-meta-box');
            if ($metaBox.length) {
                $('html, body').animate({
                    scrollTop: $metaBox.offset().top - 100
                }, 500);
                
                $metaBox.addClass('nexus-highlight');
                setTimeout(function() {
                    $metaBox.removeClass('nexus-highlight');
                }, 2000);
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        NexusTranslator.init();
    });

    // Add highlight animation CSS
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .nexus-highlight {
                animation: nexus-highlight-pulse 2s ease-in-out;
            }
            
            @keyframes nexus-highlight-pulse {
                0%, 100% { background-color: transparent; }
                50% { background-color: #fff3cd; }
            }
        `)
        .appendTo('head');

    // Expose to global scope if needed
    window.NexusTranslator = NexusTranslator;

})(jQuery);