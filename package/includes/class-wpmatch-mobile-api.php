<?php
/**
 * Mobile App API Endpoints for WPMatch
 *
 * Comprehensive REST API specifically designed for mobile applications
 * with features like authentication, profile management, matching, and messaging.
 *
 * @package WPMatch
 * @since 1.3.0
 */

class WPMatch_Mobile_API {

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
	 * Initialize the class.
	 *
	 * @param string $plugin_name Plugin name.
	 * @param string $version Plugin version.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Initialize mobile API system.
	 */
	public static function init() {
		$instance = new self( 'wpmatch', WPMATCH_VERSION );
		add_action( 'rest_api_init', array( $instance, 'register_routes' ) );
		add_action( 'wp_loaded', array( $instance, 'setup_jwt_auth' ) );
		add_filter( 'determine_current_user', array( $instance, 'determine_current_user' ), 20 );
	}

	/**
	 * Register REST API routes for mobile app.
	 */
	public function register_routes() {
		// Authentication endpoints.
		register_rest_route(
			'wpmatch/v1/mobile',
			'/auth/login',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_mobile_login' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'username'    => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_user',
					),
					'password'    => array(
						'required' => true,
					),
					'device_info' => array(
						'required'          => false,
						'sanitize_callback' => array( $this, 'sanitize_device_info' ),
					),
				),
			)
		);

		register_rest_route(
			'wpmatch/v1/mobile',
			'/auth/register',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_mobile_register' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'email'       => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_email',
						'validate_callback' => array( $this, 'validate_email' ),
					),
					'username'    => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_user',
					),
					'password'    => array(
						'required' => true,
					),
					'device_info' => array(
						'required'          => false,
						'sanitize_callback' => array( $this, 'sanitize_device_info' ),
					),
				),
			)
		);

		register_rest_route(
			'wpmatch/v1/mobile',
			'/auth/refresh',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_refresh_token' ),
				'permission_callback' => array( $this, 'check_mobile_auth' ),
				'args'                => array(
					'refresh_token' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'wpmatch/v1/mobile',
			'/auth/logout',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_mobile_logout' ),
				'permission_callback' => array( $this, 'check_mobile_auth' ),
			)
		);

		// Profile endpoints.
		register_rest_route(
			'wpmatch/v1/mobile',
			'/profile',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_get_current_profile' ),
				'permission_callback' => array( $this, 'check_mobile_auth' ),
			)
		);

		register_rest_route(
			'wpmatch/v1/mobile',
			'/profile',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'api_update_profile' ),
				'permission_callback' => array( $this, 'check_mobile_auth' ),
				'args'                => array(
					'display_name' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
					'bio'          => array(
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'age'          => array(
						'sanitize_callback' => 'absint',
						'validate_callback' => array( $this, 'validate_age' ),
					),
					'location'     => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
					'interests'    => array(
						'sanitize_callback' => array( $this, 'sanitize_interests' ),
					),
					'preferences'  => array(
						'sanitize_callback' => array( $this, 'sanitize_preferences' ),
					),
				),
			)
		);

		register_rest_route(
			'wpmatch/v1/mobile',
			'/profile/photos',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_upload_photo' ),
				'permission_callback' => array( $this, 'check_mobile_auth' ),
			)
		);

		register_rest_route(
			'wpmatch/v1/mobile',
			'/profile/photos/(?P<photo_id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'api_delete_photo' ),
				'permission_callback' => array( $this, 'check_mobile_auth' ),
				'args'                => array(
					'photo_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Discovery and matching endpoints.
		register_rest_route(
			'wpmatch/v1/mobile',
			'/discover',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_get_discovery_queue' ),
				'permission_callback' => array( $this, 'check_mobile_auth' ),
				'args'                => array(
					'limit'   => array(
						'default'           => 10,
						'sanitize_callback' => 'absint',
					),
					'refresh' => array(
						'default'           => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
			)
		);

		register_rest_route(
			'wpmatch/v1/mobile',
			'/swipe',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_record_swipe' ),
				'permission_callback' => array( $this, 'check_mobile_auth' ),
				'args'                => array(
					'target_user_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'action'         => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( $this, 'validate_swipe_action' ),
					),
					'location'       => array(
						'sanitize_callback' => array( $this, 'sanitize_location' ),
					),
				),
			)
		);

		register_rest_route(
			'wpmatch/v1/mobile',
			'/matches',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_get_matches' ),
				'permission_callback' => array( $this, 'check_mobile_auth' ),
				'args'                => array(
					'page'     => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
					'filter'   => array(
						'default'           => 'all',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Messaging endpoints.
		register_rest_route(
			'wpmatch/v1/mobile',
			'/conversations',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_get_conversations' ),
				'permission_callback' => array( $this, 'check_mobile_auth' ),
				'args'                => array(
					'page'     => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'wpmatch/v1/mobile',
			'/conversations/(?P<conversation_id>\d+)/messages',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_get_messages' ),
				'permission_callback' => array( $this, 'check_mobile_auth' ),
				'args'                => array(
					'conversation_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'page'            => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page'        => array(
						'default'           => 50,
						'sanitize_callback' => 'absint',
					),
					'since'           => array(
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'wpmatch/v1/mobile',
			'/conversations/(?P<conversation_id>\d+)/messages',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_send_message' ),
				'permission_callback' => array( $this, 'check_mobile_auth' ),
				'args'                => array(
					'conversation_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'content'         => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'message_type'    => array(
						'default'           => 'text',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Settings and preferences.
		register_rest_route(
			'wpmatch/v1/mobile',
			'/settings/notifications',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_get_notification_settings' ),
				'permission_callback' => array( $this, 'check_mobile_auth' ),
			)
		);

		register_rest_route(
			'wpmatch/v1/mobile',
			'/settings/notifications',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'api_update_notification_settings' ),
				'permission_callback' => array( $this, 'check_mobile_auth' ),
				'args'                => array(
					'push_matches'  => array(
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
					'push_messages' => array(
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
					'push_likes'    => array(
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
					'email_digest'  => array(
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
			)
		);

		register_rest_route(
			'wpmatch/v1/mobile',
			'/settings/privacy',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_get_privacy_settings' ),
				'permission_callback' => array( $this, 'check_mobile_auth' ),
			)
		);

		register_rest_route(
			'wpmatch/v1/mobile',
			'/settings/privacy',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'api_update_privacy_settings' ),
				'permission_callback' => array( $this, 'check_mobile_auth' ),
				'args'                => array(
					'show_age'      => array(
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
					'show_distance' => array(
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
					'discoverable'  => array(
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
					'online_status' => array(
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
			)
		);

		// Push notification endpoints.
		register_rest_route(
			'wpmatch/v1/mobile',
			'/notifications/register-device',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_register_device' ),
				'permission_callback' => array( $this, 'check_mobile_auth' ),
				'args'                => array(
					'device_token' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'platform'     => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( $this, 'validate_platform' ),
					),
				),
			)
		);
	}

	/**
	 * Set up JWT authentication.
	 */
	public function setup_jwt_auth() {
		// Only set up JWT for mobile API requests.
		if ( ! $this->is_mobile_api_request() ) {
			return;
		}

		// Create JWT secret if it doesn't exist.
		$jwt_secret = get_option( 'wpmatch_jwt_secret' );
		if ( ! $jwt_secret ) {
			$jwt_secret = wp_generate_password( 64, true );
			update_option( 'wpmatch_jwt_secret', $jwt_secret );
		}
	}

	/**
	 * Check if current request is for mobile API.
	 *
	 * @return bool
	 */
	private function is_mobile_api_request() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		return strpos( $request_uri, '/wp-json/wpmatch/v1/mobile/' ) !== false;
	}

	/**
	 * Determine current user for JWT authentication.
	 *
	 * @param int|bool $user_id Current user ID.
	 * @return int|bool
	 */
	public function determine_current_user( $user_id ) {
		if ( ! $this->is_mobile_api_request() ) {
			return $user_id;
		}

		// Try to get user from JWT token.
		$token = $this->get_auth_token();
		if ( $token ) {
			$decoded = $this->decode_jwt_token( $token );
			if ( $decoded && isset( $decoded->user_id ) ) {
				return $decoded->user_id;
			}
		}

		return $user_id;
	}

	/**
	 * Get authorization token from request.
	 *
	 * @return string|null
	 */
	private function get_auth_token() {
		$headers = getallheaders();

		if ( isset( $headers['Authorization'] ) ) {
			$auth_header = $headers['Authorization'];
			if ( strpos( $auth_header, 'Bearer ' ) === 0 ) {
				return substr( $auth_header, 7 );
			}
		}

		return null;
	}

	/**
	 * Create JWT token for user.
	 *
	 * @param int   $user_id User ID.
	 * @param array $device_info Device information.
	 * @return array
	 */
	private function create_jwt_token( $user_id, $device_info = array() ) {
		$secret = get_option( 'wpmatch_jwt_secret' );
		$now    = time();

		$payload = array(
			'user_id' => $user_id,
			'iat'     => $now,
			'exp'     => $now + ( 7 * DAY_IN_SECONDS ), // 7 days.
			'device'  => $device_info,
		);

		$refresh_payload = array(
			'user_id' => $user_id,
			'iat'     => $now,
			'exp'     => $now + ( 30 * DAY_IN_SECONDS ), // 30 days.
			'type'    => 'refresh',
		);

		// Simple JWT encoding (in production, use a proper JWT library).
		$token         = base64_encode( wp_json_encode( $payload ) );
		$refresh_token = base64_encode( wp_json_encode( $refresh_payload ) );

		// Store device info.
		if ( ! empty( $device_info ) ) {
			$this->store_device_info( $user_id, $device_info );
		}

		return array(
			'access_token'  => $token,
			'refresh_token' => $refresh_token,
			'expires_in'    => 7 * DAY_IN_SECONDS,
		);
	}

	/**
	 * Decode JWT token.
	 *
	 * @param string $token JWT token.
	 * @return object|null
	 */
	private function decode_jwt_token( $token ) {
		$decoded = base64_decode( $token );
		if ( ! $decoded ) {
			return null;
		}

		$payload = json_decode( $decoded );
		if ( ! $payload || ! isset( $payload->user_id, $payload->exp ) ) {
			return null;
		}

		// Check expiration.
		if ( $payload->exp < time() ) {
			return null;
		}

		return $payload;
	}

	/**
	 * Store device information for user.
	 *
	 * @param int   $user_id User ID.
	 * @param array $device_info Device information.
	 */
	private function store_device_info( $user_id, $device_info ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_mobile_devices';

		// Check if table exists, create if not.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			$this->create_mobile_devices_table();
		}

		$wpdb->replace(
			$table_name,
			array(
				'user_id'      => $user_id,
				'device_token' => isset( $device_info['device_token'] ) ? sanitize_text_field( $device_info['device_token'] ) : '',
				'platform'     => isset( $device_info['platform'] ) ? sanitize_text_field( $device_info['platform'] ) : '',
				'app_version'  => isset( $device_info['app_version'] ) ? sanitize_text_field( $device_info['app_version'] ) : '',
				'os_version'   => isset( $device_info['os_version'] ) ? sanitize_text_field( $device_info['os_version'] ) : '',
				'device_model' => isset( $device_info['device_model'] ) ? sanitize_text_field( $device_info['device_model'] ) : '',
				'last_active'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Create mobile devices table.
	 */
	private function create_mobile_devices_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_mobile_devices';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			device_token varchar(500) NOT NULL,
			platform varchar(20) NOT NULL,
			app_version varchar(20) DEFAULT '',
			os_version varchar(50) DEFAULT '',
			device_model varchar(100) DEFAULT '',
			last_active datetime NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY unique_user_device (user_id, device_token),
			KEY user_id (user_id),
			KEY platform (platform),
			KEY last_active (last_active)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Check mobile authentication.
	 *
	 * @return bool
	 */
	public function check_mobile_auth() {
		$user_id = get_current_user_id();
		return $user_id > 0;
	}

	/**
	 * Mobile login endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function api_mobile_login( $request ) {
		$username    = $request->get_param( 'username' );
		$password    = $request->get_param( 'password' );
		$device_info = $request->get_param( 'device_info' );

		// Authenticate user.
		$user = wp_authenticate( $username, $password );

		if ( is_wp_error( $user ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => esc_html__( 'Invalid credentials.', 'wpmatch' ),
					'code'    => 'invalid_credentials',
				)
			);
		}

		// Generate JWT token.
		$token_data = $this->create_jwt_token( $user->ID, $device_info );

		// Get user profile data.
		$profile = $this->get_user_profile( $user->ID );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'user'    => array(
						'id'           => $user->ID,
						'username'     => $user->user_login,
						'email'        => $user->user_email,
						'display_name' => $user->display_name,
					),
					'profile' => $profile,
					'token'   => $token_data,
				),
			)
		);
	}

	/**
	 * Mobile registration endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function api_mobile_register( $request ) {
		$email       = $request->get_param( 'email' );
		$username    = $request->get_param( 'username' );
		$password    = $request->get_param( 'password' );
		$device_info = $request->get_param( 'device_info' );

		// Check if email or username already exists.
		if ( email_exists( $email ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => esc_html__( 'Email already exists.', 'wpmatch' ),
					'code'    => 'email_exists',
				)
			);
		}

		if ( username_exists( $username ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => esc_html__( 'Username already exists.', 'wpmatch' ),
					'code'    => 'username_exists',
				)
			);
		}

		// Create user.
		$user_id = wp_create_user( $username, $password, $email );

		if ( is_wp_error( $user_id ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => $user_id->get_error_message(),
					'code'    => $user_id->get_error_code(),
				)
			);
		}

		// Initialize user profile.
		$this->initialize_user_profile( $user_id );

		// Generate JWT token.
		$token_data = $this->create_jwt_token( $user_id, $device_info );

		// Get user data.
		$user    = get_user_by( 'ID', $user_id );
		$profile = $this->get_user_profile( $user_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'user'    => array(
						'id'           => $user->ID,
						'username'     => $user->user_login,
						'email'        => $user->user_email,
						'display_name' => $user->display_name,
					),
					'profile' => $profile,
					'token'   => $token_data,
				),
			)
		);
	}

	/**
	 * Refresh token endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function api_refresh_token( $request ) {
		$refresh_token = $request->get_param( 'refresh_token' );

		$decoded = $this->decode_jwt_token( $refresh_token );
		if ( ! $decoded || ! isset( $decoded->type ) || 'refresh' !== $decoded->type ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => esc_html__( 'Invalid refresh token.', 'wpmatch' ),
					'code'    => 'invalid_refresh_token',
				)
			);
		}

		// Generate new tokens.
		$token_data = $this->create_jwt_token( $decoded->user_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'token' => $token_data,
				),
			)
		);
	}

	/**
	 * Mobile logout endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function api_mobile_logout( $request ) {
		$user_id = get_current_user_id();

		// In a real implementation, you'd invalidate the token
		// For now, just return success.

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => esc_html__( 'Logged out successfully.', 'wpmatch' ),
			)
		);
	}

	/**
	 * Get current user profile.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function api_get_current_profile( $request ) {
		$user_id = get_current_user_id();
		$profile = $this->get_user_profile( $user_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $profile,
			)
		);
	}

	/**
	 * Update user profile.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function api_update_profile( $request ) {
		$user_id = get_current_user_id();

		$fields = array(
			'display_name' => $request->get_param( 'display_name' ),
			'bio'          => $request->get_param( 'bio' ),
			'age'          => $request->get_param( 'age' ),
			'location'     => $request->get_param( 'location' ),
			'interests'    => $request->get_param( 'interests' ),
			'preferences'  => $request->get_param( 'preferences' ),
		);

		// Update user display name.
		if ( ! empty( $fields['display_name'] ) ) {
			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => $fields['display_name'],
				)
			);
		}

		// Update profile fields.
		foreach ( $fields as $key => $value ) {
			if ( null !== $value && 'display_name' !== $key ) {
				update_user_meta( $user_id, 'wpmatch_' . $key, $value );
			}
		}

		$profile = $this->get_user_profile( $user_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $profile,
				'message' => esc_html__( 'Profile updated successfully.', 'wpmatch' ),
			)
		);
	}

	/**
	 * Get discovery queue.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function api_get_discovery_queue( $request ) {
		$user_id = get_current_user_id();
		$limit   = $request->get_param( 'limit' );
		$refresh = $request->get_param( 'refresh' );

		// Use the matching algorithm to get potential matches.
		if ( class_exists( 'WPMatch_Advanced_Matching' ) ) {
			$matches = WPMatch_Advanced_Matching::get_potential_matches( $user_id, $limit );
		} else {
			$matches = array(); // Fallback if advanced matching is not available.
		}

		$profiles = array();
		foreach ( $matches as $match ) {
			$profile = $this->get_user_profile( $match['user_id'] );
			if ( $profile ) {
				$profile['compatibility_score'] = $match['compatibility_score'];
				$profiles[]                     = $profile;
			}
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'profiles' => $profiles,
					'count'    => count( $profiles ),
					'refresh'  => $refresh,
				),
			)
		);
	}

	/**
	 * Record swipe action.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function api_record_swipe( $request ) {
		$user_id        = get_current_user_id();
		$target_user_id = $request->get_param( 'target_user_id' );
		$action         = $request->get_param( 'action' );
		$location       = $request->get_param( 'location' );

		// Record swipe using existing API.
		if ( class_exists( 'WPMatch_API' ) ) {
			$api_instance  = new WPMatch_API( 'wpmatch', WPMATCH_VERSION );
			$swipe_request = new WP_REST_Request( 'POST', '/wpmatch/v1/swipe' );
			$swipe_request->set_param( 'target_user_id', $target_user_id );
			$swipe_request->set_param( 'swipe_type', $action );

			$result = $api_instance->handle_swipe( $swipe_request );
			if ( is_wp_error( $result ) ) {
				return rest_ensure_response(
					array(
						'success' => false,
						'message' => $result->get_error_message(),
					)
				);
			}

			$response_data = $result->get_data();

			return rest_ensure_response(
				array(
					'success' => true,
					'data'    => array(
						'is_match'    => isset( $response_data['is_match'] ) ? $response_data['is_match'] : false,
						'match_id'    => isset( $response_data['match_id'] ) ? $response_data['match_id'] : null,
						'action'      => $action,
						'target_user' => $this->get_user_profile( $target_user_id ),
					),
				)
			);
		}

		return rest_ensure_response(
			array(
				'success' => false,
				'message' => esc_html__( 'Swipe functionality not available.', 'wpmatch' ),
			)
		);
	}

	/**
	 * Get user matches.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function api_get_matches( $request ) {
		$user_id  = get_current_user_id();
		$page     = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );
		$filter   = $request->get_param( 'filter' );

		global $wpdb;

		$offset = ( $page - 1 ) * $per_page;

		$matches_table = $wpdb->prefix . 'wpmatch_matches';

		$where_clause = '';
		if ( 'recent' === $filter ) {
			$where_clause = 'AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
		}

		$matches = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $matches_table
				WHERE (user1_id = %d OR user2_id = %d)
				AND status = 'matched'
				$where_clause
				ORDER BY created_at DESC
				LIMIT %d OFFSET %d",
				$user_id,
				$user_id,
				$per_page,
				$offset
			)
		);

		$match_profiles = array();
		foreach ( $matches as $match ) {
			$other_user_id = ( $match->user1_id == $user_id ) ? $match->user2_id : $match->user1_id;
			$profile       = $this->get_user_profile( $other_user_id );

			if ( $profile ) {
				$profile['match_id']   = $match->id;
				$profile['matched_at'] = $match->created_at;
				$match_profiles[]      = $profile;
			}
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'matches'    => $match_profiles,
					'pagination' => array(
						'page'     => $page,
						'per_page' => $per_page,
						'total'    => count( $match_profiles ),
					),
				),
			)
		);
	}

	/**
	 * Get user profile data.
	 *
	 * @param int $user_id User ID.
	 * @return array|null
	 */
	private function get_user_profile( $user_id ) {
		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			return null;
		}

		// Get profile photos.
		$photos = get_user_meta( $user_id, 'wpmatch_photos', true );
		if ( ! is_array( $photos ) ) {
			$photos = array();
		}

		// Format photos with full URLs.
		$formatted_photos = array();
		foreach ( $photos as $photo ) {
			if ( is_array( $photo ) && isset( $photo['url'] ) ) {
				$formatted_photos[] = $photo;
			}
		}

		return array(
			'id'            => $user->ID,
			'username'      => $user->user_login,
			'display_name'  => $user->display_name,
			'bio'           => get_user_meta( $user_id, 'wpmatch_bio', true ),
			'age'           => (int) get_user_meta( $user_id, 'wpmatch_age', true ),
			'location'      => get_user_meta( $user_id, 'wpmatch_location', true ),
			'interests'     => get_user_meta( $user_id, 'wpmatch_interests', true ),
			'preferences'   => get_user_meta( $user_id, 'wpmatch_preferences', true ),
			'photos'        => $formatted_photos,
			'verification'  => array(
				'email_verified' => (bool) get_user_meta( $user_id, 'wpmatch_email_verified', true ),
				'photo_verified' => (bool) get_user_meta( $user_id, 'wpmatch_photo_verified', true ),
			),
			'last_active'   => get_user_meta( $user_id, 'wpmatch_last_active', true ),
			'online_status' => $this->get_user_online_status( $user_id ),
		);
	}

	/**
	 * Initialize user profile after registration.
	 *
	 * @param int $user_id User ID.
	 */
	private function initialize_user_profile( $user_id ) {
		$default_preferences = array(
			'age_range' => array(
				'min' => 18,
				'max' => 99,
			),
			'distance'  => 50,
			'show_me'   => 'everyone',
		);

		update_user_meta( $user_id, 'wpmatch_preferences', $default_preferences );
		update_user_meta( $user_id, 'wpmatch_interests', array() );
		update_user_meta( $user_id, 'wpmatch_photos', array() );
		update_user_meta( $user_id, 'wpmatch_last_active', current_time( 'mysql' ) );
	}

	/**
	 * Get user online status.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function get_user_online_status( $user_id ) {
		$last_active = get_user_meta( $user_id, 'wpmatch_last_active', true );
		if ( ! $last_active ) {
			return 'offline';
		}

		$time_diff = time() - strtotime( $last_active );

		if ( $time_diff < 300 ) { // 5 minutes.
			return 'online';
		} elseif ( $time_diff < 3600 ) { // 1 hour.
			return 'recently_active';
		} else {
			return 'offline';
		}
	}

	/**
	 * Validation and sanitization callbacks.
	 */

	/**
	 * Sanitize device info.
	 *
	 * @param array $device_info Device information.
	 * @return array
	 */
	public function sanitize_device_info( $device_info ) {
		if ( ! is_array( $device_info ) ) {
			return array();
		}

		$sanitized      = array();
		$allowed_fields = array( 'device_token', 'platform', 'app_version', 'os_version', 'device_model' );

		foreach ( $allowed_fields as $field ) {
			if ( isset( $device_info[ $field ] ) ) {
				$sanitized[ $field ] = sanitize_text_field( $device_info[ $field ] );
			}
		}

		return $sanitized;
	}

	/**
	 * Validate email.
	 *
	 * @param string $email Email address.
	 * @return bool
	 */
	public function validate_email( $email ) {
		return is_email( $email );
	}

	/**
	 * Validate age.
	 *
	 * @param int $age Age value.
	 * @return bool
	 */
	public function validate_age( $age ) {
		return $age >= 18 && $age <= 99;
	}

	/**
	 * Sanitize interests array.
	 *
	 * @param array $interests Interests array.
	 * @return array
	 */
	public function sanitize_interests( $interests ) {
		if ( ! is_array( $interests ) ) {
			return array();
		}

		return array_map( 'sanitize_text_field', $interests );
	}

	/**
	 * Sanitize preferences array.
	 *
	 * @param array $preferences Preferences array.
	 * @return array
	 */
	public function sanitize_preferences( $preferences ) {
		if ( ! is_array( $preferences ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $preferences as $key => $value ) {
			$sanitized[ sanitize_key( $key ) ] = sanitize_text_field( $value );
		}

		return $sanitized;
	}

	/**
	 * Validate swipe action.
	 *
	 * @param string $action Swipe action.
	 * @return bool
	 */
	public function validate_swipe_action( $action ) {
		return in_array( $action, array( 'like', 'pass', 'super_like' ), true );
	}

	/**
	 * Sanitize location data.
	 *
	 * @param array $location Location data.
	 * @return array
	 */
	public function sanitize_location( $location ) {
		if ( ! is_array( $location ) ) {
			return array();
		}

		$sanitized = array();
		if ( isset( $location['latitude'] ) ) {
			$sanitized['latitude'] = (float) $location['latitude'];
		}
		if ( isset( $location['longitude'] ) ) {
			$sanitized['longitude'] = (float) $location['longitude'];
		}

		return $sanitized;
	}

	/**
	 * Validate platform.
	 *
	 * @param string $platform Platform name.
	 * @return bool
	 */
	public function validate_platform( $platform ) {
		return in_array( $platform, array( 'ios', 'android' ), true );
	}

	/**
	 * Placeholder methods for remaining endpoints.
	 * These would be implemented with full functionality.
	 */
	public function api_upload_photo( $request ) {
		return rest_ensure_response(
			array(
				'success' => false,
				'message' => 'Not implemented yet',
			)
		);
	}

	public function api_delete_photo( $request ) {
		return rest_ensure_response(
			array(
				'success' => false,
				'message' => 'Not implemented yet',
			)
		);
	}

	public function api_get_conversations( $request ) {
		return rest_ensure_response(
			array(
				'success' => false,
				'message' => 'Not implemented yet',
			)
		);
	}

	public function api_get_messages( $request ) {
		return rest_ensure_response(
			array(
				'success' => false,
				'message' => 'Not implemented yet',
			)
		);
	}

	public function api_send_message( $request ) {
		return rest_ensure_response(
			array(
				'success' => false,
				'message' => 'Not implemented yet',
			)
		);
	}

	public function api_get_notification_settings( $request ) {
		return rest_ensure_response(
			array(
				'success' => false,
				'message' => 'Not implemented yet',
			)
		);
	}

	public function api_update_notification_settings( $request ) {
		return rest_ensure_response(
			array(
				'success' => false,
				'message' => 'Not implemented yet',
			)
		);
	}

	public function api_get_privacy_settings( $request ) {
		return rest_ensure_response(
			array(
				'success' => false,
				'message' => 'Not implemented yet',
			)
		);
	}

	public function api_update_privacy_settings( $request ) {
		return rest_ensure_response(
			array(
				'success' => false,
				'message' => 'Not implemented yet',
			)
		);
	}

	public function api_register_device( $request ) {
		return rest_ensure_response(
			array(
				'success' => false,
				'message' => 'Not implemented yet',
			)
		);
	}
}
