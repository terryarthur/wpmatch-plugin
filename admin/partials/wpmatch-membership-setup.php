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

		<!-- Custom Tier Builder -->
		<div class="card">
			<h2><?php esc_html_e( 'Create Custom Membership Tier', 'wpmatch' ); ?></h2>
			<p><?php esc_html_e( 'Design your own membership tier with custom features and pricing.', 'wpmatch' ); ?></p>

			<form method="post" action="" id="custom-tier-form">
				<?php wp_nonce_field( 'wpmatch_create_custom_tier', 'wpmatch_custom_tier_nonce' ); ?>
				<input type="hidden" name="action" value="create_custom_tier">

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Tier Name', 'wpmatch' ); ?></th>
						<td>
							<input type="text" name="custom_tier_name" placeholder="e.g., Professional, Student, VIP" required style="width: 300px;" />
							<p class="description"><?php esc_html_e( 'The name users will see for this membership level.', 'wpmatch' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Price', 'wpmatch' ); ?></th>
						<td>
							<input type="number" name="custom_tier_price" step="0.01" min="0" required style="width: 150px;" />
							<?php echo esc_html( get_woocommerce_currency_symbol() ); ?>
							<select name="custom_billing_period">
								<option value="month"><?php esc_html_e( '/ month', 'wpmatch' ); ?></option>
								<option value="year"><?php esc_html_e( '/ year', 'wpmatch' ); ?></option>
								<option value="week"><?php esc_html_e( '/ week', 'wpmatch' ); ?></option>
								<option value="day"><?php esc_html_e( '/ day', 'wpmatch' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Features', 'wpmatch' ); ?></th>
						<td>
							<div class="custom-features-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">

								<label style="display: flex; align-items: center; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
									<input type="checkbox" name="custom_features[]" value="unlimited_likes" style="margin-right: 10px;">
									<div>
										<strong><?php esc_html_e( 'Unlimited Daily Likes', 'wpmatch' ); ?></strong><br>
										<small><?php esc_html_e( 'Remove daily like limits', 'wpmatch' ); ?></small>
									</div>
								</label>

								<label style="display: flex; align-items: center; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
									<input type="checkbox" name="custom_features[]" value="see_who_liked" style="margin-right: 10px;">
									<div>
										<strong><?php esc_html_e( 'See Who Liked You', 'wpmatch' ); ?></strong><br>
										<small><?php esc_html_e( 'View profiles that liked you', 'wpmatch' ); ?></small>
									</div>
								</label>

								<label style="display: flex; align-items: center; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
									<input type="checkbox" name="custom_features[]" value="advanced_search" style="margin-right: 10px;">
									<div>
										<strong><?php esc_html_e( 'Advanced Search Filters', 'wpmatch' ); ?></strong><br>
										<small><?php esc_html_e( 'Enhanced filtering options', 'wpmatch' ); ?></small>
									</div>
								</label>

								<label style="display: flex; align-items: center; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
									<input type="checkbox" name="custom_features[]" value="profile_visitors" style="margin-right: 10px;">
									<div>
										<strong><?php esc_html_e( 'Profile Visitors', 'wpmatch' ); ?></strong><br>
										<small><?php esc_html_e( 'See who viewed your profile', 'wpmatch' ); ?></small>
									</div>
								</label>

								<label style="display: flex; align-items: center; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
									<input type="checkbox" name="custom_features[]" value="read_receipts" style="margin-right: 10px;">
									<div>
										<strong><?php esc_html_e( 'Read Receipts', 'wpmatch' ); ?></strong><br>
										<small><?php esc_html_e( 'See when messages are read', 'wpmatch' ); ?></small>
									</div>
								</label>

								<label style="display: flex; align-items: center; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
									<input type="checkbox" name="custom_features[]" value="profile_boost_weekly" style="margin-right: 10px;">
									<div>
										<strong><?php esc_html_e( 'Weekly Profile Boost', 'wpmatch' ); ?></strong><br>
										<small><?php esc_html_e( 'Boost visibility weekly', 'wpmatch' ); ?></small>
									</div>
								</label>

								<label style="display: flex; align-items: center; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
									<input type="checkbox" name="custom_features[]" value="profile_boost_monthly" style="margin-right: 10px;">
									<div>
										<strong><?php esc_html_e( 'Monthly Profile Boost', 'wpmatch' ); ?></strong><br>
										<small><?php esc_html_e( 'Boost visibility monthly', 'wpmatch' ); ?></small>
									</div>
								</label>

								<label style="display: flex; align-items: center; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
									<input type="checkbox" name="custom_features[]" value="priority_support" style="margin-right: 10px;">
									<div>
										<strong><?php esc_html_e( 'Priority Support', 'wpmatch' ); ?></strong><br>
										<small><?php esc_html_e( 'Faster customer service', 'wpmatch' ); ?></small>
									</div>
								</label>

								<label style="display: flex; align-items: center; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
									<input type="checkbox" name="custom_features[]" value="hide_ads" style="margin-right: 10px;">
									<div>
										<strong><?php esc_html_e( 'Ad-Free Experience', 'wpmatch' ); ?></strong><br>
										<small><?php esc_html_e( 'Remove all advertisements', 'wpmatch' ); ?></small>
									</div>
								</label>

								<label style="display: flex; align-items: center; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
									<input type="checkbox" name="custom_features[]" value="incognito_mode" style="margin-right: 10px;">
									<div>
										<strong><?php esc_html_e( 'Incognito Browsing', 'wpmatch' ); ?></strong><br>
										<small><?php esc_html_e( 'Browse profiles privately', 'wpmatch' ); ?></small>
									</div>
								</label>

								<label style="display: flex; align-items: center; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
									<input type="checkbox" name="custom_features[]" value="message_filters" style="margin-right: 10px;">
									<div>
										<strong><?php esc_html_e( 'Advanced Message Filters', 'wpmatch' ); ?></strong><br>
										<small><?php esc_html_e( 'Filter incoming messages', 'wpmatch' ); ?></small>
									</div>
								</label>

								<label style="display: flex; align-items: center; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
									<input type="checkbox" name="custom_features[]" value="super_likes" style="margin-right: 10px;">
									<div>
										<strong><?php esc_html_e( 'Super Likes', 'wpmatch' ); ?></strong><br>
										<small><?php esc_html_e( 'Send special highlighted likes', 'wpmatch' ); ?></small>
									</div>
								</label>
							</div>

							<div style="margin-top: 20px;">
								<label style="display: flex; align-items: flex-start;">
									<span style="font-weight: bold; margin-right: 10px; margin-top: 2px;"><?php esc_html_e( 'Daily Likes Limit:', 'wpmatch' ); ?></span>
									<div>
										<input type="number" name="custom_daily_likes" min="1" placeholder="e.g., 50, 100, 500" style="width: 150px;" />
										<p class="description"><?php esc_html_e( 'Number of daily likes allowed (leave empty for unlimited)', 'wpmatch' ); ?></p>
									</div>
								</label>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Trial Period', 'wpmatch' ); ?></th>
						<td>
							<input type="number" name="custom_trial_days" min="0" max="90" value="0" style="width: 100px;" />
							<?php esc_html_e( 'days', 'wpmatch' ); ?>
							<p class="description"><?php esc_html_e( 'Free trial period for new subscribers (0 for no trial)', 'wpmatch' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Create Custom Tier', 'wpmatch' ); ?>
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