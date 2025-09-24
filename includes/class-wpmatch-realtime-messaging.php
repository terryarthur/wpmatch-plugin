<?php
/**
 * WPMatch Real-time Messaging System
 *
 * Handles real-time messaging functionality including WebSocket connections,
 * message delivery, push notifications, and message management.
 *
 * @package WPMatch
 * @subpackage Messaging
 * @since 1.2.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPMatch Real-time Messaging class.
 *
 * @since 1.2.0
 */
class WPMatch_Realtime_Messaging {

	/**
	 * Messaging constants.
	 */
	const MAX_MESSAGE_LENGTH     = 1000;
	const MAX_MESSAGES_PER_HOUR  = 50;
	const TYPING_TIMEOUT_SECONDS = 5;
	const MESSAGE_BATCH_SIZE     = 20;

	/**
	 * Initialize real-time messaging features.
	 */
	public static function init() {
		// Create necessary database tables.
		self::create_tables();

		// Register REST API endpoints.
		add_action( 'rest_api_init', array( __CLASS__, 'register_api_routes' ) );

		// Handle AJAX requests for messaging.
		add_action( 'wp_ajax_wpmatch_send_message', array( __CLASS__, 'handle_send_message' ) );
		add_action( 'wp_ajax_wpmatch_get_messages', array( __CLASS__, 'handle_get_messages' ) );
		add_action( 'wp_ajax_wpmatch_mark_read', array( __CLASS__, 'handle_mark_read' ) );
		add_action( 'wp_ajax_wpmatch_typing_indicator', array( __CLASS__, 'handle_typing_indicator' ) );

		// WebSocket connection handling.
		add_action( 'wpmatch_websocket_message', array( __CLASS__, 'handle_websocket_message' ), 10, 2 );

		// Scheduled cleanup of old messages.
		if ( ! wp_next_scheduled( 'wpmatch_cleanup_old_messages' ) ) {
			wp_schedule_event( time(), 'daily', 'wpmatch_cleanup_old_messages' );
		}
		add_action( 'wpmatch_cleanup_old_messages', array( __CLASS__, 'cleanup_old_messages' ) );

		// Push notification hooks.
		add_action( 'wpmatch_message_sent', array( __CLASS__, 'send_push_notification' ), 10, 3 );

		// Message moderation hooks.
		add_filter( 'wpmatch_message_content', array( __CLASS__, 'moderate_message_content' ) );
	}

	/**
	 * Register REST API routes for messaging.
	 */
	public static function register_api_routes() {
		register_rest_route(
			'wpmatch/v1',
			'/messages',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_conversation' ),
				'permission_callback' => array( __CLASS__, 'check_messaging_permissions' ),
				'args'                => array(
					'conversation_id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'page'            => array(
						'default'           => 1,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
					),
				),
			)
		);

		register_rest_route(
			'wpmatch/v1',
			'/messages',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'send_message' ),
				'permission_callback' => array( __CLASS__, 'check_messaging_permissions' ),
				'args'                => array(
					'recipient_id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'content'      => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_string( $param ) && strlen( $param ) <= self::MAX_MESSAGE_LENGTH;
						},
					),
					'message_type' => array(
						'default'           => 'text',
						'validate_callback' => function ( $param ) {
							return in_array( $param, array( 'text', 'image', 'emoji', 'gif' ), true );
						},
					),
				),
			)
		);

		register_rest_route(
			'wpmatch/v1',
			'/conversations',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_conversations' ),
				'permission_callback' => array( __CLASS__, 'check_messaging_permissions' ),
			)
		);

		register_rest_route(
			'wpmatch/v1',
			'/typing',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_typing_status' ),
				'permission_callback' => array( __CLASS__, 'check_messaging_permissions' ),
				'args'                => array(
					'conversation_id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'is_typing'       => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_bool( $param );
						},
					),
				),
			)
		);
	}

	/**
	 * Send a real-time message.
	 *
	 * @since 1.2.0
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public static function send_message( $request ) {
		$sender_id    = get_current_user_id();
		$recipient_id = (int) $request->get_param( 'recipient_id' );
		$content      = sanitize_textarea_field( $request->get_param( 'content' ) );
		$message_type = sanitize_text_field( $request->get_param( 'message_type' ) );

		// Rate limiting check.
		if ( ! self::check_rate_limit( $sender_id ) ) {
			return new WP_Error( 'rate_limited', 'Too many messages sent recently. Please wait before sending another message.', array( 'status' => 429 ) );
		}

		// Check if users can message each other.
		if ( ! self::can_users_message( $sender_id, $recipient_id ) ) {
			return new WP_Error( 'messaging_blocked', 'You cannot send messages to this user.', array( 'status' => 403 ) );
		}

		// Content moderation.
		$content = apply_filters( 'wpmatch_message_content', $content, $sender_id, $recipient_id );
		if ( empty( $content ) ) {
			return new WP_Error( 'content_blocked', 'Message content was filtered or blocked.', array( 'status' => 400 ) );
		}

		// Get or create conversation.
		$conversation_id = self::get_or_create_conversation( $sender_id, $recipient_id );

		// Store message in database.
		$message_id = self::store_message( $conversation_id, $sender_id, $content, $message_type );

		if ( ! $message_id ) {
			return new WP_Error( 'message_failed', 'Failed to send message.', array( 'status' => 500 ) );
		}

		// Send real-time notification via WebSocket.
		self::send_realtime_notification(
			$recipient_id,
			array(
				'type'            => 'new_message',
				'message_id'      => $message_id,
				'conversation_id' => $conversation_id,
				'sender_id'       => $sender_id,
				'content'         => $content,
				'message_type'    => $message_type,
				'timestamp'       => current_time( 'mysql' ),
			)
		);

		// Trigger hooks for notifications and analytics.
		do_action( 'wpmatch_message_sent', $sender_id, $recipient_id, $message_id );

		return new WP_REST_Response(
			array(
				'success'    => true,
				'message_id' => $message_id,
				'timestamp'  => current_time( 'mysql' ),
			),
			200
		);
	}

	/**
	 * Get conversation messages.
	 *
	 * @since 1.2.0
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public static function get_conversation( $request ) {
		$user_id         = get_current_user_id();
		$conversation_id = (int) $request->get_param( 'conversation_id' );
		$page            = (int) $request->get_param( 'page' );

		// Verify user has access to this conversation.
		if ( ! self::user_has_conversation_access( $user_id, $conversation_id ) ) {
			return new WP_Error( 'access_denied', 'You do not have access to this conversation.', array( 'status' => 403 ) );
		}

		global $wpdb;
		$messages_table = $wpdb->prefix . 'wpmatch_messages';

		$offset = ( $page - 1 ) * self::MESSAGE_BATCH_SIZE;
		$limit  = self::MESSAGE_BATCH_SIZE;

		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.message_id as id, m.sender_id, m.message_content as content, m.message_type, m.created_at as sent_at, m.read_at,
					   u.display_name, u.user_email
				FROM {$messages_table} m
				LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
				WHERE m.conversation_id = %s
				ORDER BY m.created_at DESC
				LIMIT %d OFFSET %d",
				$conversation_id,
				$limit,
				$offset
			)
		);

		// Mark messages as read.
		self::mark_messages_read( $conversation_id, $user_id );

		return new WP_REST_Response(
			array(
				'messages' => $messages,
				'page'     => $page,
				'has_more' => count( $messages ) === $limit,
			),
			200
		);
	}

	/**
	 * Get user's conversations list.
	 *
	 * @since 1.2.0
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response Response object.
	 */
	public static function get_conversations( $request ) {
		$user_id = get_current_user_id();

		global $wpdb;
		$conversations_table = $wpdb->prefix . 'wpmatch_conversations';
		$messages_table      = $wpdb->prefix . 'wpmatch_messages';

		$conversations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.conversation_id as id, c.user1_id, c.user2_id, c.created_at,
					   CASE
						   WHEN c.user1_id = %d THEN c.user2_id
						   ELSE c.user1_id
					   END as other_user_id,
					   u.display_name as other_user_name,
					   (SELECT COUNT(*) FROM {$messages_table} m
						WHERE m.conversation_id = c.conversation_id
						AND m.sender_id != %d
						AND m.read_at IS NULL) as unread_count,
					   (SELECT m.message_content FROM {$messages_table} m
						WHERE m.conversation_id = c.conversation_id
						ORDER BY m.created_at DESC LIMIT 1) as last_message,
					   (SELECT m.created_at FROM {$messages_table} m
						WHERE m.conversation_id = c.conversation_id
						ORDER BY m.created_at DESC LIMIT 1) as last_message_time
				FROM {$conversations_table} c
				LEFT JOIN {$wpdb->users} u ON (
					CASE
						WHEN c.user1_id = %d THEN c.user2_id
						ELSE c.user1_id
					END = u.ID
				)
				WHERE c.user1_id = %d OR c.user2_id = %d
				ORDER BY last_message_time DESC",
				$user_id,
				$user_id,
				$user_id,
				$user_id,
				$user_id
			)
		);

		return new WP_REST_Response(
			array(
				'conversations' => $conversations,
			),
			200
		);
	}

	/**
	 * Handle typing indicator status.
	 *
	 * @since 1.2.0
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response Response object.
	 */
	public static function handle_typing_status( $request ) {
		$user_id         = get_current_user_id();
		$conversation_id = (int) $request->get_param( 'conversation_id' );
		$is_typing       = (bool) $request->get_param( 'is_typing' );

		// Verify user has access to this conversation.
		if ( ! self::user_has_conversation_access( $user_id, $conversation_id ) ) {
			return new WP_Error( 'access_denied', 'You do not have access to this conversation.', array( 'status' => 403 ) );
		}

		// Get the other user in the conversation.
		$other_user_id = self::get_conversation_other_user( $conversation_id, $user_id );

		if ( $other_user_id ) {
			// Send typing indicator via WebSocket.
			self::send_realtime_notification(
				$other_user_id,
				array(
					'type'            => 'typing_indicator',
					'conversation_id' => $conversation_id,
					'user_id'         => $user_id,
					'is_typing'       => $is_typing,
					'timestamp'       => current_time( 'mysql' ),
				)
			);
		}

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Check if user has permission to use messaging.
	 *
	 * @since 1.2.0
	 * @param WP_REST_Request $request The request object.
	 * @return bool Whether user has permission.
	 */
	public static function check_messaging_permissions( $request ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// Check if user has messaging privileges.
		$user_id = get_current_user_id();
		return apply_filters( 'wpmatch_user_can_message', true, $user_id );
	}

	/**
	 * Check rate limiting for messages.
	 *
	 * @since 1.2.0
	 * @param int $user_id User ID.
	 * @return bool Whether user is within rate limits.
	 */
	private static function check_rate_limit( $user_id ) {
		global $wpdb;
		$messages_table = $wpdb->prefix . 'wpmatch_messages';

		$hour_ago      = date( 'Y-m-d H:i:s', strtotime( '-1 hour' ) );
		$message_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$messages_table}
				WHERE sender_id = %d AND created_at > %s",
				$user_id,
				$hour_ago
			)
		);

		return $message_count < self::MAX_MESSAGES_PER_HOUR;
	}

	/**
	 * Check if two users can message each other.
	 *
	 * @since 1.2.0
	 * @param int $user1_id First user ID.
	 * @param int $user2_id Second user ID.
	 * @return bool Whether users can message.
	 */
	private static function can_users_message( $user1_id, $user2_id ) {
		// Check if users exist.
		if ( ! get_userdata( $user1_id ) || ! get_userdata( $user2_id ) ) {
			return false;
		}

		// Check for blocks.
		if ( self::is_user_blocked( $user1_id, $user2_id ) ) {
			return false;
		}

		// Check if they have matched (optional requirement).
		$require_match = get_option( 'wpmatch_require_match_to_message', false );
		if ( $require_match && ! self::users_have_matched( $user1_id, $user2_id ) ) {
			return false;
		}

		return apply_filters( 'wpmatch_can_users_message', true, $user1_id, $user2_id );
	}

	/**
	 * Get or create conversation between two users.
	 *
	 * @since 1.2.0
	 * @param int $user1_id First user ID.
	 * @param int $user2_id Second user ID.
	 * @return int Conversation ID.
	 */
	private static function get_or_create_conversation( $user1_id, $user2_id ) {
		global $wpdb;
		$conversations_table = $wpdb->prefix . 'wpmatch_conversations';

		// Ensure consistent ordering.
		$min_user = min( $user1_id, $user2_id );
		$max_user = max( $user1_id, $user2_id );

		// Create conversation ID in format user1_user2.
		$conversation_id = $min_user . '_' . $max_user;

		// Try to find existing conversation.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT conversation_id FROM {$conversations_table}
				WHERE conversation_id = %s",
				$conversation_id
			)
		);

		if ( ! $existing ) {
			// Create new conversation.
			$wpdb->insert(
				$conversations_table,
				array(
					'conversation_id' => $conversation_id,
					'user1_id'        => $min_user,
					'user2_id'        => $max_user,
					'created_at'      => current_time( 'mysql' ),
				),
				array( '%s', '%d', '%d', '%s' )
			);
		}

		return $conversation_id;
	}

	/**
	 * Store message in database.
	 *
	 * @since 1.2.0
	 * @param string $conversation_id Conversation ID.
	 * @param int    $sender_id Sender user ID.
	 * @param string $content Message content.
	 * @param string $message_type Message type.
	 * @return int|false Message ID or false on failure.
	 */
	private static function store_message( $conversation_id, $sender_id, $content, $message_type ) {
		global $wpdb;
		$messages_table = $wpdb->prefix . 'wpmatch_messages';

		// Get recipient ID from conversation_id format (user1_user2)
		$user_ids     = explode( '_', $conversation_id );
		$recipient_id = ( $user_ids[0] == $sender_id ) ? $user_ids[1] : $user_ids[0];

		$result = $wpdb->insert(
			$messages_table,
			array(
				'conversation_id' => $conversation_id,
				'sender_id'       => $sender_id,
				'recipient_id'    => $recipient_id,
				'message_content' => $content,
				'message_type'    => $message_type,
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Send real-time notification via WebSocket.
	 *
	 * @since 1.2.0
	 * @param int   $user_id Target user ID.
	 * @param array $data Notification data.
	 */
	private static function send_realtime_notification( $user_id, $data ) {
		// This would integrate with a WebSocket server (like Socket.IO or Pusher).
		// For now, we'll store the notification for polling-based retrieval.

		global $wpdb;
		$notifications_table = $wpdb->prefix . 'wpmatch_realtime_notifications';

		$wpdb->insert(
			$notifications_table,
			array(
				'user_id'    => $user_id,
				'data'       => wp_json_encode( $data ),
				'created_at' => current_time( 'mysql' ),
				'delivered'  => 0,
			),
			array( '%d', '%s', '%s', '%d' )
		);

		// Hook for real WebSocket implementation.
		do_action( 'wpmatch_send_websocket_notification', $user_id, $data );
	}

	/**
	 * Send push notification for new message.
	 *
	 * @since 1.2.0
	 * @param int $sender_id Sender user ID.
	 * @param int $recipient_id Recipient user ID.
	 * @param int $message_id Message ID.
	 */
	public static function send_push_notification( $sender_id, $recipient_id, $message_id ) {
		// Check if recipient has push notifications enabled.
		$push_enabled = get_user_meta( $recipient_id, 'wpmatch_push_notifications', true );
		if ( ! $push_enabled ) {
			return;
		}

		$sender = get_userdata( $sender_id );
		if ( ! $sender ) {
			return;
		}

		$notification_data = array(
			'title' => sprintf( 'New message from %s', $sender->display_name ),
			'body'  => 'You have received a new message',
			'icon'  => get_avatar_url( $sender_id ),
			'data'  => array(
				'type'       => 'new_message',
				'message_id' => $message_id,
				'sender_id'  => $sender_id,
			),
		);

		// Hook for push notification service integration.
		do_action( 'wpmatch_send_push_notification', $recipient_id, $notification_data );
	}

	/**
	 * Moderate message content.
	 *
	 * @since 1.2.0
	 * @param string $content Message content.
	 * @return string Moderated content.
	 */
	public static function moderate_message_content( $content ) {
		// Basic profanity filter.
		$profanity_words = get_option( 'wpmatch_profanity_words', array() );

		foreach ( $profanity_words as $word ) {
			$content = str_ireplace( $word, str_repeat( '*', strlen( $word ) ), $content );
		}

		// Remove potential XSS.
		$content = wp_kses( $content, array() );

		// Check for spam patterns.
		if ( self::is_spam_content( $content ) ) {
			return '';
		}

		return $content;
	}

	/**
	 * Check if content appears to be spam.
	 *
	 * @since 1.2.0
	 * @param string $content Message content.
	 * @return bool Whether content is spam.
	 */
	private static function is_spam_content( $content ) {
		// Check for excessive URLs.
		$url_count = preg_match_all( '/https?:\/\//', $content );
		if ( $url_count > 2 ) {
			return true;
		}

		// Check for excessive repeated characters.
		if ( preg_match( '/(.)\1{10,}/', $content ) ) {
			return true;
		}

		// Check for excessive capital letters.
		$capital_ratio = strlen( preg_replace( '/[^A-Z]/', '', $content ) ) / strlen( $content );
		if ( $capital_ratio > 0.7 && strlen( $content ) > 20 ) {
			return true;
		}

		return false;
	}

	/**
	 * Create database tables for messaging.
	 *
	 * @since 1.2.0
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Check if messages table already exists (created by main activator)
		$messages_table = $wpdb->prefix . 'wpmatch_messages';
		$table_exists   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $messages_table ) ) === $messages_table;

		// Only create realtime-specific tables, not the main messages table
		// Real-time notifications table.
		$notifications_table = $wpdb->prefix . 'wpmatch_realtime_notifications';
		$sql_notifications   = "CREATE TABLE IF NOT EXISTS $notifications_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			data longtext NOT NULL,
			created_at datetime NOT NULL,
			delivered tinyint(1) DEFAULT 0,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY created_at (created_at),
			KEY delivered (delivered)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_notifications );

		// Add missing columns to existing messages table if needed
		if ( $table_exists ) {
			self::update_messages_table_schema();
		}
	}

	/**
	 * Update messages table schema to support realtime messaging.
	 *
	 * @since 1.2.0
	 */
	private static function update_messages_table_schema() {
		global $wpdb;

		$messages_table = $wpdb->prefix . 'wpmatch_messages';

		// Check if required columns exist and add them if missing
		$columns = $wpdb->get_col( "DESCRIBE {$messages_table}", 0 );

		// Add content column if missing (maps to message_content)
		if ( ! in_array( 'content', $columns, true ) && in_array( 'message_content', $columns, true ) ) {
			// Content already exists as message_content, no need to add
		}

		// Add sent_at column if missing (maps to created_at)
		if ( ! in_array( 'sent_at', $columns, true ) && in_array( 'created_at', $columns, true ) ) {
			// sent_at already exists as created_at, no need to add
		}

		// Update conversation_id to support both string and bigint formats
		$conversation_column = $wpdb->get_row( "SHOW COLUMNS FROM {$messages_table} LIKE 'conversation_id'" );
		if ( $conversation_column && strpos( $conversation_column->Type, 'varchar' ) !== false ) {
			// Keep existing varchar format for compatibility
		}
	}

	/**
	 * Cleanup old messages and notifications.
	 *
	 * @since 1.2.0
	 */
	public static function cleanup_old_messages() {
		global $wpdb;

		// Delete old notifications (older than 7 days).
		$notifications_table = $wpdb->prefix . 'wpmatch_realtime_notifications';
		$wpdb->query(
			"DELETE FROM {$notifications_table}
			WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
		);

		// Optional: Archive old messages (older than 1 year).
		$archive_enabled = get_option( 'wpmatch_archive_old_messages', false );
		if ( $archive_enabled ) {
			$messages_table = $wpdb->prefix . 'wpmatch_messages';
			$wpdb->query(
				"DELETE FROM {$messages_table}
				WHERE sent_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)"
			);
		}
	}

	/**
	 * Helper methods for conversation management.
	 */

	/**
	 * Check if user has access to conversation.
	 *
	 * @since 1.2.0
	 * @param int $user_id User ID.
	 * @param int $conversation_id Conversation ID.
	 * @return bool Whether user has access.
	 */
	private static function user_has_conversation_access( $user_id, $conversation_id ) {
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
	 * Get the other user in a conversation.
	 *
	 * @since 1.2.0
	 * @param int $conversation_id Conversation ID.
	 * @param int $user_id Current user ID.
	 * @return int|false Other user ID or false.
	 */
	private static function get_conversation_other_user( $conversation_id, $user_id ) {
		global $wpdb;
		$conversations_table = $wpdb->prefix . 'wpmatch_conversations';

		$conversation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT user1_id, user2_id FROM {$conversations_table} WHERE conversation_id = %s",
				$conversation_id
			)
		);

		if ( ! $conversation ) {
			return false;
		}

		return $conversation->user1_id === $user_id ? $conversation->user2_id : $conversation->user1_id;
	}

	/**
	 * Mark messages as read.
	 *
	 * @since 1.2.0
	 * @param int $conversation_id Conversation ID.
	 * @param int $user_id User ID.
	 */
	private static function mark_messages_read( $conversation_id, $user_id ) {
		global $wpdb;
		$messages_table = $wpdb->prefix . 'wpmatch_messages';

		$wpdb->update(
			$messages_table,
			array( 'read_at' => current_time( 'mysql' ) ),
			array(
				'conversation_id' => $conversation_id,
				'read_at'         => null,
			),
			array( '%s' ),
			array( '%d', '%s' )
		);
	}

	/**
	 * Check if user is blocked.
	 *
	 * @since 1.2.0
	 * @param int $user1_id First user ID.
	 * @param int $user2_id Second user ID.
	 * @return bool Whether user is blocked.
	 */
	private static function is_user_blocked( $user1_id, $user2_id ) {
		// This would check a blocks table - simplified for now.
		$blocked_users = get_user_meta( $user1_id, 'wpmatch_blocked_users', true );
		if ( ! is_array( $blocked_users ) ) {
			$blocked_users = array();
		}

		return in_array( $user2_id, $blocked_users, true );
	}

	/**
	 * Check if users have matched.
	 *
	 * @since 1.2.0
	 * @param int $user1_id First user ID.
	 * @param int $user2_id Second user ID.
	 * @return bool Whether users have matched.
	 */
	private static function users_have_matched( $user1_id, $user2_id ) {
		global $wpdb;
		$matches_table = $wpdb->prefix . 'wpmatch_matches';

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$matches_table}
				WHERE (user1_id = %d AND user2_id = %d)
				OR (user1_id = %d AND user2_id = %d)",
				$user1_id,
				$user2_id,
				$user2_id,
				$user1_id
			)
		);

		return $count > 0;
	}

	/**
	 * AJAX handler methods.
	 */

	/**
	 * Handle AJAX send message request.
	 */
	public static function handle_send_message() {
		check_ajax_referer( 'wpmatch_messaging', 'nonce' );

		$sender_id    = get_current_user_id();
		$recipient_id = (int) $_POST['recipient_id'];
		$content      = sanitize_textarea_field( $_POST['content'] );

		if ( ! self::can_users_message( $sender_id, $recipient_id ) ) {
			wp_die( 'Unauthorized', 403 );
		}

		$conversation_id = self::get_or_create_conversation( $sender_id, $recipient_id );
		$message_id      = self::store_message( $conversation_id, $sender_id, $content, 'text' );

		if ( $message_id ) {
			wp_send_json_success(
				array(
					'message_id' => $message_id,
					'timestamp'  => current_time( 'mysql' ),
				)
			);
		} else {
			wp_send_json_error( 'Failed to send message' );
		}
	}

	/**
	 * Handle AJAX get messages request.
	 */
	public static function handle_get_messages() {
		check_ajax_referer( 'wpmatch_messaging', 'nonce' );

		$user_id         = get_current_user_id();
		$conversation_id = (int) $_POST['conversation_id'];

		if ( ! self::user_has_conversation_access( $user_id, $conversation_id ) ) {
			wp_die( 'Unauthorized', 403 );
		}

		global $wpdb;
		$messages_table = $wpdb->prefix . 'wpmatch_messages';

		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT message_id as id, sender_id, message_content as content, message_type, created_at as sent_at, read_at
				FROM {$messages_table}
				WHERE conversation_id = %s
				ORDER BY created_at DESC
				LIMIT %d",
				$conversation_id,
				self::MESSAGE_BATCH_SIZE
			)
		);

		wp_send_json_success( $messages );
	}

	/**
	 * Handle AJAX mark read request.
	 */
	public static function handle_mark_read() {
		check_ajax_referer( 'wpmatch_messaging', 'nonce' );

		$user_id         = get_current_user_id();
		$conversation_id = (int) $_POST['conversation_id'];

		if ( ! self::user_has_conversation_access( $user_id, $conversation_id ) ) {
			wp_die( 'Unauthorized', 403 );
		}

		self::mark_messages_read( $conversation_id, $user_id );
		wp_send_json_success();
	}

	/**
	 * Handle AJAX typing indicator request.
	 */
	public static function handle_typing_indicator() {
		check_ajax_referer( 'wpmatch_messaging', 'nonce' );

		$user_id         = get_current_user_id();
		$conversation_id = (int) $_POST['conversation_id'];
		$is_typing       = (bool) $_POST['is_typing'];

		if ( ! self::user_has_conversation_access( $user_id, $conversation_id ) ) {
			wp_die( 'Unauthorized', 403 );
		}

		$other_user_id = self::get_conversation_other_user( $conversation_id, $user_id );

		if ( $other_user_id ) {
			self::send_realtime_notification(
				$other_user_id,
				array(
					'type'            => 'typing_indicator',
					'conversation_id' => $conversation_id,
					'user_id'         => $user_id,
					'is_typing'       => $is_typing,
				)
			);
		}

		wp_send_json_success();
	}
}
