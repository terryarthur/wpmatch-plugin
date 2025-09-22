<?php
/**
 * WPMatch Events System
 *
 * Handles dating events, virtual meetups, speed dating sessions,
 * and event management functionality.
 *
 * @package WPMatch
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPMatch Events class.
 */
class WPMatch_Events {

	/**
	 * Initialize the events system.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'create_database_tables' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );

		// Event scheduling hooks.
		add_action( 'wpmatch_event_reminder', array( __CLASS__, 'send_event_reminder' ), 10, 2 );
		add_action( 'wpmatch_event_start', array( __CLASS__, 'start_event' ) );
		add_action( 'wpmatch_event_end', array( __CLASS__, 'end_event' ) );

		// Speed dating hooks.
		add_action( 'wpmatch_speed_dating_round', array( __CLASS__, 'advance_speed_dating_round' ) );
		add_action( 'wpmatch_speed_dating_end', array( __CLASS__, 'end_speed_dating_session' ) );
	}

	/**
	 * Create database tables for events.
	 */
	public static function create_database_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Events table.
		$table_name = $wpdb->prefix . 'wpmatch_events';
		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			description text,
			event_type enum('virtual_meetup','speed_dating','group_chat','video_party','themed_night','icebreaker','singles_mixer','hobby_meetup') NOT NULL DEFAULT 'virtual_meetup',
			status enum('draft','published','cancelled','completed') NOT NULL DEFAULT 'draft',
			creator_id bigint(20) NOT NULL,
			max_participants int DEFAULT 50,
			current_participants int DEFAULT 0,
			age_min int DEFAULT 18,
			age_max int DEFAULT 100,
			location_type enum('virtual','hybrid','in_person') NOT NULL DEFAULT 'virtual',
			location_data longtext,
			event_start datetime NOT NULL,
			event_end datetime NOT NULL,
			registration_deadline datetime,
			entry_fee decimal(10,2) DEFAULT 0.00,
			currency varchar(3) DEFAULT 'USD',
			featured tinyint(1) DEFAULT 0,
			settings longtext,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY creator_id (creator_id),
			KEY event_type (event_type),
			KEY status (status),
			KEY event_start (event_start),
			KEY featured (featured)
		) $charset_collate;";

		// Event registrations table.
		$table_name = $wpdb->prefix . 'wpmatch_event_registrations';
		$sql .= "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			event_id bigint(20) NOT NULL,
			user_id bigint(20) NOT NULL,
			registration_status enum('registered','confirmed','cancelled','no_show','attended') NOT NULL DEFAULT 'registered',
			payment_status enum('pending','paid','refunded','failed') NOT NULL DEFAULT 'pending',
			payment_amount decimal(10,2) DEFAULT 0.00,
			registration_data longtext,
			registered_at datetime DEFAULT CURRENT_TIMESTAMP,
			confirmed_at datetime,
			PRIMARY KEY (id),
			UNIQUE KEY event_user (event_id, user_id),
			KEY event_id (event_id),
			KEY user_id (user_id),
			KEY registration_status (registration_status),
			KEY payment_status (payment_status)
		) $charset_collate;";

		// Speed dating sessions table.
		$table_name = $wpdb->prefix . 'wpmatch_speed_dating_sessions';
		$sql .= "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			event_id bigint(20) NOT NULL,
			session_name varchar(255),
			current_round int DEFAULT 0,
			total_rounds int DEFAULT 6,
			round_duration int DEFAULT 300,
			break_duration int DEFAULT 60,
			status enum('waiting','active','paused','completed') NOT NULL DEFAULT 'waiting',
			participants longtext,
			rounds_data longtext,
			matching_results longtext,
			session_start datetime,
			session_end datetime,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY event_id (event_id),
			KEY status (status),
			KEY session_start (session_start)
		) $charset_collate;";

		// Event matches table (for post-event connections).
		$table_name = $wpdb->prefix . 'wpmatch_event_matches';
		$sql .= "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			event_id bigint(20) NOT NULL,
			user1_id bigint(20) NOT NULL,
			user2_id bigint(20) NOT NULL,
			match_type enum('mutual_interest','speed_dating_match','event_connection') NOT NULL,
			user1_interested tinyint(1) DEFAULT 0,
			user2_interested tinyint(1) DEFAULT 0,
			is_mutual tinyint(1) DEFAULT 0,
			match_score decimal(5,2),
			interaction_data longtext,
			matched_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY event_users (event_id, user1_id, user2_id),
			KEY event_id (event_id),
			KEY user1_id (user1_id),
			KEY user2_id (user2_id),
			KEY is_mutual (is_mutual),
			KEY match_type (match_type)
		) $charset_collate;";

		// Event chat rooms table.
		$table_name = $wpdb->prefix . 'wpmatch_event_chat_rooms';
		$sql .= "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			event_id bigint(20) NOT NULL,
			room_name varchar(255) NOT NULL,
			room_type enum('main','breakout','private','speed_dating') NOT NULL DEFAULT 'main',
			participant_limit int DEFAULT 20,
			current_participants int DEFAULT 0,
			room_settings longtext,
			is_active tinyint(1) DEFAULT 1,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY event_id (event_id),
			KEY room_type (room_type),
			KEY is_active (is_active)
		) $charset_collate;";

		// Event feedback table.
		$table_name = $wpdb->prefix . 'wpmatch_event_feedback';
		$sql .= "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			event_id bigint(20) NOT NULL,
			user_id bigint(20) NOT NULL,
			rating int NOT NULL,
			feedback_text text,
			would_attend_again tinyint(1) DEFAULT 1,
			favorite_aspects longtext,
			improvement_suggestions text,
			submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY event_user_feedback (event_id, user_id),
			KEY event_id (event_id),
			KEY user_id (user_id),
			KEY rating (rating)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Register REST API routes.
	 */
	public static function register_rest_routes() {
		// Events management.
		register_rest_route( 'wpmatch/v1', '/events', array(
			'methods' => 'GET',
			'callback' => array( __CLASS__, 'api_get_events' ),
			'permission_callback' => '__return_true',
			'args' => array(
				'type' => array( 'required' => false ),
				'status' => array( 'default' => 'published' ),
				'featured' => array( 'required' => false ),
				'page' => array( 'default' => 1 ),
				'per_page' => array( 'default' => 10 ),
			),
		) );

		register_rest_route( 'wpmatch/v1', '/events', array(
			'methods' => 'POST',
			'callback' => array( __CLASS__, 'api_create_event' ),
			'permission_callback' => array( __CLASS__, 'check_create_permission' ),
			'args' => array(
				'title' => array( 'required' => true ),
				'description' => array( 'required' => false ),
				'event_type' => array( 'required' => true ),
				'event_start' => array( 'required' => true ),
				'event_end' => array( 'required' => true ),
				'max_participants' => array( 'default' => 50 ),
				'age_min' => array( 'default' => 18 ),
				'age_max' => array( 'default' => 100 ),
				'entry_fee' => array( 'default' => 0 ),
			),
		) );

		register_rest_route( 'wpmatch/v1', '/events/(?P<event_id>\d+)', array(
			'methods' => 'GET',
			'callback' => array( __CLASS__, 'api_get_event' ),
			'permission_callback' => '__return_true',
			'args' => array(
				'event_id' => array( 'required' => true ),
			),
		) );

		register_rest_route( 'wpmatch/v1', '/events/(?P<event_id>\d+)', array(
			'methods' => array( 'PUT', 'PATCH' ),
			'callback' => array( __CLASS__, 'api_update_event' ),
			'permission_callback' => array( __CLASS__, 'check_edit_permission' ),
			'args' => array(
				'event_id' => array( 'required' => true ),
			),
		) );

		// Event registration.
		register_rest_route( 'wpmatch/v1', '/events/(?P<event_id>\d+)/register', array(
			'methods' => 'POST',
			'callback' => array( __CLASS__, 'api_register_for_event' ),
			'permission_callback' => array( __CLASS__, 'check_user_permission' ),
			'args' => array(
				'event_id' => array( 'required' => true ),
			),
		) );

		register_rest_route( 'wpmatch/v1', '/events/(?P<event_id>\d+)/unregister', array(
			'methods' => 'POST',
			'callback' => array( __CLASS__, 'api_unregister_from_event' ),
			'permission_callback' => array( __CLASS__, 'check_user_permission' ),
			'args' => array(
				'event_id' => array( 'required' => true ),
			),
		) );

		// Event participants.
		register_rest_route( 'wpmatch/v1', '/events/(?P<event_id>\d+)/participants', array(
			'methods' => 'GET',
			'callback' => array( __CLASS__, 'api_get_event_participants' ),
			'permission_callback' => array( __CLASS__, 'check_user_permission' ),
			'args' => array(
				'event_id' => array( 'required' => true ),
			),
		) );

		// Speed dating.
		register_rest_route( 'wpmatch/v1', '/events/(?P<event_id>\d+)/speed-dating/start', array(
			'methods' => 'POST',
			'callback' => array( __CLASS__, 'api_start_speed_dating' ),
			'permission_callback' => array( __CLASS__, 'check_organizer_permission' ),
			'args' => array(
				'event_id' => array( 'required' => true ),
				'round_duration' => array( 'default' => 300 ),
				'total_rounds' => array( 'default' => 6 ),
			),
		) );

		register_rest_route( 'wpmatch/v1', '/events/(?P<event_id>\d+)/speed-dating/status', array(
			'methods' => 'GET',
			'callback' => array( __CLASS__, 'api_get_speed_dating_status' ),
			'permission_callback' => array( __CLASS__, 'check_user_permission' ),
			'args' => array(
				'event_id' => array( 'required' => true ),
			),
		) );

		register_rest_route( 'wpmatch/v1', '/events/(?P<event_id>\d+)/speed-dating/interest', array(
			'methods' => 'POST',
			'callback' => array( __CLASS__, 'api_mark_speed_dating_interest' ),
			'permission_callback' => array( __CLASS__, 'check_user_permission' ),
			'args' => array(
				'event_id' => array( 'required' => true ),
				'partner_id' => array( 'required' => true ),
				'interested' => array( 'required' => true ),
			),
		) );

		// Event feedback.
		register_rest_route( 'wpmatch/v1', '/events/(?P<event_id>\d+)/feedback', array(
			'methods' => 'POST',
			'callback' => array( __CLASS__, 'api_submit_feedback' ),
			'permission_callback' => array( __CLASS__, 'check_user_permission' ),
			'args' => array(
				'event_id' => array( 'required' => true ),
				'rating' => array( 'required' => true ),
				'feedback_text' => array( 'required' => false ),
				'would_attend_again' => array( 'default' => true ),
			),
		) );

		// My events.
		register_rest_route( 'wpmatch/v1', '/events/my-events', array(
			'methods' => 'GET',
			'callback' => array( __CLASS__, 'api_get_my_events' ),
			'permission_callback' => array( __CLASS__, 'check_user_permission' ),
			'args' => array(
				'type' => array( 'default' => 'registered' ), // registered, created, attended
			),
		) );
	}

	/**
	 * Enqueue frontend scripts and styles.
	 */
	public static function enqueue_scripts() {
		if ( is_page() || is_singular() ) {
			wp_enqueue_script(
				'wpmatch-events',
				WPMATCH_PLUGIN_URL . 'public/js/wpmatch-events.js',
				array( 'jquery' ),
				WPMATCH_VERSION,
				true
			);

			wp_enqueue_style(
				'wpmatch-events',
				WPMATCH_PLUGIN_URL . 'public/css/wpmatch-events.css',
				array(),
				WPMATCH_VERSION
			);

			wp_localize_script( 'wpmatch-events', 'wpMatchEvents', array(
				'apiUrl' => rest_url( 'wpmatch/v1' ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'strings' => array(
					'registerSuccess' => __( 'Successfully registered for event!', 'wpmatch' ),
					'registerError' => __( 'Registration failed. Please try again.', 'wpmatch' ),
					'eventFull' => __( 'Sorry, this event is full.', 'wpmatch' ),
					'alreadyRegistered' => __( 'You are already registered for this event.', 'wpmatch' ),
					'confirmUnregister' => __( 'Are you sure you want to unregister from this event?', 'wpmatch' ),
					'feedbackSubmitted' => __( 'Thank you for your feedback!', 'wpmatch' ),
					'speedDatingMatch' => __( 'You have a mutual match!', 'wpmatch' ),
					'nextRound' => __( 'Get ready for the next round!', 'wpmatch' ),
					'eventStarting' => __( 'Event is starting in 5 minutes!', 'wpmatch' ),
					'eventStarted' => __( 'Event has started!', 'wpmatch' ),
				),
			) );
		}
	}

	/**
	 * Get events API endpoint.
	 */
	public static function api_get_events( $request ) {
		global $wpdb;

		$type = $request->get_param( 'type' );
		$status = $request->get_param( 'status' );
		$featured = $request->get_param( 'featured' );
		$page = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );

		$where_conditions = array( "status = %s" );
		$where_values = array( $status );

		if ( $type ) {
			$where_conditions[] = "event_type = %s";
			$where_values[] = $type;
		}

		if ( $featured !== null ) {
			$where_conditions[] = "featured = %d";
			$where_values[] = $featured ? 1 : 0;
		}

		// Only show future events by default.
		$where_conditions[] = "event_start > %s";
		$where_values[] = current_time( 'mysql' );

		$where_clause = implode( ' AND ', $where_conditions );
		$offset = ( $page - 1 ) * $per_page;

		$table_name = $wpdb->prefix . 'wpmatch_events';
		$query = $wpdb->prepare(
			"SELECT * FROM $table_name WHERE $where_clause ORDER BY event_start ASC LIMIT %d OFFSET %d",
			array_merge( $where_values, array( $per_page, $offset ) )
		);

		$events = $wpdb->get_results( $query );

		// Enhance events with additional data.
		foreach ( $events as &$event ) {
			$event = self::enhance_event_data( $event );
		}

		return rest_ensure_response( array(
			'success' => true,
			'data' => $events,
			'pagination' => array(
				'page' => $page,
				'per_page' => $per_page,
				'total_pages' => ceil( $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM $table_name WHERE $where_clause",
					$where_values
				) ) / $per_page ),
			),
		) );
	}

	/**
	 * Get single event API endpoint.
	 */
	public static function api_get_event( $request ) {
		global $wpdb;

		$event_id = $request->get_param( 'event_id' );
		$table_name = $wpdb->prefix . 'wpmatch_events';

		$event = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE id = %d",
			$event_id
		) );

		if ( ! $event ) {
			return new WP_Error( 'event_not_found', __( 'Event not found.', 'wpmatch' ), array( 'status' => 404 ) );
		}

		$event = self::enhance_event_data( $event );

		return rest_ensure_response( array(
			'success' => true,
			'data' => $event,
		) );
	}

	/**
	 * Create event API endpoint.
	 */
	public static function api_create_event( $request ) {
		global $wpdb;

		$user_id = get_current_user_id();
		$title = sanitize_text_field( $request->get_param( 'title' ) );
		$description = sanitize_textarea_field( $request->get_param( 'description' ) );
		$event_type = sanitize_text_field( $request->get_param( 'event_type' ) );
		$event_start = sanitize_text_field( $request->get_param( 'event_start' ) );
		$event_end = sanitize_text_field( $request->get_param( 'event_end' ) );
		$max_participants = absint( $request->get_param( 'max_participants' ) );
		$age_min = absint( $request->get_param( 'age_min' ) );
		$age_max = absint( $request->get_param( 'age_max' ) );
		$entry_fee = floatval( $request->get_param( 'entry_fee' ) );

		$table_name = $wpdb->prefix . 'wpmatch_events';
		$result = $wpdb->insert(
			$table_name,
			array(
				'title' => $title,
				'description' => $description,
				'event_type' => $event_type,
				'creator_id' => $user_id,
				'max_participants' => $max_participants,
				'age_min' => $age_min,
				'age_max' => $age_max,
				'event_start' => $event_start,
				'event_end' => $event_end,
				'entry_fee' => $entry_fee,
				'status' => 'published',
			),
			array( '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%f', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'create_failed', __( 'Failed to create event.', 'wpmatch' ), array( 'status' => 500 ) );
		}

		$event_id = $wpdb->insert_id;

		// Schedule event reminders.
		self::schedule_event_reminders( $event_id, $event_start );

		return rest_ensure_response( array(
			'success' => true,
			'data' => array(
				'event_id' => $event_id,
				'message' => __( 'Event created successfully!', 'wpmatch' ),
			),
		) );
	}

	/**
	 * Register for event API endpoint.
	 */
	public static function api_register_for_event( $request ) {
		global $wpdb;

		$user_id = get_current_user_id();
		$event_id = $request->get_param( 'event_id' );

		// Check if event exists and is available.
		$event = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}wpmatch_events WHERE id = %d AND status = 'published'",
			$event_id
		) );

		if ( ! $event ) {
			return new WP_Error( 'event_not_found', __( 'Event not found or not available.', 'wpmatch' ), array( 'status' => 404 ) );
		}

		// Check if event is full.
		if ( $event->current_participants >= $event->max_participants ) {
			return new WP_Error( 'event_full', __( 'This event is full.', 'wpmatch' ), array( 'status' => 400 ) );
		}

		// Check if user is already registered.
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}wpmatch_event_registrations WHERE event_id = %d AND user_id = %d",
			$event_id,
			$user_id
		) );

		if ( $existing ) {
			return new WP_Error( 'already_registered', __( 'You are already registered for this event.', 'wpmatch' ), array( 'status' => 400 ) );
		}

		// Register user.
		$registration_table = $wpdb->prefix . 'wpmatch_event_registrations';
		$result = $wpdb->insert(
			$registration_table,
			array(
				'event_id' => $event_id,
				'user_id' => $user_id,
				'registration_status' => 'registered',
				'payment_status' => $event->entry_fee > 0 ? 'pending' : 'paid',
				'payment_amount' => $event->entry_fee,
			),
			array( '%d', '%d', '%s', '%s', '%f' )
		);

		if ( false === $result ) {
			return new WP_Error( 'registration_failed', __( 'Registration failed.', 'wpmatch' ), array( 'status' => 500 ) );
		}

		// Update participant count.
		$wpdb->update(
			$wpdb->prefix . 'wpmatch_events',
			array( 'current_participants' => $event->current_participants + 1 ),
			array( 'id' => $event_id ),
			array( '%d' ),
			array( '%d' )
		);

		// Trigger gamification.
		if ( class_exists( 'WPMatch_Gamification' ) ) {
			do_action( 'wpmatch_event_registered', 'event_registration', array( 'user_id' => $user_id ) );
		}

		return rest_ensure_response( array(
			'success' => true,
			'data' => array(
				'message' => __( 'Successfully registered for event!', 'wpmatch' ),
				'registration_id' => $wpdb->insert_id,
				'requires_payment' => $event->entry_fee > 0,
			),
		) );
	}

	/**
	 * Start speed dating session API endpoint.
	 */
	public static function api_start_speed_dating( $request ) {
		global $wpdb;

		$event_id = $request->get_param( 'event_id' );
		$round_duration = $request->get_param( 'round_duration' );
		$total_rounds = $request->get_param( 'total_rounds' );

		// Get event participants.
		$participants = $wpdb->get_results( $wpdb->prepare(
			"SELECT user_id FROM {$wpdb->prefix}wpmatch_event_registrations
			WHERE event_id = %d AND registration_status = 'confirmed'",
			$event_id
		) );

		if ( count( $participants ) < 4 ) {
			return new WP_Error( 'insufficient_participants', __( 'Need at least 4 participants for speed dating.', 'wpmatch' ), array( 'status' => 400 ) );
		}

		// Create speed dating session.
		$session_table = $wpdb->prefix . 'wpmatch_speed_dating_sessions';
		$result = $wpdb->insert(
			$session_table,
			array(
				'event_id' => $event_id,
				'session_name' => 'Speed Dating Session',
				'total_rounds' => $total_rounds,
				'round_duration' => $round_duration,
				'status' => 'active',
				'participants' => wp_json_encode( wp_list_pluck( $participants, 'user_id' ) ),
				'session_start' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'session_failed', __( 'Failed to start speed dating session.', 'wpmatch' ), array( 'status' => 500 ) );
		}

		$session_id = $wpdb->insert_id;

		// Schedule first round end.
		wp_schedule_single_event(
			time() + $round_duration,
			'wpmatch_speed_dating_round',
			array( $session_id )
		);

		return rest_ensure_response( array(
			'success' => true,
			'data' => array(
				'session_id' => $session_id,
				'message' => __( 'Speed dating session started!', 'wpmatch' ),
				'round_duration' => $round_duration,
				'participants' => count( $participants ),
			),
		) );
	}

	/**
	 * Submit event feedback API endpoint.
	 */
	public static function api_submit_feedback( $request ) {
		global $wpdb;

		$user_id = get_current_user_id();
		$event_id = $request->get_param( 'event_id' );
		$rating = absint( $request->get_param( 'rating' ) );
		$feedback_text = sanitize_textarea_field( $request->get_param( 'feedback_text' ) );
		$would_attend_again = $request->get_param( 'would_attend_again' ) ? 1 : 0;

		if ( $rating < 1 || $rating > 5 ) {
			return new WP_Error( 'invalid_rating', __( 'Rating must be between 1 and 5.', 'wpmatch' ), array( 'status' => 400 ) );
		}

		$feedback_table = $wpdb->prefix . 'wpmatch_event_feedback';
		$result = $wpdb->replace(
			$feedback_table,
			array(
				'event_id' => $event_id,
				'user_id' => $user_id,
				'rating' => $rating,
				'feedback_text' => $feedback_text,
				'would_attend_again' => $would_attend_again,
			),
			array( '%d', '%d', '%d', '%s', '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'feedback_failed', __( 'Failed to submit feedback.', 'wpmatch' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array(
			'success' => true,
			'data' => array(
				'message' => __( 'Thank you for your feedback!', 'wpmatch' ),
			),
		) );
	}

	/**
	 * Get my events API endpoint.
	 */
	public static function api_get_my_events( $request ) {
		return rest_ensure_response( array(
			'success' => false,
			'message' => 'Not implemented yet',
		) );
	}

	/**
	 * Enhanced event data with additional information.
	 */
	private static function enhance_event_data( $event ) {
		// Add creator information.
		$creator = get_userdata( $event->creator_id );
		$event->creator_name = $creator ? $creator->display_name : 'Unknown';

		// Add time calculations.
		$event->is_upcoming = strtotime( $event->event_start ) > time();
		$event->is_happening_now = time() >= strtotime( $event->event_start ) && time() <= strtotime( $event->event_end );
		$event->time_until_start = $event->is_upcoming ? human_time_diff( time(), strtotime( $event->event_start ) ) : null;

		// Add registration status for current user.
		if ( is_user_logged_in() ) {
			global $wpdb;
			$user_id = get_current_user_id();
			$registration = $wpdb->get_row( $wpdb->prepare(
				"SELECT registration_status, payment_status FROM {$wpdb->prefix}wpmatch_event_registrations
				WHERE event_id = %d AND user_id = %d",
				$event->id,
				$user_id
			) );
			$event->user_registered = $registration ? $registration->registration_status : false;
			$event->payment_status = $registration ? $registration->payment_status : null;
		}

		// Parse settings if they exist.
		if ( $event->settings ) {
			$event->settings = json_decode( $event->settings, true );
		}

		return $event;
	}

	/**
	 * Schedule event reminders.
	 */
	private static function schedule_event_reminders( $event_id, $event_start ) {
		$event_time = strtotime( $event_start );

		// 24 hour reminder.
		$reminder_24h = $event_time - ( 24 * 60 * 60 );
		if ( $reminder_24h > time() ) {
			wp_schedule_single_event( $reminder_24h, 'wpmatch_event_reminder', array( $event_id, '24_hours' ) );
		}

		// 1 hour reminder.
		$reminder_1h = $event_time - ( 60 * 60 );
		if ( $reminder_1h > time() ) {
			wp_schedule_single_event( $reminder_1h, 'wpmatch_event_reminder', array( $event_id, '1_hour' ) );
		}

		// Event start.
		wp_schedule_single_event( $event_time, 'wpmatch_event_start', array( $event_id ) );
	}

	/**
	 * Send event reminder.
	 */
	public static function send_event_reminder( $event_id, $timing ) {
		// Implementation for sending reminders via email/push notifications.
		// This would integrate with the site's notification system.
	}

	/**
	 * Start event.
	 */
	public static function start_event( $event_id ) {
		// Implementation for event start procedures.
		// Could send notifications, open chat rooms, etc.
	}

	/**
	 * End event.
	 */
	public static function end_event( $event_id ) {
		// Implementation for event end procedures.
		// Could close chat rooms, generate reports, etc.
	}

	/**
	 * Advance speed dating round.
	 */
	public static function advance_speed_dating_round( $session_id ) {
		global $wpdb;

		$session_table = $wpdb->prefix . 'wpmatch_speed_dating_sessions';
		$session = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $session_table WHERE id = %d",
			$session_id
		) );

		if ( ! $session ) {
			return;
		}

		$current_round = $session->current_round + 1;

		if ( $current_round >= $session->total_rounds ) {
			// End session.
			wp_schedule_single_event( time(), 'wpmatch_speed_dating_end', array( $session_id ) );
			return;
		}

		// Update to next round.
		$wpdb->update(
			$session_table,
			array( 'current_round' => $current_round ),
			array( 'id' => $session_id ),
			array( '%d' ),
			array( '%d' )
		);

		// Schedule next round.
		wp_schedule_single_event(
			time() + $session->round_duration,
			'wpmatch_speed_dating_round',
			array( $session_id )
		);
	}

	/**
	 * End speed dating session.
	 */
	public static function end_speed_dating_session( $session_id ) {
		global $wpdb;

		$session_table = $wpdb->prefix . 'wpmatch_speed_dating_sessions';
		$wpdb->update(
			$session_table,
			array(
				'status' => 'completed',
				'session_end' => current_time( 'mysql' ),
			),
			array( 'id' => $session_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		// Process matches and send notifications.
		self::process_speed_dating_matches( $session_id );
	}

	/**
	 * Process speed dating matches.
	 */
	private static function process_speed_dating_matches( $session_id ) {
		// Implementation for processing mutual interests into matches.
	}

	/**
	 * Permission callbacks.
	 */
	public static function check_user_permission() {
		return is_user_logged_in();
	}

	public static function check_create_permission() {
		return current_user_can( 'edit_posts' ) || current_user_can( 'wpmatch_create_events' );
	}

	public static function check_edit_permission( $request ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$event_id = $request->get_param( 'event_id' );
		global $wpdb;
		$event = $wpdb->get_row( $wpdb->prepare(
			"SELECT creator_id FROM {$wpdb->prefix}wpmatch_events WHERE id = %d",
			$event_id
		) );

		return $event && ( $event->creator_id == get_current_user_id() || current_user_can( 'manage_options' ) );
	}

	public static function check_organizer_permission( $request ) {
		return self::check_edit_permission( $request );
	}
}