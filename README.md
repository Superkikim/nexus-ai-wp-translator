# Nexus AI WP Translator - Project Structure

## 📁 File Structure

```
nexus-ai-wp-translator/
├── nexus-ai-wp-translator.php          # Main plugin file (header + bootstrap)
├── README.md                           # Documentation
├── LICENSE                             # MIT License
├── .gitignore                          # Git ignore
├── includes/
│   ├── class-nexus-translator.php      # Main plugin class
│   ├── class-translator-api.php        # Claude AI API communication
│   ├── class-translator-admin.php      # Admin interface
│   ├── class-translator-ajax.php       # AJAX handlers
│   ├── class-post-linker.php          # Post relationship management
│   └── class-language-manager.php      # Language management
├── admin/
│   ├── css/
│   │   ├── admin-style.css             # Admin styles
│   │   └── popup-style.css             # Popup styles
│   ├── js/
│   │   ├── admin-script.js             # Admin scripts
│   │   ├── translation-popup.js        # Translation popup
│   │   └── progress-handler.js         # Real-time feedback handler
│   └── views/
│       ├── admin-page.php              # Settings page
│       ├── translation-popup.php       # Popup template
│       └── translation-status.php      # Translation status display
├── public/
│   ├── css/
│   │   └── public-style.css            # Frontend styles (if needed)
│   └── js/
│       └── public-script.js            # Frontend scripts (if needed)
├── languages/
│   ├── nexus-ai-wp-translator.pot      # Translation template
│   ├── nexus-ai-wp-translator-fr_FR.po # French translation
│   └── nexus-ai-wp-translator-en_US.po # English translation
└── assets/
    ├── icon-128x128.png                # Plugin icon
    └── banner-1544x500.png             # WordPress.org banner
```

## 🏗️ Class Architecture

### 1. **Nexus_Translator** (Main Class)
- Plugin entry point
- Activation/deactivation management
- Loading other classes
- WordPress hooks management

### 2. **Translator_API** 
- Claude AI API communication
- Error handling (credits, network, limits)
- Translation request formatting
- Translation caching (optional)

### 3. **Translator_Admin**
- Admin interface
- Settings page (languages, API key)
- Post editor integration
- Permission management

### 4. **Translator_AJAX**
- AJAX request handlers
- Real-time translation
- Progress feedback
- JSON response management

### 5. **Post_Linker**
- Original/translated post relationships
- Metadata relationships (post_meta)
- Language version navigation
- Status synchronization

### 6. **Language_Manager**
- Supported language management
- Admin language detection
- Translation pair configuration
- Language code validation

## 📋 Database

### WordPress Tables Used
- **wp_posts** : Translated article storage
- **wp_postmeta** : Post relationships (original/translated)
  - `_nexus_translation_of` : Original post ID
  - `_nexus_language` : Language code (fr, en, etc.)
  - `_nexus_translation_status` : Status (pending, completed, error)

### Post Metadata
```php
// For a translated post
add_post_meta($translated_post_id, '_nexus_translation_of', $original_post_id);
add_post_meta($translated_post_id, '_nexus_language', 'en');
add_post_meta($translated_post_i