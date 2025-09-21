<?php
/**
 * Admin dashboard view with unified header design and upsell opportunities
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

	<div class="wpmatch-stats-grid">
		<div class="wpmatch-stat-card">
			<div class="wpmatch-stat-number" data-stat="total_users">0</div>
			<div class="wpmatch-stat-label"><?php esc_html_e( 'Total Users', 'wpmatch' ); ?></div>
		</div>
		<div class="wpmatch-stat-card">
			<div class="wpmatch-stat-number" data-stat="total_profiles">0</div>
			<div class="wpmatch-stat-label"><?php esc_html_e( 'Total Profiles', 'wpmatch' ); ?></div>
		</div>
		<div class="wpmatch-stat-card">
			<div class="wpmatch-stat-number" data-stat="active_users">0</div>
			<div class="wpmatch-stat-label"><?php esc_html_e( 'Active Users (30 days)', 'wpmatch' ); ?></div>
		</div>
		<div class="wpmatch-stat-card">
			<div class="wpmatch-stat-number" data-stat="complete_profiles">0</div>
			<div class="wpmatch-stat-label"><?php esc_html_e( 'Complete Profiles', 'wpmatch' ); ?></div>
		</div>
	</div>

	<div class="card">
		<h2><?php esc_html_e( 'Welcome to WPMatch!', 'wpmatch' ); ?></h2>
		<p><?php esc_html_e( 'Your WordPress dating plugin is now active. Get started by configuring your settings and creating some user profiles.', 'wpmatch' ); ?></p>

		<div class="wpmatch-quick-setup">
			<h3><span class="dashicons dashicons-magic-wand"></span> <?php esc_html_e( 'Quick Setup (Recommended)', 'wpmatch' ); ?></h3>
			<p><?php esc_html_e( 'Launch your dating site in minutes! These automated tools will set up everything you need to start earning revenue immediately.', 'wpmatch' ); ?></p>

			<div class="wpmatch-setup-buttons">
				<button id="wpmatch-generate-sample-data" class="button button-primary button-large">
					<span class="dashicons dashicons-admin-users"></span>
					<?php esc_html_e( 'Generate Sample Users', 'wpmatch' ); ?>
				</button>
				<button id="wpmatch-create-demo-pages" class="button button-primary button-large">
					<span class="dashicons dashicons-admin-page"></span>
					<?php esc_html_e( 'Create Dating Pages', 'wpmatch' ); ?>
				</button>
			</div>

			<div class="wpmatch-setup-benefits">
				<div class="benefit-item">
					<span class="dashicons dashicons-yes-alt"></span>
					<span><?php esc_html_e( 'Creates 5 realistic demo profiles for testing', 'wpmatch' ); ?></span>
				</div>
				<div class="benefit-item">
					<span class="dashicons dashicons-yes-alt"></span>
					<span><?php esc_html_e( 'Sets up all essential dating pages with working shortcodes', 'wpmatch' ); ?></span>
				</div>
				<div class="benefit-item">
					<span class="dashicons dashicons-yes-alt"></span>
					<span><?php esc_html_e( 'Enables user registration automatically', 'wpmatch' ); ?></span>
				</div>
				<div class="benefit-item">
					<span class="dashicons dashicons-yes-alt"></span>
					<span><?php esc_html_e( 'Ready to start accepting paying members!', 'wpmatch' ); ?></span>
				</div>
			</div>

			<p class="description">
				<?php printf(
					/* translators: %s: link to admin help page */
					esc_html__( 'Need more details? Check the %s for complete setup instructions.', 'wpmatch' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=wpmatch-admin-help' ) ) . '">' . esc_html__( 'Admin Help & Setup page', 'wpmatch' ) . '</a>'
				); ?>
			</p>
		</div>

		<h3><?php esc_html_e( 'Quick Actions', 'wpmatch' ); ?></h3>
		<ul>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpmatch-settings' ) ); ?>"><?php esc_html_e( 'Configure Settings', 'wpmatch' ); ?></a></li>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpmatch-users' ) ); ?>"><?php esc_html_e( 'Manage Users', 'wpmatch' ); ?></a></li>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpmatch-reports' ) ); ?>"><?php esc_html_e( 'View Reports', 'wpmatch' ); ?></a></li>
		</ul>

		<h3><?php esc_html_e( 'Getting Started', 'wpmatch' ); ?></h3>
		<ol>
			<li><?php esc_html_e( 'Configure your plugin settings', 'wpmatch' ); ?></li>
			<li><?php esc_html_e( 'Enable user registration', 'wpmatch' ); ?></li>
			<li><?php esc_html_e( 'Add shortcodes to your pages', 'wpmatch' ); ?></li>
			<li><?php esc_html_e( 'Start building your dating community!', 'wpmatch' ); ?></li>
		</ol>
	</div>
</div>