<?php
/**
 * WPMatch Admin Privacy & GDPR Page
 *
 * Template for the privacy and GDPR compliance admin page.
 *
 * @package WPMatch
 * @since   1.0.0
 */

// Security check.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$privacy_settings = array(
	'wpmatch_privacy_policy_url'     => get_option( 'wpmatch_privacy_policy_url', get_privacy_policy_url() ),
	'wpmatch_data_retention_days'    => get_option( 'wpmatch_data_retention_days', 365 ),
	'wpmatch_cookie_consent_enabled' => get_option( 'wpmatch_cookie_consent_enabled', true ),
	'wpmatch_analytics_enabled'      => get_option( 'wpmatch_analytics_enabled', false ),
);

$recent_consent_changes = array_slice( array_reverse( $consent_log ), 0, 10 );

// Get some stats for the overview
global $wpdb;
$total_users = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );
$users_with_consent = $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key LIKE '_wpmatch_consent_%'" );
$consent_percentage = $total_users > 0 ? round( ( $users_with_consent / $total_users ) * 100, 1 ) : 0;

// GDPR Compliance checklist.
$checklist_items = array(
	'privacy_policy'     => array(
		'title'       => 'Privacy Policy Published',
		'description' => 'A comprehensive privacy policy page is published and accessible',
		'check'       => ! empty( $privacy_settings['wpmatch_privacy_policy_url'] ),
	),
	'cookie_banner'      => array(
		'title'       => 'Cookie Consent Banner',
		'description' => 'Cookie consent banner is enabled for visitors',
		'check'       => ! empty( $privacy_settings['wpmatch_cookie_consent_enabled'] ),
	),
	'data_export'        => array(
		'title'       => 'Data Export Functionality',
		'description' => 'Users can request and download their personal data',
		'check'       => true, // Always available.
	),
	'data_deletion'      => array(
		'title'       => 'Data Deletion Tools',
		'description' => 'Users can request deletion of their personal data',
		'check'       => true, // Always available.
	),
	'consent_management' => array(
		'title'       => 'Consent Management',
		'description' => 'Granular consent controls for different data types',
		'check'       => true, // Always available.
	),
	'data_retention'     => array(
		'title'       => 'Data Retention Policy',
		'description' => 'Automatic data cleanup after retention period',
		'check'       => ! empty( $privacy_settings['wpmatch_data_retention_days'] ) && $privacy_settings['wpmatch_data_retention_days'] > 0,
	),
);

$compliant_count = count( array_filter( $checklist_items, function( $item ) { return $item['check']; } ) );
$total_count = count( $checklist_items );
$compliance_percentage = round( ( $compliant_count / $total_count ) * 100 );
?>

<div class="wrap wpmatch-admin">
	<div class="wpmatch-admin-header">
		<div class="wpmatch-header-content">
			<div class="wpmatch-header-main">
				<h1>
					<span class="dashicons dashicons-privacy"></span>
					<?php esc_html_e( 'Privacy & GDPR Compliance', 'wpmatch' ); ?>
				</h1>
				<p><?php esc_html_e( 'Manage GDPR compliance, user consent, data retention, and privacy controls for your dating site.', 'wpmatch' ); ?></p>
			</div>
			<div class="wpmatch-header-actions">
				<a href="#" class="wpmatch-button secondary" id="export-all-consent">
					<span class="dashicons dashicons-download"></span>
					<?php esc_html_e( 'Export Data', 'wpmatch' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpmatch-settings' ) ); ?>" class="wpmatch-button secondary">
					<span class="dashicons dashicons-admin-settings"></span>
					<?php esc_html_e( 'Settings', 'wpmatch' ); ?>
				</a>
			</div>
		</div>
	</div>

	<!-- Quick Stats Overview -->
	<div class="wpmatch-stats-grid">
		<div class="wpmatch-stat-card">
			<div class="wpmatch-stat-header">
				<span class="dashicons dashicons-shield"></span>
				<span class="wpmatch-stat-label"><?php esc_html_e( 'GDPR Compliance', 'wpmatch' ); ?></span>
			</div>
			<div class="wpmatch-stat-number"><?php echo esc_html( $compliance_percentage ); ?>%</div>
			<div class="wpmatch-stat-change <?php echo $compliance_percentage >= 80 ? 'positive' : 'negative'; ?>">
				<span class="dashicons <?php echo $compliance_percentage >= 80 ? 'dashicons-yes' : 'dashicons-warning'; ?>"></span>
				<?php echo esc_html( $compliant_count . ' of ' . $total_count . ' items' ); ?>
			</div>
		</div>

		<div class="wpmatch-stat-card">
			<div class="wpmatch-stat-header">
				<span class="dashicons dashicons-groups"></span>
				<span class="wpmatch-stat-label"><?php esc_html_e( 'Total Users', 'wpmatch' ); ?></span>
			</div>
			<div class="wpmatch-stat-number"><?php echo esc_html( number_format( $total_users ) ); ?></div>
			<div class="wpmatch-stat-change">
				<span class="dashicons dashicons-admin-users"></span>
				<?php esc_html_e( 'Registered users', 'wpmatch' ); ?>
			</div>
		</div>

		<div class="wpmatch-stat-card">
			<div class="wpmatch-stat-header">
				<span class="dashicons dashicons-yes-alt"></span>
				<span class="wpmatch-stat-label"><?php esc_html_e( 'With Consent', 'wpmatch' ); ?></span>
			</div>
			<div class="wpmatch-stat-number"><?php echo esc_html( number_format( $users_with_consent ) ); ?></div>
			<div class="wpmatch-stat-change positive">
				<span class="dashicons dashicons-chart-line"></span>
				<?php echo esc_html( $consent_percentage . '% coverage' ); ?>
			</div>
		</div>

		<div class="wpmatch-stat-card">
			<div class="wpmatch-stat-header">
				<span class="dashicons dashicons-backup"></span>
				<span class="wpmatch-stat-label"><?php esc_html_e( 'Data Retention', 'wpmatch' ); ?></span>
			</div>
			<div class="wpmatch-stat-number"><?php echo esc_html( $privacy_settings['wpmatch_data_retention_days'] ); ?></div>
			<div class="wpmatch-stat-change">
				<span class="dashicons dashicons-calendar-alt"></span>
				<?php esc_html_e( 'Days retention', 'wpmatch' ); ?>
			</div>
		</div>
	</div>

	<!-- Tabbed Interface -->
	<div class="wpmatch-tabs-container">
		<div class="wpmatch-tabs">
			<button class="wpmatch-tab active" data-tab="privacy-settings">
				<span class="dashicons dashicons-admin-settings"></span>
				<?php esc_html_e( 'Settings', 'wpmatch' ); ?>
			</button>
			<button class="wpmatch-tab" data-tab="compliance-checklist">
				<span class="dashicons dashicons-yes"></span>
				<?php esc_html_e( 'Compliance', 'wpmatch' ); ?>
			</button>
			<button class="wpmatch-tab" data-tab="consent-management">
				<span class="dashicons dashicons-groups"></span>
				<?php esc_html_e( 'Consent', 'wpmatch' ); ?>
			</button>
			<button class="wpmatch-tab" data-tab="data-overview">
				<span class="dashicons dashicons-database"></span>
				<?php esc_html_e( 'Data Types', 'wpmatch' ); ?>
			</button>
			<button class="wpmatch-tab" data-tab="privacy-policy">
				<span class="dashicons dashicons-edit-page"></span>
				<?php esc_html_e( 'Policy', 'wpmatch' ); ?>
			</button>
		</div>

		<!-- Settings Tab -->
		<div id="privacy-settings" class="wpmatch-tab-panel active">
			<div class="wpmatch-card">
				<div class="wpmatch-card-header">
					<h2><?php esc_html_e( 'Privacy Settings', 'wpmatch' ); ?></h2>
				</div>
				<div class="wpmatch-card-content">
					<form method="post" action="options.php" class="wpmatch-privacy-settings-form">
						<?php settings_fields( 'wpmatch_privacy_settings' ); ?>
						<table class="form-table">
							<tbody>
								<tr>
									<th scope="row">
										<label for="wpmatch_privacy_policy_url"><?php esc_html_e( 'Privacy Policy URL', 'wpmatch' ); ?></label>
									</th>
									<td>
										<input type="url"
											id="wpmatch_privacy_policy_url"
											name="wpmatch_privacy_policy_url"
											value="<?php echo esc_url( $privacy_settings['wpmatch_privacy_policy_url'] ); ?>"
											class="regular-text" />
										<p class="description">
											<?php esc_html_e( 'URL to your privacy policy page. This will be linked in consent banners and forms.', 'wpmatch' ); ?>
										</p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="wpmatch_data_retention_days"><?php esc_html_e( 'Data Retention Period', 'wpmatch' ); ?></label>
									</th>
									<td>
										<input type="number"
											id="wpmatch_data_retention_days"
											name="wpmatch_data_retention_days"
											value="<?php echo esc_attr( $privacy_settings['wpmatch_data_retention_days'] ); ?>"
											min="30"
											max="2555"
											class="small-text" />
										<span><?php esc_html_e( 'days', 'wpmatch' ); ?></span>
										<p class="description">
											<?php esc_html_e( 'How long to retain user data after account deletion. Minimum 30 days, maximum 7 years (2555 days).', 'wpmatch' ); ?>
										</p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="wpmatch_cookie_consent_enabled"><?php esc_html_e( 'Cookie Consent Banner', 'wpmatch' ); ?></label>
									</th>
									<td>
										<label>
											<input type="checkbox"
												id="wpmatch_cookie_consent_enabled"
												name="wpmatch_cookie_consent_enabled"
												value="1"
												<?php checked( $privacy_settings['wpmatch_cookie_consent_enabled'] ); ?> />
											<?php esc_html_e( 'Enable cookie consent banner for visitors', 'wpmatch' ); ?>
										</label>
										<p class="description">
											<?php esc_html_e( 'Shows a GDPR-compliant cookie consent banner to new visitors.', 'wpmatch' ); ?>
										</p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="wpmatch_analytics_enabled"><?php esc_html_e( 'Analytics Tracking', 'wpmatch' ); ?></label>
									</th>
									<td>
										<label>
											<input type="checkbox"
												id="wpmatch_analytics_enabled"
												name="wpmatch_analytics_enabled"
												value="1"
												<?php checked( $privacy_settings['wpmatch_analytics_enabled'] ); ?> />
											<?php esc_html_e( 'Enable analytics tracking (only with user consent)', 'wpmatch' ); ?>
										</label>
										<p class="description">
											<?php esc_html_e( 'When enabled, analytics scripts will load only for users who have given consent.', 'wpmatch' ); ?>
										</p>
									</td>
								</tr>
							</tbody>
						</table>
						<p class="submit">
							<button type="submit" class="wpmatch-button primary">
								<?php esc_html_e( 'Save Privacy Settings', 'wpmatch' ); ?>
							</button>
						</p>
					</form>
				</div>
			</div>
		</div>

		<!-- Compliance Checklist Tab -->
		<div id="compliance-checklist" class="wpmatch-tab-panel">
			<div class="wpmatch-card">
				<div class="wpmatch-card-header">
					<h2><?php esc_html_e( 'GDPR Compliance Checklist', 'wpmatch' ); ?></h2>
				</div>
				<div class="wpmatch-card-content">
					<div class="compliance-checklist">
						<?php foreach ( $checklist_items as $item_key => $item ) : ?>
						<div class="compliance-item <?php echo $item['check'] ? 'compliant' : 'non-compliant'; ?>">
							<div class="compliance-status">
								<span class="dashicons <?php echo $item['check'] ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
							</div>
							<div class="compliance-content">
								<h4><?php echo esc_html( $item['title'] ); ?></h4>
								<p><?php echo esc_html( $item['description'] ); ?></p>
								<?php if ( ! $item['check'] ) : ?>
								<p class="compliance-action">
									<strong><?php esc_html_e( 'Action needed:', 'wpmatch' ); ?></strong>
									<?php
									switch ( $item_key ) {
										case 'privacy_policy':
											esc_html_e( 'Create and publish a privacy policy page.', 'wpmatch' );
											break;
										case 'cookie_banner':
											esc_html_e( 'Enable the cookie consent banner in settings.', 'wpmatch' );
											break;
										case 'data_retention':
											esc_html_e( 'Set a data retention period in settings.', 'wpmatch' );
											break;
									}
									?>
								</p>
								<?php endif; ?>
							</div>
						</div>
						<?php endforeach; ?>
					</div>

					<div class="compliance-summary">
						<div class="summary-score">
							<div class="score-circle score-<?php echo $compliance_percentage >= 80 ? 'good' : ( $compliance_percentage >= 60 ? 'fair' : 'poor' ); ?>">
								<span class="score-number"><?php echo esc_html( $compliance_percentage ); ?>%</span>
							</div>
							<div class="score-text">
								<h3><?php esc_html_e( 'GDPR Compliance Score', 'wpmatch' ); ?></h3>
								<p><?php echo esc_html( $compliant_count ); ?> of <?php echo esc_html( $total_count ); ?> requirements met</p>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Consent Management Tab -->
		<div id="consent-management" class="wpmatch-tab-panel">
			<div class="wpmatch-card">
				<div class="wpmatch-card-header">
					<h2><?php esc_html_e( 'User Consent Management', 'wpmatch' ); ?></h2>
				</div>
				<div class="wpmatch-card-content">
					<div class="consent-actions">
						<h3><?php esc_html_e( 'Bulk Actions', 'wpmatch' ); ?></h3>
						<button type="button" class="wpmatch-button secondary" id="cleanup-expired-data">
							<span class="dashicons dashicons-trash"></span>
							<?php esc_html_e( 'Clean Up Expired Data', 'wpmatch' ); ?>
						</button>
					</div>

					<?php if ( ! empty( $recent_consent_changes ) ) : ?>
					<div class="recent-consent-changes">
						<h3><?php esc_html_e( 'Recent Consent Changes', 'wpmatch' ); ?></h3>
						<div class="consent-log-table">
							<table class="widefat striped">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Date', 'wpmatch' ); ?></th>
										<th><?php esc_html_e( 'User ID', 'wpmatch' ); ?></th>
										<th><?php esc_html_e( 'Changes', 'wpmatch' ); ?></th>
										<th><?php esc_html_e( 'IP Address', 'wpmatch' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $recent_consent_changes as $log_entry ) : ?>
									<tr>
										<td><?php echo esc_html( $log_entry['timestamp'] ); ?></td>
										<td>
											<?php
											$user = get_user_by( 'id', $log_entry['user_id'] );
											echo $user ? esc_html( $user->display_name ) : esc_html( $log_entry['user_id'] );
											?>
										</td>
										<td class="consent-changes">
											<?php foreach ( $log_entry['changes'] as $key => $change ) : ?>
											<span class="consent-change">
												<?php echo esc_html( ucfirst( $key ) ); ?>:
												<span class="change-from"><?php echo $change['from'] ? 'Yes' : 'No'; ?></span>
												→
												<span class="change-to"><?php echo $change['to'] ? 'Yes' : 'No'; ?></span>
											</span>
											<?php endforeach; ?>
										</td>
										<td><?php echo esc_html( $log_entry['ip'] ); ?></td>
									</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
					<?php else : ?>
					<p><?php esc_html_e( 'No consent changes recorded yet.', 'wpmatch' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!-- Data Collection Overview Tab -->
		<div id="data-overview" class="wpmatch-tab-panel">
			<div class="wpmatch-card">
				<div class="wpmatch-card-header">
					<h2><?php esc_html_e( 'Data Collection Overview', 'wpmatch' ); ?></h2>
				</div>
				<div class="wpmatch-card-content">
					<div class="data-types-grid">
						<?php foreach ( $data_types as $type_key => $type_data ) : ?>
						<div class="data-type-item">
							<div class="data-type-content">
								<h3><?php echo esc_html( $type_data['name'] ); ?></h3>

								<div class="data-type-details">
									<p><strong><?php esc_html_e( 'What we collect:', 'wpmatch' ); ?></strong></p>
									<p><?php echo esc_html( $type_data['description'] ); ?></p>

									<p><strong><?php esc_html_e( 'Purpose:', 'wpmatch' ); ?></strong></p>
									<p><?php echo esc_html( $type_data['purpose'] ); ?></p>

									<p><strong><?php esc_html_e( 'Retention:', 'wpmatch' ); ?></strong></p>
									<p><?php echo esc_html( $type_data['retention'] ); ?></p>
								</div>
							</div>
						</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</div>

		<!-- Privacy Policy Tab -->
		<div id="privacy-policy" class="wpmatch-tab-panel">
			<div class="wpmatch-card">
				<div class="wpmatch-card-header">
					<h2><?php esc_html_e( 'Privacy Policy Content', 'wpmatch' ); ?></h2>
				</div>
				<div class="wpmatch-card-content">
					<p><?php esc_html_e( 'Use this content as a starting point for your privacy policy. Customize it according to your specific data practices and legal requirements.', 'wpmatch' ); ?></p>

					<div class="privacy-policy-content">
						<textarea readonly class="large-text" rows="20">
<?php echo esc_textarea( WPMatch_Privacy_Manager::get_privacy_policy_content() ); ?>
						</textarea>
					</div>

					<div class="policy-actions">
						<button type="button" class="wpmatch-button secondary" id="copy-privacy-content">
							<span class="dashicons dashicons-clipboard"></span>
							<?php esc_html_e( 'Copy to Clipboard', 'wpmatch' ); ?>
						</button>
						<?php if ( current_user_can( 'edit_pages' ) ) : ?>
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=page' ) ); ?>" class="wpmatch-button primary">
							<span class="dashicons dashicons-edit"></span>
							<?php esc_html_e( 'Create Privacy Policy Page', 'wpmatch' ); ?>
						</a>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
// Tab switching functionality
document.addEventListener('DOMContentLoaded', function() {
	const tabButtons = document.querySelectorAll('.wpmatch-tab');
	const tabContents = document.querySelectorAll('.wpmatch-tab-panel');

	tabButtons.forEach(button => {
		button.addEventListener('click', function() {
			const targetTab = this.getAttribute('data-tab');

			// Remove active class from all buttons and contents
			tabButtons.forEach(btn => btn.classList.remove('active'));
			tabContents.forEach(content => content.classList.remove('active'));

			// Add active class to clicked button and corresponding content
			this.classList.add('active');
			const targetContent = document.getElementById(targetTab);
			if (targetContent) {
				targetContent.classList.add('active');
			}
		});
	});
});

jQuery(document).ready(function($) {
	// Export all consent data
	$('#export-all-consent').on('click', function(e) {
		e.preventDefault();

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpmatch_export_consent_data',
				nonce: '<?php echo esc_js( wp_create_nonce( 'wpmatch_privacy_nonce' ) ); ?>'
			},
			success: function(response) {
				if (response.success) {
					var blob = new Blob([JSON.stringify(response.data, null, 2)], {type: 'application/json'});
					var url = window.URL.createObjectURL(blob);
					var a = document.createElement('a');
					a.href = url;
					a.download = 'wpmatch-consent-export-' + new Date().toISOString() + '.json';
					document.body.appendChild(a);
					a.click();
					window.URL.revokeObjectURL(url);
				}
			}
		});
	});

	// Cleanup expired data
	$('#cleanup-expired-data').on('click', function(e) {
		e.preventDefault();

		if (!confirm('This will permanently delete expired data according to your retention settings. Continue?')) {
			return;
		}

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpmatch_cleanup_expired_data',
				nonce: '<?php echo esc_js( wp_create_nonce( 'wpmatch_privacy_nonce' ) ); ?>'
			},
			success: function(response) {
				if (response.success) {
					alert('Expired data cleaned up successfully.');
					location.reload();
				}
			}
		});
	});

	// Copy privacy policy content
	$('#copy-privacy-content').on('click', function(e) {
		e.preventDefault();

		var textarea = $(this).closest('.wpmatch-card-content').find('textarea')[0];
		textarea.select();
		document.execCommand('copy');

		$(this).text('Copied!');
		setTimeout(function() {
			$('#copy-privacy-content').html('<span class="dashicons dashicons-clipboard"></span> Copy to Clipboard');
		}, 2000);
	});
});
</script>