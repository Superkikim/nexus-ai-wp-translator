<?php
/**
 * MODIFICATION NÉCESSAIRE dans class-nexus-translator.php
 * 
 * Remplacer la ligne dans init_components() :
 */

// ANCIEN CODE (à supprimer) :
// if (class_exists('Translator_AJAX')) {
//     $this->components['ajax'] = new Translator_AJAX();
// }

// NOUVEAU CODE (à ajouter) :
if (is_admin()) {
    // Charger le coordinator AJAX au lieu de l'ancienne classe
    require_once NEXUS_TRANSLATOR_INCLUDES_DIR . 'class-translator-ajax-coordinator.php';
    $this->components['ajax_coordinator'] = new Translator_AJAX_Coordinator();
}

/**
 * Et dans nexus-ai-wp-translator.php, modifier load_includes() :
 */

// ANCIEN CODE dans load_includes() (à supprimer) :
// 'class-translator-ajax.php'

// NOUVEAU CODE (à ajouter) :
// Le coordinator se charge automatiquement du loading

/**
 * Structure finale des fichiers AJAX :
 * 
 * includes/
 * ├── class-translator-ajax-coordinator.php  # ← NOUVEAU (remplace l'ancien)
 * └── ajax/
 *     ├── class-ajax-base.php               # ← NOUVEAU
 *     ├── class-ajax-translation.php        # ← NOUVEAU  
 *     ├── class-ajax-admin.php             # ← NOUVEAU
 *     └── class-ajax-analytics.php         # ← NOUVEAU
 */