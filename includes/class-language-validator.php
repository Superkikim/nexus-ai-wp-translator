<?php
/**
 * File: class-language-validator.php
 * Location: /includes/class-language-validator.php
 * 
 * Language Validator Class
 * Responsible for: Language validation rules, settings validation, error handling
 * 
 * @package Nexus\Translator
 */

namespace Nexus\Translator;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Language validation class
 * 
 * Handles all validation logic for languages, pairs, settings, and configuration.
 * Provides comprehensive validation rules and error reporting.
 * 
 */
class Language_Validator {
    
    /**
     * Languages instance reference
     * 
     * @var Languages
     */
    private $languages;
    
    /**
     * Validation rules registry
     * 
     * @var array
     */
    private $validation_rules = array();
    
    /**
     * Current validation errors
     * 
     * @var array
     */
    private $validation_errors = array();
    
    /**
     * Constructor
     * 
     * @param Languages $languages Languages instance
     */
    public function __construct($languages) {
        $this->languages = $languages;
        $this->init_validation_rules();
    }
    
    /**
     * Initialize validation rules
     * 
     * @return void
     */
    private function init_validation_rules() {
        $this->validation_rules = array(
            'language_code' => array(
                'required' => true,
                'pattern'  => '/^[a-z]{2}(_[A-Z]{2})?$/',
                'message'  => __('Language code must be in ISO format (e.g., en, fr, en_US)', 'nexus-ai-wp-translator'),
                'examples' => array('en', 'fr', 'en_US', 'fr_FR'),
            ),
            'language_code_length' => array(
                'min_length' => 2,
                'max_length' => 5,
                'message'    => __('Language code must be 2-5 characters long', 'nexus-ai-wp-translator'),
            ),
            'source_target_different' => array(
                'required' => true,
                'message'  => __('Source and target languages must be different', 'nexus-ai-wp-translator'),
            ),
            'pair_supported' => array(
                'required' => true,
                'message'  => __('This language pair is not supported', 'nexus-ai-wp-translator'),
            ),
            'source_required' => array(
                'required' => true,
                'message'  => __('Source language is required', 'nexus-ai-wp-translator'),
            ),
            'target_required' => array(
                'required' => true,
                'message'  => __('Target language is required', 'nexus-ai-wp-translator'),
            ),
            'target_languages_array' => array(
                'type'    => 'array',
                'message' => __('Target languages must be provided as an array', 'nexus-ai-wp-translator'),
            ),
            'target_languages_not_empty' => array(
                'min_count' => 1,
                'message'   => __('At least one target language must be selected', 'nexus-ai-wp-translator'),
            ),
            'target_languages_max' => array(
                'max_count' => 10,
                'message'   => __('Maximum 10 target languages can be selected', 'nexus-ai-wp-translator'),
            ),
        );
        
        // Allow extensions to add custom validation rules
        $this->validation_rules = apply_filters('nexus_language_validation_rules', $this->validation_rules);
    }
    
    /**
     * Validate language code format
     * 
     * @param string $language_code Language code to validate
     * @return bool True if valid
     */
    public function validate_language_code($language_code) {
        $this->clear_errors();
        
        // Check if empty
        if (empty($language_code)) {
            $this->add_error('language_code', __('Language code cannot be empty', 'nexus-ai-wp-translator'));
            return false;
        }
        
        // Check length
        $length = strlen($language_code);
        if ($length < $this->validation_rules['language_code_length']['min_length'] || 
            $length > $this->validation_rules['language_code_length']['max_length']) {
            $this->add_error('language_code', $this->validation_rules['language_code_length']['message']);
            return false;
        }
        
        // Check pattern
        if (!preg_match($this->validation_rules['language_code']['pattern'], $language_code)) {
            $this->add_error('language_code', $this->validation_rules['language_code']['message']);
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate language pair
     * 
     * @param string $source_code Source language code
     * @param string $target_code Target language code
     * @return array Validation result
     */
    public function validate_language_pair($source_code, $target_code) {
        $this->clear_errors();
        $is_valid = true;
        
        // Validate source language code
        if (empty($source_code)) {
            $this->add_error('source', $this->validation_rules['source_required']['message']);
            $is_valid = false;
        } elseif (!$this->validate_language_code($source_code)) {
            $this->add_error('source', $this->validation_rules['language_code']['message']);
            $is_valid = false;
        } elseif (!$this->languages->is_language_supported($source_code)) {
            $this->add_error('source', __('Source language is not supported', 'nexus-ai-wp-translator'));
            $is_valid = false;
        }
        
        // Validate target language code
        if (empty($target_code)) {
            $this->add_error('target', $this->validation_rules['target_required']['message']);
            $is_valid = false;
        } elseif (!$this->validate_language_code($target_code)) {
            $this->add_error('target', $this->validation_rules['language_code']['message']);
            $is_valid = false;
        } elseif (!$this->languages->is_language_supported($target_code)) {
            $this->add_error('target', __('Target language is not supported', 'nexus-ai-wp-translator'));
            $is_valid = false;
        }
        
        // Check if languages are different
        if ($is_valid && $source_code === $target_code) {
            $this->add_error('pair', $this->validation_rules['source_target_different']['message']);
            $is_valid = false;
        }
        
        // Check if pair is supported
        if ($is_valid && !$this->languages->is_pair_supported($source_code, $target_code)) {
            $this->add_error('pair', $this->validation_rules['pair_supported']['message']);
            $is_valid = false;
        }
        
        // Get pair information if valid
        $pair_info = null;
        if ($is_valid) {
            $pair_info = $this->languages->get_pair_info($source_code, $target_code);
            
            // Add complexity warnings
            if ($pair_info && $pair_info['complexity'] === 'high') {
                $this->add_warning('complexity', __('This language pair has high translation complexity', 'nexus-ai-wp-translator'));
            }
        }
        
        return array(
            'valid'    => $is_valid,
            'errors'   => $this->validation_errors,
            'warnings' => $this->get_warnings(),
            'pair'     => $pair_info,
        );
    }
    
    /**
     * Validate plugin settings
     * 
     * @param array $settings Settings to validate
     * @return array Validation result
     */
    public function validate_settings($settings) {
        $this->clear_errors();
        $is_valid = true;
        $cleaned_settings = $settings;
        
        // Validate source language
        if (empty($settings['source_language'])) {
            $this->add_error('source_language', $this->validation_rules['source_required']['message']);
            $is_valid = false;
        } elseif (!$this->languages->is_language_supported($settings['source_language'])) {
            $this->add_error('source_language', __('Selected source language is not supported', 'nexus-ai-wp-translator'));
            $cleaned_settings['source_language'] = 'en'; // Default fallback
            $is_valid = false;
        }
        
        // Validate target languages
        if (empty($settings['target_languages'])) {
            $this->add_error('target_languages', $this->validation_rules['target_languages_not_empty']['message']);
            $is_valid = false;
        } elseif (!is_array($settings['target_languages'])) {
            $this->add_error('target_languages', $this->validation_rules['target_languages_array']['message']);
            $is_valid = false;
        } else {
            // Validate each target language
            $valid_targets = array();
            $invalid_targets = array();
            
            foreach ($settings['target_languages'] as $target) {
                if ($this->languages->is_language_supported($target)) {
                    $valid_targets[] = $target;
                } else {
                    $invalid_targets[] = $target;
                }
            }
            
            // Check if we have valid targets
            if (empty($valid_targets)) {
                $this->add_error('target_languages', $this->validation_rules['target_languages_not_empty']['message']);
                $cleaned_settings['target_languages'] = array('fr', 'es'); // Default fallback
                $is_valid = false;
            } elseif (count($valid_targets) > $this->validation_rules['target_languages_max']['max_count']) {
                $this->add_error('target_languages', $this->validation_rules['target_languages_max']['message']);
                $valid_targets = array_slice($valid_targets, 0, $this->validation_rules['target_languages_max']['max_count']);
                $cleaned_settings['target_languages'] = $valid_targets;
                $is_valid = false;
            } else {
                $cleaned_settings['target_languages'] = $valid_targets;
            }
            
            // Report invalid targets
            if (!empty($invalid_targets)) {
                $this->add_warning('invalid_targets', sprintf(
                    /* translators: %s: Comma-separated list of language codes */
                    __('Invalid target languages removed: %s', 'nexus-ai-wp-translator'),
                    implode(', ', $invalid_targets)
                ));
            }
        }
        
        // Validate source not in targets
        if (!empty($cleaned_settings['source_language']) && 
            !empty($cleaned_settings['target_languages']) && 
            in_array($cleaned_settings['source_language'], $cleaned_settings['target_languages'])) {
            
            $this->add_warning('source_in_targets', __('Source language removed from target languages', 'nexus-ai-wp-translator'));
            $cleaned_settings['target_languages'] = array_diff($cleaned_settings['target_languages'], array($cleaned_settings['source_language']));
        }
        
        return array(
            'valid'     => $is_valid,
            'errors'    => $this->validation_errors,
            'warnings'  => $this->get_warnings(),
            'cleaned'   => $cleaned_settings,
            'changes'   => $this->detect_changes($settings, $cleaned_settings),
        );
    }
    
    /**
     * Validate current plugin settings
     * 
     * @return void
     */
    public function validate_current_settings() {
        $settings = get_option('nexus_ai_translator_settings', array());
        
        if (empty($settings)) {
            return;
        }
        
        $validation = $this->validate_settings($settings);
        
        // Update settings if changes were made
        if (!empty($validation['changes'])) {
            update_option('nexus_ai_translator_settings', $validation['cleaned']);
            
            // Add admin notices for changes
            $this->add_settings_change_notices($validation);
            
            // Fire analytics event
            do_action('nexus_analytics_event', 'language_settings_auto_corrected', array(
                'changes' => $validation['changes'],
                'errors'  => $validation['errors'],
                'warnings' => $validation['warnings'],
                'timestamp' => current_time('mysql'),
            ));
        }
    }
    
    /**
     * Validate language configuration data
     * 
     * @param array $config Language configuration
     * @return array Validation result
     */
    public function validate_language_config($config) {
        $this->clear_errors();
        $is_valid = true;
        
        $required_fields = array('name', 'native', 'locale', 'direction', 'accuracy');
        
        foreach ($required_fields as $field) {
            if (empty($config[$field])) {
                $this->add_error($field, sprintf(
                    /* translators: %s: Field name */
                    __('Field %s is required', 'nexus-ai-wp-translator'),
                    $field
                ));
                $is_valid = false;
            }
        }
        
        // Validate direction
        if (!empty($config['direction']) && !in_array($config['direction'], array('ltr', 'rtl'))) {
            $this->add_error('direction', __('Direction must be either "ltr" or "rtl"', 'nexus-ai-wp-translator'));
            $is_valid = false;
        }
        
        // Validate accuracy
        if (!empty($config['accuracy']) && !in_array($config['accuracy'], array('excellent', 'good', 'fair'))) {
            $this->add_error('accuracy', __('Accuracy must be "excellent", "good", or "fair"', 'nexus-ai-wp-translator'));
            $is_valid = false;
        }
        
        // Validate locale format
        if (!empty($config['locale']) && !preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $config['locale'])) {
            $this->add_error('locale', __('Locale must be in valid format (e.g., en_US, fr_FR)', 'nexus-ai-wp-translator'));
            $is_valid = false;
        }
        
        return array(
            'valid'  => $is_valid,
            'errors' => $this->validation_errors,
        );
    }
    
    /**
     * Get validation errors
     * 
     * @return array Validation errors
     */
    public function get_validation_errors() {
        return $this->validation_errors;
    }
    
    /**
     * Get validation warnings
     * 
     * @return array Validation warnings
     */
    public function get_warnings() {
        return $this->get_option('validation_warnings', array());
    }
    
    /**
     * Check if last validation was successful
     * 
     * @return bool True if valid
     */
    public function is_valid() {
        return empty($this->validation_errors);
    }
    
    /**
     * Get validation rule by name
     * 
     * @param string $rule_name Rule name
     * @return array|false Rule data or false if not found
     */
    public function get_validation_rule($rule_name) {
        return isset($this->validation_rules[$rule_name]) ? $this->validation_rules[$rule_name] : false;
    }
    
    /**
     * Add custom validation rule
     * 
     * @param string $rule_name Rule name
     * @param array $rule_config Rule configuration
     * @return void
     */
    public function add_validation_rule($rule_name, $rule_config) {
        $this->validation_rules[$rule_name] = $rule_config;
    }
    
    /**
     * Clear validation errors
     * 
     * @return void
     */
    private function clear_errors() {
        $this->validation_errors = array();
        $this->set_option('validation_warnings', array());
    }
    
    /**
     * Add validation error
     * 
     * @param string $field Field name
     * @param string $message Error message
     * @return void
     */
    private function add_error($field, $message) {
        $this->validation_errors[$field] = $message;
    }
    
    /**
     * Add validation warning
     * 
     * @param string $field Field name
     * @param string $message Warning message
     * @return void
     */
    private function add_warning($field, $message) {
        $warnings = $this->get_option('validation_warnings', array());
        $warnings[$field] = $message;
        $this->set_option('validation_warnings', $warnings);
    }
    
    /**
     * Detect changes between original and cleaned settings
     * 
     * @param array $original Original settings
     * @param array $cleaned Cleaned settings
     * @return array Changes detected
     */
    private function detect_changes($original, $cleaned) {
        $changes = array();
        
        foreach ($cleaned as $key => $value) {
            if (!isset($original[$key]) || $original[$key] !== $value) {
                $changes[$key] = array(
                    'old' => $original[$key] ?? null,
                    'new' => $value,
                );
            }
        }
        
        return $changes;
    }
    
    /**
     * Add admin notices for settings changes
     * 
     * @param array $validation Validation result
     * @return void
     */
    private function add_settings_change_notices($validation) {
        if (!empty($validation['errors'])) {
            add_action('admin_notices', function() use ($validation) {
                echo '<div class="notice notice-error"><p>';
                echo '<strong>' . __('Nexus AI Translator:', 'nexus-ai-wp-translator') . '</strong> ';
                echo __('Language settings had errors and were automatically corrected.', 'nexus-ai-wp-translator');
                echo '</p></div>';
            });
        }
        
        if (!empty($validation['warnings'])) {
            add_action('admin_notices', function() use ($validation) {
                echo '<div class="notice notice-warning"><p>';
                echo '<strong>' . __('Nexus AI Translator:', 'nexus-ai-wp-translator') . '</strong> ';
                echo __('Some language settings were adjusted:', 'nexus-ai-wp-translator') . '<br>';
                foreach ($validation['warnings'] as $warning) {
                    echo '• ' . esc_html($warning) . '<br>';
                }
                echo '</p></div>';
            });
        }
    }
    
    /**
     * Helper: Get option value (temporary storage for warnings)
     * 
     * @param string $key Option key
     * @param mixed $default Default value
     * @return mixed Option value
     */
    private function get_option($key, $default = null) {
        static $temp_storage = array();
        return isset($temp_storage[$key]) ? $temp_storage[$key] : $default;
    }
    
    /**
     * Helper: Set option value (temporary storage for warnings)
     * 
     * @param string $key Option key
     * @param mixed $value Option value
     * @return void
     */
    private function set_option($key, $value) {
        static $temp_storage = array();
        $temp_storage[$key] = $value;
    }
}