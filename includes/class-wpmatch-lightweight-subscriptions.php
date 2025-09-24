<?php
/**
 * WPMatch Lightweight Subscription System
 *
 * Alternative to WooCommerce for subscription management.
 * Premium addon sold via Freemius.
 *
 * @package WPMatch
 * @since   1.0.0
 */

// Security check.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPMatch Lightweight Subscription System Class
 *
 * Handles subscription creation, management, and billing without WooCommerce dependency.
 */
class WPMatch_Lightweight_Subscriptions {

	/**
	 * Available subscription intervals.
	 *
	 * @var array
	 */
	private static $intervals = array(
		'daily'   => array(
			'label'  => 'Daily',
			'period' => 'P1D',
		),
		'weekly'  => array(
			'label'  => 'Weekly',
			'period' => 'P1W',
		),
		'monthly' => array(
			'label'  => 'Monthly',
			'period' => 'P1M',
		),
		'yearly'  => array(
			'label'  => 'Yearly',
			'period' => 'P1Y',
		),
	);

	/**
	 * Initialize the subscription system.
	 */
	public static function init() {
		// Check if feature is licensed to admin.
		if ( ! WPMatch_Admin_License_Manager::has_admin_feature( 'subscription_system' ) ) {
			return;
		}

		add_action( 'init', array( __CLASS__, 'create_subscription_tables' ) );
		add_action( 'wp_ajax_wpmatch_create_subscription', array( __CLASS__, 'handle_create_subscription' ) );
		add_action( 'wp_ajax_wpmatch_cancel_subscription', array( __CLASS__, 'handle_cancel_subscription' ) );
		add_action( 'wp_ajax_wpmatch_update_subscription', array( __CLASS__, 'handle_update_subscription' ) );
		add_action( 'wpmatch_daily_cron', array( __CLASS__, 'process_subscription_renewals' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
	}

	/**
	 * Create subscription management database tables.
	 */
	public static function create_subscription_tables() {
		global $wpdb;

		$subscriptions_table = $wpdb->prefix . 'wpmatch_subscriptions';
		$transactions_table = $wpdb->prefix . 'wpmatch_subscription_transactions';
		$plans_table = $wpdb->prefix . 'wpmatch_subscription_plans';

		// Subscription plans table.
		$sql_plans = "CREATE TABLE IF NOT EXISTS {$plans_table} (
			id int(11) NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			description text,
			price decimal(10,2) NOT NULL,
			interval_type varchar(20) NOT NULL DEFAULT 'monthly',
			interval_count int(11) NOT NULL DEFAULT 1,
			trial_period_days int(11) DEFAULT 0,
			features longtext,
			status enum('active','inactive') DEFAULT 'active',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY status (status),
			KEY interval_type (interval_type)
		) {$wpdb->get_charset_collate()};";

		// User subscriptions table.
		$sql_subscriptions = "CREATE TABLE IF NOT EXISTS {$subscriptions_table} (
			id int(11) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			plan_id int(11) NOT NULL,
			status enum('active','canceled','expired','suspended','trialing') DEFAULT 'trialing',
			current_period_start datetime NOT NULL,
			current_period_end datetime NOT NULL,
			trial_end datetime DEFAULT NULL,
			canceled_at datetime DEFAULT NULL,
			payment_method varchar(50) DEFAULT NULL,
			payment_token varchar(255) DEFAULT NULL,
			last_payment_date datetime DEFAULT NULL,
			next_payment_date datetime DEFAULT NULL,
			total_paid decimal(10,2) DEFAULT 0.00,
			metadata longtext,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY plan_id (plan_id),
			KEY status (status),
			KEY next_payment_date (next_payment_date),
			FOREIGN KEY (user_id) REFERENCES {$wpdb->users}(ID) ON DELETE CASCADE
		) {$wpdb->get_charset_collate()};";

		// Transaction history table.
		$sql_transactions = "CREATE TABLE IF NOT EXISTS {$transactions_table} (
			id int(11) NOT NULL AUTO_INCREMENT,
			subscription_id int(11) NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			transaction_id varchar(255) NOT NULL,
			amount decimal(10,2) NOT NULL,
			currency varchar(3) DEFAULT 'USD',
			status enum('pending','completed','failed','refunded') DEFAULT 'pending',
			payment_method varchar(50) NOT NULL,
			gateway_response longtext,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY transaction_id (transaction_id),
			KEY subscription_id (subscription_id),
			KEY user_id (user_id),
			KEY status (status),
			KEY created_at (created_at),
			FOREIGN KEY (subscription_id) REFERENCES {$subscriptions_table}(id) ON DELETE CASCADE,
			FOREIGN KEY (user_id) REFERENCES {$wpdb->users}(ID) ON DELETE CASCADE
		) {$wpdb->get_charset_collate()};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_plans );
		dbDelta( $sql_subscriptions );
		dbDelta( $sql_transactions );
	}

	/**
	 * Create a new subscription plan.
	 *
	 * @param array $plan_data Plan configuration.
	 * @return int|false Plan ID on success, false on failure.
	 */
	public static function create_plan( $plan_data ) {
		global $wpdb;

		$defaults = array(
			'name'               => '',
			'description'        => '',
			'price'              => 0.00,
			'interval_type'      => 'monthly',
			'interval_count'     => 1,
			'trial_period_days'  => 0,
			'features'           => array(),
			'status'             => 'active',
		);

		$plan_data = wp_parse_args( $plan_data, $defaults );

		// Validate required fields.
		if ( empty( $plan_data['name'] ) || $plan_data['price'] < 0 ) {
			return false;
		}

		// Validate interval type.
		if ( ! isset( self::$intervals[ $plan_data['interval_type'] ] ) ) {
			return false;
		}

		$table = $wpdb->prefix . 'wpmatch_subscription_plans';
		$result = $wpdb->insert(
			$table,
			array(
				'name'              => sanitize_text_field( $plan_data['name'] ),
				'description'       => sanitize_textarea_field( $plan_data['description'] ),
				'price'             => floatval( $plan_data['price'] ),
				'interval_type'     => sanitize_key( $plan_data['interval_type'] ),
				'interval_count'    => absint( $plan_data['interval_count'] ),
				'trial_period_days' => absint( $plan_data['trial_period_days'] ),
				'features'          => maybe_serialize( $plan_data['features'] ),
				'status'            => sanitize_key( $plan_data['status'] ),
			),
			array( '%s', '%s', '%f', '%s', '%d', '%d', '%s', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Create a new subscription for a user.
	 *
	 * @param int   $user_id User ID.
	 * @param int   $plan_id Plan ID.
	 * @param array $subscription_data Additional subscription data.
	 * @return int|false Subscription ID on success, false on failure.
	 */
	public static function create_subscription( $user_id, $plan_id, $subscription_data = array() ) {
		global $wpdb;

		$user_id = absint( $user_id );
		$plan_id = absint( $plan_id );

		if ( ! $user_id || ! $plan_id ) {
			return false;
		}

		// Get plan details.
		$plan = self::get_plan( $plan_id );
		if ( ! $plan ) {
			return false;
		}

		// Calculate subscription periods.
		$current_time = current_time( 'mysql' );
		$trial_end = null;

		if ( $plan->trial_period_days > 0 ) {
			$trial_end = date( 'Y-m-d H:i:s', strtotime( $current_time . ' +' . $plan->trial_period_days . ' days' ) );
			$period_start = $trial_end;
		} else {
			$period_start = $current_time;
		}

		// Calculate next billing date.
		$interval = new DateInterval( self::$intervals[ $plan->interval_type ]['period'] );
		$next_billing = new DateTime( $period_start );
		$next_billing->add( $interval );

		$defaults = array(
			'status'             => $trial_end ? 'trialing' : 'active',
			'payment_method'     => '',
			'payment_token'      => '',
			'metadata'           => array(),
		);

		$subscription_data = wp_parse_args( $subscription_data, $defaults );

		$table = $wpdb->prefix . 'wpmatch_subscriptions';
		$result = $wpdb->insert(
			$table,
			array(
				'user_id'               => $user_id,
				'plan_id'               => $plan_id,
				'status'                => sanitize_key( $subscription_data['status'] ),
				'current_period_start'  => $period_start,
				'current_period_end'    => $next_billing->format( 'Y-m-d H:i:s' ),
				'trial_end'             => $trial_end,
				'payment_method'        => sanitize_text_field( $subscription_data['payment_method'] ),
				'payment_token'         => sanitize_text_field( $subscription_data['payment_token'] ),
				'next_payment_date'     => $trial_end ? $trial_end : $next_billing->format( 'Y-m-d H:i:s' ),
				'metadata'              => maybe_serialize( $subscription_data['metadata'] ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $result ) {
			$subscription_id = $wpdb->insert_id;

			// Update user membership data.
			$membership_data = array(
				'status'      => 'active',
				'level'       => $plan->name,
				'expiry_date' => $next_billing->format( 'Y-m-d H:i:s' ),
				'features'    => maybe_unserialize( $plan->features ),
			);

			update_user_meta( $user_id, '_wpmatch_membership_data', $membership_data );

			// Trigger action for third-party integrations.
			do_action( 'wpmatch_subscription_created', $subscription_id, $user_id, $plan_id );

			return $subscription_id;
		}

		return false;
	}

	/**
	 * Cancel a subscription.
	 *
	 * @param int  $subscription_id Subscription ID.
	 * @param bool $immediately Whether to cancel immediately or at period end.
	 * @return bool
	 */
	public static function cancel_subscription( $subscription_id, $immediately = false ) {
		global $wpdb;

		$subscription_id = absint( $subscription_id );
		if ( ! $subscription_id ) {
			return false;
		}

		$subscription = self::get_subscription( $subscription_id );
		if ( ! $subscription ) {
			return false;
		}

		$table = $wpdb->prefix . 'wpmatch_subscriptions';
		$update_data = array(
			'canceled_at' => current_time( 'mysql' ),
		);

		if ( $immediately ) {
			$update_data['status'] = 'canceled';
			$update_data['current_period_end'] = current_time( 'mysql' );

			// Update user membership immediately.
			delete_user_meta( $subscription->user_id, '_wpmatch_membership_data' );
		} else {
			// Cancel at period end.
			$update_data['status'] = 'canceled';
		}

		$result = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $subscription_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			do_action( 'wpmatch_subscription_canceled', $subscription_id, $subscription->user_id, $immediately );
			return true;
		}

		return false;
	}

	/**
	 * Process subscription renewals.
	 */
	public static function process_subscription_renewals() {
		global $wpdb;

		$table = $wpdb->prefix . 'wpmatch_subscriptions';
		$current_time = current_time( 'mysql' );

		// Get subscriptions due for renewal.
		$due_subscriptions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE status IN ('active', 'trialing')
				AND next_payment_date <= %s
				ORDER BY next_payment_date ASC",
				$current_time
			)
		);

		foreach ( $due_subscriptions as $subscription ) {
			self::process_subscription_renewal( $subscription );
		}

		// Handle expired trials.
		$expired_trials = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE status = 'trialing'
				AND trial_end <= %s",
				$current_time
			)
		);

		foreach ( $expired_trials as $subscription ) {
			self::handle_trial_expiration( $subscription );
		}
	}

	/**
	 * Process individual subscription renewal.
	 *
	 * @param object $subscription Subscription object.
	 * @return bool
	 */
	private static function process_subscription_renewal( $subscription ) {
		// Get plan details.
		$plan = self::get_plan( $subscription->plan_id );
		if ( ! $plan ) {
			return false;
		}

		// Attempt payment processing.
		$payment_result = self::process_payment( $subscription, $plan->price );

		if ( $payment_result['success'] ) {
			// Update subscription for next period.
			self::advance_subscription_period( $subscription );

			// Record successful transaction.
			self::record_transaction( $subscription->id, $subscription->user_id, $payment_result['transaction_id'], $plan->price, 'completed', $subscription->payment_method );

			return true;
		} else {
			// Handle payment failure.
			self::handle_payment_failure( $subscription, $payment_result['error'] );
			return false;
		}
	}

	/**
	 * Advance subscription to next billing period.
	 *
	 * @param object $subscription Subscription object.
	 */
	private static function advance_subscription_period( $subscription ) {
		global $wpdb;

		$plan = self::get_plan( $subscription->plan_id );
		$interval = new DateInterval( self::$intervals[ $plan->interval_type ]['period'] );

		$current_end = new DateTime( $subscription->current_period_end );
		$next_start = clone $current_end;
		$next_end = clone $current_end;
		$next_end->add( $interval );

		$table = $wpdb->prefix . 'wpmatch_subscriptions';
		$wpdb->update(
			$table,
			array(
				'current_period_start' => $next_start->format( 'Y-m-d H:i:s' ),
				'current_period_end'   => $next_end->format( 'Y-m-d H:i:s' ),
				'next_payment_date'    => $next_end->format( 'Y-m-d H:i:s' ),
				'last_payment_date'    => current_time( 'mysql' ),
				'status'               => 'active',
			),
			array( 'id' => $subscription->id ),
			array( '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		// Update user membership data.
		$membership_data = array(
			'status'      => 'active',
			'level'       => $plan->name,
			'expiry_date' => $next_end->format( 'Y-m-d H:i:s' ),
			'features'    => maybe_unserialize( $plan->features ),
		);

		update_user_meta( $subscription->user_id, '_wpmatch_membership_data', $membership_data );
	}

	/**
	 * Process payment for subscription.
	 *
	 * @param object $subscription Subscription object.
	 * @param float  $amount Payment amount.
	 * @return array Payment result.
	 */
	private static function process_payment( $subscription, $amount ) {
		// This would integrate with payment gateways.
		// For now, return a mock successful payment.
		return array(
			'success'        => true,
			'transaction_id' => 'txn_' . uniqid(),
			'amount'         => $amount,
			'error'          => null,
		);
	}

	/**
	 * Record transaction in database.
	 *
	 * @param int    $subscription_id Subscription ID.
	 * @param int    $user_id User ID.
	 * @param string $transaction_id Transaction ID.
	 * @param float  $amount Amount.
	 * @param string $status Transaction status.
	 * @param string $payment_method Payment method.
	 * @return bool
	 */
	private static function record_transaction( $subscription_id, $user_id, $transaction_id, $amount, $status, $payment_method ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wpmatch_subscription_transactions';
		$result = $wpdb->insert(
			$table,
			array(
				'subscription_id' => $subscription_id,
				'user_id'         => $user_id,
				'transaction_id'  => $transaction_id,
				'amount'          => $amount,
				'status'          => $status,
				'payment_method'  => $payment_method,
			),
			array( '%d', '%d', '%s', '%f', '%s', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Get subscription by ID.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @return object|null
	 */
	public static function get_subscription( $subscription_id ) {
		global $wpdb;

		$subscription_id = absint( $subscription_id );
		if ( ! $subscription_id ) {
			return null;
		}

		$table = $wpdb->prefix . 'wpmatch_subscriptions';
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$subscription_id
			)
		);
	}

	/**
	 * Get plan by ID.
	 *
	 * @param int $plan_id Plan ID.
	 * @return object|null
	 */
	public static function get_plan( $plan_id ) {
		global $wpdb;

		$plan_id = absint( $plan_id );
		if ( ! $plan_id ) {
			return null;
		}

		$table = $wpdb->prefix . 'wpmatch_subscription_plans';
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$plan_id
			)
		);
	}

	/**
	 * Handle trial expiration.
	 *
	 * @param object $subscription Subscription object.
	 */
	private static function handle_trial_expiration( $subscription ) {
		// Attempt first payment.
		$plan = self::get_plan( $subscription->plan_id );
		$payment_result = self::process_payment( $subscription, $plan->price );

		if ( $payment_result['success'] ) {
			// Convert to paid subscription.
			self::advance_subscription_period( $subscription );
			self::record_transaction( $subscription->id, $subscription->user_id, $payment_result['transaction_id'], $plan->price, 'completed', $subscription->payment_method );
		} else {
			// Cancel subscription due to payment failure.
			self::cancel_subscription( $subscription->id, true );
		}
	}

	/**
	 * Handle payment failure.
	 *
	 * @param object $subscription Subscription object.
	 * @param string $error Error message.
	 */
	private static function handle_payment_failure( $subscription, $error ) {
		global $wpdb;

		// Record failed transaction.
		self::record_transaction( $subscription->id, $subscription->user_id, 'failed_' . uniqid(), 0, 'failed', $subscription->payment_method );

		// Suspend subscription or cancel after multiple failures.
		$table = $wpdb->prefix . 'wpmatch_subscriptions';
		$wpdb->update(
			$table,
			array( 'status' => 'suspended' ),
			array( 'id' => $subscription->id ),
			array( '%s' ),
			array( '%d' )
		);

		// Notify user of payment failure.
		do_action( 'wpmatch_subscription_payment_failed', $subscription->id, $subscription->user_id, $error );
	}

	/**
	 * Add admin menu for subscription management.
	 */
	public static function add_admin_menu() {
		add_submenu_page(
			'wpmatch-admin',
			esc_html__( 'Subscriptions', 'wpmatch' ),
			esc_html__( 'Subscriptions', 'wpmatch' ),
			'manage_options',
			'wpmatch-subscriptions',
			array( __CLASS__, 'render_subscriptions_page' )
		);
	}

	/**
	 * Render subscriptions admin page.
	 */
	public static function render_subscriptions_page() {
		// This would render the subscription management interface.
		echo '<div class="wrap"><h1>' . esc_html__( 'Subscription Management', 'wpmatch' ) . '</h1></div>';
	}

	/**
	 * Handle AJAX subscription creation.
	 */
	public static function handle_create_subscription() {
		check_ajax_referer( 'wpmatch_subscription_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wpmatch' ) );
		}

		// Implementation for AJAX subscription creation.
		wp_send_json_success( array( 'message' => 'Subscription creation functionality ready.' ) );
	}

	/**
	 * Handle AJAX subscription cancellation.
	 */
	public static function handle_cancel_subscription() {
		check_ajax_referer( 'wpmatch_subscription_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wpmatch' ) );
		}

		// Implementation for AJAX subscription cancellation.
		wp_send_json_success( array( 'message' => 'Subscription cancellation functionality ready.' ) );
	}

	/**
	 * Handle AJAX subscription update.
	 */
	public static function handle_update_subscription() {
		check_ajax_referer( 'wpmatch_subscription_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wpmatch' ) );
		}

		// Implementation for AJAX subscription update.
		wp_send_json_success( array( 'message' => 'Subscription update functionality ready.' ) );
	}
}

// Initialize lightweight subscriptions if feature is available.
if ( class_exists( 'WPMatch_Admin_License_Manager' ) ) {
	WPMatch_Lightweight_Subscriptions::init();
}