<?php
/**
 * WPMatch Machine Learning Matching Engine
 *
 * Advanced matching algorithms with machine learning capabilities
 *
 * @package WPMatch
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPMatch_ML_Matching {

	private static $instance = null;

	const MATCHING_TABLE = 'wpmatch_ml_matching_data';
	const FEEDBACK_TABLE = 'wpmatch_ml_feedback';
	const MODEL_TABLE = 'wpmatch_ml_models';

	private $feature_weights = array();
	private $user_vectors = array();
	private $model_version = '1.0';

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->init_feature_weights();
		$this->init_hooks();
	}

	private function init_feature_weights() {
		$this->feature_weights = get_option( 'wpmatch_ml_feature_weights', array(
			'age_compatibility'       => 0.15,
			'location_proximity'      => 0.20,
			'interests_similarity'    => 0.25,
			'lifestyle_compatibility' => 0.15,
			'personality_match'       => 0.20,
			'activity_level'          => 0.05,
		) );
	}

	private function init_hooks() {
		add_action( 'init', array( $this, 'create_ml_tables' ) );
		add_action( 'wpmatch_user_swipe', array( $this, 'record_swipe_feedback' ), 10, 3 );
		add_action( 'wpmatch_match_created', array( $this, 'record_match_success' ), 10, 2 );
		add_action( 'wpmatch_message_sent', array( $this, 'record_conversation_start' ), 10, 2 );
		add_action( 'wpmatch_profile_updated', array( $this, 'update_user_vector' ) );

		// Scheduled events
		add_action( 'wpmatch_ml_train_models', array( $this, 'train_recommendation_models' ) );
		add_action( 'wpmatch_ml_update_vectors', array( $this, 'batch_update_user_vectors' ) );

		// AJAX handlers
		add_action( 'wp_ajax_wpmatch_get_ml_recommendations', array( $this, 'ajax_get_recommendations' ) );
		add_action( 'wp_ajax_wpmatch_update_preferences', array( $this, 'ajax_update_preferences' ) );

		// Schedule events if not already scheduled
		if ( ! wp_next_scheduled( 'wpmatch_ml_train_models' ) ) {
			wp_schedule_event( time(), 'daily', 'wpmatch_ml_train_models' );
		}

		if ( ! wp_next_scheduled( 'wpmatch_ml_update_vectors' ) ) {
			wp_schedule_event( time(), 'hourly', 'wpmatch_ml_update_vectors' );
		}
	}

	public function create_ml_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// ML matching data table
		$matching_table = $wpdb->prefix . self::MATCHING_TABLE;
		$matching_sql = "CREATE TABLE IF NOT EXISTS $matching_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			feature_vector longtext NOT NULL,
			preferences longtext NOT NULL,
			last_updated datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_id (user_id),
			KEY last_updated (last_updated)
		) $charset_collate;";

		// ML feedback table
		$feedback_table = $wpdb->prefix . self::FEEDBACK_TABLE;
		$feedback_sql = "CREATE TABLE IF NOT EXISTS $feedback_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			target_user_id bigint(20) NOT NULL,
			action_type varchar(20) NOT NULL,
			feedback_score decimal(3,2) NOT NULL,
			context_data longtext DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY target_user_id (target_user_id),
			KEY action_type (action_type),
			KEY created_at (created_at)
		) $charset_collate;";

		// ML models table
		$models_table = $wpdb->prefix . self::MODEL_TABLE;
		$models_sql = "CREATE TABLE IF NOT EXISTS $models_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			model_name varchar(100) NOT NULL,
			model_version varchar(20) NOT NULL,
			model_data longtext NOT NULL,
			accuracy_score decimal(5,4) DEFAULT NULL,
			training_date datetime NOT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY model_version_unique (model_name, model_version),
			KEY is_active (is_active)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $matching_sql );
		dbDelta( $feedback_sql );
		dbDelta( $models_sql );
	}

	public function get_ml_recommendations( $user_id, $limit = 20, $exclude_ids = array() ) {
		$user_vector = $this->get_user_vector( $user_id );

		if ( ! $user_vector ) {
			// Fallback to traditional matching
			return $this->get_traditional_matches( $user_id, $limit, $exclude_ids );
		}

		$candidates = $this->get_candidate_users( $user_id, $exclude_ids );
		$scored_candidates = array();

		foreach ( $candidates as $candidate ) {
			$candidate_vector = $this->get_user_vector( $candidate->ID );

			if ( $candidate_vector ) {
				$compatibility_score = $this->calculate_compatibility_score(
					$user_vector,
					$candidate_vector,
					$user_id,
					$candidate->ID
				);

				$scored_candidates[] = array(
					'user_id' => $candidate->ID,
					'score'   => $compatibility_score,
					'reasons' => $this->get_match_reasons( $user_vector, $candidate_vector ),
				);
			}
		}

		// Sort by compatibility score
		usort( $scored_candidates, function( $a, $b ) {
			return $b['score'] <=> $a['score'];
		} );

		return array_slice( $scored_candidates, 0, $limit );
	}

	private function calculate_compatibility_score( $user_vector, $candidate_vector, $user_id, $candidate_id ) {
		$total_score = 0;
		$weighted_features = array(
			'age_compatibility'       => $this->calculate_age_compatibility( $user_vector, $candidate_vector ),
			'location_proximity'      => $this->calculate_location_proximity( $user_vector, $candidate_vector ),
			'interests_similarity'    => $this->calculate_interests_similarity( $user_vector, $candidate_vector ),
			'lifestyle_compatibility' => $this->calculate_lifestyle_compatibility( $user_vector, $candidate_vector ),
			'personality_match'       => $this->calculate_personality_match( $user_vector, $candidate_vector ),
			'activity_level'          => $this->calculate_activity_compatibility( $user_vector, $candidate_vector ),
		);

		foreach ( $weighted_features as $feature => $score ) {
			$weight = isset( $this->feature_weights[ $feature ] ) ? $this->feature_weights[ $feature ] : 0.1;
			$total_score += $score * $weight;
		}

		// Apply machine learning adjustments
		$ml_adjustment = $this->get_ml_score_adjustment( $user_id, $candidate_id, $weighted_features );
		$total_score += $ml_adjustment;

		// Apply user feedback learning
		$feedback_adjustment = $this->get_feedback_adjustment( $user_id, $weighted_features );
		$total_score += $feedback_adjustment;

		return max( 0, min( 100, $total_score ) );
	}

	private function calculate_age_compatibility( $user_vector, $candidate_vector ) {
		$user_age = $user_vector['demographics']['age'] ?? 25;
		$candidate_age = $candidate_vector['demographics']['age'] ?? 25;
		$user_age_range = $user_vector['preferences']['age_range'] ?? array( 'min' => 18, 'max' => 99 );

		// Check if candidate age is within user's preferred range
		if ( $candidate_age < $user_age_range['min'] || $candidate_age > $user_age_range['max'] ) {
			return 0;
		}

		// Calculate age compatibility based on distance from ideal age
		$ideal_age = ( $user_age_range['min'] + $user_age_range['max'] ) / 2;
		$age_distance = abs( $candidate_age - $ideal_age );
		$max_distance = max( $ideal_age - $user_age_range['min'], $user_age_range['max'] - $ideal_age );

		return max( 0, 100 - ( $age_distance / $max_distance * 100 ) );
	}

	private function calculate_location_proximity( $user_vector, $candidate_vector ) {
		$user_location = $user_vector['demographics']['location'] ?? array();
		$candidate_location = $candidate_vector['demographics']['location'] ?? array();

		if ( empty( $user_location['lat'] ) || empty( $candidate_location['lat'] ) ) {
			return 50; // Neutral score if location data is missing
		}

		$distance = $this->calculate_distance(
			$user_location['lat'],
			$user_location['lng'],
			$candidate_location['lat'],
			$candidate_location['lng']
		);

		$max_distance = $user_vector['preferences']['max_distance'] ?? 50;

		if ( $distance > $max_distance ) {
			return 0;
		}

		// Higher score for closer proximity
		return max( 0, 100 - ( $distance / $max_distance * 100 ) );
	}

	private function calculate_interests_similarity( $user_vector, $candidate_vector ) {
		$user_interests = $user_vector['interests'] ?? array();
		$candidate_interests = $candidate_vector['interests'] ?? array();

		if ( empty( $user_interests ) || empty( $candidate_interests ) ) {
			return 30; // Neutral score if no interests
		}

		$common_interests = array_intersect( $user_interests, $candidate_interests );
		$total_interests = array_unique( array_merge( $user_interests, $candidate_interests ) );

		$similarity = count( $common_interests ) / count( $total_interests );
		return $similarity * 100;
	}

	private function calculate_lifestyle_compatibility( $user_vector, $candidate_vector ) {
		$user_lifestyle = $user_vector['lifestyle'] ?? array();
		$candidate_lifestyle = $candidate_vector['lifestyle'] ?? array();

		$compatibility_factors = array(
			'smoking'        => $this->compare_lifestyle_factor( $user_lifestyle['smoking'] ?? '', $candidate_lifestyle['smoking'] ?? '' ),
			'drinking'       => $this->compare_lifestyle_factor( $user_lifestyle['drinking'] ?? '', $candidate_lifestyle['drinking'] ?? '' ),
			'exercise'       => $this->compare_lifestyle_factor( $user_lifestyle['exercise'] ?? '', $candidate_lifestyle['exercise'] ?? '' ),
			'diet'           => $this->compare_lifestyle_factor( $user_lifestyle['diet'] ?? '', $candidate_lifestyle['diet'] ?? '' ),
			'pets'           => $this->compare_lifestyle_factor( $user_lifestyle['pets'] ?? '', $candidate_lifestyle['pets'] ?? '' ),
			'relationship_goals' => $this->compare_lifestyle_factor( $user_lifestyle['relationship_goals'] ?? '', $candidate_lifestyle['relationship_goals'] ?? '' ),
		);

		return array_sum( $compatibility_factors ) / count( $compatibility_factors );
	}

	private function calculate_personality_match( $user_vector, $candidate_vector ) {
		$user_personality = $user_vector['personality'] ?? array();
		$candidate_personality = $candidate_vector['personality'] ?? array();

		if ( empty( $user_personality ) || empty( $candidate_personality ) ) {
			return 50; // Neutral score
		}

		// Big Five personality traits matching
		$traits = array( 'openness', 'conscientiousness', 'extraversion', 'agreeableness', 'neuroticism' );
		$compatibility_scores = array();

		foreach ( $traits as $trait ) {
			$user_score = $user_personality[ $trait ] ?? 50;
			$candidate_score = $candidate_personality[ $trait ] ?? 50;

			// Some traits work better with similarity, others with complementarity
			if ( in_array( $trait, array( 'agreeableness', 'conscientiousness' ), true ) ) {
				// Similarity preferred
				$compatibility_scores[] = 100 - abs( $user_score - $candidate_score );
			} else {
				// Some complementarity can be good
				$difference = abs( $user_score - $candidate_score );
				$optimal_difference = 20; // Sweet spot for complementarity
				if ( $difference <= $optimal_difference ) {
					$compatibility_scores[] = 100 - ( $difference / $optimal_difference * 30 );
				} else {
					$compatibility_scores[] = 70 - ( ( $difference - $optimal_difference ) / 80 * 70 );
				}
			}
		}

		return array_sum( $compatibility_scores ) / count( $compatibility_scores );
	}

	private function calculate_activity_compatibility( $user_vector, $candidate_vector ) {
		$user_activity = $user_vector['activity'] ?? array();
		$candidate_activity = $candidate_vector['activity'] ?? array();

		$user_score = $user_activity['level'] ?? 50;
		$candidate_score = $candidate_activity['level'] ?? 50;

		// Activity levels should be somewhat compatible
		$difference = abs( $user_score - $candidate_score );
		return max( 0, 100 - $difference );
	}

	private function compare_lifestyle_factor( $user_value, $candidate_value ) {
		if ( empty( $user_value ) || empty( $candidate_value ) ) {
			return 50; // Neutral if no data
		}

		// Define compatibility matrix
		$compatibility_matrix = array(
			'smoking' => array(
				'never'      => array( 'never' => 100, 'occasionally' => 20, 'regularly' => 0 ),
				'occasionally' => array( 'never' => 60, 'occasionally' => 100, 'regularly' => 70 ),
				'regularly'  => array( 'never' => 10, 'occasionally' => 70, 'regularly' => 100 ),
			),
			'drinking' => array(
				'never'      => array( 'never' => 100, 'socially' => 40, 'regularly' => 10 ),
				'socially'   => array( 'never' => 60, 'socially' => 100, 'regularly' => 80 ),
				'regularly'  => array( 'never' => 20, 'socially' => 80, 'regularly' => 100 ),
			),
		);

		// For factors not in matrix, use exact match or partial compatibility
		if ( $user_value === $candidate_value ) {
			return 100;
		}

		return 30; // Partial compatibility for different values
	}

	private function get_ml_score_adjustment( $user_id, $candidate_id, $features ) {
		// This would integrate with actual ML models
		// For now, we'll simulate ML-based adjustments

		$user_preferences = $this->get_learned_user_preferences( $user_id );
		$adjustment = 0;

		// Adjust based on learned user behavior
		foreach ( $features as $feature => $score ) {
			$preference_weight = $user_preferences[ $feature ] ?? 1.0;
			$adjustment += ( $score - 50 ) * ( $preference_weight - 1.0 ) * 0.1;
		}

		return $adjustment;
	}

	private function get_feedback_adjustment( $user_id, $features ) {
		global $wpdb;

		// Get recent feedback patterns
		$recent_feedback = $wpdb->get_results( $wpdb->prepare(
			"SELECT action_type, AVG(feedback_score) as avg_score, COUNT(*) as count
			FROM {$wpdb->prefix}wpmatch_ml_feedback
			WHERE user_id = %d AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
			GROUP BY action_type",
			$user_id
		) );

		$adjustment = 0;
		$total_feedback = 0;

		foreach ( $recent_feedback as $feedback ) {
			$total_feedback += $feedback->count;

			if ( 'like' === $feedback->action_type && $feedback->avg_score > 0.7 ) {
				$adjustment += 5; // Boost similar profiles
			} elseif ( 'pass' === $feedback->action_type && $feedback->avg_score < 0.3 ) {
				$adjustment -= 3; // Reduce similar profiles
			}
		}

		// Normalize adjustment based on feedback volume
		if ( $total_feedback > 0 ) {
			$adjustment = $adjustment * min( 1.0, $total_feedback / 50 );
		}

		return $adjustment;
	}

	public function update_user_vector( $user_id ) {
		$vector = $this->build_user_vector( $user_id );

		global $wpdb;

		$wpdb->replace(
			$wpdb->prefix . self::MATCHING_TABLE,
			array(
				'user_id'        => $user_id,
				'feature_vector' => wp_json_encode( $vector ),
				'preferences'    => wp_json_encode( $this->get_user_preferences( $user_id ) ),
			),
			array( '%d', '%s', '%s' )
		);
	}

	private function build_user_vector( $user_id ) {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return array();
		}

		$profile_data = get_user_meta( $user_id, 'wpmatch_profile', true );
		$profile_data = is_array( $profile_data ) ? $profile_data : array();

		return array(
			'demographics' => array(
				'age'      => $this->calculate_age( $profile_data['birth_date'] ?? '' ),
				'gender'   => $profile_data['gender'] ?? '',
				'location' => $profile_data['location'] ?? array(),
			),
			'interests' => $profile_data['interests'] ?? array(),
			'lifestyle' => array(
				'smoking'           => $profile_data['smoking'] ?? '',
				'drinking'          => $profile_data['drinking'] ?? '',
				'exercise'          => $profile_data['exercise'] ?? '',
				'diet'              => $profile_data['diet'] ?? '',
				'pets'              => $profile_data['pets'] ?? '',
				'relationship_goals' => $profile_data['relationship_goals'] ?? '',
			),
			'personality' => $this->get_personality_scores( $user_id ),
			'activity'   => $this->get_activity_metrics( $user_id ),
		);
	}

	private function get_personality_scores( $user_id ) {
		// This could integrate with personality tests or be inferred from behavior
		$saved_scores = get_user_meta( $user_id, 'wpmatch_personality_scores', true );

		if ( is_array( $saved_scores ) && ! empty( $saved_scores ) ) {
			return $saved_scores;
		}

		// Default neutral scores
		return array(
			'openness'         => 50,
			'conscientiousness' => 50,
			'extraversion'     => 50,
			'agreeableness'    => 50,
			'neuroticism'      => 50,
		);
	}

	private function get_activity_metrics( $user_id ) {
		global $wpdb;

		$thirty_days_ago = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );

		// Calculate activity level based on app usage
		$activity_data = $wpdb->get_row( $wpdb->prepare(
			"SELECT
				COUNT(DISTINCT DATE(created_at)) as active_days,
				COUNT(*) as total_actions,
				AVG(CASE WHEN action = 'like' THEN 1 ELSE 0 END) as like_ratio
			FROM {$wpdb->prefix}wpmatch_user_actions
			WHERE user_id = %d AND created_at >= %s",
			$user_id,
			$thirty_days_ago
		) );

		$activity_level = 0;
		if ( $activity_data ) {
			$activity_level = min( 100, ( $activity_data->active_days / 30 ) * 100 );
		}

		return array(
			'level'      => $activity_level,
			'like_ratio' => $activity_data->like_ratio ?? 0.5,
		);
	}

	public function record_swipe_feedback( $user_id, $target_user_id, $action ) {
		$score = 'like' === $action ? 1.0 : 0.0;

		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . self::FEEDBACK_TABLE,
			array(
				'user_id'        => $user_id,
				'target_user_id' => $target_user_id,
				'action_type'    => $action,
				'feedback_score' => $score,
				'context_data'   => wp_json_encode( array(
					'timestamp' => time(),
					'source'    => 'swipe',
				) ),
			),
			array( '%d', '%d', '%s', '%f', '%s' )
		);

		// Update user preferences based on feedback
		$this->update_learned_preferences( $user_id, $target_user_id, $score );
	}

	public function record_match_success( $user_id, $target_user_id ) {
		global $wpdb;

		// Record high-value feedback for both users
		$wpdb->insert(
			$wpdb->prefix . self::FEEDBACK_TABLE,
			array(
				'user_id'        => $user_id,
				'target_user_id' => $target_user_id,
				'action_type'    => 'match',
				'feedback_score' => 1.0,
				'context_data'   => wp_json_encode( array(
					'timestamp' => time(),
					'source'    => 'match',
				) ),
			),
			array( '%d', '%d', '%s', '%f', '%s' )
		);
	}

	public function record_conversation_start( $user_id, $target_user_id ) {
		global $wpdb;

		// High-value feedback indicating strong interest
		$wpdb->insert(
			$wpdb->prefix . self::FEEDBACK_TABLE,
			array(
				'user_id'        => $user_id,
				'target_user_id' => $target_user_id,
				'action_type'    => 'message',
				'feedback_score' => 1.0,
				'context_data'   => wp_json_encode( array(
					'timestamp' => time(),
					'source'    => 'conversation',
				) ),
			),
			array( '%d', '%d', '%s', '%f', '%s' )
		);
	}

	private function update_learned_preferences( $user_id, $target_user_id, $feedback_score ) {
		$user_vector = $this->get_user_vector( $user_id );
		$target_vector = $this->get_user_vector( $target_user_id );

		if ( ! $user_vector || ! $target_vector ) {
			return;
		}

		$current_preferences = $this->get_learned_user_preferences( $user_id );

		// Adjust preferences based on feedback
		$learning_rate = 0.05; // How quickly to adapt

		foreach ( $this->feature_weights as $feature => $weight ) {
			$feature_score = $this->calculate_feature_score( $feature, $user_vector, $target_vector );

			if ( $feature_score > 0 ) {
				$preference_adjustment = ( $feedback_score - 0.5 ) * $learning_rate;
				$current_preferences[ $feature ] = max( 0.1, min( 2.0,
					$current_preferences[ $feature ] + $preference_adjustment
				) );
			}
		}

		update_user_meta( $user_id, 'wpmatch_learned_preferences', $current_preferences );
	}

	private function calculate_feature_score( $feature, $user_vector, $target_vector ) {
		switch ( $feature ) {
			case 'age_compatibility':
				return $this->calculate_age_compatibility( $user_vector, $target_vector );
			case 'location_proximity':
				return $this->calculate_location_proximity( $user_vector, $target_vector );
			case 'interests_similarity':
				return $this->calculate_interests_similarity( $user_vector, $target_vector );
			case 'lifestyle_compatibility':
				return $this->calculate_lifestyle_compatibility( $user_vector, $target_vector );
			case 'personality_match':
				return $this->calculate_personality_match( $user_vector, $target_vector );
			case 'activity_level':
				return $this->calculate_activity_compatibility( $user_vector, $target_vector );
			default:
				return 0;
		}
	}

	private function get_learned_user_preferences( $user_id ) {
		$preferences = get_user_meta( $user_id, 'wpmatch_learned_preferences', true );

		if ( ! is_array( $preferences ) ) {
			$preferences = array();
		}

		// Set defaults for missing preferences
		foreach ( $this->feature_weights as $feature => $weight ) {
			if ( ! isset( $preferences[ $feature ] ) ) {
				$preferences[ $feature ] = 1.0;
			}
		}

		return $preferences;
	}

	private function get_user_vector( $user_id ) {
		global $wpdb;

		$cached_vector = wp_cache_get( 'user_vector_' . $user_id, 'wpmatch_ml' );
		if ( $cached_vector ) {
			return $cached_vector;
		}

		$result = $wpdb->get_var( $wpdb->prepare(
			"SELECT feature_vector FROM {$wpdb->prefix}wpmatch_ml_matching_data WHERE user_id = %d",
			$user_id
		) );

		if ( $result ) {
			$vector = json_decode( $result, true );
			wp_cache_set( 'user_vector_' . $user_id, $vector, 'wpmatch_ml', 3600 );
			return $vector;
		}

		// Build and cache vector if not exists
		$this->update_user_vector( $user_id );
		return $this->get_user_vector( $user_id );
	}

	private function get_user_preferences( $user_id ) {
		$profile_data = get_user_meta( $user_id, 'wpmatch_profile', true );

		return array(
			'age_range'    => $profile_data['age_preference'] ?? array( 'min' => 18, 'max' => 99 ),
			'max_distance' => $profile_data['max_distance'] ?? 50,
			'gender_preference' => $profile_data['gender_preference'] ?? array(),
		);
	}

	private function get_candidate_users( $user_id, $exclude_ids = array() ) {
		$user_preferences = $this->get_user_preferences( $user_id );
		$exclude_ids[] = $user_id;

		$args = array(
			'exclude'    => $exclude_ids,
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key'     => 'wpmatch_profile_complete',
					'value'   => '1',
					'compare' => '=',
				),
			),
			'number'     => 500, // Reasonable candidate pool
		);

		// Add gender filtering if specified
		if ( ! empty( $user_preferences['gender_preference'] ) ) {
			$args['meta_query'][] = array(
				'key'     => 'wpmatch_profile',
				'value'   => $user_preferences['gender_preference'],
				'compare' => 'LIKE',
			);
		}

		return get_users( $args );
	}

	private function get_traditional_matches( $user_id, $limit, $exclude_ids ) {
		// Fallback to existing matching algorithm
		$matching_algorithm = WPMatch_Matching_Algorithm::get_instance();
		return $matching_algorithm->find_matches( $user_id, $limit );
	}

	private function get_match_reasons( $user_vector, $candidate_vector ) {
		$reasons = array();

		// Check for strong compatibility factors
		if ( $this->calculate_interests_similarity( $user_vector, $candidate_vector ) > 70 ) {
			$common_interests = array_intersect(
				$user_vector['interests'] ?? array(),
				$candidate_vector['interests'] ?? array()
			);
			if ( ! empty( $common_interests ) ) {
				$reasons[] = sprintf(
					__( 'You both enjoy %s', 'wpmatch' ),
					implode( ', ', array_slice( $common_interests, 0, 3 ) )
				);
			}
		}

		if ( $this->calculate_location_proximity( $user_vector, $candidate_vector ) > 80 ) {
			$reasons[] = __( 'You\'re located nearby', 'wpmatch' );
		}

		if ( $this->calculate_age_compatibility( $user_vector, $candidate_vector ) > 90 ) {
			$reasons[] = __( 'You\'re in a similar age range', 'wpmatch' );
		}

		return $reasons;
	}

	private function calculate_age( $birth_date ) {
		if ( empty( $birth_date ) ) {
			return 25; // Default age
		}

		$birth_timestamp = strtotime( $birth_date );
		if ( ! $birth_timestamp ) {
			return 25;
		}

		return floor( ( time() - $birth_timestamp ) / ( 365.25 * 24 * 3600 ) );
	}

	private function calculate_distance( $lat1, $lng1, $lat2, $lng2 ) {
		$earth_radius = 6371; // Earth's radius in kilometers

		$lat1_rad = deg2rad( $lat1 );
		$lng1_rad = deg2rad( $lng1 );
		$lat2_rad = deg2rad( $lat2 );
		$lng2_rad = deg2rad( $lng2 );

		$delta_lat = $lat2_rad - $lat1_rad;
		$delta_lng = $lng2_rad - $lng1_rad;

		$a = sin( $delta_lat / 2 ) * sin( $delta_lat / 2 ) +
			 cos( $lat1_rad ) * cos( $lat2_rad ) *
			 sin( $delta_lng / 2 ) * sin( $delta_lng / 2 );

		$c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

		return $earth_radius * $c;
	}

	public function batch_update_user_vectors() {
		$users = get_users( array(
			'meta_key'   => 'wpmatch_profile_complete',
			'meta_value' => '1',
			'number'     => 100, // Process in batches
		) );

		foreach ( $users as $user ) {
			$this->update_user_vector( $user->ID );
		}
	}

	public function train_recommendation_models() {
		// This would implement actual ML model training
		// For now, we'll simulate the process and update feature weights

		global $wpdb;

		// Analyze successful matches to adjust feature weights
		$success_data = $wpdb->get_results(
			"SELECT f.*, u1.feature_vector as user1_vector, u2.feature_vector as user2_vector
			FROM {$wpdb->prefix}wpmatch_ml_feedback f
			JOIN {$wpdb->prefix}wpmatch_ml_matching_data u1 ON f.user_id = u1.user_id
			JOIN {$wpdb->prefix}wpmatch_ml_matching_data u2 ON f.target_user_id = u2.user_id
			WHERE f.action_type IN ('match', 'message')
			AND f.created_at > DATE_SUB(NOW(), INTERVAL 90 DAY)"
		);

		if ( count( $success_data ) > 50 ) {
			$this->analyze_and_update_weights( $success_data );
		}
	}

	private function analyze_and_update_weights( $success_data ) {
		$feature_performance = array();

		foreach ( $success_data as $data ) {
			$user1_vector = json_decode( $data->user1_vector, true );
			$user2_vector = json_decode( $data->user2_vector, true );

			if ( $user1_vector && $user2_vector ) {
				foreach ( $this->feature_weights as $feature => $weight ) {
					$score = $this->calculate_feature_score( $feature, $user1_vector, $user2_vector );

					if ( ! isset( $feature_performance[ $feature ] ) ) {
						$feature_performance[ $feature ] = array();
					}

					$feature_performance[ $feature ][] = $score;
				}
			}
		}

		// Update weights based on performance
		$new_weights = array();
		$total_weight = 0;

		foreach ( $feature_performance as $feature => $scores ) {
			$avg_score = array_sum( $scores ) / count( $scores );
			$new_weights[ $feature ] = max( 0.05, min( 0.40, $avg_score / 100 ) );
			$total_weight += $new_weights[ $feature ];
		}

		// Normalize weights to sum to 1
		foreach ( $new_weights as $feature => $weight ) {
			$this->feature_weights[ $feature ] = $weight / $total_weight;
		}

		update_option( 'wpmatch_ml_feature_weights', $this->feature_weights );
	}

	// AJAX handlers
	public function ajax_get_recommendations() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpmatch_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => 'User not logged in' ) );
		}

		$limit = absint( $_POST['limit'] ?? 20 );
		$exclude_ids = isset( $_POST['exclude_ids'] ) ? array_map( 'absint', $_POST['exclude_ids'] ) : array();

		$recommendations = $this->get_ml_recommendations( $user_id, $limit, $exclude_ids );

		wp_send_json_success( array(
			'recommendations' => $recommendations,
			'count'           => count( $recommendations ),
		) );
	}

	public function ajax_update_preferences() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpmatch_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => 'User not logged in' ) );
		}

		$preferences = isset( $_POST['preferences'] ) ? array_map( 'sanitize_text_field', $_POST['preferences'] ) : array();

		// Update user preferences and rebuild vector
		$current_profile = get_user_meta( $user_id, 'wpmatch_profile', true );
		$current_profile = is_array( $current_profile ) ? $current_profile : array();

		foreach ( $preferences as $key => $value ) {
			$current_profile[ $key ] = $value;
		}

		update_user_meta( $user_id, 'wpmatch_profile', $current_profile );
		$this->update_user_vector( $user_id );

		wp_send_json_success( array( 'message' => 'Preferences updated successfully' ) );
	}
}