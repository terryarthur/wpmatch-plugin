<?php
/**
 * Gamification System for WPMatch
 *
 * Implements achievement badges, daily challenges, streaks, leaderboards,
 * rewards system, and engagement incentives to enhance user experience.
 *
 * @package WPMatch
 * @since 1.7.0
 */

/**
 * WPMatch Gamification class.
 *
 * Handles all gamification features including achievements, challenges,
 * points, rewards, and leaderboards for enhanced user engagement.
 */
class WPMatch_Gamification {

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
	 * Achievement types and their configurations.
	 *
	 * @var array
	 */
	private $achievement_types;

	/**
	 * Daily challenges configuration.
	 *
	 * @var array
	 */
	private $daily_challenges;

	/**
	 * Initialize the class.
	 *
	 * @param string $plugin_name Plugin name.
	 * @param string $version Plugin version.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		// Delay setup until after WordPress has loaded text domains.
		add_action( 'init', array( $this, 'setup_achievement_types' ), 5 );
		add_action( 'init', array( $this, 'setup_daily_challenges' ), 5 );
	}

	/**
	 * Initialize gamification system.
	 */
	public static function init() {
		$instance = new self( 'wpmatch', WPMATCH_VERSION );
		add_action( 'init', array( $instance, 'setup_database' ) );
		add_action( 'rest_api_init', array( $instance, 'register_routes' ) );
		add_action( 'wp_enqueue_scripts', array( $instance, 'enqueue_scripts' ) );

		// Hook into user actions for achievements.
		add_action( 'wpmatch_user_registered', array( $instance, 'trigger_achievement' ), 10, 2 );
		add_action( 'wpmatch_profile_completed', array( $instance, 'trigger_achievement' ), 10, 2 );
		add_action( 'wpmatch_photo_uploaded', array( $instance, 'trigger_achievement' ), 10, 2 );
		add_action( 'wpmatch_swipe_made', array( $instance, 'trigger_achievement' ), 10, 2 );
		add_action( 'wpmatch_match_created', array( $instance, 'trigger_achievement' ), 10, 2 );
		add_action( 'wpmatch_new_match_created', array( $instance, 'handle_new_match_created' ), 10, 3 );
		add_action( 'wpmatch_message_sent', array( $instance, 'trigger_achievement' ), 10, 2 );
		add_action( 'wpmatch_video_call_completed', array( $instance, 'trigger_achievement' ), 10, 2 );
		add_action( 'wpmatch_social_connected', array( $instance, 'trigger_achievement' ), 10, 2 );
		add_action( 'wpmatch_event_registered', array( $instance, 'trigger_achievement' ), 10, 2 );

		// Daily challenge tracking.
		add_action( 'wp_login', array( $instance, 'update_login_streak' ), 10, 2 );
		add_action( 'wp_footer', array( $instance, 'add_gamification_ui' ) );

		// Scheduled events.
		if ( ! wp_next_scheduled( 'wpmatch_reset_daily_challenges' ) ) {
			wp_schedule_event( time(), 'daily', 'wpmatch_reset_daily_challenges' );
		}
		add_action( 'wpmatch_reset_daily_challenges', array( $instance, 'reset_daily_challenges' ) );

		if ( ! wp_next_scheduled( 'wpmatch_update_leaderboards' ) ) {
			wp_schedule_event( time(), 'hourly', 'wpmatch_update_leaderboards' );
		}
		add_action( 'wpmatch_update_leaderboards', array( $instance, 'update_leaderboards' ) );
	}

	/**
	 * Set up achievement types.
	 */
	public function setup_achievement_types() {
		$this->achievement_types = array(
			'profile_master'       => array(
				'name'        => esc_html__( 'Profile Master', 'wpmatch' ),
				'description' => esc_html__( 'Complete your profile 100%', 'wpmatch' ),
				'icon'        => 'fas fa-user-check',
				'color'       => '#4CAF50',
				'points'      => 100,
				'trigger'     => 'profile_completed',
				'levels'      => array(
					1 => array(
						'requirement' => 1,
						'title'       => 'Profile Complete',
					),
				),
			),
			'social_butterfly'     => array(
				'name'        => esc_html__( 'Social Butterfly', 'wpmatch' ),
				'description' => esc_html__( 'Connect your social media accounts', 'wpmatch' ),
				'icon'        => 'fas fa-share-alt',
				'color'       => '#9C27B0',
				'points'      => 50,
				'trigger'     => 'social_connected',
				'levels'      => array(
					1 => array(
						'requirement' => 1,
						'title'       => 'Connected',
					),
					2 => array(
						'requirement' => 3,
						'title'       => 'Super Connected',
					),
					3 => array(
						'requirement' => 5,
						'title'       => 'Social Master',
					),
				),
			),
			'swipe_master'         => array(
				'name'        => esc_html__( 'Swipe Master', 'wpmatch' ),
				'description' => esc_html__( 'Make swipes to find your match', 'wpmatch' ),
				'icon'        => 'fas fa-hand-pointer',
				'color'       => '#FF9800',
				'points'      => 10,
				'trigger'     => 'swipe_made',
				'levels'      => array(
					1 => array(
						'requirement' => 10,
						'title'       => 'Getting Started',
					),
					2 => array(
						'requirement' => 50,
						'title'       => 'Active Swiper',
					),
					3 => array(
						'requirement' => 100,
						'title'       => 'Swipe Expert',
					),
					4 => array(
						'requirement' => 500,
						'title'       => 'Swipe Legend',
					),
				),
			),
			'match_maker'          => array(
				'name'        => esc_html__( 'Match Maker', 'wpmatch' ),
				'description' => esc_html__( 'Create successful matches', 'wpmatch' ),
				'icon'        => 'fas fa-heart',
				'color'       => '#E91E63',
				'points'      => 50,
				'trigger'     => 'match_created',
				'levels'      => array(
					1 => array(
						'requirement' => 1,
						'title'       => 'First Match',
					),
					2 => array(
						'requirement' => 5,
						'title'       => 'Popular',
					),
					3 => array(
						'requirement' => 10,
						'title'       => 'Heartthrob',
					),
					4 => array(
						'requirement' => 25,
						'title'       => 'Love Magnet',
					),
				),
			),
			'conversation_starter' => array(
				'name'        => esc_html__( 'Conversation Starter', 'wpmatch' ),
				'description' => esc_html__( 'Send messages to your matches', 'wpmatch' ),
				'icon'        => 'fas fa-comments',
				'color'       => '#2196F3',
				'points'      => 20,
				'trigger'     => 'message_sent',
				'levels'      => array(
					1 => array(
						'requirement' => 1,
						'title'       => 'First Message',
					),
					2 => array(
						'requirement' => 10,
						'title'       => 'Chatty',
					),
					3 => array(
						'requirement' => 50,
						'title'       => 'Communicator',
					),
					4 => array(
						'requirement' => 100,
						'title'       => 'Talk Show Host',
					),
				),
			),
			'photo_collector'      => array(
				'name'        => esc_html__( 'Photo Collector', 'wpmatch' ),
				'description' => esc_html__( 'Upload photos to your profile', 'wpmatch' ),
				'icon'        => 'fas fa-camera',
				'color'       => '#795548',
				'points'      => 25,
				'trigger'     => 'photo_uploaded',
				'levels'      => array(
					1 => array(
						'requirement' => 1,
						'title'       => 'Picture Perfect',
					),
					2 => array(
						'requirement' => 3,
						'title'       => 'Photo Pro',
					),
					3 => array(
						'requirement' => 6,
						'title'       => 'Gallery Master',
					),
				),
			),
			'video_caller'         => array(
				'name'        => esc_html__( 'Video Caller', 'wpmatch' ),
				'description' => esc_html__( 'Complete video calls with matches', 'wpmatch' ),
				'icon'        => 'fas fa-video',
				'color'       => '#607D8B',
				'points'      => 75,
				'trigger'     => 'video_call_completed',
				'levels'      => array(
					1 => array(
						'requirement' => 1,
						'title'       => 'Face to Face',
					),
					2 => array(
						'requirement' => 5,
						'title'       => 'Video Regular',
					),
					3 => array(
						'requirement' => 15,
						'title'       => 'Call Champion',
					),
				),
			),
			'daily_warrior'        => array(
				'name'        => esc_html__( 'Daily Warrior', 'wpmatch' ),
				'description' => esc_html__( 'Log in daily to maintain your streak', 'wpmatch' ),
				'icon'        => 'fas fa-fire',
				'color'       => '#FF5722',
				'points'      => 30,
				'trigger'     => 'daily_login',
				'levels'      => array(
					1 => array(
						'requirement' => 3,
						'title'       => '3 Day Streak',
					),
					2 => array(
						'requirement' => 7,
						'title'       => 'Week Warrior',
					),
					3 => array(
						'requirement' => 30,
						'title'       => 'Month Master',
					),
					4 => array(
						'requirement' => 100,
						'title'       => 'Streak Legend',
					),
				),
			),
		);
	}

	/**
	 * Set up daily challenges.
	 */
	public function setup_daily_challenges() {
		$this->daily_challenges = array(
			'swipe_challenge'   => array(
				'name'        => esc_html__( 'Swipe Explorer', 'wpmatch' ),
				'description' => esc_html__( 'Make 20 swipes today', 'wpmatch' ),
				'target'      => 20,
				'action'      => 'swipe_made',
				'points'      => 50,
				'icon'        => 'fas fa-hand-pointer',
			),
			'message_challenge' => array(
				'name'        => esc_html__( 'Conversation Champion', 'wpmatch' ),
				'description' => esc_html__( 'Send 5 messages today', 'wpmatch' ),
				'target'      => 5,
				'action'      => 'message_sent',
				'points'      => 75,
				'icon'        => 'fas fa-comments',
			),
			'profile_challenge' => array(
				'name'        => esc_html__( 'Profile Perfectionist', 'wpmatch' ),
				'description' => esc_html__( 'Update your profile today', 'wpmatch' ),
				'target'      => 1,
				'action'      => 'profile_updated',
				'points'      => 100,
				'icon'        => 'fas fa-user-edit',
			),
			'match_challenge'   => array(
				'name'        => esc_html__( 'Match Seeker', 'wpmatch' ),
				'description' => esc_html__( 'Create 2 new matches today', 'wpmatch' ),
				'target'      => 2,
				'action'      => 'match_created',
				'points'      => 150,
				'icon'        => 'fas fa-heart',
			),
		);
	}

	/**
	 * Set up database tables.
	 */
	public function setup_database() {
		$this->create_achievements_table();
		$this->create_user_achievements_table();
		$this->create_user_points_table();
		$this->create_daily_challenges_table();
		$this->create_user_challenges_table();
		$this->create_leaderboards_table();
		$this->create_rewards_table();
		$this->create_user_rewards_table();
		$this->create_streaks_table();
	}

	/**
	 * Create achievements table.
	 */
	private function create_achievements_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_achievements';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			achievement_key varchar(100) NOT NULL,
			name varchar(255) NOT NULL,
			description text,
			icon varchar(100),
			color varchar(7),
			points int(11) DEFAULT 0,
			trigger_action varchar(100),
			level_data longtext,
			is_active tinyint(1) DEFAULT 1,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY achievement_key (achievement_key),
			KEY trigger_action (trigger_action),
			KEY is_active (is_active)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Insert default achievements.
		$this->insert_default_achievements();
	}

	/**
	 * Create user achievements table.
	 */
	private function create_user_achievements_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_user_achievements';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			achievement_id bigint(20) NOT NULL,
			level int(11) DEFAULT 1,
			progress int(11) DEFAULT 0,
			unlocked_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_achievement (user_id, achievement_id),
			KEY user_id (user_id),
			KEY achievement_id (achievement_id),
			KEY unlocked_at (unlocked_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create user points table.
	 */
	private function create_user_points_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_user_points';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			total_points int(11) DEFAULT 0,
			lifetime_points int(11) DEFAULT 0,
			spent_points int(11) DEFAULT 0,
			level int(11) DEFAULT 1,
			last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_id (user_id),
			KEY total_points (total_points),
			KEY level (level)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create daily challenges table.
	 */
	private function create_daily_challenges_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_daily_challenges';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			challenge_key varchar(100) NOT NULL,
			name varchar(255) NOT NULL,
			description text,
			target_value int(11) NOT NULL,
			trigger_action varchar(100) NOT NULL,
			points_reward int(11) DEFAULT 0,
			icon varchar(100),
			difficulty tinyint(1) DEFAULT 1,
			is_active tinyint(1) DEFAULT 1,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY challenge_key (challenge_key),
			KEY trigger_action (trigger_action),
			KEY difficulty (difficulty),
			KEY is_active (is_active)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Insert default challenges.
		$this->insert_default_challenges();
	}

	/**
	 * Create user challenges table.
	 */
	private function create_user_challenges_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_user_challenges';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			challenge_id bigint(20) NOT NULL,
			progress int(11) DEFAULT 0,
			completed tinyint(1) DEFAULT 0,
			completed_at datetime,
			challenge_date date NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY user_challenge_date (user_id, challenge_id, challenge_date),
			KEY user_id (user_id),
			KEY challenge_id (challenge_id),
			KEY challenge_date (challenge_date),
			KEY completed (completed)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create leaderboards table.
	 */
	private function create_leaderboards_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_leaderboards';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			leaderboard_type varchar(50) NOT NULL,
			user_id bigint(20) NOT NULL,
			score int(11) NOT NULL,
			rank_position int(11),
			period varchar(20) DEFAULT 'weekly',
			period_start date,
			period_end date,
			last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_type_period (user_id, leaderboard_type, period, period_start),
			KEY leaderboard_type (leaderboard_type),
			KEY rank_position (rank_position),
			KEY period (period),
			KEY score (score)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create rewards table.
	 */
	private function create_rewards_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_rewards';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			reward_key varchar(100) NOT NULL,
			name varchar(255) NOT NULL,
			description text,
			reward_type varchar(50) NOT NULL,
			cost_points int(11) NOT NULL,
			value_data longtext,
			icon varchar(100),
			category varchar(100),
			stock_quantity int(11) DEFAULT -1,
			is_active tinyint(1) DEFAULT 1,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY reward_key (reward_key),
			KEY reward_type (reward_type),
			KEY cost_points (cost_points),
			KEY category (category),
			KEY is_active (is_active)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Insert default rewards.
		$this->insert_default_rewards();
	}

	/**
	 * Create user rewards table.
	 */
	private function create_user_rewards_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_user_rewards';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			reward_id bigint(20) NOT NULL,
			points_spent int(11) NOT NULL,
			status varchar(20) DEFAULT 'redeemed',
			redeemed_at datetime DEFAULT CURRENT_TIMESTAMP,
			expires_at datetime,
			used_at datetime,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY reward_id (reward_id),
			KEY status (status),
			KEY redeemed_at (redeemed_at),
			KEY expires_at (expires_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create streaks table.
	 */
	private function create_streaks_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_user_streaks';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			streak_type varchar(50) NOT NULL,
			current_streak int(11) DEFAULT 0,
			longest_streak int(11) DEFAULT 0,
			last_activity_date date,
			started_at datetime,
			PRIMARY KEY (id),
			UNIQUE KEY user_streak_type (user_id, streak_type),
			KEY user_id (user_id),
			KEY streak_type (streak_type),
			KEY current_streak (current_streak),
			KEY longest_streak (longest_streak)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		// Achievement endpoints.
		register_rest_route(
			'wpmatch/v1',
			'/gamification/achievements',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_get_achievements' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'user_id' => array(
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'wpmatch/v1',
			'/gamification/points',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_get_user_points' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'user_id' => array(
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Challenge endpoints.
		register_rest_route(
			'wpmatch/v1',
			'/gamification/challenges',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_get_daily_challenges' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
			)
		);

		register_rest_route(
			'wpmatch/v1',
			'/gamification/challenges/progress',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_get_challenge_progress' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
			)
		);

		// Leaderboard endpoints.
		register_rest_route(
			'wpmatch/v1',
			'/gamification/leaderboards',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_get_leaderboards' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'type'   => array(
						'default'           => 'points',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'period' => array(
						'default'           => 'weekly',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'limit'  => array(
						'default'           => 10,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Rewards endpoints.
		register_rest_route(
			'wpmatch/v1',
			'/gamification/rewards',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_get_rewards' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'category' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'wpmatch/v1',
			'/gamification/rewards/redeem',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_redeem_reward' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'reward_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Stats endpoints.
		register_rest_route(
			'wpmatch/v1',
			'/gamification/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_get_user_stats' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
			)
		);

		register_rest_route(
			'wpmatch/v1',
			'/gamification/streaks',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_get_user_streaks' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
			)
		);
	}

	/**
	 * Enqueue scripts and styles.
	 */
	public function enqueue_scripts() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		wp_enqueue_script(
			'wpmatch-gamification',
			WPMATCH_PLUGIN_URL . 'public/js/wpmatch-gamification.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		wp_localize_script(
			'wpmatch-gamification',
			'wpMatchGamification',
			array(
				'apiUrl'      => home_url( '/wp-json/wpmatch/v1' ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'currentUser' => get_current_user_id(),
				'strings'     => array(
					'achievementUnlocked' => esc_html__( 'Achievement Unlocked!', 'wpmatch' ),
					'challengeCompleted'  => esc_html__( 'Challenge Completed!', 'wpmatch' ),
					'pointsEarned'        => esc_html__( 'Points Earned:', 'wpmatch' ),
					'levelUp'             => esc_html__( 'Level Up!', 'wpmatch' ),
					'streakContinues'     => esc_html__( 'Streak continues!', 'wpmatch' ),
					'rewardRedeemed'      => esc_html__( 'Reward redeemed successfully!', 'wpmatch' ),
					'insufficientPoints'  => esc_html__( 'Insufficient points for this reward.', 'wpmatch' ),
				),
			)
		);

		wp_enqueue_style(
			'wpmatch-gamification',
			WPMATCH_PLUGIN_URL . 'public/css/wpmatch-gamification.css',
			array(),
			$this->version
		);
	}

	/**
	 * Add gamification UI to footer.
	 */
	public function add_gamification_ui() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id     = get_current_user_id();
		$user_points = $this->get_user_points( $user_id );
		$user_level  = $this->calculate_user_level( $user_points['total_points'] );

		?>
		<div id="wpmatch-gamification-ui" class="wpmatch-gamification-ui">
			<!-- Floating Progress Bar -->
			<div class="gamification-progress-bar">
				<div class="progress-info">
					<span class="user-level">Level <?php echo esc_html( $user_level ); ?></span>
					<span class="user-points"><?php echo esc_html( $user_points['total_points'] ); ?> XP</span>
				</div>
				<div class="progress-bar">
					<div class="progress-fill" data-progress="<?php echo esc_attr( $this->get_level_progress_percentage( $user_points['total_points'] ) ); ?>"></div>
				</div>
			</div>

			<!-- Achievement Notification -->
			<div id="achievement-notification" class="achievement-notification" style="display: none;">
				<div class="notification-content">
					<div class="achievement-icon">
						<i class="fas fa-trophy"></i>
					</div>
					<div class="achievement-details">
						<h4 class="achievement-title"></h4>
						<p class="achievement-description"></p>
						<span class="points-earned"></span>
					</div>
				</div>
			</div>

			<!-- Gamification Panel Toggle -->
			<div class="gamification-toggle">
				<button class="toggle-btn" data-target="achievements">
					<i class="fas fa-trophy"></i>
					<span class="toggle-label"><?php esc_html_e( 'Achievements', 'wpmatch' ); ?></span>
				</button>
				<button class="toggle-btn" data-target="challenges">
					<i class="fas fa-tasks"></i>
					<span class="toggle-label"><?php esc_html_e( 'Challenges', 'wpmatch' ); ?></span>
				</button>
				<button class="toggle-btn" data-target="leaderboard">
					<i class="fas fa-crown"></i>
					<span class="toggle-label"><?php esc_html_e( 'Leaderboard', 'wpmatch' ); ?></span>
				</button>
				<button class="toggle-btn" data-target="rewards">
					<i class="fas fa-gift"></i>
					<span class="toggle-label"><?php esc_html_e( 'Rewards', 'wpmatch' ); ?></span>
				</button>
			</div>

			<!-- Gamification Panels -->
			<div id="gamification-panel" class="gamification-panel" style="display: none;">
				<div class="panel-header">
					<h3 class="panel-title"></h3>
					<button class="panel-close">
						<i class="fas fa-times"></i>
					</button>
				</div>
				<div class="panel-content">
					<!-- Content loaded dynamically -->
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Trigger achievement check.
	 *
	 * @param string $action Achievement trigger action.
	 * @param array  $data Additional data.
	 */
	public static function trigger_achievement( $action, $data = array() ) {
		// Create instance to access achievement data and methods
		$instance = new self( 'wpmatch', WPMATCH_VERSION );
		$instance->trigger_achievement_instance( $action, $data );
	}

	/**
	 * Instance method to trigger achievement check.
	 *
	 * @param string $action Achievement trigger action.
	 * @param array  $data Additional data.
	 */
	public function trigger_achievement_instance( $action, $data = array() ) {
		$user_id = isset( $data['user_id'] ) ? $data['user_id'] : get_current_user_id();

		if ( ! $user_id ) {
			return;
		}

		// Find achievements triggered by this action.
		foreach ( $this->achievement_types as $key => $achievement ) {
			if ( $achievement['trigger'] === $action ) {
				$this->check_achievement_progress( $user_id, $key, $achievement );
			}
		}

		// Update daily challenges.
		$this->update_challenge_progress( $user_id, $action );

		// Update streaks.
		$this->update_streak( $user_id, $action );
	}

	/**
	 * Handle new match created event.
	 *
	 * @param int $match_id Match ID.
	 * @param int $user1_id First user ID.
	 * @param int $user2_id Second user ID.
	 */
	public function handle_new_match_created( $match_id, $user1_id, $user2_id ) {
		// Trigger match achievement for both users.
		$this->trigger_achievement(
			'match_created',
			array(
				'user_id'  => $user1_id,
				'match_id' => $match_id,
			)
		);
		$this->trigger_achievement(
			'match_created',
			array(
				'user_id'  => $user2_id,
				'match_id' => $match_id,
			)
		);

		// Check for first match achievement specifically.
		$this->trigger_achievement(
			'first_match',
			array(
				'user_id'  => $user1_id,
				'match_id' => $match_id,
			)
		);
		$this->trigger_achievement(
			'first_match',
			array(
				'user_id'  => $user2_id,
				'match_id' => $match_id,
			)
		);
	}

	/**
	 * Check achievement progress.
	 *
	 * @param int    $user_id User ID.
	 * @param string $achievement_key Achievement key.
	 * @param array  $achievement Achievement data.
	 */
	private function check_achievement_progress( $user_id, $achievement_key, $achievement ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_user_achievements';

		// Get current progress.
		$achievements_table = $wpdb->prefix . 'wpmatch_achievements';
		$user_achievement   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpmatch_user_achievements WHERE user_id = %d AND achievement_id = (
					SELECT id FROM {$wpdb->prefix}wpmatch_achievements WHERE achievement_key = %s
				)",
				$user_id,
				$achievement_key
			)
		);

		$current_progress = $user_achievement ? $user_achievement->progress : 0;
		$current_level    = $user_achievement ? $user_achievement->level : 0;
		$new_progress     = $current_progress + 1;

		// Check if user reaches next level.
		$next_level        = $current_level + 1;
		$level_requirement = isset( $achievement['levels'][ $next_level ]['requirement'] )
			? $achievement['levels'][ $next_level ]['requirement']
			: null;

		if ( $level_requirement && $new_progress >= $level_requirement ) {
			// Level up!
			$this->unlock_achievement_level( $user_id, $achievement_key, $next_level, $achievement );
		} else {
			// Update progress.
			$this->update_achievement_progress( $user_id, $achievement_key, $new_progress );
		}
	}

	/**
	 * Unlock achievement level.
	 *
	 * @param int    $user_id User ID.
	 * @param string $achievement_key Achievement key.
	 * @param int    $level Level.
	 * @param array  $achievement Achievement data.
	 */
	private function unlock_achievement_level( $user_id, $achievement_key, $level, $achievement ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_user_achievements';

		// Get achievement ID.
		$achievement_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}wpmatch_achievements WHERE achievement_key = %s",
				$achievement_key
			)
		);

		// Update or insert user achievement.
		$wpdb->replace(
			$table_name,
			array(
				'user_id'        => $user_id,
				'achievement_id' => $achievement_id,
				'level'          => $level,
				'progress'       => $achievement['levels'][ $level ]['requirement'],
			),
			array( '%d', '%d', '%d', '%d' )
		);

		// Award points.
		$points = $achievement['points'] * $level;
		$this->award_points( $user_id, $points );

		// Trigger notification.
		$this->trigger_achievement_notification( $user_id, $achievement, $level );
	}

	/**
	 * Award points to user.
	 *
	 * @param int $user_id User ID.
	 * @param int $points Points to award.
	 */
	public function award_points( $user_id, $points ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_user_points';

		// Get current points.
		$current_points = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpmatch_user_points WHERE user_id = %d",
				$user_id
			)
		);

		if ( $current_points ) {
			// Update existing record.
			$wpdb->update(
				$table_name,
				array(
					'total_points'    => $current_points->total_points + $points,
					'lifetime_points' => $current_points->lifetime_points + $points,
				),
				array( 'user_id' => $user_id ),
				array( '%d', '%d' ),
				array( '%d' )
			);
		} else {
			// Create new record.
			$wpdb->insert(
				$table_name,
				array(
					'user_id'         => $user_id,
					'total_points'    => $points,
					'lifetime_points' => $points,
					'level'           => 1,
				),
				array( '%d', '%d', '%d', '%d' )
			);
		}
	}

	/**
	 * Get user points.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_user_points( $user_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_user_points';

		$points = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpmatch_user_points WHERE user_id = %d",
				$user_id
			)
		);

		if ( ! $points ) {
			return array(
				'total_points'    => 0,
				'lifetime_points' => 0,
				'spent_points'    => 0,
				'level'           => 1,
			);
		}

		return array(
			'total_points'    => (int) $points->total_points,
			'lifetime_points' => (int) $points->lifetime_points,
			'spent_points'    => (int) $points->spent_points,
			'level'           => (int) $points->level,
		);
	}

	/**
	 * Calculate user level based on points.
	 *
	 * @param int $points Total points.
	 * @return int
	 */
	private function calculate_user_level( $points ) {
		// Level progression: 0-99: Level 1, 100-299: Level 2, 300-599: Level 3, etc.
		if ( $points < 100 ) {
			return 1;
		} elseif ( $points < 300 ) {
			return 2;
		} elseif ( $points < 600 ) {
			return 3;
		} elseif ( $points < 1000 ) {
			return 4;
		} elseif ( $points < 1500 ) {
			return 5;
		} else {
			return 5 + floor( ( $points - 1500 ) / 1000 );
		}
	}

	/**
	 * Get level progress percentage.
	 *
	 * @param int $points Total points.
	 * @return int
	 */
	private function get_level_progress_percentage( $points ) {
		$current_level    = $this->calculate_user_level( $points );
		$level_thresholds = array( 0, 100, 300, 600, 1000, 1500 );

		if ( $current_level <= 5 ) {
			$current_threshold = $level_thresholds[ $current_level - 1 ];
			$next_threshold    = $level_thresholds[ $current_level ];
		} else {
			$current_threshold = 1500 + ( ( $current_level - 6 ) * 1000 );
			$next_threshold    = $current_threshold + 1000;
		}

		$progress     = $points - $current_threshold;
		$total_needed = $next_threshold - $current_threshold;

		return min( 100, ( $progress / $total_needed ) * 100 );
	}

	/**
	 * Insert default achievements.
	 */
	private function insert_default_achievements() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_achievements';

		foreach ( $this->achievement_types as $key => $achievement ) {
			$wpdb->replace(
				$table_name,
				array(
					'achievement_key' => $key,
					'name'            => $achievement['name'],
					'description'     => $achievement['description'],
					'icon'            => $achievement['icon'],
					'color'           => $achievement['color'],
					'points'          => $achievement['points'],
					'trigger_action'  => $achievement['trigger'],
					'level_data'      => wp_json_encode( $achievement['levels'] ),
				),
				array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
			);
		}
	}

	/**
	 * Insert default challenges.
	 */
	private function insert_default_challenges() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_daily_challenges';

		foreach ( $this->daily_challenges as $key => $challenge ) {
			$wpdb->replace(
				$table_name,
				array(
					'challenge_key'  => $key,
					'name'           => $challenge['name'],
					'description'    => $challenge['description'],
					'target_value'   => $challenge['target'],
					'trigger_action' => $challenge['action'],
					'points_reward'  => $challenge['points'],
					'icon'           => $challenge['icon'],
				),
				array( '%s', '%s', '%s', '%d', '%s', '%d', '%s' )
			);
		}
	}

	/**
	 * Insert default rewards.
	 */
	private function insert_default_rewards() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_rewards';

		$rewards = array(
			array(
				'key'         => 'super_like_pack',
				'name'        => esc_html__( 'Super Like Pack (5)', 'wpmatch' ),
				'description' => esc_html__( 'Get 5 super likes to stand out', 'wpmatch' ),
				'type'        => 'feature',
				'cost'        => 200,
				'value'       => wp_json_encode( array( 'super_likes' => 5 ) ),
				'icon'        => 'fas fa-star',
				'category'    => 'boosts',
			),
			array(
				'key'         => 'profile_boost_24h',
				'name'        => esc_html__( 'Profile Boost (24h)', 'wpmatch' ),
				'description' => esc_html__( 'Boost your profile visibility for 24 hours', 'wpmatch' ),
				'type'        => 'boost',
				'cost'        => 150,
				'value'       => wp_json_encode( array( 'duration' => 24 ) ),
				'icon'        => 'fas fa-rocket',
				'category'    => 'boosts',
			),
			array(
				'key'         => 'unlimited_swipes_24h',
				'name'        => esc_html__( 'Unlimited Swipes (24h)', 'wpmatch' ),
				'description' => esc_html__( 'Remove swipe limits for 24 hours', 'wpmatch' ),
				'type'        => 'feature',
				'cost'        => 100,
				'value'       => wp_json_encode( array( 'duration' => 24 ) ),
				'icon'        => 'fas fa-infinity',
				'category'    => 'features',
			),
		);

		foreach ( $rewards as $reward ) {
			$wpdb->replace(
				$table_name,
				array(
					'reward_key'  => $reward['key'],
					'name'        => $reward['name'],
					'description' => $reward['description'],
					'reward_type' => $reward['type'],
					'cost_points' => $reward['cost'],
					'value_data'  => $reward['value'],
					'icon'        => $reward['icon'],
					'category'    => $reward['category'],
				),
				array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * Placeholder methods for remaining functionality.
	 */
	/**
	 * Update user login streak.
	 *
	 * @param string  $user_login Username.
	 * @param WP_User $user User object.
	 */
	public function update_login_streak( $user_login, $user ) {
		// Implementation for login streak tracking.
	}

	/**
	 * Reset daily challenges.
	 */
	public function reset_daily_challenges() {
		// Implementation for resetting daily challenges.
	}

	/**
	 * Update leaderboards.
	 */
	public function update_leaderboards() {
		// Implementation for updating leaderboards.
	}

	/**
	 * Update achievement progress.
	 *
	 * @param int    $user_id User ID.
	 * @param string $achievement_key Achievement key.
	 * @param int    $progress Progress value.
	 */
	public function update_achievement_progress( $user_id, $achievement_key, $progress ) {
		// Implementation for updating achievement progress.
	}

	/**
	 * Update challenge progress.
	 *
	 * @param int    $user_id User ID.
	 * @param string $action Action type.
	 */
	public function update_challenge_progress( $user_id, $action ) {
		// Implementation for updating challenge progress.
	}

	/**
	 * Update user streak.
	 *
	 * @param int    $user_id User ID.
	 * @param string $action Action type.
	 */
	public function update_streak( $user_id, $action ) {
		// Implementation for updating streaks.
	}

	/**
	 * Trigger achievement notification.
	 *
	 * @param int   $user_id User ID.
	 * @param array $achievement Achievement data.
	 * @param int   $level Achievement level.
	 */
	public function trigger_achievement_notification( $user_id, $achievement, $level ) {
		// Implementation for triggering achievement notifications.
	}

	/**
	 * Check user authentication.
	 *
	 * @return bool
	 */
	public function check_user_auth() {
		return is_user_logged_in();
	}

	/**
	 * Placeholder API methods.
	 */
	/**
	 * API endpoint to get user achievements.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response
	 */
	public function api_get_achievements( $request ) {
		return rest_ensure_response(
			array(
				'success' => false,
				'message' => 'Not implemented yet',
			)
		);
	}

	/**
	 * API endpoint to get user points.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response
	 */
	public function api_get_user_points( $request ) {
		$user_id = $request->get_param( 'user_id' ) ? $request->get_param( 'user_id' ) : get_current_user_id();
		$points  = $this->get_user_points( $user_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $points,
			)
		);
	}

	/**
	 * API endpoint to get daily challenges.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response
	 */
	public function api_get_daily_challenges( $request ) {
		return rest_ensure_response(
			array(
				'success' => false,
				'message' => 'Not implemented yet',
			)
		);
	}

	/**
	 * API endpoint to get challenge progress.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response
	 */
	public function api_get_challenge_progress( $request ) {
		return rest_ensure_response(
			array(
				'success' => false,
				'message' => 'Not implemented yet',
			)
		);
	}

	/**
	 * API endpoint to get leaderboards.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response
	 */
	public function api_get_leaderboards( $request ) {
		return rest_ensure_response(
			array(
				'success' => false,
				'message' => 'Not implemented yet',
			)
		);
	}

	/**
	 * API endpoint to get rewards.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response
	 */
	public function api_get_rewards( $request ) {
		return rest_ensure_response(
			array(
				'success' => false,
				'message' => 'Not implemented yet',
			)
		);
	}

	/**
	 * API endpoint to redeem a reward.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response
	 */
	public function api_redeem_reward( $request ) {
		return rest_ensure_response(
			array(
				'success' => false,
				'message' => 'Not implemented yet',
			)
		);
	}

	/**
	 * API endpoint to get user stats.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response
	 */
	public function api_get_user_stats( $request ) {
		return rest_ensure_response(
			array(
				'success' => false,
				'message' => 'Not implemented yet',
			)
		);
	}

	/**
	 * API endpoint to get user streaks.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response
	 */
	public function api_get_user_streaks( $request ) {
		return rest_ensure_response(
			array(
				'success' => false,
				'message' => 'Not implemented yet',
			)
		);
	}
}