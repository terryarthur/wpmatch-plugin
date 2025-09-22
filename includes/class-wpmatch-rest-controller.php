<?php
/**
 * WPMatch REST API Controller
 *
 * Central controller for all REST API endpoints.
 *
 * @package WPMatch
 * @since 1.7.0
 */

/**
 * Main REST API Controller class.
 */
class WPMatch_REST_Controller {

	/**
	 * API namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wpmatch/v1';

	/**
	 * Instance of the class.
	 *
	 * @var WPMatch_REST_Controller
	 */
	private static $instance = null;

	/**
	 * Registered endpoints.
	 *
	 * @var array
	 */
	protected $endpoints = array();

	/**
	 * Get the single instance.
	 *
	 * @return WPMatch_REST_Controller
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
		add_action( 'rest_api_init', array( $this, 'register_all_routes' ), 5 );
	}

	/**
	 * Register all REST API routes.
	 */
	public function register_all_routes() {
		// Register core routes.
		$this->register_core_routes();

		// Register core feature routes.
		$this->register_user_media_routes();
		$this->register_user_interests_routes();
		$this->register_user_preferences_routes();

		// Register feature routes.
		$this->register_gamification_routes();
		$this->register_events_routes();
		$this->register_voice_notes_routes();
		$this->register_location_routes();
		$this->register_messaging_routes();
		$this->register_video_routes();
		$this->register_ai_routes();
		$this->register_social_routes();
		$this->register_verification_routes();
		$this->register_analytics_routes();

		// Log registered routes for debugging.
		$this->log_registered_routes();
	}

	/**
	 * Register core routes.
	 */
	protected function register_core_routes() {
		// User authentication.
		register_rest_route(
			$this->namespace,
			'/auth/login',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'login' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'username' => array(
						'required' => true,
						'type'     => 'string',
					),
					'password' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/auth/logout',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'logout' ),
				'permission_callback' => array( $this, 'is_authenticated' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/auth/refresh',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'refresh_token' ),
				'permission_callback' => array( $this, 'is_authenticated' ),
			)
		);

		// User management.
		register_rest_route(
			$this->namespace,
			'/users/register',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'register_user' ),
				'permission_callback' => '__return_true',
				'args'                => $this->get_user_registration_args(),
			)
		);

		register_rest_route(
			$this->namespace,
			'/users/me',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_current_user' ),
				'permission_callback' => array( $this, 'is_authenticated' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/users/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_user' ),
					'permission_callback' => array( $this, 'can_view_user' ),
					'args'                => array(
						'id' => array(
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_user' ),
					'permission_callback' => array( $this, 'can_edit_user' ),
					'args'                => $this->get_user_update_args(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_user' ),
					'permission_callback' => array( $this, 'can_delete_user' ),
				),
			)
		);

		// Profiles.
		register_rest_route(
			$this->namespace,
			'/profiles/(?P<user_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_profile' ),
					'permission_callback' => array( $this, 'can_view_profile' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_profile' ),
					'permission_callback' => array( $this, 'can_edit_profile' ),
					'args'                => $this->get_profile_update_args(),
				),
			)
		);

		// Matches.
		register_rest_route(
			$this->namespace,
			'/matches',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_matches' ),
				'permission_callback' => array( $this, 'is_authenticated' ),
				'args'                => array(
					'page'     => array(
						'default' => 1,
						'type'    => 'integer',
					),
					'per_page' => array(
						'default' => 20,
						'type'    => 'integer',
					),
				),
			)
		);

		// Swipes.
		register_rest_route(
			$this->namespace,
			'/swipes',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_swipe' ),
				'permission_callback' => array( $this, 'is_authenticated' ),
				'args'                => array(
					'target_user_id' => array(
						'required' => true,
						'type'     => 'integer',
					),
					'direction'      => array(
						'required' => true,
						'type'     => 'string',
						'enum'     => array( 'like', 'pass', 'super_like' ),
					),
				),
			)
		);

		// Queue.
		register_rest_route(
			$this->namespace,
			'/queue',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_queue' ),
				'permission_callback' => array( $this, 'is_authenticated' ),
				'args'                => array(
					'limit' => array(
						'default' => 10,
						'type'    => 'integer',
					),
				),
			)
		);

		// Search.
		register_rest_route(
			$this->namespace,
			'/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search_users' ),
				'permission_callback' => array( $this, 'is_authenticated' ),
				'args'                => $this->get_search_args(),
			)
		);

		$this->endpoints['core'] = true;
	}

	/**
	 * Register gamification routes.
	 */
	protected function register_gamification_routes() {
		if ( class_exists( 'WPMatch_Gamification' ) ) {
			// Get the existing instance if available, or create with proper params.
			$gamification = new WPMatch_Gamification( 'wpmatch', WPMATCH_VERSION );

			// Achievements.
			register_rest_route(
				$this->namespace,
				'/gamification/achievements',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $gamification, 'api_get_achievements' ),
					'permission_callback' => array( $this, 'is_authenticated' ),
				)
			);

			// User points.
			register_rest_route(
				$this->namespace,
				'/gamification/points',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $gamification, 'api_get_user_points' ),
					'permission_callback' => array( $this, 'is_authenticated' ),
				)
			);

			// Daily challenges.
			register_rest_route(
				$this->namespace,
				'/gamification/challenges',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $gamification, 'api_get_daily_challenges' ),
					'permission_callback' => array( $this, 'is_authenticated' ),
				)
			);

			// Leaderboards.
			register_rest_route(
				$this->namespace,
				'/gamification/leaderboards',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $gamification, 'api_get_leaderboards' ),
					'permission_callback' => array( $this, 'is_authenticated' ),
					'args'                => array(
						'type' => array(
							'default' => 'weekly',
							'type'    => 'string',
							'enum'    => array( 'daily', 'weekly', 'monthly', 'all_time' ),
						),
					),
				)
			);

			// Rewards.
			register_rest_route(
				$this->namespace,
				'/gamification/rewards',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $gamification, 'api_get_rewards' ),
					'permission_callback' => array( $this, 'is_authenticated' ),
				)
			);

			register_rest_route(
				$this->namespace,
				'/gamification/rewards/(?P<reward_id>\d+)/redeem',
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $gamification, 'api_redeem_reward' ),
					'permission_callback' => array( $this, 'is_authenticated' ),
				)
			);

			// User stats.
			register_rest_route(
				$this->namespace,
				'/gamification/stats',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $gamification, 'api_get_user_stats' ),
					'permission_callback' => array( $this, 'is_authenticated' ),
				)
			);

			// Streaks.
			register_rest_route(
				$this->namespace,
				'/gamification/streaks',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $gamification, 'api_get_user_streaks' ),
					'permission_callback' => array( $this, 'is_authenticated' ),
				)
			);

			$this->endpoints['gamification'] = true;
		}
	}

	/**
	 * Register events routes.
	 */
	protected function register_events_routes() {
		if ( class_exists( 'WPMatch_Events' ) ) {
			$events = new WPMatch_Events( 'wpmatch', WPMATCH_VERSION );

			// Events list.
			register_rest_route(
				$this->namespace,
				'/events',
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $events, 'api_get_events' ),
						'permission_callback' => array( $this, 'is_authenticated' ),
						'args'                => array(
							'status'   => array(
								'default' => 'upcoming',
								'type'    => 'string',
							),
							'type'     => array(
								'type' => 'string',
							),
							'page'     => array(
								'default' => 1,
								'type'    => 'integer',
							),
							'per_page' => array(
								'default' => 20,
								'type'    => 'integer',
							),
						),
					),
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( $events, 'api_create_event' ),
						'permission_callback' => array( $this, 'can_create_events' ),
						'args'                => $this->get_event_creation_args(),
					),
				)
			);

			// Single event.
			register_rest_route(
				$this->namespace,
				'/events/(?P<event_id>\d+)',
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $events, 'api_get_event' ),
						'permission_callback' => array( $this, 'is_authenticated' ),
					),
					array(
						'methods'             => WP_REST_Server::EDITABLE,
						'callback'            => array( $events, 'api_update_event' ),
						'permission_callback' => array( $this, 'can_edit_event' ),
					),
					array(
						'methods'             => WP_REST_Server::DELETABLE,
						'callback'            => array( $events, 'api_delete_event' ),
						'permission_callback' => array( $this, 'can_delete_event' ),
					),
				)
			);

			// Event registration.
			register_rest_route(
				$this->namespace,
				'/events/(?P<event_id>\d+)/register',
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $events, 'api_register_for_event' ),
					'permission_callback' => array( $this, 'is_authenticated' ),
				)
			);

			// Speed dating.
			register_rest_route(
				$this->namespace,
				'/events/speed-dating/current',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $events, 'api_get_current_speed_dating' ),
					'permission_callback' => array( $this, 'is_authenticated' ),
				)
			);

			$this->endpoints['events'] = true;
		}
	}

	/**
	 * Register voice notes routes.
	 */
	protected function register_voice_notes_routes() {
		if ( class_exists( 'WPMatch_Voice_Notes' ) ) {
			$voice_notes = new WPMatch_Voice_Notes( 'wpmatch', WPMATCH_VERSION );

			// Voice notes list.
			register_rest_route(
				$this->namespace,
				'/voice-notes',
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $voice_notes, 'api_get_voice_notes' ),
						'permission_callback' => array( $this, 'is_authenticated' ),
					),
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( $voice_notes, 'api_upload_voice_note' ),
						'permission_callback' => array( $this, 'is_authenticated' ),
					),
				)
			);

			// Voice note reactions.
			register_rest_route(
				$this->namespace,
				'/voice-notes/(?P<note_id>\d+)/react',
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $voice_notes, 'api_add_reaction' ),
					'permission_callback' => array( $this, 'is_authenticated' ),
				)
			);

			$this->endpoints['voice_notes'] = true;
		}
	}

	/**
	 * Register location routes.
	 */
	protected function register_location_routes() {
		if ( class_exists( 'WPMatch_Location' ) ) {
			$location = new WPMatch_Location( 'wpmatch', WPMATCH_VERSION );

			// Location updates.
			register_rest_route(
				$this->namespace,
				'/location/update',
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $location, 'api_update_location' ),
					'permission_callback' => array( $this, 'is_authenticated' ),
				)
			);

			// Nearby users.
			register_rest_route(
				$this->namespace,
				'/location/nearby',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $location, 'api_get_nearby_users' ),
					'permission_callback' => array( $this, 'is_authenticated' ),
				)
			);

			$this->endpoints['location'] = true;
		}
	}

	/**
	 * Register messaging routes.
	 */
	protected function register_messaging_routes() {
		// Messages.
		register_rest_route(
			$this->namespace,
			'/messages',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_messages' ),
					'permission_callback' => array( $this, 'is_authenticated' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'send_message' ),
					'permission_callback' => array( $this, 'is_authenticated' ),
				),
			)
		);

		// Conversations.
		register_rest_route(
			$this->namespace,
			'/conversations',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_conversations' ),
				'permission_callback' => array( $this, 'is_authenticated' ),
			)
		);

		$this->endpoints['messaging'] = true;
	}

	/**
	 * Register video routes.
	 */
	protected function register_video_routes() {
		if ( class_exists( 'WPMatch_Video_Chat' ) ) {
			// Video calls.
			register_rest_route(
				$this->namespace,
				'/video/call',
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'initiate_video_call' ),
					'permission_callback' => array( $this, 'is_authenticated' ),
				)
			);

			$this->endpoints['video'] = true;
		}
	}

	/**
	 * Register AI routes.
	 */
	protected function register_ai_routes() {
		if ( class_exists( 'WPMatch_AI_Chatbot' ) ) {
			// AI suggestions.
			register_rest_route(
				$this->namespace,
				'/ai/suggestions',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_ai_suggestions' ),
					'permission_callback' => array( $this, 'is_authenticated' ),
				)
			);

			// Conversation starters.
			register_rest_route(
				$this->namespace,
				'/ai/conversation-starters',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_conversation_starters' ),
					'permission_callback' => array( $this, 'is_authenticated' ),
				)
			);

			$this->endpoints['ai'] = true;
		}
	}

	/**
	 * Register social routes.
	 */
	protected function register_social_routes() {
		if ( class_exists( 'WPMatch_Social_Integrations' ) ) {
			// Social login.
			register_rest_route(
				$this->namespace,
				'/social/login',
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'social_login' ),
					'permission_callback' => '__return_true',
				)
			);

			$this->endpoints['social'] = true;
		}
	}

	/**
	 * Register verification routes.
	 */
	protected function register_verification_routes() {
		if ( class_exists( 'WPMatch_Photo_Verification' ) ) {
			// Photo verification.
			register_rest_route(
				$this->namespace,
				'/verification/photo',
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'submit_photo_verification' ),
					'permission_callback' => array( $this, 'is_authenticated' ),
				)
			);

			$this->endpoints['verification'] = true;
		}
	}

	/**
	 * Register analytics routes.
	 */
	protected function register_analytics_routes() {
		// Analytics.
		register_rest_route(
			$this->namespace,
			'/analytics/track',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'track_event' ),
				'permission_callback' => array( $this, 'is_authenticated' ),
			)
		);

		$this->endpoints['analytics'] = true;
	}

	/**
	 * Log registered routes for debugging.
	 */
	protected function log_registered_routes() {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'WPMatch REST API Endpoints Registered: ' . wp_json_encode( array_keys( $this->endpoints ) ) );
		}
	}

	/**
	 * Permission callbacks.
	 */
	public function is_authenticated() {
		return is_user_logged_in();
	}

	public function can_view_user( $request ) {
		return is_user_logged_in();
	}

	public function can_edit_user( $request ) {
		$user_id = $request->get_param( 'id' );
		return get_current_user_id() == $user_id || current_user_can( 'edit_users' );
	}

	public function can_delete_user( $request ) {
		return current_user_can( 'delete_users' );
	}

	public function can_view_profile( $request ) {
		return is_user_logged_in();
	}

	public function can_edit_profile( $request ) {
		$user_id = $request->get_param( 'user_id' );
		return get_current_user_id() == $user_id;
	}

	public function can_create_events() {
		return is_user_logged_in() && current_user_can( 'publish_posts' );
	}

	public function can_edit_event( $request ) {
		// Check if user is event owner or admin.
		return is_user_logged_in();
	}

	public function can_delete_event( $request ) {
		// Check if user is event owner or admin.
		return is_user_logged_in();
	}

	/**
	 * Get user registration arguments.
	 */
	protected function get_user_registration_args() {
		return array(
			'username' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_user',
			),
			'email'    => array(
				'required'          => true,
				'type'              => 'string',
				'format'            => 'email',
				'sanitize_callback' => 'sanitize_email',
			),
			'password' => array(
				'required' => true,
				'type'     => 'string',
			),
		);
	}

	/**
	 * Get user update arguments.
	 */
	protected function get_user_update_args() {
		return array(
			'first_name' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'last_name'  => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'email'      => array(
				'type'              => 'string',
				'format'            => 'email',
				'sanitize_callback' => 'sanitize_email',
			),
		);
	}

	/**
	 * Get profile update arguments.
	 */
	protected function get_profile_update_args() {
		return array(
			'bio'         => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'age'         => array(
				'type' => 'integer',
			),
			'location'    => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'interests'   => array(
				'type' => 'array',
			),
			'preferences' => array(
				'type' => 'object',
			),
		);
	}

	/**
	 * Get search arguments.
	 */
	protected function get_search_args() {
		return array(
			'query'     => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'age_min'   => array(
				'type' => 'integer',
			),
			'age_max'   => array(
				'type' => 'integer',
			),
			'distance'  => array(
				'type' => 'integer',
			),
			'interests' => array(
				'type' => 'array',
			),
			'order_by'  => array(
				'default' => 'distance',
				'type'    => 'string',
				'enum'    => array( 'distance', 'age', 'activity', 'match_score' ),
			),
			'page'      => array(
				'default' => 1,
				'type'    => 'integer',
			),
			'per_page'  => array(
				'default' => 20,
				'type'    => 'integer',
			),
		);
	}

	/**
	 * Get event creation arguments.
	 */
	protected function get_event_creation_args() {
		return array(
			'title'            => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'description'      => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'event_type'       => array(
				'required' => true,
				'type'     => 'string',
				'enum'     => array( 'virtual_meetup', 'speed_dating', 'group_activity' ),
			),
			'start_time'       => array(
				'required' => true,
				'type'     => 'string',
				'format'   => 'date-time',
			),
			'duration_minutes' => array(
				'required' => true,
				'type'     => 'integer',
			),
			'max_participants' => array(
				'type' => 'integer',
			),
		);
	}

	/**
	 * Callback methods - these would normally delegate to service classes.
	 */
	public function login( $request ) {
		// Implement login logic.
		return rest_ensure_response( array( 'message' => 'Login endpoint' ) );
	}

	public function logout( $request ) {
		wp_logout();
		return rest_ensure_response( array( 'success' => true ) );
	}

	public function refresh_token( $request ) {
		// Implement token refresh logic.
		return rest_ensure_response( array( 'message' => 'Token refresh endpoint' ) );
	}

	public function register_user( $request ) {
		// Delegate to user service.
		return rest_ensure_response( array( 'message' => 'User registration endpoint' ) );
	}

	public function get_current_user( $request ) {
		$user = wp_get_current_user();
		return rest_ensure_response(
			array(
				'id'       => $user->ID,
				'username' => $user->user_login,
				'email'    => $user->user_email,
				'name'     => $user->display_name,
			)
		);
	}

	public function get_user( $request ) {
		// Implement get user logic.
		return rest_ensure_response( array( 'message' => 'Get user endpoint' ) );
	}

	public function update_user( $request ) {
		// Implement update user logic.
		return rest_ensure_response( array( 'message' => 'Update user endpoint' ) );
	}

	public function delete_user( $request ) {
		// Implement delete user logic.
		return rest_ensure_response( array( 'message' => 'Delete user endpoint' ) );
	}

	public function get_profile( $request ) {
		// Implement get profile logic.
		return rest_ensure_response( array( 'message' => 'Get profile endpoint' ) );
	}

	public function update_profile( $request ) {
		// Implement update profile logic.
		return rest_ensure_response( array( 'message' => 'Update profile endpoint' ) );
	}

	public function get_matches( $request ) {
		// Implement get matches logic.
		return rest_ensure_response( array( 'message' => 'Get matches endpoint' ) );
	}

	public function create_swipe( $request ) {
		// Implement swipe logic.
		return rest_ensure_response( array( 'message' => 'Swipe endpoint' ) );
	}

	public function get_queue( $request ) {
		// Implement get queue logic.
		return rest_ensure_response( array( 'message' => 'Get queue endpoint' ) );
	}

	public function search_users( $request ) {
		// Implement search logic.
		return rest_ensure_response( array( 'message' => 'Search endpoint' ) );
	}

	public function get_messages( $request ) {
		// Implement get messages logic.
		return rest_ensure_response( array( 'message' => 'Get messages endpoint' ) );
	}

	public function send_message( $request ) {
		// Implement send message logic.
		return rest_ensure_response( array( 'message' => 'Send message endpoint' ) );
	}

	public function get_conversations( $request ) {
		// Implement get conversations logic.
		return rest_ensure_response( array( 'message' => 'Get conversations endpoint' ) );
	}

	public function initiate_video_call( $request ) {
		// Implement video call logic.
		return rest_ensure_response( array( 'message' => 'Video call endpoint' ) );
	}

	public function get_ai_suggestions( $request ) {
		// Implement AI suggestions logic.
		return rest_ensure_response( array( 'message' => 'AI suggestions endpoint' ) );
	}

	public function get_conversation_starters( $request ) {
		// Implement conversation starters logic.
		return rest_ensure_response( array( 'message' => 'Conversation starters endpoint' ) );
	}

	public function social_login( $request ) {
		// Implement social login logic.
		return rest_ensure_response( array( 'message' => 'Social login endpoint' ) );
	}

	public function submit_photo_verification( $request ) {
		// Implement photo verification logic.
		return rest_ensure_response( array( 'message' => 'Photo verification endpoint' ) );
	}

	public function track_event( $request ) {
		// Implement event tracking logic.
		return rest_ensure_response( array( 'message' => 'Track event endpoint' ) );
	}

	/**
	 * Register user media routes.
	 */
	protected function register_user_media_routes() {
		if ( ! class_exists( 'WPMatch_User_Media' ) ) {
			return;
		}

		$user_media = new WPMatch_User_Media( 'wpmatch', WPMATCH_VERSION );

		// Get user media.
		register_rest_route(
			$this->namespace,
			'/media/user/(?P<user_id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $user_media, 'api_get_user_media' ),
				'permission_callback' => array( $this, 'is_authenticated' ),
			)
		);

		// Upload media.
		register_rest_route(
			$this->namespace,
			'/media/upload',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $user_media, 'api_upload_media' ),
				'permission_callback' => array( $this, 'is_authenticated' ),
			)
		);

		// Update media.
		register_rest_route(
			$this->namespace,
			'/media/(?P<media_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $user_media, 'api_update_media' ),
					'permission_callback' => array( $this, 'is_authenticated' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $user_media, 'api_delete_media' ),
					'permission_callback' => array( $this, 'is_authenticated' ),
				),
			)
		);

		// Set primary media.
		register_rest_route(
			$this->namespace,
			'/media/(?P<media_id>\d+)/primary',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $user_media, 'api_set_primary_media' ),
				'permission_callback' => array( $this, 'is_authenticated' ),
			)
		);

		// Reorder media.
		register_rest_route(
			$this->namespace,
			'/media/reorder',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $user_media, 'api_reorder_media' ),
				'permission_callback' => array( $this, 'is_authenticated' ),
			)
		);

		$this->endpoints['user_media'] = true;
	}

	/**
	 * Register user interests routes.
	 */
	protected function register_user_interests_routes() {
		if ( ! class_exists( 'WPMatch_User_Interests' ) ) {
			return;
		}

		$user_interests = new WPMatch_User_Interests( 'wpmatch', WPMATCH_VERSION );

		// Get user interests.
		register_rest_route(
			$this->namespace,
			'/interests/user/(?P<user_id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $user_interests, 'api_get_user_interests' ),
				'permission_callback' => array( $this, 'is_authenticated' ),
			)
		);

		// Add user interest.
		register_rest_route(
			$this->namespace,
			'/interests/user/add',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $user_interests, 'api_add_user_interest' ),
				'permission_callback' => array( $this, 'is_authenticated' ),
			)
		);

		// Remove user interest.
		register_rest_route(
			$this->namespace,
			'/interests/user/remove',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $user_interests, 'api_remove_user_interest' ),
				'permission_callback' => array( $this, 'is_authenticated' ),
			)
		);

		// Get interest categories.
		register_rest_route(
			$this->namespace,
			'/interests/categories',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $user_interests, 'api_get_categories' ),
				'permission_callback' => '__return_true',
			)
		);

		// Search interests.
		register_rest_route(
			$this->namespace,
			'/interests/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $user_interests, 'api_search_interests' ),
				'permission_callback' => '__return_true',
			)
		);

		// Get interest compatibility.
		register_rest_route(
			$this->namespace,
			'/interests/compatibility/(?P<user_id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $user_interests, 'api_get_compatibility' ),
				'permission_callback' => array( $this, 'is_authenticated' ),
			)
		);

		$this->endpoints['user_interests'] = true;
	}

	/**
	 * Register user preferences routes.
	 */
	protected function register_user_preferences_routes() {
		if ( ! class_exists( 'WPMatch_User_Preferences' ) ) {
			return;
		}

		$user_preferences = new WPMatch_User_Preferences( 'wpmatch', WPMATCH_VERSION );

		// Get user preferences.
		register_rest_route(
			$this->namespace,
			'/preferences/user/(?P<user_id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $user_preferences, 'api_get_user_preferences' ),
				'permission_callback' => array( $this, 'is_authenticated' ),
			)
		);

		// Update user preferences.
		register_rest_route(
			$this->namespace,
			'/preferences/update',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $user_preferences, 'api_update_user_preferences' ),
				'permission_callback' => array( $this, 'is_authenticated' ),
			)
		);

		// Block user.
		register_rest_route(
			$this->namespace,
			'/preferences/block/(?P<user_id>\d+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $user_preferences, 'api_block_user' ),
				'permission_callback' => array( $this, 'is_authenticated' ),
			)
		);

		// Unblock user.
		register_rest_route(
			$this->namespace,
			'/preferences/unblock/(?P<user_id>\d+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $user_preferences, 'api_unblock_user' ),
				'permission_callback' => array( $this, 'is_authenticated' ),
			)
		);

		// Get blocked users.
		register_rest_route(
			$this->namespace,
			'/preferences/blocked',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $user_preferences, 'api_get_blocked_users' ),
				'permission_callback' => array( $this, 'is_authenticated' ),
			)
		);

		// Check preference match.
		register_rest_route(
			$this->namespace,
			'/preferences/match/(?P<candidate_id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $user_preferences, 'api_check_preference_match' ),
				'permission_callback' => array( $this, 'is_authenticated' ),
			)
		);

		$this->endpoints['user_preferences'] = true;
	}
}

// Initialize the REST controller.
WPMatch_REST_Controller::get_instance();
