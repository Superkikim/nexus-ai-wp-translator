<?php
/**
 * File: class-translation-content.php
 * Location: /includes/class-translation-content.php
 * 
 * Translation Content Processor Class
 * Responsible for: Content preparation, HTML cleaning, custom fields handling
 */

namespace Nexus\Translator;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Translation content processor class
 * 
 * Handles content preparation, cleaning, and processing for translation.
 * Manages content extraction, HTML preservation, and custom field handling.
 */
class Translation_Content {
    
    private $translator;
    
    public function __construct($translator) {
        $this->translator = $translator;
    }
    
    /**
     * Prepare content for translation
     * 
     * @param WP_Post $post Source post
     * @param array $options Content processing options
     * @return array Prepared content parts
     */
    public function prepare_content($post, $options = array()) {
        $content_parts = array();
        
        // Title
        if (!empty($post->post_title)) {
            $content_parts['title'] = $this->clean_text_content($post->post_title);
        }
        
        // Content
        if (!empty($post->post_content)) {
            $content_parts['content'] = $this->prepare_html_content($post->post_content, $options);
        }
        
        // Excerpt
        if (!empty($post->post_excerpt)) {
            $content_parts['excerpt'] = $this->clean_text_content($post->post_excerpt);
        }
        
        // Custom fields (if enabled)
        if ($options['translate_custom_fields'] ?? false) {
            $custom_fields = $this->get_translatable_custom_fields($post->ID, $options);
            if (!empty($custom_fields)) {
                $content_parts = array_merge($content_parts, $custom_fields);
            }
        }
        
        // SEO fields (if available)
        if ($options['translate_seo_fields'] ?? false) {
            $seo_fields = $this->get_seo_fields($post->ID);
            if (!empty($seo_fields)) {
                $content_parts = array_merge($content_parts, $seo_fields);
            }
        }
        
        return apply_filters('nexus_translation_content_parts', $content_parts, $post, $options);
    }
    
    /**
     * Prepare HTML content for translation
     * 
     * @param string $content Raw HTML content
     * @param array $options Processing options
     * @return string Prepared content
     */
    public function prepare_html_content($content, $options = array()) {
        // Store shortcodes for later restoration
        $shortcodes = $this->extract_shortcodes($content);
        
        // Remove shortcodes temporarily
        $content = $this->remove_shortcodes($content, $shortcodes);
        
        // Store HTML blocks that shouldn't be translated
        $protected_blocks = $this->extract_protected_blocks($content);
        $content = $this->protect_blocks($content, $protected_blocks);
        
        // Clean HTML but preserve structure
        $content = $this->clean_html_content($content);
        
        // Store for restoration after translation
        update_transient('nexus_temp_shortcodes_' . md5($content), $shortcodes, HOUR_IN_SECONDS);
        update_transient('nexus_temp_blocks_' . md5($content), $protected_blocks, HOUR_IN_SECONDS);
        
        return $content;
    }
    
    /**
     * Restore content after translation
     * 
     * @param string $translated_content Translated content
     * @param string $original_content Original content for reference
     * @return string Restored content
     */
    public function restore_content($translated_content, $original_content) {
        $content_hash = md5($original_content);
        
        // Restore protected blocks
        $protected_blocks = get_transient('nexus_temp_blocks_' . $content_hash);
        if ($protected_blocks) {
            $translated_content = $this->restore_blocks($translated_content, $protected_blocks);
            delete_transient('nexus_temp_blocks_' . $content_hash);
        }
        
        // Restore shortcodes
        $shortcodes = get_transient('nexus_temp_shortcodes_' . $content_hash);
        if ($shortcodes) {
            $translated_content = $this->restore_shortcodes($translated_content, $shortcodes);
            delete_transient('nexus_temp_shortcodes_' . $content_hash);
        }
        
        return $translated_content;
    }
    
    /**
     * Clean text content for translation
     * 
     * @param string $text Raw text
     * @return string Cleaned text
     */
    public function clean_text_content($text) {
        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Trim
        $text = trim($text);
        
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        
        return $text;
    }
    
    /**
     * Clean HTML content while preserving structure
     * 
     * @param string $content Raw HTML content
     * @return string Cleaned content
     */
    public function clean_html_content($content) {
        // Remove WordPress auto-generated p tags around shortcodes
        $content = preg_replace('/<p>\s*\[([^\]]+)\]\s*<\/p>/', '[$1]', $content);
        
        // Clean up empty paragraphs
        $content = preg_replace('/<p[^>]*>[\s&nbsp;]*<\/p>/i', '', $content);
        
        // Clean up multiple line breaks
        $content = preg_replace('/(<br\s*\/?>[\s]*){3,}/i', '<br><br>', $content);
        
        // Ensure valid HTML structure
        $content = wp_kses_post($content);
        
        return trim($content);
    }
    
    /**
     * Extract shortcodes from content
     * 
     * @param string $content Content with shortcodes
     * @return array Extracted shortcodes
     */
    private function extract_shortcodes($content) {
        $shortcodes = array();
        
        // Find all shortcodes
        preg_match_all('/\[([^\]]+)\]/', $content, $matches, PREG_OFFSET_CAPTURE);
        
        foreach ($matches[0] as $index => $match) {
            $shortcode = $match[0];
            $position = $match[1];
            
            $placeholder = '{{SHORTCODE_' . $index . '}}';
            $shortcodes[$placeholder] = $shortcode;
        }
        
        return $shortcodes;
    }
    
    /**
     * Remove shortcodes and replace with placeholders
     * 
     * @param string $content Content with shortcodes
     * @param array $shortcodes Shortcode mapping
     * @return string Content with placeholders
     */
    private function remove_shortcodes($content, $shortcodes) {
        foreach ($shortcodes as $placeholder => $shortcode) {
            $content = str_replace($shortcode, $placeholder, $content);
        }
        
        return $content;
    }
    
    /**
     * Restore shortcodes from placeholders
     * 
     * @param string $content Content with placeholders
     * @param array $shortcodes Shortcode mapping
     * @return string Content with restored shortcodes
     */
    private function restore_shortcodes($content, $shortcodes) {
        foreach ($shortcodes as $placeholder => $shortcode) {
            $content = str_replace($placeholder, $shortcode, $content);
        }
        
        return $content;
    }
    
    /**
     * Extract protected blocks that shouldn't be translated
     * 
     * @param string $content HTML content
     * @return array Protected blocks
     */
    private function extract_protected_blocks($content) {
        $protected_blocks = array();
        $block_index = 0;
        
        // Code blocks
        $content = preg_replace_callback(
            '/<(code|pre)[^>]*>.*?<\/\1>/is',
            function($matches) use (&$protected_blocks, &$block_index) {
                $placeholder = '{{PROTECTED_BLOCK_' . $block_index . '}}';
                $protected_blocks[$placeholder] = $matches[0];
                $block_index++;
                return $placeholder;
            },
            $content
        );
        
        // Script tags
        $content = preg_replace_callback(
            '/<script[^>]*>.*?<\/script>/is',
            function($matches) use (&$protected_blocks, &$block_index) {
                $placeholder = '{{PROTECTED_BLOCK_' . $block_index . '}}';
                $protected_blocks[$placeholder] = $matches[0];
                $block_index++;
                return $placeholder;
            },
            $content
        );
        
        // Style tags
        $content = preg_replace_callback(
            '/<style[^>]*>.*?<\/style>/is',
            function($matches) use (&$protected_blocks, &$block_index) {
                $placeholder = '{{PROTECTED_BLOCK_' . $block_index . '}}';
                $protected_blocks[$placeholder] = $matches[0];
                $block_index++;
                return $placeholder;
            },
            $content
        );
        
        // URLs in href and src attributes
        $content = preg_replace_callback(
            '/(href|src)=["\']([^"\']+)["\']/i',
            function($matches) use (&$protected_blocks, &$block_index) {
                $placeholder = '{{PROTECTED_BLOCK_' . $block_index . '}}';
                $protected_blocks[$placeholder] = $matches[0];
                $block_index++;
                return $placeholder;
            },
            $content
        );
        
        return $protected_blocks;
    }
    
    /**
     * Protect blocks in content
     * 
     * @param string $content Original content
     * @param array $protected_blocks Protected blocks mapping
     * @return string Content with protected placeholders
     */
    private function protect_blocks($content, $protected_blocks) {
        foreach ($protected_blocks as $placeholder => $block) {
            $content = str_replace($block, $placeholder, $content);
        }
        
        return $content;
    }
    
    /**
     * Restore protected blocks
     * 
     * @param string $content Content with placeholders
     * @param array $protected_blocks Protected blocks mapping
     * @return string Content with restored blocks
     */
    private function restore_blocks($content, $protected_blocks) {
        foreach ($protected_blocks as $placeholder => $block) {
            $content = str_replace($placeholder, $block, $content);
        }
        
        return $content;
    }
    
    /**
     * Get translatable custom fields
     * 
     * @param int $post_id Post ID
     * @param array $options Processing options
     * @return array Translatable custom fields
     */
    private function get_translatable_custom_fields($post_id, $options = array()) {
        $translatable_fields = apply_filters('nexus_translatable_custom_fields', array(
            'custom_description',
            'additional_content',
            'subtitle',
            'summary'
        ));
        
        $custom_fields = array();
        
        foreach ($translatable_fields as $field_key) {
            $field_value = get_post_meta($post_id, $field_key, true);
            
            if (!empty($field_value) && is_string($field_value)) {
                $custom_fields['custom_field_' . $field_key] = $this->clean_text_content($field_value);
            }
        }
        
        return $custom_fields;
    }
    
    /**
     * Get SEO fields for translation
     * 
     * @param int $post_id Post ID
     * @return array SEO fields
     */
    private function get_seo_fields($post_id) {
        $seo_fields = array();
        
        // Yoast SEO
        if (defined('WPSEO_VERSION')) {
            $meta_title = get_post_meta($post_id, '_yoast_wpseo_title', true);
            $meta_description = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
            
            if (!empty($meta_title)) {
                $seo_fields['seo_title'] = $this->clean_text_content($meta_title);
            }
            
            if (!empty($meta_description)) {
                $seo_fields['seo_description'] = $this->clean_text_content($meta_description);
            }
        }
        
        // RankMath SEO
        if (defined('RANK_MATH_VERSION')) {
            $meta_title = get_post_meta($post_id, 'rank_math_title', true);
            $meta_description = get_post_meta($post_id, 'rank_math_description', true);
            
            if (!empty($meta_title)) {
                $seo_fields['seo_title'] = $this->clean_text_content($meta_title);
            }
            
            if (!empty($meta_description)) {
                $seo_fields['seo_description'] = $this->clean_text_content($meta_description);
            }
        }
        
        // All in One SEO
        if (defined('AIOSEO_VERSION')) {
            $meta_title = get_post_meta($post_id, '_aioseo_title', true);
            $meta_description = get_post_meta($post_id, '_aioseo_description', true);
            
            if (!empty($meta_title)) {
                $seo_fields['seo_title'] = $this->clean_text_content($meta_title);
            }
            
            if (!empty($meta_description)) {
                $seo_fields['seo_description'] = $this->clean_text_content($meta_description);
            }
        }
        
        return apply_filters('nexus_seo_fields', $seo_fields, $post_id);
    }
    
    /**
     * Apply translated SEO fields to post
     * 
     * @param int $translated_post_id Translated post ID
     * @param array $translated_seo Translated SEO data
     * @return void
     */
    public function apply_translated_seo_fields($translated_post_id, $translated_seo) {
        if (empty($translated_seo)) {
            return;
        }
        
        // Yoast SEO
        if (defined('WPSEO_VERSION')) {
            if (isset($translated_seo['seo_title'])) {
                update_post_meta($translated_post_id, '_yoast_wpseo_title', $translated_seo['seo_title']);
            }
            
            if (isset($translated_seo['seo_description'])) {
                update_post_meta($translated_post_id, '_yoast_wpseo_metadesc', $translated_seo['seo_description']);
            }
        }
        
        // RankMath SEO
        if (defined('RANK_MATH_VERSION')) {
            if (isset($translated_seo['seo_title'])) {
                update_post_meta($translated_post_id, 'rank_math_title', $translated_seo['seo_title']);
            }
            
            if (isset($translated_seo['seo_description'])) {
                update_post_meta($translated_post_id, 'rank_math_description', $translated_seo['seo_description']);
            }
        }
        
        // All in One SEO
        if (defined('AIOSEO_VERSION')) {
            if (isset($translated_seo['seo_title'])) {
                update_post_meta($translated_post_id, '_aioseo_title', $translated_seo['seo_title']);
            }
            
            if (isset($translated_seo['seo_description'])) {
                update_post_meta($translated_post_id, '_aioseo_description', $translated_seo['seo_description']);
            }
        }
        
        do_action('nexus_seo_fields_applied', $translated_post_id, $translated_seo);
    }
    
    /**
     * Apply translated custom fields to post
     * 
     * @param int $translated_post_id Translated post ID
     * @param array $translated_fields Translated custom fields
     * @return void
     */
    public function apply_translated_custom_fields($translated_post_id, $translated_fields) {
        foreach ($translated_fields as $field_key => $field_value) {
            // Remove 'custom_field_' prefix
            $actual_field_key = str_replace('custom_field_', '', $field_key);
            
            update_post_meta($translated_post_id, $actual_field_key, $field_value);
        }
    }
    
    /**
     * Extract translatable text from content
     * 
     * @param string $content HTML content
     * @return array Translatable text segments
     */
    public function extract_translatable_text($content) {
        $text_segments = array();
        
        // Extract text from HTML elements while preserving structure
        $dom = new \DOMDocument();
        $dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $xpath = new \DOMXPath($dom);
        $text_nodes = $xpath->query('//text()[normalize-space()]');
        
        foreach ($text_nodes as $index => $node) {
            $text = trim($node->nodeValue);
            
            if (!empty($text) && $this->is_translatable_text($text)) {
                $placeholder = '{{TEXT_SEGMENT_' . $index . '}}';
                $text_segments[$placeholder] = $text;
                $node->nodeValue = $placeholder;
            }
        }
        
        return array(
            'content' => $dom->saveHTML(),
            'segments' => $text_segments
        );
    }
    
    /**
     * Check if text should be translated
     * 
     * @param string $text Text to check
     * @return bool True if translatable
     */
    private function is_translatable_text($text) {
        // Skip very short text
        if (strlen($text) < 3) {
            return false;
        }
        
        // Skip numbers only
        if (is_numeric($text)) {
            return false;
        }
        
        // Skip URLs
        if (filter_var($text, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Skip email addresses
        if (filter_var($text, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        // Skip HTML entities
        if (preg_match('/^&[a-z]+;$/i', $text)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get content processing statistics
     * 
     * @param string $content Original content
     * @return array Processing statistics
     */
    public function get_content_stats($content) {
        $stats = array(
            'original_length' => strlen($content),
            'word_count' => str_word_count(strip_tags($content)),
            'shortcode_count' => preg_match_all('/\[([^\]]+)\]/', $content),
            'html_tag_count' => preg_match_all('/<[^>]+>/', $content),
            'paragraph_count' => substr_count($content, '</p>'),
            'estimated_api_calls' => $this->estimate_api_calls($content)
        );
        
        return $stats;
    }
    
    /**
     * Estimate API calls needed for content
     * 
     * @param string $content Content to analyze
     * @return int Estimated API calls
     */
    private function estimate_api_calls($content) {
        $word_count = str_word_count(strip_tags($content));
        
        // Estimate based on API limits (assuming ~4000 tokens per call)
        // 1 token ≈ 0.75 words for English
        $tokens_per_call = 3000; // Conservative estimate
        $words_per_call = $tokens_per_call * 0.75;
        
        return max(1, ceil($word_count / $words_per_call));
    }
    
    /**
     * Validate content for translation
     * 
     * @param string $content Content to validate
     * @return array Validation result
     */
    public function validate_content($content) {
        $errors = array();
        $warnings = array();
        
        // Check content length
        if (strlen($content) > 100000) {
            $errors[] = __('Content is too long for translation (max 100,000 characters)', 'nexus-ai-wp-translator');
        }
        
        // Check for malformed HTML
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $html_errors = libxml_get_errors();
        
        if (!empty($html_errors)) {
            $warnings[] = __('Content contains malformed HTML that may affect translation', 'nexus-ai-wp-translator');
        }
        
        libxml_clear_errors();
        libxml_use_internal_errors(false);
        
        // Check for excessive shortcodes
        $shortcode_count = preg_match_all('/\[([^\]]+)\]/', $content);
        if ($shortcode_count > 50) {
            $warnings[] = __('Content contains many shortcodes which may affect translation quality', 'nexus-ai-wp-translator');
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        );
    }
}