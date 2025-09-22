<?php
/**
 * Social Media Integrations for WPMatch
 *
 * Handles social media login, profile importing, sharing features,
 * and social validation for enhanced user experience and verification.
 *
 * @package WPMatch
 * @since 1.4.0
 */

class WPMatch_Social_Integrations {

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
	 * Initialize the class.
	 *
	 * @param string $plugin_name Plugin name.
	 * @param string $version Plugin version.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Initialize social integrations system.
	 */
	public static function init() {
		$instance = new self( 'wpmatch', WPMATCH_VERSION );
		add_action( 'init', array( $instance, 'setup_database' ) );
		add_action( 'rest_api_init', array( $instance, 'register_routes' ) );
		add_action( 'wp_enqueue_scripts', array( $instance, 'enqueue_scripts' ) );
		add_action( 'login_enqueue_scripts', array( $instance, 'enqueue_login_scripts' ) );
		add_action( 'wp_footer', array( $instance, 'add_social_login_buttons' ) );
		add_action( 'wpmatch_profile_form', array( $instance, 'add_social_connect_section' ) );
	}

	/**
	 * Set up database tables for social integrations.
	 */
	public function setup_database() {
		$this->create_social_accounts_table();
		$this->create_social_shares_table();
		$this->create_social_imports_table();
	}

	/**
	 * Create social accounts table.
	 */
	private function create_social_accounts_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_social_accounts';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			provider varchar(50) NOT NULL,
			provider_user_id varchar(255) NOT NULL,
			access_token text,
			refresh_token text,
			expires_at datetime,
			profile_data longtext,
			is_verified tinyint(1) DEFAULT 0,
			connected_at datetime DEFAULT CURRENT_TIMESTAMP,
			last_sync datetime,
			PRIMARY KEY (id),
			UNIQUE KEY unique_provider_user (user_id, provider),
			UNIQUE KEY unique_provider_id (provider, provider_user_id),
			KEY user_id (user_id),
			KEY provider (provider),
			KEY is_verified (is_verified)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create social shares table.
	 */
	private function create_social_shares_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_social_shares';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			share_type varchar(50) NOT NULL,
			platform varchar(50) NOT NULL,
			content_type varchar(50) NOT NULL,
			content_id bigint(20),
			share_url text,
			engagement_data longtext,
			shared_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY share_type (share_type),
			KEY platform (platform),
			KEY shared_at (shared_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create social imports table.
	 */
	private function create_social_imports_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_social_imports';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			provider varchar(50) NOT NULL,
			import_type varchar(50) NOT NULL,
			status varchar(20) DEFAULT 'pending',
			imported_data longtext,
			error_message text,
			imported_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY provider (provider),
			KEY import_type (import_type),
			KEY status (status),
			KEY imported_at (imported_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		// Social login endpoints.
		register_rest_route(
			'wpmatch/v1',
			'/social/auth/(?P<provider>[\w-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_social_auth_start' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'provider' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( $this, 'validate_provider' ),
					),
					'redirect_uri' => array(
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);

		register_rest_route(
			'wpmatch/v1',
			'/social/auth/(?P<provider>[\w-]+)/callback',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_social_auth_callback' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'provider' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'code'     => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'state'    => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Social account management.
		register_rest_route(
			'wpmatch/v1',
			'/social/accounts',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_get_connected_accounts' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
			)
		);

		register_rest_route(
			'wpmatch/v1',
			'/social/accounts/(?P<provider>[\w-]+)/connect',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_connect_social_account' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'provider'    => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'access_token' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'wpmatch/v1',
			'/social/accounts/(?P<provider>[\w-]+)/disconnect',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_disconnect_social_account' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'provider' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Profile import endpoints.
		register_rest_route(
			'wpmatch/v1',
			'/social/import/(?P<provider>[\w-]+)/profile',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_import_social_profile' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'provider' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'fields'   => array(
						'sanitize_callback' => array( $this, 'sanitize_import_fields' ),
					),
				),
			)
		);

		register_rest_route(
			'wpmatch/v1',
			'/social/import/(?P<provider>[\w-]+)/photos',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_import_social_photos' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'provider'   => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'photo_urls' => array(
						'sanitize_callback' => array( $this, 'sanitize_photo_urls' ),
					),
				),
			)
		);

		// Sharing endpoints.
		register_rest_route(
			'wpmatch/v1',
			'/social/share',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_social_share' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'platform'     => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'content_type' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'content'      => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'url'          => array(
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);

		// Verification endpoints.
		register_rest_route(
			'wpmatch/v1',
			'/social/verify/(?P<provider>[\w-]+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_verify_social_account' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'provider' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Enqueue frontend scripts.
	 */
	public function enqueue_scripts() {
		if ( ! $this->should_load_social_scripts() ) {
			return;
		}

		wp_enqueue_script(
			'wpmatch-social-js',
			WPMATCH_PLUGIN_URL . 'public/js/wpmatch-social.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		wp_localize_script(
			'wpmatch-social-js',
			'wpMatchSocial',
			array(
				'apiUrl'       => home_url( '/wp-json/wpmatch/v1' ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'currentUser'  => get_current_user_id(),
				'providers'    => $this->get_enabled_providers(),
				'strings'      => array(
					'connecting'      => esc_html__( 'Connecting...', 'wpmatch' ),
					'importing'       => esc_html__( 'Importing...', 'wpmatch' ),
					'sharing'         => esc_html__( 'Sharing...', 'wpmatch' ),
					'success'         => esc_html__( 'Success!', 'wpmatch' ),
					'error'           => esc_html__( 'An error occurred. Please try again.', 'wpmatch' ),
					'confirmDisconnect' => esc_html__( 'Are you sure you want to disconnect this account?', 'wpmatch' ),
				),
			)
		);

		wp_enqueue_style(
			'wpmatch-social-css',
			WPMATCH_PLUGIN_URL . 'public/css/wpmatch-social.css',
			array(),
			$this->version
		);
	}

	/**
	 * Enqueue login scripts.
	 */
	public function enqueue_login_scripts() {
		wp_enqueue_script(
			'wpmatch-social-login-js',
			WPMATCH_PLUGIN_URL . 'public/js/wpmatch-social-login.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		wp_localize_script(
			'wpmatch-social-login-js',
			'wpMatchSocialLogin',
			array(
				'apiUrl'    => home_url( '/wp-json/wpmatch/v1' ),
				'providers' => $this->get_enabled_providers(),
			)
		);
	}

	/**
	 * Check if should load social scripts.
	 *
	 * @return bool
	 */
	private function should_load_social_scripts() {
		// Load on profile pages, registration, login, and matching pages.
		if ( is_user_logged_in() ) {
			return true;
		}

		if ( is_page() ) {
			$page_template = get_page_template_slug();
			return in_array( $page_template, array( 'page-wpmatch-profile.php', 'page-wpmatch-register.php' ), true );
		}

		return false;
	}

	/**
	 * Get enabled social providers.
	 *
	 * @return array
	 */
	private function get_enabled_providers() {
		$settings = get_option( 'wpmatch_social_settings', array() );

		$providers = array();
		$available_providers = array(
			'facebook'  => array(
				'name'  => 'Facebook',
				'icon'  => 'fab fa-facebook-f',
				'color' => '#1877f2',
			),
			'instagram' => array(
				'name'  => 'Instagram',
				'icon'  => 'fab fa-instagram',
				'color' => '#e4405f',
			),
			'twitter'   => array(
				'name'  => 'Twitter',
				'icon'  => 'fab fa-twitter',
				'color' => '#1da1f2',
			),
			'linkedin'  => array(
				'name'  => 'LinkedIn',
				'icon'  => 'fab fa-linkedin-in',
				'color' => '#0a66c2',
			),
			'google'    => array(
				'name'  => 'Google',
				'icon'  => 'fab fa-google',
				'color' => '#4285f4',
			),
		);

		foreach ( $available_providers as $provider_id => $provider_data ) {
			if ( ! empty( $settings[ $provider_id . '_enabled' ] ) ) {
				$providers[ $provider_id ] = $provider_data;
			}
		}

		return $providers;
	}

	/**
	 * Add social login buttons to login/register forms.
	 */
	public function add_social_login_buttons() {
		if ( ! is_page() || is_user_logged_in() ) {
			return;
		}

		$page_template = get_page_template_slug();
		if ( ! in_array( $page_template, array( 'page-wpmatch-login.php', 'page-wpmatch-register.php' ), true ) ) {
			return;
		}

		$providers = $this->get_enabled_providers();
		if ( empty( $providers ) ) {
			return;
		}

		echo '<div class="wpmatch-social-login-section">';
		echo '<div class="social-login-divider">';
		echo '<span>' . esc_html__( 'Or continue with', 'wpmatch' ) . '</span>';
		echo '</div>';
		echo '<div class="social-login-buttons">';

		foreach ( $providers as $provider_id => $provider ) {
			echo '<button class="social-login-btn social-login-' . esc_attr( $provider_id ) . '" data-provider="' . esc_attr( $provider_id ) . '">';
			echo '<i class="' . esc_attr( $provider['icon'] ) . '"></i>';
			echo '<span>' . esc_html( $provider['name'] ) . '</span>';
			echo '</button>';
		}

		echo '</div>';
		echo '</div>';
	}

	/**
	 * Add social connect section to profile form.
	 */
	public function add_social_connect_section() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id = get_current_user_id();
		$connected_accounts = $this->get_user_connected_accounts( $user_id );
		$providers = $this->get_enabled_providers();

		if ( empty( $providers ) ) {
			return;
		}

		echo '<div class="wpmatch-social-connect-section">';
		echo '<h3>' . esc_html__( 'Social Media Accounts', 'wpmatch' ) . '</h3>';
		echo '<p>' . esc_html__( 'Connect your social media accounts to enhance your profile and verification status.', 'wpmatch' ) . '</p>';

		foreach ( $providers as $provider_id => $provider ) {
			$is_connected = isset( $connected_accounts[ $provider_id ] );
			$account_data = $is_connected ? $connected_accounts[ $provider_id ] : null;

			echo '<div class="social-account-item" data-provider="' . esc_attr( $provider_id ) . '">';
			echo '<div class="social-account-info">';
			echo '<i class="' . esc_attr( $provider['icon'] ) . '"></i>';
			echo '<span class="provider-name">' . esc_html( $provider['name'] ) . '</span>';

			if ( $is_connected ) {
				echo '<span class="connection-status connected">' . esc_html__( 'Connected', 'wpmatch' ) . '</span>';
				if ( $account_data && $account_data->is_verified ) {
					echo '<span class="verification-badge">' . esc_html__( 'Verified', 'wpmatch' ) . '</span>';
				}
			} else {
				echo '<span class="connection-status disconnected">' . esc_html__( 'Not Connected', 'wpmatch' ) . '</span>';
			}

			echo '</div>';
			echo '<div class="social-account-actions">';

			if ( $is_connected ) {
				echo '<button class="btn-import-profile" data-provider="' . esc_attr( $provider_id ) . '">';
				echo esc_html__( 'Import Profile', 'wpmatch' );
				echo '</button>';
				echo '<button class="btn-import-photos" data-provider="' . esc_attr( $provider_id ) . '">';
				echo esc_html__( 'Import Photos', 'wpmatch' );
				echo '</button>';
				if ( ! $account_data || ! $account_data->is_verified ) {
					echo '<button class="btn-verify-account" data-provider="' . esc_attr( $provider_id ) . '">';
					echo esc_html__( 'Verify', 'wpmatch' );
					echo '</button>';
				}
				echo '<button class="btn-disconnect-account" data-provider="' . esc_attr( $provider_id ) . '">';
				echo esc_html__( 'Disconnect', 'wpmatch' );
				echo '</button>';
			} else {
				echo '<button class="btn-connect-account" data-provider="' . esc_attr( $provider_id ) . '">';
				echo esc_html__( 'Connect', 'wpmatch' );
				echo '</button>';
			}

			echo '</div>';
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Get user's connected social accounts.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	private function get_user_connected_accounts( $user_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_social_accounts';

		$accounts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE user_id = %d",
				$user_id
			)
		);

		$connected = array();
		foreach ( $accounts as $account ) {
			$connected[ $account->provider ] = $account;
		}

		return $connected;
	}

	/**
	 * API: Start social authentication.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function api_social_auth_start( $request ) {
		$provider = $request->get_param( 'provider' );
		$redirect_uri = $request->get_param( 'redirect_uri' );

		$auth_url = $this->get_provider_auth_url( $provider, $redirect_uri );

		if ( ! $auth_url ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => esc_html__( 'Social provider not configured.', 'wpmatch' ),
				)
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'auth_url' => $auth_url,
					'provider' => $provider,
				),
			)
		);
	}

	/**
	 * Get provider authentication URL.
	 *
	 * @param string $provider Provider name.
	 * @param string $redirect_uri Redirect URI.
	 * @return string|false
	 */
	private function get_provider_auth_url( $provider, $redirect_uri = null ) {
		$settings = get_option( 'wpmatch_social_settings', array() );

		if ( empty( $settings[ $provider . '_app_id' ] ) || empty( $settings[ $provider . '_app_secret' ] ) ) {
			return false;
		}

		$callback_url = home_url( '/wp-json/wpmatch/v1/social/auth/' . $provider . '/callback' );

		switch ( $provider ) {
			case 'facebook':
				$scope = 'public_profile,email';
				$state = wp_create_nonce( 'wpmatch_social_' . $provider );
				return 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query(
					array(
						'client_id'     => $settings[ $provider . '_app_id' ],
						'redirect_uri'  => $callback_url,
						'scope'         => $scope,
						'state'         => $state,
						'response_type' => 'code',
					)
				);

			case 'google':
				$scope = 'openid profile email';
				$state = wp_create_nonce( 'wpmatch_social_' . $provider );
				return 'https://accounts.google.com/o/oauth2/auth?' . http_build_query(
					array(
						'client_id'     => $settings[ $provider . '_app_id' ],
						'redirect_uri'  => $callback_url,
						'scope'         => $scope,
						'state'         => $state,
						'response_type' => 'code',
						'access_type'   => 'offline',
					)
				);

			case 'instagram':
				$scope = 'user_profile,user_media';
				$state = wp_create_nonce( 'wpmatch_social_' . $provider );
				return 'https://api.instagram.com/oauth/authorize?' . http_build_query(
					array(
						'client_id'     => $settings[ $provider . '_app_id' ],
						'redirect_uri'  => $callback_url,
						'scope'         => $scope,
						'state'         => $state,
						'response_type' => 'code',
					)
				);

			default:
				return false;
		}
	}

	/**
	 * Validation and permission callbacks.
	 */

	/**
	 * Validate social provider.
	 *
	 * @param string $provider Provider name.
	 * @return bool
	 */
	public function validate_provider( $provider ) {
		$allowed_providers = array( 'facebook', 'instagram', 'twitter', 'linkedin', 'google' );
		return in_array( $provider, $allowed_providers, true );
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
	 * Sanitization callbacks.
	 */

	/**
	 * Sanitize import fields.
	 *
	 * @param array $fields Fields array.
	 * @return array
	 */
	public function sanitize_import_fields( $fields ) {
		if ( ! is_array( $fields ) ) {
			return array();
		}

		$allowed_fields = array( 'name', 'bio', 'location', 'age', 'interests', 'photos' );
		return array_intersect( $fields, $allowed_fields );
	}

	/**
	 * Sanitize photo URLs.
	 *
	 * @param array $urls Photo URLs.
	 * @return array
	 */
	public function sanitize_photo_urls( $urls ) {
		if ( ! is_array( $urls ) ) {
			return array();
		}

		return array_map( 'esc_url_raw', $urls );
	}

	/**
	 * Placeholder methods for remaining API endpoints.
	 * These would be implemented with full OAuth flow functionality.
	 */

	public function api_social_auth_callback( $request ) {
		return rest_ensure_response( array( 'success' => false, 'message' => 'OAuth callback not fully implemented yet' ) );
	}

	public function api_get_connected_accounts( $request ) {
		$user_id = get_current_user_id();
		$connected = $this->get_user_connected_accounts( $user_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'accounts' => $connected,
					'count'    => count( $connected ),
				),
			)
		);
	}

	public function api_connect_social_account( $request ) {
		return rest_ensure_response( array( 'success' => false, 'message' => 'Social account connection not fully implemented yet' ) );
	}

	public function api_disconnect_social_account( $request ) {
		return rest_ensure_response( array( 'success' => false, 'message' => 'Social account disconnection not fully implemented yet' ) );
	}

	public function api_import_social_profile( $request ) {
		return rest_ensure_response( array( 'success' => false, 'message' => 'Profile import not fully implemented yet' ) );
	}

	public function api_import_social_photos( $request ) {
		return rest_ensure_response( array( 'success' => false, 'message' => 'Photo import not fully implemented yet' ) );
	}

	public function api_social_share( $request ) {
		return rest_ensure_response( array( 'success' => false, 'message' => 'Social sharing not fully implemented yet' ) );
	}

	public function api_verify_social_account( $request ) {
		return rest_ensure_response( array( 'success' => false, 'message' => 'Social verification not fully implemented yet' ) );
	}
}