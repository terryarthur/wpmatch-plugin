<?php
/**
 * WPMatch Search Manager
 *
 * Handles search functionality for finding potential matches.
 *
 * @package WPMatch
 * @since   1.0.0
 */

// Security check.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPMatch Search Manager Class
 *
 * Handles search functionality for finding potential matches.
 */
class WPMatch_Search_Manager {

	/**
	 * Search for potential matches based on criteria.
	 *
	 * @param int   $user_id User performing the search.
	 * @param array $criteria Search criteria.
	 * @return array Search results.
	 */
	public static function search_matches( $user_id, $criteria = array() ) {
		global $wpdb;

		$user_id = absint( $user_id );

		// Get current user's profile for reference.
		$user_profile = self::get_user_profile( $user_id );
		if ( ! $user_profile ) {
			return array();
		}

		// Default search criteria.
		$defaults = array(
			'min_age'        => 18,
			'max_age'        => 99,
			'max_distance'   => 50,
			'gender'         => '',
			'location'       => '',
			'keywords'       => '',
			'exclude_swiped' => true,
			'limit'          => 20,
			'offset'         => 0,
		);

		$criteria = wp_parse_args( $criteria, $defaults );

		// Sanitize inputs.
		$criteria['min_age']      = absint( $criteria['min_age'] );
		$criteria['max_age']      = absint( $criteria['max_age'] );
		$criteria['max_distance'] = absint( $criteria['max_distance'] );
		$criteria['gender']       = sanitize_text_field( $criteria['gender'] );
		$criteria['location']     = sanitize_text_field( $criteria['location'] );
		$criteria['keywords']     = sanitize_text_field( $criteria['keywords'] );
		$criteria['limit']        = absint( $criteria['limit'] );
		$criteria['offset']       = absint( $criteria['offset'] );

		// Build the search query.
		$table_profiles = $wpdb->prefix . 'wpmatch_user_profiles';
		$table_users    = $wpdb->users;

		$where_conditions = array();
		$query_params     = array();

		// Exclude current user.
		$where_conditions[] = 'p.user_id != %d';
		$query_params[]     = $user_id;

		// Age filter.
		if ( $criteria['min_age'] > 0 ) {
			$where_conditions[] = 'p.age >= %d';
			$query_params[]     = $criteria['min_age'];
		}

		if ( $criteria['max_age'] > 0 && $criteria['max_age'] < 120 ) {
			$where_conditions[] = 'p.age <= %d';
			$query_params[]     = $criteria['max_age'];
		}

		// Gender filter.
		if ( ! empty( $criteria['gender'] ) ) {
			$where_conditions[] = 'p.gender = %s';
			$query_params[]     = $criteria['gender'];
		}

		// Location filter (if provided).
		if ( ! empty( $criteria['location'] ) ) {
			$where_conditions[] = 'p.location LIKE %s';
			$query_params[]     = '%' . $wpdb->esc_like( $criteria['location'] ) . '%';
		}

		// Keywords search (search in about_me and looking_for).
		if ( ! empty( $criteria['keywords'] ) ) {
			$where_conditions[] = '(p.about_me LIKE %s OR p.looking_for LIKE %s)';
			$keywords_param     = '%' . $wpdb->esc_like( $criteria['keywords'] ) . '%';
			$query_params[]     = $keywords_param;
			$query_params[]     = $keywords_param;
		}

		// Distance filter (if user has location).
		if ( $user_profile->latitude && $user_profile->longitude && $criteria['max_distance'] > 0 ) {
			$distance_formula   = self::get_distance_formula( $user_profile->latitude, $user_profile->longitude );
			$where_conditions[] = "({$distance_formula}) <= %d";
			$query_params[]     = $criteria['max_distance'];
		}

		// Exclude users who have been swiped on.
		if ( $criteria['exclude_swiped'] ) {
			$table_swipes       = $wpdb->prefix . 'wpmatch_swipes';
			$where_conditions[] = "p.user_id NOT IN (
				SELECT target_user_id FROM {$table_swipes}
				WHERE user_id = %d
			)";
			$query_params[]     = $user_id;
		}

		// Only include profiles with some completion.
		$where_conditions[] = 'p.profile_completion > 0';

		// Build the final query.
		$where_clause = implode( ' AND ', $where_conditions );

		$query = '
			SELECT p.*, u.display_name, u.user_email,
			CASE
				WHEN p.latitude IS NOT NULL AND p.longitude IS NOT NULL AND %f IS NOT NULL AND %f IS NOT NULL
				THEN ' . self::get_distance_formula( $user_profile->latitude, $user_profile->longitude ) . "
				ELSE NULL
			END as distance
			FROM {$table_profiles} p
			INNER JOIN {$table_users} u ON p.user_id = u.ID
			WHERE {$where_clause}
			ORDER BY p.last_active DESC, p.profile_completion DESC
			LIMIT %d OFFSET %d
		";

		// Add user coordinates to params for distance calculation.
		array_unshift( $query_params, $user_profile->latitude, $user_profile->longitude );
		$query_params[] = $criteria['limit'];
		$query_params[] = $criteria['offset'];

		$results = $wpdb->get_results( $wpdb->prepare( $query, $query_params ) );

		// Add profile photos to results.
		if ( $results ) {
			foreach ( $results as &$profile ) {
				$profile->photos        = self::get_user_photos( $profile->user_id );
				$profile->primary_photo = self::get_primary_photo( $profile->user_id );
			}
		}

		return $results ? $results : array();
	}

	/**
	 * Get quick search suggestions based on partial input.
	 *
	 * @param string $query Search query.
	 * @param int    $limit Number of suggestions.
	 * @return array Suggestions.
	 */
	public static function get_search_suggestions( $query, $limit = 10 ) {
		global $wpdb;

		$query = sanitize_text_field( $query );
		$limit = absint( $limit );

		if ( strlen( $query ) < 2 ) {
			return array();
		}

		$table_profiles = $wpdb->prefix . 'wpmatch_user_profiles';

		// Search for locations.
		$locations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT location
				FROM {$table_profiles}
				WHERE location LIKE %s
				AND location IS NOT NULL
				AND location != ''
				ORDER BY location ASC
				LIMIT %d",
				'%' . $wpdb->esc_like( $query ) . '%',
				$limit
			)
		);

		$suggestions = array();
		foreach ( $locations as $location ) {
			$suggestions[] = array(
				'type'  => 'location',
				'value' => $location->location,
				'label' => $location->location,
			);
		}

		return $suggestions;
	}

	/**
	 * Get popular search filters for the search form.
	 *
	 * @return array Popular filters.
	 */
	public static function get_popular_filters() {
		global $wpdb;

		$table_profiles = $wpdb->prefix . 'wpmatch_user_profiles';

		// Get popular locations.
		$popular_locations = $wpdb->get_results(
			"SELECT location, COUNT(*) as count
			FROM {$table_profiles}
			WHERE location IS NOT NULL
			AND location != ''
			GROUP BY location
			ORDER BY count DESC
			LIMIT 10"
		);

		// Get age distribution.
		$age_ranges = $wpdb->get_results(
			"SELECT
				CASE
					WHEN age BETWEEN 18 AND 25 THEN '18-25'
					WHEN age BETWEEN 26 AND 35 THEN '26-35'
					WHEN age BETWEEN 36 AND 45 THEN '36-45'
					WHEN age BETWEEN 46 AND 55 THEN '46-55'
					ELSE '55+'
				END as age_range,
				COUNT(*) as count
			FROM {$table_profiles}
			WHERE age IS NOT NULL
			GROUP BY age_range
			ORDER BY count DESC"
		);

		return array(
			'locations'  => $popular_locations,
			'age_ranges' => $age_ranges,
		);
	}

	/**
	 * Save user search preferences.
	 *
	 * @param int   $user_id User ID.
	 * @param array $preferences Search preferences.
	 * @return bool Success status.
	 */
	public static function save_search_preferences( $user_id, $preferences ) {
		$user_id = absint( $user_id );

		$defaults = array(
			'min_age'      => 18,
			'max_age'      => 99,
			'max_distance' => 50,
			'gender'       => '',
		);

		$preferences = wp_parse_args( $preferences, $defaults );

		// Sanitize preferences.
		$preferences['min_age']      = absint( $preferences['min_age'] );
		$preferences['max_age']      = absint( $preferences['max_age'] );
		$preferences['max_distance'] = absint( $preferences['max_distance'] );
		$preferences['gender']       = sanitize_text_field( $preferences['gender'] );

		return update_user_meta( $user_id, 'wpmatch_search_preferences', $preferences );
	}

	/**
	 * Get user search preferences.
	 *
	 * @param int $user_id User ID.
	 * @return array Search preferences.
	 */
	public static function get_search_preferences( $user_id ) {
		$user_id = absint( $user_id );

		$preferences = get_user_meta( $user_id, 'wpmatch_search_preferences', true );

		$defaults = array(
			'min_age'      => 18,
			'max_age'      => 99,
			'max_distance' => 50,
			'gender'       => '',
		);

		return wp_parse_args( $preferences, $defaults );
	}

	/**
	 * Get user profile data.
	 *
	 * @param int $user_id User ID.
	 * @return object|null User profile.
	 */
	private static function get_user_profile( $user_id ) {
		global $wpdb;

		$table_profiles = $wpdb->prefix . 'wpmatch_user_profiles';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_profiles} WHERE user_id = %d",
				$user_id
			)
		);
	}

	/**
	 * Get user photos.
	 *
	 * @param int $user_id User ID.
	 * @return array User photos.
	 */
	private static function get_user_photos( $user_id ) {
		global $wpdb;

		$table_media = $wpdb->prefix . 'wpmatch_user_media';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_media}
				WHERE user_id = %d
				AND media_type = 'photo'
				ORDER BY is_primary DESC, display_order ASC",
				$user_id
			)
		);
	}

	/**
	 * Get primary photo for user.
	 *
	 * @param int $user_id User ID.
	 * @return string|null Primary photo URL.
	 */
	private static function get_primary_photo( $user_id ) {
		global $wpdb;

		$table_media = $wpdb->prefix . 'wpmatch_user_media';

		$primary_photo = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT file_path FROM {$table_media}
				WHERE user_id = %d
				AND media_type = 'photo'
				AND is_primary = 1
				LIMIT 1",
				$user_id
			)
		);

		return $primary_photo ? $primary_photo : null;
	}

	/**
	 * Get distance calculation formula for SQL.
	 *
	 * @param float $lat User latitude.
	 * @param float $lng User longitude.
	 * @return string SQL distance formula.
	 */
	private static function get_distance_formula( $lat, $lng ) {
		return "(
			3959 * acos(
				cos(radians({$lat})) *
				cos(radians(p.latitude)) *
				cos(radians(p.longitude) - radians({$lng})) +
				sin(radians({$lat})) *
				sin(radians(p.latitude))
			)
		)";
	}
}
