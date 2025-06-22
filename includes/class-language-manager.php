<?php
/**
 * File: class-language-manager.php
 * Location: /includes/class-language-manager.php
 * 
 * Language Manager Class
 * 
 * Manages supported languages and language-related functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class Language_Manager {
    
    /**
     * Supported languages
     */
    private $supported_languages = array(
        'fr' => array(
            'name' => 'French',
            'native_name' => 'Français',
            'flag' => '🇫🇷',
            'direction' => 'ltr'
        ),
        'en' => array(
            'name' => 'English',
            'native_name' => 'English',
            'flag' => '🇺🇸',
            'direction' => 'ltr'
        ),
        'es' => array(
            'name' => 'Spanish',
            'native_name' => 'Español',
            'flag' => '🇪🇸',
            'direction' => 'ltr'
        ),
        'de' => array(
            'name' => 'German',
            'native_name' => 'Deutsch',
            'flag' => '🇩🇪',
            'direction' => 'ltr'
        ),
        'it' => array(
            'name' => 'Italian',
            'native_name' => 'Italiano',
            'flag' => '🇮🇹',
            'direction' => 'ltr'
        ),
        'pt' => array(
            'name' => 'Portuguese',
            'native_name' => 'Português',
            'flag' => '🇵🇹',
            'direction' => 'ltr'
        ),
        'nl' => array(
            'name' => 'Dutch',
            'native_name' => 'Nederlands',
            'flag' => '🇳🇱',
            'direction' => 'ltr'
        ),
        'ru' => array(
            'name' => 'Russian',
            'native_name' => 'Русский',
            'flag' => '🇷🇺',
            'direction' => 'ltr'
        ),
        'ja' => array(
            'name' => 'Japanese',
            'native_name' => '日本語',
            'flag' => '🇯🇵',
            'direction' => 'ltr'
        ),
        'zh' => array(
            'name' => 'Chinese',
            'native_name' => '中文',
            'flag' => '🇨🇳',
            'direction' => 'ltr'
        )
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add language detection for admin
        add_action('admin_init', array($this, 'detect_admin_language'));
    }
    
    /**
     * Get supported languages
     * 
     * @return array Supported languages
     */
    public function get_supported_languages() {
        return apply_filters('nexus_translator_supported_languages', $this->supported_languages);
    }
    
    /**
     * Get language info
     * 
     * @param string $code Language code
     * @return array|false Language info or false if not found
     */
    public function get_language_info($code) {
        $languages = $this->get_supported_languages();
        return $languages[$code] ?? false;
    }
    
    /**
     * Get language name
     * 
     * @param string $code Language code
     * @param bool $native Return native name
     * @return string Language name
     */
    public function get_language_name($code, $native = false) {
        $info = $this->get_language_info($code);
        if (!$info) {
            return strtoupper($code);
        }
        
        return $native ? $info['native_name'] : $info['name'];
    }
    
    /**
     * Get language flag
     * 
     * @param string $code Language code
     * @return string Flag emoji
     */
    public function get_language_flag($code) {
        $info = $this->get_language_info($code);
        return $info ? $info['flag'] : '🌍';
    }
    
    /**
     * Validate language code
     * 
     * @param string $code Language code
     * @return bool Is valid
     */
    public function is_valid_language_code($code) {
        return array_key_exists($code, $this->get_supported_languages());
    }
    
    /**
     * Get configured source language
     * 
     * @return string Source language code
     */
    public function get_source_language() {
        $settings = get_option('nexus_translator_language_settings', array());
        return $settings['source_language'] ?? 'fr';
    }
    
    /**
     * Get configured target languages
     * 
     * @return array Target language codes
     */
    public function get_target_languages() {
        $settings = get_option('nexus_translator_language_settings', array());
        return $settings['target_languages'] ?? array('en');
    }
    
    /**
     * Set source language
     * 
     * @param string $code Language code
     * @return bool Success
     */
    public function set_source_language($code) {
        if (!$this->is_valid_language_code($code)) {
            return false;
        }
        
        $settings = get_option('nexus_translator_language_settings', array());
        $settings['source_language'] = $code;
        
        return update_option('nexus_translator_language_settings', $settings);
    }
    
    /**
     * Set target languages
     * 
     * @param array $codes Language codes
     * @return bool Success
     */
    public function set_target_languages($codes) {
        // Validate all codes
        foreach ($codes as $code) {
            if (!$this->is_valid_language_code($code)) {
                return false;
            }
        }
        
        $settings = get_option('nexus_translator_language_settings', array());
        $settings['target_languages'] = array_unique($codes);
        
        return update_option('nexus_translator_language_settings', $settings);
    }
    
    /**
     * Get language pairs for translation
     * 
     * @return array Language pairs
     */
    public function get_translation_pairs() {
        $source = $this->get_source_language();
        $targets = $this->get_target_languages();
        
        $pairs = array();
        foreach ($targets as $target) {
            if ($target !== $source) {
                $pairs[] = array(
                    'source' => $source,
                    'target' => $target,
                    'source_name' => $this->get_language_name($source),
                    'target_name' => $this->get_language_name($target),
                    'source_flag' => $this->get_language_flag($source),
                    'target_flag' => $this->get_language_flag($target)
                );
            }
        }
        
        return $pairs;
    }
    
    /**
     * Detect admin language
     */
    public function detect_admin_language() {
        $current_user = wp_get_current_user();
        if ($current_user && $current_user->ID) {
            $user_locale = get_user_meta($current_user->ID, 'locale', true);
            if ($user_locale) {
                $language_code = substr($user_locale, 0, 2);
                
                // Store detected language for potential use
                if ($this->is_valid_language_code($language_code)) {
                    update_user_meta($current_user->ID, 'nexus_detected_language', $language_code);
                }
            }
        }
    }
    
    /**
     * Get user's preferred language
     * 
     * @param int $user_id User ID (optional, defaults to current user)
     * @return string Language code
     */
    public function get_user_language($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Check stored detected language
        $detected = get_user_meta($user_id, 'nexus_detected_language', true);
        if ($detected && $this->is_valid_language_code($detected)) {
            return $detected;
        }
        
        // Fallback to site language
        $locale = get_locale();
        $language_code = substr($locale, 0, 2);
        
        return $this->is_valid_language_code($language_code) ? $language_code : 'en';
    }
    
    /**
     * Get languages for select dropdown
     * 
     * @param bool $include_flags Include flag emojis
     * @return array Formatted languages for select
     */
    public function get_languages_for_select($include_flags = true) {
        $languages = $this->get_supported_languages();
        $options = array();
        
        foreach ($languages as $code => $info) {
            $label = $info['name'];
            if ($include_flags) {
                $label = $info['flag'] . ' ' . $label;
            }
            $options[$code] = $label;
        }
        
        return $options;
    }
    
    /**
     * Get translation statistics
     * 
     * @return array Translation statistics
     */
    public function get_translation_statistics() {
        global $wpdb;
        
        $stats = array();
        $languages = $this->get_supported_languages();
        
        foreach ($languages as $code => $info) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} 
                 WHERE meta_key = '_nexus_language' 
                 AND meta_value = %s",
                $code
            ));
            
            $stats[$code] = array(
                'name' => $info['name'],
                'flag' => $info['flag'],
                'count' => (int) $count
            );
        }
        
        return $stats;
    }
    
    /**
     * Export language settings
     * 
     * @return array Language settings for export
     */
    public function export_settings() {
        return array(
            'source_language' => $this->get_source_language(),
            'target_languages' => $this->get_target_languages(),
            'supported_languages' => $this->get_supported_languages()
        );
    }
    
    /**
     * Import language settings
     * 
     * @param array $settings Settings to import
     * @return bool Success
     */
    public function import_settings($settings) {
        if (!is_array($settings)) {
            return false;
        }
        
        $current_settings = get_option('nexus_translator_language_settings', array());
        
        if (isset($settings['source_language']) && $this->is_valid_language_code($settings['source_language'])) {
            $current_settings['source_language'] = $settings['source_language'];
        }
        
        if (isset($settings['target_languages']) && is_array($settings['target_languages'])) {
            $valid_targets = array();
            foreach ($settings['target_languages'] as $code) {
                if ($this->is_valid_language_code($code)) {
                    $valid_targets[] = $code;
                }
            }
            if (!empty($valid_targets)) {
                $current_settings['target_languages'] = $valid_targets;
            }
        }
        
        return update_option('nexus_translator_language_settings', $current_settings);
    }
}