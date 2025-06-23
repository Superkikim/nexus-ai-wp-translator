<?php
/**
 * File: diagnostic-tool.php
 * Location: /admin/diagnostic-tool.php
 * 
 * Nexus AI WP Translator - Diagnostic Tool
 * Add this to your admin menu for debugging
 */

if (!defined('ABSPATH')) {
    exit;
}

class Nexus_Diagnostic_Tool {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_diagnostic_page'));
    }
    
    public function add_diagnostic_page() {
        add_submenu_page(
            'tools.php',
            'Nexus Translator Diagnostics',
            'Nexus Diagnostics',
            'manage_options',
            'nexus-diagnostics',
            array($this, 'render_diagnostic_page')
        );
    }
    
    public function render_diagnostic_page() {
        echo '<div class="wrap">';
        echo '<h1>Nexus Translator Diagnostics</h1>';
        
        // 1. Check Plugin Files
        $this->check_plugin_files();
        
        // 2. Check API Configuration
        $this->check_api_configuration();
        
        // 3. Check Language Settings
        $this->check_language_settings();
        
        // 4. Check Database Structure
        $this->check_database_structure();
        
        // 5. Check AJAX Endpoints
        $this->check_ajax_endpoints();
        
        // 6. Check JavaScript Loading
        $this->check_javascript_loading();
        
        // 7. Test Translation Process
        $this->test_translation_process();
        
        echo '</div>';
    }
    
    private function check_plugin_files() {
        echo '<h2>1. Plugin File Structure</h2>';
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>File</th><th>Status</th><th>Size</th></tr></thead><tbody>';
        
        $files = array(
            'Main Plugin' => NEXUS_TRANSLATOR_PLUGIN_FILE,
            'Main Class' => NEXUS_TRANSLATOR_INCLUDES_DIR . 'class-nexus-translator.php',
            'API Class' => NEXUS_TRANSLATOR_INCLUDES_DIR . 'class-translator-api.php',
            'AJAX Class' => NEXUS_TRANSLATOR_INCLUDES_DIR . 'class-translator-ajax.php',
            'Post Linker' => NEXUS_TRANSLATOR_INCLUDES_DIR . 'class-post-linker.php',
            'Language Manager' => NEXUS_TRANSLATOR_INCLUDES_DIR . 'class-language-manager.php',
            'Admin Class' => NEXUS_TRANSLATOR_INCLUDES_DIR . 'class-translator-admin.php',
            'Admin Script' => NEXUS_TRANSLATOR_PLUGIN_URL . 'admin/js/admin-script.js',
            'Admin CSS' => NEXUS_TRANSLATOR_PLUGIN_URL . 'admin/css/admin-style.css',
            'Meta Box View' => NEXUS_TRANSLATOR_ADMIN_DIR . 'views/translation-meta-box.php'
        );
        
        foreach ($files as $name => $path) {
            $exists = file_exists($path);
            $size = $exists ? filesize($path) : 0;
            $status = $exists ? '✅ Exists' : '❌ Missing';
            echo "<tr><td>$name</td><td>$status</td><td>" . ($exists ? number_format($size) . ' bytes' : 'N/A') . "</td></tr>";
        }
        
        echo '</tbody></table>';
    }
    
    private function check_api_configuration() {
        echo '<h2>2. API Configuration</h2>';
        
        $api = new Translator_API();
        $api_settings = get_option('nexus_translator_api_settings', array());
        
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Setting</th><th>Value</th><th>Status</th></tr></thead><tbody>';
        
        $api_key = $api_settings['claude_api_key'] ?? '';
        $model = $api_settings['model'] ?? 'claude-sonnet-4-20250514';
        
        echo '<tr><td>API Key</td><td>' . (empty($api_key) ? 'Not set' : '***' . substr($api_key, -4)) . '</td><td>' . (empty($api_key) ? '❌ Missing' : '✅ Set') . '</td></tr>';
        echo '<tr><td>Model</td><td>' . esc_html($model) . '</td><td>✅ OK</td></tr>';
        echo '<tr><td>API Configured</td><td>' . ($api->is_api_configured() ? 'Yes' : 'No') . '</td><td>' . ($api->is_api_configured() ? '✅ Ready' : '❌ Not Ready') . '</td></tr>';
        
        echo '</tbody></table>';
        
        if ($api->is_api_configured()) {
            echo '<p><strong>Testing API Connection...</strong></p>';
            $test_result = $api->test_api_connection();
            if ($test_result['success']) {
                echo '<div class="notice notice-success"><p>✅ API Connection Successful: ' . esc_html($test_result['message']) . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>❌ API Connection Failed: ' . esc_html($test_result['error']) . '</p></div>';
            }
        }
    }
    
    private function check_language_settings() {
        echo '<h2>3. Language Settings</h2>';
        
        $language_manager = new Language_Manager();
        $settings = get_option('nexus_translator_language_settings', array());
        
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Setting</th><th>Value</th><th>Status</th></tr></thead><tbody>';
        
        $source_lang = $settings['source_language'] ?? 'Not set';
        $target_langs = $settings['target_languages'] ?? array();
        
        echo '<tr><td>Source Language</td><td>' . esc_html($source_lang) . '</td><td>' . ($source_lang !== 'Not set' ? '✅ Set' : '❌ Not set') . '</td></tr>';
        echo '<tr><td>Target Languages</td><td>' . (empty($target_langs) ? 'None' : implode(', ', $target_langs)) . '</td><td>' . (empty($target_langs) ? '❌ None set' : '✅ Set') . '</td></tr>';
        
        echo '</tbody></table>';
    }
    
    private function check_database_structure() {
        echo '<h2>4. Database Structure</h2>';
        
        global $wpdb;
        
        // Check for existing translations
        $translation_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_nexus_translation_of'");
        $language_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_nexus_language'");
        
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Metric</th><th>Count</th></tr></thead><tbody>';
        echo '<tr><td>Translation Relationships</td><td>' . intval($translation_count) . '</td></tr>';
        echo '<tr><td>Posts with Language</td><td>' . intval($language_count) . '</td></tr>';
        echo '</tbody></table>';
    }
    
    private function check_ajax_endpoints() {
        echo '<h2>5. AJAX Endpoints</h2>';
        
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Action</th><th>Handler</th><th>Status</th></tr></thead><tbody>';
        
        $ajax_actions = array(
            'nexus_translate_post' => 'Translator_AJAX::handle_translate_post',
            'nexus_test_api_connection' => 'Translator_AJAX::handle_test_api_connection',
            'nexus_update_translation' => 'Translator_AJAX::handle_update_translation'
        );
        
        foreach ($ajax_actions as $action => $handler) {
            $hook_exists = has_action("wp_ajax_$action");
            $status = $hook_exists ? '✅ Registered' : '❌ Missing';
            echo "<tr><td>$action</td><td>$handler</td><td>$status</td></tr>";
        }
        
        echo '</tbody></table>';
    }
    
    private function check_javascript_loading() {
        echo '<h2>6. JavaScript Loading</h2>';
        
        echo '<p>Check browser console for JavaScript errors and logs.</p>';
        echo '<p>Expected console messages when loading post edit page:</p>';
        echo '<ul>';
        echo '<li>"Nexus Translator: Script loaded"</li>';
        echo '<li>"Nexus Translator: Initializing..."</li>';
        echo '<li>"Nexus Translator: Found buttons - Translate: X, Update: Y, Test API: Z"</li>';
        echo '</ul>';
        
        // Add inline JavaScript to test
        echo '<script>';
        echo 'console.log("Nexus Diagnostic: Testing JavaScript load");';
        echo 'if (typeof jQuery !== "undefined") console.log("Nexus Diagnostic: jQuery loaded");';
        echo 'if (typeof nexusTranslator !== "undefined") console.log("Nexus Diagnostic: nexusTranslator object found", nexusTranslator);';
        echo 'else console.error("Nexus Diagnostic: nexusTranslator object NOT found");';
        echo '</script>';
    }
    
    private function test_translation_process() {
        echo '<h2>7. Test Translation Process</h2>';
        
        // Get a test post
        $test_post = get_posts(array(
            'numberposts' => 1,
            'post_status' => 'publish'
        ));
        
        if (empty($test_post)) {
            echo '<p>❌ No published posts found to test with.</p>';
            return;
        }
        
        $post = $test_post[0];
        echo '<p>Testing with post: <strong>' . esc_html($post->post_title) . '</strong> (ID: ' . $post->ID . ')</p>';
        
        $translator = new Nexus_Translator();
        $post_linker = $translator->get_post_linker();
        
        // Check current translations
        $translations = $post_linker->get_all_translations($post->ID);
        $current_language = $post_linker->get_post_language($post->ID);
        
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Check</th><th>Result</th></tr></thead><tbody>';
        echo '<tr><td>Current Language</td><td>' . ($current_language ?: 'Not set') . '</td></tr>';
        echo '<tr><td>Existing Translations</td><td>' . count($translations) . '</td></tr>';
        echo '</tbody></table>';
        
        // Test button that would trigger translation
        $language_settings = get_option('nexus_translator_language_settings', array());
        $target_languages = $language_settings['target_languages'] ?? array();
        
        if (!empty($target_languages)) {
            echo '<p><strong>Available target languages:</strong> ' . implode(', ', $target_languages) . '</p>';
            
            foreach ($target_languages as $target_lang) {
                if (!isset($translations[$target_lang])) {
                    echo '<button class="button nexus-translate-btn" data-post-id="' . $post->ID . '" data-target-lang="' . esc_attr($target_lang) . '">Test Translate to ' . esc_html($target_lang) . '</button> ';
                }
            }
        } else {
            echo '<p>❌ No target languages configured.</p>';
        }
    }
}

// Initialize diagnostic tool if we're in admin
if (is_admin()) {
    new Nexus_Diagnostic_Tool();
}