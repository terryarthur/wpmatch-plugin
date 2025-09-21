<?php
/**
 * Messages interface template
 *
 * @package WPMatch
 */

// Security check
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get current user
$current_user = wp_get_current_user();
if ( ! $current_user->ID ) {
	echo '<div class="wpmatch-notice error"><p>' . esc_html__( 'Please log in to view messages.', 'wpmatch' ) . '</p></div>';
	return;
}

// Get conversations
$conversations = WPMatch_Message_Manager::get_conversations( $current_user->ID );
$total_unread = WPMatch_Message_Manager::get_unread_count( $current_user->ID );

// Get current conversation
$current_conversation = '';
if ( isset( $_GET['conversation'] ) ) {
	$current_conversation = sanitize_text_field( $_GET['conversation'] );
}

?>

<div class="wpmatch-messages-container">
	<div class="wpmatch-messages-layout">

		<!-- Conversation List -->
		<div class="wpmatch-conversations-sidebar">
			<div class="wpmatch-conversations-header">
				<h3><?php esc_html_e( 'Messages', 'wpmatch' ); ?></h3>
				<?php if ( $total_unread > 0 ) : ?>
					<span class="wpmatch-unread-badge"><?php echo esc_html( $total_unread ); ?></span>
				<?php endif; ?>
			</div>

			<div class="wpmatch-conversations-list">
				<?php if ( empty( $conversations ) ) : ?>
					<div class="wpmatch-no-conversations">
						<p><?php esc_html_e( 'No conversations yet.', 'wpmatch' ); ?></p>
						<p><a href="<?php echo esc_url( home_url( '/browse/' ) ); ?>" class="wpmatch-button">
							<?php esc_html_e( 'Start browsing profiles', 'wpmatch' ); ?>
						</a></p>
					</div>
				<?php else : ?>
					<?php foreach ( $conversations as $conversation ) : ?>
						<?php
						$is_active = ( $current_conversation === $conversation->conversation_id );
						$unread_class = $conversation->unread_count > 0 ? 'unread' : '';
						$conversation_url = add_query_arg( 'conversation', $conversation->conversation_id );
						?>
						<div class="wpmatch-conversation-item <?php echo esc_attr( $unread_class ); ?> <?php echo $is_active ? 'active' : ''; ?>">
							<a href="<?php echo esc_url( $conversation_url ); ?>" class="wpmatch-conversation-link">
								<div class="wpmatch-conversation-avatar">
									<?php echo get_avatar( $conversation->other_user_id, 50 ); ?>
								</div>
								<div class="wpmatch-conversation-content">
									<div class="wpmatch-conversation-meta">
										<h4><?php echo esc_html( $conversation->other_user_name ); ?></h4>
										<?php if ( $conversation->unread_count > 0 ) : ?>
											<span class="wpmatch-unread-count"><?php echo esc_html( $conversation->unread_count ); ?></span>
										<?php endif; ?>
									</div>
									<div class="wpmatch-last-message">
										<?php if ( $conversation->last_message ) : ?>
											<p><?php echo esc_html( wp_trim_words( $conversation->last_message, 8 ) ); ?></p>
											<span class="wpmatch-message-time">
												<?php echo esc_html( human_time_diff( strtotime( $conversation->last_message_time ) ) . ' ago' ); ?>
											</span>
										<?php else : ?>
											<p class="wpmatch-no-messages"><?php esc_html_e( 'No messages yet', 'wpmatch' ); ?></p>
										<?php endif; ?>
									</div>
								</div>
							</a>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>

		<!-- Message Thread -->
		<div class="wpmatch-message-thread">
			<?php if ( $current_conversation ) : ?>
				<?php
				// Get messages for current conversation
				$messages = WPMatch_Message_Manager::get_messages( $current_conversation );

				// Get conversation info
				$conversation_info = null;
				foreach ( $conversations as $conv ) {
					if ( $conv->conversation_id === $current_conversation ) {
						$conversation_info = $conv;
						break;
					}
				}

				if ( $conversation_info ) :
					// Mark conversation as read
					WPMatch_Message_Manager::mark_conversation_as_read( $current_conversation, $current_user->ID );
				?>

					<!-- Message Header -->
					<div class="wpmatch-message-header">
						<div class="wpmatch-conversation-user">
							<?php echo get_avatar( $conversation_info->other_user_id, 40 ); ?>
							<h3><?php echo esc_html( $conversation_info->other_user_name ); ?></h3>
						</div>
						<div class="wpmatch-message-actions">
							<button type="button" class="wpmatch-button secondary small" id="wpmatch-block-user" data-user-id="<?php echo esc_attr( $conversation_info->other_user_id ); ?>">
								<?php esc_html_e( 'Block', 'wpmatch' ); ?>
							</button>
						</div>
					</div>

					<!-- Messages -->
					<div class="wpmatch-messages-list" id="wpmatch-messages-list">
						<?php if ( empty( $messages ) ) : ?>
							<div class="wpmatch-no-messages">
								<p><?php esc_html_e( 'No messages yet. Start the conversation!', 'wpmatch' ); ?></p>
							</div>
						<?php else : ?>
							<?php
							// Reverse messages to show oldest first
							$messages = array_reverse( $messages );
							foreach ( $messages as $message ) :
								$is_sender = ( $message->sender_id == $current_user->ID );
								$message_class = $is_sender ? 'sent' : 'received';
							?>
								<div class="wpmatch-message <?php echo esc_attr( $message_class ); ?>" data-message-id="<?php echo esc_attr( $message->message_id ); ?>">
									<div class="wpmatch-message-content">
										<p><?php echo esc_html( $message->message_content ); ?></p>
										<span class="wpmatch-message-time">
											<?php echo esc_html( date( 'M j, Y g:i A', strtotime( $message->created_at ) ) ); ?>
											<?php if ( $is_sender && $message->is_read ) : ?>
												<span class="wpmatch-read-indicator">✓</span>
											<?php endif; ?>
										</span>
									</div>
									<?php if ( $is_sender ) : ?>
										<div class="wpmatch-message-actions">
											<button type="button" class="wpmatch-delete-message" data-message-id="<?php echo esc_attr( $message->message_id ); ?>" title="<?php esc_attr_e( 'Delete message', 'wpmatch' ); ?>">
												×
											</button>
										</div>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>

					<!-- Message Input -->
					<div class="wpmatch-message-input-container">
						<form id="wpmatch-send-message-form" class="wpmatch-send-message-form">
							<div class="wpmatch-message-input-group">
								<textarea
									id="wpmatch-message-input"
									placeholder="<?php esc_attr_e( 'Type your message...', 'wpmatch' ); ?>"
									rows="2"
									maxlength="1000"
									required
								></textarea>
								<button type="submit" class="wpmatch-send-button">
									<span class="dashicons dashicons-arrow-right-alt2"></span>
								</button>
							</div>
							<input type="hidden" name="conversation_id" value="<?php echo esc_attr( $current_conversation ); ?>">
							<input type="hidden" name="recipient_id" value="<?php echo esc_attr( $conversation_info->other_user_id ); ?>">
							<?php wp_nonce_field( 'wpmatch_send_message', 'wpmatch_message_nonce' ); ?>
						</form>
					</div>

				<?php else : ?>
					<div class="wpmatch-message-error">
						<p><?php esc_html_e( 'Conversation not found.', 'wpmatch' ); ?></p>
					</div>
				<?php endif; ?>

			<?php else : ?>
				<!-- No conversation selected -->
				<div class="wpmatch-no-conversation-selected">
					<div class="wpmatch-empty-state">
						<span class="dashicons dashicons-email-alt"></span>
						<h3><?php esc_html_e( 'Select a conversation', 'wpmatch' ); ?></h3>
						<p><?php esc_html_e( 'Choose a conversation from the sidebar to start messaging.', 'wpmatch' ); ?></p>
					</div>
				</div>
			<?php endif; ?>
		</div>

	</div>
</div>

<!-- JavaScript for real-time messaging -->
<script type="text/javascript">
	// Pass data to JavaScript
	var wpmatch_messages = {
		ajax_url: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
		nonce: '<?php echo esc_js( wp_create_nonce( 'wpmatch_messages_nonce' ) ); ?>',
		current_user_id: <?php echo absint( $current_user->ID ); ?>,
		current_conversation: '<?php echo esc_js( $current_conversation ); ?>',
		strings: {
			sending: '<?php echo esc_js( __( 'Sending...', 'wpmatch' ) ); ?>',
			send_error: '<?php echo esc_js( __( 'Failed to send message.', 'wpmatch' ) ); ?>',
			delete_confirm: '<?php echo esc_js( __( 'Delete this message?', 'wpmatch' ) ); ?>',
			block_confirm: '<?php echo esc_js( __( 'Block this user? You will no longer receive messages from them.', 'wpmatch' ) ); ?>'
		}
	};
</script>