<?php
/**
 * User Interests Management System
 *
 * Handles user interests, categories, and interest-based matching.
 *
 * @package WPMatch
 */

/**
 * User Interests Management Class
 *
 * Manages user interests with categories, subcategories, and matching algorithms.
 */
class WPMatch_User_Interests {

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
	 * Maximum interests per user.
	 *
	 * @var int
	 */
	private $max_interests = 50;

	/**
	 * Predefined interest categories.
	 *
	 * @var array
	 */
	private $default_categories = array(
		'hobbies'       => array(
			'name'      => 'Hobbies & Leisure',
			'interests' => array(
				'reading',
				'writing',
				'photography',
				'painting',
				'drawing',
				'cooking',
				'baking',
				'gardening',
				'knitting',
				'woodworking',
				'crafts',
				'collecting',
				'puzzles',
			),
		),
		'sports'        => array(
			'name'      => 'Sports & Fitness',
			'interests' => array(
				'running',
				'cycling',
				'swimming',
				'yoga',
				'pilates',
				'weightlifting',
				'basketball',
				'football',
				'soccer',
				'tennis',
				'golf',
				'hiking',
				'climbing',
				'skiing',
				'surfing',
			),
		),
		'music'         => array(
			'name'      => 'Music & Arts',
			'interests' => array(
				'classical',
				'rock',
				'pop',
				'jazz',
				'country',
				'electronic',
				'hip-hop',
				'blues',
				'folk',
				'opera',
				'concerts',
				'karaoke',
				'dancing',
				'theater',
				'museums',
			),
		),
		'entertainment' => array(
			'name'      => 'Entertainment',
			'interests' => array(
				'movies',
				'tv shows',
				'netflix',
				'gaming',
				'board games',
				'comedy',
				'stand-up',
				'podcasts',
				'youtube',
				'streaming',
				'documentaries',
				'anime',
				'comics',
			),
		),
		'travel'        => array(
			'name'      => 'Travel & Adventure',
			'interests' => array(
				'backpacking',
				'luxury travel',
				'camping',
				'road trips',
				'international travel',
				'cultural experiences',
				'adventure sports',
				'beach vacations',
				'city breaks',
			),
		),
		'food'          => array(
			'name'      => 'Food & Drink',
			'interests' => array(
				'wine tasting',
				'craft beer',
				'coffee',
				'fine dining',
				'street food',
				'vegetarian',
				'vegan',
				'asian cuisine',
				'italian cuisine',
				'mexican food',
				'bbq',
				'desserts',
			),
		),
		'technology'    => array(
			'name'      => 'Technology',
			'interests' => array(
				'programming',
				'ai',
				'crypto',
				'gadgets',
				'smartphones',
				'computers',
				'automation',
				'virtual reality',
				'drones',
				'robotics',
				'startups',
				'innovation',
			),
		),
		'lifestyle'     => array(
			'name'      => 'Lifestyle',
			'interests' => array(
				'fashion',
				'beauty',
				'wellness',
				'meditation',
				'spirituality',
				'volunteering',
				'environment',
				'sustainability',
				'minimalism',
				'luxury',
				'shopping',
			),
		),
		'learning'      => array(
			'name'      => 'Learning & Growth',
			'interests' => array(
				'languages',
				'history',
				'science',
				'philosophy',
				'psychology',
				'self-help',
				'business',
				'investing',
				'courses',
				'workshops',
				'conferences',
			),
		),
		'social'        => array(
			'name'      => 'Social Activities',
			'interests' => array(
				'parties',
				'networking',
				'meetups',
				'clubs',
				'communities',
				'activism',
				'politics',
				'debate',
				'public speaking',
				'leadership',
			),
		),
	);

	/**
	 * Initialize the user interests system.
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
		add_action( 'wp_ajax_wpmatch_add_interest', array( $this, 'ajax_add_interest' ) );
		add_action( 'wp_ajax_wpmatch_remove_interest', array( $this, 'ajax_remove_interest' ) );
		add_action( 'wp_ajax_wpmatch_get_interests', array( $this, 'ajax_get_interests' ) );
		add_action( 'wp_ajax_wpmatch_search_interests', array( $this, 'ajax_search_interests' ) );
		add_action( 'wp_ajax_wpmatch_get_categories', array( $this, 'ajax_get_categories' ) );

		// Admin hooks.
		add_action( 'wp_ajax_wpmatch_admin_get_interests', array( $this, 'ajax_admin_get_interests' ) );
		add_action( 'wp_ajax_wpmatch_admin_manage_interest', array( $this, 'ajax_admin_manage_interest' ) );

		// Initialization hook.
		add_action( 'init', array( $this, 'maybe_initialize_categories' ) );
	}

	/**
	 * Maybe initialize default interest categories.
	 */
	public function maybe_initialize_categories() {
		if ( ! get_option( 'wpmatch_interests_initialized', false ) ) {
			$this->initialize_default_categories();
			update_option( 'wpmatch_interests_initialized', true );
		}
	}

	/**
	 * Initialize default interest categories.
	 */
	private function initialize_default_categories() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_interest_categories';

		foreach ( $this->default_categories as $category_key => $category_data ) {
			// Insert category.
			$wpdb->insert(
				$table_name,
				array(
					'category_key'  => $category_key,
					'category_name' => $category_data['name'],
					'is_active'     => 1,
					'sort_order'    => array_search( $category_key, array_keys( $this->default_categories ) ),
					'created_at'    => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%d', '%d', '%s' )
			);

			$category_id = $wpdb->insert_id;

			// Insert default interests for this category.
			foreach ( $category_data['interests'] as $index => $interest ) {
				$this->add_predefined_interest( $category_id, $interest, $index );
			}
		}

		// Clear cache.
		$this->cache_manager->delete_group( 'interests' );
	}

	/**
	 * Add a predefined interest to a category.
	 *
	 * @param int    $category_id Category ID.
	 * @param string $interest    Interest name.
	 * @param int    $sort_order  Sort order.
	 */
	private function add_predefined_interest( $category_id, $interest, $sort_order = 0 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_predefined_interests';

		$wpdb->insert(
			$table_name,
			array(
				'category_id'   => $category_id,
				'interest_name' => $interest,
				'interest_slug' => sanitize_title( $interest ),
				'is_active'     => 1,
				'sort_order'    => $sort_order,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%d', '%s' )
		);
	}

	/**
	 * Add interest to user profile.
	 *
	 * @param int    $user_id  User ID.
	 * @param string $category Interest category.
	 * @param string $interest Interest name.
	 * @return bool|WP_Error Success or error.
	 */
	public function add_user_interest( $user_id, $category, $interest ) {
		// Validate inputs.
		$user_id  = absint( $user_id );
		$category = sanitize_text_field( $category );
		$interest = sanitize_text_field( $interest );

		if ( empty( $user_id ) || empty( $category ) || empty( $interest ) ) {
			return new WP_Error( 'invalid_data', 'Invalid user ID, category, or interest' );
		}

		// Check if user exists.
		if ( ! get_userdata( $user_id ) ) {
			return new WP_Error( 'user_not_found', 'User not found' );
		}

		// Check interest limit.
		$current_count = $this->get_user_interest_count( $user_id );
		if ( $current_count >= $this->max_interests ) {
			return new WP_Error( 'interest_limit', 'Maximum interests limit reached' );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'wpmatch_user_interests';

		// Check if interest already exists.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT interest_id FROM {$table_name} WHERE user_id = %d AND interest_category = %s AND interest_name = %s",
				$user_id,
				$category,
				$interest
			)
		);

		if ( $existing ) {
			return new WP_Error( 'interest_exists', 'Interest already added' );
		}

		// Insert new interest.
		$result = $wpdb->insert(
			$table_name,
			array(
				'user_id'           => $user_id,
				'interest_category' => $category,
				'interest_name'     => $interest,
				'created_at'        => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', 'Database error occurred' );
		}

		// Clear user cache.
		$this->cache_manager->delete( "user_interests_{$user_id}", 'interests' );

		// Queue interest analysis job.
		$this->job_queue->queue_job( 'analyze_user_interests', array( 'user_id' => $user_id ) );

		return true;
	}

	/**
	 * Remove interest from user profile.
	 *
	 * @param int    $user_id  User ID.
	 * @param string $category Interest category.
	 * @param string $interest Interest name.
	 * @return bool|WP_Error Success or error.
	 */
	public function remove_user_interest( $user_id, $category, $interest ) {
		// Validate inputs.
		$user_id  = absint( $user_id );
		$category = sanitize_text_field( $category );
		$interest = sanitize_text_field( $interest );

		if ( empty( $user_id ) || empty( $category ) || empty( $interest ) ) {
			return new WP_Error( 'invalid_data', 'Invalid user ID, category, or interest' );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'wpmatch_user_interests';

		$result = $wpdb->delete(
			$table_name,
			array(
				'user_id'           => $user_id,
				'interest_category' => $category,
				'interest_name'     => $interest,
			),
			array( '%d', '%s', '%s' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', 'Database error occurred' );
		}

		if ( $result === 0 ) {
			return new WP_Error( 'interest_not_found', 'Interest not found' );
		}

		// Clear user cache.
		$this->cache_manager->delete( "user_interests_{$user_id}", 'interests' );

		// Queue interest analysis job.
		$this->job_queue->queue_job( 'analyze_user_interests', array( 'user_id' => $user_id ) );

		return true;
	}

	/**
	 * Get all interests for a user.
	 *
	 * @param int  $user_id User ID.
	 * @param bool $grouped Group by category.
	 * @return array User interests.
	 */
	public function get_user_interests( $user_id, $grouped = false ) {
		$user_id = absint( $user_id );

		if ( empty( $user_id ) ) {
			return array();
		}

		$cache_key = "user_interests_{$user_id}" . ( $grouped ? '_grouped' : '' );
		$cached    = $this->cache_manager->get( $cache_key, 'interests' );

		if ( $cached !== false ) {
			return $cached;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'wpmatch_user_interests';

		$interests = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY interest_category, interest_name",
				$user_id
			),
			ARRAY_A
		);

		if ( $grouped ) {
			$grouped_interests = array();
			foreach ( $interests as $interest ) {
				$category = $interest['interest_category'];
				if ( ! isset( $grouped_interests[ $category ] ) ) {
					$grouped_interests[ $category ] = array();
				}
				$grouped_interests[ $category ][] = $interest;
			}
			$interests = $grouped_interests;
		}

		// Cache for 1 hour.
		$this->cache_manager->set( $cache_key, $interests, 'interests', 3600 );

		return $interests;
	}

	/**
	 * Get user interest count.
	 *
	 * @param int $user_id User ID.
	 * @return int Interest count.
	 */
	public function get_user_interest_count( $user_id ) {
		$user_id = absint( $user_id );

		if ( empty( $user_id ) ) {
			return 0;
		}

		$cache_key = "user_interest_count_{$user_id}";
		$cached    = $this->cache_manager->get( $cache_key, 'interests' );

		if ( $cached !== false ) {
			return $cached;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'wpmatch_user_interests';

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d",
				$user_id
			)
		);

		$count = absint( $count );

		// Cache for 1 hour.
		$this->cache_manager->set( $cache_key, $count, 'interests', 3600 );

		return $count;
	}

	/**
	 * Get all interest categories.
	 *
	 * @param bool $include_interests Include interests in each category.
	 * @return array Interest categories.
	 */
	public function get_interest_categories( $include_interests = false ) {
		$cache_key = 'interest_categories' . ( $include_interests ? '_with_interests' : '' );
		$cached    = $this->cache_manager->get( $cache_key, 'interests' );

		if ( $cached !== false ) {
			return $cached;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'wpmatch_interest_categories';

		$categories = $wpdb->get_results(
			"SELECT * FROM {$table_name} WHERE is_active = 1 ORDER BY sort_order, category_name",
			ARRAY_A
		);

		if ( $include_interests ) {
			foreach ( $categories as &$category ) {
				$category['interests'] = $this->get_predefined_interests( $category['id'] );
			}
		}

		// Cache for 24 hours.
		$this->cache_manager->set( $cache_key, $categories, 'interests', 86400 );

		return $categories;
	}

	/**
	 * Get predefined interests for a category.
	 *
	 * @param int $category_id Category ID.
	 * @return array Predefined interests.
	 */
	public function get_predefined_interests( $category_id ) {
		$category_id = absint( $category_id );

		if ( empty( $category_id ) ) {
			return array();
		}

		$cache_key = "predefined_interests_{$category_id}";
		$cached    = $this->cache_manager->get( $cache_key, 'interests' );

		if ( $cached !== false ) {
			return $cached;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'wpmatch_predefined_interests';

		$interests = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE category_id = %d AND is_active = 1 ORDER BY sort_order, interest_name",
				$category_id
			),
			ARRAY_A
		);

		// Cache for 24 hours.
		$this->cache_manager->set( $cache_key, $interests, 'interests', 86400 );

		return $interests;
	}

	/**
	 * Search interests by name.
	 *
	 * @param string $search_term Search term.
	 * @param int    $limit       Result limit.
	 * @return array Search results.
	 */
	public function search_interests( $search_term, $limit = 20 ) {
		$search_term = sanitize_text_field( $search_term );
		$limit       = absint( $limit );

		if ( empty( $search_term ) || strlen( $search_term ) < 2 ) {
			return array();
		}

		if ( $limit > 100 ) {
			$limit = 100;
		}

		$cache_key = 'search_' . md5( $search_term . $limit );
		$cached    = $this->cache_manager->get( $cache_key, 'interests' );

		if ( $cached !== false ) {
			return $cached;
		}

		global $wpdb;
		$table_name       = $wpdb->prefix . 'wpmatch_predefined_interests';
		$categories_table = $wpdb->prefix . 'wpmatch_interest_categories';

		$search_like = '%' . $wpdb->esc_like( $search_term ) . '%';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.*, c.category_name, c.category_key
			FROM {$table_name} p
			JOIN {$categories_table} c ON p.category_id = c.id
			WHERE p.is_active = 1 AND c.is_active = 1
			AND p.interest_name LIKE %s
			ORDER BY p.interest_name LIMIT %d",
				$search_like,
				$limit
			),
			ARRAY_A
		);

		// Cache for 30 minutes.
		$this->cache_manager->set( $cache_key, $results, 'interests', 1800 );

		return $results;
	}

	/**
	 * Calculate interest compatibility between two users.
	 *
	 * @param int $user1_id First user ID.
	 * @param int $user2_id Second user ID.
	 * @return array Compatibility data.
	 */
	public function calculate_interest_compatibility( $user1_id, $user2_id ) {
		$user1_id = absint( $user1_id );
		$user2_id = absint( $user2_id );

		if ( empty( $user1_id ) || empty( $user2_id ) || $user1_id === $user2_id ) {
			return array(
				'score'             => 0,
				'common_interests'  => array(),
				'common_categories' => array(),
				'total_interests'   => array(
					'user1' => 0,
					'user2' => 0,
				),
			);
		}

		$cache_key = "compatibility_{$user1_id}_{$user2_id}";
		$cached    = $this->cache_manager->get( $cache_key, 'interests' );

		if ( $cached !== false ) {
			return $cached;
		}

		$user1_interests = $this->get_user_interests( $user1_id, true );
		$user2_interests = $this->get_user_interests( $user2_id, true );

		$common_interests  = array();
		$common_categories = array();
		$user1_total       = 0;
		$user2_total       = 0;

		// Count total interests.
		foreach ( $user1_interests as $category => $interests ) {
			$user1_total += count( $interests );
		}

		foreach ( $user2_interests as $category => $interests ) {
			$user2_total += count( $interests );
		}

		// Find common interests and categories.
		foreach ( $user1_interests as $category => $interests ) {
			if ( isset( $user2_interests[ $category ] ) ) {
				$common_categories[] = $category;

				$user1_names = array_column( $interests, 'interest_name' );
				$user2_names = array_column( $user2_interests[ $category ], 'interest_name' );

				$common_in_category = array_intersect( $user1_names, $user2_names );

				foreach ( $common_in_category as $interest_name ) {
					$common_interests[] = array(
						'category' => $category,
						'interest' => $interest_name,
					);
				}
			}
		}

		// Calculate compatibility score (0-100).
		$total_unique_interests = count(
			array_unique(
				array_merge(
					array_column( array_merge( ...array_values( $user1_interests ) ), 'interest_name' ),
					array_column( array_merge( ...array_values( $user2_interests ) ), 'interest_name' )
				)
			)
		);

		$score = 0;
		if ( $total_unique_interests > 0 ) {
			$common_count   = count( $common_interests );
			$category_bonus = count( $common_categories ) * 2; // Bonus for shared categories.
			$score          = min( 100, ( ( $common_count + $category_bonus ) / $total_unique_interests ) * 100 );
		}

		$compatibility = array(
			'score'             => round( $score, 2 ),
			'common_interests'  => $common_interests,
			'common_categories' => $common_categories,
			'total_interests'   => array(
				'user1' => $user1_total,
				'user2' => $user2_total,
			),
		);

		// Cache for 6 hours.
		$this->cache_manager->set( $cache_key, $compatibility, 'interests', 21600 );

		return $compatibility;
	}

	/**
	 * Get interest statistics.
	 *
	 * @return array Interest statistics.
	 */
	public function get_interest_statistics() {
		$cache_key = 'interest_statistics';
		$cached    = $this->cache_manager->get( $cache_key, 'interests' );

		if ( $cached !== false ) {
			return $cached;
		}

		global $wpdb;
		$user_interests_table = $wpdb->prefix . 'wpmatch_user_interests';
		$categories_table     = $wpdb->prefix . 'wpmatch_interest_categories';

		$stats = array();

		// Most popular interests overall.
		$popular_interests = $wpdb->get_results(
			"SELECT interest_name, interest_category, COUNT(*) as user_count
			FROM {$user_interests_table}
			GROUP BY interest_name, interest_category
			ORDER BY user_count DESC
			LIMIT 20",
			ARRAY_A
		);

		// Most popular categories.
		$popular_categories = $wpdb->get_results(
			"SELECT ui.interest_category, c.category_name, COUNT(*) as user_count
			FROM {$user_interests_table} ui
			JOIN {$categories_table} c ON ui.interest_category = c.category_key
			GROUP BY ui.interest_category, c.category_name
			ORDER BY user_count DESC",
			ARRAY_A
		);

		// Total counts.
		$total_users_with_interests = $wpdb->get_var(
			"SELECT COUNT(DISTINCT user_id) FROM {$user_interests_table}"
		);

		$total_interests = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$user_interests_table}"
		);

		$avg_interests_per_user = $total_users_with_interests > 0 ?
			round( $total_interests / $total_users_with_interests, 2 ) : 0;

		$stats = array(
			'popular_interests'          => $popular_interests,
			'popular_categories'         => $popular_categories,
			'total_users_with_interests' => absint( $total_users_with_interests ),
			'total_interests'            => absint( $total_interests ),
			'average_interests_per_user' => $avg_interests_per_user,
		);

		// Cache for 1 hour.
		$this->cache_manager->set( $cache_key, $stats, 'interests', 3600 );

		return $stats;
	}

	/**
	 * AJAX handler for adding user interest.
	 */
	public function ajax_add_interest() {
		check_ajax_referer( 'wpmatch_interests', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( 'User not logged in' );
		}

		$category = sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) );
		$interest = sanitize_text_field( wp_unslash( $_POST['interest'] ?? '' ) );

		$result = $this->add_user_interest( $user_id, $category, $interest );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success(
			array(
				'message'   => 'Interest added successfully',
				'interests' => $this->get_user_interests( $user_id, true ),
			)
		);
	}

	/**
	 * AJAX handler for removing user interest.
	 */
	public function ajax_remove_interest() {
		check_ajax_referer( 'wpmatch_interests', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( 'User not logged in' );
		}

		$category = sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) );
		$interest = sanitize_text_field( wp_unslash( $_POST['interest'] ?? '' ) );

		$result = $this->remove_user_interest( $user_id, $category, $interest );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success(
			array(
				'message'   => 'Interest removed successfully',
				'interests' => $this->get_user_interests( $user_id, true ),
			)
		);
	}

	/**
	 * AJAX handler for getting user interests.
	 */
	public function ajax_get_interests() {
		check_ajax_referer( 'wpmatch_interests', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( 'User not logged in' );
		}

		$grouped = ! empty( $_POST['grouped'] );

		wp_send_json_success(
			array(
				'interests'     => $this->get_user_interests( $user_id, $grouped ),
				'count'         => $this->get_user_interest_count( $user_id ),
				'max_interests' => $this->max_interests,
			)
		);
	}

	/**
	 * AJAX handler for searching interests.
	 */
	public function ajax_search_interests() {
		check_ajax_referer( 'wpmatch_interests', 'nonce' );

		$search_term = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );
		$limit       = absint( $_POST['limit'] ?? 20 );

		wp_send_json_success(
			array(
				'results' => $this->search_interests( $search_term, $limit ),
			)
		);
	}

	/**
	 * AJAX handler for getting interest categories.
	 */
	public function ajax_get_categories() {
		check_ajax_referer( 'wpmatch_interests', 'nonce' );

		$include_interests = ! empty( $_POST['include_interests'] );

		wp_send_json_success(
			array(
				'categories' => $this->get_interest_categories( $include_interests ),
			)
		);
	}

	/**
	 * AJAX handler for admin getting interests.
	 */
	public function ajax_admin_get_interests() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		check_ajax_referer( 'wpmatch_admin', 'nonce' );

		wp_send_json_success(
			array(
				'statistics' => $this->get_interest_statistics(),
				'categories' => $this->get_interest_categories( true ),
			)
		);
	}

	/**
	 * AJAX handler for admin managing interests.
	 */
	public function ajax_admin_manage_interest() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		check_ajax_referer( 'wpmatch_admin', 'nonce' );

		$action = sanitize_text_field( wp_unslash( $_POST['action_type'] ?? '' ) );

		switch ( $action ) {
			case 'add_category':
				// Implementation for adding category.
				break;
			case 'edit_category':
				// Implementation for editing category.
				break;
			case 'delete_category':
				// Implementation for deleting category.
				break;
			case 'add_interest':
				// Implementation for adding predefined interest.
				break;
			case 'edit_interest':
				// Implementation for editing predefined interest.
				break;
			case 'delete_interest':
				// Implementation for deleting predefined interest.
				break;
			default:
				wp_send_json_error( 'Invalid action' );
		}
	}

	/**
	 * REST API: Get user interests.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function api_get_user_interests( $request ) {
		$user_id = $request->get_param( 'user_id' );
		$grouped = $request->get_param( 'grouped' ) === 'true';

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
				'interests'     => $this->get_user_interests( $user_id, $grouped ),
				'count'         => $this->get_user_interest_count( $user_id ),
				'max_interests' => $this->max_interests,
			)
		);
	}

	/**
	 * REST API: Add user interest.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function api_add_user_interest( $request ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return new WP_REST_Response( array( 'error' => 'User not logged in' ), 401 );
		}

		$category = $request->get_param( 'category' );
		$interest = $request->get_param( 'interest' );

		$result = $this->add_user_interest( $user_id, $category, $interest );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'error' => $result->get_error_message() ), 400 );
		}

		return new WP_REST_Response(
			array(
				'success'   => true,
				'message'   => 'Interest added successfully',
				'interests' => $this->get_user_interests( $user_id, true ),
			)
		);
	}

	/**
	 * REST API: Remove user interest.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function api_remove_user_interest( $request ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return new WP_REST_Response( array( 'error' => 'User not logged in' ), 401 );
		}

		$category = $request->get_param( 'category' );
		$interest = $request->get_param( 'interest' );

		$result = $this->remove_user_interest( $user_id, $category, $interest );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'error' => $result->get_error_message() ), 400 );
		}

		return new WP_REST_Response(
			array(
				'success'   => true,
				'message'   => 'Interest removed successfully',
				'interests' => $this->get_user_interests( $user_id, true ),
			)
		);
	}

	/**
	 * REST API: Get interest categories.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function api_get_categories( $request ) {
		$include_interests = $request->get_param( 'include_interests' ) === 'true';

		return new WP_REST_Response(
			array(
				'categories' => $this->get_interest_categories( $include_interests ),
			)
		);
	}

	/**
	 * REST API: Search interests.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function api_search_interests( $request ) {
		$search_term = $request->get_param( 'search' );
		$limit       = $request->get_param( 'limit' ) ? $request->get_param( 'limit' ) : 20;

		return new WP_REST_Response(
			array(
				'results' => $this->search_interests( $search_term, $limit ),
			)
		);
	}

	/**
	 * REST API: Get interest compatibility.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function api_get_compatibility( $request ) {
		$user1_id = get_current_user_id();
		$user2_id = $request->get_param( 'user_id' );

		if ( ! $user1_id || ! $user2_id ) {
			return new WP_REST_Response( array( 'error' => 'Invalid user IDs' ), 400 );
		}

		return new WP_REST_Response(
			$this->calculate_interest_compatibility( $user1_id, $user2_id )
		);
	}
}
