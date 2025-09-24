<?php
/**
 * WPMatch Admin Marketplace Page
 *
 * Template for the feature marketplace admin page.
 *
 * @package WPMatch
 * @since   1.0.0
 */

// Security check.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user_tiers = array(
	'free'    => esc_html__( 'Free', 'wpmatch' ),
	'premium' => esc_html__( 'Premium', 'wpmatch' ),
	'vip'     => esc_html__( 'VIP', 'wpmatch' ),
);
?>

<div class="wrap wpmatch-admin-page">
	<div class="wpmatch-admin-header">
		<h1>
			<span class="dashicons dashicons-cart wpmatch-header-icon"></span>
			<?php esc_html_e( 'Feature Marketplace', 'wpmatch' ); ?>
		</h1>
		<p class="wpmatch-page-description">
			<?php esc_html_e( 'Purchase and manage premium features for your dating site. Licensed features can be assigned to different user membership tiers.', 'wpmatch' ); ?>
		</p>
	</div>

	<div class="wpmatch-admin-content">
		<!-- Licensed Features Section -->
		<?php if ( ! empty( $licensed_features ) ) : ?>
		<div class="wpmatch-card">
			<div class="wpmatch-card-header">
				<h2>
					<span class="dashicons dashicons-yes-alt"></span>
					<?php esc_html_e( 'Your Licensed Features', 'wpmatch' ); ?>
				</h2>
			</div>
			<div class="wpmatch-card-content">
				<div class="wpmatch-licensed-features">
					<?php foreach ( $licensed_features as $slug => $feature ) : ?>
					<div class="wpmatch-licensed-feature">
						<div class="feature-info">
							<h3><?php echo esc_html( $feature['name'] ); ?></h3>
							<p><?php echo esc_html( $feature['description'] ); ?></p>
							<div class="feature-meta">
								<span class="feature-status status-<?php echo esc_attr( $feature['status'] ); ?>">
									<?php echo esc_html( ucfirst( $feature['status'] ) ); ?>
								</span>
								<?php if ( $feature['expires_at'] ) : ?>
								<span class="feature-expiry">
									<?php
									printf(
										/* translators: %s: Expiry date */
										esc_html__( 'Expires: %s', 'wpmatch' ),
										esc_html( date_i18n( get_option( 'date_format' ), strtotime( $feature['expires_at'] ) ) )
									);
									?>
								</span>
								<?php endif; ?>
							</div>
						</div>
						<div class="feature-assignments">
							<h4><?php esc_html_e( 'User Tier Access', 'wpmatch' ); ?></h4>
							<div class="tier-toggles">
								<?php foreach ( $user_tiers as $tier_slug => $tier_name ) : ?>
								<?php
								$is_enabled = isset( $feature_matrix[ $slug ][ $tier_slug ] ) && $feature_matrix[ $slug ][ $tier_slug ]['enabled'];
								?>
								<label class="tier-toggle">
									<input type="checkbox"
										   class="feature-assignment-toggle"
										   data-feature="<?php echo esc_attr( $slug ); ?>"
										   data-tier="<?php echo esc_attr( $tier_slug ); ?>"
										   <?php checked( $is_enabled ); ?>>
									<span class="tier-name"><?php echo esc_html( $tier_name ); ?></span>
								</label>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<!-- Available Features Section -->
		<div class="wpmatch-card">
			<div class="wpmatch-card-header">
				<h2>
					<span class="dashicons dashicons-store"></span>
					<?php esc_html_e( 'Available Premium Features', 'wpmatch' ); ?>
				</h2>
			</div>
			<div class="wpmatch-card-content">
				<div class="wpmatch-features-grid">
					<?php foreach ( $available_features as $slug => $feature ) : ?>
					<?php $is_licensed = isset( $licensed_features[ $slug ] ); ?>
					<div class="wpmatch-feature-card <?php echo $is_licensed ? 'licensed' : 'available'; ?>">
						<div class="feature-header">
							<h3><?php echo esc_html( $feature['name'] ); ?></h3>
							<div class="feature-price">
								<?php if ( $is_licensed ) : ?>
								<span class="licensed-badge">
									<span class="dashicons dashicons-yes-alt"></span>
									<?php esc_html_e( 'Licensed', 'wpmatch' ); ?>
								</span>
								<?php else : ?>
								<span class="price-badge">$<?php echo esc_html( $feature['price'] ); ?></span>
								<?php endif; ?>
							</div>
						</div>
						<div class="feature-content">
							<p><?php echo esc_html( $feature['description'] ); ?></p>

							<?php if ( ! empty( $feature['dependencies'] ) ) : ?>
							<div class="feature-dependencies">
								<strong><?php esc_html_e( 'Requires:', 'wpmatch' ); ?></strong>
								<ul>
									<?php foreach ( $feature['dependencies'] as $dep_slug ) : ?>
									<?php $dep_feature = $available_features[ $dep_slug ] ?? null; ?>
									<?php if ( $dep_feature ) : ?>
									<li>
										<?php echo esc_html( $dep_feature['name'] ); ?>
										<?php if ( isset( $licensed_features[ $dep_slug ] ) ) : ?>
										<span class="dashicons dashicons-yes-alt dependency-met"></span>
										<?php else : ?>
										<span class="dashicons dashicons-warning dependency-missing"></span>
										<?php endif; ?>
									</li>
									<?php endif; ?>
									<?php endforeach; ?>
								</ul>
							</div>
							<?php endif; ?>
						</div>
						<div class="feature-actions">
							<?php if ( $is_licensed ) : ?>
							<button type="button" class="wpmatch-button secondary" disabled>
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'Already Licensed', 'wpmatch' ); ?>
							</button>
							<?php else : ?>
							<button type="button"
									class="wpmatch-button primary purchase-feature-btn"
									data-feature="<?php echo esc_attr( $slug ); ?>">
								<span class="dashicons dashicons-cart"></span>
								<?php esc_html_e( 'Purchase Feature', 'wpmatch' ); ?>
							</button>
							<?php endif; ?>
						</div>
					</div>
					<?php endforeach; ?>
				</div>

				<!-- Pro Bundle Offer -->
				<div class="wpmatch-pro-bundle">
					<div class="bundle-header">
						<h3>
							<span class="dashicons dashicons-star-filled"></span>
							<?php esc_html_e( 'WPMatch Pro Bundle', 'wpmatch' ); ?>
						</h3>
						<div class="bundle-savings">
							<?php
							$total_individual = array_sum( array_column( $available_features, 'price' ) );
							$bundle_price = 199;
							$savings = $total_individual - $bundle_price;
							$savings_percent = round( ( $savings / $total_individual ) * 100 );
							?>
							<span class="original-price">$<?php echo esc_html( $total_individual ); ?></span>
							<span class="bundle-price">$<?php echo esc_html( $bundle_price ); ?></span>
							<span class="savings-badge"><?php echo esc_html( $savings_percent ); ?>% Off</span>
						</div>
					</div>
					<div class="bundle-content">
						<p><?php esc_html_e( 'Get all premium features at a significant discount. Perfect for professional dating sites that want the complete feature set.', 'wpmatch' ); ?></p>
						<div class="bundle-features">
							<h4><?php esc_html_e( 'Included Features:', 'wpmatch' ); ?></h4>
							<ul>
								<?php foreach ( $available_features as $feature ) : ?>
								<li><?php echo esc_html( $feature['name'] ); ?></li>
								<?php endforeach; ?>
							</ul>
						</div>
						<button type="button" class="wpmatch-button primary large purchase-bundle-btn">
							<span class="dashicons dashicons-cart"></span>
							<?php esc_html_e( 'Purchase Pro Bundle', 'wpmatch' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Freemius Integration Notice -->
		<div class="wpmatch-card">
			<div class="wpmatch-card-header">
				<h2>
					<span class="dashicons dashicons-info"></span>
					<?php esc_html_e( 'About Premium Features', 'wpmatch' ); ?>
				</h2>
			</div>
			<div class="wpmatch-card-content">
				<div class="freemius-info">
					<p><?php esc_html_e( 'Premium features are sold and managed through Freemius, ensuring secure licensing and automatic updates. All purchases include:', 'wpmatch' ); ?></p>
					<ul>
						<li><?php esc_html_e( '1 year of updates and support', 'wpmatch' ); ?></li>
						<li><?php esc_html_e( 'Automatic license validation', 'wpmatch' ); ?></li>
						<li><?php esc_html_e( 'Secure payment processing', 'wpmatch' ); ?></li>
						<li><?php esc_html_e( '30-day money-back guarantee', 'wpmatch' ); ?></li>
					</ul>
				</div>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// Handle feature assignment toggles
	$('.feature-assignment-toggle').on('change', function() {
		const $this = $(this);
		const feature = $this.data('feature');
		const tier = $this.data('tier');
		const enabled = $this.is(':checked');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpmatch_toggle_feature_assignment',
				nonce: '<?php echo esc_js( wp_create_nonce( 'wpmatch_admin_nonce' ) ); ?>',
				feature_slug: feature,
				user_tier: tier,
				enabled: enabled
			},
			success: function(response) {
				if (response.success) {
					// Show success message
					const message = $('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
					$('.wpmatch-admin-header').after(message);
					setTimeout(function() {
						message.fadeOut();
					}, 3000);
				} else {
					// Revert checkbox
					$this.prop('checked', !enabled);
					alert('Error: ' + response.data);
				}
			},
			error: function() {
				// Revert checkbox
				$this.prop('checked', !enabled);
				alert('An error occurred. Please try again.');
			}
		});
	});

	// Handle feature purchase
	$('.purchase-feature-btn').on('click', function() {
		const feature = $(this).data('feature');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpmatch_purchase_feature',
				nonce: '<?php echo esc_js( wp_create_nonce( 'wpmatch_admin_nonce' ) ); ?>',
				feature_slug: feature
			},
			success: function(response) {
				if (response.success) {
					// Redirect to Freemius
					window.open(response.data.purchase_url, '_blank');
				} else {
					alert('Error: ' + response.data);
				}
			},
			error: function() {
				alert('An error occurred. Please try again.');
			}
		});
	});

	// Handle Pro bundle purchase
	$('.purchase-bundle-btn').on('click', function() {
		// This would redirect to Freemius Pro bundle page
		const bundleUrl = '<?php echo esc_url( admin_url( "admin.php?page=wpmatch-marketplace&action=purchase&bundle=pro" ) ); ?>';
		window.open(bundleUrl, '_blank');
	});
});
</script>