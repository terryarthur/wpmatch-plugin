<?php
/**
 * WPMatch Performance Optimizer
 *
 * Handles database query optimization, caching, and performance improvements.
 *
 * @package WPMatch
 * @subpackage Performance
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPMatch Performance Optimizer class.
 *
 * @since 1.0.0
 */
class WPMatch_Performance_Optimizer {

	/**
	 * Cache group for WPMatch data.
	 */
	const CACHE_GROUP = 'wpmatch';

	/**
	 * Cache durations.
	 */
	const CACHE_DURATION_SHORT  = 300;   // 5 minutes
	const CACHE_DURATION_MEDIUM = 3600;  // 1 hour
	const CACHE_DURATION_LONG   = 86400; // 24 hours

	/**
	 * Initialize performance optimizations.
	 */
	public static function init() {
		// Add query optimization hooks.
		add_action( 'init', array( __CLASS__, 'setup_optimizations' ) );

		// Add cache invalidation hooks.
		add_action( 'wpmatch_profile_updated', array( __CLASS__, 'invalidate_profile_cache' ) );
		add_action( 'wpmatch_match_created', array( __CLASS__, 'invalidate_match_cache' ) );
		add_action( 'wpmatch_swipe_recorded', array( __CLASS__, 'invalidate_swipe_cache' ) );

		// Add database optimization hooks.
		add_action( 'wp_scheduled_delete', array( __CLASS__, 'cleanup_old_data' ) );
	}

	/**
	 * Setup database optimizations.
	 */
	public static function setup_optimizations() {
		// Ensure database indexes exist.
		self::ensure_database_indexes();

		// Schedule cleanup tasks.
		if ( ! wp_next_scheduled( 'wpmatch_cleanup_old_data' ) ) {
			wp_schedule_event( time(), 'daily', 'wpmatch_cleanup_old_data' );
		}
	}

	/**
	 * Ensure critical database indexes exist for performance.
	 */
	public static function ensure_database_indexes() {
		global $wpdb;

		$indexes = array(
			// User profiles table indexes.
			array(
				'table' => $wpdb->prefix . 'wpmatch_user_profiles',
				'index' => 'idx_age_gender_location',
				'sql'   => 'CREATE INDEX idx_age_gender_location ON %s (age, gender, latitude, longitude)',
			),
			array(
				'table' => $wpdb->prefix . 'wpmatch_user_profiles',
				'index' => 'idx_last_active_profile',
				'sql'   => 'CREATE INDEX idx_last_active_profile ON %s (last_active, profile_completion)',
			),

			// Swipes table indexes.
			array(
				'table' => $wpdb->prefix . 'wpmatch_swipes',
				'index' => 'idx_user_target_composite',
				'sql'   => 'CREATE INDEX idx_user_target_composite ON %s (user_id, target_user_id, swipe_type)',
			),
			array(
				'table' => $wpdb->prefix . 'wpmatch_swipes',
				'index' => 'idx_target_user_like',
				'sql'   => 'CREATE INDEX idx_target_user_like ON %s (target_user_id, swipe_type, created_at)',
			),

			// Matches table indexes.
			array(
				'table' => $wpdb->prefix . 'wpmatch_matches',
				'index' => 'idx_user_status_created',
				'sql'   => 'CREATE INDEX idx_user_status_created ON %s (user1_id, user2_id, status, created_at)',
			),

			// Messages table indexes.
			array(
				'table' => $wpdb->prefix . 'wpmatch_messages',
				'index' => 'idx_conversation_created',
				'sql'   => 'CREATE INDEX idx_conversation_created ON %s (conversation_id, created_at)',
			),
			array(
				'table' => $wpdb->prefix . 'wpmatch_messages',
				'index' => 'idx_recipient_unread',
				'sql'   => 'CREATE INDEX idx_recipient_unread ON %s (recipient_id, is_read, created_at)',
			),

			// User queue table indexes.
			array(
				'table' => $wpdb->prefix . 'wpmatch_user_queue',
				'index' => 'idx_user_priority_active',
				'sql'   => 'CREATE INDEX idx_user_priority_active ON %s (user_id, priority, is_active)',
			),
		);

		foreach ( $indexes as $index_data ) {
			self::create_index_if_not_exists( $index_data );
		}
	}

	/**
	 * Create database index if it doesn't exist.
	 *
	 * @param array $index_data Index configuration.
	 */
	private static function create_index_if_not_exists( $index_data ) {
		global $wpdb;

		$table_name = $index_data['table'];
		$index_name = $index_data['index'];
		$create_sql = $index_data['sql'];

		// Check if index exists.
		$index_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM INFORMATION_SCHEMA.STATISTICS
				WHERE table_schema = DATABASE()
				AND table_name = %s
				AND index_name = %s",
				$table_name,
				$index_name
			)
		);

		if ( ! $index_exists ) {
			// Create the index.
			$wpdb->query( sprintf( $create_sql, $table_name ) );
		}
	}

	/**
	 * Get optimized matches for user with caching.
	 *
	 * @param int $user_id User ID.
	 * @param array $args Query arguments.
	 * @return array Matches data.
	 */
	public static function get_cached_matches( $user_id, $args = array() ) {
		$cache_key = 'matches_' . $user_id . '_' . md5( serialize( $args ) );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		// Get fresh data.
		$matches = self::get_optimized_matches( $user_id, $args );

		// Cache for 5 minutes.
		wp_cache_set( $cache_key, $matches, self::CACHE_GROUP, self::CACHE_DURATION_SHORT );

		return $matches;
	}

	/**
	 * Get optimized matches using efficient queries.
	 *
	 * @param int $user_id User ID.
	 * @param array $args Query arguments.
	 * @return array Matches data.
	 */
	private static function get_optimized_matches( $user_id, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'limit'   => 20,
			'offset'  => 0,
			'min_age' => 18,
			'max_age' => 99,
			'distance' => 50,
		);

		$args = wp_parse_args( $args, $defaults );

		// Get user preferences first.
		$user_prefs = self::get_cached_user_preferences( $user_id );

		// Build optimized query using multiple indexes.
		$query = "
			SELECT
				p.user_id,
				p.age,
				p.gender,
				p.location,
				p.latitude,
				p.longitude,
				p.about_me,
				u.display_name,
				(
					6371 * acos(
						cos(radians(%f)) * cos(radians(p.latitude)) *
						cos(radians(p.longitude) - radians(%f)) +
						sin(radians(%f)) * sin(radians(p.latitude))
					)
				) AS distance_km
			FROM {$wpdb->prefix}wpmatch_user_profiles p
			INNER JOIN {$wpdb->users} u ON p.user_id = u.ID
			WHERE p.user_id != %d
			AND p.age BETWEEN %d AND %d
			AND p.profile_completion >= 70
			AND p.last_active > DATE_SUB(NOW(), INTERVAL 30 DAY)
			AND NOT EXISTS (
				SELECT 1 FROM {$wpdb->prefix}wpmatch_swipes s
				WHERE s.user_id = %d AND s.target_user_id = p.user_id
			)
			AND NOT EXISTS (
				SELECT 1 FROM {$wpdb->prefix}wpmatch_matches m
				WHERE (m.user1_id = %d AND m.user2_id = p.user_id)
				OR (m.user1_id = p.user_id AND m.user2_id = %d)
			)
		";

		$query_params = array(
			$user_prefs->latitude ?? 0,
			$user_prefs->longitude ?? 0,
			$user_prefs->latitude ?? 0,
			$user_id,
			$args['min_age'],
			$args['max_age'],
			$user_id,
			$user_id,
			$user_id,
		);

		// Add gender filter if specified.
		if ( ! empty( $user_prefs->preferred_gender ) && 'any' !== $user_prefs->preferred_gender ) {
			$query .= ' AND p.gender = %s';
			$query_params[] = $user_prefs->preferred_gender;
		}

		// Add distance filter in HAVING clause for efficiency.
		if ( $user_prefs->latitude && $user_prefs->longitude ) {
			$query .= ' HAVING distance_km <= %d';
			$query_params[] = $args['distance'];
		}

		// Order by match score (simplified for performance).
		$query .= ' ORDER BY
			CASE
				WHEN p.last_active > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 3
				WHEN p.last_active > DATE_SUB(NOW(), INTERVAL 14 DAY) THEN 2
				ELSE 1
			END DESC,
			distance_km ASC,
			p.profile_completion DESC
			LIMIT %d OFFSET %d';

		$query_params[] = $args['limit'];
		$query_params[] = $args['offset'];

		return $wpdb->get_results( $wpdb->prepare( $query, $query_params ) );
	}

	/**
	 * Get cached user preferences.
	 *
	 * @param int $user_id User ID.
	 * @return object User preferences.
	 */
	public static function get_cached_user_preferences( $user_id ) {
		$cache_key = 'user_prefs_' . $user_id;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		$prefs = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpmatch_user_preferences WHERE user_id = %d",
				$user_id
			)
		);

		// Cache for 1 hour.
		wp_cache_set( $cache_key, $prefs, self::CACHE_GROUP, self::CACHE_DURATION_MEDIUM );

		return $prefs;
	}

	/**
	 * Get cached user profile with media.
	 *
	 * @param int $user_id User ID.
	 * @return array Profile data with media.
	 */
	public static function get_cached_user_profile( $user_id ) {
		$cache_key = 'profile_' . $user_id;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		// Get profile and media in single query.
		$query = "
			SELECT
				p.*,
				u.display_name,
				u.user_email,
				GROUP_CONCAT(
					CONCAT(m.media_id, ':', m.file_path, ':', m.media_type, ':', m.is_primary)
					ORDER BY m.display_order
				) as media_data
			FROM {$wpdb->prefix}wpmatch_user_profiles p
			INNER JOIN {$wpdb->users} u ON p.user_id = u.ID
			LEFT JOIN {$wpdb->prefix}wpmatch_user_media m ON p.user_id = m.user_id
			WHERE p.user_id = %d
			GROUP BY p.user_id
		";

		$profile = $wpdb->get_row( $wpdb->prepare( $query, $user_id ) );

		if ( $profile && $profile->media_data ) {
			// Parse media data.
			$media_items = explode( ',', $profile->media_data );
			$profile->media = array();

			foreach ( $media_items as $media_item ) {
				$parts = explode( ':', $media_item );
				if ( count( $parts ) === 4 ) {
					$profile->media[] = array(
						'id'         => $parts[0],
						'file_path'  => $parts[1],
						'media_type' => $parts[2],
						'is_primary' => (bool) $parts[3],
					);
				}
			}

			unset( $profile->media_data );
		}

		// Cache for 30 minutes.
		wp_cache_set( $cache_key, $profile, self::CACHE_GROUP, 1800 );

		return $profile;
	}

	/**
	 * Get optimized message threads.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit Limit.
	 * @return array Message threads.
	 */
	public static function get_cached_message_threads( $user_id, $limit = 20 ) {
		$cache_key = 'msg_threads_' . $user_id . '_' . $limit;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		// Get conversations with last message in single query.
		$query = "
			SELECT
				c.conversation_id,
				c.user1_id,
				c.user2_id,
				c.last_message_at,
				m.message_content as last_message,
				m.sender_id as last_sender_id,
				(
					SELECT COUNT(*)
					FROM {$wpdb->prefix}wpmatch_messages m2
					WHERE m2.conversation_id = c.conversation_id
					AND m2.recipient_id = %d
					AND m2.is_read = 0
				) as unread_count,
				CASE
					WHEN c.user1_id = %d THEN u2.display_name
					ELSE u1.display_name
				END as other_user_name,
				CASE
					WHEN c.user1_id = %d THEN c.user2_id
					ELSE c.user1_id
				END as other_user_id
			FROM {$wpdb->prefix}wpmatch_conversations c
			LEFT JOIN {$wpdb->prefix}wpmatch_messages m ON c.last_message_id = m.message_id
			LEFT JOIN {$wpdb->users} u1 ON c.user1_id = u1.ID
			LEFT JOIN {$wpdb->users} u2 ON c.user2_id = u2.ID
			WHERE (c.user1_id = %d OR c.user2_id = %d)
			AND c.user1_deleted = 0 AND c.user2_deleted = 0
			ORDER BY c.last_message_at DESC
			LIMIT %d
		";

		$threads = $wpdb->get_results(
			$wpdb->prepare(
				$query,
				$user_id,
				$user_id,
				$user_id,
				$user_id,
				$user_id,
				$limit
			)
		);

		// Cache for 2 minutes (frequently updated).
		wp_cache_set( $cache_key, $threads, self::CACHE_GROUP, 120 );

		return $threads;
	}

	/**
	 * Batch process swipes for improved performance.
	 *
	 * @param array $swipes Array of swipe data.
	 * @return array Results.
	 */
	public static function batch_process_swipes( $swipes ) {
		global $wpdb;

		$results = array();
		$values  = array();

		// Prepare batch insert.
		foreach ( $swipes as $swipe ) {
			$values[] = $wpdb->prepare(
				'(%d, %d, %s, NOW(), %s)',
				$swipe['user_id'],
				$swipe['target_user_id'],
				$swipe['swipe_type'],
				$swipe['ip_address']
			);
		}

		if ( ! empty( $values ) ) {
			// Batch insert swipes.
			$query = "INSERT INTO {$wpdb->prefix}wpmatch_swipes
				(user_id, target_user_id, swipe_type, created_at, ip_address)
				VALUES " . implode( ',', $values );

			$wpdb->query( $query );

			// Check for matches in batch.
			$results = self::batch_check_matches( $swipes );
		}

		return $results;
	}

	/**
	 * Batch check for matches.
	 *
	 * @param array $swipes Array of swipe data.
	 * @return array Match results.
	 */
	private static function batch_check_matches( $swipes ) {
		global $wpdb;

		$like_swipes = array_filter( $swipes, function( $swipe ) {
			return in_array( $swipe['swipe_type'], array( 'like', 'super_like' ), true );
		});

		if ( empty( $like_swipes ) ) {
			return array();
		}

		$user_pairs = array();
		foreach ( $like_swipes as $swipe ) {
			$user_pairs[] = sprintf( '(%d, %d)', $swipe['target_user_id'], $swipe['user_id'] );
		}

		// Check for mutual likes in single query.
		$query = "
			SELECT s.user_id, s.target_user_id
			FROM {$wpdb->prefix}wpmatch_swipes s
			WHERE (s.user_id, s.target_user_id) IN (" . implode( ',', $user_pairs ) . ")
			AND s.swipe_type IN ('like', 'super_like')
		";

		$mutual_likes = $wpdb->get_results( $query );

		// Create matches for mutual likes.
		$matches = array();
		foreach ( $mutual_likes as $mutual ) {
			$match_id = self::create_match( $mutual->user_id, $mutual->target_user_id );
			if ( $match_id ) {
				$matches[] = array(
					'match_id' => $match_id,
					'user1_id' => $mutual->user_id,
					'user2_id' => $mutual->target_user_id,
				);
			}
		}

		return $matches;
	}

	/**
	 * Create a match between two users.
	 *
	 * @param int $user1_id First user ID.
	 * @param int $user2_id Second user ID.
	 * @return int|false Match ID or false on failure.
	 */
	private static function create_match( $user1_id, $user2_id ) {
		global $wpdb;

		// Ensure consistent ordering.
		if ( $user1_id > $user2_id ) {
			$temp     = $user1_id;
			$user1_id = $user2_id;
			$user2_id = $temp;
		}

		$result = $wpdb->insert(
			$wpdb->prefix . 'wpmatch_matches',
			array(
				'user1_id'   => $user1_id,
				'user2_id'   => $user2_id,
				'status'     => 'active',
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Cache invalidation methods.
	 */
	public static function invalidate_profile_cache( $user_id ) {
		wp_cache_delete( 'profile_' . $user_id, self::CACHE_GROUP );
		wp_cache_delete( 'user_prefs_' . $user_id, self::CACHE_GROUP );
	}

	public static function invalidate_match_cache( $user1_id, $user2_id ) {
		// Invalidate matches cache for both users.
		$pattern = 'matches_' . $user1_id . '_*';
		wp_cache_flush_group( self::CACHE_GROUP );

		$pattern = 'matches_' . $user2_id . '_*';
		wp_cache_flush_group( self::CACHE_GROUP );
	}

	public static function invalidate_swipe_cache( $user_id ) {
		$pattern = 'matches_' . $user_id . '_*';
		wp_cache_flush_group( self::CACHE_GROUP );
	}

	/**
	 * Clean up old data for performance.
	 */
	public static function cleanup_old_data() {
		global $wpdb;

		// Delete old swipe records (older than 6 months).
		$wpdb->query(
			"DELETE FROM {$wpdb->prefix}wpmatch_swipes
			WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH)"
		);

		// Delete old message data (soft-deleted messages older than 30 days).
		$wpdb->query(
			"DELETE FROM {$wpdb->prefix}wpmatch_messages
			WHERE (is_deleted_sender = 1 AND is_deleted_recipient = 1)
			AND updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
		);

		// Delete expired verification tokens.
		$wpdb->query(
			"DELETE FROM {$wpdb->prefix}wpmatch_user_verifications
			WHERE expires_at < NOW()
			AND verification_status != 'verified'"
		);

		// Optimize tables.
		$tables = array(
			$wpdb->prefix . 'wpmatch_swipes',
			$wpdb->prefix . 'wpmatch_messages',
			$wpdb->prefix . 'wpmatch_user_verifications',
			$wpdb->prefix . 'wpmatch_user_queue',
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "OPTIMIZE TABLE {$table}" );
		}
	}

	/**
	 * Get cache statistics for debugging.
	 *
	 * @return array Cache statistics.
	 */
	public static function get_cache_stats() {
		global $wp_object_cache;

		$stats = array(
			'cache_hits'   => 0,
			'cache_misses' => 0,
			'cache_ratio'  => 0,
		);

		if ( isset( $wp_object_cache->cache_hits ) ) {
			$stats['cache_hits']   = $wp_object_cache->cache_hits;
			$stats['cache_misses'] = $wp_object_cache->cache_misses;

			$total = $stats['cache_hits'] + $stats['cache_misses'];
			if ( $total > 0 ) {
				$stats['cache_ratio'] = round( ( $stats['cache_hits'] / $total ) * 100, 2 );
			}
		}

		return $stats;
	}
}

// Initialize performance optimizer.
WPMatch_Performance_Optimizer::init();