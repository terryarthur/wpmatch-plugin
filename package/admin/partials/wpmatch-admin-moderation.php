<?php
/**
 * Content Moderation Dashboard for WPMatch
 *
 * @package WPMatch
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// Get current filter
$current_filter = isset( $_GET['filter'] ) ? sanitize_text_field( $_GET['filter'] ) : 'pending';
$per_page = 20;
$current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$offset = ( $current_page - 1 ) * $per_page;

// Database tables
$profile_table = $wpdb->prefix . 'wpmatch_user_profiles';
$photos_table = $wpdb->prefix . 'wpmatch_user_photos';
$reports_table = $wpdb->prefix . 'wpmatch_reports';
$messages_table = $wpdb->prefix . 'wpmatch_messages';

// Get moderation statistics
$pending_profiles = $wpdb->get_var( "SELECT COUNT(*) FROM $profile_table WHERE status = 'pending'" );
$pending_photos = $wpdb->get_var( "SELECT COUNT(*) FROM $photos_table WHERE moderation_status = 'pending'" );
$pending_reports = $wpdb->get_var( "SELECT COUNT(*) FROM $reports_table WHERE status = 'pending'" );
$flagged_messages = $wpdb->get_var( "SELECT COUNT(*) FROM $messages_table WHERE is_flagged = 1" );

// Handle different moderation queues
$items = array();
$total_items = 0;

switch ( $current_filter ) {
	case 'profiles':
		// Get profiles pending moderation
		$items_query = "
			SELECT p.*, u.display_name, u.user_email, u.user_registered
			FROM $profile_table p
			LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
			WHERE p.status = 'pending'
			ORDER BY p.created_at DESC
			LIMIT %d OFFSET %d
		";
		$items = $wpdb->get_results( $wpdb->prepare( $items_query, $per_page, $offset ) );
		$total_items = $pending_profiles;
		break;

	case 'photos':
		// Get photos pending moderation
		$items_query = "
			SELECT ph.*, p.user_id, u.display_name
			FROM $photos_table ph
			LEFT JOIN $profile_table p ON ph.user_id = p.user_id
			LEFT JOIN {$wpdb->users} u ON ph.user_id = u.ID
			WHERE ph.moderation_status = 'pending'
			ORDER BY ph.uploaded_at DESC
			LIMIT %d OFFSET %d
		";
		$items = $wpdb->get_results( $wpdb->prepare( $items_query, $per_page, $offset ) );
		$total_items = $pending_photos;
		break;

	case 'reports':
		// Get pending reports
		$items_query = "
			SELECT r.*,
				   reporter.display_name as reporter_name,
				   reported.display_name as reported_name
			FROM $reports_table r
			LEFT JOIN {$wpdb->users} reporter ON r.reporter_id = reporter.ID
			LEFT JOIN {$wpdb->users} reported ON r.reported_id = reported.ID
			WHERE r.status = 'pending'
			ORDER BY r.created_at DESC
			LIMIT %d OFFSET %d
		";
		$items = $wpdb->get_results( $wpdb->prepare( $items_query, $per_page, $offset ) );
		$total_items = $pending_reports;
		break;

	case 'messages':
		// Get flagged messages
		$items_query = "
			SELECT m.*,
				   sender.display_name as sender_name,
				   recipient.display_name as recipient_name
			FROM $messages_table m
			LEFT JOIN {$wpdb->users} sender ON m.sender_id = sender.ID
			LEFT JOIN {$wpdb->users} recipient ON m.recipient_id = recipient.ID
			WHERE m.is_flagged = 1
			ORDER BY m.created_at DESC
			LIMIT %d OFFSET %d
		";
		$items = $wpdb->get_results( $wpdb->prepare( $items_query, $per_page, $offset ) );
		$total_items = $flagged_messages;
		break;

	default:
		// Get all pending items for overview
		$current_filter = 'pending';
		break;
}

$total_pages = ceil( $total_items / $per_page );

// Get recent moderation activity
$recent_activity = $wpdb->get_results( $wpdb->prepare(
	"SELECT
		'profile' as type,
		p.user_id as item_id,
		u.display_name as user_name,
		p.status as action,
		p.updated_at as timestamp
	FROM $profile_table p
	LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
	WHERE p.updated_at >= %s AND p.status IN ('approved', 'rejected')

	UNION ALL

	SELECT
		'photo' as type,
		ph.id as item_id,
		u.display_name as user_name,
		ph.moderation_status as action,
		ph.moderated_at as timestamp
	FROM $photos_table ph
	LEFT JOIN {$wpdb->users} u ON ph.user_id = u.ID
	WHERE ph.moderated_at >= %s AND ph.moderation_status IN ('approved', 'rejected')

	ORDER BY timestamp DESC
	LIMIT 10",
	date( 'Y-m-d H:i:s', strtotime( '-24 hours' ) ),
	date( 'Y-m-d H:i:s', strtotime( '-24 hours' ) )
) );
?>

<div class="wrap wpmatch-moderation">
	<!-- Header -->
	<div class="wpmatch-moderation-header">
		<div class="moderation-title">
			<h1>
				<span class="dashicons dashicons-shield-alt"></span>
				<?php esc_html_e( 'Content Moderation', 'wpmatch' ); ?>
			</h1>
			<p><?php esc_html_e( 'Review and moderate user-generated content to maintain a safe dating environment.', 'wpmatch' ); ?></p>
		</div>
		<div class="moderation-actions">
			<button type="button" class="wpmatch-button secondary" onclick="refreshModeration()">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Refresh', 'wpmatch' ); ?>
			</button>
			<button type="button" class="wpmatch-button" onclick="showModerationSettings()">
				<span class="dashicons dashicons-admin-settings"></span>
				<?php esc_html_e( 'Settings', 'wpmatch' ); ?>
			</button>
		</div>
	</div>

	<!-- Statistics Overview -->
	<div class="moderation-stats">
		<div class="stat-card urgent">
			<div class="stat-icon">
				<span class="dashicons dashicons-admin-users"></span>
			</div>
			<div class="stat-content">
				<h3><?php echo esc_html( number_format( $pending_profiles ) ); ?></h3>
				<p><?php esc_html_e( 'Pending Profiles', 'wpmatch' ); ?></p>
			</div>
		</div>

		<div class="stat-card warning">
			<div class="stat-icon">
				<span class="dashicons dashicons-format-image"></span>
			</div>
			<div class="stat-content">
				<h3><?php echo esc_html( number_format( $pending_photos ) ); ?></h3>
				<p><?php esc_html_e( 'Pending Photos', 'wpmatch' ); ?></p>
			</div>
		</div>

		<div class="stat-card danger">
			<div class="stat-icon">
				<span class="dashicons dashicons-flag"></span>
			</div>
			<div class="stat-content">
				<h3><?php echo esc_html( number_format( $pending_reports ) ); ?></h3>
				<p><?php esc_html_e( 'Pending Reports', 'wpmatch' ); ?></p>
			</div>
		</div>

		<div class="stat-card alert">
			<div class="stat-icon">
				<span class="dashicons dashicons-email-alt"></span>
			</div>
			<div class="stat-content">
				<h3><?php echo esc_html( number_format( $flagged_messages ) ); ?></h3>
				<p><?php esc_html_e( 'Flagged Messages', 'wpmatch' ); ?></p>
			</div>
		</div>
	</div>

	<!-- Filter Tabs -->
	<div class="moderation-tabs">
		<a href="<?php echo esc_url( remove_query_arg( 'filter' ) ); ?>" class="tab-link <?php echo 'pending' === $current_filter ? 'active' : ''; ?>">
			<?php esc_html_e( 'Overview', 'wpmatch' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'filter', 'profiles' ) ); ?>" class="tab-link <?php echo 'profiles' === $current_filter ? 'active' : ''; ?>">
			<?php esc_html_e( 'Profiles', 'wpmatch' ); ?>
			<?php if ( $pending_profiles > 0 ) : ?>
				<span class="tab-count"><?php echo esc_html( $pending_profiles ); ?></span>
			<?php endif; ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'filter', 'photos' ) ); ?>" class="tab-link <?php echo 'photos' === $current_filter ? 'active' : ''; ?>">
			<?php esc_html_e( 'Photos', 'wpmatch' ); ?>
			<?php if ( $pending_photos > 0 ) : ?>
				<span class="tab-count"><?php echo esc_html( $pending_photos ); ?></span>
			<?php endif; ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'filter', 'reports' ) ); ?>" class="tab-link <?php echo 'reports' === $current_filter ? 'active' : ''; ?>">
			<?php esc_html_e( 'Reports', 'wpmatch' ); ?>
			<?php if ( $pending_reports > 0 ) : ?>
				<span class="tab-count"><?php echo esc_html( $pending_reports ); ?></span>
			<?php endif; ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'filter', 'messages' ) ); ?>" class="tab-link <?php echo 'messages' === $current_filter ? 'active' : ''; ?>">
			<?php esc_html_e( 'Flagged Messages', 'wpmatch' ); ?>
			<?php if ( $flagged_messages > 0 ) : ?>
				<span class="tab-count"><?php echo esc_html( $flagged_messages ); ?></span>
			<?php endif; ?>
		</a>
	</div>

	<!-- Content Area -->
	<div class="moderation-content">
		<?php if ( 'pending' === $current_filter ) : ?>
			<!-- Overview Dashboard -->
			<div class="moderation-overview">
				<div class="overview-grid">
					<!-- Quick Actions -->
					<div class="quick-actions">
						<h3><?php esc_html_e( 'Quick Actions', 'wpmatch' ); ?></h3>
						<div class="action-buttons">
							<button type="button" class="action-btn profiles" onclick="location.href='<?php echo esc_url( add_query_arg( 'filter', 'profiles' ) ); ?>'">
								<span class="dashicons dashicons-admin-users"></span>
								<?php esc_html_e( 'Review Profiles', 'wpmatch' ); ?>
								<?php if ( $pending_profiles > 0 ) : ?>
									<span class="action-count"><?php echo esc_html( $pending_profiles ); ?></span>
								<?php endif; ?>
							</button>
							<button type="button" class="action-btn photos" onclick="location.href='<?php echo esc_url( add_query_arg( 'filter', 'photos' ) ); ?>'">
								<span class="dashicons dashicons-format-image"></span>
								<?php esc_html_e( 'Review Photos', 'wpmatch' ); ?>
								<?php if ( $pending_photos > 0 ) : ?>
									<span class="action-count"><?php echo esc_html( $pending_photos ); ?></span>
								<?php endif; ?>
							</button>
							<button type="button" class="action-btn reports" onclick="location.href='<?php echo esc_url( add_query_arg( 'filter', 'reports' ) ); ?>'">
								<span class="dashicons dashicons-flag"></span>
								<?php esc_html_e( 'Handle Reports', 'wpmatch' ); ?>
								<?php if ( $pending_reports > 0 ) : ?>
									<span class="action-count"><?php echo esc_html( $pending_reports ); ?></span>
								<?php endif; ?>
							</button>
							<button type="button" class="action-btn messages" onclick="location.href='<?php echo esc_url( add_query_arg( 'filter', 'messages' ) ); ?>'">
								<span class="dashicons dashicons-email-alt"></span>
								<?php esc_html_e( 'Review Messages', 'wpmatch' ); ?>
								<?php if ( $flagged_messages > 0 ) : ?>
									<span class="action-count"><?php echo esc_html( $flagged_messages ); ?></span>
								<?php endif; ?>
							</button>
						</div>
					</div>

					<!-- Recent Activity -->
					<div class="recent-activity">
						<h3><?php esc_html_e( 'Recent Activity (24h)', 'wpmatch' ); ?></h3>
						<div class="activity-list">
							<?php if ( ! empty( $recent_activity ) ) : ?>
								<?php foreach ( $recent_activity as $activity ) : ?>
									<div class="activity-item">
										<div class="activity-icon">
											<?php if ( 'profile' === $activity->type ) : ?>
												<span class="dashicons dashicons-admin-users"></span>
											<?php elseif ( 'photo' === $activity->type ) : ?>
												<span class="dashicons dashicons-format-image"></span>
											<?php endif; ?>
										</div>
										<div class="activity-content">
											<div class="activity-text">
												<?php
												printf(
													/* translators: 1: action, 2: type, 3: user name */
													esc_html__( '%1$s %2$s for %3$s', 'wpmatch' ),
													esc_html( ucfirst( $activity->action ) ),
													esc_html( $activity->type ),
													esc_html( $activity->user_name )
												);
												?>
											</div>
											<div class="activity-time">
												<?php echo esc_html( human_time_diff( strtotime( $activity->timestamp ) ) . ' ago' ); ?>
											</div>
										</div>
									</div>
								<?php endforeach; ?>
							<?php else : ?>
								<p><?php esc_html_e( 'No recent activity.', 'wpmatch' ); ?></p>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>

		<?php else : ?>
			<!-- Moderation Queue Content -->
			<div class="moderation-queue">
				<?php if ( ! empty( $items ) ) : ?>
					<div class="queue-header">
						<div class="queue-info">
							<?php
							printf(
								/* translators: 1: start item, 2: end item, 3: total items */
								esc_html__( 'Showing %1$d-%2$d of %3$d items', 'wpmatch' ),
								( ( $current_page - 1 ) * $per_page ) + 1,
								min( $current_page * $per_page, $total_items ),
								$total_items
							);
							?>
						</div>
						<div class="bulk-actions">
							<select id="bulk-action-select">
								<option value=""><?php esc_html_e( 'Bulk Actions', 'wpmatch' ); ?></option>
								<option value="approve"><?php esc_html_e( 'Approve', 'wpmatch' ); ?></option>
								<option value="reject"><?php esc_html_e( 'Reject', 'wpmatch' ); ?></option>
								<?php if ( 'reports' === $current_filter ) : ?>
									<option value="resolve"><?php esc_html_e( 'Resolve', 'wpmatch' ); ?></option>
								<?php endif; ?>
							</select>
							<button type="button" class="wpmatch-button secondary" onclick="performBulkModeration()"><?php esc_html_e( 'Apply', 'wpmatch' ); ?></button>
						</div>
					</div>

					<div class="moderation-items">
						<?php foreach ( $items as $item ) : ?>
							<?php
							// Render different item types
							if ( 'profiles' === $current_filter ) {
								include WPMATCH_PLUGIN_DIR . 'admin/partials/moderation/profile-item.php';
							} elseif ( 'photos' === $current_filter ) {
								include WPMATCH_PLUGIN_DIR . 'admin/partials/moderation/photo-item.php';
							} elseif ( 'reports' === $current_filter ) {
								include WPMATCH_PLUGIN_DIR . 'admin/partials/moderation/report-item.php';
							} elseif ( 'messages' === $current_filter ) {
								include WPMATCH_PLUGIN_DIR . 'admin/partials/moderation/message-item.php';
							}
							?>
						<?php endforeach; ?>
					</div>

					<!-- Pagination -->
					<?php if ( $total_pages > 1 ) : ?>
						<div class="moderation-pagination">
							<?php
							$pagination_args = array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'current'   => $current_page,
								'total'     => $total_pages,
								'prev_text' => '&laquo; ' . __( 'Previous', 'wpmatch' ),
								'next_text' => __( 'Next', 'wpmatch' ) . ' &raquo;',
							);
							echo wp_kses_post( paginate_links( $pagination_args ) );
							?>
						</div>
					<?php endif; ?>

				<?php else : ?>
					<div class="empty-queue">
						<div class="empty-icon">
							<span class="dashicons dashicons-shield-alt"></span>
						</div>
						<h3><?php esc_html_e( 'No items to moderate', 'wpmatch' ); ?></h3>
						<p>
							<?php
							switch ( $current_filter ) {
								case 'profiles':
									esc_html_e( 'All profiles have been reviewed.', 'wpmatch' );
									break;
								case 'photos':
									esc_html_e( 'All photos have been reviewed.', 'wpmatch' );
									break;
								case 'reports':
									esc_html_e( 'No pending reports.', 'wpmatch' );
									break;
								case 'messages':
									esc_html_e( 'No flagged messages.', 'wpmatch' );
									break;
							}
							?>
						</p>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</div>

<!-- Moderation Settings Modal -->
<div id="moderation-settings-modal" class="wpmatch-modal" style="display: none;">
	<div class="modal-content">
		<div class="modal-header">
			<h2><?php esc_html_e( 'Moderation Settings', 'wpmatch' ); ?></h2>
			<button type="button" class="modal-close" onclick="closeModerationModal()">&times;</button>
		</div>
		<div class="modal-body">
			<form id="moderation-settings-form">
				<div class="setting-group">
					<label>
						<input type="checkbox" name="auto_approve_verified" id="auto_approve_verified">
						<?php esc_html_e( 'Auto-approve content from verified users', 'wpmatch' ); ?>
					</label>
				</div>
				<div class="setting-group">
					<label>
						<input type="checkbox" name="enable_photo_analysis" id="enable_photo_analysis">
						<?php esc_html_e( 'Enable automatic photo content analysis', 'wpmatch' ); ?>
					</label>
				</div>
				<div class="setting-group">
					<label>
						<input type="checkbox" name="require_manual_review" id="require_manual_review">
						<?php esc_html_e( 'Require manual review for all new profiles', 'wpmatch' ); ?>
					</label>
				</div>
				<div class="setting-group">
					<label for="message_filter_level"><?php esc_html_e( 'Message filtering level:', 'wpmatch' ); ?></label>
					<select name="message_filter_level" id="message_filter_level">
						<option value="off"><?php esc_html_e( 'Off', 'wpmatch' ); ?></option>
						<option value="basic"><?php esc_html_e( 'Basic', 'wpmatch' ); ?></option>
						<option value="strict"><?php esc_html_e( 'Strict', 'wpmatch' ); ?></option>
					</select>
				</div>
				<div class="modal-actions">
					<button type="button" class="wpmatch-button secondary" onclick="closeModerationModal()"><?php esc_html_e( 'Cancel', 'wpmatch' ); ?></button>
					<button type="submit" class="wpmatch-button"><?php esc_html_e( 'Save Settings', 'wpmatch' ); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- Styles -->
<style>
.wpmatch-moderation {
	background: #f1f1f1;
	margin: 0 -20px;
	padding: 20px;
	min-height: calc(100vh - 32px);
}

.wpmatch-moderation-header {
	background: white;
	padding: 20px;
	border-radius: 8px;
	margin-bottom: 20px;
	display: flex;
	justify-content: space-between;
	align-items: center;
	box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.moderation-title h1 {
	margin: 0;
	color: #2c3e50;
	display: flex;
	align-items: center;
	gap: 10px;
}

.moderation-title p {
	margin: 5px 0 0 0;
	color: #666;
}

.moderation-actions {
	display: flex;
	gap: 10px;
}

.moderation-stats {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 20px;
	margin-bottom: 20px;
}

.stat-card {
	background: white;
	padding: 20px;
	border-radius: 8px;
	display: flex;
	align-items: center;
	box-shadow: 0 2px 4px rgba(0,0,0,0.1);
	border-left: 4px solid #667eea;
}

.stat-card.urgent { border-left-color: #e91e63; }
.stat-card.warning { border-left-color: #ff9800; }
.stat-card.danger { border-left-color: #f44336; }
.stat-card.alert { border-left-color: #9c27b0; }

.stat-icon {
	margin-right: 15px;
	opacity: 0.8;
}

.stat-icon .dashicons {
	font-size: 32px;
	color: #667eea;
}

.stat-content h3 {
	font-size: 28px;
	font-weight: 700;
	margin: 0;
	color: #2c3e50;
}

.stat-content p {
	margin: 0;
	color: #666;
	font-size: 14px;
}

.moderation-tabs {
	background: white;
	border-radius: 8px;
	padding: 0;
	margin-bottom: 20px;
	display: flex;
	box-shadow: 0 2px 4px rgba(0,0,0,0.1);
	overflow: hidden;
}

.tab-link {
	padding: 15px 20px;
	text-decoration: none;
	color: #666;
	border-bottom: 3px solid transparent;
	transition: all 0.3s ease;
	display: flex;
	align-items: center;
	gap: 8px;
}

.tab-link:hover {
	background: #f8f9fa;
	color: #2c3e50;
}

.tab-link.active {
	color: #667eea;
	border-bottom-color: #667eea;
	background: #f8f9fa;
}

.tab-count {
	background: #e91e63;
	color: white;
	padding: 2px 6px;
	border-radius: 10px;
	font-size: 11px;
	font-weight: 600;
	min-width: 16px;
	text-align: center;
}

.moderation-content {
	background: white;
	border-radius: 8px;
	padding: 20px;
	box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.overview-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 30px;
}

.quick-actions h3,
.recent-activity h3 {
	margin: 0 0 15px 0;
	color: #2c3e50;
	border-bottom: 2px solid #667eea;
	padding-bottom: 10px;
}

.action-buttons {
	display: grid;
	gap: 10px;
}

.action-btn {
	padding: 15px;
	border: 1px solid #e9ecef;
	border-radius: 8px;
	background: white;
	cursor: pointer;
	transition: all 0.3s ease;
	display: flex;
	align-items: center;
	justify-content: space-between;
	text-align: left;
}

.action-btn:hover {
	border-color: #667eea;
	background: #f8f9fa;
}

.action-btn .dashicons {
	margin-right: 10px;
	color: #667eea;
}

.action-count {
	background: #e91e63;
	color: white;
	padding: 4px 8px;
	border-radius: 12px;
	font-size: 12px;
	font-weight: 600;
}

.activity-list {
	max-height: 300px;
	overflow-y: auto;
}

.activity-item {
	display: flex;
	align-items: center;
	padding: 10px 0;
	border-bottom: 1px solid #eee;
}

.activity-item:last-child {
	border-bottom: none;
}

.activity-icon {
	margin-right: 15px;
}

.activity-icon .dashicons {
	color: #667eea;
	font-size: 18px;
}

.activity-content {
	flex: 1;
}

.activity-text {
	font-weight: 600;
	color: #2c3e50;
	margin-bottom: 2px;
}

.activity-time {
	font-size: 12px;
	color: #666;
}

.queue-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding-bottom: 15px;
	margin-bottom: 20px;
	border-bottom: 1px solid #eee;
}

.bulk-actions {
	display: flex;
	gap: 10px;
	align-items: center;
}

.bulk-actions select {
	padding: 6px 10px;
	border-radius: 4px;
	border: 1px solid #ddd;
}

.moderation-items {
	display: flex;
	flex-direction: column;
	gap: 15px;
}

.empty-queue {
	text-align: center;
	padding: 60px 20px;
	color: #666;
}

.empty-icon .dashicons {
	font-size: 64px;
	color: #ddd;
	margin-bottom: 20px;
}

.empty-queue h3 {
	margin: 0 0 10px 0;
	color: #2c3e50;
}

/* Modal styles */
.wpmatch-modal {
	position: fixed;
	z-index: 9999;
	left: 0;
	top: 0;
	width: 100%;
	height: 100%;
	background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
	background-color: #fff;
	margin: 5% auto;
	padding: 0;
	border-radius: 8px;
	width: 80%;
	max-width: 600px;
	box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.modal-header {
	padding: 20px;
	border-bottom: 1px solid #ddd;
	display: flex;
	justify-content: space-between;
	align-items: center;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	color: white;
	border-radius: 8px 8px 0 0;
}

.modal-header h2 {
	margin: 0;
	color: white;
}

.modal-close {
	background: none;
	border: none;
	font-size: 24px;
	cursor: pointer;
	color: white;
	padding: 0;
	width: 30px;
	height: 30px;
	display: flex;
	align-items: center;
	justify-content: center;
}

.modal-body {
	padding: 20px;
}

.setting-group {
	margin-bottom: 15px;
}

.setting-group label {
	display: block;
	margin-bottom: 5px;
	font-weight: 600;
	color: #2c3e50;
}

.setting-group input[type="checkbox"] {
	margin-right: 8px;
}

.setting-group select {
	width: 100%;
	padding: 8px 12px;
	border-radius: 4px;
	border: 1px solid #ddd;
}

.modal-actions {
	margin-top: 20px;
	display: flex;
	gap: 10px;
	justify-content: flex-end;
}

@media (max-width: 768px) {
	.wpmatch-moderation-header {
		flex-direction: column;
		gap: 15px;
		text-align: center;
	}

	.moderation-stats {
		grid-template-columns: 1fr;
	}

	.moderation-tabs {
		flex-direction: column;
	}

	.overview-grid {
		grid-template-columns: 1fr;
	}

	.queue-header {
		flex-direction: column;
		gap: 15px;
	}
}
</style>

<!-- JavaScript -->
<script>
var wpmatchModeration = {
	nonce: '<?php echo wp_create_nonce( 'wpmatch_admin_nonce' ); ?>'
};

function refreshModeration() {
	location.reload();
}

function showModerationSettings() {
	document.getElementById('moderation-settings-modal').style.display = 'block';
}

function closeModerationModal() {
	document.getElementById('moderation-settings-modal').style.display = 'none';
}

function performBulkModeration() {
	const action = document.getElementById('bulk-action-select').value;
	const selectedItems = document.querySelectorAll('.moderation-checkbox:checked');

	if (!action) {
		alert('Please select an action.');
		return;
	}

	if (selectedItems.length === 0) {
		alert('Please select at least one item.');
		return;
	}

	if (confirm(`Are you sure you want to ${action} ${selectedItems.length} item(s)?`)) {
		const itemIds = Array.from(selectedItems).map(checkbox => checkbox.value);

		fetch(ajaxurl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: new URLSearchParams({
				action: 'wpmatch_bulk_moderate',
				item_ids: itemIds,
				moderation_action: action,
				content_type: '<?php echo esc_js( $current_filter ); ?>',
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
		})
		.catch(error => {
			alert('Failed to perform bulk moderation.');
		});
	}
}

// Close modal when clicking outside
window.onclick = function(event) {
	const modal = document.getElementById('moderation-settings-modal');
	if (event.target === modal) {
		closeModerationModal();
	}
}
</script>