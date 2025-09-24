jQuery(document).ready(function($) {
    'use strict';

    var currentConversationId = null;
    var messagePollingInterval = null;
    var typingTimeout = null;
    var isTyping = false;

    // Initialize messaging interface
    function initMessagingInterface() {
        bindEvents();
        loadConversations();

        // Start polling for new messages
        startMessagePolling();
    }

    // Bind event listeners
    function bindEvents() {
        // Conversation selection
        $(document).on('click', '.wpmatch-conversation-item', function() {
            var conversationId = $(this).data('conversation-id');
            selectConversation(conversationId);
        });

        // Search conversations
        $('.wpmatch-search-input').on('input', function() {
            var searchTerm = $(this).val().toLowerCase();
            filterConversations(searchTerm);
        });

        // Send message
        $('.wpmatch-send-button').on('click', sendMessage);
        $('.wpmatch-message-input').on('keypress', function(e) {
            if (e.which === 13 && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        // Typing indicator
        $('.wpmatch-message-input').on('input', function() {
            handleTyping();
        });

        // Auto-resize message input
        $('.wpmatch-message-input').on('input', function() {
            autoResizeTextarea(this);
        });

        // Attachment button
        $('.wpmatch-attachment-button').on('click', function() {
            // Placeholder for file attachment functionality
            alert('File attachment feature coming soon!');
        });
    }

    // Load conversations list
    function loadConversations() {
        $.ajax({
            url: wpmatchMessaging.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpmatch_get_conversations',
                nonce: wpmatchMessaging.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderConversations(response.data);

                    // Auto-select first conversation if none selected
                    if (!currentConversationId && response.data.length > 0) {
                        selectConversation(response.data[0].id);
                    }
                } else {
                    showEmptyConversations();
                }
            },
            error: function() {
                showError('Failed to load conversations');
            }
        });
    }

    // Render conversations in sidebar
    function renderConversations(conversations) {
        var $list = $('.wpmatch-conversations-list');
        $list.empty();

        if (conversations.length === 0) {
            showEmptyConversations();
            return;
        }

        conversations.forEach(function(conversation) {
            var itemHtml = createConversationItem(conversation);
            $list.append(itemHtml);
        });
    }

    // Create conversation list item
    function createConversationItem(conversation) {
        var timeAgo = formatTimeAgo(conversation.last_message_time);
        var unreadCount = conversation.unread_count || 0;
        var unreadBadge = unreadCount > 0 ? '<span class="wpmatch-unread-count">' + unreadCount + '</span>' : '';
        var onlineIndicator = conversation.user.online ? '<div class="wpmatch-online-indicator"></div>' : '';

        var itemHtml = '<div class="wpmatch-conversation-item" data-conversation-id="' + conversation.id + '">' +
            '<div class="wpmatch-conversation-photo" style="background-image: url(' + (conversation.user.photo || wpmatchMessaging.defaultPhoto) + ');">' +
                onlineIndicator +
            '</div>' +
            '<div class="wpmatch-conversation-info">' +
                '<div class="wpmatch-conversation-header">' +
                    '<div class="wpmatch-conversation-name">' + conversation.user.name + '</div>' +
                    '<div class="wpmatch-message-time">' + timeAgo + '</div>' +
                '</div>' +
                '<div class="wpmatch-last-message">' + (conversation.last_message || 'No messages yet') + '</div>' +
            '</div>' +
            unreadBadge +
        '</div>';

        return $(itemHtml);
    }

    // Select and load conversation
    function selectConversation(conversationId) {
        currentConversationId = conversationId;

        // Update UI
        $('.wpmatch-conversation-item').removeClass('active');
        $('.wpmatch-conversation-item[data-conversation-id="' + conversationId + '"]').addClass('active');

        // Load messages
        loadMessages(conversationId);

        // Mark as read
        markConversationAsRead(conversationId);
    }

    // Load messages for conversation
    function loadMessages(conversationId) {
        showChatLoading();

        $.ajax({
            url: wpmatchMessaging.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpmatch_get_messages',
                nonce: wpmatchMessaging.nonce,
                conversation_id: conversationId
            },
            success: function(response) {
                if (response.success) {
                    renderMessages(response.data.messages, response.data.user);
                } else {
                    showChatError('Failed to load messages');
                }
            },
            error: function() {
                showChatError('Network error occurred');
            }
        });
    }

    // Render messages in chat area
    function renderMessages(messages, userInfo) {
        // Update chat header
        updateChatHeader(userInfo);

        // Render messages
        var $messagesContainer = $('.wpmatch-chat-messages');
        $messagesContainer.empty();

        if (messages.length === 0) {
            $messagesContainer.html('<div class="wpmatch-empty-chat"><h3>Start the conversation!</h3><p>Say hello to ' + userInfo.name + '</p></div>');
            return;
        }

        messages.forEach(function(message) {
            var messageHtml = createMessageElement(message);
            $messagesContainer.append(messageHtml);
        });

        // Scroll to bottom
        scrollToBottom();
    }

    // Update chat header with user info
    function updateChatHeader(userInfo) {
        $('.wpmatch-chat-user-photo').css('background-image', 'url(' + (userInfo.photo || wpmatchMessaging.defaultPhoto) + ')');
        $('.wpmatch-chat-user-info h4').text(userInfo.name);
        $('.wpmatch-chat-user-status').text(userInfo.online ? 'Online now' : 'Last seen ' + formatTimeAgo(userInfo.last_seen));
    }

    // Create message element
    function createMessageElement(message) {
        var isSent = message.sender_id == wpmatchMessaging.currentUserId;
        var messageClass = isSent ? 'sent' : 'received';
        var timeFormatted = formatMessageTime(message.created_at);
        var readStatus = isSent && message.read_at ? 'Read' : (isSent ? 'Delivered' : '');

        var messageHtml = '<div class="wpmatch-message ' + messageClass + '">' +
            '<div class="wpmatch-message-content">' +
                '<div class="wpmatch-message-text">' + escapeHtml(message.content) + '</div>' +
                '<div class="wpmatch-message-meta">' +
                    '<span class="wpmatch-message-time">' + timeFormatted + '</span>' +
                    (readStatus ? '<span class="wpmatch-read-status">' + readStatus + '</span>' : '') +
                '</div>' +
            '</div>' +
        '</div>';

        return $(messageHtml);
    }

    // Send message
    function sendMessage() {
        var $input = $('.wpmatch-message-input');
        var message = $input.val().trim();

        if (!message || !currentConversationId) return;

        var $sendBtn = $('.wpmatch-send-button');
        $sendBtn.prop('disabled', true);

        $.ajax({
            url: wpmatchMessaging.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpmatch_send_message',
                nonce: wpmatchMessaging.nonce,
                conversation_id: currentConversationId,
                message: message
            },
            success: function(response) {
                if (response.success) {
                    // Clear input
                    $input.val('').trigger('input');

                    // Add message to chat
                    var messageHtml = createMessageElement(response.data);
                    $('.wpmatch-chat-messages').append(messageHtml);
                    scrollToBottom();

                    // Update conversation list
                    updateConversationPreview(currentConversationId, message);
                } else {
                    alert('Failed to send message: ' + (response.data || 'Unknown error'));
                }
            },
            error: function() {
                alert('Network error. Please try again.');
            },
            complete: function() {
                $sendBtn.prop('disabled', false);
            }
        });
    }

    // Handle typing indicator
    function handleTyping() {
        if (!isTyping) {
            isTyping = true;
            sendTypingIndicator(true);
        }

        clearTimeout(typingTimeout);
        typingTimeout = setTimeout(function() {
            isTyping = false;
            sendTypingIndicator(false);
        }, 2000);
    }

    // Send typing indicator to server
    function sendTypingIndicator(typing) {
        if (!currentConversationId) return;

        $.ajax({
            url: wpmatchMessaging.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpmatch_typing_indicator',
                nonce: wpmatchMessaging.nonce,
                conversation_id: currentConversationId,
                typing: typing
            }
        });
    }

    // Filter conversations by search term
    function filterConversations(searchTerm) {
        $('.wpmatch-conversation-item').each(function() {
            var name = $(this).find('.wpmatch-conversation-name').text().toLowerCase();
            var lastMessage = $(this).find('.wpmatch-last-message').text().toLowerCase();

            if (name.includes(searchTerm) || lastMessage.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    }

    // Mark conversation as read
    function markConversationAsRead(conversationId) {
        $.ajax({
            url: wpmatchMessaging.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpmatch_mark_read',
                nonce: wpmatchMessaging.nonce,
                conversation_id: conversationId
            },
            success: function() {
                // Remove unread badge
                $('.wpmatch-conversation-item[data-conversation-id="' + conversationId + '"] .wpmatch-unread-count').remove();
            }
        });
    }

    // Update conversation preview
    function updateConversationPreview(conversationId, lastMessage) {
        var $conversation = $('.wpmatch-conversation-item[data-conversation-id="' + conversationId + '"]');
        $conversation.find('.wpmatch-last-message').text(lastMessage);
        $conversation.find('.wpmatch-message-time').text('Just now');

        // Move to top of list
        $conversation.prependTo('.wpmatch-conversations-list');
    }

    // Start polling for new messages
    function startMessagePolling() {
        messagePollingInterval = setInterval(function() {
            if (currentConversationId) {
                checkForNewMessages();
            }
            refreshConversationsList();
        }, 5000); // Poll every 5 seconds
    }

    // Check for new messages in current conversation
    function checkForNewMessages() {
        var lastMessageTime = $('.wpmatch-message:last .wpmatch-message-time').attr('data-timestamp') || 0;

        $.ajax({
            url: wpmatchMessaging.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpmatch_check_new_messages',
                nonce: wpmatchMessaging.nonce,
                conversation_id: currentConversationId,
                since: lastMessageTime
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    response.data.forEach(function(message) {
                        var messageHtml = createMessageElement(message);
                        $('.wpmatch-chat-messages').append(messageHtml);
                    });
                    scrollToBottom();
                }
            }
        });
    }

    // Refresh conversations list
    function refreshConversationsList() {
        $.ajax({
            url: wpmatchMessaging.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpmatch_get_conversations',
                nonce: wpmatchMessaging.nonce
            },
            success: function(response) {
                if (response.success) {
                    var selectedId = currentConversationId;
                    renderConversations(response.data);

                    if (selectedId) {
                        $('.wpmatch-conversation-item[data-conversation-id="' + selectedId + '"]').addClass('active');
                    }
                }
            }
        });
    }

    // Utility functions
    function autoResizeTextarea(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
    }

    function scrollToBottom() {
        var $messages = $('.wpmatch-chat-messages');
        $messages.scrollTop($messages[0].scrollHeight);
    }

    function formatTimeAgo(timestamp) {
        if (!timestamp) return '';

        var now = new Date();
        var time = new Date(timestamp);
        var diffMs = now - time;
        var diffMins = Math.floor(diffMs / (1000 * 60));
        var diffHours = Math.floor(diffMins / 60);
        var diffDays = Math.floor(diffHours / 24);

        if (diffDays > 0) {
            return diffDays + 'd';
        } else if (diffHours > 0) {
            return diffHours + 'h';
        } else if (diffMins > 0) {
            return diffMins + 'm';
        } else {
            return 'now';
        }
    }

    function formatMessageTime(timestamp) {
        var date = new Date(timestamp);
        return date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showEmptyConversations() {
        $('.wpmatch-conversations-list').html(
            '<div style="text-align: center; padding: 40px 20px; color: #666;">' +
                '<h4>No conversations yet</h4>' +
                '<p>Start matching with people to begin chatting!</p>' +
            '</div>'
        );

        $('.wpmatch-chat-area').html(
            '<div class="wpmatch-empty-chat">' +
                '<h3>No conversation selected</h3>' +
                '<p>Select a conversation from the sidebar to start chatting</p>' +
            '</div>'
        );
    }

    function showChatLoading() {
        $('.wpmatch-chat-area').html(
            '<div style="display: flex; justify-content: center; align-items: center; height: 100%; color: #666;">' +
                '<div>Loading conversation...</div>' +
            '</div>'
        );
    }

    function showChatError(message) {
        $('.wpmatch-chat-area').html(
            '<div style="display: flex; justify-content: center; align-items: center; height: 100%; color: #e91e63;">' +
                '<div>' + message + '</div>' +
            '</div>'
        );
    }

    function showError(message) {
        console.error('Messaging error:', message);
    }

    // Cleanup on page unload
    $(window).on('beforeunload', function() {
        if (messagePollingInterval) {
            clearInterval(messagePollingInterval);
        }
    });

    // Initialize the interface
    initMessagingInterface();
});