<?php
/**
 * WPMatch Social Media OAuth Integration
 *
 * OAuth authentication and social media integrations for dating profiles
 *
 * @package WPMatch
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPMatch_Social_OAuth {

	private static $instance = null;

	const OAUTH_TOKENS_TABLE = 'wpmatch_oauth_tokens';
	const SOCIAL_PROFILES_TABLE = 'wpmatch_social_profiles';

	private $providers = array();
	private $oauth_settings = array();

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->init_providers();
		$this->load_oauth_settings();
		$this->init_hooks();
	}

	private function init_providers() {
		$this->providers = array(
			'facebook' => array(
				'name'          => 'Facebook',
				'auth_url'      => 'https://www.facebook.com/v18.0/dialog/oauth',
				'token_url'     => 'https://graph.facebook.com/v18.0/oauth/access_token',
				'api_base'      => 'https://graph.facebook.com/v18.0',
				'scope'         => 'email,public_profile,user_photos,user_birthday,user_location',
				'icon'          => 'fab fa-facebook-f',
				'color'         => '#1877f2',
				'enabled'       => true,
			),
			'google' => array(
				'name'          => 'Google',
				'auth_url'      => 'https://accounts.google.com/o/oauth2/v2/auth',
				'token_url'     => 'https://oauth2.googleapis.com/token',
				'api_base'      => 'https://www.googleapis.com/oauth2/v2',
				'scope'         => 'openid email profile',
				'icon'          => 'fab fa-google',
				'color'         => '#db4437',
				'enabled'       => true,
			),
			'instagram' => array(
				'name'          => 'Instagram',
				'auth_url'      => 'https://api.instagram.com/oauth/authorize',
				'token_url'     => 'https://api.instagram.com/oauth/access_token',
				'api_base'      => 'https://graph.instagram.com',
				'scope'         => 'user_profile,user_media',
				'icon'          => 'fab fa-instagram',
				'color'         => '#e4405f',
				'enabled'       => false, // Requires business verification
			),
			'linkedin' => array(
				'name'          => 'LinkedIn',
				'auth_url'      => 'https://www.linkedin.com/oauth/v2/authorization',
				'token_url'     => 'https://www.linkedin.com/oauth/v2/accessToken',
				'api_base'      => 'https://api.linkedin.com/v2',
				'scope'         => 'r_liteprofile r_emailaddress',
				'icon'          => 'fab fa-linkedin-in',
				'color'         => '#0077b5',
				'enabled'       => true,
			),
			'twitter' => array(
				'name'          => 'Twitter',
				'auth_url'      => 'https://twitter.com/i/oauth2/authorize',
				'token_url'     => 'https://api.twitter.com/2/oauth2/token',
				'api_base'      => 'https://api.twitter.com/2',
				'scope'         => 'tweet.read users.read',
				'icon'          => 'fab fa-twitter',
				'color'         => '#1da1f2',
				'enabled'       => true,
			),
			'spotify' => array(
				'name'          => 'Spotify',
				'auth_url'      => 'https://accounts.spotify.com/authorize',
				'token_url'     => 'https://accounts.spotify.com/api/token',
				'api_base'      => 'https://api.spotify.com/v1',
				'scope'         => 'user-read-email user-read-private user-top-read',
				'icon'          => 'fab fa-spotify',
				'color'         => '#1db954',
				'enabled'       => true,
			),
		);
	}

	private function load_oauth_settings() {
		$this->oauth_settings = get_option( 'wpmatch_oauth_settings', array() );

		// Set default settings for each provider
		foreach ( $this->providers as $provider_id => $provider ) {
			if ( ! isset( $this->oauth_settings[ $provider_id ] ) ) {
				$this->oauth_settings[ $provider_id ] = array(
					'client_id'     => '',
					'client_secret' => '',
					'enabled'       => false,
				);
			}
		}
	}

	private function init_hooks() {
		add_action( 'init', array( $this, 'create_oauth_tables' ) );
		add_action( 'init', array( $this, 'handle_oauth_callback' ) );

		// Frontend hooks
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_oauth_scripts' ) );
		add_action( 'wpmatch_login_form', array( $this, 'render_social_login_buttons' ) );
		add_action( 'wpmatch_profile_social_section', array( $this, 'render_social_connections' ) );

		// AJAX handlers
		add_action( 'wp_ajax_wpmatch_connect_social', array( $this, 'ajax_connect_social' ) );
		add_action( 'wp_ajax_wpmatch_disconnect_social', array( $this, 'ajax_disconnect_social' ) );
		add_action( 'wp_ajax_wpmatch_import_social_data', array( $this, 'ajax_import_social_data' ) );
		add_action( 'wp_ajax_wpmatch_get_social_photos', array( $this, 'ajax_get_social_photos' ) );

		// Admin hooks
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_admin_settings' ) );

		// Profile enhancement hooks
		add_action( 'wpmatch_profile_created', array( $this, 'enhance_profile_with_social_data' ) );
		add_action( 'wpmatch_social_connected', array( $this, 'sync_social_profile_data' ), 10, 2 );

		// Scheduled tasks
		add_action( 'wpmatch_refresh_social_data', array( $this, 'refresh_all_social_data' ) );

		if ( ! wp_next_scheduled( 'wpmatch_refresh_social_data' ) ) {
			wp_schedule_event( time(), 'daily', 'wpmatch_refresh_social_data' );
		}
	}

	public function create_oauth_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// OAuth tokens table
		$oauth_table = $wpdb->prefix . self::OAUTH_TOKENS_TABLE;
		$oauth_sql = "CREATE TABLE IF NOT EXISTS $oauth_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			provider varchar(50) NOT NULL,
			provider_user_id varchar(255) NOT NULL,
			access_token text NOT NULL,
			refresh_token text DEFAULT NULL,
			token_type varchar(50) DEFAULT 'Bearer',
			expires_at datetime DEFAULT NULL,
			scope text DEFAULT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_provider (user_id, provider),
			KEY provider (provider),
			KEY provider_user_id (provider_user_id),
			KEY is_active (is_active),
			KEY expires_at (expires_at)
		) $charset_collate;";

		// Social profiles table
		$profiles_table = $wpdb->prefix . self::SOCIAL_PROFILES_TABLE;
		$profiles_sql = "CREATE TABLE IF NOT EXISTS $profiles_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			provider varchar(50) NOT NULL,
			provider_user_id varchar(255) NOT NULL,
			profile_data longtext NOT NULL,
			profile_url varchar(500) DEFAULT NULL,
			photos longtext DEFAULT NULL,
			interests longtext DEFAULT NULL,
			last_synced datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_provider (user_id, provider),
			KEY provider (provider),
			KEY last_synced (last_synced)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $oauth_sql );
		dbDelta( $profiles_sql );
	}

	public function get_oauth_url( $provider, $redirect_uri = null ) {
		if ( ! isset( $this->providers[ $provider ] ) ) {
			return false;
		}

		$provider_config = $this->providers[ $provider ];
		$settings = $this->oauth_settings[ $provider ];

		if ( empty( $settings['client_id'] ) || ! $settings['enabled'] ) {
			return false;
		}

		if ( ! $redirect_uri ) {
			$redirect_uri = home_url( '/oauth-callback/' . $provider );
		}

		$state = wp_generate_password( 32, false );
		set_transient( 'wpmatch_oauth_state_' . $state, array(
			'provider'     => $provider,
			'user_id'      => get_current_user_id(),
			'redirect_uri' => $redirect_uri,
		), 600 ); // 10 minutes

		$params = array(
			'client_id'     => $settings['client_id'],
			'redirect_uri'  => $redirect_uri,
			'scope'         => $provider_config['scope'],
			'response_type' => 'code',
			'state'         => $state,
		);

		// Provider-specific parameters
		switch ( $provider ) {
			case 'google':
				$params['access_type'] = 'offline';
				$params['prompt'] = 'consent';
				break;

			case 'facebook':
				$params['display'] = 'popup';
				break;

			case 'linkedin':
				// LinkedIn uses different parameter names
				unset( $params['scope'] );
				$params['scope'] = str_replace( ' ', '%20', $provider_config['scope'] );
				break;
		}

		return $provider_config['auth_url'] . '?' . http_build_query( $params );
	}

	public function handle_oauth_callback() {
		if ( ! isset( $_GET['code'] ) || ! isset( $_GET['state'] ) ) {
			return;
		}

		$code = sanitize_text_field( wp_unslash( $_GET['code'] ) );
		$state = sanitize_text_field( wp_unslash( $_GET['state'] ) );

		$state_data = get_transient( 'wpmatch_oauth_state_' . $state );
		if ( ! $state_data ) {
			wp_die( esc_html__( 'Invalid or expired OAuth state. Please try again.', 'wpmatch' ) );
		}

		delete_transient( 'wpmatch_oauth_state_' . $state );

		$provider = $state_data['provider'];
		$user_id = $state_data['user_id'];

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$result = $this->exchange_code_for_token( $provider, $code, $state_data['redirect_uri'] );

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( sprintf( __( 'OAuth error: %s', 'wpmatch' ), $result->get_error_message() ) ) );
		}

		$this->store_oauth_token( $user_id, $provider, $result );
		$this->fetch_and_store_profile_data( $user_id, $provider, $result['access_token'] );

		do_action( 'wpmatch_social_connected', $user_id, $provider );

		wp_safe_redirect( add_query_arg( array(
			'oauth_success' => '1',
			'provider'      => $provider,
		), home_url( '/profile/social' ) ) );
		exit;
	}

	private function exchange_code_for_token( $provider, $code, $redirect_uri ) {
		$provider_config = $this->providers[ $provider ];
		$settings = $this->oauth_settings[ $provider ];

		$params = array(
			'grant_type'    => 'authorization_code',
			'client_id'     => $settings['client_id'],
			'client_secret' => $settings['client_secret'],
			'code'          => $code,
			'redirect_uri'  => $redirect_uri,
		);

		$response = wp_remote_post( $provider_config['token_url'], array(
			'body'    => $params,
			'headers' => array(
				'Accept' => 'application/json',
			),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! $data || isset( $data['error'] ) ) {
			return new WP_Error( 'oauth_error', $data['error_description'] ?? 'Unknown OAuth error' );
		}

		return array(
			'access_token'  => $data['access_token'],
			'refresh_token' => $data['refresh_token'] ?? null,
			'token_type'    => $data['token_type'] ?? 'Bearer',
			'expires_in'    => $data['expires_in'] ?? null,
			'scope'         => $data['scope'] ?? null,
		);
	}

	private function store_oauth_token( $user_id, $provider, $token_data ) {
		global $wpdb;

		$expires_at = null;
		if ( isset( $token_data['expires_in'] ) ) {
			$expires_at = gmdate( 'Y-m-d H:i:s', time() + (int) $token_data['expires_in'] );
		}

		return $wpdb->replace(
			$wpdb->prefix . self::OAUTH_TOKENS_TABLE,
			array(
				'user_id'       => $user_id,
				'provider'      => $provider,
				'access_token'  => $token_data['access_token'],
				'refresh_token' => $token_data['refresh_token'],
				'token_type'    => $token_data['token_type'],
				'expires_at'    => $expires_at,
				'scope'         => $token_data['scope'],
				'is_active'     => 1,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);
	}

	private function fetch_and_store_profile_data( $user_id, $provider, $access_token ) {
		$profile_data = $this->fetch_profile_data( $provider, $access_token );

		if ( is_wp_error( $profile_data ) ) {
			return $profile_data;
		}

		global $wpdb;

		$photos = $this->fetch_profile_photos( $provider, $access_token );
		$interests = $this->extract_interests_from_profile( $provider, $profile_data );

		return $wpdb->replace(
			$wpdb->prefix . self::SOCIAL_PROFILES_TABLE,
			array(
				'user_id'         => $user_id,
				'provider'        => $provider,
				'provider_user_id' => $profile_data['id'],
				'profile_data'    => wp_json_encode( $profile_data ),
				'profile_url'     => $this->generate_profile_url( $provider, $profile_data ),
				'photos'          => wp_json_encode( $photos ),
				'interests'       => wp_json_encode( $interests ),
				'last_synced'     => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	private function fetch_profile_data( $provider, $access_token ) {
		$provider_config = $this->providers[ $provider ];
		$api_endpoints = array(
			'facebook'  => '/me?fields=id,name,email,birthday,location,picture.type(large)',
			'google'    => '/userinfo',
			'linkedin'  => '/people/~:(id,firstName,lastName,emailAddress,pictureUrl)',
			'twitter'   => '/users/me?user.fields=id,name,username,profile_image_url,location',
			'spotify'   => '/me',
		);

		$endpoint = $api_endpoints[ $provider ] ?? '/me';
		$url = $provider_config['api_base'] . $endpoint;

		$response = wp_remote_get( $url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Accept'        => 'application/json',
			),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! $data || isset( $data['error'] ) ) {
			return new WP_Error( 'api_error', 'Failed to fetch profile data' );
		}

		return $this->normalize_profile_data( $provider, $data );
	}

	private function normalize_profile_data( $provider, $data ) {
		$normalized = array(
			'id'         => '',
			'name'       => '',
			'email'      => '',
			'picture'    => '',
			'location'   => '',
			'birthday'   => '',
			'raw_data'   => $data,
		);

		switch ( $provider ) {
			case 'facebook':
				$normalized['id'] = $data['id'] ?? '';
				$normalized['name'] = $data['name'] ?? '';
				$normalized['email'] = $data['email'] ?? '';
				$normalized['picture'] = $data['picture']['data']['url'] ?? '';
				$normalized['location'] = $data['location']['name'] ?? '';
				$normalized['birthday'] = $data['birthday'] ?? '';
				break;

			case 'google':
				$normalized['id'] = $data['id'] ?? '';
				$normalized['name'] = $data['name'] ?? '';
				$normalized['email'] = $data['email'] ?? '';
				$normalized['picture'] = $data['picture'] ?? '';
				break;

			case 'linkedin':
				$normalized['id'] = $data['id'] ?? '';
				$normalized['name'] = trim( ( $data['firstName'] ?? '' ) . ' ' . ( $data['lastName'] ?? '' ) );
				$normalized['email'] = $data['emailAddress'] ?? '';
				$normalized['picture'] = $data['pictureUrl'] ?? '';
				break;

			case 'twitter':
				$normalized['id'] = $data['data']['id'] ?? '';
				$normalized['name'] = $data['data']['name'] ?? '';
				$normalized['picture'] = $data['data']['profile_image_url'] ?? '';
				$normalized['location'] = $data['data']['location'] ?? '';
				break;

			case 'spotify':
				$normalized['id'] = $data['id'] ?? '';
				$normalized['name'] = $data['display_name'] ?? '';
				$normalized['email'] = $data['email'] ?? '';
				$normalized['picture'] = $data['images'][0]['url'] ?? '';
				break;
		}

		return $normalized;
	}

	private function fetch_profile_photos( $provider, $access_token ) {
		$photos = array();

		switch ( $provider ) {
			case 'facebook':
				$photos = $this->fetch_facebook_photos( $access_token );
				break;

			case 'instagram':
				$photos = $this->fetch_instagram_photos( $access_token );
				break;

			default:
				// Other providers don't typically provide photo access
				break;
		}

		return $photos;
	}

	private function fetch_facebook_photos( $access_token ) {
		$url = 'https://graph.facebook.com/v18.0/me/photos/uploaded?fields=source,picture&limit=20';

		$response = wp_remote_get( $url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
			),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		$photos = array();
		if ( isset( $data['data'] ) ) {
			foreach ( $data['data'] as $photo ) {
				$photos[] = array(
					'id'        => $photo['id'],
					'url'       => $photo['source'],
					'thumbnail' => $photo['picture'],
				);
			}
		}

		return $photos;
	}

	private function fetch_instagram_photos( $access_token ) {
		$url = 'https://graph.instagram.com/me/media?fields=id,media_type,media_url,thumbnail_url&limit=20';

		$response = wp_remote_get( $url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
			),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		$photos = array();
		if ( isset( $data['data'] ) ) {
			foreach ( $data['data'] as $media ) {
				if ( 'IMAGE' === $media['media_type'] ) {
					$photos[] = array(
						'id'        => $media['id'],
						'url'       => $media['media_url'],
						'thumbnail' => $media['thumbnail_url'] ?? $media['media_url'],
					);
				}
			}
		}

		return $photos;
	}

	private function extract_interests_from_profile( $provider, $profile_data ) {
		$interests = array();

		switch ( $provider ) {
			case 'spotify':
				// Get top artists/genres from Spotify
				$interests = $this->fetch_spotify_interests( $profile_data );
				break;

			case 'facebook':
				// Extract interests from Facebook likes/pages
				if ( isset( $profile_data['raw_data']['likes'] ) ) {
					foreach ( $profile_data['raw_data']['likes']['data'] as $like ) {
						$interests[] = $like['name'];
					}
				}
				break;

			default:
				// For other providers, interests might be extracted from bio/description
				break;
		}

		return array_slice( $interests, 0, 10 ); // Limit to 10 interests
	}

	private function fetch_spotify_interests( $profile_data ) {
		// This would require additional Spotify API calls
		// For now, return empty array
		return array();
	}

	private function generate_profile_url( $provider, $profile_data ) {
		$urls = array(
			'facebook'  => 'https://facebook.com/' . $profile_data['id'],
			'instagram' => 'https://instagram.com/' . ( $profile_data['username'] ?? $profile_data['id'] ),
			'twitter'   => 'https://twitter.com/' . ( $profile_data['username'] ?? $profile_data['id'] ),
			'linkedin'  => 'https://linkedin.com/in/' . $profile_data['id'],
			'spotify'   => 'https://open.spotify.com/user/' . $profile_data['id'],
		);

		return $urls[ $provider ] ?? '';
	}

	public function get_user_social_connections( $user_id ) {
		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT sp.*, ot.is_active as token_active
			FROM {$wpdb->prefix}wpmatch_social_profiles sp
			LEFT JOIN {$wpdb->prefix}wpmatch_oauth_tokens ot ON sp.user_id = ot.user_id AND sp.provider = ot.provider
			WHERE sp.user_id = %d
			ORDER BY sp.created_at DESC",
			$user_id
		) );
	}

	public function disconnect_social_account( $user_id, $provider ) {
		global $wpdb;

		// Remove OAuth token
		$wpdb->delete(
			$wpdb->prefix . self::OAUTH_TOKENS_TABLE,
			array(
				'user_id'  => $user_id,
				'provider' => $provider,
			),
			array( '%d', '%s' )
		);

		// Remove social profile data
		$wpdb->delete(
			$wpdb->prefix . self::SOCIAL_PROFILES_TABLE,
			array(
				'user_id'  => $user_id,
				'provider' => $provider,
			),
			array( '%d', '%s' )
		);

		do_action( 'wpmatch_social_disconnected', $user_id, $provider );

		return true;
	}

	public function enhance_profile_with_social_data( $user_id ) {
		$social_connections = $this->get_user_social_connections( $user_id );
		$profile_data = get_user_meta( $user_id, 'wpmatch_profile', true );
		$profile_data = is_array( $profile_data ) ? $profile_data : array();

		foreach ( $social_connections as $connection ) {
			$social_data = json_decode( $connection->profile_data, true );

			if ( ! $social_data ) {
				continue;
			}

			// Auto-fill basic profile information if not set
			if ( empty( $profile_data['location'] ) && ! empty( $social_data['location'] ) ) {
				$profile_data['location'] = $social_data['location'];
			}

			if ( empty( $profile_data['birthday'] ) && ! empty( $social_data['birthday'] ) ) {
				$profile_data['birthday'] = $social_data['birthday'];
			}

			// Add social interests
			if ( ! empty( $connection->interests ) ) {
				$interests = json_decode( $connection->interests, true );
				if ( is_array( $interests ) ) {
					$existing_interests = $profile_data['interests'] ?? array();
					$profile_data['interests'] = array_unique( array_merge( $existing_interests, $interests ) );
				}
			}

			// Add social verification
			$profile_data['social_verified'][ $connection->provider ] = true;
		}

		update_user_meta( $user_id, 'wpmatch_profile', $profile_data );
	}

	public function sync_social_profile_data( $user_id, $provider ) {
		global $wpdb;

		$token = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}wpmatch_oauth_tokens
			WHERE user_id = %d AND provider = %s AND is_active = 1",
			$user_id,
			$provider
		) );

		if ( ! $token ) {
			return false;
		}

		// Check if token is expired and refresh if possible
		if ( $token->expires_at && strtotime( $token->expires_at ) < time() ) {
			if ( $token->refresh_token ) {
				$refreshed = $this->refresh_access_token( $provider, $token->refresh_token );
				if ( $refreshed ) {
					$token->access_token = $refreshed['access_token'];
				} else {
					return false;
				}
			} else {
				return false;
			}
		}

		return $this->fetch_and_store_profile_data( $user_id, $provider, $token->access_token );
	}

	private function refresh_access_token( $provider, $refresh_token ) {
		$provider_config = $this->providers[ $provider ];
		$settings = $this->oauth_settings[ $provider ];

		$params = array(
			'grant_type'    => 'refresh_token',
			'refresh_token' => $refresh_token,
			'client_id'     => $settings['client_id'],
			'client_secret' => $settings['client_secret'],
		);

		$response = wp_remote_post( $provider_config['token_url'], array(
			'body'    => $params,
			'headers' => array(
				'Accept' => 'application/json',
			),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! $data || isset( $data['error'] ) ) {
			return false;
		}

		return array(
			'access_token'  => $data['access_token'],
			'refresh_token' => $data['refresh_token'] ?? $refresh_token,
			'expires_in'    => $data['expires_in'] ?? null,
		);
	}

	public function render_social_login_buttons() {
		$enabled_providers = array_filter( $this->providers, function( $provider, $key ) {
			return $provider['enabled'] && ! empty( $this->oauth_settings[ $key ]['client_id'] ) && $this->oauth_settings[ $key ]['enabled'];
		}, ARRAY_FILTER_USE_BOTH );

		if ( empty( $enabled_providers ) ) {
			return;
		}

		echo '<div class="wpmatch-social-login">';
		echo '<p class="social-login-title">' . esc_html__( 'Or continue with:', 'wpmatch' ) . '</p>';
		echo '<div class="social-login-buttons">';

		foreach ( $enabled_providers as $provider_id => $provider ) {
			$oauth_url = $this->get_oauth_url( $provider_id );
			if ( $oauth_url ) {
				printf(
					'<a href="%s" class="social-login-btn social-login-%s" style="background-color: %s">
						<i class="%s"></i>
						<span>%s</span>
					</a>',
					esc_url( $oauth_url ),
					esc_attr( $provider_id ),
					esc_attr( $provider['color'] ),
					esc_attr( $provider['icon'] ),
					esc_html( sprintf( __( 'Continue with %s', 'wpmatch' ), $provider['name'] ) )
				);
			}
		}

		echo '</div>';
		echo '</div>';
	}

	public function render_social_connections() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id = get_current_user_id();
		$connections = $this->get_user_social_connections( $user_id );
		$available_providers = array_filter( $this->providers, function( $provider, $key ) {
			return $provider['enabled'] && ! empty( $this->oauth_settings[ $key ]['client_id'] ) && $this->oauth_settings[ $key ]['enabled'];
		}, ARRAY_FILTER_USE_BOTH );

		echo '<div class="wpmatch-social-connections">';
		echo '<h3>' . esc_html__( 'Social Media Connections', 'wpmatch' ) . '</h3>';

		foreach ( $available_providers as $provider_id => $provider ) {
			$connected = false;
			$connection_data = null;

			foreach ( $connections as $connection ) {
				if ( $connection->provider === $provider_id ) {
					$connected = true;
					$connection_data = $connection;
					break;
				}
			}

			echo '<div class="social-connection-item">';
			echo '<div class="social-provider-info">';
			printf( '<i class="%s" style="color: %s"></i>', esc_attr( $provider['icon'] ), esc_attr( $provider['color'] ) );
			echo '<span class="provider-name">' . esc_html( $provider['name'] ) . '</span>';
			echo '</div>';

			if ( $connected ) {
				echo '<div class="connection-status connected">';
				echo '<span class="status-text">' . esc_html__( 'Connected', 'wpmatch' ) . '</span>';
				printf(
					'<button class="disconnect-btn" data-provider="%s">%s</button>',
					esc_attr( $provider_id ),
					esc_html__( 'Disconnect', 'wpmatch' )
				);
				echo '</div>';
			} else {
				$oauth_url = $this->get_oauth_url( $provider_id );
				echo '<div class="connection-status not-connected">';
				if ( $oauth_url ) {
					printf(
						'<a href="%s" class="connect-btn">%s</a>',
						esc_url( $oauth_url ),
						esc_html__( 'Connect', 'wpmatch' )
					);
				} else {
					echo '<span class="status-text disabled">' . esc_html__( 'Not Available', 'wpmatch' ) . '</span>';
				}
				echo '</div>';
			}

			echo '</div>';
		}

		echo '</div>';
	}

	public function enqueue_oauth_scripts() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		wp_enqueue_script( 'wpmatch-social-oauth',
			plugins_url( 'public/js/wpmatch-social.js', dirname( __FILE__ ) ),
			array( 'jquery' ), '1.0.0', true
		);

		wp_localize_script( 'wpmatch-social-oauth', 'wpMatchSocial', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'wpmatch_social_nonce' ),
			'strings' => array(
				'confirmDisconnect' => __( 'Are you sure you want to disconnect this account?', 'wpmatch' ),
				'connecting'        => __( 'Connecting...', 'wpmatch' ),
				'disconnecting'     => __( 'Disconnecting...', 'wpmatch' ),
				'connected'         => __( 'Connected successfully!', 'wpmatch' ),
				'disconnected'      => __( 'Disconnected successfully!', 'wpmatch' ),
				'error'             => __( 'An error occurred. Please try again.', 'wpmatch' ),
			),
		) );

		wp_enqueue_style( 'wpmatch-social-styles',
			plugins_url( 'public/css/wpmatch-social.css', dirname( __FILE__ ) ),
			array(), '1.0.0'
		);
	}

	public function refresh_all_social_data() {
		global $wpdb;

		$active_tokens = $wpdb->get_results(
			"SELECT DISTINCT user_id, provider FROM {$wpdb->prefix}wpmatch_oauth_tokens
			WHERE is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())"
		);

		foreach ( $active_tokens as $token ) {
			$this->sync_social_profile_data( $token->user_id, $token->provider );
		}
	}

	// AJAX handlers
	public function ajax_connect_social() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpmatch_social_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		$user_id = get_current_user_id();
		$provider = sanitize_text_field( wp_unslash( $_POST['provider'] ?? '' ) );

		if ( ! $user_id || ! $provider ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters' ) );
		}

		$oauth_url = $this->get_oauth_url( $provider );

		if ( $oauth_url ) {
			wp_send_json_success( array( 'oauth_url' => $oauth_url ) );
		} else {
			wp_send_json_error( array( 'message' => 'Provider not available' ) );
		}
	}

	public function ajax_disconnect_social() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpmatch_social_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		$user_id = get_current_user_id();
		$provider = sanitize_text_field( wp_unslash( $_POST['provider'] ?? '' ) );

		if ( ! $user_id || ! $provider ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters' ) );
		}

		$result = $this->disconnect_social_account( $user_id, $provider );

		if ( $result ) {
			wp_send_json_success( array( 'message' => 'Account disconnected successfully' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to disconnect account' ) );
		}
	}

	public function ajax_import_social_data() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpmatch_social_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		$user_id = get_current_user_id();
		$provider = sanitize_text_field( wp_unslash( $_POST['provider'] ?? '' ) );

		if ( ! $user_id || ! $provider ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters' ) );
		}

		$result = $this->sync_social_profile_data( $user_id, $provider );

		if ( $result ) {
			$this->enhance_profile_with_social_data( $user_id );
			wp_send_json_success( array( 'message' => 'Social data imported successfully' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to import social data' ) );
		}
	}

	public function ajax_get_social_photos() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpmatch_social_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		$user_id = get_current_user_id();
		$provider = sanitize_text_field( wp_unslash( $_POST['provider'] ?? '' ) );

		if ( ! $user_id || ! $provider ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters' ) );
		}

		global $wpdb;

		$social_profile = $wpdb->get_row( $wpdb->prepare(
			"SELECT photos FROM {$wpdb->prefix}wpmatch_social_profiles
			WHERE user_id = %d AND provider = %s",
			$user_id,
			$provider
		) );

		if ( $social_profile ) {
			$photos = json_decode( $social_profile->photos, true );
			wp_send_json_success( array( 'photos' => $photos ?? array() ) );
		} else {
			wp_send_json_error( array( 'message' => 'No photos found' ) );
		}
	}

	// Admin functions
	public function add_admin_menu() {
		add_submenu_page(
			'wpmatch-admin',
			__( 'Social OAuth', 'wpmatch' ),
			__( 'Social OAuth', 'wpmatch' ),
			'manage_options',
			'wpmatch-social-oauth',
			array( $this, 'admin_page' )
		);
	}

	public function register_admin_settings() {
		register_setting( 'wpmatch_oauth_settings', 'wpmatch_oauth_settings' );

		foreach ( $this->providers as $provider_id => $provider ) {
			add_settings_section(
				"wpmatch_oauth_{$provider_id}",
				$provider['name'],
				null,
				'wpmatch_oauth_settings'
			);

			add_settings_field(
				"wpmatch_oauth_{$provider_id}_enabled",
				__( 'Enable', 'wpmatch' ),
				array( $this, 'render_checkbox_field' ),
				'wpmatch_oauth_settings',
				"wpmatch_oauth_{$provider_id}",
				array(
					'field_id' => "{$provider_id}_enabled",
					'value'    => $this->oauth_settings[ $provider_id ]['enabled'] ?? false,
				)
			);

			add_settings_field(
				"wpmatch_oauth_{$provider_id}_client_id",
				__( 'Client ID', 'wpmatch' ),
				array( $this, 'render_text_field' ),
				'wpmatch_oauth_settings',
				"wpmatch_oauth_{$provider_id}",
				array(
					'field_id' => "{$provider_id}_client_id",
					'value'    => $this->oauth_settings[ $provider_id ]['client_id'] ?? '',
				)
			);

			add_settings_field(
				"wpmatch_oauth_{$provider_id}_client_secret",
				__( 'Client Secret', 'wpmatch' ),
				array( $this, 'render_password_field' ),
				'wpmatch_oauth_settings',
				"wpmatch_oauth_{$provider_id}",
				array(
					'field_id' => "{$provider_id}_client_secret",
					'value'    => $this->oauth_settings[ $provider_id ]['client_secret'] ?? '',
				)
			);
		}
	}

	public function render_checkbox_field( $args ) {
		printf(
			'<input type="checkbox" name="wpmatch_oauth_settings[%s]" value="1" %s />',
			esc_attr( $args['field_id'] ),
			checked( $args['value'], true, false )
		);
	}

	public function render_text_field( $args ) {
		printf(
			'<input type="text" name="wpmatch_oauth_settings[%s]" value="%s" class="regular-text" />',
			esc_attr( $args['field_id'] ),
			esc_attr( $args['value'] )
		);
	}

	public function render_password_field( $args ) {
		printf(
			'<input type="password" name="wpmatch_oauth_settings[%s]" value="%s" class="regular-text" />',
			esc_attr( $args['field_id'] ),
			esc_attr( $args['value'] )
		);
	}

	public function admin_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Social OAuth Settings', 'wpmatch' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'wpmatch_oauth_settings' );
				do_settings_sections( 'wpmatch_oauth_settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}