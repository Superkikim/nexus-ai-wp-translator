<?php
/**
 * File: class-translator-api.php
 * Location: /includes/class-translator-api.php
 * 
 * Translator API Class - CONFIGURABLE LIMITS VERSION
 * 
 * Handles communication with Claude AI API with user-configurable rate limiting
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translator_API {
    
    /**
     * API endpoint
     */
    private const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';
    
    /**
     * API settings
     */
    private $api_settings;
    
    /**
     * Default protection constants (used as fallbacks)
     */
    private const DEFAULT_MAX_CALLS_PER_HOUR = 50;
    private const DEFAULT_MAX_CALLS_PER_DAY = 200;
    private const DEFAULT_MIN_REQUEST_INTERVAL = 2;
    private const DEFAULT_REQUEST_TIMEOUT = 60;
    private const DEFAULT_EMERGENCY_THRESHOLD = 10;
    private const DEFAULT_TRANSLATION_COOLDOWN = 300;
    private const MAX_RETRIES = 2;
    
    /**
     * Rate limiting
     */
    private static $last_request_time = 0;
    private static $request_count_hour = 0;
    private static $request_count_day = 0;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_settings = get_option('nexus_translator_api_settings', array());
        $this->init_rate_limiting();
    }
    
    /**
     * Get configurable limit values
     */
    private function get_max_calls_per_hour() {
        return (int) ($this->api_settings['max_calls_per_hour'] ?? self::DEFAULT_MAX_CALLS_PER_HOUR);
    }
    
    private function get_max_calls_per_day() {
        return (int) ($this->api_settings['max_calls_per_day'] ?? self::DEFAULT_MAX_CALLS_PER_DAY);
    }
    
    private function get_min_request_interval() {
        return (int) ($this->api_settings['min_request_interval'] ?? self::DEFAULT_MIN_REQUEST_INTERVAL);
    }
    
    private function get_request_timeout() {
        return (int) ($this->api_settings['request_timeout'] ?? self::DEFAULT_REQUEST_TIMEOUT);
    }
    
    private function get_emergency_threshold() {
        return (int) ($this->api_settings['emergency_stop_threshold'] ?? self::DEFAULT_EMERGENCY_THRESHOLD);
    }
    
    private function get_translation_cooldown() {
        return (int) ($this->api_settings['translation_cooldown'] ?? self::DEFAULT_TRANSLATION_COOLDOWN);
    }
    
    /**
     * Initialize rate limiting
     */
    private function init_rate_limiting() {
        // Get current request counts
        $hour_key = 'nexus_api_calls_hour_' . date('YmdH');
        $day_key = 'nexus_api_calls_day_' . date('Ymd');
        
        self::$request_count_hour = (int) get_transient($hour_key);
        self::$request_count_day = (int) get_transient($day_key);
        
        // Get last request time
        self::$last_request_time = (int) get_transient('nexus_last_api_request');
    }
    
    /**
     * Check if emergency stop is active
     */
    private function is_emergency_stop_active() {
        return get_option('nexus_translator_emergency_stop', false);
    }
    
    /**
     * Check rate limits using configurable values
     */
    private function check_rate_limits() {
        $max_hour = $this->get_max_calls_per_hour();
        $max_day = $this->get_max_calls_per_day();
        $min_interval = $this->get_min_request_interval();
        
        if (self::$request_count_hour >= $max_hour) {
            return array(
                'success' => false,
                'error' => sprintf(
                    __('Hourly API rate limit exceeded (%d calls/hour)', 'nexus-ai-wp-translator'),
                    $max_hour
                ),
                'error_code' => 'RATE_LIMIT_HOUR',
                'limit' => $max_hour,
                'current' => self::$request_count_hour
            );
        }
        
        if (self::$request_count_day >= $max_day) {
            return array(
                'success' => false,
                'error' => sprintf(
                    __('Daily API rate limit exceeded (%d calls/day)', 'nexus-ai-wp-translator'),
                    $max_day
                ),
                'error_code' => 'RATE_LIMIT_DAY',
                'limit' => $max_day,
                'current' => self::$request_count_day
            );
        }
        
        // Check minimum interval between requests
        $current_time = time();
        $time_since_last_request = $current_time - self::$last_request_time;
        
        if ($time_since_last_request < $min_interval) {
            $wait_time = $min_interval - $time_since_last_request;
            return array(
                'success' => false,
                'error' => sprintf(
                    __('Rate limit: must wait %d seconds between requests', 'nexus-ai-wp-translator'),
                    $wait_time
                ),
                'error_code' => 'RATE_LIMIT_INTERVAL',
                'wait_time' => $wait_time
            );
        }
        
        return array('success' => true);
    }
    
    /**
     * Update rate limiting counters
     */
    private function update_rate_limiting() {
        $current_time = time();
        
        // Update counters
        self::$request_count_hour++;
        self::$request_count_day++;
        self::$last_request_time = $current_time;
        
        // Store in transients
        $hour_key = 'nexus_api_calls_hour_' . date('YmdH');
        $day_key = 'nexus_api_calls_day_' . date('Ymd');
        
        set_transient($hour_key, self::$request_count_hour, HOUR_IN_SECONDS);
        set_transient($day_key, self::$request_count_day, DAY_IN_SECONDS);
        set_transient('nexus_last_api_request', $current_time, DAY_IN_SECONDS);
        
        // Log API usage with configurable limits
        $max_hour = $this->get_max_calls_per_hour();
        $max_day = $this->get_max_calls_per_day();
        
        error_log("Nexus Translator: API call #" . self::$request_count_day . " - Hour: " . self::$request_count_hour . "/$max_hour, Day: " . self::$request_count_day . "/$max_day");
        
        // Check if approaching emergency threshold
        $emergency_threshold = $this->get_emergency_threshold();
        if (self::$request_count_hour >= $emergency_threshold) {
            $this->trigger_emergency_stop("Hourly API calls exceeded emergency threshold ($emergency_threshold)");
        }
    }
    
    /**
     * Trigger emergency stop
     */
    private function trigger_emergency_stop($reason) {
        update_option('nexus_translator_emergency_stop', true);
        update_option('nexus_translator_emergency_reason', $reason);
        update_option('nexus_translator_emergency_time', current_time('timestamp'));
        
        error_log("Nexus Translator: EMERGENCY STOP TRIGGERED - $reason");
        
        // Send email notification
        $admin_email = get_option('admin_email');
        if ($admin_email) {
            wp_mail(
                $admin_email,
                'Nexus Translator Emergency Stop',
                "Emergency stop activated on " . get_site_url() . "\nReason: $reason\n\nCheck your settings: " . admin_url('admin.php?page=nexus-translator-settings')
            );
        }
    }
    
    /**
     * Get total API calls today
     */
    public function get_total_api_calls_today() {
        return self::$request_count_day;
    }
    
    /**
     * PROTECTED translate content using Claude AI
     */
    public function translate_content($content, $source_lang, $target_lang, $post_type = 'post') {
        // Emergency stop check
        if ($this->is_emergency_stop_active()) {
            return $this->error_response(__('Translation disabled (Emergency Stop active)', 'nexus-ai-wp-translator'));
        }
        
        // Validate API key
        if (!$this->is_api_configured()) {
            return $this->error_response(__('Claude API not configured', 'nexus-ai-wp-translator'));
        }
        
        // Prevent same-language translation
        if ($source_lang === $target_lang) {
            return $this->error_response(sprintf(
                __('Cannot translate from %s to %s (same language)', 'nexus-ai-wp-translator'),
                $source_lang,
                $target_lang
            ));
        }
        
        // Check rate limits with configurable values
        $rate_check = $this->check_rate_limits();
        if (!$rate_check['success']) {
            if (isset($rate_check['wait_time'])) {
                // If it's just an interval limit, wait and retry once
                sleep($rate_check['wait_time']);
                $rate_check = $this->check_rate_limits();
                if (!$rate_check['success']) {
                    return $rate_check;
                }
            } else {
                return $rate_check;
            }
        }
        
        // Validate content length (configurable in future)
        $max_content_length = 100000; // 100KB limit
        if (strlen($content) > $max_content_length) {
            return $this->error_response(sprintf(
                __('Content too long for translation (max %s)', 'nexus-ai-wp-translator'),
                size_format($max_content_length)
            ));
        }
        
        // Prepare translation data
        $translation_data = $this->prepare_translation_data($content, $source_lang, $target_lang, $post_type);
        
        // Make API request with configurable timeout
        $response = $this->make_api_request_with_retries($translation_data);
        
        // Update rate limiting
        $this->update_rate_limiting();
        
        // Process response
        return $this->process_api_response($response);
    }
    
    /**
     * Make API request with retry logic and configurable timeout
     */
    private function make_api_request_with_retries($data) {
        $last_error = null;
        
        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            error_log("Nexus Translator: API request attempt $attempt/" . self::MAX_RETRIES);
            
            $response = $this->make_api_request($data);
            
            // Check if request was successful
            if (!is_wp_error($response)) {
                $response_code = wp_remote_retrieve_response_code($response);
                
                // Success or client error (don't retry client errors)
                if ($response_code < 500) {
                    return $response;
                }
            }
            
            $last_error = $response;
            
            // Wait before retry (exponential backoff)
            if ($attempt < self::MAX_RETRIES) {
                $wait_time = pow(2, $attempt); // 2, 4, 8 seconds
                error_log("Nexus Translator: Request failed, waiting {$wait_time}s before retry");
                sleep($wait_time);
            }
        }
        
        error_log("Nexus Translator: All API request attempts failed");
        return $last_error;
    }
    
    /**
     * Check if API is properly configured
     */
    public function is_api_configured() {
        return !empty($this->api_settings['claude_api_key']);
    }
    
    /**
     * Prepare translation data for API request
     */
    private function prepare_translation_data($content, $source_lang, $target_lang, $post_type) {
        $source_language = $this->get_language_name($source_lang);
        $target_language = $this->get_language_name($target_lang);
        
        // Create translation prompt
        $prompt = $this->build_translation_prompt($content, $source_language, $target_language, $post_type);
        
        return array(
            'model' => $this->get_model(),
            'max_tokens' => $this->get_max_tokens(),
            'temperature' => $this->get_temperature(),
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            )
        );
    }
    
    /**
     * Build translation prompt
     */
    private function build_translation_prompt($content, $source_language, $target_language, $post_type) {
        $post_type_instruction = $this->get_post_type_instruction($post_type);
        
        $prompt = sprintf(
            "You are a professional translator specialized in WordPress web content translation.\n\n" .
            "TASK: Translate the following content from %s to %s.\n\n" .
            "INSTRUCTIONS:\n" .
            "- %s\n" .
            "- Preserve HTML formatting and WordPress shortcodes exactly\n" .
            "- Translate only textual content, never HTML tags or attributes\n" .
            "- Maintain the style and tone of the original content\n" .
            "- Adapt cultural references if necessary\n" .
            "- Return only the translation, without comments or explanations\n" .
            "- Keep the exact same structure (TITLE: ... CONTENT: ...)\n\n" .
            "CONTENT TO TRANSLATE:\n%s",
            $source_language,
            $target_language,
            $post_type_instruction,
            $content
        );
        
        return $prompt;
    }
    
    /**
     * Get post type specific instruction
     */
    private function get_post_type_instruction($post_type) {
        $instructions = array(
            'post' => 'Translate this blog article while maintaining its informative and engaging character',
            'page' => 'Translate this web page while maintaining its structure and purpose',
            'product' => 'Translate this product sheet while maintaining its commercial and persuasive aspect'
        );
        
        return $instructions[$post_type] ?? 'Translate this web content professionally';
    }
    
    /**
     * Make API request to Claude with configurable timeout
     */
    private function make_api_request($data) {
        $headers = array(
            'Content-Type' => 'application/json',
            'x-api-key' => $this->api_settings['claude_api_key'],
            'anthropic-version' => '2023-06-01',
            'User-Agent' => 'Nexus-Translator/' . (defined('NEXUS_TRANSLATOR_VERSION') ? NEXUS_TRANSLATOR_VERSION : '1.0.0')
        );
        
        $timeout = $this->get_request_timeout();
        
        $args = array(
            'method' => 'POST',
            'headers' => $headers,
            'body' => json_encode($data),
            'timeout' => $timeout,
            'sslverify' => true,
            'blocking' => true
        );
        
        // Add debug logging
        if ($this->is_debug_mode()) {
            error_log('Nexus Translator API Request: ' . json_encode(array(
                'model' => $data['model'],
                'max_tokens' => $data['max_tokens'],
                'content_length' => strlen($data['messages'][0]['content']),
                'timeout' => $timeout
            )));
        }
        
        $start_time = microtime(true);
        $response = wp_remote_request(self::API_ENDPOINT, $args);
        $end_time = microtime(true);
        
        // Log request duration
        $duration = round(($end_time - $start_time) * 1000, 2);
        error_log("Nexus Translator: API request completed in {$duration}ms (timeout: {$timeout}s)");
        
        // Log response for debugging
        if ($this->is_debug_mode()) {
            $response_code = is_wp_error($response) ? 'ERROR' : wp_remote_retrieve_response_code($response);
            $response_size = is_wp_error($response) ? 0 : strlen(wp_remote_retrieve_body($response));
            error_log("Nexus Translator API Response: Code={$response_code}, Size={$response_size}bytes, Duration={$duration}ms");
        }
        
        return $response;
    }
    
    /**
     * Process API response
     */
    private function process_api_response($response) {
        // Check for WordPress HTTP errors
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log("Nexus Translator: WordPress HTTP Error: $error_message");
            return $this->error_response($error_message);
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Check HTTP status
        if ($response_code !== 200) {
            return $this->handle_api_error($response_code, $response_body);
        }
        
        // Parse JSON response
        $data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Nexus Translator: JSON parsing error: ' . json_last_error_msg());
            return $this->error_response(__('JSON parsing error', 'nexus-ai-wp-translator'));
        }
        
        // Validate response structure
        if (!isset($data['content'][0]['text'])) {
            error_log('Nexus Translator: Invalid API response structure: ' . print_r($data, true));
            return $this->error_response(__('Invalid API response structure', 'nexus-ai-wp-translator'));
        }
        
        // Extract translated content
        $translated_content = trim($data['content'][0]['text']);
        
        // Validate translated content
        if (empty($translated_content)) {
            return $this->error_response(__('Empty translation received from API', 'nexus-ai-wp-translator'));
        }
        
        if (strlen($translated_content) < 10) {
            return $this->error_response(__('Translation too short, possible API error', 'nexus-ai-wp-translator'));
        }
        
        return array(
            'success' => true,
            'translated_content' => $translated_content,
            'usage' => $data['usage'] ?? null,
            'model' => $data['model'] ?? $this->get_model()
        );
    }
    
    /**
     * Handle API errors with enhanced error reporting
     */
    private function handle_api_error($status_code, $response_body) {
        $error_data = json_decode($response_body, true);
        $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : __('Unknown API error', 'nexus-ai-wp-translator');
        
        // Log detailed error information
        error_log("Nexus Translator: API Error {$status_code}: $error_message");
        
        // Map common error codes to user-friendly messages
        $error_messages = array(
            400 => __('Invalid request - check your content and settings', 'nexus-ai-wp-translator'),
            401 => __('Invalid or missing API key', 'nexus-ai-wp-translator'),
            403 => __('Access forbidden - check your API permissions', 'nexus-ai-wp-translator'),
            429 => __('Rate limit exceeded - try again later', 'nexus-ai-wp-translator'),
            500 => __('Claude AI server error', 'nexus-ai-wp-translator'),
            529 => __('Claude service temporarily overloaded', 'nexus-ai-wp-translator')
        );
        
        $user_message = $error_messages[$status_code] ?? $error_message;
        
        // Check if we should activate emergency stop
        if (in_array($status_code, array(401, 403))) {
            error_log("Nexus Translator: API authentication error, considering emergency stop");
        }
        
        if ($status_code === 429) {
            // Rate limit hit, implement longer cooldown
            set_transient('nexus_api_rate_limit_hit', true, HOUR_IN_SECONDS);
            error_log("Nexus Translator: Rate limit hit, implementing 1-hour cooldown");
        }
        
        return array(
            'success' => false,
            'error' => $user_message,
            'error_code' => $status_code,
            'raw_error' => $error_message
        );
    }
    
    /**
     * Create error response
     */
    private function error_response($message) {
        return array(
            'success' => false,
            'error' => $message
        );
    }
    
    /**
     * Get language name from code
     */
    private function get_language_name($code) {
        $languages = array(
            'fr' => 'French',
            'en' => 'English',
            'es' => 'Spanish',
            'de' => 'German',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'nl' => 'Dutch',
            'ru' => 'Russian',
            'ja' => 'Japanese',
            'zh' => 'Chinese',
            'ar' => 'Arabic',
            'hi' => 'Hindi',
            'ko' => 'Korean',
            'sv' => 'Swedish',
            'da' => 'Danish',
            'no' => 'Norwegian',
            'fi' => 'Finnish',
            'pl' => 'Polish',
            'tr' => 'Turkish',
            'he' => 'Hebrew'
        );
        
        return $languages[$code] ?? $code;
    }
    
    /**
     * Get configured model
     */
    private function get_model() {
        return $this->api_settings['model'] ?? 'claude-sonnet-4-20250514';
    }
    
    /**
     * Get max tokens
     */
    private function get_max_tokens() {
        return (int) ($this->api_settings['max_tokens'] ?? 4000);
    }
    
    /**
     * Get temperature
     */
    private function get_temperature() {
        return (float) ($this->api_settings['temperature'] ?? 0.3);
    }
    
    /**
     * Check if debug mode is enabled
     */
    private function is_debug_mode() {
        $options = get_option('nexus_translator_options', array());
        return !empty($options['debug_mode']);
    }
    
    /**
     * PROTECTED test API connection
     */
    public function test_api_connection() {
        return $this->test_api_key_direct();
    }
    
    /**
     * Get API usage statistics with configurable limits
     */
    public function get_usage_stats() {
        return array(
            'translations_today' => self::$request_count_day,
            'translations_month' => 0, // Placeholder
            'tokens_used' => 0, // Placeholder
            'calls_today' => self::$request_count_day,
            'calls_hour' => self::$request_count_hour,
            'limit_day' => $this->get_max_calls_per_day(),
            'limit_hour' => $this->get_max_calls_per_hour(),
            'emergency_stop' => $this->is_emergency_stop_active(),
            'rate_limit_hit' => get_transient('nexus_api_rate_limit_hit') ? true : false,
            'settings' => array(
                'min_interval' => $this->get_min_request_interval(),
                'timeout' => $this->get_request_timeout(),
                'emergency_threshold' => $this->get_emergency_threshold(),
                'cooldown' => $this->get_translation_cooldown()
            )
        );
    }
    
    /**
     * Reset rate limits (admin only)
     */
    public function reset_rate_limits() {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        // Clear all rate limiting data
        $hour_key = 'nexus_api_calls_hour_' . date('YmdH');
        $day_key = 'nexus_api_calls_day_' . date('Ymd');
        
        delete_transient($hour_key);
        delete_transient($day_key);
        delete_transient('nexus_last_api_request');
        delete_transient('nexus_api_rate_limit_hit');
        
        // Reset emergency stop
        delete_option('nexus_translator_emergency_stop');
        delete_option('nexus_translator_emergency_reason');
        delete_option('nexus_translator_emergency_time');
        
        // Reset static counters
        self::$request_count_hour = 0;
        self::$request_count_day = 0;
        self::$last_request_time = 0;
        
        error_log('Nexus Translator: Rate limits reset by administrator');
        
        return true;
    }
    
    /**
     * Get rate limit status with configurable values
     */
    public function get_rate_limit_status() {
        return array(
            'hour_calls' => self::$request_count_hour,
            'hour_limit' => $this->get_max_calls_per_hour(),
            'day_calls' => self::$request_count_day,
            'day_limit' => $this->get_max_calls_per_day(),
            'last_request' => self::$last_request_time,
            'min_interval' => $this->get_min_request_interval(),
            'can_make_request' => $this->can_make_request(),
            'time_until_next' => $this->time_until_next_request(),
            'percentages' => array(
                'hour' => round((self::$request_count_hour / max(1, $this->get_max_calls_per_hour())) * 100, 1),
                'day' => round((self::$request_count_day / max(1, $this->get_max_calls_per_day())) * 100, 1)
            )
        );
    }
    
    /**
     * Check if we can make a request right now
     */
    public function can_make_request() {
        if ($this->is_emergency_stop_active()) {
            return false;
        }
        
        $rate_check = $this->check_rate_limits();
        return $rate_check['success'];
    }
    
    /**
     * Get time until next allowed request
     */
    public function time_until_next_request() {
        if ($this->is_emergency_stop_active()) {
            return -1; // Emergency stop active
        }
        
        $current_time = time();
        $time_since_last = $current_time - self::$last_request_time;
        $min_interval = $this->get_min_request_interval();
        
        if ($time_since_last < $min_interval) {
            return $min_interval - $time_since_last;
        }
        
        return 0; // Can make request now
    }
    
    /**
     * Check if translation cooldown allows new translation
     */
    public function can_translate_post($post_id) {
        if ($this->is_emergency_stop_active()) {
            return array(
                'can_translate' => false,
                'reason' => __('Emergency stop active', 'nexus-ai-wp-translator')
            );
        }
        
        // Check if we can make API request
        if (!$this->can_make_request()) {
            $status = $this->get_rate_limit_status();
            return array(
                'can_translate' => false,
                'reason' => sprintf(
                    __('Rate limit reached: %d/%d calls today, %d/%d this hour', 'nexus-ai-wp-translator'),
                    $status['day_calls'],
                    $status['day_limit'],
                    $status['hour_calls'],
                    $status['hour_limit']
                )
            );
        }
        
        // Check translation cooldown
        $active_translations = get_option('nexus_translator_active_translations', array());
        $lock_key = "post_$post_id";
        
        if (isset($active_translations[$lock_key])) {
            $lock_time = $active_translations[$lock_key];
            $current_time = current_time('timestamp');
            $cooldown = $this->get_translation_cooldown();
            
            if (($current_time - $lock_time) < $cooldown) {
                $remaining = $cooldown - ($current_time - $lock_time);
                return array(
                    'can_translate' => false,
                    'reason' => sprintf(
                        __('Translation cooldown: %d seconds remaining', 'nexus-ai-wp-translator'),
                        $remaining
                    ),
                    'cooldown_remaining' => $remaining
                );
            }
        }
        
        return array(
            'can_translate' => true,
            'reason' => __('Ready to translate', 'nexus-ai-wp-translator')
        );
    }
    
    /**
     * Get current configuration summary
     */
    public function get_configuration_summary() {
        return array(
            'api_configured' => $this->is_api_configured(),
            'model' => $this->get_model(),
            'max_tokens' => $this->get_max_tokens(),
            'temperature' => $this->get_temperature(),
            'limits' => array(
                'calls_per_hour' => $this->get_max_calls_per_hour(),
                'calls_per_day' => $this->get_max_calls_per_day(),
                'min_interval' => $this->get_min_request_interval(),
                'timeout' => $this->get_request_timeout(),
                'emergency_threshold' => $this->get_emergency_threshold(),
                'translation_cooldown' => $this->get_translation_cooldown()
            ),
            'safety' => array(
                'emergency_stop' => $this->is_emergency_stop_active(),
                'debug_mode' => $this->is_debug_mode()
            )
        );
    }
    
    /**
     * Validate configuration settings
     */
    public function validate_configuration() {
        $issues = array();
        
        // Check API key
        if (!$this->is_api_configured()) {
            $issues[] = __('API key not configured', 'nexus-ai-wp-translator');
        }
        
        // Check if limits are reasonable
        $max_hour = $this->get_max_calls_per_hour();
        $max_day = $this->get_max_calls_per_day();
        
        if ($max_hour > $max_day) {
            $issues[] = __('Hourly limit cannot be higher than daily limit', 'nexus-ai-wp-translator');
        }
        
        if ($max_day > 1000) {
            $issues[] = __('Daily limit is very high - consider lower value to prevent unexpected charges', 'nexus-ai-wp-translator');
        }
        
        $timeout = $this->get_request_timeout();
        if ($timeout < 30) {
            $issues[] = __('Request timeout is very low - may cause translation failures', 'nexus-ai-wp-translator');
        }
        
        $cooldown = $this->get_translation_cooldown();
        if ($cooldown < 60) {
            $issues[] = __('Translation cooldown is very low - may allow accidental re-translations', 'nexus-ai-wp-translator');
        }
        
        return array(
            'valid' => empty($issues),
            'issues' => $issues
        );
    }
    
    /**
     * Export configuration for backup
     */
    public function export_configuration() {
        return array(
            'api_settings' => $this->api_settings,
            'exported_at' => current_time('mysql'),
            'plugin_version' => defined('NEXUS_TRANSLATOR_VERSION') ? NEXUS_TRANSLATOR_VERSION : '1.0.0'
        );
    }
    
    /**
     * Import configuration from backup
     */
    public function import_configuration($config_data) {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        if (!is_array($config_data) || !isset($config_data['api_settings'])) {
            return false;
        }
        
        // Validate imported settings
        $settings = $config_data['api_settings'];
        
        // Basic validation
        if (isset($settings['max_calls_per_hour']) && $settings['max_calls_per_hour'] > 1000) {
            return false; // Unreasonable limit
        }
        
        if (isset($settings['max_calls_per_day']) && $settings['max_calls_per_day'] > 10000) {
            return false; // Unreasonable limit
        }
        
        // Update settings
        update_option('nexus_translator_api_settings', $settings);
        $this->api_settings = $settings;
        
        error_log('Nexus Translator: Configuration imported successfully');
        
        return true;
    }

    


/**
 * Test direct de la clé API sans rate limiting
 */
    public function test_api_key_direct($api_key = null) {
        // Utiliser la clé fournie ou celle configurée
        $test_key = $api_key ?: ($this->api_settings['claude_api_key'] ?? '');
        
        if (empty($test_key)) {
            return array(
                'success' => false,
                'error' => __('No API key provided', 'nexus-ai-wp-translator')
            );
        }
        
        // Test minimal direct sans checks
        $test_data = array(
            'model' => 'claude-sonnet-4-20250514',
            'max_tokens' => 100,
            'temperature' => 0.1,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => 'Translate this to French: "Hello, this is a test."'
                )
            )
        );
        
        $headers = array(
            'Content-Type' => 'application/json',
            'x-api-key' => $test_key,
            'anthropic-version' => '2023-06-01',
            'User-Agent' => 'Nexus-Translator-Test/1.0.0'
        );
        
        $args = array(
            'method' => 'POST',
            'headers' => $headers,
            'body' => json_encode($test_data),
            'timeout' => 30,
            'sslverify' => true
        );
        
        // Log de debug
        if ($this->is_debug_mode()) {
            error_log('Nexus Test API: Testing key ending in ...' . substr($test_key, -4));
        }
        
        $response = wp_remote_request(self::API_ENDPOINT, $args);
        
        // Analyse de la réponse
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log("Nexus Test API: WordPress HTTP Error: $error_message");
            return array(
                'success' => false,
                'error' => "Connection error: $error_message"
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        error_log("Nexus Test API: Response code: $response_code");
        
        if ($response_code !== 200) {
            error_log("Nexus Test API: Error response: $response_body");
            
            $error_data = json_decode($response_body, true);
            $error_message = 'API Error';
            
            if (isset($error_data['error']['message'])) {
                $error_message = $error_data['error']['message'];
            }
            
            // Messages d'erreur spécifiques
            switch ($response_code) {
                case 401:
                    $error_message = 'Invalid API key - please check your key from Anthropic Console';
                    break;
                case 403:
                    $error_message = 'API key doesn\'t have permission - check your Anthropic account';
                    break;
                case 429:
                    $error_message = 'Rate limit exceeded - wait a moment and try again';
                    break;
                case 500:
                    $error_message = 'Claude API server error - try again later';
                    break;
            }
            
            return array(
                'success' => false,
                'error' => $error_message,
                'response_code' => $response_code,
                'raw_response' => $response_body
            );
        }
        
        // Parse successful response
        $data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'error' => 'Invalid JSON response from API'
            );
        }
        
        if (!isset($data['content'][0]['text'])) {
            return array(
                'success' => false,
                'error' => 'Unexpected API response structure'
            );
        }
        
        return array(
            'success' => true,
            'message' => 'API connection successful!',
            'test_translation' => $data['content'][0]['text'],
            'model_used' => $data['model'] ?? 'claude-sonnet-4-20250514',
            'usage' => $data['usage'] ?? null
        );
    }

}