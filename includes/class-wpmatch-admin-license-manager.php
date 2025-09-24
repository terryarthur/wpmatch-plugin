<?php
/**
 * WPMatch Admin License Manager
 *
 * Manages admin-level feature licensing and Freemius integration.
 *
 * @package WPMatch
 * @since   1.0.0
 */

// Security check.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPMatch Admin License Manager Class
 *
 * Handles admin feature licensing, Freemius integration, and feature distribution.
 */
class WPMatch_Admin_License_Manager {

	/**
	 * Available premium features and their details.
	 *
	 * @var array
	 */
	private static $available_features = array(
		'video_chat'        => array(
			'name'         => 'Video Chat System',
			'description'  => 'WebRTC-powered video calling for users',
			'freemius_id'  => 'video_chat',
			'price'        => 69,
			'dependencies' => array(),
		),
		'ai_matching'       => array(
			'name'         => 'AI Matching Engine',
			'description'  => 'Machine learning-powered compatibility matching',
			'freemius_id'  => 'ai_matching',
			'price'        => 59,
			'dependencies' => array( 'advanced_analytics' ),
		),
		'advanced_analytics' => array(
			'name'         => 'Advanced Analytics',
			'description'  => 'Detailed reporting and user insights',
			'freemius_id'  => 'advanced_analytics',
			'price'        => 39,
			'dependencies' => array(),
		),
		'subscription_system' => array(
			'name'         => 'Lightweight Subscription System',
			'description'  => 'WooCommerce-free subscription management',
			'freemius_id'  => 'subscription_system',
			'price'        => 49,
			'dependencies' => array(),
		),
		'payment_stripe'    => array(
			'name'         => 'Stripe Payment Gateway',
			'description'  => 'Accept payments via Stripe',
			'freemius_id'  => 'payment_stripe',
			'price'        => 29,
			'dependencies' => array( 'subscription_system' ),
		),
		'payment_paypal'    => array(
			'name'         => 'PayPal Payment Gateway',
			'description'  => 'Accept payments via PayPal',
			'freemius_id'  => 'payment_paypal',
			'price'        => 29,
			'dependencies' => array( 'subscription_system' ),
		),
		'mobile_api'        => array(
			'name'         => 'Mobile App API',
			'description'  => 'REST endpoints for native mobile apps',
			'freemius_id'  => 'mobile_api',
			'price'        => 79,
			'dependencies' => array(),
		),
	);

	/**
	 * Initialize the license manager.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'create_license_tables' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'wp_ajax_wpmatch_purchase_feature', array( __CLASS__, 'handle_feature_purchase' ) );
		add_action( 'wp_ajax_wpmatch_toggle_feature_assignment', array( __CLASS__, 'handle_feature_assignment' ) );
	}

	/**
	 * Create license management database tables.
	 */
	public static function create_license_tables() {
		global $wpdb;

		$admin_licenses_table = $wpdb->prefix . 'wpmatch_admin_licenses';
		$feature_assignments_table = $wpdb->prefix . 'wpmatch_feature_assignments';

		// Admin licenses table.
		$sql_licenses = "CREATE TABLE IF NOT EXISTS {$admin_licenses_table} (
			id int(11) NOT NULL AUTO_INCREMENT,
			feature_slug varchar(50) NOT NULL,
			license_key varchar(255) NOT NULL,
			status enum('active','inactive','expired') DEFAULT 'active',
			expires_at datetime DEFAULT NULL,
			freemius_data longtext,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY feature_slug (feature_slug),
			KEY status (status),
			KEY expires_at (expires_at)
		) {$wpdb->get_charset_collate()};";

		// Feature assignments table.
		$sql_assignments = "CREATE TABLE IF NOT EXISTS {$feature_assignments_table} (
			id int(11) NOT NULL AUTO_INCREMENT,
			feature_slug varchar(50) NOT NULL,
			user_tier varchar(20) NOT NULL,
			enabled tinyint(1) DEFAULT 0,
			settings longtext,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY feature_tier (feature_slug, user_tier),
			KEY feature_slug (feature_slug),
			KEY user_tier (user_tier)
		) {$wpdb->get_charset_collate()};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_licenses );
		dbDelta( $sql_assignments );
	}

	/**
	 * Check if admin has purchased a specific feature.
	 *
	 * @param string $feature_slug Feature identifier.
	 * @return bool
	 */
	public static function has_admin_feature( $feature_slug ) {
		global $wpdb;

		$feature_slug = sanitize_key( $feature_slug );
		if ( empty( $feature_slug ) ) {
			return false;
		}

		$table = $wpdb->prefix . 'wpmatch_admin_licenses';
		$license = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE feature_slug = %s AND status = 'active'",
				$feature_slug
			)
		);

		if ( ! $license ) {
			return false;
		}

		// Check if license has expired.
		if ( $license->expires_at && strtotime( $license->expires_at ) < current_time( 'timestamp' ) ) {
			// Update status to expired.
			$wpdb->update(
				$table,
				array( 'status' => 'expired' ),
				array( 'id' => $license->id ),
				array( '%s' ),
				array( '%d' )
			);
			return false;
		}

		return true;
	}

	/**
	 * Get all licensed admin features.
	 *
	 * @return array
	 */
	public static function get_admin_features() {
		global $wpdb;

		$table = $wpdb->prefix . 'wpmatch_admin_licenses';
		$licenses = $wpdb->get_results(
			"SELECT * FROM {$table} WHERE status = 'active' ORDER BY created_at DESC"
		);

		$features = array();
		foreach ( $licenses as $license ) {
			if ( isset( self::$available_features[ $license->feature_slug ] ) ) {
				$features[ $license->feature_slug ] = array_merge(
					self::$available_features[ $license->feature_slug ],
					array(
						'license_key' => $license->license_key,
						'expires_at'  => $license->expires_at,
						'status'      => $license->status,
					)
				);
			}
		}

		return $features;
	}

	/**
	 * Get available features for purchase.
	 *
	 * @return array
	 */
	public static function get_available_features() {
		return self::$available_features;
	}

	/**
	 * Add feature license to database.
	 *
	 * @param string $feature_slug Feature identifier.
	 * @param string $license_key License key from Freemius.
	 * @param string $expires_at Expiration date.
	 * @param array  $freemius_data Additional Freemius data.
	 * @return bool
	 */
	public static function add_feature_license( $feature_slug, $license_key, $expires_at = null, $freemius_data = array() ) {
		global $wpdb;

		$feature_slug = sanitize_key( $feature_slug );
		$license_key = sanitize_text_field( $license_key );

		if ( empty( $feature_slug ) || empty( $license_key ) ) {
			return false;
		}

		$table = $wpdb->prefix . 'wpmatch_admin_licenses';
		$result = $wpdb->replace(
			$table,
			array(
				'feature_slug'  => $feature_slug,
				'license_key'   => $license_key,
				'status'        => 'active',
				'expires_at'    => $expires_at,
				'freemius_data' => maybe_serialize( $freemius_data ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Check if user has access to a specific feature based on admin settings.
	 *
	 * @param int    $user_id User ID.
	 * @param string $feature_slug Feature identifier.
	 * @return bool
	 */
	public static function user_has_feature_access( $user_id, $feature_slug ) {
		// First check if admin has the feature.
		if ( ! self::has_admin_feature( $feature_slug ) ) {
			return false;
		}

		// Get user's membership tier.
		$user_tier = WPMatch_Membership_Manager::get_user_membership_level( $user_id );

		// Check if feature is enabled for this user tier.
		return self::is_feature_enabled_for_tier( $feature_slug, $user_tier );
	}

	/**
	 * Check if feature is enabled for a specific user tier.
	 *
	 * @param string $feature_slug Feature identifier.
	 * @param string $user_tier User tier (free, premium, vip, etc.).
	 * @return bool
	 */
	public static function is_feature_enabled_for_tier( $feature_slug, $user_tier ) {
		global $wpdb;

		$feature_slug = sanitize_key( $feature_slug );
		$user_tier = sanitize_key( $user_tier );

		if ( empty( $feature_slug ) || empty( $user_tier ) ) {
			return false;
		}

		$table = $wpdb->prefix . 'wpmatch_feature_assignments';
		$assignment = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT enabled FROM {$table} WHERE feature_slug = %s AND user_tier = %s",
				$feature_slug,
				$user_tier
			)
		);

		return (bool) $assignment;
	}

	/**
	 * Set feature assignment for user tier.
	 *
	 * @param string $feature_slug Feature identifier.
	 * @param string $user_tier User tier.
	 * @param bool   $enabled Whether feature is enabled.
	 * @param array  $settings Additional settings.
	 * @return bool
	 */
	public static function set_feature_assignment( $feature_slug, $user_tier, $enabled, $settings = array() ) {
		global $wpdb;

		$feature_slug = sanitize_key( $feature_slug );
		$user_tier = sanitize_key( $user_tier );

		if ( empty( $feature_slug ) || empty( $user_tier ) ) {
			return false;
		}

		$table = $wpdb->prefix . 'wpmatch_feature_assignments';
		$result = $wpdb->replace(
			$table,
			array(
				'feature_slug' => $feature_slug,
				'user_tier'    => $user_tier,
				'enabled'      => $enabled ? 1 : 0,
				'settings'     => maybe_serialize( $settings ),
			),
			array( '%s', '%s', '%d', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Get feature matrix showing which features are enabled for which tiers.
	 *
	 * @return array
	 */
	public static function get_feature_matrix() {
		global $wpdb;

		$table = $wpdb->prefix . 'wpmatch_feature_assignments';
		$assignments = $wpdb->get_results(
			"SELECT feature_slug, user_tier, enabled, settings FROM {$table} ORDER BY feature_slug, user_tier"
		);

		$matrix = array();
		foreach ( $assignments as $assignment ) {
			if ( ! isset( $matrix[ $assignment->feature_slug ] ) ) {
				$matrix[ $assignment->feature_slug ] = array();
			}
			$matrix[ $assignment->feature_slug ][ $assignment->user_tier ] = array(
				'enabled'  => (bool) $assignment->enabled,
				'settings' => maybe_unserialize( $assignment->settings ),
			);
		}

		return $matrix;
	}

	/**
	 * Add admin menu for license management.
	 */
	public static function add_admin_menu() {
		add_submenu_page(
			'wpmatch-admin',
			esc_html__( 'Feature Marketplace', 'wpmatch' ),
			esc_html__( 'Marketplace', 'wpmatch' ),
			'manage_options',
			'wpmatch-marketplace',
			array( __CLASS__, 'render_marketplace_page' )
		);
	}

	/**
	 * Render the feature marketplace admin page.
	 */
	public static function render_marketplace_page() {
		$licensed_features = self::get_admin_features();
		$available_features = self::get_available_features();
		$feature_matrix = self::get_feature_matrix();

		include WPMATCH_PLUGIN_DIR . 'admin/partials/wpmatch-admin-marketplace.php';
	}

	/**
	 * Handle AJAX feature purchase request.
	 */
	public static function handle_feature_purchase() {
		check_ajax_referer( 'wpmatch_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wpmatch' ) );
		}

		$feature_slug = sanitize_key( $_POST['feature_slug'] ?? '' );

		if ( empty( $feature_slug ) || ! isset( self::$available_features[ $feature_slug ] ) ) {
			wp_send_json_error( esc_html__( 'Invalid feature.', 'wpmatch' ) );
		}

		$feature = self::$available_features[ $feature_slug ];

		// Generate Freemius purchase URL.
		$purchase_url = add_query_arg(
			array(
				'page'    => 'wpmatch-marketplace',
				'action'  => 'purchase',
				'feature' => $feature_slug,
			),
			admin_url( 'admin.php' )
		);

		wp_send_json_success(
			array(
				'message'      => sprintf(
					/* translators: %s: Feature name */
					esc_html__( 'Redirecting to purchase %s...', 'wpmatch' ),
					$feature['name']
				),
				'purchase_url' => $purchase_url,
			)
		);
	}

	/**
	 * Handle AJAX feature assignment toggle.
	 */
	public static function handle_feature_assignment() {
		check_ajax_referer( 'wpmatch_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wpmatch' ) );
		}

		$feature_slug = sanitize_key( $_POST['feature_slug'] ?? '' );
		$user_tier = sanitize_key( $_POST['user_tier'] ?? '' );
		$enabled = isset( $_POST['enabled'] ) ? (bool) $_POST['enabled'] : false;

		if ( empty( $feature_slug ) || empty( $user_tier ) ) {
			wp_send_json_error( esc_html__( 'Invalid parameters.', 'wpmatch' ) );
		}

		// Check if admin has this feature.
		if ( ! self::has_admin_feature( $feature_slug ) ) {
			wp_send_json_error( esc_html__( 'Feature not licensed.', 'wpmatch' ) );
		}

		$result = self::set_feature_assignment( $feature_slug, $user_tier, $enabled );

		if ( $result ) {
			wp_send_json_success(
				array(
					'message' => $enabled
						? esc_html__( 'Feature enabled for user tier.', 'wpmatch' )
						: esc_html__( 'Feature disabled for user tier.', 'wpmatch' ),
				)
			);
		} else {
			wp_send_json_error( esc_html__( 'Failed to update feature assignment.', 'wpmatch' ) );
		}
	}
}

// Initialize the license manager.
WPMatch_Admin_License_Manager::init();