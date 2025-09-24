<?php
/**
 * Beautiful Tabbed Admin Settings View - Enhanced UX
 *
 * @package WPMatch
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = get_option( 'wpmatch_settings', array() );
?>

<div class="wrap wpmatch-admin">
	<div class="wpmatch-admin-header">
		<div class="wpmatch-header-content">
			<div class="wpmatch-header-main">
				<h1>
					<span class="dashicons dashicons-admin-settings"></span>
					<?php esc_html_e( 'WPMatch Settings', 'wpmatch' ); ?>
				</h1>
				<p><?php esc_html_e( 'Configure your dating platform settings using the organized tabs below for easy navigation and management.', 'wpmatch' ); ?></p>
			</div>
			<div class="wpmatch-header-actions">
				<a href="#" class="wpmatch-button wpmatch-button-upgrade">
					<span class="dashicons dashicons-star-filled"></span>
					<?php esc_html_e( 'Upgrade for More Features', 'wpmatch' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpmatch' ) ); ?>" class="wpmatch-button secondary">
					<span class="dashicons dashicons-analytics"></span>
					<?php esc_html_e( 'View Dashboard', 'wpmatch' ); ?>
				</a>
			</div>
		</div>
		<div class="wpmatch-upgrade-notice">
			<div class="wpmatch-upgrade-content">
				<span class="dashicons dashicons-admin-tools"></span>
				<div class="wpmatch-upgrade-text">
					<strong><?php esc_html_e( 'Pro Settings:', 'wpmatch' ); ?></strong>
					<?php esc_html_e( 'Unlock advanced matchmaking algorithms, payment processing, video chat, and premium user features.', 'wpmatch' ); ?>
				</div>
				<a href="#" class="wpmatch-upgrade-link"><?php esc_html_e( 'See All Pro Features', 'wpmatch' ); ?></a>
			</div>
		</div>
	</div>

	<form method="post" action="options.php" id="wpmatch-settings-form">
		<?php settings_fields( 'wpmatch_settings' ); ?>
		<?php do_settings_sections( 'wpmatch_settings' ); ?>

		<!-- Beautiful Tab Navigation -->
		<div class="wpmatch-tabs" role="tablist">
			<button type="button" class="wpmatch-tab active" data-tab="general" role="tab" aria-selected="true" aria-controls="tab-general">
				<span class="dashicons dashicons-admin-settings"></span>
				<?php esc_html_e( 'General', 'wpmatch' ); ?>
			</button>
			<button type="button" class="wpmatch-tab" data-tab="users" role="tab" aria-selected="false" aria-controls="tab-users">
				<span class="dashicons dashicons-admin-users"></span>
				<?php esc_html_e( 'Users & Registration', 'wpmatch' ); ?>
			</button>
			<button type="button" class="wpmatch-tab" data-tab="matching" role="tab" aria-selected="false" aria-controls="tab-matching">
				<span class="dashicons dashicons-heart"></span>
				<?php esc_html_e( 'Matching Algorithm', 'wpmatch' ); ?>
			</button>
			<button type="button" class="wpmatch-tab" data-tab="security" role="tab" aria-selected="false" aria-controls="tab-security">
				<span class="dashicons dashicons-shield"></span>
				<?php esc_html_e( 'Security & Safety', 'wpmatch' ); ?>
			</button>
			<button type="button" class="wpmatch-tab" data-tab="notifications" role="tab" aria-selected="false" aria-controls="tab-notifications">
				<span class="dashicons dashicons-email"></span>
				<?php esc_html_e( 'Notifications', 'wpmatch' ); ?>
			</button>
			<button type="button" class="wpmatch-tab" data-tab="advanced" role="tab" aria-selected="false" aria-controls="tab-advanced">
				<span class="dashicons dashicons-admin-tools"></span>
				<?php esc_html_e( 'Advanced', 'wpmatch' ); ?>
			</button>
		</div>

		<!-- Tab Content Panels -->
		<div class="wpmatch-tab-content">

			<!-- General Settings Tab -->
			<div id="tab-general" class="wpmatch-tab-panel active" role="tabpanel" aria-labelledby="tab-general">
				<div class="card">
					<h2>
						<span class="dashicons dashicons-admin-settings"></span>
						<?php esc_html_e( 'General Settings', 'wpmatch' ); ?>
					</h2>
					<p class="wpmatch-tab-description"><?php esc_html_e( 'Basic configuration options for your dating platform.', 'wpmatch' ); ?></p>

					<table class="form-table wpmatch-form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Site Name', 'wpmatch' ); ?></th>
							<td>
								<input type="text" name="wpmatch_settings[site_name]" class="regular-text"
									value="<?php echo esc_attr( isset( $settings['site_name'] ) ? $settings['site_name'] : get_bloginfo( 'name' ) ); ?>" />
								<p class="description"><?php esc_html_e( 'The name of your dating platform displayed to users.', 'wpmatch' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable Plugin', 'wpmatch' ); ?></th>
							<td>
								<label class="wpmatch-toggle">
									<input type="checkbox" name="wpmatch_settings[enable_plugin]" value="1"
										<?php checked( isset( $settings['enable_plugin'] ) ? $settings['enable_plugin'] : true ); ?> />
									<span class="wpmatch-toggle-slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Enable or disable the WPMatch dating functionality.', 'wpmatch' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Debug Mode', 'wpmatch' ); ?></th>
							<td>
								<label class="wpmatch-toggle">
									<input type="checkbox" name="wpmatch_settings[debug_mode]" value="1"
										<?php checked( isset( $settings['debug_mode'] ) ? $settings['debug_mode'] : false ); ?> />
									<span class="wpmatch-toggle-slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Enable debug logging for troubleshooting (disable in production).', 'wpmatch' ); ?></p>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<!-- Users & Registration Tab -->
			<div id="tab-users" class="wpmatch-tab-panel" role="tabpanel" aria-labelledby="tab-users">
				<div class="card">
					<h2>
						<span class="dashicons dashicons-admin-users"></span>
						<?php esc_html_e( 'User Registration & Profiles', 'wpmatch' ); ?>
					</h2>
					<p class="wpmatch-tab-description"><?php esc_html_e( 'Control how users can register and set up their dating profiles.', 'wpmatch' ); ?></p>

					<table class="form-table wpmatch-form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable Registration', 'wpmatch' ); ?></th>
							<td>
								<label class="wpmatch-toggle">
									<input type="checkbox" name="wpmatch_settings[enable_registration]" value="1"
										<?php checked( isset( $settings['enable_registration'] ) ? $settings['enable_registration'] : true ); ?> />
									<span class="wpmatch-toggle-slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Allow new users to register on your dating site.', 'wpmatch' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable Social Login', 'wpmatch' ); ?></th>
							<td>
								<label class="wpmatch-toggle">
									<input type="checkbox" name="wpmatch_settings[enable_social_login]" value="1"
										<?php checked( isset( $settings['enable_social_login'] ) ? $settings['enable_social_login'] : false ); ?> />
									<span class="wpmatch-toggle-slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Allow users to register and login with social media accounts.', 'wpmatch' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Minimum Age', 'wpmatch' ); ?></th>
							<td>
								<input type="number" name="wpmatch_settings[min_age]" class="small-text"
									value="<?php echo esc_attr( isset( $settings['min_age'] ) ? $settings['min_age'] : 18 ); ?>"
									min="18" max="99" />
								<span class="description"><?php esc_html_e( 'years old', 'wpmatch' ); ?></span>
								<p class="description"><?php esc_html_e( 'Minimum age for users to register (18-99 years).', 'wpmatch' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Maximum Photos', 'wpmatch' ); ?></th>
							<td>
								<input type="number" name="wpmatch_settings[max_photos]" class="small-text"
									value="<?php echo esc_attr( isset( $settings['max_photos'] ) ? $settings['max_photos'] : 10 ); ?>"
									min="1" max="20" />
								<span class="description"><?php esc_html_e( 'photos per profile', 'wpmatch' ); ?></span>
								<p class="description"><?php esc_html_e( 'Maximum number of photos users can upload to their profile.', 'wpmatch' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Profile Completion Required', 'wpmatch' ); ?></th>
							<td>
								<input type="number" name="wpmatch_settings[required_profile_completion]" class="small-text"
									value="<?php echo esc_attr( isset( $settings['required_profile_completion'] ) ? $settings['required_profile_completion'] : 80 ); ?>"
									min="50" max="100" />
								<span class="description">%</span>
								<p class="description"><?php esc_html_e( 'Minimum profile completion percentage required to start matching.', 'wpmatch' ); ?></p>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<!-- Matching Algorithm Tab -->
			<div id="tab-matching" class="wpmatch-tab-panel" role="tabpanel" aria-labelledby="tab-matching">
				<div class="card">
					<h2>
						<span class="dashicons dashicons-heart"></span>
						<?php esc_html_e( 'Matching Algorithm Settings', 'wpmatch' ); ?>
					</h2>
					<p class="wpmatch-tab-description"><?php esc_html_e( 'Fine-tune how the matching algorithm works to create better connections.', 'wpmatch' ); ?></p>

					<table class="form-table wpmatch-form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Default Search Radius', 'wpmatch' ); ?></th>
							<td>
								<input type="number" name="wpmatch_settings[default_search_radius]" class="small-text"
									value="<?php echo esc_attr( isset( $settings['default_search_radius'] ) ? $settings['default_search_radius'] : 50 ); ?>"
									min="1" max="500" />
								<span class="description"><?php esc_html_e( 'miles', 'wpmatch' ); ?></span>
								<p class="description"><?php esc_html_e( 'Default radius for location-based searches.', 'wpmatch' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Daily Match Suggestions', 'wpmatch' ); ?></th>
							<td>
								<input type="number" name="wpmatch_settings[daily_match_suggestions]" class="small-text"
									value="<?php echo esc_attr( isset( $settings['daily_match_suggestions'] ) ? $settings['daily_match_suggestions'] : 5 ); ?>"
									min="1" max="50" />
								<span class="description"><?php esc_html_e( 'potential matches per day', 'wpmatch' ); ?></span>
								<p class="description"><?php esc_html_e( 'Number of daily match suggestions to show each user.', 'wpmatch' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Age Range Weight', 'wpmatch' ); ?></th>
							<td>
								<input type="range" name="wpmatch_settings[age_weight]" class="wpmatch-range"
									value="<?php echo esc_attr( isset( $settings['age_weight'] ) ? $settings['age_weight'] : 30 ); ?>"
									min="0" max="100" />
								<span class="wpmatch-range-value">30%</span>
								<p class="description"><?php esc_html_e( 'How much age compatibility affects matching score.', 'wpmatch' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Location Weight', 'wpmatch' ); ?></th>
							<td>
								<input type="range" name="wpmatch_settings[location_weight]" class="wpmatch-range"
									value="<?php echo esc_attr( isset( $settings['location_weight'] ) ? $settings['location_weight'] : 40 ); ?>"
									min="0" max="100" />
								<span class="wpmatch-range-value">40%</span>
								<p class="description"><?php esc_html_e( 'How much geographic distance affects matching score.', 'wpmatch' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Interests Weight', 'wpmatch' ); ?></th>
							<td>
								<input type="range" name="wpmatch_settings[interests_weight]" class="wpmatch-range"
									value="<?php echo esc_attr( isset( $settings['interests_weight'] ) ? $settings['interests_weight'] : 30 ); ?>"
									min="0" max="100" />
								<span class="wpmatch-range-value">30%</span>
								<p class="description"><?php esc_html_e( 'How much shared interests affect matching score.', 'wpmatch' ); ?></p>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<!-- Security & Safety Tab -->
			<div id="tab-security" class="wpmatch-tab-panel" role="tabpanel" aria-labelledby="tab-security">
				<div class="card">
					<h2>
						<span class="dashicons dashicons-shield"></span>
						<?php esc_html_e( 'Security & Safety Settings', 'wpmatch' ); ?>
					</h2>
					<p class="wpmatch-tab-description"><?php esc_html_e( 'Protect your users with robust security and safety features.', 'wpmatch' ); ?></p>

					<table class="form-table wpmatch-form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Require Email Verification', 'wpmatch' ); ?></th>
							<td>
								<label class="wpmatch-toggle">
									<input type="checkbox" name="wpmatch_settings[require_email_verification]" value="1"
										<?php checked( isset( $settings['require_email_verification'] ) ? $settings['require_email_verification'] : true ); ?> />
									<span class="wpmatch-toggle-slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Require users to verify their email address before accessing the site.', 'wpmatch' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable Photo Verification', 'wpmatch' ); ?></th>
							<td>
								<label class="wpmatch-toggle">
									<input type="checkbox" name="wpmatch_settings[enable_photo_verification]" value="1"
										<?php checked( isset( $settings['enable_photo_verification'] ) ? $settings['enable_photo_verification'] : true ); ?> />
									<span class="wpmatch-toggle-slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Require manual approval of profile photos for safety.', 'wpmatch' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable Reporting System', 'wpmatch' ); ?></th>
							<td>
								<label class="wpmatch-toggle">
									<input type="checkbox" name="wpmatch_settings[enable_reporting]" value="1"
										<?php checked( isset( $settings['enable_reporting'] ) ? $settings['enable_reporting'] : true ); ?> />
									<span class="wpmatch-toggle-slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Allow users to report inappropriate behavior or profiles.', 'wpmatch' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Auto-Block After Reports', 'wpmatch' ); ?></th>
							<td>
								<input type="number" name="wpmatch_settings[auto_block_reports]" class="small-text"
									value="<?php echo esc_attr( isset( $settings['auto_block_reports'] ) ? $settings['auto_block_reports'] : 5 ); ?>"
									min="1" max="20" />
								<span class="description"><?php esc_html_e( 'reports', 'wpmatch' ); ?></span>
								<p class="description"><?php esc_html_e( 'Automatically suspend users after this many reports.', 'wpmatch' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Message Content Filtering', 'wpmatch' ); ?></th>
							<td>
								<label class="wpmatch-toggle">
									<input type="checkbox" name="wpmatch_settings[enable_content_filter]" value="1"
										<?php checked( isset( $settings['enable_content_filter'] ) ? $settings['enable_content_filter'] : true ); ?> />
									<span class="wpmatch-toggle-slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Filter inappropriate content in messages automatically.', 'wpmatch' ); ?></p>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<!-- Notifications Tab -->
			<div id="tab-notifications" class="wpmatch-tab-panel" role="tabpanel" aria-labelledby="tab-notifications">
				<div class="card">
					<h2>
						<span class="dashicons dashicons-email"></span>
						<?php esc_html_e( 'Notification Settings', 'wpmatch' ); ?>
					</h2>
					<p class="wpmatch-tab-description"><?php esc_html_e( 'Configure email and in-app notifications to keep users engaged.', 'wpmatch' ); ?></p>

					<table class="form-table wpmatch-form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Email Notifications', 'wpmatch' ); ?></th>
							<td>
								<label class="wpmatch-toggle">
									<input type="checkbox" name="wpmatch_settings[enable_email_notifications]" value="1"
										<?php checked( isset( $settings['enable_email_notifications'] ) ? $settings['enable_email_notifications'] : true ); ?> />
									<span class="wpmatch-toggle-slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Send email notifications for matches, messages, and activity.', 'wpmatch' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'New Match Notifications', 'wpmatch' ); ?></th>
							<td>
								<label class="wpmatch-toggle">
									<input type="checkbox" name="wpmatch_settings[notify_new_matches]" value="1"
										<?php checked( isset( $settings['notify_new_matches'] ) ? $settings['notify_new_matches'] : true ); ?> />
									<span class="wpmatch-toggle-slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Notify users when they get a new match.', 'wpmatch' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Message Notifications', 'wpmatch' ); ?></th>
							<td>
								<label class="wpmatch-toggle">
									<input type="checkbox" name="wpmatch_settings[notify_new_messages]" value="1"
										<?php checked( isset( $settings['notify_new_messages'] ) ? $settings['notify_new_messages'] : true ); ?> />
									<span class="wpmatch-toggle-slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Notify users when they receive new messages.', 'wpmatch' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Weekly Digest Emails', 'wpmatch' ); ?></th>
							<td>
								<label class="wpmatch-toggle">
									<input type="checkbox" name="wpmatch_settings[weekly_digest]" value="1"
										<?php checked( isset( $settings['weekly_digest'] ) ? $settings['weekly_digest'] : false ); ?> />
									<span class="wpmatch-toggle-slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Send weekly summary emails to keep users engaged.', 'wpmatch' ); ?></p>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<!-- Advanced Tab -->
			<div id="tab-advanced" class="wpmatch-tab-panel" role="tabpanel" aria-labelledby="tab-advanced">
				<div class="card">
					<h2>
						<span class="dashicons dashicons-admin-tools"></span>
						<?php esc_html_e( 'Advanced Settings', 'wpmatch' ); ?>
					</h2>
					<p class="wpmatch-tab-description"><?php esc_html_e( 'Advanced configuration options for power users and developers.', 'wpmatch' ); ?></p>

					<table class="form-table wpmatch-form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'API Rate Limiting', 'wpmatch' ); ?></th>
							<td>
								<input type="number" name="wpmatch_settings[api_rate_limit]" class="small-text"
									value="<?php echo esc_attr( isset( $settings['api_rate_limit'] ) ? $settings['api_rate_limit'] : 100 ); ?>"
									min="10" max="1000" />
								<span class="description"><?php esc_html_e( 'requests per hour', 'wpmatch' ); ?></span>
								<p class="description"><?php esc_html_e( 'Maximum API requests per user per hour.', 'wpmatch' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Cache Duration', 'wpmatch' ); ?></th>
							<td>
								<input type="number" name="wpmatch_settings[cache_duration]" class="small-text"
									value="<?php echo esc_attr( isset( $settings['cache_duration'] ) ? $settings['cache_duration'] : 300 ); ?>"
									min="60" max="3600" />
								<span class="description"><?php esc_html_e( 'seconds', 'wpmatch' ); ?></span>
								<p class="description"><?php esc_html_e( 'How long to cache match results and user data.', 'wpmatch' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Database Cleanup', 'wpmatch' ); ?></th>
							<td>
								<label class="wpmatch-toggle">
									<input type="checkbox" name="wpmatch_settings[auto_cleanup]" value="1"
										<?php checked( isset( $settings['auto_cleanup'] ) ? $settings['auto_cleanup'] : false ); ?> />
									<span class="wpmatch-toggle-slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Automatically clean up old data and inactive accounts.', 'wpmatch' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Custom CSS', 'wpmatch' ); ?></th>
							<td>
								<textarea name="wpmatch_settings[custom_css]" rows="6" cols="50" class="large-text code"><?php echo esc_textarea( isset( $settings['custom_css'] ) ? $settings['custom_css'] : '' ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Add custom CSS to override default styling.', 'wpmatch' ); ?></p>
							</td>
						</tr>
					</table>
				</div>
			</div>

		</div>

		<!-- Save Button (Fixed Position) -->
		<div class="wpmatch-settings-footer">
			<button type="submit" name="submit" class="wpmatch-button primary">
				<span class="dashicons dashicons-saved"></span>
				<?php esc_html_e( 'Save All Settings', 'wpmatch' ); ?>
			</button>
			<button type="button" class="wpmatch-button secondary" id="wpmatch-reset-settings">
				<?php esc_html_e( 'Reset to Defaults', 'wpmatch' ); ?>
			</button>
		</div>
	</form>

	<!-- Settings Import/Export -->
	<div class="card wpmatch-import-export">
		<h2>
			<span class="dashicons dashicons-database-import"></span>
			<?php esc_html_e( 'Import/Export Settings', 'wpmatch' ); ?>
		</h2>
		<p><?php esc_html_e( 'Backup your settings or transfer them to another site.', 'wpmatch' ); ?></p>

		<div class="import-export-actions">
			<div class="export-section">
				<h3><?php esc_html_e( 'Export Settings', 'wpmatch' ); ?></h3>
				<p><?php esc_html_e( 'Download a backup of your current settings.', 'wpmatch' ); ?></p>
				<button type="button" class="wpmatch-button secondary" id="export-settings">
					<span class="dashicons dashicons-download"></span>
					<?php esc_html_e( 'Export Settings', 'wpmatch' ); ?>
				</button>
			</div>

			<div class="import-section">
				<h3><?php esc_html_e( 'Import Settings', 'wpmatch' ); ?></h3>
				<p><?php esc_html_e( 'Upload a settings file to restore configuration.', 'wpmatch' ); ?></p>
				<input type="file" id="import-file" accept=".json" style="margin-bottom: 10px;">
				<button type="button" class="wpmatch-button" id="import-settings" disabled>
					<span class="dashicons dashicons-upload"></span>
					<?php esc_html_e( 'Import Settings', 'wpmatch' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// Tab Navigation
	$('.wpmatch-tab').on('click', function() {
		var targetTab = $(this).data('tab');

		// Update tab buttons
		$('.wpmatch-tab').removeClass('active').attr('aria-selected', 'false');
		$(this).addClass('active').attr('aria-selected', 'true');

		// Update tab panels
		$('.wpmatch-tab-panel').removeClass('active');
		$('#tab-' + targetTab).addClass('active');

		// Save current tab to localStorage
		localStorage.setItem('wpmatch_current_tab', targetTab);
	});

	// Restore last active tab
	var lastTab = localStorage.getItem('wpmatch_current_tab');
	if (lastTab) {
		$('.wpmatch-tab[data-tab="' + lastTab + '"]').click();
	}

	// Range slider updates
	$('.wpmatch-range').on('input', function() {
		var value = $(this).val();
		$(this).next('.wpmatch-range-value').text(value + '%');
	});

	// Initialize range values
	$('.wpmatch-range').each(function() {
		var value = $(this).val();
		$(this).next('.wpmatch-range-value').text(value + '%');
	});

	// Settings form validation
	$('#wpmatch-settings-form').on('submit', function(e) {
		var isValid = true;
		var errorMessages = [];

		// Validate minimum age
		var minAge = parseInt($('input[name="wpmatch_settings[min_age]"]').val());
		if (minAge < 18) {
			errorMessages.push('<?php esc_html_e( 'Minimum age must be at least 18 years.', 'wpmatch' ); ?>');
			isValid = false;
		}

		// Validate matching weights (should total around 100%)
		var ageWeight = parseInt($('input[name="wpmatch_settings[age_weight]"]').val());
		var locationWeight = parseInt($('input[name="wpmatch_settings[location_weight]"]').val());
		var interestsWeight = parseInt($('input[name="wpmatch_settings[interests_weight]"]').val());
		var totalWeight = ageWeight + locationWeight + interestsWeight;

		if (Math.abs(totalWeight - 100) > 10) {
			errorMessages.push('<?php esc_html_e( 'Matching algorithm weights should total approximately 100%.', 'wpmatch' ); ?>');
		}

		if (!isValid) {
			e.preventDefault();
			alert('<?php esc_html_e( 'Please fix the following errors:', 'wpmatch' ); ?>\n\n' + errorMessages.join('\n'));
		} else {
			// Show loading state
			$(this).find('input[type="submit"]').prop('disabled', true).val('<?php esc_html_e( 'Saving...', 'wpmatch' ); ?>');
		}
	});

	// Reset settings confirmation
	$('#wpmatch-reset-settings').on('click', function() {
		if (confirm('<?php esc_html_e( 'Are you sure you want to reset all settings to their default values? This action cannot be undone.', 'wpmatch' ); ?>')) {
			// AJAX request to reset settings
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmatch_reset_settings',
					nonce: '<?php echo esc_js( wp_create_nonce( 'wpmatch_admin_nonce' ) ); ?>'
				},
				success: function(response) {
					if (response.success) {
						alert('<?php esc_html_e( 'Settings have been reset to defaults.', 'wpmatch' ); ?>');
						location.reload();
					} else {
						alert('<?php esc_html_e( 'Error resetting settings. Please try again.', 'wpmatch' ); ?>');
					}
				},
				error: function() {
					alert('<?php esc_html_e( 'Error resetting settings. Please try again.', 'wpmatch' ); ?>');
				}
			});
		}
	});

	// Export settings
	$('#export-settings').on('click', function() {
		var button = $(this);
		var originalText = button.html();

		button.prop('disabled', true).html('<span class="dashicons dashicons-update-alt"></span> <?php esc_html_e( 'Exporting...', 'wpmatch' ); ?>');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpmatch_export_settings',
				nonce: '<?php echo esc_js( wp_create_nonce( 'wpmatch_admin_nonce' ) ); ?>'
			},
			success: function(response) {
				if (response.success) {
					// Create and download file
					var blob = new Blob([JSON.stringify(response.data, null, 2)], {type: 'application/json'});
					var url = window.URL.createObjectURL(blob);
					var a = document.createElement('a');
					a.href = url;
					a.download = 'wpmatch-settings-' + new Date().toISOString().split('T')[0] + '.json';
					document.body.appendChild(a);
					a.click();
					document.body.removeChild(a);
					window.URL.revokeObjectURL(url);
				} else {
					alert('<?php esc_html_e( 'Error exporting settings.', 'wpmatch' ); ?>');
				}
				button.prop('disabled', false).html(originalText);
			},
			error: function() {
				alert('<?php esc_html_e( 'Error exporting settings.', 'wpmatch' ); ?>');
				button.prop('disabled', false).html(originalText);
			}
		});
	});

	// Import file selection
	$('#import-file').on('change', function() {
		var file = this.files[0];
		$('#import-settings').prop('disabled', !file);
	});

	// Import settings
	$('#import-settings').on('click', function() {
		var fileInput = $('#import-file')[0];
		var file = fileInput.files[0];

		if (!file) {
			alert('<?php esc_html_e( 'Please select a file to import.', 'wpmatch' ); ?>');
			return;
		}

		var reader = new FileReader();
		reader.onload = function(e) {
			try {
				var settings = JSON.parse(e.target.result);

				if (confirm('<?php esc_html_e( 'Are you sure you want to import these settings? This will overwrite your current configuration.', 'wpmatch' ); ?>')) {
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'wpmatch_import_settings',
							settings: JSON.stringify(settings),
							nonce: '<?php echo esc_js( wp_create_nonce( 'wpmatch_admin_nonce' ) ); ?>'
						},
						success: function(response) {
							if (response.success) {
								alert('<?php esc_html_e( 'Settings imported successfully.', 'wpmatch' ); ?>');
								location.reload();
							} else {
								alert('<?php esc_html_e( 'Error importing settings: ', 'wpmatch' ); ?>' + response.data);
							}
						},
						error: function() {
							alert('<?php esc_html_e( 'Error importing settings.', 'wpmatch' ); ?>');
						}
					});
				}
			} catch (error) {
				alert('<?php esc_html_e( 'Invalid settings file format.', 'wpmatch' ); ?>');
			}
		};
		reader.readAsText(file);
	});

	// Auto-save settings every 30 seconds if changes detected
	var settingsChanged = false;
	var autoSaveInterval;

	$('#wpmatch-settings-form input, #wpmatch-settings-form textarea, #wpmatch-settings-form select').on('change', function() {
		settingsChanged = true;
		if (!autoSaveInterval) {
			autoSaveInterval = setInterval(function() {
				if (settingsChanged) {
					autoSaveSettings();
				}
			}, 30000);
		}
	});

	function autoSaveSettings() {
		var formData = $('#wpmatch-settings-form').serialize();

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: formData + '&action=wpmatch_auto_save_settings&nonce=<?php echo esc_js( wp_create_nonce( 'wpmatch_admin_nonce' ) ); ?>',
			success: function(response) {
				if (response.success) {
					settingsChanged = false;
					showNotification('<?php esc_html_e( 'Settings auto-saved', 'wpmatch' ); ?>', 'success');
				}
			}
		});
	}

	function showNotification(message, type) {
		var notification = $('<div class="wpmatch-notification ' + type + '">' + message + '</div>');
		$('body').append(notification);

		setTimeout(function() {
			notification.addClass('show');
		}, 100);

		setTimeout(function() {
			notification.removeClass('show');
			setTimeout(function() {
				notification.remove();
			}, 300);
		}, 3000);
	}

	// Keyboard shortcuts
	$(document).on('keydown', function(e) {
		if (e.ctrlKey || e.metaKey) {
			switch(e.keyCode) {
				case 83: // Ctrl+S - Save
					e.preventDefault();
					$('#wpmatch-settings-form').submit();
					break;
				case 49: case 50: case 51: case 52: case 53: case 54: // Ctrl+1-6 - Switch tabs
					e.preventDefault();
					var tabIndex = e.keyCode - 49;
					$('.wpmatch-tab').eq(tabIndex).click();
					break;
			}
		}
	});
});
</script>

<style>
.wpmatch-import-export {
	margin-top: 20px;
	padding: 20px;
}

.import-export-actions {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 30px;
	margin-top: 20px;
}

.export-section, .import-section {
	padding: 20px;
	border: 1px solid #e1e1e1;
	border-radius: 8px;
	background: #fafafa;
}

.export-section h3, .import-section h3 {
	margin-top: 0;
	color: #2c3e50;
}

.wpmatch-notification {
	position: fixed;
	top: 32px;
	right: 20px;
	z-index: 9999;
	padding: 12px 20px;
	border-radius: 6px;
	color: white;
	font-weight: 600;
	transform: translateX(100%);
	transition: transform 0.3s ease;
}

.wpmatch-notification.show {
	transform: translateX(0);
}

.wpmatch-notification.success {
	background: #28a745;
}

.wpmatch-notification.error {
	background: #dc3545;
}

@media (max-width: 768px) {
	.import-export-actions {
		grid-template-columns: 1fr;
	}
}
</style>