<?php
/**
 * File: class-languages.php
 * Location: /includes/class-languages.php
 * 
 * Main Language Manager Class (Core Registry & Operations)
 * Responsible for: Language definitions, basic operations, WordPress integration
 * 
 * @package Nexus\Translator
 * @since 0.0.1
 */

namespace Nexus\Translator;

use Nexus\Translator\Abstracts\Abstract_Module;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main language manager class - Core functionality only
 * 
 * Handles language registry, basic operations, and coordinates with helper classes.
 * This is the main orchestrator for all language-related functionality.
 * 
 * @since 0.0.1
 */
class Languages extends Abstract_Module {
    
    /**
     * Supported languages registry
     * 
     * @since 0.0.1
     * @var array
     */
    private $supported_languages = array();
    
    /**
     * Available translation pairs
     * 
     * @since 0.0.1
     * @var array
     */
    private $translation_pairs = array();
    
    /**
     * Language validator instance
     * 
     * @since 0.0.1
     * @var Language_Validator
     */
    private $validator;
    
    /**
     * Language AJAX handler instance
     * 
     * @since 0.0.1
     * @var Language_Ajax
     */
    private $ajax_handler;
    
    /**
     * Language analytics instance
     * 
     * @since 0.0.1
     * @var Language_Analytics
     */
    private $analytics;
    
    /**
     * Get module name/identifier
     * 
     * @since 0.0.1
     * @return string Module name
     */
    protected function get_module_name() {
        return 'languages';
    }
    
    /**
     * Module-specific initialization
     * 
     * @since 0.0.1
     * @return void
     */
    protected function module_init() {
        // Initialize core language data
        $this->init_supported_languages();
        $this->init_translation_pairs();
        
        // Load helper classes
        $this->load_helper_classes();
        
        // Initialize helper components
        $this->init_helper_components();
    }
    
    /**
     * Register WordPress hooks
     * 
     * @since 0.0.1
     * @return void
     */
    protected function register_hooks() {
        // Admin initialization
        $this->add_hook('admin_init', array($this, 'admin_init'));
        
        // Let helper classes register their own hooks
        if ($this->ajax_handler) {
            $this->ajax_handler->register_hooks();
        }
        
        if ($this->analytics) {
            $this->analytics->register_hooks();
        }
    }
    
    /**
     * Load helper class files
     * 
     * @since 0.0.1
     * @return void
     */
    private function load_helper_classes() {
        $helper_files = array(
            'class-language-validator.php',
            'class-language-ajax.php',
            'class-language-analytics.php',
        );
        
        foreach ($helper_files as $file) {
            $file_path = NEXUS_AI_TRANSLATOR_INCLUDES_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }
    
    /**
     * Initialize helper components
     * 
     * @since 0.0.1
     * @return void
     */
    private function init_helper_components() {
        // Initialize validator
        if (class_exists('Nexus\\Translator\\Language_Validator')) {
            $this->validator = new Language_Validator($this);
        }
        
        // Initialize AJAX handler
        if (class_exists('Nexus\\Translator\\Language_Ajax')) {
            $this->ajax_handler = new Language_Ajax($this);
        }
        
        // Initialize analytics
        if (class_exists('Nexus\\Translator\\Language_Analytics')) {
            $this->analytics = new Language_Analytics($this);
        }
    }
    
    /**
     * Initialize supported languages registry
     * 
     * @since 0.0.1
     * @return void
     */
    private function init_supported_languages() {
        $this->supported_languages = array(
            'en' => array(
                'name'       => __('English', 'nexus-ai-wp-translator'),
                'native'     => 'English',
                'locale'     => 'en_US',
                'direction'  => 'ltr',
                'accuracy'   => 'excellent',
                'family'     => 'germanic',
                'script'     => 'latin',
                'targets'    => array('fr', 'es', 'de', 'it', 'pt', 'ja', 'ko', 'zh', 'ar', 'ru', 'nl', 'pl'),
            ),
            'fr' => array(
                'name'       => __('French', 'nexus-ai-wp-translator'),
                'native'     => 'Français',
                'locale'     => 'fr_FR',
                'direction'  => 'ltr',
                'accuracy'   => 'excellent',
                'family'     => 'romance',
                'script'     => 'latin',
                'targets'    => array('en', 'es', 'de', 'it', 'pt'),
            ),
            'es' => array(
                'name'       => __('Spanish', 'nexus-ai-wp-translator'),
                'native'     => 'Español',
                'locale'     => 'es_ES',
                'direction'  => 'ltr',
                'accuracy'   => 'excellent',
                'family'     => 'romance',
                'script'     => 'latin',
                'targets'    => array('en', 'fr', 'de', 'it', 'pt'),
            ),
            'de' => array(
                'name'       => __('German', 'nexus-ai-wp-translator'),
                'native'     => 'Deutsch',
                'locale'     => 'de_DE',
                'direction'  => 'ltr',
                'accuracy'   => 'excellent',
                'family'     => 'germanic',
                'script'     => 'latin',
                'targets'    => array('en', 'fr', 'es', 'it', 'nl'),
            ),
            'it' => array(
                'name'       => __('Italian', 'nexus-ai-wp-translator'),
                'native'     => 'Italiano',
                'locale'     => 'it_IT',
                'direction'  => 'ltr',
                'accuracy'   => 'excellent',
                'family'     => 'romance',
                'script'     => 'latin',
                'targets'    => array('en', 'fr', 'es', 'de'),
            ),
            'pt' => array(
                'name'       => __('Portuguese', 'nexus-ai-wp-translator'),
                'native'     => 'Português',
                'locale'     => 'pt_PT',
                'direction'  => 'ltr',
                'accuracy'   => 'excellent',
                'family'     => 'romance',
                'script'     => 'latin',
                'targets'    => array('en', 'fr', 'es'),
            ),
            'ja' => array(
                'name'       => __('Japanese', 'nexus-ai-wp-translator'),
                'native'     => '日本語',
                'locale'     => 'ja',
                'direction'  => 'ltr',
                'accuracy'   => 'excellent',
                'family'     => 'japonic',
                'script'     => 'japanese',
                'targets'    => array('en'),
            ),
            'ko' => array(
                'name'       => __('Korean', 'nexus-ai-wp-translator'),
                'native'     => '한국어',
                'locale'     => 'ko_KR',
                'direction'  => 'ltr',
                'accuracy'   => 'excellent',
                'family'     => 'koreanic',
                'script'     => 'hangul',
                'targets'    => array('en'),
            ),
            'zh' => array(
                'name'       => __('Chinese (Simplified)', 'nexus-ai-wp-translator'),
                'native'     => '简体中文',
                'locale'     => 'zh_CN',
                'direction'  => 'ltr',
                'accuracy'   => 'excellent',
                'family'     => 'sino-tibetan',
                'script'     => 'chinese',
                'targets'    => array('en'),
            ),
            'ar' => array(
                'name'       => __('Arabic', 'nexus-ai-wp-translator'),
                'native'     => 'العربية',
                'locale'     => 'ar',
                'direction'  => 'rtl',
                'accuracy'   => 'good',
                'family'     => 'semitic',
                'script'     => 'arabic',
                'targets'    => array('en'),
            ),
            'ru' => array(
                'name'       => __('Russian', 'nexus-ai-wp-translator'),
                'native'     => 'Русский',
                'locale'     => 'ru_RU',
                'direction'  => 'ltr',
                'accuracy'   => 'good',
                'family'     => 'slavic',
                'script'     => 'cyrillic',
                'targets'    => array('en'),
            ),
            'nl' => array(
                'name'       => __('Dutch', 'nexus-ai-wp-translator'),
                'native'     => 'Nederlands',
                'locale'     => 'nl_NL',
                'direction'  => 'ltr',
                'accuracy'   => 'excellent',
                'family'     => 'germanic',
                'script'     => 'latin',
                'targets'    => array('en', 'de'),
            ),
            'pl' => array(
                'name'       => __('Polish', 'nexus-ai-wp-translator'),
                'native'     => 'Polski',
                'locale'     => 'pl_PL',
                'direction'  => 'ltr',
                'accuracy'   => 'good',
                'family'     => 'slavic',
                'script'     => 'latin',
                'targets'    => array('en'),
            ),
        );
        
        // Allow extensions to add custom languages
        $this->supported_languages = apply_filters('nexus_supported_languages', $this->supported_languages);
    }
    
    /**
     * Initialize translation pairs from language data
     * 
     * @since 0.0.1
     * @return void
     */
    private function init_translation_pairs() {
        foreach ($this->supported_languages as $source_code => $source_data) {
            if (!isset($source_data['targets']) || !is_array($source_data['targets'])) {
                continue;
            }
            
            foreach ($source_data['targets'] as $target_code) {
                if (!isset($this->supported_languages[$target_code])) {
                    continue;
                }
                
                $pair_key = $source_code . '_' . $target_code;
                $this->translation_pairs[$pair_key] = array(
                    'source'      => $source_code,
                    'target'      => $target_code,
                    'source_name' => $source_data['name'],
                    'target_name' => $this->supported_languages[$target_code]['name'],
                    'accuracy'    => $this->calculate_pair_accuracy($source_code, $target_code),
                    'complexity'  => $this->calculate_pair_complexity($source_code, $target_code),
                    'family_match' => $this->check_family_match($source_code, $target_code),
                    'script_match' => $this->check_script_match($source_code, $target_code),
                );
            }
        }
        
        // Allow extensions to modify translation pairs
        $this->translation_pairs = apply_filters('nexus_translation_pairs', $this->translation_pairs);
    }
    
    /**
     * Get all supported languages
     * 
     * @since 0.0.1
     * @return array Supported languages
     */
    public function get_supported_languages() {
        return $this->supported_languages;
    }
    
    /**
     * Get language information by code
     * 
     * @since 0.0.1
     * @param string $language_code Language code
     * @return array|false Language data or false if not found
     */
    public function get_language($language_code) {
        return isset($this->supported_languages[$language_code]) ? 
               $this->supported_languages[$language_code] : false;
    }
    
    /**
     * Check if language is supported
     * 
     * @since 0.0.1
     * @param string $language_code Language code
     * @return bool True if supported
     */
    public function is_language_supported($language_code) {
        return isset($this->supported_languages[$language_code]);
    }
    
    /**
     * Get available translation pairs
     * 
     * @since 0.0.1
     * @param string $source_code Optional source language filter
     * @return array Translation pairs
     */
    public function get_translation_pairs($source_code = '') {
        if (empty($source_code)) {
            return $this->translation_pairs;
        }
        
        $filtered_pairs = array();
        foreach ($this->translation_pairs as $pair_key => $pair_data) {
            if ($pair_data['source'] === $source_code) {
                $filtered_pairs[$pair_key] = $pair_data;
            }
        }
        
        return $filtered_pairs;
    }
    
    /**
     * Check if translation pair is supported
     * 
     * @since 0.0.1
     * @param string $source_code Source language code
     * @param string $target_code Target language code
     * @return bool True if supported
     */
    public function is_pair_supported($source_code, $target_code) {
        $pair_key = $source_code . '_' . $target_code;
        return isset($this->translation_pairs[$pair_key]);
    }
    
    /**
     * Get translation pair information
     * 
     * @since 0.0.1
     * @param string $source_code Source language code
     * @param string $target_code Target language code
     * @return array|false Pair information or false if not supported
     */
    public function get_pair_info($source_code, $target_code) {
        $pair_key = $source_code . '_' . $target_code;
        return isset($this->translation_pairs[$pair_key]) ? 
               $this->translation_pairs[$pair_key] : false;
    }
    
    /**
     * Get WordPress locale for language code
     * 
     * @since 0.0.1
     * @param string $language_code Language code
     * @return string WordPress locale
     */
    public function get_wordpress_locale($language_code) {
        $language = $this->get_language($language_code);
        return $language ? $language['locale'] : $language_code;
    }
    
    /**
     * Get language direction (LTR/RTL)
     * 
     * @since 0.0.1
     * @param string $language_code Language code
     * @return string Direction ('ltr' or 'rtl')
     */
    public function get_language_direction($language_code) {
        $language = $this->get_language($language_code);
        return $language ? $language['direction'] : 'ltr';
    }
    
    /**
     * Get language family
     * 
     * @since 0.0.1
     * @param string $language_code Language code
     * @return string Language family
     */
    public function get_language_family($language_code) {
        $language = $this->get_language($language_code);
        return $language ? $language['family'] : 'unknown';
    }
    
    /**
     * Get language script system
     * 
     * @since 0.0.1
     * @param string $language_code Language code
     * @return string Script system
     */
    public function get_language_script($language_code) {
        $language = $this->get_language($language_code);
        return $language ? $language['script'] : 'unknown';
    }
    
    /**
     * Get formatted language list for dropdowns
     * 
     * @since 0.0.1
     * @param bool $include_native Include native names
     * @param string $filter_family Optional family filter
     * @return array Formatted language list
     */
    public function get_language_dropdown_options($include_native = true, $filter_family = '') {
        $options = array();
        
        foreach ($this->supported_languages as $code => $data) {
            // Apply family filter if specified
            if (!empty($filter_family) && $data['family'] !== $filter_family) {
                continue;
            }
            
            if ($include_native && !empty($data['native'])) {
                $options[$code] = sprintf('%s (%s)', $data['name'], $data['native']);
            } else {
                $options[$code] = $data['name'];
            }
        }
        
        return $options;
    }
    
    /**
     * Get available target languages for source
     * 
     * @since 0.0.1
     * @param string $source_code Source language code
     * @return array Available target languages
     */
    public function get_available_targets($source_code) {
        $language = $this->get_language($source_code);
        if (!$language || !isset($language['targets'])) {
            return array();
        }
        
        $targets = array();
        foreach ($language['targets'] as $target_code) {
            $target_language = $this->get_language($target_code);
            if ($target_language) {
                $targets[$target_code] = $target_language['name'];
            }
        }
        
        return $targets;
    }
    
    /**
     * Delegate validation to validator component
     * 
     * @since 0.0.1
     * @param string $source_code Source language code
     * @param string $target_code Target language code
     * @return array Validation result
     */
    public function validate_language_pair($source_code, $target_code) {
        if ($this->validator) {
            return $this->validator->validate_language_pair($source_code, $target_code);
        }
        
        // Fallback basic validation
        return $this->basic_pair_validation($source_code, $target_code);
    }
    
    /**
     * Get validator instance
     * 
     * @since 0.0.1
     * @return Language_Validator|null Validator instance
     */
    public function get_validator() {
        return $this->validator;
    }
    
    /**
     * Get AJAX handler instance
     * 
     * @since 0.0.1
     * @return Language_Ajax|null AJAX handler instance
     */
    public function get_ajax_handler() {
        return $this->ajax_handler;
    }
    
    /**
     * Get analytics instance
     * 
     * @since 0.0.1
     * @return Language_Analytics|null Analytics instance
     */
    public function get_analytics() {
        return $this->analytics;
    }
    
    /**
     * Admin initialization hook
     * 
     * @since 0.0.1
     * @return void
     */
    public function admin_init() {
        // Delegate to validator for settings validation
        if ($this->validator) {
            $this->validator->validate_current_settings();
        }
    }
    
    /**
     * Helper: Calculate translation accuracy for pair
     * 
     * @since 0.0.1
     * @param string $source_code Source language
     * @param string $target_code Target language
     * @return string Accuracy level
     */
    private function calculate_pair_accuracy($source_code, $target_code) {
        $source_accuracy = $this->supported_languages[$source_code]['accuracy'] ?? 'good';
        $target_accuracy = $this->supported_languages[$target_code]['accuracy'] ?? 'good';
        
        // Return lowest accuracy level
        if ($source_accuracy === 'excellent' && $target_accuracy === 'excellent') {
            return 'excellent';
        } elseif (in_array('good', array($source_accuracy, $target_accuracy))) {
            return 'good';
        } else {
            return 'fair';
        }
    }
    
    /**
     * Helper: Calculate translation complexity for pair
     * 
     * @since 0.0.1
     * @param string $source_code Source language
     * @param string $target_code Target language
     * @return string Complexity level
     */
    private function calculate_pair_complexity($source_code, $target_code) {
        $complexity_score = 0;
        
        // RTL languages add complexity
        if ($this->get_language_direction($source_code) === 'rtl' || 
            $this->get_language_direction($target_code) === 'rtl') {
            $complexity_score += 2;
        }
        
        // Different language families add complexity
        if (!$this->check_family_match($source_code, $target_code)) {
            $complexity_score += 1;
        }
        
        // Different scripts add complexity
        if (!$this->check_script_match($source_code, $target_code)) {
            $complexity_score += 1;
        }
        
        if ($complexity_score >= 3) {
            return 'high';
        } elseif ($complexity_score >= 1) {
            return 'medium';
        } else {
            return 'low';
        }
    }
    
    /**
     * Helper: Check if languages are from same family
     * 
     * @since 0.0.1
     * @param string $source_code Source language
     * @param string $target_code Target language
     * @return bool True if same family
     */
    private function check_family_match($source_code, $target_code) {
        $source_family = $this->get_language_family($source_code);
        $target_family = $this->get_language_family($target_code);
        
        return $source_family === $target_family && $source_family !== 'unknown';
    }
    
    /**
     * Helper: Check if languages use same script
     * 
     * @since 0.0.1
     * @param string $source_code Source language
     * @param string $target_code Target language
     * @return bool True if same script
     */
    private function check_script_match($source_code, $target_code) {
        $source_script = $this->get_language_script($source_code);
        $target_script = $this->get_language_script($target_code);
        
        return $source_script === $target_script && $source_script !== 'unknown';
    }
    
    /**
     * Fallback basic validation when validator not available
     * 
     * @since 0.0.1
     * @param string $source_code Source language code
     * @param string $target_code Target language code
     * @return array Basic validation result
     */
    private function basic_pair_validation($source_code, $target_code) {
        $errors = array();
        
        if (!$this->is_language_supported($source_code)) {
            $errors['source'] = __('Source language not supported.', 'nexus-ai-wp-translator');
        }
        
        if (!$this->is_language_supported($target_code)) {
            $errors['target'] = __('Target language not supported.', 'nexus-ai-wp-translator');
        }
        
        if ($source_code === $target_code) {
            $errors['pair'] = __('Source and target must be different.', 'nexus-ai-wp-translator');
        }
        
        if (empty($errors) && !$this->is_pair_supported($source_code, $target_code)) {
            $errors['pair'] = __('Translation pair not supported.', 'nexus-ai-wp-translator');
        }
        
        return array(
            'valid'  => empty($errors),
            'errors' => $errors,
            'pair'   => empty($errors) ? $this->get_pair_info($source_code, $target_code) : null,
        );
    }
}