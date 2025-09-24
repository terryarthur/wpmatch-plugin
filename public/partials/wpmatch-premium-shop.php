<?php
/**
 * Premium shop template
 *
 * @package WPMatch
 */

// Security check.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get current user.
$current_user = wp_get_current_user();
if ( ! $current_user->ID ) {
	echo '<div class="wpmatch-notice error"><p>' . esc_html__( 'Please log in to view premium memberships.', 'wpmatch' ) . '</p></div>';
	return;
}

// Get user's current membership.
$current_level   = WPMatch_Membership_Manager::get_user_membership_level( $current_user->ID );
$has_premium     = WPMatch_Membership_Manager::has_active_premium_membership( $current_user->ID );
$membership_data = get_user_meta( $current_user->ID, '_wpmatch_membership_data', true );

// Get membership products.
$basic_product    = WPMatch_WooCommerce_Integration::get_membership_product( 'wpmatch_basic_premium' );
$gold_product     = WPMatch_WooCommerce_Integration::get_membership_product( 'wpmatch_gold_premium' );
$platinum_product = WPMatch_WooCommerce_Integration::get_membership_product( 'wpmatch_platinum_premium' );

// Get feature products.
$super_likes_product = WPMatch_WooCommerce_Integration::get_feature_product( 'super_likes_pack' );
$boost_product       = WPMatch_WooCommerce_Integration::get_feature_product( 'profile_boost' );
$filters_product     = WPMatch_WooCommerce_Integration::get_feature_product( 'premium_filters' );
?>

<div class="wpmatch-premium-shop">
	<div class="wpmatch-shop-header">
		<h2><?php esc_html_e( 'Upgrade Your Dating Experience', 'wpmatch' ); ?></h2>
		<p><?php esc_html_e( 'Unlock premium features and find your perfect match faster!', 'wpmatch' ); ?></p>
	</div>

	<?php if ( $has_premium ) : ?>
		<div class="wpmatch-current-membership">
			<div class="wpmatch-membership-status">
				<h3><?php esc_html_e( 'Your Current Membership', 'wpmatch' ); ?></h3>
				<div class="wpmatch-status-card <?php echo esc_attr( $current_level ); ?>">
					<div class="wpmatch-status-icon">
						<span class="dashicons dashicons-star-filled"></span>
					</div>
					<div class="wpmatch-status-info">
						<h4><?php echo esc_html( ucfirst( $current_level ) ); ?> <?php esc_html_e( 'Membership', 'wpmatch' ); ?></h4>
						<?php if ( isset( $membership_data['expiry_date'] ) ) : ?>
							<p><?php esc_html_e( 'Expires:', 'wpmatch' ); ?> <?php echo esc_html( gmdate( 'F j, Y', strtotime( $membership_data['expiry_date'] ) ) ); ?></p>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<!-- Premium Memberships -->
	<div class="wpmatch-membership-plans">
		<h3><?php esc_html_e( 'Premium Memberships', 'wpmatch' ); ?></h3>
		<div class="wpmatch-plans-grid">

			<!-- Basic Premium -->
			<?php if ( $basic_product ) : ?>
				<div class="wpmatch-plan-card basic <?php echo ( 'basic' === $current_level ) ? 'current' : ''; ?>">
					<div class="wpmatch-plan-header">
						<h4><?php echo esc_html( $basic_product->get_name() ); ?></h4>
						<div class="wpmatch-plan-price">
							<span class="wpmatch-currency">$</span>
							<span class="wpmatch-amount"><?php echo esc_html( $basic_product->get_price() ); ?></span>
							<span class="wpmatch-period">/month</span>
						</div>
					</div>
					<div class="wpmatch-plan-features">
						<ul>
							<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Unlimited Likes', 'wpmatch' ); ?></li>
							<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'See Who Liked You', 'wpmatch' ); ?></li>
							<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'No Ads', 'wpmatch' ); ?></li>
							<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Priority Support', 'wpmatch' ); ?></li>
						</ul>
					</div>
					<div class="wpmatch-plan-action">
						<?php if ( 'basic' === $current_level ) : ?>
							<button class="wpmatch-button primary disabled" disabled>
								<?php esc_html_e( 'Current Plan', 'wpmatch' ); ?>
							</button>
						<?php else : ?>
							<a href="<?php echo esc_url( get_permalink( $basic_product->get_id() ) ); ?>" class="wpmatch-button primary">
								<?php esc_html_e( 'Choose Basic', 'wpmatch' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<!-- Gold Premium -->
			<?php if ( $gold_product ) : ?>
				<div class="wpmatch-plan-card gold featured <?php echo ( 'gold' === $current_level ) ? 'current' : ''; ?>">
					<div class="wpmatch-plan-badge">
						<span><?php esc_html_e( 'Most Popular', 'wpmatch' ); ?></span>
					</div>
					<div class="wpmatch-plan-header">
						<h4><?php echo esc_html( $gold_product->get_name() ); ?></h4>
						<div class="wpmatch-plan-price">
							<span class="wpmatch-currency">$</span>
							<span class="wpmatch-amount"><?php echo esc_html( $gold_product->get_price() ); ?></span>
							<span class="wpmatch-period">/month</span>
						</div>
					</div>
					<div class="wpmatch-plan-features">
						<ul>
							<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Everything in Basic', 'wpmatch' ); ?></li>
							<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( '5 Super Likes Daily', 'wpmatch' ); ?></li>
							<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Profile Boost', 'wpmatch' ); ?></li>
							<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Read Receipts', 'wpmatch' ); ?></li>
						</ul>
					</div>
					<div class="wpmatch-plan-action">
						<?php if ( 'gold' === $current_level ) : ?>
							<button class="wpmatch-button primary disabled" disabled>
								<?php esc_html_e( 'Current Plan', 'wpmatch' ); ?>
							</button>
						<?php else : ?>
							<a href="<?php echo esc_url( get_permalink( $gold_product->get_id() ) ); ?>" class="wpmatch-button primary">
								<?php esc_html_e( 'Choose Gold', 'wpmatch' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<!-- Platinum Premium -->
			<?php if ( $platinum_product ) : ?>
				<div class="wpmatch-plan-card platinum <?php echo ( 'platinum' === $current_level ) ? 'current' : ''; ?>">
					<div class="wpmatch-plan-header">
						<h4><?php echo esc_html( $platinum_product->get_name() ); ?></h4>
						<div class="wpmatch-plan-price">
							<span class="wpmatch-currency">$</span>
							<span class="wpmatch-amount"><?php echo esc_html( $platinum_product->get_price() ); ?></span>
							<span class="wpmatch-period">/month</span>
						</div>
					</div>
					<div class="wpmatch-plan-features">
						<ul>
							<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Everything in Gold', 'wpmatch' ); ?></li>
							<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Advanced Search Filters', 'wpmatch' ); ?></li>
							<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Message Before Matching', 'wpmatch' ); ?></li>
							<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'VIP Support', 'wpmatch' ); ?></li>
						</ul>
					</div>
					<div class="wpmatch-plan-action">
						<?php if ( 'platinum' === $current_level ) : ?>
							<button class="wpmatch-button primary disabled" disabled>
								<?php esc_html_e( 'Current Plan', 'wpmatch' ); ?>
							</button>
						<?php else : ?>
							<a href="<?php echo esc_url( get_permalink( $platinum_product->get_id() ) ); ?>" class="wpmatch-button primary">
								<?php esc_html_e( 'Choose Platinum', 'wpmatch' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

		</div>
	</div>

	<!-- Individual Features -->
	<div class="wpmatch-feature-shop">
		<h3><?php esc_html_e( 'Individual Features', 'wpmatch' ); ?></h3>
		<p><?php esc_html_e( 'Purchase features individually as needed.', 'wpmatch' ); ?></p>

		<div class="wpmatch-features-grid">

			<!-- Super Likes Pack -->
			<?php if ( $super_likes_product ) : ?>
				<div class="wpmatch-feature-card">
					<div class="wpmatch-feature-icon">
						<span class="dashicons dashicons-heart"></span>
					</div>
					<div class="wpmatch-feature-info">
						<h4><?php echo esc_html( $super_likes_product->get_name() ); ?></h4>
						<p><?php echo esc_html( $super_likes_product->get_description() ); ?></p>
						<div class="wpmatch-feature-price">
							$<?php echo esc_html( $super_likes_product->get_price() ); ?>
						</div>
					</div>
					<div class="wpmatch-feature-action">
						<a href="<?php echo esc_url( get_permalink( $super_likes_product->get_id() ) ); ?>" class="wpmatch-button secondary">
							<?php esc_html_e( 'Buy Now', 'wpmatch' ); ?>
						</a>
					</div>
				</div>
			<?php endif; ?>

			<!-- Profile Boost -->
			<?php if ( $boost_product ) : ?>
				<div class="wpmatch-feature-card">
					<div class="wpmatch-feature-icon">
						<span class="dashicons dashicons-superhero"></span>
					</div>
					<div class="wpmatch-feature-info">
						<h4><?php echo esc_html( $boost_product->get_name() ); ?></h4>
						<p><?php echo esc_html( $boost_product->get_description() ); ?></p>
						<div class="wpmatch-feature-price">
							$<?php echo esc_html( $boost_product->get_price() ); ?>
						</div>
					</div>
					<div class="wpmatch-feature-action">
						<a href="<?php echo esc_url( get_permalink( $boost_product->get_id() ) ); ?>" class="wpmatch-button secondary">
							<?php esc_html_e( 'Buy Now', 'wpmatch' ); ?>
						</a>
					</div>
				</div>
			<?php endif; ?>

			<!-- Advanced Filters -->
			<?php if ( $filters_product ) : ?>
				<div class="wpmatch-feature-card">
					<div class="wpmatch-feature-icon">
						<span class="dashicons dashicons-filter"></span>
					</div>
					<div class="wpmatch-feature-info">
						<h4><?php echo esc_html( $filters_product->get_name() ); ?></h4>
						<p><?php echo esc_html( $filters_product->get_description() ); ?></p>
						<div class="wpmatch-feature-price">
							$<?php echo esc_html( $filters_product->get_price() ); ?>
						</div>
					</div>
					<div class="wpmatch-feature-action">
						<a href="<?php echo esc_url( get_permalink( $filters_product->get_id() ) ); ?>" class="wpmatch-button secondary">
							<?php esc_html_e( 'Buy Now', 'wpmatch' ); ?>
						</a>
					</div>
				</div>
			<?php endif; ?>

		</div>
	</div>

	<!-- Money Back Guarantee -->
	<div class="wpmatch-guarantee">
		<div class="wpmatch-guarantee-content">
			<div class="wpmatch-guarantee-icon">
				<span class="dashicons dashicons-shield"></span>
			</div>
			<div class="wpmatch-guarantee-text">
				<h4><?php esc_html_e( '30-Day Money-Back Guarantee', 'wpmatch' ); ?></h4>
				<p><?php esc_html_e( 'Not satisfied? Get a full refund within 30 days, no questions asked.', 'wpmatch' ); ?></p>
			</div>
		</div>
	</div>
</div>