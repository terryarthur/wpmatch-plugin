<?php
/**
 * WPMatch Core Matching Algorithm
 *
 * Handles all matching logic, filtering, and queue generation.
 *
 * @package WPMatch
 * @subpackage Matching
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPMatch Matching Algorithm class.
 *
 * @since 1.0.0
 */
class WPMatch_Matching_Algorithm {

	/**
	 * Rate limit constants.
	 */
	const SWIPES_PER_HOUR       = 100;
	const SUPER_LIKES_PER_DAY   = 5;
	const QUEUE_MIN_SIZE        = 10;
	const QUEUE_MAX_SIZE        = 50;
	const MATCH_INACTIVITY_DAYS = 14;

	/**
	 * Process a swipe action.
	 *
	 * @since 1.0.0
	 * @param int    $user_id User ID making the swipe.
	 * @param int    $target_user_id Target user ID.
	 * @param string $swipe_type Type of swipe (like, pass, super_like).
	 * @return array Result array with success status and data.
	 */
	public static function process_swipe_action( $user_id, $target_user_id, $swipe_type ) {
		$user_id        = absint( $user_id );
		$target_user_id = absint( $target_user_id );
		$swipe_type     = sanitize_text_field( $swipe_type );

		// Validate inputs.
		if ( ! $user_id || ! $target_user_id || $user_id === $target_user_id ) {
			return array(
				'success' => false,
				'error'   => 'invalid_parameters',
			);
		}

		// Check rate limits.
		$rate_check = self::check_rate_limits( $user_id, $swipe_type );
		if ( ! $rate_check['allowed'] ) {
			return array(
				'success' => false,
				'error'   => $rate_check['error'],
				'message' => $rate_check['message'],
			);
		}

		// Check for existing swipe.
		if ( self::has_already_swiped( $user_id, $target_user_id ) ) {
			return array(
				'success' => false,
				'error'   => 'already_swiped',
				'message' => __( 'You have already swiped on this user.', 'wpmatch' ),
			);
		}

		// Record the swipe.
		$swipe_id = WPMatch_Swipe_DB::record_swipe(
			$user_id,
			$target_user_id,
			$swipe_type,
			self::get_user_ip()
		);

		if ( ! $swipe_id ) {
			return array(
				'success' => false,
				'error'   => 'swipe_failed',
				'message' => __( 'Failed to record swipe. Please try again.', 'wpmatch' ),
			);
		}

		// Check for mutual match if it was a like.
		$is_match = false;
		$match_id = null;

		if ( in_array( $swipe_type, array( 'like', 'super_like' ), true ) ) {
			$match_check = self::check_for_mutual_match( $user_id, $target_user_id );
			$is_match    = $match_check['is_match'];
			$match_id    = $match_check['match_id'];

			// Send notifications.
			if ( $is_match ) {
				self::send_match_notification( $user_id, $target_user_id, $match_id );
			} elseif ( 'super_like' === $swipe_type ) {
				self::send_super_like_notification( $user_id, $target_user_id );
			}
		}

		// Remove from queue.
		self::remove_from_queue( $user_id, $target_user_id );

		// Trigger action hook.
		do_action( 'wpmatch_swipe_processed', $swipe_id, $user_id, $target_user_id, $swipe_type, $is_match );

		return array(
			'success'    => true,
			'swipe_id'   => $swipe_id,
			'is_match'   => $is_match,
			'match_id'   => $match_id,
			'swipe_type' => $swipe_type,
		);
	}

	/**
	 * Build or refresh user's match queue.
	 *
	 * @since 1.0.0
	 * @param int  $user_id User ID.
	 * @param bool $force_refresh Force queue rebuild.
	 * @return array Queue information.
	 */
	public static function build_user_queue( $user_id, $force_refresh = false ) {
		global $wpdb;

		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return array();
		}

		$queue_table = $wpdb->prefix . 'wpmatch_match_queue';

		// Check existing queue size.
		if ( ! $force_refresh ) {
			$existing_count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$queue_table} WHERE user_id = %d",
					$user_id
				)
			);

			if ( $existing_count >= self::QUEUE_MIN_SIZE ) {
				return self::get_active_queue( $user_id );
			}
		}

		// Clear old queue if refreshing.
		if ( $force_refresh ) {
			$wpdb->delete( $queue_table, array( 'user_id' => $user_id ), array( '%d' ) );
		}

		// Get user preferences.
		$preferences = self::get_user_preferences( $user_id );
		if ( ! $preferences ) {
			return array();
		}

		// Get potential matches.
		$potential_matches = self::get_filtered_potential_matches( $user_id, $preferences );

		if ( empty( $potential_matches ) ) {
			return array();
		}

		// Calculate compatibility scores and build queue.
		$queue_entries = array();
		foreach ( $potential_matches as $match ) {
			$compatibility_score = self::calculate_comprehensive_compatibility(
				$user_id,
				$match->user_id
			);

			// Check if user super liked us (priority).
			$priority = self::check_super_like_priority( $match->user_id, $user_id );

			$queue_entries[] = array(
				'user_id'             => $user_id,
				'potential_match_id'  => $match->user_id,
				'compatibility_score' => $compatibility_score,
				'priority'            => $priority,
				'last_shown'          => null,
				'created_at'          => current_time( 'mysql' ),
			);
		}

		// Apply advanced matching enhancements to queue entries.
		$queue_entries = apply_filters( 'wpmatch_user_matches', $queue_entries, $user_id );

		// Sort by priority and compatibility score.
		usort(
			$queue_entries,
			function ( $a, $b ) {
				if ( $a['priority'] !== $b['priority'] ) {
					return $b['priority'] - $a['priority'];
				}
				return $b['compatibility_score'] <=> $a['compatibility_score'];
			}
		);

		// Limit queue size.
		$queue_entries = array_slice( $queue_entries, 0, self::QUEUE_MAX_SIZE );

		// Insert into database.
		foreach ( $queue_entries as $entry ) {
			$wpdb->insert( $queue_table, $entry );
		}

		return self::get_active_queue( $user_id );
	}

	/**
	 * Get filtered potential matches based on preferences.
	 *
	 * @since 1.0.0
	 * @param int    $user_id User ID.
	 * @param object $preferences User preferences.
	 * @return array Filtered matches.
	 */
	private static function get_filtered_potential_matches( $user_id, $preferences ) {
		global $wpdb;

		$profiles_table = $wpdb->prefix . 'wpmatch_user_profiles';
		$swipes_table   = $wpdb->prefix . 'wpmatch_swipes';
		$matches_table  = $wpdb->prefix . 'wpmatch_matches';

		// Build query with preference filters.
		$query = "SELECT p.*, u.user_login, u.display_name
				FROM {$profiles_table} p
				INNER JOIN {$wpdb->users} u ON p.user_id = u.ID
				WHERE p.user_id != %d";

		$query_params = array( $user_id );

		// Age filter.
		if ( $preferences->min_age && $preferences->max_age ) {
			$query         .= ' AND p.age BETWEEN %d AND %d';
			$query_params[] = $preferences->min_age;
			$query_params[] = $preferences->max_age;
		}

		// Gender filter.
		if ( $preferences->preferred_gender && 'any' !== $preferences->preferred_gender ) {
			$query         .= ' AND p.gender = %s';
			$query_params[] = $preferences->preferred_gender;
		}

		// Exclude already swiped users.
		$query         .= " AND p.user_id NOT IN (
			SELECT target_user_id FROM {$swipes_table}
			WHERE user_id = %d AND is_undo = 0
		)";
		$query_params[] = $user_id;

		// Exclude existing matches.
		$query         .= " AND p.user_id NOT IN (
			SELECT CASE
				WHEN user1_id = %d THEN user2_id
				ELSE user1_id
			END as matched_user
			FROM {$matches_table}
			WHERE (user1_id = %d OR user2_id = %d)
			AND status = 'active'
		)";
		$query_params[] = $user_id;
		$query_params[] = $user_id;
		$query_params[] = $user_id;

		// Order by last activity.
		$query .= ' ORDER BY p.last_active DESC LIMIT 200';

		$results = $wpdb->get_results( $wpdb->prepare( $query, $query_params ) );

		// Apply distance filter if needed.
		if ( $preferences->max_distance && $results ) {
			$user_profile = self::get_user_profile( $user_id );
			if ( $user_profile && $user_profile->latitude && $user_profile->longitude ) {
				$results = self::filter_by_distance(
					$results,
					$user_profile->latitude,
					$user_profile->longitude,
					$preferences->max_distance
				);
			}
		}

		return $results;
	}

	/**
	 * Filter results by distance.
	 *
	 * @since 1.0.0
	 * @param array $results User results.
	 * @param float $user_lat User latitude.
	 * @param float $user_lon User longitude.
	 * @param int   $max_distance Maximum distance in miles.
	 * @return array Filtered results.
	 */
	private static function filter_by_distance( $results, $user_lat, $user_lon, $max_distance ) {
		$filtered = array();

		foreach ( $results as $result ) {
			if ( ! $result->latitude || ! $result->longitude ) {
				continue;
			}

			$distance = self::calculate_distance(
				$user_lat,
				$user_lon,
				$result->latitude,
				$result->longitude
			);

			if ( $distance <= $max_distance ) {
				$result->distance = $distance;
				$filtered[]       = $result;
			}
		}

		// Sort by distance.
		usort(
			$filtered,
			function ( $a, $b ) {
				return $a->distance <=> $b->distance;
			}
		);

		return $filtered;
	}

	/**
	 * Calculate comprehensive compatibility score.
	 *
	 * @since 1.0.0
	 * @param int $user1_id First user ID.
	 * @param int $user2_id Second user ID.
	 * @return float Compatibility score (0.0 to 1.0).
	 */
	public static function calculate_comprehensive_compatibility( $user1_id, $user2_id ) {
		global $wpdb;

		$scores  = array();
		$weights = array();

		// Get user profiles.
		$user1_profile = self::get_user_profile( $user1_id );
		$user2_profile = self::get_user_profile( $user2_id );

		if ( ! $user1_profile || ! $user2_profile ) {
			return 0.5; // Default score.
		}

		// Age compatibility (weight: 20%).
		if ( $user1_profile->age && $user2_profile->age ) {
			$age_diff       = abs( $user1_profile->age - $user2_profile->age );
			$age_score      = max( 0, 1 - ( $age_diff / 20 ) );
			$scores['age']  = $age_score;
			$weights['age'] = 0.2;
		}

		// Location proximity (weight: 25%).
		if ( $user1_profile->latitude && $user1_profile->longitude &&
			$user2_profile->latitude && $user2_profile->longitude ) {
			$distance            = self::calculate_distance(
				$user1_profile->latitude,
				$user1_profile->longitude,
				$user2_profile->latitude,
				$user2_profile->longitude
			);
			$distance_score      = max( 0, 1 - ( $distance / 100 ) );
			$scores['distance']  = $distance_score;
			$weights['distance'] = 0.25;
		}

		// Common interests (weight: 30%).
		$interests_table  = $wpdb->prefix . 'wpmatch_user_interests';
		$common_interests = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT i1.interest_name)
			FROM {$interests_table} i1
			INNER JOIN {$interests_table} i2
			ON i1.interest_name = i2.interest_name
			WHERE i1.user_id = %d AND i2.user_id = %d",
				$user1_id,
				$user2_id
			)
		);

		if ( $common_interests > 0 ) {
			$interest_score       = min( 1.0, $common_interests / 5 );
			$scores['interests']  = $interest_score;
			$weights['interests'] = 0.3;
		}

		// Activity level compatibility (weight: 10%).
		$days_since_active1  = self::days_since_active( $user1_profile->last_active );
		$days_since_active2  = self::days_since_active( $user2_profile->last_active );
		$activity_diff       = abs( $days_since_active1 - $days_since_active2 );
		$activity_score      = max( 0, 1 - ( $activity_diff / 30 ) );
		$scores['activity']  = $activity_score;
		$weights['activity'] = 0.1;

		// Profile completion (weight: 15%).
		$completion_avg        = ( $user1_profile->profile_completion + $user2_profile->profile_completion ) / 200;
		$scores['completion']  = $completion_avg;
		$weights['completion'] = 0.15;

		// Calculate weighted average.
		if ( empty( $scores ) ) {
			return 0.5;
		}

		$total_score  = 0;
		$total_weight = 0;

		foreach ( $scores as $key => $score ) {
			$weight        = isset( $weights[ $key ] ) ? $weights[ $key ] : 0.1;
			$total_score  += $score * $weight;
			$total_weight += $weight;
		}

		$base_score = $total_weight > 0 ? $total_score / $total_weight : 0.5;

		// Apply advanced matching enhancements.
		$enhanced_score = apply_filters( 'wpmatch_compatibility_score', $base_score, $user1_id, $user2_id );

		return $enhanced_score;
	}

	/**
	 * Check and enforce rate limits.
	 *
	 * @since 1.0.0
	 * @param int    $user_id User ID.
	 * @param string $swipe_type Type of swipe.
	 * @return array Rate limit check result.
	 */
	private static function check_rate_limits( $user_id, $swipe_type ) {
		global $wpdb;

		$swipes_table = $wpdb->prefix . 'wpmatch_swipes';

		// Check hourly swipe limit.
		$hour_ago         = date( 'Y-m-d H:i:s', strtotime( '-1 hour' ) );
		$swipes_last_hour = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$swipes_table}
			WHERE user_id = %d AND created_at > %s AND is_undo = 0",
				$user_id,
				$hour_ago
			)
		);

		if ( $swipes_last_hour >= self::SWIPES_PER_HOUR ) {
			$minutes_until_reset = 60 - intval( date( 'i' ) );
			return array(
				'allowed' => false,
				'error'   => 'rate_limit_exceeded',
				'message' => sprintf(
					__( 'You have reached the limit of %1$d swipes per hour. Try again in %2$d minutes.', 'wpmatch' ),
					self::SWIPES_PER_HOUR,
					$minutes_until_reset
				),
			);
		}

		// Check daily super like limit.
		if ( 'super_like' === $swipe_type ) {
			$today_start       = date( 'Y-m-d 00:00:00' );
			$super_likes_today = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$swipes_table}
				WHERE user_id = %d AND swipe_type = 'super_like'
				AND created_at > %s AND is_undo = 0",
					$user_id,
					$today_start
				)
			);

			if ( $super_likes_today >= self::SUPER_LIKES_PER_DAY ) {
				return array(
					'allowed' => false,
					'error'   => 'super_likes_exceeded',
					'message' => sprintf(
						__( 'You have used all %d super likes for today. More will be available tomorrow.', 'wpmatch' ),
						self::SUPER_LIKES_PER_DAY
					),
				);
			}
		}

		return array( 'allowed' => true );
	}

	/**
	 * Check if user has already swiped on target.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @param int $target_user_id Target user ID.
	 * @return bool True if already swiped.
	 */
	private static function has_already_swiped( $user_id, $target_user_id ) {
		global $wpdb;

		$swipes_table = $wpdb->prefix . 'wpmatch_swipes';

		$existing_swipe = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT swipe_id FROM {$swipes_table}
			WHERE user_id = %d AND target_user_id = %d AND is_undo = 0",
				$user_id,
				$target_user_id
			)
		);

		return (bool) $existing_swipe;
	}

	/**
	 * Check for mutual match after a like.
	 *
	 * @since 1.0.0
	 * @param int $user_id User who just swiped.
	 * @param int $target_user_id Target of the swipe.
	 * @return array Match check result.
	 */
	private static function check_for_mutual_match( $user_id, $target_user_id ) {
		global $wpdb;

		$swipes_table = $wpdb->prefix . 'wpmatch_swipes';

		// Check if target user has liked us back.
		$mutual_like = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT swipe_id FROM {$swipes_table}
			WHERE user_id = %d AND target_user_id = %d
			AND swipe_type IN ('like', 'super_like') AND is_undo = 0",
				$target_user_id,
				$user_id
			)
		);

		if ( ! $mutual_like ) {
			return array(
				'is_match' => false,
				'match_id' => null,
			);
		}

		// Create match record.
		$matches_table = $wpdb->prefix . 'wpmatch_matches';

		// Ensure consistent ordering.
		$user1_id = min( $user_id, $target_user_id );
		$user2_id = max( $user_id, $target_user_id );

		// Check if match already exists.
		$existing_match = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT match_id FROM {$matches_table}
			WHERE user1_id = %d AND user2_id = %d",
				$user1_id,
				$user2_id
			)
		);

		if ( $existing_match ) {
			// Reactivate if it was unmatched.
			$wpdb->update(
				$matches_table,
				array(
					'status'     => 'active',
					'matched_at' => current_time( 'mysql' ),
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'match_id' => $existing_match )
			);

			return array(
				'is_match' => true,
				'match_id' => $existing_match,
			);
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
			)
		);

		if ( $result ) {
			return array(
				'is_match' => true,
				'match_id' => $wpdb->insert_id,
			);
		}

		return array(
			'is_match' => false,
			'match_id' => null,
		);
	}

	/**
	 * Send match notification to both users.
	 *
	 * @since 1.0.0
	 * @param int $user1_id First user ID.
	 * @param int $user2_id Second user ID.
	 * @param int $match_id Match ID.
	 */
	private static function send_match_notification( $user1_id, $user2_id, $match_id ) {
		// Trigger action for notification plugins to handle.
		do_action( 'wpmatch_new_match', $match_id, $user1_id, $user2_id );

		// Store notification in database.
		self::store_notification(
			$user1_id,
			'new_match',
			array(
				'matched_user_id' => $user2_id,
				'match_id'        => $match_id,
			)
		);

		self::store_notification(
			$user2_id,
			'new_match',
			array(
				'matched_user_id' => $user1_id,
				'match_id'        => $match_id,
			)
		);
	}

	/**
	 * Send super like notification.
	 *
	 * @since 1.0.0
	 * @param int $sender_id User who sent super like.
	 * @param int $recipient_id User who received super like.
	 */
	private static function send_super_like_notification( $sender_id, $recipient_id ) {
		// Trigger action for notification handling.
		do_action( 'wpmatch_super_like_received', $recipient_id, $sender_id );

		// Store notification.
		self::store_notification(
			$recipient_id,
			'super_like_received',
			array(
				'sender_id' => $sender_id,
			)
		);
	}

	/**
	 * Store notification in database.
	 *
	 * @since 1.0.0
	 * @param int    $user_id User ID.
	 * @param string $type Notification type.
	 * @param array  $data Notification data.
	 */
	private static function store_notification( $user_id, $type, $data = array() ) {
		// This would typically store in a notifications table.
		// For now, we'll use user meta as a simple implementation.
		$notifications = get_user_meta( $user_id, 'wpmatch_notifications', true );
		if ( ! is_array( $notifications ) ) {
			$notifications = array();
		}

		$notifications[] = array(
			'type'       => $type,
			'data'       => $data,
			'created_at' => current_time( 'mysql' ),
			'read'       => false,
		);

		// Keep only last 50 notifications.
		if ( count( $notifications ) > 50 ) {
			$notifications = array_slice( $notifications, -50 );
		}

		update_user_meta( $user_id, 'wpmatch_notifications', $notifications );
	}

	/**
	 * Helper methods for queue management.
	 */
	private static function get_active_queue( $user_id ) {
		global $wpdb;

		$queue_table = $wpdb->prefix . 'wpmatch_match_queue';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$queue_table}
			WHERE user_id = %d
			ORDER BY priority DESC, compatibility_score DESC
			LIMIT %d",
				$user_id,
				self::QUEUE_MAX_SIZE
			)
		);
	}

	private static function remove_from_queue( $user_id, $target_user_id ) {
		global $wpdb;

		$queue_table = $wpdb->prefix . 'wpmatch_match_queue';

		$wpdb->delete(
			$queue_table,
			array(
				'user_id'            => $user_id,
				'potential_match_id' => $target_user_id,
			),
			array( '%d', '%d' )
		);
	}

	private static function check_super_like_priority( $sender_id, $recipient_id ) {
		global $wpdb;

		$swipes_table = $wpdb->prefix . 'wpmatch_swipes';

		$super_like = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT swipe_id FROM {$swipes_table}
			WHERE user_id = %d AND target_user_id = %d
			AND swipe_type = 'super_like' AND is_undo = 0",
				$sender_id,
				$recipient_id
			)
		);

		return $super_like ? 100 : 0; // High priority if super liked.
	}

	/**
	 * Utility methods.
	 */
	private static function get_user_profile( $user_id ) {
		global $wpdb;

		$profiles_table = $wpdb->prefix . 'wpmatch_user_profiles';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$profiles_table} WHERE user_id = %d",
				$user_id
			)
		);
	}

	private static function get_user_preferences( $user_id ) {
		global $wpdb;

		$preferences_table = $wpdb->prefix . 'wpmatch_user_preferences';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$preferences_table} WHERE user_id = %d",
				$user_id
			)
		);
	}

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

	private static function days_since_active( $last_active ) {
		if ( ! $last_active ) {
			return 999;
		}

		$last_active_timestamp = strtotime( $last_active );
		$current_timestamp     = current_time( 'timestamp' );

		return intval( ( $current_timestamp - $last_active_timestamp ) / DAY_IN_SECONDS );
	}

	private static function get_user_ip() {
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return '0.0.0.0';
	}
}
