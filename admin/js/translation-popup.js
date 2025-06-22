/**
 * File: translation-popup.js
 * Location: /admin/js/translation-popup.js
 * 
 * Nexus AI WP Translator - Translation Popup
 */

(function($) {
    'use strict';

    const TranslationPopup = {
        
        /**
         * Initialize popup functionality
         */
        init: function() {
            this.createPopupHTML();
            this.bindEvents();
            this.checkAutoShow();
        },

        /**
         * Create popup HTML structure
         */
        createPopupHTML: function() {
            if ($('#nexus-translation-popup').length) {
                return; // Already exists
            }

            const popupHTML = `
                <div id="nexus-translation-popup" class="nexus-popup-overlay" style="display: none;">
                    <div class="nexus-popup-container">
                        <div class="nexus-popup-header">
                            <h3>🌍 Translate Content</h3>
                            <button class="nexus-popup-close" aria-label="Close">&times;</button>
                        </div>
                        
                        <div class="nexus-popup-content">
                            <div class="nexus-popup-step nexus-step-select" data-step="1">
                                <p><strong>Select target language for translation:</strong></p>
                                <div class="nexus-language-grid" id="nexus-language-options">
                                    <!-- Languages will be populated dynamically -->
                                </div>
                                <div class="nexus-popup-actions">
                                    <button class="button button-secondary nexus-popup-cancel">Cancel</button>
                                    <button class="button button-primary nexus-start-translation" disabled>Start Translation</button>
                                </div>
                            </div>
                            
                            <div class="nexus-popup-step nexus-step-progress" data-step="2" style="display: none;">
                                <div class="nexus-progress-container">
                                    <div class="nexus-progress-spinner"></div>
                                    <h4>Translating content...</h4>
                                    <p class="nexus-progress-status">Preparing translation request...</p>
                                    <div class="nexus-progress-bar">
                                        <div class="nexus-progress-fill"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="nexus-popup-step nexus-step-result" data-step="3" style="display: none;">
                                <div class="nexus-result-container">
                                    <!-- Success or error content -->
                                </div>
                                <div class="nexus-popup-actions">
                                    <button class="button nexus-popup-close">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(popupHTML);
        },

        /**
         * Bind popup events
         */
        bindEvents: function() {
            const $popup = $('#nexus-translation-popup');
            
            // Close popup events
            $popup.on('click', '.nexus-popup-close, .nexus-popup-cancel', this.hidePopup);
            $popup.on('click', '.nexus-popup-overlay', function(e) {
                if (e.target === this) {
                    TranslationPopup.hidePopup();
                }
            });
            
            // Escape key to close
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $popup.is(':visible')) {
                    TranslationPopup.hidePopup();
                }
            });
            
            // Language selection
            $popup.on('click', '.nexus-language-option', this.selectLanguage);
            
            // Start translation
            $popup.on('click', '.nexus-start-translation', this.startTranslation);
            
            // External triggers
            $(document).on('click', '[data-nexus-translate]', this.handleExternalTrigger);
        },

        /**
         * Show popup for specific post
         */
        showPopup: function(postId, availableLanguages) {
            const $popup = $('#nexus-translation-popup');
            
            // Store current post ID
            $popup.data('post-id', postId);
            
            // Reset to first step
            this.goToStep(1);
            
            // Populate language options
            this.populateLanguages(availableLanguages);
            
            // Show popup with animation
            $popup.fadeIn(300);
            $('body').addClass('nexus-popup-open');
            
            // Focus first language option for accessibility
            setTimeout(function() {
                $popup.find('.nexus-language-option:first').focus();
            }, 350);
        },

        /**
         * Hide popup
         */
        hidePopup: function() {
            const $popup = $('#nexus-translation-popup');
            
            $popup.fadeOut(300, function() {
                $('body').removeClass('nexus-popup-open');
                TranslationPopup.resetPopup();
            });
        },

        /**
         * Reset popup to initial state
         */
        resetPopup: function() {
            const $popup = $('#nexus-translation-popup');
            
            $popup.removeData('post-id selected-language');
            $('.nexus-language-option').removeClass('selected');
            $('.nexus-start-translation').prop('disabled', true);
            this.goToStep(1);
        },

        /**
         * Navigate to specific step
         */
        goToStep: function(step) {
            $('.nexus-popup-step').hide();
            $(`.nexus-popup-step[data-step="${step}"]`).show();
        },

        /**
         * Populate language options
         */
        populateLanguages: function(languages) {
            const $container = $('#nexus-language-options');
            $container.empty();
            
            if (!languages || languages.length === 0) {
                $container.html('<p class="nexus-no-languages">No target languages configured. Please check your settings.</p>');
                return;
            }
            
            languages.forEach(function(lang) {
                const $option = $(`
                    <div class="nexus-language-option" data-language="${lang.code}" tabindex="0" role="button">
                        <span class="nexus-language-flag">${lang.flag}</span>
                        <span class="nexus-language-name">${lang.name}</span>
                        <span class="nexus-language-native">${lang.native_name}</span>
                    </div>
                `);
                
                $container.append($option);
            });
        },

        /**
         * Handle language selection
         */
        selectLanguage: function(e) {
            e.preventDefault();
            
            const $option = $(this);
            const language = $option.data('language');
            
            // Update selection
            $('.nexus-language-option').removeClass('selected');
            $option.addClass('selected');
            
            // Store selected language
            $('#nexus-translation-popup').data('selected-language', language);
            
            // Enable start button
            $('.nexus-start-translation').prop('disabled', false);
        },

        /**
         * Start translation process
         */
        startTranslation: function(e) {
            e.preventDefault();
            
            const $popup = $('#nexus-translation-popup');
            const postId = $popup.data('post-id');
            const language = $popup.data('selected-language');
            
            if (!postId || !language) {
                alert('Missing required data for translation');
                return;
            }
            
            // Go to progress step
            TranslationPopup.goToStep(2);
            
            // Start translation with progress updates
            TranslationPopup.performTranslation(postId, language);
        },

        /**
         * Perform the actual translation
         */
        performTranslation: function(postId, language) {
            // Update progress
            this.updateProgress('Sending request to Claude AI...', 25);
            
            $.post(nexusTranslator.ajaxUrl, {
                action: 'nexus_translate_post',
                post_id: postId,
                target_language: language,
                nonce: nexusTranslator.nonce
            })
            .done(function(response) {
                TranslationPopup.updateProgress('Processing translation...', 75);
                
                setTimeout(function() {
                    if (response.success) {
                        TranslationPopup.showSuccess(response.data);
                    } else {
                        TranslationPopup.showError(response.data || 'Translation failed');
                    }
                }, 500);
            })
            .fail(function(xhr) {
                let errorMessage = 'Server error occurred';
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMessage = xhr.responseJSON.data;
                }
                TranslationPopup.showError(errorMessage);
            });
        },

        /**
         * Update progress display
         */
        updateProgress: function(message, percentage) {
            $('.nexus-progress-status').text(message);
            
            if (percentage) {
                $('.nexus-progress-fill').css('width', percentage + '%');
            }
        },

        /**
         * Show success result
         */
        showSuccess: function(data) {
            const $container = $('.nexus-result-container');
            
            let html = `
                <div class="nexus-result-success">
                    <div class="nexus-result-icon">✅</div>
                    <h4>Translation Completed Successfully!</h4>
                    <p>Your content has been translated and saved as a draft.</p>
            `;
            
            if (data.edit_link || data.view_link) {
                html += '<div class="nexus-result-actions">';
                
                if (data.edit_link) {
                    html += `<a href="${data.edit_link}" class="button button-primary" target="_blank">Edit Translation</a> `;
                }
                
                if (data.view_link) {
                    html += `<a href="${data.view_link}" class="button button-secondary" target="_blank">Preview Translation</a>`;
                }
                
                html += '</div>';
            }
            
            if (data.usage && data.usage.input_tokens) {
                html += `<p class="nexus-usage-info"><small>Tokens used: ${data.usage.input_tokens} input, ${data.usage.output_tokens} output</small></p>`;
            }
            
            html += '</div>';
            
            $container.html(html);
            this.goToStep(3);
        },

        /**
         * Show error result
         */
        showError: function(errorMessage) {
            const $container = $('.nexus-result-container');
            
            const html = `
                <div class="nexus-result-error">
                    <div class="nexus-result-icon">❌</div>
                    <h4>Translation Failed</h4>
                    <p>${errorMessage}</p>
                    <div class="nexus-result-actions">
                        <button class="button nexus-retry-translation">Try Again</button>
                        <a href="${nexusTranslator.settingsUrl || '#'}" class="button button-secondary" target="_blank">Check Settings</a>
                    </div>
                </div>
            `;
            
            $container.html(html);
            this.goToStep(3);
            
            // Bind retry event
            $container.find('.nexus-retry-translation').on('click', function() {
                TranslationPopup.goToStep(1);
            });
        },

        /**
         * Handle external triggers
         */
        handleExternalTrigger: function(e) {
            e.preventDefault();
            
            const $trigger = $(this);
            const postId = $trigger.data('nexus-translate') || $trigger.data('post-id');
            const targetLang = $trigger.data('target-lang');
            
            if (!postId) {
                console.error('No post ID specified for translation');
                return;
            }
            
            // If specific language is provided, start translation directly
            if (targetLang) {
                TranslationPopup.startDirectTranslation(postId, targetLang);
                return;
            }
            
            // Otherwise show language selection popup
            TranslationPopup.getAvailableLanguages(postId);
        },

        /**
         * Start direct translation without popup
         */
        startDirectTranslation: function(postId, language) {
            // You might want to show a simpler progress indicator
            if (window.NexusTranslator && typeof window.NexusTranslator.startTranslation === 'function') {
                window.NexusTranslator.startTranslation(postId, language, $(document.body));
            }
        },

        /**
         * Get available languages for a post
         */
        getAvailableLanguages: function(postId) {
            // This would typically come from a server request
            // For now, use default languages from settings
            const defaultLanguages = [
                { code: 'en', name: 'English', native_name: 'English', flag: '🇺🇸' },
                { code: 'es', name: 'Spanish', native_name: 'Español', flag: '🇪🇸' },
                { code: 'de', name: 'German', native_name: 'Deutsch', flag: '🇩🇪' }
            ];
            
            this.showPopup(postId, defaultLanguages);
        },

        /**
         * Check if popup should auto-show
         */
        checkAutoShow: function() {
            // This method is now primarily used for URL-based triggers
            // The main popup trigger is now handled via PHP injection in admin_footer
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('nexus_translate')) {
                const postId = urlParams.get('post_id') || urlParams.get('post');
                if (postId) {
                    setTimeout(() => {
                        this.getAvailableLanguages(postId);
                    }, 1000);
                }
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        TranslationPopup.init();
    });

    // Expose to global scope
    window.TranslationPopup = TranslationPopup;

})(jQuery);