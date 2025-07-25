<?php
/**
 * File: class-ajax-base.php
 * Location: /includes/ajax/class-ajax-base.php
 * 
 * AJAX Base Class - Fonctionnalités communes
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class Ajax_Base {
    
    /**
     * 🔒 PROTECTION : Requêtes en cours
     */
    protected static $active_requests = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks - À implémenter dans chaque classe fille
     */
    abstract protected function init_hooks();
    
    /**
     * 🔒 PROTECTION : Validation de sécurité commune
     */
    protected function validate_ajax_request($required_capability = 'edit_posts') {
        // Vérifier le nonce
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce')) {
            $this->send_error('Security check failed', 'NONCE_FAILED');
        }
        
        // Vérifier les permissions
        if (!current_user_can($required_capability)) {
            $this->send_error('Insufficient permissions', 'PERMISSION_DENIED');
        }
        
        return true;
    }
    
    /**
     * 🔒 PROTECTION : Éviter requêtes simultanées identiques
     */
    protected function check_duplicate_request($request_key) {
        if (isset(self::$active_requests[$request_key])) {
            $this->send_error('Request already in progress', 'DUPLICATE_REQUEST');
        }
        
        self::$active_requests[$request_key] = time();
        return $request_key;
    }
    
    /**
     * 🔒 PROTECTION : Nettoyer requête active
     */
    protected function cleanup_request($request_key) {
        unset(self::$active_requests[$request_key]);
        error_log("Nexus AJAX: Cleaned up request: $request_key");
    }
    
    /**
     * Envoyer une réponse de succès
     */
    protected function send_success($data = array(), $message = '') {
        if ($message) {
            $data['message'] = $message;
        }
        wp_send_json_success($data);
    }
    
    /**
     * Envoyer une réponse d'erreur
     */
    protected function send_error($message, $error_code = 'GENERIC_ERROR', $data = array()) {
        error_log("Nexus AJAX Error [$error_code]: $message");
        
        $error_data = array_merge($data, array(
            'error' => $message,
            'error_code' => $error_code
        ));
        
        wp_send_json_error($error_data);
    }
    
    /**
     * Valider un ID de post
     */
    protected function validate_post_id($post_id, $required_capability = 'edit_post') {
        $post_id = (int) $post_id;
        
        if (!$post_id) {
            $this->send_error('Invalid post ID', 'INVALID_POST_ID');
        }
        
        $post = get_post($post_id);
        if (!$post) {
            $this->send_error('Post not found', 'POST_NOT_FOUND');
        }
        
        if (!current_user_can($required_capability, $post_id)) {
            $this->send_error('No permission for this post', 'POST_PERMISSION_DENIED');
        }
        
        return $post;
    }
    
    /**
     * Logger l'utilisation pour analytics
     */
    protected function log_usage($action, $data = array()) {
        $log_entry = array_merge($data, array(
            'timestamp' => current_time('timestamp'),
            'date' => current_time('Y-m-d H:i:s'),
            'action' => $action,
            'user_id' => get_current_user_id(),
            'user_login' => wp_get_current_user()->user_login
        ));
        
        $usage_log = get_option('nexus_translator_usage_log', array());
        $usage_log[] = $log_entry;
        
        // Garder seulement les 200 dernières entrées
        if (count($usage_log) > 200) {
            $usage_log = array_slice($usage_log, -200);
        }
        
        update_option('nexus_translator_usage_log', $usage_log);
    }
    
    /**
     * 🔒 MÉTHODES DE DIAGNOSTIC
     */
    public static function get_active_requests() {
        return self::$active_requests;
    }
    
    public static function force_cleanup_requests() {
        self::$active_requests = array();
        error_log("Nexus AJAX Base: Force cleanup completed");
    }
    
    /**
     * Cleanup des requêtes expirées (>5 minutes)
     */
    public static function cleanup_expired_requests() {
        $current_time = time();
        $timeout = 300; // 5 minutes
        $cleaned = 0;
        
        foreach (self::$active_requests as $key => $timestamp) {
            if (($current_time - $timestamp) > $timeout) {
                unset(self::$active_requests[$key]);
                $cleaned++;
            }
        }
        
        if ($cleaned > 0) {
            error_log("Nexus AJAX Base: Cleaned up $cleaned expired requests");
        }
        
        return $cleaned;
    }
}