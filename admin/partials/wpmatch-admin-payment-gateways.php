<?php
/**
 * WPMatch Admin Payment Gateways Page
 *
 * Template for the payment gateways admin page.
 *
 * @package WPMatch
 * @since   1.0.0
 */

// Security check.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap wpmatch-admin-page">
	<div class="wpmatch-admin-header">
		<h1>
			<span class="dashicons dashicons-money-alt wpmatch-header-icon"></span>
			<?php esc_html_e( 'Payment Gateways', 'wpmatch' ); ?>
		</h1>
		<p class="wpmatch-page-description">
			<?php esc_html_e( 'Configure payment gateways for processing subscription payments. Premium gateways require individual licenses or the Pro bundle.', 'wpmatch' ); ?>
		</p>
	</div>

	<div class="wpmatch-admin-content">
		<!-- Gateway Configuration Section -->
		<div class="wpmatch-card">
			<div class="wpmatch-card-header">
				<h2>
					<span class="dashicons dashicons-admin-settings"></span>
					<?php esc_html_e( 'Available Payment Gateways', 'wpmatch' ); ?>
				</h2>
			</div>
			<div class="wpmatch-card-content">
				<div class="wpmatch-gateways-list">
					<?php foreach ( $gateways as $gateway_id => $gateway ) : ?>
					<?php
					$is_premium = ! empty( $gateway['required_feature'] );
					$is_licensed = $is_premium ? WPMatch_Admin_License_Manager::has_admin_feature( $gateway['required_feature'] ) : true;
					$is_enabled = ! empty( $gateway['enabled'] ) && $is_licensed;
					?>
					<div class="wpmatch-gateway-item <?php echo $is_enabled ? 'enabled' : 'disabled'; ?> <?php echo $is_premium ? 'premium' : 'free'; ?>">
						<div class="gateway-header">
							<div class="gateway-info">
								<h3><?php echo esc_html( $gateway['name'] ); ?></h3>
								<p><?php echo esc_html( $gateway['description'] ); ?></p>
								<div class="gateway-features">
									<strong><?php esc_html_e( 'Supports:', 'wpmatch' ); ?></strong>
									<?php foreach ( $gateway['supports'] as $feature ) : ?>
									<span class="feature-badge"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $feature ) ) ); ?></span>
									<?php endforeach; ?>
								</div>
							</div>
							<div class="gateway-status">
								<?php if ( $is_premium && ! $is_licensed ) : ?>
								<span class="status-badge unlicensed">
									<span class="dashicons dashicons-lock"></span>
									<?php esc_html_e( 'Requires License', 'wpmatch' ); ?>
								</span>
								<?php elseif ( $is_enabled ) : ?>
								<span class="status-badge enabled">
									<span class="dashicons dashicons-yes-alt"></span>
									<?php esc_html_e( 'Enabled', 'wpmatch' ); ?>
								</span>
								<?php else : ?>
								<span class="status-badge disabled">
									<span class="dashicons dashicons-dismiss"></span>
									<?php esc_html_e( 'Disabled', 'wpmatch' ); ?>
								</span>
								<?php endif; ?>
							</div>
						</div>

						<div class="gateway-actions">
							<?php if ( $is_premium && ! $is_licensed ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpmatch-marketplace' ) ); ?>"
							   class="wpmatch-button primary">
								<span class="dashicons dashicons-cart"></span>
								<?php esc_html_e( 'Purchase License', 'wpmatch' ); ?>
							</a>
							<?php else : ?>
							<div class="gateway-toggle">
								<label class="toggle-switch">
									<input type="checkbox"
										   class="gateway-toggle-input"
										   data-gateway="<?php echo esc_attr( $gateway_id ); ?>"
										   <?php checked( $is_enabled ); ?>>
									<span class="toggle-slider"></span>
								</label>
								<span class="toggle-label">
									<?php echo $is_enabled ? esc_html__( 'Enabled', 'wpmatch' ) : esc_html__( 'Disabled', 'wpmatch' ); ?>
								</span>
							</div>
							<button type="button"
									class="wpmatch-button secondary configure-gateway-btn"
									data-gateway="<?php echo esc_attr( $gateway_id ); ?>"
									<?php disabled( ! $is_licensed ); ?>>
								<span class="dashicons dashicons-admin-generic"></span>
								<?php esc_html_e( 'Configure', 'wpmatch' ); ?>
							</button>
							<?php endif; ?>
						</div>

						<!-- Gateway Configuration Panel (Hidden by default) -->
						<?php if ( $is_licensed ) : ?>
						<div class="gateway-config" id="config-<?php echo esc_attr( $gateway_id ); ?>" style="display: none;">
							<form class="gateway-settings-form" data-gateway="<?php echo esc_attr( $gateway_id ); ?>">
								<?php wp_nonce_field( 'wpmatch_gateway_settings', 'gateway_nonce' ); ?>

								<?php if ( $gateway_id === 'stripe' ) : ?>
								<!-- Stripe Configuration -->
								<div class="setting-group">
									<label for="<?php echo esc_attr( $gateway_id ); ?>_test_mode">
										<input type="checkbox"
											   id="<?php echo esc_attr( $gateway_id ); ?>_test_mode"
											   name="test_mode"
											   value="1">
										<?php esc_html_e( 'Enable Test Mode', 'wpmatch' ); ?>
									</label>
								</div>
								<div class="setting-group">
									<label for="<?php echo esc_attr( $gateway_id ); ?>_test_publishable_key">
										<?php esc_html_e( 'Test Publishable Key', 'wpmatch' ); ?>
									</label>
									<input type="text"
										   id="<?php echo esc_attr( $gateway_id ); ?>_test_publishable_key"
										   name="test_publishable_key"
										   class="regular-text">
								</div>
								<div class="setting-group">
									<label for="<?php echo esc_attr( $gateway_id ); ?>_test_secret_key">
										<?php esc_html_e( 'Test Secret Key', 'wpmatch' ); ?>
									</label>
									<input type="password"
										   id="<?php echo esc_attr( $gateway_id ); ?>_test_secret_key"
										   name="test_secret_key"
										   class="regular-text">
								</div>
								<div class="setting-group">
									<label for="<?php echo esc_attr( $gateway_id ); ?>_live_publishable_key">
										<?php esc_html_e( 'Live Publishable Key', 'wpmatch' ); ?>
									</label>
									<input type="text"
										   id="<?php echo esc_attr( $gateway_id ); ?>_live_publishable_key"
										   name="live_publishable_key"
										   class="regular-text">
								</div>
								<div class="setting-group">
									<label for="<?php echo esc_attr( $gateway_id ); ?>_live_secret_key">
										<?php esc_html_e( 'Live Secret Key', 'wpmatch' ); ?>
									</label>
									<input type="password"
										   id="<?php echo esc_attr( $gateway_id ); ?>_live_secret_key"
										   name="live_secret_key"
										   class="regular-text">
								</div>

								<?php elseif ( $gateway_id === 'paypal' ) : ?>
								<!-- PayPal Configuration -->
								<div class="setting-group">
									<label for="<?php echo esc_attr( $gateway_id ); ?>_sandbox_mode">
										<input type="checkbox"
											   id="<?php echo esc_attr( $gateway_id ); ?>_sandbox_mode"
											   name="sandbox_mode"
											   value="1">
										<?php esc_html_e( 'Enable Sandbox Mode', 'wpmatch' ); ?>
									</label>
								</div>
								<div class="setting-group">
									<label for="<?php echo esc_attr( $gateway_id ); ?>_client_id">
										<?php esc_html_e( 'PayPal Client ID', 'wpmatch' ); ?>
									</label>
									<input type="text"
										   id="<?php echo esc_attr( $gateway_id ); ?>_client_id"
										   name="client_id"
										   class="regular-text">
								</div>
								<div class="setting-group">
									<label for="<?php echo esc_attr( $gateway_id ); ?>_client_secret">
										<?php esc_html_e( 'PayPal Client Secret', 'wpmatch' ); ?>
									</label>
									<input type="password"
										   id="<?php echo esc_attr( $gateway_id ); ?>_client_secret"
										   name="client_secret"
										   class="regular-text">
								</div>

								<?php elseif ( $gateway_id === 'manual' ) : ?>
								<!-- Manual Gateway Configuration -->
								<div class="setting-group">
									<label for="<?php echo esc_attr( $gateway_id ); ?>_instructions">
										<?php esc_html_e( 'Payment Instructions', 'wpmatch' ); ?>
									</label>
									<textarea id="<?php echo esc_attr( $gateway_id ); ?>_instructions"
											  name="instructions"
											  rows="4"
											  class="large-text"
											  placeholder="<?php esc_attr_e( 'Enter instructions for manual payments...', 'wpmatch' ); ?>"></textarea>
								</div>
								<?php endif; ?>

								<div class="setting-actions">
									<button type="submit" class="wpmatch-button primary">
										<span class="dashicons dashicons-saved"></span>
										<?php esc_html_e( 'Save Settings', 'wpmatch' ); ?>
									</button>
									<button type="button" class="wpmatch-button secondary cancel-config-btn">
										<?php esc_html_e( 'Cancel', 'wpmatch' ); ?>
									</button>
								</div>
							</form>
						</div>
						<?php endif; ?>
					</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>

		<!-- Gateway Testing Section -->
		<div class="wpmatch-card">
			<div class="wpmatch-card-header">
				<h2>
					<span class="dashicons dashicons-yes-alt"></span>
					<?php esc_html_e( 'Gateway Testing', 'wpmatch' ); ?>
				</h2>
			</div>
			<div class="wpmatch-card-content">
				<div class="gateway-testing">
					<p><?php esc_html_e( 'Test your payment gateway configuration with a small test transaction.', 'wpmatch' ); ?></p>
					<form id="gateway-test-form">
						<?php wp_nonce_field( 'wpmatch_test_payment', 'test_nonce' ); ?>
						<div class="test-form-row">
							<div class="form-field">
								<label for="test_gateway"><?php esc_html_e( 'Gateway', 'wpmatch' ); ?></label>
								<select id="test_gateway" name="gateway">
									<option value=""><?php esc_html_e( 'Select Gateway', 'wpmatch' ); ?></option>
									<?php foreach ( $gateways as $gateway_id => $gateway ) : ?>
									<?php if ( ! empty( $gateway['enabled'] ) ) : ?>
									<option value="<?php echo esc_attr( $gateway_id ); ?>">
										<?php echo esc_html( $gateway['name'] ); ?>
									</option>
									<?php endif; ?>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="form-field">
								<label for="test_amount"><?php esc_html_e( 'Amount', 'wpmatch' ); ?></label>
								<input type="number"
									   id="test_amount"
									   name="amount"
									   value="1.00"
									   step="0.01"
									   min="0.01"
									   class="small-text">
							</div>
							<div class="form-field">
								<label for="test_currency"><?php esc_html_e( 'Currency', 'wpmatch' ); ?></label>
								<select id="test_currency" name="currency">
									<option value="USD">USD</option>
									<option value="EUR">EUR</option>
									<option value="GBP">GBP</option>
									<option value="CAD">CAD</option>
									<option value="AUD">AUD</option>
								</select>
							</div>
						</div>
						<button type="submit" class="wpmatch-button primary">
							<span class="dashicons dashicons-clock"></span>
							<?php esc_html_e( 'Run Test Payment', 'wpmatch' ); ?>
						</button>
					</form>
					<div id="test-results" style="display: none;"></div>
				</div>
			</div>
		</div>

		<!-- Premium Gateways Promotion -->
		<div class="wpmatch-card">
			<div class="wpmatch-card-header">
				<h2>
					<span class="dashicons dashicons-star-filled"></span>
					<?php esc_html_e( 'Premium Payment Gateways', 'wpmatch' ); ?>
				</h2>
			</div>
			<div class="wpmatch-card-content">
				<div class="premium-gateways-promo">
					<p><?php esc_html_e( 'Unlock professional payment processing with our premium gateway addons:', 'wpmatch' ); ?></p>
					<div class="premium-features-list">
						<ul>
							<li><strong><?php esc_html_e( 'Stripe Gateway', 'wpmatch' ); ?></strong> - Credit card processing with 3D Secure support</li>
							<li><strong><?php esc_html_e( 'PayPal Gateway', 'wpmatch' ); ?></strong> - PayPal payments with subscription support</li>
							<li><strong><?php esc_html_e( 'Automatic Renewals', 'wpmatch' ); ?></strong> - Seamless recurring payment processing</li>
							<li><strong><?php esc_html_e( 'Refund Management', 'wpmatch' ); ?></strong> - Process refunds directly from admin</li>
							<li><strong><?php esc_html_e( 'PCI Compliance', 'wpmatch' ); ?></strong> - Secure tokenized payment storage</li>
						</ul>
					</div>
					<div class="promo-actions">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpmatch-marketplace' ) ); ?>"
						   class="wpmatch-button primary large">
							<span class="dashicons dashicons-cart"></span>
							<?php esc_html_e( 'Browse Premium Gateways', 'wpmatch' ); ?>
						</a>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// Handle gateway enable/disable toggles
	$('.gateway-toggle-input').on('change', function() {
		const $this = $(this);
		const gateway = $this.data('gateway');
		const enabled = $this.is(':checked');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpmatch_toggle_gateway',
				nonce: '<?php echo esc_js( wp_create_nonce( 'wpmatch_gateway_nonce' ) ); ?>',
				gateway_id: gateway,
				enabled: enabled
			},
			success: function(response) {
				if (response.success) {
					$this.closest('.wpmatch-gateway-item').toggleClass('enabled disabled');
					const $label = $this.closest('.gateway-toggle').find('.toggle-label');
					$label.text(enabled ? 'Enabled' : 'Disabled');
				} else {
					// Revert toggle
					$this.prop('checked', !enabled);
					alert('Error: ' + response.data);
				}
			},
			error: function() {
				// Revert toggle
				$this.prop('checked', !enabled);
				alert('An error occurred. Please try again.');
			}
		});
	});

	// Handle gateway configuration
	$('.configure-gateway-btn').on('click', function() {
		const gateway = $(this).data('gateway');
		const $config = $('#config-' + gateway);

		if ($config.is(':visible')) {
			$config.slideUp();
			$(this).find('.dashicons').removeClass('dashicons-arrow-up').addClass('dashicons-admin-generic');
		} else {
			$('.gateway-config').slideUp(); // Close others
			$('.configure-gateway-btn .dashicons').removeClass('dashicons-arrow-up').addClass('dashicons-admin-generic');
			$config.slideDown();
			$(this).find('.dashicons').removeClass('dashicons-admin-generic').addClass('dashicons-arrow-up');
		}
	});

	// Handle configuration form submission
	$('.gateway-settings-form').on('submit', function(e) {
		e.preventDefault();

		const $form = $(this);
		const gateway = $form.data('gateway');
		const formData = $form.serialize();

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpmatch_save_gateway_settings',
				gateway_id: gateway,
				settings: formData
			},
			success: function(response) {
				if (response.success) {
					// Show success message
					const message = $('<div class="notice notice-success is-dismissible"><p>Gateway settings saved successfully!</p></div>');
					$('.wpmatch-admin-header').after(message);
					setTimeout(function() {
						message.fadeOut();
					}, 3000);
				} else {
					alert('Error: ' + response.data);
				}
			},
			error: function() {
				alert('An error occurred while saving settings.');
			}
		});
	});

	// Handle cancel configuration
	$('.cancel-config-btn').on('click', function() {
		$(this).closest('.gateway-config').slideUp();
		$('.configure-gateway-btn .dashicons').removeClass('dashicons-arrow-up').addClass('dashicons-admin-generic');
	});

	// Handle test payment
	$('#gateway-test-form').on('submit', function(e) {
		e.preventDefault();

		const $form = $(this);
		const $results = $('#test-results');
		const formData = $form.serialize();

		$results.html('<div class="test-loading">Running test payment...</div>').show();

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpmatch_test_payment',
				test_data: formData
			},
			success: function(response) {
				if (response.success) {
					$results.html('<div class="test-success">✓ Test payment successful! Transaction ID: ' + response.data.transaction_id + '</div>');
				} else {
					$results.html('<div class="test-error">✗ Test payment failed: ' + response.data + '</div>');
				}
			},
			error: function() {
				$results.html('<div class="test-error">✗ Test payment failed: Network error</div>');
			}
		});
	});
});
</script>