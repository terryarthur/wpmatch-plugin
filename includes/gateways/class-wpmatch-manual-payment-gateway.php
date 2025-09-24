<?php
/**
 * WPMatch Manual Payment Gateway
 *
 * Free gateway for testing and manual payment processing.
 *
 * @package WPMatch
 * @since   1.0.0
 */

// Security check.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manual Payment Gateway Class
 *
 * Processes manual/test payments without external payment processing.
 */
class WPMatch_Manual_Payment_Gateway {

	/**
	 * Gateway configuration.
	 *
	 * @var array
	 */
	private $config;

	/**
	 * Constructor.
	 *
	 * @param array $config Gateway configuration.
	 */
	public function __construct( $config ) {
		$this->config = $config;
	}

	/**
	 * Process a payment.
	 *
	 * @param array $payment_data Payment data.
	 * @return array Payment result.
	 */
	public function process_payment( $payment_data ) {
		// Manual gateway always succeeds for testing purposes.
		return array(
			'success'        => true,
			'transaction_id' => 'manual_' . uniqid(),
			'amount'         => $payment_data['amount'],
			'currency'       => $payment_data['currency'],
			'gateway_data'   => array(
				'method' => 'manual',
				'note'   => 'Manual payment processed for testing',
			),
		);
	}

	/**
	 * Create payment token (not supported by manual gateway).
	 *
	 * @param array $payment_method Payment method data.
	 * @param int   $user_id User ID.
	 * @return array Token result.
	 */
	public function create_token( $payment_method, $user_id ) {
		return array(
			'success' => false,
			'error'   => esc_html__( 'Tokenization not supported by manual gateway.', 'wpmatch' ),
		);
	}

	/**
	 * Process refund (simulated for manual gateway).
	 *
	 * @param string $transaction_id Original transaction ID.
	 * @param float  $amount Refund amount.
	 * @param string $reason Refund reason.
	 * @return array Refund result.
	 */
	public function process_refund( $transaction_id, $amount, $reason = '' ) {
		// Manual gateway simulates successful refund.
		return array(
			'success'   => true,
			'refund_id' => 'refund_' . uniqid(),
			'amount'    => $amount,
			'reason'    => $reason,
		);
	}
}