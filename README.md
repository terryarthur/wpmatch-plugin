# WPMatch - WordPress Dating Plugin

A comprehensive WordPress dating plugin that creates a complete matching system with advanced membership features and WooCommerce integration.

## Features

### Core Dating Features
- **User Profile Management** - Complete profile creation and editing system
- **Advanced Matching Algorithm** - Smart compatibility matching based on user preferences
- **Swipe Interface** - Modern Tinder-like swipe system for browsing potential matches
- **Real-time Messaging** - Secure messaging system between matched users
- **Search & Discovery** - Advanced search filters for finding compatible users

### Membership System
- **Custom Tier Builder** - Create unlimited membership tiers with 12+ customizable features:
  - Unlimited Messages
  - Premium Profile Badge
  - Advanced Search Filters
  - Profile Boost
  - Super Likes
  - Read Receipts
  - Profile Views
  - Match Preferences
  - Ad-Free Experience
  - Priority Support
  - Exclusive Events
  - Monthly Spotlight
- **WooCommerce Integration** - Full e-commerce functionality for membership sales
- **Feature Restrictions** - Granular control over feature access per membership tier

### Administrative Tools
- **Enhanced Demo System** - Generate realistic sample users with complete dating profiles
- **Demo Data Management** - Clean removal of demo content with safety checks
- **Comprehensive Analytics** - Track user engagement and plugin performance
- **Membership Management** - Complete oversight of all membership tiers and users

## Installation

1. Upload the plugin files to `/wp-content/plugins/wpmatch/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Navigate to WPMatch in your admin menu to configure settings
4. Set up your membership tiers and pricing through the membership setup interface

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- WooCommerce plugin (for membership features)

## Quick Start

### Setting Up Membership Tiers

1. Go to **WPMatch > Membership Setup** in your WordPress admin
2. Use the **Custom Tier Builder** to create membership plans:
   - Enter tier name and pricing
   - Select features to include with each tier
   - Save to automatically create WooCommerce products
3. Configure feature restrictions for each tier level

### Generating Demo Content

1. Navigate to **WPMatch > Dashboard**
2. Click **Generate Sample Data** to create realistic demo users
3. Use **Create Demo Pages** to set up example dating pages with embedded shortcodes
4. When ready to go live, use **Cleanup Demo Data** to safely remove all demo content

### Customizing the Experience

- Use shortcodes to embed dating features on any page:
  - `[wpmatch_profile]` - User profile management
  - `[wpmatch_swipe]` - Swipe interface
  - `[wpmatch_matches]` - View matches
  - `[wpmatch_messages]` - Messaging system
  - `[wpmatch_search]` - Advanced search

## Development

This plugin follows WordPress coding standards and security best practices:

- All user inputs are properly sanitized
- CSRF protection with WordPress nonces
- Prepared statements for database queries
- Capability checks for administrative functions
- Internationalization ready

## Support

For support and documentation, visit the plugin settings page in your WordPress admin area.

## License

This plugin is licensed under the GPL v2 or later.

---

**Version:** 1.0.0
**Tested up to:** WordPress 6.4
**Requires PHP:** 7.4+