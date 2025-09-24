<?php
/**
 * WPMatch Security Dashboard
 *
 * Admin interface for security monitoring and logging
 *
 * @package WPMatch
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$security_logger = WPMatch_Security_Logger::get_instance();
$stats = $security_logger->get_security_statistics( 7 );
$alerts = $security_logger->get_security_alerts( 'open' );
$recent_logs = $security_logger->get_security_logs( 10 );
?>

<div class="wrap wpmatch-admin">
	<div class="wpmatch-admin-header">
		<div class="wpmatch-header-content">
			<div class="wpmatch-header-main">
				<h1>
					<span class="dashicons dashicons-shield"></span>
					<?php esc_html_e( 'Security Dashboard', 'wpmatch' ); ?>
				</h1>
				<p><?php esc_html_e( 'Monitor security events, track threats, and manage security settings for your dating platform.', 'wpmatch' ); ?></p>
			</div>
			<div class="wpmatch-header-actions">
				<a href="#" class="wpmatch-button secondary" onclick="document.querySelector('.export-logs-btn').click()">
					<span class="dashicons dashicons-download"></span>
					<?php esc_html_e( 'Export Logs', 'wpmatch' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpmatch-settings' ) ); ?>" class="wpmatch-button secondary">
					<span class="dashicons dashicons-admin-settings"></span>
					<?php esc_html_e( 'Settings', 'wpmatch' ); ?>
				</a>
			</div>
		</div>
	</div>

	<div class="wpmatch-security-overview">
		<div class="wpmatch-stats-grid">
			<div class="wpmatch-stat-card">
				<div class="stat-icon">🛡️</div>
				<div class="stat-content">
					<h3><?php echo esc_html( number_format_i18n( $stats['total_events'] ) ); ?></h3>
					<p><?php esc_html_e( 'Total Events (7 days)', 'wpmatch' ); ?></p>
				</div>
			</div>

			<div class="wpmatch-stat-card critical">
				<div class="stat-icon">⚠️</div>
				<div class="stat-content">
					<h3><?php echo esc_html( number_format_i18n( $stats['critical_events'] ) ); ?></h3>
					<p><?php esc_html_e( 'Critical Events', 'wpmatch' ); ?></p>
				</div>
			</div>

			<div class="wpmatch-stat-card">
				<div class="stat-icon">🌐</div>
				<div class="stat-content">
					<h3><?php echo esc_html( number_format_i18n( $stats['unique_ips'] ) ); ?></h3>
					<p><?php esc_html_e( 'Unique IP Addresses', 'wpmatch' ); ?></p>
				</div>
			</div>

			<div class="wpmatch-stat-card alerts">
				<div class="stat-icon">🚨</div>
				<div class="stat-content">
					<h3><?php echo esc_html( number_format_i18n( $stats['open_alerts'] ) ); ?></h3>
					<p><?php esc_html_e( 'Open Alerts', 'wpmatch' ); ?></p>
				</div>
			</div>
		</div>
	</div>

	<div class="wpmatch-security-tabs">
		<nav class="nav-tab-wrapper">
			<a href="#alerts" class="nav-tab nav-tab-active" data-tab="alerts">
				<?php esc_html_e( 'Security Alerts', 'wpmatch' ); ?>
				<?php if ( count( $alerts ) > 0 ) : ?>
					<span class="tab-count"><?php echo esc_html( count( $alerts ) ); ?></span>
				<?php endif; ?>
			</a>
			<a href="#logs" class="nav-tab" data-tab="logs">
				<?php esc_html_e( 'Security Logs', 'wpmatch' ); ?>
			</a>
			<a href="#analytics" class="nav-tab" data-tab="analytics">
				<?php esc_html_e( 'Analytics', 'wpmatch' ); ?>
			</a>
			<a href="#settings" class="nav-tab" data-tab="settings">
				<?php esc_html_e( 'Settings', 'wpmatch' ); ?>
			</a>
		</nav>

		<div id="alerts" class="tab-content active">
			<div class="tab-header">
				<h2><?php esc_html_e( 'Security Alerts', 'wpmatch' ); ?></h2>
				<div class="tab-actions">
					<button type="button" class="button" id="refresh-alerts">
						<?php esc_html_e( 'Refresh', 'wpmatch' ); ?>
					</button>
				</div>
			</div>

			<?php if ( empty( $alerts ) ) : ?>
				<div class="notice notice-success">
					<p><?php esc_html_e( 'No open security alerts. Your site appears to be secure.', 'wpmatch' ); ?></p>
				</div>
			<?php else : ?>
				<div class="wpmatch-alerts-list">
					<?php foreach ( $alerts as $alert ) : ?>
						<div class="alert-item severity-<?php echo esc_attr( $alert->severity ); ?>" data-alert-id="<?php echo esc_attr( $alert->id ); ?>">
							<div class="alert-header">
								<div class="alert-title">
									<span class="severity-badge"><?php echo esc_html( $this->get_severity_label( $alert->severity ) ); ?></span>
									<h4><?php echo esc_html( $alert->title ); ?></h4>
								</div>
								<div class="alert-meta">
									<span class="alert-count"><?php echo esc_html( sprintf( _n( '%d event', '%d events', $alert->event_count, 'wpmatch' ), $alert->event_count ) ); ?></span>
									<span class="alert-time"><?php echo esc_html( human_time_diff( strtotime( $alert->created_at ) ) ); ?> ago</span>
								</div>
							</div>

							<div class="alert-description">
								<p><?php echo esc_html( $alert->description ); ?></p>
							</div>

							<div class="alert-actions">
								<select class="alert-status-select" data-current="<?php echo esc_attr( $alert->status ); ?>">
									<option value="open" <?php selected( $alert->status, 'open' ); ?>><?php esc_html_e( 'Open', 'wpmatch' ); ?></option>
									<option value="investigating" <?php selected( $alert->status, 'investigating' ); ?>><?php esc_html_e( 'Investigating', 'wpmatch' ); ?></option>
									<option value="resolved" <?php selected( $alert->status, 'resolved' ); ?>><?php esc_html_e( 'Resolved', 'wpmatch' ); ?></option>
									<option value="false_positive" <?php selected( $alert->status, 'false_positive' ); ?>><?php esc_html_e( 'False Positive', 'wpmatch' ); ?></option>
								</select>

								<button type="button" class="wpmatch-button primary update-alert-status">
									<span class="dashicons dashicons-update"></span>
									<?php esc_html_e( 'Update Status', 'wpmatch' ); ?>
								</button>

								<button type="button" class="button view-related-logs" data-alert-type="<?php echo esc_attr( $alert->alert_type ); ?>">
									<?php esc_html_e( 'View Related Logs', 'wpmatch' ); ?>
								</button>
							</div>

							<div class="alert-resolution" style="display: none;">
								<textarea placeholder="<?php esc_attr_e( 'Resolution notes (optional)', 'wpmatch' ); ?>" rows="3"></textarea>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<div id="logs" class="tab-content">
			<div class="tab-header">
				<h2><?php esc_html_e( 'Security Logs', 'wpmatch' ); ?></h2>
				<div class="tab-actions">
					<button type="button" class="button" id="export-logs">
						<?php esc_html_e( 'Export Logs', 'wpmatch' ); ?>
					</button>
					<button type="button" class="button" id="refresh-logs">
						<?php esc_html_e( 'Refresh', 'wpmatch' ); ?>
					</button>
				</div>
			</div>

			<div class="logs-filters">
				<div class="filter-row">
					<select id="filter-event-type">
						<option value=""><?php esc_html_e( 'All Event Types', 'wpmatch' ); ?></option>
						<option value="failed_login"><?php esc_html_e( 'Failed Login', 'wpmatch' ); ?></option>
						<option value="sql_injection"><?php esc_html_e( 'SQL Injection', 'wpmatch' ); ?></option>
						<option value="xss_attempt"><?php esc_html_e( 'XSS Attempt', 'wpmatch' ); ?></option>
						<option value="rate_limit"><?php esc_html_e( 'Rate Limit', 'wpmatch' ); ?></option>
						<option value="suspicious_file"><?php esc_html_e( 'Suspicious File', 'wpmatch' ); ?></option>
						<option value="privilege_escalation"><?php esc_html_e( 'Privilege Escalation', 'wpmatch' ); ?></option>
					</select>

					<select id="filter-severity">
						<option value=""><?php esc_html_e( 'All Severities', 'wpmatch' ); ?></option>
						<option value="1"><?php esc_html_e( 'Low', 'wpmatch' ); ?></option>
						<option value="2"><?php esc_html_e( 'Medium', 'wpmatch' ); ?></option>
						<option value="3"><?php esc_html_e( 'High', 'wpmatch' ); ?></option>
						<option value="4"><?php esc_html_e( 'Critical', 'wpmatch' ); ?></option>
					</select>

					<input type="text" id="filter-ip" placeholder="<?php esc_attr_e( 'IP Address', 'wpmatch' ); ?>">

					<input type="date" id="filter-date-from" placeholder="<?php esc_attr_e( 'Date From', 'wpmatch' ); ?>">
					<input type="date" id="filter-date-to" placeholder="<?php esc_attr_e( 'Date To', 'wpmatch' ); ?>">

					<button type="button" class="button" id="apply-filters">
						<?php esc_html_e( 'Apply Filters', 'wpmatch' ); ?>
					</button>
					<button type="button" class="button" id="clear-filters">
						<?php esc_html_e( 'Clear', 'wpmatch' ); ?>
					</button>
				</div>
			</div>

			<div class="logs-table-container">
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Time', 'wpmatch' ); ?></th>
							<th><?php esc_html_e( 'Event Type', 'wpmatch' ); ?></th>
							<th><?php esc_html_e( 'Severity', 'wpmatch' ); ?></th>
							<th><?php esc_html_e( 'Message', 'wpmatch' ); ?></th>
							<th><?php esc_html_e( 'IP Address', 'wpmatch' ); ?></th>
							<th><?php esc_html_e( 'User', 'wpmatch' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'wpmatch' ); ?></th>
						</tr>
					</thead>
					<tbody id="security-logs-tbody">
						<?php foreach ( $recent_logs as $log ) : ?>
							<tr>
								<td><?php echo esc_html( human_time_diff( strtotime( $log->created_at ) ) ); ?> ago</td>
								<td>
									<span class="event-type-badge event-<?php echo esc_attr( $log->event_type ); ?>">
										<?php echo esc_html( ucwords( str_replace( '_', ' ', $log->event_type ) ) ); ?>
									</span>
								</td>
								<td>
									<span class="severity-badge severity-<?php echo esc_attr( $log->severity ); ?>">
										<?php echo esc_html( $this->get_severity_label( $log->severity ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $log->message ); ?></td>
								<td>
									<code><?php echo esc_html( $log->ip_address ); ?></code>
									<button type="button" class="button-link block-ip" data-ip="<?php echo esc_attr( $log->ip_address ); ?>">
										<?php esc_html_e( 'Block', 'wpmatch' ); ?>
									</button>
								</td>
								<td>
									<?php if ( $log->user_id ) : ?>
										<?php $user = get_user_by( 'id', $log->user_id ); ?>
										<?php if ( $user ) : ?>
											<a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $log->user_id ) ); ?>">
												<?php echo esc_html( $user->display_name ); ?>
											</a>
										<?php else : ?>
											<?php esc_html_e( 'Deleted User', 'wpmatch' ); ?>
										<?php endif; ?>
									<?php else : ?>
										<?php esc_html_e( 'Guest', 'wpmatch' ); ?>
									<?php endif; ?>
								</td>
								<td>
									<button type="button" class="button-link view-log-details" data-log-id="<?php echo esc_attr( $log->id ); ?>">
										<?php esc_html_e( 'Details', 'wpmatch' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<div class="logs-pagination">
					<button type="button" class="button" id="load-more-logs">
						<?php esc_html_e( 'Load More', 'wpmatch' ); ?>
					</button>
				</div>
			</div>
		</div>

		<div id="analytics" class="tab-content">
			<div class="tab-header">
				<h2><?php esc_html_e( 'Security Analytics', 'wpmatch' ); ?></h2>
			</div>

			<div class="analytics-grid">
				<div class="analytics-card">
					<h3><?php esc_html_e( 'Events by Type', 'wpmatch' ); ?></h3>
					<div class="chart-container">
						<canvas id="events-by-type-chart"></canvas>
					</div>
				</div>

				<div class="analytics-card">
					<h3><?php esc_html_e( 'Events by Severity', 'wpmatch' ); ?></h3>
					<div class="chart-container">
						<canvas id="events-by-severity-chart"></canvas>
					</div>
				</div>

				<div class="analytics-card full-width">
					<h3><?php esc_html_e( 'Event Timeline (Last 7 Days)', 'wpmatch' ); ?></h3>
					<div class="chart-container">
						<canvas id="events-timeline-chart"></canvas>
					</div>
				</div>
			</div>
		</div>

		<div id="settings" class="tab-content">
			<div class="tab-header">
				<h2><?php esc_html_e( 'Security Settings', 'wpmatch' ); ?></h2>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( 'wpmatch_security_settings' ); ?>

				<table class="wpmatch-form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Log Retention Period', 'wpmatch' ); ?></th>
						<td>
							<input type="number" name="wpmatch_log_retention_days" value="<?php echo esc_attr( get_option( 'wpmatch_log_retention_days', 90 ) ); ?>" min="1" max="365" />
							<p class="description"><?php esc_html_e( 'Number of days to retain security logs (1-365 days)', 'wpmatch' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Email Notifications', 'wpmatch' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wpmatch_security_email_notifications" value="1" <?php checked( get_option( 'wpmatch_security_email_notifications', 1 ) ); ?> />
								<?php esc_html_e( 'Send email notifications for security alerts', 'wpmatch' ); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Notification Email', 'wpmatch' ); ?></th>
						<td>
							<input type="email" name="wpmatch_security_admin_email" value="<?php echo esc_attr( get_option( 'wpmatch_security_admin_email', get_option( 'admin_email' ) ) ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Email address to receive security notifications', 'wpmatch' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Minimum Alert Severity', 'wpmatch' ); ?></th>
						<td>
							<select name="wpmatch_min_alert_severity">
								<option value="1" <?php selected( get_option( 'wpmatch_min_alert_severity', 2 ), 1 ); ?>><?php esc_html_e( 'Low', 'wpmatch' ); ?></option>
								<option value="2" <?php selected( get_option( 'wpmatch_min_alert_severity', 2 ), 2 ); ?>><?php esc_html_e( 'Medium', 'wpmatch' ); ?></option>
								<option value="3" <?php selected( get_option( 'wpmatch_min_alert_severity', 2 ), 3 ); ?>><?php esc_html_e( 'High', 'wpmatch' ); ?></option>
								<option value="4" <?php selected( get_option( 'wpmatch_min_alert_severity', 2 ), 4 ); ?>><?php esc_html_e( 'Critical', 'wpmatch' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Minimum severity level to trigger email notifications', 'wpmatch' ); ?></p>
						</td>
					</tr>
				</table>

				<button type="submit" name="submit" class="wpmatch-button primary">
					<span class="dashicons dashicons-saved"></span>
					<?php esc_html_e( 'Save Security Settings', 'wpmatch' ); ?>
				</button>
			</form>

			<div class="security-tools">
				<h3><?php esc_html_e( 'Security Tools', 'wpmatch' ); ?></h3>

				<div class="tool-buttons">
					<button type="button" class="button" id="clear-all-logs">
						<?php esc_html_e( 'Clear All Logs', 'wpmatch' ); ?>
					</button>
					<button type="button" class="button" id="test-security-alert">
						<?php esc_html_e( 'Test Security Alert', 'wpmatch' ); ?>
					</button>
					<button type="button" class="wpmatch-button primary" id="run-security-scan">
						<span class="dashicons dashicons-search"></span>
						<?php esc_html_e( 'Run Security Scan', 'wpmatch' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Log Details Modal -->
<div id="log-details-modal" class="wpmatch-modal" style="display: none;">
	<div class="modal-backdrop"></div>
	<div class="modal-content">
		<div class="modal-header">
			<h3><?php esc_html_e( 'Log Details', 'wpmatch' ); ?></h3>
			<button type="button" class="modal-close">&times;</button>
		</div>
		<div class="modal-body">
			<div id="log-details-content"></div>
		</div>
	</div>
</div>

<?php
// Helper method to get severity labels
if ( ! function_exists( 'get_severity_label' ) ) {
	function get_severity_label( $severity ) {
		$labels = array(
			1 => __( 'Low', 'wpmatch' ),
			2 => __( 'Medium', 'wpmatch' ),
			3 => __( 'High', 'wpmatch' ),
			4 => __( 'Critical', 'wpmatch' ),
		);
		return isset( $labels[ $severity ] ) ? $labels[ $severity ] : __( 'Unknown', 'wpmatch' );
	}
}
?>

<style>
.wpmatch-security-dashboard {
	max-width: 1200px;
}

.wpmatch-security-overview {
	margin-bottom: 30px;
}

.wpmatch-stats-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
	gap: 20px;
	margin-bottom: 30px;
}

.wpmatch-stat-card {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	padding: 20px;
	display: flex;
	align-items: center;
	gap: 15px;
	box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.wpmatch-stat-card.critical {
	border-left: 4px solid #dc3232;
}

.wpmatch-stat-card.alerts {
	border-left: 4px solid #ffb900;
}

.stat-icon {
	font-size: 32px;
	opacity: 0.8;
}

.stat-content h3 {
	margin: 0;
	font-size: 28px;
	font-weight: 600;
	color: #1d2327;
}

.stat-content p {
	margin: 5px 0 0;
	color: #646970;
	font-size: 14px;
}

.wpmatch-security-tabs .nav-tab-wrapper {
	border-bottom: 1px solid #ccd0d4;
}

.nav-tab .tab-count {
	background: #dc3232;
	color: white;
	border-radius: 10px;
	padding: 2px 8px;
	font-size: 11px;
	margin-left: 5px;
}

.tab-content {
	display: none;
	padding: 20px 0;
}

.tab-content.active {
	display: block;
}

.tab-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 20px;
	padding-bottom: 10px;
	border-bottom: 1px solid #ccd0d4;
}

.tab-actions {
	display: flex;
	gap: 10px;
}

.wpmatch-alerts-list {
	space-y: 15px;
}

.alert-item {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	padding: 20px;
	margin-bottom: 15px;
}

.alert-item.severity-4 {
	border-left: 4px solid #dc3232;
}

.alert-item.severity-3 {
	border-left: 4px solid #fd7e14;
}

.alert-item.severity-2 {
	border-left: 4px solid #ffb900;
}

.alert-item.severity-1 {
	border-left: 4px solid #00a32a;
}

.alert-header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	margin-bottom: 10px;
}

.alert-title {
	display: flex;
	align-items: center;
	gap: 10px;
}

.alert-title h4 {
	margin: 0;
	font-size: 16px;
}

.severity-badge {
	padding: 4px 8px;
	border-radius: 3px;
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
	color: white;
}

.severity-badge.severity-4 {
	background: #dc3232;
}

.severity-badge.severity-3 {
	background: #fd7e14;
}

.severity-badge.severity-2 {
	background: #ffb900;
}

.severity-badge.severity-1 {
	background: #00a32a;
}

.alert-meta {
	display: flex;
	gap: 15px;
	font-size: 13px;
	color: #646970;
}

.alert-actions {
	display: flex;
	gap: 10px;
	align-items: center;
	margin-top: 15px;
	padding-top: 15px;
	border-top: 1px solid #f0f0f1;
}

.logs-filters {
	background: #f9f9f9;
	padding: 15px;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	margin-bottom: 20px;
}

.filter-row {
	display: flex;
	gap: 10px;
	align-items: center;
	flex-wrap: wrap;
}

.filter-row > * {
	margin-bottom: 0;
}

.logs-table-container {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
}

.event-type-badge {
	padding: 4px 8px;
	border-radius: 3px;
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
	color: white;
}

.event-type-badge.event-failed_login {
	background: #ffb900;
}

.event-type-badge.event-sql_injection {
	background: #dc3232;
}

.event-type-badge.event-xss_attempt {
	background: #fd7e14;
}

.event-type-badge.event-rate_limit {
	background: #00a32a;
}

.logs-pagination {
	padding: 15px;
	text-align: center;
	border-top: 1px solid #ccd0d4;
}

.analytics-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
	gap: 20px;
}

.analytics-card {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	padding: 20px;
}

.analytics-card.full-width {
	grid-column: 1 / -1;
}

.chart-container {
	height: 300px;
	margin-top: 15px;
}

.security-tools {
	margin-top: 30px;
	padding-top: 20px;
	border-top: 1px solid #ccd0d4;
}

.tool-buttons {
	display: flex;
	gap: 10px;
	flex-wrap: wrap;
}

.wpmatch-modal {
	position: fixed;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	z-index: 100000;
}

.modal-backdrop {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	background: rgba(0,0,0,0.7);
}

.modal-content {
	position: relative;
	background: #fff;
	margin: 50px auto;
	max-width: 800px;
	max-height: 80vh;
	border-radius: 4px;
	overflow: hidden;
}

.modal-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 20px;
	border-bottom: 1px solid #ccd0d4;
}

.modal-header h3 {
	margin: 0;
}

.modal-close {
	background: none;
	border: none;
	font-size: 24px;
	cursor: pointer;
	padding: 0;
	width: 30px;
	height: 30px;
	display: flex;
	align-items: center;
	justify-content: center;
}

.modal-body {
	padding: 20px;
	max-height: 60vh;
	overflow-y: auto;
}

@media (max-width: 768px) {
	.wpmatch-stats-grid {
		grid-template-columns: 1fr;
	}

	.alert-header {
		flex-direction: column;
		align-items: flex-start;
		gap: 10px;
	}

	.alert-actions {
		flex-direction: column;
		align-items: stretch;
	}

	.filter-row {
		flex-direction: column;
		align-items: stretch;
	}

	.analytics-grid {
		grid-template-columns: 1fr;
	}

	.modal-content {
		margin: 20px;
		max-width: none;
	}
}
</style>