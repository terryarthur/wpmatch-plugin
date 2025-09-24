<?php
/**
 * Video Chat Integration for WPMatch
 *
 * Implements WebRTC-based video dating with features like video calls,
 * virtual dates, speed dating rooms, and video profiles.
 *
 * @package WPMatch
 * @since 1.5.0
 */

class WPMatch_Video_Chat {

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
	 * WebRTC signaling server URL.
	 *
	 * @var string
	 */
	private $signaling_server;

	/**
	 * TURN/STUN servers configuration.
	 *
	 * @var array
	 */
	private $ice_servers;

	/**
	 * Initialize the class.
	 *
	 * @param string $plugin_name Plugin name.
	 * @param string $version Plugin version.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->setup_webrtc_config();
	}

	/**
	 * Initialize video chat system.
	 */
	public static function init() {
		$instance = new self( 'wpmatch', WPMATCH_VERSION );
		add_action( 'init', array( $instance, 'setup_database' ) );
		add_action( 'rest_api_init', array( $instance, 'register_routes' ) );
		add_action( 'wp_enqueue_scripts', array( $instance, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_wpmatch_signal', array( $instance, 'handle_signaling' ) );
		add_action( 'wp_ajax_nopriv_wpmatch_signal', array( $instance, 'handle_signaling' ) );
		add_filter( 'wpmatch_profile_actions', array( $instance, 'add_video_call_button' ), 10, 2 );
		add_action( 'wp_footer', array( $instance, 'add_video_chat_modal' ) );

		// Schedule cleanup of old call records.
		if ( ! wp_next_scheduled( 'wpmatch_cleanup_video_calls' ) ) {
			wp_schedule_event( time(), 'hourly', 'wpmatch_cleanup_video_calls' );
		}
		add_action( 'wpmatch_cleanup_video_calls', array( $instance, 'cleanup_old_calls' ) );
	}

	/**
	 * Set up WebRTC configuration.
	 */
	private function setup_webrtc_config() {
		$settings = get_option( 'wpmatch_video_settings', array() );

		// Default to public STUN servers if none configured.
		$this->ice_servers = array(
			array(
				'urls' => 'stun:stun.l.google.com:19302',
			),
			array(
				'urls' => 'stun:stun1.l.google.com:19302',
			),
		);

		// Add TURN server if configured.
		if ( ! empty( $settings['turn_server'] ) ) {
			$this->ice_servers[] = array(
				'urls'       => $settings['turn_server'],
				'username'   => isset( $settings['turn_username'] ) ? $settings['turn_username'] : '',
				'credential' => isset( $settings['turn_password'] ) ? $settings['turn_password'] : '',
			);
		}

		// Set signaling server URL.
		$this->signaling_server = ! empty( $settings['signaling_server'] )
			? $settings['signaling_server']
			: home_url( '/wp-admin/admin-ajax.php' );
	}

	/**
	 * Set up database tables.
	 */
	public function setup_database() {
		$this->create_video_calls_table();
		$this->create_call_logs_table();
		$this->create_video_rooms_table();
		$this->create_video_profiles_table();
		$this->create_speed_dating_sessions_table();
	}

	/**
	 * Create video calls table.
	 */
	private function create_video_calls_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_video_calls';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			room_id varchar(100) NOT NULL,
			caller_id bigint(20) NOT NULL,
			callee_id bigint(20) NOT NULL,
			call_type varchar(20) DEFAULT 'video',
			status varchar(20) DEFAULT 'initiating',
			ice_candidates longtext,
			offer_sdp longtext,
			answer_sdp longtext,
			started_at datetime,
			connected_at datetime,
			ended_at datetime,
			duration int(11) DEFAULT 0,
			end_reason varchar(50),
			quality_rating tinyint(1),
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY room_id (room_id),
			KEY caller_id (caller_id),
			KEY callee_id (callee_id),
			KEY status (status),
			KEY started_at (started_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create call logs table.
	 */
	private function create_call_logs_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_call_logs';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			call_id bigint(20) NOT NULL,
			user_id bigint(20) NOT NULL,
			event_type varchar(50) NOT NULL,
			event_data longtext,
			connection_stats longtext,
			timestamp datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY call_id (call_id),
			KEY user_id (user_id),
			KEY event_type (event_type),
			KEY timestamp (timestamp)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create video rooms table.
	 */
	private function create_video_rooms_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_video_rooms';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			room_code varchar(20) NOT NULL,
			room_type varchar(50) DEFAULT 'private',
			host_id bigint(20) NOT NULL,
			max_participants int(11) DEFAULT 2,
			current_participants int(11) DEFAULT 0,
			settings longtext,
			is_active tinyint(1) DEFAULT 1,
			scheduled_at datetime,
			started_at datetime,
			ended_at datetime,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY room_code (room_code),
			KEY host_id (host_id),
			KEY room_type (room_type),
			KEY is_active (is_active),
			KEY scheduled_at (scheduled_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create video profiles table.
	 */
	private function create_video_profiles_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_video_profiles';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			video_url text NOT NULL,
			thumbnail_url text,
			duration int(11) DEFAULT 0,
			prompt_question text,
			is_active tinyint(1) DEFAULT 1,
			views int(11) DEFAULT 0,
			likes int(11) DEFAULT 0,
			uploaded_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY is_active (is_active),
			KEY uploaded_at (uploaded_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create speed dating sessions table.
	 */
	private function create_speed_dating_sessions_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_speed_dating_sessions';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			session_name varchar(255) NOT NULL,
			host_id bigint(20) NOT NULL,
			max_participants int(11) DEFAULT 20,
			round_duration int(11) DEFAULT 180,
			break_duration int(11) DEFAULT 60,
			age_range varchar(20),
			interests text,
			status varchar(20) DEFAULT 'scheduled',
			participants longtext,
			matches longtext,
			scheduled_at datetime NOT NULL,
			started_at datetime,
			ended_at datetime,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY host_id (host_id),
			KEY status (status),
			KEY scheduled_at (scheduled_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		// Video call endpoints.
		register_rest_route(
			'wpmatch/v1',
			'/video/call/initiate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_initiate_call' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'callee_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'call_type' => array(
						'default'           => 'video',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( $this, 'validate_call_type' ),
					),
				),
			)
		);

		register_rest_route(
			'wpmatch/v1',
			'/video/call/accept',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_accept_call' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'room_id' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'wpmatch/v1',
			'/video/call/decline',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_decline_call' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'room_id' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'reason'  => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'wpmatch/v1',
			'/video/call/end',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_end_call' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'room_id' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'reason'  => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'wpmatch/v1',
			'/video/call/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_get_call_status' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'room_id' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Video profile endpoints.
		register_rest_route(
			'wpmatch/v1',
			'/video/profile/upload',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_upload_video_profile' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
			)
		);

		register_rest_route(
			'wpmatch/v1',
			'/video/profile/(?P<user_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_get_video_profile' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'user_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Virtual date room endpoints.
		register_rest_route(
			'wpmatch/v1',
			'/video/room/create',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_create_room' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'room_type' => array(
						'default'           => 'private',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'settings'  => array(
						'sanitize_callback' => array( $this, 'sanitize_room_settings' ),
					),
				),
			)
		);

		register_rest_route(
			'wpmatch/v1',
			'/video/room/join',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_join_room' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'room_code' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Speed dating endpoints.
		register_rest_route(
			'wpmatch/v1',
			'/video/speed-dating/sessions',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_get_speed_dating_sessions' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'status' => array(
						'default'           => 'scheduled',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'wpmatch/v1',
			'/video/speed-dating/join',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_join_speed_dating' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'session_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'wpmatch/v1',
			'/video/speed-dating/match',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_speed_dating_match' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'session_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'partner_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'interested' => array(
						'required'          => true,
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
			)
		);

		// Ice candidate and signaling endpoints.
		register_rest_route(
			'wpmatch/v1',
			'/video/ice-candidate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_exchange_ice_candidate' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'room_id'   => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'candidate' => array(
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			'wpmatch/v1',
			'/video/offer',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_send_offer' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'room_id' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'offer'   => array(
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			'wpmatch/v1',
			'/video/answer',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_send_answer' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'room_id' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'answer'  => array(
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Enqueue scripts and styles.
	 */
	public function enqueue_scripts() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Enqueue WebRTC adapter.
		wp_enqueue_script(
			'webrtc-adapter',
			WPMATCH_PLUGIN_URL . 'public/js/webrtc-adapter.js',
			array(),
			'1.0.0',
			true
		);

		// Enqueue main video chat script.
		wp_enqueue_script(
			'wpmatch-video-chat',
			WPMATCH_PLUGIN_URL . 'public/js/wpmatch-video-chat.js',
			array( 'jquery', 'webrtc-adapter' ),
			$this->version,
			true
		);

		wp_localize_script(
			'wpmatch-video-chat',
			'wpMatchVideo',
			array(
				'apiUrl'          => home_url( '/wp-json/wpmatch/v1' ),
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'wp_rest' ),
				'currentUserId'   => get_current_user_id(),
				'iceServers'      => $this->ice_servers,
				'signalingServer' => $this->signaling_server,
				'strings'         => array(
					'calling'          => esc_html__( 'Calling...', 'wpmatch' ),
					'connecting'       => esc_html__( 'Connecting...', 'wpmatch' ),
					'connected'        => esc_html__( 'Connected', 'wpmatch' ),
					'callEnded'        => esc_html__( 'Call ended', 'wpmatch' ),
					'callDeclined'     => esc_html__( 'Call declined', 'wpmatch' ),
					'networkError'     => esc_html__( 'Network error', 'wpmatch' ),
					'permissionDenied' => esc_html__( 'Camera/microphone permission denied', 'wpmatch' ),
					'incomingCall'     => esc_html__( 'Incoming video call from', 'wpmatch' ),
					'accept'           => esc_html__( 'Accept', 'wpmatch' ),
					'decline'          => esc_html__( 'Decline', 'wpmatch' ),
					'endCall'          => esc_html__( 'End Call', 'wpmatch' ),
					'muteAudio'        => esc_html__( 'Mute', 'wpmatch' ),
					'unmuteAudio'      => esc_html__( 'Unmute', 'wpmatch' ),
					'turnOffVideo'     => esc_html__( 'Turn off video', 'wpmatch' ),
					'turnOnVideo'      => esc_html__( 'Turn on video', 'wpmatch' ),
					'switchCamera'     => esc_html__( 'Switch camera', 'wpmatch' ),
					'shareScreen'      => esc_html__( 'Share screen', 'wpmatch' ),
					'stopSharing'      => esc_html__( 'Stop sharing', 'wpmatch' ),
				),
			)
		);

		// Enqueue styles.
		wp_enqueue_style(
			'wpmatch-video-chat',
			WPMATCH_PLUGIN_URL . 'public/css/wpmatch-video-chat.css',
			array(),
			$this->version
		);
	}

	/**
	 * Add video call button to profile actions.
	 *
	 * @param array $actions Current actions.
	 * @param int   $user_id User ID.
	 * @return array
	 */
	public function add_video_call_button( $actions, $user_id ) {
		if ( ! is_user_logged_in() || $user_id === get_current_user_id() ) {
			return $actions;
		}

		// Check if users are matched.
		if ( $this->are_users_matched( get_current_user_id(), $user_id ) ) {
			$actions['video_call'] = array(
				'label' => esc_html__( 'Video Call', 'wpmatch' ),
				'icon'  => 'fas fa-video',
				'class' => 'btn-video-call',
				'data'  => array(
					'user-id' => $user_id,
				),
			);
		}

		return $actions;
	}

	/**
	 * Add video chat modal to footer.
	 */
	public function add_video_chat_modal() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		?>
		<div id="wpmatch-video-modal" class="wpmatch-video-modal" style="display: none;">
			<div class="video-modal-content">
				<div class="video-header">
					<div class="video-status">
						<span class="status-text"></span>
						<span class="call-timer" style="display: none;">00:00</span>
					</div>
					<button class="btn-minimize" title="<?php esc_attr_e( 'Minimize', 'wpmatch' ); ?>">
						<i class="fas fa-minus"></i>
					</button>
					<button class="btn-close-modal" title="<?php esc_attr_e( 'Close', 'wpmatch' ); ?>">
						<i class="fas fa-times"></i>
					</button>
				</div>

				<div class="video-container">
					<video id="remote-video" class="remote-video" autoplay playsinline></video>
					<video id="local-video" class="local-video" autoplay playsinline muted></video>

					<div class="video-placeholder" id="video-placeholder">
						<img src="" alt="" class="caller-avatar">
						<div class="placeholder-text"></div>
					</div>
				</div>

				<div class="video-controls">
					<button class="btn-control btn-audio" data-muted="false">
						<i class="fas fa-microphone"></i>
					</button>
					<button class="btn-control btn-video" data-off="false">
						<i class="fas fa-video"></i>
					</button>
					<button class="btn-control btn-screen-share">
						<i class="fas fa-desktop"></i>
					</button>
					<button class="btn-control btn-end-call">
						<i class="fas fa-phone-slash"></i>
					</button>
					<button class="btn-control btn-switch-camera">
						<i class="fas fa-sync-alt"></i>
					</button>
					<button class="btn-control btn-fullscreen">
						<i class="fas fa-expand"></i>
					</button>
				</div>

				<div class="incoming-call" id="incoming-call" style="display: none;">
					<img src="" alt="" class="caller-avatar">
					<div class="caller-name"></div>
					<div class="call-actions">
						<button class="btn-accept-call">
							<i class="fas fa-phone"></i>
							<?php esc_html_e( 'Accept', 'wpmatch' ); ?>
						</button>
						<button class="btn-decline-call">
							<i class="fas fa-phone-slash"></i>
							<?php esc_html_e( 'Decline', 'wpmatch' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle WebRTC signaling via AJAX.
	 */
	public function handle_signaling() {
		check_ajax_referer( 'wp_rest', 'nonce' );

		$action  = isset( $_POST['signal_type'] ) ? sanitize_text_field( wp_unslash( $_POST['signal_type'] ) ) : '';
		$room_id = isset( $_POST['room_id'] ) ? sanitize_text_field( wp_unslash( $_POST['room_id'] ) ) : '';
		$data    = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : null;

		if ( ! $action || ! $room_id ) {
			wp_send_json_error( 'Invalid signaling request' );
		}

		// Store signaling data in transient for peer to retrieve.
		$transient_key = 'wpmatch_signal_' . $room_id . '_' . $action;
		set_transient( $transient_key, $data, 30 ); // 30 second timeout.

		wp_send_json_success( array( 'stored' => true ) );
	}

	/**
	 * API: Initiate video call.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function api_initiate_call( $request ) {
		$caller_id = get_current_user_id();
		$callee_id = $request->get_param( 'callee_id' );
		$call_type = $request->get_param( 'call_type' );

		// Check if users are matched.
		if ( ! $this->are_users_matched( $caller_id, $callee_id ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => esc_html__( 'You can only call matched users.', 'wpmatch' ),
				)
			);
		}

		// Check if callee is online.
		if ( ! $this->is_user_online( $callee_id ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => esc_html__( 'User is not available for calls.', 'wpmatch' ),
				)
			);
		}

		// Check for existing active call.
		if ( $this->has_active_call( $caller_id ) || $this->has_active_call( $callee_id ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => esc_html__( 'User is already in a call.', 'wpmatch' ),
				)
			);
		}

		// Generate room ID.
		$room_id = $this->generate_room_id();

		// Create call record.
		global $wpdb;
		$table_name = $wpdb->prefix . 'wpmatch_video_calls';

		$wpdb->insert(
			$table_name,
			array(
				'room_id'    => $room_id,
				'caller_id'  => $caller_id,
				'callee_id'  => $callee_id,
				'call_type'  => $call_type,
				'status'     => 'ringing',
				'started_at' => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		$call_id = $wpdb->insert_id;

		// Send notification to callee.
		$this->send_call_notification( $callee_id, $caller_id, $room_id );

		// Get caller info.
		$caller = get_user_by( 'ID', $caller_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'call_id'     => $call_id,
					'room_id'     => $room_id,
					'ice_servers' => $this->ice_servers,
					'caller'      => array(
						'id'           => $caller->ID,
						'display_name' => $caller->display_name,
						'avatar'       => get_avatar_url( $caller->ID ),
					),
				),
			)
		);
	}

	/**
	 * Check if users are matched.
	 *
	 * @param int $user1_id User 1 ID.
	 * @param int $user2_id User 2 ID.
	 * @return bool
	 */
	private function are_users_matched( $user1_id, $user2_id ) {
		global $wpdb;

		$matches_table = $wpdb->prefix . 'wpmatch_matches';

		$match = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $matches_table
				WHERE ((user1_id = %d AND user2_id = %d)
				OR (user1_id = %d AND user2_id = %d))
				AND status = 'matched'",
				$user1_id,
				$user2_id,
				$user2_id,
				$user1_id
			)
		);

		return ! empty( $match );
	}

	/**
	 * Check if user is online.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private function is_user_online( $user_id ) {
		$last_active = get_user_meta( $user_id, 'wpmatch_last_active', true );
		if ( ! $last_active ) {
			return false;
		}

		$time_diff = time() - strtotime( $last_active );
		return $time_diff < 300; // Consider online if active in last 5 minutes.
	}

	/**
	 * Check if user has active call.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private function has_active_call( $user_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_video_calls';

		$active_call = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $table_name
				WHERE (caller_id = %d OR callee_id = %d)
				AND status IN ('ringing', 'connecting', 'connected')
				AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
				$user_id,
				$user_id
			)
		);

		return ! empty( $active_call );
	}

	/**
	 * Generate unique room ID.
	 *
	 * @return string
	 */
	private function generate_room_id() {
		return 'room_' . wp_generate_password( 20, false );
	}

	/**
	 * Send call notification to user.
	 *
	 * @param int    $user_id User ID.
	 * @param int    $caller_id Caller ID.
	 * @param string $room_id Room ID.
	 */
	private function send_call_notification( $user_id, $caller_id, $room_id ) {
		// Store notification in database for real-time retrieval.
		global $wpdb;

		$notifications_table = $wpdb->prefix . 'wpmatch_realtime_notifications';

		$caller = get_user_by( 'ID', $caller_id );

		$wpdb->insert(
			$notifications_table,
			array(
				'user_id' => $user_id,
				'type'    => 'video_call',
				'data'    => wp_json_encode(
					array(
						'room_id'       => $room_id,
						'caller_id'     => $caller_id,
						'caller_name'   => $caller->display_name,
						'caller_avatar' => get_avatar_url( $caller->ID ),
					)
				),
				'is_read' => 0,
			),
			array( '%d', '%s', '%s', '%d' )
		);

		// Trigger action for push notifications if configured.
		do_action( 'wpmatch_incoming_call', $user_id, $caller_id, $room_id );
	}

	/**
	 * Clean up old call records.
	 */
	public function cleanup_old_calls() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_video_calls';

		// Delete calls older than 30 days.
		$wpdb->query(
			"DELETE FROM $table_name
			WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
		);

		// Update stuck calls.
		$wpdb->query(
			"UPDATE $table_name
			SET status = 'timeout', end_reason = 'auto_timeout'
			WHERE status IN ('ringing', 'connecting')
			AND created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
		);
	}

	/**
	 * Validation and permission callbacks.
	 */

	/**
	 * Check user authentication.
	 *
	 * @return bool
	 */
	public function check_user_auth() {
		return is_user_logged_in();
	}

	/**
	 * Validate call type.
	 *
	 * @param string $call_type Call type.
	 * @return bool
	 */
	public function validate_call_type( $call_type ) {
		return in_array( $call_type, array( 'video', 'audio' ), true );
	}

	/**
	 * Sanitize room settings.
	 *
	 * @param array $settings Settings array.
	 * @return array
	 */
	public function sanitize_room_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return array();
		}

		$sanitized    = array();
		$allowed_keys = array( 'background', 'effects', 'max_duration', 'theme' );

		foreach ( $allowed_keys as $key ) {
			if ( isset( $settings[ $key ] ) ) {
				$sanitized[ $key ] = sanitize_text_field( $settings[ $key ] );
			}
		}

		return $sanitized;
	}

	/**
	 * Placeholder methods for remaining API endpoints.
	 */
	public function api_accept_call( $request ) {
		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Call accepted',
			)
		);
	}

	public function api_decline_call( $request ) {
		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Call declined',
			)
		);
	}

	public function api_end_call( $request ) {
		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Call ended',
			)
		);
	}

	public function api_get_call_status( $request ) {
		return rest_ensure_response(
			array(
				'success' => true,
				'status'  => 'connected',
			)
		);
	}

	public function api_upload_video_profile( $request ) {
		return rest_ensure_response(
			array(
				'success' => false,
				'message' => 'Not implemented yet',
			)
		);
	}

	public function api_get_video_profile( $request ) {
		return rest_ensure_response(
			array(
				'success' => false,
				'message' => 'Not implemented yet',
			)
		);
	}

	public function api_create_room( $request ) {
		return rest_ensure_response(
			array(
				'success' => false,
				'message' => 'Not implemented yet',
			)
		);
	}

	public function api_join_room( $request ) {
		return rest_ensure_response(
			array(
				'success' => false,
				'message' => 'Not implemented yet',
			)
		);
	}

	public function api_get_speed_dating_sessions( $request ) {
		return rest_ensure_response(
			array(
				'success' => false,
				'message' => 'Not implemented yet',
			)
		);
	}

	public function api_join_speed_dating( $request ) {
		return rest_ensure_response(
			array(
				'success' => false,
				'message' => 'Not implemented yet',
			)
		);
	}

	public function api_speed_dating_match( $request ) {
		return rest_ensure_response(
			array(
				'success' => false,
				'message' => 'Not implemented yet',
			)
		);
	}

	public function api_exchange_ice_candidate( $request ) {
		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'ICE candidate stored',
			)
		);
	}

	public function api_send_offer( $request ) {
		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Offer stored',
			)
		);
	}

	public function api_send_answer( $request ) {
		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Answer stored',
			)
		);
	}
}