<?php
/**
 * WPMatch Advanced Matching Algorithm
 *
 * Enhanced matching with ML-based compatibility, behavioral analysis, and adaptive learning.
 *
 * @package WPMatch
 * @subpackage AdvancedMatching
 * @since 1.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPMatch Advanced Matching Algorithm class.
 *
 * @since 1.1.0
 */
class WPMatch_Advanced_Matching {

	/**
	 * Advanced matching constants.
	 */
	const ML_WEIGHT_THRESHOLD     = 0.7;
	const BEHAVIOR_ANALYSIS_DAYS  = 30;
	const PREFERENCE_LEARNING_MIN = 10;
	const BOOST_MULTIPLIER        = 2.5;
	const RECENCY_BOOST_HOURS     = 24;

	/**
	 * Initialize advanced matching features.
	 */
	public static function init() {
		// Create necessary database tables.
		self::create_tables();

		// Hook into existing matching process.
		add_filter( 'wpmatch_compatibility_score', array( __CLASS__, 'enhance_compatibility_score' ), 10, 3 );
		add_filter( 'wpmatch_user_matches', array( __CLASS__, 'enhance_match_results' ), 10, 2 );
		add_action( 'wpmatch_swipe_processed', array( __CLASS__, 'learn_from_swipe' ), 10, 5 );
		add_action( 'wpmatch_message_sent', array( __CLASS__, 'learn_from_message' ), 10, 3 );

		// Schedule periodic learning updates.
		if ( ! wp_next_scheduled( 'wpmatch_update_ml_models' ) ) {
			wp_schedule_event( time(), 'daily', 'wpmatch_update_ml_models' );
		}
		add_action( 'wpmatch_update_ml_models', array( __CLASS__, 'update_ml_models' ) );

		// Track user interactions for learning.
		add_action( 'wpmatch_user_interaction', array( __CLASS__, 'track_user_interaction' ), 10, 4 );

		// Add advanced matching admin settings.
		add_filter( 'wpmatch_admin_settings_sections', array( __CLASS__, 'add_admin_settings' ) );

		// Schedule cleanup of old behavioral data.
		if ( ! wp_next_scheduled( 'wpmatch_cleanup_behavioral_data' ) ) {
			wp_schedule_event( time(), 'weekly', 'wpmatch_cleanup_behavioral_data' );
		}
		add_action( 'wpmatch_cleanup_behavioral_data', array( __CLASS__, 'cleanup_old_data' ) );
	}

	/**
	 * Enhanced compatibility scoring with machine learning.
	 *
	 * @param float $base_score Original compatibility score.
	 * @param int   $user1_id First user ID.
	 * @param int   $user2_id Second user ID.
	 * @return float Enhanced compatibility score.
	 */
	public static function enhance_compatibility_score( $base_score, $user1_id, $user2_id ) {
		$user1_id = absint( $user1_id );
		$user2_id = absint( $user2_id );

		if ( ! $user1_id || ! $user2_id ) {
			return $base_score;
		}

		$enhancements = array();

		// Behavioral compatibility analysis.
		$behavioral_score = self::calculate_behavioral_compatibility( $user1_id, $user2_id );
		$enhancements['behavioral'] = array( 'score' => $behavioral_score, 'weight' => 0.25 );

		// Preference learning based compatibility.
		$preference_score = self::calculate_preference_compatibility( $user1_id, $user2_id );
		$enhancements['preference'] = array( 'score' => $preference_score, 'weight' => 0.20 );

		// Communication style compatibility.
		$communication_score = self::calculate_communication_compatibility( $user1_id, $user2_id );
		$enhancements['communication'] = array( 'score' => $communication_score, 'weight' => 0.15 );

		// Temporal activity pattern matching.
		$temporal_score = self::calculate_temporal_compatibility( $user1_id, $user2_id );
		$enhancements['temporal'] = array( 'score' => $temporal_score, 'weight' => 0.10 );

		// Social network analysis (if available).
		$social_score = self::calculate_social_compatibility( $user1_id, $user2_id );
		$enhancements['social'] = array( 'score' => $social_score, 'weight' => 0.15 );

		// Success prediction based on historical data.
		$success_score = self::predict_relationship_success( $user1_id, $user2_id );
		$enhancements['success'] = array( 'score' => $success_score, 'weight' => 0.15 );

		// Calculate enhanced score.
		$total_enhanced_score = 0;
		$total_weight = 0;

		foreach ( $enhancements as $enhancement ) {
			if ( $enhancement['score'] !== null ) {
				$total_enhanced_score += $enhancement['score'] * $enhancement['weight'];
				$total_weight += $enhancement['weight'];
			}
		}

		// Blend with base score.
		$base_weight = 1.0 - $total_weight;
		$final_score = ( $base_score * $base_weight ) + $total_enhanced_score;

		// Apply ML confidence weighting.
		$ml_confidence = self::get_ml_confidence( $user1_id, $user2_id );
		if ( $ml_confidence >= self::ML_WEIGHT_THRESHOLD ) {
			$final_score = ( $final_score * $ml_confidence ) + ( $base_score * ( 1 - $ml_confidence ) );
		}

		return min( 1.0, max( 0.0, $final_score ) );
	}

	/**
	 * Calculate behavioral compatibility based on app usage patterns.
	 *
	 * @param int $user1_id First user ID.
	 * @param int $user2_id Second user ID.
	 * @return float|null Behavioral compatibility score.
	 */
	private static function calculate_behavioral_compatibility( $user1_id, $user2_id ) {
		global $wpdb;

		$swipes_table = $wpdb->prefix . 'wpmatch_swipes';
		$cutoff_date = date( 'Y-m-d H:i:s', strtotime( '-' . self::BEHAVIOR_ANALYSIS_DAYS . ' days' ) );

		// Get behavioral patterns for both users.
		$user1_patterns = self::get_user_behavioral_patterns( $user1_id, $cutoff_date );
		$user2_patterns = self::get_user_behavioral_patterns( $user2_id, $cutoff_date );

		if ( ! $user1_patterns || ! $user2_patterns ) {
			return null;
		}

		$compatibility_factors = array();

		// Swipe selectivity similarity.
		$selectivity_diff = abs( $user1_patterns['selectivity'] - $user2_patterns['selectivity'] );
		$compatibility_factors['selectivity'] = max( 0, 1 - ( $selectivity_diff / 0.5 ) );

		// Activity time overlap.
		$time_overlap = self::calculate_activity_time_overlap( $user1_patterns['active_hours'], $user2_patterns['active_hours'] );
		$compatibility_factors['time_overlap'] = $time_overlap;

		// Response time compatibility.
		$response_diff = abs( $user1_patterns['avg_response_time'] - $user2_patterns['avg_response_time'] );
		$compatibility_factors['response_time'] = max( 0, 1 - ( $response_diff / 86400 ) ); // Normalize by 24 hours.

		// Session length compatibility.
		$session_diff = abs( $user1_patterns['avg_session_length'] - $user2_patterns['avg_session_length'] );
		$compatibility_factors['session_length'] = max( 0, 1 - ( $session_diff / 7200 ) ); // Normalize by 2 hours.

		// Calculate weighted average.
		$weights = array(
			'selectivity'    => 0.3,
			'time_overlap'   => 0.3,
			'response_time'  => 0.2,
			'session_length' => 0.2,
		);

		$total_score = 0;
		foreach ( $compatibility_factors as $factor => $score ) {
			$total_score += $score * $weights[ $factor ];
		}

		return $total_score;
	}

	/**
	 * Calculate preference-based compatibility using learned preferences.
	 *
	 * @param int $user1_id First user ID.
	 * @param int $user2_id Second user ID.
	 * @return float|null Preference compatibility score.
	 */
	private static function calculate_preference_compatibility( $user1_id, $user2_id ) {
		$user1_preferences = self::get_learned_preferences( $user1_id );
		$user2_preferences = self::get_learned_preferences( $user2_id );

		if ( ! $user1_preferences || ! $user2_preferences ) {
			return null;
		}

		$user1_profile = WPMatch_Matching_Algorithm::get_user_profile( $user1_id );
		$user2_profile = WPMatch_Matching_Algorithm::get_user_profile( $user2_id );

		if ( ! $user1_profile || ! $user2_profile ) {
			return null;
		}

		$compatibility_score = 0;
		$total_weight = 0;

		// Check if user2 matches user1's learned preferences.
		foreach ( $user1_preferences as $preference => $weight ) {
			$matches_preference = self::does_profile_match_preference( $user2_profile, $preference, $weight );
			if ( $matches_preference !== null ) {
				$compatibility_score += $matches_preference * $weight;
				$total_weight += $weight;
			}
		}

		// Check if user1 matches user2's learned preferences.
		foreach ( $user2_preferences as $preference => $weight ) {
			$matches_preference = self::does_profile_match_preference( $user1_profile, $preference, $weight );
			if ( $matches_preference !== null ) {
				$compatibility_score += $matches_preference * $weight;
				$total_weight += $weight;
			}
		}

		return $total_weight > 0 ? $compatibility_score / $total_weight : null;
	}

	/**
	 * Calculate communication style compatibility.
	 *
	 * @param int $user1_id First user ID.
	 * @param int $user2_id Second user ID.
	 * @return float|null Communication compatibility score.
	 */
	private static function calculate_communication_compatibility( $user1_id, $user2_id ) {
		global $wpdb;

		$messages_table = $wpdb->prefix . 'wpmatch_messages';

		// Get communication patterns for both users.
		$user1_comm = self::get_communication_patterns( $user1_id );
		$user2_comm = self::get_communication_patterns( $user2_id );

		if ( ! $user1_comm || ! $user2_comm ) {
			return null;
		}

		$compatibility_factors = array();

		// Message length compatibility.
		$length_diff = abs( $user1_comm['avg_message_length'] - $user2_comm['avg_message_length'] );
		$compatibility_factors['message_length'] = max( 0, 1 - ( $length_diff / 500 ) ); // Normalize by 500 chars.

		// Response frequency compatibility.
		$frequency_diff = abs( $user1_comm['messages_per_day'] - $user2_comm['messages_per_day'] );
		$compatibility_factors['frequency'] = max( 0, 1 - ( $frequency_diff / 50 ) ); // Normalize by 50 messages.

		// Emoji usage compatibility.
		$emoji_diff = abs( $user1_comm['emoji_usage'] - $user2_comm['emoji_usage'] );
		$compatibility_factors['emoji'] = max( 0, 1 - $emoji_diff );

		// Question asking tendency.
		$question_diff = abs( $user1_comm['question_ratio'] - $user2_comm['question_ratio'] );
		$compatibility_factors['questions'] = max( 0, 1 - $question_diff );

		// Calculate weighted average.
		$weights = array(
			'message_length' => 0.25,
			'frequency'      => 0.35,
			'emoji'          => 0.2,
			'questions'      => 0.2,
		);

		$total_score = 0;
		foreach ( $compatibility_factors as $factor => $score ) {
			$total_score += $score * $weights[ $factor ];
		}

		return $total_score;
	}

	/**
	 * Calculate temporal compatibility (when users are active).
	 *
	 * @param int $user1_id First user ID.
	 * @param int $user2_id Second user ID.
	 * @return float Temporal compatibility score.
	 */
	private static function calculate_temporal_compatibility( $user1_id, $user2_id ) {
		$user1_schedule = self::get_user_activity_schedule( $user1_id );
		$user2_schedule = self::get_user_activity_schedule( $user2_id );

		if ( ! $user1_schedule || ! $user2_schedule ) {
			return 0.5; // Default neutral score.
		}

		$overlap_score = 0;
		$total_slots = 0;

		// Check overlap for each hour of the week (168 total hours).
		for ( $day = 0; $day < 7; $day++ ) {
			for ( $hour = 0; $hour < 24; $hour++ ) {
				$slot_key = $day . '_' . $hour;
				$user1_active = isset( $user1_schedule[ $slot_key ] ) ? $user1_schedule[ $slot_key ] : 0;
				$user2_active = isset( $user2_schedule[ $slot_key ] ) ? $user2_schedule[ $slot_key ] : 0;

				// Calculate overlap intensity.
				$overlap_intensity = min( $user1_active, $user2_active );
				$overlap_score += $overlap_intensity;
				$total_slots++;
			}
		}

		return $total_slots > 0 ? $overlap_score / $total_slots : 0.5;
	}

	/**
	 * Calculate social compatibility based on mutual connections.
	 *
	 * @param int $user1_id First user ID.
	 * @param int $user2_id Second user ID.
	 * @return float|null Social compatibility score.
	 */
	private static function calculate_social_compatibility( $user1_id, $user2_id ) {
		// This would integrate with social media APIs or mutual friend data.
		// For now, we'll use a simplified version based on common interests and locations.

		global $wpdb;

		$interests_table = $wpdb->prefix . 'wpmatch_user_interests';

		// Get mutual interests count.
		$mutual_interests = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT i1.interest_name) as mutual_count
				FROM {$interests_table} i1
				INNER JOIN {$interests_table} i2 ON i1.interest_name = i2.interest_name
				WHERE i1.user_id = %d AND i2.user_id = %d",
				$user1_id,
				$user2_id
			)
		);

		// Get total unique interests for normalization.
		$total_interests = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT interest_name)
				FROM {$interests_table}
				WHERE user_id IN (%d, %d)",
				$user1_id,
				$user2_id
			)
		);

		if ( ! $total_interests ) {
			return null;
		}

		$interest_overlap = $mutual_interests / max( 1, $total_interests );

		// Check for location-based social signals.
		$location_similarity = self::get_location_social_score( $user1_id, $user2_id );

		// Combine social signals.
		$social_score = ( $interest_overlap * 0.7 ) + ( $location_similarity * 0.3 );

		return min( 1.0, $social_score );
	}

	/**
	 * Predict relationship success based on historical data.
	 *
	 * @param int $user1_id First user ID.
	 * @param int $user2_id Second user ID.
	 * @return float Success prediction score.
	 */
	private static function predict_relationship_success( $user1_id, $user2_id ) {
		// This uses historical match data to predict success likelihood.

		$user1_history = self::get_user_relationship_history( $user1_id );
		$user2_history = self::get_user_relationship_history( $user2_id );

		$success_factors = array();

		// Past relationship duration patterns.
		if ( $user1_history['avg_relationship_duration'] && $user2_history['avg_relationship_duration'] ) {
			$duration_compatibility = 1 - abs( $user1_history['avg_relationship_duration'] - $user2_history['avg_relationship_duration'] ) / 365;
			$success_factors['duration'] = max( 0, $duration_compatibility );
		}

		// Response rate patterns.
		if ( $user1_history['message_response_rate'] && $user2_history['message_response_rate'] ) {
			$response_avg = ( $user1_history['message_response_rate'] + $user2_history['message_response_rate'] ) / 2;
			$success_factors['response'] = $response_avg;
		}

		// Profile completion correlation with success.
		$user1_profile = WPMatch_Matching_Algorithm::get_user_profile( $user1_id );
		$user2_profile = WPMatch_Matching_Algorithm::get_user_profile( $user2_id );

		if ( $user1_profile && $user2_profile ) {
			$completion_avg = ( $user1_profile->profile_completion + $user2_profile->profile_completion ) / 200;
			$success_factors['completion'] = $completion_avg;
		}

		// Calculate weighted prediction.
		if ( empty( $success_factors ) ) {
			return 0.5; // Default neutral prediction.
		}

		$weights = array(
			'duration'   => 0.4,
			'response'   => 0.35,
			'completion' => 0.25,
		);

		$prediction_score = 0;
		$total_weight = 0;

		foreach ( $success_factors as $factor => $score ) {
			$weight = isset( $weights[ $factor ] ) ? $weights[ $factor ] : 0.1;
			$prediction_score += $score * $weight;
			$total_weight += $weight;
		}

		return $total_weight > 0 ? $prediction_score / $total_weight : 0.5;
	}

	/**
	 * Apply advanced sorting to match queue.
	 *
	 * @param array $queue Current match queue.
	 * @param int   $user_id User ID.
	 * @return array Enhanced match queue.
	 */
	public static function apply_advanced_sorting( $queue, $user_id ) {
		if ( empty( $queue ) ) {
			return $queue;
		}

		$user_id = absint( $user_id );
		$enhanced_queue = array();

		foreach ( $queue as $queue_item ) {
			$queue_item->boost_score = 0;
			$queue_item->final_score = $queue_item->compatibility_score;

			// Apply recency boost.
			$recency_boost = self::calculate_recency_boost( $queue_item->potential_match_id );
			$queue_item->boost_score += $recency_boost;

			// Apply membership tier boost.
			$membership_boost = self::calculate_membership_boost( $queue_item->potential_match_id );
			$queue_item->boost_score += $membership_boost;

			// Apply behavioral boost.
			$behavioral_boost = self::calculate_behavioral_boost( $user_id, $queue_item->potential_match_id );
			$queue_item->boost_score += $behavioral_boost;

			// Apply final score with boosts.
			$queue_item->final_score = $queue_item->compatibility_score * ( 1 + $queue_item->boost_score );

			$enhanced_queue[] = $queue_item;
		}

		// Sort by final score.
		usort(
			$enhanced_queue,
			function ( $a, $b ) {
				return $b->final_score <=> $a->final_score;
			}
		);

		return $enhanced_queue;
	}

	/**
	 * Learn from user swipe behavior.
	 *
	 * @param int    $swipe_id Swipe ID.
	 * @param int    $user_id User who swiped.
	 * @param int    $target_user_id Target of swipe.
	 * @param string $swipe_type Type of swipe.
	 * @param bool   $is_match Whether it resulted in a match.
	 */
	public static function learn_from_swipe( $swipe_id, $user_id, $target_user_id, $swipe_type, $is_match ) {
		$user_id = absint( $user_id );
		$target_user_id = absint( $target_user_id );

		if ( ! $user_id || ! $target_user_id ) {
			return;
		}

		// Get target user profile for learning.
		$target_profile = WPMatch_Matching_Algorithm::get_user_profile( $target_user_id );
		if ( ! $target_profile ) {
			return;
		}

		// Update learned preferences.
		self::update_learned_preferences( $user_id, $target_profile, $swipe_type );

		// Update behavioral patterns.
		self::update_behavioral_patterns( $user_id, $swipe_type );

		// If it's a match, strengthen the learning signal.
		if ( $is_match ) {
			self::reinforce_successful_patterns( $user_id, $target_user_id );
		}
	}

	/**
	 * Learn from messaging behavior.
	 *
	 * @param int $message_id Message ID.
	 * @param int $sender_id Sender user ID.
	 * @param int $recipient_id Recipient user ID.
	 */
	public static function learn_from_message( $message_id, $sender_id, $recipient_id ) {
		// Update communication patterns.
		self::update_communication_patterns( $sender_id );

		// Strengthen match if users are actively messaging.
		self::strengthen_active_match( $sender_id, $recipient_id );
	}

	/**
	 * Update machine learning models (scheduled daily).
	 */
	public static function update_machine_learning_models() {
		// Legacy method name - redirect to new method.
		self::update_ml_models();
	}

	/**
	 * Update machine learning models.
	 *
	 * @since 1.1.0
	 */
	public static function update_ml_models() {
		// This would update ML models with new data.
		// For this implementation, we'll update statistical models.

		self::update_global_preference_patterns();
		self::update_success_prediction_models();
		self::cleanup_old_learning_data();
	}

	/**
	 * Helper methods for data retrieval and analysis.
	 */

	private static function get_user_behavioral_patterns( $user_id, $cutoff_date ) {
		global $wpdb;

		$swipes_table = $wpdb->prefix . 'wpmatch_swipes';

		// Get swipe data for analysis.
		$swipe_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT swipe_type, created_at,
				 HOUR(created_at) as hour,
				 DAYOFWEEK(created_at) as day_of_week
				 FROM {$swipes_table}
				 WHERE user_id = %d AND created_at > %s
				 ORDER BY created_at",
				$user_id,
				$cutoff_date
			)
		);

		if ( empty( $swipe_data ) ) {
			return null;
		}

		$total_swipes = count( $swipe_data );
		$likes = array_filter( $swipe_data, function( $swipe ) {
			return in_array( $swipe->swipe_type, array( 'like', 'super_like' ) );
		});

		$patterns = array(
			'selectivity' => count( $likes ) / max( 1, $total_swipes ),
			'active_hours' => self::extract_active_hours( $swipe_data ),
			'avg_response_time' => self::calculate_avg_response_time( $user_id ),
			'avg_session_length' => self::calculate_avg_session_length( $user_id ),
		);

		return $patterns;
	}

	private static function get_learned_preferences( $user_id ) {
		$preferences = get_user_meta( $user_id, '_wpmatch_learned_preferences', true );

		if ( ! is_array( $preferences ) || empty( $preferences ) ) {
			return null;
		}

		// Filter preferences with sufficient learning data.
		$filtered_preferences = array();
		foreach ( $preferences as $preference => $data ) {
			if ( isset( $data['confidence'] ) && $data['confidence'] >= self::PREFERENCE_LEARNING_MIN ) {
				$filtered_preferences[ $preference ] = $data['weight'];
			}
		}

		return empty( $filtered_preferences ) ? null : $filtered_preferences;
	}

	private static function get_communication_patterns( $user_id ) {
		global $wpdb;

		$messages_table = $wpdb->prefix . 'wpmatch_messages';
		$cutoff_date = date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

		$comm_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT message_content, created_at
				 FROM {$messages_table}
				 WHERE sender_id = %d AND created_at > %s",
				$user_id,
				$cutoff_date
			)
		);

		if ( empty( $comm_data ) ) {
			return null;
		}

		$total_messages = count( $comm_data );
		$total_length = 0;
		$emoji_count = 0;
		$question_count = 0;

		foreach ( $comm_data as $message ) {
			$length = strlen( $message->message_content );
			$total_length += $length;

			// Count emojis (simplified detection).
			$emoji_count += preg_match_all( '/[\x{1F600}-\x{1F64F}]|[\x{1F300}-\x{1F5FF}]|[\x{1F680}-\x{1F6FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}]/u', $message->message_content );

			// Count questions.
			$question_count += substr_count( $message->message_content, '?' );
		}

		return array(
			'avg_message_length' => $total_length / $total_messages,
			'messages_per_day' => $total_messages / 30,
			'emoji_usage' => $emoji_count / $total_messages,
			'question_ratio' => $question_count / $total_messages,
		);
	}

	private static function extract_active_hours( $swipe_data ) {
		$hour_activity = array();

		foreach ( $swipe_data as $swipe ) {
			$day_hour_key = $swipe->day_of_week . '_' . $swipe->hour;
			if ( ! isset( $hour_activity[ $day_hour_key ] ) ) {
				$hour_activity[ $day_hour_key ] = 0;
			}
			$hour_activity[ $day_hour_key ]++;
		}

		// Normalize activity levels.
		$max_activity = max( array_values( $hour_activity ) );
		foreach ( $hour_activity as $key => $activity ) {
			$hour_activity[ $key ] = $activity / $max_activity;
		}

		return $hour_activity;
	}

	private static function calculate_recency_boost( $user_id ) {
		$user_profile = WPMatch_Matching_Algorithm::get_user_profile( $user_id );

		if ( ! $user_profile || ! $user_profile->last_active ) {
			return 0;
		}

		$hours_since_active = ( current_time( 'timestamp' ) - strtotime( $user_profile->last_active ) ) / 3600;

		if ( $hours_since_active <= self::RECENCY_BOOST_HOURS ) {
			return ( self::RECENCY_BOOST_HOURS - $hours_since_active ) / self::RECENCY_BOOST_HOURS * 0.3;
		}

		return 0;
	}

	private static function calculate_membership_boost( $user_id ) {
		$membership_level = WPMatch_Membership_Manager::get_user_membership_level( $user_id );

		$boost_map = array(
			'free'     => 0,
			'basic'    => 0.1,
			'gold'     => 0.2,
			'platinum' => 0.3,
		);

		return isset( $boost_map[ $membership_level ] ) ? $boost_map[ $membership_level ] : 0;
	}

	private static function update_learned_preferences( $user_id, $target_profile, $swipe_type ) {
		$preferences = get_user_meta( $user_id, '_wpmatch_learned_preferences', true );
		if ( ! is_array( $preferences ) ) {
			$preferences = array();
		}

		$weight_adjustment = ( 'like' === $swipe_type || 'super_like' === $swipe_type ) ? 1 : -1;
		if ( 'super_like' === $swipe_type ) {
			$weight_adjustment = 2; // Stronger signal.
		}

		// Learn age preferences.
		if ( $target_profile->age ) {
			$age_key = 'age_' . intval( $target_profile->age / 5 ) * 5; // Group by 5-year ranges.
			self::update_preference_weight( $preferences, $age_key, $weight_adjustment );
		}

		// Learn location preferences.
		if ( $target_profile->city ) {
			$location_key = 'location_' . sanitize_key( $target_profile->city );
			self::update_preference_weight( $preferences, $location_key, $weight_adjustment );
		}

		// Learn education preferences.
		if ( $target_profile->education_level ) {
			$education_key = 'education_' . sanitize_key( $target_profile->education_level );
			self::update_preference_weight( $preferences, $education_key, $weight_adjustment );
		}

		update_user_meta( $user_id, '_wpmatch_learned_preferences', $preferences );
	}

	private static function update_preference_weight( &$preferences, $key, $adjustment ) {
		if ( ! isset( $preferences[ $key ] ) ) {
			$preferences[ $key ] = array(
				'weight' => 0,
				'confidence' => 0,
			);
		}

		$preferences[ $key ]['weight'] += $adjustment * 0.1;
		$preferences[ $key ]['confidence']++;

		// Normalize weight between -1 and 1.
		$preferences[ $key ]['weight'] = max( -1, min( 1, $preferences[ $key ]['weight'] ) );
	}

	private static function get_ml_confidence( $user1_id, $user2_id ) {
		// Calculate confidence based on available data.
		$user1_swipe_count = self::get_user_swipe_count( $user1_id );
		$user2_swipe_count = self::get_user_swipe_count( $user2_id );

		$min_swipes_for_confidence = 50;
		$confidence = min( $user1_swipe_count, $user2_swipe_count ) / $min_swipes_for_confidence;

		return min( 1.0, $confidence );
	}

	private static function get_user_swipe_count( $user_id ) {
		global $wpdb;

		$swipes_table = $wpdb->prefix . 'wpmatch_swipes';

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$swipes_table} WHERE user_id = %d",
				$user_id
			)
		);
	}

	// Placeholder methods for additional functionality.
	private static function calculate_activity_time_overlap( $schedule1, $schedule2 ) {
		// Implementation for calculating time overlap.
		return 0.5; // Placeholder.
	}

	private static function does_profile_match_preference( $profile, $preference, $weight ) {
		// Implementation for checking if profile matches learned preference.
		return 0.5; // Placeholder.
	}

	private static function get_user_activity_schedule( $user_id ) {
		// Implementation for getting user activity schedule.
		return null; // Placeholder.
	}

	private static function get_location_social_score( $user1_id, $user2_id ) {
		// Implementation for location-based social scoring.
		return 0.5; // Placeholder.
	}

	private static function get_user_relationship_history( $user_id ) {
		// Implementation for getting relationship history.
		return array(
			'avg_relationship_duration' => null,
			'message_response_rate' => null,
		); // Placeholder.
	}

	private static function calculate_avg_response_time( $user_id ) {
		// Implementation for calculating average response time.
		return 3600; // Placeholder: 1 hour.
	}

	private static function calculate_avg_session_length( $user_id ) {
		// Implementation for calculating average session length.
		return 1800; // Placeholder: 30 minutes.
	}

	private static function calculate_behavioral_boost( $user_id, $target_user_id ) {
		// Implementation for behavioral boost calculation.
		return 0; // Placeholder.
	}

	private static function update_behavioral_patterns( $user_id, $swipe_type ) {
		// Implementation for updating behavioral patterns.
	}

	private static function reinforce_successful_patterns( $user_id, $target_user_id ) {
		// Implementation for reinforcing successful patterns.
	}

	private static function update_communication_patterns( $user_id ) {
		// Implementation for updating communication patterns.
	}

	private static function strengthen_active_match( $sender_id, $recipient_id ) {
		// Implementation for strengthening active matches.
	}

	private static function update_global_preference_patterns() {
		// Implementation for updating global patterns.
	}

	private static function update_success_prediction_models() {
		// Implementation for updating success prediction models.
	}

	private static function cleanup_old_learning_data() {
		// Implementation for cleaning up old learning data.
	}


	/**
	 * Track user interaction for learning.
	 *
	 * @since 1.1.0
	 * @param int    $user_id User ID.
	 * @param int    $target_user_id Target user ID.
	 * @param string $interaction_type Type of interaction.
	 * @param mixed  $data Additional data.
	 */
	public static function track_user_interaction( $user_id, $target_user_id, $interaction_type, $data = null ) {
		global $wpdb;

		$interactions_table = $wpdb->prefix . 'wpmatch_user_interactions';

		$wpdb->insert(
			$interactions_table,
			array(
				'user_id'          => absint( $user_id ),
				'target_user_id'   => absint( $target_user_id ),
				'interaction_type' => sanitize_text_field( $interaction_type ),
				'interaction_data' => maybe_serialize( $data ),
				'created_at'       => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Add admin settings for advanced matching.
	 *
	 * @since 1.1.0
	 * @param array $sections Existing settings sections.
	 * @return array Updated settings sections.
	 */
	public static function add_admin_settings( $sections ) {
		$sections['advanced_matching'] = array(
			'title'    => esc_html__( 'Advanced Matching', 'wpmatch' ),
			'priority' => 35,
			'fields'   => array(
				'enable_ml_matching' => array(
					'title'       => esc_html__( 'Enable ML Matching', 'wpmatch' ),
					'type'        => 'checkbox',
					'description' => esc_html__( 'Enable machine learning-based compatibility scoring.', 'wpmatch' ),
					'default'     => true,
				),
				'enable_behavioral_analysis' => array(
					'title'       => esc_html__( 'Behavioral Analysis', 'wpmatch' ),
					'type'        => 'checkbox',
					'description' => esc_html__( 'Analyze user behavior patterns for better matching.', 'wpmatch' ),
					'default'     => true,
				),
				'enable_preference_learning' => array(
					'title'       => esc_html__( 'Preference Learning', 'wpmatch' ),
					'type'        => 'checkbox',
					'description' => esc_html__( 'Learn from user preferences and adapt matching accordingly.', 'wpmatch' ),
					'default'     => true,
				),
				'ml_weight_threshold' => array(
					'title'       => esc_html__( 'ML Weight Threshold', 'wpmatch' ),
					'type'        => 'number',
					'description' => esc_html__( 'Minimum threshold for machine learning weight adjustments.', 'wpmatch' ),
					'default'     => 0.7,
					'min'         => 0.1,
					'max'         => 1.0,
					'step'        => 0.1,
				),
			),
		);

		return $sections;
	}

	/**
	 * Clean up old behavioral data.
	 *
	 * @since 1.1.0
	 */
	public static function cleanup_old_data() {
		global $wpdb;

		$interactions_table = $wpdb->prefix . 'wpmatch_user_interactions';

		// Delete interactions older than 90 days.
		$wpdb->query(
			"DELETE FROM {$interactions_table}
			WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
		);
	}

	/**
	 * Create database tables for advanced matching.
	 *
	 * @since 1.1.0
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// User interactions table for behavioral analysis.
		$interactions_table = $wpdb->prefix . 'wpmatch_user_interactions';
		$sql_interactions = "CREATE TABLE $interactions_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			target_user_id bigint(20) NOT NULL,
			interaction_type varchar(50) NOT NULL,
			interaction_data longtext,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY target_user_id (target_user_id),
			KEY interaction_type (interaction_type),
			KEY created_at (created_at)
		) $charset_collate;";

		// User behavioral patterns table.
		$patterns_table = $wpdb->prefix . 'wpmatch_behavioral_patterns';
		$sql_patterns = "CREATE TABLE $patterns_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			pattern_type varchar(50) NOT NULL,
			pattern_data longtext,
			confidence_score decimal(3,2) DEFAULT 0.00,
			last_updated datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY user_pattern (user_id, pattern_type),
			KEY pattern_type (pattern_type),
			KEY confidence_score (confidence_score)
		) $charset_collate;";

		// ML model weights table.
		$weights_table = $wpdb->prefix . 'wpmatch_ml_weights';
		$sql_weights = "CREATE TABLE $weights_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			weight_type varchar(50) NOT NULL,
			weight_key varchar(100) NOT NULL,
			weight_value decimal(5,4) NOT NULL,
			last_updated datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY weight_key (weight_type, weight_key),
			KEY weight_type (weight_type)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_interactions );
		dbDelta( $sql_patterns );
		dbDelta( $sql_weights );
	}
}