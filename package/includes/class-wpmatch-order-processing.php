<?php
/**
 * WPMatch Order Processing
 *
 * Handles WooCommerce order processing for WPMatch products.
 *
 * @package WPMatch
 * @since   1.0.0
 */

// Security check.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPMatch Order Processing Class
 *
 * Processes WooCommerce orders for premium memberships and features.
 */
class WPMatch_Order_Processing {

	/**
	 * Initialize order processing hooks.
	 */
	public static function init() {
		// Process completed orders.
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'process_completed_order' ) );

		// Handle refunds.
		add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'process_refunded_order' ) );

		// Handle order cancellations.
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'process_cancelled_order' ) );

		// Custom order item meta.
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'add_custom_order_item_meta' ), 10, 4 );
	}

	/**
	 * Process completed WPMatch orders.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function process_completed_order( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$user_id = $order->get_user_id();

		if ( ! $user_id ) {
			return;
		}

		foreach ( $order->get_items() as $item_id => $item ) {
			$product_id = $item->get_product_id();
			$product    = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			// Check if this is a WPMatch product.
			$product_type = get_post_meta( $product_id, '_wpmatch_product_type', true );
			$feature_type = get_post_meta( $product_id, '_wpmatch_feature_type', true );

			if ( $product_type ) {
				self::activate_membership( $user_id, $product_type, $product_id );
			} elseif ( $feature_type ) {
				self::activate_feature( $user_id, $feature_type, $product_id );
			}
		}

		// Send welcome email for premium members.
		do_action( 'wpmatch_premium_membership_activated', $user_id, $order_id );
	}

	/**
	 * Activate premium membership.
	 *
	 * @param int    $user_id User ID.
	 * @param string $product_type Product type.
	 * @param int    $product_id Product ID.
	 */
	private static function activate_membership( $user_id, $product_type, $product_id ) {
		$duration = get_post_meta( $product_id, '_wpmatch_membership_duration', true );
		$features = get_post_meta( $product_id, '_wpmatch_features', true );

		if ( ! $duration ) {
			$duration = 30; // Default 30 days.
		}

		// Determine membership level.
		$level = 'basic';
		if ( false !== strpos( $product_type, 'gold' ) ) {
			$level = 'gold';
		} elseif ( false !== strpos( $product_type, 'platinum' ) ) {
			$level = 'platinum';
		}

		WPMatch_Membership_Manager::activate_membership( $user_id, $level, $duration, $product_id );

		// Log membership activation.
		error_log( sprintf( 'WPMatch: Membership activated for user %d, level %s, expires in %d days', $user_id, $level, $duration ) );
	}

	/**
	 * Activate individual feature.
	 *
	 * @param int    $user_id User ID.
	 * @param string $feature_type Feature type.
	 * @param int    $product_id Product ID.
	 */
	private static function activate_feature( $user_id, $feature_type, $product_id ) {
		$duration = get_post_meta( $product_id, '_wpmatch_feature_duration', true );
		$quantity = get_post_meta( $product_id, '_wpmatch_feature_quantity', true );

		WPMatch_Membership_Manager::activate_feature( $user_id, $feature_type, $product_id, $quantity, $duration );

		// Log feature activation.
		error_log( sprintf( 'WPMatch: Feature %s activated for user %d', $feature_type, $user_id ) );
	}

	/**
	 * Process refunded orders.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function process_refunded_order( $order_id ) {
		$order   = wc_get_order( $order_id );
		$user_id = $order->get_user_id();

		if ( ! $user_id ) {
			return;
		}

		// Deactivate memberships/features from this order.
		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			self::deactivate_product_benefits( $user_id, $product_id );
		}
	}

	/**
	 * Process cancelled orders.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function process_cancelled_order( $order_id ) {
		// Same logic as refunded orders.
		self::process_refunded_order( $order_id );
	}

	/**
	 * Deactivate product benefits.
	 *
	 * @param int $user_id User ID.
	 * @param int $product_id Product ID.
	 */
	private static function deactivate_product_benefits( $user_id, $product_id ) {
		// Check if it's a membership product.
		$product_type = get_post_meta( $product_id, '_wpmatch_product_type', true );

		if ( $product_type ) {
			$membership_data = get_user_meta( $user_id, '_wpmatch_membership_data', true );

			if ( $membership_data && isset( $membership_data['product_id'] ) && $membership_data['product_id'] == $product_id ) {
				WPMatch_Membership_Manager::deactivate_membership( $user_id );
			}
		}

		do_action( 'wpmatch_product_benefits_deactivated', $user_id, $product_id );
	}

	/**
	 * Add custom order item meta.
	 *
	 * @param WC_Order_Item_Product $item Order item.
	 * @param string                $cart_item_key Cart item key.
	 * @param array                 $values Cart item values.
	 * @param WC_Order              $order Order object.
	 */
	public static function add_custom_order_item_meta( $item, $cart_item_key, $values, $order ) {
		$product_id = $item->get_product_id();

		// Add WPMatch metadata to order items.
		$product_type = get_post_meta( $product_id, '_wpmatch_product_type', true );
		$feature_type = get_post_meta( $product_id, '_wpmatch_feature_type', true );

		if ( $product_type ) {
			$item->add_meta_data( '_wpmatch_product_type', $product_type );
			$duration = get_post_meta( $product_id, '_wpmatch_membership_duration', true );
			if ( $duration ) {
				$item->add_meta_data( '_wpmatch_membership_duration', $duration );
			}
		} elseif ( $feature_type ) {
			$item->add_meta_data( '_wpmatch_feature_type', $feature_type );
			$quantity = get_post_meta( $product_id, '_wpmatch_feature_quantity', true );
			$duration = get_post_meta( $product_id, '_wpmatch_feature_duration', true );
			if ( $quantity ) {
				$item->add_meta_data( '_wpmatch_feature_quantity', $quantity );
			}
			if ( $duration ) {
				$item->add_meta_data( '_wpmatch_feature_duration', $duration );
			}
		}
	}
}
