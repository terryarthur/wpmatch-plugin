<?php
/**
 * WPMatch Swipe Actions
 *
 * Handles like, dislike, super like, and undo functionality for the matching system.
 *
 * @package WPMatch
 */

/**
 * WPMatch Swipe Actions class.
 *
 * @since 1.0.0
 */
class WPMatch_Swipe_Actions {

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
	 * Action types.
	 */
	const ACTION_TYPES = array(
		'like',
		'dislike',
		'super_like',
		'pass',
		'block',
		'report',
	);

	/**
	 * Rate limits.
	 */
	const RATE_LIMITS = array(
		'likes_per_day'       => 100,
		'super_likes_per_day' => 5,
		'actions_per_minute'  => 10,
	);

	/**
	 * Initialize the class.
	 *
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Process a swipe action.
	 *
	 * @since 1.0.0
	 * @param int    $user_id User performing the action.
	 * @param int    $target_user_id Target user.
	 * @param string $action_type Type of action.
	 * @param array  $metadata Additional metadata.
	 * @return array Result with success status and data.
	 */
	public function process_action( $user_id, $target_user_id, $action_type, $metadata = array() ) {
		// Validate inputs.
		$user_id        = absint( $user_id );
		$target_user_id = absint( $target_user_id );
		$action_type    = sanitize_text_field( $action_type );

		if ( ! $user_id || ! $target_user_id || $user_id === $target_user_id ) {
			return array(
				'success' => false,
				'error'   => 'invalid_users',
				'message' => __( 'Invalid user IDs.', 'wpmatch' ),
			);
		}

		if ( ! in_array( $action_type, self::ACTION_TYPES, true ) ) {
			return array(
				'success' => false,
				'error'   => 'invalid_action',
				'message' => __( 'Invalid action type.', 'wpmatch' ),
			);
		}

		// Check rate limits.
		$rate_check = $this->check_rate_limits( $user_id, $action_type );
		if ( ! $rate_check['allowed'] ) {
			return $rate_check;
		}

		// Check if action already exists.
		if ( $this->has_existing_action( $user_id, $target_user_id ) ) {
			return array(
				'success' => false,
				'error'   => 'action_exists',
				'message' => __( 'You have already performed an action on this user.', 'wpmatch' ),
			);
		}

		// Validate target user exists and is active.
		if ( ! $this->is_valid_target_user( $target_user_id ) ) {
			return array(
				'success' => false,
				'error'   => 'invalid_target',
				'message' => __( 'Target user is not available.', 'wpmatch' ),
			);
		}

		// Record the action.
		$action_id = $this->record_action( $user_id, $target_user_id, $action_type, $metadata );

		if ( ! $action_id ) {
			return array(
				'success' => false,
				'error'   => 'record_failed',
				'message' => __( 'Failed to record action.', 'wpmatch' ),
			);
		}

		// Process action-specific logic.
		$result = $this->process_action_logic( $action_id, $user_id, $target_user_id, $action_type, $metadata );

		// Remove from queue.
		$this->remove_from_queue( $user_id, $target_user_id );

		// Update user activity.
		$this->update_user_activity( $user_id );

		// Trigger action hook.
		do_action( 'wpmatch_action_processed', $action_id, $user_id, $target_user_id, $action_type, $result );

		return $result;
	}

	/**
	 * Like a user.
	 *
	 * @since 1.0.0
	 * @param int   $user_id User performing the like.
	 * @param int   $target_user_id Target user.
	 * @param array $metadata Additional metadata.
	 * @return array Result with success status and data.
	 */
	public function like_user( $user_id, $target_user_id, $metadata = array() ) {
		return $this->process_action( $user_id, $target_user_id, 'like', $metadata );
	}

	/**
	 * Dislike (pass) a user.
	 *
	 * @since 1.0.0
	 * @param int   $user_id User performing the dislike.
	 * @param int   $target_user_id Target user.
	 * @param array $metadata Additional metadata.
	 * @return array Result with success status and data.
	 */
	public function dislike_user( $user_id, $target_user_id, $metadata = array() ) {
		return $this->process_action( $user_id, $target_user_id, 'dislike', $metadata );
	}

	/**
	 * Super like a user.
	 *
	 * @since 1.0.0
	 * @param int   $user_id User performing the super like.
	 * @param int   $target_user_id Target user.
	 * @param array $metadata Additional metadata.
	 * @return array Result with success status and data.
	 */
	public function super_like_user( $user_id, $target_user_id, $metadata = array() ) {
		return $this->process_action( $user_id, $target_user_id, 'super_like', $metadata );
	}

	/**
	 * Block a user.
	 *
	 * @since 1.0.0
	 * @param int    $user_id User performing the block.
	 * @param int    $target_user_id Target user.
	 * @param string $reason Block reason.
	 * @return array Result with success status and data.
	 */
	public function block_user( $user_id, $target_user_id, $reason = '' ) {
		$metadata = array( 'reason' => sanitize_textarea_field( $reason ) );
		return $this->process_action( $user_id, $target_user_id, 'block', $metadata );
	}

	/**
	 * Report a user.
	 *
	 * @since 1.0.0
	 * @param int    $user_id User making the report.
	 * @param int    $target_user_id Target user.
	 * @param string $reason Report reason.
	 * @param string $description Report description.
	 * @return array Result with success status and data.
	 */
	public function report_user( $user_id, $target_user_id, $reason = '', $description = '' ) {
		$metadata = array(
			'reason'      => sanitize_text_field( $reason ),
			'description' => sanitize_textarea_field( $description ),
		);
		return $this->process_action( $user_id, $target_user_id, 'report', $metadata );
	}

	/**
	 * Undo the last action.
	 *
	 * @since 1.0.0
	 * @param int $user_id User requesting undo.
	 * @return array Result with success status and data.
	 */
	public function undo_last_action( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return array(
				'success' => false,
				'error'   => 'invalid_user',
				'message' => __( 'Invalid user ID.', 'wpmatch' ),
			);
		}

		$actions_table = $wpdb->prefix . 'wpmatch_user_actions';

		// Get the last action that can be undone.
		$last_action = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$actions_table}
			WHERE user_id = %d AND action_type IN ('like', 'dislike', 'super_like', 'pass')
			AND is_undone = 0
			ORDER BY created_at DESC
			LIMIT 1",
				$user_id
			)
		);

		if ( ! $last_action ) {
			return array(
				'success' => false,
				'error'   => 'no_action_to_undo',
				'message' => __( 'No recent action to undo.', 'wpmatch' ),
			);
		}

		// Check if undo is allowed (within time limit).
		$time_limit   = apply_filters( 'wpmatch_undo_time_limit', 30 ); // 30 seconds default.
		$action_time  = strtotime( $last_action->created_at );
		$current_time = current_time( 'timestamp' );

		if ( ( $current_time - $action_time ) > $time_limit ) {
			return array(
				'success' => false,
				'error'   => 'undo_expired',
				'message' => sprintf(
					__( 'Undo is only available within %d seconds of the action.', 'wpmatch' ),
					$time_limit
				),
			);
		}

		// Mark action as undone.
		$result = $wpdb->update(
			$actions_table,
			array(
				'is_undone' => 1,
				'undone_at' => current_time( 'mysql' ),
			),
			array( 'action_id' => $last_action->action_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		if ( ! $result ) {
			return array(
				'success' => false,
				'error'   => 'undo_failed',
				'message' => __( 'Failed to undo action.', 'wpmatch' ),
			);
		}

		// If it was a like that created a match, remove the match.
		if ( 'like' === $last_action->action_type || 'super_like' === $last_action->action_type ) {
			$this->remove_match_if_exists( $user_id, absint( $last_action->target_user_id ) );
		}

		// Add user back to queue.
		$this->add_back_to_queue( $user_id, absint( $last_action->target_user_id ) );

		return array(
			'success'       => true,
			'undone_action' => array(
				'action_id'      => $last_action->action_id,
				'target_user_id' => $last_action->target_user_id,
				'action_type'    => $last_action->action_type,
			),
		);
	}

	/**
	 * Get user's action history.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @param int $limit Number of actions to retrieve.
	 * @param int $offset Offset for pagination.
	 * @return array Action history.
	 */
	public function get_action_history( $user_id, $limit = 50, $offset = 0 ) {
		global $wpdb;

		$user_id = absint( $user_id );
		$limit   = absint( $limit );
		$offset  = absint( $offset );

		if ( ! $user_id ) {
			return array();
		}

		$actions_table = $wpdb->prefix . 'wpmatch_user_actions';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, u.display_name as target_user_name, p.age, p.location
			FROM {$actions_table} a
			INNER JOIN {$wpdb->users} u ON a.target_user_id = u.ID
			LEFT JOIN {$wpdb->prefix}wpmatch_user_profiles p ON u.ID = p.user_id
			WHERE a.user_id = %d
			ORDER BY a.created_at DESC
			LIMIT %d OFFSET %d",
				$user_id,
				$limit,
				$offset
			),
			ARRAY_A
		);
	}

	/**
	 * Get users who liked the current user.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @param int $limit Number of likes to retrieve.
	 * @param int $offset Offset for pagination.
	 * @return array Users who liked this user.
	 */
	public function get_users_who_liked_me( $user_id, $limit = 20, $offset = 0 ) {
		global $wpdb;

		$user_id = absint( $user_id );
		$limit   = absint( $limit );
		$offset  = absint( $offset );

		if ( ! $user_id ) {
			return array();
		}

		$actions_table = $wpdb->prefix . 'wpmatch_user_actions';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, u.display_name, p.age, p.location, p.about_me
			FROM {$actions_table} a
			INNER JOIN {$wpdb->users} u ON a.user_id = u.ID
			INNER JOIN {$wpdb->prefix}wpmatch_user_profiles p ON u.ID = p.user_id
			WHERE a.target_user_id = %d
			AND a.action_type IN ('like', 'super_like')
			AND a.is_undone = 0
			AND NOT EXISTS (
				SELECT 1 FROM {$actions_table} my_actions
				WHERE my_actions.user_id = %d
				AND my_actions.target_user_id = a.user_id
				AND my_actions.is_undone = 0
			)
			ORDER BY a.action_type DESC, a.created_at DESC
			LIMIT %d OFFSET %d",
				$user_id,
				$user_id,
				$limit,
				$offset
			),
			ARRAY_A
		);
	}

	/**
	 * Get mutual likes (matches).
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @param int $limit Number of matches to retrieve.
	 * @param int $offset Offset for pagination.
	 * @return array Mutual matches.
	 */
	public function get_mutual_likes( $user_id, $limit = 20, $offset = 0 ) {
		global $wpdb;

		$user_id = absint( $user_id );
		$limit   = absint( $limit );
		$offset  = absint( $offset );

		if ( ! $user_id ) {
			return array();
		}

		$matches_table = $wpdb->prefix . 'wpmatch_matches';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.*,
				CASE
					WHEN m.user1_id = %d THEN m.user2_id
					ELSE m.user1_id
				END as other_user_id,
				u.display_name as other_user_name,
				p.age, p.location, p.about_me
			FROM {$matches_table} m
			INNER JOIN {$wpdb->users} u ON (
				CASE
					WHEN m.user1_id = %d THEN m.user2_id
					ELSE m.user1_id
				END
			) = u.ID
			INNER JOIN {$wpdb->prefix}wpmatch_user_profiles p ON u.ID = p.user_id
			WHERE (m.user1_id = %d OR m.user2_id = %d)
			AND m.status = 'active'
			ORDER BY m.matched_at DESC
			LIMIT %d OFFSET %d",
				$user_id,
				$user_id,
				$user_id,
				$user_id,
				$limit,
				$offset
			),
			ARRAY_A
		);
	}

	/**
	 * Check rate limits for actions.
	 *
	 * @since 1.0.0
	 * @param int    $user_id User ID.
	 * @param string $action_type Action type.
	 * @return array Rate limit check result.
	 */
	private function check_rate_limits( $user_id, $action_type ) {
		global $wpdb;

		$actions_table = $wpdb->prefix . 'wpmatch_user_actions';

		// Check per-minute rate limit.
		$minute_ago          = date( 'Y-m-d H:i:s', strtotime( '-1 minute' ) );
		$actions_last_minute = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$actions_table}
			WHERE user_id = %d AND created_at > %s",
				$user_id,
				$minute_ago
			)
		);

		if ( $actions_last_minute >= self::RATE_LIMITS['actions_per_minute'] ) {
			return array(
				'success' => false,
				'allowed' => false,
				'error'   => 'rate_limit_minute',
				'message' => __( 'Too many actions. Please wait a moment before trying again.', 'wpmatch' ),
			);
		}

		// Check daily limits for specific actions.
		$today_start = date( 'Y-m-d 00:00:00' );

		if ( 'like' === $action_type ) {
			$likes_today = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$actions_table}
				WHERE user_id = %d AND action_type = 'like'
				AND created_at > %s AND is_undone = 0",
					$user_id,
					$today_start
				)
			);

			if ( $likes_today >= self::RATE_LIMITS['likes_per_day'] ) {
				return array(
					'success' => false,
					'allowed' => false,
					'error'   => 'daily_likes_exceeded',
					'message' => sprintf(
						__( 'You have reached the daily limit of %d likes. More will be available tomorrow.', 'wpmatch' ),
						self::RATE_LIMITS['likes_per_day']
					),
				);
			}
		}

		if ( 'super_like' === $action_type ) {
			$super_likes_today = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$actions_table}
				WHERE user_id = %d AND action_type = 'super_like'
				AND created_at > %s AND is_undone = 0",
					$user_id,
					$today_start
				)
			);

			if ( $super_likes_today >= self::RATE_LIMITS['super_likes_per_day'] ) {
				return array(
					'success' => false,
					'allowed' => false,
					'error'   => 'daily_super_likes_exceeded',
					'message' => sprintf(
						__( 'You have reached the daily limit of %d super likes. More will be available tomorrow.', 'wpmatch' ),
						self::RATE_LIMITS['super_likes_per_day']
					),
				);
			}
		}

		return array( 'allowed' => true );
	}

	/**
	 * Check if user has existing action on target.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @param int $target_user_id Target user ID.
	 * @return bool True if action exists.
	 */
	private function has_existing_action( $user_id, $target_user_id ) {
		global $wpdb;

		$actions_table = $wpdb->prefix . 'wpmatch_user_actions';

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT action_id FROM {$actions_table}
			WHERE user_id = %d AND target_user_id = %d AND is_undone = 0",
				$user_id,
				$target_user_id
			)
		);

		return (bool) $existing;
	}

	/**
	 * Validate target user.
	 *
	 * @since 1.0.0
	 * @param int $target_user_id Target user ID.
	 * @return bool True if valid.
	 */
	private function is_valid_target_user( $target_user_id ) {
		$user = get_userdata( $target_user_id );

		if ( ! $user ) {
			return false;
		}

		// Check if user has a dating profile.
		global $wpdb;
		$profiles_table = $wpdb->prefix . 'wpmatch_user_profiles';

		$profile = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT profile_id FROM {$profiles_table} WHERE user_id = %d",
				$target_user_id
			)
		);

		return (bool) $profile;
	}

	/**
	 * Record action in database.
	 *
	 * @since 1.0.0
	 * @param int    $user_id User ID.
	 * @param int    $target_user_id Target user ID.
	 * @param string $action_type Action type.
	 * @param array  $metadata Additional metadata.
	 * @return int|false Action ID or false on failure.
	 */
	private function record_action( $user_id, $target_user_id, $action_type, $metadata ) {
		global $wpdb;

		$actions_table = $wpdb->prefix . 'wpmatch_user_actions';

		$result = $wpdb->insert(
			$actions_table,
			array(
				'user_id'        => $user_id,
				'target_user_id' => $target_user_id,
				'action_type'    => $action_type,
				'metadata'       => wp_json_encode( $metadata ),
				'ip_address'     => $this->get_client_ip(),
				'user_agent'     => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Process action-specific logic.
	 *
	 * @since 1.0.0
	 * @param int    $action_id Action ID.
	 * @param int    $user_id User ID.
	 * @param int    $target_user_id Target user ID.
	 * @param string $action_type Action type.
	 * @param array  $metadata Additional metadata.
	 * @return array Result with success status and data.
	 */
	private function process_action_logic( $action_id, $user_id, $target_user_id, $action_type, $metadata ) {
		$result = array(
			'success'     => true,
			'action_id'   => $action_id,
			'action_type' => $action_type,
			'is_match'    => false,
			'match_id'    => null,
		);

		switch ( $action_type ) {
			case 'like':
			case 'super_like':
				// Check for mutual like (match).
				$match_result       = $this->check_for_match( $user_id, $target_user_id );
				$result['is_match'] = $match_result['is_match'];
				$result['match_id'] = $match_result['match_id'];

				if ( $result['is_match'] ) {
					$this->send_match_notifications( $user_id, $target_user_id, $result['match_id'] );
				} elseif ( 'super_like' === $action_type ) {
					$this->send_super_like_notification( $user_id, $target_user_id );
				}
				break;

			case 'block':
				$this->process_block_action( $user_id, $target_user_id, $metadata );
				break;

			case 'report':
				$this->process_report_action( $user_id, $target_user_id, $metadata );
				break;
		}

		return $result;
	}

	/**
	 * Check for mutual match.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @param int $target_user_id Target user ID.
	 * @return array Match result.
	 */
	private function check_for_match( $user_id, $target_user_id ) {
		global $wpdb;

		$actions_table = $wpdb->prefix . 'wpmatch_user_actions';

		// Check if target user has liked us.
		$mutual_like = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT action_id FROM {$actions_table}
			WHERE user_id = %d AND target_user_id = %d
			AND action_type IN ('like', 'super_like') AND is_undone = 0",
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
			// Reactivate if needed.
			$wpdb->update(
				$matches_table,
				array(
					'status'     => 'active',
					'matched_at' => current_time( 'mysql' ),
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'match_id' => $existing_match ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
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
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
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
	 * Remove from queue.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @param int $target_user_id Target user ID.
	 */
	private function remove_from_queue( $user_id, $target_user_id ) {
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

	/**
	 * Update user activity timestamp.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 */
	private function update_user_activity( $user_id ) {
		update_user_meta( $user_id, 'wpmatch_last_activity', current_time( 'mysql' ) );

		// Also update in profile table.
		global $wpdb;
		$profiles_table = $wpdb->prefix . 'wpmatch_user_profiles';

		$wpdb->update(
			$profiles_table,
			array( 'last_active' => current_time( 'mysql' ) ),
			array( 'user_id' => $user_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Get client IP address.
	 *
	 * @since 1.0.0
	 * @return string Client IP address.
	 */
	private function get_client_ip() {
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return '0.0.0.0';
	}

	/**
	 * Send match notifications.
	 *
	 * @since 1.0.0
	 * @param int $user1_id First user ID.
	 * @param int $user2_id Second user ID.
	 * @param int $match_id Match ID.
	 */
	private function send_match_notifications( $user1_id, $user2_id, $match_id ) {
		do_action( 'wpmatch_new_match', $match_id, $user1_id, $user2_id );
	}

	/**
	 * Send super like notification.
	 *
	 * @since 1.0.0
	 * @param int $sender_id Sender user ID.
	 * @param int $recipient_id Recipient user ID.
	 */
	private function send_super_like_notification( $sender_id, $recipient_id ) {
		do_action( 'wpmatch_super_like_received', $recipient_id, $sender_id );
	}

	/**
	 * Process block action.
	 *
	 * @since 1.0.0
	 * @param int   $user_id User doing the blocking.
	 * @param int   $target_user_id User being blocked.
	 * @param array $metadata Block metadata.
	 */
	private function process_block_action( $user_id, $target_user_id, $metadata ) {
		// Add to blocked users list in preferences.
		global $wpdb;
		$preferences_table = $wpdb->prefix . 'wpmatch_user_preferences';

		$current_blocked = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT blocked_users FROM {$preferences_table} WHERE user_id = %d",
				$user_id
			)
		);

		$blocked_list = json_decode( $current_blocked, true );
		if ( ! is_array( $blocked_list ) ) {
			$blocked_list = array();
		}

		if ( ! in_array( $target_user_id, $blocked_list, true ) ) {
			$blocked_list[] = $target_user_id;

			$wpdb->update(
				$preferences_table,
				array( 'blocked_users' => wp_json_encode( $blocked_list ) ),
				array( 'user_id' => $user_id ),
				array( '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Process report action.
	 *
	 * @since 1.0.0
	 * @param int   $user_id User making the report.
	 * @param int   $target_user_id User being reported.
	 * @param array $metadata Report metadata.
	 */
	private function process_report_action( $user_id, $target_user_id, $metadata ) {
		// This could create a report in a dedicated reports table.
		// For now, we'll use the action metadata to store report details.
		do_action( 'wpmatch_user_reported', $target_user_id, $user_id, $metadata );
	}

	/**
	 * Remove match if it exists.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @param int $target_user_id Target user ID.
	 */
	private function remove_match_if_exists( $user_id, $target_user_id ) {
		global $wpdb;

		$matches_table = $wpdb->prefix . 'wpmatch_matches';

		$user1_id = min( $user_id, $target_user_id );
		$user2_id = max( $user_id, $target_user_id );

		$wpdb->update(
			$matches_table,
			array( 'status' => 'inactive' ),
			array(
				'user1_id' => $user1_id,
				'user2_id' => $user2_id,
			),
			array( '%s' ),
			array( '%d', '%d' )
		);
	}

	/**
	 * Add user back to queue after undo.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @param int $target_user_id Target user ID.
	 */
	private function add_back_to_queue( $user_id, $target_user_id ) {
		global $wpdb;

		$queue_table = $wpdb->prefix . 'wpmatch_match_queue';

		// Check if already in queue.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$queue_table}
			WHERE user_id = %d AND potential_match_id = %d",
				$user_id,
				$target_user_id
			)
		);

		if ( ! $existing ) {
			// Calculate compatibility score.
			$compatibility_score = WPMatch_Matching_Algorithm::calculate_comprehensive_compatibility( $user_id, $target_user_id );

			$wpdb->insert(
				$queue_table,
				array(
					'user_id'             => $user_id,
					'potential_match_id'  => $target_user_id,
					'compatibility_score' => $compatibility_score,
					'priority'            => 0,
					'created_at'          => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%f', '%d', '%s' )
			);
		}
	}

	/**
	 * API endpoint for liking a user.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response REST response.
	 */
	public function api_like_user( $request ) {
		$user_id        = get_current_user_id();
		$target_user_id = absint( $request->get_param( 'target_user_id' ) );

		$result = $this->like_user( $user_id, $target_user_id );

		return rest_ensure_response( $result );
	}

	/**
	 * API endpoint for disliking a user.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response REST response.
	 */
	public function api_dislike_user( $request ) {
		$user_id        = get_current_user_id();
		$target_user_id = absint( $request->get_param( 'target_user_id' ) );

		$result = $this->dislike_user( $user_id, $target_user_id );

		return rest_ensure_response( $result );
	}

	/**
	 * API endpoint for super liking a user.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response REST response.
	 */
	public function api_super_like_user( $request ) {
		$user_id        = get_current_user_id();
		$target_user_id = absint( $request->get_param( 'target_user_id' ) );

		$result = $this->super_like_user( $user_id, $target_user_id );

		return rest_ensure_response( $result );
	}

	/**
	 * API endpoint for blocking a user.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response REST response.
	 */
	public function api_block_user( $request ) {
		$user_id        = get_current_user_id();
		$target_user_id = absint( $request->get_param( 'target_user_id' ) );
		$reason         = sanitize_textarea_field( $request->get_param( 'reason' ) );

		$result = $this->block_user( $user_id, $target_user_id, $reason );

		return rest_ensure_response( $result );
	}

	/**
	 * API endpoint for reporting a user.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response REST response.
	 */
	public function api_report_user( $request ) {
		$user_id        = get_current_user_id();
		$target_user_id = absint( $request->get_param( 'target_user_id' ) );
		$reason         = sanitize_text_field( $request->get_param( 'reason' ) );
		$description    = sanitize_textarea_field( $request->get_param( 'description' ) );

		$result = $this->report_user( $user_id, $target_user_id, $reason, $description );

		return rest_ensure_response( $result );
	}

	/**
	 * API endpoint for undoing last action.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response REST response.
	 */
	public function api_undo_last_action( $request ) {
		$user_id = get_current_user_id();

		$result = $this->undo_last_action( $user_id );

		return rest_ensure_response( $result );
	}

	/**
	 * API endpoint for getting action history.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response REST response.
	 */
	public function api_get_action_history( $request ) {
		$user_id = get_current_user_id();
		$limit   = absint( $request->get_param( 'limit' ) );
		$offset  = absint( $request->get_param( 'offset' ) );

		$history = $this->get_action_history( $user_id, $limit, $offset );

		return rest_ensure_response(
			array(
				'success' => true,
				'history' => $history,
				'count'   => count( $history ),
			)
		);
	}

	/**
	 * API endpoint for getting users who liked me.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response REST response.
	 */
	public function api_get_users_who_liked_me( $request ) {
		$user_id = get_current_user_id();
		$limit   = absint( $request->get_param( 'limit' ) );
		$offset  = absint( $request->get_param( 'offset' ) );

		$users = $this->get_users_who_liked_me( $user_id, $limit, $offset );

		return rest_ensure_response(
			array(
				'success' => true,
				'users'   => $users,
				'count'   => count( $users ),
			)
		);
	}

	/**
	 * API endpoint for getting mutual likes.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response REST response.
	 */
	public function api_get_mutual_likes( $request ) {
		$user_id = get_current_user_id();
		$limit   = absint( $request->get_param( 'limit' ) );
		$offset  = absint( $request->get_param( 'offset' ) );

		$matches = $this->get_mutual_likes( $user_id, $limit, $offset );

		return rest_ensure_response(
			array(
				'success' => true,
				'matches' => $matches,
				'count'   => count( $matches ),
			)
		);
	}
}
