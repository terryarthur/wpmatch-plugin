<?php
/**
 * WPMatch Membership REST API Controller
 *
 * Handles REST API endpoints for membership management.
 *
 * @package WPMatch
 * @since 1.8.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPMatch Membership REST Controller class.
 *
 * @since 1.8.0
 */
class WPMatch_Membership_REST_Controller extends WP_REST_Controller {

	/**
	 * Namespace for REST API.
	 *
	 * @var string
	 */
	protected $namespace = 'wpmatch/v1';

	/**
	 * Resource name.
	 *
	 * @var string
	 */
	protected $rest_base = 'membership';

	/**
	 * Register REST API routes.
	 *
	 * @since 1.8.0
	 */
	public function register_routes() {
		// GET /membership/status - Get current user's membership status.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/status',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_membership_status' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
			)
		);

		// GET /membership/tiers - Get available membership tiers.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/tiers',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_membership_tiers' ),
					'permission_callback' => '__return_true', // Public endpoint.
				),
				'args' => array(
					'billing_period' => array(
						'default'           => 'monthly',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $param ) {
							return in_array( $param, array( 'monthly', 'annual' ), true );
						},
					),
				),
			)
		);

		// POST /membership/upgrade - Initiate membership upgrade.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/upgrade',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'upgrade_membership' ),
					'permission_callback' => array( $this, 'permission_callback' ),
					'args'                => array(
						'tier'           => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => function ( $param ) {
								return in_array( $param, array( 'premium', 'vip' ), true );
							},
						),
						'billing_period' => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => function ( $param ) {
								return in_array( $param, array( 'monthly', 'annual' ), true );
							},
						),
					),
				),
			)
		);

		// POST /membership/cancel - Cancel active subscription.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/cancel',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'cancel_membership' ),
					'permission_callback' => array( $this, 'permission_callback' ),
					'args'                => array(
						'reason'   => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
						'feedback' => array(
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
			)
		);

		// POST /membership/reactivate - Reactivate cancelled subscription.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/reactivate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reactivate_membership' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
			)
		);

		// GET /membership/billing-history - Get user's billing history.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/billing-history',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_billing_history' ),
					'permission_callback' => array( $this, 'permission_callback' ),
					'args'                => array(
						'page'     => array(
							'default'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page' => array(
							'default'           => 20,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// POST /membership/payment-method - Update payment method.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/payment-method',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_payment_method' ),
					'permission_callback' => array( $this, 'permission_callback' ),
					'args'                => array(
						'payment_method_id' => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	/**
	 * Permission callback for authenticated endpoints.
	 *
	 * @since 1.8.0
	 * @return bool|WP_Error
	 */
	public function permission_callback() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_forbidden',
				esc_html__( 'You must be logged in to access this endpoint.', 'wpmatch' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Get current user's membership status.
	 *
	 * @since 1.8.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_membership_status( $request ) {
		$user_id = get_current_user_id();

		$membership_level = WPMatch_Membership_Manager::get_user_membership_level( $user_id );
		$membership_data  = get_user_meta( $user_id, '_wpmatch_membership_data', true );

		$response_data = array(
			'tier'      => $membership_level,
			'tier_name' => $this->get_tier_display_name( $membership_level ),
			'is_active' => WPMatch_Membership_Manager::has_active_premium_membership( $user_id ),
		);

		// Add membership details if active.
		if ( ! empty( $membership_data ) && is_array( $membership_data ) ) {
			if ( isset( $membership_data['expiry_date'] ) ) {
				$expiry_timestamp                = strtotime( $membership_data['expiry_date'] );
				$response_data['expires_at']     = gmdate( 'Y-m-d\TH:i:s', $expiry_timestamp );
				$response_data['days_remaining'] = max( 0, ceil( ( $expiry_timestamp - time() ) / DAY_IN_SECONDS ) );
			}

			if ( isset( $membership_data['subscription_id'] ) ) {
				$response_data['subscription_id'] = absint( $membership_data['subscription_id'] );
			}

			if ( isset( $membership_data['started_at'] ) ) {
				$response_data['started_at'] = sanitize_text_field( $membership_data['started_at'] );
			}
		}

		// Get features for current tier.
		$response_data['features'] = $this->get_tier_features( $membership_level );

		// Check if user can upgrade.
		$response_data['can_upgrade'] = in_array( $membership_level, array( 'free', 'premium' ), true );
		if ( 'premium' === $membership_level ) {
			$response_data['upgrade_tier'] = 'vip';
		} elseif ( 'free' === $membership_level ) {
			$response_data['upgrade_tier'] = 'premium';
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $response_data,
			)
		);
	}

	/**
	 * Get available membership tiers and pricing.
	 *
	 * @since 1.8.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_membership_tiers( $request ) {
		$billing_period = $request->get_param( 'billing_period' );
		$user_id        = get_current_user_id();
		$current_tier   = $user_id ? WPMatch_Membership_Manager::get_user_membership_level( $user_id ) : 'free';

		$tiers = array(
			array(
				'tier'           => 'free',
				'name'           => __( 'Free', 'wpmatch' ),
				'price'          => '$0',
				'billing_period' => 'forever',
				'features'       => $this->get_tier_features( 'free' ),
				'limits'         => array(
					'daily_swipes'        => 100,
					'monthly_super_likes' => 0,
				),
			),
			array(
				'tier'           => 'premium',
				'name'           => __( 'Premium', 'wpmatch' ),
				'price'          => 'monthly' === $billing_period ? '$19.99' : '$15.99/month',
				'annual_price'   => 'annual' === $billing_period ? '$191.90/year' : null,
				'annual_savings' => 'annual' === $billing_period ? '20% savings' : null,
				'billing_period' => $billing_period,
				'is_popular'     => true,
				'features'       => $this->get_tier_features( 'premium' ),
				'limits'         => array(
					'daily_swipes'        => -1,
					'monthly_super_likes' => 5,
				),
			),
			array(
				'tier'           => 'vip',
				'name'           => __( 'VIP', 'wpmatch' ),
				'price'          => 'monthly' === $billing_period ? '$39.99' : '$31.99/month',
				'annual_price'   => 'annual' === $billing_period ? '$383.90/year' : null,
				'annual_savings' => 'annual' === $billing_period ? '20% savings' : null,
				'billing_period' => $billing_period,
				'features'       => $this->get_tier_features( 'vip' ),
				'limits'         => array(
					'daily_swipes'        => -1,
					'monthly_super_likes' => 10,
					'monthly_boosts'      => 2,
				),
			),
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'current_tier' => $current_tier,
					'tiers'        => $tiers,
				),
			)
		);
	}

	/**
	 * Initiate membership upgrade.
	 *
	 * @since 1.8.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function upgrade_membership( $request ) {
		$tier           = $request->get_param( 'tier' );
		$billing_period = $request->get_param( 'billing_period' );

		// Check if WooCommerce is active.
		if ( ! function_exists( 'WC' ) ) {
			return new WP_Error(
				'woocommerce_not_active',
				esc_html__( 'WooCommerce is not active.', 'wpmatch' ),
				array( 'status' => 503 )
			);
		}

		// Get product ID for tier and billing period.
		$product_id = $this->get_product_id_for_tier( $tier, $billing_period );

		if ( ! $product_id ) {
			return new WP_Error(
				'product_not_found',
				esc_html__( 'Membership product not found.', 'wpmatch' ),
				array( 'status' => 404 )
			);
		}

		// Generate checkout URL.
		$checkout_url = add_query_arg(
			array( 'add-to-cart' => $product_id ),
			wc_get_checkout_url()
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'product_id'   => $product_id,
					'checkout_url' => esc_url( $checkout_url ),
				),
			)
		);
	}

	/**
	 * Cancel active subscription.
	 *
	 * @since 1.8.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function cancel_membership( $request ) {
		$user_id  = get_current_user_id();
		$reason   = $request->get_param( 'reason' );
		$feedback = $request->get_param( 'feedback' );

		$membership_data = get_user_meta( $user_id, '_wpmatch_membership_data', true );

		if ( empty( $membership_data['subscription_id'] ) ) {
			return new WP_Error(
				'no_active_subscription',
				esc_html__( 'No active subscription found.', 'wpmatch' ),
				array( 'status' => 404 )
			);
		}

		$subscription_id = absint( $membership_data['subscription_id'] );

		// Check if WooCommerce Subscriptions is active.
		if ( ! function_exists( 'wcs_get_subscription' ) ) {
			return new WP_Error(
				'subscriptions_not_active',
				esc_html__( 'WooCommerce Subscriptions is not active.', 'wpmatch' ),
				array( 'status' => 503 )
			);
		}

		$subscription = wcs_get_subscription( $subscription_id );

		if ( ! $subscription ) {
			return new WP_Error(
				'subscription_not_found',
				esc_html__( 'Subscription not found.', 'wpmatch' ),
				array( 'status' => 404 )
			);
		}

		// Cancel subscription.
		$subscription->update_status( 'cancelled' );

		// Log cancellation reason.
		if ( $reason || $feedback ) {
			update_user_meta(
				$user_id,
				'_wpmatch_cancellation_data',
				array(
					'reason'   => $reason,
					'feedback' => $feedback,
					'date'     => current_time( 'mysql' ),
				)
			);
		}

		$expiry_date = $subscription->get_date( 'end' );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'message'      => esc_html__( 'Subscription cancelled. You\'ll retain access until your current period ends.', 'wpmatch' ),
					'access_until' => $expiry_date ? gmdate( 'Y-m-d\TH:i:s', strtotime( $expiry_date ) ) : null,
				),
			)
		);
	}

	/**
	 * Reactivate cancelled subscription.
	 *
	 * @since 1.8.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reactivate_membership( $request ) {
		$user_id         = get_current_user_id();
		$membership_data = get_user_meta( $user_id, '_wpmatch_membership_data', true );

		if ( empty( $membership_data['subscription_id'] ) ) {
			return new WP_Error(
				'no_subscription_found',
				esc_html__( 'No subscription found to reactivate.', 'wpmatch' ),
				array( 'status' => 404 )
			);
		}

		$subscription_id = absint( $membership_data['subscription_id'] );

		if ( ! function_exists( 'wcs_get_subscription' ) ) {
			return new WP_Error(
				'subscriptions_not_active',
				esc_html__( 'WooCommerce Subscriptions is not active.', 'wpmatch' ),
				array( 'status' => 503 )
			);
		}

		$subscription = wcs_get_subscription( $subscription_id );

		if ( ! $subscription ) {
			return new WP_Error(
				'subscription_not_found',
				esc_html__( 'Subscription not found.', 'wpmatch' ),
				array( 'status' => 404 )
			);
		}

		if ( 'cancelled' !== $subscription->get_status() ) {
			return new WP_Error(
				'subscription_not_cancelled',
				esc_html__( 'Subscription is not cancelled.', 'wpmatch' ),
				array( 'status' => 400 )
			);
		}

		// Reactivate subscription.
		$subscription->update_status( 'active' );

		$next_payment = $subscription->get_date( 'next_payment' );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'message'           => esc_html__( 'Subscription reactivated successfully.', 'wpmatch' ),
					'next_payment_date' => $next_payment ? gmdate( 'Y-m-d\TH:i:s', strtotime( $next_payment ) ) : null,
				),
			)
		);
	}

	/**
	 * Get user's billing history.
	 *
	 * @since 1.8.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_billing_history( $request ) {
		$user_id  = get_current_user_id();
		$page     = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );

		if ( ! function_exists( 'wc_get_orders' ) ) {
			return new WP_Error(
				'woocommerce_not_active',
				esc_html__( 'WooCommerce is not active.', 'wpmatch' ),
				array( 'status' => 503 )
			);
		}

		// Get orders for current user.
		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => $per_page,
				'offset'      => ( $page - 1 ) * $per_page,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);

		$transactions = array();
		foreach ( $orders as $order ) {
			$transactions[] = array(
				'order_id'    => $order->get_id(),
				'date'        => gmdate( 'Y-m-d\TH:i:s', $order->get_date_created()->getTimestamp() ),
				'type'        => $this->get_order_type( $order ),
				'description' => $this->get_order_description( $order ),
				'amount'      => $order->get_formatted_order_total(),
				'status'      => $order->get_status(),
				'invoice_url' => esc_url( $order->get_view_order_url() ),
			);
		}

		// Get total count for pagination.
		$total_orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'return'      => 'ids',
				'limit'       => -1,
			)
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'transactions' => $transactions,
					'pagination'   => array(
						'total'        => count( $total_orders ),
						'pages'        => ceil( count( $total_orders ) / $per_page ),
						'current_page' => $page,
					),
				),
			)
		);
	}

	/**
	 * Update payment method for subscription.
	 *
	 * @since 1.8.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_payment_method( $request ) {
		// Note: This is a placeholder. Actual payment method update
		// typically happens via WooCommerce frontend or payment gateway.
		return new WP_Error(
			'not_implemented',
			esc_html__( 'Payment method updates must be done through your account page.', 'wpmatch' ),
			array( 'status' => 501 )
		);
	}

	/**
	 * Get tier display name.
	 *
	 * @since 1.8.0
	 * @param string $tier Tier slug.
	 * @return string
	 */
	private function get_tier_display_name( $tier ) {
		$names = array(
			'free'    => __( 'Free Member', 'wpmatch' ),
			'premium' => __( 'Premium Member', 'wpmatch' ),
			'vip'     => __( 'VIP Member', 'wpmatch' ),
		);

		return isset( $names[ $tier ] ) ? $names[ $tier ] : __( 'Unknown', 'wpmatch' );
	}

	/**
	 * Get features for tier.
	 *
	 * @since 1.8.0
	 * @param string $tier Tier slug.
	 * @return array
	 */
	private function get_tier_features( $tier ) {
		$features = array(
			'free'    => array(
				__( '100 swipes per day', 'wpmatch' ),
				__( 'Basic search', 'wpmatch' ),
				__( 'Match with others', 'wpmatch' ),
				__( 'Send messages to matches', 'wpmatch' ),
			),
			'premium' => array(
				__( 'Unlimited swipes', 'wpmatch' ),
				__( 'Read receipts', 'wpmatch' ),
				__( 'Advanced search filters', 'wpmatch' ),
				__( 'See who liked you', 'wpmatch' ),
				__( 'Undo last 5 swipes', 'wpmatch' ),
				__( 'Priority support', 'wpmatch' ),
			),
			'vip'     => array(
				__( 'All Premium features', 'wpmatch' ),
				__( 'Profile boost (2x per month)', 'wpmatch' ),
				__( 'Incognito mode', 'wpmatch' ),
				__( '10 Super Likes per month', 'wpmatch' ),
				__( 'Priority visibility', 'wpmatch' ),
				__( 'Verified badge priority', 'wpmatch' ),
				__( 'VIP customer support', 'wpmatch' ),
			),
		);

		return isset( $features[ $tier ] ) ? $features[ $tier ] : array();
	}

	/**
	 * Get product ID for tier and billing period.
	 *
	 * @since 1.8.0
	 * @param string $tier Tier slug.
	 * @param string $billing_period Billing period.
	 * @return int|false
	 */
	private function get_product_id_for_tier( $tier, $billing_period ) {
		// This should be configured in plugin settings.
		// For now, return false (product needs to be configured).
		$product_map = get_option( 'wpmatch_membership_products', array() );

		$key = $tier . '_' . $billing_period;
		return isset( $product_map[ $key ] ) ? absint( $product_map[ $key ] ) : false;
	}

	/**
	 * Get order type.
	 *
	 * @since 1.8.0
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	private function get_order_type( $order ) {
		// Check if order contains subscription products.
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( $product && $product->is_type( array( 'subscription', 'subscription_variation' ) ) ) {
				return 'subscription_purchase';
			}
		}

		return 'credits_purchase';
	}

	/**
	 * Get order description.
	 *
	 * @since 1.8.0
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	private function get_order_description( $order ) {
		$items = $order->get_items();
		if ( empty( $items ) ) {
			return '';
		}

		$first_item = reset( $items );
		return $first_item->get_name();
	}
}
