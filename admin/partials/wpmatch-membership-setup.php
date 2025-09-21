<?php
/**
 * WPMatch Membership Setup Page
 *
 * Provides interface for creating and managing WooCommerce membership products.
 *
 * @package WPMatch
 */

// Security check.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check WooCommerce is active.
if ( ! class_exists( 'WooCommerce' ) ) {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'WooCommerce is required for membership features. Please install and activate WooCommerce.', 'wpmatch' ); ?></p>
	</div>
	<?php
	return;
}

// Get existing membership products.
$membership_products = get_option( 'wpmatch_membership_products', array() );
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="wpmatch-membership-setup">
		<div class="card">
			<h2><?php esc_html_e( 'Quick Setup - Recommended Membership Tiers', 'wpmatch' ); ?></h2>
			<p><?php esc_html_e( 'Click the button below to automatically create recommended membership tiers in WooCommerce.', 'wpmatch' ); ?></p>

			<form method="post" action="">
				<?php wp_nonce_field( 'wpmatch_setup_memberships', 'wpmatch_setup_nonce' ); ?>
				<input type="hidden" name="action" value="setup_default_memberships">

				<table class="form-table">
					<tr>
						<th colspan="2">
							<h3><?php esc_html_e( 'Membership Tiers to Create:', 'wpmatch' ); ?></h3>
						</th>
					</tr>

					<!-- Basic Membership -->
					<tr>
						<td style="width: 30%;">
							<label>
								<input type="checkbox" name="create_basic" value="1" checked>
								<strong><?php esc_html_e( 'Basic Membership', 'wpmatch' ); ?></strong>
							</label>
						</td>
						<td>
							<input type="text" name="basic_price" value="9.99" size="10" />
							<?php echo esc_html( get_woocommerce_currency_symbol() ); ?> / <?php esc_html_e( 'month', 'wpmatch' ); ?>
							<p class="description">
								<?php esc_html_e( 'Features: 50 daily likes, Basic search filters, Standard messaging', 'wpmatch' ); ?>
							</p>
						</td>
					</tr>

					<!-- Gold Membership -->
					<tr>
						<td>
							<label>
								<input type="checkbox" name="create_gold" value="1" checked>
								<strong><?php esc_html_e( 'Gold Membership', 'wpmatch' ); ?></strong>
							</label>
						</td>
						<td>
							<input type="text" name="gold_price" value="19.99" size="10" />
							<?php echo esc_html( get_woocommerce_currency_symbol() ); ?> / <?php esc_html_e( 'month', 'wpmatch' ); ?>
							<p class="description">
								<?php esc_html_e( 'Features: 200 daily likes, See who liked you, Advanced search, Profile visitors', 'wpmatch' ); ?>
							</p>
						</td>
					</tr>

					<!-- Platinum Membership -->
					<tr>
						<td>
							<label>
								<input type="checkbox" name="create_platinum" value="1" checked>
								<strong><?php esc_html_e( 'Platinum Membership', 'wpmatch' ); ?></strong>
							</label>
						</td>
						<td>
							<input type="text" name="platinum_price" value="39.99" size="10" />
							<?php echo esc_html( get_woocommerce_currency_symbol() ); ?> / <?php esc_html_e( 'month', 'wpmatch' ); ?>
							<p class="description">
								<?php esc_html_e( 'Features: Unlimited likes, Priority visibility, Read receipts, Profile boost, Advanced analytics', 'wpmatch' ); ?>
							</p>
						</td>
					</tr>

					<!-- Billing Period -->
					<tr>
						<th scope="row"><?php esc_html_e( 'Billing Period', 'wpmatch' ); ?></th>
						<td>
							<select name="billing_period">
								<option value="month"><?php esc_html_e( 'Monthly', 'wpmatch' ); ?></option>
								<option value="year"><?php esc_html_e( 'Yearly', 'wpmatch' ); ?></option>
								<option value="week"><?php esc_html_e( 'Weekly', 'wpmatch' ); ?></option>
								<option value="day"><?php esc_html_e( 'Daily', 'wpmatch' ); ?></option>
							</select>
							<p class="description">
								<?php esc_html_e( 'How often members will be billed for their subscription.', 'wpmatch' ); ?>
							</p>
						</td>
					</tr>

					<!-- Trial Period -->
					<tr>
						<th scope="row"><?php esc_html_e( 'Free Trial', 'wpmatch' ); ?></th>
						<td>
							<input type="number" name="trial_days" value="7" min="0" max="90" />
							<?php esc_html_e( 'days', 'wpmatch' ); ?>
							<p class="description">
								<?php esc_html_e( 'Offer a free trial period for new members (0 for no trial).', 'wpmatch' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Create Membership Products', 'wpmatch' ); ?>
					</button>
				</p>
			</form>
		</div>

		<?php if ( ! empty( $membership_products ) ) : ?>
		<div class="card">
			<h2><?php esc_html_e( 'Existing Membership Products', 'wpmatch' ); ?></h2>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Product Name', 'wpmatch' ); ?></th>
						<th><?php esc_html_e( 'Price', 'wpmatch' ); ?></th>
						<th><?php esc_html_e( 'Type', 'wpmatch' ); ?></th>
						<th><?php esc_html_e( 'Status', 'wpmatch' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'wpmatch' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $membership_products as $product_id ) {
						$product = wc_get_product( $product_id );
						if ( ! $product ) {
							continue;
						}
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $product->get_name() ); ?></strong>
							</td>
							<td><?php echo wp_kses_post( $product->get_price_html() ); ?></td>
							<td>
								<?php
								if ( $product->is_type( 'subscription' ) ) {
									esc_html_e( 'Subscription', 'wpmatch' );
								} else {
									echo esc_html( ucfirst( $product->get_type() ) );
								}
								?>
							</td>
							<td>
								<?php
								if ( 'publish' === $product->get_status() ) {
									echo '<span style="color: green;">' . esc_html__( 'Active', 'wpmatch' ) . '</span>';
								} else {
									echo '<span style="color: orange;">' . esc_html( ucfirst( $product->get_status() ) ) . '</span>';
								}
								?>
							</td>
							<td>
								<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $product_id . '&action=edit' ) ); ?>" class="button button-small">
									<?php esc_html_e( 'Edit', 'wpmatch' ); ?>
								</a>
								<a href="<?php echo esc_url( $product->get_permalink() ); ?>" target="_blank" class="button button-small">
									<?php esc_html_e( 'View', 'wpmatch' ); ?>
								</a>
							</td>
						</tr>
						<?php
					}
					?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>

		<!-- Manual Configuration -->
		<div class="card">
			<h2><?php esc_html_e( 'Manual Configuration', 'wpmatch' ); ?></h2>
			<p><?php esc_html_e( 'You can also create membership products manually in WooCommerce:', 'wpmatch' ); ?></p>

			<ol>
				<li><?php esc_html_e( 'Go to Products → Add New in WooCommerce', 'wpmatch' ); ?></li>
				<li><?php esc_html_e( 'Set product type to "Simple" or "Subscription" (if using WooCommerce Subscriptions)', 'wpmatch' ); ?></li>
				<li><?php esc_html_e( 'Add product to "WPMatch Memberships" category', 'wpmatch' ); ?></li>
				<li><?php esc_html_e( 'Set the product as Virtual and Downloadable', 'wpmatch' ); ?></li>
				<li><?php esc_html_e( 'Add custom meta field: _wpmatch_membership_level (basic, gold, or platinum)', 'wpmatch' ); ?></li>
			</ol>

			<p>
				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>" class="button">
					<?php esc_html_e( 'Create Product Manually', 'wpmatch' ); ?>
				</a>
			</p>
		</div>

		<!-- Feature Comparison -->
		<div class="card">
			<h2><?php esc_html_e( 'Membership Features Comparison', 'wpmatch' ); ?></h2>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Feature', 'wpmatch' ); ?></th>
						<th><?php esc_html_e( 'Free', 'wpmatch' ); ?></th>
						<th><?php esc_html_e( 'Basic', 'wpmatch' ); ?></th>
						<th><?php esc_html_e( 'Gold', 'wpmatch' ); ?></th>
						<th><?php esc_html_e( 'Platinum', 'wpmatch' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><strong><?php esc_html_e( 'Daily Likes', 'wpmatch' ); ?></strong></td>
						<td>10</td>
						<td>50</td>
						<td>200</td>
						<td><?php esc_html_e( 'Unlimited', 'wpmatch' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'See Who Liked You', 'wpmatch' ); ?></strong></td>
						<td>❌</td>
						<td>❌</td>
						<td>✅</td>
						<td>✅</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Advanced Search Filters', 'wpmatch' ); ?></strong></td>
						<td>❌</td>
						<td>✅</td>
						<td>✅</td>
						<td>✅</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Profile Visitors', 'wpmatch' ); ?></strong></td>
						<td>❌</td>
						<td>❌</td>
						<td>✅</td>
						<td>✅</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Read Receipts', 'wpmatch' ); ?></strong></td>
						<td>❌</td>
						<td>❌</td>
						<td>❌</td>
						<td>✅</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Profile Boost', 'wpmatch' ); ?></strong></td>
						<td>❌</td>
						<td>❌</td>
						<td>Monthly</td>
						<td>Weekly</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Priority Support', 'wpmatch' ); ?></strong></td>
						<td>❌</td>
						<td>❌</td>
						<td>❌</td>
						<td>✅</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Profile Badge', 'wpmatch' ); ?></strong></td>
						<td>❌</td>
						<td>❌</td>
						<td>Gold</td>
						<td>Platinum</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>

<style>
.wpmatch-membership-setup .card {
	background: #fff;
	border: 1px solid #ccd0d4;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
	margin-top: 20px;
	padding: 20px;
}

.wpmatch-membership-setup .card h2 {
	margin-top: 0;
	padding-bottom: 10px;
	border-bottom: 1px solid #eee;
}

.wpmatch-membership-setup .form-table th {
	width: 200px;
}

.wpmatch-membership-setup .description {
	font-style: italic;
	color: #666;
}
</style>