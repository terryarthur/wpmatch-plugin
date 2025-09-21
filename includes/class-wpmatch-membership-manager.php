<?php
/**
 * WPMatch Membership Manager
 *
 * Handles user membership management and access control.
 *
 * @package WPMatch
 * @since   1.0.0
 */

// Security check.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPMatch Membership Manager Class
 *
 * Manages user memberships and feature access.
 */
class WPMatch_Membership_Manager {

	/**
	 * Check if user has purchased a specific product.
	 *
	 * @param int $user_id User ID.
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public static function has_user_purchased_product( $user_id, $product_id ) {
		if ( ! function_exists( 'wc_customer_bought_product' ) ) {
			return false;
		}

		$user_id    = absint( $user_id );
		$product_id = absint( $product_id );

		if ( ! $user_id || ! $product_id ) {
			return false;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}

		return wc_customer_bought_product( $user->user_email, $user_id, $product_id );
	}

	/**
	 * Check if user has active premium membership.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function has_active_premium_membership( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}

		$membership_data = get_user_meta( $user_id, '_wpmatch_membership_data', true );

		if ( empty( $membership_data ) || ! isset( $membership_data['status'] ) ) {
			return false;
		}

		if ( 'active' !== $membership_data['status'] ) {
			return false;
		}

		$expiry_date = isset( $membership_data['expiry_date'] ) ? $membership_data['expiry_date'] : '';

		if ( empty( $expiry_date ) ) {
			return false;
		}

		return strtotime( $expiry_date ) > current_time( 'timestamp' );
	}

	/**
	 * Get user's membership level.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function get_user_membership_level( $user_id ) {
		if ( ! self::has_active_premium_membership( $user_id ) ) {
			return 'free';
		}

		$membership_data = get_user_meta( $user_id, '_wpmatch_membership_data', true );
		return isset( $membership_data['level'] ) ? $membership_data['level'] : 'free';
	}

	/**
	 * Check if user has specific feature access.
	 *
	 * @param int    $user_id User ID.
	 * @param string $feature Feature name.
	 * @return bool
	 */
	public static function user_can_access_feature( $user_id, $feature ) {
		$user_id = absint( $user_id );
		$feature = sanitize_text_field( $feature );

		if ( ! $user_id || ! $feature ) {
			return false;
		}

		// Free features available to all users.
		$free_features = array( 'basic_swipe', 'basic_messaging', 'profile_creation' );

		if ( in_array( $feature, $free_features, true ) ) {
			return true;
		}

		// Check premium membership features.
		$membership_level   = self::get_user_membership_level( $user_id );
		$membership_features = self::get_membership_features( $membership_level );

		if ( in_array( $feature, $membership_features, true ) ) {
			return true;
		}

		// Check individual feature purchases.
		return self::has_active_feature_purchase( $user_id, $feature );
	}

	/**
	 * Get features for membership level.
	 *
	 * @param string $level Membership level.
	 * @return array
	 */
	private static function get_membership_features( $level ) {
		$features_map = array(
			'free'     => array( 'basic_swipe', 'basic_messaging', 'profile_creation' ),
			'basic'    => array( 'basic_swipe', 'basic_messaging', 'profile_creation', 'unlimited_likes', 'see_who_liked_you' ),
			'gold'     => array( 'basic_swipe', 'basic_messaging', 'profile_creation', 'unlimited_likes', 'see_who_liked_you', 'super_likes', 'boost_profile' ),
			'platinum' => array( 'basic_swipe', 'basic_messaging', 'profile_creation', 'unlimited_likes', 'see_who_liked_you', 'super_likes', 'boost_profile', 'priority_support', 'advanced_filters' ),
		);

		return isset( $features_map[ $level ] ) ? $features_map[ $level ] : $features_map['free'];
	}

	/**
	 * Check individual feature purchases.
	 *
	 * @param int    $user_id User ID.
	 * @param string $feature Feature name.
	 * @return bool
	 */
	private static function has_active_feature_purchase( $user_id, $feature ) {
		$feature_purchases = get_user_meta( $user_id, '_wpmatch_feature_purchases', true );

		if ( empty( $feature_purchases ) || ! isset( $feature_purchases[ $feature ] ) ) {
			return false;
		}

		$feature_data = $feature_purchases[ $feature ];
		$expiry       = isset( $feature_data['expiry'] ) ? $feature_data['expiry'] : 0;

		return $expiry > current_time( 'timestamp' );
	}

	/**
	 * Get user's daily swipe count.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public static function get_user_daily_swipe_count( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return 0;
		}

		$table_name = $wpdb->prefix . 'wpmatch_swipes';
		$today      = current_time( 'Y-m-d' );

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d AND DATE(created_at) = %s",
				$user_id,
				$today
			)
		);

		return $count ? absint( $count ) : 0;
	}

	/**
	 * Get user's super likes remaining.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public static function get_user_super_likes_remaining( $user_id ) {
		$super_likes_data = get_user_meta( $user_id, '_wpmatch_super_likes_data', true );
		return isset( $super_likes_data['remaining'] ) ? absint( $super_likes_data['remaining'] ) : 0;
	}

	/**
	 * Get user's daily message count.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public static function get_user_daily_message_count( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return 0;
		}

		$table_name = $wpdb->prefix . 'wpmatch_messages';
		$today      = current_time( 'Y-m-d' );

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE sender_id = %d AND DATE(created_at) = %s",
				$user_id,
				$today
			)
		);

		return $count ? absint( $count ) : 0;
	}

	/**
	 * Activate premium membership for user.
	 *
	 * @param int    $user_id User ID.
	 * @param string $level Membership level.
	 * @param int    $duration Duration in days.
	 * @param int    $product_id Product ID.
	 * @return bool
	 */
	public static function activate_membership( $user_id, $level, $duration, $product_id ) {
		$user_id    = absint( $user_id );
		$level      = sanitize_text_field( $level );
		$duration   = absint( $duration );
		$product_id = absint( $product_id );

		if ( ! $user_id || ! $level || ! $duration ) {
			return false;
		}

		$expiry_date = gmdate( 'Y-m-d H:i:s', strtotime( "+{$duration} days" ) );

		$membership_data = array(
			'level'          => $level,
			'product_id'     => $product_id,
			'expiry_date'    => $expiry_date,
			'activated_date' => current_time( 'mysql' ),
			'status'         => 'active',
		);

		$result = update_user_meta( $user_id, '_wpmatch_membership_data', $membership_data );

		if ( $result ) {
			do_action( 'wpmatch_membership_activated', $user_id, $level, $expiry_date );
		}

		return $result;
	}

	/**
	 * Activate feature for user.
	 *
	 * @param int    $user_id User ID.
	 * @param string $feature_type Feature type.
	 * @param int    $product_id Product ID.
	 * @param int    $quantity Quantity (for consumable features).
	 * @param int    $duration Duration in hours (for time-based features).
	 * @return bool
	 */
	public static function activate_feature( $user_id, $feature_type, $product_id, $quantity = null, $duration = null ) {
		$user_id      = absint( $user_id );
		$feature_type = sanitize_text_field( $feature_type );
		$product_id   = absint( $product_id );

		if ( ! $user_id || ! $feature_type ) {
			return false;
		}

		$feature_purchases = get_user_meta( $user_id, '_wpmatch_feature_purchases', true );
		if ( ! is_array( $feature_purchases ) ) {
			$feature_purchases = array();
		}

		if ( $duration ) {
			// Time-based feature.
			$expiry = strtotime( "+{$duration} hours" );
			$feature_purchases[ $feature_type ] = array(
				'expiry'     => $expiry,
				'activated'  => current_time( 'timestamp' ),
				'product_id' => $product_id,
			);
		} elseif ( $quantity ) {
			// Quantity-based feature.
			$current_quantity = isset( $feature_purchases[ $feature_type ]['remaining'] )
				? $feature_purchases[ $feature_type ]['remaining'] : 0;

			$feature_purchases[ $feature_type ] = array(
				'remaining'      => $current_quantity + absint( $quantity ),
				'total_purchased' => absint( $quantity ),
				'product_id'     => $product_id,
				'last_purchase'  => current_time( 'timestamp' ),
			);
		}

		$result = update_user_meta( $user_id, '_wpmatch_feature_purchases', $feature_purchases );

		// Update specific feature data for quick access.
		if ( 'super_likes_pack' === $feature_type && $quantity ) {
			$super_likes_data = get_user_meta( $user_id, '_wpmatch_super_likes_data', true );
			if ( ! is_array( $super_likes_data ) ) {
				$super_likes_data = array();
			}
			$super_likes_data['remaining'] = ( $super_likes_data['remaining'] ?? 0 ) + absint( $quantity );
			update_user_meta( $user_id, '_wpmatch_super_likes_data', $super_likes_data );
		}

		if ( $result ) {
			do_action( 'wpmatch_feature_activated', $user_id, $feature_type, $product_id );
		}

		return $result;
	}

	/**
	 * Deactivate membership for user.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function deactivate_membership( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}

		$membership_data = get_user_meta( $user_id, '_wpmatch_membership_data', true );
		if ( ! is_array( $membership_data ) ) {
			return false;
		}

		$membership_data['status']          = 'cancelled';
		$membership_data['cancelled_date']  = current_time( 'mysql' );

		$result = update_user_meta( $user_id, '_wpmatch_membership_data', $membership_data );

		if ( $result ) {
			do_action( 'wpmatch_membership_deactivated', $user_id );
		}

		return $result;
	}
}