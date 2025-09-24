<?php
/**
 * Profile moderation item template
 *
 * @var object $item Profile item data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$avatar_url = get_avatar_url( $item->user_id, array( 'size' => 80 ) );
$joined_date = human_time_diff( strtotime( $item->user_registered ) );
?>

<div class="moderation-item profile-item" data-item-id="<?php echo esc_attr( $item->user_id ); ?>">
	<div class="item-checkbox">
		<input type="checkbox" class="moderation-checkbox" value="<?php echo esc_attr( $item->user_id ); ?>">
	</div>

	<div class="item-avatar">
		<img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $item->display_name ); ?>" class="avatar">
	</div>

	<div class="item-content">
		<div class="item-header">
			<h4 class="item-title"><?php echo esc_html( $item->display_name ); ?></h4>
			<div class="item-meta">
				<span class="user-email"><?php echo esc_html( $item->user_email ); ?></span>
				<span class="join-date"><?php printf( esc_html__( 'Joined %s ago', 'wpmatch' ), esc_html( $joined_date ) ); ?></span>
			</div>
		</div>

		<div class="profile-details">
			<div class="detail-row">
				<strong><?php esc_html_e( 'Age:', 'wpmatch' ); ?></strong>
				<span><?php echo esc_html( $item->age ?: __( 'Not specified', 'wpmatch' ) ); ?></span>
			</div>
			<div class="detail-row">
				<strong><?php esc_html_e( 'Gender:', 'wpmatch' ); ?></strong>
				<span><?php echo esc_html( $item->gender ?: __( 'Not specified', 'wpmatch' ) ); ?></span>
			</div>
			<div class="detail-row">
				<strong><?php esc_html_e( 'Location:', 'wpmatch' ); ?></strong>
				<span><?php echo esc_html( $item->location ?: __( 'Not specified', 'wpmatch' ) ); ?></span>
			</div>
			<?php if ( $item->about_me ) : ?>
				<div class="profile-bio">
					<strong><?php esc_html_e( 'About:', 'wpmatch' ); ?></strong>
					<p><?php echo esc_html( wp_trim_words( $item->about_me, 30 ) ); ?></p>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="item-actions">
		<div class="action-buttons">
			<button type="button" class="action-btn approve" onclick="moderateItem(<?php echo esc_attr( $item->user_id ); ?>, 'approve', 'profile')">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Approve', 'wpmatch' ); ?>
			</button>
			<button type="button" class="action-btn reject" onclick="moderateItem(<?php echo esc_attr( $item->user_id ); ?>, 'reject', 'profile')">
				<span class="dashicons dashicons-dismiss"></span>
				<?php esc_html_e( 'Reject', 'wpmatch' ); ?>
			</button>
			<button type="button" class="action-btn view" onclick="showUserProfile(<?php echo esc_attr( $item->user_id ); ?>)">
				<span class="dashicons dashicons-visibility"></span>
				<?php esc_html_e( 'View Full', 'wpmatch' ); ?>
			</button>
		</div>
	</div>
</div>

<style>
.moderation-item {
	background: white;
	border: 1px solid #e9ecef;
	border-radius: 8px;
	padding: 20px;
	display: flex;
	align-items: flex-start;
	gap: 15px;
	transition: border-color 0.3s ease;
}

.moderation-item:hover {
	border-color: #667eea;
}

.item-checkbox input {
	margin: 0;
}

.item-avatar img {
	width: 80px;
	height: 80px;
	border-radius: 50%;
	object-fit: cover;
}

.item-content {
	flex: 1;
}

.item-header {
	margin-bottom: 15px;
}

.item-title {
	margin: 0 0 5px 0;
	color: #2c3e50;
	font-size: 18px;
}

.item-meta {
	display: flex;
	gap: 15px;
	font-size: 14px;
	color: #666;
}

.profile-details {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 10px;
}

.detail-row {
	display: flex;
	gap: 8px;
}

.detail-row strong {
	color: #2c3e50;
}

.profile-bio {
	grid-column: 1 / -1;
	margin-top: 10px;
}

.profile-bio p {
	margin: 5px 0 0 0;
	color: #555;
}

.item-actions {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.action-buttons {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.action-btn {
	padding: 10px 15px;
	border: 1px solid #ddd;
	border-radius: 6px;
	background: white;
	cursor: pointer;
	transition: all 0.3s ease;
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 14px;
	min-width: 120px;
}

.action-btn:hover {
	background: #f8f9fa;
}

.action-btn.approve {
	color: #28a745;
	border-color: #28a745;
}

.action-btn.approve:hover {
	background: #28a745;
	color: white;
}

.action-btn.reject {
	color: #dc3545;
	border-color: #dc3545;
}

.action-btn.reject:hover {
	background: #dc3545;
	color: white;
}

.action-btn.view {
	color: #17a2b8;
	border-color: #17a2b8;
}

.action-btn.view:hover {
	background: #17a2b8;
	color: white;
}

@media (max-width: 768px) {
	.moderation-item {
		flex-direction: column;
	}

	.profile-details {
		grid-template-columns: 1fr;
	}

	.action-buttons {
		flex-direction: row;
		flex-wrap: wrap;
	}
}
</style>