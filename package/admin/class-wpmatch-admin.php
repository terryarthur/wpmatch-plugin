<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package WPMatch
 */

/**
 * The admin-specific functionality of the plugin.
 */
class WPMatch_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register the stylesheets for the admin area.
	 */
	public function enqueue_styles() {
		wp_enqueue_style(
			$this->plugin_name,
			WPMATCH_PLUGIN_URL . 'admin/css/wpmatch-admin.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Register the JavaScript for the admin area.
	 */
	public function enqueue_scripts() {
		wp_enqueue_script(
			$this->plugin_name,
			WPMATCH_PLUGIN_URL . 'admin/js/wpmatch-admin.js',
			array( 'jquery' ),
			$this->version,
			false
		);

		// Localize script for AJAX.
		wp_localize_script(
			$this->plugin_name,
			'wpmatch_admin',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wpmatch_admin_nonce' ),
			)
		);
	}

	/**
	 * Add admin menu items.
	 */
	public function add_admin_menu() {
		// Main menu item.
		add_menu_page(
			__( 'WPMatch Dating', 'wpmatch' ),
			__( 'WPMatch', 'wpmatch' ),
			'manage_options',
			'wpmatch',
			array( $this, 'display_admin_dashboard' ),
			'dashicons-heart',
			30
		);

		// Dashboard submenu.
		add_submenu_page(
			'wpmatch',
			__( 'Dashboard', 'wpmatch' ),
			__( 'Dashboard', 'wpmatch' ),
			'manage_options',
			'wpmatch',
			array( $this, 'display_admin_dashboard' )
		);

		// Users submenu.
		add_submenu_page(
			'wpmatch',
			__( 'Dating Users', 'wpmatch' ),
			__( 'Users', 'wpmatch' ),
			'wpmatch_manage_users',
			'wpmatch-users',
			array( $this, 'display_users_page' )
		);

		// Settings submenu.
		add_submenu_page(
			'wpmatch',
			__( 'Settings', 'wpmatch' ),
			__( 'Settings', 'wpmatch' ),
			'manage_options',
			'wpmatch-settings',
			array( $this, 'display_settings_page' )
		);

		// Membership Setup submenu.
		add_submenu_page(
			'wpmatch',
			__( 'Membership Setup', 'wpmatch' ),
			__( 'Memberships', 'wpmatch' ),
			'manage_options',
			'wpmatch-membership-setup',
			array( $this, 'display_membership_setup_page' )
		);

		// Reports submenu.
		add_submenu_page(
			'wpmatch',
			__( 'Reports', 'wpmatch' ),
			__( 'Reports', 'wpmatch' ),
			'wpmatch_view_analytics',
			'wpmatch-reports',
			array( $this, 'display_reports_page' )
		);

		// Help & Documentation submenu (for site admins).
		add_submenu_page(
			'wpmatch',
			__( 'Admin Help & Setup', 'wpmatch' ),
			__( 'Admin Help', 'wpmatch' ),
			'manage_options',
			'wpmatch-admin-help',
			array( $this, 'display_admin_help_page' )
		);
	}

	/**
	 * Display the admin dashboard page.
	 */
	public function display_admin_dashboard() {
		require_once WPMATCH_PLUGIN_DIR . 'admin/partials/wpmatch-admin-dashboard.php';
	}

	/**
	 * Display the users management page.
	 */
	public function display_users_page() {
		require_once WPMATCH_PLUGIN_DIR . 'admin/partials/wpmatch-admin-users.php';
	}

	/**
	 * Display the settings page.
	 */
	public function display_settings_page() {
		require_once WPMATCH_PLUGIN_DIR . 'admin/partials/wpmatch-admin-settings.php';
	}

	/**
	 * Display the membership setup page.
	 */
	public function display_membership_setup_page() {
		// Process form submission if present.
		if ( class_exists( 'WPMatch_Membership_Setup' ) ) {
			WPMatch_Membership_Setup::process_setup_form();
		}

		// Display the page.
		require_once WPMATCH_PLUGIN_DIR . 'admin/partials/wpmatch-membership-setup.php';
	}

	/**
	 * Display the reports page.
	 */
	public function display_reports_page() {
		require_once WPMATCH_PLUGIN_DIR . 'admin/partials/wpmatch-admin-reports.php';
	}

	/**
	 * Display the admin help page.
	 */
	public function display_admin_help_page() {
		require_once WPMATCH_PLUGIN_DIR . 'admin/partials/wpmatch-admin-help.php';
	}

	/**
	 * Display admin notices.
	 */
	public function admin_notices() {
		// Check if we need to display any notices.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check for activation notice.
		if ( get_transient( 'wpmatch_activation_notice' ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'WPMatch Dating Plugin activated successfully!', 'wpmatch' ); ?></p>
			</div>
			<?php
			delete_transient( 'wpmatch_activation_notice' );
		}
	}

	/**
	 * Add plugin action links.
	 *
	 * @param array $links Existing links.
	 * @return array Modified links.
	 */
	public function add_action_links( $links ) {
		$action_links = array(
			'<a href="' . admin_url( 'admin.php?page=wpmatch-settings' ) . '">' . __( 'Settings', 'wpmatch' ) . '</a>',
		);
		return array_merge( $action_links, $links );
	}

	/**
	 * Add custom columns to users list.
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_user_columns( $columns ) {
		$columns['wpmatch_profile']     = __( 'Dating Profile', 'wpmatch' );
		$columns['wpmatch_verified']    = __( 'Verified', 'wpmatch' );
		$columns['wpmatch_last_active'] = __( 'Last Active', 'wpmatch' );
		return $columns;
	}

	/**
	 * Display custom column content.
	 *
	 * @param string $value     Column value.
	 * @param string $column_name Column name.
	 * @param int    $user_id   User ID.
	 * @return string Column content.
	 */
	public function show_user_columns( $value, $column_name, $user_id ) {
		switch ( $column_name ) {
			case 'wpmatch_profile':
				$profile_complete = get_user_meta( $user_id, 'wpmatch_profile_complete', true );
				if ( $profile_complete ) {
					$value = '<span style="color: green;">✓</span> ' . __( 'Complete', 'wpmatch' );
				} else {
					$value = '<span style="color: orange;">○</span> ' . __( 'Incomplete', 'wpmatch' );
				}
				break;

			case 'wpmatch_verified':
				$verified = get_user_meta( $user_id, 'wpmatch_verification_status', true );
				if ( 'verified' === $verified ) {
					$value = '<span style="color: green;">✓</span>';
				} else {
					$value = '<span style="color: gray;">✗</span>';
				}
				break;

			case 'wpmatch_last_active':
				$last_active = get_user_meta( $user_id, 'wpmatch_last_active', true );
				if ( $last_active ) {
					$value = human_time_diff( strtotime( $last_active ) ) . ' ' . __( 'ago', 'wpmatch' );
				} else {
					$value = __( 'Never', 'wpmatch' );
				}
				break;
		}
		return $value;
	}

	/**
	 * Handle admin AJAX requests.
	 */
	public function handle_admin_ajax() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpmatch_admin_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed', 'wpmatch' ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'wpmatch' ) );
		}

		// Handle different actions.
		$action = isset( $_POST['action_type'] ) ? sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) : '';

		switch ( $action ) {
			case 'save_settings':
				$this->save_settings();
				break;
			default:
				wp_die( esc_html__( 'Invalid action', 'wpmatch' ) );
		}
	}

	/**
	 * Save plugin settings.
	 */
	private function save_settings() {
		// Implementation for saving settings.
		wp_send_json_success( array( 'message' => __( 'Settings saved successfully', 'wpmatch' ) ) );
	}

	/**
	 * Generate sample data for testing.
	 */
	public function generate_sample_data() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpmatch_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'wpmatch' ) ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'wpmatch' ) ) );
		}

		try {
			$created_users = $this->create_sample_users();
			wp_send_json_success(
				array(
					'message'       => sprintf(
						/* translators: %d: number of created users */
						__( 'Created %d sample users with complete profiles!', 'wpmatch' ),
						count( $created_users )
					),
					'users_created' => count( $created_users ),
				)
			);
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Create demo pages automatically.
	 */
	public function create_demo_pages() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpmatch_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'wpmatch' ) ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'wpmatch' ) ) );
		}

		try {
			$created_pages = $this->create_essential_pages();

			// Create navigation menu with the pages.
			$menu_created = $this->create_dating_navigation_menu( $created_pages );

			// Enable WordPress user registration.
			update_option( 'users_can_register', 1 );

			$success_message = sprintf(
				/* translators: %d: number of created pages */
				__( 'Created %d essential pages and enabled user registration!', 'wpmatch' ),
				count( $created_pages )
			);

			if ( $menu_created ) {
				$success_message .= __( ' Navigation menu "Dating Site" was also created.', 'wpmatch' );
			}

			wp_send_json_success(
				array(
					'message'       => $success_message,
					'pages_created' => $created_pages,
					'menu_created'  => $menu_created,
				)
			);
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Clean up demo data (users, pages, menu).
	 */
	public function cleanup_demo_data() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpmatch_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'wpmatch' ) ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'wpmatch' ) ) );
		}

		try {
			$cleanup_results = $this->remove_demo_data();
			wp_send_json_success(
				array(
					'message'        => __( 'Demo data cleanup completed successfully!', 'wpmatch' ),
					'cleanup_counts' => $cleanup_results,
				)
			);
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Remove demo data from the system.
	 *
	 * @return array Cleanup statistics.
	 */
	private function remove_demo_data() {
		$cleanup_stats = array(
			'users_removed'    => 0,
			'pages_removed'    => 0,
			'menus_removed'    => 0,
			'profiles_removed' => 0,
			'messages_removed' => 0,
		);

		// Remove demo users (identified by email domain @example.com).
		$demo_users = get_users(
			array(
				'search'         => '@example.com',
				'search_columns' => array( 'user_email' ),
				'fields'         => 'all',
			)
		);

		foreach ( $demo_users as $user ) {
			// Only remove users with @example.com emails to be safe.
			if ( strpos( $user->user_email, '@example.com' ) !== false ) {
				// Clean up user's profile data and interactions first.
				$this->cleanup_user_profile_data( $user->ID );

				// Remove the user.
				if ( wp_delete_user( $user->ID ) ) {
					++$cleanup_stats['users_removed'];
				}
			}
		}

		// Remove demo pages (identified by specific slugs we created).
		$demo_page_slugs = array(
			'dating',
			'browse',
			'search',
			'matches',
			'profile/edit',
			'messages',
			'premium',
			'help',
		);

		foreach ( $demo_page_slugs as $slug ) {
			$page = get_page_by_path( $slug );
			if ( $page && ! is_wp_error( $page ) ) {
				// Additional check - only remove if it contains our demo content.
				$content = get_post_field( 'post_content', $page->ID );
				if ( strpos( $content, 'wpmatch-' ) !== false || strpos( $content, 'Dating Home' ) !== false || strpos( $content, 'Find your perfect match' ) !== false ) {
					if ( wp_delete_post( $page->ID, true ) ) {
						++$cleanup_stats['pages_removed'];
					}
				}
			}
		}

		// Remove demo navigation menu.
		$menu = wp_get_nav_menu_object( 'Dating Navigation' );
		if ( $menu && ! is_wp_error( $menu ) ) {
			if ( wp_delete_nav_menu( $menu->term_id ) ) {
				++$cleanup_stats['menus_removed'];
			}
		}

		// Clean up any orphaned profile data and demo interactions.
		$this->cleanup_orphaned_demo_data();

		return $cleanup_stats;
	}

	/**
	 * Clean up user profile data and interactions.
	 *
	 * @param int $user_id User ID to clean up.
	 */
	private function cleanup_user_profile_data( $user_id ) {
		global $wpdb;

		// Clean up user meta related to WPMatch.
		$meta_keys_to_remove = array(
			'wpmatch_profile',
			'wpmatch_preferences',
			'wpmatch_location',
			'wpmatch_last_active',
			'wpmatch_verification_status',
			'wpmatch_membership_level',
		);

		foreach ( $meta_keys_to_remove as $meta_key ) {
			delete_user_meta( $user_id, $meta_key );
		}

		// Clean up any matches, likes, messages involving this user.
		// This would depend on how your database tables are structured.
		// For now, we'll clean up based on common patterns.

		// If you have custom tables for matches, likes, messages, etc., clean them here.
		// Example (adjust table names and structure as needed):
		/*
		$wpdb->delete(
			$wpdb->prefix . 'wpmatch_likes',
			array(
				'user_id' => $user_id
			),
			array( '%d' )
		);

		$wpdb->delete(
			$wpdb->prefix . 'wpmatch_likes',
			array(
				'liked_user_id' => $user_id
			),
			array( '%d' )
		);

		$wpdb->delete(
			$wpdb->prefix . 'wpmatch_matches',
			array(
				'user_1_id' => $user_id
			),
			array( '%d' )
		);

		$wpdb->delete(
			$wpdb->prefix . 'wpmatch_matches',
			array(
				'user_2_id' => $user_id
			),
			array( '%d' )
		);

		$wpdb->delete(
			$wpdb->prefix . 'wpmatch_messages',
			array(
				'sender_id' => $user_id
			),
			array( '%d' )
		);

		$wpdb->delete(
			$wpdb->prefix . 'wpmatch_messages',
			array(
				'recipient_id' => $user_id
			),
			array( '%d' )
		);
		*/
	}

	/**
	 * Clean up any orphaned demo data.
	 */
	private function cleanup_orphaned_demo_data() {
		global $wpdb;

		// Clean up any demo-related options.
		$demo_options = array(
			'wpmatch_demo_users_created',
			'wpmatch_demo_pages_created',
			'wpmatch_demo_menu_created',
		);

		foreach ( $demo_options as $option ) {
			delete_option( $option );
		}

		// Clean up any orphaned profile data where user no longer exists.
		// This is more complex and would depend on your specific data structure.
	}

	/**
	 * Create sample users with complete profiles.
	 */
	private function create_sample_users() {
		$profile_manager = new WPMatch_Profile_Manager();
		$created_users   = array();

		// Sample user data.
		$sample_users = array(
			array(
				'username'     => 'sarah_adventurer',
				'email'        => 'sarah.demo@example.com',
				'display_name' => 'Sarah',
				'profile'      => array(
					'age'            => 28,
					'gender'         => 'female',
					'orientation'    => 'straight',
					'location'       => 'San Francisco, CA',
					'latitude'       => 37.7749,
					'longitude'      => -122.4194,
					'education'      => 'bachelors',
					'profession'     => 'Software Engineer',
					'height'         => 165,
					'body_type'      => 'athletic',
					'smoking'        => 'never',
					'drinking'       => 'socially',
					'children'       => 'none',
					'wants_children' => 'maybe',
					'about_me'       => 'Adventure seeker who loves hiking, rock climbing, and trying new restaurants. I work in tech but I\'m passionate about travel and photography. Looking for someone who shares my love for the outdoors and can keep up with my spontaneous weekend trips!',
					'looking_for'    => 'Someone genuine, adventurous, and ready for both mountain hikes and cozy movie nights. Bonus points if you have your own passport and aren\'t afraid of heights!',
					'preferences'    => array(
						'min_age'          => 25,
						'max_age'          => 35,
						'max_distance'     => 50,
						'preferred_gender' => 'male',
					),
				),
			),
			array(
				'username'     => 'mike_chef',
				'email'        => 'mike.demo@example.com',
				'display_name' => 'Mike',
				'profile'      => array(
					'age'            => 32,
					'gender'         => 'male',
					'orientation'    => 'straight',
					'location'       => 'San Francisco, CA',
					'latitude'       => 37.7849,
					'longitude'      => -122.4094,
					'education'      => 'some_college',
					'profession'     => 'Chef',
					'height'         => 180,
					'body_type'      => 'average',
					'smoking'        => 'never',
					'drinking'       => 'occasionally',
					'children'       => 'none',
					'wants_children' => 'yes',
					'about_me'       => 'Professional chef with a passion for creating amazing food and experiences. When I\'m not in the kitchen, you\'ll find me at farmers markets, trying new wines, or playing guitar. I believe good food brings people together.',
					'looking_for'    => 'Someone who appreciates good food (I love cooking for others!), enjoys trying new things, and values quality time together. Family-oriented and down-to-earth.',
					'preferences'    => array(
						'min_age'          => 22,
						'max_age'          => 35,
						'max_distance'     => 30,
						'preferred_gender' => 'female',
					),
				),
			),
			array(
				'username'     => 'emma_artist',
				'email'        => 'emma.demo@example.com',
				'display_name' => 'Emma',
				'profile'      => array(
					'age'            => 26,
					'gender'         => 'female',
					'orientation'    => 'bisexual',
					'location'       => 'Oakland, CA',
					'latitude'       => 37.8044,
					'longitude'      => -122.2712,
					'education'      => 'masters',
					'profession'     => 'Graphic Designer',
					'height'         => 160,
					'body_type'      => 'slim',
					'smoking'        => 'never',
					'drinking'       => 'socially',
					'children'       => 'none',
					'wants_children' => 'not_sure',
					'about_me'       => 'Creative soul who sees art in everything. I work as a graphic designer but spend my free time painting, visiting galleries, and exploring the city\'s coffee scene. I\'m a dog mom to a rescue pittie named Pixel.',
					'looking_for'    => 'Someone creative, open-minded, and kind. I value deep conversations, artistic expression, and making the world a more beautiful place together.',
					'preferences'    => array(
						'min_age'          => 23,
						'max_age'          => 32,
						'max_distance'     => 40,
						'preferred_gender' => '',
					),
				),
			),
			array(
				'username'     => 'alex_teacher',
				'email'        => 'alex.demo@example.com',
				'display_name' => 'Alex',
				'profile'      => array(
					'age'            => 29,
					'gender'         => 'male',
					'orientation'    => 'straight',
					'location'       => 'Berkeley, CA',
					'latitude'       => 37.8715,
					'longitude'      => -122.2730,
					'education'      => 'masters',
					'profession'     => 'Elementary School Teacher',
					'height'         => 175,
					'body_type'      => 'athletic',
					'smoking'        => 'never',
					'drinking'       => 'occasionally',
					'children'       => 'none',
					'wants_children' => 'yes',
					'about_me'       => 'Elementary school teacher who loves working with kids and making learning fun. I\'m into fitness, reading, and board games. Weekends you\'ll find me hiking, at a brewery with friends, or planning my next classroom theme.',
					'looking_for'    => 'Someone kind, intelligent, and who loves kids (or at least tolerates my endless stories about my students!). Looking for a partner to build a family with.',
					'preferences'    => array(
						'min_age'          => 24,
						'max_age'          => 33,
						'max_distance'     => 25,
						'preferred_gender' => 'female',
					),
				),
			),
			array(
				'username'     => 'jess_nurse',
				'email'        => 'jess.demo@example.com',
				'display_name' => 'Jessica',
				'profile'      => array(
					'age'            => 31,
					'gender'         => 'female',
					'orientation'    => 'straight',
					'location'       => 'San Jose, CA',
					'latitude'       => 37.3382,
					'longitude'      => -121.8863,
					'education'      => 'bachelors',
					'profession'     => 'Registered Nurse',
					'height'         => 168,
					'body_type'      => 'curvy',
					'smoking'        => 'never',
					'drinking'       => 'socially',
					'children'       => 'have_children',
					'wants_children' => 'maybe',
					'about_me'       => 'ER nurse and single mom to an amazing 8-year-old. I\'m passionate about helping others and making a difference. In my free time, I love yoga, reading, and family adventures. My son is my world, but I\'m ready to share my heart.',
					'looking_for'    => 'Someone patient, understanding, and good with kids. Looking for a genuine connection with someone who values family and isn\'t afraid of a ready-made family.',
					'preferences'    => array(
						'min_age'          => 28,
						'max_age'          => 40,
						'max_distance'     => 60,
						'preferred_gender' => 'male',
					),
				),
			),
			array(
				'username'     => 'jessica_doctor',
				'email'        => 'jessica.demo@example.com',
				'display_name' => 'Jessica',
				'profile'      => array(
					'age'            => 31,
					'gender'         => 'female',
					'orientation'    => 'straight',
					'location'       => 'Palo Alto, CA',
					'latitude'       => 37.4419,
					'longitude'      => -122.1430,
					'education'      => 'doctorate',
					'profession'     => 'Doctor',
					'height'         => 168,
					'body_type'      => 'fit',
					'smoking'        => 'never',
					'drinking'       => 'rarely',
					'children'       => 'none',
					'wants_children' => 'yes',
					'about_me'       => 'Pediatrician who loves working with kids. When I\'m not at the hospital, I enjoy pilates, cooking healthy meals, and weekend getaways. I value work-life balance and believe in making time for the people and things that matter.',
					'looking_for'    => 'Someone ambitious yet grounded, who values health and wellness. Looking for a partner to share both quiet nights in and exciting adventures.',
					'preferences'    => array(
						'min_age'          => 28,
						'max_age'          => 38,
						'max_distance'     => 45,
						'preferred_gender' => 'male',
					),
				),
			),
			array(
				'username'     => 'ryan_engineer',
				'email'        => 'ryan.demo@example.com',
				'display_name' => 'Ryan',
				'profile'      => array(
					'age'            => 27,
					'gender'         => 'male',
					'orientation'    => 'straight',
					'location'       => 'San Jose, CA',
					'latitude'       => 37.3382,
					'longitude'      => -121.8863,
					'education'      => 'masters',
					'profession'     => 'Software Engineer',
					'height'         => 175,
					'body_type'      => 'athletic',
					'smoking'        => 'never',
					'drinking'       => 'socially',
					'children'       => 'none',
					'wants_children' => 'yes',
					'about_me'       => 'Tech enthusiast who loves building apps and solving complex problems. Outside of coding, I\'m into rock climbing, board games, and exploring craft breweries. Always up for trying new restaurants or planning the next adventure.',
					'looking_for'    => 'Someone intelligent, curious, and fun to be around. Bonus points if you can beat me at Settlers of Catan or suggest a great hiking trail!',
					'preferences'    => array(
						'min_age'          => 22,
						'max_age'          => 32,
						'max_distance'     => 40,
						'preferred_gender' => 'female',
					),
				),
			),
			array(
				'username'     => 'maria_lawyer',
				'email'        => 'maria.demo@example.com',
				'display_name' => 'Maria',
				'profile'      => array(
					'age'            => 34,
					'gender'         => 'female',
					'orientation'    => 'straight',
					'location'       => 'Berkeley, CA',
					'latitude'       => 37.8715,
					'longitude'      => -122.2730,
					'education'      => 'masters',
					'profession'     => 'Lawyer',
					'height'         => 163,
					'body_type'      => 'average',
					'smoking'        => 'never',
					'drinking'       => 'occasionally',
					'children'       => 'none',
					'wants_children' => 'maybe',
					'about_me'       => 'Environmental lawyer passionate about making a positive impact. I love traveling, learning new languages (currently studying Italian), and hosting dinner parties for friends. Weekends often find me at farmers markets or exploring new neighborhoods.',
					'looking_for'    => 'Someone intellectually stimulating who shares my passion for social justice and good conversation over great wine.',
					'preferences'    => array(
						'min_age'          => 30,
						'max_age'          => 42,
						'max_distance'     => 35,
						'preferred_gender' => 'male',
					),
				),
			),
			array(
				'username'     => 'brandon_photographer',
				'email'        => 'brandon.demo@example.com',
				'display_name' => 'Brandon',
				'profile'      => array(
					'age'            => 30,
					'gender'         => 'male',
					'orientation'    => 'straight',
					'location'       => 'San Francisco, CA',
					'latitude'       => 37.7849,
					'longitude'      => -122.4194,
					'education'      => 'bachelors',
					'profession'     => 'Photographer',
					'height'         => 183,
					'body_type'      => 'tall',
					'smoking'        => 'never',
					'drinking'       => 'socially',
					'children'       => 'none',
					'wants_children' => 'yes',
					'about_me'       => 'Wedding and portrait photographer who sees beauty in everyday moments. I love capturing people\'s authentic selves and telling their stories through images. When not behind the camera, I enjoy surfing, vinyl record collecting, and discovering hidden gems in the city.',
					'looking_for'    => 'Someone creative and spontaneous who appreciates art and isn\'t camera-shy. Let\'s explore the city together and create some beautiful memories!',
					'preferences'    => array(
						'min_age'          => 24,
						'max_age'          => 35,
						'max_distance'     => 50,
						'preferred_gender' => 'female',
					),
				),
			),
			array(
				'username'     => 'lisa_yoga',
				'email'        => 'lisa.demo@example.com',
				'display_name' => 'Lisa',
				'profile'      => array(
					'age'            => 29,
					'gender'         => 'female',
					'orientation'    => 'bisexual',
					'location'       => 'Mill Valley, CA',
					'latitude'       => 37.9061,
					'longitude'      => -122.5450,
					'education'      => 'bachelors',
					'profession'     => 'Yoga Instructor',
					'height'         => 170,
					'body_type'      => 'athletic',
					'smoking'        => 'never',
					'drinking'       => 'rarely',
					'children'       => 'none',
					'wants_children' => 'not_sure',
					'about_me'       => 'Yoga instructor and wellness coach who believes in living mindfully. I start each day with meditation and end it with gratitude. Love spending time in nature, trying plant-based recipes, and helping others find their inner peace.',
					'looking_for'    => 'Someone who values personal growth, mindfulness, and authentic connections. Whether you\'re a fellow yogi or just beginning your wellness journey, let\'s support each other.',
					'preferences'    => array(
						'min_age'          => 25,
						'max_age'          => 35,
						'max_distance'     => 60,
						'preferred_gender' => '',
					),
				),
			),
			array(
				'username'     => 'carlos_startup',
				'email'        => 'carlos.demo@example.com',
				'display_name' => 'Carlos',
				'profile'      => array(
					'age'            => 33,
					'gender'         => 'male',
					'orientation'    => 'straight',
					'location'       => 'Mountain View, CA',
					'latitude'       => 37.3861,
					'longitude'      => -122.0839,
					'education'      => 'masters',
					'profession'     => 'Entrepreneur',
					'height'         => 178,
					'body_type'      => 'average',
					'smoking'        => 'never',
					'drinking'       => 'socially',
					'children'       => 'none',
					'wants_children' => 'yes',
					'about_me'       => 'Serial entrepreneur working on my third startup in the sustainability space. When I\'m not building the future, I love salsa dancing, cooking elaborate Sunday dinners, and mentoring young entrepreneurs. Family is everything to me.',
					'looking_for'    => 'Someone ambitious and family-oriented who can handle the startup life while building something beautiful together. Bonus if you can salsa dance or teach me something new!',
					'preferences'    => array(
						'min_age'          => 26,
						'max_age'          => 35,
						'max_distance'     => 30,
						'preferred_gender' => 'female',
					),
				),
			),
			array(
				'username'     => 'amy_veterinarian',
				'email'        => 'amy.demo@example.com',
				'display_name' => 'Amy',
				'profile'      => array(
					'age'            => 28,
					'gender'         => 'female',
					'orientation'    => 'straight',
					'location'       => 'Redwood City, CA',
					'latitude'       => 37.4852,
					'longitude'      => -122.2364,
					'education'      => 'doctorate',
					'profession'     => 'Veterinarian',
					'height'         => 165,
					'body_type'      => 'petite',
					'smoking'        => 'never',
					'drinking'       => 'socially',
					'children'       => 'none',
					'wants_children' => 'yes',
					'about_me'       => 'Veterinarian who\'s passionate about animal welfare and rescue work. I volunteer at local shelters and have two rescue dogs who are my world. Love hiking with my pups, trying new breweries, and binge-watching nature documentaries.',
					'looking_for'    => 'Fellow animal lover who understands that my dogs come first! Looking for someone kind, patient, and ready for slobbery kisses (from the dogs, mostly).',
					'preferences'    => array(
						'min_age'          => 25,
						'max_age'          => 35,
						'max_distance'     => 40,
						'preferred_gender' => 'male',
					),
				),
			),
			array(
				'username'     => 'jason_firefighter',
				'email'        => 'jason.demo@example.com',
				'display_name' => 'Jason',
				'profile'      => array(
					'age'            => 31,
					'gender'         => 'male',
					'orientation'    => 'straight',
					'location'       => 'San Mateo, CA',
					'latitude'       => 37.5630,
					'longitude'      => -122.3255,
					'education'      => 'some_college',
					'profession'     => 'Firefighter',
					'height'         => 185,
					'body_type'      => 'muscular',
					'smoking'        => 'never',
					'drinking'       => 'occasionally',
					'children'       => 'none',
					'wants_children' => 'yes',
					'about_me'       => 'Firefighter and EMT who loves serving the community. When I\'m off duty, I\'m usually at the gym, playing softball, or working on my motorcycle. I value loyalty, honesty, and making every day count.',
					'looking_for'    => 'Someone genuine and down-to-earth who can handle the unpredictable schedule of a first responder. Looking for my ride-or-die partner in crime.',
					'preferences'    => array(
						'min_age'          => 24,
						'max_age'          => 33,
						'max_distance'     => 45,
						'preferred_gender' => 'female',
					),
				),
			),
			array(
				'username'     => 'sophia_architect',
				'email'        => 'sophia.demo@example.com',
				'display_name' => 'Sophia',
				'profile'      => array(
					'age'            => 30,
					'gender'         => 'female',
					'orientation'    => 'straight',
					'location'       => 'San Francisco, CA',
					'latitude'       => 37.7749,
					'longitude'      => -122.4194,
					'education'      => 'masters',
					'profession'     => 'Architect',
					'height'         => 172,
					'body_type'      => 'tall',
					'smoking'        => 'never',
					'drinking'       => 'socially',
					'children'       => 'none',
					'wants_children' => 'maybe',
					'about_me'       => 'Architect specializing in sustainable design. I love creating spaces that bring people together and respect our environment. Outside of work, I enjoy urban sketching, vintage shopping, and exploring architectural marvels around the world.',
					'looking_for'    => 'Someone who appreciates good design, thoughtful conversation, and isn\'t afraid to get lost wandering through new cities with me.',
					'preferences'    => array(
						'min_age'          => 27,
						'max_age'          => 37,
						'max_distance'     => 40,
						'preferred_gender' => 'male',
					),
				),
			),
			array(
				'username'     => 'kevin_teacher',
				'email'        => 'kevin.demo@example.com',
				'display_name' => 'Kevin',
				'profile'      => array(
					'age'            => 26,
					'gender'         => 'male',
					'orientation'    => 'straight',
					'location'       => 'Oakland, CA',
					'latitude'       => 37.8044,
					'longitude'      => -122.2712,
					'education'      => 'masters',
					'profession'     => 'High School Teacher',
					'height'         => 173,
					'body_type'      => 'slim',
					'smoking'        => 'never',
					'drinking'       => 'socially',
					'children'       => 'none',
					'wants_children' => 'yes',
					'about_me'       => 'High school math teacher who loves inspiring the next generation. I coach the robotics team and run a summer coding camp. Outside school, I\'m into board games, fantasy football, and trying to perfect my pizza-making skills.',
					'looking_for'    => 'Someone patient, kind, and who believes education can change the world. Bonus points if you can help me understand why teenagers think my math jokes aren\'t funny!',
					'preferences'    => array(
						'min_age'          => 22,
						'max_age'          => 30,
						'max_distance'     => 35,
						'preferred_gender' => 'female',
					),
				),
			),
			array(
				'username'     => 'natalie_marketing',
				'email'        => 'natalie.demo@example.com',
				'display_name' => 'Natalie',
				'profile'      => array(
					'age'            => 27,
					'gender'         => 'female',
					'orientation'    => 'straight',
					'location'       => 'San Francisco, CA',
					'latitude'       => 37.7849,
					'longitude'      => -122.4094,
					'education'      => 'bachelors',
					'profession'     => 'Marketing Manager',
					'height'         => 160,
					'body_type'      => 'average',
					'smoking'        => 'never',
					'drinking'       => 'socially',
					'children'       => 'none',
					'wants_children' => 'not_sure',
					'about_me'       => 'Marketing manager by day, food blogger by night. I love discovering new restaurants, traveling to food destinations, and sharing my culinary adventures on social media. Always planning my next trip or dinner party!',
					'looking_for'    => 'Someone adventurous with a good appetite! Looking for a partner in culinary crimes who\'s ready to explore the world one bite at a time.',
					'preferences'    => array(
						'min_age'          => 24,
						'max_age'          => 32,
						'max_distance'     => 30,
						'preferred_gender' => 'male',
					),
				),
			),
			array(
				'username'     => 'daniel_musician',
				'email'        => 'daniel.demo@example.com',
				'display_name' => 'Daniel',
				'profile'      => array(
					'age'            => 29,
					'gender'         => 'male',
					'orientation'    => 'bisexual',
					'location'       => 'Berkeley, CA',
					'latitude'       => 37.8715,
					'longitude'      => -122.2730,
					'education'      => 'bachelors',
					'profession'     => 'Musician',
					'height'         => 175,
					'body_type'      => 'artistic',
					'smoking'        => 'occasionally',
					'drinking'       => 'socially',
					'children'       => 'none',
					'wants_children' => 'not_sure',
					'about_me'       => 'Indie folk musician who plays coffee shops and small venues around the Bay Area. Music is my language for expressing what words can\'t capture. When not writing songs, I enjoy vintage shopping, poetry readings, and long walks through Golden Gate Park.',
					'looking_for'    => 'Someone creative and soulful who appreciates art in all its forms. Looking for deep connections and meaningful conversations under the stars.',
					'preferences'    => array(
						'min_age'          => 24,
						'max_age'          => 35,
						'max_distance'     => 50,
						'preferred_gender' => '',
					),
				),
			),
			array(
				'username'     => 'rachel_scientist',
				'email'        => 'rachel.demo@example.com',
				'display_name' => 'Rachel',
				'profile'      => array(
					'age'            => 32,
					'gender'         => 'female',
					'orientation'    => 'straight',
					'location'       => 'Fremont, CA',
					'latitude'       => 37.5485,
					'longitude'      => -121.9886,
					'education'      => 'doctorate',
					'profession'     => 'Research Scientist',
					'height'         => 167,
					'body_type'      => 'average',
					'smoking'        => 'never',
					'drinking'       => 'rarely',
					'children'       => 'none',
					'wants_children' => 'yes',
					'about_me'       => 'Biotech research scientist working on cancer treatments. Science nerd who loves puzzles, escape rooms, and documentaries. I find wonder in both microscopic cells and vast galaxies. Always curious and asking "why" about everything.',
					'looking_for'    => 'Someone intellectually curious who can match my enthusiasm for learning new things. Bonus if you can explain quantum physics or recommend a great sci-fi book!',
					'preferences'    => array(
						'min_age'          => 28,
						'max_age'          => 38,
						'max_distance'     => 45,
						'preferred_gender' => 'male',
					),
				),
			),
			array(
				'username'     => 'tyler_consultant',
				'email'        => 'tyler.demo@example.com',
				'display_name' => 'Tyler',
				'profile'      => array(
					'age'            => 28,
					'gender'         => 'male',
					'orientation'    => 'straight',
					'location'       => 'Sunnyvale, CA',
					'latitude'       => 37.3688,
					'longitude'      => -122.0363,
					'education'      => 'masters',
					'profession'     => 'Business Consultant',
					'height'         => 180,
					'body_type'      => 'athletic',
					'smoking'        => 'never',
					'drinking'       => 'socially',
					'children'       => 'none',
					'wants_children' => 'yes',
					'about_me'       => 'Business consultant who helps startups scale efficiently. I love solving complex problems and turning visions into reality. Outside work, I\'m training for my next triathlon, learning to cook, and planning weekend adventures.',
					'looking_for'    => 'Someone ambitious and goal-oriented who values both hard work and play. Let\'s motivate each other to be our best selves while having fun along the way!',
					'preferences'    => array(
						'min_age'          => 24,
						'max_age'          => 32,
						'max_distance'     => 35,
						'preferred_gender' => 'female',
					),
				),
			),
			array(
				'username'     => 'olivia_writer',
				'email'        => 'olivia.demo@example.com',
				'display_name' => 'Olivia',
				'profile'      => array(
					'age'            => 26,
					'gender'         => 'female',
					'orientation'    => 'straight',
					'location'       => 'San Francisco, CA',
					'latitude'       => 37.7649,
					'longitude'      => -122.4094,
					'education'      => 'masters',
					'profession'     => 'Freelance Writer',
					'height'         => 163,
					'body_type'      => 'petite',
					'smoking'        => 'never',
					'drinking'       => 'occasionally',
					'children'       => 'none',
					'wants_children' => 'maybe',
					'about_me'       => 'Freelance writer specializing in travel and lifestyle content. I\'ve backpacked through 30 countries and counting! Love storytelling, cozy bookshops, rainy day reading sessions, and finding the perfect local coffee spot in every city.',
					'looking_for'    => 'Fellow wanderer or someone who dreams of adventure. Looking for my co-author in life\'s greatest story - whether that\'s exploring new countries or new neighborhoods.',
					'preferences'    => array(
						'min_age'          => 24,
						'max_age'          => 32,
						'max_distance'     => 40,
						'preferred_gender' => 'male',
					),
				),
			),
			array(
				'username'     => 'marcus_trainer',
				'email'        => 'marcus.demo@example.com',
				'display_name' => 'Marcus',
				'profile'      => array(
					'age'            => 30,
					'gender'         => 'male',
					'orientation'    => 'straight',
					'location'       => 'San Francisco, CA',
					'latitude'       => 37.7749,
					'longitude'      => -122.4194,
					'education'      => 'bachelors',
					'profession'     => 'Personal Trainer',
					'height'         => 188,
					'body_type'      => 'muscular',
					'smoking'        => 'never',
					'drinking'       => 'rarely',
					'children'       => 'none',
					'wants_children' => 'yes',
					'about_me'       => 'Personal trainer and nutrition coach passionate about helping people reach their fitness goals. Former college athlete who believes in balance - work hard, eat well, and enjoy life. Love meal prepping, weekend hikes, and Sunday football.',
					'looking_for'    => 'Someone who values health and wellness but knows how to have fun. Looking for a workout partner in life who\'s ready to build something strong together.',
					'preferences'    => array(
						'min_age'          => 24,
						'max_age'          => 33,
						'max_distance'     => 40,
						'preferred_gender' => 'female',
					),
				),
			),
			array(
				'username'     => 'grace_therapist',
				'email'        => 'grace.demo@example.com',
				'display_name' => 'Grace',
				'profile'      => array(
					'age'            => 33,
					'gender'         => 'female',
					'orientation'    => 'straight',
					'location'       => 'Walnut Creek, CA',
					'latitude'       => 37.9063,
					'longitude'      => -122.0653,
					'education'      => 'masters',
					'profession'     => 'Marriage Therapist',
					'height'         => 168,
					'body_type'      => 'average',
					'smoking'        => 'never',
					'drinking'       => 'occasionally',
					'children'       => 'none',
					'wants_children' => 'yes',
					'about_me'       => 'Marriage and family therapist who believes in the power of authentic communication and emotional connection. I love helping couples build stronger relationships. Outside work, I enjoy gardening, pottery classes, and hosting intimate dinner parties.',
					'looking_for'    => 'Someone emotionally intelligent who values deep connection and personal growth. Looking for a genuine partnership built on trust, communication, and mutual respect.',
					'preferences'    => array(
						'min_age'          => 29,
						'max_age'          => 40,
						'max_distance'     => 50,
						'preferred_gender' => 'male',
					),
				),
			),
			array(
				'username'     => 'nathan_chef',
				'email'        => 'nathan.demo@example.com',
				'display_name' => 'Nathan',
				'profile'      => array(
					'age'            => 35,
					'gender'         => 'male',
					'orientation'    => 'straight',
					'location'       => 'Napa, CA',
					'latitude'       => 38.2975,
					'longitude'      => -122.2869,
					'education'      => 'some_college',
					'profession'     => 'Executive Chef',
					'height'         => 182,
					'body_type'      => 'average',
					'smoking'        => 'never',
					'drinking'       => 'regularly',
					'children'       => 'yes',
					'wants_children' => 'no',
					'about_me'       => 'Executive chef and single dad to two amazing teenagers. I run a farm-to-table restaurant in Napa Valley and love creating memorable dining experiences. When not in the kitchen, I\'m coaching my kids\' soccer teams or exploring wine country.',
					'looking_for'    => 'Someone who understands that my kids come first but is ready to be part of our family adventure. Looking for a partner who appreciates good food, wine, and the chaos that comes with teenagers.',
					'preferences'    => array(
						'min_age'          => 30,
						'max_age'          => 42,
						'max_distance'     => 60,
						'preferred_gender' => 'female',
					),
				),
			),
			array(
				'username'     => 'maya_startup',
				'email'        => 'maya.demo@example.com',
				'display_name' => 'Maya',
				'profile'      => array(
					'age'            => 29,
					'gender'         => 'female',
					'orientation'    => 'bisexual',
					'location'       => 'San Francisco, CA',
					'latitude'       => 37.7749,
					'longitude'      => -122.4194,
					'education'      => 'masters',
					'profession'     => 'Product Manager',
					'height'         => 165,
					'body_type'      => 'athletic',
					'smoking'        => 'never',
					'drinking'       => 'socially',
					'children'       => 'none',
					'wants_children' => 'not_sure',
					'about_me'       => 'Product manager at a fintech startup, passionate about building technology that makes people\'s lives better. I love the startup hustle but make time for rock climbing, meditation, and weekend farmers market trips. Always learning something new.',
					'looking_for'    => 'Someone driven and curious who shares my passion for innovation and making a positive impact. Let\'s build something beautiful together, whether it\'s a product, a relationship, or just a really good weekend.',
					'preferences'    => array(
						'min_age'          => 25,
						'max_age'          => 35,
						'max_distance'     => 45,
						'preferred_gender' => '',
					),
				),
			),
		);

		foreach ( $sample_users as $user_data ) {
			// Check if user already exists.
			if ( username_exists( $user_data['username'] ) || email_exists( $user_data['email'] ) ) {
				continue;
			}

			// Create WordPress user.
			$user_id = wp_create_user(
				$user_data['username'],
				'DemoPassword123!',
				$user_data['email']
			);

			if ( is_wp_error( $user_id ) ) {
				continue;
			}

			// Update display name.
			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => $user_data['display_name'],
				)
			);

			// Add dating member role.
			$user = new WP_User( $user_id );
			$user->add_role( 'wpmatch_member' );

			// Create profile.
			$result = $profile_manager->save_profile( $user_id, $user_data['profile'] );

			if ( ! is_wp_error( $result ) ) {
				$created_users[] = array(
					'user_id'      => $user_id,
					'username'     => $user_data['username'],
					'display_name' => $user_data['display_name'],
				);
			}
		}

		// Create sample interactions between users.
		$this->create_sample_interactions( $created_users );

		return $created_users;
	}

	/**
	 * Create sample interactions between demo users.
	 *
	 * @param array $created_users Array of created user IDs.
	 * @since 1.0.0
	 */
	private function create_sample_interactions( $created_users ) {
		if ( count( $created_users ) < 2 ) {
			return; // Need at least 2 users for interactions.
		}

		global $wpdb;

		// Get table names.
		$swipes_table   = $wpdb->prefix . 'wpmatch_swipes';
		$matches_table  = $wpdb->prefix . 'wpmatch_matches';
		$messages_table = $wpdb->prefix . 'wpmatch_messages';

		// Sample interactions - create realistic dating app activity.
		$interactions = array(
			// Sarah likes Mike (mutual match).
			array(
				'swiper_id' => $created_users[0], // sarah_adventurer.
				'target_id' => $created_users[1], // mike_chef.
				'action'    => 'like',
				'mutual'    => true,
			),
			// Mike likes Sarah back (creates match).
			array(
				'swiper_id' => $created_users[1], // mike_chef.
				'target_id' => $created_users[0], // sarah_adventurer.
				'action'    => 'like',
				'mutual'    => true,
			),
			// Emma likes Sarah.
			array(
				'swiper_id' => $created_users[2], // emma_artist.
				'target_id' => $created_users[0], // sarah_adventurer.
				'action'    => 'like',
				'mutual'    => false,
			),
			// Alex likes Emma (mutual match).
			array(
				'swiper_id' => $created_users[3], // alex_teacher.
				'target_id' => $created_users[2], // emma_artist.
				'action'    => 'like',
				'mutual'    => true,
			),
			// Emma likes Alex back.
			array(
				'swiper_id' => $created_users[2], // emma_artist.
				'target_id' => $created_users[3], // alex_teacher.
				'action'    => 'like',
				'mutual'    => true,
			),
			// David passes on Sarah.
			array(
				'swiper_id' => $created_users[4], // david_musician.
				'target_id' => $created_users[0], // sarah_adventurer.
				'action'    => 'pass',
				'mutual'    => false,
			),
			// Sarah likes David (one-sided).
			array(
				'swiper_id' => $created_users[0], // sarah_adventurer.
				'target_id' => $created_users[4], // david_musician.
				'action'    => 'like',
				'mutual'    => false,
			),
		);

		// Create swipes.
		foreach ( $interactions as $interaction ) {
			$wpdb->insert(
				$swipes_table,
				array(
					'user_id'    => $interaction['swiper_id'],
					'target_id'  => $interaction['target_id'],
					'action'     => $interaction['action'],
					'created_at' => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%s' )
			);
		}

		// Create mutual matches.
		$matches = array(
			// Sarah & Mike match.
			array( $created_users[0], $created_users[1] ),
			// Emma & Alex match.
			array( $created_users[2], $created_users[3] ),
		);

		foreach ( $matches as $match ) {
			// Create unique conversation ID.
			$conversation_id = 'conv_' . min( $match[0], $match[1] ) . '_' . max( $match[0], $match[1] );

			// Insert match record.
			$wpdb->insert(
				$matches_table,
				array(
					'user_1_id'       => min( $match[0], $match[1] ),
					'user_2_id'       => max( $match[0], $match[1] ),
					'conversation_id' => $conversation_id,
					'matched_at'      => current_time( 'mysql' ),
					'status'          => 'active',
				),
				array( '%d', '%d', '%s', '%s', '%s' )
			);
		}

		// Create sample messages between matches.
		$sample_messages = array(
			// Sarah & Mike conversation.
			array(
				'conversation_id' => 'conv_' . min( $created_users[0], $created_users[1] ) . '_' . max( $created_users[0], $created_users[1] ),
				'sender_id'       => $created_users[0], // Sarah.
				'recipient_id'    => $created_users[1], // Mike.
				'content'         => 'Hi Mike! I saw you\'re a chef - that\'s amazing! I love trying new restaurants. What\'s your favorite cuisine to cook?',
				'created_at'      => date( 'Y-m-d H:i:s', strtotime( '-2 hours' ) ),
			),
			array(
				'conversation_id' => 'conv_' . min( $created_users[0], $created_users[1] ) . '_' . max( $created_users[0], $created_users[1] ),
				'sender_id'       => $created_users[1], // Mike.
				'recipient_id'    => $created_users[0], // Sarah.
				'content'         => 'Hey Sarah! Thanks for the message! I love cooking Italian and Mediterranean food the most. Your hiking photos are incredible - where was that mountain shot taken?',
				'created_at'      => date( 'Y-m-d H:i:s', strtotime( '-1 hour 45 minutes' ) ),
			),
			array(
				'conversation_id' => 'conv_' . min( $created_users[0], $created_users[1] ) . '_' . max( $created_users[0], $created_users[1] ),
				'sender_id'       => $created_users[0], // Sarah.
				'recipient_id'    => $created_users[1], // Mike.
				'content'         => 'That was Half Dome in Yosemite! One of my favorite climbs. Maybe I could cook for you sometime? I make a mean pasta 😄',
				'created_at'      => date( 'Y-m-d H:i:s', strtotime( '-1 hour 30 minutes' ) ),
			),
			// Emma & Alex conversation.
			array(
				'conversation_id' => 'conv_' . min( $created_users[2], $created_users[3] ) . '_' . max( $created_users[2], $created_users[3] ),
				'sender_id'       => $created_users[3], // Alex.
				'recipient_id'    => $created_users[2], // Emma.
				'content'         => 'Hi Emma! Your art is beautiful - I checked out your portfolio. Do you have any pieces displayed locally?',
				'created_at'      => date( 'Y-m-d H:i:s', strtotime( '-3 hours' ) ),
			),
			array(
				'conversation_id' => 'conv_' . min( $created_users[2], $created_users[3] ) . '_' . max( $created_users[2], $created_users[3] ),
				'sender_id'       => $created_users[2], // Emma.
				'recipient_id'    => $created_users[3], // Alex.
				'content'         => 'Thank you so much! Yes, I have a few pieces at the downtown gallery on 3rd Street. I love that you\'re a teacher - what grade do you teach?',
				'created_at'      => date( 'Y-m-d H:i:s', strtotime( '-2 hours 30 minutes' ) ),
			),
		);

		// Insert sample messages.
		foreach ( $sample_messages as $message ) {
			$wpdb->insert(
				$messages_table,
				array(
					'conversation_id' => $message['conversation_id'],
					'sender_id'       => $message['sender_id'],
					'recipient_id'    => $message['recipient_id'],
					'message_content' => $message['content'],
					'message_type'    => 'text',
					'is_read'         => 0,
					'created_at'      => $message['created_at'],
				),
				array( '%s', '%d', '%d', '%s', '%s', '%d', '%s' )
			);
		}
	}

	/**
	 * Create essential pages for the dating site.
	 */
	private function create_essential_pages() {
		$pages_to_create = array(
			array(
				'title'   => 'Dating Home',
				'slug'    => 'dating',
				'content' => '<h2>Welcome to Our Dating Community!</h2>
				<p>Find your perfect match in our growing community of singles. Get started by creating your profile or browsing potential matches.</p>

				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 30px 0;">
					<div style="padding: 20px; border: 1px solid #ddd; border-radius: 8px; text-align: center;">
						<h3>🎯 Browse Matches</h3>
						<p>Discover amazing people near you with our smart matching system.</p>
						<a href="/browse/" style="background: #fd297b; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">Start Swiping</a>
					</div>

					<div style="padding: 20px; border: 1px solid #ddd; border-radius: 8px; text-align: center;">
						<h3>🔍 Search Matches</h3>
						<p>Use advanced filters to find exactly who you\'re looking for.</p>
						<a href="/search/" style="background: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">Search Now</a>
					</div>

					<div style="padding: 20px; border: 1px solid #ddd; border-radius: 8px; text-align: center;">
						<h3>💕 My Matches</h3>
						<p>See who you\'ve matched with and start conversations.</p>
						<a href="/matches/" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">View Matches</a>
					</div>

					<div style="padding: 20px; border: 1px solid #ddd; border-radius: 8px; text-align: center;">
						<h3>✏️ Edit Profile</h3>
						<p>Create an amazing profile that shows off your personality.</p>
						<a href="/profile/edit/" style="background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">Edit Profile</a>
					</div>

					<div style="padding: 20px; border: 1px solid #ddd; border-radius: 8px; text-align: center;">
						<h3>⭐ Go Premium</h3>
						<p>Unlock unlimited likes, see who liked you, and get more matches!</p>
						<a href="/premium/" style="background: #ffc107; color: #212529; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">Upgrade Now</a>
					</div>
				</div>

				<h3>How It Works</h3>
				<ol>
					<li><strong>Create Your Profile:</strong> Add photos and tell us about yourself</li>
					<li><strong>Set Your Preferences:</strong> Tell us what you\'re looking for</li>
					<li><strong>Start Swiping:</strong> Browse through potential matches</li>
					<li><strong>Get Matches:</strong> When someone you like also likes you, it\'s a match!</li>
					<li><strong>Start Chatting:</strong> Message your matches and get to know each other</li>
				</ol>',
			),
			array(
				'title'   => 'Browse Matches',
				'slug'    => 'browse',
				'content' => '<h2>Discover Your Perfect Match</h2>
				<p>Swipe through potential matches and find someone special. Like someone? Swipe right! Not interested? Swipe left.</p>

				[wpmatch_swipe]',
			),
			array(
				'title'   => 'Edit Profile',
				'slug'    => 'profile/edit',
				'content' => '<h2>Create Your Dating Profile</h2>
				<p>Make a great first impression! Complete your profile to get better matches and more likes.</p>

				[wpmatch_profile_form]',
			),
			array(
				'title'   => 'My Matches',
				'slug'    => 'matches',
				'content' => '<h2>Your Matches</h2>
				<p>These are people who liked you back! Start a conversation and see where it leads.</p>

				[wpmatch_matches]',
			),
			array(
				'title'   => 'My Profile',
				'slug'    => 'profile',
				'content' => '<h2>Your Profile</h2>
				<p>This is how others see your profile. Want to make changes?</p>

				[wpmatch_profile]

				<p style="text-align: center; margin-top: 20px;">
					<a href="/profile/edit/" style="background: #fd297b; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">Edit My Profile</a>
				</p>',
			),
			array(
				'title'   => 'Join Our Dating Community',
				'slug'    => 'register',
				'content' => '<h2>Find Your Perfect Match</h2>
				<p>Join thousands of singles who have found love through our platform. Create your account and start your dating journey today!</p>

				[wpmatch_registration redirect="/profile/edit/"]

				<p style="text-align: center; margin-top: 20px;">
					Already have an account? <a href="' . wp_login_url() . '">Sign in here</a>
				</p>',
			),
			array(
				'title'   => 'Search Matches',
				'slug'    => 'search',
				'content' => '<h2>Find Your Perfect Match</h2>
				<p>Use our advanced search filters to find exactly who you\'re looking for. Set your preferences for age, distance, interests, and more!</p>

				[wpmatch_search]',
			),
			array(
				'title'   => 'Premium Memberships',
				'slug'    => 'premium',
				'content' => '<h2>Upgrade Your Dating Experience</h2>
				<p>Get more matches, see who liked you, and unlock exclusive features with our premium memberships.</p>

				[wpmatch_premium_shop]

				<div style="background: #f8f9fa; padding: 20px; margin: 30px 0; border-radius: 8px; text-align: center;">
					<h3 style="color: #fd297b;">✨ Why Go Premium?</h3>
					<ul style="text-align: left; display: inline-block; margin: 0;">
						<li>Unlimited daily likes</li>
						<li>See who liked your profile</li>
						<li>Advanced search filters</li>
						<li>Read receipts for messages</li>
						<li>Profile boost for more visibility</li>
						<li>Priority customer support</li>
					</ul>
				</div>',
			),
			array(
				'title'   => 'Help & Dating Tips',
				'slug'    => 'help',
				'content' => '<h2>Dating Help & Tips</h2>
				<p>New to online dating? Check out our comprehensive guide to make the most of your experience!</p>

				[wpmatch_user_guide]',
			),
		);

		$created_pages = array();

		foreach ( $pages_to_create as $page_data ) {
			// Check if page already exists.
			$existing_page = get_page_by_path( $page_data['slug'] );
			if ( $existing_page ) {
				continue;
			}

			// Create the page.
			$page_id = wp_insert_post(
				array(
					'post_title'     => $page_data['title'],
					'post_name'      => $page_data['slug'],
					'post_content'   => $page_data['content'],
					'post_status'    => 'publish',
					'post_type'      => 'page',
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
				)
			);

			if ( ! is_wp_error( $page_id ) && $page_id ) {
				$created_pages[] = array(
					'id'    => $page_id,
					'title' => $page_data['title'],
					'url'   => get_permalink( $page_id ),
					'slug'  => $page_data['slug'],
				);
			}
		}

		return $created_pages;
	}

	/**
	 * Create a navigation menu for the dating site.
	 *
	 * @param array $created_pages Array of created pages with their data.
	 * @return bool Whether the menu was created successfully.
	 */
	private function create_dating_navigation_menu( $created_pages ) {
		// Check if menu already exists.
		$menu_name = 'Dating Site';
		$menu      = wp_get_nav_menu_object( $menu_name );

		if ( $menu ) {
			// Menu already exists, don't create duplicate.
			return false;
		}

		// Create the menu.
		$menu_id = wp_create_nav_menu( $menu_name );

		if ( is_wp_error( $menu_id ) ) {
			return false;
		}

		// Define menu items in the order we want them.
		$menu_items = array(
			array(
				'title'    => 'Home',
				'slug'     => 'dating',
				'priority' => 1,
			),
			array(
				'title'    => 'Browse',
				'slug'     => 'browse',
				'priority' => 2,
			),
			array(
				'title'    => 'My Profile',
				'slug'     => 'profile',
				'priority' => 3,
			),
			array(
				'title'    => 'My Matches',
				'slug'     => 'matches',
				'priority' => 4,
			),
			array(
				'title'    => 'Join',
				'slug'     => 'register',
				'priority' => 5,
			),
			array(
				'title'    => 'Help',
				'slug'     => 'help',
				'priority' => 6,
			),
		);

		// Add pages to menu.
		foreach ( $menu_items as $menu_item ) {
			// Find the created page with this slug.
			$page_data = null;
			foreach ( $created_pages as $page ) {
				if ( $page['slug'] === $menu_item['slug'] ||
					str_replace( '/', '', $page['slug'] ) === str_replace( '/', '', $menu_item['slug'] ) ) {
					$page_data = $page;
					break;
				}
			}

			if ( $page_data ) {
				wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-title'     => $menu_item['title'],
						'menu-item-object'    => 'page',
						'menu-item-object-id' => $page_data['id'],
						'menu-item-type'      => 'post_type',
						'menu-item-status'    => 'publish',
						'menu-item-position'  => $menu_item['priority'],
					)
				);
			}
		}

		// Set this menu as the primary menu if the theme supports it.
		$locations = get_theme_mod( 'nav_menu_locations' );
		if ( ! $locations ) {
			$locations = array();
		}

		// Try common menu location names.
		$possible_locations = array( 'primary', 'header', 'main', 'top', 'header-menu', 'primary-menu' );
		$theme_locations    = get_registered_nav_menus();

		foreach ( $possible_locations as $location ) {
			if ( isset( $theme_locations[ $location ] ) ) {
				$locations[ $location ] = $menu_id;
				break;
			}
		}

		// If no standard location found, use the first available.
		if ( ! empty( $theme_locations ) && ! in_array( $menu_id, $locations, true ) ) {
			$first_location               = array_keys( $theme_locations )[0];
			$locations[ $first_location ] = $menu_id;
		}

		set_theme_mod( 'nav_menu_locations', $locations );

		return true;
	}

}