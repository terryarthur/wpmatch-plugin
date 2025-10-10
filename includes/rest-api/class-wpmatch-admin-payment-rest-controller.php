<?php
/**
 * WPMatch Admin Payment REST API Controller
 *
 * Handles admin REST API endpoints for payment and subscription management.
 *
 * @package WPMatch
 * @since 1.8.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPMatch Admin Payment REST Controller class.
 *
 * @since 1.8.0
 */
class WPMatch_Admin_Payment_REST_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'admin';

	/**
	 * Register REST API routes.
	 *
	 * @since 1.8.0
	 */
	public function register_routes() {
		// GET /admin/revenue/dashboard - Get revenue analytics dashboard.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/revenue/dashboard',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_revenue_dashboard' ),
					'permission_callback' => array( $this, 'admin_permission_callback' ),
					'args'                => array(
						'date_from' => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
						'date_to'   => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// GET /admin/subscriptions - Get all active subscriptions.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/subscriptions',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_subscriptions' ),
					'permission_callback' => array( $this, 'admin_permission_callback' ),
					'args'                => array(
						'status' => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
						'tier'   => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
						'search' => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// POST /admin/credits/adjust - Manually adjust user credit balance.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/credits/adjust',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'adjust_credits' ),
					'permission_callback' => array( $this, 'admin_permission_callback' ),
					'args'                => array(
						'user_id' => array(
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
						'amount'  => array(
							'required'          => true,
							'sanitize_callback' => 'intval',
						),
						'reason'  => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	/**
	 * Permission callback for admin endpoints.
	 *
	 * @since 1.8.0
	 * @return bool|WP_Error
	 */
	public function admin_permission_callback() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				esc_html__( 'You do not have permission to access this endpoint.', 'wpmatch' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Get revenue dashboard data.
	 *
	 * @since 1.8.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_revenue_dashboard( $request ) {
		$date_from = $request->get_param( 'date_from' );
		$date_to   = $request->get_param( 'date_to' );

		// Default to last 30 days if not specified.
		if ( ! $date_from ) {
			$date_from = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		}
		if ( ! $date_to ) {
			$date_to = gmdate( 'Y-m-d' );
		}

		// Calculate revenue metrics.
		$metrics = $this->calculate_revenue_metrics( $date_from, $date_to );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $metrics,
			)
		);
	}

	/**
	 * Get all subscriptions with filtering.
	 *
	 * @since 1.8.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_subscriptions( $request ) {
		$status = $request->get_param( 'status' );
		$tier   = $request->get_param( 'tier' );
		$search = $request->get_param( 'search' );

		if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
			return new WP_Error(
				'subscriptions_not_active',
				esc_html__( 'WooCommerce Subscriptions is not active.', 'wpmatch' ),
				array( 'status' => 503 )
			);
		}

		// Build query args.
		$args = array(
			'subscriptions_per_page' => -1,
			'orderby'                => 'start_date',
			'order'                  => 'DESC',
		);

		if ( $status ) {
			$args['subscription_status'] = $status;
		}

		// Get subscriptions.
		$subscriptions = wcs_get_subscriptions( $args );

		$subscription_data = array();
		foreach ( $subscriptions as $subscription ) {
			$user_id = $subscription->get_user_id();
			$user    = get_user_by( 'id', $user_id );

			if ( ! $user ) {
				continue;
			}

			// Apply search filter.
			if ( $search && false === stripos( $user->display_name, $search ) && false === stripos( $user->user_email, $search ) ) {
				continue;
			}

			$membership_level = WPMatch_Membership_Manager::get_user_membership_level( $user_id );

			// Apply tier filter.
			if ( $tier && $tier !== $membership_level ) {
				continue;
			}

			$subscription_data[] = array(
				'subscription_id' => $subscription->get_id(),
				'user'            => array(
					'id'           => $user_id,
					'display_name' => $user->display_name,
					'email'        => $user->user_email,
				),
				'tier'            => $membership_level,
				'status'          => $subscription->get_status(),
				'start_date'      => $subscription->get_date( 'start' ) ? gmdate( 'Y-m-d\TH:i:s', strtotime( $subscription->get_date( 'start' ) ) ) : null,
				'next_payment'    => $subscription->get_date( 'next_payment' ) ? gmdate( 'Y-m-d\TH:i:s', strtotime( $subscription->get_date( 'next_payment' ) ) ) : null,
				'end_date'        => $subscription->get_date( 'end' ) ? gmdate( 'Y-m-d\TH:i:s', strtotime( $subscription->get_date( 'end' ) ) ) : null,
				'total'           => $subscription->get_formatted_order_total(),
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'subscriptions' => $subscription_data,
					'total'         => count( $subscription_data ),
				),
			)
		);
	}

	/**
	 * Manually adjust user credit balance.
	 *
	 * @since 1.8.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function adjust_credits( $request ) {
		$user_id = $request->get_param( 'user_id' );
		$amount  = $request->get_param( 'amount' );
		$reason  = $request->get_param( 'reason' );

		// Verify user exists.
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return new WP_Error(
				'user_not_found',
				esc_html__( 'User not found.', 'wpmatch' ),
				array( 'status' => 404 )
			);
		}

		// Get current balance.
		$old_balance = absint( get_user_meta( $user_id, '_wpmatch_credit_balance', true ) );

		// Calculate new balance.
		$new_balance = max( 0, $old_balance + $amount );

		// Update balance.
		update_user_meta( $user_id, '_wpmatch_credit_balance', $new_balance );

		// Record transaction.
		global $wpdb;
		$table_name = $wpdb->prefix . 'wpmatch_credit_transactions';

		// Check if table exists.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			$wpdb->insert(
				$table_name,
				array(
					'user_id'          => $user_id,
					'amount'           => $amount,
					'transaction_type' => 'admin_adjustment',
					'action_type'      => 'manual_adjustment',
					'reference_id'     => get_current_user_id(),
					'balance_after'    => $new_balance,
					'created_at'       => current_time( 'mysql' ),
					'notes'            => $reason,
				),
				array( '%d', '%d', '%s', '%s', '%d', '%d', '%s', '%s' )
			);
		}

		// Log admin action.
		do_action( 'wpmatch_admin_credits_adjusted', $user_id, $amount, $reason, get_current_user_id() );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'user_id'     => $user_id,
					'old_balance' => $old_balance,
					'adjustment'  => $amount,
					'new_balance' => $new_balance,
					'reason'      => $reason,
					'adjusted_by' => get_current_user_id(),
					'adjusted_at' => current_time( 'mysql' ),
				),
			)
		);
	}

	/**
	 * Calculate revenue metrics for dashboard.
	 *
	 * @since 1.8.0
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @return array
	 */
	private function calculate_revenue_metrics( $date_from, $date_to ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array(
				'error' => 'WooCommerce is not active',
			);
		}

		// Get orders in date range.
		$orders = wc_get_orders(
			array(
				'date_created' => $date_from . '...' . $date_to,
				'limit'        => -1,
				'status'       => array( 'completed', 'processing' ),
			)
		);

		$total_revenue = 0;
		$refunds       = 0;
		$order_count   = 0;
		$by_tier       = array(
			'premium' => array(
				'subscribers' => 0,
				'revenue'     => 0,
			),
			'vip'     => array(
				'subscribers' => 0,
				'revenue'     => 0,
			),
			'credits' => array(
				'purchases' => 0,
				'revenue'   => 0,
			),
		);

		foreach ( $orders as $order ) {
			++$order_count;
			$order_total    = floatval( $order->get_total() );
			$total_revenue += $order_total;

			// Calculate refunds.
			$refunds += floatval( $order->get_total_refunded() );

			// Categorize by product type.
			foreach ( $order->get_items() as $item ) {
				$product = $item->get_product();
				if ( ! $product ) {
					continue;
				}

				$product_name = strtolower( $product->get_name() );

				if ( strpos( $product_name, 'premium' ) !== false ) {
					++$by_tier['premium']['subscribers'];
					$by_tier['premium']['revenue'] += $order_total;
				} elseif ( strpos( $product_name, 'vip' ) !== false ) {
					++$by_tier['vip']['subscribers'];
					$by_tier['vip']['revenue'] += $order_total;
				} elseif ( strpos( $product_name, 'credit' ) !== false ) {
					++$by_tier['credits']['purchases'];
					$by_tier['credits']['revenue'] += $order_total;
				}
			}
		}

		$net_revenue = $total_revenue - $refunds;

		// Get active subscriber counts.
		$active_subscribers = $this->get_active_subscriber_count();

		// Calculate MRR (Monthly Recurring Revenue).
		$mrr = $this->calculate_mrr();

		return array(
			'period'  => array(
				'from' => $date_from,
				'to'   => $date_to,
			),
			'summary' => array(
				'total_revenue'      => '$' . number_format( $total_revenue, 2 ),
				'net_revenue'        => '$' . number_format( $net_revenue, 2 ),
				'refunds'            => '$' . number_format( $refunds, 2 ),
				'order_count'        => $order_count,
				'active_subscribers' => $active_subscribers,
			),
			'mrr'     => '$' . number_format( $mrr, 2 ),
			'arr'     => '$' . number_format( $mrr * 12, 2 ),
			'by_tier' => array(
				array(
					'tier'        => 'premium',
					'subscribers' => $by_tier['premium']['subscribers'],
					'revenue'     => '$' . number_format( $by_tier['premium']['revenue'], 2 ),
				),
				array(
					'tier'        => 'vip',
					'subscribers' => $by_tier['vip']['subscribers'],
					'revenue'     => '$' . number_format( $by_tier['vip']['revenue'], 2 ),
				),
				array(
					'tier'      => 'credits',
					'purchases' => $by_tier['credits']['purchases'],
					'revenue'   => '$' . number_format( $by_tier['credits']['revenue'], 2 ),
				),
			),
		);
	}

	/**
	 * Get active subscriber count.
	 *
	 * @since 1.8.0
	 * @return int
	 */
	private function get_active_subscriber_count() {
		if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
			return 0;
		}

		$subscriptions = wcs_get_subscriptions(
			array(
				'subscription_status'    => 'active',
				'subscriptions_per_page' => -1,
			)
		);

		return count( $subscriptions );
	}

	/**
	 * Calculate Monthly Recurring Revenue (MRR).
	 *
	 * @since 1.8.0
	 * @return float
	 */
	private function calculate_mrr() {
		if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
			return 0;
		}

		$subscriptions = wcs_get_subscriptions(
			array(
				'subscription_status'    => 'active',
				'subscriptions_per_page' => -1,
			)
		);

		$mrr = 0;

		foreach ( $subscriptions as $subscription ) {
			$total = floatval( $subscription->get_total() );

			// Convert to monthly based on billing period.
			$billing_period = $subscription->get_billing_period();

			if ( 'year' === $billing_period ) {
				$mrr += $total / 12;
			} else {
				$mrr += $total;
			}
		}

		return $mrr;
	}
}
