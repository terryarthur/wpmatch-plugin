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

		return $created_users;
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
						<h3>✏️ Edit Profile</h3>
						<p>Create an amazing profile that shows off your personality.</p>
						<a href="/profile/edit/" style="background: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">Edit Profile</a>
					</div>

					<div style="padding: 20px; border: 1px solid #ddd; border-radius: 8px; text-align: center;">
						<h3>💕 My Matches</h3>
						<p>See who you\'ve matched with and start conversations.</p>
						<a href="/matches/" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">View Matches</a>
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