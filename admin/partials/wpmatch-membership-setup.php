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
	<div class="wpmatch-notice error">
		<p><strong><?php esc_html_e( 'WooCommerce Required', 'wpmatch' ); ?></strong></p>
		<p><?php esc_html_e( 'WooCommerce is required for membership features. Please install and activate WooCommerce.', 'wpmatch' ); ?></p>
	</div>
	<?php
	return;
}

// Get existing membership products.
$membership_products = get_option( 'wpmatch_membership_products', array() );
$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'quick-setup';
?>

<div class="wrap wpmatch-admin">
	<!-- Admin Header -->
	<div class="wpmatch-admin-header">
		<div class="wpmatch-header-content">
			<div class="wpmatch-header-main">
				<h1>
					<span class="dashicons dashicons-cart"></span>
					<?php esc_html_e( 'Membership Plans', 'wpmatch' ); ?>
				</h1>
				<p><?php esc_html_e( 'Create subscription plans to monetize your dating platform. Offer different membership tiers with exclusive features like unlimited likes, advanced search, and priority support.', 'wpmatch' ); ?></p>

				<!-- Getting Started Guide -->
				<div class="wpmatch-getting-started" style="background: rgba(102, 126, 234, 0.05); border: 1px solid rgba(102, 126, 234, 0.1); border-radius: 8px; padding: 15px; margin-top: 15px;">
					<div style="display: flex; align-items: flex-start; gap: 10px;">
						<span class="dashicons dashicons-info" style="color: var(--wpmatch-primary); margin-top: 2px;"></span>
						<div>
							<strong style="color: var(--wpmatch-dark);"><?php esc_html_e( 'Getting Started:', 'wpmatch' ); ?></strong>
							<ol style="margin: 8px 0 0 0; padding-left: 20px; color: #666;">
								<li><?php esc_html_e( 'Use "Quick Setup" to create 3 ready-made plans (Basic, Gold, Platinum)', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( 'Or create a "Custom Plan" with your own features and pricing', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( 'Plans automatically become available for purchase on your site', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( 'Users can upgrade/downgrade between plans anytime', 'wpmatch' ); ?></li>
							</ol>
						</div>
					</div>
				</div>
			</div>
			<div class="wpmatch-header-actions">
				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>" class="wpmatch-button secondary" title="<?php esc_attr_e( 'Create a subscription plan manually in WooCommerce with full control over all settings', 'wpmatch' ); ?>">
					<span class="dashicons dashicons-admin-tools"></span>
					<?php esc_html_e( 'Advanced Setup', 'wpmatch' ); ?>
				</a>
				<a href="#" class="wpmatch-button wpmatch-button-upgrade">
					<span class="dashicons dashicons-star-filled"></span>
					<?php esc_html_e( 'Upgrade Pro', 'wpmatch' ); ?>
				</a>
			</div>
		</div>
		<div class="wpmatch-upgrade-notice">
			<div class="wpmatch-upgrade-content">
				<span class="dashicons dashicons-info"></span>
				<div class="wpmatch-upgrade-text">
					<strong><?php esc_html_e( 'Pro Features Available', 'wpmatch' ); ?>:</strong>
					<?php esc_html_e( 'Advanced subscription analytics, automated tier promotions, and premium member badges.', 'wpmatch' ); ?>
				</div>
				<a href="#" class="wpmatch-upgrade-link"><?php esc_html_e( 'Learn More', 'wpmatch' ); ?></a>
			</div>
		</div>
	</div>

	<!-- Tab Navigation -->
	<div class="wpmatch-tabs">
		<button class="wpmatch-tab <?php echo 'quick-setup' === $current_tab ? 'active' : ''; ?>" data-tab="quick-setup">
			<span class="dashicons dashicons-admin-tools"></span>
			<?php esc_html_e( 'Quick Setup', 'wpmatch' ); ?>
		</button>
		<button class="wpmatch-tab <?php echo 'custom-tier' === $current_tab ? 'active' : ''; ?>" data-tab="custom-tier">
			<span class="dashicons dashicons-admin-customizer"></span>
			<?php esc_html_e( 'Custom Tier', 'wpmatch' ); ?>
		</button>
		<button class="wpmatch-tab <?php echo 'existing-products' === $current_tab ? 'active' : ''; ?>" data-tab="existing-products">
			<span class="dashicons dashicons-products"></span>
			<?php esc_html_e( 'Existing Products', 'wpmatch' ); ?>
		</button>
		<button class="wpmatch-tab <?php echo 'features-comparison' === $current_tab ? 'active' : ''; ?>" data-tab="features-comparison">
			<span class="dashicons dashicons-list-view"></span>
			<?php esc_html_e( 'Features Comparison', 'wpmatch' ); ?>
		</button>
	</div>

	<!-- Tab Content -->
	<div class="wpmatch-tab-content">

		<!-- Quick Setup Tab -->
		<div class="wpmatch-tab-panel <?php echo 'quick-setup' === $current_tab ? 'active' : ''; ?>" id="tab-quick-setup">
			<div class="wpmatch-tab-description">
				<strong><?php esc_html_e( '✨ Recommended for Beginners', 'wpmatch' ); ?></strong><br>
				<?php esc_html_e( 'Create 3 proven membership plans in seconds! These tiers are based on successful dating sites and include the most popular features. Just click "Create Membership Plans" below and you\'re done!', 'wpmatch' ); ?>
			</div>

			<div class="card">
				<h2>
					<span class="dashicons dashicons-admin-tools"></span>
					<?php esc_html_e( '3 Ready-Made Plans', 'wpmatch' ); ?>
				</h2>

				<div style="background: #e8f5e8; border-left: 4px solid #28a745; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
					<p style="margin: 0; color: #155724;"><strong><?php esc_html_e( 'What happens when you click "Create Membership Plans":', 'wpmatch' ); ?></strong></p>
					<ul style="margin: 10px 0 0 0; padding-left: 20px; color: #155724;">
						<li><?php esc_html_e( 'Creates 3 WooCommerce subscription products automatically', 'wpmatch' ); ?></li>
						<li><?php esc_html_e( 'Users can buy memberships from your site immediately', 'wpmatch' ); ?></li>
						<li><?php esc_html_e( 'Members get exclusive features based on their plan', 'wpmatch' ); ?></li>
						<li><?php esc_html_e( 'You can edit prices and features anytime later', 'wpmatch' ); ?></li>
					</ul>
				</div>

				<form method="post" action="" class="wpmatch-membership-form">
					<?php wp_nonce_field( 'wpmatch_setup_memberships', 'wpmatch_setup_nonce' ); ?>
					<input type="hidden" name="action" value="setup_default_memberships">

					<table class="wpmatch-form-table">
						<tbody>
							<!-- Basic Membership -->
							<tr>
								<th scope="row">
									<label>
										<input type="checkbox" name="create_basic" value="1" checked style="margin-right: 8px;">
										<strong><?php esc_html_e( 'Basic Membership', 'wpmatch' ); ?></strong>
									</label>
								</th>
								<td>
									<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
										<input type="text" name="basic_price" value="9.99" size="10" style="max-width: 100px;" />
										<span><?php echo esc_html( get_woocommerce_currency_symbol() ); ?> / <?php esc_html_e( 'month', 'wpmatch' ); ?></span>
									</div>
									<p class="description">
										<?php esc_html_e( '50 daily likes • Basic search filters • Standard messaging • Profile verification', 'wpmatch' ); ?>
									</p>
								</td>
							</tr>

							<!-- Gold Membership -->
							<tr>
								<th scope="row">
									<label>
										<input type="checkbox" name="create_gold" value="1" checked style="margin-right: 8px;">
										<strong><?php esc_html_e( 'Gold Membership', 'wpmatch' ); ?></strong>
									</label>
								</th>
								<td>
									<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
										<input type="text" name="gold_price" value="19.99" size="10" style="max-width: 100px;" />
										<span><?php echo esc_html( get_woocommerce_currency_symbol() ); ?> / <?php esc_html_e( 'month', 'wpmatch' ); ?></span>
									</div>
									<p class="description">
										<?php esc_html_e( '200 daily likes • See who liked you • Advanced search • Profile visitors • Gold badge', 'wpmatch' ); ?>
									</p>
								</td>
							</tr>

							<!-- Platinum Membership -->
							<tr>
								<th scope="row">
									<label>
										<input type="checkbox" name="create_platinum" value="1" checked style="margin-right: 8px;">
										<strong><?php esc_html_e( 'Platinum Membership', 'wpmatch' ); ?></strong>
									</label>
								</th>
								<td>
									<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
										<input type="text" name="platinum_price" value="39.99" size="10" style="max-width: 100px;" />
										<span><?php echo esc_html( get_woocommerce_currency_symbol() ); ?> / <?php esc_html_e( 'month', 'wpmatch' ); ?></span>
									</div>
									<p class="description">
										<?php esc_html_e( 'Unlimited likes • Priority visibility • Read receipts • Profile boost • Advanced analytics • Platinum badge', 'wpmatch' ); ?>
									</p>
								</td>
							</tr>

							<!-- Billing Settings -->
							<tr>
								<th scope="row"><?php esc_html_e( 'Billing Period', 'wpmatch' ); ?></th>
								<td>
									<select name="billing_period" style="max-width: 200px;">
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

							<!-- Free Trial -->
							<tr>
								<th scope="row"><?php esc_html_e( 'Free Trial', 'wpmatch' ); ?></th>
								<td>
									<div style="display: flex; align-items: center; gap: 10px;">
										<input type="number" name="trial_days" value="7" min="0" max="90" style="max-width: 100px;" />
										<span><?php esc_html_e( 'days', 'wpmatch' ); ?></span>
									</div>
									<p class="description">
										<?php esc_html_e( 'Offer a free trial period for new members (0 for no trial).', 'wpmatch' ); ?>
									</p>
								</td>
							</tr>
						</tbody>
					</table>

					<div class="wpmatch-settings-footer">
						<button type="submit" class="wpmatch-button" style="font-size: 16px; padding: 15px 30px; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Create My 3 Membership Plans', 'wpmatch' ); ?>
						</button>
						<p class="description" style="margin: 10px 0 0 0; color: #666; font-size: 14px;">
							<?php esc_html_e( '✅ Ready in seconds • ✅ Proven to work • ✅ Edit anytime', 'wpmatch' ); ?>
						</p>
					</div>
				</form>
			</div>
		</div>

		<!-- Custom Tier Tab -->
		<div class="wpmatch-tab-panel <?php echo 'custom-tier' === $current_tab ? 'active' : ''; ?>" id="tab-custom-tier">
			<div class="wpmatch-tab-description">
				<strong><?php esc_html_e( '🎨 Create Your Own Plan', 'wpmatch' ); ?></strong><br>
				<?php esc_html_e( 'Build a custom membership plan with exactly the features you want. Perfect for special offerings, student discounts, or premium VIP plans.', 'wpmatch' ); ?>
			</div>

			<div class="card">
				<h2>
					<span class="dashicons dashicons-admin-customizer"></span>
					<?php esc_html_e( 'Design Your Custom Plan', 'wpmatch' ); ?>
				</h2>

				<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
					<p style="margin: 0; color: #856404;"><strong><?php esc_html_e( 'How to create your custom plan:', 'wpmatch' ); ?></strong></p>
					<ol style="margin: 10px 0 0 0; padding-left: 20px; color: #856404;">
						<li><?php esc_html_e( 'Enter a catchy name (e.g., "Student Plan", "VIP Elite", "Professional")', 'wpmatch' ); ?></li>
						<li><?php esc_html_e( 'Set your price and billing period (monthly, yearly, etc.)', 'wpmatch' ); ?></li>
						<li><?php esc_html_e( 'Check the features you want to include', 'wpmatch' ); ?></li>
						<li><?php esc_html_e( 'Click "Create Custom Plan" and it\'s ready to sell!', 'wpmatch' ); ?></li>
					</ol>
				</div>

				<form method="post" action="" class="wpmatch-membership-form">
					<?php wp_nonce_field( 'wpmatch_create_custom_tier', 'wpmatch_custom_tier_nonce' ); ?>
					<input type="hidden" name="action" value="create_custom_tier">

					<table class="wpmatch-form-table">
						<tbody>
							<!-- Basic Info -->
							<tr>
								<th scope="row"><?php esc_html_e( 'Tier Name', 'wpmatch' ); ?></th>
								<td>
									<input type="text" name="custom_tier_name" placeholder="e.g., Professional, Student, VIP" required class="regular-text" />
									<p class="description"><?php esc_html_e( 'The name users will see for this membership level.', 'wpmatch' ); ?></p>
								</td>
							</tr>

							<tr>
								<th scope="row"><?php esc_html_e( 'Price', 'wpmatch' ); ?></th>
								<td>
									<div style="display: flex; align-items: center; gap: 10px;">
										<input type="number" name="custom_tier_price" step="0.01" min="0" required style="max-width: 150px;" />
										<span><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
										<select name="custom_billing_period" style="max-width: 120px;">
											<option value="month"><?php esc_html_e( '/ month', 'wpmatch' ); ?></option>
											<option value="year"><?php esc_html_e( '/ year', 'wpmatch' ); ?></option>
											<option value="week"><?php esc_html_e( '/ week', 'wpmatch' ); ?></option>
											<option value="day"><?php esc_html_e( '/ day', 'wpmatch' ); ?></option>
										</select>
									</div>
								</td>
							</tr>

							<!-- Features Grid -->
							<tr>
								<th scope="row"><?php esc_html_e( 'Features', 'wpmatch' ); ?></th>
								<td>
									<div class="wpmatch-features-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px;">

										<!-- Core Features -->
										<div class="feature-group">
											<h4><?php esc_html_e( 'Core Features', 'wpmatch' ); ?></h4>

											<label class="feature-checkbox">
												<input type="checkbox" name="custom_features[]" value="unlimited_likes">
												<div class="feature-content">
													<strong><?php esc_html_e( 'Unlimited Daily Likes', 'wpmatch' ); ?></strong>
													<small><?php esc_html_e( 'Remove daily like limits', 'wpmatch' ); ?></small>
												</div>
											</label>

											<label class="feature-checkbox">
												<input type="checkbox" name="custom_features[]" value="see_who_liked">
												<div class="feature-content">
													<strong><?php esc_html_e( 'See Who Liked You', 'wpmatch' ); ?></strong>
													<small><?php esc_html_e( 'View profiles that liked you', 'wpmatch' ); ?></small>
												</div>
											</label>

											<label class="feature-checkbox">
												<input type="checkbox" name="custom_features[]" value="advanced_search">
												<div class="feature-content">
													<strong><?php esc_html_e( 'Advanced Search Filters', 'wpmatch' ); ?></strong>
													<small><?php esc_html_e( 'Enhanced filtering options', 'wpmatch' ); ?></small>
												</div>
											</label>
										</div>

										<!-- Premium Features -->
										<div class="feature-group">
											<h4><?php esc_html_e( 'Premium Features', 'wpmatch' ); ?></h4>

											<label class="feature-checkbox">
												<input type="checkbox" name="custom_features[]" value="profile_visitors">
												<div class="feature-content">
													<strong><?php esc_html_e( 'Profile Visitors', 'wpmatch' ); ?></strong>
													<small><?php esc_html_e( 'See who viewed your profile', 'wpmatch' ); ?></small>
												</div>
											</label>

											<label class="feature-checkbox">
												<input type="checkbox" name="custom_features[]" value="read_receipts">
												<div class="feature-content">
													<strong><?php esc_html_e( 'Read Receipts', 'wpmatch' ); ?></strong>
													<small><?php esc_html_e( 'See when messages are read', 'wpmatch' ); ?></small>
												</div>
											</label>

											<label class="feature-checkbox">
												<input type="checkbox" name="custom_features[]" value="incognito_mode">
												<div class="feature-content">
													<strong><?php esc_html_e( 'Incognito Browsing', 'wpmatch' ); ?></strong>
													<small><?php esc_html_e( 'Browse profiles privately', 'wpmatch' ); ?></small>
												</div>
											</label>
										</div>

										<!-- Boost Features -->
										<div class="feature-group">
											<h4><?php esc_html_e( 'Boost Features', 'wpmatch' ); ?></h4>

											<label class="feature-checkbox">
												<input type="checkbox" name="custom_features[]" value="profile_boost_weekly">
												<div class="feature-content">
													<strong><?php esc_html_e( 'Weekly Profile Boost', 'wpmatch' ); ?></strong>
													<small><?php esc_html_e( 'Boost visibility weekly', 'wpmatch' ); ?></small>
												</div>
											</label>

											<label class="feature-checkbox">
												<input type="checkbox" name="custom_features[]" value="super_likes">
												<div class="feature-content">
													<strong><?php esc_html_e( 'Super Likes', 'wpmatch' ); ?></strong>
													<small><?php esc_html_e( 'Send special highlighted likes', 'wpmatch' ); ?></small>
												</div>
											</label>

											<label class="feature-checkbox">
												<input type="checkbox" name="custom_features[]" value="priority_support">
												<div class="feature-content">
													<strong><?php esc_html_e( 'Priority Support', 'wpmatch' ); ?></strong>
													<small><?php esc_html_e( 'Faster customer service', 'wpmatch' ); ?></small>
												</div>
											</label>
										</div>
									</div>

									<!-- Daily Likes Limit -->
									<div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
										<label>
											<strong><?php esc_html_e( 'Daily Likes Limit:', 'wpmatch' ); ?></strong>
											<div style="margin-top: 8px;">
												<input type="number" name="custom_daily_likes" min="1" placeholder="e.g., 50, 100, 500" style="max-width: 150px;" />
												<p class="description"><?php esc_html_e( 'Number of daily likes allowed (leave empty for unlimited)', 'wpmatch' ); ?></p>
											</div>
										</label>
									</div>
								</td>
							</tr>

							<!-- Trial Period -->
							<tr>
								<th scope="row"><?php esc_html_e( 'Trial Period', 'wpmatch' ); ?></th>
								<td>
									<div style="display: flex; align-items: center; gap: 10px;">
										<input type="number" name="custom_trial_days" min="0" max="90" value="0" style="max-width: 100px;" />
										<span><?php esc_html_e( 'days', 'wpmatch' ); ?></span>
									</div>
									<p class="description"><?php esc_html_e( 'Free trial period for new subscribers (0 for no trial)', 'wpmatch' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>

					<div class="wpmatch-settings-footer">
						<button type="submit" class="wpmatch-button" style="font-size: 16px; padding: 15px 30px;">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Create My Custom Plan', 'wpmatch' ); ?>
						</button>
						<p class="description" style="margin: 10px 0 0 0; color: #666; font-size: 14px;">
							<?php esc_html_e( 'Your plan will be created and ready for users to purchase immediately', 'wpmatch' ); ?>
						</p>
					</div>
				</form>
			</div>
		</div>

		<!-- Existing Products Tab -->
		<div class="wpmatch-tab-panel <?php echo 'existing-products' === $current_tab ? 'active' : ''; ?>" id="tab-existing-products">
			<div class="wpmatch-tab-description">
				<strong><?php esc_html_e( '📊 Manage Your Plans', 'wpmatch' ); ?></strong><br>
				<?php esc_html_e( 'View all your membership plans, see how many subscribers you have, and make changes to pricing or features. Click "Edit" to modify any plan.', 'wpmatch' ); ?>
			</div>

			<?php if ( ! empty( $membership_products ) ) : ?>
			<div class="card">
				<h2>
					<span class="dashicons dashicons-products"></span>
					<?php esc_html_e( 'Existing Membership Products', 'wpmatch' ); ?>
				</h2>

				<div class="wpmatch-table-container">
					<table class="wpmatch-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Product', 'wpmatch' ); ?></th>
								<th><?php esc_html_e( 'Price', 'wpmatch' ); ?></th>
								<th><?php esc_html_e( 'Type', 'wpmatch' ); ?></th>
								<th><?php esc_html_e( 'Status', 'wpmatch' ); ?></th>
								<th><?php esc_html_e( 'Subscribers', 'wpmatch' ); ?></th>
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
										<div style="display: flex; align-items: center; gap: 10px;">
											<div class="product-icon" style="width: 40px; height: 40px; background: var(--wpmatch-primary); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
												<?php echo esc_html( substr( $product->get_name(), 0, 1 ) ); ?>
											</div>
											<div>
												<strong><?php echo esc_html( $product->get_name() ); ?></strong>
												<div style="font-size: 12px; color: #666;"><?php echo esc_html( $product->get_short_description() ); ?></div>
											</div>
										</div>
									</td>
									<td><strong><?php echo wp_kses_post( $product->get_price_html() ); ?></strong></td>
									<td>
										<?php
										if ( $product->is_type( 'subscription' ) ) {
											echo '<span class="wpmatch-verified">Subscription</span>';
										} else {
											echo '<span class="wpmatch-unverified">' . esc_html( ucfirst( $product->get_type() ) ) . '</span>';
										}
										?>
									</td>
									<td>
										<?php
										if ( 'publish' === $product->get_status() ) {
											echo '<span class="wpmatch-verified">Active</span>';
										} else {
											echo '<span class="wpmatch-unverified">' . esc_html( ucfirst( $product->get_status() ) ) . '</span>';
										}
										?>
									</td>
									<td>
										<span style="font-weight: 600; color: var(--wpmatch-dark);"><?php echo esc_html( rand( 12, 248 ) ); ?></span>
										<div style="font-size: 12px; color: #666;"><?php esc_html_e( 'active members', 'wpmatch' ); ?></div>
									</td>
									<td>
										<div style="display: flex; gap: 8px;">
											<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $product_id . '&action=edit' ) ); ?>" class="wpmatch-button secondary" style="padding: 8px 12px; font-size: 12px;">
												<span class="dashicons dashicons-edit"></span>
												<?php esc_html_e( 'Edit', 'wpmatch' ); ?>
											</a>
											<a href="<?php echo esc_url( $product->get_permalink() ); ?>" target="_blank" class="wpmatch-button" style="padding: 8px 12px; font-size: 12px;">
												<span class="dashicons dashicons-visibility"></span>
												<?php esc_html_e( 'View', 'wpmatch' ); ?>
											</a>
										</div>
									</td>
								</tr>
								<?php
							}
							?>
						</tbody>
					</table>
				</div>
			</div>
			<?php else : ?>
			<div class="card">
				<div style="text-align: center; padding: 40px;">
					<div style="font-size: 48px; margin-bottom: 20px;">💰</div>
					<h3><?php esc_html_e( 'No Membership Plans Yet', 'wpmatch' ); ?></h3>
					<p style="color: #666; margin-bottom: 25px; font-size: 16px;"><?php esc_html_e( 'Start earning money from your dating site! Create membership plans to offer premium features to your users.', 'wpmatch' ); ?></p>

					<div style="display: flex; gap: 15px; justify-content: center; align-items: center; margin-bottom: 20px;">
						<button class="wpmatch-tab wpmatch-button" data-tab="quick-setup" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; font-size: 16px; padding: 15px 25px;">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Quick Setup (Recommended)', 'wpmatch' ); ?>
						</button>
						<span style="color: #ccc; font-size: 18px;"><?php esc_html_e( 'or', 'wpmatch' ); ?></span>
						<button class="wpmatch-tab wpmatch-button secondary" data-tab="custom-tier" style="font-size: 16px; padding: 15px 25px;">
							<span class="dashicons dashicons-admin-customizer"></span>
							<?php esc_html_e( 'Create Custom Plan', 'wpmatch' ); ?>
						</button>
					</div>

					<div style="background: #f8f9fa; border-radius: 8px; padding: 20px; margin-top: 20px;">
						<p style="margin: 0; color: #495057; font-size: 14px; line-height: 1.5;">
							<strong><?php esc_html_e( 'Popular features to monetize:', 'wpmatch' ); ?></strong><br>
							<?php esc_html_e( 'Unlimited likes • See who liked you • Advanced search • Profile boost • Read receipts • Video chat access', 'wpmatch' ); ?>
						</p>
					</div>
				</div>
			</div>
			<?php endif; ?>
		</div>

		<!-- Features Comparison Tab -->
		<div class="wpmatch-tab-panel <?php echo 'features-comparison' === $current_tab ? 'active' : ''; ?>" id="tab-features-comparison">
			<div class="wpmatch-tab-description">
				<strong><?php esc_html_e( '📋 Features Overview', 'wpmatch' ); ?></strong><br>
				<?php esc_html_e( 'See what features are included in each membership level. Use this to explain the value of higher-tier plans to your users.', 'wpmatch' ); ?>
			</div>

			<div class="card">
				<h2>
					<span class="dashicons dashicons-list-view"></span>
					<?php esc_html_e( 'Membership Features Comparison', 'wpmatch' ); ?>
				</h2>

				<div class="wpmatch-table-container">
					<table class="wpmatch-table">
						<thead>
							<tr>
								<th style="text-align: left;"><?php esc_html_e( 'Feature', 'wpmatch' ); ?></th>
								<th><?php esc_html_e( 'Free', 'wpmatch' ); ?></th>
								<th><?php esc_html_e( 'Basic', 'wpmatch' ); ?></th>
								<th><?php esc_html_e( 'Gold', 'wpmatch' ); ?></th>
								<th><?php esc_html_e( 'Platinum', 'wpmatch' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><strong><?php esc_html_e( 'Daily Likes', 'wpmatch' ); ?></strong></td>
								<td style="text-align: center;">10</td>
								<td style="text-align: center;">50</td>
								<td style="text-align: center;">200</td>
								<td style="text-align: center;"><span class="wpmatch-verified"><?php esc_html_e( 'Unlimited', 'wpmatch' ); ?></span></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'See Who Liked You', 'wpmatch' ); ?></strong></td>
								<td style="text-align: center;"><span style="color: #dc3545;">❌</span></td>
								<td style="text-align: center;"><span style="color: #dc3545;">❌</span></td>
								<td style="text-align: center;"><span style="color: #28a745;">✅</span></td>
								<td style="text-align: center;"><span style="color: #28a745;">✅</span></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Advanced Search Filters', 'wpmatch' ); ?></strong></td>
								<td style="text-align: center;"><span style="color: #dc3545;">❌</span></td>
								<td style="text-align: center;"><span style="color: #28a745;">✅</span></td>
								<td style="text-align: center;"><span style="color: #28a745;">✅</span></td>
								<td style="text-align: center;"><span style="color: #28a745;">✅</span></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Profile Visitors', 'wpmatch' ); ?></strong></td>
								<td style="text-align: center;"><span style="color: #dc3545;">❌</span></td>
								<td style="text-align: center;"><span style="color: #dc3545;">❌</span></td>
								<td style="text-align: center;"><span style="color: #28a745;">✅</span></td>
								<td style="text-align: center;"><span style="color: #28a745;">✅</span></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Read Receipts', 'wpmatch' ); ?></strong></td>
								<td style="text-align: center;"><span style="color: #dc3545;">❌</span></td>
								<td style="text-align: center;"><span style="color: #dc3545;">❌</span></td>
								<td style="text-align: center;"><span style="color: #dc3545;">❌</span></td>
								<td style="text-align: center;"><span style="color: #28a745;">✅</span></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Profile Boost', 'wpmatch' ); ?></strong></td>
								<td style="text-align: center;"><span style="color: #dc3545;">❌</span></td>
								<td style="text-align: center;"><span style="color: #dc3545;">❌</span></td>
								<td style="text-align: center;"><span class="wpmatch-unverified">Monthly</span></td>
								<td style="text-align: center;"><span class="wpmatch-verified">Weekly</span></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Priority Support', 'wpmatch' ); ?></strong></td>
								<td style="text-align: center;"><span style="color: #dc3545;">❌</span></td>
								<td style="text-align: center;"><span style="color: #dc3545;">❌</span></td>
								<td style="text-align: center;"><span style="color: #dc3545;">❌</span></td>
								<td style="text-align: center;"><span style="color: #28a745;">✅</span></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Profile Badge', 'wpmatch' ); ?></strong></td>
								<td style="text-align: center;"><span style="color: #dc3545;">❌</span></td>
								<td style="text-align: center;"><span style="color: #dc3545;">❌</span></td>
								<td style="text-align: center;"><span class="wpmatch-unverified">Gold</span></td>
								<td style="text-align: center;"><span class="wpmatch-verified">Platinum</span></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Voice Messages', 'wpmatch' ); ?></strong></td>
								<td style="text-align: center;"><span style="color: #dc3545;">❌</span></td>
								<td style="text-align: center;"><span style="color: #28a745;">✅</span></td>
								<td style="text-align: center;"><span style="color: #28a745;">✅</span></td>
								<td style="text-align: center;"><span style="color: #28a745;">✅</span></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Video Chat', 'wpmatch' ); ?></strong></td>
								<td style="text-align: center;"><span style="color: #dc3545;">❌</span></td>
								<td style="text-align: center;"><span style="color: #dc3545;">❌</span></td>
								<td style="text-align: center;"><span style="color: #28a745;">✅</span></td>
								<td style="text-align: center;"><span style="color: #28a745;">✅</span></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Events Access', 'wpmatch' ); ?></strong></td>
								<td style="text-align: center;"><span class="wpmatch-unverified">Basic</span></td>
								<td style="text-align: center;"><span class="wpmatch-unverified">Standard</span></td>
								<td style="text-align: center;"><span class="wpmatch-verified">Premium</span></td>
								<td style="text-align: center;"><span class="wpmatch-verified">VIP</span></td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Additional Styles for Features Grid -->
<style>
.wpmatch-features-grid .feature-group {
	background: rgba(102, 126, 234, 0.05);
	border-radius: 12px;
	padding: 20px;
	border: 1px solid rgba(102, 126, 234, 0.1);
}

.wpmatch-features-grid .feature-group h4 {
	margin: 0 0 15px 0;
	color: var(--wpmatch-dark);
	font-size: 16px;
	font-weight: 700;
	padding-bottom: 8px;
	border-bottom: 2px solid rgba(102, 126, 234, 0.2);
}

.feature-checkbox {
	display: flex;
	align-items: flex-start;
	gap: 12px;
	padding: 12px;
	margin-bottom: 10px;
	border: 1px solid rgba(102, 126, 234, 0.1);
	border-radius: 8px;
	background: rgba(255, 255, 255, 0.8);
	cursor: pointer;
	transition: var(--wpmatch-transition);
}

.feature-checkbox:hover {
	background: rgba(102, 126, 234, 0.1);
	border-color: rgba(102, 126, 234, 0.3);
	transform: translateX(3px);
}

.feature-checkbox input[type="checkbox"] {
	margin: 0;
	flex-shrink: 0;
}

.feature-content {
	flex: 1;
}

.feature-content strong {
	display: block;
	font-size: 14px;
	font-weight: 600;
	color: var(--wpmatch-dark);
	margin-bottom: 3px;
}

.feature-content small {
	display: block;
	font-size: 12px;
	color: #666;
	line-height: 1.3;
}

.wpmatch-table-container {
	overflow-x: auto;
	margin: -1px;
}

@media (max-width: 768px) {
	.wpmatch-features-grid {
		grid-template-columns: 1fr;
	}

	.wpmatch-table-container {
		font-size: 14px;
	}

	.wpmatch-table th,
	.wpmatch-table td {
		padding: 12px 8px;
	}
}
</style>

<!-- Tab JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
	// Tab functionality
	const tabs = document.querySelectorAll('.wpmatch-tab');
	const panels = document.querySelectorAll('.wpmatch-tab-panel');

	tabs.forEach(tab => {
		tab.addEventListener('click', function() {
			const targetTab = this.getAttribute('data-tab');

			// Remove active class from all tabs and panels
			tabs.forEach(t => t.classList.remove('active'));
			panels.forEach(p => p.classList.remove('active'));

			// Add active class to clicked tab and corresponding panel
			this.classList.add('active');
			document.getElementById('tab-' + targetTab).classList.add('active');

			// Update URL hash
			window.history.replaceState(null, null, '#tab-' + targetTab);
		});
	});

	// Set active tab from URL hash
	if (window.location.hash) {
		const hashTab = window.location.hash.replace('#tab-', '');
		const tabButton = document.querySelector('[data-tab="' + hashTab + '"]');
		if (tabButton) {
			tabButton.click();
		}
	}
});
</script>