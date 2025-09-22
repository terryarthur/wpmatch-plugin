/**
 * WPMatch WebSocket Client
 *
 * Handles realtime communication with the WebSocket server.
 *
 * @package WPMatch
 * @since 1.7.0
 */

(function($) {
    'use strict';

    /**
     * WebSocket Client class
     */
    class WPMatchWebSocket {
        constructor(config) {
            this.config = {
                url: '',
                auth_token: '',
                user_id: 0,
                reconnect: true,
                heartbeat: 30,
                maxReconnectAttempts: 5,
                reconnectDelay: 3000,
                ...config
            };

            this.ws = null;
            this.connected = false;
            this.reconnectAttempts = 0;
            this.heartbeatInterval = null;
            this.eventHandlers = new Map();
            this.messageQueue = [];

            this.init();
        }

        /**
         * Initialize WebSocket connection
         */
        init() {
            if (!this.config.url || !this.config.auth_token) {
                console.warn('WPMatch WebSocket: Missing configuration');
                return;
            }

            this.connect();
            this.registerDefaultHandlers();
        }

        /**
         * Connect to WebSocket server
         */
        connect() {
            if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                return;
            }

            const wsUrl = `${this.config.url}?token=${encodeURIComponent(this.config.auth_token)}`;

            try {
                this.ws = new WebSocket(wsUrl);
                this.setupEventListeners();
            } catch (error) {
                console.error('WPMatch WebSocket: Connection failed', error);
                this.handleReconnect();
            }
        }

        /**
         * Setup WebSocket event listeners
         */
        setupEventListeners() {
            this.ws.onopen = (event) => {
                console.log('WPMatch WebSocket: Connected');
                this.connected = true;
                this.reconnectAttempts = 0;
                this.startHeartbeat();
                this.processMessageQueue();
                this.trigger('connected', { event });
            };

            this.ws.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    this.handleMessage(data);
                } catch (error) {
                    console.error('WPMatch WebSocket: Invalid message format', error);
                }
            };

            this.ws.onclose = (event) => {
                console.log('WPMatch WebSocket: Disconnected', event.code, event.reason);
                this.connected = false;
                this.stopHeartbeat();
                this.trigger('disconnected', { event });

                if (this.config.reconnect && event.code !== 1000) {
                    this.handleReconnect();
                }
            };

            this.ws.onerror = (error) => {
                console.error('WPMatch WebSocket: Error', error);
                this.trigger('error', { error });
            };
        }

        /**
         * Handle incoming messages
         */
        handleMessage(data) {
            const { event, data: eventData, timestamp } = data;

            // Handle system events
            switch (event) {
                case 'heartbeat_response':
                    // Server responded to heartbeat
                    break;

                default:
                    // Trigger custom event handlers
                    this.trigger(event, eventData);
                    break;
            }
        }

        /**
         * Send message to server
         */
        send(event, data = {}, to = null) {
            const message = {
                event,
                data,
                to,
                timestamp: Date.now()
            };

            if (this.connected && this.ws.readyState === WebSocket.OPEN) {
                this.ws.send(JSON.stringify(message));
            } else {
                // Queue message for later
                this.messageQueue.push(message);
            }
        }

        /**
         * Process queued messages
         */
        processMessageQueue() {
            while (this.messageQueue.length > 0) {
                const message = this.messageQueue.shift();
                this.ws.send(JSON.stringify(message));
            }
        }

        /**
         * Start heartbeat
         */
        startHeartbeat() {
            if (this.heartbeatInterval) {
                clearInterval(this.heartbeatInterval);
            }

            this.heartbeatInterval = setInterval(() => {
                if (this.connected) {
                    this.send('heartbeat');
                }
            }, this.config.heartbeat * 1000);
        }

        /**
         * Stop heartbeat
         */
        stopHeartbeat() {
            if (this.heartbeatInterval) {
                clearInterval(this.heartbeatInterval);
                this.heartbeatInterval = null;
            }
        }

        /**
         * Handle reconnection
         */
        handleReconnect() {
            if (!this.config.reconnect || this.reconnectAttempts >= this.config.maxReconnectAttempts) {
                console.log('WPMatch WebSocket: Max reconnection attempts reached');
                return;
            }

            this.reconnectAttempts++;
            const delay = this.config.reconnectDelay * this.reconnectAttempts;

            console.log(`WPMatch WebSocket: Reconnecting in ${delay}ms (attempt ${this.reconnectAttempts})`);

            setTimeout(() => {
                this.connect();
            }, delay);
        }

        /**
         * Register event handler
         */
        on(event, handler) {
            if (!this.eventHandlers.has(event)) {
                this.eventHandlers.set(event, []);
            }
            this.eventHandlers.get(event).push(handler);
        }

        /**
         * Unregister event handler
         */
        off(event, handler) {
            if (this.eventHandlers.has(event)) {
                const handlers = this.eventHandlers.get(event);
                const index = handlers.indexOf(handler);
                if (index > -1) {
                    handlers.splice(index, 1);
                }
            }
        }

        /**
         * Trigger event handlers
         */
        trigger(event, data) {
            if (this.eventHandlers.has(event)) {
                this.eventHandlers.get(event).forEach(handler => {
                    try {
                        handler(data);
                    } catch (error) {
                        console.error(`WPMatch WebSocket: Error in ${event} handler`, error);
                    }
                });
            }

            // Also trigger jQuery event for compatibility
            $(document).trigger('wpmatch_websocket_' + event, [data]);
        }

        /**
         * Register default event handlers
         */
        registerDefaultHandlers() {
            // New match notification
            this.on('new_match', (data) => {
                this.showNotification('New Match!', 'You have a new match!', 'match');
                this.updateMatchesList();
            });

            // New message notification
            this.on('new_message', (data) => {
                this.showNotification('New Message', data.message.content || 'You have a new message', 'message');
                this.updateMessagesList(data);
            });

            // User status updates
            this.on('user_online', (data) => {
                this.updateUserStatus(data.user_id, 'online');
            });

            this.on('user_offline', (data) => {
                this.updateUserStatus(data.user_id, 'offline');
            });

            // Typing indicators
            this.on('typing_start', (data) => {
                this.showTypingIndicator(data.user_id, true);
            });

            this.on('typing_stop', (data) => {
                this.showTypingIndicator(data.user_id, false);
            });

            // Like notifications
            this.on('new_like', (data) => {
                this.showNotification('New Like!', 'Someone liked your profile!', 'like');
            });

            this.on('new_super_like', (data) => {
                this.showNotification('Super Like!', 'Someone super liked your profile!', 'super_like');
            });

            // Video call events
            this.on('video_call_request', (data) => {
                this.handleVideoCallRequest(data);
            });

            // Achievement notifications
            this.on('achievement_unlocked', (data) => {
                this.showAchievementNotification(data.achievement);
            });

            // Event updates
            this.on('event_update', (data) => {
                this.updateEventInfo(data.event_id, data.event_data);
            });
        }

        /**
         * Show notification
         */
        showNotification(title, message, type = 'info') {
            // Check if browser notifications are supported and allowed
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification(title, {
                    body: message,
                    icon: wpmatch_websocket.notification_icon || '/wp-content/plugins/wpmatch/assets/icon.png'
                });
            }

            // Show in-app notification
            this.showInAppNotification(title, message, type);
        }

        /**
         * Show in-app notification
         */
        showInAppNotification(title, message, type) {
            const notification = $(`
                <div class="wpmatch-notification wpmatch-notification-${type}">
                    <div class="notification-content">
                        <h4>${title}</h4>
                        <p>${message}</p>
                    </div>
                    <button class="notification-close">&times;</button>
                </div>
            `);

            // Add to page
            $('body').append(notification);

            // Auto-hide after 5 seconds
            setTimeout(() => {
                notification.fadeOut(() => notification.remove());
            }, 5000);

            // Manual close
            notification.find('.notification-close').on('click', () => {
                notification.fadeOut(() => notification.remove());
            });
        }

        /**
         * Update matches list
         */
        updateMatchesList() {
            // Reload matches section if present
            const matchesContainer = $('.wpmatch-matches');
            if (matchesContainer.length > 0) {
                // Trigger refresh of matches
                $(document).trigger('wpmatch_refresh_matches');
            }
        }

        /**
         * Update messages list
         */
        updateMessagesList(data) {
            // Update conversation if it's currently open
            const conversationContainer = $(`.conversation[data-user-id="${data.sender_id}"]`);
            if (conversationContainer.length > 0) {
                // Add new message to conversation
                this.appendMessageToConversation(data.sender_id, data.message);
            }

            // Update conversations list
            $(document).trigger('wpmatch_refresh_conversations');
        }

        /**
         * Update user status
         */
        updateUserStatus(userId, status) {
            $(`.user-status[data-user-id="${userId}"]`).removeClass('online offline').addClass(status);
            $(`.user-card[data-user-id="${userId}"] .status-indicator`).removeClass('online offline').addClass(status);
        }

        /**
         * Show typing indicator
         */
        showTypingIndicator(userId, isTyping) {
            const indicator = $(`.typing-indicator[data-user-id="${userId}"]`);

            if (isTyping) {
                if (indicator.length === 0) {
                    const newIndicator = $(`
                        <div class="typing-indicator" data-user-id="${userId}">
                            <span>Typing...</span>
                            <div class="typing-dots">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                        </div>
                    `);
                    $(`.conversation[data-user-id="${userId}"] .messages`).append(newIndicator);
                }
            } else {
                indicator.remove();
            }
        }

        /**
         * Handle video call request
         */
        handleVideoCallRequest(data) {
            const modal = $(`
                <div class="wpmatch-modal video-call-modal">
                    <div class="modal-content">
                        <h3>Incoming Video Call</h3>
                        <p>User ${data.caller_id} wants to video chat with you.</p>
                        <div class="modal-actions">
                            <button class="btn btn-primary accept-call" data-call-id="${data.call_id}">Accept</button>
                            <button class="btn btn-secondary decline-call" data-call-id="${data.call_id}">Decline</button>
                        </div>
                    </div>
                </div>
            `);

            $('body').append(modal);

            // Handle accept
            modal.find('.accept-call').on('click', () => {
                this.send('video_call_accepted', { call_id: data.call_id }, [data.caller_id]);
                modal.remove();
                // Start video call interface
                this.startVideoCall(data.call_id);
            });

            // Handle decline
            modal.find('.decline-call').on('click', () => {
                this.send('video_call_rejected', { call_id: data.call_id }, [data.caller_id]);
                modal.remove();
            });

            // Auto-decline after 30 seconds
            setTimeout(() => {
                if (modal.is(':visible')) {
                    modal.find('.decline-call').click();
                }
            }, 30000);
        }

        /**
         * Show achievement notification
         */
        showAchievementNotification(achievement) {
            const notification = $(`
                <div class="wpmatch-achievement-notification">
                    <div class="achievement-content">
                        <img src="${achievement.icon}" alt="${achievement.name}">
                        <div class="achievement-text">
                            <h4>Achievement Unlocked!</h4>
                            <h5>${achievement.name}</h5>
                            <p>${achievement.description}</p>
                        </div>
                    </div>
                </div>
            `);

            $('body').append(notification);

            // Animate in
            notification.addClass('show');

            // Auto-hide after 8 seconds
            setTimeout(() => {
                notification.removeClass('show');
                setTimeout(() => notification.remove(), 300);
            }, 8000);
        }

        /**
         * Start video call
         */
        startVideoCall(callId) {
            // This would integrate with WebRTC for actual video calling
            console.log('Starting video call:', callId);
            $(document).trigger('wpmatch_start_video_call', [callId]);
        }

        /**
         * Append message to conversation
         */
        appendMessageToConversation(userId, message) {
            const conversation = $(`.conversation[data-user-id="${userId}"] .messages`);
            if (conversation.length > 0) {
                const messageHtml = $(`
                    <div class="message message-received">
                        <div class="message-content">${message.content}</div>
                        <div class="message-time">${new Date().toLocaleTimeString()}</div>
                    </div>
                `);
                conversation.append(messageHtml);
                conversation.scrollTop(conversation[0].scrollHeight);
            }
        }

        /**
         * Update event info
         */
        updateEventInfo(eventId, eventData) {
            const eventCard = $(`.event-card[data-event-id="${eventId}"]`);
            if (eventCard.length > 0) {
                // Update event information
                if (eventData.title) {
                    eventCard.find('.event-title').text(eventData.title);
                }
                if (eventData.participants) {
                    eventCard.find('.participants-count').text(eventData.participants);
                }

                // Show update indicator
                eventCard.addClass('updated');
                setTimeout(() => eventCard.removeClass('updated'), 2000);
            }
        }

        /**
         * Disconnect WebSocket
         */
        disconnect() {
            this.config.reconnect = false;
            this.stopHeartbeat();

            if (this.ws) {
                this.ws.close(1000, 'Client disconnect');
            }
        }

        /**
         * Get connection status
         */
        isConnected() {
            return this.connected && this.ws && this.ws.readyState === WebSocket.OPEN;
        }
    }

    // Initialize WebSocket when config is available
    $(document).ready(() => {
        if (window.wpmatch_websocket && window.wpmatch_websocket.enabled) {
            window.WPMatchWS = new WPMatchWebSocket(window.wpmatch_websocket);

            // Request notification permission
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }

            // Global methods for external use
            window.wpmatch = window.wpmatch || {};
            window.wpmatch.websocket = {
                send: (event, data, to) => window.WPMatchWS.send(event, data, to),
                on: (event, handler) => window.WPMatchWS.on(event, handler),
                off: (event, handler) => window.WPMatchWS.off(event, handler),
                isConnected: () => window.WPMatchWS.isConnected()
            };
        }
    });

    // Typing indicators for message input
    $(document).on('input', '.message-input', function() {
        const userId = $(this).closest('.conversation').data('user-id');

        if (window.wpmatch && window.wpmatch.websocket) {
            // Start typing
            window.wpmatch.websocket.send('typing_start', {}, [userId]);

            // Clear existing timeout
            if (this.typingTimeout) {
                clearTimeout(this.typingTimeout);
            }

            // Stop typing after 3 seconds of inactivity
            this.typingTimeout = setTimeout(() => {
                window.wpmatch.websocket.send('typing_stop', {}, [userId]);
            }, 3000);
        }
    });

    // Stop typing when input loses focus
    $(document).on('blur', '.message-input', function() {
        const userId = $(this).closest('.conversation').data('user-id');

        if (window.wpmatch && window.wpmatch.websocket) {
            window.wpmatch.websocket.send('typing_stop', {}, [userId]);
        }

        if (this.typingTimeout) {
            clearTimeout(this.typingTimeout);
        }
    });

})(jQuery);