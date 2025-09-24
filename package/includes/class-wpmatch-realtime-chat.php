<?php
/**
 * WPMatch Real-time Chat System
 *
 * WebSocket-based real-time messaging with video calling capabilities
 *
 * @package WPMatch
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPMatch_Realtime_Chat {

	private static $instance = null;

	const CHAT_TABLE = 'wpmatch_chat_rooms';
	const MESSAGES_TABLE = 'wpmatch_chat_messages';
	const TYPING_TABLE = 'wpmatch_chat_typing';
	const CALLS_TABLE = 'wpmatch_video_calls';

	private $websocket_url;
	private $websocket_key;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->websocket_url = get_option( 'wpmatch_websocket_url', 'ws://localhost:3000' );
		$this->websocket_key = get_option( 'wpmatch_websocket_key', wp_generate_password( 32, false ) );
		$this->init_hooks();
	}

	private function init_hooks() {
		add_action( 'init', array( $this, 'create_chat_tables' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_chat_scripts' ) );

		// AJAX handlers
		add_action( 'wp_ajax_wpmatch_send_message', array( $this, 'ajax_send_message' ) );
		add_action( 'wp_ajax_wpmatch_get_messages', array( $this, 'ajax_get_messages' ) );
		add_action( 'wp_ajax_wpmatch_create_chat_room', array( $this, 'ajax_create_chat_room' ) );
		add_action( 'wp_ajax_wpmatch_typing_indicator', array( $this, 'ajax_typing_indicator' ) );
		add_action( 'wp_ajax_wpmatch_mark_read', array( $this, 'ajax_mark_read' ) );
		add_action( 'wp_ajax_wpmatch_upload_media', array( $this, 'ajax_upload_media' ) );

		// Video calling
		add_action( 'wp_ajax_wpmatch_initiate_call', array( $this, 'ajax_initiate_call' ) );
		add_action( 'wp_ajax_wpmatch_answer_call', array( $this, 'ajax_answer_call' ) );
		add_action( 'wp_ajax_wpmatch_end_call', array( $this, 'ajax_end_call' ) );
		add_action( 'wp_ajax_wpmatch_get_call_token', array( $this, 'ajax_get_call_token' ) );

		// WebSocket management
		add_action( 'wp_ajax_wpmatch_get_ws_token', array( $this, 'ajax_get_ws_token' ) );

		// Cleanup
		add_action( 'wpmatch_cleanup_chat_data', array( $this, 'cleanup_chat_data' ) );

		// Schedule cleanup if not already scheduled
		if ( ! wp_next_scheduled( 'wpmatch_cleanup_chat_data' ) ) {
			wp_schedule_event( time(), 'daily', 'wpmatch_cleanup_chat_data' );
		}
	}

	public function create_chat_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Chat rooms table
		$chat_rooms_table = $wpdb->prefix . self::CHAT_TABLE;
		$chat_rooms_sql = "CREATE TABLE IF NOT EXISTS $chat_rooms_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			room_id varchar(100) NOT NULL,
			user1_id bigint(20) NOT NULL,
			user2_id bigint(20) NOT NULL,
			last_message_id bigint(20) DEFAULT NULL,
			last_activity datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY room_id (room_id),
			KEY user1_id (user1_id),
			KEY user2_id (user2_id),
			KEY last_activity (last_activity),
			KEY status (status)
		) $charset_collate;";

		// Chat messages table
		$messages_table = $wpdb->prefix . self::MESSAGES_TABLE;
		$messages_sql = "CREATE TABLE IF NOT EXISTS $messages_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			room_id varchar(100) NOT NULL,
			sender_id bigint(20) NOT NULL,
			message_type varchar(20) NOT NULL DEFAULT 'text',
			content longtext NOT NULL,
			media_url varchar(500) DEFAULT NULL,
			media_type varchar(50) DEFAULT NULL,
			read_by_recipient tinyint(1) NOT NULL DEFAULT 0,
			read_at datetime DEFAULT NULL,
			edited_at datetime DEFAULT NULL,
			deleted_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY room_id (room_id),
			KEY sender_id (sender_id),
			KEY message_type (message_type),
			KEY created_at (created_at),
			KEY read_by_recipient (read_by_recipient)
		) $charset_collate;";

		// Typing indicators table
		$typing_table = $wpdb->prefix . self::TYPING_TABLE;
		$typing_sql = "CREATE TABLE IF NOT EXISTS $typing_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			room_id varchar(100) NOT NULL,
			user_id bigint(20) NOT NULL,
			is_typing tinyint(1) NOT NULL DEFAULT 1,
			expires_at datetime NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY room_user (room_id, user_id),
			KEY expires_at (expires_at)
		) $charset_collate;";

		// Video calls table
		$calls_table = $wpdb->prefix . self::CALLS_TABLE;
		$calls_sql = "CREATE TABLE IF NOT EXISTS $calls_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			call_id varchar(100) NOT NULL,
			room_id varchar(100) NOT NULL,
			caller_id bigint(20) NOT NULL,
			callee_id bigint(20) NOT NULL,
			call_type varchar(20) NOT NULL DEFAULT 'video',
			status varchar(20) NOT NULL DEFAULT 'initiated',
			started_at datetime DEFAULT NULL,
			ended_at datetime DEFAULT NULL,
			duration int(11) DEFAULT NULL,
			end_reason varchar(50) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY call_id (call_id),
			KEY room_id (room_id),
			KEY caller_id (caller_id),
			KEY callee_id (callee_id),
			KEY status (status),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $chat_rooms_sql );
		dbDelta( $messages_sql );
		dbDelta( $typing_sql );
		dbDelta( $calls_sql );
	}

	public function enqueue_chat_scripts() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		wp_enqueue_script( 'wpmatch-realtime-chat',
			plugins_url( 'public/js/wpmatch-realtime-chat.js', dirname( __FILE__ ) ),
			array( 'jquery' ), '1.0.0', true
		);

		wp_enqueue_script( 'wpmatch-video-chat',
			plugins_url( 'public/js/wpmatch-video-chat.js', dirname( __FILE__ ) ),
			array( 'jquery' ), '1.0.0', true
		);

		wp_localize_script( 'wpmatch-realtime-chat', 'wpMatchChat', array(
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'websocketUrl' => $this->websocket_url,
			'nonce'        => wp_create_nonce( 'wpmatch_chat_nonce' ),
			'userId'       => get_current_user_id(),
			'strings'      => array(
				'typing'         => __( 'is typing...', 'wpmatch' ),
				'online'         => __( 'Online', 'wpmatch' ),
				'offline'        => __( 'Offline', 'wpmatch' ),
				'messageDeleted' => __( 'Message deleted', 'wpmatch' ),
				'callIncoming'   => __( 'Incoming call from', 'wpmatch' ),
				'callConnecting' => __( 'Connecting...', 'wpmatch' ),
				'callEnded'      => __( 'Call ended', 'wpmatch' ),
			),
		) );

		wp_enqueue_style( 'wpmatch-chat-styles',
			plugins_url( 'public/css/wpmatch-chat.css', dirname( __FILE__ ) ),
			array(), '1.0.0'
		);
	}

	public function create_chat_room( $user1_id, $user2_id ) {
		global $wpdb;

		// Ensure consistent ordering for room ID
		$user_ids = array( $user1_id, $user2_id );
		sort( $user_ids );
		$room_id = 'room_' . implode( '_', $user_ids );

		// Check if room already exists
		$existing_room = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}wpmatch_chat_rooms WHERE room_id = %s",
			$room_id
		) );

		if ( $existing_room ) {
			return $room_id;
		}

		// Create new room
		$result = $wpdb->insert(
			$wpdb->prefix . self::CHAT_TABLE,
			array(
				'room_id'  => $room_id,
				'user1_id' => min( $user1_id, $user2_id ),
				'user2_id' => max( $user1_id, $user2_id ),
				'status'   => 'active',
			),
			array( '%s', '%d', '%d', '%s' )
		);

		if ( $result ) {
			// Notify WebSocket server about new room
			$this->notify_websocket_server( 'room_created', array(
				'room_id'  => $room_id,
				'user1_id' => min( $user1_id, $user2_id ),
				'user2_id' => max( $user1_id, $user2_id ),
			) );

			return $room_id;
		}

		return false;
	}

	public function send_message( $room_id, $sender_id, $content, $message_type = 'text', $media_data = null ) {
		global $wpdb;

		// Validate room access
		if ( ! $this->user_has_room_access( $sender_id, $room_id ) ) {
			return false;
		}

		// Security: Sanitize content
		$content = sanitize_textarea_field( $content );

		// Handle media uploads
		$media_url = null;
		$media_type = null;

		if ( $media_data && 'media' === $message_type ) {
			$upload_result = $this->handle_media_upload( $media_data );
			if ( $upload_result ) {
				$media_url = $upload_result['url'];
				$media_type = $upload_result['type'];
			} else {
				return false;
			}
		}

		// Insert message
		$message_id = $wpdb->insert(
			$wpdb->prefix . self::MESSAGES_TABLE,
			array(
				'room_id'      => $room_id,
				'sender_id'    => $sender_id,
				'message_type' => $message_type,
				'content'      => $content,
				'media_url'    => $media_url,
				'media_type'   => $media_type,
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( ! $message_id ) {
			return false;
		}

		// Update room last activity
		$wpdb->update(
			$wpdb->prefix . self::CHAT_TABLE,
			array(
				'last_message_id' => $message_id,
				'last_activity'   => current_time( 'mysql' ),
			),
			array( 'room_id' => $room_id ),
			array( '%d', '%s' ),
			array( '%s' )
		);

		// Get message data for real-time delivery
		$message_data = $this->get_message_data( $message_id );

		// Send to WebSocket server for real-time delivery
		$this->notify_websocket_server( 'new_message', array(
			'room_id' => $room_id,
			'message' => $message_data,
		) );

		// Trigger push notification
		$this->trigger_push_notification( $room_id, $sender_id, $content, $message_type );

		return $message_id;
	}

	public function get_messages( $room_id, $user_id, $limit = 50, $offset = 0 ) {
		global $wpdb;

		// Validate room access
		if ( ! $this->user_has_room_access( $user_id, $room_id ) ) {
			return false;
		}

		$messages = $wpdb->get_results( $wpdb->prepare(
			"SELECT m.*, u.display_name as sender_name, u.user_email as sender_email
			FROM {$wpdb->prefix}wpmatch_chat_messages m
			JOIN {$wpdb->users} u ON m.sender_id = u.ID
			WHERE m.room_id = %s AND m.deleted_at IS NULL
			ORDER BY m.created_at DESC
			LIMIT %d OFFSET %d",
			$room_id,
			$limit,
			$offset
		) );

		// Format messages for frontend
		$formatted_messages = array();
		foreach ( array_reverse( $messages ) as $message ) {
			$formatted_messages[] = $this->format_message( $message );
		}

		return $formatted_messages;
	}

	private function format_message( $message ) {
		return array(
			'id'           => $message->id,
			'room_id'      => $message->room_id,
			'sender_id'    => $message->sender_id,
			'sender_name'  => $message->sender_name,
			'message_type' => $message->message_type,
			'content'      => $message->content,
			'media_url'    => $message->media_url,
			'media_type'   => $message->media_type,
			'read'         => (bool) $message->read_by_recipient,
			'edited'       => ! is_null( $message->edited_at ),
			'timestamp'    => strtotime( $message->created_at ),
			'created_at'   => $message->created_at,
		);
	}

	private function get_message_data( $message_id ) {
		global $wpdb;

		$message = $wpdb->get_row( $wpdb->prepare(
			"SELECT m.*, u.display_name as sender_name
			FROM {$wpdb->prefix}wpmatch_chat_messages m
			JOIN {$wpdb->users} u ON m.sender_id = u.ID
			WHERE m.id = %d",
			$message_id
		) );

		return $message ? $this->format_message( $message ) : null;
	}

	public function mark_messages_read( $room_id, $user_id ) {
		global $wpdb;

		if ( ! $this->user_has_room_access( $user_id, $room_id ) ) {
			return false;
		}

		$result = $wpdb->update(
			$wpdb->prefix . self::MESSAGES_TABLE,
			array(
				'read_by_recipient' => 1,
				'read_at'           => current_time( 'mysql' ),
			),
			array(
				'room_id'           => $room_id,
				'read_by_recipient' => 0,
			),
			array( '%d', '%s' ),
			array( '%s', '%d' )
		);

		// Notify WebSocket about read status
		$this->notify_websocket_server( 'messages_read', array(
			'room_id' => $room_id,
			'user_id' => $user_id,
		) );

		return $result !== false;
	}

	public function set_typing_indicator( $room_id, $user_id, $is_typing = true ) {
		global $wpdb;

		if ( ! $this->user_has_room_access( $user_id, $room_id ) ) {
			return false;
		}

		$expires_at = gmdate( 'Y-m-d H:i:s', time() + 10 ); // Expires in 10 seconds

		if ( $is_typing ) {
			$wpdb->replace(
				$wpdb->prefix . self::TYPING_TABLE,
				array(
					'room_id'    => $room_id,
					'user_id'    => $user_id,
					'is_typing'  => 1,
					'expires_at' => $expires_at,
				),
				array( '%s', '%d', '%d', '%s' )
			);
		} else {
			$wpdb->delete(
				$wpdb->prefix . self::TYPING_TABLE,
				array(
					'room_id' => $room_id,
					'user_id' => $user_id,
				),
				array( '%s', '%d' )
			);
		}

		// Notify WebSocket about typing status
		$this->notify_websocket_server( 'typing_indicator', array(
			'room_id'   => $room_id,
			'user_id'   => $user_id,
			'is_typing' => $is_typing,
		) );

		return true;
	}

	public function initiate_video_call( $caller_id, $callee_id, $call_type = 'video' ) {
		global $wpdb;

		// Get or create chat room
		$room_id = $this->create_chat_room( $caller_id, $callee_id );
		if ( ! $room_id ) {
			return false;
		}

		$call_id = 'call_' . time() . '_' . wp_generate_password( 8, false );

		// Create call record
		$result = $wpdb->insert(
			$wpdb->prefix . self::CALLS_TABLE,
			array(
				'call_id'   => $call_id,
				'room_id'   => $room_id,
				'caller_id' => $caller_id,
				'callee_id' => $callee_id,
				'call_type' => $call_type,
				'status'    => 'initiated',
			),
			array( '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		if ( ! $result ) {
			return false;
		}

		// Generate WebRTC tokens/credentials
		$call_tokens = $this->generate_call_tokens( $call_id, $caller_id, $callee_id );

		// Notify callee via WebSocket
		$this->notify_websocket_server( 'incoming_call', array(
			'call_id'     => $call_id,
			'room_id'     => $room_id,
			'caller_id'   => $caller_id,
			'callee_id'   => $callee_id,
			'call_type'   => $call_type,
			'caller_name' => get_user_by( 'id', $caller_id )->display_name,
		) );

		// Send push notification
		$this->send_call_notification( $caller_id, $callee_id, $call_type );

		return array(
			'call_id' => $call_id,
			'tokens'  => $call_tokens,
		);
	}

	public function answer_video_call( $call_id, $user_id ) {
		global $wpdb;

		$call = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}wpmatch_video_calls WHERE call_id = %s",
			$call_id
		) );

		if ( ! $call || (int) $call->callee_id !== $user_id ) {
			return false;
		}

		// Update call status
		$wpdb->update(
			$wpdb->prefix . self::CALLS_TABLE,
			array(
				'status'     => 'answered',
				'started_at' => current_time( 'mysql' ),
			),
			array( 'call_id' => $call_id ),
			array( '%s', '%s' ),
			array( '%s' )
		);

		// Generate tokens for callee
		$call_tokens = $this->generate_call_tokens( $call_id, $call->caller_id, $call->callee_id );

		// Notify caller via WebSocket
		$this->notify_websocket_server( 'call_answered', array(
			'call_id'  => $call_id,
			'room_id'  => $call->room_id,
			'answerer' => $user_id,
		) );

		return array(
			'call_id' => $call_id,
			'tokens'  => $call_tokens,
		);
	}

	public function end_video_call( $call_id, $user_id, $reason = 'ended' ) {
		global $wpdb;

		$call = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}wpmatch_video_calls WHERE call_id = %s",
			$call_id
		) );

		if ( ! $call || ( (int) $call->caller_id !== $user_id && (int) $call->callee_id !== $user_id ) ) {
			return false;
		}

		$duration = null;
		if ( $call->started_at ) {
			$duration = time() - strtotime( $call->started_at );
		}

		// Update call record
		$wpdb->update(
			$wpdb->prefix . self::CALLS_TABLE,
			array(
				'status'     => 'ended',
				'ended_at'   => current_time( 'mysql' ),
				'duration'   => $duration,
				'end_reason' => $reason,
			),
			array( 'call_id' => $call_id ),
			array( '%s', '%s', '%d', '%s' ),
			array( '%s' )
		);

		// Notify other participant via WebSocket
		$this->notify_websocket_server( 'call_ended', array(
			'call_id'    => $call_id,
			'room_id'    => $call->room_id,
			'ended_by'   => $user_id,
			'duration'   => $duration,
			'end_reason' => $reason,
		) );

		return true;
	}

	private function generate_call_tokens( $call_id, $caller_id, $callee_id ) {
		// This would integrate with a WebRTC service like Twilio, Agora, or Jitsi
		// For demonstration, we'll return mock tokens

		return array(
			'ice_servers' => array(
				array(
					'urls' => array( 'stun:stun.l.google.com:19302' ),
				),
				array(
					'urls'       => array( 'turn:your-turn-server.com:3478' ),
					'username'   => 'wpmatch_' . $call_id,
					'credential' => wp_generate_password( 20, false ),
				),
			),
			'call_room'   => $call_id,
			'jwt_token'   => $this->generate_webrtc_jwt( $call_id, $caller_id, $callee_id ),
		);
	}

	private function generate_webrtc_jwt( $call_id, $caller_id, $callee_id ) {
		// Generate JWT token for WebRTC authentication
		$payload = array(
			'call_id'   => $call_id,
			'caller_id' => $caller_id,
			'callee_id' => $callee_id,
			'exp'       => time() + 3600, // 1 hour expiry
			'iat'       => time(),
		);

		// In production, use a proper JWT library
		return base64_encode( wp_json_encode( $payload ) );
	}

	private function user_has_room_access( $user_id, $room_id ) {
		global $wpdb;

		$room = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}wpmatch_chat_rooms WHERE room_id = %s",
			$room_id
		) );

		return $room && ( (int) $room->user1_id === $user_id || (int) $room->user2_id === $user_id );
	}

	private function handle_media_upload( $media_data ) {
		// Handle file upload with security checks
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
		}

		$upload_overrides = array(
			'test_form' => false,
			'mimes'     => array(
				'jpg|jpeg' => 'image/jpeg',
				'png'      => 'image/png',
				'gif'      => 'image/gif',
				'mp4'      => 'video/mp4',
				'webm'     => 'video/webm',
				'pdf'      => 'application/pdf',
			),
		);

		$uploaded_file = wp_handle_upload( $media_data, $upload_overrides );

		if ( isset( $uploaded_file['error'] ) ) {
			return false;
		}

		return array(
			'url'  => $uploaded_file['url'],
			'type' => $uploaded_file['type'],
		);
	}

	private function notify_websocket_server( $event_type, $data ) {
		// Send event to WebSocket server via HTTP API
		$payload = array(
			'event' => $event_type,
			'data'  => $data,
			'key'   => $this->websocket_key,
		);

		$response = wp_remote_post( str_replace( 'ws://', 'http://', $this->websocket_url ) . '/api/notify', array(
			'body'    => wp_json_encode( $payload ),
			'headers' => array( 'Content-Type' => 'application/json' ),
			'timeout' => 5,
		) );

		return ! is_wp_error( $response );
	}

	private function trigger_push_notification( $room_id, $sender_id, $content, $message_type ) {
		global $wpdb;

		// Get recipient ID
		$room = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}wpmatch_chat_rooms WHERE room_id = %s",
			$room_id
		) );

		if ( ! $room ) {
			return;
		}

		$recipient_id = ( (int) $room->user1_id === $sender_id ) ? $room->user2_id : $room->user1_id;
		$sender = get_user_by( 'id', $sender_id );

		// Trigger notification system
		do_action( 'wpmatch_send_notification', $recipient_id, array(
			'type'    => 'new_message',
			'title'   => sprintf( __( 'New message from %s', 'wpmatch' ), $sender->display_name ),
			'message' => 'media' === $message_type ? __( 'Sent a photo', 'wpmatch' ) : wp_trim_words( $content, 10 ),
			'data'    => array(
				'room_id'   => $room_id,
				'sender_id' => $sender_id,
			),
		) );
	}

	private function send_call_notification( $caller_id, $callee_id, $call_type ) {
		$caller = get_user_by( 'id', $caller_id );

		do_action( 'wpmatch_send_notification', $callee_id, array(
			'type'    => 'incoming_call',
			'title'   => sprintf( __( 'Incoming %s call', 'wpmatch' ), $call_type ),
			'message' => sprintf( __( '%s is calling you', 'wpmatch' ), $caller->display_name ),
			'data'    => array(
				'caller_id' => $caller_id,
				'call_type' => $call_type,
			),
		) );
	}

	public function cleanup_chat_data() {
		global $wpdb;

		// Clean up expired typing indicators
		$wpdb->delete(
			$wpdb->prefix . self::TYPING_TABLE,
			array( 'expires_at <' => current_time( 'mysql' ) ),
			array( '%s' )
		);

		// Clean up old ended calls (older than 30 days)
		$thirty_days_ago = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
		$wpdb->delete(
			$wpdb->prefix . self::CALLS_TABLE,
			array(
				'status'     => 'ended',
				'ended_at <' => $thirty_days_ago,
			),
			array( '%s', '%s' )
		);
	}

	// AJAX handlers
	public function ajax_create_chat_room() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpmatch_chat_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		$user_id = get_current_user_id();
		$target_user_id = absint( $_POST['target_user_id'] ?? 0 );

		if ( ! $user_id || ! $target_user_id ) {
			wp_send_json_error( array( 'message' => 'Invalid user IDs' ) );
		}

		$room_id = $this->create_chat_room( $user_id, $target_user_id );

		if ( $room_id ) {
			wp_send_json_success( array( 'room_id' => $room_id ) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to create chat room' ) );
		}
	}

	public function ajax_send_message() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpmatch_chat_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		$user_id = get_current_user_id();
		$room_id = sanitize_text_field( wp_unslash( $_POST['room_id'] ?? '' ) );
		$content = sanitize_textarea_field( wp_unslash( $_POST['content'] ?? '' ) );
		$message_type = sanitize_text_field( wp_unslash( $_POST['message_type'] ?? 'text' ) );

		if ( ! $user_id || ! $room_id || ! $content ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters' ) );
		}

		$message_id = $this->send_message( $room_id, $user_id, $content, $message_type );

		if ( $message_id ) {
			$message_data = $this->get_message_data( $message_id );
			wp_send_json_success( array( 'message' => $message_data ) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to send message' ) );
		}
	}

	public function ajax_get_messages() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpmatch_chat_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		$user_id = get_current_user_id();
		$room_id = sanitize_text_field( wp_unslash( $_POST['room_id'] ?? '' ) );
		$limit = absint( $_POST['limit'] ?? 50 );
		$offset = absint( $_POST['offset'] ?? 0 );

		if ( ! $user_id || ! $room_id ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters' ) );
		}

		$messages = $this->get_messages( $room_id, $user_id, $limit, $offset );

		if ( $messages !== false ) {
			wp_send_json_success( array( 'messages' => $messages ) );
		} else {
			wp_send_json_error( array( 'message' => 'Access denied' ) );
		}
	}

	public function ajax_typing_indicator() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpmatch_chat_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		$user_id = get_current_user_id();
		$room_id = sanitize_text_field( wp_unslash( $_POST['room_id'] ?? '' ) );
		$is_typing = isset( $_POST['is_typing'] ) && $_POST['is_typing'] === 'true';

		if ( ! $user_id || ! $room_id ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters' ) );
		}

		$result = $this->set_typing_indicator( $room_id, $user_id, $is_typing );

		if ( $result ) {
			wp_send_json_success();
		} else {
			wp_send_json_error( array( 'message' => 'Failed to update typing indicator' ) );
		}
	}

	public function ajax_mark_read() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpmatch_chat_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		$user_id = get_current_user_id();
		$room_id = sanitize_text_field( wp_unslash( $_POST['room_id'] ?? '' ) );

		if ( ! $user_id || ! $room_id ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters' ) );
		}

		$result = $this->mark_messages_read( $room_id, $user_id );

		if ( $result ) {
			wp_send_json_success();
		} else {
			wp_send_json_error( array( 'message' => 'Failed to mark messages as read' ) );
		}
	}

	public function ajax_initiate_call() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpmatch_chat_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		$caller_id = get_current_user_id();
		$callee_id = absint( $_POST['callee_id'] ?? 0 );
		$call_type = sanitize_text_field( wp_unslash( $_POST['call_type'] ?? 'video' ) );

		if ( ! $caller_id || ! $callee_id ) {
			wp_send_json_error( array( 'message' => 'Invalid user IDs' ) );
		}

		$result = $this->initiate_video_call( $caller_id, $callee_id, $call_type );

		if ( $result ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to initiate call' ) );
		}
	}

	public function ajax_answer_call() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpmatch_chat_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		$user_id = get_current_user_id();
		$call_id = sanitize_text_field( wp_unslash( $_POST['call_id'] ?? '' ) );

		if ( ! $user_id || ! $call_id ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters' ) );
		}

		$result = $this->answer_video_call( $call_id, $user_id );

		if ( $result ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to answer call' ) );
		}
	}

	public function ajax_end_call() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpmatch_chat_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		$user_id = get_current_user_id();
		$call_id = sanitize_text_field( wp_unslash( $_POST['call_id'] ?? '' ) );
		$reason = sanitize_text_field( wp_unslash( $_POST['reason'] ?? 'ended' ) );

		if ( ! $user_id || ! $call_id ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters' ) );
		}

		$result = $this->end_video_call( $call_id, $user_id, $reason );

		if ( $result ) {
			wp_send_json_success();
		} else {
			wp_send_json_error( array( 'message' => 'Failed to end call' ) );
		}
	}

	public function ajax_get_ws_token() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpmatch_chat_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => 'User not logged in' ) );
		}

		// Generate WebSocket authentication token
		$token = wp_generate_password( 32, false );
		set_transient( 'wpmatch_ws_token_' . $user_id, $token, 3600 ); // 1 hour expiry

		wp_send_json_success( array(
			'token'        => $token,
			'websocket_url' => $this->websocket_url,
		) );
	}

	public function ajax_upload_media() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpmatch_chat_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => 'User not logged in' ) );
		}

		if ( ! isset( $_FILES['media'] ) ) {
			wp_send_json_error( array( 'message' => 'No file uploaded' ) );
		}

		$upload_result = $this->handle_media_upload( $_FILES['media'] );

		if ( $upload_result ) {
			wp_send_json_success( $upload_result );
		} else {
			wp_send_json_error( array( 'message' => 'File upload failed' ) );
		}
	}

	public function ajax_get_call_token() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpmatch_chat_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		$user_id = get_current_user_id();
		$call_id = sanitize_text_field( wp_unslash( $_POST['call_id'] ?? '' ) );

		if ( ! $user_id || ! $call_id ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters' ) );
		}

		// Verify user has access to this call
		global $wpdb;
		$call = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}wpmatch_video_calls WHERE call_id = %s",
			$call_id
		) );

		if ( ! $call || ( (int) $call->caller_id !== $user_id && (int) $call->callee_id !== $user_id ) ) {
			wp_send_json_error( array( 'message' => 'Access denied' ) );
		}

		$tokens = $this->generate_call_tokens( $call_id, $call->caller_id, $call->callee_id );

		wp_send_json_success( array( 'tokens' => $tokens ) );
	}
}