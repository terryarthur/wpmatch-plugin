<?php
/**
 * Swipe matching database operations
 *
 * @package WPMatch
 */

/**
 * Handles all database operations for the swipe matching system.
 */
class WPMatch_Swipe_DB {

	/**
	 * Create swipe matching database tables.
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Swipes table - tracks all swipe actions.
		$table_swipes = $wpdb->prefix . 'wpmatch_swipes';
		$sql_swipes = "CREATE TABLE IF NOT EXISTS $table_swipes (
			swipe_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			target_user_id bigint(20) UNSIGNED NOT NULL,
			swipe_type enum('like','pass','super_like') NOT NULL DEFAULT 'like',
			is_undo tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			ip_address varchar(45) DEFAULT NULL,
			PRIMARY KEY (swipe_id),
			UNIQUE KEY unique_swipe (user_id, target_user_id, is_undo),
			KEY idx_user_swipes (user_id, created_at),
			KEY idx_target_swipes (target_user_id, swipe_type),
			KEY idx_swipe_type (swipe_type, created_at),
			KEY idx_undo_status (is_undo, created_at),
			FOREIGN KEY (user_id) REFERENCES {$wpdb->users}(ID) ON DELETE CASCADE,
			FOREIGN KEY (target_user_id) REFERENCES {$wpdb->users}(ID) ON DELETE CASCADE
		) $charset_collate;";

		// Matches table - stores confirmed mutual matches.
		$table_matches = $wpdb->prefix . 'wpmatch_matches';
		$sql_matches = "CREATE TABLE IF NOT EXISTS $table_matches (
			match_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user1_id bigint(20) UNSIGNED NOT NULL,
			user2_id bigint(20) UNSIGNED NOT NULL,
			matched_at datetime DEFAULT CURRENT_TIMESTAMP,
			status enum('active','unmatched','blocked') NOT NULL DEFAULT 'active',
			last_activity datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (match_id),
			UNIQUE KEY unique_match (user1_id, user2_id),
			KEY idx_user1_matches (user1_id, status, matched_at),
			KEY idx_user2_matches (user2_id, status, matched_at),
			KEY idx_match_status (status, last_activity),
			KEY idx_matched_date (matched_at),
			FOREIGN KEY (user1_id) REFERENCES {$wpdb->users}(ID) ON DELETE CASCADE,
			FOREIGN KEY (user2_id) REFERENCES {$wpdb->users}(ID) ON DELETE CASCADE,
			CHECK (user1_id < user2_id)
		) $charset_collate;";

		// Match queue table - optimizes potential match discovery.
		$table_queue = $wpdb->prefix . 'wpmatch_match_queue';
		$sql_queue = "CREATE TABLE IF NOT EXISTS $table_queue (
			queue_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			potential_match_id bigint(20) UNSIGNED NOT NULL,
			compatibility_score decimal(3,2) DEFAULT 0.50,
			last_shown datetime DEFAULT NULL,
			priority int(11) NOT NULL DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (queue_id),
			UNIQUE KEY unique_queue_entry (user_id, potential_match_id),
			KEY idx_user_queue (user_id, compatibility_score DESC, priority DESC),
			KEY idx_last_shown (user_id, last_shown),
			KEY idx_priority (user_id, priority DESC),
			FOREIGN KEY (user_id) REFERENCES {$wpdb->users}(ID) ON DELETE CASCADE,
			FOREIGN KEY (potential_match_id) REFERENCES {$wpdb->users}(ID) ON DELETE CASCADE
		) $charset_collate;";

		// Analytics table - stores aggregated swipe statistics.
		$table_analytics = $wpdb->prefix . 'wpmatch_swipe_analytics';
		$sql_analytics = "CREATE TABLE IF NOT EXISTS $table_analytics (
			analytics_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			date_recorded date NOT NULL,
			user_id bigint(20) UNSIGNED DEFAULT NULL,
			total_swipes int(11) NOT NULL DEFAULT 0,
			likes_given int(11) NOT NULL DEFAULT 0,
			likes_received int(11) NOT NULL DEFAULT 0,
			passes_given int(11) NOT NULL DEFAULT 0,
			passes_received int(11) NOT NULL DEFAULT 0,
			super_likes_given int(11) NOT NULL DEFAULT 0,
			super_likes_received int(11) NOT NULL DEFAULT 0,
			matches_created int(11) NOT NULL DEFAULT 0,
			swipe_back_rate decimal(5,2) DEFAULT 0.00,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (analytics_id),
			UNIQUE KEY unique_daily_stats (date_recorded, user_id),
			KEY idx_date_recorded (date_recorded),
			KEY idx_user_analytics (user_id, date_recorded),
			KEY idx_match_stats (matches_created, date_recorded),
			FOREIGN KEY (user_id) REFERENCES {$wpdb->users}(ID) ON DELETE CASCADE
		) $charset_collate;";

		// Execute table creation using WordPress dbDelta.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_swipes );
		dbDelta( $sql_matches );
		dbDelta( $sql_queue );
		dbDelta( $sql_analytics );

		// Store database version for future updates.
		update_option( 'wpmatch_swipe_db_version', '1.0.0' );

		// Create database triggers for automatic functionality.
		self::create_triggers();
	}

	/**
	 * Create database triggers for automatic match detection and analytics.
	 */
	private static function create_triggers() {
		global $wpdb;

		// Trigger for automatic match creation after mutual like.
		$trigger_match = "
		CREATE TRIGGER IF NOT EXISTS wpmatch_auto_match
		AFTER INSERT ON {$wpdb->prefix}wpmatch_swipes
		FOR EACH ROW
		BEGIN
			DECLARE mutual_like_exists INT DEFAULT 0;
			DECLARE match_exists INT DEFAULT 0;

			-- Only process like and super_like swipes that are not undos
			IF NEW.swipe_type IN ('like', 'super_like') AND NEW.is_undo = 0 THEN
				-- Check if target user has also liked this user
				SELECT COUNT(*) INTO mutual_like_exists
				FROM {$wpdb->prefix}wpmatch_swipes
				WHERE user_id = NEW.target_user_id
				AND target_user_id = NEW.user_id
				AND swipe_type IN ('like', 'super_like')
				AND is_undo = 0;

				-- Check if match already exists
				SELECT COUNT(*) INTO match_exists
				FROM {$wpdb->prefix}wpmatch_matches
				WHERE (user1_id = LEAST(NEW.user_id, NEW.target_user_id)
				AND user2_id = GREATEST(NEW.user_id, NEW.target_user_id))
				AND status = 'active';

				-- Create match if mutual like exists and no active match exists
				IF mutual_like_exists > 0 AND match_exists = 0 THEN
					INSERT INTO {$wpdb->prefix}wpmatch_matches
					(user1_id, user2_id, matched_at, status, last_activity, created_at, updated_at)
					VALUES (
						LEAST(NEW.user_id, NEW.target_user_id),
						GREATEST(NEW.user_id, NEW.target_user_id),
						NOW(),
						'active',
						NOW(),
						NOW(),
						NOW()
					);
				END IF;
			END IF;
		END;";

		// Note: WordPress/MySQL doesn't always support triggers in shared hosting.
		// We'll implement this logic in PHP as a fallback.
		try {
			$wpdb->query( $trigger_match );
		} catch ( Exception $e ) {
			// Trigger creation failed, will use PHP logic instead.
			error_log( 'WPMatch: Could not create database triggers, using PHP fallback: ' . $e->getMessage() );
		}
	}

	/**
	 * Record a swipe action.
	 *
	 * @param int    $user_id User performing the swipe.
	 * @param int    $target_user_id User being swiped on.
	 * @param string $swipe_type Type of swipe (like, pass, super_like).
	 * @param string $ip_address IP address for tracking.
	 * @return int|false Swipe ID on success, false on failure.
	 */
	public static function record_swipe( $user_id, $target_user_id, $swipe_type, $ip_address = null ) {
		global $wpdb;

		// Validate inputs.
		$user_id = absint( $user_id );
		$target_user_id = absint( $target_user_id );
		$swipe_type = sanitize_text_field( $swipe_type );
		$ip_address = sanitize_text_field( $ip_address );

		if ( ! $user_id || ! $target_user_id || $user_id === $target_user_id ) {
			return false;
		}

		if ( ! in_array( $swipe_type, array( 'like', 'pass', 'super_like' ), true ) ) {
			return false;
		}

		// Check for existing active swipe.
		$table_name = $wpdb->prefix . 'wpmatch_swipes';
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT swipe_id FROM $table_name
			WHERE user_id = %d AND target_user_id = %d AND is_undo = 0",
			$user_id,
			$target_user_id
		) );

		if ( $existing ) {
			return false; // Duplicate swipe not allowed.
		}

		// Insert swipe record.
		$result = $wpdb->insert(
			$table_name,
			array(
				'user_id'        => $user_id,
				'target_user_id' => $target_user_id,
				'swipe_type'     => $swipe_type,
				'is_undo'        => 0,
				'created_at'     => current_time( 'mysql' ),
				'ip_address'     => $ip_address ?: $_SERVER['REMOTE_ADDR'],
			),
			array( '%d', '%d', '%s', '%d', '%s', '%s' )
		);

		if ( ! $result ) {
			return false;
		}

		$swipe_id = $wpdb->insert_id;

		// Check for mutual match if this was a like.
		if ( in_array( $swipe_type, array( 'like', 'super_like' ), true ) ) {
			self::check_mutual_match( $user_id, $target_user_id );
		}

		// Update analytics.
		self::update_daily_analytics( $user_id, $swipe_type, 'given' );
		self::update_daily_analytics( $target_user_id, $swipe_type, 'received' );

		return $swipe_id;
	}

	/**
	 * Check for mutual match and create if found.
	 *
	 * @param int $user_id First user ID.
	 * @param int $target_user_id Second user ID.
	 * @return int|false Match ID if created, false otherwise.
	 */
	public static function check_mutual_match( $user_id, $target_user_id ) {
		global $wpdb;

		$swipes_table = $wpdb->prefix . 'wpmatch_swipes';
		$matches_table = $wpdb->prefix . 'wpmatch_matches';

		// Check if target user has liked this user.
		$mutual_like = $wpdb->get_var( $wpdb->prepare(
			"SELECT swipe_id FROM $swipes_table
			WHERE user_id = %d AND target_user_id = %d
			AND swipe_type IN ('like', 'super_like') AND is_undo = 0",
			$target_user_id,
			$user_id
		) );

		if ( ! $mutual_like ) {
			return false;
		}

		// Ensure consistent ordering (lower ID first).
		$user1_id = min( $user_id, $target_user_id );
		$user2_id = max( $user_id, $target_user_id );

		// Check if match already exists.
		$existing_match = $wpdb->get_var( $wpdb->prepare(
			"SELECT match_id FROM $matches_table
			WHERE user1_id = %d AND user2_id = %d AND status = 'active'",
			$user1_id,
			$user2_id
		) );

		if ( $existing_match ) {
			return $existing_match;
		}

		// Create new match.
		$result = $wpdb->insert(
			$matches_table,
			array(
				'user1_id'      => $user1_id,
				'user2_id'      => $user2_id,
				'matched_at'    => current_time( 'mysql' ),
				'status'        => 'active',
				'last_activity' => current_time( 'mysql' ),
				'created_at'    => current_time( 'mysql' ),
				'updated_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $result ) {
			$match_id = $wpdb->insert_id;

			// Update analytics for both users.
			self::update_daily_analytics( $user1_id, 'match', 'created' );
			self::update_daily_analytics( $user2_id, 'match', 'created' );

			// Trigger match notification hook.
			do_action( 'wpmatch_new_match_created', $match_id, $user1_id, $user2_id );

			return $match_id;
		}

		return false;
	}

	/**
	 * Undo the last swipe for a user.
	 *
	 * @param int $user_id User ID.
	 * @return bool Success status.
	 */
	public static function undo_last_swipe( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}

		$table_name = $wpdb->prefix . 'wpmatch_swipes';

		// Get the last active swipe.
		$last_swipe = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table_name
			WHERE user_id = %d AND is_undo = 0
			ORDER BY created_at DESC LIMIT 1",
			$user_id
		) );

		if ( ! $last_swipe ) {
			return false;
		}

		// Mark swipe as undone.
		$result = $wpdb->update(
			$table_name,
			array( 'is_undo' => 1 ),
			array( 'swipe_id' => $last_swipe->swipe_id ),
			array( '%d' ),
			array( '%d' )
		);

		if ( $result ) {
			// If this was a like that created a match, remove the match.
			if ( in_array( $last_swipe->swipe_type, array( 'like', 'super_like' ), true ) ) {
				self::remove_match_if_exists( $user_id, $last_swipe->target_user_id );
			}

			// Trigger undo hook.
			do_action( 'wpmatch_swipe_undone', $last_swipe->swipe_id, $user_id, $last_swipe->target_user_id );

			return true;
		}

		return false;
	}

	/**
	 * Remove match if it exists between two users.
	 *
	 * @param int $user1_id First user ID.
	 * @param int $user2_id Second user ID.
	 * @return bool Success status.
	 */
	private static function remove_match_if_exists( $user1_id, $user2_id ) {
		global $wpdb;

		$user1_id = min( $user1_id, $user2_id );
		$user2_id = max( $user1_id, $user2_id );

		$table_name = $wpdb->prefix . 'wpmatch_matches';

		// Check if both users still have active likes for each other.
		$swipes_table = $wpdb->prefix . 'wpmatch_swipes';
		$user1_likes_user2 = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $swipes_table
			WHERE user_id = %d AND target_user_id = %d
			AND swipe_type IN ('like', 'super_like') AND is_undo = 0",
			$user1_id,
			$user2_id
		) );

		$user2_likes_user1 = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $swipes_table
			WHERE user_id = %d AND target_user_id = %d
			AND swipe_type IN ('like', 'super_like') AND is_undo = 0",
			$user2_id,
			$user1_id
		) );

		// If either user no longer has an active like, remove the match.
		if ( ! $user1_likes_user2 || ! $user2_likes_user1 ) {
			return $wpdb->update(
				$table_name,
				array( 'status' => 'unmatched' ),
				array(
					'user1_id' => $user1_id,
					'user2_id' => $user2_id,
				),
				array( '%s' ),
				array( '%d', '%d' )
			);
		}

		return false;
	}

	/**
	 * Update daily analytics.
	 *
	 * @param int    $user_id User ID.
	 * @param string $action_type Action type.
	 * @param string $direction Direction (given/received/created).
	 */
	private static function update_daily_analytics( $user_id, $action_type, $direction ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_swipe_analytics';
		$today = current_time( 'Y-m-d' );

		// Get or create today's analytics record.
		$analytics = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE user_id = %d AND date_recorded = %s",
			$user_id,
			$today
		) );

		$field_map = array(
			'like_given'       => 'likes_given',
			'like_received'    => 'likes_received',
			'pass_given'       => 'passes_given',
			'pass_received'    => 'passes_received',
			'super_like_given' => 'super_likes_given',
			'super_like_received' => 'super_likes_received',
			'match_created'    => 'matches_created',
		);

		$field_key = $action_type . '_' . $direction;
		$db_field = isset( $field_map[ $field_key ] ) ? $field_map[ $field_key ] : null;

		if ( ! $db_field ) {
			return;
		}

		if ( $analytics ) {
			// Update existing record.
			$wpdb->query( $wpdb->prepare(
				"UPDATE $table_name SET $db_field = $db_field + 1,
				total_swipes = total_swipes + 1,
				updated_at = NOW()
				WHERE analytics_id = %d",
				$analytics->analytics_id
			) );
		} else {
			// Create new record.
			$data = array(
				'date_recorded' => $today,
				'user_id'       => $user_id,
				'total_swipes'  => 1,
				$db_field       => 1,
				'created_at'    => current_time( 'mysql' ),
				'updated_at'    => current_time( 'mysql' ),
			);

			$wpdb->insert( $table_name, $data );
		}
	}

	/**
	 * Drop all swipe matching tables.
	 * Used for cleanup during uninstall.
	 */
	public static function drop_tables() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'wpmatch_swipe_analytics',
			$wpdb->prefix . 'wpmatch_match_queue',
			$wpdb->prefix . 'wpmatch_matches',
			$wpdb->prefix . 'wpmatch_swipes',
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS $table" );
		}

		delete_option( 'wpmatch_swipe_db_version' );
	}

	/**
	 * Get user's swipe history.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @param int $limit Number of swipes to retrieve.
	 * @param int $offset Offset for pagination.
	 * @return array|null Array of swipe records or null on failure.
	 */
	public static function get_user_swipes( $user_id, $limit = 50, $offset = 0 ) {
		global $wpdb;

		$user_id = absint( $user_id );
		$limit = absint( $limit );
		$offset = absint( $offset );

		if ( ! $user_id ) {
			return null;
		}

		$table_name = $wpdb->prefix . 'wpmatch_swipes';

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table_name}
			WHERE user_id = %d AND is_undo = 0
			ORDER BY created_at DESC
			LIMIT %d OFFSET %d",
			$user_id,
			$limit,
			$offset
		) );

		return $results;
	}

	/**
	 * Get matches for a user.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @param string $status Match status (active, unmatched).
	 * @return array|null Array of match records or null on failure.
	 */
	public static function get_matches_for_user( $user_id, $status = 'active' ) {
		global $wpdb;

		$user_id = absint( $user_id );
		$status = sanitize_text_field( $status );

		if ( ! $user_id ) {
			return null;
		}

		$table_name = $wpdb->prefix . 'wpmatch_matches';

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table_name}
			WHERE (user1_id = %d OR user2_id = %d) AND status = %s
			ORDER BY matched_at DESC",
			$user_id,
			$user_id,
			$status
		) );

		return $results;
	}

	/**
	 * Build match queue for a user.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @param int $queue_size Size of queue to build.
	 * @return int Number of queue entries created.
	 */
	public static function build_match_queue( $user_id, $queue_size = 50 ) {
		global $wpdb;

		$user_id = absint( $user_id );
		$queue_size = absint( $queue_size );

		if ( ! $user_id ) {
			return 0;
		}

		$queue_table = $wpdb->prefix . 'wpmatch_match_queue';
		$swipes_table = $wpdb->prefix . 'wpmatch_swipes';
		$profiles_table = $wpdb->prefix . 'wpmatch_user_profiles';
		$preferences_table = $wpdb->prefix . 'wpmatch_user_preferences';

		// Clear existing queue for user.
		$wpdb->delete( $queue_table, array( 'user_id' => $user_id ), array( '%d' ) );

		// Get user preferences.
		$user_prefs = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$preferences_table} WHERE user_id = %d",
			$user_id
		) );

		if ( ! $user_prefs ) {
			return 0;
		}

		// Get potential matches based on preferences and excluding already swiped users.
		$potential_matches = $wpdb->get_results( $wpdb->prepare(
			"SELECT p.user_id, p.age, p.gender, p.latitude, p.longitude
			FROM {$profiles_table} p
			WHERE p.user_id != %d
			AND p.age BETWEEN %d AND %d
			AND (p.gender = %s OR %s = 'any')
			AND p.user_id NOT IN (
				SELECT target_user_id FROM {$swipes_table}
				WHERE user_id = %d AND is_undo = 0
			)
			ORDER BY p.last_active DESC
			LIMIT %d",
			$user_id,
			$user_prefs->min_age,
			$user_prefs->max_age,
			$user_prefs->preferred_gender,
			$user_prefs->preferred_gender,
			$user_id,
			$queue_size
		) );

		$queue_count = 0;

		foreach ( $potential_matches as $match ) {
			// Calculate compatibility score (simplified).
			$compatibility_score = self::calculate_compatibility_score( $user_id, $match->user_id );

			// Insert into queue.
			$result = $wpdb->insert(
				$queue_table,
				array(
					'user_id' => $user_id,
					'potential_match_id' => $match->user_id,
					'compatibility_score' => $compatibility_score,
					'last_shown' => null,
					'priority' => 0,
					'created_at' => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%f', '%s', '%d', '%s' )
			);

			if ( $result ) {
				$queue_count++;
			}
		}

		return $queue_count;
	}

	/**
	 * Get swipe analytics for a user.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @param string $period Time period (day, week, month, all).
	 * @return array|null Analytics data or null on failure.
	 */
	public static function get_swipe_analytics( $user_id, $period = 'all' ) {
		global $wpdb;

		$user_id = absint( $user_id );
		$period = sanitize_text_field( $period );

		if ( ! $user_id ) {
			return null;
		}

		$analytics_table = $wpdb->prefix . 'wpmatch_swipe_analytics';

		// Build date condition based on period.
		$date_condition = '';
		switch ( $period ) {
			case 'day':
				$date_condition = "AND DATE(created_at) = CURDATE()";
				break;
			case 'week':
				$date_condition = "AND YEARWEEK(created_at) = YEARWEEK(NOW())";
				break;
			case 'month':
				$date_condition = "AND YEAR(created_at) = YEAR(NOW()) AND MONTH(created_at) = MONTH(NOW())";
				break;
			default:
				$date_condition = '';
		}

		$analytics = $wpdb->get_row( $wpdb->prepare(
			"SELECT
				user_id,
				SUM(total_swipes) as total_swipes,
				SUM(likes_given) as likes_given,
				SUM(passes_given) as passes_given,
				SUM(super_likes_given) as super_likes_given,
				SUM(likes_received) as likes_received,
				SUM(passes_received) as passes_received,
				SUM(super_likes_received) as super_likes_received,
				SUM(matches_created) as matches_created,
				MAX(updated_at) as last_updated
			FROM {$analytics_table}
			WHERE user_id = %d {$date_condition}
			GROUP BY user_id",
			$user_id
		) );

		if ( ! $analytics ) {
			// Return empty analytics if no data found.
			return array(
				'user_id' => $user_id,
				'total_swipes' => 0,
				'likes_given' => 0,
				'passes_given' => 0,
				'super_likes_given' => 0,
				'likes_received' => 0,
				'passes_received' => 0,
				'super_likes_received' => 0,
				'matches_created' => 0,
				'last_updated' => null,
			);
		}

		return (array) $analytics;
	}

	/**
	 * Calculate compatibility score between two users (simplified version).
	 *
	 * @since 1.0.0
	 * @param int $user1_id First user ID.
	 * @param int $user2_id Second user ID.
	 * @return float Compatibility score between 0.0 and 1.0.
	 */
	private static function calculate_compatibility_score( $user1_id, $user2_id ) {
		global $wpdb;

		$user1_id = absint( $user1_id );
		$user2_id = absint( $user2_id );

		// Get user profiles.
		$profiles_table = $wpdb->prefix . 'wpmatch_user_profiles';
		$interests_table = $wpdb->prefix . 'wpmatch_user_interests';

		$user1_profile = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$profiles_table} WHERE user_id = %d",
			$user1_id
		) );

		$user2_profile = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$profiles_table} WHERE user_id = %d",
			$user2_id
		) );

		if ( ! $user1_profile || ! $user2_profile ) {
			return 0.5; // Default compatibility.
		}

		$score = 0.0;
		$factors = 0;

		// Age compatibility (closer ages = higher score).
		if ( $user1_profile->age && $user2_profile->age ) {
			$age_diff = abs( $user1_profile->age - $user2_profile->age );
			$age_score = max( 0, 1 - ( $age_diff / 20 ) );
			$score += $age_score;
			$factors++;
		}

		// Location proximity (if both have coordinates).
		if ( $user1_profile->latitude && $user1_profile->longitude &&
			 $user2_profile->latitude && $user2_profile->longitude ) {
			$distance = self::calculate_distance(
				$user1_profile->latitude, $user1_profile->longitude,
				$user2_profile->latitude, $user2_profile->longitude
			);
			$distance_score = max( 0, 1 - ( $distance / 100 ) ); // 100 miles max distance.
			$score += $distance_score;
			$factors++;
		}

		// Common interests.
		$common_interests = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$interests_table} i1
			INNER JOIN {$interests_table} i2 ON i1.interest_name = i2.interest_name
			WHERE i1.user_id = %d AND i2.user_id = %d",
			$user1_id,
			$user2_id
		) );

		if ( $common_interests > 0 ) {
			$interest_score = min( 1.0, $common_interests / 5 ); // Max score at 5 common interests.
			$score += $interest_score;
			$factors++;
		}

		// Return average score or default.
		return $factors > 0 ? $score / $factors : 0.5;
	}

	/**
	 * Calculate distance between two coordinates.
	 *
	 * @since 1.0.0
	 * @param float $lat1 Latitude 1.
	 * @param float $lon1 Longitude 1.
	 * @param float $lat2 Latitude 2.
	 * @param float $lon2 Longitude 2.
	 * @return float Distance in miles.
	 */
	private static function calculate_distance( $lat1, $lon1, $lat2, $lon2 ) {
		$earth_radius = 3959; // Miles.

		$lat1_rad = deg2rad( $lat1 );
		$lon1_rad = deg2rad( $lon1 );
		$lat2_rad = deg2rad( $lat2 );
		$lon2_rad = deg2rad( $lon2 );

		$delta_lat = $lat2_rad - $lat1_rad;
		$delta_lon = $lon2_rad - $lon1_rad;

		$a = sin( $delta_lat / 2 ) * sin( $delta_lat / 2 ) +
			 cos( $lat1_rad ) * cos( $lat2_rad ) *
			 sin( $delta_lon / 2 ) * sin( $delta_lon / 2 );

		$c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

		return $earth_radius * $c;
	}
}