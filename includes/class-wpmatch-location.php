<?php
/**
 * WPMatch Location-Based Features
 *
 * Handles location-based matching, nearby users discovery,
 * and location-aware functionality for the dating platform.
 *
 * @package WPMatch
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPMatch Location class.
 */
class WPMatch_Location {

	/**
	 * Initialize the location system.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'create_database_tables' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );

		// Location update hooks.
		add_action( 'wpmatch_update_user_location', array( __CLASS__, 'update_user_location' ), 10, 3 );
		add_action( 'wpmatch_location_privacy_check', array( __CLASS__, 'check_location_privacy' ) );

		// Scheduled location cleanup.
		add_action( 'wpmatch_cleanup_old_locations', array( __CLASS__, 'cleanup_old_locations' ) );

		// Privacy and safety hooks.
		add_filter( 'wpmatch_location_precision', array( __CLASS__, 'apply_location_precision' ), 10, 2 );
		add_filter( 'wpmatch_nearby_users_distance', array( __CLASS__, 'filter_nearby_distance' ), 10, 2 );
	}

	/**
	 * Create database tables for location features.
	 */
	public static function create_database_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// User locations table.
		$table_name = $wpdb->prefix . 'wpmatch_user_locations';
		$sql        = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			latitude decimal(10,8) NOT NULL,
			longitude decimal(11,8) NOT NULL,
			accuracy float DEFAULT NULL,
			address text,
			city varchar(100),
			state varchar(100),
			country varchar(100),
			postal_code varchar(20),
			timezone varchar(50),
			location_type enum('current','home','work','manual') DEFAULT 'current',
			privacy_level enum('exact','approximate','city_only','hidden') DEFAULT 'approximate',
			is_active tinyint(1) DEFAULT 1,
			last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_location_type (user_id, location_type),
			KEY user_id (user_id),
			KEY location_coords (latitude, longitude),
			KEY privacy_level (privacy_level),
			KEY is_active (is_active),
			KEY last_updated (last_updated)
		) $charset_collate;";

		// Location-based matches table.
		$table_name = $wpdb->prefix . 'wpmatch_location_matches';
		$sql       .= "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user1_id bigint(20) NOT NULL,
			user2_id bigint(20) NOT NULL,
			distance_km decimal(8,3) NOT NULL,
			calculated_at datetime DEFAULT CURRENT_TIMESTAMP,
			match_score decimal(5,2),
			is_nearby_match tinyint(1) DEFAULT 0,
			location_compatibility decimal(3,2),
			PRIMARY KEY (id),
			UNIQUE KEY user_pair (user1_id, user2_id),
			KEY user1_id (user1_id),
			KEY user2_id (user2_id),
			KEY distance_km (distance_km),
			KEY is_nearby_match (is_nearby_match),
			KEY calculated_at (calculated_at)
		) $charset_collate;";

		// Location-based events table.
		$table_name = $wpdb->prefix . 'wpmatch_location_events';
		$sql       .= "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			event_id bigint(20) NOT NULL,
			latitude decimal(10,8),
			longitude decimal(11,8),
			address text,
			venue_name varchar(255),
			city varchar(100),
			state varchar(100),
			country varchar(100),
			radius_km decimal(6,2) DEFAULT 50.00,
			location_required tinyint(1) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY event_id (event_id),
			KEY location_coords (latitude, longitude),
			KEY city (city),
			KEY radius_km (radius_km),
			KEY location_required (location_required)
		) $charset_collate;";

		// Location search history table.
		$table_name = $wpdb->prefix . 'wpmatch_location_searches';
		$sql       .= "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			search_latitude decimal(10,8) NOT NULL,
			search_longitude decimal(11,8) NOT NULL,
			search_radius_km decimal(6,2) NOT NULL,
			results_count int DEFAULT 0,
			search_filters longtext,
			searched_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY search_coords (search_latitude, search_longitude),
			KEY searched_at (searched_at)
		) $charset_collate;";

		// Location privacy settings table.
		$table_name = $wpdb->prefix . 'wpmatch_location_privacy';
		$sql       .= "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			show_exact_location tinyint(1) DEFAULT 0,
			show_distance tinyint(1) DEFAULT 1,
			max_distance_km decimal(6,2) DEFAULT 100.00,
			location_blur_radius_km decimal(4,2) DEFAULT 5.00,
			hide_from_nearby tinyint(1) DEFAULT 0,
			blocked_locations longtext,
			safe_zones longtext,
			auto_hide_home tinyint(1) DEFAULT 1,
			auto_hide_work tinyint(1) DEFAULT 1,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_id (user_id),
			KEY show_distance (show_distance),
			KEY max_distance_km (max_distance_km)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Register REST API routes.
	 */
	public static function register_rest_routes() {
		// Update user location.
		register_rest_route(
			'wpmatch/v1',
			'/location/update',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'api_update_location' ),
				'permission_callback' => array( __CLASS__, 'check_user_permission' ),
				'args'                => array(
					'latitude'      => array( 'required' => true ),
					'longitude'     => array( 'required' => true ),
					'accuracy'      => array( 'required' => false ),
					'location_type' => array( 'default' => 'current' ),
					'privacy_level' => array( 'default' => 'approximate' ),
				),
			)
		);

		// Get nearby users.
		register_rest_route(
			'wpmatch/v1',
			'/location/nearby',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'api_get_nearby_users' ),
				'permission_callback' => array( __CLASS__, 'check_user_permission' ),
				'args'                => array(
					'radius'  => array( 'default' => 50 ),
					'limit'   => array( 'default' => 20 ),
					'min_age' => array( 'required' => false ),
					'max_age' => array( 'required' => false ),
					'gender'  => array( 'required' => false ),
				),
			)
		);

		// Search by location.
		register_rest_route(
			'wpmatch/v1',
			'/location/search',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'api_search_by_location' ),
				'permission_callback' => array( __CLASS__, 'check_user_permission' ),
				'args'                => array(
					'latitude'  => array( 'required' => true ),
					'longitude' => array( 'required' => true ),
					'radius'    => array( 'default' => 25 ),
					'filters'   => array( 'required' => false ),
				),
			)
		);

		// Get location-based events.
		register_rest_route(
			'wpmatch/v1',
			'/location/events',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'api_get_location_events' ),
				'permission_callback' => array( __CLASS__, 'check_user_permission' ),
				'args'                => array(
					'latitude'  => array( 'required' => false ),
					'longitude' => array( 'required' => false ),
					'radius'    => array( 'default' => 50 ),
					'limit'     => array( 'default' => 10 ),
				),
			)
		);

		// Get user's location settings.
		register_rest_route(
			'wpmatch/v1',
			'/location/settings',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'api_get_location_settings' ),
				'permission_callback' => array( __CLASS__, 'check_user_permission' ),
			)
		);

		// Update location privacy settings.
		register_rest_route(
			'wpmatch/v1',
			'/location/privacy',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'api_update_privacy_settings' ),
				'permission_callback' => array( __CLASS__, 'check_user_permission' ),
				'args'                => array(
					'show_exact_location'     => array( 'default' => false ),
					'show_distance'           => array( 'default' => true ),
					'max_distance_km'         => array( 'default' => 100 ),
					'location_blur_radius_km' => array( 'default' => 5 ),
					'hide_from_nearby'        => array( 'default' => false ),
				),
			)
		);

		// Get distance between users.
		register_rest_route(
			'wpmatch/v1',
			'/location/distance/(?P<user_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'api_get_distance_to_user' ),
				'permission_callback' => array( __CLASS__, 'check_user_permission' ),
				'args'                => array(
					'user_id' => array( 'required' => true ),
				),
			)
		);

		// Location-based matching.
		register_rest_route(
			'wpmatch/v1',
			'/location/matches',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'api_get_location_matches' ),
				'permission_callback' => array( __CLASS__, 'check_user_permission' ),
				'args'                => array(
					'max_distance' => array( 'default' => 50 ),
					'limit'        => array( 'default' => 10 ),
				),
			)
		);
	}

	/**
	 * Enqueue frontend scripts and styles.
	 */
	public static function enqueue_scripts() {
		if ( is_page() || is_singular() ) {
			wp_enqueue_script(
				'wpmatch-location',
				WPMATCH_PLUGIN_URL . 'public/js/wpmatch-location.js',
				array( 'jquery' ),
				WPMATCH_VERSION,
				true
			);

			wp_enqueue_style(
				'wpmatch-location',
				WPMATCH_PLUGIN_URL . 'public/css/wpmatch-location.css',
				array(),
				WPMATCH_VERSION
			);

			wp_localize_script(
				'wpmatch-location',
				'wpMatchLocation',
				array(
					'apiUrl'          => rest_url( 'wpmatch/v1' ),
					'nonce'           => wp_create_nonce( 'wp_rest' ),
					'mapApiKey'       => get_option( 'wpmatch_google_maps_api_key', '' ),
					'defaultRadius'   => apply_filters( 'wpmatch_default_search_radius', 50 ),
					'maxRadius'       => apply_filters( 'wpmatch_max_search_radius', 500 ),
					'locationTimeout' => apply_filters( 'wpmatch_location_timeout', 15000 ),
					'strings'         => array(
						'locationPermissionDenied' => __( 'Location permission denied.', 'wpmatch' ),
						'locationNotSupported'     => __( 'Geolocation not supported by this browser.', 'wpmatch' ),
						'locationTimeout'          => __( 'Location request timed out.', 'wpmatch' ),
						'locationError'            => __( 'Error getting your location.', 'wpmatch' ),
						'locationUpdated'          => __( 'Location updated successfully!', 'wpmatch' ),
						'nearbyUsersLoaded'        => __( 'Found nearby users!', 'wpmatch' ),
						'noNearbyUsers'            => __( 'No users found in your area.', 'wpmatch' ),
						'distanceKm'               => __( '%s km away', 'wpmatch' ),
						'distanceMiles'            => __( '%s miles away', 'wpmatch' ),
						'currentLocation'          => __( 'Current Location', 'wpmatch' ),
						'homeLocation'             => __( 'Home', 'wpmatch' ),
						'workLocation'             => __( 'Work', 'wpmatch' ),
						'manualLocation'           => __( 'Custom Location', 'wpmatch' ),
						'privacyExact'             => __( 'Show exact location', 'wpmatch' ),
						'privacyApproximate'       => __( 'Show approximate location', 'wpmatch' ),
						'privacyCityOnly'          => __( 'Show city only', 'wpmatch' ),
						'privacyHidden'            => __( 'Hide location', 'wpmatch' ),
						'searchingNearby'          => __( 'Searching for nearby users...', 'wpmatch' ),
						'loadingEvents'            => __( 'Loading nearby events...', 'wpmatch' ),
					),
				)
			);
		}
	}

	/**
	 * Update user location API endpoint.
	 */
	public static function api_update_location( $request ) {
		$user_id       = get_current_user_id();
		$latitude      = floatval( $request->get_param( 'latitude' ) );
		$longitude     = floatval( $request->get_param( 'longitude' ) );
		$accuracy      = $request->get_param( 'accuracy' ) ? floatval( $request->get_param( 'accuracy' ) ) : null;
		$location_type = sanitize_text_field( $request->get_param( 'location_type' ) );
		$privacy_level = sanitize_text_field( $request->get_param( 'privacy_level' ) );

		// Validate coordinates.
		if ( $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180 ) {
			return new WP_Error( 'invalid_coordinates', __( 'Invalid coordinates provided.', 'wpmatch' ), array( 'status' => 400 ) );
		}

		// Get location details from coordinates (reverse geocoding).
		$location_details = self::reverse_geocode( $latitude, $longitude );

		// Save location.
		global $wpdb;
		$table_name = $wpdb->prefix . 'wpmatch_user_locations';

		$result = $wpdb->replace(
			$table_name,
			array(
				'user_id'       => $user_id,
				'latitude'      => $latitude,
				'longitude'     => $longitude,
				'accuracy'      => $accuracy,
				'address'       => $location_details['address'],
				'city'          => $location_details['city'],
				'state'         => $location_details['state'],
				'country'       => $location_details['country'],
				'postal_code'   => $location_details['postal_code'],
				'timezone'      => $location_details['timezone'],
				'location_type' => $location_type,
				'privacy_level' => $privacy_level,
				'is_active'     => 1,
			),
			array( '%d', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'location_save_failed', __( 'Failed to save location.', 'wpmatch' ), array( 'status' => 500 ) );
		}

		// Update location-based matches.
		self::update_location_matches( $user_id );

		// Trigger gamification.
		if ( class_exists( 'WPMatch_Gamification' ) ) {
			WPMatch_Gamification::trigger_achievement( 'location_shared', array( 'user_id' => $user_id ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'message'          => __( 'Location updated successfully!', 'wpmatch' ),
					'location_details' => $location_details,
				),
			)
		);
	}

	/**
	 * Get nearby users API endpoint.
	 */
	public static function api_get_nearby_users( $request ) {
		$user_id = get_current_user_id();
		$radius  = floatval( $request->get_param( 'radius' ) );
		$limit   = absint( $request->get_param( 'limit' ) );
		$min_age = $request->get_param( 'min_age' ) ? absint( $request->get_param( 'min_age' ) ) : null;
		$max_age = $request->get_param( 'max_age' ) ? absint( $request->get_param( 'max_age' ) ) : null;
		$gender  = $request->get_param( 'gender' ) ? sanitize_text_field( $request->get_param( 'gender' ) ) : null;

		// Get user's current location.
		$user_location = self::get_user_location( $user_id );
		if ( ! $user_location ) {
			return new WP_Error( 'no_location', __( 'Please update your location first.', 'wpmatch' ), array( 'status' => 400 ) );
		}

		// Check user's privacy settings.
		$privacy_settings = self::get_user_privacy_settings( $user_id );
		if ( $privacy_settings['hide_from_nearby'] ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'data'    => array(),
					'message' => __( 'Nearby search is disabled in your privacy settings.', 'wpmatch' ),
				)
			);
		}

		// Find nearby users.
		$nearby_users = self::find_nearby_users(
			$user_location,
			$radius,
			$limit,
			array(
				'min_age'         => $min_age,
				'max_age'         => $max_age,
				'gender'          => $gender,
				'exclude_user_id' => $user_id,
			)
		);

		// Log search for analytics.
		self::log_location_search( $user_id, $user_location['latitude'], $user_location['longitude'], $radius, count( $nearby_users ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $nearby_users,
				'meta'    => array(
					'search_radius' => $radius,
					'user_location' => array(
						'city'  => $user_location['city'],
						'state' => $user_location['state'],
					),
				),
			)
		);
	}

	/**
	 * Search by location API endpoint.
	 */
	public static function api_search_by_location( $request ) {
		$user_id   = get_current_user_id();
		$latitude  = floatval( $request->get_param( 'latitude' ) );
		$longitude = floatval( $request->get_param( 'longitude' ) );
		$radius    = floatval( $request->get_param( 'radius' ) );
		$filters   = $request->get_param( 'filters' ) ? $request->get_param( 'filters' ) : array();

		// Validate coordinates.
		if ( $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180 ) {
			return new WP_Error( 'invalid_coordinates', __( 'Invalid coordinates provided.', 'wpmatch' ), array( 'status' => 400 ) );
		}

		$search_location = array(
			'latitude'  => $latitude,
			'longitude' => $longitude,
		);

		// Find users near the search location.
		$nearby_users = self::find_nearby_users(
			$search_location,
			$radius,
			50,
			array_merge(
				$filters,
				array(
					'exclude_user_id' => $user_id,
				)
			)
		);

		// Log search.
		self::log_location_search( $user_id, $latitude, $longitude, $radius, count( $nearby_users ), $filters );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $nearby_users,
				'meta'    => array(
					'search_location' => $search_location,
					'search_radius'   => $radius,
					'filters_applied' => $filters,
				),
			)
		);
	}

	/**
	 * Get location-based events API endpoint.
	 */
	public static function api_get_location_events( $request ) {
		$user_id   = get_current_user_id();
		$latitude  = $request->get_param( 'latitude' );
		$longitude = $request->get_param( 'longitude' );
		$radius    = floatval( $request->get_param( 'radius' ) );
		$limit     = absint( $request->get_param( 'limit' ) );

		// If no coordinates provided, use user's location.
		if ( ! $latitude || ! $longitude ) {
			$user_location = self::get_user_location( $user_id );
			if ( ! $user_location ) {
				return new WP_Error( 'no_location', __( 'Please provide coordinates or update your location.', 'wpmatch' ), array( 'status' => 400 ) );
			}
			$latitude  = $user_location['latitude'];
			$longitude = $user_location['longitude'];
		}

		// Find nearby events.
		$nearby_events = self::find_nearby_events( $latitude, $longitude, $radius, $limit );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $nearby_events,
				'meta'    => array(
					'search_location' => array(
						'latitude'  => floatval( $latitude ),
						'longitude' => floatval( $longitude ),
					),
					'search_radius'   => $radius,
				),
			)
		);
	}

	/**
	 * Get location settings API endpoint.
	 */
	public static function api_get_location_settings( $request ) {
		$user_id = get_current_user_id();

		$user_location    = self::get_user_location( $user_id );
		$privacy_settings = self::get_user_privacy_settings( $user_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'location' => $user_location,
					'privacy'  => $privacy_settings,
				),
			)
		);
	}

	/**
	 * Update privacy settings API endpoint.
	 */
	public static function api_update_privacy_settings( $request ) {
		$user_id                 = get_current_user_id();
		$show_exact_location     = $request->get_param( 'show_exact_location' ) ? 1 : 0;
		$show_distance           = $request->get_param( 'show_distance' ) ? 1 : 0;
		$max_distance_km         = floatval( $request->get_param( 'max_distance_km' ) );
		$location_blur_radius_km = floatval( $request->get_param( 'location_blur_radius_km' ) );
		$hide_from_nearby        = $request->get_param( 'hide_from_nearby' ) ? 1 : 0;

		global $wpdb;
		$table_name = $wpdb->prefix . 'wpmatch_location_privacy';

		$result = $wpdb->replace(
			$table_name,
			array(
				'user_id'                 => $user_id,
				'show_exact_location'     => $show_exact_location,
				'show_distance'           => $show_distance,
				'max_distance_km'         => $max_distance_km,
				'location_blur_radius_km' => $location_blur_radius_km,
				'hide_from_nearby'        => $hide_from_nearby,
			),
			array( '%d', '%d', '%d', '%f', '%f', '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'privacy_save_failed', __( 'Failed to save privacy settings.', 'wpmatch' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'message' => __( 'Privacy settings updated successfully!', 'wpmatch' ),
				),
			)
		);
	}

	/**
	 * Get distance to user API endpoint.
	 */
	public static function api_get_distance_to_user( $request ) {
		$current_user_id = get_current_user_id();
		$target_user_id  = absint( $request->get_param( 'user_id' ) );

		if ( $current_user_id === $target_user_id ) {
			return new WP_Error( 'same_user', __( 'Cannot calculate distance to yourself.', 'wpmatch' ), array( 'status' => 400 ) );
		}

		$distance_data = self::calculate_user_distance( $current_user_id, $target_user_id );

		if ( ! $distance_data ) {
			return new WP_Error( 'distance_calculation_failed', __( 'Unable to calculate distance.', 'wpmatch' ), array( 'status' => 400 ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $distance_data,
			)
		);
	}

	/**
	 * Get location matches API endpoint.
	 */
	public static function api_get_location_matches( $request ) {
		$user_id       = get_current_user_id();
		$max_distance  = floatval( $request->get_param( 'max_distance' ) );
		$limit         = absint( $request->get_param( 'limit' ) );

		// Get user's location.
		$user_location = self::get_user_location( $user_id );
		if ( ! $user_location ) {
			return new WP_Error( 'no_location', __( 'Please update your location first.', 'wpmatch' ), array( 'status' => 400 ) );
		}

		// Get user's preferences.
		$preferences = self::get_user_matching_preferences( $user_id );

		// Find location-based matches.
		$location_matches = self::find_location_matches( $user_id, $user_location, $max_distance, $limit, $preferences );

		// Calculate and update match scores.
		foreach ( $location_matches as &$match ) {
			$match['match_score'] = self::calculate_location_match_score( $user_id, $match['user_id'], $match['distance_km'] );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $location_matches,
				'meta'    => array(
					'user_location'  => array(
						'city'  => $user_location['city'],
						'state' => $user_location['state'],
					),
					'search_radius'  => $max_distance,
					'total_matches'  => count( $location_matches ),
				),
			)
		);
	}

	/**
	 * Get user's current location.
	 */
	public static function get_user_location( $user_id, $location_type = 'current' ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_user_locations';
		$location   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE user_id = %d AND location_type = %s AND is_active = 1 ORDER BY last_updated DESC LIMIT 1",
				$user_id,
				$location_type
			),
			ARRAY_A
		);

		return $location;
	}

	/**
	 * Get user's privacy settings.
	 */
	public static function get_user_privacy_settings( $user_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_location_privacy';
		$settings   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE user_id = %d",
				$user_id
			),
			ARRAY_A
		);

		// Return default settings if none exist.
		if ( ! $settings ) {
			return array(
				'show_exact_location'     => 0,
				'show_distance'           => 1,
				'max_distance_km'         => 100.00,
				'location_blur_radius_km' => 5.00,
				'hide_from_nearby'        => 0,
			);
		}

		return $settings;
	}

	/**
	 * Find nearby users.
	 */
	public static function find_nearby_users( $location, $radius_km, $limit = 20, $filters = array() ) {
		global $wpdb;

		$latitude  = $location['latitude'];
		$longitude = $location['longitude'];

		// Build distance calculation using Haversine formula.
		$distance_formula = "
			( 6371 * acos( cos( radians($latitude) )
			* cos( radians( ul.latitude ) )
			* cos( radians( ul.longitude ) - radians($longitude) )
			+ sin( radians($latitude) )
			* sin( radians( ul.latitude ) ) ) )
		";

		$where_conditions = array(
			'ul.is_active = 1',
			"ul.privacy_level != 'hidden'",
			"$distance_formula <= %f",
		);
		$where_values     = array( $radius_km );

		// Add user exclusion.
		if ( isset( $filters['exclude_user_id'] ) ) {
			$where_conditions[] = 'ul.user_id != %d';
			$where_values[]     = $filters['exclude_user_id'];
		}

		// Add age filters.
		if ( isset( $filters['min_age'] ) ) {
			$where_conditions[] = 'TIMESTAMPDIFF(YEAR, um_age.meta_value, CURDATE()) >= %d';
			$where_values[]     = $filters['min_age'];
		}

		if ( isset( $filters['max_age'] ) ) {
			$where_conditions[] = 'TIMESTAMPDIFF(YEAR, um_age.meta_value, CURDATE()) <= %d';
			$where_values[]     = $filters['max_age'];
		}

		// Add gender filter.
		if ( isset( $filters['gender'] ) ) {
			$where_conditions[] = 'um_gender.meta_value = %s';
			$where_values[]     = $filters['gender'];
		}

		$where_clause = implode( ' AND ', $where_conditions );

		$query = $wpdb->prepare(
			"SELECT
				u.ID as user_id,
				u.display_name,
				u.user_email,
				ul.latitude,
				ul.longitude,
				ul.city,
				ul.state,
				ul.privacy_level,
				$distance_formula as distance_km,
				um_age.meta_value as birth_date,
				um_gender.meta_value as gender
			FROM {$wpdb->prefix}wpmatch_user_locations ul
			INNER JOIN {$wpdb->users} u ON ul.user_id = u.ID
			LEFT JOIN {$wpdb->usermeta} um_age ON u.ID = um_age.user_id AND um_age.meta_key = 'birth_date'
			LEFT JOIN {$wpdb->usermeta} um_gender ON u.ID = um_gender.user_id AND um_gender.meta_key = 'gender'
			WHERE $where_clause
			HAVING distance_km <= %f
			ORDER BY distance_km ASC
			LIMIT %d",
			array_merge( $where_values, array( $radius_km, $limit ) )
		);

		$results = $wpdb->get_results( $query, ARRAY_A );

		// Process results and apply privacy filtering.
		foreach ( $results as &$user ) {
			$user                = self::apply_location_privacy( $user );
			$user['distance_km'] = round( $user['distance_km'], 1 );
			$user['age']         = $user['birth_date'] ? self::calculate_age( $user['birth_date'] ) : null;
			unset( $user['birth_date'] );
		}

		return $results;
	}

	/**
	 * Find nearby events.
	 */
	public static function find_nearby_events( $latitude, $longitude, $radius_km, $limit = 10 ) {
		global $wpdb;

		// Build distance calculation.
		$distance_formula = "
			( 6371 * acos( cos( radians($latitude) )
			* cos( radians( le.latitude ) )
			* cos( radians( le.longitude ) - radians($longitude) )
			+ sin( radians($latitude) )
			* sin( radians( le.latitude ) ) ) )
		";

		$query = $wpdb->prepare(
			"SELECT
				e.*,
				le.latitude,
				le.longitude,
				le.address,
				le.venue_name,
				le.city,
				le.state,
				$distance_formula as distance_km
			FROM {$wpdb->prefix}wpmatch_events e
			INNER JOIN {$wpdb->prefix}wpmatch_location_events le ON e.id = le.event_id
			WHERE e.status = 'published'
			AND e.event_start > NOW()
			AND $distance_formula <= %f
			ORDER BY distance_km ASC, e.event_start ASC
			LIMIT %d",
			$radius_km,
			$limit
		);

		$events = $wpdb->get_results( $query, ARRAY_A );

		foreach ( $events as &$event ) {
			$event['distance_km'] = round( $event['distance_km'], 1 );
		}

		return $events;
	}

	/**
	 * Calculate distance between two users.
	 */
	public static function calculate_user_distance( $user1_id, $user2_id ) {
		$user1_location = self::get_user_location( $user1_id );
		$user2_location = self::get_user_location( $user2_id );

		if ( ! $user1_location || ! $user2_location ) {
			return false;
		}

		$distance_km = self::calculate_distance(
			$user1_location['latitude'],
			$user1_location['longitude'],
			$user2_location['latitude'],
			$user2_location['longitude']
		);

		return array(
			'distance_km'    => round( $distance_km, 1 ),
			'distance_miles' => round( $distance_km * 0.621371, 1 ),
			'user1_city'     => $user1_location['city'],
			'user2_city'     => $user2_location['city'],
		);
	}

	/**
	 * Calculate distance between two points using Haversine formula.
	 */
	public static function calculate_distance( $lat1, $lon1, $lat2, $lon2 ) {
		$earth_radius = 6371; // kilometers.

		$d_lat = deg2rad( $lat2 - $lat1 );
		$d_lon = deg2rad( $lon2 - $lon1 );

		$a = sin( $d_lat / 2 ) * sin( $d_lat / 2 ) + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( $d_lon / 2 ) * sin( $d_lon / 2 );
		$c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

		return $earth_radius * $c;
	}

	/**
	 * Apply location privacy filtering.
	 */
	public static function apply_location_privacy( $user_data ) {
		$privacy_level = $user_data['privacy_level'];

		switch ( $privacy_level ) {
			case 'exact':
				// Return exact coordinates.
				break;

			case 'approximate':
				// Blur coordinates by ~1-5km.
				$user_data['latitude']  = self::blur_coordinate( $user_data['latitude'], 0.01 );
				$user_data['longitude'] = self::blur_coordinate( $user_data['longitude'], 0.01 );
				break;

			case 'city_only':
				// Remove exact coordinates, show only city.
				unset( $user_data['latitude'] );
				unset( $user_data['longitude'] );
				break;

			case 'hidden':
				// Should not appear in results, but just in case.
				unset( $user_data['latitude'] );
				unset( $user_data['longitude'] );
				unset( $user_data['city'] );
				unset( $user_data['state'] );
				break;
		}

		return $user_data;
	}

	/**
	 * Blur a coordinate by adding random offset.
	 */
	private static function blur_coordinate( $coordinate, $max_offset ) {
		$offset = ( rand( -1000, 1000 ) / 1000 ) * $max_offset;
		return $coordinate + $offset;
	}

	/**
	 * Calculate age from birth date.
	 */
	private static function calculate_age( $birth_date ) {
		$birth = new DateTime( $birth_date );
		$today = new DateTime( 'today' );
		return $birth->diff( $today )->y;
	}

	/**
	 * Reverse geocode coordinates to address.
	 */
	private static function reverse_geocode( $latitude, $longitude ) {
		// Placeholder for geocoding service integration.
		// This would integrate with Google Maps API, MapBox, or similar service.
		return array(
			'address'     => 'Address not available',
			'city'        => 'Unknown City',
			'state'       => 'Unknown State',
			'country'     => 'Unknown Country',
			'postal_code' => '',
			'timezone'    => 'UTC',
		);
	}

	/**
	 * Update location-based matches for a user.
	 */
	private static function update_location_matches( $user_id ) {
		global $wpdb;

		// Get user's location.
		$user_location = self::get_user_location( $user_id );
		if ( ! $user_location ) {
			return;
		}

		// Get user's preferences.
		$preferences = self::get_user_matching_preferences( $user_id );
		$max_distance = $preferences['max_distance_km'] ?? 100;

		// Find potential matches within distance.
		$potential_matches = self::find_nearby_users(
			$user_location,
			$max_distance,
			100,
			array(
				'exclude_user_id' => $user_id,
				'min_age'         => $preferences['min_age'] ?? null,
				'max_age'         => $preferences['max_age'] ?? null,
				'gender'          => $preferences['preferred_gender'] ?? null,
			)
		);

		// Update location matches table.
		$table_name = $wpdb->prefix . 'wpmatch_location_matches';

		// Clear existing matches for this user.
		$wpdb->delete( $table_name, array( 'user1_id' => $user_id ), array( '%d' ) );

		// Insert new matches.
		foreach ( $potential_matches as $match ) {
			$match_score = self::calculate_location_match_score( $user_id, $match['user_id'], $match['distance_km'] );
			$location_compatibility = self::calculate_location_compatibility( $user_location, $match );

			$wpdb->insert(
				$table_name,
				array(
					'user1_id'               => $user_id,
					'user2_id'               => $match['user_id'],
					'distance_km'            => $match['distance_km'],
					'match_score'            => $match_score,
					'is_nearby_match'        => $match['distance_km'] <= 25 ? 1 : 0,
					'location_compatibility' => $location_compatibility,
				),
				array( '%d', '%d', '%f', '%f', '%d', '%f' )
			);
		}
	}

	/**
	 * Find location-based matches for a user.
	 */
	private static function find_location_matches( $user_id, $user_location, $max_distance, $limit, $preferences ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_location_matches';

		// First try to get from cached matches.
		$matches = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT lm.*, u.display_name, u.user_email
				FROM $table_name lm
				INNER JOIN {$wpdb->users} u ON lm.user2_id = u.ID
				WHERE lm.user1_id = %d
				AND lm.distance_km <= %f
				ORDER BY lm.match_score DESC, lm.distance_km ASC
				LIMIT %d",
				$user_id,
				$max_distance,
				$limit
			),
			ARRAY_A
		);

		// If no cached matches or not enough, find fresh ones.
		if ( count( $matches ) < $limit ) {
			$fresh_matches = self::find_nearby_users(
				$user_location,
				$max_distance,
				$limit,
				array_merge(
					$preferences,
					array( 'exclude_user_id' => $user_id )
				)
			);

			// Merge with cached matches.
			$user_ids_cached = array_column( $matches, 'user2_id' );
			foreach ( $fresh_matches as $fresh_match ) {
				if ( ! in_array( $fresh_match['user_id'], $user_ids_cached, true ) ) {
					$matches[] = array_merge( $fresh_match, array( 'user2_id' => $fresh_match['user_id'] ) );
				}
			}

			$matches = array_slice( $matches, 0, $limit );
		}

		return $matches;
	}

	/**
	 * Calculate location-based match score.
	 */
	private static function calculate_location_match_score( $user1_id, $user2_id, $distance_km ) {
		// Base score starts at 100 and decreases with distance.
		$base_score = 100;

		// Distance penalty (closer = higher score).
		$distance_penalty = min( $distance_km * 0.5, 50 ); // Max penalty of 50 points.
		$distance_score = max( 0, $base_score - $distance_penalty );

		// Activity bonus (if both users are in the same area frequently).
		$activity_bonus = self::calculate_activity_bonus( $user1_id, $user2_id );

		// Common places bonus.
		$common_places_bonus = self::calculate_common_places_bonus( $user1_id, $user2_id );

		$total_score = $distance_score + $activity_bonus + $common_places_bonus;

		return min( 100, max( 0, $total_score ) );
	}

	/**
	 * Calculate location compatibility between two users.
	 */
	private static function calculate_location_compatibility( $user1_location, $user2_data ) {
		$compatibility = 1.0; // Perfect compatibility by default.

		// Same city bonus.
		if ( $user1_location['city'] === $user2_data['city'] ) {
			$compatibility += 0.2;
		}

		// Same state bonus.
		if ( $user1_location['state'] === $user2_data['state'] ) {
			$compatibility += 0.1;
		}

		// Distance penalty.
		$distance_km = $user2_data['distance_km'];
		if ( $distance_km > 50 ) {
			$compatibility -= ( $distance_km - 50 ) * 0.01;
		}

		return min( 1.0, max( 0.0, $compatibility ) );
	}

	/**
	 * Get user's matching preferences.
	 */
	private static function get_user_matching_preferences( $user_id ) {
		global $wpdb;

		// Get from user preferences table.
		$preferences = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpmatch_user_preferences WHERE user_id = %d",
				$user_id
			),
			ARRAY_A
		);

		if ( ! $preferences ) {
			// Default preferences.
			return array(
				'min_age'         => 18,
				'max_age'         => 99,
				'max_distance_km' => 50,
				'preferred_gender' => null,
			);
		}

		return array(
			'min_age'          => $preferences['min_age'],
			'max_age'          => $preferences['max_age'],
			'max_distance_km'  => $preferences['max_distance'],
			'preferred_gender' => $preferences['preferred_gender'],
		);
	}

	/**
	 * Calculate activity bonus based on location patterns.
	 */
	private static function calculate_activity_bonus( $user1_id, $user2_id ) {
		// Placeholder for activity pattern analysis.
		// This could analyze common locations visited, timing patterns, etc.
		return 0;
	}

	/**
	 * Calculate bonus for common places/areas.
	 */
	private static function calculate_common_places_bonus( $user1_id, $user2_id ) {
		global $wpdb;

		// Check for common cities in location history.
		$common_cities = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT ul1.city)
				FROM {$wpdb->prefix}wpmatch_user_locations ul1
				INNER JOIN {$wpdb->prefix}wpmatch_user_locations ul2 ON ul1.city = ul2.city
				WHERE ul1.user_id = %d AND ul2.user_id = %d
				AND ul1.city IS NOT NULL AND ul1.city != ''",
				$user1_id,
				$user2_id
			)
		);

		return $common_cities * 5; // 5 points per common city.
	}

	/**
	 * Log location search for analytics.
	 */
	private static function log_location_search( $user_id, $latitude, $longitude, $radius, $results_count, $filters = array() ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_location_searches';
		$wpdb->insert(
			$table_name,
			array(
				'user_id'          => $user_id,
				'search_latitude'  => $latitude,
				'search_longitude' => $longitude,
				'search_radius_km' => $radius,
				'results_count'    => $results_count,
				'search_filters'   => wp_json_encode( $filters ),
			),
			array( '%d', '%f', '%f', '%f', '%d', '%s' )
		);
	}

	/**
	 * Clean up old location data.
	 */
	public static function cleanup_old_locations() {
		global $wpdb;

		// Remove location data older than 30 days.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}wpmatch_user_locations WHERE last_updated < %s",
				date( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
			)
		);

		// Remove old search logs.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}wpmatch_location_searches WHERE searched_at < %s",
				date( 'Y-m-d H:i:s', strtotime( '-90 days' ) )
			)
		);
	}

	/**
	 * Permission callback.
	 */
	public static function check_user_permission() {
		return is_user_logged_in();
	}
}
