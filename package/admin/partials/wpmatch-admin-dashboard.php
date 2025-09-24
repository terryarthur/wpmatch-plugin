<?php
/**
 * Admin dashboard view with comprehensive analytics and management tools
 *
 * @package WPMatch
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get dashboard statistics.
global $wpdb;

// Total users with dating profiles.
$total_users = $wpdb->get_var(
	"SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}wpmatch_user_profiles"
);

// Active users (logged in within 30 days).
$active_users = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(DISTINCT user_id)
		FROM {$wpdb->usermeta}
		WHERE meta_key = 'wpmatch_last_active'
		AND meta_value > %s",
		date( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
	)
);

// Complete profiles.
$complete_profiles = $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->prefix}wpmatch_user_profiles
	WHERE completion_percentage >= 80"
);

// Total matches.
$total_matches = $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->prefix}wpmatch_matches
	WHERE status = 'active'"
);

// Total messages.
$total_messages = $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->prefix}wpmatch_messages"
);

// Daily active users.
$daily_active = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(DISTINCT user_id)
		FROM {$wpdb->usermeta}
		WHERE meta_key = 'wpmatch_last_active'
		AND meta_value > %s",
		date( 'Y-m-d H:i:s', strtotime( '-24 hours' ) )
	)
);

// Recent user registrations (last 7 days).
$recent_registrations = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->users}
		WHERE user_registered > %s",
		date( 'Y-m-d H:i:s', strtotime( '-7 days' ) )
	)
);

// Premium members (if membership system exists).
$premium_members = $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->usermeta}
	WHERE meta_key = 'wpmatch_membership_level'
	AND meta_value != 'free'"
);

// Get recent activity.
$recent_matches = $wpdb->get_results(
	"SELECT m.*, u1.display_name as user1_name, u2.display_name as user2_name
	FROM {$wpdb->prefix}wpmatch_matches m
	LEFT JOIN {$wpdb->users} u1 ON m.user1_id = u1.ID
	LEFT JOIN {$wpdb->users} u2 ON m.user2_id = u2.ID
	WHERE m.status = 'active'
	ORDER BY m.matched_at DESC
	LIMIT 5"
);

// Reports requiring moderation.
$pending_reports = $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->prefix}wpmatch_reports
	WHERE status = 'pending'"
);

// Photo verification requests.
$verification_requests = $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->prefix}wpmatch_verification_requests
	WHERE status = 'pending'"
);
?>

<div class="wrap wpmatch-admin">
	<div class="wpmatch-admin-header">
		<div class="wpmatch-header-content">
			<div class="wpmatch-header-main">
				<h1>
					<span class="dashicons dashicons-analytics"></span>
					<?php esc_html_e( 'WPMatch Dashboard', 'wpmatch' ); ?>
				</h1>
				<p><?php esc_html_e( 'Monitor your dating platform performance, user engagement, and growth metrics.', 'wpmatch' ); ?></p>
			</div>
			<div class="wpmatch-header-actions">
				<a href="#" class="wpmatch-button wpmatch-button-upgrade">
					<span class="dashicons dashicons-star-filled"></span>
					<?php esc_html_e( 'Upgrade to Pro', 'wpmatch' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpmatch-settings' ) ); ?>" class="wpmatch-button secondary">
					<span class="dashicons dashicons-admin-settings"></span>
					<?php esc_html_e( 'Settings', 'wpmatch' ); ?>
				</a>
			</div>
		</div>
	</div>

	<!-- Main Statistics Grid -->
	<div class="wpmatch-stats-grid">
		<div class="wpmatch-stat-card">
			<div class="wpmatch-stat-header">
				<span class="dashicons dashicons-groups"></span>
				<span class="wpmatch-stat-label"><?php esc_html_e( 'Total Users', 'wpmatch' ); ?></span>
			</div>
			<div class="wpmatch-stat-number"><?php echo esc_html( number_format( $total_users ?: 0 ) ); ?></div>
			<div class="wpmatch-stat-change positive">
				<span class="dashicons dashicons-arrow-up-alt"></span>
				<?php printf( esc_html__( '+%d this week', 'wpmatch' ), $recent_registrations ?: 0 ); ?>
			</div>
		</div>

		<div class="wpmatch-stat-card">
			<div class="wpmatch-stat-header">
				<span class="dashicons dashicons-clock"></span>
				<span class="wpmatch-stat-label"><?php esc_html_e( 'Active Users', 'wpmatch' ); ?></span>
			</div>
			<div class="wpmatch-stat-number"><?php echo esc_html( number_format( $active_users ?: 0 ) ); ?></div>
			<div class="wpmatch-stat-change">
				<span class="dashicons dashicons-visibility"></span>
				<?php printf( esc_html__( '%d online today', 'wpmatch' ), $daily_active ?: 0 ); ?>
			</div>
		</div>

		<div class="wpmatch-stat-card">
			<div class="wpmatch-stat-header">
				<span class="dashicons dashicons-heart"></span>
				<span class="wpmatch-stat-label"><?php esc_html_e( 'Total Matches', 'wpmatch' ); ?></span>
			</div>
			<div class="wpmatch-stat-number"><?php echo esc_html( number_format( $total_matches ?: 0 ) ); ?></div>
			<div class="wpmatch-stat-change positive">
				<span class="dashicons dashicons-arrow-up-alt"></span>
				<?php esc_html_e( 'Connecting hearts', 'wpmatch' ); ?>
			</div>
		</div>

		<div class="wpmatch-stat-card">
			<div class="wpmatch-stat-header">
				<span class="dashicons dashicons-email-alt"></span>
				<span class="wpmatch-stat-label"><?php esc_html_e( 'Messages Sent', 'wpmatch' ); ?></span>
			</div>
			<div class="wpmatch-stat-number"><?php echo esc_html( number_format( $total_messages ?: 0 ) ); ?></div>
			<div class="wpmatch-stat-change positive">
				<span class="dashicons dashicons-format-chat"></span>
				<?php esc_html_e( 'Conversations flowing', 'wpmatch' ); ?>
			</div>
		</div>
	</div>

	<!-- Quick Actions and Alerts -->
	<div class="wpmatch-dashboard-row">
		<div class="wpmatch-dashboard-col-8">
			<!-- Recent Activity -->
			<div class="wpmatch-dashboard-card">
				<div class="wpmatch-card-header">
					<h3><span class="dashicons dashicons-heart"></span> <?php esc_html_e( 'Recent Matches', 'wpmatch' ); ?></h3>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpmatch-reports' ) ); ?>" class="wpmatch-button small">
						<?php esc_html_e( 'View All', 'wpmatch' ); ?>
					</a>
				</div>
				<div class="wpmatch-card-content">
					<?php if ( $recent_matches ) : ?>
						<div class="wpmatch-recent-matches">
							<?php foreach ( $recent_matches as $match ) : ?>
								<div class="wpmatch-match-item">
									<div class="wpmatch-match-users">
										<span class="wpmatch-user-name"><?php echo esc_html( $match->user1_name ?: 'Unknown User' ); ?></span>
										<span class="wpmatch-match-icon">💕</span>
										<span class="wpmatch-user-name"><?php echo esc_html( $match->user2_name ?: 'Unknown User' ); ?></span>
									</div>
									<div class="wpmatch-match-time">
										<?php echo esc_html( human_time_diff( strtotime( $match->matched_at ) ) . ' ago' ); ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<p class="wpmatch-empty-state">
							<span class="dashicons dashicons-info"></span>
							<?php esc_html_e( 'No matches yet. Start by creating some user profiles!', 'wpmatch' ); ?>
						</p>
					<?php endif; ?>
				</div>
			</div>

			<!-- Platform Health -->
			<div class="wpmatch-dashboard-card">
				<div class="wpmatch-card-header">
					<h3><span class="dashicons dashicons-chart-line"></span> <?php esc_html_e( 'Platform Health', 'wpmatch' ); ?></h3>
				</div>
				<div class="wpmatch-card-content">
					<div class="wpmatch-health-metrics">
						<div class="wpmatch-health-item">
							<div class="wpmatch-health-label"><?php esc_html_e( 'Profile Completion Rate', 'wpmatch' ); ?></div>
							<div class="wpmatch-health-bar">
								<?php
								$completion_rate = $total_users > 0 ? round( ( $complete_profiles / $total_users ) * 100 ) : 0;
								?>
								<div class="wpmatch-health-fill" style="width: <?php echo esc_attr( $completion_rate ); ?>%"></div>
							</div>
							<div class="wpmatch-health-value"><?php echo esc_html( $completion_rate ); ?>%</div>
						</div>

						<div class="wpmatch-health-item">
							<div class="wpmatch-health-label"><?php esc_html_e( 'User Engagement', 'wpmatch' ); ?></div>
							<div class="wpmatch-health-bar">
								<?php
								$engagement_rate = $total_users > 0 ? round( ( $active_users / $total_users ) * 100 ) : 0;
								?>
								<div class="wpmatch-health-fill" style="width: <?php echo esc_attr( $engagement_rate ); ?>%"></div>
							</div>
							<div class="wpmatch-health-value"><?php echo esc_html( $engagement_rate ); ?>%</div>
						</div>

						<div class="wpmatch-health-item">
							<div class="wpmatch-health-label"><?php esc_html_e( 'Match Success Rate', 'wpmatch' ); ?></div>
							<div class="wpmatch-health-bar">
								<?php
								$match_rate = $total_users > 1 ? round( ( $total_matches / ( $total_users / 2 ) ) * 100 ) : 0;
								$match_rate = min( $match_rate, 100 ); // Cap at 100%
								?>
								<div class="wpmatch-health-fill" style="width: <?php echo esc_attr( $match_rate ); ?>%"></div>
							</div>
							<div class="wpmatch-health-value"><?php echo esc_html( $match_rate ); ?>%</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="wpmatch-dashboard-col-4">
			<!-- Quick Setup -->
			<?php if ( $total_users < 5 ) : ?>
			<div class="wpmatch-dashboard-card setup-card">
				<div class="wpmatch-card-header">
					<h3><span class="dashicons dashicons-magic-wand"></span> <?php esc_html_e( 'Quick Setup', 'wpmatch' ); ?></h3>
				</div>
				<div class="wpmatch-card-content">
					<p><?php esc_html_e( 'Get your dating platform ready in minutes!', 'wpmatch' ); ?></p>

					<div class="wpmatch-setup-actions">
						<button id="wpmatch-generate-sample-data" class="wpmatch-button primary full-width">
							<span class="dashicons dashicons-admin-users"></span>
							<?php esc_html_e( 'Generate Demo Users', 'wpmatch' ); ?>
						</button>

						<button id="wpmatch-create-demo-pages" class="wpmatch-button secondary full-width">
							<span class="dashicons dashicons-admin-page"></span>
							<?php esc_html_e( 'Create Dating Pages', 'wpmatch' ); ?>
						</button>
					</div>

					<div class="wpmatch-setup-note">
						<span class="dashicons dashicons-info"></span>
						<small><?php esc_html_e( 'This creates realistic demo profiles and essential pages with working shortcodes.', 'wpmatch' ); ?></small>
					</div>
				</div>
			</div>
			<?php endif; ?>

			<!-- Moderation Alerts -->
			<?php if ( $pending_reports > 0 || $verification_requests > 0 ) : ?>
			<div class="wpmatch-dashboard-card alert-card">
				<div class="wpmatch-card-header">
					<h3><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Requires Attention', 'wpmatch' ); ?></h3>
				</div>
				<div class="wpmatch-card-content">
					<?php if ( $pending_reports > 0 ) : ?>
						<div class="wpmatch-alert-item">
							<span class="wpmatch-alert-count"><?php echo esc_html( $pending_reports ); ?></span>
							<span class="wpmatch-alert-text"><?php esc_html_e( 'Reports pending review', 'wpmatch' ); ?></span>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpmatch-moderation' ) ); ?>" class="wpmatch-alert-action">
								<?php esc_html_e( 'Review', 'wpmatch' ); ?>
							</a>
						</div>
					<?php endif; ?>

					<?php if ( $verification_requests > 0 ) : ?>
						<div class="wpmatch-alert-item">
							<span class="wpmatch-alert-count"><?php echo esc_html( $verification_requests ); ?></span>
							<span class="wpmatch-alert-text"><?php esc_html_e( 'Verification requests', 'wpmatch' ); ?></span>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpmatch-verification' ) ); ?>" class="wpmatch-alert-action">
								<?php esc_html_e( 'Review', 'wpmatch' ); ?>
							</a>
						</div>
					<?php endif; ?>
				</div>
			</div>
			<?php endif; ?>

			<!-- Revenue Summary -->
			<div class="wpmatch-dashboard-card">
				<div class="wpmatch-card-header">
					<h3><span class="dashicons dashicons-money-alt"></span> <?php esc_html_e( 'Revenue Overview', 'wpmatch' ); ?></h3>
				</div>
				<div class="wpmatch-card-content">
					<div class="wpmatch-revenue-stats">
						<div class="wpmatch-revenue-item">
							<div class="wpmatch-revenue-value">$<?php echo esc_html( number_format( 0 ) ); ?></div>
							<div class="wpmatch-revenue-label"><?php esc_html_e( 'This Month', 'wpmatch' ); ?></div>
						</div>
						<div class="wpmatch-revenue-item">
							<div class="wpmatch-revenue-value"><?php echo esc_html( $premium_members ?: 0 ); ?></div>
							<div class="wpmatch-revenue-label"><?php esc_html_e( 'Premium Members', 'wpmatch' ); ?></div>
						</div>
					</div>

					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpmatch-membership-setup' ) ); ?>" class="wpmatch-button secondary full-width">
						<span class="dashicons dashicons-cart"></span>
						<?php esc_html_e( 'Setup Memberships', 'wpmatch' ); ?>
					</a>
				</div>
			</div>

			<!-- Quick Actions -->
			<div class="wpmatch-dashboard-card">
				<div class="wpmatch-card-header">
					<h3><span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'Quick Actions', 'wpmatch' ); ?></h3>
				</div>
				<div class="wpmatch-card-content">
					<div class="wpmatch-quick-actions">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpmatch-users' ) ); ?>" class="wpmatch-quick-action">
							<span class="dashicons dashicons-admin-users"></span>
							<span><?php esc_html_e( 'Manage Users', 'wpmatch' ); ?></span>
						</a>

						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpmatch-reports' ) ); ?>" class="wpmatch-quick-action">
							<span class="dashicons dashicons-chart-bar"></span>
							<span><?php esc_html_e( 'View Analytics', 'wpmatch' ); ?></span>
						</a>

						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpmatch-settings' ) ); ?>" class="wpmatch-quick-action">
							<span class="dashicons dashicons-admin-settings"></span>
							<span><?php esc_html_e( 'Plugin Settings', 'wpmatch' ); ?></span>
						</a>

						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpmatch-admin-help' ) ); ?>" class="wpmatch-quick-action">
							<span class="dashicons dashicons-sos"></span>
							<span><?php esc_html_e( 'Get Help', 'wpmatch' ); ?></span>
						</a>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- AJAX Loading States -->
<div id="wpmatch-loading-overlay" style="display: none;">
	<div class="wpmatch-loading-content">
		<div class="wpmatch-spinner"></div>
		<p id="wpmatch-loading-message"><?php esc_html_e( 'Processing...', 'wpmatch' ); ?></p>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// Handle demo data generation
	$('#wpmatch-generate-sample-data').on('click', function() {
		var $button = $(this);
		var originalText = $button.text();

		$button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + '<?php echo esc_js( __( 'Creating Users...', 'wpmatch' ) ); ?>');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpmatch_generate_sample_data',
				nonce: '<?php echo esc_js( wp_create_nonce( 'wpmatch_admin_nonce' ) ); ?>'
			},
			success: function(response) {
				if (response.success) {
					$button.removeClass('primary').addClass('success').html('<span class="dashicons dashicons-yes"></span> ' + response.data.message);
					setTimeout(function() {
						location.reload();
					}, 2000);
				} else {
					alert('Error: ' + response.data.message);
					$button.prop('disabled', false).text(originalText);
				}
			},
			error: function() {
				alert('<?php echo esc_js( __( 'Network error. Please try again.', 'wpmatch' ) ); ?>');
				$button.prop('disabled', false).text(originalText);
			}
		});
	});

	// Handle demo pages creation
	$('#wpmatch-create-demo-pages').on('click', function() {
		var $button = $(this);
		var originalText = $button.text();

		$button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + '<?php echo esc_js( __( 'Creating Pages...', 'wpmatch' ) ); ?>');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpmatch_create_demo_pages',
				nonce: '<?php echo esc_js( wp_create_nonce( 'wpmatch_admin_nonce' ) ); ?>'
			},
			success: function(response) {
				if (response.success) {
					$button.removeClass('secondary').addClass('success').html('<span class="dashicons dashicons-yes"></span> ' + response.data.message);
					setTimeout(function() {
						location.reload();
					}, 2000);
				} else {
					alert('Error: ' + response.data.message);
					$button.prop('disabled', false).text(originalText);
				}
			},
			error: function() {
				alert('<?php echo esc_js( __( 'Network error. Please try again.', 'wpmatch' ) ); ?>');
				$button.prop('disabled', false).text(originalText);
			}
		});
	});

	// Auto-refresh stats every 5 minutes
	setInterval(function() {
		location.reload();
	}, 300000);
});
</script>