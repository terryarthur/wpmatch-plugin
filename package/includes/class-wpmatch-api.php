<?php
/**
 * REST API functionality for the plugin.
 *
 * @package WPMatch
 */

/**
 * REST API functionality for the plugin.
 */
class WPMatch_API {

	/**
	 * The ID of this plugin.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * The namespace for our REST API routes.
	 *
	 * @var string
	 */
	private $namespace = 'wpmatch/v1';

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $plugin_name The name of the plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		// User registration endpoints.
		register_rest_route(
			$this->namespace,
			'/users/register',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'register_user' ),
				'permission_callback' => '__return_true',
				'args'                => $this->get_registration_args(),
			)
		);

		// Profile endpoints.
		register_rest_route(
			$this->namespace,
			'/profiles/(?P<user_id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_profile' ),
				'permission_callback' => array( $this, 'check_profile_permissions' ),
				'args'                => array(
					'user_id' => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/profiles/(?P<user_id>\d+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_profile' ),
				'permission_callback' => array( $this, 'check_profile_edit_permissions' ),
				'args'                => $this->get_profile_args(),
			)
		);

		// Swipe endpoints.
		register_rest_route(
			$this->namespace,
			'/swipe',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'process_swipe' ),
				'permission_callback' => array( $this, 'check_user_logged_in' ),
				'args'                => $this->get_swipe_args(),
			)
		);

		register_rest_route(
			$this->namespace,
			'/queue',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_user_queue' ),
				'permission_callback' => array( $this, 'check_user_logged_in' ),
				'args'                => array(
					'limit'   => array(
						'default'           => 10,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param <= 50;
						},
					),
					'refresh' => array(
						'default'           => false,
						'validate_callback' => function ( $param ) {
							return is_bool( $param ) || in_array( $param, array( 'true', 'false', '1', '0' ), true );
						},
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/matches',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_user_matches' ),
				'permission_callback' => array( $this, 'check_user_logged_in' ),
				'args'                => array(
					'page'     => array(
						'default'           => 1,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
					),
					'per_page' => array(
						'default'           => 20,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param <= 100;
						},
					),
				),
			)
		);

		// Admin endpoints.
		register_rest_route(
			$this->namespace,
			'/admin/users',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_admin_users' ),
				'permission_callback' => array( $this, 'check_admin_permissions' ),
				'args'                => array(
					'page'     => array(
						'default'           => 1,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'per_page' => array(
						'default'           => 10,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param <= 100;
						},
					),
				),
			)
		);

		// Stats endpoint.
		register_rest_route(
			$this->namespace,
			'/admin/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_admin_stats' ),
				'permission_callback' => array( $this, 'check_admin_permissions' ),
			)
		);
	}

	/**
	 * Register a new user.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function register_user( $request ) {
		$email    = sanitize_email( $request->get_param( 'email' ) );
		$username = sanitize_user( $request->get_param( 'username' ) );
		$password = $request->get_param( 'password' );

		// Validate inputs.
		if ( empty( $email ) || ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Invalid email address', 'wpmatch' ), array( 'status' => 400 ) );
		}

		if ( email_exists( $email ) ) {
			return new WP_Error( 'email_exists', __( 'Email already registered', 'wpmatch' ), array( 'status' => 400 ) );
		}

		if ( username_exists( $username ) ) {
			return new WP_Error( 'username_exists', __( 'Username already taken', 'wpmatch' ), array( 'status' => 400 ) );
		}

		// Create user.
		$user_id = wp_create_user( $username, $password, $email );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		// Return success response.
		return rest_ensure_response(
			array(
				'user_id' => $user_id,
				'message' => __( 'User registered successfully', 'wpmatch' ),
			)
		);
	}

	/**
	 * Get user profile.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_profile( $request ) {
		global $wpdb;

		$user_id = absint( $request->get_param( 'user_id' ) );

		// Get profile data.
		$table_name = $wpdb->prefix . 'wpmatch_user_profiles';
		$profile    = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE user_id = %d",
				$user_id
			),
			ARRAY_A
		);

		if ( ! $profile ) {
			return new WP_Error( 'profile_not_found', __( 'Profile not found', 'wpmatch' ), array( 'status' => 404 ) );
		}

		// Get user data.
		$user                    = get_userdata( $user_id );
		$profile['username']     = $user->user_login;
		$profile['display_name'] = $user->display_name;

		return rest_ensure_response( $profile );
	}

	/**
	 * Update user profile.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function update_profile( $request ) {
		global $wpdb;

		$user_id = absint( $request->get_param( 'user_id' ) );
		$params  = $request->get_params();

		// Prepare update data.
		$update_data    = array();
		$allowed_fields = array(
			'age',
			'location',
			'gender',
			'orientation',
			'education',
			'profession',
			'about_me',
			'looking_for',
		);

		foreach ( $allowed_fields as $field ) {
			if ( isset( $params[ $field ] ) ) {
				$update_data[ $field ] = sanitize_text_field( $params[ $field ] );
			}
		}

		if ( empty( $update_data ) ) {
			return new WP_Error( 'no_data', __( 'No data to update', 'wpmatch' ), array( 'status' => 400 ) );
		}

		$update_data['updated_at'] = current_time( 'mysql' );

		// Update profile.
		$table_name = $wpdb->prefix . 'wpmatch_user_profiles';
		$result     = $wpdb->update(
			$table_name,
			$update_data,
			array( 'user_id' => $user_id ),
			null,
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'update_failed', __( 'Failed to update profile', 'wpmatch' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'message' => __( 'Profile updated successfully', 'wpmatch' ),
			)
		);
	}

	/**
	 * Get admin users list.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_admin_users( $request ) {
		$page     = absint( $request->get_param( 'page' ) );
		$per_page = absint( $request->get_param( 'per_page' ) );

		$args = array(
			'number'   => $per_page,
			'offset'   => ( $page - 1 ) * $per_page,
			'role__in' => array( 'wpmatch_member', 'wpmatch_premium_member' ),
		);

		$users       = get_users( $args );
		$total_users = count_users();

		$data = array();
		foreach ( $users as $user ) {
			$data[] = array(
				'id'           => $user->ID,
				'username'     => $user->user_login,
				'email'        => $user->user_email,
				'display_name' => $user->display_name,
				'registered'   => $user->user_registered,
			);
		}

		return rest_ensure_response(
			array(
				'users'    => $data,
				'total'    => $total_users['total_users'],
				'page'     => $page,
				'per_page' => $per_page,
			)
		);
	}

	/**
	 * Get admin statistics.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function get_admin_stats() {
		global $wpdb;

		// Get user counts.
		$user_counts = count_users();

		// Get profile counts.
		$table_name        = $wpdb->prefix . 'wpmatch_user_profiles';
		$total_profiles    = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
		$complete_profiles = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE profile_completion >= 80" );

		// Get active users (last 30 days).
		$thirty_days_ago = date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
		$active_users    = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table_name WHERE last_active >= %s",
				$thirty_days_ago
			)
		);

		return rest_ensure_response(
			array(
				'total_users'       => $user_counts['total_users'],
				'total_profiles'    => $total_profiles,
				'complete_profiles' => $complete_profiles,
				'active_users'      => $active_users,
			)
		);
	}

	/**
	 * Check if user can view profiles.
	 *
	 * @return bool Whether the user has permission.
	 */
	public function check_profile_permissions() {
		return is_user_logged_in();
	}

	/**
	 * Check if user can edit profile.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool Whether the user has permission.
	 */
	public function check_profile_edit_permissions( $request ) {
		$user_id = absint( $request->get_param( 'user_id' ) );
		return is_user_logged_in() && ( get_current_user_id() === $user_id || current_user_can( 'manage_options' ) );
	}

	/**
	 * Check admin permissions.
	 *
	 * @return bool Whether the user has permission.
	 */
	public function check_admin_permissions() {
		return current_user_can( 'wpmatch_manage_users' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Handle REST authentication.
	 *
	 * @param mixed $result Current authentication result.
	 * @return mixed Modified authentication result.
	 */
	public function rest_authentication( $result ) {
		// Custom authentication logic can go here.
		return $result;
	}

	/**
	 * Check permissions before dispatch.
	 *
	 * @param mixed           $result  Current result.
	 * @param WP_REST_Server  $server  Server instance.
	 * @param WP_REST_Request $request Request object.
	 * @return mixed Modified result.
	 */
	public function check_permissions( $result, $server, $request ) {
		// Additional permission checks can go here.
		return $result;
	}

	/**
	 * Get registration arguments.
	 *
	 * @return array Registration arguments.
	 */
	private function get_registration_args() {
		return array(
			'email'    => array(
				'required'          => true,
				'validate_callback' => function ( $param ) {
					return is_email( $param );
				},
				'sanitize_callback' => 'sanitize_email',
			),
			'username' => array(
				'required'          => true,
				'sanitize_callback' => 'sanitize_user',
			),
			'password' => array(
				'required'          => true,
				'validate_callback' => function ( $param ) {
					return strlen( $param ) >= 8;
				},
			),
		);
	}

	/**
	 * Get profile arguments.
	 *
	 * @return array Profile arguments.
	 */
	private function get_profile_args() {
		return array(
			'user_id'     => array(
				'validate_callback' => function ( $param ) {
					return is_numeric( $param );
				},
			),
			'age'         => array(
				'validate_callback' => function ( $param ) {
					return is_numeric( $param ) && $param >= 18 && $param <= 99;
				},
			),
			'location'    => array(
				'sanitize_callback' => 'sanitize_text_field',
			),
			'about_me'    => array(
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'looking_for' => array(
				'sanitize_callback' => 'sanitize_textarea_field',
			),
		);
	}

	/**
	 * Process a swipe action.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function process_swipe( $request ) {
		$user_id        = get_current_user_id();
		$target_user_id = absint( $request->get_param( 'target_user_id' ) );
		$swipe_type     = sanitize_text_field( $request->get_param( 'swipe_type' ) );

		// Validate swipe type.
		$valid_types = array( 'like', 'pass', 'super_like' );
		if ( ! in_array( $swipe_type, $valid_types, true ) ) {
			return new WP_Error( 'invalid_swipe_type', __( 'Invalid swipe type', 'wpmatch' ), array( 'status' => 400 ) );
		}

		// Process the swipe.
		$result = WPMatch_Matching_Algorithm::process_swipe_action( $user_id, $target_user_id, $swipe_type );

		if ( ! $result['success'] ) {
			return new WP_Error( $result['error'], $result['message'] ?? __( 'Swipe failed', 'wpmatch' ), array( 'status' => 400 ) );
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Get user's match queue.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_user_queue( $request ) {
		$user_id = get_current_user_id();
		$limit   = absint( $request->get_param( 'limit' ) );
		$refresh = rest_sanitize_boolean( $request->get_param( 'refresh' ) );

		// Build or refresh queue.
		$queue = WPMatch_Matching_Algorithm::build_user_queue( $user_id, $refresh );

		if ( empty( $queue ) ) {
			return rest_ensure_response(
				array(
					'queue'   => array(),
					'message' => __( 'No potential matches found. Try updating your preferences or expanding your search criteria.', 'wpmatch' ),
				)
			);
		}

		// Prepare response data.
		$profile_manager = new WPMatch_Profile_Manager();
		$queue_data      = array();

		foreach ( array_slice( $queue, 0, $limit ) as $queue_item ) {
			$profile = $profile_manager->get_profile( $queue_item->potential_match_id );
			if ( $profile ) {
				$queue_data[] = array(
					'user_id'             => $profile->user_id,
					'display_name'        => get_userdata( $profile->user_id )->display_name,
					'age'                 => $profile->age,
					'location'            => $profile->location,
					'about_me'            => $profile->about_me,
					'profile_completion'  => $profile->profile_completion,
					'compatibility_score' => $queue_item->compatibility_score,
					'media'               => $profile->media,
					'interests'           => $profile->interests,
				);
			}
		}

		return rest_ensure_response(
			array(
				'queue' => $queue_data,
				'total' => count( $queue ),
			)
		);
	}

	/**
	 * Get user's matches.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_user_matches( $request ) {
		$user_id  = get_current_user_id();
		$page     = absint( $request->get_param( 'page' ) );
		$per_page = absint( $request->get_param( 'per_page' ) );

		global $wpdb;
		$matches_table = $wpdb->prefix . 'wpmatch_matches';

		// Get matches with pagination.
		$offset = ( $page - 1 ) * $per_page;

		$matches = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$matches_table}
			WHERE (user1_id = %d OR user2_id = %d)
			AND status = 'active'
			ORDER BY matched_at DESC
			LIMIT %d OFFSET %d",
				$user_id,
				$user_id,
				$per_page,
				$offset
			)
		);

		$matches_data    = array();
		$profile_manager = new WPMatch_Profile_Manager();

		foreach ( $matches as $match ) {
			// Get the other user's ID.
			$other_user_id = ( $match->user1_id === $user_id ) ? $match->user2_id : $match->user1_id;
			$profile       = $profile_manager->get_profile( $other_user_id );

			if ( $profile ) {
				$matches_data[] = array(
					'match_id'   => $match->match_id,
					'matched_at' => $match->matched_at,
					'user'       => array(
						'user_id'      => $profile->user_id,
						'display_name' => get_userdata( $profile->user_id )->display_name,
						'age'          => $profile->age,
						'location'     => $profile->location,
						'about_me'     => $profile->about_me,
						'media'        => array_slice( $profile->media, 0, 1 ), // Just primary photo.
					),
				);
			}
		}

		// Get total count.
		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$matches_table}
			WHERE (user1_id = %d OR user2_id = %d)
			AND status = 'active'",
				$user_id,
				$user_id
			)
		);

		return rest_ensure_response(
			array(
				'matches'     => $matches_data,
				'total'       => $total,
				'page'        => $page,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total / $per_page ),
			)
		);
	}

	/**
	 * Check if user is logged in.
	 *
	 * @return bool|WP_Error True if logged in, error otherwise.
	 */
	public function check_user_logged_in() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'not_logged_in', __( 'You must be logged in to access this endpoint', 'wpmatch' ), array( 'status' => 401 ) );
		}
		return true;
	}

	/**
	 * Get swipe arguments.
	 *
	 * @return array Swipe arguments.
	 */
	private function get_swipe_args() {
		return array(
			'target_user_id' => array(
				'required'          => true,
				'validate_callback' => function ( $param ) {
					return is_numeric( $param ) && $param > 0;
				},
				'sanitize_callback' => 'absint',
			),
			'swipe_type'     => array(
				'required'          => true,
				'validate_callback' => function ( $param ) {
					return in_array( $param, array( 'like', 'pass', 'super_like' ), true );
				},
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}
}
