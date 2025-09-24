<?php
/**
 * WPMatch Freemius Integration
 *
 * Handles Freemius SDK integration for premium feature licensing.
 *
 * @package WPMatch
 * @since   1.0.0
 */

// Security check.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPMatch Freemius Integration Class
 *
 * Manages Freemius licensing and premium feature activation.
 */
class WPMatch_Freemius_Integration {

	/**
	 * Freemius instance.
	 *
	 * @var object
	 */
	private static $freemius;

	/**
	 * Plugin configuration for Freemius.
	 *
	 * @var array
	 */
	private static $config = array(
		'id'                  => 0, // Replace with actual Freemius plugin ID.
		'slug'                => 'wpmatch',
		'type'                => 'plugin',
		'public_key'          => '', // Replace with actual public key.
		'is_premium'          => false,
		'premium_suffix'      => 'Pro',
		'has_addons'          => true,
		'has_paid_plans'      => true,
		'menu'                => array(
			'slug'       => 'wpmatch-admin',
			'override_exact' => true,
			'contact'    => false,
			'support'    => false,
		),
	);

	/**
	 * Available premium addons.
	 *
	 * @var array
	 */
	private static $addons = array(
		'subscription_system' => array(
			'id'         => 0, // Replace with actual addon ID.
			'slug'       => 'wpmatch-subscriptions',
			'public_key' => '', // Replace with actual public key.
			'title'      => 'Lightweight Subscription System',
		),
		'payment_stripe'      => array(
			'id'         => 0,
			'slug'       => 'wpmatch-stripe',
			'public_key' => '',
			'title'      => 'Stripe Payment Gateway',
		),
		'payment_paypal'      => array(
			'id'         => 0,
			'slug'       => 'wpmatch-paypal',
			'public_key' => '',
			'title'      => 'PayPal Payment Gateway',
		),
		'advanced_analytics'  => array(
			'id'         => 0,
			'slug'       => 'wpmatch-analytics',
			'public_key' => '',
			'title'      => 'Advanced Analytics',
		),
		'video_chat'          => array(
			'id'         => 0,
			'slug'       => 'wpmatch-video-chat',
			'public_key' => '',
			'title'      => 'Video Chat System',
		),
		'ai_matching'         => array(
			'id'         => 0,
			'slug'       => 'wpmatch-ai-matching',
			'public_key' => '',
			'title'      => 'AI Matching Engine',
		),
		'mobile_api'          => array(
			'id'         => 0,
			'slug'       => 'wpmatch-mobile-api',
			'public_key' => '',
			'title'      => 'Mobile App API',
		),
	);

	/**
	 * Initialize Freemius integration.
	 */
	public static function init() {
		// Only initialize if Freemius SDK is available.
		if ( ! class_exists( 'Freemius' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'freemius_missing_notice' ) );
			return;
		}

		// Initialize main plugin with Freemius.
		self::init_freemius();

		// Initialize addon integrations.
		self::init_addons();

		// Hook into license activation/deactivation.
		add_action( 'fs_after_license_activation', array( __CLASS__, 'handle_license_activation' ), 10, 2 );
		add_action( 'fs_after_license_deactivation', array( __CLASS__, 'handle_license_deactivation' ), 10, 2 );
	}

	/**
	 * Initialize main Freemius instance.
	 */
	private static function init_freemius() {
		global $wpmatch_fs;

		if ( ! isset( $wpmatch_fs ) ) {
			// Include Freemius SDK (this would be in a separate directory).
			require_once WPMATCH_PLUGIN_DIR . 'freemius/start.php';

			$wpmatch_fs = fs_dynamic_init( self::$config );
		}

		self::$freemius = $wpmatch_fs;

		// Add custom license management hooks.
		self::$freemius->add_filter( 'connect_message', array( __CLASS__, 'custom_connect_message' ) );
		self::$freemius->add_filter( 'show_admin_notice', array( __CLASS__, 'filter_admin_notices' ), 10, 2 );
	}

	/**
	 * Initialize premium addons.
	 */
	private static function init_addons() {
		foreach ( self::$addons as $addon_slug => $addon_config ) {
			// Check if addon is active/licensed.
			if ( self::is_addon_licensed( $addon_slug ) ) {
				self::activate_addon_features( $addon_slug );
			}
		}
	}

	/**
	 * Check if specific addon is licensed.
	 *
	 * @param string $addon_slug Addon identifier.
	 * @return bool
	 */
	public static function is_addon_licensed( $addon_slug ) {
		if ( ! self::$freemius ) {
			return false;
		}

		// Check if this is a pro bundle license (includes all addons).
		if ( self::$freemius->is_plan( 'pro' ) ) {
			return true;
		}

		// Check individual addon license.
		$addon_config = self::$addons[ $addon_slug ] ?? null;
		if ( ! $addon_config ) {
			return false;
		}

		// This would check the specific addon license.
		// For now, return false until actual Freemius integration is complete.
		return false;
	}

	/**
	 * Activate features for a licensed addon.
	 *
	 * @param string $addon_slug Addon identifier.
	 */
	private static function activate_addon_features( $addon_slug ) {
		$addon_config = self::$addons[ $addon_slug ] ?? null;
		if ( ! $addon_config ) {
			return;
		}

		// Add license to admin license manager.
		WPMatch_Admin_License_Manager::add_feature_license(
			$addon_slug,
			'freemius_' . $addon_slug,
			null, // Freemius handles expiration.
			array(
				'source' => 'freemius',
				'addon_id' => $addon_config['id'],
			)
		);

		// Trigger addon-specific activation.
		do_action( 'wpmatch_addon_activated', $addon_slug, $addon_config );

		// Load addon-specific classes.
		self::load_addon_classes( $addon_slug );
	}

	/**
	 * Load classes for activated addon.
	 *
	 * @param string $addon_slug Addon identifier.
	 */
	private static function load_addon_classes( $addon_slug ) {
		switch ( $addon_slug ) {
			case 'subscription_system':
				if ( ! class_exists( 'WPMatch_Lightweight_Subscriptions' ) ) {
					require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-lightweight-subscriptions.php';
				}
				break;

			case 'payment_stripe':
				if ( ! class_exists( 'WPMatch_Stripe_Payment_Gateway' ) ) {
					require_once WPMATCH_PLUGIN_DIR . 'includes/gateways/class-wpmatch-stripe-payment-gateway.php';
				}
				break;

			case 'payment_paypal':
				if ( ! class_exists( 'WPMatch_PayPal_Payment_Gateway' ) ) {
					require_once WPMATCH_PLUGIN_DIR . 'includes/gateways/class-wpmatch-paypal-payment-gateway.php';
				}
				break;

			// Add other addon loading logic here.
		}
	}

	/**
	 * Handle license activation.
	 *
	 * @param object $license License object.
	 * @param object $fs Freemius instance.
	 */
	public static function handle_license_activation( $license, $fs ) {
		// Determine which addon was activated.
		$addon_slug = self::get_addon_slug_from_fs_instance( $fs );

		if ( $addon_slug ) {
			self::activate_addon_features( $addon_slug );

			// Show success notice.
			add_action( 'admin_notices', function() use ( $addon_slug ) {
				$addon_config = self::$addons[ $addon_slug ];
				echo '<div class="notice notice-success is-dismissible">';
				echo '<p>' . sprintf(
					/* translators: %s: Addon title */
					esc_html__( '%s has been activated successfully!', 'wpmatch' ),
					esc_html( $addon_config['title'] )
				) . '</p>';
				echo '</div>';
			} );
		}
	}

	/**
	 * Handle license deactivation.
	 *
	 * @param object $license License object.
	 * @param object $fs Freemius instance.
	 */
	public static function handle_license_deactivation( $license, $fs ) {
		// Determine which addon was deactivated.
		$addon_slug = self::get_addon_slug_from_fs_instance( $fs );

		if ( $addon_slug ) {
			// Remove from admin license manager.
			// This would need to be implemented in the license manager.

			// Trigger addon-specific deactivation.
			do_action( 'wpmatch_addon_deactivated', $addon_slug );
		}
	}

	/**
	 * Get addon slug from Freemius instance.
	 *
	 * @param object $fs Freemius instance.
	 * @return string|false
	 */
	private static function get_addon_slug_from_fs_instance( $fs ) {
		$fs_slug = $fs->get_slug();

		foreach ( self::$addons as $addon_slug => $addon_config ) {
			if ( $addon_config['slug'] === $fs_slug ) {
				return $addon_slug;
			}
		}

		return false;
	}

	/**
	 * Get Freemius purchase URL for addon.
	 *
	 * @param string $addon_slug Addon identifier.
	 * @return string
	 */
	public static function get_purchase_url( $addon_slug ) {
		if ( ! self::$freemius ) {
			return '#';
		}

		$addon_config = self::$addons[ $addon_slug ] ?? null;
		if ( ! $addon_config ) {
			return '#';
		}

		// Generate Freemius purchase URL.
		return self::$freemius->get_upgrade_url();
	}

	/**
	 * Get Pro bundle purchase URL.
	 *
	 * @return string
	 */
	public static function get_pro_bundle_url() {
		if ( ! self::$freemius ) {
			return '#';
		}

		return self::$freemius->get_upgrade_url( 'pro' );
	}

	/**
	 * Custom connect message for Freemius opt-in.
	 *
	 * @return string
	 */
	public static function custom_connect_message() {
		return esc_html__(
			'Never miss an important update - opt-in to our security and feature updates notifications, and non-sensitive diagnostic tracking with Freemius.',
			'wpmatch'
		);
	}

	/**
	 * Filter Freemius admin notices.
	 *
	 * @param bool  $show Whether to show notice.
	 * @param array $msg Notice data.
	 * @return bool
	 */
	public static function filter_admin_notices( $show, $msg ) {
		// Hide certain Freemius notices to reduce noise.
		$hidden_notices = array( 'trial_promotion', 'discount_promotion' );

		if ( isset( $msg['id'] ) && in_array( $msg['id'], $hidden_notices, true ) ) {
			return false;
		}

		return $show;
	}

	/**
	 * Show notice when Freemius SDK is missing.
	 */
	public static function freemius_missing_notice() {
		echo '<div class="notice notice-warning">';
		echo '<p>' . esc_html__(
			'WPMatch: Freemius SDK is not available. Premium features will not function until the SDK is properly installed.',
			'wpmatch'
		) . '</p>';
		echo '</div>';
	}

	/**
	 * Get Freemius instance.
	 *
	 * @return object|null
	 */
	public static function get_freemius() {
		return self::$freemius;
	}

	/**
	 * Check if user has access to premium features.
	 *
	 * @return bool
	 */
	public static function has_premium_access() {
		if ( ! self::$freemius ) {
			return false;
		}

		return self::$freemius->is_premium() || self::$freemius->is_trial();
	}

	/**
	 * Get current plan information.
	 *
	 * @return array
	 */
	public static function get_plan_info() {
		if ( ! self::$freemius || ! self::has_premium_access() ) {
			return array(
				'plan'    => 'free',
				'title'   => 'Free',
				'expires' => null,
			);
		}

		$plan = self::$freemius->get_plan();
		$license = self::$freemius->_get_license();

		return array(
			'plan'    => $plan->name ?? 'unknown',
			'title'   => $plan->title ?? 'Unknown Plan',
			'expires' => $license->expires ?? null,
		);
	}
}

// Initialize Freemius integration.
add_action( 'plugins_loaded', array( 'WPMatch_Freemius_Integration', 'init' ) );