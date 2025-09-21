<?php
/**
 * WPMatch Message Manager
 *
 * Handles messaging functionality for the dating plugin.
 *
 * @package WPMatch
 */

class WPMatch_Message_Manager {

	/**
	 * Send a message between users
	 *
	 * @param int    $sender_id     User ID of sender
	 * @param int    $recipient_id  User ID of recipient
	 * @param string $message       Message content
	 * @param string $type          Message type (text, emoji, image, gif)
	 * @return array                Result array with success status and data
	 */
	public static function send_message( $sender_id, $recipient_id, $message, $type = 'text' ) {
		global $wpdb;

		// Validate inputs
		$sender_id    = absint( $sender_id );
		$recipient_id = absint( $recipient_id );
		$message      = sanitize_textarea_field( $message );
		$type         = sanitize_key( $type );

		if ( ! $sender_id || ! $recipient_id || empty( $message ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid message data.', 'wpmatch' ),
			);
		}

		// Check if users can message each other
		if ( ! self::can_users_message( $sender_id, $recipient_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'You cannot send messages to this user.', 'wpmatch' ),
			);
		}

		// Get or create conversation
		$conversation_id = self::get_conversation_id( $sender_id, $recipient_id );
		if ( ! $conversation_id ) {
			$conversation_id = self::create_conversation( $sender_id, $recipient_id );
		}

		// Insert message
		$table_messages = $wpdb->prefix . 'wpmatch_messages';
		$result         = $wpdb->insert(
			$table_messages,
			array(
				'conversation_id' => $conversation_id,
				'sender_id'       => $sender_id,
				'recipient_id'    => $recipient_id,
				'message_content' => $message,
				'message_type'    => $type,
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		if ( ! $result ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to send message.', 'wpmatch' ),
			);
		}

		$message_id = $wpdb->insert_id;

		// Update conversation
		self::update_conversation( $conversation_id, $message_id );

		return array(
			'success'    => true,
			'message'    => __( 'Message sent successfully.', 'wpmatch' ),
			'message_id' => $message_id,
		);
	}

	/**
	 * Get messages for a conversation
	 *
	 * @param string $conversation_id Conversation ID
	 * @param int    $limit          Number of messages to retrieve
	 * @param int    $offset         Offset for pagination
	 * @return array                 Array of messages
	 */
	public static function get_messages( $conversation_id, $limit = 50, $offset = 0 ) {
		global $wpdb;

		$conversation_id = sanitize_text_field( $conversation_id );
		$limit           = absint( $limit );
		$offset          = absint( $offset );

		$table_messages = $wpdb->prefix . 'wpmatch_messages';

		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.*, u.display_name, u.user_email
				FROM {$table_messages} m
				LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
				WHERE m.conversation_id = %s
				AND m.is_deleted_sender = 0
				AND m.is_deleted_recipient = 0
				ORDER BY m.created_at DESC
				LIMIT %d OFFSET %d",
				$conversation_id,
				$limit,
				$offset
			)
		);

		return $messages ? $messages : array();
	}

	/**
	 * Get conversations for a user
	 *
	 * @param int $user_id User ID
	 * @return array       Array of conversations
	 */
	public static function get_conversations( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );

		$table_conversations = $wpdb->prefix . 'wpmatch_conversations';
		$table_messages      = $wpdb->prefix . 'wpmatch_messages';

		$conversations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.*,
				CASE
					WHEN c.user1_id = %d THEN c.user2_id
					ELSE c.user1_id
				END as other_user_id,
				CASE
					WHEN c.user1_id = %d THEN u2.display_name
					ELSE u1.display_name
				END as other_user_name,
				m.message_content as last_message,
				m.created_at as last_message_time,
				(SELECT COUNT(*) FROM {$table_messages}
				 WHERE conversation_id = c.conversation_id
				 AND recipient_id = %d AND is_read = 0) as unread_count
				FROM {$table_conversations} c
				LEFT JOIN {$wpdb->users} u1 ON c.user1_id = u1.ID
				LEFT JOIN {$wpdb->users} u2 ON c.user2_id = u2.ID
				LEFT JOIN {$table_messages} m ON c.last_message_id = m.message_id
				WHERE (c.user1_id = %d OR c.user2_id = %d)
				AND ((c.user1_id = %d AND c.user1_deleted = 0) OR (c.user2_id = %d AND c.user2_deleted = 0))
				ORDER BY c.last_message_at DESC",
				$user_id,
				$user_id,
				$user_id,
				$user_id,
				$user_id,
				$user_id,
				$user_id
			)
		);

		return $conversations ? $conversations : array();
	}

	/**
	 * Mark message as read
	 *
	 * @param int $message_id Message ID
	 * @param int $user_id    User ID reading the message
	 * @return bool           Success status
	 */
	public static function mark_as_read( $message_id, $user_id ) {
		global $wpdb;

		$message_id = absint( $message_id );
		$user_id    = absint( $user_id );

		$table_messages = $wpdb->prefix . 'wpmatch_messages';

		$result = $wpdb->update(
			$table_messages,
			array(
				'is_read' => 1,
				'read_at' => current_time( 'mysql' ),
			),
			array(
				'message_id'   => $message_id,
				'recipient_id' => $user_id,
			),
			array( '%d', '%s' ),
			array( '%d', '%d' )
		);

		return $result !== false;
	}

	/**
	 * Mark all messages in conversation as read
	 *
	 * @param string $conversation_id Conversation ID
	 * @param int    $user_id         User ID
	 * @return bool                   Success status
	 */
	public static function mark_conversation_as_read( $conversation_id, $user_id ) {
		global $wpdb;

		$conversation_id = sanitize_text_field( $conversation_id );
		$user_id         = absint( $user_id );

		$table_messages = $wpdb->prefix . 'wpmatch_messages';

		$result = $wpdb->update(
			$table_messages,
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

		return $result !== false;
	}

	/**
	 * Delete message for user
	 *
	 * @param int $message_id Message ID
	 * @param int $user_id    User ID
	 * @return bool           Success status
	 */
	public static function delete_message( $message_id, $user_id ) {
		global $wpdb;

		$message_id = absint( $message_id );
		$user_id    = absint( $user_id );

		$table_messages = $wpdb->prefix . 'wpmatch_messages';

		// Check if user is sender or recipient
		$message = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT sender_id, recipient_id FROM {$table_messages} WHERE message_id = %d",
				$message_id
			)
		);

		if ( ! $message ) {
			return false;
		}

		$update_field = '';
		if ( $message->sender_id == $user_id ) {
			$update_field = 'is_deleted_sender';
		} elseif ( $message->recipient_id == $user_id ) {
			$update_field = 'is_deleted_recipient';
		} else {
			return false;
		}

		$result = $wpdb->update(
			$table_messages,
			array( $update_field => 1 ),
			array( 'message_id' => $message_id ),
			array( '%d' ),
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Block/unblock user in conversation
	 *
	 * @param int  $user_id       User performing the action
	 * @param int  $other_user_id User to block/unblock
	 * @param bool $block         Whether to block (true) or unblock (false)
	 * @return bool               Success status
	 */
	public static function block_user( $user_id, $other_user_id, $block = true ) {
		global $wpdb;

		$user_id       = absint( $user_id );
		$other_user_id = absint( $other_user_id );

		$conversation_id = self::get_conversation_id( $user_id, $other_user_id );
		if ( ! $conversation_id ) {
			return false;
		}

		$table_conversations = $wpdb->prefix . 'wpmatch_conversations';

		// Determine which user field to update
		$conversation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT user1_id, user2_id FROM {$table_conversations} WHERE conversation_id = %s",
				$conversation_id
			)
		);

		if ( ! $conversation ) {
			return false;
		}

		$update_field = '';
		if ( $conversation->user1_id == $user_id ) {
			$update_field = 'user1_blocked';
		} elseif ( $conversation->user2_id == $user_id ) {
			$update_field = 'user2_blocked';
		} else {
			return false;
		}

		$result = $wpdb->update(
			$table_conversations,
			array( $update_field => $block ? 1 : 0 ),
			array( 'conversation_id' => $conversation_id ),
			array( '%d' ),
			array( '%s' )
		);

		return $result !== false;
	}

	/**
	 * Check if users can message each other
	 *
	 * @param int $user1_id First user ID
	 * @param int $user2_id Second user ID
	 * @return bool         Whether users can message
	 */
	private static function can_users_message( $user1_id, $user2_id ) {
		// Check if users are blocked
		$conversation_id = self::get_conversation_id( $user1_id, $user2_id );
		if ( $conversation_id ) {
			global $wpdb;
			$table_conversations = $wpdb->prefix . 'wpmatch_conversations';

			$conversation = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT user1_blocked, user2_blocked, user1_id FROM {$table_conversations} WHERE conversation_id = %s",
					$conversation_id
				)
			);

			if ( $conversation ) {
				if ( $conversation->user1_id == $user1_id && $conversation->user2_blocked ) {
					return false;
				}
				if ( $conversation->user1_id == $user2_id && $conversation->user1_blocked ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Get conversation ID between two users
	 *
	 * @param int $user1_id First user ID
	 * @param int $user2_id Second user ID
	 * @return string|null  Conversation ID or null if not found
	 */
	private static function get_conversation_id( $user1_id, $user2_id ) {
		global $wpdb;

		$table_conversations = $wpdb->prefix . 'wpmatch_conversations';

		$conversation_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT conversation_id FROM {$table_conversations}
				WHERE (user1_id = %d AND user2_id = %d) OR (user1_id = %d AND user2_id = %d)",
				$user1_id,
				$user2_id,
				$user2_id,
				$user1_id
			)
		);

		return $conversation_id;
	}

	/**
	 * Create new conversation between users
	 *
	 * @param int $user1_id First user ID
	 * @param int $user2_id Second user ID
	 * @return string       Conversation ID
	 */
	private static function create_conversation( $user1_id, $user2_id ) {
		global $wpdb;

		// Ensure consistent ordering for conversation ID
		$lower_id        = min( $user1_id, $user2_id );
		$higher_id       = max( $user1_id, $user2_id );
		$conversation_id = "conv_{$lower_id}_{$higher_id}";

		$table_conversations = $wpdb->prefix . 'wpmatch_conversations';

		$wpdb->insert(
			$table_conversations,
			array(
				'conversation_id' => $conversation_id,
				'user1_id'        => $lower_id,
				'user2_id'        => $higher_id,
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%s' )
		);

		return $conversation_id;
	}

	/**
	 * Update conversation with last message info
	 *
	 * @param string $conversation_id Conversation ID
	 * @param int    $message_id      Last message ID
	 */
	private static function update_conversation( $conversation_id, $message_id ) {
		global $wpdb;

		$table_conversations = $wpdb->prefix . 'wpmatch_conversations';

		$wpdb->update(
			$table_conversations,
			array(
				'last_message_id' => $message_id,
				'last_message_at' => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
			),
			array( 'conversation_id' => $conversation_id ),
			array( '%d', '%s', '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Get unread message count for user
	 *
	 * @param int $user_id User ID
	 * @return int         Number of unread messages
	 */
	public static function get_unread_count( $user_id ) {
		global $wpdb;

		$user_id        = absint( $user_id );
		$table_messages = $wpdb->prefix . 'wpmatch_messages';

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_messages}
				WHERE recipient_id = %d AND is_read = 0 AND is_deleted_recipient = 0",
				$user_id
			)
		);

		return absint( $count );
	}
}
