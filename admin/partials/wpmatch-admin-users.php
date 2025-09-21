<?php
/**
 * Admin users view with unified header design and upsell opportunities
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
					<span class="dashicons dashicons-admin-users"></span>
					<?php esc_html_e( 'Dating Users', 'wpmatch' ); ?>
				</h1>
				<p><?php esc_html_e( 'Manage your dating community members, moderate profiles, and ensure user safety.', 'wpmatch' ); ?></p>
			</div>
			<div class="wpmatch-header-actions">
				<a href="#" class="wpmatch-button wpmatch-button-upgrade">
					<span class="dashicons dashicons-star-filled"></span>
					<?php esc_html_e( 'Upgrade for Advanced User Management', 'wpmatch' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" class="wpmatch-button secondary">
					<span class="dashicons dashicons-admin-users"></span>
					<?php esc_html_e( 'WordPress Users', 'wpmatch' ); ?>
				</a>
			</div>
		</div>
		<div class="wpmatch-upgrade-notice">
			<div class="wpmatch-upgrade-content">
				<span class="dashicons dashicons-info"></span>
				<div class="wpmatch-upgrade-text">
					<strong><?php esc_html_e( 'Pro Feature:', 'wpmatch' ); ?></strong>
					<?php esc_html_e( 'Advanced user management, profile verification, and moderation tools are available in WPMatch Pro.', 'wpmatch' ); ?>
				</div>
				<a href="#" class="wpmatch-upgrade-link"><?php esc_html_e( 'Learn More', 'wpmatch' ); ?></a>
			</div>
		</div>
	</div>

	<div class="card">
		<h2><?php esc_html_e( 'User Management', 'wpmatch' ); ?></h2>
		<p><?php esc_html_e( 'Manage your dating site members, view profiles, and moderate content.', 'wpmatch' ); ?></p>

		<p><em><?php esc_html_e( 'User management functionality will be implemented in the next development phase.', 'wpmatch' ); ?></em></p>

		<h3><?php esc_html_e( 'Available Features (Coming Soon)', 'wpmatch' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'View and search user profiles', 'wpmatch' ); ?></li>
			<li><?php esc_html_e( 'Verify user accounts', 'wpmatch' ); ?></li>
			<li><?php esc_html_e( 'Suspend or ban problematic users', 'wpmatch' ); ?></li>
			<li><?php esc_html_e( 'Review reported content', 'wpmatch' ); ?></li>
			<li><?php esc_html_e( 'Bulk user operations', 'wpmatch' ); ?></li>
		</ul>

		<a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" class="wpmatch-button"><?php esc_html_e( 'View WordPress Users', 'wpmatch' ); ?></a>
	</div>
</div>