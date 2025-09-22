<?php
/**
 * The core plugin class.
 *
 * @package WPMatch
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 */
class WPMatch {

	/**
	 * The single instance of the class.
	 *
	 * @var WPMatch
	 */
	private static $instance = null;

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @var WPMatch_Loader
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 */
	private function __construct() {
		$this->version     = WPMATCH_VERSION;
		$this->plugin_name = 'wpmatch';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_api_hooks();
		$this->define_ajax_hooks();
		$this->define_woocommerce_hooks();
		$this->define_advanced_matching_hooks();
		$this->define_messaging_hooks();
		$this->define_photo_verification_hooks();
		$this->define_mobile_api_hooks();
		$this->define_social_integrations_hooks();
		$this->define_video_chat_hooks();
		$this->define_ai_chatbot_hooks();
		$this->define_gamification_hooks();
		$this->define_events_hooks();
		$this->define_voice_notes_hooks();
		$this->define_location_hooks();
		$this->define_cron_hooks();
	}

	/**
	 * Main WPMatch Instance.
	 *
	 * Ensures only one instance of WPMatch is loaded or can be loaded.
	 *
	 * @return WPMatch - Main instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Cloning is forbidden.
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning is forbidden.', 'wpmatch' ), '1.0.0' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Unserializing instances of this class is forbidden.', 'wpmatch' ), '1.0.0' );
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 */
	private function load_dependencies() {
		// Load the loader class.
		require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-loader.php';

		// Load the internationalization class.
		require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-i18n.php';

		// Load admin classes.
		if ( is_admin() ) {
			require_once WPMATCH_PLUGIN_DIR . 'admin/class-wpmatch-admin.php';
		}

		// Load public classes.
		if ( ! is_admin() ) {
			require_once WPMATCH_PLUGIN_DIR . 'public/class-wpmatch-public.php';
			require_once WPMATCH_PLUGIN_DIR . 'public/class-wpmatch-swipe-interface.php';
		}

		// Load API classes.
		require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-api.php';
		require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-rest-controller.php';
		require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-realtime-manager.php';
		require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-job-queue.php';

		// Load AJAX handlers.
		require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-ajax-handlers.php';

		// Load profile management.
		require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-profile-manager.php';

		// Load matching algorithm.
		require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-matching-algorithm.php';

		// Load advanced matching.
		require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-advanced-matching.php';

		// Load real-time messaging.
		require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-realtime-messaging.php';

		// Load photo verification.
		require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-photo-verification.php';

		// Load mobile API.
		require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-mobile-api.php';

		// Load social integrations.
		require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-social-integrations.php';

		// Load video chat.
		require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-video-chat.php';

		// Load AI chatbot.
		require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-ai-chatbot.php';

		// Load gamification.
		require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-gamification.php';

		// Load events system.
		require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-events.php';

		// Load voice notes system.
		require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-voice-notes.php';

		// Load location-based features.
		require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-location.php';

		// Load message manager.
		require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-message-manager.php';

		// Load search manager.
		require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-search-manager.php';

		// Load WooCommerce integration.
		require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-woocommerce-integration.php';
		require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-membership-manager.php';
		require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-subscription-manager.php';
		require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-order-processing.php';
		require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-feature-restrictions.php';

		// Create the loader instance.
		$this->loader = new WPMatch_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the WPMatch_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 */
	private function set_locale() {
		$plugin_i18n = new WPMatch_i18n();
		$plugin_i18n->set_domain( $this->get_plugin_name() );

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 */
	private function define_admin_hooks() {
		if ( ! is_admin() ) {
			return;
		}

		$plugin_admin = new WPMatch_Admin( $this->get_plugin_name(), $this->get_version() );

		// Admin scripts and styles.
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		// Admin menu.
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );

		// Admin notices.
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'admin_notices' );

		// Settings links.
		$this->loader->add_filter( 'plugin_action_links_' . WPMATCH_PLUGIN_BASENAME, $plugin_admin, 'add_action_links' );

		// User columns in admin.
		$this->loader->add_filter( 'manage_users_columns', $plugin_admin, 'add_user_columns' );
		$this->loader->add_filter( 'manage_users_custom_column', $plugin_admin, 'show_user_columns', 10, 3 );

		// AJAX handlers for admin.
		$this->loader->add_action( 'wp_ajax_wpmatch_admin_action', $plugin_admin, 'handle_admin_ajax' );
		$this->loader->add_action( 'wp_ajax_wpmatch_generate_sample_data', $plugin_admin, 'generate_sample_data' );
		$this->loader->add_action( 'wp_ajax_wpmatch_create_demo_pages', $plugin_admin, 'create_demo_pages' );
		$this->loader->add_action( 'wp_ajax_wpmatch_cleanup_demo_data', $plugin_admin, 'cleanup_demo_data' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 */
	private function define_public_hooks() {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		$plugin_public = new WPMatch_Public( $this->get_plugin_name(), $this->get_version() );

		// Public scripts and styles.
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		// Shortcodes.
		$this->loader->add_action( 'init', $plugin_public, 'register_shortcodes' );

		// User registration hooks.
		$this->loader->add_action( 'user_register', $plugin_public, 'handle_user_registration' );
		$this->loader->add_action( 'wp_login', $plugin_public, 'handle_user_login', 10, 2 );

		// AJAX handlers for public.
		$this->loader->add_action( 'wp_ajax_wpmatch_public_action', $plugin_public, 'handle_public_ajax' );
		$this->loader->add_action( 'wp_ajax_nopriv_wpmatch_public_action', $plugin_public, 'handle_public_ajax' );

		// Template redirects.
		$this->loader->add_action( 'template_redirect', $plugin_public, 'template_redirect' );
	}

	/**
	 * Register all of the hooks related to the API functionality
	 * of the plugin.
	 */
	private function define_api_hooks() {
		$plugin_api = new WPMatch_API( $this->get_plugin_name(), $this->get_version() );

		// Register REST API routes.
		$this->loader->add_action( 'rest_api_init', $plugin_api, 'register_routes' );

		// REST API authentication.
		$this->loader->add_filter( 'rest_authentication_errors', $plugin_api, 'rest_authentication' );

		// REST API permissions.
		$this->loader->add_filter( 'rest_pre_dispatch', $plugin_api, 'check_permissions', 10, 3 );
	}

	/**
	 * Register all of the hooks related to AJAX functionality.
	 */
	private function define_ajax_hooks() {
		// Initialize AJAX handlers.
		WPMatch_AJAX_Handlers::init();
	}

	/**
	 * Register all of the hooks related to WooCommerce integration.
	 */
	private function define_woocommerce_hooks() {
		// Initialize WooCommerce integration.
		WPMatch_WooCommerce_Integration::init();

		// Initialize subscription management.
		WPMatch_Subscription_Manager::init();

		// Initialize order processing.
		WPMatch_Order_Processing::init();

		// Initialize feature restrictions.
		WPMatch_Feature_Restrictions::init();
	}

	/**
	 * Register all of the hooks related to advanced matching functionality.
	 */
	private function define_advanced_matching_hooks() {
		// Initialize advanced matching system.
		WPMatch_Advanced_Matching::init();

		// Note: Advanced matching hooks are registered directly in WPMatch_Advanced_Matching::init()
		// since they use static methods that don't work with the WPMatch_Loader pattern.
	}

	/**
	 * Register all of the hooks related to real-time messaging functionality.
	 */
	private function define_messaging_hooks() {
		// Initialize real-time messaging system.
		WPMatch_Realtime_Messaging::init();

		// Note: Messaging hooks are registered directly in WPMatch_Realtime_Messaging::init()
		// since they use static methods and REST API routes.
	}

	/**
	 * Register all of the hooks related to photo verification functionality.
	 */
	private function define_photo_verification_hooks() {
		// Initialize photo verification system.
		WPMatch_Photo_Verification::init();

		// Note: Photo verification hooks are registered directly in WPMatch_Photo_Verification::init()
		// since they use static methods and REST API routes.
	}

	/**
	 * Register all of the hooks related to mobile API functionality.
	 */
	private function define_mobile_api_hooks() {
		// Initialize mobile API system.
		WPMatch_Mobile_API::init();

		// Note: Mobile API hooks are registered directly in WPMatch_Mobile_API::init()
		// since they use static methods and REST API routes.
	}

	/**
	 * Register all of the hooks related to social integrations functionality.
	 */
	private function define_social_integrations_hooks() {
		// Initialize social integrations system.
		WPMatch_Social_Integrations::init();

		// Note: Social integration hooks are registered directly in WPMatch_Social_Integrations::init()
		// since they use static methods and REST API routes.
	}

	/**
	 * Register all of the hooks related to video chat functionality.
	 */
	private function define_video_chat_hooks() {
		// Initialize video chat system.
		WPMatch_Video_Chat::init();

		// Note: Video chat hooks are registered directly in WPMatch_Video_Chat::init()
		// since they use static methods and REST API routes.
	}

	/**
	 * Register all of the hooks related to AI chatbot functionality.
	 */
	private function define_ai_chatbot_hooks() {
		// Initialize AI chatbot system.
		WPMatch_AI_Chatbot::init();

		// Note: AI chatbot hooks are registered directly in WPMatch_AI_Chatbot::init()
		// since they use static methods and REST API routes.
	}

	/**
	 * Register all of the hooks related to gamification functionality.
	 */
	private function define_gamification_hooks() {
		// Initialize gamification system.
		WPMatch_Gamification::init();

		// Note: Gamification hooks are registered directly in WPMatch_Gamification::init()
		// since they use static methods and REST API routes.
	}

	/**
	 * Register all of the hooks related to events functionality.
	 */
	private function define_events_hooks() {
		// Initialize events system.
		WPMatch_Events::init();

		// Note: Events hooks are registered directly in WPMatch_Events::init()
		// since they use static methods and REST API routes.
	}

	/**
	 * Register all of the hooks related to voice notes functionality.
	 */
	private function define_voice_notes_hooks() {
		// Initialize voice notes system.
		WPMatch_Voice_Notes::init();

		// Note: Voice notes hooks are registered directly in WPMatch_Voice_Notes::init()
		// since they use static methods and REST API routes.
	}

	/**
	 * Register all of the hooks related to location-based functionality.
	 */
	private function define_location_hooks() {
		// Initialize location system.
		WPMatch_Location::init();

		// Note: Location hooks are registered directly in WPMatch_Location::init()
		// since they use static methods and REST API routes.
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @return string The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Register all cron-related hooks.
	 */
	private function define_cron_hooks() {
		// Add custom cron intervals.
		$this->loader->add_filter( 'cron_schedules', $this, 'add_cron_intervals' );
	}

	/**
	 * Add custom cron intervals.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array Modified schedules.
	 */
	public function add_cron_intervals( $schedules ) {
		$schedules['every_minute'] = array(
			'interval' => 60,
			'display'  => esc_html__( 'Every Minute', 'wpmatch' ),
		);

		$schedules['every_five_minutes'] = array(
			'interval' => 300,
			'display'  => esc_html__( 'Every 5 Minutes', 'wpmatch' ),
		);

		$schedules['every_fifteen_minutes'] = array(
			'interval' => 900,
			'display'  => esc_html__( 'Every 15 Minutes', 'wpmatch' ),
		);

		return $schedules;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @return WPMatch_Loader Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @return string The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Get plugin settings.
	 *
	 * @param string $key Optional. Specific setting key.
	 * @param mixed  $default Optional. Default value if setting doesn't exist.
	 * @return mixed Settings array or specific setting value.
	 */
	public function get_settings( $key = null, $default = null ) {
		$settings = get_option( 'wpmatch_settings', array() );

		if ( null === $key ) {
			return $settings;
		}

		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	/**
	 * Update plugin settings.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $value Setting value.
	 * @return bool Whether the settings were updated.
	 */
	public function update_setting( $key, $value ) {
		$settings         = $this->get_settings();
		$settings[ $key ] = $value;
		return update_option( 'wpmatch_settings', $settings );
	}
}
