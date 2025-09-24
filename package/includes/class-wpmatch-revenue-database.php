<?php
/**
 * Database schema and management for revenue tracking
 *
 * @package WPMatch
 */

/**
 * Handle revenue database operations
 */
class WPMatch_Revenue_Database {

	/**
	 * Create revenue tracking tables
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Transactions table
		$transactions_table = $wpdb->prefix . 'wpmatch_transactions';
		$transactions_sql   = "CREATE TABLE $transactions_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			subscription_id bigint(20) DEFAULT NULL,
			transaction_id varchar(255) NOT NULL,
			payment_method varchar(50) DEFAULT NULL,
			amount decimal(10,2) NOT NULL,
			currency varchar(3) DEFAULT 'USD',
			status varchar(20) DEFAULT 'pending',
			payment_date datetime DEFAULT NULL,
			created_date datetime DEFAULT CURRENT_TIMESTAMP,
			updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			notes text DEFAULT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY subscription_id (subscription_id),
			KEY status (status),
			KEY payment_date (payment_date),
			UNIQUE KEY transaction_id (transaction_id)
		) $charset_collate;";

		// Subscriptions table
		$subscriptions_table = $wpdb->prefix . 'wpmatch_subscriptions';
		$subscriptions_sql   = "CREATE TABLE $subscriptions_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			plan_type varchar(50) NOT NULL,
			billing_period varchar(20) DEFAULT 'monthly',
			amount decimal(10,2) NOT NULL,
			currency varchar(3) DEFAULT 'USD',
			status varchar(20) DEFAULT 'active',
			created_date datetime DEFAULT CURRENT_TIMESTAMP,
			start_date datetime DEFAULT CURRENT_TIMESTAMP,
			end_date datetime DEFAULT NULL,
			cancelled_date datetime DEFAULT NULL,
			next_billing_date datetime DEFAULT NULL,
			payment_method varchar(50) DEFAULT NULL,
			subscription_id varchar(255) DEFAULT NULL,
			trial_end_date datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY plan_type (plan_type),
			KEY status (status),
			KEY next_billing_date (next_billing_date),
			UNIQUE KEY subscription_id (subscription_id)
		) $charset_collate;";

		// Revenue analytics table (for caching daily/monthly stats)
		$analytics_table = $wpdb->prefix . 'wpmatch_revenue_analytics';
		$analytics_sql   = "CREATE TABLE $analytics_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			date date NOT NULL,
			period_type varchar(10) DEFAULT 'daily',
			total_revenue decimal(10,2) DEFAULT 0,
			transaction_count int(11) DEFAULT 0,
			new_subscriptions int(11) DEFAULT 0,
			cancelled_subscriptions int(11) DEFAULT 0,
			active_subscriptions int(11) DEFAULT 0,
			mrr decimal(10,2) DEFAULT 0,
			churn_rate decimal(5,2) DEFAULT 0,
			created_date datetime DEFAULT CURRENT_TIMESTAMP,
			updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY date_period (date, period_type),
			KEY period_type (period_type)
		) $charset_collate;";

		// Plan types table
		$plans_table = $wpdb->prefix . 'wpmatch_subscription_plans';
		$plans_sql   = "CREATE TABLE $plans_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			plan_name varchar(100) NOT NULL,
			plan_type varchar(50) NOT NULL,
			description text DEFAULT NULL,
			amount decimal(10,2) NOT NULL,
			currency varchar(3) DEFAULT 'USD',
			billing_period varchar(20) DEFAULT 'monthly',
			trial_period_days int(11) DEFAULT 0,
			features text DEFAULT NULL,
			max_matches_per_day int(11) DEFAULT 0,
			max_messages_per_day int(11) DEFAULT 0,
			priority_support tinyint(1) DEFAULT 0,
			verified_badge tinyint(1) DEFAULT 0,
			status varchar(20) DEFAULT 'active',
			sort_order int(11) DEFAULT 0,
			created_date datetime DEFAULT CURRENT_TIMESTAMP,
			updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY plan_type (plan_type),
			KEY status (status),
			KEY sort_order (sort_order)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $transactions_sql );
		dbDelta( $subscriptions_sql );
		dbDelta( $analytics_sql );
		dbDelta( $plans_sql );

		// Insert default subscription plans
		self::insert_default_plans();

		// Add revenue tracking version
		update_option( 'wpmatch_revenue_db_version', '1.0' );
	}

	/**
	 * Insert default subscription plans
	 */
	private static function insert_default_plans() {
		global $wpdb;

		$plans_table = $wpdb->prefix . 'wpmatch_subscription_plans';

		// Check if plans already exist
		$existing_plans = $wpdb->get_var( "SELECT COUNT(*) FROM $plans_table" );

		if ( $existing_plans > 0 ) {
			return; // Plans already exist
		}

		$default_plans = array(
			array(
				'plan_name'            => 'Basic Plan',
				'plan_type'            => 'basic',
				'description'          => 'Essential features for finding matches',
				'amount'               => 9.99,
				'billing_period'       => 'monthly',
				'trial_period_days'    => 7,
				'features'             => wp_json_encode(
					array(
						'10 matches per day',
						'5 super likes per week',
						'Basic messaging',
						'Profile boost once per month',
					)
				),
				'max_matches_per_day'  => 10,
				'max_messages_per_day' => 50,
				'priority_support'     => 0,
				'verified_badge'       => 0,
				'sort_order'           => 1,
			),
			array(
				'plan_name'            => 'Premium Plan',
				'plan_type'            => 'premium',
				'description'          => 'Enhanced features for serious daters',
				'amount'               => 19.99,
				'billing_period'       => 'monthly',
				'trial_period_days'    => 14,
				'features'             => wp_json_encode(
					array(
						'Unlimited matches',
						'Unlimited super likes',
						'Priority messaging',
						'Weekly profile boost',
						'See who liked you',
						'Message read receipts',
					)
				),
				'max_matches_per_day'  => -1, // Unlimited
				'max_messages_per_day' => -1, // Unlimited
				'priority_support'     => 1,
				'verified_badge'       => 1,
				'sort_order'           => 2,
			),
			array(
				'plan_name'            => 'VIP Plan',
				'plan_type'            => 'vip',
				'description'          => 'Exclusive features for VIP members',
				'amount'               => 39.99,
				'billing_period'       => 'monthly',
				'trial_period_days'    => 30,
				'features'             => wp_json_encode(
					array(
						'All Premium features',
						'Priority in match suggestions',
						'Exclusive VIP badge',
						'Monthly coaching session',
						'Priority customer support',
						'Advanced search filters',
						'Incognito mode',
					)
				),
				'max_matches_per_day'  => -1, // Unlimited
				'max_messages_per_day' => -1, // Unlimited
				'priority_support'     => 1,
				'verified_badge'       => 1,
				'sort_order'           => 3,
			),
		);

		foreach ( $default_plans as $plan ) {
			$wpdb->insert( $plans_table, $plan );
		}
	}

	/**
	 * Record a new transaction
	 */
	public static function record_transaction( $data ) {
		global $wpdb;

		$defaults = array(
			'user_id'         => 0,
			'subscription_id' => null,
			'transaction_id'  => '',
			'payment_method'  => '',
			'amount'          => 0.00,
			'currency'        => 'USD',
			'status'          => 'pending',
			'payment_date'    => null,
			'notes'           => '',
		);

		$data = wp_parse_args( $data, $defaults );

		// Sanitize data
		$data['user_id']         = absint( $data['user_id'] );
		$data['subscription_id'] = $data['subscription_id'] ? absint( $data['subscription_id'] ) : null;
		$data['transaction_id']  = sanitize_text_field( $data['transaction_id'] );
		$data['payment_method']  = sanitize_text_field( $data['payment_method'] );
		$data['amount']          = floatval( $data['amount'] );
		$data['currency']        = sanitize_text_field( $data['currency'] );
		$data['status']          = sanitize_text_field( $data['status'] );
		$data['notes']           = sanitize_textarea_field( $data['notes'] );

		$transactions_table = $wpdb->prefix . 'wpmatch_transactions';

		$result = $wpdb->insert( $transactions_table, $data );

		if ( $result ) {
			$transaction_id = $wpdb->insert_id;

			// Update revenue analytics if transaction is completed
			if ( $data['status'] === 'completed' ) {
				self::update_daily_analytics( $data['payment_date'] ? $data['payment_date'] : current_time( 'mysql' ) );
			}

			return $transaction_id;
		}

		return false;
	}

	/**
	 * Create a new subscription
	 */
	public static function create_subscription( $data ) {
		global $wpdb;

		$defaults = array(
			'user_id'           => 0,
			'plan_type'         => '',
			'billing_period'    => 'monthly',
			'amount'            => 0.00,
			'currency'          => 'USD',
			'status'            => 'active',
			'start_date'        => current_time( 'mysql' ),
			'end_date'          => null,
			'next_billing_date' => null,
			'payment_method'    => '',
			'subscription_id'   => '',
			'trial_end_date'    => null,
		);

		$data = wp_parse_args( $data, $defaults );

		// Calculate next billing date
		if ( ! $data['next_billing_date'] ) {
			$start_date = $data['trial_end_date'] ? $data['trial_end_date'] : $data['start_date'];
			switch ( $data['billing_period'] ) {
				case 'monthly':
					$data['next_billing_date'] = date( 'Y-m-d H:i:s', strtotime( $start_date . ' +1 month' ) );
					break;
				case 'yearly':
					$data['next_billing_date'] = date( 'Y-m-d H:i:s', strtotime( $start_date . ' +1 year' ) );
					break;
				case 'quarterly':
					$data['next_billing_date'] = date( 'Y-m-d H:i:s', strtotime( $start_date . ' +3 months' ) );
					break;
			}
		}

		$subscriptions_table = $wpdb->prefix . 'wpmatch_subscriptions';
		$result              = $wpdb->insert( $subscriptions_table, $data );

		if ( $result ) {
			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Update daily revenue analytics
	 */
	public static function update_daily_analytics( $date ) {
		global $wpdb;

		$analytics_date = date( 'Y-m-d', strtotime( $date ) );

		$transactions_table  = $wpdb->prefix . 'wpmatch_transactions';
		$subscriptions_table = $wpdb->prefix . 'wpmatch_subscriptions';
		$analytics_table     = $wpdb->prefix . 'wpmatch_revenue_analytics';

		// Calculate daily metrics
		$daily_revenue = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(amount)
			FROM $transactions_table
			WHERE status = 'completed'
			AND DATE(payment_date) = %s",
				$analytics_date
			)
		);

		$transaction_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
			FROM $transactions_table
			WHERE status = 'completed'
			AND DATE(payment_date) = %s",
				$analytics_date
			)
		);

		$new_subscriptions = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
			FROM $subscriptions_table
			WHERE DATE(created_date) = %s",
				$analytics_date
			)
		);

		$cancelled_subscriptions = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
			FROM $subscriptions_table
			WHERE DATE(cancelled_date) = %s",
				$analytics_date
			)
		);

		$active_subscriptions = $wpdb->get_var(
			"SELECT COUNT(*)
			FROM $subscriptions_table
			WHERE status = 'active'"
		);

		// Calculate MRR
		$mrr = $wpdb->get_var(
			"SELECT SUM(
				CASE
					WHEN billing_period = 'monthly' THEN amount
					WHEN billing_period = 'yearly' THEN amount / 12
					WHEN billing_period = 'quarterly' THEN amount / 3
					ELSE amount
				END
			)
			FROM $subscriptions_table
			WHERE status = 'active'"
		);

		$analytics_data = array(
			'date'                    => $analytics_date,
			'period_type'             => 'daily',
			'total_revenue'           => $daily_revenue ?: 0,
			'transaction_count'       => $transaction_count ?: 0,
			'new_subscriptions'       => $new_subscriptions ?: 0,
			'cancelled_subscriptions' => $cancelled_subscriptions ?: 0,
			'active_subscriptions'    => $active_subscriptions ?: 0,
			'mrr'                     => $mrr ?: 0,
		);

		// Insert or update analytics record
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $analytics_table WHERE date = %s AND period_type = 'daily'",
				$analytics_date
			)
		);

		if ( $existing ) {
			$wpdb->update(
				$analytics_table,
				$analytics_data,
				array( 'id' => $existing )
			);
		} else {
			$wpdb->insert( $analytics_table, $analytics_data );
		}
	}

	/**
	 * Get subscription plans
	 */
	public static function get_subscription_plans( $status = 'active' ) {
		global $wpdb;

		$plans_table = $wpdb->prefix . 'wpmatch_subscription_plans';

		$sql = "SELECT * FROM $plans_table";
		if ( $status ) {
			$sql .= $wpdb->prepare( ' WHERE status = %s', $status );
		}
		$sql .= ' ORDER BY sort_order ASC';

		return $wpdb->get_results( $sql );
	}

	/**
	 * Drop revenue tracking tables (for uninstall)
	 */
	public static function drop_tables() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'wpmatch_transactions',
			$wpdb->prefix . 'wpmatch_subscriptions',
			$wpdb->prefix . 'wpmatch_revenue_analytics',
			$wpdb->prefix . 'wpmatch_subscription_plans',
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		delete_option( 'wpmatch_revenue_db_version' );
	}
}
