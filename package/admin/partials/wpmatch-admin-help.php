<?php
/**
 * Admin help and documentation page for site administrators
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
					<span class="dashicons dashicons-editor-help"></span>
					<?php esc_html_e( 'Admin Help & Setup Guide', 'wpmatch' ); ?>
				</h1>
				<p><?php esc_html_e( 'Complete setup guide for site administrators. Learn how to configure WPMatch, create pages, and manage your dating community.', 'wpmatch' ); ?></p>
			</div>
			<div class="wpmatch-header-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpmatch-settings' ) ); ?>" class="wpmatch-button primary">
					<span class="dashicons dashicons-admin-settings"></span>
					<?php esc_html_e( 'Go to Settings', 'wpmatch' ); ?>
				</a>
			</div>
		</div>
	</div>

	<!-- Tab Navigation -->
	<div class="wpmatch-tabs" role="tablist">
		<button type="button" class="wpmatch-tab active" data-tab="quick-start" role="tab" aria-selected="true" aria-controls="tab-quick-start">
			<span class="dashicons dashicons-magic-wand"></span>
			<?php esc_html_e( 'Quick Start', 'wpmatch' ); ?>
		</button>
		<button type="button" class="wpmatch-tab" data-tab="shortcodes" role="tab" aria-selected="false" aria-controls="tab-shortcodes">
			<span class="dashicons dashicons-shortcode"></span>
			<?php esc_html_e( 'Shortcodes', 'wpmatch' ); ?>
		</button>
		<button type="button" class="wpmatch-tab" data-tab="pages-setup" role="tab" aria-selected="false" aria-controls="tab-pages-setup">
			<span class="dashicons dashicons-admin-page"></span>
			<?php esc_html_e( 'Pages Setup', 'wpmatch' ); ?>
		</button>
		<button type="button" class="wpmatch-tab" data-tab="user-management" role="tab" aria-selected="false" aria-controls="tab-user-management">
			<span class="dashicons dashicons-admin-users"></span>
			<?php esc_html_e( 'User Management', 'wpmatch' ); ?>
		</button>
		<button type="button" class="wpmatch-tab" data-tab="troubleshooting" role="tab" aria-selected="false" aria-controls="tab-troubleshooting">
			<span class="dashicons dashicons-sos"></span>
			<?php esc_html_e( 'Troubleshooting', 'wpmatch' ); ?>
		</button>
	</div>

	<!-- Tab Content Panels -->
	<div class="wpmatch-tab-content">

		<!-- Quick Start Tab -->
		<div id="tab-quick-start" class="wpmatch-tab-panel active" role="tabpanel" aria-labelledby="tab-quick-start">
			<div class="card">
				<h2><?php esc_html_e( '🚀 Quick Start Guide', 'wpmatch' ); ?></h2>
				<p><?php esc_html_e( 'Get your dating site up and running in 5 minutes:', 'wpmatch' ); ?></p>

				<div class="wpmatch-setup-steps">
					<div class="wpmatch-step">
						<span class="step-number">1</span>
						<div class="step-content">
							<h3><?php esc_html_e( 'Configure Basic Settings', 'wpmatch' ); ?></h3>
							<p><?php esc_html_e( 'Go to Settings and configure your basic preferences like age limits, distance, and photo requirements.', 'wpmatch' ); ?></p>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpmatch-settings' ) ); ?>" class="wpmatch-button secondary"><?php esc_html_e( 'Open Settings', 'wpmatch' ); ?></a>
						</div>
					</div>

					<div class="wpmatch-step">
						<span class="step-number">2</span>
						<div class="step-content">
							<h3><?php esc_html_e( 'Create Essential Pages', 'wpmatch' ); ?></h3>
							<p><?php esc_html_e( 'Create these essential pages using our shortcodes:', 'wpmatch' ); ?></p>
							<ul>
								<li><strong><?php esc_html_e( 'Dating Profile Page:', 'wpmatch' ); ?></strong> <code>[wpmatch_profile_form]</code></li>
								<li><strong><?php esc_html_e( 'Browse/Swipe Page:', 'wpmatch' ); ?></strong> <code>[wpmatch_swipe]</code></li>
								<li><strong><?php esc_html_e( 'My Matches Page:', 'wpmatch' ); ?></strong> <code>[wpmatch_matches]</code></li>
							</ul>
						</div>
					</div>

					<div class="wpmatch-step">
						<span class="step-number">3</span>
						<div class="step-content">
							<h3><?php esc_html_e( 'Enable User Registration', 'wpmatch' ); ?></h3>
							<p><?php esc_html_e( 'Make sure WordPress user registration is enabled in Settings → General → "Anyone can register"', 'wpmatch' ); ?></p>
							<a href="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>" class="wpmatch-button secondary"><?php esc_html_e( 'WordPress Settings', 'wpmatch' ); ?></a>
						</div>
					</div>

					<div class="wpmatch-step">
						<span class="step-number">4</span>
						<div class="step-content">
							<h3><?php esc_html_e( 'Add Sample Data (Optional)', 'wpmatch' ); ?></h3>
							<p><?php esc_html_e( 'For testing purposes, you can generate sample user profiles to see how the system works.', 'wpmatch' ); ?></p>
							<button type="button" class="wpmatch-button secondary" id="generate-sample-data"><?php esc_html_e( 'Generate Sample Data', 'wpmatch' ); ?></button>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Shortcodes Tab -->
		<div id="tab-shortcodes" class="wpmatch-tab-panel" role="tabpanel" aria-labelledby="tab-shortcodes">
			<div class="card">
				<h2><?php esc_html_e( '📝 Available Shortcodes', 'wpmatch' ); ?></h2>
				<p><?php esc_html_e( 'Use these shortcodes to add dating functionality to any page or post:', 'wpmatch' ); ?></p>

				<div class="wpmatch-shortcode-list">
					<div class="shortcode-item">
						<h3><code>[wpmatch_profile_form]</code></h3>
						<p><?php esc_html_e( 'Displays the profile creation/editing form for logged-in users.', 'wpmatch' ); ?></p>
						<div class="shortcode-example">
							<strong><?php esc_html_e( 'Usage:', 'wpmatch' ); ?></strong>
							<code>[wpmatch_profile_form]</code>
						</div>
						<p><em><?php esc_html_e( 'Best for: Create a dedicated "Edit Profile" page', 'wpmatch' ); ?></em></p>
					</div>

					<div class="shortcode-item">
						<h3><code>[wpmatch_swipe]</code></h3>
						<p><?php esc_html_e( 'Displays the Tinder-style swipe interface for browsing potential matches.', 'wpmatch' ); ?></p>
						<div class="shortcode-example">
							<strong><?php esc_html_e( 'Usage:', 'wpmatch' ); ?></strong>
							<code>[wpmatch_swipe limit="10"]</code>
						</div>
						<p><em><?php esc_html_e( 'Best for: Main "Browse" or "Discover" page', 'wpmatch' ); ?></em></p>
					</div>

					<div class="shortcode-item">
						<h3><code>[wpmatch_matches]</code></h3>
						<p><?php esc_html_e( 'Displays user\'s current matches with pagination.', 'wpmatch' ); ?></p>
						<div class="shortcode-example">
							<strong><?php esc_html_e( 'Usage:', 'wpmatch' ); ?></strong>
							<code>[wpmatch_matches limit="20"]</code>
						</div>
						<p><em><?php esc_html_e( 'Best for: "My Matches" page', 'wpmatch' ); ?></em></p>
					</div>

					<div class="shortcode-item">
						<h3><code>[wpmatch_profile]</code></h3>
						<p><?php esc_html_e( 'Displays a user profile (read-only view).', 'wpmatch' ); ?></p>
						<div class="shortcode-example">
							<strong><?php esc_html_e( 'Usage:', 'wpmatch' ); ?></strong>
							<code>[wpmatch_profile user_id="123"]</code><br>
							<code>[wpmatch_profile]</code> <?php esc_html_e( '(shows current user)', 'wpmatch' ); ?>
						</div>
						<p><em><?php esc_html_e( 'Best for: Public profile pages or "My Profile" view', 'wpmatch' ); ?></em></p>
					</div>

					<div class="shortcode-item">
						<h3><code>[wpmatch_registration]</code></h3>
						<p><?php esc_html_e( 'Displays a custom registration form for new users.', 'wpmatch' ); ?></p>
						<div class="shortcode-example">
							<strong><?php esc_html_e( 'Usage:', 'wpmatch' ); ?></strong>
							<code>[wpmatch_registration redirect="/profile-setup/"]</code>
						</div>
						<p><em><?php esc_html_e( 'Best for: Custom registration landing pages', 'wpmatch' ); ?></em></p>
					</div>
				</div>
			</div>
		</div>

		<!-- Pages Setup Tab -->
		<div id="tab-pages-setup" class="wpmatch-tab-panel" role="tabpanel" aria-labelledby="tab-pages-setup">
			<div class="card">
				<h2><?php esc_html_e( '📄 Recommended Page Structure', 'wpmatch' ); ?></h2>
				<p><?php esc_html_e( 'Create these pages for a complete dating site experience:', 'wpmatch' ); ?></p>

				<div class="wpmatch-pages-grid">
					<div class="page-template">
						<h3><?php esc_html_e( '❤️ Browse Matches', 'wpmatch' ); ?></h3>
						<p><strong><?php esc_html_e( 'Page slug:', 'wpmatch' ); ?></strong> <code>/browse/</code></p>
						<p><strong><?php esc_html_e( 'Shortcode:', 'wpmatch' ); ?></strong> <code>[wpmatch_swipe]</code></p>
						<p><?php esc_html_e( 'Your main discovery page where users swipe through potential matches.', 'wpmatch' ); ?></p>
					</div>

					<div class="page-template">
						<h3><?php esc_html_e( '👤 Edit Profile', 'wpmatch' ); ?></h3>
						<p><strong><?php esc_html_e( 'Page slug:', 'wpmatch' ); ?></strong> <code>/profile/edit/</code></p>
						<p><strong><?php esc_html_e( 'Shortcode:', 'wpmatch' ); ?></strong> <code>[wpmatch_profile_form]</code></p>
						<p><?php esc_html_e( 'Where users create and edit their dating profiles.', 'wpmatch' ); ?></p>
					</div>

					<div class="page-template">
						<h3><?php esc_html_e( '💕 My Matches', 'wpmatch' ); ?></h3>
						<p><strong><?php esc_html_e( 'Page slug:', 'wpmatch' ); ?></strong> <code>/matches/</code></p>
						<p><strong><?php esc_html_e( 'Shortcode:', 'wpmatch' ); ?></strong> <code>[wpmatch_matches]</code></p>
						<p><?php esc_html_e( 'Display user\'s mutual matches and potential conversations.', 'wpmatch' ); ?></p>
					</div>

					<div class="page-template">
						<h3><?php esc_html_e( '👁️ My Profile', 'wpmatch' ); ?></h3>
						<p><strong><?php esc_html_e( 'Page slug:', 'wpmatch' ); ?></strong> <code>/profile/</code></p>
						<p><strong><?php esc_html_e( 'Shortcode:', 'wpmatch' ); ?></strong> <code>[wpmatch_profile]</code></p>
						<p><?php esc_html_e( 'Read-only view of user\'s own profile as others see it.', 'wpmatch' ); ?></p>
					</div>

					<div class="page-template">
						<h3><?php esc_html_e( '📝 Join Us', 'wpmatch' ); ?></h3>
						<p><strong><?php esc_html_e( 'Page slug:', 'wpmatch' ); ?></strong> <code>/register/</code></p>
						<p><strong><?php esc_html_e( 'Shortcode:', 'wpmatch' ); ?></strong> <code>[wpmatch_registration redirect="/profile/edit/"]</code></p>
						<p><?php esc_html_e( 'Custom registration page that redirects to profile setup.', 'wpmatch' ); ?></p>
					</div>

					<div class="page-template">
						<h3><?php esc_html_e( '🏠 Dating Home', 'wpmatch' ); ?></h3>
						<p><strong><?php esc_html_e( 'Page slug:', 'wpmatch' ); ?></strong> <code>/dating/</code></p>
						<p><strong><?php esc_html_e( 'Content:', 'wpmatch' ); ?></strong> <?php esc_html_e( 'Custom content + links to other pages', 'wpmatch' ); ?></p>
						<p><?php esc_html_e( 'Main landing page for your dating section with navigation.', 'wpmatch' ); ?></p>
					</div>
				</div>

				<div class="wpmatch-auto-setup">
					<h3><?php esc_html_e( '⚡ Quick Setup', 'wpmatch' ); ?></h3>
					<p><?php esc_html_e( 'Want us to create these pages automatically?', 'wpmatch' ); ?></p>
					<button type="button" class="wpmatch-button primary" id="create-demo-pages"><?php esc_html_e( 'Create Demo Pages', 'wpmatch' ); ?></button>
				</div>
			</div>
		</div>

		<!-- User Management Tab -->
		<div id="tab-user-management" class="wpmatch-tab-panel" role="tabpanel" aria-labelledby="tab-user-management">
			<div class="card">
				<h2><?php esc_html_e( '👥 User Management', 'wpmatch' ); ?></h2>

				<h3><?php esc_html_e( 'User Roles & Capabilities', 'wpmatch' ); ?></h3>
				<div class="wpmatch-user-roles">
					<div class="user-role">
						<h4><?php esc_html_e( 'Dating Member', 'wpmatch' ); ?></h4>
						<p><?php esc_html_e( 'Basic free members with standard dating features.', 'wpmatch' ); ?></p>
						<ul>
							<li><?php esc_html_e( 'Create and edit profile', 'wpmatch' ); ?></li>
							<li><?php esc_html_e( 'Upload photos', 'wpmatch' ); ?></li>
							<li><?php esc_html_e( 'Limited swipes per day', 'wpmatch' ); ?></li>
							<li><?php esc_html_e( 'Send and receive messages', 'wpmatch' ); ?></li>
						</ul>
					</div>

					<div class="user-role">
						<h4><?php esc_html_e( 'Premium Member', 'wpmatch' ); ?></h4>
						<p><?php esc_html_e( 'Paid members with enhanced features.', 'wpmatch' ); ?></p>
						<ul>
							<li><?php esc_html_e( 'All Dating Member features', 'wpmatch' ); ?></li>
							<li><?php esc_html_e( 'Unlimited swipes', 'wpmatch' ); ?></li>
							<li><?php esc_html_e( 'See who liked them', 'wpmatch' ); ?></li>
							<li><?php esc_html_e( 'Advanced search filters', 'wpmatch' ); ?></li>
							<li><?php esc_html_e( 'Profile boost features', 'wpmatch' ); ?></li>
						</ul>
					</div>

					<div class="user-role">
						<h4><?php esc_html_e( 'Dating Moderator', 'wpmatch' ); ?></h4>
						<p><?php esc_html_e( 'Staff members who moderate content.', 'wpmatch' ); ?></p>
						<ul>
							<li><?php esc_html_e( 'Moderate profiles and photos', 'wpmatch' ); ?></li>
							<li><?php esc_html_e( 'Review reported content', 'wpmatch' ); ?></li>
							<li><?php esc_html_e( 'Ban problematic users', 'wpmatch' ); ?></li>
						</ul>
					</div>
				</div>

				<h3><?php esc_html_e( 'Managing Users', 'wpmatch' ); ?></h3>
				<ul>
					<li><strong><?php esc_html_e( 'View Dating Users:', 'wpmatch' ); ?></strong> <?php esc_html_e( 'Go to WPMatch → Users', 'wpmatch' ); ?></li>
					<li><strong><?php esc_html_e( 'WordPress Users:', 'wpmatch' ); ?></strong> <?php esc_html_e( 'Standard WordPress Users page shows all users', 'wpmatch' ); ?></li>
					<li><strong><?php esc_html_e( 'User Roles:', 'wpmatch' ); ?></strong> <?php esc_html_e( 'Change roles from Users page or use a role manager plugin', 'wpmatch' ); ?></li>
				</ul>
			</div>
		</div>

		<!-- Troubleshooting Tab -->
		<div id="tab-troubleshooting" class="wpmatch-tab-panel" role="tabpanel" aria-labelledby="tab-troubleshooting">
			<div class="card">
				<h2><?php esc_html_e( '🔧 Troubleshooting', 'wpmatch' ); ?></h2>

				<h3><?php esc_html_e( 'Common Issues', 'wpmatch' ); ?></h3>

				<div class="wpmatch-faq">
					<div class="faq-item">
						<h4><?php esc_html_e( 'Shortcodes not working / showing raw code', 'wpmatch' ); ?></h4>
						<p><?php esc_html_e( 'Make sure you\'re using the exact shortcode syntax. Check that the plugin is active and there are no PHP errors.', 'wpmatch' ); ?></p>
					</div>

					<div class="faq-item">
						<h4><?php esc_html_e( 'Users can\'t register', 'wpmatch' ); ?></h4>
						<p><?php esc_html_e( 'Enable user registration in WordPress Settings → General → "Anyone can register". Also check that your registration page is published.', 'wpmatch' ); ?></p>
					</div>

					<div class="faq-item">
						<h4><?php esc_html_e( 'No potential matches showing', 'wpmatch' ); ?></h4>
						<p><?php esc_html_e( 'You need at least 2 users with completed profiles. Use the "Generate Sample Data" button above to create test users.', 'wpmatch' ); ?></p>
					</div>

					<div class="faq-item">
						<h4><?php esc_html_e( 'Swipe interface not working', 'wpmatch' ); ?></h4>
						<p><?php esc_html_e( 'Make sure JavaScript is enabled. Check browser console for errors. The interface requires completed user profiles to function.', 'wpmatch' ); ?></p>
					</div>

					<div class="faq-item">
						<h4><?php esc_html_e( 'CSS/styling issues', 'wpmatch' ); ?></h4>
						<p><?php esc_html_e( 'Clear any caching plugins. Check that your theme doesn\'t conflict with our CSS. You can customize styles in Appearance → Customize.', 'wpmatch' ); ?></p>
					</div>
				</div>

				<h3><?php esc_html_e( 'Getting Help', 'wpmatch' ); ?></h3>
				<p><?php esc_html_e( 'If you need additional help:', 'wpmatch' ); ?></p>
				<ul>
					<li><?php esc_html_e( 'Check WordPress admin for error messages', 'wpmatch' ); ?></li>
					<li><?php esc_html_e( 'Enable WordPress debug mode to see detailed errors', 'wpmatch' ); ?></li>
					<li><?php esc_html_e( 'Check browser console for JavaScript errors', 'wpmatch' ); ?></li>
					<li><?php esc_html_e( 'Test with a default WordPress theme to rule out theme conflicts', 'wpmatch' ); ?></li>
				</ul>
			</div>
		</div>

	</div>
</div>

<script>
jQuery(document).ready(function($) {
	// Initialize tab functionality
	if (typeof WPMatchAdmin !== 'undefined') {
		WPMatchAdmin.initTabs();

		// Bind tab click events
		$('.wpmatch-tab').on('click', WPMatchAdmin.switchTab);
	} else {
		// Fallback tab functionality if WPMatchAdmin is not loaded
		$('.wpmatch-tab').on('click', function(e) {
			e.preventDefault();

			var $tab = $(this);
			var target = $tab.data('tab');

			if ($tab.hasClass('active')) {
				return;
			}

			$('.wpmatch-tab').removeClass('active').attr('aria-selected', 'false');
			$tab.addClass('active').attr('aria-selected', 'true');

			$('.wpmatch-tab-panel').removeClass('active').hide();
			$('#tab-' + target).addClass('active').show();
		});

		// Ensure only active tab is visible on load
		$('.wpmatch-tab-panel:not(.active)').hide();
	}

	// Generate sample data
	$('#generate-sample-data').on('click', function() {
		var button = $(this);
		button.prop('disabled', true).text('<?php esc_html_e( 'Generating...', 'wpmatch' ); ?>');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpmatch_generate_sample_data',
				nonce: '<?php echo esc_js( wp_create_nonce( 'wpmatch_admin_nonce' ) ); ?>'
			},
			success: function(response) {
				if (response.success) {
					button.text('<?php esc_html_e( 'Sample Data Generated!', 'wpmatch' ); ?>').removeClass('secondary').addClass('success');
					alert('<?php esc_html_e( 'Sample data generated successfully! You can now test the dating features.', 'wpmatch' ); ?>');
				} else {
					button.prop('disabled', false).text('<?php esc_html_e( 'Generate Sample Data', 'wpmatch' ); ?>');
					alert('<?php esc_html_e( 'Error generating sample data. Please try again.', 'wpmatch' ); ?>');
				}
			},
			error: function() {
				button.prop('disabled', false).text('<?php esc_html_e( 'Generate Sample Data', 'wpmatch' ); ?>');
				alert('<?php esc_html_e( 'Error generating sample data. Please try again.', 'wpmatch' ); ?>');
			}
		});
	});

	// Create demo pages
	$('#create-demo-pages').on('click', function() {
		var button = $(this);
		button.prop('disabled', true).text('<?php esc_html_e( 'Creating Pages...', 'wpmatch' ); ?>');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpmatch_create_demo_pages',
				nonce: '<?php echo esc_js( wp_create_nonce( 'wpmatch_admin_nonce' ) ); ?>'
			},
			success: function(response) {
				if (response.success) {
					button.text('<?php esc_html_e( 'Pages Created!', 'wpmatch' ); ?>').removeClass('primary').addClass('success');
					alert('<?php esc_html_e( 'Demo pages created successfully! Check your Pages list in WordPress admin.', 'wpmatch' ); ?>');
				} else {
					button.prop('disabled', false).text('<?php esc_html_e( 'Create Demo Pages', 'wpmatch' ); ?>');
					alert('<?php esc_html_e( 'Error creating pages. Please try again.', 'wpmatch' ); ?>');
				}
			},
			error: function() {
				button.prop('disabled', false).text('<?php esc_html_e( 'Create Demo Pages', 'wpmatch' ); ?>');
				alert('<?php esc_html_e( 'Error creating pages. Please try again.', 'wpmatch' ); ?>');
			}
		});
	});
});
</script>