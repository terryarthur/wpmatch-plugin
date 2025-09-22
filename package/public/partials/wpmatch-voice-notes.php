<?php
/**
 * Voice Notes Template
 *
 * @package WPMatch
 * @since 1.6.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_user_id = get_current_user_id();
$recipient_id    = isset( $atts['recipient_id'] ) ? absint( $atts['recipient_id'] ) : 0;
?>

<div class="wpmatch-voice-notes" data-recipient-id="<?php echo esc_attr( $recipient_id ); ?>">
	<!-- Header -->
	<div class="voice-notes-header">
		<h3><?php esc_html_e( 'Voice Messages', 'wpmatch' ); ?></h3>
		<p class="voice-notes-subtitle"><?php esc_html_e( 'Record and send voice messages', 'wpmatch' ); ?></p>
	</div>

	<!-- Voice Recorder -->
	<div class="voice-recorder">
		<div class="recorder-status">
			<div class="status-text"><?php esc_html_e( 'Ready to record', 'wpmatch' ); ?></div>
			<div class="recording-timer">00:00.00</div>
		</div>

		<!-- Recording Visualization -->
		<div class="recording-visualizer">
			<div class="visualizer-bars">
				<!-- Bars will be created dynamically -->
			</div>
		</div>

		<!-- Recorder Controls -->
		<div class="recorder-controls">
			<button class="recorder-btn btn-record" title="<?php esc_attr_e( 'Start Recording (R)', 'wpmatch' ); ?>">
				🎤
			</button>
			<button class="recorder-btn btn-stop" disabled title="<?php esc_attr_e( 'Stop Recording (S)', 'wpmatch' ); ?>">
				⏹️
			</button>
			<button class="recorder-btn btn-play" disabled title="<?php esc_attr_e( 'Play Preview (Space)', 'wpmatch' ); ?>">
				▶️
			</button>
			<button class="recorder-btn btn-pause" disabled title="<?php esc_attr_e( 'Pause Preview', 'wpmatch' ); ?>">
				⏸️
			</button>
			<button class="recorder-btn btn-delete" disabled title="<?php esc_attr_e( 'Delete Recording', 'wpmatch' ); ?>">
				🗑️
			</button>
			<?php if ( $recipient_id ) : ?>
				<button class="recorder-btn btn-send" disabled data-recipient-id="<?php echo esc_attr( $recipient_id ); ?>" title="<?php esc_attr_e( 'Send Voice Note', 'wpmatch' ); ?>">
					📤
				</button>
			<?php endif; ?>
		</div>

		<!-- Quality Settings -->
		<div class="quality-settings">
			<h4><?php esc_html_e( 'Recording Quality', 'wpmatch' ); ?></h4>
			<div class="quality-options">
				<button class="quality-btn" data-quality="low"><?php esc_html_e( 'Low', 'wpmatch' ); ?></button>
				<button class="quality-btn active" data-quality="medium"><?php esc_html_e( 'Medium', 'wpmatch' ); ?></button>
				<button class="quality-btn" data-quality="high"><?php esc_html_e( 'High', 'wpmatch' ); ?></button>
			</div>
		</div>
	</div>

	<!-- Voice Notes List -->
	<div class="voice-notes-list">
		<div class="section-header">
			<h4><?php esc_html_e( 'Voice Messages', 'wpmatch' ); ?></h4>
		</div>

		<!-- Filters -->
		<div class="voice-notes-filters">
			<button class="filter-btn active" data-filter="all"><?php esc_html_e( 'All', 'wpmatch' ); ?></button>
			<button class="filter-btn" data-filter="sent"><?php esc_html_e( 'Sent', 'wpmatch' ); ?></button>
			<button class="filter-btn" data-filter="received"><?php esc_html_e( 'Received', 'wpmatch' ); ?></button>
			<button class="filter-btn" data-filter="favorites"><?php esc_html_e( 'Favorites', 'wpmatch' ); ?></button>
		</div>

		<!-- Voice Notes Container -->
		<div class="voice-notes-container">
			<!-- Voice notes will be loaded via AJAX -->
			<div class="voice-loading">
				<div class="loading-spinner"></div>
				<span><?php esc_html_e( 'Loading voice messages...', 'wpmatch' ); ?></span>
			</div>
		</div>
	</div>

	<!-- Instructions -->
	<div class="voice-instructions">
		<h5><?php esc_html_e( 'How to use:', 'wpmatch' ); ?></h5>
		<ul>
			<li><?php esc_html_e( 'Click the microphone button or press "R" to start recording', 'wpmatch' ); ?></li>
			<li><?php esc_html_e( 'Click stop or press "S" to finish recording', 'wpmatch' ); ?></li>
			<li><?php esc_html_e( 'Preview your recording before sending', 'wpmatch' ); ?></li>
			<li><?php esc_html_e( 'Choose quality settings for different file sizes', 'wpmatch' ); ?></li>
		</ul>
	</div>
</div>

<!-- Voice Message Template for Chat Integration -->
<script type="text/template" id="voice-message-template">
	<div class="voice-message <%- direction %>">
		<div class="voice-message-content">
			<div class="player-controls">
				<button class="player-btn play-btn" data-url="<%- file_url %>">
					<span class="play-icon">▶</span>
					<span class="pause-icon" style="display: none;">⏸</span>
				</button>
				<div class="player-progress">
					<div class="progress-filled" style="width: 0%"></div>
					<div class="progress-handle" style="left: 0%"></div>
				</div>
				<div class="player-time">0:00 / <%- duration %></div>
			</div>
		</div>
	</div>
</script>

<script type="text/javascript">
// Localize script data
var wpmatch_voice_notes = {
	rest_url: '<?php echo esc_url( rest_url() ); ?>',
	nonce: '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>',
	current_user_id: <?php echo esc_js( $current_user_id ); ?>,
	default_avatar: '<?php echo esc_url( get_avatar_url( $current_user_id, array( 'size' => 36 ) ) ); ?>',
	strings: {
		loading: '<?php esc_html_e( 'Loading...', 'wpmatch' ); ?>',
		error: '<?php esc_html_e( 'Error occurred', 'wpmatch' ); ?>',
		no_voice_notes: '<?php esc_html_e( 'No voice messages found', 'wpmatch' ); ?>',
		delete_confirm: '<?php esc_html_e( 'Delete this recording?', 'wpmatch' ); ?>',
		delete_note_confirm: '<?php esc_html_e( 'Delete this voice message? This action cannot be undone.', 'wpmatch' ); ?>',
		send_success: '<?php esc_html_e( 'Voice message sent successfully!', 'wpmatch' ); ?>',
		send_error: '<?php esc_html_e( 'Failed to send voice message', 'wpmatch' ); ?>',
		upload_error: '<?php esc_html_e( 'Upload failed. Please try again.', 'wpmatch' ); ?>',
		load_error: '<?php esc_html_e( 'Failed to load voice messages', 'wpmatch' ); ?>',
		reaction_error: '<?php esc_html_e( 'Failed to add reaction', 'wpmatch' ); ?>',
		delete_error: '<?php esc_html_e( 'Failed to delete voice message', 'wpmatch' ); ?>',
		microphone_error: '<?php esc_html_e( 'Microphone access denied. Please allow microphone access to record voice messages.', 'wpmatch' ); ?>',
		browser_error: '<?php esc_html_e( 'Voice recording is not supported in this browser', 'wpmatch' ); ?>',
		recording_too_short: '<?php esc_html_e( 'Recording too short. Minimum duration is 1 second.', 'wpmatch' ); ?>',
		recording_too_long: '<?php esc_html_e( 'Recording too long. Maximum duration is 5 minutes.', 'wpmatch' ); ?>',
		file_too_large: '<?php esc_html_e( 'File too large. Maximum size is 10MB.', 'wpmatch' ); ?>'
	}
};
</script>

<style>
/* Inline critical styles for immediate rendering */
.voice-notes-container {
	min-height: 200px;
}

.voice-loading {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 10px;
	padding: 40px;
	color: #666;
}

.voice-instructions {
	background: #f8f9fa;
	border-radius: 8px;
	padding: 15px;
	margin-top: 20px;
}

.voice-instructions h5 {
	margin: 0 0 10px 0;
	color: #333;
}

.voice-instructions ul {
	margin: 0;
	padding-left: 20px;
}

.voice-instructions li {
	margin-bottom: 5px;
	color: #666;
	font-size: 14px;
}

@media (max-width: 768px) {
	.voice-instructions {
		padding: 12px;
		font-size: 13px;
	}
}
</style>