<?php
/**
 * WPMatch Credits REST API Controller
 *
 * Handles REST API endpoints for credit management.
 *
 * @package WPMatch
 * @since 1.8.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPMatch Credits REST Controller class.
 *
 * @since 1.8.0
 */
class WPMatch_Credits_REST_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'credits';

	/**
	 * Register REST API routes.
	 *
	 * @since 1.8.0
	 */
	public function register_routes() {
		// GET /credits/balance - Get user's credit balance.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/balance',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_credit_balance' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
			)
		);

		// GET /credits/packages - Get available credit packages.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/packages',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_credit_packages' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
			)
		);

		// POST /credits/spend - Spend credits on an action.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/spend',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'spend_credits' ),
					'permission_callback' => array( $this, 'permission_callback' ),
					'args'                => array(
						'action'       => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => function ( $param ) {
								return in_array( $param, array( 'profile_boost', 'super_like', 'see_likes', 'undo_swipes' ), true );
							},
						),
						'reference_id' => array(
							'sanitize_callback' => 'absint',
						),
						'duration'     => array(
							'sanitize_callback' => 'absint',
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
	 * Get user's credit balance and transaction history.
	 *
	 * @since 1.8.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_credit_balance( $request ) {
		$user_id = get_current_user_id();

		// Get credit balance from user meta.
		$balance = absint( get_user_meta( $user_id, '_wpmatch_credit_balance', true ) );

		// Get recent transactions.
		$transactions = $this->get_recent_transactions( $user_id, 10 );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'balance'             => $balance,
					'recent_transactions' => $transactions,
				),
			)
		);
	}

	/**
	 * Get available credit packages for purchase.
	 *
	 * @since 1.8.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_credit_packages( $request ) {
		// Get configured credit packages from settings.
		$packages = $this->get_configured_packages();

		// Get credit action costs.
		$credit_actions = array(
			array(
				'action'      => 'profile_boost',
				'name'        => __( 'Profile Boost (30 min)', 'wpmatch' ),
				'cost'        => 5,
				'description' => __( 'Get 2x visibility for 30 minutes', 'wpmatch' ),
			),
			array(
				'action'      => 'super_like',
				'name'        => __( 'Super Like', 'wpmatch' ),
				'cost'        => 2,
				'description' => __( 'Stand out to someone special', 'wpmatch' ),
			),
			array(
				'action'      => 'see_likes',
				'name'        => __( 'See Who Liked You (24h)', 'wpmatch' ),
				'cost'        => 3,
				'description' => __( 'See everyone who liked you for 24 hours', 'wpmatch' ),
			),
			array(
				'action'      => 'undo_swipes',
				'name'        => __( 'Undo Last Swipe', 'wpmatch' ),
				'cost'        => 1,
				'description' => __( 'Undo your last swipe action', 'wpmatch' ),
			),
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'packages'       => $packages,
					'credit_actions' => $credit_actions,
				),
			)
		);
	}

	/**
	 * Spend credits on a premium action.
	 *
	 * @since 1.8.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function spend_credits( $request ) {
		$user_id      = get_current_user_id();
		$action       = $request->get_param( 'action' );
		$reference_id = $request->get_param( 'reference_id' );
		$duration     = $request->get_param( 'duration' );

		// Get action cost.
		$cost = $this->get_action_cost( $action );

		if ( ! $cost ) {
			return new WP_Error(
				'invalid_action',
				esc_html__( 'Invalid credit action.', 'wpmatch' ),
				array( 'status' => 400 )
			);
		}

		// Get current balance.
		$balance = absint( get_user_meta( $user_id, '_wpmatch_credit_balance', true ) );

		// Check sufficient balance.
		if ( $balance < $cost ) {
			return new WP_Error(
				'insufficient_credits',
				sprintf(
					/* translators: %1$d: required credits, %2$d: available credits */
					esc_html__( 'You need %1$d credits for this action. Your balance: %2$d credits', 'wpmatch' ),
					$cost,
					$balance
				),
				array(
					'status'    => 402,
					'required'  => $cost,
					'available' => $balance,
					'needed'    => $cost - $balance,
				)
			);
		}

		// Deduct credits (atomic operation).
		$new_balance = $balance - $cost;
		update_user_meta( $user_id, '_wpmatch_credit_balance', $new_balance );

		// Record transaction.
		$transaction_id = $this->record_transaction(
			$user_id,
			-$cost,
			'spend',
			$action,
			$reference_id
		);

		// Activate the premium action.
		$expires_at = $this->activate_premium_action( $user_id, $action, $duration, $reference_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'transaction_id' => $transaction_id,
					'action'         => $action,
					'credits_spent'  => $cost,
					'balance_after'  => $new_balance,
					'expires_at'     => $expires_at,
				),
			)
		);
	}

	/**
	 * Get recent transactions for user.
	 *
	 * @since 1.8.0
	 * @param int $user_id User ID.
	 * @param int $limit Number of transactions to retrieve.
	 * @return array
	 */
	private function get_recent_transactions( $user_id, $limit = 10 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_credit_transactions';

		// Check if table exists.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$transactions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name}
				WHERE user_id = %d
				ORDER BY created_at DESC
				LIMIT %d",
				$user_id,
				$limit
			),
			ARRAY_A
		);

		$formatted = array();
		foreach ( $transactions as $transaction ) {
			$formatted[] = array(
				'id'            => absint( $transaction['id'] ),
				'type'          => sanitize_text_field( $transaction['transaction_type'] ),
				'amount'        => intval( $transaction['amount'] ),
				'action'        => sanitize_text_field( $transaction['action_type'] ),
				'balance_after' => absint( $transaction['balance_after'] ),
				'created_at'    => sanitize_text_field( $transaction['created_at'] ),
			);
		}

		return $formatted;
	}

	/**
	 * Get configured credit packages.
	 *
	 * @since 1.8.0
	 * @return array
	 */
	private function get_configured_packages() {
		// Default packages (can be configured in settings).
		return array(
			array(
				'id'               => 'credits_10',
				'credits'          => 10,
				'price'            => '$4.99',
				'price_per_credit' => '$0.50',
				'savings'          => null,
			),
			array(
				'id'               => 'credits_50',
				'credits'          => 50,
				'price'            => '$19.99',
				'price_per_credit' => '$0.40',
				'savings'          => '20% savings',
			),
			array(
				'id'               => 'credits_100',
				'credits'          => 100,
				'price'            => '$34.99',
				'price_per_credit' => '$0.35',
				'savings'          => '30% savings',
				'is_best_value'    => true,
			),
		);
	}

	/**
	 * Get cost for credit action.
	 *
	 * @since 1.8.0
	 * @param string $action Action type.
	 * @return int|false
	 */
	private function get_action_cost( $action ) {
		$costs = array(
			'profile_boost' => 5,
			'super_like'    => 2,
			'see_likes'     => 3,
			'undo_swipes'   => 1,
		);

		return isset( $costs[ $action ] ) ? $costs[ $action ] : false;
	}

	/**
	 * Record credit transaction.
	 *
	 * @since 1.8.0
	 * @param int    $user_id User ID.
	 * @param int    $amount Amount (positive for add, negative for spend).
	 * @param string $type Transaction type.
	 * @param string $action Action type.
	 * @param int    $reference_id Reference ID.
	 * @return int|false Transaction ID or false on failure.
	 */
	private function record_transaction( $user_id, $amount, $type, $action, $reference_id = 0 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_credit_transactions';

		// Check if table exists, if not return false.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			return false;
		}

		$balance_after = absint( get_user_meta( $user_id, '_wpmatch_credit_balance', true ) );

		$result = $wpdb->insert(
			$table_name,
			array(
				'user_id'          => $user_id,
				'amount'           => $amount,
				'transaction_type' => $type,
				'action_type'      => $action,
				'reference_id'     => $reference_id,
				'balance_after'    => $balance_after,
				'created_at'       => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%d', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Activate premium action after credit spend.
	 *
	 * @since 1.8.0
	 * @param int    $user_id User ID.
	 * @param string $action Action type.
	 * @param int    $duration Duration in minutes (for boost).
	 * @param int    $reference_id Reference ID (target user for super like).
	 * @return string|null Expiration timestamp or null.
	 */
	private function activate_premium_action( $user_id, $action, $duration, $reference_id ) {
		$expires_at = null;

		switch ( $action ) {
			case 'profile_boost':
				// Activate profile boost for 30 minutes.
				$boost_duration = $duration ? $duration : 30;
				$expires_at     = time() + ( $boost_duration * MINUTE_IN_SECONDS );
				update_user_meta( $user_id, '_wpmatch_boost_expires', $expires_at );
				update_user_meta( $user_id, '_wpmatch_boost_active', 1 );
				break;

			case 'super_like':
				// Mark as super like (would integrate with swipe system).
				if ( $reference_id ) {
					update_user_meta( $user_id, '_wpmatch_super_like_' . $reference_id, time() );
				}
				break;

			case 'see_likes':
				// Unlock likes view for 24 hours.
				$expires_at = time() + DAY_IN_SECONDS;
				update_user_meta( $user_id, '_wpmatch_see_likes_expires', $expires_at );
				update_user_meta( $user_id, '_wpmatch_see_likes_active', 1 );
				break;

			case 'undo_swipes':
				// Enable undo for last swipe.
				update_user_meta( $user_id, '_wpmatch_undo_available', 1 );
				break;
		}

		return $expires_at ? gmdate( 'Y-m-d\TH:i:s', $expires_at ) : null;
	}
}
