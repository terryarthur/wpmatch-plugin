/**
 * WPMatch AI Chatbot JavaScript
 *
 * @package WPMatch
 * @subpackage AI
 * @since 1.0.0
 */

(function($) {
	'use strict';

	/**
	 * AI Chatbot functionality
	 */
	const WPMatchAIChatbot = {

		/**
		 * Initialize AI chatbot
		 */
		init: function() {
			this.bindEvents();
			this.initializeChatbot();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			$(document).on('click', '.wpmatch-ai-chat-toggle', this.toggleChatbot);
			$(document).on('click', '.wpmatch-ai-chat-send', this.sendMessage);
			$(document).on('keypress', '.wpmatch-ai-chat-input', this.handleKeyPress);
			$(document).on('click', '.wpmatch-ai-suggestion', this.selectSuggestion);
		},

		/**
		 * Initialize chatbot interface
		 */
		initializeChatbot: function() {
			if ($('#wpmatch-ai-chatbot').length === 0) {
				return;
			}

			// Check if messages container exists
			if ($('.wpmatch-ai-chat-messages').length === 0) {
				console.log('AI Chatbot: Messages container not found, skipping initialization');
				return;
			}

			// Add welcome message
			this.addMessage('Hello! I\'m here to help you with dating advice and suggestions. How can I assist you today?', 'bot');
			this.loadConversationStarters();
		},

		/**
		 * Toggle chatbot visibility
		 */
		toggleChatbot: function(e) {
			e.preventDefault();
			$('#wpmatch-ai-chatbot').toggleClass('open');
		},

		/**
		 * Handle enter key press in input
		 */
		handleKeyPress: function(e) {
			if (e.which === 13) {
				e.preventDefault();
				WPMatchAIChatbot.sendMessage();
			}
		},

		/**
		 * Send message to AI
		 */
		sendMessage: function() {
			const input = $('.wpmatch-ai-chat-input');
			const message = input.val().trim();

			if (!message) {
				return;
			}

			// Add user message to chat
			WPMatchAIChatbot.addMessage(message, 'user');
			input.val('');

			// Show typing indicator
			WPMatchAIChatbot.showTyping();

			// Send to backend
			$.ajax({
				url: wpmatch_ajax.ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmatch_ai_chat',
					message: message,
					nonce: wpmatch_ajax.nonce
				},
				success: function(response) {
					WPMatchAIChatbot.hideTyping();

					if (response.success) {
						WPMatchAIChatbot.addMessage(response.data.message, 'bot');

						// Add suggestions if provided
						if (response.data.suggestions) {
							WPMatchAIChatbot.addSuggestions(response.data.suggestions);
						}
					} else {
						WPMatchAIChatbot.addMessage('Sorry, I\'m having trouble understanding. Please try again.', 'bot');
					}
				},
				error: function() {
					WPMatchAIChatbot.hideTyping();
					WPMatchAIChatbot.addMessage('I\'m currently unavailable. Please try again later.', 'bot');
				}
			});
		},

		/**
		 * Add message to chat interface
		 */
		addMessage: function(message, sender) {
			const chatMessages = $('.wpmatch-ai-chat-messages');
			const messageHtml = `
				<div class="wpmatch-ai-message wpmatch-ai-message-${sender}">
					<div class="wpmatch-ai-message-content">${message}</div>
					<div class="wpmatch-ai-message-time">${new Date().toLocaleTimeString()}</div>
				</div>
			`;

			chatMessages.append(messageHtml);
			chatMessages.scrollTop(chatMessages[0].scrollHeight);
		},

		/**
		 * Show typing indicator
		 */
		showTyping: function() {
			const chatMessages = $('.wpmatch-ai-chat-messages');
			chatMessages.append(`
				<div class="wpmatch-ai-typing">
					<div class="wpmatch-ai-typing-dots">
						<span></span><span></span><span></span>
					</div>
				</div>
			`);
			chatMessages.scrollTop(chatMessages[0].scrollHeight);
		},

		/**
		 * Hide typing indicator
		 */
		hideTyping: function() {
			$('.wpmatch-ai-typing').remove();
		},

		/**
		 * Add suggestion buttons
		 */
		addSuggestions: function(suggestions) {
			const chatMessages = $('.wpmatch-ai-chat-messages');
			let suggestionsHtml = '<div class="wpmatch-ai-suggestions">';

			suggestions.forEach(function(suggestion) {
				suggestionsHtml += `<button class="wpmatch-ai-suggestion" data-text="${suggestion}">${suggestion}</button>`;
			});

			suggestionsHtml += '</div>';
			chatMessages.append(suggestionsHtml);
			chatMessages.scrollTop(chatMessages[0].scrollHeight);
		},

		/**
		 * Select a suggestion
		 */
		selectSuggestion: function(e) {
			e.preventDefault();
			const text = $(this).data('text');
			$('.wpmatch-ai-chat-input').val(text);
			$('.wpmatch-ai-suggestions').remove();
		},

		/**
		 * Load conversation starters
		 */
		loadConversationStarters: function() {
			const starters = [
				'Help me write a better bio',
				'What should I say in my first message?',
				'How do I start a conversation?',
				'Give me dating advice'
			];

			this.addSuggestions(starters);
		}
	};

	// Initialize when document is ready
	$(document).ready(function() {
		WPMatchAIChatbot.init();
	});

	// Expose to global scope
	window.WPMatchAIChatbot = WPMatchAIChatbot;

})(jQuery);