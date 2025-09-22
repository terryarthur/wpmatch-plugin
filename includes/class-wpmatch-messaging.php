<?php
/**
 * WPMatch Messaging System
 *
 * Handles all messaging functionality including conversations, message sending,
 * and real-time chat features.
 *
 * @package WPMatch
 */

/**
 * WPMatch Messaging class.
 *
 * @since 1.0.0
 */
class WPMatch_Messaging {

	/**
	 * Plugin name.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Message types.
	 */
	const MESSAGE_TYPES = array(
		'text',
		'emoji',
		'image',
		'gif',
		'voice_note',
		'video_message',
		'location',
		'contact',
	);

	/**
	 * Initialize the class.
	 *
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Send a message between matched users.
	 *
	 * @since 1.0.0
	 * @param int    $sender_id Sender user ID.
	 * @param int    $recipient_id Recipient user ID.
	 * @param string $message_content Message content.
	 * @param string $message_type Message type.
	 * @param string $attachment_url Optional attachment URL.
	 * @return array Result with success status and message data.
	 */
	public function send_message( $sender_id, $recipient_id, $message_content, $message_type = 'text', $attachment_url = '' ) {
		global $wpdb;

		// Validate inputs.
		$sender_id       = absint( $sender_id );
		$recipient_id    = absint( $recipient_id );
		$message_content = sanitize_textarea_field( $message_content );
		$message_type    = sanitize_text_field( $message_type );
		$attachment_url  = sanitize_url( $attachment_url );

		if ( ! $sender_id || ! $recipient_id || $sender_id === $recipient_id ) {
			return array(
				'success' => false,
				'error'   => 'invalid_users',
				'message' => __( 'Invalid user IDs.', 'wpmatch' ),
			);
		}

		if ( empty( $message_content ) && empty( $attachment_url ) ) {
			return array(
				'success' => false,
				'error'   => 'empty_message',
				'message' => __( 'Message content cannot be empty.', 'wpmatch' ),
			);
		}

		if ( ! in_array( $message_type, self::MESSAGE_TYPES, true ) ) {
			$message_type = 'text';
		}

		// Check if users are matched.
		if ( ! $this->are_users_matched( $sender_id, $recipient_id ) ) {
			return array(
				'success' => false,
				'error'   => 'not_matched',
				'message' => __( 'You can only message users you have matched with.', 'wpmatch' ),
			);
		}

		// Check if conversation is blocked.
		if ( $this->is_conversation_blocked( $sender_id, $recipient_id ) ) {
			return array(
				'success' => false,
				'error'   => 'conversation_blocked',
				'message' => __( 'This conversation has been blocked.', 'wpmatch' ),
			);
		}

		// Check messaging permissions.
		if ( ! $this->can_send_message( $sender_id, $recipient_id ) ) {
			return array(
				'success' => false,
				'error'   => 'no_permission',
				'message' => __( 'You do not have permission to send messages to this user.', 'wpmatch' ),
			);
		}

		// Get or create conversation.
		$conversation_id = $this->get_or_create_conversation( $sender_id, $recipient_id );

		if ( ! $conversation_id ) {
			return array(
				'success' => false,
				'error'   => 'conversation_failed',
				'message' => __( 'Failed to create conversation.', 'wpmatch' ),
			);
		}

		// Insert message.
		$messages_table = $wpdb->prefix . 'wpmatch_messages';
		$result         = $wpdb->insert(
			$messages_table,
			array(
				'conversation_id' => $conversation_id,
				'sender_id'       => $sender_id,
				'recipient_id'    => $recipient_id,
				'message_content' => $message_content,
				'message_type'    => $message_type,
				'attachment_url'  => $attachment_url,
				'is_read'         => 0,
				'created_at'      => current_time( 'mysql' ),
			),
			array(
				'%s',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
			)
		);

		if ( ! $result ) {
			return array(
				'success' => false,
				'error'   => 'message_failed',
				'message' => __( 'Failed to send message.', 'wpmatch' ),
			);
		}

		$message_id = $wpdb->insert_id;

		// Update conversation last activity.
		$this->update_conversation_activity( $conversation_id, $message_id );

		// Send real-time notification.
		$this->send_realtime_notification( $recipient_id, $sender_id, $message_id, $message_content );

		// Send push notification.
		$this->send_push_notification( $recipient_id, $sender_id, $message_content );

		// Trigger action hook.
		do_action( 'wpmatch_message_sent', $message_id, $sender_id, $recipient_id, $conversation_id );

		return array(
			'success'         => true,
			'message_id'      => $message_id,
			'conversation_id' => $conversation_id,
			'timestamp'       => current_time( 'mysql' ),
		);
	}

	/**
	 * Get conversation messages.
	 *
	 * @since 1.0.0
	 * @param string $conversation_id Conversation ID.
	 * @param int    $user_id User requesting messages.
	 * @param int    $limit Number of messages to retrieve.
	 * @param int    $offset Offset for pagination.
	 * @return array Messages array.
	 */
	public function get_conversation_messages( $conversation_id, $user_id, $limit = 50, $offset = 0 ) {
		global $wpdb;

		$conversation_id = sanitize_text_field( $conversation_id );
		$user_id         = absint( $user_id );
		$limit           = absint( $limit );
		$offset          = absint( $offset );

		if ( ! $conversation_id || ! $user_id ) {
			return array();
		}

		// Verify user is part of conversation.
		if ( ! $this->is_user_in_conversation( $conversation_id, $user_id ) ) {
			return array();
		}

		$messages_table = $wpdb->prefix . 'wpmatch_messages';

		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.*, u.display_name as sender_name, u.user_login as sender_username
			FROM {$messages_table} m
			INNER JOIN {$wpdb->users} u ON m.sender_id = u.ID
			WHERE m.conversation_id = %s
			AND (
				(m.sender_id = %d AND m.is_deleted_sender = 0)
				OR (m.recipient_id = %d AND m.is_deleted_recipient = 0)
			)
			ORDER BY m.created_at DESC
			LIMIT %d OFFSET %d",
				$conversation_id,
				$user_id,
				$user_id,
				$limit,
				$offset
			),
			ARRAY_A
		);

		// Mark messages as read.
		$this->mark_messages_as_read( $conversation_id, $user_id );

		return array_reverse( $messages );
	}

	/**
	 * Get user conversations list.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @param int $limit Number of conversations to retrieve.
	 * @param int $offset Offset for pagination.
	 * @return array Conversations array.
	 */
	public function get_user_conversations( $user_id, $limit = 20, $offset = 0 ) {
		global $wpdb;

		$user_id = absint( $user_id );
		$limit   = absint( $limit );
		$offset  = absint( $offset );

		if ( ! $user_id ) {
			return array();
		}

		$conversations_table = $wpdb->prefix . 'wpmatch_conversations';
		$messages_table      = $wpdb->prefix . 'wpmatch_messages';

		$conversations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.*,
				CASE
					WHEN c.user1_id = %d THEN c.user2_id
					ELSE c.user1_id
				END as other_user_id,
				u.display_name as other_user_name,
				p.age, p.location,
				m.message_content as last_message_content,
				m.message_type as last_message_type,
				m.sender_id as last_message_sender_id,
				(SELECT COUNT(*) FROM {$messages_table} unread
				 WHERE unread.conversation_id = c.conversation_id
				 AND unread.recipient_id = %d
				 AND unread.is_read = 0
				 AND unread.is_deleted_recipient = 0) as unread_count
			FROM {$conversations_table} c
			INNER JOIN {$wpdb->users} u ON (
				CASE
					WHEN c.user1_id = %d THEN c.user2_id
					ELSE c.user1_id
				END
			) = u.ID
			LEFT JOIN {$wpdb->prefix}wpmatch_user_profiles p ON u.ID = p.user_id
			LEFT JOIN {$messages_table} m ON c.last_message_id = m.message_id
			WHERE (c.user1_id = %d OR c.user2_id = %d)
			AND (
				(c.user1_id = %d AND c.user1_deleted = 0 AND c.user1_blocked = 0)
				OR (c.user2_id = %d AND c.user2_deleted = 0 AND c.user2_blocked = 0)
			)
			ORDER BY c.last_message_at DESC
			LIMIT %d OFFSET %d",
				$user_id, // CASE condition.
				$user_id, // unread_count subquery.
				$user_id, // CASE condition in JOIN.
				$user_id,
				$user_id, // WHERE conditions.
				$user_id,
				$user_id, // WHERE conditions for deleted/blocked.
				$limit,
				$offset
			),
			ARRAY_A
		);

		// Add additional data for each conversation.
		foreach ( $conversations as &$conversation ) {
			$conversation['other_user_photos'] = $this->get_user_primary_photo( $conversation['other_user_id'] );
			$conversation['is_online']         = $this->is_user_online( $conversation['other_user_id'] );
			$conversation['last_seen']         = $this->get_user_last_seen( $conversation['other_user_id'] );
		}

		return $conversations;
	}

	/**
	 * Mark messages as read in a conversation.
	 *
	 * @since 1.0.0
	 * @param string $conversation_id Conversation ID.
	 * @param int    $user_id User ID marking messages as read.
	 * @return bool Success status.
	 */
	public function mark_messages_as_read( $conversation_id, $user_id ) {
		global $wpdb;

		$conversation_id = sanitize_text_field( $conversation_id );
		$user_id         = absint( $user_id );

		if ( ! $conversation_id || ! $user_id ) {
			return false;
		}

		$messages_table = $wpdb->prefix . 'wpmatch_messages';

		$result = $wpdb->update(
			$messages_table,
			array(
				'is_read' => 1,
				'read_at' => current_time( 'mysql' ),
			),
			array(
				'conversation_id' => $conversation_id,
				'recipient_id'    => $user_id,
				'is_read'         => 0,
			),
			array( '%d', '%s' ),
			array( '%s', '%d', '%d' )
		);

		// Send read receipt notification.
		if ( $result ) {
			$this->send_read_receipt_notification( $conversation_id, $user_id );
		}

		return $result !== false;
	}

	/**
	 * Delete a message for the current user.
	 *
	 * @since 1.0.0
	 * @param int $message_id Message ID.
	 * @param int $user_id User ID.
	 * @return bool Success status.
	 */
	public function delete_message( $message_id, $user_id ) {
		global $wpdb;

		$message_id = absint( $message_id );
		$user_id    = absint( $user_id );

		if ( ! $message_id || ! $user_id ) {
			return false;
		}

		$messages_table = $wpdb->prefix . 'wpmatch_messages';

		// Get message details.
		$message = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$messages_table} WHERE message_id = %d",
				$message_id
			)
		);

		if ( ! $message ) {
			return false;
		}

		// Determine which field to update based on user role.
		$update_field = '';
		if ( absint( $message->sender_id ) === $user_id ) {
			$update_field = 'is_deleted_sender';
		} elseif ( absint( $message->recipient_id ) === $user_id ) {
			$update_field = 'is_deleted_recipient';
		}

		if ( ! $update_field ) {
			return false;
		}

		return $wpdb->update(
			$messages_table,
			array( $update_field => 1 ),
			array( 'message_id' => $message_id ),
			array( '%d' ),
			array( '%d' )
		) !== false;
	}

	/**
	 * Block a conversation.
	 *
	 * @since 1.0.0
	 * @param string $conversation_id Conversation ID.
	 * @param int    $user_id User ID doing the blocking.
	 * @return bool Success status.
	 */
	public function block_conversation( $conversation_id, $user_id ) {
		global $wpdb;

		$conversation_id = sanitize_text_field( $conversation_id );
		$user_id         = absint( $user_id );

		if ( ! $conversation_id || ! $user_id ) {
			return false;
		}

		$conversations_table = $wpdb->prefix . 'wpmatch_conversations';

		// Get conversation details.
		$conversation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$conversations_table} WHERE conversation_id = %s",
				$conversation_id
			)
		);

		if ( ! $conversation ) {
			return false;
		}

		// Determine which field to update.
		$update_field = '';
		if ( absint( $conversation->user1_id ) === $user_id ) {
			$update_field = 'user1_blocked';
		} elseif ( absint( $conversation->user2_id ) === $user_id ) {
			$update_field = 'user2_blocked';
		}

		if ( ! $update_field ) {
			return false;
		}

		$result = $wpdb->update(
			$conversations_table,
			array( $update_field => 1 ),
			array( 'conversation_id' => $conversation_id ),
			array( '%d' ),
			array( '%s' )
		);

		// Also block the user in preferences.
		if ( $result ) {
			$other_user_id = ( absint( $conversation->user1_id ) === $user_id ) ? absint( $conversation->user2_id ) : absint( $conversation->user1_id );
			$this->block_user_in_preferences( $user_id, $other_user_id );
		}

		return $result !== false;
	}

	/**
	 * Check if users are matched.
	 *
	 * @since 1.0.0
	 * @param int $user1_id First user ID.
	 * @param int $user2_id Second user ID.
	 * @return bool True if matched.
	 */
	private function are_users_matched( $user1_id, $user2_id ) {
		global $wpdb;

		$matches_table = $wpdb->prefix . 'wpmatch_matches';

		// Ensure consistent ordering.
		$min_user_id = min( $user1_id, $user2_id );
		$max_user_id = max( $user1_id, $user2_id );

		$match = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT match_id FROM {$matches_table}
			WHERE user1_id = %d AND user2_id = %d AND status = 'active'",
				$min_user_id,
				$max_user_id
			)
		);

		return (bool) $match;
	}

	/**
	 * Check if conversation is blocked.
	 *
	 * @since 1.0.0
	 * @param int $user1_id First user ID.
	 * @param int $user2_id Second user ID.
	 * @return bool True if blocked.
	 */
	private function is_conversation_blocked( $user1_id, $user2_id ) {
		global $wpdb;

		$conversations_table = $wpdb->prefix . 'wpmatch_conversations';

		$conversation_id = $this->generate_conversation_id( $user1_id, $user2_id );

		$blocked = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT CASE
				WHEN user1_id = %d THEN user1_blocked
				WHEN user2_id = %d THEN user2_blocked
				ELSE 0
			END as is_blocked
			FROM {$conversations_table}
			WHERE conversation_id = %s",
				$user1_id,
				$user1_id,
				$conversation_id
			)
		);

		return (bool) $blocked;
	}

	/**
	 * Check if user can send messages.
	 *
	 * @since 1.0.0
	 * @param int $sender_id Sender user ID.
	 * @param int $recipient_id Recipient user ID.
	 * @return bool True if allowed.
	 */
	private function can_send_message( $sender_id, $recipient_id ) {
		// Check recipient preferences.
		$recipient_preferences = get_user_meta( $recipient_id, 'wpmatch_messaging_preferences', true );

		if ( is_array( $recipient_preferences ) && isset( $recipient_preferences['allow_messages'] ) ) {
			return (bool) $recipient_preferences['allow_messages'];
		}

		// Default to allowing messages for matched users.
		return true;
	}

	/**
	 * Get or create conversation between two users.
	 *
	 * @since 1.0.0
	 * @param int $user1_id First user ID.
	 * @param int $user2_id Second user ID.
	 * @return string|false Conversation ID or false on failure.
	 */
	private function get_or_create_conversation( $user1_id, $user2_id ) {
		global $wpdb;

		$conversation_id     = $this->generate_conversation_id( $user1_id, $user2_id );
		$conversations_table = $wpdb->prefix . 'wpmatch_conversations';

		// Check if conversation exists.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT conversation_id FROM {$conversations_table} WHERE conversation_id = %s",
				$conversation_id
			)
		);

		if ( $existing ) {
			return $existing;
		}

		// Create new conversation.
		$min_user_id = min( $user1_id, $user2_id );
		$max_user_id = max( $user1_id, $user2_id );

		$result = $wpdb->insert(
			$conversations_table,
			array(
				'conversation_id' => $conversation_id,
				'user1_id'        => $min_user_id,
				'user2_id'        => $max_user_id,
				'created_at'      => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%s', '%s' )
		);

		return $result ? $conversation_id : false;
	}

	/**
	 * Generate conversation ID from two user IDs.
	 *
	 * @since 1.0.0
	 * @param int $user1_id First user ID.
	 * @param int $user2_id Second user ID.
	 * @return string Conversation ID.
	 */
	private function generate_conversation_id( $user1_id, $user2_id ) {
		$min_user_id = min( $user1_id, $user2_id );
		$max_user_id = max( $user1_id, $user2_id );
		return 'conv_' . $min_user_id . '_' . $max_user_id;
	}

	/**
	 * Update conversation last activity.
	 *
	 * @since 1.0.0
	 * @param string $conversation_id Conversation ID.
	 * @param int    $last_message_id Last message ID.
	 */
	private function update_conversation_activity( $conversation_id, $last_message_id ) {
		global $wpdb;

		$conversations_table = $wpdb->prefix . 'wpmatch_conversations';

		$wpdb->update(
			$conversations_table,
			array(
				'last_message_id' => $last_message_id,
				'last_message_at' => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
			),
			array( 'conversation_id' => $conversation_id ),
			array( '%d', '%s', '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Check if user is in conversation.
	 *
	 * @since 1.0.0
	 * @param string $conversation_id Conversation ID.
	 * @param int    $user_id User ID.
	 * @return bool True if user is in conversation.
	 */
	private function is_user_in_conversation( $conversation_id, $user_id ) {
		global $wpdb;

		$conversations_table = $wpdb->prefix . 'wpmatch_conversations';

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$conversations_table}
			WHERE conversation_id = %s AND (user1_id = %d OR user2_id = %d)",
				$conversation_id,
				$user_id,
				$user_id
			)
		);

		return $count > 0;
	}

	/**
	 * Get user primary photo.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return string|null Photo URL or null.
	 */
	private function get_user_primary_photo( $user_id ) {
		global $wpdb;

		$media_table = $wpdb->prefix . 'wpmatch_user_media';

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT file_path FROM {$media_table}
			WHERE user_id = %d AND media_type = 'photo' AND is_primary = 1 AND is_verified = 1
			LIMIT 1",
				$user_id
			)
		);
	}

	/**
	 * Check if user is online.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return bool True if online.
	 */
	private function is_user_online( $user_id ) {
		$last_activity = get_user_meta( $user_id, 'wpmatch_last_activity', true );

		if ( ! $last_activity ) {
			return false;
		}

		$threshold = strtotime( '-15 minutes' );
		return strtotime( $last_activity ) > $threshold;
	}

	/**
	 * Get user last seen time.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return string|null Last seen time or null.
	 */
	private function get_user_last_seen( $user_id ) {
		return get_user_meta( $user_id, 'wpmatch_last_activity', true );
	}

	/**
	 * Send real-time notification.
	 *
	 * @since 1.0.0
	 * @param int    $recipient_id Recipient user ID.
	 * @param int    $sender_id Sender user ID.
	 * @param int    $message_id Message ID.
	 * @param string $message_content Message content.
	 */
	private function send_realtime_notification( $recipient_id, $sender_id, $message_id, $message_content ) {
		// This would integrate with WebSocket server.
		do_action(
			'wpmatch_realtime_message',
			$recipient_id,
			array(
				'type'       => 'new_message',
				'sender_id'  => $sender_id,
				'message_id' => $message_id,
				'content'    => $message_content,
				'timestamp'  => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Send push notification.
	 *
	 * @since 1.0.0
	 * @param int    $recipient_id Recipient user ID.
	 * @param int    $sender_id Sender user ID.
	 * @param string $message_content Message content.
	 */
	private function send_push_notification( $recipient_id, $sender_id, $message_content ) {
		// Check if user has push notifications enabled.
		$push_enabled = get_user_meta( $recipient_id, 'wpmatch_push_notifications', true );

		if ( ! $push_enabled ) {
			return;
		}

		$sender = get_userdata( $sender_id );
		if ( ! $sender ) {
			return;
		}

		// Trigger push notification.
		do_action(
			'wpmatch_send_push_notification',
			$recipient_id,
			array(
				'title' => sprintf( __( 'New message from %s', 'wpmatch' ), $sender->display_name ),
				'body'  => wp_trim_words( $message_content, 10 ),
				'data'  => array(
					'type'      => 'new_message',
					'sender_id' => $sender_id,
				),
			)
		);
	}

	/**
	 * Send read receipt notification.
	 *
	 * @since 1.0.0
	 * @param string $conversation_id Conversation ID.
	 * @param int    $reader_id User who read the messages.
	 */
	private function send_read_receipt_notification( $conversation_id, $reader_id ) {
		// Get the other user in conversation.
		global $wpdb;

		$conversations_table = $wpdb->prefix . 'wpmatch_conversations';

		$other_user_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT CASE
				WHEN user1_id = %d THEN user2_id
				ELSE user1_id
			END as other_user_id
			FROM {$conversations_table}
			WHERE conversation_id = %s",
				$reader_id,
				$conversation_id
			)
		);

		if ( $other_user_id ) {
			do_action(
				'wpmatch_realtime_message',
				$other_user_id,
				array(
					'type'            => 'messages_read',
					'conversation_id' => $conversation_id,
					'reader_id'       => $reader_id,
					'timestamp'       => current_time( 'mysql' ),
				)
			);
		}
	}

	/**
	 * Block user in preferences.
	 *
	 * @since 1.0.0
	 * @param int $user_id User doing the blocking.
	 * @param int $blocked_user_id User being blocked.
	 */
	private function block_user_in_preferences( $user_id, $blocked_user_id ) {
		global $wpdb;

		$preferences_table = $wpdb->prefix . 'wpmatch_user_preferences';

		// Get current blocked users.
		$current_blocked = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT blocked_users FROM {$preferences_table} WHERE user_id = %d",
				$user_id
			)
		);

		$blocked_list = json_decode( $current_blocked, true );
		if ( ! is_array( $blocked_list ) ) {
			$blocked_list = array();
		}

		// Add to blocked list if not already present.
		if ( ! in_array( $blocked_user_id, $blocked_list, true ) ) {
			$blocked_list[] = $blocked_user_id;

			$wpdb->update(
				$preferences_table,
				array( 'blocked_users' => wp_json_encode( $blocked_list ) ),
				array( 'user_id' => $user_id ),
				array( '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Get unread message count for user.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return int Unread message count.
	 */
	public function get_unread_count( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return 0;
		}

		$messages_table = $wpdb->prefix . 'wpmatch_messages';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$messages_table}
			WHERE recipient_id = %d AND is_read = 0 AND is_deleted_recipient = 0",
				$user_id
			)
		);
	}

	/**
	 * API endpoint for sending a message.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response REST response.
	 */
	public function api_send_message( $request ) {
		$sender_id       = get_current_user_id();
		$recipient_id    = absint( $request->get_param( 'recipient_id' ) );
		$message_content = sanitize_textarea_field( $request->get_param( 'message_content' ) );
		$message_type    = sanitize_text_field( $request->get_param( 'message_type' ) );
		$attachment_url  = sanitize_url( $request->get_param( 'attachment_url' ) );

		$result = $this->send_message( $sender_id, $recipient_id, $message_content, $message_type, $attachment_url );

		return rest_ensure_response( $result );
	}

	/**
	 * API endpoint for getting conversation messages.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response REST response.
	 */
	public function api_get_conversation_messages( $request ) {
		$user_id         = get_current_user_id();
		$conversation_id = sanitize_text_field( $request->get_param( 'conversation_id' ) );
		$limit           = absint( $request->get_param( 'limit' ) );
		$offset          = absint( $request->get_param( 'offset' ) );

		$messages = $this->get_conversation_messages( $conversation_id, $user_id, $limit, $offset );

		return rest_ensure_response(
			array(
				'success'  => true,
				'messages' => $messages,
				'count'    => count( $messages ),
			)
		);
	}

	/**
	 * API endpoint for getting user conversations.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response REST response.
	 */
	public function api_get_user_conversations( $request ) {
		$user_id = get_current_user_id();
		$limit   = absint( $request->get_param( 'limit' ) );
		$offset  = absint( $request->get_param( 'offset' ) );

		$conversations = $this->get_user_conversations( $user_id, $limit, $offset );

		return rest_ensure_response(
			array(
				'success'       => true,
				'conversations' => $conversations,
				'count'         => count( $conversations ),
			)
		);
	}

	/**
	 * API endpoint for marking messages as read.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response REST response.
	 */
	public function api_mark_messages_as_read( $request ) {
		$user_id         = get_current_user_id();
		$conversation_id = sanitize_text_field( $request->get_param( 'conversation_id' ) );

		$result = $this->mark_messages_as_read( $conversation_id, $user_id );

		return rest_ensure_response(
			array(
				'success' => $result,
			)
		);
	}

	/**
	 * API endpoint for deleting a message.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response REST response.
	 */
	public function api_delete_message( $request ) {
		$user_id    = get_current_user_id();
		$message_id = absint( $request->get_param( 'message_id' ) );

		$result = $this->delete_message( $message_id, $user_id );

		return rest_ensure_response(
			array(
				'success' => $result,
			)
		);
	}

	/**
	 * API endpoint for blocking a conversation.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response REST response.
	 */
	public function api_block_conversation( $request ) {
		$user_id         = get_current_user_id();
		$conversation_id = sanitize_text_field( $request->get_param( 'conversation_id' ) );

		$result = $this->block_conversation( $conversation_id, $user_id );

		return rest_ensure_response(
			array(
				'success' => $result,
			)
		);
	}

	/**
	 * API endpoint for getting unread message count.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response REST response.
	 */
	public function api_get_unread_count( $request ) {
		$user_id = get_current_user_id();

		$count = $this->get_unread_count( $user_id );

		return rest_ensure_response(
			array(
				'success'      => true,
				'unread_count' => $count,
			)
		);
	}
}
