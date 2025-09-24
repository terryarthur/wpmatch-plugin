<?php
/**
 * WPMatch Stripe Payment Gateway
 *
 * Premium payment gateway for Stripe integration.
 * Requires 'payment_stripe' feature license.
 *
 * @package WPMatch
 * @since   1.0.0
 */

// Security check.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stripe Payment Gateway Class
 *
 * Processes payments through Stripe API.
 */
class WPMatch_Stripe_Payment_Gateway {

	/**
	 * Gateway configuration.
	 *
	 * @var array
	 */
	private $config;

	/**
	 * Stripe API key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Test mode flag.
	 *
	 * @var bool
	 */
	private $test_mode;

	/**
	 * Constructor.
	 *
	 * @param array $config Gateway configuration.
	 */
	public function __construct( $config ) {
		$this->config = $config;
		$this->test_mode = ! empty( $config['settings']['test_mode'] );
		$this->api_key = $this->test_mode
			? ( $config['settings']['test_secret_key'] ?? '' )
			: ( $config['settings']['live_secret_key'] ?? '' );
	}

	/**
	 * Process a payment through Stripe.
	 *
	 * @param array $payment_data Payment data.
	 * @return array Payment result.
	 */
	public function process_payment( $payment_data ) {
		if ( empty( $this->api_key ) ) {
			return array(
				'success' => false,
				'error'   => esc_html__( 'Stripe API key not configured.', 'wpmatch' ),
			);
		}

		// Convert amount to cents (Stripe requirement).
		$amount_cents = round( $payment_data['amount'] * 100 );

		// Prepare Stripe payment intent.
		$payment_intent_data = array(
			'amount'               => $amount_cents,
			'currency'             => strtolower( $payment_data['currency'] ),
			'automatic_payment_methods' => array(
				'enabled' => true,
			),
			'metadata'             => array(
				'user_id'      => $payment_data['user_id'],
				'subscription_id' => $payment_data['subscription_id'] ?? '',
				'source'       => 'wpmatch',
			),
		);

		// Add payment method if provided.
		if ( ! empty( $payment_data['payment_method'] ) ) {
			$payment_intent_data['payment_method'] = $payment_data['payment_method'];
			$payment_intent_data['confirmation_method'] = 'manual';
			$payment_intent_data['confirm'] = true;
		}

		try {
			// Make API call to Stripe.
			$response = $this->make_stripe_request( 'payment_intents', $payment_intent_data );

			if ( $response && $response['status'] === 'succeeded' ) {
				return array(
					'success'        => true,
					'transaction_id' => $response['id'],
					'amount'         => $payment_data['amount'],
					'currency'       => $payment_data['currency'],
					'gateway_data'   => $response,
				);
			} elseif ( $response && $response['status'] === 'requires_action' ) {
				// 3D Secure or other authentication required.
				return array(
					'success'           => false,
					'requires_action'   => true,
					'client_secret'     => $response['client_secret'],
					'error'             => esc_html__( 'Payment requires additional authentication.', 'wpmatch' ),
				);
			} else {
				return array(
					'success' => false,
					'error'   => $response['error']['message'] ?? esc_html__( 'Payment failed.', 'wpmatch' ),
				);
			}
		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Create a payment token for recurring payments.
	 *
	 * @param array $payment_method Payment method data.
	 * @param int   $user_id User ID.
	 * @return array Token result.
	 */
	public function create_token( $payment_method, $user_id ) {
		if ( empty( $this->api_key ) ) {
			return array(
				'success' => false,
				'error'   => esc_html__( 'Stripe API key not configured.', 'wpmatch' ),
			);
		}

		try {
			// Create or retrieve Stripe customer.
			$customer_id = $this->get_or_create_stripe_customer( $user_id );

			if ( ! $customer_id ) {
				return array(
					'success' => false,
					'error'   => esc_html__( 'Failed to create Stripe customer.', 'wpmatch' ),
				);
			}

			// Attach payment method to customer.
			$attach_data = array(
				'customer' => $customer_id,
			);

			$response = $this->make_stripe_request(
				'payment_methods/' . $payment_method['id'] . '/attach',
				$attach_data,
				'POST'
			);

			if ( $response && $response['id'] ) {
				// Store payment method reference.
				update_user_meta( $user_id, '_wpmatch_stripe_payment_method', $response['id'] );
				update_user_meta( $user_id, '_wpmatch_stripe_customer_id', $customer_id );

				return array(
					'success' => true,
					'token'   => $response['id'],
					'details' => array(
						'type'    => $response['type'],
						'last4'   => $response['card']['last4'] ?? '',
						'expires' => ( $response['card']['exp_month'] ?? '' ) . '/' . ( $response['card']['exp_year'] ?? '' ),
					),
				);
			}

			return array(
				'success' => false,
				'error'   => esc_html__( 'Failed to save payment method.', 'wpmatch' ),
			);
		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Process a refund through Stripe.
	 *
	 * @param string $transaction_id Original transaction ID.
	 * @param float  $amount Refund amount.
	 * @param string $reason Refund reason.
	 * @return array Refund result.
	 */
	public function process_refund( $transaction_id, $amount, $reason = '' ) {
		if ( empty( $this->api_key ) ) {
			return array(
				'success' => false,
				'error'   => esc_html__( 'Stripe API key not configured.', 'wpmatch' ),
			);
		}

		$refund_data = array(
			'payment_intent' => $transaction_id,
			'amount'         => round( $amount * 100 ), // Convert to cents.
		);

		if ( ! empty( $reason ) ) {
			$refund_data['reason'] = 'requested_by_customer';
			$refund_data['metadata'] = array( 'reason' => $reason );
		}

		try {
			$response = $this->make_stripe_request( 'refunds', $refund_data );

			if ( $response && $response['status'] === 'succeeded' ) {
				return array(
					'success'   => true,
					'refund_id' => $response['id'],
					'amount'    => $amount,
					'status'    => $response['status'],
				);
			}

			return array(
				'success' => false,
				'error'   => $response['error']['message'] ?? esc_html__( 'Refund failed.', 'wpmatch' ),
			);
		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Get or create Stripe customer for user.
	 *
	 * @param int $user_id User ID.
	 * @return string|false Customer ID or false on failure.
	 */
	private function get_or_create_stripe_customer( $user_id ) {
		// Check if customer already exists.
		$customer_id = get_user_meta( $user_id, '_wpmatch_stripe_customer_id', true );

		if ( $customer_id ) {
			return $customer_id;
		}

		// Create new customer.
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}

		$customer_data = array(
			'email'    => $user->user_email,
			'name'     => $user->display_name,
			'metadata' => array(
				'wp_user_id' => $user_id,
				'source'     => 'wpmatch',
			),
		);

		try {
			$response = $this->make_stripe_request( 'customers', $customer_data );

			if ( $response && $response['id'] ) {
				update_user_meta( $user_id, '_wpmatch_stripe_customer_id', $response['id'] );
				return $response['id'];
			}
		} catch ( Exception $e ) {
			// Log error but don't expose details.
			error_log( 'Stripe customer creation failed: ' . $e->getMessage() );
		}

		return false;
	}

	/**
	 * Make a request to Stripe API.
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $data Request data.
	 * @param string $method HTTP method.
	 * @return array|false Response or false on failure.
	 */
	private function make_stripe_request( $endpoint, $data = array(), $method = 'POST' ) {
		$url = 'https://api.stripe.com/v1/' . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
			'body'    => http_build_query( $data ),
			'timeout' => 30,
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new Exception( $response->get_error_message() );
		}

		$body = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $body, true );

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code >= 400 ) {
			$error_message = $decoded['error']['message'] ?? 'Unknown Stripe error';
			throw new Exception( $error_message );
		}

		return $decoded;
	}
}