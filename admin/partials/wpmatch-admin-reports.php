<?php
/**
 * Admin reports view with unified header design and upsell opportunities
 *
 * @package WPMatch
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap wpmatch-admin">
	<div class="wpmatch-admin-header">
		<div class="wpmatch-header-content">
			<div class="wpmatch-header-main">
				<h1>
					<span class="dashicons dashicons-chart-area"></span>
					<?php esc_html_e( 'Analytics & Reports', 'wpmatch' ); ?>
				</h1>
				<p><?php esc_html_e( 'Track your dating platform success with detailed analytics, user behavior insights, and revenue metrics.', 'wpmatch' ); ?></p>
			</div>
			<div class="wpmatch-header-actions">
				<a href="#" class="wpmatch-button wpmatch-button-upgrade">
					<span class="dashicons dashicons-star-filled"></span>
					<?php esc_html_e( 'Unlock Premium Analytics', 'wpmatch' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpmatch-settings' ) ); ?>" class="wpmatch-button secondary">
					<span class="dashicons dashicons-admin-settings"></span>
					<?php esc_html_e( 'Configure Tracking', 'wpmatch' ); ?>
				</a>
			</div>
		</div>
		<div class="wpmatch-upgrade-notice">
			<div class="wpmatch-upgrade-content">
				<span class="dashicons dashicons-chart-line"></span>
				<div class="wpmatch-upgrade-text">
					<strong><?php esc_html_e( 'Pro Analytics:', 'wpmatch' ); ?></strong>
					<?php esc_html_e( 'Get detailed conversion tracking, A/B testing, revenue analytics, and custom reporting dashboards.', 'wpmatch' ); ?>
				</div>
				<a href="#" class="wpmatch-upgrade-link"><?php esc_html_e( 'View Features', 'wpmatch' ); ?></a>
			</div>
		</div>
	</div>

	<div class="card">
		<h2><?php esc_html_e( 'Analytics & Reports', 'wpmatch' ); ?></h2>
		<p><?php esc_html_e( 'View detailed analytics about your dating site performance and user engagement.', 'wpmatch' ); ?></p>

		<p><em><?php esc_html_e( 'Analytics and reporting functionality will be implemented in the next development phase.', 'wpmatch' ); ?></em></p>

		<h3><?php esc_html_e( 'Available Reports (Coming Soon)', 'wpmatch' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'User registration trends', 'wpmatch' ); ?></li>
			<li><?php esc_html_e( 'Profile completion rates', 'wpmatch' ); ?></li>
			<li><?php esc_html_e( 'Match success rates', 'wpmatch' ); ?></li>
			<li><?php esc_html_e( 'User engagement metrics', 'wpmatch' ); ?></li>
			<li><?php esc_html_e( 'Revenue tracking (premium features)', 'wpmatch' ); ?></li>
			<li><?php esc_html_e( 'Geographic user distribution', 'wpmatch' ); ?></li>
		</ul>
	</div>
</div>