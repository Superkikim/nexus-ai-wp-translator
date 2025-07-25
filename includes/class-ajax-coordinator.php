<?php
/**
 * File: class-translator-ajax-coordinator.php
 * Location: /includes/class-translator-ajax-coordinator.php
 * 
 * AJAX Coordinator - Charge et coordonne tous les handlers AJAX
 * REMPLACE l'ancien class-translator-ajax.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translator_AJAX_Coordinator {
    
    /**
     * Instances des handlers spécialisés
     */
    private $translation_handler;
    private $admin_handler;
    private $analytics_handler;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_handlers();
        $this->init_cleanup_hooks();
    }
    
    /**
     * Charger tous les handlers spécialisés
     */
    private function load_handlers() {
        // Charger les classes de base
        require_once NEXUS_TRANSLATOR_INCLUDES_DIR . 'ajax/class-ajax-base.php';
        
        // Charger et initialiser les handlers spécialisés
        if (file_exists(NEXUS_TRANSLATOR_INCLUDES_DIR . 'ajax/class-ajax-translation.php')) {
            require_once NEXUS_TRANSLATOR_INCLUDES_DIR . 'ajax/class-ajax-translation.php';
            $this->translation_handler = new Ajax_Translation();
            error_log('Nexus AJAX Coordinator: Translation handler loaded');
        }
        
        if (file_exists(NEXUS_TRANSLATOR_INCLUDES_DIR . 'ajax/class-ajax-admin.php')) {
            require_once NEXUS_TRANSLATOR_INCLUDES_DIR . 'ajax/class-ajax-admin.php';
            $this->admin_handler = new Ajax_Admin();
            error_log('Nexus AJAX Coordinator: Admin handler loaded');
        }
        
        if (file_exists(NEXUS_TRANSLATOR_INCLUDES_DIR . 'ajax/class-ajax-analytics.php')) {
            require_once NEXUS_TRANSLATOR_INCLUDES_DIR . 'ajax/class-ajax-analytics.php';
            $this->analytics_handler = new Ajax_Analytics();
            error_log('Nexus AJAX Coordinator: Analytics handler loaded');
        }
    }
    
    /**
     * Initialiser les hooks de nettoyage
     */
    private function init_cleanup_hooks() {
        // Nettoyage périodique des requêtes expirées
        add_action('wp_ajax_nexus_force_cleanup', array($this, 'handle_force_cleanup'));
        
        // Hook WordPress pour nettoyage quotidien
        if (!wp_next_scheduled('nexus_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'nexus_daily_cleanup');
        }
        add_action('nexus_daily_cleanup', array($this, 'daily_cleanup'));
    }
    
    /**
     * Nettoyage quotidien automatique
     */
    public function daily_cleanup() {
        error_log('Nexus AJAX Coordinator: Starting daily cleanup');
        
        // Nettoyer les requêtes expirées de la classe de base
        $cleaned_requests = Ajax_Base::cleanup_expired_requests();
        
        // Nettoyer les verrous de traduction obsolètes
        if ($this->analytics_handler) {
            // Note: Cette méthode devrait être publique dans Ajax_Analytics
            // $cleaned_locks = $this->analytics_handler->cleanup_old_translation_locks();
        }
        
        // Nettoyer les logs anciens (garder 30 jours)
        $this->cleanup_old_logs();
        
        error_log("Nexus AJAX Coordinator: Daily cleanup completed - $cleaned_requests requests cleaned");
    }
    
    /**
     * Nettoyage forcé (pour debug/admin)
     */
    public function handle_force_cleanup() {
        if (!wp_verify_nonce($_POST['nonce'], 'nexus_translator_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(array('error' => 'Access denied', 'error_code' => 'ACCESS_DENIED'));
        }
        
        // Force cleanup sur tous les handlers
        Ajax_Base::force_cleanup_requests();
        
        // Nettoyer les verrous
        delete_option('nexus_translator_active_translations');
        
        // Clear transients
        delete_transient('nexus_translator_cache');
        
        wp_send_json_success(array(
            'message' => 'Force cleanup completed',
            'timestamp' => current_time('mysql')
        ));
    }
    
    /**
     * Nettoyer les anciens logs
     */
    private function cleanup_old_logs() {
        $retention_days = get_option('nexus_translator_analytics_retention', 30);
        $cutoff_date = date('Y-m-d', strtotime("-{$retention_days} days"));
        
        // Nettoyer les stats quotidiennes
        $daily_stats = get_option('nexus_translator_daily_stats', array());
        foreach ($daily_stats as $date => $stats) {
            if ($date < $cutoff_date) {
                unset($daily_stats[$date]);
            }
        }
        update_option('nexus_translator_daily_stats', $daily_stats);
        
        // Nettoyer les logs d'utilisation
        $usage_log = get_option('nexus_translator_usage_log', array());
        $cutoff_timestamp = strtotime("-{$retention_days} days");
        
        $cleaned_log = array_filter($usage_log, function($entry) use ($cutoff_timestamp) {
            return $entry['timestamp'] >= $cutoff_timestamp;
        });
        
        if (count($cleaned_log) !== count($usage_log)) {
            update_option('nexus_translator_usage_log', array_values($cleaned_log));
            $cleaned_count = count($usage_log) - count($cleaned_log);
            error_log("Nexus AJAX Coordinator: Cleaned {$cleaned_count} old log entries");
        }
    }
    
    /**
     * Obtenir le statut de tous les handlers
     */
    public function get_handlers_status() {
        return array(
            'translation_handler' => $this->translation_handler ? 'loaded' : 'missing',
            'admin_handler' => $this->admin_handler ? 'loaded' : 'missing',
            'analytics_handler' => $this->analytics_handler ? 'loaded' : 'missing',
            'active_requests' => Ajax_Base::get_active_requests(),
            'scheduled_cleanup' => wp_next_scheduled('nexus_daily_cleanup')
        );
    }
    
    /**
     * 🔒 MÉTHODES DE DIAGNOSTIC PUBLIC
     */
    public function force_cleanup_all() {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        Ajax_Base::force_cleanup_requests();
        delete_option('nexus_translator_active_translations');
        wp_cache_flush();
        
        error_log('Nexus AJAX Coordinator: Force cleanup all completed by admin');
        return true;
    }
}