/**
 * WPMatch Real-time Messaging Interface
 *
 * Handles real-time messaging functionality on the frontend including
 * WebSocket connections, message display, typing indicators, and notifications.
 *
 * @package WPMatch
 * @since 1.2.0
 */

class WPMatchMessaging {
    constructor(options = {}) {
        this.options = {
            apiUrl: wpMatch.apiUrl || '/wp-json/wpmatch/v1',
            nonce: wpMatch.nonce || '',
            currentUserId: wpMatch.currentUserId || 0,
            pollInterval: 3000,
            typingTimeout: 3000,
            ...options
        };

        this.conversations = new Map();
        this.currentConversationId = null;
        this.typingTimer = null;
        this.isTyping = false;
        this.lastMessageId = 0;
        this.pollTimer = null;

        this.init();
    }

    /**
     * Initialize messaging interface.
     */
    init() {
        this.bindEvents();
        this.startPolling();
        this.loadConversations();
    }

    /**
     * Bind DOM events.
     */
    bindEvents() {
        // Send message button.
        document.addEventListener('click', (e) => {
            if (e.target.matches('.wpmatch-send-message')) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // Enter key to send message.
        document.addEventListener('keypress', (e) => {
            if (e.target.matches('.wpmatch-message-input') && e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // Typing indicator.
        document.addEventListener('input', (e) => {
            if (e.target.matches('.wpmatch-message-input')) {
                this.handleTyping();
            }
        });

        // Conversation selection.
        document.addEventListener('click', (e) => {
            if (e.target.matches('.wpmatch-conversation-item')) {
                e.preventDefault();
                const conversationId = e.target.dataset.conversationId;
                this.loadConversation(conversationId);
            }
        });

        // Mark messages as read when conversation is viewed.
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && this.currentConversationId) {
                this.markMessagesRead(this.currentConversationId);
            }
        });
    }

    /**
     * Start polling for new messages.
     */
    startPolling() {
        this.pollTimer = setInterval(() => {
            this.checkForNewMessages();
        }, this.options.pollInterval);
    }

    /**
     * Stop polling.
     */
    stopPolling() {
        if (this.pollTimer) {
            clearInterval(this.pollTimer);
            this.pollTimer = null;
        }
    }

    /**
     * Send a message.
     */
    async sendMessage() {
        const input = document.querySelector('.wpmatch-message-input');
        const recipientId = document.querySelector('[data-recipient-id]')?.dataset.recipientId;

        if (!input || !recipientId) {
            console.error('Missing message input or recipient ID');
            return;
        }

        const content = input.value.trim();
        if (!content) {
            return;
        }

        // Clear input immediately for better UX.
        input.value = '';

        // Add optimistic message to UI.
        this.addMessageToUI({
            sender_id: this.options.currentUserId,
            content: content,
            sent_at: new Date().toISOString(),
            sending: true
        });

        try {
            const response = await fetch(`${this.options.apiUrl}/messages`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.options.nonce
                },
                body: JSON.stringify({
                    recipient_id: parseInt(recipientId),
                    content: content,
                    message_type: 'text'
                })
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Failed to send message');
            }

            // Remove optimistic message and add real one.
            this.updateOptimisticMessage(result.message_id);

        } catch (error) {
            console.error('Error sending message:', error);
            this.showError('Failed to send message. Please try again.');

            // Remove failed optimistic message.
            this.removeOptimisticMessage();
        }
    }

    /**
     * Load conversations list.
     */
    async loadConversations() {
        try {
            const response = await fetch(`${this.options.apiUrl}/conversations`, {
                headers: {
                    'X-WP-Nonce': this.options.nonce
                }
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Failed to load conversations');
            }

            this.renderConversations(result.conversations);

        } catch (error) {
            console.error('Error loading conversations:', error);
            this.showError('Failed to load conversations.');
        }
    }

    /**
     * Load specific conversation messages.
     */
    async loadConversation(conversationId) {
        this.currentConversationId = conversationId;

        try {
            const response = await fetch(`${this.options.apiUrl}/messages?conversation_id=${conversationId}`, {
                headers: {
                    'X-WP-Nonce': this.options.nonce
                }
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Failed to load conversation');
            }

            this.renderMessages(result.messages);
            this.markMessagesRead(conversationId);

        } catch (error) {
            console.error('Error loading conversation:', error);
            this.showError('Failed to load conversation.');
        }
    }

    /**
     * Check for new messages via polling.
     */
    async checkForNewMessages() {
        if (!this.currentConversationId) {
            return;
        }

        try {
            const response = await fetch(`${this.options.apiUrl}/messages?conversation_id=${this.currentConversationId}&since=${this.lastMessageId}`, {
                headers: {
                    'X-WP-Nonce': this.options.nonce
                }
            });

            const result = await response.json();

            if (response.ok && result.messages && result.messages.length > 0) {
                result.messages.forEach(message => {
                    this.addMessageToUI(message);
                });

                this.lastMessageId = Math.max(...result.messages.map(m => m.id));
                this.markMessagesRead(this.currentConversationId);
            }

        } catch (error) {
            console.error('Error checking for new messages:', error);
        }
    }

    /**
     * Handle typing indicator.
     */
    handleTyping() {
        if (!this.currentConversationId) {
            return;
        }

        // Clear existing timer.
        if (this.typingTimer) {
            clearTimeout(this.typingTimer);
        }

        // Send typing start if not already typing.
        if (!this.isTyping) {
            this.isTyping = true;
            this.sendTypingIndicator(true);
        }

        // Set timer to stop typing indicator.
        this.typingTimer = setTimeout(() => {
            this.isTyping = false;
            this.sendTypingIndicator(false);
        }, this.options.typingTimeout);
    }

    /**
     * Send typing indicator status.
     */
    async sendTypingIndicator(isTyping) {
        if (!this.currentConversationId) {
            return;
        }

        try {
            await fetch(`${this.options.apiUrl}/typing`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.options.nonce
                },
                body: JSON.stringify({
                    conversation_id: this.currentConversationId,
                    is_typing: isTyping
                })
            });
        } catch (error) {
            console.error('Error sending typing indicator:', error);
        }
    }

    /**
     * Mark messages as read.
     */
    async markMessagesRead(conversationId) {
        try {
            await fetch(`${this.options.apiUrl}/messages/read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.options.nonce
                },
                body: JSON.stringify({
                    conversation_id: conversationId
                })
            });
        } catch (error) {
            console.error('Error marking messages as read:', error);
        }
    }

    /**
     * Render conversations list.
     */
    renderConversations(conversations) {
        const container = document.querySelector('.wpmatch-conversations-list');
        if (!container) {
            return;
        }

        const html = conversations.map(conversation => `
            <div class="wpmatch-conversation-item" data-conversation-id="${conversation.id}">
                <div class="conversation-avatar">
                    <img src="${this.getAvatarUrl(conversation.other_user_id)}" alt="${conversation.other_user_name}">
                    ${conversation.unread_count > 0 ? `<span class="unread-badge">${conversation.unread_count}</span>` : ''}
                </div>
                <div class="conversation-details">
                    <div class="conversation-name">${conversation.other_user_name}</div>
                    <div class="conversation-preview">${conversation.last_message || 'No messages yet'}</div>
                    <div class="conversation-time">${this.formatTime(conversation.last_message_time)}</div>
                </div>
            </div>
        `).join('');

        container.innerHTML = html;
    }

    /**
     * Render messages in conversation.
     */
    renderMessages(messages) {
        const container = document.querySelector('.wpmatch-messages-container');
        if (!container) {
            return;
        }

        const html = messages.reverse().map(message => this.renderMessage(message)).join('');
        container.innerHTML = html;

        // Scroll to bottom.
        container.scrollTop = container.scrollHeight;
    }

    /**
     * Add single message to UI.
     */
    addMessageToUI(message) {
        const container = document.querySelector('.wpmatch-messages-container');
        if (!container) {
            return;
        }

        const messageElement = document.createElement('div');
        messageElement.innerHTML = this.renderMessage(message);
        container.appendChild(messageElement.firstElementChild);

        // Scroll to bottom.
        container.scrollTop = container.scrollHeight;

        // Play notification sound for incoming messages.
        if (message.sender_id !== this.options.currentUserId) {
            this.playNotificationSound();
        }
    }

    /**
     * Render single message.
     */
    renderMessage(message) {
        const isOwn = message.sender_id == this.options.currentUserId;
        const statusClass = message.sending ? 'sending' : (message.read_at ? 'read' : 'delivered');

        return `
            <div class="wpmatch-message ${isOwn ? 'own-message' : 'other-message'}" data-message-id="${message.id || 'temp'}">
                <div class="message-content">
                    <div class="message-text">${this.escapeHtml(message.content)}</div>
                    <div class="message-meta">
                        <span class="message-time">${this.formatTime(message.sent_at)}</span>
                        ${isOwn ? `<span class="message-status ${statusClass}"></span>` : ''}
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Update optimistic message with real data.
     */
    updateOptimisticMessage(messageId) {
        const tempMessage = document.querySelector('[data-message-id="temp"]');
        if (tempMessage) {
            tempMessage.dataset.messageId = messageId;
            tempMessage.querySelector('.message-status').classList.remove('sending');
            tempMessage.querySelector('.message-status').classList.add('delivered');
        }
    }

    /**
     * Remove failed optimistic message.
     */
    removeOptimisticMessage() {
        const tempMessage = document.querySelector('[data-message-id="temp"]');
        if (tempMessage) {
            tempMessage.remove();
        }
    }

    /**
     * Show error message.
     */
    showError(message) {
        const errorContainer = document.querySelector('.wpmatch-error-messages');
        if (errorContainer) {
            errorContainer.innerHTML = `<div class="error-message">${message}</div>`;
            setTimeout(() => {
                errorContainer.innerHTML = '';
            }, 5000);
        }
    }

    /**
     * Play notification sound.
     */
    playNotificationSound() {
        // Only play if user has enabled sound notifications.
        const soundEnabled = localStorage.getItem('wpmatch_sound_notifications') !== 'false';
        if (!soundEnabled) {
            return;
        }

        try {
            const audio = new Audio(wpMatch.notificationSound || '/wp-content/plugins/wpmatch/public/sounds/notification.mp3');
            audio.volume = 0.3;
            audio.play().catch(e => {
                console.log('Could not play notification sound:', e);
            });
        } catch (error) {
            console.log('Audio not supported:', error);
        }
    }

    /**
     * Utility methods.
     */

    /**
     * Get avatar URL for user.
     */
    getAvatarUrl(userId) {
        return `https://www.gravatar.com/avatar/${userId}?s=48&d=mp`;
    }

    /**
     * Format timestamp for display.
     */
    formatTime(timestamp) {
        if (!timestamp) {
            return '';
        }

        const date = new Date(timestamp);
        const now = new Date();
        const diffInHours = (now - date) / (1000 * 60 * 60);

        if (diffInHours < 24) {
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } else if (diffInHours < 24 * 7) {
            return date.toLocaleDateString([], { weekday: 'short' });
        } else {
            return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
        }
    }

    /**
     * Escape HTML to prevent XSS.
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Destroy messaging interface.
     */
    destroy() {
        this.stopPolling();

        if (this.typingTimer) {
            clearTimeout(this.typingTimer);
        }
    }
}

// Auto-initialize when DOM is ready.
document.addEventListener('DOMContentLoaded', function() {
    if (typeof wpMatch !== 'undefined' && document.querySelector('.wpmatch-messaging-interface')) {
        window.wpMatchMessaging = new WPMatchMessaging();
    }
});

// Export for manual initialization.
window.WPMatchMessaging = WPMatchMessaging;