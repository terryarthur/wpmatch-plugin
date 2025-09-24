<?php
/**
 * User Profile Management Class
 *
 * Handles creation, updating, and retrieval of user dating profiles.
 *
 * @package WPMatch
 */

/**
 * WPMatch Profile Manager Class.
 *
 * Manages user dating profiles with complete CRUD operations,
 * profile completion tracking, and WordPress security compliance.
 */
class WPMatch_Profile_Manager {

	/**
	 * The table name for user profiles.
	 *
	 * @var string
	 */
	private $table_profiles;

	/**
	 * The table name for user media.
	 *
	 * @var string
	 */
	private $table_media;

	/**
	 * The table name for user interests.
	 *
	 * @var string
	 */
	private $table_interests;

	/**
	 * The table name for user preferences.
	 *
	 * @var string
	 */
	private $table_preferences;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table_profiles    = $wpdb->prefix . 'wpmatch_user_profiles';
		$this->table_media       = $wpdb->prefix . 'wpmatch_user_media';
		$this->table_interests   = $wpdb->prefix . 'wpmatch_user_interests';
		$this->table_preferences = $wpdb->prefix . 'wpmatch_user_preferences';
	}

	/**
	 * Create or update a user profile.
	 *
	 * @param int   $user_id The WordPress user ID.
	 * @param array $profile_data The profile data to save.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function save_profile( $user_id, $profile_data ) {
		// Validate user exists.
		if ( ! get_userdata( $user_id ) ) {
			return new WP_Error( 'user_not_found', __( 'User not found.', 'wpmatch' ) );
		}

		// Sanitize and validate profile data.
		$sanitized_data = $this->sanitize_profile_data( $profile_data );

		if ( is_wp_error( $sanitized_data ) ) {
			return $sanitized_data;
		}

		global $wpdb;

		// Check if profile exists.
		$existing_profile = $this->get_profile( $user_id );

		// Prepare data for database.
		$db_data = array(
			'user_id'        => $user_id,
			'age'            => $sanitized_data['age'],
			'location'       => $sanitized_data['location'],
			'latitude'       => $sanitized_data['latitude'],
			'longitude'      => $sanitized_data['longitude'],
			'gender'         => $sanitized_data['gender'],
			'orientation'    => $sanitized_data['orientation'],
			'education'      => $sanitized_data['education'],
			'profession'     => $sanitized_data['profession'],
			'income_range'   => $sanitized_data['income_range'],
			'height'         => $sanitized_data['height'],
			'body_type'      => $sanitized_data['body_type'],
			'ethnicity'      => $sanitized_data['ethnicity'],
			'smoking'        => $sanitized_data['smoking'],
			'drinking'       => $sanitized_data['drinking'],
			'children'       => $sanitized_data['children'],
			'wants_children' => $sanitized_data['wants_children'],
			'pets'           => $sanitized_data['pets'],
			'about_me'       => $sanitized_data['about_me'],
			'looking_for'    => $sanitized_data['looking_for'],
			'last_active'    => current_time( 'mysql' ),
		);

		// Calculate profile completion percentage.
		$db_data['profile_completion'] = $this->calculate_completion_percentage( $db_data );

		$data_types = array(
			'%d', // user_id.
			'%d', // age.
			'%s', // location.
			'%f', // latitude.
			'%f', // longitude.
			'%s', // gender.
			'%s', // orientation.
			'%s', // education.
			'%s', // profession.
			'%s', // income_range.
			'%d', // height.
			'%s', // body_type.
			'%s', // ethnicity.
			'%s', // smoking.
			'%s', // drinking.
			'%s', // children.
			'%s', // wants_children.
			'%s', // pets.
			'%s', // about_me.
			'%s', // looking_for.
			'%s', // last_active.
			'%d', // profile_completion.
		);

		if ( $existing_profile ) {
			// Update existing profile.
			$result = $wpdb->update(
				$this->table_profiles,
				$db_data,
				array( 'user_id' => $user_id ),
				$data_types,
				array( '%d' )
			);
		} else {
			// Insert new profile.
			$result = $wpdb->insert(
				$this->table_profiles,
				$db_data,
				$data_types
			);
		}

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to save profile.', 'wpmatch' ) );
		}

		// Update user preferences if provided.
		if ( isset( $profile_data['preferences'] ) && is_array( $profile_data['preferences'] ) ) {
			$this->save_user_preferences( $user_id, $profile_data['preferences'] );
		}

		// Update user interests if provided.
		if ( isset( $profile_data['interests'] ) && is_array( $profile_data['interests'] ) ) {
			$this->save_user_interests( $user_id, $profile_data['interests'] );
		}

		return true;
	}

	/**
	 * Get a user's profile data.
	 *
	 * @param int $user_id The WordPress user ID.
	 * @return object|null The profile object or null if not found.
	 */
	public function get_profile( $user_id ) {
		global $wpdb;

		$profile = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->prefix}wpmatch_user_profiles` WHERE user_id = %d",
				$user_id
			)
		);

		if ( $profile ) {
			// Add additional data.
			$profile->preferences = $this->get_user_preferences( $user_id );
			$profile->interests   = $this->get_user_interests( $user_id );
			$profile->media       = $this->get_user_media( $user_id );
		}

		return $profile;
	}

	/**
	 * Get multiple profiles for browsing/matching.
	 *
	 * @param array $args Query arguments.
	 * @return array Array of profile objects.
	 */
	public function get_profiles( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'limit'          => 20,
			'offset'         => 0,
			'exclude_user'   => 0,
			'min_age'        => 18,
			'max_age'        => 99,
			'gender'         => '',
			'location_lat'   => null,
			'location_lng'   => null,
			'max_distance'   => 50,
			'has_photo'      => true,
			'min_completion' => 50,
			'order_by'       => 'last_active',
			'order'          => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		// Build query.
		$where_clauses = array();
		$query_params  = array();

		// Exclude specific user.
		if ( $args['exclude_user'] ) {
			$where_clauses[] = 'p.user_id != %d';
			$query_params[]  = $args['exclude_user'];
		}

		// Age range.
		if ( $args['min_age'] ) {
			$where_clauses[] = 'p.age >= %d';
			$query_params[]  = $args['min_age'];
		}
		if ( $args['max_age'] ) {
			$where_clauses[] = 'p.age <= %d';
			$query_params[]  = $args['max_age'];
		}

		// Gender filter.
		if ( $args['gender'] ) {
			$where_clauses[] = 'p.gender = %s';
			$query_params[]  = $args['gender'];
		}

		// Profile completion minimum.
		if ( $args['min_completion'] ) {
			$where_clauses[] = 'p.profile_completion >= %d';
			$query_params[]  = $args['min_completion'];
		}

		// Has photo requirement.
		if ( $args['has_photo'] ) {
			$where_clauses[] = 'EXISTS (SELECT 1 FROM `' . $wpdb->prefix . 'wpmatch_user_media` m WHERE m.user_id = p.user_id AND m.media_type = "photo")';
		}

		// Distance filter (if location provided).
		if ( null !== $args['location_lat'] && null !== $args['location_lng'] && $args['max_distance'] ) {
			$where_clauses[] = '(
				6371 * acos(
					cos(radians(%f)) * cos(radians(p.latitude)) *
					cos(radians(p.longitude) - radians(%f)) +
					sin(radians(%f)) * sin(radians(p.latitude))
				)
			) <= %d';
			$query_params[]  = $args['location_lat'];
			$query_params[]  = $args['location_lng'];
			$query_params[]  = $args['location_lat'];
			$query_params[]  = $args['max_distance'];
		}

		$where_sql = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';

		// Order by clause.
		$order_by = sanitize_sql_orderby( $args['order_by'] . ' ' . $args['order'] );
		if ( ! $order_by ) {
			$order_by = 'p.last_active DESC';
		}

		// Prepare the base query without dynamic parts.
		$base_sql = "SELECT p.*, u.display_name, u.user_email
			FROM `{$wpdb->prefix}wpmatch_user_profiles` p
			INNER JOIN `{$wpdb->users}` u ON p.user_id = u.ID";

		// Add WHERE clause if we have conditions.
		if ( ! empty( $where_clauses ) ) {
			$base_sql .= ' WHERE ' . implode( ' AND ', $where_clauses );
		}

		// Add ORDER BY.
		$base_sql .= " ORDER BY {$order_by}";

		// Add LIMIT and OFFSET.
		$query_params[] = absint( $args['limit'] );
		$query_params[] = absint( $args['offset'] );
		$base_sql      .= ' LIMIT %d OFFSET %d';

		// Prepare and execute the query.
		$prepared_sql = $wpdb->prepare( $base_sql, $query_params );
		$profiles     = $wpdb->get_results( $prepared_sql );

		// Add additional data for each profile.
		foreach ( $profiles as $profile ) {
			$profile->media     = $this->get_user_media( $profile->user_id, 1 ); // Get primary photo.
			$profile->interests = $this->get_user_interests( $profile->user_id );
		}

		return $profiles;
	}

	/**
	 * Delete a user profile and all associated data.
	 *
	 * @param int $user_id The WordPress user ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_profile( $user_id ) {
		global $wpdb;

		// Delete in order due to foreign key constraints.
		$tables_to_clean = array(
			$this->table_interests,
			$this->table_media,
			$this->table_preferences,
			$this->table_profiles,
		);

		foreach ( $tables_to_clean as $table ) {
			$wpdb->delete( $table, array( 'user_id' => $user_id ), array( '%d' ) );
		}

		return true;
	}

	/**
	 * Save user preferences.
	 *
	 * @param int   $user_id The user ID.
	 * @param array $preferences The preferences data.
	 * @return bool True on success, false on failure.
	 */
	public function save_user_preferences( $user_id, $preferences ) {
		global $wpdb;

		$sanitized = $this->sanitize_preferences_data( $preferences );

		$data = array(
			'user_id'             => $user_id,
			'min_age'             => $sanitized['min_age'],
			'max_age'             => $sanitized['max_age'],
			'max_distance'        => $sanitized['max_distance'],
			'preferred_gender'    => $sanitized['preferred_gender'],
			'preferred_ethnicity' => $sanitized['preferred_ethnicity'],
			'preferred_body_type' => $sanitized['preferred_body_type'],
			'preferred_education' => $sanitized['preferred_education'],
			'preferred_children'  => $sanitized['preferred_children'],
			'show_profile'        => $sanitized['show_profile'],
			'allow_messages'      => $sanitized['allow_messages'],
			'email_notifications' => $sanitized['email_notifications'],
			'push_notifications'  => $sanitized['push_notifications'],
		);

		$data_types = array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d' );

		// Check if preferences exist.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT preference_id FROM `{$wpdb->prefix}wpmatch_user_preferences` WHERE user_id = %d",
				$user_id
			)
		);

		if ( $existing ) {
			return false !== $wpdb->update(
				$this->table_preferences,
				$data,
				array( 'user_id' => $user_id ),
				$data_types,
				array( '%d' )
			);
		} else {
			return false !== $wpdb->insert( $this->table_preferences, $data, $data_types );
		}
	}

	/**
	 * Get user preferences.
	 *
	 * @param int $user_id The user ID.
	 * @return object|null The preferences object or null.
	 */
	public function get_user_preferences( $user_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->prefix}wpmatch_user_preferences` WHERE user_id = %d",
				$user_id
			)
		);
	}

	/**
	 * Save user interests.
	 *
	 * @param int   $user_id The user ID.
	 * @param array $interests Array of interests.
	 * @return bool True on success, false on failure.
	 */
	public function save_user_interests( $user_id, $interests ) {
		global $wpdb;

		// Clear existing interests.
		$wpdb->delete( $this->table_interests, array( 'user_id' => $user_id ), array( '%d' ) );

		// Insert new interests.
		foreach ( $interests as $interest ) {
			if ( ! is_array( $interest ) || ! isset( $interest['category'], $interest['name'] ) ) {
				continue;
			}

			$wpdb->insert(
				$this->table_interests,
				array(
					'user_id'           => $user_id,
					'interest_category' => sanitize_text_field( $interest['category'] ),
					'interest_name'     => sanitize_text_field( $interest['name'] ),
				),
				array( '%d', '%s', '%s' )
			);
		}

		return true;
	}

	/**
	 * Get user interests.
	 *
	 * @param int $user_id The user ID.
	 * @return array Array of interest objects.
	 */
	public function get_user_interests( $user_id ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->prefix}wpmatch_user_interests` WHERE user_id = %d ORDER BY interest_category, interest_name",
				$user_id
			)
		);
	}

	/**
	 * Get user media.
	 *
	 * @param int $user_id The user ID.
	 * @param int $limit Optional. Limit number of results.
	 * @return array Array of media objects.
	 */
	public function get_user_media( $user_id, $limit = 0 ) {
		global $wpdb;

		if ( $limit > 0 ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM `{$wpdb->prefix}wpmatch_user_media` WHERE user_id = %d ORDER BY is_primary DESC, display_order ASC LIMIT %d",
					$user_id,
					$limit
				)
			);
		} else {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM `{$wpdb->prefix}wpmatch_user_media` WHERE user_id = %d ORDER BY is_primary DESC, display_order ASC",
					$user_id
				)
			);
		}
	}

	/**
	 * Sanitize profile data.
	 *
	 * @param array $data Raw profile data.
	 * @return array|WP_Error Sanitized data or error.
	 */
	private function sanitize_profile_data( $data ) {
		$sanitized = array();

		// Age validation.
		$sanitized['age'] = isset( $data['age'] ) ? absint( $data['age'] ) : null;
		if ( $sanitized['age'] && ( $sanitized['age'] < 18 || $sanitized['age'] > 120 ) ) {
			return new WP_Error( 'invalid_age', __( 'Age must be between 18 and 120.', 'wpmatch' ) );
		}

		// Location fields.
		$sanitized['location']  = isset( $data['location'] ) ? sanitize_text_field( $data['location'] ) : '';
		$sanitized['latitude']  = isset( $data['latitude'] ) ? floatval( $data['latitude'] ) : null;
		$sanitized['longitude'] = isset( $data['longitude'] ) ? floatval( $data['longitude'] ) : null;

		// Dropdown/select fields.
		$sanitized['gender']         = isset( $data['gender'] ) ? sanitize_text_field( $data['gender'] ) : '';
		$sanitized['orientation']    = isset( $data['orientation'] ) ? sanitize_text_field( $data['orientation'] ) : '';
		$sanitized['education']      = isset( $data['education'] ) ? sanitize_text_field( $data['education'] ) : '';
		$sanitized['profession']     = isset( $data['profession'] ) ? sanitize_text_field( $data['profession'] ) : '';
		$sanitized['income_range']   = isset( $data['income_range'] ) ? sanitize_text_field( $data['income_range'] ) : '';
		$sanitized['body_type']      = isset( $data['body_type'] ) ? sanitize_text_field( $data['body_type'] ) : '';
		$sanitized['ethnicity']      = isset( $data['ethnicity'] ) ? sanitize_text_field( $data['ethnicity'] ) : '';
		$sanitized['smoking']        = isset( $data['smoking'] ) ? sanitize_text_field( $data['smoking'] ) : '';
		$sanitized['drinking']       = isset( $data['drinking'] ) ? sanitize_text_field( $data['drinking'] ) : '';
		$sanitized['children']       = isset( $data['children'] ) ? sanitize_text_field( $data['children'] ) : '';
		$sanitized['wants_children'] = isset( $data['wants_children'] ) ? sanitize_text_field( $data['wants_children'] ) : '';
		$sanitized['pets']           = isset( $data['pets'] ) ? sanitize_text_field( $data['pets'] ) : '';

		// Height validation.
		$sanitized['height'] = isset( $data['height'] ) ? absint( $data['height'] ) : null;
		if ( $sanitized['height'] && ( $sanitized['height'] < 120 || $sanitized['height'] > 250 ) ) {
			return new WP_Error( 'invalid_height', __( 'Height must be between 120cm and 250cm.', 'wpmatch' ) );
		}

		// Text areas.
		$sanitized['about_me']    = isset( $data['about_me'] ) ? sanitize_textarea_field( $data['about_me'] ) : '';
		$sanitized['looking_for'] = isset( $data['looking_for'] ) ? sanitize_textarea_field( $data['looking_for'] ) : '';

		return $sanitized;
	}

	/**
	 * Sanitize preferences data.
	 *
	 * @param array $data Raw preferences data.
	 * @return array Sanitized preferences data.
	 */
	private function sanitize_preferences_data( $data ) {
		return array(
			'min_age'             => isset( $data['min_age'] ) ? absint( $data['min_age'] ) : 18,
			'max_age'             => isset( $data['max_age'] ) ? absint( $data['max_age'] ) : 99,
			'max_distance'        => isset( $data['max_distance'] ) ? absint( $data['max_distance'] ) : 50,
			'preferred_gender'    => isset( $data['preferred_gender'] ) ? sanitize_text_field( $data['preferred_gender'] ) : '',
			'preferred_ethnicity' => isset( $data['preferred_ethnicity'] ) ? sanitize_text_field( $data['preferred_ethnicity'] ) : '',
			'preferred_body_type' => isset( $data['preferred_body_type'] ) ? sanitize_text_field( $data['preferred_body_type'] ) : '',
			'preferred_education' => isset( $data['preferred_education'] ) ? sanitize_text_field( $data['preferred_education'] ) : '',
			'preferred_children'  => isset( $data['preferred_children'] ) ? sanitize_text_field( $data['preferred_children'] ) : '',
			'show_profile'        => isset( $data['show_profile'] ) ? absint( $data['show_profile'] ) : 1,
			'allow_messages'      => isset( $data['allow_messages'] ) ? absint( $data['allow_messages'] ) : 1,
			'email_notifications' => isset( $data['email_notifications'] ) ? absint( $data['email_notifications'] ) : 1,
			'push_notifications'  => isset( $data['push_notifications'] ) ? absint( $data['push_notifications'] ) : 1,
		);
	}

	/**
	 * Calculate profile completion percentage.
	 *
	 * @param array $profile_data The profile data.
	 * @return int Completion percentage (0-100).
	 */
	private function calculate_completion_percentage( $profile_data ) {
		$required_fields = array(
			'age'         => 10,
			'gender'      => 10,
			'location'    => 10,
			'about_me'    => 15,
			'profession'  => 8,
			'education'   => 8,
			'body_type'   => 5,
			'height'      => 5,
			'smoking'     => 5,
			'drinking'    => 5,
			'orientation' => 10,
			'looking_for' => 9,
		);

		$total_possible = 100;
		$earned_points  = 0;

		foreach ( $required_fields as $field => $points ) {
			if ( ! empty( $profile_data[ $field ] ) ) {
				$earned_points += $points;
			}
		}

		return min( 100, $earned_points );
	}
}
