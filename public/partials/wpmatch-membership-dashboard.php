<?php
/**
 * User Membership Dashboard
 *
 * Displays user's current membership status, features, and management options.
 *
 * @package WPMatch
 * @since 1.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get current user ID.
$user_id = get_current_user_id();
if ( ! $user_id ) {
	echo '<p>' . esc_html__( 'Please log in to view your membership dashboard.', 'wpmatch' ) . '</p>';
	return;
}

// Get user's membership data.
$current_membership = WPMatch_Membership_Manager::get_user_membership_level( $user_id );
$memberships        = get_user_meta( $user_id, '_wpmatch_memberships', true );
$membership_data    = get_user_meta( $user_id, '_wpmatch_membership_data', true );

// Get membership features (using a simple feature map for now).
$all_features = array(
	'free'     => array(
		'basic_swipe'      => __( 'Basic Swiping', 'wpmatch' ),
		'basic_messaging'  => __( 'Basic Messaging', 'wpmatch' ),
		'profile_creation' => __( 'Profile Creation', 'wpmatch' ),
	),
	'basic'    => array(
		'basic_swipe'       => __( 'Basic Swiping', 'wpmatch' ),
		'basic_messaging'   => __( 'Basic Messaging', 'wpmatch' ),
		'profile_creation'  => __( 'Profile Creation', 'wpmatch' ),
		'unlimited_likes'   => __( 'Unlimited Likes', 'wpmatch' ),
		'see_who_liked_you' => __( 'See Who Liked You', 'wpmatch' ),
	),
	'gold'     => array(
		'basic_swipe'       => __( 'Basic Swiping', 'wpmatch' ),
		'basic_messaging'   => __( 'Basic Messaging', 'wpmatch' ),
		'profile_creation'  => __( 'Profile Creation', 'wpmatch' ),
		'unlimited_likes'   => __( 'Unlimited Likes', 'wpmatch' ),
		'see_who_liked_you' => __( 'See Who Liked You', 'wpmatch' ),
		'super_likes'       => __( 'Super Likes', 'wpmatch' ),
		'boost_profile'     => __( 'Profile Boost', 'wpmatch' ),
	),
	'platinum' => array(
		'basic_swipe'       => __( 'Basic Swiping', 'wpmatch' ),
		'basic_messaging'   => __( 'Basic Messaging', 'wpmatch' ),
		'profile_creation'  => __( 'Profile Creation', 'wpmatch' ),
		'unlimited_likes'   => __( 'Unlimited Likes', 'wpmatch' ),
		'see_who_liked_you' => __( 'See Who Liked You', 'wpmatch' ),
		'super_likes'       => __( 'Super Likes', 'wpmatch' ),
		'boost_profile'     => __( 'Profile Boost', 'wpmatch' ),
		'priority_support'  => __( 'Priority Support', 'wpmatch' ),
		'advanced_filters'  => __( 'Advanced Filters', 'wpmatch' ),
	),
);

$features = isset( $all_features[ $current_membership ] ) ? $all_features[ $current_membership ] : $all_features['free'];

// Get active subscriptions.
$active_subscriptions = array();
if ( function_exists( 'wcs_get_users_subscriptions' ) ) {
	$subscriptions = wcs_get_users_subscriptions( $user_id );
	foreach ( $subscriptions as $subscription ) {
		if ( in_array( $subscription->get_status(), array( 'active', 'on-hold', 'pending-cancel' ), true ) ) {
			$active_subscriptions[] = $subscription;
		}
	}
}

?>

<div class="wpmatch-membership-dashboard">
	<div class="wpmatch-dashboard-header">
		<h2><?php esc_html_e( 'My Membership Dashboard', 'wpmatch' ); ?></h2>
		<p class="dashboard-subtitle"><?php esc_html_e( 'Manage your dating membership and explore premium features', 'wpmatch' ); ?></p>
	</div>

	<div class="wpmatch-dashboard-content">
		<!-- Current Membership Status -->
		<div class="membership-status-card">
			<div class="card-header">
				<h3><?php esc_html_e( 'Current Membership', 'wpmatch' ); ?></h3>
				<span class="membership-badge membership-<?php echo esc_attr( $current_membership ); ?>">
					<?php echo esc_html( ucfirst( str_replace( '-', ' ', $current_membership ) ) ); ?>
				</span>
			</div>

			<div class="card-content">
				<?php if ( 'free' === $current_membership ) : ?>
					<div class="membership-info">
						<p><?php esc_html_e( 'You are currently on our free plan. Upgrade to unlock premium dating features!', 'wpmatch' ); ?></p>
						<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) . '?product_cat=wpmatch-memberships' ); ?>" class="upgrade-button">
							<?php esc_html_e( 'Upgrade Now', 'wpmatch' ); ?>
						</a>
					</div>
				<?php else : ?>
					<?php if ( is_array( $memberships ) && isset( $memberships[ $current_membership ] ) ) : ?>
						<?php $membership_data = $memberships[ $current_membership ]; ?>
						<div class="membership-info">
							<div class="membership-details">
								<div class="detail-item">
									<span class="label"><?php esc_html_e( 'Status:', 'wpmatch' ); ?></span>
									<span class="value status-<?php echo esc_attr( $membership_data['status'] ); ?>">
										<?php echo esc_html( ucfirst( $membership_data['status'] ) ); ?>
									</span>
								</div>

								<?php if ( isset( $membership_data['activated_at'] ) ) : ?>
									<div class="detail-item">
										<span class="label"><?php esc_html_e( 'Active Since:', 'wpmatch' ); ?></span>
										<span class="value"><?php echo esc_html( date_i18n( get_option( 'date_format' ), $membership_data['activated_at'] ) ); ?></span>
									</div>
								<?php endif; ?>

								<?php if ( isset( $membership_data['expires_at'] ) && $membership_data['expires_at'] > 0 ) : ?>
									<div class="detail-item">
										<span class="label"><?php esc_html_e( 'Expires:', 'wpmatch' ); ?></span>
										<span class="value">
											<?php
											$expires_date = date_i18n( get_option( 'date_format' ), $membership_data['expires_at'] );
											$days_left    = ceil( ( $membership_data['expires_at'] - time() ) / DAY_IN_SECONDS );

											if ( $days_left > 0 ) {
												/* translators: %1$s: expiry date, %2$d: days remaining */
												echo esc_html( sprintf( __( '%1$s (%2$d days left)', 'wpmatch' ), $expires_date, $days_left ) );
											} else {
												echo esc_html( $expires_date . ' ' . __( '(Expired)', 'wpmatch' ) );
											}
											?>
										</span>
									</div>
								<?php endif; ?>

								<?php if ( 'subscription' === $membership_data['reference_type'] ) : ?>
									<div class="detail-item">
										<span class="label"><?php esc_html_e( 'Subscription:', 'wpmatch' ); ?></span>
										<span class="value"><?php esc_html_e( 'Auto-renewable', 'wpmatch' ); ?></span>
									</div>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>

		<!-- Membership Features -->
		<div class="membership-features-card">
			<div class="card-header">
				<h3><?php esc_html_e( 'Your Features', 'wpmatch' ); ?></h3>
			</div>

			<div class="card-content">
				<div class="features-grid">
					<div class="feature-item">
						<div class="feature-icon">💕</div>
						<div class="feature-details">
							<h4><?php esc_html_e( 'Daily Likes', 'wpmatch' ); ?></h4>
							<p class="feature-value">
								<?php
								if ( 'unlimited' === $features['daily_likes'] ) {
									esc_html_e( 'Unlimited', 'wpmatch' );
								} else {
									echo esc_html( $features['daily_likes'] . ' ' . __( 'per day', 'wpmatch' ) );
								}
								?>
							</p>
						</div>
					</div>

					<div class="feature-item">
						<div class="feature-icon">👀</div>
						<div class="feature-details">
							<h4><?php esc_html_e( 'See Who Liked You', 'wpmatch' ); ?></h4>
							<p class="feature-value">
								<?php echo $features['see_who_liked'] ? esc_html__( 'Available', 'wpmatch' ) : esc_html__( 'Not Available', 'wpmatch' ); ?>
							</p>
						</div>
					</div>

					<div class="feature-item">
						<div class="feature-icon">🔍</div>
						<div class="feature-details">
							<h4><?php esc_html_e( 'Advanced Search', 'wpmatch' ); ?></h4>
							<p class="feature-value">
								<?php echo $features['advanced_search'] ? esc_html__( 'Available', 'wpmatch' ) : esc_html__( 'Basic Only', 'wpmatch' ); ?>
							</p>
						</div>
					</div>

					<div class="feature-item">
						<div class="feature-icon">📍</div>
						<div class="feature-details">
							<h4><?php esc_html_e( 'Profile Visitors', 'wpmatch' ); ?></h4>
							<p class="feature-value">
								<?php echo $features['profile_visitors'] ? esc_html__( 'Available', 'wpmatch' ) : esc_html__( 'Not Available', 'wpmatch' ); ?>
							</p>
						</div>
					</div>

					<div class="feature-item">
						<div class="feature-icon">✅</div>
						<div class="feature-details">
							<h4><?php esc_html_e( 'Read Receipts', 'wpmatch' ); ?></h4>
							<p class="feature-value">
								<?php echo $features['read_receipts'] ? esc_html__( 'Available', 'wpmatch' ) : esc_html__( 'Not Available', 'wpmatch' ); ?>
							</p>
						</div>
					</div>

					<div class="feature-item">
						<div class="feature-icon">🚀</div>
						<div class="feature-details">
							<h4><?php esc_html_e( 'Profile Boost', 'wpmatch' ); ?></h4>
							<p class="feature-value">
								<?php
								if ( $features['profile_boost'] ) {
									echo esc_html( ucfirst( $features['profile_boost'] ) );
								} else {
									esc_html_e( 'Not Available', 'wpmatch' );
								}
								?>
							</p>
						</div>
					</div>

					<div class="feature-item">
						<div class="feature-icon">🎧</div>
						<div class="feature-details">
							<h4><?php esc_html_e( 'Priority Support', 'wpmatch' ); ?></h4>
							<p class="feature-value">
								<?php echo $features['priority_support'] ? esc_html__( 'Available', 'wpmatch' ) : esc_html__( 'Standard', 'wpmatch' ); ?>
							</p>
						</div>
					</div>

					<div class="feature-item">
						<div class="feature-icon">⭐</div>
						<div class="feature-details">
							<h4><?php esc_html_e( 'Profile Badge', 'wpmatch' ); ?></h4>
							<p class="feature-value">
								<?php
								if ( $features['profile_badge'] ) {
									echo esc_html( ucfirst( $features['profile_badge'] ) );
								} else {
									esc_html_e( 'None', 'wpmatch' );
								}
								?>
							</p>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Subscription Management -->
		<?php if ( ! empty( $active_subscriptions ) ) : ?>
			<div class="subscription-management-card">
				<div class="card-header">
					<h3><?php esc_html_e( 'Subscription Management', 'wpmatch' ); ?></h3>
				</div>

				<div class="card-content">
					<?php foreach ( $active_subscriptions as $subscription ) : ?>
						<div class="subscription-item">
							<div class="subscription-details">
								<h4><?php echo esc_html( $subscription->get_name() ); ?></h4>
								<p class="subscription-meta">
									<?php
									/* translators: %1$s: next payment amount, %2$s: next payment date */
									printf(
										esc_html__( 'Next payment: %1$s on %2$s', 'wpmatch' ),
										esc_html( $subscription->get_formatted_order_total() ),
										esc_html( $subscription->get_date_to_display( 'next_payment' ) )
									);
									?>
								</p>
								<p class="subscription-status">
									<?php esc_html_e( 'Status:', 'wpmatch' ); ?>
									<span class="status-badge status-<?php echo esc_attr( $subscription->get_status() ); ?>">
										<?php echo esc_html( wcs_get_subscription_status_name( $subscription->get_status() ) ); ?>
									</span>
								</p>
							</div>

							<div class="subscription-actions">
								<?php if ( 'active' === $subscription->get_status() ) : ?>
									<form method="post" class="subscription-action-form">
										<?php wp_nonce_field( 'wpmatch_membership_action', 'wpmatch_membership_nonce' ); ?>
										<input type="hidden" name="wpmatch_membership_action" value="cancel_subscription">
										<input type="hidden" name="subscription_id" value="<?php echo esc_attr( $subscription->get_id() ); ?>">
										<button type="submit" class="cancel-subscription-btn" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to cancel your subscription?', 'wpmatch' ); ?>')">
											<?php esc_html_e( 'Cancel Subscription', 'wpmatch' ); ?>
										</button>
									</form>
								<?php elseif ( 'on-hold' === $subscription->get_status() ) : ?>
									<form method="post" class="subscription-action-form">
										<?php wp_nonce_field( 'wpmatch_membership_action', 'wpmatch_membership_nonce' ); ?>
										<input type="hidden" name="wpmatch_membership_action" value="reactivate_subscription">
										<input type="hidden" name="subscription_id" value="<?php echo esc_attr( $subscription->get_id() ); ?>">
										<button type="submit" class="reactivate-subscription-btn">
											<?php esc_html_e( 'Reactivate Subscription', 'wpmatch' ); ?>
										</button>
									</form>
								<?php endif; ?>

								<a href="<?php echo esc_url( $subscription->get_view_order_url() ); ?>" class="view-subscription-btn">
									<?php esc_html_e( 'View Details', 'wpmatch' ); ?>
								</a>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<!-- Feature Usage Stats (if available) -->
		<?php
		$usage_stats = get_user_meta( $user_id, '_wpmatch_usage_stats', true );
		if ( is_array( $usage_stats ) && ! empty( $usage_stats ) ) :
			?>
			<div class="usage-stats-card">
				<div class="card-header">
					<h3><?php esc_html_e( 'Usage Statistics', 'wpmatch' ); ?></h3>
					<p class="card-subtitle"><?php esc_html_e( 'This month', 'wpmatch' ); ?></p>
				</div>

				<div class="card-content">
					<div class="stats-grid">
						<?php if ( isset( $usage_stats['likes_sent'] ) ) : ?>
							<div class="stat-item">
								<div class="stat-number"><?php echo esc_html( $usage_stats['likes_sent'] ); ?></div>
								<div class="stat-label"><?php esc_html_e( 'Likes Sent', 'wpmatch' ); ?></div>
							</div>
						<?php endif; ?>

						<?php if ( isset( $usage_stats['likes_received'] ) ) : ?>
							<div class="stat-item">
								<div class="stat-number"><?php echo esc_html( $usage_stats['likes_received'] ); ?></div>
								<div class="stat-label"><?php esc_html_e( 'Likes Received', 'wpmatch' ); ?></div>
							</div>
						<?php endif; ?>

						<?php if ( isset( $usage_stats['matches'] ) ) : ?>
							<div class="stat-item">
								<div class="stat-number"><?php echo esc_html( $usage_stats['matches'] ); ?></div>
								<div class="stat-label"><?php esc_html_e( 'New Matches', 'wpmatch' ); ?></div>
							</div>
						<?php endif; ?>

						<?php if ( isset( $usage_stats['messages_sent'] ) ) : ?>
							<div class="stat-item">
								<div class="stat-number"><?php echo esc_html( $usage_stats['messages_sent'] ); ?></div>
								<div class="stat-label"><?php esc_html_e( 'Messages Sent', 'wpmatch' ); ?></div>
							</div>
						<?php endif; ?>

						<?php if ( isset( $usage_stats['profile_views'] ) ) : ?>
							<div class="stat-item">
								<div class="stat-number"><?php echo esc_html( $usage_stats['profile_views'] ); ?></div>
								<div class="stat-label"><?php esc_html_e( 'Profile Views', 'wpmatch' ); ?></div>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<!-- Membership History -->
		<?php if ( ! empty( $membership_logs ) ) : ?>
			<div class="membership-history-card">
				<div class="card-header">
					<h3><?php esc_html_e( 'Membership History', 'wpmatch' ); ?></h3>
				</div>

				<div class="card-content">
					<div class="history-timeline">
						<?php
						// Show last 10 events.
						$recent_logs = array_slice( array_reverse( $membership_logs ), 0, 10 );
						foreach ( $recent_logs as $log ) :
							?>
							<div class="timeline-item">
								<div class="timeline-date">
									<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $log['timestamp'] ) ); ?>
								</div>
								<div class="timeline-content">
									<h4 class="timeline-title">
										<?php
										switch ( $log['event'] ) {
											case 'membership_activated':
												esc_html_e( 'Membership Activated', 'wpmatch' );
												break;
											case 'membership_suspended':
												esc_html_e( 'Membership Suspended', 'wpmatch' );
												break;
											case 'membership_deactivated':
												esc_html_e( 'Membership Deactivated', 'wpmatch' );
												break;
											case 'membership_expired':
												esc_html_e( 'Membership Expired', 'wpmatch' );
												break;
											case 'subscription_status_change':
												esc_html_e( 'Subscription Status Changed', 'wpmatch' );
												break;
											default:
												echo esc_html( ucfirst( str_replace( '_', ' ', $log['event'] ) ) );
										}
										?>
									</h4>
									<?php if ( isset( $log['data']['membership_level'] ) ) : ?>
										<p class="timeline-description">
											<?php
											/* translators: %s: membership level */
											printf( esc_html__( 'Level: %s', 'wpmatch' ), esc_html( ucfirst( str_replace( '-', ' ', $log['data']['membership_level'] ) ) ) );
											?>
										</p>
									<?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<!-- Upgrade Prompt for Free Users -->
		<?php if ( 'free' === $current_membership ) : ?>
			<div class="upgrade-prompt-card">
				<div class="card-header">
					<h3><?php esc_html_e( 'Unlock Premium Features', 'wpmatch' ); ?></h3>
				</div>

				<div class="card-content">
					<div class="upgrade-benefits">
						<ul class="benefits-list">
							<li>💕 <?php esc_html_e( 'Unlimited daily likes', 'wpmatch' ); ?></li>
							<li>👀 <?php esc_html_e( 'See who liked your profile', 'wpmatch' ); ?></li>
							<li>🔍 <?php esc_html_e( 'Advanced search filters', 'wpmatch' ); ?></li>
							<li>📍 <?php esc_html_e( 'See who visited your profile', 'wpmatch' ); ?></li>
							<li>✅ <?php esc_html_e( 'Read receipts for messages', 'wpmatch' ); ?></li>
							<li>🚀 <?php esc_html_e( 'Profile boost for better visibility', 'wpmatch' ); ?></li>
							<li>🎧 <?php esc_html_e( 'Priority customer support', 'wpmatch' ); ?></li>
						</ul>

						<div class="upgrade-action">
							<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) . '?product_cat=wpmatch-memberships' ); ?>" class="upgrade-button-large">
								<?php esc_html_e( 'Browse Membership Plans', 'wpmatch' ); ?>
							</a>
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>

<!-- Dashboard Notices -->
<?php if ( isset( $_GET['cancelled'] ) && '1' === $_GET['cancelled'] ) : ?>
	<div class="wpmatch-notice notice-success">
		<p><?php esc_html_e( 'Your subscription has been cancelled successfully.', 'wpmatch' ); ?></p>
	</div>
<?php endif; ?>

<?php if ( isset( $_GET['reactivated'] ) && '1' === $_GET['reactivated'] ) : ?>
	<div class="wpmatch-notice notice-success">
		<p><?php esc_html_e( 'Your subscription has been reactivated successfully.', 'wpmatch' ); ?></p>
	</div>
<?php endif; ?>