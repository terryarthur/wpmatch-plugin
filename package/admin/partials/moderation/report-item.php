<?php
/**
 * Report moderation item template
 *
 * @var object $item Report item data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$reported_date = human_time_diff( strtotime( $item->created_at ) );
$reporter_avatar = get_avatar_url( $item->reporter_id, array( 'size' => 40 ) );
$reported_avatar = get_avatar_url( $item->reported_id, array( 'size' => 40 ) );

// Report type labels
$report_types = array(
	'inappropriate_content' => __( 'Inappropriate Content', 'wpmatch' ),
	'fake_profile' => __( 'Fake Profile', 'wpmatch' ),
	'harassment' => __( 'Harassment', 'wpmatch' ),
	'spam' => __( 'Spam', 'wpmatch' ),
	'underage' => __( 'Underage User', 'wpmatch' ),
	'impersonation' => __( 'Impersonation', 'wpmatch' ),
	'offensive_behavior' => __( 'Offensive Behavior', 'wpmatch' ),
	'other' => __( 'Other', 'wpmatch' ),
);

$report_type_label = isset( $report_types[ $item->report_type ] ) ? $report_types[ $item->report_type ] : ucfirst( str_replace( '_', ' ', $item->report_type ) );
?>

<div class="moderation-item report-item" data-item-id="<?php echo esc_attr( $item->id ); ?>">
	<div class="item-checkbox">
		<input type="checkbox" class="moderation-checkbox" value="<?php echo esc_attr( $item->id ); ?>">
	</div>

	<div class="report-severity">
		<div class="severity-indicator severity-<?php echo esc_attr( $item->priority ?: 'medium' ); ?>" title="<?php echo esc_attr( ucfirst( $item->priority ?: 'medium' ) . ' Priority' ); ?>">
			<span class="dashicons dashicons-flag"></span>
		</div>
	</div>

	<div class="item-content">
		<div class="item-header">
			<h4 class="item-title">
				<?php echo esc_html( $report_type_label ); ?>
				<span class="report-id">#<?php echo esc_html( $item->id ); ?></span>
			</h4>
			<div class="item-meta">
				<span class="report-date"><?php printf( esc_html__( 'Reported %s ago', 'wpmatch' ), esc_html( $reported_date ) ); ?></span>
			</div>
		</div>

		<div class="report-details">
			<div class="report-parties">
				<div class="party reporter">
					<img src="<?php echo esc_url( $reporter_avatar ); ?>" alt="Reporter" class="party-avatar">
					<div class="party-info">
						<strong><?php esc_html_e( 'Reporter:', 'wpmatch' ); ?></strong>
						<span><?php echo esc_html( $item->reporter_name ?: __( 'Anonymous', 'wpmatch' ) ); ?></span>
					</div>
				</div>
				<div class="party-arrow">→</div>
				<div class="party reported">
					<img src="<?php echo esc_url( $reported_avatar ); ?>" alt="Reported user" class="party-avatar">
					<div class="party-info">
						<strong><?php esc_html_e( 'Reported:', 'wpmatch' ); ?></strong>
						<span><?php echo esc_html( $item->reported_name ?: __( 'Unknown User', 'wpmatch' ) ); ?></span>
					</div>
				</div>
			</div>

			<?php if ( $item->description ) : ?>
				<div class="report-description">
					<strong><?php esc_html_e( 'Description:', 'wpmatch' ); ?></strong>
					<p><?php echo esc_html( $item->description ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( $item->evidence_data ) : ?>
				<div class="report-evidence">
					<strong><?php esc_html_e( 'Evidence:', 'wpmatch' ); ?></strong>
					<div class="evidence-preview">
						<?php
						$evidence = json_decode( $item->evidence_data, true );
						if ( $evidence ) {
							if ( isset( $evidence['message_id'] ) ) {
								echo '<span class="evidence-type">📧 Message Evidence</span>';
							} elseif ( isset( $evidence['photo_url'] ) ) {
								echo '<span class="evidence-type">📷 Photo Evidence</span>';
							} elseif ( isset( $evidence['profile_data'] ) ) {
								echo '<span class="evidence-type">👤 Profile Evidence</span>';
							}
						}
						?>
					</div>
				</div>
			<?php endif; ?>

			<div class="report-metadata">
				<div class="metadata-row">
					<strong><?php esc_html_e( 'Priority:', 'wpmatch' ); ?></strong>
					<span class="priority-badge priority-<?php echo esc_attr( $item->priority ?: 'medium' ); ?>">
						<?php echo esc_html( ucfirst( $item->priority ?: 'medium' ) ); ?>
					</span>
				</div>
				<div class="metadata-row">
					<strong><?php esc_html_e( 'Status:', 'wpmatch' ); ?></strong>
					<span class="status-badge status-<?php echo esc_attr( $item->status ); ?>">
						<?php echo esc_html( ucfirst( $item->status ) ); ?>
					</span>
				</div>
			</div>
		</div>

		<div class="investigation-notes">
			<h5><?php esc_html_e( 'Investigation Notes', 'wpmatch' ); ?></h5>
			<textarea class="investigation-textarea" placeholder="<?php esc_attr_e( 'Add investigation notes...', 'wpmatch' ); ?>" data-report-id="<?php echo esc_attr( $item->id ); ?>"></textarea>
		</div>
	</div>

	<div class="item-actions">
		<div class="action-buttons">
			<button type="button" class="action-btn investigate" onclick="investigateReport(<?php echo esc_attr( $item->id ); ?>)">
				<span class="dashicons dashicons-search"></span>
				<?php esc_html_e( 'Investigate', 'wpmatch' ); ?>
			</button>
			<button type="button" class="action-btn resolve" onclick="moderateItem(<?php echo esc_attr( $item->id ); ?>, 'resolve', 'report')">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Resolve', 'wpmatch' ); ?>
			</button>
			<button type="button" class="action-btn escalate" onclick="escalateReport(<?php echo esc_attr( $item->id ); ?>)">
				<span class="dashicons dashicons-warning"></span>
				<?php esc_html_e( 'Escalate', 'wpmatch' ); ?>
			</button>
			<button type="button" class="action-btn view-reported" onclick="showUserProfile(<?php echo esc_attr( $item->reported_id ); ?>)">
				<span class="dashicons dashicons-admin-users"></span>
				<?php esc_html_e( 'View Reported', 'wpmatch' ); ?>
			</button>
		</div>

		<div class="resolution-options" style="display: none;">
			<h5><?php esc_html_e( 'Resolution Action', 'wpmatch' ); ?></h5>
			<select class="resolution-action">
				<option value=""><?php esc_html_e( 'Select action...', 'wpmatch' ); ?></option>
				<option value="no_action"><?php esc_html_e( 'No action required', 'wpmatch' ); ?></option>
				<option value="warn_user"><?php esc_html_e( 'Warn the user', 'wpmatch' ); ?></option>
				<option value="suspend_user"><?php esc_html_e( 'Suspend user temporarily', 'wpmatch' ); ?></option>
				<option value="ban_user"><?php esc_html_e( 'Ban user permanently', 'wpmatch' ); ?></option>
				<option value="remove_content"><?php esc_html_e( 'Remove reported content', 'wpmatch' ); ?></option>
			</select>
			<textarea class="resolution-notes" placeholder="<?php esc_attr_e( 'Resolution notes (required)', 'wpmatch' ); ?>"></textarea>
		</div>
	</div>
</div>

<style>
.report-item {
	border-left: 4px solid #dc3545;
}

.report-severity {
	margin-right: 10px;
}

.severity-indicator {
	width: 40px;
	height: 40px;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	color: white;
	font-weight: bold;
}

.severity-low { background: #28a745; }
.severity-medium { background: #ffc107; color: #212529; }
.severity-high { background: #fd7e14; }
.severity-critical { background: #dc3545; }

.report-id {
	font-size: 14px;
	color: #666;
	font-weight: normal;
}

.report-parties {
	display: flex;
	align-items: center;
	gap: 15px;
	margin-bottom: 15px;
	padding: 15px;
	background: #f8f9fa;
	border-radius: 8px;
}

.party {
	display: flex;
	align-items: center;
	gap: 10px;
}

.party-avatar {
	width: 40px;
	height: 40px;
	border-radius: 50%;
}

.party-info {
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.party-arrow {
	font-size: 18px;
	color: #dc3545;
	font-weight: bold;
}

.report-description {
	margin-bottom: 15px;
}

.report-description p {
	margin: 5px 0 0 0;
	padding: 10px;
	background: #fff3cd;
	border: 1px solid #ffeaa7;
	border-radius: 4px;
	color: #856404;
}

.report-evidence {
	margin-bottom: 15px;
}

.evidence-preview {
	margin-top: 5px;
}

.evidence-type {
	display: inline-block;
	padding: 4px 8px;
	background: #e9ecef;
	border-radius: 4px;
	font-size: 12px;
	color: #495057;
}

.report-metadata {
	display: flex;
	gap: 20px;
	margin-bottom: 15px;
}

.metadata-row {
	display: flex;
	align-items: center;
	gap: 8px;
}

.priority-badge {
	padding: 2px 8px;
	border-radius: 12px;
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
}

.priority-low { background: #d4edda; color: #155724; }
.priority-medium { background: #fff3cd; color: #856404; }
.priority-high { background: #f8d7da; color: #721c24; }
.priority-critical { background: #721c24; color: white; }

.investigation-notes {
	border-top: 1px solid #eee;
	padding-top: 15px;
}

.investigation-notes h5 {
	margin: 0 0 10px 0;
	color: #2c3e50;
	font-size: 14px;
}

.investigation-textarea {
	width: 100%;
	min-height: 60px;
	padding: 8px;
	border-radius: 4px;
	border: 1px solid #ddd;
	resize: vertical;
}

.action-btn.investigate {
	color: #17a2b8;
	border-color: #17a2b8;
}

.action-btn.investigate:hover {
	background: #17a2b8;
	color: white;
}

.action-btn.resolve {
	color: #28a745;
	border-color: #28a745;
}

.action-btn.resolve:hover {
	background: #28a745;
	color: white;
}

.action-btn.escalate {
	color: #dc3545;
	border-color: #dc3545;
}

.action-btn.escalate:hover {
	background: #dc3545;
	color: white;
}

.resolution-options {
	margin-top: 15px;
	padding-top: 15px;
	border-top: 1px solid #eee;
}

.resolution-options h5 {
	margin: 0 0 10px 0;
	color: #dc3545;
	font-size: 14px;
}

.resolution-action {
	width: 100%;
	padding: 8px;
	border-radius: 4px;
	border: 1px solid #ddd;
	margin-bottom: 10px;
}

.resolution-notes {
	width: 100%;
	min-height: 60px;
	padding: 8px;
	border-radius: 4px;
	border: 1px solid #ddd;
	resize: vertical;
}

@media (max-width: 768px) {
	.report-parties {
		flex-direction: column;
		gap: 10px;
	}

	.party-arrow {
		transform: rotate(90deg);
	}

	.report-metadata {
		flex-direction: column;
		gap: 8px;
	}
}
</style>

<script>
function investigateReport(reportId) {
	// Show investigation options
	alert('Investigation tools would be implemented here - view user history, check for patterns, review evidence details, etc.');
}

function escalateReport(reportId) {
	if (confirm('Are you sure you want to escalate this report to senior moderation?')) {
		fetch(ajaxurl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: new URLSearchParams({
				action: 'wpmatch_escalate_report',
				report_id: reportId,
				nonce: wpmatchModeration.nonce
			})
		})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				alert(data.data.message);
				location.reload();
			} else {
				alert('Error: ' + data.data.message);
			}
		});
	}
}

// Show/hide resolution options when resolve button is clicked
document.addEventListener('DOMContentLoaded', function() {
	document.querySelectorAll('.action-btn.resolve').forEach(button => {
		button.addEventListener('click', function() {
			const resolutionSection = this.closest('.item-actions').querySelector('.resolution-options');
			if (resolutionSection) {
				resolutionSection.style.display = resolutionSection.style.display === 'none' ? 'block' : 'none';
			}
		});
	});
});
</script>