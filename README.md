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
│   ├── class-translator-ajax-coordinator.php # AJAX coordinator (modular system)
│   ├── class-post-linker.php          # Post relationship management
│   ├── class-language-manager.php      # Language management
│   ├── class-translation-panel.php     # Translation panel/metabox
│   └── ajax/                           # Modular AJAX System
│       ├── class-ajax-base.php         # Base AJAX class (security/protection)
│       ├── class-ajax-translation.php  # Translation AJAX handlers
│       ├── class-ajax-admin.php        # Admin/emergency AJAX handlers
│       └── class-ajax-analytics.php    # Analytics AJAX handlers
├── admin/
│   ├── css/
│   │   ├── admin-style.css             # Admin styles
│   │   └── popup-style.css             # Popup styles
│   ├── js/
│   │   ├── admin-core.js               # Core admin JavaScript
│   │   └── admin-modules.js            # Translation & analytics modules
│   └── views/
│       ├── admin-page.php              # Settings page
│       ├── translation-meta-box.php    # Translation metabox template
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

### Core Classes

#### 1. **Nexus_Translator** (Main Class)
- Plugin entry point and lifecycle management
- Component initialization and orchestration
- WordPress hooks and action management
- Activation/deactivation procedures

#### 2. **Translator_API** 
- Claude AI API communication and authentication
- Request/response handling with retry logic
- Error handling (credits, network, rate limits)
- Translation request formatting and validation
- Rate limiting and usage tracking

#### 3. **Translator_Admin**
- WordPress admin interface integration
- Settings page management (API, languages, general)
- Post editor integration and metabox registration
- User permission and capability management
- Admin notices and validation messages

#### 4. **Post_Linker**
- Original/translated post relationship management
- WordPress metadata handling (post_meta)
- Language version navigation and linking
- Translation status tracking and synchronization
- Cleanup of orphaned relationships

#### 5. **Language_Manager**
- Supported language configuration and validation
- Source/target language management
- Language code normalization and validation
- Language flags and display names
- Translation pair configuration

#### 6. **Translation_Panel**
- Post editor metabox interface
- Real-time translation status display
- Language selection and management
- Translation action buttons and controls

### Modular AJAX System

#### 7. **Translator_AJAX_Coordinator**
- Centralized AJAX handler loading and coordination
- Specialized handler initialization
- System cleanup and maintenance scheduling
- Emergency handler registration
- Request routing and error handling

#### 8. **Ajax_Base** (Abstract Base Class)
- Common security validation for all AJAX requests
- Request deduplication and rate limiting protection
- Enhanced logging and debugging capabilities
- Error standardization and response formatting
- Usage analytics and monitoring

#### 9. **Ajax_Translation** (Translation Handlers)
- Core translation request processing
- Post content preparation and API communication
- Translation relationship creation and management
- Bulk translation processing
- Content copying (meta, taxonomies, etc.)

#### 10. **Ajax_Admin** (Admin/Emergency Handlers)
- Emergency stop controls and system protection
- Rate limit management and reset functionality
- Configuration export/import capabilities
- System diagnostics and validation
- Administrative maintenance operations

#### 11. **Ajax_Analytics** (Analytics Handlers)
- Usage statistics collection and analysis
- System performance monitoring
- Error tracking and trend analysis
- Data export capabilities (JSON, CSV)
- Historical data cleanup and retention

## 📊 Database Schema

### WordPress Tables Used
- **wp_posts**: Storage for translated content
- **wp_postmeta**: Translation relationships and metadata
- **wp_options**: Plugin configuration and analytics

### Post Metadata Keys
```
_nexus_translation_of      → ID of original post
_nexus_language           → Language code (fr, en, es, etc.)
_nexus_translation_status → pending|completed|error|outdated
_nexus_has_translation_XX → Translation ID for language XX
_nexus_auto_translate     → Auto-translation enabled flag
_nexus_target_languages   → Array of target languages
_nexus_translation_timestamp → Translation completion time
```

### Plugin Options
```
nexus_translator_options           → General plugin settings
nexus_translator_api_settings      → Claude API configuration
nexus_translator_language_settings → Language preferences
nexus_translator_emergency_stop    → Emergency stop status
nexus_translator_usage_log         → Analytics and usage data
nexus_translator_daily_stats       → Daily statistics cache
nexus_translator_active_translations → Currently processing translations
```

## 🔄 Translation Workflow

### Standard Translation Process
1. **User initiates translation** via post editor metabox or bulk action
2. **Security validation** checks permissions and nonce verification
3. **Pre-translation validation** ensures post editability and API availability
4. **Content preparation** extracts title, content, excerpt, and metadata
5. **API communication** sends structured request to Claude AI
6. **Response processing** validates and parses translated content
7. **Post creation** creates new WordPress post with translated content
8. **Relationship establishment** links original and translated posts
9. **Metadata copying** transfers relevant post meta and taxonomies
10. **Status updates** marks translation as completed and logs usage

### Emergency Protection Features
- **Duplicate request prevention** blocks simultaneous translation attempts
- **Rate limiting** prevents API quota exhaustion
- **Emergency stop** allows immediate halt of all translation activity
- **Request timeout handling** cleans up stuck or abandoned requests
- **Error recovery** provides detailed error reporting and suggested actions

## 🛡️ Security Features

### AJAX Security
- **WordPress nonce verification** for all AJAX requests
- **User capability checking** ensures proper permissions
- **Request deduplication** prevents abuse and conflicts
- **Input sanitization** validates all user-provided data
- **Error logging** tracks security violations and attempts

### API Protection
- **Rate limiting** prevents quota exhaustion
- **Request queuing** manages high-volume translation loads
- **Error handling** gracefully manages API failures
- **Usage tracking** monitors API consumption patterns
- **Emergency controls** provide immediate system protection

### Data Protection
- **Metadata isolation** prevents cross-contamination
- **Relationship validation** ensures data integrity
- **Cleanup procedures** remove orphaned data
- **Backup considerations** for configuration export/import

## ⚙️ Configuration Management

### API Settings
- Claude AI API key configuration
- Rate limiting preferences
- Debug mode and logging levels
- Error handling preferences

### Language Settings
- Source language selection
- Target language configuration
- Auto-translation preferences
- Language-specific settings

### Advanced Settings
- Emergency controls and system protection
- Analytics data retention policies
- Performance optimization settings
- Bulk operation limits

## 📈 Analytics & Monitoring

### Usage Analytics
- Translation volume and success rates
- Language pair popularity
- User activity patterns
- Error frequency and types

### System Monitoring
- API response times and reliability
- Memory usage and performance metrics
- Request queue status
- Error tracking and trends

### Data Management
- Configurable data retention periods
- Export capabilities for external analysis
- Cleanup procedures for optimal performance
- Historical trend analysis

## 🚀 Installation & Setup

### Requirements
- WordPress 5.0+
- PHP 7.4+
- Claude AI API key
- cURL support
- Adequate memory limits for bulk operations

### Installation Steps
1. Upload plugin files to `/wp-content/plugins/nexus-ai-wp-translator/`
2. Activate plugin through WordPress admin
3. Configure Claude AI API key in settings
4. Select source and target languages
5. Test API connection and begin translating

### Configuration Validation
The plugin includes comprehensive validation to ensure proper setup:
- API key verification and connection testing
- Language configuration validation
- Permission and capability checks
- System resource verification

## 🔧 Development & Debugging

### Debug Mode
Enable debug mode in plugin settings for:
- Detailed AJAX request/response logging
- API communication tracing
- Performance metric collection
- Error stack trace capture

### Log Locations
- WordPress debug log: `/wp-content/debug.log`
- Plugin-specific logs in database options
- Analytics data accessible via admin interface

### Common Issues
- **Emergency stop active**: Check advanced settings to reset
- **API connection failures**: Verify API key and network connectivity
- **Rate limiting**: Use emergency controls to reset limits
- **Memory issues**: Increase PHP memory limits for bulk operations

## 📝 Contributing

### Code Standards
- Follow WordPress coding standards
- Implement comprehensive error handling
- Include security validation for all user inputs
- Document all public methods and complex logic
- Write unit tests for critical functionality

### Architecture Guidelines
- Use modular AJAX system for new features
- Extend Ajax_Base for new AJAX handlers
- Follow established security patterns
- Implement proper logging and analytics
- Consider performance impact of new features

This documentation reflects the current modular architecture and provides comprehensive guidance for users, administrators, and developers working with the Nexus AI WP Translator plugin.