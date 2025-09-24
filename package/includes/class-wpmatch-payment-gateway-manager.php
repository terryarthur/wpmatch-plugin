<?php
/**
 * WPMatch Payment Gateway Manager
 *
 * Manages payment gateway integration for the lightweight subscription system.
 * Supports multiple gateways as premium addons.
 *
 * @package WPMatch
 * @since   1.0.0
 */

// Security check.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPMatch Payment Gateway Manager Class
 *
 * Handles payment gateway registration, processing, and management.
 */
class WPMatch_Payment_Gateway_Manager {

	/**
	 * Registered payment gateways.
	 *
	 * @var array
	 */
	private static $gateways = array();

	/**
	 * Initialize the payment gateway manager.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_default_gateways' ) );
		add_action( 'wp_ajax_wpmatch_process_payment', array( __CLASS__, 'handle_payment_processing' ) );
		add_action( 'wp_ajax_nopriv_wpmatch_process_payment', array( __CLASS__, 'handle_payment_processing' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
	}

	/**
	 * Register a payment gateway.
	 *
	 * @param string $gateway_id Unique gateway identifier.
	 * @param array  $gateway_config Gateway configuration.
	 * @return bool
	 */
	public static function register_gateway( $gateway_id, $gateway_config ) {
		$gateway_id = sanitize_key( $gateway_id );

		if ( empty( $gateway_id ) || isset( self::$gateways[ $gateway_id ] ) ) {
			return false;
		}

		$defaults = array(
			'name'               => '',
			'description'        => '',
			'supports'           => array( 'subscriptions', 'refunds' ),
			'enabled'            => false,
			'test_mode'          => false,
			'settings'           => array(),
			'processor_class'    => '',
			'required_feature'   => '',
		);

		$gateway_config = wp_parse_args( $gateway_config, $defaults );

		// Check if required feature is licensed (for premium gateways).
		if ( ! empty( $gateway_config['required_feature'] ) ) {
			if ( ! WPMatch_Admin_License_Manager::has_admin_feature( $gateway_config['required_feature'] ) ) {
				return false;
			}
		}

		self::$gateways[ $gateway_id ] = $gateway_config;
		return true;
	}

	/**
	 * Register default payment gateways.
	 */
	public static function register_default_gateways() {
		// Manual/Test Gateway (always available).
		self::register_gateway(
			'manual',
			array(
				'name'            => esc_html__( 'Manual Payment', 'wpmatch' ),
				'description'     => esc_html__( 'Accept manual payments for testing purposes.', 'wpmatch' ),
				'supports'        => array( 'subscriptions' ),
				'enabled'         => true,
				'processor_class' => 'WPMatch_Manual_Payment_Gateway',
			)
		);

		// Stripe Gateway (premium addon).
		self::register_gateway(
			'stripe',
			array(
				'name'               => esc_html__( 'Stripe', 'wpmatch' ),
				'description'        => esc_html__( 'Accept credit card payments via Stripe.', 'wpmatch' ),
				'supports'           => array( 'subscriptions', 'refunds', 'tokenization' ),
				'enabled'            => false,
				'processor_class'    => 'WPMatch_Stripe_Payment_Gateway',
				'required_feature'   => 'payment_stripe',
			)
		);

		// PayPal Gateway (premium addon).
		self::register_gateway(
			'paypal',
			array(
				'name'               => esc_html__( 'PayPal', 'wpmatch' ),
				'description'        => esc_html__( 'Accept payments via PayPal.', 'wpmatch' ),
				'supports'           => array( 'subscriptions', 'refunds' ),
				'enabled'            => false,
				'processor_class'    => 'WPMatch_PayPal_Payment_Gateway',
				'required_feature'   => 'payment_paypal',
			)
		);

		do_action( 'wpmatch_register_payment_gateways' );
	}

	/**
	 * Get all registered gateways.
	 *
	 * @param bool $enabled_only Whether to return only enabled gateways.
	 * @return array
	 */
	public static function get_gateways( $enabled_only = false ) {
		if ( $enabled_only ) {
			return array_filter( self::$gateways, function( $gateway ) {
				return ! empty( $gateway['enabled'] );
			} );
		}

		return self::$gateways;
	}

	/**
	 * Get a specific gateway by ID.
	 *
	 * @param string $gateway_id Gateway identifier.
	 * @return array|null
	 */
	public static function get_gateway( $gateway_id ) {
		$gateway_id = sanitize_key( $gateway_id );
		return isset( self::$gateways[ $gateway_id ] ) ? self::$gateways[ $gateway_id ] : null;
	}

	/**
	 * Process a payment through specified gateway.
	 *
	 * @param string $gateway_id Gateway identifier.
	 * @param array  $payment_data Payment data.
	 * @return array Payment result.
	 */
	public static function process_payment( $gateway_id, $payment_data ) {
		$gateway = self::get_gateway( $gateway_id );

		if ( ! $gateway ) {
			return array(
				'success' => false,
				'error'   => esc_html__( 'Payment gateway not found.', 'wpmatch' ),
			);
		}

		if ( empty( $gateway['enabled'] ) ) {
			return array(
				'success' => false,
				'error'   => esc_html__( 'Payment gateway is disabled.', 'wpmatch' ),
			);
		}

		// Load gateway processor class.
		$processor_class = $gateway['processor_class'];

		if ( ! class_exists( $processor_class ) ) {
			return array(
				'success' => false,
				'error'   => esc_html__( 'Payment processor not available.', 'wpmatch' ),
			);
		}

		// Validate payment data.
		$validation_result = self::validate_payment_data( $payment_data );
		if ( ! $validation_result['valid'] ) {
			return array(
				'success' => false,
				'error'   => $validation_result['error'],
			);
		}

		// Process payment.
		try {
			$processor = new $processor_class( $gateway );
			$result = $processor->process_payment( $payment_data );

			// Log transaction.
			self::log_transaction( $gateway_id, $payment_data, $result );

			return $result;
		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Validate payment data.
	 *
	 * @param array $payment_data Payment data.
	 * @return array Validation result.
	 */
	private static function validate_payment_data( $payment_data ) {
		$required_fields = array( 'amount', 'currency', 'user_id' );

		foreach ( $required_fields as $field ) {
			if ( empty( $payment_data[ $field ] ) ) {
				return array(
					'valid' => false,
					'error' => sprintf(
						/* translators: %s: Field name */
						esc_html__( 'Missing required field: %s', 'wpmatch' ),
						$field
					),
				);
			}
		}

		// Validate amount.
		if ( ! is_numeric( $payment_data['amount'] ) || $payment_data['amount'] <= 0 ) {
			return array(
				'valid' => false,
				'error' => esc_html__( 'Invalid payment amount.', 'wpmatch' ),
			);
		}

		// Validate currency.
		$supported_currencies = array( 'USD', 'EUR', 'GBP', 'CAD', 'AUD' );
		if ( ! in_array( strtoupper( $payment_data['currency'] ), $supported_currencies, true ) ) {
			return array(
				'valid' => false,
				'error' => esc_html__( 'Unsupported currency.', 'wpmatch' ),
			);
		}

		// Validate user.
		if ( ! get_user_by( 'id', $payment_data['user_id'] ) ) {
			return array(
				'valid' => false,
				'error' => esc_html__( 'Invalid user.', 'wpmatch' ),
			);
		}

		return array( 'valid' => true );
	}

	/**
	 * Create a payment token for recurring payments.
	 *
	 * @param string $gateway_id Gateway identifier.
	 * @param array  $payment_method Payment method data.
	 * @param int    $user_id User ID.
	 * @return array Token creation result.
	 */
	public static function create_payment_token( $gateway_id, $payment_method, $user_id ) {
		$gateway = self::get_gateway( $gateway_id );

		if ( ! $gateway || ! in_array( 'tokenization', $gateway['supports'], true ) ) {
			return array(
				'success' => false,
				'error'   => esc_html__( 'Tokenization not supported by gateway.', 'wpmatch' ),
			);
		}

		$processor_class = $gateway['processor_class'];

		if ( ! class_exists( $processor_class ) ) {
			return array(
				'success' => false,
				'error'   => esc_html__( 'Payment processor not available.', 'wpmatch' ),
			);
		}

		try {
			$processor = new $processor_class( $gateway );
			return $processor->create_token( $payment_method, $user_id );
		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Process a refund.
	 *
	 * @param string $gateway_id Gateway identifier.
	 * @param string $transaction_id Original transaction ID.
	 * @param float  $amount Refund amount.
	 * @param string $reason Refund reason.
	 * @return array Refund result.
	 */
	public static function process_refund( $gateway_id, $transaction_id, $amount, $reason = '' ) {
		$gateway = self::get_gateway( $gateway_id );

		if ( ! $gateway || ! in_array( 'refunds', $gateway['supports'], true ) ) {
			return array(
				'success' => false,
				'error'   => esc_html__( 'Refunds not supported by gateway.', 'wpmatch' ),
			);
		}

		$processor_class = $gateway['processor_class'];

		if ( ! class_exists( $processor_class ) ) {
			return array(
				'success' => false,
				'error'   => esc_html__( 'Payment processor not available.', 'wpmatch' ),
			);
		}

		try {
			$processor = new $processor_class( $gateway );
			return $processor->process_refund( $transaction_id, $amount, $reason );
		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Log transaction for audit purposes.
	 *
	 * @param string $gateway_id Gateway identifier.
	 * @param array  $payment_data Payment data.
	 * @param array  $result Payment result.
	 */
	private static function log_transaction( $gateway_id, $payment_data, $result ) {
		$log_data = array(
			'gateway'     => $gateway_id,
			'amount'      => $payment_data['amount'],
			'currency'    => $payment_data['currency'],
			'user_id'     => $payment_data['user_id'],
			'success'     => $result['success'],
			'timestamp'   => current_time( 'mysql' ),
		);

		if ( ! $result['success'] ) {
			$log_data['error'] = $result['error'];
		} else {
			$log_data['transaction_id'] = $result['transaction_id'] ?? '';
		}

		// Log to WordPress debug log or custom logging system.
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( 'WPMatch Payment: ' . wp_json_encode( $log_data ) );
		}

		do_action( 'wpmatch_payment_logged', $log_data );
	}

	/**
	 * Update gateway settings.
	 *
	 * @param string $gateway_id Gateway identifier.
	 * @param array  $settings New settings.
	 * @return bool
	 */
	public static function update_gateway_settings( $gateway_id, $settings ) {
		$gateway_id = sanitize_key( $gateway_id );

		if ( ! isset( self::$gateways[ $gateway_id ] ) ) {
			return false;
		}

		// Sanitize settings.
		$sanitized_settings = array();
		foreach ( $settings as $key => $value ) {
			$sanitized_settings[ sanitize_key( $key ) ] = sanitize_text_field( $value );
		}

		// Update gateway configuration.
		self::$gateways[ $gateway_id ]['settings'] = $sanitized_settings;

		// Save to database.
		$all_gateway_settings = get_option( 'wpmatch_gateway_settings', array() );
		$all_gateway_settings[ $gateway_id ] = $sanitized_settings;
		update_option( 'wpmatch_gateway_settings', $all_gateway_settings );

		return true;
	}

	/**
	 * Enable or disable a gateway.
	 *
	 * @param string $gateway_id Gateway identifier.
	 * @param bool   $enabled Whether to enable the gateway.
	 * @return bool
	 */
	public static function set_gateway_enabled( $gateway_id, $enabled ) {
		$gateway_id = sanitize_key( $gateway_id );

		if ( ! isset( self::$gateways[ $gateway_id ] ) ) {
			return false;
		}

		self::$gateways[ $gateway_id ]['enabled'] = (bool) $enabled;

		// Save to database.
		$enabled_gateways = get_option( 'wpmatch_enabled_gateways', array() );
		if ( $enabled ) {
			$enabled_gateways[ $gateway_id ] = true;
		} else {
			unset( $enabled_gateways[ $gateway_id ] );
		}
		update_option( 'wpmatch_enabled_gateways', $enabled_gateways );

		return true;
	}

	/**
	 * Handle AJAX payment processing.
	 */
	public static function handle_payment_processing() {
		check_ajax_referer( 'wpmatch_payment_nonce', 'nonce' );

		$gateway_id = sanitize_key( $_POST['gateway_id'] ?? '' );
		$payment_data = array(
			'amount'   => floatval( $_POST['amount'] ?? 0 ),
			'currency' => sanitize_text_field( $_POST['currency'] ?? 'USD' ),
			'user_id'  => absint( $_POST['user_id'] ?? 0 ),
		);

		$result = self::process_payment( $gateway_id, $payment_data );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result['error'] );
		}
	}

	/**
	 * Add admin menu for payment gateway management.
	 */
	public static function add_admin_menu() {
		add_submenu_page(
			'wpmatch-admin',
			esc_html__( 'Payment Gateways', 'wpmatch' ),
			esc_html__( 'Payment Gateways', 'wpmatch' ),
			'manage_options',
			'wpmatch-payment-gateways',
			array( __CLASS__, 'render_gateways_page' )
		);
	}

	/**
	 * Render payment gateways admin page.
	 */
	public static function render_gateways_page() {
		$gateways = self::get_gateways();
		include WPMATCH_PLUGIN_DIR . 'admin/partials/wpmatch-admin-payment-gateways.php';
	}
}

// Initialize payment gateway manager.
WPMatch_Payment_Gateway_Manager::init();