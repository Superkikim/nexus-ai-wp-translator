<?php
/**
 * File: interface-queue.php
 * Location: /includes/interfaces/interface-queue.php
 * 
 * Queue interface for future bulk operations and background processing
 * Defines contract for queuing translation jobs, batch operations, and task management
 */

namespace Nexus\Translator\Interfaces;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Queue Interface
 * 
 * Contract for queue implementations
 * Defines methods for managing background jobs, bulk operations, and task processing
 * 
 * @package Nexus\Translator\Interfaces
 */
interface Queue_Interface {
    
    /**
     * Add job to queue
     * 
     * @param string $job_type Type of job (e.g., 'translation', 'bulk_translate')
     * @param array $job_data Job parameters and data
     * @param int $priority Job priority (lower number = higher priority)
     * @param int $delay Delay before processing (in seconds)
     * @return string|false Job ID or false on failure
     */
    public function add_job($job_type, $job_data, $priority = 10, $delay = 0);
    
    /**
     * Add multiple jobs to queue
     * 
     * @param array $jobs Array of job definitions
     * @return array Array of job IDs (false for failed jobs)
     */
    public function add_jobs($jobs);
    
    /**
     * Get next job from queue
     * 
     * @param array $job_types Job types to process (empty = all types)
     * @return array|false Job data or false if no jobs
     */
    public function get_next_job($job_types = array());
    
    /**
     * Mark job as completed
     * 
     * @param string $job_id Job ID
     * @param array $result Job result data
     * @return bool True on success, false on failure
     */
    public function complete_job($job_id, $result = array());
    
    /**
     * Mark job as failed
     * 
     * @param string $job_id Job ID
     * @param string $error_message Error message
     * @param bool $retry Whether to retry the job
     * @return bool True on success, false on failure
     */
    public function fail_job($job_id, $error_message, $retry = true);
    
    /**
     * Get job status
     * 
     * @param string $job_id Job ID
     * @return array|false Job status data or false if not found
     */
    public function get_job_status($job_id);
    
    /**
     * Cancel/remove job from queue
     * 
     * @param string $job_id Job ID
     * @return bool True on success, false on failure
     */
    public function cancel_job($job_id);
    
    /**
     * Get queue statistics
     * 
     * @param string $job_type Optional job type filter
     * @return array Queue statistics (pending, completed, failed counts)
     */
    public function get_stats($job_type = '');
    
    /**
     * Get jobs list with optional filtering
     * 
     * @param array $filters Filters (status, job_type, user_id, etc.)
     * @param int $limit Maximum number of jobs to return
     * @param int $offset Offset for pagination
     * @return array Jobs list
     */
    public function get_jobs($filters = array(), $limit = 20, $offset = 0);
    
    /**
     * Process jobs in queue
     * 
     * @param int $max_jobs Maximum number of jobs to process
     * @param int $time_limit Maximum execution time in seconds
     * @return array Processing results
     */
    public function process_jobs($max_jobs = 10, $time_limit = 30);
    
    /**
     * Clear completed jobs older than specified time
     * 
     * @param string $retention_period Time period (e.g., '7days', '30days')
     * @param array $job_types Job types to clean (empty = all types)
     * @return int Number of jobs cleaned
     */
    public function cleanup_old_jobs($retention_period = '7days', $job_types = array());
    
    /**
     * Pause/resume queue processing
     * 
     * @param bool $paused True to pause, false to resume
     * @return bool True on success, false on failure
     */
    public function set_paused($paused);
    
    /**
     * Check if queue is paused
     * 
     * @return bool True if paused, false if active
     */
    public function is_paused();
    
    /**
     * Get queue health status
     * 
     * @return array Health status information
     */
    public function get_health_status();
    
    /**
     * Retry failed jobs
     * 
     * @param array $filters Filters for which jobs to retry
     * @param int $max_retries Maximum retry attempts
     * @return int Number of jobs queued for retry
     */
    public function retry_failed_jobs($filters = array(), $max_retries = 3);
    
    /**
     * Create batch job for bulk operations
     * 
     * @param string $batch_type Batch operation type
     * @param array $items Items to process in batch
     * @param array $options Batch processing options
     * @return string|false Batch ID or false on failure
     */
    public function create_batch($batch_type, $items, $options = array());
    
    /**
     * Get batch status and progress
     * 
     * @param string $batch_id Batch ID
     * @return array|false Batch status or false if not found
     */
    public function get_batch_status($batch_id);
    
    /**
     * Cancel entire batch
     * 
     * @param string $batch_id Batch ID
     * @return bool True on success, false on failure
     */
    public function cancel_batch($batch_id);
}