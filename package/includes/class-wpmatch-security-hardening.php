<?php
/**
 * WPMatch Security Hardening
 *
 * Comprehensive security hardening measures to protect the WordPress installation
 *
 * @package WPMatch
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPMatch_Security_Hardening {

	private static $instance = null;

	private $hardening_options = array();

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->init_hardening_options();
		$this->init_hooks();
	}

	private function init_hardening_options() {
		$this->hardening_options = get_option( 'wpmatch_security_hardening', array(
			'disable_file_editing'         => true,
			'disable_xmlrpc'               => true,
			'remove_wp_version'            => true,
			'disable_user_enumeration'     => true,
			'limit_login_attempts'         => true,
			'disable_directory_browsing'   => true,
			'secure_headers'               => true,
			'disable_trackbacks'           => true,
			'remove_generator_tags'        => true,
			'disable_rest_api_users'       => true,
			'hide_login_errors'            => true,
			'disable_application_passwords' => true,
		) );
	}

	private function init_hooks() {
		// Core hardening measures
		add_action( 'init', array( $this, 'apply_hardening_measures' ) );

		// Security headers
		add_action( 'send_headers', array( $this, 'add_security_headers' ) );

		// Login security
		add_filter( 'authenticate', array( $this, 'limit_login_attempts' ), 30, 3 );
		add_action( 'wp_login_failed', array( $this, 'log_failed_login' ) );

		// Hide login errors
		if ( $this->is_enabled( 'hide_login_errors' ) ) {
			add_filter( 'login_errors', array( $this, 'hide_login_errors' ) );
		}

		// Disable user enumeration
		if ( $this->is_enabled( 'disable_user_enumeration' ) ) {
			add_action( 'init', array( $this, 'disable_user_enumeration' ) );
		}

		// XML-RPC security
		if ( $this->is_enabled( 'disable_xmlrpc' ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'wp_headers', array( $this, 'remove_xmlrpc_pingback_header' ) );
		}

		// Remove WordPress version
		if ( $this->is_enabled( 'remove_wp_version' ) ) {
			remove_action( 'wp_head', 'wp_generator' );
			add_filter( 'the_generator', '__return_empty_string' );
		}

		// Remove generator tags
		if ( $this->is_enabled( 'remove_generator_tags' ) ) {
			$this->remove_generator_tags();
		}

		// Disable file editing
		if ( $this->is_enabled( 'disable_file_editing' ) ) {
			if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
				define( 'DISALLOW_FILE_EDIT', true );
			}
		}

		// Disable trackbacks
		if ( $this->is_enabled( 'disable_trackbacks' ) ) {
			add_filter( 'xmlrpc_methods', array( $this, 'disable_xmlrpc_pingback' ) );
		}

		// REST API security
		if ( $this->is_enabled( 'disable_rest_api_users' ) ) {
			add_filter( 'rest_endpoints', array( $this, 'disable_rest_api_users' ) );
		}

		// Disable application passwords
		if ( $this->is_enabled( 'disable_application_passwords' ) ) {
			add_filter( 'wp_is_application_passwords_available', '__return_false' );
		}

		// .htaccess hardening
		add_action( 'generate_rewrite_rules', array( $this, 'add_htaccess_hardening' ) );

		// Admin hooks
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'wp_ajax_wpmatch_toggle_hardening', array( $this, 'ajax_toggle_hardening' ) );
		add_action( 'wp_ajax_wpmatch_scan_vulnerabilities', array( $this, 'ajax_scan_vulnerabilities' ) );
	}

	public function apply_hardening_measures() {
		// Force SSL for admin and login pages
		if ( ! defined( 'FORCE_SSL_ADMIN' ) && is_ssl() ) {
			define( 'FORCE_SSL_ADMIN', true );
		}

		// Disable directory browsing
		if ( $this->is_enabled( 'disable_directory_browsing' ) ) {
			$this->disable_directory_browsing();
		}

		// Set secure cookie settings
		$this->set_secure_cookies();

		// Remove unnecessary WordPress features
		$this->remove_unnecessary_features();
	}

	public function add_security_headers() {
		if ( ! $this->is_enabled( 'secure_headers' ) ) {
			return;
		}

		// X-Content-Type-Options
		header( 'X-Content-Type-Options: nosniff' );

		// X-Frame-Options
		header( 'X-Frame-Options: SAMEORIGIN' );

		// X-XSS-Protection
		header( 'X-XSS-Protection: 1; mode=block' );

		// Referrer Policy
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );

		// Content Security Policy
		$csp = $this->get_content_security_policy();
		if ( $csp ) {
			header( 'Content-Security-Policy: ' . $csp );
		}

		// Strict Transport Security (only if HTTPS)
		if ( is_ssl() ) {
			header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );
		}

		// Permissions Policy
		header( 'Permissions-Policy: geolocation=(), microphone=(), camera=()' );
	}

	private function get_content_security_policy() {
		$directives = array(
			"default-src 'self'",
			"script-src 'self' 'unsafe-inline' 'unsafe-eval' *.googleapis.com *.gstatic.com",
			"style-src 'self' 'unsafe-inline' *.googleapis.com",
			"img-src 'self' data: *.gravatar.com *.wordpress.com",
			"font-src 'self' data: *.googleapis.com *.gstatic.com",
			"worker-src 'self' blob:",
			"connect-src 'self'",
			"frame-src 'self'",
			"object-src 'none'",
			"base-uri 'self'",
			"form-action 'self'",
		);

		return implode( '; ', apply_filters( 'wpmatch_csp_directives', $directives ) );
	}

	public function limit_login_attempts( $user, $username, $password ) {
		if ( ! $this->is_enabled( 'limit_login_attempts' ) ) {
			return $user;
		}

		$ip_address = $this->get_client_ip();
		$attempts_key = 'wpmatch_login_attempts_' . md5( $ip_address );
		$lockout_key = 'wpmatch_login_lockout_' . md5( $ip_address );

		// Check if IP is currently locked out
		if ( get_transient( $lockout_key ) ) {
			$lockout_time = get_transient( $lockout_key );
			$remaining_time = $lockout_time - time();

			if ( $remaining_time > 0 ) {
				return new WP_Error(
					'login_locked',
					sprintf(
						__( 'Too many failed login attempts. Please try again in %d minutes.', 'wpmatch' ),
						ceil( $remaining_time / 60 )
					)
				);
			} else {
				delete_transient( $lockout_key );
				delete_transient( $attempts_key );
			}
		}

		return $user;
	}

	public function log_failed_login( $username ) {
		if ( ! $this->is_enabled( 'limit_login_attempts' ) ) {
			return;
		}

		$ip_address = $this->get_client_ip();
		$attempts_key = 'wpmatch_login_attempts_' . md5( $ip_address );
		$lockout_key = 'wpmatch_login_lockout_' . md5( $ip_address );

		$attempts = get_transient( $attempts_key );
		$attempts = $attempts ? $attempts + 1 : 1;

		// Set attempts counter (expires in 1 hour)
		set_transient( $attempts_key, $attempts, HOUR_IN_SECONDS );

		// Lock out after 5 failed attempts
		if ( $attempts >= 5 ) {
			$lockout_duration = $this->get_lockout_duration( $attempts );
			set_transient( $lockout_key, time() + $lockout_duration, $lockout_duration );

			// Log security event
			do_action( 'wpmatch_security_event', 'login_lockout', array(
				'ip_address' => $ip_address,
				'username'   => $username,
				'attempts'   => $attempts,
				'duration'   => $lockout_duration,
			) );
		}
	}

	private function get_lockout_duration( $attempts ) {
		// Progressive lockout: 5 min, 15 min, 30 min, 1 hour, 24 hours
		$durations = array( 300, 900, 1800, 3600, 86400 );
		$index = min( $attempts - 5, count( $durations ) - 1 );
		return $durations[ $index ];
	}

	public function hide_login_errors() {
		return __( 'Invalid username or password.', 'wpmatch' );
	}

	public function disable_user_enumeration() {
		// Disable user enumeration via /?author=1
		if ( ! is_admin() && isset( $_GET['author'] ) ) {
			wp_die( esc_html__( 'Access denied.', 'wpmatch' ), 403 );
		}

		// Disable user enumeration via REST API
		add_filter( 'rest_user_query', array( $this, 'disable_rest_user_query' ), 10, 2 );
	}

	public function disable_rest_user_query( $prepared_args, $request ) {
		if ( ! current_user_can( 'list_users' ) ) {
			return new WP_Error(
				'rest_user_cannot_view',
				__( 'Sorry, you are not allowed to list users.', 'wpmatch' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return $prepared_args;
	}

	public function remove_xmlrpc_pingback_header( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	public function disable_xmlrpc_pingback( $methods ) {
		unset( $methods['pingback.ping'] );
		unset( $methods['pingback.extensions.getPingbacks'] );
		return $methods;
	}

	public function disable_rest_api_users( $endpoints ) {
		if ( isset( $endpoints['/wp/v2/users'] ) ) {
			unset( $endpoints['/wp/v2/users'] );
		}
		if ( isset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] ) ) {
			unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		}
		return $endpoints;
	}

	private function remove_generator_tags() {
		// Remove various generator meta tags
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );

		// Remove generator from RSS feeds
		add_filter( 'the_generator', '__return_empty_string' );
	}

	private function disable_directory_browsing() {
		$htaccess_file = ABSPATH . '.htaccess';

		if ( ! file_exists( $htaccess_file ) || ! is_writable( $htaccess_file ) ) {
			return;
		}

		$htaccess_content = file_get_contents( $htaccess_file );

		// Check if Options -Indexes is already present
		if ( strpos( $htaccess_content, 'Options -Indexes' ) === false ) {
			$security_rules = "\n# WPMatch Security - Disable Directory Browsing\nOptions -Indexes\n";
			file_put_contents( $htaccess_file, $security_rules . $htaccess_content );
		}
	}

	private function set_secure_cookies() {
		// Set secure and httponly cookie flags
		if ( is_ssl() ) {
			ini_set( 'session.cookie_secure', '1' );
		}
		ini_set( 'session.cookie_httponly', '1' );
		ini_set( 'session.use_only_cookies', '1' );
	}

	private function remove_unnecessary_features() {
		// Remove unnecessary WordPress features that could be security risks
		remove_action( 'wp_head', 'wp_resource_hints', 2 );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		remove_action( 'wp_head', 'rest_output_link_wp_head' );

		// Disable pingbacks
		add_filter( 'wp_headers', array( $this, 'remove_pingback_header' ) );
	}

	public function remove_pingback_header( $headers ) {
		if ( isset( $headers['X-Pingback'] ) ) {
			unset( $headers['X-Pingback'] );
		}
		return $headers;
	}

	public function add_htaccess_hardening() {
		$htaccess_file = ABSPATH . '.htaccess';

		if ( ! file_exists( $htaccess_file ) || ! is_writable( $htaccess_file ) ) {
			return;
		}

		$security_rules = $this->get_htaccess_security_rules();
		$current_content = file_get_contents( $htaccess_file );

		// Check if our security rules are already present
		if ( strpos( $current_content, '# WPMatch Security Rules' ) === false ) {
			// Add security rules at the beginning
			$new_content = $security_rules . "\n" . $current_content;
			file_put_contents( $htaccess_file, $new_content );
		}
	}

	private function get_htaccess_security_rules() {
		return '# WPMatch Security Rules
# Protect sensitive files
<FilesMatch "(^#.*#|\.(bak|config|dist|fla|inc|ini|log|psd|sh|sql|sw[op])|~)$">
	Order allow,deny
	Deny from all
	Satisfy All
</FilesMatch>

# Protect wp-config.php
<Files wp-config.php>
	Order allow,deny
	Deny from all
</Files>

# Protect .htaccess
<Files .htaccess>
	Order allow,deny
	Deny from all
</Files>

# Disable PHP execution in uploads directory
<Directory "' . wp_upload_dir()['basedir'] . '">
	<FilesMatch "\.(php|php3|php4|php5|phtml)$">
		Order Deny,Allow
		Deny from All
	</FilesMatch>
</Directory>

# Block access to wp-includes
<IfModule mod_rewrite.c>
	RewriteEngine On
	RewriteBase /
	RewriteRule ^wp-admin/includes/ - [F,L]
	RewriteRule !^wp-includes/ - [S=3]
	RewriteRule ^wp-includes/[^/]+\.php$ - [F,L]
	RewriteRule ^wp-includes/js/tinymce/langs/.+\.php - [F,L]
	RewriteRule ^wp-includes/theme-compat/ - [F,L]
</IfModule>

# Protect against malicious URL requests
<IfModule mod_rewrite.c>
	RewriteEngine On
	RewriteCond %{QUERY_STRING} (\<|%3C).*script.*(\>|%3E) [NC,OR]
	RewriteCond %{QUERY_STRING} GLOBALS(=|\[|\%[0-9A-Z]{0,2}) [OR]
	RewriteCond %{QUERY_STRING} _REQUEST(=|\[|\%[0-9A-Z]{0,2}) [OR]
	RewriteCond %{QUERY_STRING} proc/self/environ [OR]
	RewriteCond %{QUERY_STRING} mosConfig_[a-zA-Z_]{1,21}(=|\%3D) [OR]
	RewriteCond %{QUERY_STRING} base64_encode.*\(.*\) [OR]
	RewriteCond %{QUERY_STRING} (<|%3C)([^s]*s)+cript.*(>|%3E) [NC,OR]
	RewriteCond %{QUERY_STRING} (\<|%3C).*iframe.*(\>|%3E) [NC]
	RewriteRule .* - [F]
</IfModule>

# Block suspicious user agents
<IfModule mod_rewrite.c>
	RewriteEngine On
	RewriteCond %{HTTP_USER_AGENT} ^$ [OR]
	RewriteCond %{HTTP_USER_AGENT} ^(-|\.|_) [OR]
	RewriteCond %{HTTP_USER_AGENT} (libwww-perl|wget|python|nikto|curl|scan|java|winhttp|clshttp|loader) [NC,OR]
	RewriteCond %{HTTP_USER_AGENT} (<|>|\'|%0A|%0D|%27|%3C|%3E|%00) [NC,OR]
	RewriteCond %{HTTP_USER_AGENT} (;|<|>|\'|"|\)|\(|%0A|%0D|%22|%27|%28|%3C|%3E|%00).*(libwww-perl|wget|python|nikto|curl|scan|java|winhttp|HTTrack|clshttp|archiver|loader|email|harvest|extract|grab|miner) [NC]
	RewriteRule .* - [F]
</IfModule>

# End WPMatch Security Rules
';
	}

	public function scan_vulnerabilities() {
		$vulnerabilities = array();

		// Check for outdated WordPress core
		$wp_version = get_bloginfo( 'version' );
		$latest_version = $this->get_latest_wp_version();
		if ( $latest_version && version_compare( $wp_version, $latest_version, '<' ) ) {
			$vulnerabilities[] = array(
				'type'        => 'outdated_core',
				'severity'    => 'high',
				'title'       => __( 'Outdated WordPress Core', 'wpmatch' ),
				'description' => sprintf(
					__( 'WordPress %s is available. You are running %s.', 'wpmatch' ),
					$latest_version,
					$wp_version
				),
				'fix'         => __( 'Update WordPress to the latest version', 'wpmatch' ),
			);
		}

		// Check for outdated plugins
		$outdated_plugins = $this->get_outdated_plugins();
		foreach ( $outdated_plugins as $plugin ) {
			$vulnerabilities[] = array(
				'type'        => 'outdated_plugin',
				'severity'    => 'medium',
				'title'       => sprintf( __( 'Outdated Plugin: %s', 'wpmatch' ), $plugin['name'] ),
				'description' => sprintf(
					__( 'Plugin %s version %s is available. You are running %s.', 'wpmatch' ),
					$plugin['name'],
					$plugin['new_version'],
					$plugin['current_version']
				),
				'fix'         => __( 'Update the plugin to the latest version', 'wpmatch' ),
			);
		}

		// Check for weak passwords
		$weak_passwords = $this->check_weak_passwords();
		if ( ! empty( $weak_passwords ) ) {
			$vulnerabilities[] = array(
				'type'        => 'weak_passwords',
				'severity'    => 'high',
				'title'       => __( 'Weak Passwords Detected', 'wpmatch' ),
				'description' => sprintf(
					__( '%d users have weak passwords that could be easily compromised.', 'wpmatch' ),
					count( $weak_passwords )
				),
				'fix'         => __( 'Enforce strong password policies and require password changes', 'wpmatch' ),
			);
		}

		// Check file permissions
		$permission_issues = $this->check_file_permissions();
		foreach ( $permission_issues as $issue ) {
			$vulnerabilities[] = array(
				'type'        => 'file_permissions',
				'severity'    => 'medium',
				'title'       => __( 'Incorrect File Permissions', 'wpmatch' ),
				'description' => sprintf(
					__( 'File %s has permissions %s, recommended: %s', 'wpmatch' ),
					$issue['file'],
					$issue['current'],
					$issue['recommended']
				),
				'fix'         => __( 'Set correct file permissions', 'wpmatch' ),
			);
		}

		// Check for exposed sensitive files
		$exposed_files = $this->check_exposed_files();
		foreach ( $exposed_files as $file ) {
			$vulnerabilities[] = array(
				'type'        => 'exposed_files',
				'severity'    => 'critical',
				'title'       => __( 'Exposed Sensitive File', 'wpmatch' ),
				'description' => sprintf(
					__( 'Sensitive file %s is publicly accessible.', 'wpmatch' ),
					$file
				),
				'fix'         => __( 'Restrict access to sensitive files', 'wpmatch' ),
			);
		}

		return $vulnerabilities;
	}

	private function get_latest_wp_version() {
		$version_check = wp_remote_get( 'https://api.wordpress.org/core/version-check/1.7/' );
		if ( is_wp_error( $version_check ) ) {
			return false;
		}

		$version_data = json_decode( wp_remote_retrieve_body( $version_check ), true );
		return isset( $version_data['offers'][0]['version'] ) ? $version_data['offers'][0]['version'] : false;
	}

	private function get_outdated_plugins() {
		if ( ! function_exists( 'get_plugin_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$outdated = array();
		$updates = get_plugin_updates();

		foreach ( $updates as $plugin_file => $plugin_data ) {
			$outdated[] = array(
				'name'            => $plugin_data->Name,
				'current_version' => $plugin_data->Version,
				'new_version'     => $plugin_data->update->new_version,
			);
		}

		return $outdated;
	}

	private function check_weak_passwords() {
		// This is a simplified check - in practice, you'd want more sophisticated password strength analysis
		$weak_users = array();
		$users = get_users( array( 'fields' => array( 'ID', 'user_login' ) ) );

		foreach ( $users as $user ) {
			// Check if user has a simple password (this is just an example)
			$user_obj = get_user_by( 'id', $user->ID );
			if ( wp_check_password( $user->user_login, $user_obj->user_pass ) ||
				 wp_check_password( '123456', $user_obj->user_pass ) ||
				 wp_check_password( 'password', $user_obj->user_pass ) ) {
				$weak_users[] = $user->ID;
			}
		}

		return $weak_users;
	}

	private function check_file_permissions() {
		$issues = array();
		$files_to_check = array(
			ABSPATH . 'wp-config.php'    => '644',
			ABSPATH . '.htaccess'        => '644',
			ABSPATH . 'index.php'        => '644',
			ABSPATH . 'wp-admin/'        => '755',
			ABSPATH . 'wp-includes/'     => '755',
		);

		foreach ( $files_to_check as $file => $recommended ) {
			if ( file_exists( $file ) ) {
				$current = substr( sprintf( '%o', fileperms( $file ) ), -3 );
				if ( $current !== $recommended ) {
					$issues[] = array(
						'file'        => str_replace( ABSPATH, '', $file ),
						'current'     => $current,
						'recommended' => $recommended,
					);
				}
			}
		}

		return $issues;
	}

	private function check_exposed_files() {
		$exposed = array();
		$sensitive_files = array(
			'wp-config.php',
			'.htaccess',
			'error_log',
			'debug.log',
			'readme.html',
			'license.txt',
		);

		foreach ( $sensitive_files as $file ) {
			$full_path = ABSPATH . $file;
			if ( file_exists( $full_path ) ) {
				$url = home_url( '/' . $file );
				$response = wp_remote_head( $url );
				if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
					$exposed[] = $file;
				}
			}
		}

		return $exposed;
	}

	private function get_client_ip() {
		$ip_keys = array( 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
	}

	private function is_enabled( $option ) {
		return isset( $this->hardening_options[ $option ] ) && $this->hardening_options[ $option ];
	}

	public function add_admin_menu() {
		add_submenu_page(
			'wpmatch-admin',
			__( 'Security Hardening', 'wpmatch' ),
			__( 'Security Hardening', 'wpmatch' ),
			'manage_options',
			'wpmatch-security-hardening',
			array( $this, 'admin_page' )
		);
	}

	public function admin_page() {
		$vulnerabilities = $this->scan_vulnerabilities();
		include_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/wpmatch-admin-security-hardening.php';
	}

	public function ajax_toggle_hardening() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpmatch_security_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$option = sanitize_text_field( wp_unslash( $_POST['option'] ?? '' ) );
		$enabled = isset( $_POST['enabled'] ) && $_POST['enabled'] === 'true';

		if ( ! $option ) {
			wp_send_json_error( array( 'message' => 'Invalid option' ) );
		}

		$this->hardening_options[ $option ] = $enabled;
		update_option( 'wpmatch_security_hardening', $this->hardening_options );

		wp_send_json_success( array(
			'message' => $enabled ?
				__( 'Security measure enabled', 'wpmatch' ) :
				__( 'Security measure disabled', 'wpmatch' ),
		) );
	}

	public function ajax_scan_vulnerabilities() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpmatch_security_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$vulnerabilities = $this->scan_vulnerabilities();

		wp_send_json_success( array(
			'vulnerabilities' => $vulnerabilities,
			'count'           => count( $vulnerabilities ),
		) );
	}
}