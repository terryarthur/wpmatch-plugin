<?php
/**
 * Message moderation item template
 *
 * @var object $item Message item data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sent_date = human_time_diff( strtotime( $item->sent_at ) );
$sender_avatar = get_avatar_url( $item->sender_id, array( 'size' => 40 ) );
$recipient_avatar = get_avatar_url( $item->recipient_id, array( 'size' => 40 ) );

// Message type indicators
$message_types = array(
	'text' => __( 'Text Message', 'wpmatch' ),
	'image' => __( 'Image Message', 'wpmatch' ),
	'voice' => __( 'Voice Note', 'wpmatch' ),
	'video' => __( 'Video Message', 'wpmatch' ),
	'emoji' => __( 'Emoji/Reaction', 'wpmatch' ),
);

$message_type_label = isset( $message_types[ $item->message_type ] ) ? $message_types[ $item->message_type ] : __( 'Unknown', 'wpmatch' );
?>

<div class="moderation-item message-item" data-item-id="<?php echo esc_attr( $item->id ); ?>">
	<div class="item-checkbox">
		<input type="checkbox" class="moderation-checkbox" value="<?php echo esc_attr( $item->id ); ?>">
	</div>

	<div class="message-indicator">
		<div class="message-type-icon message-type-<?php echo esc_attr( $item->message_type ); ?>" title="<?php echo esc_attr( $message_type_label ); ?>">
			<?php
			switch ( $item->message_type ) {
				case 'image':
					echo '<span class="dashicons dashicons-format-image"></span>';
					break;
				case 'voice':
					echo '<span class="dashicons dashicons-microphone"></span>';
					break;
				case 'video':
					echo '<span class="dashicons dashicons-video-alt3"></span>';
					break;
				case 'emoji':
					echo '<span class="dashicons dashicons-smiley"></span>';
					break;
				default:
					echo '<span class="dashicons dashicons-format-chat"></span>';
			}
			?>
		</div>
	</div>

	<div class="item-content">
		<div class="item-header">
			<h4 class="item-title">
				<?php echo esc_html( $message_type_label ); ?>
				<span class="message-id">#<?php echo esc_html( $item->id ); ?></span>
			</h4>
			<div class="item-meta">
				<span class="sent-date"><?php printf( esc_html__( 'Sent %s ago', 'wpmatch' ), esc_html( $sent_date ) ); ?></span>
				<?php if ( $item->report_count > 0 ) : ?>
					<span class="report-count"><?php printf( esc_html__( '%d reports', 'wpmatch' ), intval( $item->report_count ) ); ?></span>
				<?php endif; ?>
			</div>
		</div>

		<div class="message-parties">
			<div class="party sender">
				<img src="<?php echo esc_url( $sender_avatar ); ?>" alt="Sender" class="party-avatar">
				<div class="party-info">
					<strong><?php esc_html_e( 'From:', 'wpmatch' ); ?></strong>
					<span><?php echo esc_html( $item->sender_name ?: __( 'Unknown User', 'wpmatch' ) ); ?></span>
				</div>
			</div>
			<div class="party-arrow">→</div>
			<div class="party recipient">
				<img src="<?php echo esc_url( $recipient_avatar ); ?>" alt="Recipient" class="party-avatar">
				<div class="party-info">
					<strong><?php esc_html_e( 'To:', 'wpmatch' ); ?></strong>
					<span><?php echo esc_html( $item->recipient_name ?: __( 'Unknown User', 'wpmatch' ) ); ?></span>
				</div>
			</div>
		</div>

		<div class="message-content">
			<?php if ( 'text' === $item->message_type || 'emoji' === $item->message_type ) : ?>
				<div class="message-text">
					<strong><?php esc_html_e( 'Message:', 'wpmatch' ); ?></strong>
					<div class="message-bubble">
						<?php echo esc_html( wp_trim_words( $item->content, 50 ) ); ?>
					</div>
				</div>
			<?php elseif ( 'image' === $item->message_type && $item->attachment_url ) : ?>
				<div class="message-attachment">
					<strong><?php esc_html_e( 'Image:', 'wpmatch' ); ?></strong>
					<div class="attachment-preview">
						<img src="<?php echo esc_url( $item->attachment_url ); ?>" alt="Message attachment" class="message-image" onclick="showMessageModal('<?php echo esc_url( $item->attachment_url ); ?>')">
					</div>
					<?php if ( $item->content ) : ?>
						<div class="attachment-caption">
							<em><?php echo esc_html( $item->content ); ?></em>
						</div>
					<?php endif; ?>
				</div>
			<?php elseif ( 'voice' === $item->message_type && $item->attachment_url ) : ?>
				<div class="message-attachment">
					<strong><?php esc_html_e( 'Voice Note:', 'wpmatch' ); ?></strong>
					<div class="voice-player">
						<audio controls>
							<source src="<?php echo esc_url( $item->attachment_url ); ?>" type="audio/mpeg">
							<?php esc_html_e( 'Your browser does not support the audio element.', 'wpmatch' ); ?>
						</audio>
					</div>
				</div>
			<?php elseif ( 'video' === $item->message_type && $item->attachment_url ) : ?>
				<div class="message-attachment">
					<strong><?php esc_html_e( 'Video:', 'wpmatch' ); ?></strong>
					<div class="video-player">
						<video controls width="300">
							<source src="<?php echo esc_url( $item->attachment_url ); ?>" type="video/mp4">
							<?php esc_html_e( 'Your browser does not support the video element.', 'wpmatch' ); ?>
						</video>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( $item->report_reasons ) : ?>
			<div class="report-summary">
				<strong><?php esc_html_e( 'Reported for:', 'wpmatch' ); ?></strong>
				<div class="report-reasons">
					<?php
					$reasons = explode( ',', $item->report_reasons );
					foreach ( $reasons as $reason ) {
						echo '<span class="reason-tag">' . esc_html( trim( $reason ) ) . '</span>';
					}
					?>
				</div>
			</div>
		<?php endif; ?>

		<div class="message-metadata">
			<div class="metadata-row">
				<strong><?php esc_html_e( 'Status:', 'wpmatch' ); ?></strong>
				<span class="status-badge status-<?php echo esc_attr( $item->moderation_status ); ?>">
					<?php echo esc_html( ucfirst( $item->moderation_status ) ); ?>
				</span>
			</div>
			<div class="metadata-row">
				<strong><?php esc_html_e( 'Conversation:', 'wpmatch' ); ?></strong>
				<span><?php printf( esc_html__( 'Match #%d', 'wpmatch' ), intval( $item->match_id ) ); ?></span>
			</div>
		</div>

		<div class="moderation-assessment">
			<h5><?php esc_html_e( 'Content Assessment', 'wpmatch' ); ?></h5>
			<div class="assessment-items">
				<label>
					<input type="checkbox" class="assessment-check" data-check="appropriate">
					<?php esc_html_e( 'Content is appropriate', 'wpmatch' ); ?>
				</label>
				<label>
					<input type="checkbox" class="assessment-check" data-check="spam">
					<?php esc_html_e( 'Not spam or repetitive', 'wpmatch' ); ?>
				</label>
				<label>
					<input type="checkbox" class="assessment-check" data-check="harassment">
					<?php esc_html_e( 'No harassment detected', 'wpmatch' ); ?>
				</label>
				<label>
					<input type="checkbox" class="assessment-check" data-check="authentic">
					<?php esc_html_e( 'Appears authentic', 'wpmatch' ); ?>
				</label>
			</div>
		</div>

		<div class="investigation-notes">
			<h5><?php esc_html_e( 'Moderation Notes', 'wpmatch' ); ?></h5>
			<textarea class="moderation-textarea" placeholder="<?php esc_attr_e( 'Add moderation notes...', 'wpmatch' ); ?>" data-message-id="<?php echo esc_attr( $item->id ); ?>"><?php echo esc_textarea( $item->moderation_notes ?: '' ); ?></textarea>
		</div>
	</div>

	<div class="item-actions">
		<div class="action-buttons">
			<button type="button" class="action-btn approve" onclick="moderateItem(<?php echo esc_attr( $item->id ); ?>, 'approve', 'message')">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Approve', 'wpmatch' ); ?>
			</button>
			<button type="button" class="action-btn remove" onclick="moderateItem(<?php echo esc_attr( $item->id ); ?>, 'remove', 'message')">
				<span class="dashicons dashicons-trash"></span>
				<?php esc_html_e( 'Remove', 'wpmatch' ); ?>
			</button>
			<button type="button" class="action-btn view-conversation" onclick="showConversation(<?php echo esc_attr( $item->match_id ); ?>)">
				<span class="dashicons dashicons-format-chat"></span>
				<?php esc_html_e( 'View Thread', 'wpmatch' ); ?>
			</button>
			<button type="button" class="action-btn warn-sender" onclick="warnUser(<?php echo esc_attr( $item->sender_id ); ?>)">
				<span class="dashicons dashicons-warning"></span>
				<?php esc_html_e( 'Warn Sender', 'wpmatch' ); ?>
			</button>
		</div>

		<div class="removal-options" style="display: none;">
			<h5><?php esc_html_e( 'Removal Reason', 'wpmatch' ); ?></h5>
			<select class="removal-reason">
				<option value=""><?php esc_html_e( 'Select reason...', 'wpmatch' ); ?></option>
				<option value="inappropriate"><?php esc_html_e( 'Inappropriate content', 'wpmatch' ); ?></option>
				<option value="harassment"><?php esc_html_e( 'Harassment or abuse', 'wpmatch' ); ?></option>
				<option value="spam"><?php esc_html_e( 'Spam or promotional', 'wpmatch' ); ?></option>
				<option value="fake"><?php esc_html_e( 'Fake or misleading', 'wpmatch' ); ?></option>
				<option value="underage"><?php esc_html_e( 'Underage content', 'wpmatch' ); ?></option>
				<option value="violence"><?php esc_html_e( 'Violence or threats', 'wpmatch' ); ?></option>
				<option value="other"><?php esc_html_e( 'Other violation', 'wpmatch' ); ?></option>
			</select>
			<textarea class="removal-notes" placeholder="<?php esc_attr_e( 'Explain the reason for removal...', 'wpmatch' ); ?>"></textarea>
			<div class="removal-actions">
				<label>
					<input type="checkbox" class="notify-users">
					<?php esc_html_e( 'Notify users about removal', 'wpmatch' ); ?>
				</label>
				<label>
					<input type="checkbox" class="block-future">
					<?php esc_html_e( 'Block similar future messages', 'wpmatch' ); ?>
				</label>
			</div>
		</div>
	</div>
</div>

<!-- Message Modal -->
<div id="message-modal" class="message-modal" style="display: none;">
	<div class="message-modal-content">
		<span class="message-close" onclick="closeMessageModal()">&times;</span>
		<img id="modal-message-image" src="" alt="Full size message image">
	</div>
</div>

<style>
.message-item {
	border-left: 4px solid #17a2b8;
}

.message-indicator {
	margin-right: 10px;
}

.message-type-icon {
	width: 40px;
	height: 40px;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	color: white;
	font-weight: bold;
}

.message-type-text { background: #6c757d; }
.message-type-image { background: #28a745; }
.message-type-voice { background: #ffc107; color: #212529; }
.message-type-video { background: #dc3545; }
.message-type-emoji { background: #fd7e14; }

.message-id {
	font-size: 14px;
	color: #666;
	font-weight: normal;
}

.report-count {
	color: #dc3545;
	font-weight: 600;
}

.message-parties {
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
	color: #17a2b8;
	font-weight: bold;
}

.message-content {
	margin-bottom: 15px;
}

.message-bubble {
	background: #e3f2fd;
	border: 1px solid #bbdefb;
	border-radius: 18px;
	padding: 12px 16px;
	margin-top: 8px;
	max-width: 80%;
	word-wrap: break-word;
	position: relative;
}

.message-bubble::before {
	content: '';
	position: absolute;
	left: -8px;
	top: 12px;
	width: 0;
	height: 0;
	border-top: 8px solid transparent;
	border-bottom: 8px solid transparent;
	border-right: 8px solid #e3f2fd;
}

.attachment-preview img {
	max-width: 200px;
	max-height: 200px;
	border-radius: 8px;
	cursor: pointer;
	transition: transform 0.3s ease;
}

.attachment-preview img:hover {
	transform: scale(1.05);
}

.attachment-caption {
	margin-top: 8px;
	color: #666;
	font-style: italic;
}

.voice-player, .video-player {
	margin-top: 8px;
}

.voice-player audio {
	width: 300px;
	height: 40px;
}

.report-summary {
	margin-bottom: 15px;
	padding: 12px;
	background: #fff3cd;
	border: 1px solid #ffeaa7;
	border-radius: 6px;
}

.report-reasons {
	margin-top: 8px;
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
}

.reason-tag {
	display: inline-block;
	padding: 3px 8px;
	background: #ffc107;
	color: #212529;
	border-radius: 12px;
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
}

.message-metadata {
	display: flex;
	gap: 20px;
	margin-bottom: 15px;
}

.metadata-row {
	display: flex;
	align-items: center;
	gap: 8px;
}

.moderation-assessment {
	border-top: 1px solid #eee;
	padding-top: 15px;
	margin-bottom: 15px;
}

.moderation-assessment h5 {
	margin: 0 0 10px 0;
	color: #2c3e50;
	font-size: 14px;
}

.assessment-items {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
	gap: 8px;
}

.assessment-items label {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 13px;
	cursor: pointer;
}

.investigation-notes {
	border-top: 1px solid #eee;
	padding-top: 15px;
}

.investigation-notes h5 {
	margin: 0 0 10px 0;
	color: #2c3e50;
	font-size: 14px;
}

.moderation-textarea {
	width: 100%;
	min-height: 60px;
	padding: 8px;
	border-radius: 4px;
	border: 1px solid #ddd;
	resize: vertical;
}

.action-btn.remove {
	color: #dc3545;
	border-color: #dc3545;
}

.action-btn.remove:hover {
	background: #dc3545;
	color: white;
}

.action-btn.view-conversation {
	color: #6f42c1;
	border-color: #6f42c1;
}

.action-btn.view-conversation:hover {
	background: #6f42c1;
	color: white;
}

.action-btn.warn-sender {
	color: #fd7e14;
	border-color: #fd7e14;
}

.action-btn.warn-sender:hover {
	background: #fd7e14;
	color: white;
}

.removal-options {
	margin-top: 15px;
	padding-top: 15px;
	border-top: 1px solid #eee;
}

.removal-options h5 {
	margin: 0 0 10px 0;
	color: #dc3545;
	font-size: 14px;
}

.removal-reason {
	width: 100%;
	padding: 8px;
	border-radius: 4px;
	border: 1px solid #ddd;
	margin-bottom: 10px;
}

.removal-notes {
	width: 100%;
	min-height: 60px;
	padding: 8px;
	border-radius: 4px;
	border: 1px solid #ddd;
	resize: vertical;
	margin-bottom: 10px;
}

.removal-actions {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.removal-actions label {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 13px;
	cursor: pointer;
}

/* Message Modal Styles */
.message-modal {
	position: fixed;
	z-index: 10000;
	left: 0;
	top: 0;
	width: 100%;
	height: 100%;
	background-color: rgba(0, 0, 0, 0.9);
	display: flex;
	align-items: center;
	justify-content: center;
}

.message-modal-content {
	position: relative;
	max-width: 90%;
	max-height: 90%;
}

#modal-message-image {
	max-width: 100%;
	max-height: 100%;
	object-fit: contain;
	border-radius: 8px;
}

.message-close {
	position: absolute;
	top: -40px;
	right: 0;
	color: white;
	font-size: 30px;
	font-weight: bold;
	cursor: pointer;
	padding: 5px;
}

.message-close:hover {
	opacity: 0.7;
}

@media (max-width: 768px) {
	.message-parties {
		flex-direction: column;
		gap: 10px;
	}

	.party-arrow {
		transform: rotate(90deg);
	}

	.message-metadata {
		flex-direction: column;
		gap: 8px;
	}

	.assessment-items {
		grid-template-columns: 1fr;
	}

	.message-bubble {
		max-width: 100%;
	}

	.voice-player audio {
		width: 100%;
	}
}
</style>

<script>
function showMessageModal(imageUrl) {
	document.getElementById('modal-message-image').src = imageUrl;
	document.getElementById('message-modal').style.display = 'block';
}

function closeMessageModal() {
	document.getElementById('message-modal').style.display = 'none';
}

function showConversation(matchId) {
	// Implementation would show full conversation thread
	alert('Conversation view would be implemented here - showing all messages in match #' + matchId);
}

function warnUser(userId) {
	if (confirm('Are you sure you want to send a warning to this user?')) {
		fetch(ajaxurl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: new URLSearchParams({
				action: 'wpmatch_warn_user',
				user_id: userId,
				nonce: wpmatchModeration.nonce
			})
		})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				alert(data.data.message);
			} else {
				alert('Error: ' + data.data.message);
			}
		});
	}
}

// Show/hide removal options when remove button is clicked
document.addEventListener('DOMContentLoaded', function() {
	document.querySelectorAll('.action-btn.remove').forEach(button => {
		button.addEventListener('click', function() {
			const removalSection = this.closest('.item-actions').querySelector('.removal-options');
			if (removalSection) {
				removalSection.style.display = removalSection.style.display === 'none' ? 'block' : 'none';
			}
		});
	});
});
</script>