<?php
/**
 * WPMatch Subscription Manager
 *
 * Handles subscription lifecycle, renewals, cancellations, and membership status.
 *
 * @package WPMatch
 * @since 1.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPMatch Subscription Manager class.
 *
 * Handles subscription lifecycle, renewals, cancellations, and membership status.
 *
 * @since 1.1.0
 */
class WPMatch_Subscription_Manager {

	/**
	 * Initialize subscription hooks.
	 *
	 * @since 1.1.0
	 */
	public static function init() {
		add_action( 'woocommerce_subscription_status_updated', array( __CLASS__, 'handle_subscription_status_change' ), 10, 3 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'handle_order_completed' ) );
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'handle_order_cancelled' ) );
		add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'handle_order_refunded' ) );

		// Hooks for "Subscriptions for WooCommerce" plugin.
		add_action( 'hf_subscription_status_updated', array( __CLASS__, 'handle_hf_subscription_status_change' ), 10, 3 );
		add_action( 'hf_subscription_payment_complete', array( __CLASS__, 'handle_hf_payment_complete' ) );

		// Daily cron for checking expired memberships.
		add_action( 'wpmatch_check_expired_memberships', array( __CLASS__, 'check_expired_memberships' ) );
		if ( ! wp_next_scheduled( 'wpmatch_check_expired_memberships' ) ) {
			wp_schedule_event( time(), 'daily', 'wpmatch_check_expired_memberships' );
		}

		// User membership management hooks.
		add_action( 'init', array( __CLASS__, 'register_membership_endpoints' ) );
		add_action( 'wp_loaded', array( __CLASS__, 'handle_membership_actions' ) );
	}

	/**
	 * Handle subscription status changes for WooCommerce Subscriptions.
	 *
	 * @param WC_Subscription $subscription The subscription object.
	 * @param string          $new_status The new status.
	 * @param string          $old_status The old status.
	 * @since 1.1.0
	 */
	public static function handle_subscription_status_change( $subscription, $new_status, $old_status ) {
		$user_id         = $subscription->get_user_id();
		$subscription_id = $subscription->get_id();

		// Get WPMatch membership level from subscription.
		$membership_level = self::get_membership_level_from_subscription( $subscription );

		if ( ! $membership_level ) {
			return; // Not a WPMatch subscription.
		}

		switch ( $new_status ) {
			case 'active':
				self::activate_user_membership( $user_id, $membership_level, $subscription_id );
				break;

			case 'on-hold':
			case 'pending-cancel':
				self::suspend_user_membership( $user_id, $membership_level );
				break;

			case 'cancelled':
			case 'expired':
				self::deactivate_user_membership( $user_id, $membership_level );
				break;

			case 'pending':
				// Wait for payment completion.
				break;
		}

		// Log subscription status change.
		self::log_membership_event(
			$user_id,
			'subscription_status_change',
			array(
				'subscription_id'  => $subscription_id,
				'old_status'       => $old_status,
				'new_status'       => $new_status,
				'membership_level' => $membership_level,
			)
		);
	}

	/**
	 * Handle subscription status changes for Subscriptions for WooCommerce.
	 *
	 * @param int    $subscription_id The subscription ID.
	 * @param string $new_status The new status.
	 * @param string $old_status The old status.
	 * @since 1.1.0
	 */
	public static function handle_hf_subscription_status_change( $subscription_id, $new_status, $old_status ) {
		$subscription = wcs_get_subscription( $subscription_id );
		if ( ! $subscription ) {
			return;
		}

		$user_id          = $subscription->get_user_id();
		$membership_level = self::get_membership_level_from_subscription( $subscription );

		if ( ! $membership_level ) {
			return;
		}

		switch ( $new_status ) {
			case 'active':
				self::activate_user_membership( $user_id, $membership_level, $subscription_id );
				break;

			case 'on-hold':
			case 'pending-cancel':
				self::suspend_user_membership( $user_id, $membership_level );
				break;

			case 'cancelled':
			case 'expired':
				self::deactivate_user_membership( $user_id, $membership_level );
				break;
		}
	}

	/**
	 * Handle completed orders.
	 *
	 * @param int $order_id The order ID.
	 * @since 1.1.0
	 */
	public static function handle_order_completed( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$user_id = $order->get_user_id();
		if ( ! $user_id ) {
			return;
		}

		// Check if order contains WPMatch membership products.
		foreach ( $order->get_items() as $item ) {
			$product_id       = $item->get_product_id();
			$membership_level = get_post_meta( $product_id, '_wpmatch_membership_level', true );

			if ( $membership_level ) {
				// Check if it's a subscription product.
				$is_subscription = get_post_meta( $product_id, '_wpmatch_is_subscription', true );

				if ( 'yes' !== $is_subscription ) {
					// One-time purchase - activate membership for limited time.
					$duration = apply_filters( 'wpmatch_one_time_membership_duration', 30 ); // 30 days default.
					self::activate_user_membership( $user_id, $membership_level, $order_id, $duration );
				}
			}
		}
	}

	/**
	 * Handle cancelled orders.
	 *
	 * @param int $order_id The order ID.
	 * @since 1.1.0
	 */
	public static function handle_order_cancelled( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$user_id = $order->get_user_id();
		if ( ! $user_id ) {
			return;
		}

		// Deactivate memberships from this order.
		foreach ( $order->get_items() as $item ) {
			$product_id       = $item->get_product_id();
			$membership_level = get_post_meta( $product_id, '_wpmatch_membership_level', true );

			if ( $membership_level ) {
				self::deactivate_user_membership( $user_id, $membership_level );
			}
		}
	}

	/**
	 * Handle refunded orders.
	 *
	 * @param int $order_id The order ID.
	 * @since 1.1.0
	 */
	public static function handle_order_refunded( $order_id ) {
		// Same logic as cancelled.
		self::handle_order_cancelled( $order_id );
	}

	/**
	 * Activate user membership.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $membership_level The membership level.
	 * @param int    $reference_id The subscription or order ID.
	 * @param int    $duration Duration in days (0 = unlimited).
	 * @since 1.1.0
	 */
	public static function activate_user_membership( $user_id, $membership_level, $reference_id = 0, $duration = 0 ) {
		// Get existing memberships.
		$memberships = get_user_meta( $user_id, '_wpmatch_memberships', true );
		if ( ! is_array( $memberships ) ) {
			$memberships = array();
		}

		// Calculate expiry date.
		$expires_at = 0;
		if ( $duration > 0 ) {
			$expires_at = time() + ( $duration * DAY_IN_SECONDS );
		}

		// Add or update membership.
		$memberships[ $membership_level ] = array(
			'status'         => 'active',
			'activated_at'   => time(),
			'expires_at'     => $expires_at,
			'reference_id'   => $reference_id,
			'reference_type' => $duration > 0 ? 'order' : 'subscription',
		);

		update_user_meta( $user_id, '_wpmatch_memberships', $memberships );
		update_user_meta( $user_id, '_wpmatch_current_membership', $membership_level );

		// Log activation.
		self::log_membership_event(
			$user_id,
			'membership_activated',
			array(
				'membership_level' => $membership_level,
				'reference_id'     => $reference_id,
				'expires_at'       => $expires_at,
			)
		);

		// Trigger action for other plugins.
		do_action( 'wpmatch_membership_activated', $user_id, $membership_level, $reference_id );
	}

	/**
	 * Suspend user membership.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $membership_level The membership level.
	 * @since 1.1.0
	 */
	public static function suspend_user_membership( $user_id, $membership_level ) {
		$memberships = get_user_meta( $user_id, '_wpmatch_memberships', true );
		if ( ! is_array( $memberships ) || ! isset( $memberships[ $membership_level ] ) ) {
			return;
		}

		$memberships[ $membership_level ]['status']       = 'suspended';
		$memberships[ $membership_level ]['suspended_at'] = time();

		update_user_meta( $user_id, '_wpmatch_memberships', $memberships );

		// If this was the current membership, downgrade to free.
		$current_membership = get_user_meta( $user_id, '_wpmatch_current_membership', true );
		if ( $current_membership === $membership_level ) {
			update_user_meta( $user_id, '_wpmatch_current_membership', 'free' );
		}

		self::log_membership_event(
			$user_id,
			'membership_suspended',
			array(
				'membership_level' => $membership_level,
			)
		);

		do_action( 'wpmatch_membership_suspended', $user_id, $membership_level );
	}

	/**
	 * Deactivate user membership.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $membership_level The membership level.
	 * @since 1.1.0
	 */
	public static function deactivate_user_membership( $user_id, $membership_level ) {
		$memberships = get_user_meta( $user_id, '_wpmatch_memberships', true );
		if ( ! is_array( $memberships ) || ! isset( $memberships[ $membership_level ] ) ) {
			return;
		}

		$memberships[ $membership_level ]['status']         = 'inactive';
		$memberships[ $membership_level ]['deactivated_at'] = time();

		update_user_meta( $user_id, '_wpmatch_memberships', $memberships );

		// If this was the current membership, downgrade to free.
		$current_membership = get_user_meta( $user_id, '_wpmatch_current_membership', true );
		if ( $current_membership === $membership_level ) {
			update_user_meta( $user_id, '_wpmatch_current_membership', 'free' );
		}

		self::log_membership_event(
			$user_id,
			'membership_deactivated',
			array(
				'membership_level' => $membership_level,
			)
		);

		do_action( 'wpmatch_membership_deactivated', $user_id, $membership_level );
	}

	/**
	 * Get membership level from subscription.
	 *
	 * @param WC_Subscription $subscription The subscription object.
	 * @return string|false The membership level or false.
	 * @since 1.1.0
	 */
	private static function get_membership_level_from_subscription( $subscription ) {
		foreach ( $subscription->get_items() as $item ) {
			$product_id       = $item->get_product_id();
			$membership_level = get_post_meta( $product_id, '_wpmatch_membership_level', true );

			if ( $membership_level ) {
				return $membership_level;
			}
		}

		return false;
	}

	/**
	 * Check for expired memberships.
	 *
	 * @since 1.1.0
	 */
	public static function check_expired_memberships() {
		global $wpdb;

		// Get all users with memberships.
		$users = $wpdb->get_results(
			"SELECT user_id, meta_value
			FROM {$wpdb->usermeta}
			WHERE meta_key = '_wpmatch_memberships'"
		);

		foreach ( $users as $user ) {
			$memberships = maybe_unserialize( $user->meta_value );
			if ( ! is_array( $memberships ) ) {
				continue;
			}

			$updated = false;
			foreach ( $memberships as $level => $data ) {
				if ( isset( $data['expires_at'] ) && $data['expires_at'] > 0 && $data['expires_at'] <= time() ) {
					if ( 'active' === $data['status'] ) {
						$memberships[ $level ]['status']     = 'expired';
						$memberships[ $level ]['expired_at'] = time();
						$updated                             = true;

						// Log expiration.
						self::log_membership_event(
							$user->user_id,
							'membership_expired',
							array(
								'membership_level' => $level,
							)
						);

						do_action( 'wpmatch_membership_expired', $user->user_id, $level );
					}
				}
			}

			if ( $updated ) {
				update_user_meta( $user->user_id, '_wpmatch_memberships', $memberships );

				// Downgrade to free if current membership expired.
				$current_membership = get_user_meta( $user->user_id, '_wpmatch_current_membership', true );
				if ( isset( $memberships[ $current_membership ] ) && 'expired' === $memberships[ $current_membership ]['status'] ) {
					update_user_meta( $user->user_id, '_wpmatch_current_membership', 'free' );
				}
			}
		}
	}

	/**
	 * Log membership event.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $event The event type.
	 * @param array  $data Event data.
	 * @since 1.1.0
	 */
	private static function log_membership_event( $user_id, $event, $data = array() ) {
		$logs = get_user_meta( $user_id, '_wpmatch_membership_logs', true );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		$logs[] = array(
			'event'     => $event,
			'timestamp' => time(),
			'data'      => $data,
		);

		// Keep only last 50 log entries.
		if ( count( $logs ) > 50 ) {
			$logs = array_slice( $logs, -50 );
		}

		update_user_meta( $user_id, '_wpmatch_membership_logs', $logs );
	}

	/**
	 * Register membership management endpoints.
	 *
	 * @since 1.1.0
	 */
	public static function register_membership_endpoints() {
		add_rewrite_endpoint( 'wpmatch-membership', EP_ROOT | EP_PAGES );
	}

	/**
	 * Handle membership actions.
	 *
	 * @since 1.1.0
	 */
	public static function handle_membership_actions() {
		if ( ! isset( $_POST['wpmatch_membership_action'] ) || ! is_user_logged_in() ) {
			return;
		}

		$action  = sanitize_text_field( wp_unslash( $_POST['wpmatch_membership_action'] ) );
		$user_id = get_current_user_id();

		// Verify nonce.
		if ( ! isset( $_POST['wpmatch_membership_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpmatch_membership_nonce'] ) ), 'wpmatch_membership_action' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wpmatch' ) );
		}

		switch ( $action ) {
			case 'cancel_subscription':
				self::handle_cancel_subscription_request( $user_id );
				break;

			case 'reactivate_subscription':
				self::handle_reactivate_subscription_request( $user_id );
				break;
		}
	}

	/**
	 * Handle subscription cancellation request.
	 *
	 * @param int $user_id The user ID.
	 * @since 1.1.0
	 */
	private static function handle_cancel_subscription_request( $user_id ) {
		$subscription_id = isset( $_POST['subscription_id'] ) ? absint( $_POST['subscription_id'] ) : 0;

		if ( ! $subscription_id ) {
			wp_die( esc_html__( 'Invalid subscription ID.', 'wpmatch' ) );
		}

		// Get subscription and verify ownership.
		$subscription = wcs_get_subscription( $subscription_id );
		if ( ! $subscription || $subscription->get_user_id() !== $user_id ) {
			wp_die( esc_html__( 'You do not have permission to cancel this subscription.', 'wpmatch' ) );
		}

		// Cancel subscription.
		$subscription->update_status( 'cancelled', esc_html__( 'Cancelled by customer.', 'wpmatch' ) );

		wp_safe_redirect( add_query_arg( 'cancelled', '1', wp_get_referer() ) );
		exit;
	}

	/**
	 * Handle subscription reactivation request.
	 *
	 * @param int $user_id The user ID.
	 * @since 1.1.0
	 */
	private static function handle_reactivate_subscription_request( $user_id ) {
		$subscription_id = isset( $_POST['subscription_id'] ) ? absint( $_POST['subscription_id'] ) : 0;

		if ( ! $subscription_id ) {
			wp_die( esc_html__( 'Invalid subscription ID.', 'wpmatch' ) );
		}

		// Get subscription and verify ownership.
		$subscription = wcs_get_subscription( $subscription_id );
		if ( ! $subscription || $subscription->get_user_id() !== $user_id ) {
			wp_die( esc_html__( 'You do not have permission to reactivate this subscription.', 'wpmatch' ) );
		}

		// Reactivate subscription.
		$subscription->update_status( 'active', esc_html__( 'Reactivated by customer.', 'wpmatch' ) );

		wp_safe_redirect( add_query_arg( 'reactivated', '1', wp_get_referer() ) );
		exit;
	}

	/**
	 * Get user's active membership level.
	 *
	 * @param int $user_id The user ID.
	 * @return string The membership level.
	 * @since 1.1.0
	 */
	public static function get_user_membership_level( $user_id ) {
		$current_membership = get_user_meta( $user_id, '_wpmatch_current_membership', true );
		return $current_membership ? $current_membership : 'free';
	}

	/**
	 * Check if user has active membership.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $level The membership level to check.
	 * @return bool Whether user has active membership.
	 * @since 1.1.0
	 */
	public static function user_has_membership( $user_id, $level ) {
		$memberships = get_user_meta( $user_id, '_wpmatch_memberships', true );
		if ( ! is_array( $memberships ) || ! isset( $memberships[ $level ] ) ) {
			return false;
		}

		$membership = $memberships[ $level ];
		if ( 'active' !== $membership['status'] ) {
			return false;
		}

		// Check expiry.
		if ( isset( $membership['expires_at'] ) && $membership['expires_at'] > 0 && $membership['expires_at'] <= time() ) {
			return false;
		}

		return true;
	}

	/**
	 * Get user's membership history.
	 *
	 * @param int $user_id The user ID.
	 * @return array The membership logs.
	 * @since 1.1.0
	 */
	public static function get_user_membership_logs( $user_id ) {
		$logs = get_user_meta( $user_id, '_wpmatch_membership_logs', true );
		return is_array( $logs ) ? $logs : array();
	}
}
