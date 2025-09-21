/**
 * WPMatch Messages JavaScript
 *
 * Handles real-time messaging functionality.
 *
 * @package WPMatch
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * WPMatch Messages Controller
     */
    var WPMatchMessages = {

        /**
         * Initialize messaging functionality
         */
        init: function() {
            this.bindEvents();
            this.scrollToBottom();
            this.autoRefreshMessages();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Send message form
            $(document).on('submit', '#wpmatch-send-message-form', this.sendMessage);

            // Delete message
            $(document).on('click', '.wpmatch-delete-message', this.deleteMessage);

            // Block user
            $(document).on('click', '#wpmatch-block-user', this.blockUser);

            // Auto-resize textarea
            $(document).on('input', '#wpmatch-message-input', this.autoResizeTextarea);

            // Enter to send (Shift+Enter for new line)
            $(document).on('keydown', '#wpmatch-message-input', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    $('#wpmatch-send-message-form').trigger('submit');
                }
            });
        },

        /**
         * Send message
         */
        sendMessage: function(e) {
            e.preventDefault();

            var $form = $(this);
            var $input = $('#wpmatch-message-input');
            var $button = $form.find('.wpmatch-send-button');
            var message = $input.val().trim();

            if (!message) {
                return;
            }

            // Disable form during send
            $input.prop('disabled', true);
            $button.prop('disabled', true);
            $button.html('<span class="wpmatch-spinner"></span>');

            // Optimistically add message to UI
            var tempMessage = WPMatchMessages.createMessageElement({
                message_content: message,
                sender_id: wpmatch_messages.current_user_id,
                created_at: new Date().toISOString(),
                is_temp: true
            });
            $('#wpmatch-messages-list').append(tempMessage);
            WPMatchMessages.scrollToBottom();

            // Clear input
            $input.val('').trigger('input');

            // Send via AJAX
            $.ajax({
                url: wpmatch_messages.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpmatch_public_action',
                    action_type: 'send_message',
                    conversation_id: $form.find('input[name="conversation_id"]').val(),
                    recipient_id: $form.find('input[name="recipient_id"]').val(),
                    message: message,
                    nonce: wpmatch_messages.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Remove temp message and add real one
                        $('.wpmatch-message[data-temp="true"]').remove();

                        var realMessage = WPMatchMessages.createMessageElement({
                            message_id: response.data.message_id,
                            message_content: message,
                            sender_id: wpmatch_messages.current_user_id,
                            created_at: response.data.created_at || new Date().toISOString(),
                            is_read: false
                        });
                        $('#wpmatch-messages-list').append(realMessage);
                        WPMatchMessages.scrollToBottom();
                    } else {
                        WPMatchMessages.showError(response.data.message || wpmatch_messages.strings.send_error);
                        $('.wpmatch-message[data-temp="true"]').remove();
                    }
                },
                error: function() {
                    WPMatchMessages.showError(wpmatch_messages.strings.send_error);
                    $('.wpmatch-message[data-temp="true"]').remove();
                },
                complete: function() {
                    // Re-enable form
                    $input.prop('disabled', false);
                    $button.prop('disabled', false);
                    $button.html('<span class="dashicons dashicons-arrow-right-alt2"></span>');
                    $input.focus();
                }
            });
        },

        /**
         * Delete message
         */
        deleteMessage: function(e) {
            e.preventDefault();

            if (!confirm(wpmatch_messages.strings.delete_confirm)) {
                return;
            }

            var $button = $(this);
            var messageId = $button.data('message-id');
            var $message = $button.closest('.wpmatch-message');

            $.ajax({
                url: wpmatch_messages.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpmatch_public_action',
                    action_type: 'delete_message',
                    message_id: messageId,
                    nonce: wpmatch_messages.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $message.fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        WPMatchMessages.showError(response.data.message);
                    }
                },
                error: function() {
                    WPMatchMessages.showError('Failed to delete message.');
                }
            });
        },

        /**
         * Block user
         */
        blockUser: function(e) {
            e.preventDefault();

            if (!confirm(wpmatch_messages.strings.block_confirm)) {
                return;
            }

            var $button = $(this);
            var userId = $button.data('user-id');

            $.ajax({
                url: wpmatch_messages.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpmatch_public_action',
                    action_type: 'block_user',
                    user_id: userId,
                    nonce: wpmatch_messages.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Disable message input
                        $('#wpmatch-message-input').prop('disabled', true).attr('placeholder', 'User has been blocked');
                        $('.wpmatch-send-button').prop('disabled', true);
                        $button.text('Blocked').prop('disabled', true);
                    } else {
                        WPMatchMessages.showError(response.data.message);
                    }
                },
                error: function() {
                    WPMatchMessages.showError('Failed to block user.');
                }
            });
        },

        /**
         * Create message element
         */
        createMessageElement: function(message) {
            var isSender = (message.sender_id == wpmatch_messages.current_user_id);
            var messageClass = isSender ? 'sent' : 'received';
            var timeString = WPMatchMessages.formatMessageTime(message.created_at);
            var tempAttr = message.is_temp ? 'data-temp="true"' : '';

            var html = '<div class="wpmatch-message ' + messageClass + '" data-message-id="' + (message.message_id || '') + '" ' + tempAttr + '>' +
                      '<div class="wpmatch-message-content">' +
                      '<p>' + WPMatchMessages.escapeHtml(message.message_content) + '</p>' +
                      '<span class="wpmatch-message-time">' + timeString;

            if (isSender && message.is_read) {
                html += '<span class="wpmatch-read-indicator">✓</span>';
            }

            html += '</span></div>';

            if (isSender && !message.is_temp) {
                html += '<div class="wpmatch-message-actions">' +
                       '<button type="button" class="wpmatch-delete-message" data-message-id="' + message.message_id + '" title="Delete message">×</button>' +
                       '</div>';
            }

            html += '</div>';

            return $(html);
        },

        /**
         * Auto-resize textarea
         */
        autoResizeTextarea: function() {
            var $textarea = $(this);
            $textarea.css('height', 'auto');

            var scrollHeight = this.scrollHeight;
            var maxHeight = 120; // Maximum height in pixels

            if (scrollHeight > maxHeight) {
                $textarea.css('height', maxHeight + 'px');
                $textarea.css('overflow-y', 'scroll');
            } else {
                $textarea.css('height', scrollHeight + 'px');
                $textarea.css('overflow-y', 'hidden');
            }
        },

        /**
         * Scroll to bottom of messages
         */
        scrollToBottom: function() {
            var $messagesList = $('#wpmatch-messages-list');
            if ($messagesList.length) {
                $messagesList.scrollTop($messagesList[0].scrollHeight);
            }
        },

        /**
         * Auto-refresh messages
         */
        autoRefreshMessages: function() {
            if (!wpmatch_messages.current_conversation) {
                return;
            }

            // Refresh every 10 seconds
            setInterval(function() {
                WPMatchMessages.refreshMessages();
            }, 10000);
        },

        /**
         * Refresh messages
         */
        refreshMessages: function() {
            var $messagesList = $('#wpmatch-messages-list');
            var lastMessageId = $messagesList.find('.wpmatch-message:last').data('message-id') || 0;

            $.ajax({
                url: wpmatch_messages.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpmatch_public_action',
                    action_type: 'get_new_messages',
                    conversation_id: wpmatch_messages.current_conversation,
                    last_message_id: lastMessageId,
                    nonce: wpmatch_messages.nonce
                },
                success: function(response) {
                    if (response.success && response.data.messages.length > 0) {
                        var $messagesList = $('#wpmatch-messages-list');
                        var wasAtBottom = WPMatchMessages.isScrolledToBottom($messagesList);

                        // Add new messages
                        $.each(response.data.messages, function(index, message) {
                            var messageElement = WPMatchMessages.createMessageElement(message);
                            $messagesList.append(messageElement);
                        });

                        // Auto-scroll if user was at bottom
                        if (wasAtBottom) {
                            WPMatchMessages.scrollToBottom();
                        }

                        // Mark as read if conversation is active
                        WPMatchMessages.markAsRead();
                    }
                }
            });
        },

        /**
         * Check if scrolled to bottom
         */
        isScrolledToBottom: function($element) {
            return $element.scrollTop() + $element.innerHeight() >= $element[0].scrollHeight - 10;
        },

        /**
         * Mark conversation as read
         */
        markAsRead: function() {
            if (!wpmatch_messages.current_conversation) {
                return;
            }

            $.ajax({
                url: wpmatch_messages.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpmatch_public_action',
                    action_type: 'mark_as_read',
                    conversation_id: wpmatch_messages.current_conversation,
                    nonce: wpmatch_messages.nonce
                }
            });
        },

        /**
         * Format message time
         */
        formatMessageTime: function(dateString) {
            var date = new Date(dateString);
            var now = new Date();
            var diff = now - date;

            // If less than 24 hours, show time
            if (diff < 24 * 60 * 60 * 1000) {
                return date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            }

            // If less than a week, show day and time
            if (diff < 7 * 24 * 60 * 60 * 1000) {
                return date.toLocaleDateString([], {weekday: 'short'}) + ' ' +
                       date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            }

            // Otherwise show full date
            return date.toLocaleDateString([], {month: 'short', day: 'numeric'}) + ' ' +
                   date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        },

        /**
         * Show error message
         */
        showError: function(message) {
            var $notice = $('<div class="wpmatch-notice error"><p>' + message + '</p></div>');
            $('.wpmatch-message-header').after($notice);

            // Auto-remove after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        if ($('.wpmatch-messages-container').length) {
            WPMatchMessages.init();
        }
    });

})(jQuery);