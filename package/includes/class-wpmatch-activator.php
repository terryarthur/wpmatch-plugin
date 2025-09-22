<?php
/**
 * Fired during plugin activation
 *
 * @package WPMatch
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 */
class WPMatch_Activator {

	/**
	 * Activate the plugin.
	 *
	 * Create database tables, set default options, and create user roles.
	 */
	public static function activate() {
		// Create database tables.
		self::create_tables();

		// Run swipe database migrations.
		self::run_swipe_migrations();

		// Set default options.
		self::set_default_options();

		// Create user roles.
		self::create_user_roles();

		// Set plugin version.
		update_option( 'wpmatch_version', WPMATCH_VERSION );

		// Clear rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Create plugin database tables.
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// User profiles table.
		$table_profiles = $wpdb->prefix . 'wpmatch_user_profiles';
		$sql_profiles = "CREATE TABLE IF NOT EXISTS $table_profiles (
			profile_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			age tinyint(3) UNSIGNED DEFAULT NULL,
			location varchar(255) DEFAULT NULL,
			latitude decimal(10,8) DEFAULT NULL,
			longitude decimal(11,8) DEFAULT NULL,
			gender varchar(50) DEFAULT NULL,
			orientation varchar(50) DEFAULT NULL,
			education varchar(100) DEFAULT NULL,
			profession varchar(100) DEFAULT NULL,
			income_range varchar(50) DEFAULT NULL,
			height smallint(5) UNSIGNED DEFAULT NULL,
			body_type varchar(50) DEFAULT NULL,
			ethnicity varchar(50) DEFAULT NULL,
			smoking varchar(50) DEFAULT NULL,
			drinking varchar(50) DEFAULT NULL,
			children varchar(50) DEFAULT NULL,
			wants_children varchar(50) DEFAULT NULL,
			pets varchar(100) DEFAULT NULL,
			about_me text DEFAULT NULL,
			looking_for text DEFAULT NULL,
			profile_completion tinyint(3) UNSIGNED DEFAULT 0,
			last_active datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (profile_id),
			UNIQUE KEY user_id (user_id),
			KEY idx_location (latitude, longitude),
			KEY idx_age_gender (age, gender),
			KEY idx_last_active (last_active),
			FULLTEXT KEY idx_about (about_me, looking_for)
		) $charset_collate;";

		// User media table.
		$table_media = $wpdb->prefix . 'wpmatch_user_media';
		$sql_media = "CREATE TABLE IF NOT EXISTS $table_media (
			media_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			media_type enum('photo','video') NOT NULL DEFAULT 'photo',
			file_path varchar(255) NOT NULL,
			file_name varchar(255) NOT NULL,
			mime_type varchar(100) DEFAULT NULL,
			file_size int(11) UNSIGNED DEFAULT NULL,
			is_primary tinyint(1) DEFAULT 0,
			display_order tinyint(3) UNSIGNED DEFAULT 0,
			is_verified tinyint(1) DEFAULT 0,
			verification_status enum('pending','approved','rejected') DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (media_id),
			KEY idx_user_media (user_id, media_type),
			KEY idx_display_order (user_id, display_order)
		) $charset_collate;";

		// User interests table.
		$table_interests = $wpdb->prefix . 'wpmatch_user_interests';
		$sql_interests = "CREATE TABLE IF NOT EXISTS $table_interests (
			interest_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			interest_category varchar(100) NOT NULL,
			interest_name varchar(100) NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (interest_id),
			KEY idx_user_interests (user_id),
			KEY idx_interest_name (interest_name),
			UNIQUE KEY unique_user_interest (user_id, interest_category, interest_name)
		) $charset_collate;";

		// User preferences table.
		$table_preferences = $wpdb->prefix . 'wpmatch_user_preferences';
		$sql_preferences = "CREATE TABLE IF NOT EXISTS $table_preferences (
			preference_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			min_age tinyint(3) UNSIGNED DEFAULT 18,
			max_age tinyint(3) UNSIGNED DEFAULT 99,
			max_distance int(11) UNSIGNED DEFAULT 50,
			preferred_gender varchar(50) DEFAULT NULL,
			preferred_ethnicity text DEFAULT NULL,
			preferred_body_type text DEFAULT NULL,
			preferred_education varchar(100) DEFAULT NULL,
			preferred_children varchar(50) DEFAULT NULL,
			show_profile tinyint(1) DEFAULT 1,
			allow_messages tinyint(1) DEFAULT 1,
			email_notifications tinyint(1) DEFAULT 1,
			push_notifications tinyint(1) DEFAULT 1,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (preference_id),
			UNIQUE KEY user_id (user_id)
		) $charset_collate;";

		// User verifications table.
		$table_verifications = $wpdb->prefix . 'wpmatch_user_verifications';
		$sql_verifications = "CREATE TABLE IF NOT EXISTS $table_verifications (
			verification_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			verification_type enum('email','phone','photo','identity','background') NOT NULL,
			verification_status enum('pending','verified','failed','expired') NOT NULL DEFAULT 'pending',
			verification_token varchar(255) DEFAULT NULL,
			verification_data text DEFAULT NULL,
			verified_at datetime DEFAULT NULL,
			expires_at datetime DEFAULT NULL,
			attempts tinyint(3) UNSIGNED DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (verification_id),
			KEY idx_user_verification (user_id, verification_type),
			KEY idx_token (verification_token),
			KEY idx_status (verification_status)
		) $charset_collate;";

		// Messages table.
		$table_messages = $wpdb->prefix . 'wpmatch_messages';
		$sql_messages = "CREATE TABLE IF NOT EXISTS $table_messages (
			message_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			conversation_id varchar(100) NOT NULL,
			sender_id bigint(20) UNSIGNED NOT NULL,
			recipient_id bigint(20) UNSIGNED NOT NULL,
			message_content text NOT NULL,
			message_type enum('text','emoji','image','gif') NOT NULL DEFAULT 'text',
			attachment_url varchar(255) DEFAULT NULL,
			is_read tinyint(1) DEFAULT 0,
			read_at datetime DEFAULT NULL,
			is_deleted_sender tinyint(1) DEFAULT 0,
			is_deleted_recipient tinyint(1) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (message_id),
			KEY idx_conversation (conversation_id),
			KEY idx_sender (sender_id),
			KEY idx_recipient (recipient_id),
			KEY idx_created (created_at),
			KEY idx_unread (recipient_id, is_read)
		) $charset_collate;";

		// Conversations table.
		$table_conversations = $wpdb->prefix . 'wpmatch_conversations';
		$sql_conversations = "CREATE TABLE IF NOT EXISTS $table_conversations (
			conversation_id varchar(100) NOT NULL,
			user1_id bigint(20) UNSIGNED NOT NULL,
			user2_id bigint(20) UNSIGNED NOT NULL,
			last_message_id bigint(20) UNSIGNED DEFAULT NULL,
			last_message_at datetime DEFAULT NULL,
			user1_archived tinyint(1) DEFAULT 0,
			user2_archived tinyint(1) DEFAULT 0,
			user1_blocked tinyint(1) DEFAULT 0,
			user2_blocked tinyint(1) DEFAULT 0,
			user1_deleted tinyint(1) DEFAULT 0,
			user2_deleted tinyint(1) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (conversation_id),
			KEY idx_user1 (user1_id),
			KEY idx_user2 (user2_id),
			KEY idx_last_message (last_message_at),
			UNIQUE KEY unique_users (user1_id, user2_id)
		) $charset_collate;";

		// Job queue table.
		$table_job_queue = $wpdb->prefix . 'wpmatch_job_queue';
		$sql_job_queue = "CREATE TABLE IF NOT EXISTS $table_job_queue (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			job_type varchar(100) NOT NULL,
			job_data longtext,
			priority int(11) NOT NULL DEFAULT 5,
			status varchar(20) NOT NULL DEFAULT 'pending',
			run_at datetime NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			started_at datetime NULL,
			completed_at datetime NULL,
			failed_at datetime NULL,
			attempts int(11) NOT NULL DEFAULT 0,
			max_attempts int(11) NOT NULL DEFAULT 3,
			result longtext,
			error_message text,
			PRIMARY KEY (id),
			KEY status (status),
			KEY run_at (run_at),
			KEY priority (priority),
			KEY job_type (job_type)
		) $charset_collate;";

		// Job logs table.
		$table_job_logs = $wpdb->prefix . 'wpmatch_job_logs';
		$sql_job_logs = "CREATE TABLE IF NOT EXISTS $table_job_logs (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			job_id bigint(20) NOT NULL,
			event varchar(50) NOT NULL,
			message text,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY job_id (job_id),
			KEY event (event),
			FOREIGN KEY (job_id) REFERENCES $table_job_queue(id) ON DELETE CASCADE
		) $charset_collate;";

		// Interest categories table.
		$table_interest_categories = $wpdb->prefix . 'wpmatch_interest_categories';
		$sql_interest_categories = "CREATE TABLE IF NOT EXISTS $table_interest_categories (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			category_key varchar(100) NOT NULL,
			category_name varchar(255) NOT NULL,
			category_description text DEFAULT NULL,
			is_active tinyint(1) DEFAULT 1,
			sort_order int(11) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY category_key (category_key),
			KEY idx_active_sort (is_active, sort_order)
		) $charset_collate;";

		// Predefined interests table.
		$table_predefined_interests = $wpdb->prefix . 'wpmatch_predefined_interests';
		$sql_predefined_interests = "CREATE TABLE IF NOT EXISTS $table_predefined_interests (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			category_id bigint(20) UNSIGNED NOT NULL,
			interest_name varchar(255) NOT NULL,
			interest_slug varchar(255) NOT NULL,
			interest_description text DEFAULT NULL,
			is_active tinyint(1) DEFAULT 1,
			sort_order int(11) DEFAULT 0,
			usage_count int(11) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_category (category_id),
			KEY idx_active_sort (is_active, sort_order),
			KEY idx_slug (interest_slug),
			UNIQUE KEY unique_category_interest (category_id, interest_slug),
			FOREIGN KEY (category_id) REFERENCES $table_interest_categories(id) ON DELETE CASCADE
		) $charset_collate;";

		// Execute table creation.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_profiles );
		dbDelta( $sql_media );
		dbDelta( $sql_interests );
		dbDelta( $sql_preferences );
		dbDelta( $sql_verifications );
		dbDelta( $sql_messages );
		dbDelta( $sql_conversations );
		dbDelta( $sql_job_queue );
		dbDelta( $sql_job_logs );
		dbDelta( $sql_interest_categories );
		dbDelta( $sql_predefined_interests );

		// Store database version for future updates.
		update_option( 'wpmatch_db_version', '1.0.0' );
	}

	/**
	 * Run swipe database migrations.
	 */
	private static function run_swipe_migrations() {
		// Include the migration class.
		require_once plugin_dir_path( __FILE__ ) . 'class-wpmatch-swipe-migration.php';

		// Run migration.
		WPMatch_Swipe_Migration::migrate();
	}

	/**
	 * Set default plugin options.
	 */
	private static function set_default_options() {
		$default_options = array(
			// General settings.
			'enable_registration'     => true,
			'enable_social_login'     => true,
			'enable_phone_verification' => false,
			'require_email_verification' => true,
			'min_age'                 => 18,
			'max_age'                 => 99,

			// Profile settings.
			'max_photos'              => 10,
			'max_photo_size'          => 5, // MB.
			'allowed_photo_types'     => array( 'jpg', 'jpeg', 'png', 'gif' ),
			'enable_video_profiles'   => false,
			'max_video_size'          => 50, // MB.

			// Privacy settings.
			'default_profile_visibility' => 'public',
			'allow_anonymous_browsing'    => false,
			'enable_block_feature'        => true,
			'enable_report_feature'       => true,

			// Security settings.
			'enable_photo_verification'   => true,
			'enable_identity_verification' => false,
			'enable_background_checks'    => false,
			'max_login_attempts'          => 5,
			'lockout_duration'            => 30, // minutes.

			// Matching settings.
			'default_search_radius'       => 50, // miles.
			'enable_location_services'    => true,
			'enable_smart_matching'       => true,
			'daily_match_suggestions'     => 5,
		);

		// Only add default options if they don't exist.
		$existing_options = get_option( 'wpmatch_settings', array() );
		$merged_options = wp_parse_args( $existing_options, $default_options );
		update_option( 'wpmatch_settings', $merged_options );
	}

	/**
	 * Create custom user roles for the dating site.
	 */
	private static function create_user_roles() {
		// Dating Member role.
		add_role(
			'wpmatch_member',
			__( 'Dating Member', 'wpmatch' ),
			array(
				'read'                   => true,
				'wpmatch_edit_profile'   => true,
				'wpmatch_upload_media'   => true,
				'wpmatch_send_messages'  => true,
				'wpmatch_view_profiles'  => true,
				'wpmatch_use_search'     => true,
			)
		);

		// Premium Member role.
		add_role(
			'wpmatch_premium_member',
			__( 'Premium Member', 'wpmatch' ),
			array(
				'read'                    => true,
				'wpmatch_edit_profile'    => true,
				'wpmatch_upload_media'    => true,
				'wpmatch_send_messages'   => true,
				'wpmatch_view_profiles'   => true,
				'wpmatch_use_search'      => true,
				'wpmatch_unlimited_likes' => true,
				'wpmatch_see_who_liked'   => true,
				'wpmatch_advanced_search' => true,
				'wpmatch_boost_profile'   => true,
				'wpmatch_read_receipts'   => true,
			)
		);

		// Dating Moderator role.
		add_role(
			'wpmatch_moderator',
			__( 'Dating Moderator', 'wpmatch' ),
			array(
				'read'                      => true,
				'wpmatch_moderate_profiles' => true,
				'wpmatch_moderate_media'    => true,
				'wpmatch_moderate_messages' => true,
				'wpmatch_ban_users'         => true,
				'wpmatch_review_reports'    => true,
				'wpmatch_access_moderation' => true,
			)
		);

		// Add capabilities to administrator.
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'wpmatch_manage_settings' );
			$admin->add_cap( 'wpmatch_manage_users' );
			$admin->add_cap( 'wpmatch_view_analytics' );
			$admin->add_cap( 'wpmatch_moderate_profiles' );
			$admin->add_cap( 'wpmatch_moderate_media' );
			$admin->add_cap( 'wpmatch_moderate_messages' );
			$admin->add_cap( 'wpmatch_ban_users' );
			$admin->add_cap( 'wpmatch_review_reports' );
		}
	}
}