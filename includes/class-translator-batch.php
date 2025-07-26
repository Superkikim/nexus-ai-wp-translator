<?php
/**
 * File: class-translation-batch.php
 * Location: /includes/class-translation-batch.php
 * 
 * Translation Batch Handler Class
 * Responsible for: Batch operations, queue management, background processing
 */

namespace Nexus\Translator;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Translation batch handler class
 * 
 * Handles batch translation operations, queue management, and background processing.
 * Designed for future scalability and background job integration.
 */
class Translation_Batch {
    
    private $translator;
    private $translation_queue = array();
    
    public function __construct($translator) {
        $this->translator = $translator;
        $this->load_queue();
    }
    
    public function register_hooks() {
        // Queue processing hooks
        $this->add_hook('nexus_process_translation_queue', array($this, 'process_queue'));
        $this->add_hook('wp_loaded', array($this, 'maybe_schedule_queue_processing'));
        
        // Cleanup hooks
        $this->add_hook('nexus_daily_cleanup', array($this, 'cleanup_completed_batches'));
    }
    
    /**
     * Process batch translation
     * 
     * @param array $post_ids Array of post IDs
     * @param array $target_languages Array of target language codes
     * @param array $options Batch options
     * @return array Batch translation results
     */
    public function process_batch($post_ids, $target_languages, $options = array()) {
        $batch_id = wp_generate_uuid4();
        $total_jobs = count($post_ids) * count($target_languages);
        
        // Fire batch start hook
        do_action('nexus_translation_batch_start', $batch_id, $post_ids, $target_languages, $options);
        
        $results = array(
            'batch_id' => $batch_id,
            'total_jobs' => $total_jobs,
            'completed' => 0,
            'failed' => 0,
            'results' => array(),
            'errors' => array(),
            'start_time' => current_time('mysql'),
            'status' => 'processing'
        );
        
        // Store batch info
        $this->store_batch_info($batch_id, $results);
        
        foreach ($post_ids as $post_id) {
            foreach ($target_languages as $target_language) {
                $job_key = $post_id . '_' . $target_language;
                
                try {
                    $translation_result = $this->translator->translate_post($post_id, $target_language, $options);
                    
                    if ($translation_result['success']) {
                        $results['completed']++;
                        $results['results'][$job_key] = $translation_result['data'];
                    } else {
                        $results['failed']++;
                        $results['errors'][$job_key] = $translation_result['message'];
                    }
                    
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][$job_key] = $e->getMessage();
                }
                
                // Update batch progress
                $results['progress'] = round((($results['completed'] + $results['failed']) / $total_jobs) * 100, 2);
                $this->update_batch_info($batch_id, $results);
                
                // Fire progress hook
                do_action('nexus_translation_batch_progress', $batch_id, $results['completed'] + $results['failed'], $total_jobs);
                
                // Rate limiting - small delay between translations
                if ($options['rate_limit'] ?? true) {
                    usleep(500000); // 0.5 second delay
                }
            }
        }
        
        // Mark batch as complete
        $results['status'] = 'completed';
        $results['end_time'] = current_time('mysql');
        $this->update_batch_info($batch_id, $results);
        
        // Fire batch complete hook
        do_action('nexus_translation_batch_complete', $batch_id, $results);
        
        // Fire analytics event
        do_action('nexus_analytics_event', 'batch_translation_completed', array(
            'batch_id' => $batch_id,
            'total_jobs' => $total_jobs,
            'completed' => $results['completed'],
            'failed' => $results['failed'],
            'success_rate' => round(($results['completed'] / $total_jobs) * 100, 2)
        ));
        
        return $results;
    }
    
    /**
     * Queue post for auto translation
     * 
     * @param int $post_id Post ID to queue
     * @return bool True on success
     */
    public function queue_auto_translation($post_id) {
        $settings = get_option('nexus_ai_translator_settings', array());
        $target_languages = $settings['target_languages'] ?? array();
        
        if (empty($target_languages)) {
            return false;
        }
        
        // Check if already queued
        foreach ($this->translation_queue as $item) {
            if ($item['post_id'] === $post_id) {
                return false; // Already queued
            }
        }
        
        // Add to queue
        $queue_item = array(
            'post_id' => $post_id,
            'target_languages' => $target_languages,
            'queued_at' => current_time('mysql'),
            'priority' => 'auto',
            'options' => array(
                'post_status' => 'publish',
                'auto_translate' => true
            )
        );
        
        $this->translation_queue[] = $queue_item;
        $this->save_queue();
        
        // Fire queue hook
        do_action('nexus_translation_queued', $post_id, $target_languages);
        
        return true;
    }
    
    /**
     * Add posts to translation queue
     * 
     * @param array $post_ids Array of post IDs
     * @param array $target_languages Target languages
     * @param array $options Queue options
     * @return string Queue batch ID
     */
    public function add_to_queue($post_ids, $target_languages, $options = array()) {
        $batch_id = wp_generate_uuid4();
        
        foreach ($post_ids as $post_id) {
            $queue_item = array(
                'batch_id' => $batch_id,
                'post_id' => $post_id,
                'target_languages' => $target_languages,
                'queued_at' => current_time('mysql'),
                'priority' => $options['priority'] ?? 'normal',
                'options' => $options
            );
            
            $this->translation_queue[] = $queue_item;
        }
        
        $this->save_queue();
        
        // Fire batch queued hook
        do_action('nexus_translation_batch_queued', $batch_id, $post_ids, $target_languages, $options);
        
        return $batch_id;
    }
    
    /**
     * Process translation queue
     * 
     * @param int $limit Maximum number of items to process
     * @return array Processing results
     */
    public function process_queue($limit = 5) {
        if (empty($this->translation_queue)) {
            return array('processed' => 0, 'remaining' => 0);
        }
        
        $processed = 0;
        $remaining_queue = array();
        
        // Sort queue by priority
        $this->sort_queue_by_priority();
        
        foreach ($this->translation_queue as $queue_item) {
            if ($processed >= $limit) {
                $remaining_queue[] = $queue_item;
                continue;
            }
            
            $post_id = $queue_item['post_id'];
            $target_languages = $queue_item['target_languages'];
            $options = $queue_item['options'] ?? array();
            
            // Check if post still exists and is published
            $post = get_post($post_id);
            if (!$post || $post->post_status !== 'publish') {
                continue; // Skip this item
            }
            
            try {
                // Process one language at a time
                $target_language = array_shift($target_languages);
                
                if ($target_language) {
                    $result = $this->translator->translate_post($post_id, $target_language, $options);
                    
                    if ($result['success']) {
                        $processed++;
                        
                        // Fire individual completion hook
                        do_action('nexus_queue_item_completed', $queue_item['batch_id'] ?? null, $post_id, $target_language, $result);
                    } else {
                        // Fire failure hook
                        do_action('nexus_queue_item_failed', $queue_item['batch_id'] ?? null, $post_id, $target_language, $result['message']);
                    }
                }
                
                // If more languages remain, add back to queue
                if (!empty($target_languages)) {
                    $queue_item['target_languages'] = $target_languages;
                    $remaining_queue[] = $queue_item;
                }
                
            } catch (\Exception $e) {
                // Log error and continue
                error_log('[Nexus AI Translator] Queue processing error: ' . $e->getMessage());
                
                // Fire error hook
                do_action('nexus_queue_processing_error', $queue_item, $e->getMessage());
            }
        }
        
        // Update queue
        $this->translation_queue = $remaining_queue;
        $this->save_queue();
        
        // Fire processing complete hook
        do_action('nexus_queue_processing_complete', $processed, count($remaining_queue));
        
        return array(
            'processed' => $processed,
            'remaining' => count($remaining_queue),
            'queue_size' => count($this->translation_queue)
        );
    }
    
    /**
     * Get queue status
     * 
     * @return array Queue status information
     */
    public function get_queue_status() {
        $queue_stats = array(
            'total_items' => count($this->translation_queue),
            'by_priority' => array(),
            'oldest_item' => null,
            'estimated_processing_time' => 0
        );
        
        if (!empty($this->translation_queue)) {
            // Group by priority
            foreach ($this->translation_queue as $item) {
                $priority = $item['priority'] ?? 'normal';
                if (!isset($queue_stats['by_priority'][$priority])) {
                    $queue_stats['by_priority'][$priority] = 0;
                }
                $queue_stats['by_priority'][$priority]++;
            }
            
            // Find oldest item
            $oldest = min(array_column($this->translation_queue, 'queued_at'));
            $queue_stats['oldest_item'] = $oldest;
            
            // Estimate processing time (assume 30 seconds per translation)
            $total_translations = 0;
            foreach ($this->translation_queue as $item) {
                $total_translations += count($item['target_languages']);
            }
            $queue_stats['estimated_processing_time'] = $total_translations * 30; // seconds
        }
        
        return $queue_stats;
    }
    
    /**
     * Clear completed items from queue
     * 
     * @return int Number of items removed
     */
    public function clear_completed_items() {
        $original_count = count($this->translation_queue);
        
        $this->translation_queue = array_filter($this->translation_queue, function($item) {
            $post_id = $item['post_id'];
            $target_languages = $item['target_languages'];
            
            // Keep item if any target language is not yet translated
            foreach ($target_languages as $language) {
                if (!$this->translator->get_existing_translation($post_id, $language)) {
                    return true;
                }
            }
            
            return false;
        });
        
        $removed_count = $original_count - count($this->translation_queue);
        
        if ($removed_count > 0) {
            $this->save_queue();
        }
        
        return $removed_count;
    }
    
    /**
     * Get batch information
     * 
     * @param string $batch_id Batch ID
     * @return array|false Batch information or false if not found
     */
    public function get_batch_info($batch_id) {
        $batches = get_option('nexus_translation_batches', array());
        return isset($batches[$batch_id]) ? $batches[$batch_id] : false;
    }
    
    /**
     * Get all batch information
     * 
     * @param int $limit Maximum number of batches to return
     * @return array Array of batch information
     */
    public function get_all_batches($limit = 20) {
        $batches = get_option('nexus_translation_batches', array());
        
        // Sort by start time (newest first)
        uasort($batches, function($a, $b) {
            return strtotime($b['start_time']) - strtotime($a['start_time']);
        });
        
        return array_slice($batches, 0, $limit, true);
    }
    
    /**
     * Delete batch information
     * 
     * @param string $batch_id Batch ID
     * @return bool True on success
     */
    public function delete_batch($batch_id) {
        $batches = get_option('nexus_translation_batches', array());
        
        if (isset($batches[$batch_id])) {
            unset($batches[$batch_id]);
            update_option('nexus_translation_batches', $batches);
            return true;
        }
        
        return false;
    }
    
    public function maybe_schedule_queue_processing() {
        // Check if queue processing is already scheduled
        if (!wp_next_scheduled('nexus_process_translation_queue')) {
            // Schedule to run every 5 minutes
            wp_schedule_event(time(), 'nexus_5min', 'nexus_process_translation_queue');
        }
        
        // Add custom cron interval if not exists
        add_filter('cron_schedules', function($schedules) {
            $schedules['nexus_5min'] = array(
                'interval' => 300, // 5 minutes
                'display'  => __('Every 5 Minutes', 'nexus-ai-wp-translator')
            );
            return $schedules;
        });
    }
    
    public function cleanup_completed_batches() {
        $batches = get_option('nexus_translation_batches', array());
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-7 days'));
        $cleaned_batches = array();
        
        foreach ($batches as $batch_id => $batch_info) {
            // Keep recent batches or incomplete batches
            if ($batch_info['status'] !== 'completed' || $batch_info['end_time'] > $cutoff_date) {
                $cleaned_batches[$batch_id] = $batch_info;
            }
        }
        
        if (count($cleaned_batches) !== count($batches)) {
            update_option('nexus_translation_batches', $cleaned_batches);
            
            // Fire cleanup event
            do_action('nexus_analytics_event', 'batch_cleanup', array(
                'removed_batches' => count($batches) - count($cleaned_batches),
                'remaining_batches' => count($cleaned_batches)
            ));
        }
    }
    
    /**
     * Cancel a batch operation
     * 
     * @param string $batch_id Batch ID
     * @return bool True on success
     */
    public function cancel_batch($batch_id) {
        // Remove from queue
        $this->translation_queue = array_filter($this->translation_queue, function($item) use ($batch_id) {
            return ($item['batch_id'] ?? '') !== $batch_id;
        });
        
        $this->save_queue();
        
        // Update batch status
        $batch_info = $this->get_batch_info($batch_id);
        if ($batch_info) {
            $batch_info['status'] = 'cancelled';
            $batch_info['end_time'] = current_time('mysql');
            $this->update_batch_info($batch_id, $batch_info);
        }
        
        // Fire cancellation hook
        do_action('nexus_translation_batch_cancelled', $batch_id);
        
        return true;
    }
    
    /**
     * Estimate batch processing time
     * 
     * @param array $post_ids Post IDs
     * @param array $target_languages Target languages
     * @return array Time estimates
     */
    public function estimate_batch_time($post_ids, $target_languages) {
        $total_translations = count($post_ids) * count($target_languages);
        
        // Average time estimates based on content length
        $estimates = array();
        $total_estimated_time = 0;
        
        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if (!$post) continue;
            
            $word_count = str_word_count(strip_tags($post->post_content));
            
            // Estimate based on word count (rough approximation)
            if ($word_count < 100) {
                $estimated_time = 15; // 15 seconds
            } elseif ($word_count < 500) {
                $estimated_time = 30; // 30 seconds
            } elseif ($word_count < 1000) {
                $estimated_time = 60; // 1 minute
            } else {
                $estimated_time = 120; // 2 minutes
            }
            
            $post_total = $estimated_time * count($target_languages);
            $estimates[$post_id] = $post_total;
            $total_estimated_time += $post_total;
        }
        
        return array(
            'total_translations' => $total_translations,
            'estimated_total_time' => $total_estimated_time,
            'estimated_per_post' => $estimates,
            'formatted_time' => $this->format_duration($total_estimated_time)
        );
    }
    
    /**
     * Get batch processing statistics
     * 
     * @param string $period Time period ('day', 'week', 'month')
     * @return array Batch statistics
     */
    public function get_batch_statistics($period = 'week') {
        $batches = get_option('nexus_translation_batches', array());
        $cutoff_date = $this->get_period_cutoff($period);
        
        $stats = array(
            'total_batches' => 0,
            'completed_batches' => 0,
            'failed_batches' => 0,
            'cancelled_batches' => 0,
            'total_translations' => 0,
            'successful_translations' => 0,
            'average_batch_size' => 0,
            'average_success_rate' => 0
        );
        
        $relevant_batches = array();
        
        foreach ($batches as $batch_info) {
            if ($batch_info['start_time'] >= $cutoff_date) {
                $relevant_batches[] = $batch_info;
            }
        }
        
        if (!empty($relevant_batches)) {
            $stats['total_batches'] = count($relevant_batches);
            
            foreach ($relevant_batches as $batch) {
                switch ($batch['status']) {
                    case 'completed':
                        $stats['completed_batches']++;
                        break;
                    case 'failed':
                        $stats['failed_batches']++;
                        break;
                    case 'cancelled':
                        $stats['cancelled_batches']++;
                        break;
                }
                
                $stats['total_translations'] += ($batch['total_jobs'] ?? 0);
                $stats['successful_translations'] += ($batch['completed'] ?? 0);
            }
            
            if ($stats['total_batches'] > 0) {
                $stats['average_batch_size'] = round($stats['total_translations'] / $stats['total_batches'], 1);
            }
            
            if ($stats['total_translations'] > 0) {
                $stats['average_success_rate'] = round(($stats['successful_translations'] / $stats['total_translations']) * 100, 2);
            }
        }
        
        return $stats;
    }
    
    private function load_queue() {
        $this->translation_queue = get_transient('nexus_translation_queue') ?: array();
    }
    
    private function save_queue() {
        set_transient('nexus_translation_queue', $this->translation_queue, DAY_IN_SECONDS);
    }
    
    private function sort_queue_by_priority() {
        $priority_order = array('high' => 1, 'normal' => 2, 'low' => 3, 'auto' => 4);
        
        usort($this->translation_queue, function($a, $b) use ($priority_order) {
            $priority_a = $priority_order[$a['priority'] ?? 'normal'] ?? 2;
            $priority_b = $priority_order[$b['priority'] ?? 'normal'] ?? 2;
            
            if ($priority_a === $priority_b) {
                // If same priority, sort by queue time (older first)
                return strtotime($a['queued_at']) - strtotime($b['queued_at']);
            }
            
            return $priority_a - $priority_b;
        });
    }
    
    private function store_batch_info($batch_id, $batch_info) {
        $batches = get_option('nexus_translation_batches', array());
        $batches[$batch_id] = $batch_info;
        update_option('nexus_translation_batches', $batches);
    }
    
    private function update_batch_info($batch_id, $batch_info) {
        $batches = get_option('nexus_translation_batches', array());
        if (isset($batches[$batch_id])) {
            $batches[$batch_id] = array_merge($batches[$batch_id], $batch_info);
            update_option('nexus_translation_batches', $batches);
        }
    }
    
    private function format_duration($seconds) {
        if ($seconds < 60) {
            return sprintf(__('%d seconds', 'nexus-ai-wp-translator'), $seconds);
        } elseif ($seconds < 3600) {
            return sprintf(__('%d minutes', 'nexus-ai-wp-translator'), round($seconds / 60));
        } else {
            $hours = floor($seconds / 3600);
            $minutes = round(($seconds % 3600) / 60);
            return sprintf(__('%d hours %d minutes', 'nexus-ai-wp-translator'), $hours, $minutes);
        }
    }
    
    private function get_period_cutoff($period) {
        switch ($period) {
            case 'day':
                return date('Y-m-d H:i:s', strtotime('-1 day'));
            case 'week':
                return date('Y-m-d H:i:s', strtotime('-1 week'));
            case 'month':
                return date('Y-m-d H:i:s', strtotime('-1 month'));
            case 'year':
                return date('Y-m-d H:i:s', strtotime('-1 year'));
            default:
                return date('Y-m-d H:i:s', strtotime('-1 week'));
        }
    }
    
    // Helper method to add hooks (since this isn't extending Abstract_Module)
    private function add_hook($hook, $callback, $priority = 10, $accepted_args = 1) {
        add_action($hook, $callback, $priority, $accepted_args);
    }
}