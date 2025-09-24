<?php
/**
 * User Preferences Management System
 *
 * Handles user preferences for matching, privacy, notifications, and settings.
 *
 * @package WPMatch
 */

/**
 * User Preferences Management Class
 *
 * Manages user preferences for dating matches, privacy controls, and notifications.
 */
class WPMatch_User_Preferences {

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
	 * Cache manager instance.
	 *
	 * @var WPMatch_Cache_Manager
	 */
	private $cache_manager;

	/**
	 * Job queue instance.
	 *
	 * @var WPMatch_Job_Queue
	 */
	private $job_queue;

	/**
	 * Default preference settings.
	 *
	 * @var array
	 */
	private $default_preferences = array(
		// Matching preferences.
		'min_age'                => 18,
		'max_age'                => 35,
		'max_distance'           => 50,
		'preferred_gender'       => 'any',
		'preferred_orientation'  => 'any',
		'preferred_education'    => 'any',
		'preferred_body_type'    => 'any',
		'preferred_ethnicity'    => 'any',
		'preferred_religion'     => 'any',
		'preferred_smoking'      => 'any',
		'preferred_drinking'     => 'any',
		'preferred_children'     => 'any',
		'preferred_pets'         => 'any',
		'preferred_income_range' => 'any',

		// Privacy settings.
		'profile_visibility'     => 'public',
		'show_online_status'     => true,
		'show_last_active'       => true,
		'show_distance'          => true,
		'show_age'               => true,
		'allow_search_engines'   => false,
		'block_list'             => array(),
		'invisible_mode'         => false,

		// Notification preferences.
		'email_notifications'    => true,
		'push_notifications'     => true,
		'sms_notifications'      => false,
		'notify_new_matches'     => true,
		'notify_new_messages'    => true,
		'notify_profile_views'   => true,
		'notify_likes'           => true,
		'notify_super_likes'     => true,
		'notify_promotions'      => false,
		'notify_events'          => true,
		'notify_friend_activity' => false,

		// Communication preferences.
		'allow_messages_from'    => 'matches_only',
		'read_receipts'          => true,
		'typing_indicators'      => true,
		'auto_reply_enabled'     => false,
		'auto_reply_message'     => '',
		'message_filters'        => array(),

		// Discovery preferences.
		'discoverable'           => true,
		'boost_profile'          => false,
		'show_me_on_boost'       => true,
		'global_mode'            => false,
		'passport_location'      => '',
		'recently_active_only'   => false,

		// Premium features.
		'unlimited_likes'        => false,
		'see_who_liked'          => false,
		'rewind_last_swipe'      => false,
		'priority_likes'         => false,
		'advanced_filters'       => false,
		'incognito_mode'         => false,
	);

	/**
	 * Initialize the user preferences system.
	 *
	 * @param string $plugin_name Plugin name.
	 * @param string $version     Plugin version.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		// Initialize dependencies.
		$this->cache_manager = WPMatch_Cache_Manager::get_instance();
		$this->job_queue     = WPMatch_Job_Queue::get_instance();
	}

	/**
	 * Initialize hooks and actions.
	 */
	public function init() {
		add_action( 'wp_ajax_wpmatch_get_preferences', array( $this, 'ajax_get_preferences' ) );
		add_action( 'wp_ajax_wpmatch_update_preferences', array( $this, 'ajax_update_preferences' ) );
		add_action( 'wp_ajax_wpmatch_reset_preferences', array( $this, 'ajax_reset_preferences' ) );
		add_action( 'wp_ajax_wpmatch_block_user', array( $this, 'ajax_block_user' ) );
		add_action( 'wp_ajax_wpmatch_unblock_user', array( $this, 'ajax_unblock_user' ) );
		add_action( 'wp_ajax_wpmatch_get_blocked_users', array( $this, 'ajax_get_blocked_users' ) );

		// Admin hooks.
		add_action( 'wp_ajax_wpmatch_admin_get_user_preferences', array( $this, 'ajax_admin_get_user_preferences' ) );
		add_action( 'wp_ajax_wpmatch_admin_update_user_preferences', array( $this, 'ajax_admin_update_user_preferences' ) );

		// User registration hook.
		add_action( 'user_register', array( $this, 'create_default_preferences' ) );
	}

	/**
	 * Create default preferences for new user.
	 *
	 * @param int $user_id User ID.
	 */
	public function create_default_preferences( $user_id ) {
		$this->update_user_preferences( $user_id, $this->default_preferences );
	}

	/**
	 * Get user preferences.
	 *
	 * @param int  $user_id User ID.
	 * @param bool $use_cache Whether to use cache.
	 * @return array User preferences.
	 */
	public function get_user_preferences( $user_id, $use_cache = true ) {
		$user_id = absint( $user_id );

		if ( empty( $user_id ) ) {
			return $this->default_preferences;
		}

		$cache_key = "user_preferences_{$user_id}";

		if ( $use_cache ) {
			$cached = $this->cache_manager->get( $cache_key, 'preferences' );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'wpmatch_user_preferences';

		$preferences = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE user_id = %d",
				$user_id
			),
			ARRAY_A
		);

		if ( ! $preferences ) {
			// Create default preferences if none exist.
			$this->create_default_preferences( $user_id );
			$preferences = $this->default_preferences;
		} else {
			// Remove non-preference fields.
			unset( $preferences['preference_id'], $preferences['user_id'], $preferences['created_at'], $preferences['updated_at'] );

			// Merge with defaults to ensure all keys exist.
			$preferences = wp_parse_args( $preferences, $this->default_preferences );

			// Convert JSON fields back to arrays.
			$json_fields = array( 'block_list', 'message_filters' );
			foreach ( $json_fields as $field ) {
				if ( isset( $preferences[ $field ] ) && is_string( $preferences[ $field ] ) ) {
					$preferences[ $field ] = json_decode( $preferences[ $field ], true ) ?: array();
				}
			}
		}

		// Cache for 1 hour.
		if ( $use_cache ) {
			$this->cache_manager->set( $cache_key, $preferences, 'preferences', 3600 );
		}

		return $preferences;
	}

	/**
	 * Update user preferences.
	 *
	 * @param int   $user_id     User ID.
	 * @param array $preferences Preferences to update.
	 * @return bool|WP_Error Success or error.
	 */
	public function update_user_preferences( $user_id, $preferences ) {
		$user_id = absint( $user_id );

		if ( empty( $user_id ) || ! is_array( $preferences ) ) {
			return new WP_Error( 'invalid_data', 'Invalid user ID or preferences data' );
		}

		// Check if user exists.
		if ( ! get_userdata( $user_id ) ) {
			return new WP_Error( 'user_not_found', 'User not found' );
		}

		// Sanitize and validate preferences.
		$sanitized_preferences = $this->sanitize_preferences( $preferences );

		if ( is_wp_error( $sanitized_preferences ) ) {
			return $sanitized_preferences;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'wpmatch_user_preferences';

		// Convert arrays to JSON.
		$json_fields = array( 'block_list', 'message_filters' );
		foreach ( $json_fields as $field ) {
			if ( isset( $sanitized_preferences[ $field ] ) && is_array( $sanitized_preferences[ $field ] ) ) {
				$sanitized_preferences[ $field ] = wp_json_encode( $sanitized_preferences[ $field ] );
			}
		}

		// Check if preferences already exist.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT preference_id FROM {$table_name} WHERE user_id = %d",
				$user_id
			)
		);

		if ( $existing ) {
			// Update existing preferences.
			$result = $wpdb->update(
				$table_name,
				$sanitized_preferences,
				array( 'user_id' => $user_id ),
				$this->get_update_format( $sanitized_preferences ),
				array( '%d' )
			);
		} else {
			// Insert new preferences.
			$sanitized_preferences['user_id']    = $user_id;
			$sanitized_preferences['created_at'] = current_time( 'mysql' );

			$result = $wpdb->insert(
				$table_name,
				$sanitized_preferences,
				$this->get_insert_format( $sanitized_preferences )
			);
		}

		if ( false === $result ) {
			return new WP_Error( 'db_error', 'Database error occurred' );
		}

		// Clear cache.
		$this->cache_manager->delete( "user_preferences_{$user_id}", 'preferences' );

		// Queue preference analysis job.
		$this->job_queue->queue_job( 'analyze_user_preferences', array( 'user_id' => $user_id ) );

		return true;
	}

	/**
	 * Sanitize user preferences.
	 *
	 * @param array $preferences Raw preferences.
	 * @return array|WP_Error Sanitized preferences or error.
	 */
	private function sanitize_preferences( $preferences ) {
		$sanitized = array();

		// Numeric fields.
		$numeric_fields = array( 'min_age', 'max_age', 'max_distance' );
		foreach ( $numeric_fields as $field ) {
			if ( isset( $preferences[ $field ] ) ) {
				$sanitized[ $field ] = absint( $preferences[ $field ] );
			}
		}

		// Age validation.
		if ( isset( $sanitized['min_age'] ) && ( $sanitized['min_age'] < 18 || $sanitized['min_age'] > 100 ) ) {
			return new WP_Error( 'invalid_age', 'Minimum age must be between 18 and 100' );
		}

		if ( isset( $sanitized['max_age'] ) && ( $sanitized['max_age'] < 18 || $sanitized['max_age'] > 100 ) ) {
			return new WP_Error( 'invalid_age', 'Maximum age must be between 18 and 100' );
		}

		if ( isset( $sanitized['min_age'], $sanitized['max_age'] ) && $sanitized['min_age'] > $sanitized['max_age'] ) {
			return new WP_Error( 'invalid_age_range', 'Minimum age cannot be greater than maximum age' );
		}

		// Distance validation.
		if ( isset( $sanitized['max_distance'] ) && ( $sanitized['max_distance'] < 1 || $sanitized['max_distance'] > 500 ) ) {
			return new WP_Error( 'invalid_distance', 'Maximum distance must be between 1 and 500 miles' );
		}

		// String fields.
		$string_fields = array(
			'preferred_gender',
			'preferred_orientation',
			'preferred_education',
			'preferred_body_type',
			'preferred_ethnicity',
			'preferred_religion',
			'preferred_smoking',
			'preferred_drinking',
			'preferred_children',
			'preferred_pets',
			'preferred_income_range',
			'profile_visibility',
			'allow_messages_from',
			'auto_reply_message',
			'passport_location',
		);

		foreach ( $string_fields as $field ) {
			if ( isset( $preferences[ $field ] ) ) {
				$sanitized[ $field ] = sanitize_text_field( $preferences[ $field ] );
			}
		}

		// Boolean fields.
		$boolean_fields = array(
			'show_online_status',
			'show_last_active',
			'show_distance',
			'show_age',
			'allow_search_engines',
			'invisible_mode',
			'email_notifications',
			'push_notifications',
			'sms_notifications',
			'notify_new_matches',
			'notify_new_messages',
			'notify_profile_views',
			'notify_likes',
			'notify_super_likes',
			'notify_promotions',
			'notify_events',
			'notify_friend_activity',
			'read_receipts',
			'typing_indicators',
			'auto_reply_enabled',
			'discoverable',
			'boost_profile',
			'show_me_on_boost',
			'global_mode',
			'recently_active_only',
			'unlimited_likes',
			'see_who_liked',
			'rewind_last_swipe',
			'priority_likes',
			'advanced_filters',
			'incognito_mode',
		);

		foreach ( $boolean_fields as $field ) {
			if ( isset( $preferences[ $field ] ) ) {
				$sanitized[ $field ] = (bool) $preferences[ $field ];
			}
		}

		// Array fields.
		$array_fields = array( 'block_list', 'message_filters' );
		foreach ( $array_fields as $field ) {
			if ( isset( $preferences[ $field ] ) ) {
				if ( is_array( $preferences[ $field ] ) ) {
					$sanitized[ $field ] = array_map( 'sanitize_text_field', $preferences[ $field ] );
				} elseif ( is_string( $preferences[ $field ] ) ) {
					$decoded             = json_decode( $preferences[ $field ], true );
					$sanitized[ $field ] = is_array( $decoded ) ? array_map( 'sanitize_text_field', $decoded ) : array();
				}
			}
		}

		return $sanitized;
	}

	/**
	 * Get database insert format for preferences.
	 *
	 * @param array $preferences Preferences data.
	 * @return array Format array.
	 */
	private function get_insert_format( $preferences ) {
		$format = array();

		foreach ( $preferences as $key => $value ) {
			if ( 'user_id' === $key || in_array( $key, array( 'min_age', 'max_age', 'max_distance' ), true ) ) {
				$format[] = '%d';
			} elseif ( in_array( $key, array( 'created_at', 'updated_at' ), true ) ) {
				$format[] = '%s';
			} else {
				$format[] = is_bool( $value ) ? '%d' : '%s';
			}
		}

		return $format;
	}

	/**
	 * Get database update format for preferences.
	 *
	 * @param array $preferences Preferences data.
	 * @return array Format array.
	 */
	private function get_update_format( $preferences ) {
		$format = array();

		foreach ( $preferences as $key => $value ) {
			if ( in_array( $key, array( 'min_age', 'max_age', 'max_distance' ), true ) ) {
				$format[] = '%d';
			} else {
				$format[] = is_bool( $value ) ? '%d' : '%s';
			}
		}

		return $format;
	}

	/**
	 * Block a user.
	 *
	 * @param int $user_id         User ID doing the blocking.
	 * @param int $blocked_user_id User ID being blocked.
	 * @return bool|WP_Error Success or error.
	 */
	public function block_user( $user_id, $blocked_user_id ) {
		$user_id         = absint( $user_id );
		$blocked_user_id = absint( $blocked_user_id );

		if ( empty( $user_id ) || empty( $blocked_user_id ) || $user_id === $blocked_user_id ) {
			return new WP_Error( 'invalid_data', 'Invalid user IDs' );
		}

		// Check if users exist.
		if ( ! get_userdata( $user_id ) || ! get_userdata( $blocked_user_id ) ) {
			return new WP_Error( 'user_not_found', 'User not found' );
		}

		$preferences = $this->get_user_preferences( $user_id );
		$block_list  = $preferences['block_list'] ?? array();

		if ( ! in_array( $blocked_user_id, $block_list, true ) ) {
			$block_list[] = $blocked_user_id;

			$result = $this->update_user_preferences( $user_id, array( 'block_list' => $block_list ) );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			// Queue block notification job.
			$this->job_queue->queue_job(
				'process_user_block',
				array(
					'blocker_id' => $user_id,
					'blocked_id' => $blocked_user_id,
				)
			);
		}

		return true;
	}

	/**
	 * Unblock a user.
	 *
	 * @param int $user_id           User ID doing the unblocking.
	 * @param int $unblocked_user_id User ID being unblocked.
	 * @return bool|WP_Error Success or error.
	 */
	public function unblock_user( $user_id, $unblocked_user_id ) {
		$user_id           = absint( $user_id );
		$unblocked_user_id = absint( $unblocked_user_id );

		if ( empty( $user_id ) || empty( $unblocked_user_id ) ) {
			return new WP_Error( 'invalid_data', 'Invalid user IDs' );
		}

		$preferences = $this->get_user_preferences( $user_id );
		$block_list  = $preferences['block_list'] ?? array();

		$key = array_search( $unblocked_user_id, $block_list, true );
		if ( false !== $key ) {
			unset( $block_list[ $key ] );
			$block_list = array_values( $block_list ); // Re-index array.

			$result = $this->update_user_preferences( $user_id, array( 'block_list' => $block_list ) );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * Get blocked users for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array Blocked users information.
	 */
	public function get_blocked_users( $user_id ) {
		$user_id = absint( $user_id );

		if ( empty( $user_id ) ) {
			return array();
		}

		$preferences = $this->get_user_preferences( $user_id );
		$block_list  = $preferences['block_list'] ?? array();

		if ( empty( $block_list ) ) {
			return array();
		}

		$blocked_users = array();
		foreach ( $block_list as $blocked_id ) {
			$user_data = get_userdata( $blocked_id );
			if ( $user_data ) {
				$blocked_users[] = array(
					'user_id'      => $blocked_id,
					'display_name' => $user_data->display_name,
					'user_login'   => $user_data->user_login,
					'blocked_at'   => '', // Could be enhanced to track when blocked.
				);
			}
		}

		return $blocked_users;
	}

	/**
	 * Check if a user is blocked by another user.
	 *
	 * @param int $user_id         User ID.
	 * @param int $potential_match User ID to check.
	 * @return bool Whether user is blocked.
	 */
	public function is_user_blocked( $user_id, $potential_match ) {
		$user_id         = absint( $user_id );
		$potential_match = absint( $potential_match );

		if ( empty( $user_id ) || empty( $potential_match ) ) {
			return false;
		}

		$preferences = $this->get_user_preferences( $user_id );
		$block_list  = $preferences['block_list'] ?? array();

		return in_array( $potential_match, $block_list, true );
	}

	/**
	 * Check if user meets another user's preferences.
	 *
	 * @param int $user_id      User ID whose preferences to check.
	 * @param int $candidate_id Candidate user ID.
	 * @return array Match data with score and details.
	 */
	public function check_preference_match( $user_id, $candidate_id ) {
		$user_id      = absint( $user_id );
		$candidate_id = absint( $candidate_id );

		if ( empty( $user_id ) || empty( $candidate_id ) || $user_id === $candidate_id ) {
			return array(
				'matches'  => false,
				'score'    => 0,
				'reasons'  => array(),
				'blockers' => array(),
			);
		}

		$preferences = $this->get_user_preferences( $user_id );

		// Check if candidate is blocked.
		if ( $this->is_user_blocked( $user_id, $candidate_id ) ) {
			return array(
				'matches'  => false,
				'score'    => 0,
				'reasons'  => array(),
				'blockers' => array( 'User is blocked' ),
			);
		}

		// Get candidate's profile data (would need profile manager integration).
		$candidate_profile = $this->get_user_profile_for_matching( $candidate_id );

		if ( ! $candidate_profile ) {
			return array(
				'matches'  => false,
				'score'    => 0,
				'reasons'  => array(),
				'blockers' => array( 'Candidate profile not found' ),
			);
		}

		$score    = 0;
		$reasons  = array();
		$blockers = array();

		// Age check.
		if ( isset( $candidate_profile['age'] ) ) {
			$age = absint( $candidate_profile['age'] );
			if ( $age < $preferences['min_age'] || $age > $preferences['max_age'] ) {
				$blockers[] = 'Age outside preferred range';
			} else {
				$score    += 20;
				$reasons[] = 'Age within preferred range';
			}
		}

		// Distance check (would need location calculation).
		if ( isset( $candidate_profile['distance'] ) ) {
			$distance = absint( $candidate_profile['distance'] );
			if ( $distance > $preferences['max_distance'] ) {
				$blockers[] = 'Too far away';
			} else {
				$score    += 15;
				$reasons[] = 'Within distance preference';
			}
		}

		// Gender preference check.
		if ( 'any' !== $preferences['preferred_gender'] && isset( $candidate_profile['gender'] ) ) {
			if ( $preferences['preferred_gender'] !== $candidate_profile['gender'] ) {
				$blockers[] = 'Gender preference mismatch';
			} else {
				$score    += 25;
				$reasons[] = 'Gender preference match';
			}
		}

		// Additional preference checks.
		$preference_mappings = array(
			'preferred_education'    => 'education',
			'preferred_body_type'    => 'body_type',
			'preferred_ethnicity'    => 'ethnicity',
			'preferred_smoking'      => 'smoking',
			'preferred_drinking'     => 'drinking',
			'preferred_children'     => 'children',
			'preferred_income_range' => 'income_range',
		);

		foreach ( $preference_mappings as $pref_key => $profile_key ) {
			if ( 'any' !== $preferences[ $pref_key ] && isset( $candidate_profile[ $profile_key ] ) ) {
				if ( $preferences[ $pref_key ] === $candidate_profile[ $profile_key ] ) {
					$score    += 5;
					$reasons[] = ucfirst( str_replace( '_', ' ', $profile_key ) ) . ' preference match';
				}
			}
		}

		$matches = empty( $blockers ) && $score > 0;

		return array(
			'matches'  => $matches,
			'score'    => $score,
			'reasons'  => $reasons,
			'blockers' => $blockers,
		);
	}

	/**
	 * Get user profile data for matching (placeholder - would integrate with profile manager).
	 *
	 * @param int $user_id User ID.
	 * @return array|false Profile data or false.
	 */
	private function get_user_profile_for_matching( $user_id ) {
		// This would integrate with the profile manager to get user data.
		// For now, return placeholder data.
		return array(
			'age'          => 25,
			'gender'       => 'female',
			'education'    => 'bachelor',
			'body_type'    => 'average',
			'ethnicity'    => 'mixed',
			'smoking'      => 'no',
			'drinking'     => 'socially',
			'children'     => 'none',
			'income_range' => '50-75k',
			'distance'     => 15,
		);
	}

	/**
	 * Reset user preferences to defaults.
	 *
	 * @param int $user_id User ID.
	 * @return bool|WP_Error Success or error.
	 */
	public function reset_user_preferences( $user_id ) {
		return $this->update_user_preferences( $user_id, $this->default_preferences );
	}

	/**
	 * Get preference statistics.
	 *
	 * @return array Preference statistics.
	 */
	public function get_preference_statistics() {
		$cache_key = 'preference_statistics';
		$cached    = $this->cache_manager->get( $cache_key, 'preferences' );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'wpmatch_user_preferences';

		$stats = array();

		// Age range statistics.
		$age_stats = $wpdb->get_results(
			"SELECT AVG(min_age) as avg_min_age, AVG(max_age) as avg_max_age,
			MIN(min_age) as min_age_limit, MAX(max_age) as max_age_limit
			FROM {$table_name}",
			ARRAY_A
		);

		// Distance statistics.
		$distance_stats = $wpdb->get_results(
			"SELECT AVG(max_distance) as avg_distance, MIN(max_distance) as min_distance,
			MAX(max_distance) as max_distance
			FROM {$table_name}",
			ARRAY_A
		);

		// Gender preferences.
		$gender_prefs = $wpdb->get_results(
			"SELECT preferred_gender, COUNT(*) as count
			FROM {$table_name}
			GROUP BY preferred_gender
			ORDER BY count DESC",
			ARRAY_A
		);

		// Notification preferences.
		$notification_stats = $wpdb->get_results(
			"SELECT
				SUM(email_notifications) as email_enabled,
				SUM(push_notifications) as push_enabled,
				SUM(sms_notifications) as sms_enabled,
				COUNT(*) as total_users
			FROM {$table_name}",
			ARRAY_A
		);

		$stats = array(
			'age_statistics'         => $age_stats[0] ?? array(),
			'distance_statistics'    => $distance_stats[0] ?? array(),
			'gender_preferences'     => $gender_prefs,
			'notification_stats'     => $notification_stats[0] ?? array(),
			'total_users_with_prefs' => $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" ),
		);

		// Cache for 1 hour.
		$this->cache_manager->set( $cache_key, $stats, 'preferences', 3600 );

		return $stats;
	}

	/**
	 * AJAX handler for getting user preferences.
	 */
	public function ajax_get_preferences() {
		check_ajax_referer( 'wpmatch_preferences', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( 'User not logged in' );
		}

		wp_send_json_success(
			array(
				'preferences' => $this->get_user_preferences( $user_id ),
			)
		);
	}

	/**
	 * AJAX handler for updating user preferences.
	 */
	public function ajax_update_preferences() {
		check_ajax_referer( 'wpmatch_preferences', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( 'User not logged in' );
		}

		$preferences = json_decode( wp_unslash( $_POST['preferences'] ?? '{}' ), true );

		if ( ! is_array( $preferences ) ) {
			wp_send_json_error( 'Invalid preferences data' );
		}

		$result = $this->update_user_preferences( $user_id, $preferences );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success(
			array(
				'message'     => 'Preferences updated successfully',
				'preferences' => $this->get_user_preferences( $user_id ),
			)
		);
	}

	/**
	 * AJAX handler for resetting user preferences.
	 */
	public function ajax_reset_preferences() {
		check_ajax_referer( 'wpmatch_preferences', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( 'User not logged in' );
		}

		$result = $this->reset_user_preferences( $user_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success(
			array(
				'message'     => 'Preferences reset successfully',
				'preferences' => $this->get_user_preferences( $user_id ),
			)
		);
	}

	/**
	 * AJAX handler for blocking a user.
	 */
	public function ajax_block_user() {
		check_ajax_referer( 'wpmatch_preferences', 'nonce' );

		$user_id         = get_current_user_id();
		$blocked_user_id = absint( $_POST['blocked_user_id'] ?? 0 );

		if ( ! $user_id ) {
			wp_send_json_error( 'User not logged in' );
		}

		$result = $this->block_user( $user_id, $blocked_user_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success(
			array(
				'message'       => 'User blocked successfully',
				'blocked_users' => $this->get_blocked_users( $user_id ),
			)
		);
	}

	/**
	 * AJAX handler for unblocking a user.
	 */
	public function ajax_unblock_user() {
		check_ajax_referer( 'wpmatch_preferences', 'nonce' );

		$user_id           = get_current_user_id();
		$unblocked_user_id = absint( $_POST['unblocked_user_id'] ?? 0 );

		if ( ! $user_id ) {
			wp_send_json_error( 'User not logged in' );
		}

		$result = $this->unblock_user( $user_id, $unblocked_user_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success(
			array(
				'message'       => 'User unblocked successfully',
				'blocked_users' => $this->get_blocked_users( $user_id ),
			)
		);
	}

	/**
	 * AJAX handler for getting blocked users.
	 */
	public function ajax_get_blocked_users() {
		check_ajax_referer( 'wpmatch_preferences', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( 'User not logged in' );
		}

		wp_send_json_success(
			array(
				'blocked_users' => $this->get_blocked_users( $user_id ),
			)
		);
	}

	/**
	 * AJAX handler for admin getting user preferences.
	 */
	public function ajax_admin_get_user_preferences() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		check_ajax_referer( 'wpmatch_admin', 'nonce' );

		$user_id = absint( $_POST['user_id'] ?? 0 );

		if ( ! $user_id ) {
			wp_send_json_error( 'Invalid user ID' );
		}

		wp_send_json_success(
			array(
				'preferences' => $this->get_user_preferences( $user_id ),
				'statistics'  => $this->get_preference_statistics(),
			)
		);
	}

	/**
	 * AJAX handler for admin updating user preferences.
	 */
	public function ajax_admin_update_user_preferences() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		check_ajax_referer( 'wpmatch_admin', 'nonce' );

		$user_id     = absint( $_POST['user_id'] ?? 0 );
		$preferences = json_decode( wp_unslash( $_POST['preferences'] ?? '{}' ), true );

		if ( ! $user_id || ! is_array( $preferences ) ) {
			wp_send_json_error( 'Invalid data' );
		}

		$result = $this->update_user_preferences( $user_id, $preferences );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success(
			array(
				'message'     => 'User preferences updated successfully',
				'preferences' => $this->get_user_preferences( $user_id ),
			)
		);
	}

	/**
	 * REST API: Get user preferences.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function api_get_user_preferences( $request ) {
		$user_id = $request->get_param( 'user_id' );

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return new WP_REST_Response( array( 'error' => 'User not found' ), 400 );
		}

		// Check permissions.
		if ( get_current_user_id() !== $user_id && ! current_user_can( 'manage_options' ) ) {
			return new WP_REST_Response( array( 'error' => 'Insufficient permissions' ), 403 );
		}

		return new WP_REST_Response(
			array(
				'preferences' => $this->get_user_preferences( $user_id ),
			)
		);
	}

	/**
	 * REST API: Update user preferences.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function api_update_user_preferences( $request ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return new WP_REST_Response( array( 'error' => 'User not logged in' ), 401 );
		}

		$preferences = $request->get_param( 'preferences' );

		if ( ! is_array( $preferences ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid preferences data' ), 400 );
		}

		$result = $this->update_user_preferences( $user_id, $preferences );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'error' => $result->get_error_message() ), 400 );
		}

		return new WP_REST_Response(
			array(
				'success'     => true,
				'message'     => 'Preferences updated successfully',
				'preferences' => $this->get_user_preferences( $user_id ),
			)
		);
	}

	/**
	 * REST API: Block user.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function api_block_user( $request ) {
		$user_id         = get_current_user_id();
		$blocked_user_id = $request->get_param( 'user_id' );

		if ( ! $user_id ) {
			return new WP_REST_Response( array( 'error' => 'User not logged in' ), 401 );
		}

		$result = $this->block_user( $user_id, $blocked_user_id );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'error' => $result->get_error_message() ), 400 );
		}

		return new WP_REST_Response(
			array(
				'success'       => true,
				'message'       => 'User blocked successfully',
				'blocked_users' => $this->get_blocked_users( $user_id ),
			)
		);
	}

	/**
	 * REST API: Unblock user.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function api_unblock_user( $request ) {
		$user_id           = get_current_user_id();
		$unblocked_user_id = $request->get_param( 'user_id' );

		if ( ! $user_id ) {
			return new WP_REST_Response( array( 'error' => 'User not logged in' ), 401 );
		}

		$result = $this->unblock_user( $user_id, $unblocked_user_id );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'error' => $result->get_error_message() ), 400 );
		}

		return new WP_REST_Response(
			array(
				'success'       => true,
				'message'       => 'User unblocked successfully',
				'blocked_users' => $this->get_blocked_users( $user_id ),
			)
		);
	}

	/**
	 * REST API: Get blocked users.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function api_get_blocked_users( $request ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return new WP_REST_Response( array( 'error' => 'User not logged in' ), 401 );
		}

		return new WP_REST_Response(
			array(
				'blocked_users' => $this->get_blocked_users( $user_id ),
			)
		);
	}

	/**
	 * REST API: Check preference match.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function api_check_preference_match( $request ) {
		$user_id      = get_current_user_id();
		$candidate_id = $request->get_param( 'candidate_id' );

		if ( ! $user_id ) {
			return new WP_REST_Response( array( 'error' => 'User not logged in' ), 401 );
		}

		if ( ! $candidate_id ) {
			return new WP_REST_Response( array( 'error' => 'Candidate ID required' ), 400 );
		}

		return new WP_REST_Response(
			$this->check_preference_match( $user_id, $candidate_id )
		);
	}
}
