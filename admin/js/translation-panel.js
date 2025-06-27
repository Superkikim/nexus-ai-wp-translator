/**
 * File: translation-panel.js
 * Location: /admin/js/translation-panel.js
 * 
 * Nexus AI WP Translator - Translation Panel Scripts
 */

(function($) {
    'use strict';

    const TranslationPanel = {
        
        /**
         * Initialize
         */
        init: function() {
            console.log('Nexus Translation Panel: Initializing');
            this.bindEvents();
            console.log('Nexus Translation Panel: Initialization complete');
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Manual translation button
            $(document).on('click', '#nexus-manual-translate', this.handleManualTranslation);
            
            // Auto-translate checkbox change
            $(document).on('change', 'input[name="nexus_auto_translate"]', this.handleAutoTranslateChange);
            
            // Language checkbox changes
            $(document).on('change', 'input[name="nexus_target_languages[]"]', this.handleLanguageSelection);
            
            // Test API connection (if present)
            $(document).on('click', '#test-api-connection', this.testApiConnection);
        },

        /**
         * Handle manual translation
         */
        handleManualTranslation: function(e) {
            e.preventDefault();
            console.log('Nexus Translation Panel: Manual translation triggered');
            
            const selectedLanguages = TranslationPanel.getSelectedLanguages();
            
            if (selectedLanguages.length === 0) {
                alert(nexusTranslator.strings.selectLanguages || 'Please select at least one target language.');
                return;
            }
            
            const postId = TranslationPanel.getPostId();
            if (!postId) {
                alert('Post ID not found');
                return;
            }
            
            TranslationPanel.startTranslation(postId, selectedLanguages);
        },

        /**
         * Handle auto-translate checkbox change
         */
        handleAutoTranslateChange: function() {
            const isChecked = $(this).is(':checked');
            const $languageSection = $('.nexus-language-checkboxes');
            
            if (isChecked) {
                $languageSection.addClass('nexus-required');
                console.log('Auto-translate enabled');
            } else {
                $languageSection.removeClass('nexus-required');
                console.log('Auto-translate disabled');
            }
        },

        /**
         * Handle language selection
         */
        handleLanguageSelection: function() {
            const selectedCount = $('input[name="nexus_target_languages[]"]:checked').length;
            const $button = $('#nexus-manual-translate');
            
            if (selectedCount > 0) {
                $button.prop('disabled', false);
                $button.text((nexusTranslator.strings.translateNow || 'Translate Now') + ' (' + selectedCount + ')');
            } else {
                $button.prop('disabled', true);
                $button.text(nexusTranslator.strings.translateNow || 'Translate Now');
            }
        },

        /**
         * Get selected languages
         */
        getSelectedLanguages: function() {
            const languages = [];
            $('input[name="nexus_target_languages[]"]:checked').each(function() {
                languages.push($(this).val());
            });
            return languages;
        },

        /**
         * Get current post ID
         */
        getPostId: function() {
            // Try multiple ways to get post ID
            if (typeof window.typenow !== 'undefined' && window.typenow) {
                const urlParams = new URLSearchParams(window.location.search);
                return urlParams.get('post');
            }
            
            if ($('#post_ID').length) {
                return $('#post_ID').val();
            }
            
            const match = window.location.href.match(/[?&]post=(\d+)/);
            if (match) {
                return match[1];
            }
            
            return null;
        },

        /**
         * Start translation process
         */
        startTranslation: function(postId, languages) {
            console.log('Starting translation for post', postId, 'to languages:', languages);
            
            this.showProgress('Starting translations...');
            
            const promises = languages.map(lang => this.translateToLanguage(postId, lang));
            
            Promise.allSettled(promises).then(results => {
                this.handleTranslationResults(results, languages);
            });
        },

        /**
         * Translate to specific language
         */
        translateToLanguage: function(postId, language) {
            return new Promise((resolve, reject) => {
                $.post(nexusTranslator.ajaxUrl, {
                    action: 'nexus_translate_post',
                    post_id: postId,
                    target_language: language,
                    nonce: nexusTranslator.nonce
                })
                .done(function(response) {
                    if (response.success) {
                        resolve({
                            language: language,
                            success: true,
                            data: response.data
                        });
                    } else {
                        resolve({
                            language: language,
                            success: false,
                            error: response.data || 'Translation failed'
                        });
                    }
                })
                .fail(function(xhr, status, error) {
                    resolve({
                        language: language,
                        success: false,
                        error: 'Server error: ' + error
                    });
                });
            });
        },

        /**
         * Handle translation results
         */
        handleTranslationResults: function(results, languages) {
            this.hideProgress();
            
            const successes = results.filter(r => r.value.success);
            const failures = results.filter(r => !r.value.success);
            
            let html = '';
            
            if (successes.length > 0) {
                html += '<div class="nexus-results-success">';
                html += '<h4>✅ Successful Translations (' + successes.length + ')</h4>';
                
                successes.forEach(result => {
                    const data = result.value;
                    html += '<div class="nexus-result-item">';
                    html += '<strong>' + this.getLanguageName(data.language) + ':</strong> ';
                    html += '<a href="' + data.data.edit_link + '" target="_blank">Edit</a> | ';
                    html += '<a href="' + data.data.view_link + '" target="_blank">View</a>';
                    html += '</div>';
                });
                
                html += '</div>';
            }
            
            if (failures.length > 0) {
                html += '<div class="nexus-results-errors">';
                html += '<h4>❌ Failed Translations (' + failures.length + ')</h4>';
                
                failures.forEach(result => {
                    const data = result.value;
                    html += '<div class="nexus-result-item nexus-error">';
                    html += '<strong>' + this.getLanguageName(data.language) + ':</strong> ';
                    html += '<span class="nexus-error-text">' + data.error + '</span>';
                    html += '</div>';
                });
                
                html += '</div>';
            }
            
            this.showResults(html, successes.length > 0 ? 'success' : 'error');
            
            // Refresh page if all translations successful
            if (successes.length > 0 && failures.length === 0) {
                setTimeout(function() {
                    window.location.reload();
                }, 3000);
            }
        },

        /**
         * Get language name
         */
        getLanguageName: function(code) {
            const languages = {
                'fr': 'Français',
                'en': 'English',
                'es': 'Español',
                'de': 'Deutsch',
                'it': 'Italiano',
                'pt': 'Português',
                'nl': 'Nederlands',
                'ru': 'Русский',
                'ja': '日本語',
                'zh': '中文',
                'ar': 'العربية',
                'hi': 'हिन्दी',
                'ko': '한국어',
                'sv': 'Svenska',
                'da': 'Dansk',
                'no': 'Norsk',
                'fi': 'Suomi',
                'pl': 'Polski',
                'tr': 'Türkçe',
                'he': 'עברית'
            };
            
            return languages[code] || code.toUpperCase();
        },

        /**
         * Show progress
         */
        showProgress: function(message) {
            const $progress = $('#nexus-panel-progress');
            const $results = $('#nexus-panel-results');
            
            $results.hide();
            $progress.find('.nexus-progress-text').text(message);
            $progress.show();
            
            // Disable manual translate button
            $('#nexus-manual-translate').prop('disabled', true);
        },

        /**
         * Hide progress
         */
        hideProgress: function() {
            $('#nexus-panel-progress').hide();
            $('#nexus-manual-translate').prop('disabled', false);
            
            // Update button text
            this.handleLanguageSelection();
        },

        /**
         * Show results
         */
        showResults: function(html, type) {
            const $results = $('#nexus-panel-results');
            
            $results
                .removeClass('success error')
                .addClass(type)
                .html(html)
                .show();
        },

        /**
         * Test API connection
         */
        testApiConnection: function(e) {
            e.preventDefault();
            console.log('Testing API connection from panel');
            
            const $button = $(this);
            const $result = $('#api-test-result');
            
            $button.prop('disabled', true).text('Testing...');
            $result.empty();
            
            $.post(nexusTranslator.ajaxUrl, {
                action: 'nexus_test_api_connection',
                nonce: nexusTranslator.nonce
            })
            .done(function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success inline"><p><strong>Success!</strong> ' + response.data.message + '</p></div>');
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
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('#nexus-translation-panel').length) {
            console.log('Nexus Translation Panel: Found panel, initializing...');
            TranslationPanel.init();
        }
    });

    // Expose to global scope
    window.TranslationPanel = TranslationPanel;

})(jQuery);