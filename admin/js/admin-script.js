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
            console.log('Nexus Translator: Initializing');
            this.bindEvents();
            this.checkForTranslationPopup();
            console.log('Nexus Translator: Initialization complete');
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            console.log('Nexus Translator: Binding events');
            
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
            
            // Debug button counts
            setTimeout(function() {
                const translateBtns = $('.nexus-translate-btn').length;
                const updateBtns = $('.nexus-update-translation').length;
                const testBtn = $('#test-api-connection').length;
                
                console.log('Nexus Translator: Found buttons - Translate:', translateBtns, 'Update:', updateBtns, 'Test API:', testBtn);
                
                if (translateBtns === 0 && $('#nexus-translation-meta-box').length) {
                    console.log('Nexus Translator: Meta box exists but no translate buttons found');
                }
            }, 1000);
        },

        /**
         * Test API connection
         */
        testApiConnection: function(e) {
            e.preventDefault();
            console.log('Nexus Translator: Testing API connection');
            
            const $button = $(this);
            const $result = $('#api-test-result');
            
            $button.prop('disabled', true).text(nexusTranslator.strings.testing || 'Testing...');
            $result.empty();
            
            $.post(nexusTranslator.ajaxUrl, {
                action: 'nexus_test_api_connection',
                nonce: nexusTranslator.nonce
            })
            .done(function(response) {
                console.log('Nexus Translator: API test response:', response);
                if (response.success) {
                    $result.html('<div class="notice notice-success inline"><p><strong>Success!</strong> ' + response.data.message + '</p></div>');
                    if (response.data.test_translation) {
                        $result.append('<p><small><strong>Test translation:</strong> ' + response.data.test_translation + '</small></p>');
                    }
                } else {
                    $result.html('<div class="notice notice-error inline"><p><strong>Error:</strong> ' + response.data + '</p></div>');
                }
            })
            .fail(function(xhr, status, error) {
                console.error('Nexus Translator: API test failed:', status, error);
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
            console.log('Nexus Translator: Translation button clicked');
            
            const $button = $(this);
            const postId = $button.data('post-id');
            const targetLang = $button.data('target-lang');
            
            console.log('Nexus Translator: Translation params - Post ID:', postId, 'Target Lang:', targetLang);
            
            if (!postId || !targetLang) {
                console.error('Nexus Translator: Invalid parameters');
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
            console.log('Nexus Translator: Update translation button clicked');
            
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
            console.log('Nexus Translator: Row action translation clicked');
            
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
            console.log('Nexus Translator: Starting translation for post', postId, 'to', targetLang);
            
            if (typeof nexusTranslator === 'undefined') {
                console.error('Nexus Translator: nexusTranslator object not found');
                alert('Translation configuration not loaded. Please refresh the page.');
                return;
            }
            
            this.showProgress('Preparing translation...');
            $trigger.prop('disabled', true);
            
            console.log('Nexus Translator: Sending AJAX request');
            
            $.post(nexusTranslator.ajaxUrl, {
                action: 'nexus_translate_post',
                post_id: postId,
                target_language: targetLang,
                nonce: nexusTranslator.nonce
            })
            .done(function(response) {
                console.log('Nexus Translator: Translation response:', response);
                if (response.success) {
                    NexusTranslator.showSuccess(
                        'Translation completed successfully!',
                        response.data.edit_link,
                        response.data.view_link
                    );
                    
                    if ($('#nexus-translation-meta-box').length) {
                        console.log('Nexus Translator: Reloading page to refresh meta box');
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    }
                } else {
                    console.error('Nexus Translator: Translation failed:', response.data);
                    NexusTranslator.showError(response.data || 'Translation failed');
                }
            })
            .fail(function(xhr, status, error) {
                console.error('Nexus Translator: AJAX failed:', status, error, xhr.responseText);
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
            console.log('Nexus Translator: Updating translation');
            
            this.showProgress('Updating translation...');
            $trigger.prop('disabled', true);
            
            $.post(nexusTranslator.ajaxUrl, {
                action: 'nexus_update_translation',
                original_post_id: originalId,
                translated_post_id: translatedId,
                nonce: nexusTranslator.nonce
            })
            .done(function(response) {
                console.log('Nexus Translator: Update response:', response);
                if (response.success) {
                    NexusTranslator.showSuccess(
                        'Translation updated successfully!',
                        response.data.edit_link,
                        response.data.view_link
                    );
                    
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
            .fail(function(xhr, status, error) {
                console.error('Nexus Translator: Update failed:', status, error);
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
            console.log('Nexus Translator: Showing progress:', message);
            const $progress = $('#nexus-translation-progress');
            if ($progress.length) {
                $progress.find('.nexus-progress-text').text(message);
                $progress.show();
            } else {
                console.log('Nexus Translator: Progress element not found');
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
            console.log('Nexus Translator: Showing success:', message);
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
                console.log('Nexus Translator: Result element not found, using alert');
                alert(message);
            }
        },

        /**
         * Show error message
         */
        showError: function(message) {
            console.log('Nexus Translator: Showing error:', message);
            const $result = $('#nexus-translation-result');
            if ($result.length) {
                $result
                    .removeClass('success')
                    .addClass('error')
                    .html('<p>' + message + '</p>')
                    .show();
            } else {
                console.log('Nexus Translator: Result element not found, using alert');
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
            console.log('Nexus Translator: Language selector clicked');
            
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
                alert('Language setting functionality would be implemented here');
            }
        },

        /**
         * Check for translation popup after save
         */
        checkForTranslationPopup: function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('nexus_show_popup') === '1') {
                setTimeout(function() {
                    if (confirm('Would you like to translate this post now?')) {
                        NexusTranslator.showTranslationDialog();
                    }
                }, 1000);
            }
        },

        /**
         * Show translation dialog
         */
        showTranslationDialog: function() {
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

    $(document).ready(function() {
        console.log('Nexus Translator: Document ready');
        
        if (typeof nexusTranslator === 'undefined') {
            console.error('Nexus Translator: nexusTranslator object not found');
        } else {
            console.log('Nexus Translator: Configuration loaded:', nexusTranslator);
        }
        
        NexusTranslator.init();
    });

    window.NexusTranslator = NexusTranslator;

})(jQuery);