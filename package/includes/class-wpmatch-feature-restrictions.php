<?php
/**
 * WPMatch Feature Restrictions
 *
 * Handles feature restrictions based on membership levels.
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
 * Implements feature restrictions and access control.
 */
class WPMatch_Feature_Restrictions {

	/**
	 * Initialize feature restrictions.
	 */
	public static function init() {
		// Restrict swipe actions.
		add_filter( 'wpmatch_can_perform_swipe', array( __CLASS__, 'restrict_swipe_actions' ), 10, 3 );

		// Restrict super likes.
		add_filter( 'wpmatch_can_use_super_like', array( __CLASS__, 'restrict_super_likes' ), 10, 2 );

		// Restrict messaging.
		add_filter( 'wpmatch_can_send_message', array( __CLASS__, 'restrict_messaging' ), 10, 3 );

		// Restrict profile visibility.
		add_filter( 'wpmatch_can_see_who_liked', array( __CLASS__, 'restrict_who_liked_visibility' ), 10, 2 );

		// Restrict advanced search.
		add_filter( 'wpmatch_can_use_advanced_search', array( __CLASS__, 'restrict_advanced_search' ), 10, 2 );

		// Restrict profile boost.
		add_filter( 'wpmatch_can_boost_profile', array( __CLASS__, 'restrict_profile_boost' ), 10, 2 );
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
			'super_likes'        => __( 'Super Likes are available with Gold and Platinum memberships.', 'wpmatch' ),
			'see_who_liked_you'  => __( 'See who liked you is available with Basic, Gold, and Platinum memberships.', 'wpmatch' ),
			'advanced_filters'   => __( 'Advanced search filters are available with Platinum membership.', 'wpmatch' ),
			'boost_profile'      => __( 'Profile boost is available with Gold and Platinum memberships.', 'wpmatch' ),
			'unlimited_likes'    => __( 'Unlimited likes are available with premium memberships.', 'wpmatch' ),
			'priority_support'   => __( 'Priority support is available with Platinum membership.', 'wpmatch' ),
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
			'has_access' => $has_access,
			'message'    => '',
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