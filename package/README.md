# WPMatch - WordPress Dating Plugin

A professional WordPress dating plugin with Tinder-style swiping, advanced matching algorithms, and premium membership integration via WooCommerce.

## Features

### Core Functionality ✅
- **User Profiles** - Comprehensive dating profiles with photos, preferences, and interests
- **Smart Matching** - Algorithm-based compatibility matching with distance filtering
- **Swipe Interface** - Tinder-style swiping for likes/passes
- **Search & Discovery** - Advanced search with age, location, and interest filters
- **Messaging System** - Private messaging between matched users
- **Premium Memberships** - WooCommerce integration for paid features

### Premium Features ✅
- **Unlimited Likes** - Remove daily like limits
- **See Who Liked You** - View profiles that liked you
- **Advanced Search** - Enhanced filtering options
- **Profile Boost** - Increase profile visibility
- **Read Receipts** - See when messages are read

## Requirements

- **WordPress** 5.8+
- **PHP** 7.4+
- **MySQL** 5.7+ or MariaDB 10.3+
- **WooCommerce** 6.0+ (for premium features)

## Installation

1. Upload the `wpmatch` folder to `/wp-content/plugins/`
2. Activate the plugin through WordPress admin
3. Configure settings in **WPMatch > Settings**
4. Set up WooCommerce products for premium memberships
5. Add shortcodes to pages: `[wpmatch_search]`, `[wpmatch_matches]`, `[wpmatch_premium_shop]`

## Shortcodes

- `[wpmatch_search]` - Display search interface
- `[wpmatch_matches]` - Show user matches
- `[wpmatch_premium_shop]` - Premium membership shop

## Database Tables

The plugin creates these tables:
- `wp_wpmatch_user_profiles` - User dating profiles
- `wp_wpmatch_swipes` - Like/pass tracking
- `wp_wpmatch_matches` - Mutual matches
- `wp_wpmatch_messages` - Private messages
- `wp_wpmatch_user_media` - Profile photos

## Security & Standards

- **WordPress Coding Standards** compliant
- **PHPCS** validated code
- **Input sanitization** and output escaping
- **Nonce verification** for all forms
- **Capability checks** for user permissions
- **Prepared SQL statements** for database queries

## Version

**1.0.0** - Phase 1 MVP Release

## License

GPL v2 or later

## Support

For support and documentation, visit the plugin repository.