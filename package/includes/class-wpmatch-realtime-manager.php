<?php
/**
 * WPMatch Realtime Manager
 *
 * Handles WebSocket connections and realtime features.
 *
 * @package WPMatch
 * @since 1.7.0
 */

/**
 * Realtime Manager class.
 */
class WPMatch_Realtime_Manager {

	/**
	 * Instance of the class.
	 *
	 * @var WPMatch_Realtime_Manager
	 */
	private static $instance = null;

	/**
	 * WebSocket server configuration.
	 *
	 * @var array
	 */
	private $websocket_config = array();

	/**
	 * Active connections.
	 *
	 * @var array
	 */
	private $connections = array();

	/**
	 * Event handlers.
	 *
	 * @var array
	 */
	private $event_handlers = array();

	/**
	 * Get the single instance.
	 *
	 * @return WPMatch_Realtime_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_websocket_config();
		$this->register_event_handlers();
		$this->init_hooks();
	}

	/**
	 * Initialize WebSocket configuration.
	 */
	private function init_websocket_config() {
		$this->websocket_config = array(
			'enabled'    => get_option( 'wpmatch_websocket_enabled', false ),
			'host'       => get_option( 'wpmatch_websocket_host', 'localhost' ),
			'port'       => get_option( 'wpmatch_websocket_port', 8080 ),
			'ssl'        => get_option( 'wpmatch_websocket_ssl', false ),
			'auth_token' => get_option( 'wpmatch_websocket_auth_token', wp_generate_password( 32, false ) ),
		);

		// Save the auth token if it was generated.
		if ( ! get_option( 'wpmatch_websocket_auth_token' ) ) {
			update_option( 'wpmatch_websocket_auth_token', $this->websocket_config['auth_token'] );
		}
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Add WebSocket client script to frontend.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_websocket_script' ) );

		// Add WebSocket configuration to JavaScript.
		add_action( 'wp_footer', array( $this, 'output_websocket_config' ) );

		// REST API endpoints for WebSocket management.
		add_action( 'rest_api_init', array( $this, 'register_websocket_endpoints' ) );

		// Handle WordPress actions that should trigger realtime events.
		add_action( 'wpmatch_new_match', array( $this, 'handle_new_match' ), 10, 2 );
		add_action( 'wpmatch_new_message', array( $this, 'handle_new_message' ), 10, 3 );
		add_action( 'wpmatch_user_online', array( $this, 'handle_user_online' ), 10, 1 );
		add_action( 'wpmatch_user_offline', array( $this, 'handle_user_offline' ), 10, 1 );
		add_action( 'wpmatch_typing_start', array( $this, 'handle_typing_start' ), 10, 2 );
		add_action( 'wpmatch_typing_stop', array( $this, 'handle_typing_stop' ), 10, 2 );
		add_action( 'wpmatch_new_like', array( $this, 'handle_new_like' ), 10, 2 );
		add_action( 'wpmatch_new_super_like', array( $this, 'handle_new_super_like' ), 10, 2 );
		add_action( 'wpmatch_video_call_request', array( $this, 'handle_video_call_request' ), 10, 3 );
		add_action( 'wpmatch_event_update', array( $this, 'handle_event_update' ), 10, 2 );
		add_action( 'wpmatch_achievement_unlocked', array( $this, 'handle_achievement_unlocked' ), 10, 2 );
	}

	/**
	 * Register event handlers.
	 */
	private function register_event_handlers() {
		$this->event_handlers = array(
			'new_match'            => array( $this, 'broadcast_new_match' ),
			'new_message'          => array( $this, 'broadcast_new_message' ),
			'user_online'          => array( $this, 'broadcast_user_status' ),
			'user_offline'         => array( $this, 'broadcast_user_status' ),
			'typing_start'         => array( $this, 'broadcast_typing_status' ),
			'typing_stop'          => array( $this, 'broadcast_typing_status' ),
			'new_like'             => array( $this, 'broadcast_new_like' ),
			'new_super_like'       => array( $this, 'broadcast_new_super_like' ),
			'video_call_request'   => array( $this, 'broadcast_video_call_request' ),
			'video_call_accepted'  => array( $this, 'broadcast_video_call_accepted' ),
			'video_call_rejected'  => array( $this, 'broadcast_video_call_rejected' ),
			'video_call_ended'     => array( $this, 'broadcast_video_call_ended' ),
			'event_update'         => array( $this, 'broadcast_event_update' ),
			'achievement_unlocked' => array( $this, 'broadcast_achievement_unlocked' ),
			'location_update'      => array( $this, 'broadcast_location_update' ),
			'voice_note_uploaded'  => array( $this, 'broadcast_voice_note_uploaded' ),
			'voice_note_reaction'  => array( $this, 'broadcast_voice_note_reaction' ),
		);
	}

	/**
	 * Enqueue WebSocket client script.
	 */
	public function enqueue_websocket_script() {
		if ( ! is_user_logged_in() || ! $this->websocket_config['enabled'] ) {
			return;
		}

		wp_enqueue_script(
			'wpmatch-websocket',
			WPMATCH_PLUGIN_URL . 'public/js/wpmatch-websocket.js',
			array( 'jquery' ),
			WPMATCH_VERSION,
			true
		);
	}

	/**
	 * Output WebSocket configuration to JavaScript.
	 */
	public function output_websocket_config() {
		if ( ! is_user_logged_in() || ! $this->websocket_config['enabled'] ) {
			return;
		}

		$config = array(
			'enabled'    => true,
			'url'        => $this->get_websocket_url(),
			'auth_token' => $this->generate_user_auth_token(),
			'user_id'    => get_current_user_id(),
			'reconnect'  => true,
			'heartbeat'  => 30, // seconds
		);

		echo '<script type="text/javascript">';
		echo 'window.wpmatch_websocket = ' . wp_json_encode( $config ) . ';';
		echo '</script>';
	}

	/**
	 * Get WebSocket URL.
	 *
	 * @return string
	 */
	private function get_websocket_url() {
		$protocol = $this->websocket_config['ssl'] ? 'wss' : 'ws';
		$host     = $this->websocket_config['host'];
		$port     = $this->websocket_config['port'];

		return sprintf( '%s://%s:%d/wpmatch', $protocol, $host, $port );
	}

	/**
	 * Generate user authentication token for WebSocket.
	 *
	 * @return string
	 */
	private function generate_user_auth_token() {
		$user_id = get_current_user_id();
		$nonce   = wp_create_nonce( 'wpmatch_websocket_' . $user_id );
		$data    = array(
			'user_id' => $user_id,
			'nonce'   => $nonce,
			'expires' => time() + ( 2 * HOUR_IN_SECONDS ),
		);

		return base64_encode( wp_json_encode( $data ) );
	}

	/**
	 * Register WebSocket management endpoints.
	 */
	public function register_websocket_endpoints() {
		// WebSocket authentication endpoint.
		register_rest_route(
			'wpmatch/v1',
			'/websocket/auth',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'websocket_auth' ),
				'permission_callback' => function () {
					return is_user_logged_in();
				},
			)
		);

		// Send realtime event endpoint.
		register_rest_route(
			'wpmatch/v1',
			'/websocket/send',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'send_realtime_event' ),
				'permission_callback' => function () {
					return is_user_logged_in();
				},
				'args'                => array(
					'event' => array(
						'required' => true,
						'type'     => 'string',
					),
					'data'  => array(
						'type' => 'object',
					),
					'to'    => array(
						'type' => 'array',
					),
				),
			)
		);

		// Get online users endpoint.
		register_rest_route(
			'wpmatch/v1',
			'/websocket/online',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_online_users' ),
				'permission_callback' => function () {
					return is_user_logged_in();
				},
			)
		);
	}

	/**
	 * WebSocket authentication endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function websocket_auth( $request ) {
		$token = $this->generate_user_auth_token();

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'token'         => $token,
					'expires'       => time() + ( 2 * HOUR_IN_SECONDS ),
					'websocket_url' => $this->get_websocket_url(),
				),
			)
		);
	}

	/**
	 * Send realtime event endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function send_realtime_event( $request ) {
		$event = sanitize_text_field( $request->get_param( 'event' ) );
		$data  = $request->get_param( 'data' );
		$to    = $request->get_param( 'to' );

		if ( ! $this->is_valid_event( $event ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => 'Invalid event type',
				)
			);
		}

		$result = $this->send_event( $event, $data, $to );

		return rest_ensure_response(
			array(
				'success' => $result,
				'message' => $result ? 'Event sent successfully' : 'Failed to send event',
			)
		);
	}

	/**
	 * Get online users endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_online_users( $request ) {
		$online_users = $this->get_online_user_list();

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $online_users,
			)
		);
	}

	/**
	 * Get list of online users.
	 *
	 * @return array
	 */
	private function get_online_user_list() {
		// Get users who were active in the last 5 minutes.
		$online_users = get_transient( 'wpmatch_online_users' );

		if ( false === $online_users ) {
			$online_users = array();
		}

		// Clean up expired entries.
		$current_time = time();
		foreach ( $online_users as $user_id => $last_seen ) {
			if ( $current_time - $last_seen > 300 ) { // 5 minutes.
				unset( $online_users[ $user_id ] );
			}
		}

		// Update transient.
		set_transient( 'wpmatch_online_users', $online_users, 600 ); // 10 minutes.

		return array_keys( $online_users );
	}

	/**
	 * Mark user as online.
	 *
	 * @param int $user_id User ID.
	 */
	public function mark_user_online( $user_id ) {
		$online_users = get_transient( 'wpmatch_online_users' );

		if ( false === $online_users ) {
			$online_users = array();
		}

		$online_users[ $user_id ] = time();

		set_transient( 'wpmatch_online_users', $online_users, 600 );

		// Trigger realtime event.
		do_action( 'wpmatch_user_online', $user_id );
	}

	/**
	 * Mark user as offline.
	 *
	 * @param int $user_id User ID.
	 */
	public function mark_user_offline( $user_id ) {
		$online_users = get_transient( 'wpmatch_online_users' );

		if ( false !== $online_users && isset( $online_users[ $user_id ] ) ) {
			unset( $online_users[ $user_id ] );
			set_transient( 'wpmatch_online_users', $online_users, 600 );
		}

		// Trigger realtime event.
		do_action( 'wpmatch_user_offline', $user_id );
	}

	/**
	 * Send event to WebSocket server.
	 *
	 * @param string $event Event name.
	 * @param mixed  $data Event data.
	 * @param array  $to Recipient user IDs.
	 * @return bool
	 */
	public function send_event( $event, $data = null, $to = null ) {
		if ( ! $this->websocket_config['enabled'] ) {
			// Fallback to database storage for later delivery.
			return $this->store_event_for_delivery( $event, $data, $to );
		}

		$payload = array(
			'event'     => $event,
			'data'      => $data,
			'to'        => $to,
			'from'      => get_current_user_id(),
			'timestamp' => time(),
			'auth'      => $this->websocket_config['auth_token'],
		);

		// Send to WebSocket server via HTTP API.
		$response = wp_remote_post(
			'http://' . $this->websocket_config['host'] . ':' . ( $this->websocket_config['port'] + 1 ) . '/send',
			array(
				'body'    => wp_json_encode( $payload ),
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $this->websocket_config['auth_token'],
				),
				'timeout' => 5,
			)
		);

		if ( is_wp_error( $response ) ) {
			// Fallback to database storage.
			return $this->store_event_for_delivery( $event, $data, $to );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		return 200 === $response_code;
	}

	/**
	 * Store event for later delivery.
	 *
	 * @param string $event Event name.
	 * @param mixed  $data Event data.
	 * @param array  $to Recipient user IDs.
	 * @return bool
	 */
	private function store_event_for_delivery( $event, $data, $to ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_realtime_notifications';

		$result = $wpdb->insert(
			$table_name,
			array(
				'event_type'    => $event,
				'event_data'    => maybe_serialize( $data ),
				'recipient_ids' => maybe_serialize( $to ),
				'sender_id'     => get_current_user_id(),
				'created_at'    => current_time( 'mysql' ),
				'delivered'     => 0,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%d' )
		);

		return false !== $result;
	}

	/**
	 * Check if event type is valid.
	 *
	 * @param string $event Event name.
	 * @return bool
	 */
	private function is_valid_event( $event ) {
		return isset( $this->event_handlers[ $event ] );
	}

	/**
	 * Handle new match event.
	 *
	 * @param int $user1_id First user ID.
	 * @param int $user2_id Second user ID.
	 */
	public function handle_new_match( $user1_id, $user2_id ) {
		$this->send_event(
			'new_match',
			array(
				'match_id' => $user1_id . '_' . $user2_id,
				'user1'    => $user1_id,
				'user2'    => $user2_id,
			),
			array( $user1_id, $user2_id )
		);
	}

	/**
	 * Handle new message event.
	 *
	 * @param int   $sender_id Sender user ID.
	 * @param int   $recipient_id Recipient user ID.
	 * @param array $message Message data.
	 */
	public function handle_new_message( $sender_id, $recipient_id, $message ) {
		$this->send_event(
			'new_message',
			array(
				'message'      => $message,
				'sender_id'    => $sender_id,
				'recipient_id' => $recipient_id,
			),
			array( $recipient_id )
		);
	}

	/**
	 * Handle user online event.
	 *
	 * @param int $user_id User ID.
	 */
	public function handle_user_online( $user_id ) {
		// Get user's matches to notify them.
		$matches = $this->get_user_matches( $user_id );

		$this->send_event(
			'user_online',
			array(
				'user_id' => $user_id,
				'status'  => 'online',
			),
			$matches
		);
	}

	/**
	 * Handle user offline event.
	 *
	 * @param int $user_id User ID.
	 */
	public function handle_user_offline( $user_id ) {
		// Get user's matches to notify them.
		$matches = $this->get_user_matches( $user_id );

		$this->send_event(
			'user_offline',
			array(
				'user_id' => $user_id,
				'status'  => 'offline',
			),
			$matches
		);
	}

	/**
	 * Handle typing start event.
	 *
	 * @param int $user_id User ID who started typing.
	 * @param int $to_user_id User ID being typed to.
	 */
	public function handle_typing_start( $user_id, $to_user_id ) {
		$this->send_event(
			'typing_start',
			array(
				'user_id'    => $user_id,
				'to_user_id' => $to_user_id,
			),
			array( $to_user_id )
		);
	}

	/**
	 * Handle typing stop event.
	 *
	 * @param int $user_id User ID who stopped typing.
	 * @param int $to_user_id User ID being typed to.
	 */
	public function handle_typing_stop( $user_id, $to_user_id ) {
		$this->send_event(
			'typing_stop',
			array(
				'user_id'    => $user_id,
				'to_user_id' => $to_user_id,
			),
			array( $to_user_id )
		);
	}

	/**
	 * Handle new like event.
	 *
	 * @param int $liker_id User who liked.
	 * @param int $liked_id User who was liked.
	 */
	public function handle_new_like( $liker_id, $liked_id ) {
		$this->send_event(
			'new_like',
			array(
				'liker_id' => $liker_id,
				'liked_id' => $liked_id,
				'type'     => 'like',
			),
			array( $liked_id )
		);
	}

	/**
	 * Handle new super like event.
	 *
	 * @param int $liker_id User who super liked.
	 * @param int $liked_id User who was super liked.
	 */
	public function handle_new_super_like( $liker_id, $liked_id ) {
		$this->send_event(
			'new_super_like',
			array(
				'liker_id' => $liker_id,
				'liked_id' => $liked_id,
				'type'     => 'super_like',
			),
			array( $liked_id )
		);
	}

	/**
	 * Handle video call request event.
	 *
	 * @param int    $caller_id User who initiated the call.
	 * @param int    $callee_id User being called.
	 * @param string $call_id Call ID.
	 */
	public function handle_video_call_request( $caller_id, $callee_id, $call_id ) {
		$this->send_event(
			'video_call_request',
			array(
				'caller_id' => $caller_id,
				'callee_id' => $callee_id,
				'call_id'   => $call_id,
			),
			array( $callee_id )
		);
	}

	/**
	 * Handle event update.
	 *
	 * @param int   $event_id Event ID.
	 * @param array $event_data Event data.
	 */
	public function handle_event_update( $event_id, $event_data ) {
		// Get event participants to notify them.
		$participants = $this->get_event_participants( $event_id );

		$this->send_event(
			'event_update',
			array(
				'event_id'   => $event_id,
				'event_data' => $event_data,
			),
			$participants
		);
	}

	/**
	 * Handle achievement unlocked event.
	 *
	 * @param int   $user_id User ID.
	 * @param array $achievement Achievement data.
	 */
	public function handle_achievement_unlocked( $user_id, $achievement ) {
		$this->send_event(
			'achievement_unlocked',
			array(
				'user_id'     => $user_id,
				'achievement' => $achievement,
			),
			array( $user_id )
		);
	}

	/**
	 * Get user matches for notifications.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	private function get_user_matches( $user_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_matches';

		$matches = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT CASE
					WHEN user1_id = %d THEN user2_id
					ELSE user1_id
				END as match_user_id
				FROM {$wpdb->prefix}wpmatch_matches
				WHERE (user1_id = %d OR user2_id = %d)
				AND status = 'active'",
				$user_id,
				$user_id,
				$user_id
			)
		);

		return array_map( 'intval', $matches );
	}

	/**
	 * Get event participants.
	 *
	 * @param int $event_id Event ID.
	 * @return array
	 */
	private function get_event_participants( $event_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_event_registrations';

		$participants = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->prefix}wpmatch_event_registrations WHERE event_id = %d AND status = 'confirmed'",
				$event_id
			)
		);

		return array_map( 'intval', $participants );
	}

	/**
	 * Start WebSocket server process.
	 *
	 * @return bool
	 */
	public function start_websocket_server() {
		// This would normally start a Node.js or PHP WebSocket server process.
		// For now, we'll create a simple server script and return true.
		return $this->create_websocket_server_script();
	}

	/**
	 * Create WebSocket server script.
	 *
	 * @return bool
	 */
	private function create_websocket_server_script() {
		$server_script = WPMATCH_PLUGIN_DIR . 'websocket-server.js';

		$script_content = '
const WebSocket = require("ws");
const http = require("http");
const url = require("url");

const config = {
    port: ' . $this->websocket_config['port'] . ',
    httpPort: ' . ( $this->websocket_config['port'] + 1 ) . ',
    authToken: "' . $this->websocket_config['auth_token'] . '"
};

// WebSocket server
const wss = new WebSocket.Server({ port: config.port });

// HTTP server for sending events
const httpServer = http.createServer((req, res) => {
    if (req.method === "POST" && req.url === "/send") {
        let body = "";

        req.on("data", chunk => {
            body += chunk.toString();
        });

        req.on("end", () => {
            try {
                const data = JSON.parse(body);

                // Verify auth token
                if (data.auth !== config.authToken) {
                    res.writeHead(401);
                    res.end("Unauthorized");
                    return;
                }

                // Broadcast to specified users or all
                broadcastEvent(data);

                res.writeHead(200);
                res.end("Event sent");
            } catch (error) {
                res.writeHead(400);
                res.end("Invalid JSON");
            }
        });
    } else {
        res.writeHead(404);
        res.end("Not found");
    }
});

httpServer.listen(config.httpPort);

const clients = new Map();

wss.on("connection", (ws, req) => {
    const userId = authenticateConnection(req);

    if (!userId) {
        ws.close(1008, "Authentication failed");
        return;
    }

    clients.set(userId, ws);

    ws.on("message", (message) => {
        try {
            const data = JSON.parse(message);
            handleClientMessage(userId, data);
        } catch (error) {
            console.error("Invalid message from client:", error);
        }
    });

    ws.on("close", () => {
        clients.delete(userId);
    });

    // Send welcome message
    ws.send(JSON.stringify({
        event: "connected",
        data: { userId: userId }
    }));
});

function authenticateConnection(req) {
    // Extract token from query parameters
    const query = url.parse(req.url, true).query;
    const token = query.token;

    if (!token) {
        return null;
    }

    try {
        // Decode token (in production, validate with WordPress)
        const decoded = JSON.parse(Buffer.from(token, "base64").toString());

        if (decoded.expires < Date.now() / 1000) {
            return null;
        }

        return decoded.user_id;
    } catch (error) {
        return null;
    }
}

function handleClientMessage(userId, data) {
    // Handle client-sent messages
    switch (data.event) {
        case "heartbeat":
            // Respond to heartbeat
            const client = clients.get(userId);
            if (client) {
                client.send(JSON.stringify({
                    event: "heartbeat_response",
                    data: { timestamp: Date.now() }
                }));
            }
            break;

        case "typing_start":
        case "typing_stop":
            // Relay typing indicators
            broadcastEvent({
                event: data.event,
                data: { ...data.data, user_id: userId },
                to: data.to
            });
            break;
    }
}

function broadcastEvent(eventData) {
    const message = JSON.stringify({
        event: eventData.event,
        data: eventData.data,
        timestamp: Date.now()
    });

    if (eventData.to && Array.isArray(eventData.to)) {
        // Send to specific users
        eventData.to.forEach(userId => {
            const client = clients.get(parseInt(userId));
            if (client && client.readyState === WebSocket.OPEN) {
                client.send(message);
            }
        });
    } else {
        // Broadcast to all connected clients
        clients.forEach((client) => {
            if (client.readyState === WebSocket.OPEN) {
                client.send(message);
            }
        });
    }
}

console.log(`WebSocket server started on port ${config.port}`);
console.log(`HTTP API server started on port ${config.httpPort}`);
';

		return file_put_contents( $server_script, $script_content ) !== false;
	}

	/**
	 * Get WebSocket configuration for admin.
	 *
	 * @return array
	 */
	public function get_websocket_config() {
		return $this->websocket_config;
	}

	/**
	 * Update WebSocket configuration.
	 *
	 * @param array $config Configuration array.
	 * @return bool
	 */
	public function update_websocket_config( $config ) {
		$valid_keys = array( 'enabled', 'host', 'port', 'ssl' );

		foreach ( $valid_keys as $key ) {
			if ( isset( $config[ $key ] ) ) {
				update_option( 'wpmatch_websocket_' . $key, $config[ $key ] );
				$this->websocket_config[ $key ] = $config[ $key ];
			}
		}

		return true;
	}
}

// Initialize the realtime manager.
WPMatch_Realtime_Manager::get_instance();
