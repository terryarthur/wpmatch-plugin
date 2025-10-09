<?php
/**
 * WPMatch Feature Restrictions
 *
 * Handles admin-level feature restriction enforcement based on Freemius licensing.
 *
 * @package WPMatch
 * @since   1.0.0
 */

// Security check.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPMatch Feature Restrictions Class
 *
 * Manages admin-level feature access control based on Freemius licenses.
 */
class WPMatch_Feature_Restrictions {

	/**
	 * Premium features and their requirements.
	 *
	 * @var array
	 */
	private static $premium_features = array(
		'video_chat'          => array(
			'freemius_id'   => 'video_chat',
			'name'          => 'Video Chat System',
			'description'   => 'WebRTC-powered video calling',
			'required_plan' => 'pro',
		),
		'ai_matching'         => array(
			'freemius_id'   => 'ai_matching',
			'name'          => 'AI Matching Engine',
			'description'   => 'Machine learning compatibility matching',
			'required_plan' => 'pro',
		),
		'advanced_analytics'  => array(
			'freemius_id'   => 'advanced_analytics',
			'name'          => 'Advanced Analytics',
			'description'   => 'Detailed reporting and insights',
			'required_plan' => 'basic',
		),
		'subscription_system' => array(
			'freemius_id'   => 'subscription_system',
			'name'          => 'Lightweight Subscriptions',
			'description'   => 'Alternative to WooCommerce',
			'required_plan' => 'basic',
		),
		'payment_stripe'      => array(
			'freemius_id'   => 'payment_stripe',
			'name'          => 'Stripe Payment Gateway',
			'description'   => 'Accept payments via Stripe',
			'required_plan' => 'basic',
		),
		'payment_paypal'      => array(
			'freemius_id'   => 'payment_paypal',
			'name'          => 'PayPal Payment Gateway',
			'description'   => 'Accept payments via PayPal',
			'required_plan' => 'basic',
		),
		'mobile_api'          => array(
			'freemius_id'   => 'mobile_api',
			'name'          => 'Mobile App API',
			'description'   => 'REST API for mobile apps',
			'required_plan' => 'pro',
		),
	);

	/**
	 * Initialize the feature restriction system.
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'show_upgrade_notices' ) );
		add_filter( 'wpmatch_feature_enabled', array( __CLASS__, 'check_feature_access' ), 10, 2 );
		add_action( 'wp_ajax_wpmatch_get_feature_info', array( __CLASS__, 'ajax_get_feature_info' ) );
		add_action( 'wp_ajax_wpmatch_dismiss_upgrade_notice', array( __CLASS__, 'ajax_dismiss_upgrade_notice' ) );

		// User-level restrictions (preserved from original).
		add_filter( 'wpmatch_can_perform_swipe', array( __CLASS__, 'restrict_swipe_actions' ), 10, 3 );
		add_filter( 'wpmatch_can_use_super_like', array( __CLASS__, 'restrict_super_likes' ), 10, 2 );
		add_filter( 'wpmatch_can_send_message', array( __CLASS__, 'restrict_messaging' ), 10, 3 );
	}

	/**
	 * Check if a premium feature is available.
	 *
	 * @param string $feature_slug Feature identifier.
	 * @return bool Whether feature is accessible.
	 */
	public static function is_feature_enabled( $feature_slug ) {
		// Free features are always enabled.
		if ( ! isset( self::$premium_features[ $feature_slug ] ) ) {
			return true;
		}

		// Check Freemius license.
		global $wpmatch_fs;

		if ( ! isset( $wpmatch_fs ) || ! $wpmatch_fs->is_registered() ) {
			return false;
		}

		$feature = self::$premium_features[ $feature_slug ];

		// Check if user has required plan.
		if ( ! $wpmatch_fs->is_plan_or_trial( $feature['required_plan'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Filter hook for feature access checking.
	 *
	 * @param bool   $enabled Current enabled status.
	 * @param string $feature_slug Feature identifier.
	 * @return bool Whether feature is enabled.
	 */
	public static function check_feature_access( $enabled, $feature_slug ) {
		return self::is_feature_enabled( $feature_slug );
	}

	/**
	 * Get premium feature information.
	 *
	 * @param string $feature_slug Feature identifier.
	 * @return array|false Feature data or false if not found.
	 */
	public static function get_feature_info( $feature_slug ) {
		if ( ! isset( self::$premium_features[ $feature_slug ] ) ) {
			return false;
		}

		$feature = self::$premium_features[ $feature_slug ];
		$feature['enabled'] = self::is_feature_enabled( $feature_slug );
		$feature['slug'] = $feature_slug;

		return $feature;
	}

	/**
	 * Show upgrade notices for restricted features.
	 */
	public static function show_upgrade_notices() {
		global $wpmatch_fs;

		// Only show on WPMatch admin pages.
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'wpmatch' ) === false ) {
			return;
		}

		// Don't show if already have pro plan.
		if ( isset( $wpmatch_fs ) && $wpmatch_fs->is_plan_or_trial( 'pro' ) ) {
			return;
		}

		// Check if user recently dismissed notice.
		if ( get_transient( 'wpmatch_upgrade_notice_dismissed' ) ) {
			return;
		}

		?>
		<div class="notice notice-info is-dismissible wpmatch-upgrade-notice">
			<p>
				<strong><?php esc_html_e( 'Unlock Premium Features!', 'wpmatch' ); ?></strong>
				<?php esc_html_e( 'Get advanced analytics, video chat, AI matching, and more with WPMatch Pro.', 'wpmatch' ); ?>
				<a href="<?php echo esc_url( isset( $wpmatch_fs ) ? $wpmatch_fs->get_upgrade_url() : admin_url( 'admin.php?page=wpmatch-pricing' ) ); ?>" class="button button-primary" style="margin-left: 10px;">
					<?php esc_html_e( 'Upgrade Now', 'wpmatch' ); ?>
				</a>
			</p>
		</div>
		<script>
		jQuery(document).on('click', '.wpmatch-upgrade-notice .notice-dismiss', function() {
			jQuery.post(ajaxurl, {
				action: 'wpmatch_dismiss_upgrade_notice',
				nonce: '<?php echo esc_js( wp_create_nonce( 'wpmatch_dismiss_notice' ) ); ?>'
			});
		});
		</script>
		<?php
	}

	/**
	 * AJAX handler for dismissing upgrade notice.
	 */
	public static function ajax_dismiss_upgrade_notice() {
		check_ajax_referer( 'wpmatch_dismiss_notice', 'nonce' );

		set_transient( 'wpmatch_upgrade_notice_dismissed', true, DAY_IN_SECONDS * 7 );

		wp_send_json_success();
	}

	/**
	 * Create feature restriction notice HTML.
	 *
	 * @param string $feature_slug Feature identifier.
	 * @param string $context Where the notice is shown.
	 * @return string HTML content.
	 */
	public static function get_restriction_notice( $feature_slug, $context = 'general' ) {
		$feature = self::get_feature_info( $feature_slug );

		if ( ! $feature ) {
			return '';
		}

		global $wpmatch_fs;
		$upgrade_url = isset( $wpmatch_fs ) ? $wpmatch_fs->get_upgrade_url() : admin_url( 'admin.php?page=wpmatch-pricing' );

		ob_start();
		?>
		<div class="wpmatch-feature-restricted">
			<div class="wpmatch-restriction-notice">
				<h3><?php echo esc_html( $feature['name'] ); ?> <span class="wpmatch-pro-badge"><?php esc_html_e( 'PRO', 'wpmatch' ); ?></span></h3>
				<p><?php echo esc_html( $feature['description'] ); ?></p>
				<p class="wpmatch-restriction-message">
					<?php esc_html_e( 'This premium feature requires an active license.', 'wpmatch' ); ?>
				</p>
				<a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-primary wpmatch-upgrade-btn">
					<?php esc_html_e( 'Upgrade to Pro', 'wpmatch' ); ?>
				</a>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Restrict swipe actions based on membership.
	 *
	 * @param bool   $can_swipe Whether user can swipe.
	 * @param int    $user_id User ID.
	 * @param string $swipe_type Swipe type.
	 * @return bool|WP_Error
	 */
	public static function restrict_swipe_actions( $can_swipe, $user_id, $swipe_type ) {
		$user_id = absint( $user_id );

		// Free users get limited daily swipes.
		if ( ! WPMatch_Membership_Manager::has_active_premium_membership( $user_id ) ) {
			$daily_swipes = WPMatch_Membership_Manager::get_user_daily_swipe_count( $user_id );
			$free_limit   = 50; // Free daily limit.

			if ( $daily_swipes >= $free_limit ) {
				return new WP_Error(
					'swipe_limit_reached',
					__( 'Daily swipe limit reached. Upgrade to premium for unlimited swipes!', 'wpmatch' )
				);
			}
		}

		return $can_swipe;
	}

	/**
	 * Restrict super likes.
	 *
	 * @param bool $can_super_like Whether user can use super likes.
	 * @param int  $user_id User ID.
	 * @return bool|WP_Error
	 */
	public static function restrict_super_likes( $can_super_like, $user_id ) {
		$user_id = absint( $user_id );

		// Check if user has super like feature access.
		if ( ! WPMatch_Membership_Manager::user_can_access_feature( $user_id, 'super_likes' ) ) {
			return new WP_Error(
				'feature_restricted',
				__( 'Super Likes are a premium feature. Upgrade your membership!', 'wpmatch' )
			);
		}

		// Check super like count/limits.
		$super_likes_remaining = WPMatch_Membership_Manager::get_user_super_likes_remaining( $user_id );

		if ( $super_likes_remaining <= 0 ) {
			return new WP_Error(
				'super_likes_depleted',
				__( 'No Super Likes remaining. Purchase more or wait for renewal!', 'wpmatch' )
			);
		}

		return $can_super_like;
	}

	/**
	 * Restrict messaging features.
	 *
	 * @param bool $can_message Whether user can send messages.
	 * @param int  $user_id User ID.
	 * @param int  $recipient_id Recipient ID.
	 * @return bool|WP_Error
	 */
	public static function restrict_messaging( $can_message, $user_id, $recipient_id ) {
		$user_id = absint( $user_id );

		// Free users might have message limits.
		if ( ! WPMatch_Membership_Manager::has_active_premium_membership( $user_id ) ) {
			$daily_messages     = WPMatch_Membership_Manager::get_user_daily_message_count( $user_id );
			$free_message_limit = 10;

			if ( $daily_messages >= $free_message_limit ) {
				return new WP_Error(
					'message_limit_reached',
					__( 'Daily message limit reached. Upgrade to premium for unlimited messaging!', 'wpmatch' )
				);
			}
		}

		return $can_message;
	}

	/**
	 * Restrict "who liked you" visibility.
	 *
	 * @param bool $can_see Whether user can see who liked them.
	 * @param int  $user_id User ID.
	 * @return bool
	 */
	public static function restrict_who_liked_visibility( $can_see, $user_id ) {
		return WPMatch_Membership_Manager::user_can_access_feature( $user_id, 'see_who_liked_you' );
	}

	/**
	 * Restrict advanced search features.
	 *
	 * @param bool $can_use_advanced Whether user can use advanced search.
	 * @param int  $user_id User ID.
	 * @return bool
	 */
	public static function restrict_advanced_search( $can_use_advanced, $user_id ) {
		return WPMatch_Membership_Manager::user_can_access_feature( $user_id, 'advanced_filters' );
	}

	/**
	 * Restrict profile boost feature.
	 *
	 * @param bool $can_boost Whether user can boost profile.
	 * @param int  $user_id User ID.
	 * @return bool
	 */
	public static function restrict_profile_boost( $can_boost, $user_id ) {
		return WPMatch_Membership_Manager::user_can_access_feature( $user_id, 'boost_profile' );
	}

	/**
	 * Get restriction message for feature.
	 *
	 * @param string $feature Feature name.
	 * @return string
	 */
	public static function get_restriction_message( $feature ) {
		$messages = array(
			'super_likes'       => __( 'Super Likes are available with Gold and Platinum memberships.', 'wpmatch' ),
			'see_who_liked_you' => __( 'See who liked you is available with Basic, Gold, and Platinum memberships.', 'wpmatch' ),
			'advanced_filters'  => __( 'Advanced search filters are available with Platinum membership.', 'wpmatch' ),
			'boost_profile'     => __( 'Profile boost is available with Gold and Platinum memberships.', 'wpmatch' ),
			'unlimited_likes'   => __( 'Unlimited likes are available with premium memberships.', 'wpmatch' ),
			'priority_support'  => __( 'Priority support is available with Platinum membership.', 'wpmatch' ),
		);

		return isset( $messages[ $feature ] ) ? $messages[ $feature ] : __( 'This feature requires a premium membership.', 'wpmatch' );
	}

	/**
	 * Check if user can access premium feature with upgrade prompt.
	 *
	 * @param int    $user_id User ID.
	 * @param string $feature Feature name.
	 * @return array
	 */
	public static function check_feature_access_with_prompt( $user_id, $feature ) {
		$has_access = WPMatch_Membership_Manager::user_can_access_feature( $user_id, $feature );

		$result = array(
			'has_access'  => $has_access,
			'message'     => '',
			'upgrade_url' => '',
		);

		if ( ! $has_access ) {
			$result['message'] = self::get_restriction_message( $feature );

			// Get appropriate upgrade product.
			$upgrade_product = self::get_upgrade_product_for_feature( $feature );
			if ( $upgrade_product ) {
				$result['upgrade_url'] = get_permalink( $upgrade_product->get_id() );
			}
		}

		return $result;
	}

	/**
	 * Get appropriate upgrade product for feature.
	 *
	 * @param string $feature Feature name.
	 * @return WC_Product|null
	 */
	private static function get_upgrade_product_for_feature( $feature ) {
		$feature_product_map = array(
			'see_who_liked_you' => 'wpmatch_basic_premium',
			'super_likes'       => 'wpmatch_gold_premium',
			'boost_profile'     => 'wpmatch_gold_premium',
			'advanced_filters'  => 'wpmatch_platinum_premium',
			'priority_support'  => 'wpmatch_platinum_premium',
		);

		$product_type = isset( $feature_product_map[ $feature ] ) ? $feature_product_map[ $feature ] : 'wpmatch_basic_premium';

		return WPMatch_WooCommerce_Integration::get_membership_product( $product_type );
	}

	/**
	 * Display upgrade notice for restricted feature.
	 *
	 * @param string $feature Feature name.
	 * @param int    $user_id User ID.
	 */
	public static function display_upgrade_notice( $feature, $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$access_info = self::check_feature_access_with_prompt( $user_id, $feature );

		if ( ! $access_info['has_access'] ) {
			echo '<div class="wpmatch-upgrade-notice">';
			echo '<p>' . esc_html( $access_info['message'] ) . '</p>';
			if ( $access_info['upgrade_url'] ) {
				echo '<a href="' . esc_url( $access_info['upgrade_url'] ) . '" class="wpmatch-button primary">';
				esc_html_e( 'Upgrade Now', 'wpmatch' );
				echo '</a>';
			}
			echo '</div>';
		}
	}
}
