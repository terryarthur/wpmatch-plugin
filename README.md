# WPMatch - Ultimate WordPress Dating Plugin

A comprehensive WordPress dating plugin that combines the best features from leading dating platforms with enterprise-level functionality and extensive customization options.

## 🌟 Features

### Core Dating Platform Features
- **User Registration & Profile Creation** - Complete 4-step registration flow with photo uploads
- **Advanced Matching Algorithm** - Smart compatibility scoring based on preferences and interests
- **Swipe Interface** - Touch-friendly card-based browsing with like/pass functionality
- **Real-time Messaging** - Instant messaging system with conversation management
- **Photo & Video Profiles** - Multi-media profile support with verification
- **Search & Discovery** - Advanced filtering and location-based matching
- **Mobile-First Design** - Responsive interface optimized for all devices

### Advanced Features
- **Gamification System** - Points, badges, achievements, and daily challenges
- **Video Chat Integration** - Built-in video calling and speed dating events
- **Voice Notes** - Audio message support with reactions
- **AI-Powered Features** - Smart conversation starters and compatibility insights
- **Social Media Integration** - Facebook, Instagram, Twitter authentication
- **Events System** - Virtual meetups, speed dating, and group activities
- **Photo Verification** - AI-powered identity verification system
- **Premium Memberships** - WooCommerce integration for subscription management

### Security & Compliance Features
- **GDPR Compliance** - Complete data protection and user rights management
- **Enterprise Security** - Multi-layered threat detection and prevention
- **Data Encryption** - AES-256-GCM encryption for sensitive user data
- **Two-Factor Authentication** - Enhanced account security options
- **Rate Limiting** - Protection against abuse and spam
- **Security Auditing** - Comprehensive logging and monitoring

### Machine Learning & AI Features
- **ML-Enhanced Matching** - Behavioral learning and preference adaptation
- **Real-time Chat** - WebSocket-based instant messaging with video calling
- **Smart Notifications** - Multi-channel notification system with user preferences
- **Social OAuth Integration** - Seamless authentication with 6+ social platforms

### Developer Features
- **REST API** - Complete API with 80+ endpoints for mobile apps
- **Webhooks & Hooks** - Extensive customization through WordPress actions/filters
- **Real-time Updates** - WebSocket support for live notifications
- **Analytics Dashboard** - Comprehensive admin reporting and insights
- **Multi-language Support** - Full internationalization (i18n) ready
- **Performance Optimized** - Caching, query optimization, and CDN ready

## 📋 Requirements

- **WordPress**: 5.8 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher (or MariaDB 10.1+)
- **Memory Limit**: 256MB recommended
- **Storage**: 50MB for plugin files

### Optional Requirements
- **WooCommerce**: For premium membership features
- **SSL Certificate**: Required for secure messaging and payments
- **Cron Jobs**: For background processing (recommended)

## 🚀 Installation

### Automatic Installation (Recommended)

1. **From WordPress Admin:**
   - Go to `Plugins > Add New`
   - Search for "WPMatch"
   - Click "Install Now" and then "Activate"

2. **Upload via Admin:**
   - Download the plugin ZIP file
   - Go to `Plugins > Add New > Upload Plugin`
   - Choose file and click "Install Now"
   - Activate the plugin

### Manual Installation

1. **Download and Extract:**
   ```bash
   wget https://downloads.wordpress.org/plugin/wpmatch.zip
   unzip wpmatch.zip
   ```

2. **Upload to WordPress:**
   - Upload the `wpmatch` folder to `/wp-content/plugins/`
   - Activate through the 'Plugins' menu in WordPress

3. **Database Setup:**
   - The plugin will automatically create 56 database tables on activation
   - Ensure your database user has CREATE and ALTER privileges
   - Includes advanced features like ML weights, behavioral patterns, and security logs

## ⚙️ Configuration

### Initial Setup

1. **Admin Dashboard:**
   - Navigate to `WPMatch > Settings`
   - Configure basic settings (registration, matching preferences)
   - Set up email notifications and API keys

2. **Create Pages:**
   - The plugin includes shortcodes for different features
   - Recommended pages to create:
     ```
     [wpmatch_registration] - User registration page
     [wpmatch_swipe] - Main dating interface
     [wpmatch_messages] - Messaging dashboard
     [wpmatch_profile] - User profile management
     [wpmatch_matches] - Match discovery page
     ```

3. **Configure Permalinks:**
   - Go to `Settings > Permalinks`
   - Click "Save Changes" to refresh rewrite rules

### Essential Settings

#### Registration Settings
```php
// Minimum age requirement
define('WPMATCH_MIN_AGE', 18);

// Enable photo verification
define('WPMATCH_PHOTO_VERIFICATION', true);

// Maximum file upload size (MB)
define('WPMATCH_MAX_UPLOAD_SIZE', 5);
```

#### Matching Algorithm
```php
// Default matching radius (kilometers)
define('WPMATCH_DEFAULT_RADIUS', 50);

// Maximum daily matches
define('WPMATCH_DAILY_MATCHES', 20);

// Compatibility score threshold
define('WPMATCH_MIN_COMPATIBILITY', 60);
```

## 📱 Shortcodes

### Core Shortcodes

| Shortcode | Purpose | Example |
|-----------|---------|---------|
| `[wpmatch_registration]` | User registration form | `[wpmatch_registration redirect_url="/dashboard"]` |
| `[wpmatch_swipe]` | Main dating interface | `[wpmatch_swipe show_filters="true"]` |
| `[wpmatch_messages]` | Messaging dashboard | `[wpmatch_messages layout="sidebar"]` |
| `[wpmatch_profile]` | Profile management | `[wpmatch_profile user_id="123"]` |
| `[wpmatch_matches]` | Match discovery | `[wpmatch_matches view="grid" per_page="12"]` |
| `[wpmatch_search]` | User search interface | `[wpmatch_search show_filters="true"]` |

### Advanced Shortcodes

| Shortcode | Purpose | Example |
|-----------|---------|---------|
| `[wpmatch_gamification]` | Points and achievements | `[wpmatch_gamification show="leaderboard"]` |
| `[wpmatch_events]` | Events listing | `[wpmatch_events type="speed_dating"]` |
| `[wpmatch_video_chat]` | Video chat interface | `[wpmatch_video_chat room_type="private"]` |
| `[wpmatch_membership]` | Premium membership | `[wpmatch_membership show_plans="true"]` |

## 🛠️ Developer Guide

### REST API

The plugin provides a comprehensive REST API with 80+ endpoints:

**Base URL:** `https://yoursite.com/wp-json/wpmatch/v1/`

#### Authentication
```javascript
// Login
POST /auth/login
{
  "username": "user@example.com",
  "password": "password123"
}

// Register
POST /users/register
{
  "username": "newuser",
  "email": "user@example.com",
  "password": "password123"
}
```

#### Core Endpoints
```javascript
// Get user matches
GET /matches?page=1&per_page=20

// Send swipe action
POST /swipes
{
  "target_user_id": 123,
  "direction": "like"
}

// Send message
POST /messages/send
{
  "recipient_id": 456,
  "content": "Hello there!"
}

// Get user profile
GET /profiles/123
```

### Custom Hooks

#### Actions
```php
// User registration completed
do_action('wpmatch_user_registered', $user_id);

// New match created
do_action('wpmatch_match_created', $user1_id, $user2_id);

// Message sent
do_action('wpmatch_message_sent', $sender_id, $recipient_id, $message_id);
```

#### Filters
```php
// Modify matching algorithm
add_filter('wpmatch_compatibility_score', 'custom_scoring', 10, 3);

// Customize profile fields
add_filter('wpmatch_profile_fields', 'add_custom_fields');

// Modify search results
add_filter('wpmatch_search_results', 'custom_search_logic', 10, 2);
```

### Database Schema

The plugin creates 48 tables with relationships:

**Core Tables:**
- `wp_wpmatch_user_profiles` - User profile data
- `wp_wpmatch_matches` - Match relationships
- `wp_wpmatch_messages` - Chat messages
- `wp_wpmatch_swipes` - User swipe actions
- `wp_wpmatch_user_preferences` - Matching preferences

**Feature Tables:**
- `wp_wpmatch_gamification_*` - Points, badges, achievements
- `wp_wpmatch_events_*` - Events and registrations
- `wp_wpmatch_video_*` - Video chat functionality
- `wp_wpmatch_verification_*` - Photo verification

## 🔒 Security

### Security Features

- **Data Encryption:** All sensitive data encrypted at rest
- **CSRF Protection:** Nonce verification on all forms
- **SQL Injection Prevention:** Prepared statements throughout
- **XSS Protection:** Output escaping and input sanitization
- **File Upload Security:** Type and size validation
- **Rate Limiting:** API endpoint throttling

### Security Best Practices

1. **SSL/HTTPS:** Always use SSL for production
2. **Regular Updates:** Keep WordPress and plugins updated
3. **Strong Passwords:** Enforce password complexity
4. **Two-Factor Auth:** Recommended for admin accounts
5. **File Permissions:** Proper server file permissions
6. **Database Security:** Limited database user privileges

## 🌍 Internationalization

The plugin is fully translation-ready:

1. **Translation Files:** Located in `/languages/` directory
2. **Text Domain:** `wpmatch`
3. **POT File:** `wpmatch.pot` included for translators

### Available Translations

- English (default)
- Spanish (es_ES)
- French (fr_FR)
- German (de_DE)
- Portuguese (pt_BR)

### Adding Custom Translations

```bash
# Generate translation file
wp i18n make-pot . languages/wpmatch.pot

# Create translation
msgfmt languages/wpmatch-es_ES.po -o languages/wpmatch-es_ES.mo
```

## 🚨 Troubleshooting

### Common Issues

#### Plugin Activation Errors
```
Error: Class 'WPMatch_Cache_Manager' not found
```
**Solution:** Ensure all plugin files are uploaded correctly

#### Database Connection Issues
```
Error: Table 'wp_wpmatch_users' doesn't exist
```
**Solution:** Deactivate and reactivate plugin to run database setup

#### Memory Limit Errors
```
Fatal error: Allowed memory size exhausted
```
**Solution:** Increase PHP memory limit to 256MB minimum

#### API Authentication Errors
```
{"code":"rest_forbidden","message":"Sorry, you are not allowed to do that."}
```
**Solution:** Check user permissions and API authentication

### Debug Mode

Enable debug mode for troubleshooting:

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WPMATCH_DEBUG', true);
```

## 📞 Support

### Documentation
- **User Guide:** [https://docs.wpmatch.com/user-guide](https://docs.wpmatch.com/user-guide)
- **Developer Docs:** [https://docs.wpmatch.com/developers](https://docs.wpmatch.com/developers)
- **API Reference:** [https://docs.wpmatch.com/api](https://docs.wpmatch.com/api)

### Community Support
- **WordPress Forum:** [https://wordpress.org/support/plugin/wpmatch](https://wordpress.org/support/plugin/wpmatch)
- **GitHub Issues:** [https://github.com/wpmatch/wpmatch](https://github.com/wpmatch/wpmatch)
- **Community Discord:** [https://discord.gg/wpmatch](https://discord.gg/wpmatch)

## 📄 License

This plugin is licensed under the GPL v2 or later.

```
WPMatch - Ultimate WordPress Dating Plugin
Copyright (C) 2024 WPMatch Team

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## 📈 Changelog

### Version 1.0.0 (2024-09-22)
- Initial release
- Complete dating platform functionality
- REST API with 80+ endpoints
- Gamification system
- Video chat integration
- Mobile-first responsive design
- Performance optimizations
- Comprehensive security features

---

**Made with ❤️ by the WPMatch Team**