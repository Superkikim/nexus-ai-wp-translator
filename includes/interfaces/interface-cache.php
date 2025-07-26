<?php
/**
 * File: interface-cache.php
 * Location: /includes/interfaces/interface-cache.php
 * 
 * Cache interface for future cache implementations
 * Defines contract for caching translations, API responses, and computed data
 */

namespace Nexus\Translator\Interfaces;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cache Interface
 * 
 * Contract for cache implementations
 * Defines methods for storing, retrieving, and managing cached data
 * 
 * @since 0.0.1
 * @package Nexus\Translator\Interfaces
 */
interface Cache_Interface {
    
    /**
     * Store data in cache
     * 
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @param int $expiration Expiration time in seconds (0 = no expiration)
     * @param array $tags Cache tags for grouping and invalidation
     * @return bool True on success, false on failure
     */
    public function set($key, $data, $expiration = 3600, $tags = array());
    
    /**
     * Retrieve data from cache
     * 
     * @param string $key Cache key
     * @param mixed $default Default value if not found
     * @return mixed Cached data or default value
     */
    public function get($key, $default = null);
    
    /**
     * Check if cache key exists
     * 
     * @param string $key Cache key
     * @return bool True if exists, false otherwise
     */
    public function exists($key);
    
    /**
     * Delete specific cache entry
     * 
     * @param string $key Cache key
     * @return bool True on success, false on failure
     */
    public function delete($key);
    
    /**
     * Clear multiple cache entries
     * 
     * @param array $keys Array of cache keys
     * @return bool True if all deleted successfully
     */
    public function delete_multiple($keys);
    
    /**
     * Clear cache entries by tag
     * 
     * @param string|array $tags Tag or array of tags
     * @return bool True on success, false on failure
     */
    public function invalidate_by_tag($tags);
    
    /**
     * Clear cache entries by pattern
     * 
     * @param string $pattern Key pattern (e.g., 'translation_*')
     * @return bool True on success, false on failure
     */
    public function invalidate_by_pattern($pattern);
    
    /**
     * Clear all cache data
     * 
     * @return bool True on success, false on failure
     */
    public function flush();
    
    /**
     * Get cache statistics
     * 
     * @return array Cache statistics (hits, misses, size, etc.)
     */
    public function get_stats();
    
    /**
     * Get cache size information
     * 
     * @return array Size information (total size, entry count, etc.)
     */
    public function get_size_info();
    
    /**
     * Store data with automatic key generation
     * 
     * @param mixed $data Data to cache
     * @param array $key_components Components for key generation
     * @param int $expiration Expiration time in seconds
     * @param array $tags Cache tags
     * @return string|false Generated cache key or false on failure
     */
    public function auto_set($data, $key_components, $expiration = 3600, $tags = array());
    
    /**
     * Get or set cache data (cache-aside pattern)
     * 
     * @param string $key Cache key
     * @param callable $callback Callback to generate data if not cached
     * @param int $expiration Expiration time in seconds
     * @param array $tags Cache tags
     * @return mixed Cached or generated data
     */
    public function remember($key, $callback, $expiration = 3600, $tags = array());
    
    /**
     * Check if cache backend is available and working
     * 
     * @return bool True if cache is working
     */
    public function is_available();
    
    /**
     * Get cache configuration and status
     * 
     * @return array Configuration and status information
     */
    public function get_status();
    
    /**
     * Warm up cache with pre-computed data
     * 
     * @param array $data_set Array of key-value pairs to pre-load
     * @return bool True on success, false on failure
     */
    public function warm_up($data_set);
    
    /**
     * Clean up expired cache entries
     * 
     * @return bool True on success, false on failure
     */
    public function cleanup_expired();
}