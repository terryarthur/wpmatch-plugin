<?php
/**
 * WPMatch Messaging Interface Template
 *
 * This template provides a comprehensive real-time messaging system
 * for WPMatch dating plugin with desktop-optimized responsive design.
 *
 * @package WPMatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_user_id = get_current_user_id();
if ( ! $current_user_id ) {
	wp_redirect( wp_login_url() );
	exit;
}
?>

<div id="wpmatch-messaging-container" class="wpmatch-messaging-interface">
	<div class="messaging-layout">
		<!-- Conversations Sidebar -->
		<div class="conversations-sidebar">
			<div class="sidebar-header">
				<h3><?php esc_html_e( 'Messages', 'wpmatch' ); ?></h3>
				<div class="search-conversations">
					<input type="text" id="conversation-search" placeholder="<?php esc_attr_e( 'Search conversations...', 'wpmatch' ); ?>">
					<span class="search-icon">🔍</span>
				</div>
			</div>

			<div class="conversations-list" id="conversations-list">
				<div class="loading-conversations">
					<div class="loading-spinner"></div>
					<p><?php esc_html_e( 'Loading conversations...', 'wpmatch' ); ?></p>
				</div>
			</div>
		</div>

		<!-- Main Chat Area -->
		<div class="chat-area">
			<div class="chat-welcome" id="chat-welcome">
				<div class="welcome-content">
					<div class="welcome-icon">💬</div>
					<h3><?php esc_html_e( 'Select a conversation to start messaging', 'wpmatch' ); ?></h3>
					<p><?php esc_html_e( 'Choose from your existing conversations or start a new one with your matches.', 'wpmatch' ); ?></p>
				</div>
			</div>

			<div class="active-chat" id="active-chat" style="display: none;">
				<!-- Chat Header -->
				<div class="chat-header">
					<div class="chat-user-info">
						<div class="user-avatar">
							<img id="chat-user-avatar" src="" alt="">
							<div class="online-status" id="user-online-status"></div>
						</div>
						<div class="user-details">
							<h4 id="chat-user-name"></h4>
							<p id="chat-user-status" class="user-status"></p>
						</div>
					</div>
					<div class="chat-actions">
						<button class="action-btn" id="video-call-btn" title="<?php esc_attr_e( 'Video Call', 'wpmatch' ); ?>">📹</button>
						<button class="action-btn" id="voice-call-btn" title="<?php esc_attr_e( 'Voice Call', 'wpmatch' ); ?>">📞</button>
						<button class="action-btn" id="chat-options-btn" title="<?php esc_attr_e( 'Options', 'wpmatch' ); ?>">⚙️</button>
					</div>
				</div>

				<!-- Messages Container -->
				<div class="messages-container" id="messages-container">
					<div class="messages-scroll" id="messages-scroll">
						<!-- Messages will be loaded here -->
					</div>

					<!-- Typing Indicator -->
					<div class="typing-indicator" id="typing-indicator" style="display: none;">
						<div class="typing-animation">
							<span></span>
							<span></span>
							<span></span>
						</div>
						<span class="typing-text"></span>
					</div>
				</div>

				<!-- Message Input -->
				<div class="message-input-container">
					<div class="message-attachments" id="message-attachments" style="display: none;">
						<!-- File attachments preview -->
					</div>

					<form class="message-form" id="message-form">
						<div class="input-actions">
							<button type="button" class="attach-btn" id="attach-file-btn" title="<?php esc_attr_e( 'Attach File', 'wpmatch' ); ?>">📎</button>
							<button type="button" class="emoji-btn" id="emoji-btn" title="<?php esc_attr_e( 'Add Emoji', 'wpmatch' ); ?>">😊</button>
						</div>

						<div class="message-input-wrapper">
							<textarea
								id="message-input"
								name="message"
								placeholder="<?php esc_attr_e( 'Type your message...', 'wpmatch' ); ?>"
								rows="1"
								maxlength="1000"
							></textarea>
							<div class="char-counter">
								<span id="char-count">0</span>/1000
							</div>
						</div>

						<button type="submit" class="send-btn" id="send-btn" disabled>
							<span class="send-icon">➤</span>
						</button>

						<input type="hidden" id="conversation-id" name="conversation_id" value="">
						<input type="hidden" id="recipient-id" name="recipient_id" value="">
						<?php wp_nonce_field( 'wpmatch_send_message', 'wpmatch_message_nonce' ); ?>
					</form>
				</div>
			</div>
		</div>

		<!-- User Profile Panel -->
		<div class="profile-panel" id="profile-panel" style="display: none;">
			<div class="panel-header">
				<h4><?php esc_html_e( 'Profile', 'wpmatch' ); ?></h4>
				<button class="close-panel" id="close-profile-panel">✕</button>
			</div>
			<div class="profile-content" id="profile-content">
				<!-- Profile details will be loaded here -->
			</div>
		</div>
	</div>

	<!-- File Upload Input -->
	<input type="file" id="file-upload-input" multiple style="display: none;" accept="image/*,video/*,.pdf,.doc,.docx">

	<!-- Emoji Picker -->
	<div class="emoji-picker" id="emoji-picker" style="display: none;">
		<div class="emoji-categories">
			<button class="emoji-category active" data-category="smileys">😊</button>
			<button class="emoji-category" data-category="people">👍</button>
			<button class="emoji-category" data-category="nature">🌸</button>
			<button class="emoji-category" data-category="food">🍕</button>
			<button class="emoji-category" data-category="activities">⚽</button>
			<button class="emoji-category" data-category="travel">✈️</button>
			<button class="emoji-category" data-category="objects">💎</button>
			<button class="emoji-category" data-category="symbols">❤️</button>
		</div>
		<div class="emoji-grid" id="emoji-grid">
			<!-- Emojis will be populated here -->
		</div>
	</div>
</div>

<style>
.wpmatch-messaging-interface {
	max-width: 1400px;
	margin: 0 auto;
	padding: 20px;
	font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	min-height: 100vh;
	box-sizing: border-box;
}

.messaging-layout {
	display: grid;
	grid-template-columns: 320px 1fr auto;
	gap: 0;
	height: 80vh;
	min-height: 600px;
	background: white;
	border-radius: 20px;
	overflow: hidden;
	box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
}

/* Conversations Sidebar */
.conversations-sidebar {
	background: #f8fafc;
	border-right: 1px solid #e2e8f0;
	display: flex;
	flex-direction: column;
}

.sidebar-header {
	padding: 25px 20px;
	border-bottom: 1px solid #e2e8f0;
	background: white;
}

.sidebar-header h3 {
	margin: 0 0 15px 0;
	font-size: 22px;
	font-weight: 600;
	color: #1a202c;
}

.search-conversations {
	position: relative;
}

.search-conversations input {
	width: 100%;
	padding: 12px 40px 12px 15px;
	border: 1px solid #e2e8f0;
	border-radius: 25px;
	font-size: 14px;
	background: #f8fafc;
	transition: all 0.2s ease;
	box-sizing: border-box;
}

.search-conversations input:focus {
	outline: none;
	border-color: #667eea;
	background: white;
	box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.search-icon {
	position: absolute;
	right: 15px;
	top: 50%;
	transform: translateY(-50%);
	color: #a0aec0;
}

.conversations-list {
	flex: 1;
	overflow-y: auto;
	padding: 0;
}

.loading-conversations {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	height: 200px;
	color: #718096;
}

.loading-spinner {
	width: 30px;
	height: 30px;
	border: 3px solid #e2e8f0;
	border-top: 3px solid #667eea;
	border-radius: 50%;
	animation: spin 1s linear infinite;
	margin-bottom: 15px;
}

@keyframes spin {
	0% { transform: rotate(0deg); }
	100% { transform: rotate(360deg); }
}

.conversation-item {
	padding: 15px 20px;
	cursor: pointer;
	border-bottom: 1px solid #f1f5f9;
	transition: all 0.2s ease;
	position: relative;
}

.conversation-item:hover {
	background: white;
}

.conversation-item.active {
	background: #667eea;
	color: white;
}

.conversation-item.unread {
	background: #eef2ff;
	border-left: 4px solid #667eea;
}

.conversation-header {
	display: flex;
	align-items: center;
	margin-bottom: 8px;
}

.conversation-avatar {
	width: 45px;
	height: 45px;
	border-radius: 50%;
	margin-right: 12px;
	position: relative;
	overflow: hidden;
}

.conversation-avatar img {
	width: 100%;
	height: 100%;
	object-fit: cover;
}

.online-indicator {
	position: absolute;
	bottom: 2px;
	right: 2px;
	width: 12px;
	height: 12px;
	border-radius: 50%;
	border: 2px solid white;
}

.online-indicator.online {
	background: #48bb78;
}

.online-indicator.away {
	background: #ed8936;
}

.online-indicator.offline {
	background: #a0aec0;
}

.conversation-info {
	flex: 1;
	min-width: 0;
}

.conversation-name {
	font-weight: 600;
	font-size: 14px;
	margin: 0;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.conversation-meta {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-top: 4px;
}

.last-message {
	font-size: 13px;
	color: #718096;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	max-width: 150px;
}

.conversation-item.active .last-message {
	color: rgba(255, 255, 255, 0.8);
}

.message-time {
	font-size: 12px;
	color: #a0aec0;
	white-space: nowrap;
}

.conversation-item.active .message-time {
	color: rgba(255, 255, 255, 0.7);
}

.unread-count {
	background: #e53e3e;
	color: white;
	border-radius: 10px;
	padding: 2px 6px;
	font-size: 11px;
	font-weight: 600;
	min-width: 18px;
	text-align: center;
	margin-left: 8px;
}

/* Chat Area */
.chat-area {
	display: flex;
	flex-direction: column;
	background: white;
	position: relative;
}

.chat-welcome {
	display: flex;
	align-items: center;
	justify-content: center;
	height: 100%;
	text-align: center;
	color: #718096;
}

.welcome-content {
	max-width: 400px;
	padding: 40px;
}

.welcome-icon {
	font-size: 60px;
	margin-bottom: 20px;
}

.welcome-content h3 {
	margin: 0 0 15px 0;
	font-size: 24px;
	color: #2d3748;
}

.welcome-content p {
	font-size: 16px;
	line-height: 1.6;
	margin: 0;
}

.active-chat {
	display: flex;
	flex-direction: column;
	height: 100%;
}

.chat-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 20px 25px;
	border-bottom: 1px solid #e2e8f0;
	background: white;
	position: relative;
	z-index: 10;
}

.chat-user-info {
	display: flex;
	align-items: center;
}

.chat-header .user-avatar {
	width: 50px;
	height: 50px;
	border-radius: 50%;
	margin-right: 15px;
	position: relative;
	overflow: hidden;
}

.chat-header .user-avatar img {
	width: 100%;
	height: 100%;
	object-fit: cover;
}

.user-details h4 {
	margin: 0;
	font-size: 18px;
	font-weight: 600;
	color: #1a202c;
}

.user-status {
	margin: 4px 0 0 0;
	font-size: 14px;
	color: #718096;
}

.chat-actions {
	display: flex;
	gap: 10px;
}

.action-btn {
	width: 40px;
	height: 40px;
	border: none;
	border-radius: 50%;
	background: #f7fafc;
	color: #4a5568;
	cursor: pointer;
	font-size: 16px;
	transition: all 0.2s ease;
	display: flex;
	align-items: center;
	justify-content: center;
}

.action-btn:hover {
	background: #667eea;
	color: white;
	transform: scale(1.05);
}

/* Messages Container */
.messages-container {
	flex: 1;
	display: flex;
	flex-direction: column;
	overflow: hidden;
	position: relative;
}

.messages-scroll {
	flex: 1;
	overflow-y: auto;
	padding: 20px 25px;
	scroll-behavior: smooth;
}

.message {
	margin-bottom: 15px;
	display: flex;
	align-items: flex-end;
	gap: 10px;
}

.message.sent {
	flex-direction: row-reverse;
}

.message-avatar {
	width: 32px;
	height: 32px;
	border-radius: 50%;
	overflow: hidden;
	flex-shrink: 0;
}

.message-avatar img {
	width: 100%;
	height: 100%;
	object-fit: cover;
}

.message-content {
	max-width: 60%;
	position: relative;
}

.message-bubble {
	padding: 12px 16px;
	border-radius: 18px;
	font-size: 14px;
	line-height: 1.4;
	word-wrap: break-word;
	position: relative;
}

.message.received .message-bubble {
	background: #f1f5f9;
	color: #1a202c;
	border-bottom-left-radius: 6px;
}

.message.sent .message-bubble {
	background: #667eea;
	color: white;
	border-bottom-right-radius: 6px;
}

.message-time {
	font-size: 11px;
	color: #a0aec0;
	margin-top: 4px;
	text-align: right;
}

.message.received .message-time {
	text-align: left;
}

.message-status {
	font-size: 10px;
	color: #a0aec0;
	margin-top: 2px;
}

.message-status.read {
	color: #667eea;
}

/* Typing Indicator */
.typing-indicator {
	padding: 10px 25px;
	display: flex;
	align-items: center;
	gap: 10px;
}

.typing-animation {
	display: flex;
	gap: 3px;
}

.typing-animation span {
	width: 6px;
	height: 6px;
	border-radius: 50%;
	background: #a0aec0;
	animation: typing 1.4s infinite ease-in-out;
}

.typing-animation span:nth-child(1) {
	animation-delay: -0.32s;
}

.typing-animation span:nth-child(2) {
	animation-delay: -0.16s;
}

@keyframes typing {
	0%, 80%, 100% {
		transform: scale(0.8);
		opacity: 0.5;
	}
	40% {
		transform: scale(1);
		opacity: 1;
	}
}

.typing-text {
	font-size: 12px;
	color: #718096;
}

/* Message Input */
.message-input-container {
	border-top: 1px solid #e2e8f0;
	padding: 20px 25px;
	background: white;
}

.message-form {
	display: flex;
	align-items: flex-end;
	gap: 12px;
}

.input-actions {
	display: flex;
	gap: 8px;
}

.attach-btn, .emoji-btn {
	width: 36px;
	height: 36px;
	border: none;
	border-radius: 50%;
	background: #f7fafc;
	color: #4a5568;
	cursor: pointer;
	font-size: 16px;
	transition: all 0.2s ease;
	display: flex;
	align-items: center;
	justify-content: center;
}

.attach-btn:hover, .emoji-btn:hover {
	background: #edf2f7;
	transform: scale(1.05);
}

.message-input-wrapper {
	flex: 1;
	position: relative;
}

#message-input {
	width: 100%;
	min-height: 40px;
	max-height: 120px;
	padding: 10px 50px 10px 15px;
	border: 1px solid #e2e8f0;
	border-radius: 20px;
	font-size: 14px;
	font-family: inherit;
	resize: none;
	outline: none;
	transition: all 0.2s ease;
	box-sizing: border-box;
}

#message-input:focus {
	border-color: #667eea;
	box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.char-counter {
	position: absolute;
	right: 15px;
	bottom: 8px;
	font-size: 11px;
	color: #a0aec0;
	pointer-events: none;
}

.send-btn {
	width: 40px;
	height: 40px;
	border: none;
	border-radius: 50%;
	background: #a0aec0;
	color: white;
	cursor: pointer;
	font-size: 16px;
	transition: all 0.2s ease;
	display: flex;
	align-items: center;
	justify-content: center;
}

.send-btn:enabled {
	background: #667eea;
}

.send-btn:enabled:hover {
	background: #5a67d8;
	transform: scale(1.05);
}

.send-btn:disabled {
	cursor: not-allowed;
	opacity: 0.5;
}

/* Emoji Picker */
.emoji-picker {
	position: absolute;
	bottom: 80px;
	left: 80px;
	width: 300px;
	height: 250px;
	background: white;
	border: 1px solid #e2e8f0;
	border-radius: 15px;
	box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
	z-index: 1000;
}

.emoji-categories {
	display: flex;
	border-bottom: 1px solid #e2e8f0;
	padding: 10px;
	gap: 5px;
}

.emoji-category {
	padding: 8px;
	border: none;
	background: none;
	border-radius: 8px;
	cursor: pointer;
	font-size: 16px;
	transition: all 0.2s ease;
}

.emoji-category:hover, .emoji-category.active {
	background: #eef2ff;
}

.emoji-grid {
	padding: 10px;
	height: 180px;
	overflow-y: auto;
	display: grid;
	grid-template-columns: repeat(8, 1fr);
	gap: 5px;
}

.emoji-item {
	width: 30px;
	height: 30px;
	border: none;
	background: none;
	border-radius: 6px;
	cursor: pointer;
	font-size: 16px;
	transition: all 0.2s ease;
	display: flex;
	align-items: center;
	justify-content: center;
}

.emoji-item:hover {
	background: #f7fafc;
	transform: scale(1.2);
}

/* Responsive Design */
@media (max-width: 1024px) {
	.messaging-layout {
		grid-template-columns: 280px 1fr;
	}

	.profile-panel {
		position: absolute;
		right: 0;
		top: 0;
		height: 100%;
		background: white;
		border-left: 1px solid #e2e8f0;
		z-index: 20;
		transform: translateX(100%);
		transition: transform 0.3s ease;
	}

	.profile-panel.open {
		transform: translateX(0);
	}
}

@media (max-width: 768px) {
	.wpmatch-messaging-interface {
		padding: 10px;
	}

	.messaging-layout {
		grid-template-columns: 1fr;
		height: calc(100vh - 20px);
	}

	.conversations-sidebar {
		position: absolute;
		left: 0;
		top: 0;
		height: 100%;
		width: 100%;
		background: white;
		z-index: 30;
		transform: translateX(-100%);
		transition: transform 0.3s ease;
	}

	.conversations-sidebar.open {
		transform: translateX(0);
	}

	.chat-header {
		padding: 15px 20px;
	}

	.messages-scroll {
		padding: 15px 20px;
	}

	.message-input-container {
		padding: 15px 20px;
	}

	.message-content {
		max-width: 80%;
	}

	.emoji-picker {
		left: 20px;
		right: 20px;
		width: auto;
	}
}

@media (max-width: 480px) {
	.sidebar-header {
		padding: 20px 15px;
	}

	.conversation-item {
		padding: 12px 15px;
	}

	.chat-header {
		padding: 12px 15px;
	}

	.messages-scroll {
		padding: 12px 15px;
	}

	.message-input-container {
		padding: 12px 15px;
	}

	.action-btn {
		width: 36px;
		height: 36px;
		font-size: 14px;
	}
}
</style>

<script>
class MessagingInterface {
	constructor() {
		this.currentConversationId = null;
		this.currentRecipientId = null;
		this.isTyping = false;
		this.typingTimeout = null;
		this.lastMessageId = 0;
		this.emojis = {
			smileys: ['😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇', '🙂', '🙃', '😉', '😌', '😍', '🥰', '😘', '😗', '😙', '😚', '😋', '😛', '😝', '😜', '🤪', '🤨', '🧐', '🤓', '😎', '🤩', '🥳'],
			people: ['👍', '👎', '👊', '✊', '🤛', '🤜', '🤞', '✌️', '🤟', '🤘', '👌', '🤏', '👈', '👉', '👆', '👇', '☝️', '✋', '🤚', '🖐️', '🖖', '👋', '🤙', '💪', '🦾', '🖕', '✍️', '🙏', '🦶', '🦵'],
			nature: ['🌸', '🌺', '🌻', '🌷', '🌹', '🥀', '🌵', '🌲', '🌳', '🌴', '🌱', '🌿', '🍀', '🎋', '🎍', '🌾', '🌙', '⭐', '🌟', '✨', '🌠', '☀️', '🌤️', '⛅', '🌥️', '☁️', '🌦️', '🌧️', '⛈️', '🌩️'],
			food: ['🍕', '🍔', '🌭', '🥪', '🌮', '🌯', '🥙', '🧆', '🥚', '🍳', '🥘', '🍲', '🥗', '🍿', '🧈', '🧂', '🥯', '🍞', '🥐', '🥖', '🥨', '🧀', '🥞', '🧇', '🥓', '🍖', '🍗', '🌶️', '🥕', '🌽'],
			activities: ['⚽', '🏀', '🏈', '⚾', '🥎', '🎾', '🏐', '🏉', '🥏', '🎱', '🪀', '🏓', '🏸', '🏒', '🏑', '🥍', '🏏', '🪃', '🥅', '⛳', '🪁', '🏹', '🎣', '🤿', '🥊', '🥋', '🎽', '🛹', '🛷', '⛸️'],
			travel: ['✈️', '🚀', '🛸', '🚁', '🛶', '⛵', '🚤', '🛥️', '🛳️', '⚓', '🚂', '🚃', '🚄', '🚅', '🚆', '🚇', '🚈', '🚉', '🚊', '🚝', '🚞', '🚋', '🚌', '🚍', '🚎', '🚐', '🚑', '🚒', '🚓', '🚔'],
			objects: ['💎', '🔔', '🔕', '🎵', '🎶', '💰', '💴', '💵', '💶', '💷', '💸', '💳', '🧾', '💹', '💱', '💲', '📧', '📨', '📩', '📤', '📥', '📦', '📫', '📪', '📬', '📭', '📮', '🗳️', '✏️', '✒️'],
			symbols: ['❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔', '❣️', '💕', '💞', '💓', '💗', '💖', '💘', '💝', '💟', '☮️', '✝️', '☪️', '🕉️', '☸️', '✡️', '🔯', '🕎', '☯️', '☦️', '🛐']
		};

		this.init();
	}

	init() {
		this.bindEvents();
		this.loadConversations();
		this.initEmojiPicker();
		this.startPolling();
	}

	bindEvents() {
		// Message form submission
		document.getElementById('message-form').addEventListener('submit', (e) => {
			e.preventDefault();
			this.sendMessage();
		});

		// Message input events
		const messageInput = document.getElementById('message-input');
		messageInput.addEventListener('input', () => {
			this.handleInputChange();
			this.autoResize();
		});

		messageInput.addEventListener('keydown', (e) => {
			if (e.key === 'Enter' && !e.shiftKey) {
				e.preventDefault();
				this.sendMessage();
			}
		});

		// Conversation search
		document.getElementById('conversation-search').addEventListener('input', (e) => {
			this.filterConversations(e.target.value);
		});

		// Emoji picker toggle
		document.getElementById('emoji-btn').addEventListener('click', () => {
			this.toggleEmojiPicker();
		});

		// File attachment
		document.getElementById('attach-file-btn').addEventListener('click', () => {
			document.getElementById('file-upload-input').click();
		});

		document.getElementById('file-upload-input').addEventListener('change', (e) => {
			this.handleFileUpload(e.target.files);
		});

		// Chat actions
		document.getElementById('video-call-btn').addEventListener('click', () => {
			this.initiateVideoCall();
		});

		document.getElementById('voice-call-btn').addEventListener('click', () => {
			this.initiateVoiceCall();
		});

		document.getElementById('chat-options-btn').addEventListener('click', () => {
			this.toggleProfilePanel();
		});

		// Close profile panel
		document.getElementById('close-profile-panel').addEventListener('click', () => {
			this.closeProfilePanel();
		});

		// Click outside to close emoji picker
		document.addEventListener('click', (e) => {
			if (!e.target.closest('.emoji-picker') && !e.target.closest('#emoji-btn')) {
				this.closeEmojiPicker();
			}
		});
	}

	async loadConversations() {
		try {
			const response = await fetch(wpmatch_ajax.ajax_url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'wpmatch_get_conversations',
					nonce: wpmatch_ajax.nonce
				})
			});

			const data = await response.json();

			if (data.success) {
				this.renderConversations(data.data.conversations);
			} else {
				this.showError('Failed to load conversations');
			}
		} catch (error) {
			console.error('Error loading conversations:', error);
			this.showError('Network error occurred');
		}
	}

	renderConversations(conversations) {
		const conversationsList = document.getElementById('conversations-list');

		if (conversations.length === 0) {
			conversationsList.innerHTML = `
				<div class="no-conversations">
					<div style="text-align: center; padding: 40px 20px; color: #718096;">
						<div style="font-size: 48px; margin-bottom: 15px;">💬</div>
						<h4>No conversations yet</h4>
						<p>Start messaging with your matches to see conversations here.</p>
					</div>
				</div>
			`;
			return;
		}

		const conversationsHTML = conversations.map(conversation => {
			const isOnline = conversation.user_status === 'online';
			const isUnread = conversation.unread_count > 0;

			return `
				<div class="conversation-item ${isUnread ? 'unread' : ''}" data-conversation-id="${conversation.id}" data-user-id="${conversation.user_id}">
					<div class="conversation-header">
						<div class="conversation-avatar">
							<img src="${conversation.avatar || '/wp-content/plugins/wpmatch/public/images/default-avatar.png'}" alt="${conversation.display_name}">
							<div class="online-indicator ${isOnline ? 'online' : 'offline'}"></div>
						</div>
						<div class="conversation-info">
							<h4 class="conversation-name">${conversation.display_name}</h4>
							<div class="conversation-meta">
								<span class="last-message">${conversation.last_message || 'No messages yet'}</span>
								<div style="display: flex; align-items: center;">
									<span class="message-time">${this.formatTime(conversation.last_message_time)}</span>
									${isUnread ? `<span class="unread-count">${conversation.unread_count}</span>` : ''}
								</div>
							</div>
						</div>
					</div>
				</div>
			`;
		}).join('');

		conversationsList.innerHTML = conversationsHTML;

		// Bind conversation click events
		conversationsList.querySelectorAll('.conversation-item').forEach(item => {
			item.addEventListener('click', () => {
				const conversationId = item.dataset.conversationId;
				const userId = item.dataset.userId;
				this.openConversation(conversationId, userId);
			});
		});
	}

	async openConversation(conversationId, userId) {
		this.currentConversationId = conversationId;
		this.currentRecipientId = userId;

		// Update UI
		document.querySelectorAll('.conversation-item').forEach(item => {
			item.classList.remove('active');
		});

		document.querySelector(`[data-conversation-id="${conversationId}"]`).classList.add('active');

		// Show chat area
		document.getElementById('chat-welcome').style.display = 'none';
		document.getElementById('active-chat').style.display = 'flex';

		// Update form fields
		document.getElementById('conversation-id').value = conversationId;
		document.getElementById('recipient-id').value = userId;

		// Load user info and messages
		await this.loadUserInfo(userId);
		await this.loadMessages(conversationId);

		this.scrollToBottom();
	}

	async loadUserInfo(userId) {
		try {
			const response = await fetch(wpmatch_ajax.ajax_url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'wpmatch_get_user_info',
					user_id: userId,
					nonce: wpmatch_ajax.nonce
				})
			});

			const data = await response.json();

			if (data.success) {
				const user = data.data.user;
				document.getElementById('chat-user-avatar').src = user.avatar || '/wp-content/plugins/wpmatch/public/images/default-avatar.png';
				document.getElementById('chat-user-name').textContent = user.display_name;
				document.getElementById('chat-user-status').textContent = user.status || 'Last seen recently';
				document.getElementById('user-online-status').className = `online-status ${user.is_online ? 'online' : 'offline'}`;
			}
		} catch (error) {
			console.error('Error loading user info:', error);
		}
	}

	async loadMessages(conversationId) {
		try {
			const response = await fetch(wpmatch_ajax.ajax_url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'wpmatch_get_messages',
					conversation_id: conversationId,
					nonce: wpmatch_ajax.nonce
				})
			});

			const data = await response.json();

			if (data.success) {
				this.renderMessages(data.data.messages);
				if (data.data.messages.length > 0) {
					this.lastMessageId = Math.max(...data.data.messages.map(m => m.id));
				}
			}
		} catch (error) {
			console.error('Error loading messages:', error);
		}
	}

	renderMessages(messages) {
		const messagesContainer = document.getElementById('messages-scroll');

		if (messages.length === 0) {
			messagesContainer.innerHTML = `
				<div class="no-messages" style="text-align: center; padding: 40px; color: #718096;">
					<div style="font-size: 32px; margin-bottom: 15px;">👋</div>
					<p>Start the conversation with a friendly message!</p>
				</div>
			`;
			return;
		}

		const messagesHTML = messages.map((message, index) => {
			const isSent = message.sender_id == wpmatch_ajax.current_user_id;
			const showAvatar = index === 0 || messages[index - 1].sender_id !== message.sender_id;

			return `
				<div class="message ${isSent ? 'sent' : 'received'}" data-message-id="${message.id}">
					${showAvatar ? `
						<div class="message-avatar">
							<img src="${message.avatar || '/wp-content/plugins/wpmatch/public/images/default-avatar.png'}" alt="">
						</div>
					` : '<div class="message-avatar"></div>'}
					<div class="message-content">
						<div class="message-bubble">
							${this.formatMessageContent(message.content)}
						</div>
						<div class="message-time">${this.formatTime(message.sent_at)}</div>
						${isSent ? `<div class="message-status ${message.is_read ? 'read' : ''}">${message.is_read ? '✓✓' : '✓'}</div>` : ''}
					</div>
				</div>
			`;
		}).join('');

		messagesContainer.innerHTML = messagesHTML;
	}

	async sendMessage() {
		const messageInput = document.getElementById('message-input');
		const message = messageInput.value.trim();

		if (!message || !this.currentConversationId) return;

		const sendBtn = document.getElementById('send-btn');
		sendBtn.disabled = true;

		try {
			const response = await fetch(wpmatch_ajax.ajax_url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'wpmatch_send_message',
					conversation_id: this.currentConversationId,
					recipient_id: this.currentRecipientId,
					message: message,
					nonce: document.getElementById('wpmatch_message_nonce').value
				})
			});

			const data = await response.json();

			if (data.success) {
				messageInput.value = '';
				this.updateCharCount();
				this.autoResize();
				await this.loadMessages(this.currentConversationId);
				this.scrollToBottom();
				this.loadConversations(); // Refresh conversations list
			} else {
				this.showError(data.data.message || 'Failed to send message');
			}
		} catch (error) {
			console.error('Error sending message:', error);
			this.showError('Network error occurred');
		} finally {
			sendBtn.disabled = false;
		}
	}

	handleInputChange() {
		const messageInput = document.getElementById('message-input');
		const sendBtn = document.getElementById('send-btn');

		sendBtn.disabled = messageInput.value.trim() === '';
		this.updateCharCount();

		// Handle typing indicator
		if (!this.isTyping && messageInput.value.trim()) {
			this.isTyping = true;
			this.sendTypingIndicator(true);
		}

		clearTimeout(this.typingTimeout);
		this.typingTimeout = setTimeout(() => {
			if (this.isTyping) {
				this.isTyping = false;
				this.sendTypingIndicator(false);
			}
		}, 2000);
	}

	updateCharCount() {
		const messageInput = document.getElementById('message-input');
		const charCount = document.getElementById('char-count');
		charCount.textContent = messageInput.value.length;
	}

	autoResize() {
		const messageInput = document.getElementById('message-input');
		messageInput.style.height = 'auto';
		messageInput.style.height = Math.min(messageInput.scrollHeight, 120) + 'px';
	}

	async sendTypingIndicator(isTyping) {
		if (!this.currentConversationId) return;

		try {
			await fetch(wpmatch_ajax.ajax_url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'wpmatch_typing_indicator',
					conversation_id: this.currentConversationId,
					is_typing: isTyping ? 1 : 0,
					nonce: wpmatch_ajax.nonce
				})
			});
		} catch (error) {
			console.error('Error sending typing indicator:', error);
		}
	}

	filterConversations(query) {
		const conversations = document.querySelectorAll('.conversation-item');
		const searchQuery = query.toLowerCase();

		conversations.forEach(conversation => {
			const name = conversation.querySelector('.conversation-name').textContent.toLowerCase();
			const lastMessage = conversation.querySelector('.last-message').textContent.toLowerCase();

			if (name.includes(searchQuery) || lastMessage.includes(searchQuery)) {
				conversation.style.display = 'block';
			} else {
				conversation.style.display = 'none';
			}
		});
	}

	initEmojiPicker() {
		const emojiGrid = document.getElementById('emoji-grid');
		const emojiCategories = document.querySelectorAll('.emoji-category');

		// Load default category
		this.loadEmojiCategory('smileys');

		// Bind category buttons
		emojiCategories.forEach(btn => {
			btn.addEventListener('click', () => {
				emojiCategories.forEach(b => b.classList.remove('active'));
				btn.classList.add('active');
				this.loadEmojiCategory(btn.dataset.category);
			});
		});
	}

	loadEmojiCategory(category) {
		const emojiGrid = document.getElementById('emoji-grid');
		const emojis = this.emojis[category] || [];

		emojiGrid.innerHTML = emojis.map(emoji =>
			`<button class="emoji-item" data-emoji="${emoji}">${emoji}</button>`
		).join('');

		// Bind emoji click events
		emojiGrid.querySelectorAll('.emoji-item').forEach(btn => {
			btn.addEventListener('click', () => {
				this.insertEmoji(btn.dataset.emoji);
			});
		});
	}

	toggleEmojiPicker() {
		const emojiPicker = document.getElementById('emoji-picker');
		emojiPicker.style.display = emojiPicker.style.display === 'none' ? 'block' : 'none';
	}

	closeEmojiPicker() {
		document.getElementById('emoji-picker').style.display = 'none';
	}

	insertEmoji(emoji) {
		const messageInput = document.getElementById('message-input');
		const cursorPos = messageInput.selectionStart;
		const textBefore = messageInput.value.substring(0, cursorPos);
		const textAfter = messageInput.value.substring(cursorPos);

		messageInput.value = textBefore + emoji + textAfter;
		messageInput.focus();
		messageInput.setSelectionRange(cursorPos + emoji.length, cursorPos + emoji.length);

		this.handleInputChange();
		this.closeEmojiPicker();
	}

	handleFileUpload(files) {
		// Implementation for file upload
		console.log('File upload:', files);
		// TODO: Implement file upload functionality
	}

	initiateVideoCall() {
		// Implementation for video call
		console.log('Video call initiated');
		this.showNotification('Video calling feature coming soon!');
	}

	initiateVoiceCall() {
		// Implementation for voice call
		console.log('Voice call initiated');
		this.showNotification('Voice calling feature coming soon!');
	}

	toggleProfilePanel() {
		const profilePanel = document.getElementById('profile-panel');
		profilePanel.style.display = profilePanel.style.display === 'none' ? 'block' : 'none';

		if (profilePanel.style.display === 'block') {
			this.loadUserProfile(this.currentRecipientId);
		}
	}

	closeProfilePanel() {
		document.getElementById('profile-panel').style.display = 'none';
	}

	async loadUserProfile(userId) {
		// Implementation for loading full user profile
		const profileContent = document.getElementById('profile-content');
		profileContent.innerHTML = '<p>Loading profile...</p>';
		// TODO: Load and display full user profile
	}

	startPolling() {
		// Poll for new messages every 5 seconds
		setInterval(() => {
			if (this.currentConversationId) {
				this.checkForNewMessages();
			}
			this.loadConversations(); // Refresh conversations list
		}, 5000);
	}

	async checkForNewMessages() {
		try {
			const response = await fetch(wpmatch_ajax.ajax_url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'wpmatch_check_new_messages',
					conversation_id: this.currentConversationId,
					last_message_id: this.lastMessageId,
					nonce: wpmatch_ajax.nonce
				})
			});

			const data = await response.json();

			if (data.success && data.data.new_messages.length > 0) {
				this.appendNewMessages(data.data.new_messages);
				this.lastMessageId = Math.max(...data.data.new_messages.map(m => m.id));
				this.scrollToBottom();
			}
		} catch (error) {
			console.error('Error checking for new messages:', error);
		}
	}

	appendNewMessages(messages) {
		const messagesContainer = document.getElementById('messages-scroll');

		messages.forEach(message => {
			const isSent = message.sender_id == wpmatch_ajax.current_user_id;

			const messageElement = document.createElement('div');
			messageElement.className = `message ${isSent ? 'sent' : 'received'}`;
			messageElement.dataset.messageId = message.id;

			messageElement.innerHTML = `
				<div class="message-avatar">
					<img src="${message.avatar || '/wp-content/plugins/wpmatch/public/images/default-avatar.png'}" alt="">
				</div>
				<div class="message-content">
					<div class="message-bubble">
						${this.formatMessageContent(message.content)}
					</div>
					<div class="message-time">${this.formatTime(message.sent_at)}</div>
					${isSent ? `<div class="message-status ${message.is_read ? 'read' : ''}">${message.is_read ? '✓✓' : '✓'}</div>` : ''}
				</div>
			`;

			messagesContainer.appendChild(messageElement);
		});
	}

	formatMessageContent(content) {
		// Basic HTML escaping and link detection
		const escaped = content
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');

		// Convert URLs to links
		const withLinks = escaped.replace(
			/(https?:\/\/[^\s]+)/g,
			'<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>'
		);

		// Convert line breaks
		return withLinks.replace(/\n/g, '<br>');
	}

	formatTime(timestamp) {
		const date = new Date(timestamp);
		const now = new Date();
		const diff = now - date;

		// Less than 1 minute
		if (diff < 60000) {
			return 'now';
		}

		// Less than 1 hour
		if (diff < 3600000) {
			const minutes = Math.floor(diff / 60000);
			return `${minutes}m`;
		}

		// Less than 24 hours
		if (diff < 86400000) {
			const hours = Math.floor(diff / 3600000);
			return `${hours}h`;
		}

		// Less than 7 days
		if (diff < 604800000) {
			const days = Math.floor(diff / 86400000);
			return `${days}d`;
		}

		// More than 7 days
		return date.toLocaleDateString();
	}

	scrollToBottom() {
		const messagesContainer = document.getElementById('messages-scroll');
		messagesContainer.scrollTop = messagesContainer.scrollHeight;
	}

	showError(message) {
		this.showNotification(message, 'error');
	}

	showNotification(message, type = 'info') {
		// Create notification element
		const notification = document.createElement('div');
		notification.className = `notification notification-${type}`;
		notification.style.cssText = `
			position: fixed;
			top: 20px;
			right: 20px;
			background: ${type === 'error' ? '#fee' : '#eff'};
			color: ${type === 'error' ? '#c53030' : '#2d3748'};
			padding: 15px 20px;
			border-radius: 8px;
			border-left: 4px solid ${type === 'error' ? '#e53e3e' : '#667eea'};
			box-shadow: 0 4px 12px rgba(0,0,0,0.1);
			z-index: 1000;
			max-width: 300px;
			transform: translateX(100%);
			transition: transform 0.3s ease;
		`;
		notification.textContent = message;

		document.body.appendChild(notification);

		// Animate in
		setTimeout(() => {
			notification.style.transform = 'translateX(0)';
		}, 100);

		// Remove after 5 seconds
		setTimeout(() => {
			notification.style.transform = 'translateX(100%)';
			setTimeout(() => {
				if (notification.parentNode) {
					notification.parentNode.removeChild(notification);
				}
			}, 300);
		}, 5000);
	}
}

// Initialize messaging interface when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
	if (typeof wpmatch_ajax !== 'undefined') {
		new MessagingInterface();
	}
});
</script>