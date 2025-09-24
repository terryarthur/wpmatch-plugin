<?php
/**
 * WPMatch Two-Factor Authentication
 *
 * Comprehensive two-factor authentication system with multiple methods
 *
 * @package WPMatch
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPMatch_Two_Factor {

	private static $instance = null;

	const METHOD_EMAIL = 'email';
	const METHOD_SMS = 'sms';
	const METHOD_TOTP = 'totp';
	const METHOD_BACKUP_CODES = 'backup_codes';

	const CODE_LENGTH = 6;
	const CODE_EXPIRY = 300; // 5 minutes

	private $enabled_methods = array();
	private $required_roles = array();

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->init_settings();
		$this->init_hooks();
	}

	private function init_settings() {
		$this->enabled_methods = get_option( 'wpmatch_2fa_enabled_methods', array( self::METHOD_EMAIL ) );
		$this->required_roles = get_option( 'wpmatch_2fa_required_roles', array( 'administrator' ) );
	}

	private function init_hooks() {
		// Authentication hooks
		add_action( 'wp_login', array( $this, 'handle_login' ), 10, 2 );
		add_action( 'init', array( $this, 'handle_2fa_verification' ) );
		add_action( 'wp_logout', array( $this, 'clear_2fa_session' ) );

		// Admin hooks
		add_action( 'show_user_profile', array( $this, 'user_profile_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'user_profile_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_user_profile' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_user_profile' ) );

		// AJAX handlers
		add_action( 'wp_ajax_wpmatch_setup_totp', array( $this, 'ajax_setup_totp' ) );
		add_action( 'wp_ajax_wpmatch_verify_totp_setup', array( $this, 'ajax_verify_totp_setup' ) );
		add_action( 'wp_ajax_wpmatch_generate_backup_codes', array( $this, 'ajax_generate_backup_codes' ) );
		add_action( 'wp_ajax_wpmatch_send_2fa_code', array( $this, 'ajax_send_2fa_code' ) );
		add_action( 'wp_ajax_nopriv_wpmatch_send_2fa_code', array( $this, 'ajax_send_2fa_code' ) );

		// Create database tables
		add_action( 'init', array( $this, 'create_2fa_tables' ) );

		// Enqueue scripts
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_login_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	public function create_2fa_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Two-factor codes table
		$codes_table = $wpdb->prefix . 'wpmatch_2fa_codes';
		$codes_sql = "CREATE TABLE IF NOT EXISTS $codes_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			code varchar(20) NOT NULL,
			method varchar(20) NOT NULL,
			expires_at datetime NOT NULL,
			used_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY code (code),
			KEY expires_at (expires_at)
		) $charset_collate;";

		// User settings table
		$settings_table = $wpdb->prefix . 'wpmatch_2fa_user_settings';
		$settings_sql = "CREATE TABLE IF NOT EXISTS $settings_table (
			user_id bigint(20) NOT NULL,
			method varchar(20) NOT NULL,
			enabled tinyint(1) NOT NULL DEFAULT 0,
			settings longtext DEFAULT NULL,
			backup_codes longtext DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (user_id, method),
			KEY enabled (enabled)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $codes_sql );
		dbDelta( $settings_sql );
	}

	public function handle_login( $user_login, $user ) {
		if ( ! $this->is_2fa_required_for_user( $user ) ) {
			return;
		}

		// Set 2FA session flag
		$this->set_2fa_session( $user->ID );

		// Log out the user immediately
		wp_logout();

		// Redirect to 2FA verification page
		$redirect_url = add_query_arg( array(
			'wpmatch_2fa' => 'verify',
			'user_id'     => $user->ID,
			'nonce'       => wp_create_nonce( 'wpmatch_2fa_verify_' . $user->ID ),
		), wp_login_url() );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	public function handle_2fa_verification() {
		if ( ! isset( $_GET['wpmatch_2fa'] ) || 'verify' !== $_GET['wpmatch_2fa'] ) {
			return;
		}

		$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';

		if ( ! $user_id || ! wp_verify_nonce( $nonce, 'wpmatch_2fa_verify_' . $user_id ) ) {
			wp_die( esc_html__( 'Invalid verification request.', 'wpmatch' ) );
		}

		if ( ! $this->has_2fa_session( $user_id ) ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		$this->show_2fa_verification_form( $user_id );
	}

	private function show_2fa_verification_form( $user_id ) {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			wp_die( esc_html__( 'Invalid user.', 'wpmatch' ) );
		}

		$available_methods = $this->get_user_2fa_methods( $user_id );
		$preferred_method = $this->get_user_preferred_method( $user_id );

		// Send code for email method
		if ( self::METHOD_EMAIL === $preferred_method ) {
			$this->send_email_code( $user );
		}

		include_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/partials/wpmatch-2fa-verification.php';
		exit;
	}

	public function verify_2fa_code( $user_id, $code, $method = null ) {
		global $wpdb;

		if ( ! $method ) {
			$method = $this->get_user_preferred_method( $user_id );
		}

		// Handle backup codes
		if ( self::METHOD_BACKUP_CODES === $method ) {
			return $this->verify_backup_code( $user_id, $code );
		}

		// Handle TOTP
		if ( self::METHOD_TOTP === $method ) {
			return $this->verify_totp_code( $user_id, $code );
		}

		// Handle email/SMS codes
		$stored_code = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}wpmatch_2fa_codes
			WHERE user_id = %d AND method = %s AND code = %s
			AND expires_at > NOW() AND used_at IS NULL
			ORDER BY created_at DESC LIMIT 1",
			$user_id,
			$method,
			$code
		) );

		if ( ! $stored_code ) {
			return false;
		}

		// Mark code as used
		$wpdb->update(
			$wpdb->prefix . 'wpmatch_2fa_codes',
			array( 'used_at' => current_time( 'mysql' ) ),
			array( 'id' => $stored_code->id ),
			array( '%s' ),
			array( '%d' )
		);

		return true;
	}

	private function verify_backup_code( $user_id, $code ) {
		$backup_codes = $this->get_user_backup_codes( $user_id );

		if ( ! $backup_codes || ! in_array( $code, $backup_codes, true ) ) {
			return false;
		}

		// Remove used backup code
		$remaining_codes = array_diff( $backup_codes, array( $code ) );
		$this->save_user_backup_codes( $user_id, $remaining_codes );

		return true;
	}

	private function verify_totp_code( $user_id, $code ) {
		$secret = $this->get_user_totp_secret( $user_id );

		if ( ! $secret ) {
			return false;
		}

		return $this->verify_totp( $secret, $code );
	}

	public function complete_2fa_login( $user_id ) {
		if ( ! $this->has_2fa_session( $user_id ) ) {
			return false;
		}

		// Clear 2FA session
		$this->clear_2fa_session( $user_id );

		// Log the user in
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}

		wp_set_current_user( $user_id, $user->user_login );
		wp_set_auth_cookie( $user_id, true );
		do_action( 'wp_login', $user->user_login, $user );

		// Log security event
		$security_manager = WPMatch_Security_Manager::get_instance();
		$security_manager->log_security_event( 'two_factor_login_success', array(
			'user_id' => $user_id,
			'method'  => $this->get_user_preferred_method( $user_id ),
		) );

		return true;
	}

	public function send_email_code( $user ) {
		$code = $this->generate_verification_code();
		$this->store_verification_code( $user->ID, $code, self::METHOD_EMAIL );

		$subject = sprintf( __( '[%s] Two-Factor Authentication Code', 'wpmatch' ), get_bloginfo( 'name' ) );
		$message = $this->get_email_template( $user, $code );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
		);

		return wp_mail( $user->user_email, $subject, $message, $headers );
	}

	private function get_email_template( $user, $code ) {
		$site_name = get_bloginfo( 'name' );
		$site_url = get_site_url();

		ob_start();
		?>
		<html>
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php esc_html_e( 'Two-Factor Authentication Code', 'wpmatch' ); ?></title>
		</head>
		<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
			<div style="background: #0073aa; color: white; padding: 20px; border-radius: 5px 5px 0 0; text-align: center;">
				<h1 style="margin: 0; font-size: 24px;"><?php esc_html_e( 'Two-Factor Authentication', 'wpmatch' ); ?></h1>
			</div>

			<div style="background: #f9f9f9; padding: 30px; border: 1px solid #ddd; border-top: none;">
				<p><?php echo esc_html( sprintf( __( 'Hello %s,', 'wpmatch' ), $user->display_name ) ); ?></p>

				<p><?php esc_html_e( 'A login attempt was made to your account. To complete the login process, please use the verification code below:', 'wpmatch' ); ?></p>

				<div style="background: white; border: 2px solid #0073aa; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0;">
					<h2 style="margin: 0; font-size: 32px; letter-spacing: 8px; color: #0073aa; font-family: 'Courier New', monospace;">
						<?php echo esc_html( $code ); ?>
					</h2>
				</div>

				<p><strong><?php esc_html_e( 'This code will expire in 5 minutes.', 'wpmatch' ); ?></strong></p>

				<p><?php esc_html_e( 'If you did not attempt to log in, please ignore this email and consider changing your password.', 'wpmatch' ); ?></p>

				<hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">

				<p style="font-size: 12px; color: #666;">
					<?php echo esc_html( sprintf( __( 'This email was sent from %s (%s)', 'wpmatch' ), $site_name, $site_url ) ); ?>
				</p>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	public function generate_verification_code() {
		return sprintf( '%0' . self::CODE_LENGTH . 'd', wp_rand( 0, pow( 10, self::CODE_LENGTH ) - 1 ) );
	}

	private function store_verification_code( $user_id, $code, $method ) {
		global $wpdb;

		$expires_at = gmdate( 'Y-m-d H:i:s', time() + self::CODE_EXPIRY );

		return $wpdb->insert(
			$wpdb->prefix . 'wpmatch_2fa_codes',
			array(
				'user_id'    => $user_id,
				'code'       => $code,
				'method'     => $method,
				'expires_at' => $expires_at,
			),
			array( '%d', '%s', '%s', '%s' )
		);
	}

	public function setup_totp_for_user( $user_id ) {
		$secret = $this->generate_totp_secret();
		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return false;
		}

		// Store temporary secret for verification
		update_user_meta( $user_id, '_wpmatch_totp_secret_temp', $secret );

		return array(
			'secret'   => $secret,
			'qr_code'  => $this->get_totp_qr_code_url( $user->user_email, $secret ),
			'manual'   => $this->format_secret_for_manual_entry( $secret ),
		);
	}

	public function verify_and_enable_totp( $user_id, $code ) {
		$temp_secret = get_user_meta( $user_id, '_wpmatch_totp_secret_temp', true );

		if ( ! $temp_secret || ! $this->verify_totp( $temp_secret, $code ) ) {
			return false;
		}

		// Save the secret permanently
		$this->save_user_2fa_method( $user_id, self::METHOD_TOTP, array(
			'secret' => $temp_secret,
		) );

		// Clean up temporary secret
		delete_user_meta( $user_id, '_wpmatch_totp_secret_temp' );

		return true;
	}

	private function generate_totp_secret() {
		$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$secret = '';

		for ( $i = 0; $i < 32; $i++ ) {
			$secret .= $chars[ wp_rand( 0, strlen( $chars ) - 1 ) ];
		}

		return $secret;
	}

	private function get_totp_qr_code_url( $email, $secret ) {
		$issuer = get_bloginfo( 'name' );
		$label = rawurlencode( $issuer . ':' . $email );
		$qr_data = sprintf(
			'otpauth://totp/%s?secret=%s&issuer=%s',
			$label,
			$secret,
			rawurlencode( $issuer )
		);

		return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode( $qr_data );
	}

	private function format_secret_for_manual_entry( $secret ) {
		return implode( ' ', str_split( $secret, 4 ) );
	}

	private function verify_totp( $secret, $code ) {
		$time_window = floor( time() / 30 );

		// Check current window and previous/next windows for clock drift
		for ( $i = -1; $i <= 1; $i++ ) {
			$calculated_code = $this->calculate_totp( $secret, $time_window + $i );
			if ( hash_equals( $calculated_code, $code ) ) {
				return true;
			}
		}

		return false;
	}

	private function calculate_totp( $secret, $time_window ) {
		$secret_bytes = $this->base32_decode( $secret );
		$time_bytes = pack( 'N*', 0 ) . pack( 'N*', $time_window );

		$hash = hash_hmac( 'sha1', $time_bytes, $secret_bytes, true );
		$offset = ord( $hash[19] ) & 0xf;

		$code = (
			( ( ord( $hash[ $offset + 0 ] ) & 0x7f ) << 24 ) |
			( ( ord( $hash[ $offset + 1 ] ) & 0xff ) << 16 ) |
			( ( ord( $hash[ $offset + 2 ] ) & 0xff ) << 8 ) |
			( ord( $hash[ $offset + 3 ] ) & 0xff )
		) % pow( 10, 6 );

		return sprintf( '%06d', $code );
	}

	private function base32_decode( $input ) {
		$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$output = '';
		$v = 0;
		$vbits = 0;

		for ( $i = 0, $j = strlen( $input ); $i < $j; $i++ ) {
			$v <<= 5;
			if ( ( $x = strpos( $alphabet, $input[ $i ] ) ) !== false ) {
				$v += $x;
				$vbits += 5;
				if ( $vbits >= 8 ) {
					$output .= chr( ( $v >> ( $vbits - 8 ) ) & 255 );
					$vbits -= 8;
				}
			}
		}

		return $output;
	}

	public function generate_backup_codes( $user_id, $count = 10 ) {
		$codes = array();

		for ( $i = 0; $i < $count; $i++ ) {
			$codes[] = sprintf( '%s-%s',
				strtolower( wp_generate_password( 4, false, false ) ),
				strtolower( wp_generate_password( 4, false, false ) )
			);
		}

		$this->save_user_backup_codes( $user_id, $codes );

		return $codes;
	}

	private function save_user_backup_codes( $user_id, $codes ) {
		$this->save_user_2fa_method( $user_id, self::METHOD_BACKUP_CODES, array(
			'codes' => $codes,
		) );
	}

	private function get_user_backup_codes( $user_id ) {
		$settings = $this->get_user_2fa_settings( $user_id, self::METHOD_BACKUP_CODES );
		return isset( $settings['codes'] ) ? $settings['codes'] : array();
	}

	private function get_user_totp_secret( $user_id ) {
		$settings = $this->get_user_2fa_settings( $user_id, self::METHOD_TOTP );
		return isset( $settings['secret'] ) ? $settings['secret'] : null;
	}

	private function save_user_2fa_method( $user_id, $method, $settings ) {
		global $wpdb;

		$wpdb->replace(
			$wpdb->prefix . 'wpmatch_2fa_user_settings',
			array(
				'user_id'  => $user_id,
				'method'   => $method,
				'enabled'  => 1,
				'settings' => wp_json_encode( $settings ),
			),
			array( '%d', '%s', '%d', '%s' )
		);
	}

	private function get_user_2fa_settings( $user_id, $method ) {
		global $wpdb;

		$result = $wpdb->get_var( $wpdb->prepare(
			"SELECT settings FROM {$wpdb->prefix}wpmatch_2fa_user_settings
			WHERE user_id = %d AND method = %s AND enabled = 1",
			$user_id,
			$method
		) );

		return $result ? json_decode( $result, true ) : array();
	}

	public function get_user_2fa_methods( $user_id ) {
		global $wpdb;

		$methods = $wpdb->get_col( $wpdb->prepare(
			"SELECT method FROM {$wpdb->prefix}wpmatch_2fa_user_settings
			WHERE user_id = %d AND enabled = 1",
			$user_id
		) );

		return $methods ? $methods : array();
	}

	private function get_user_preferred_method( $user_id ) {
		$methods = $this->get_user_2fa_methods( $user_id );

		if ( empty( $methods ) ) {
			return self::METHOD_EMAIL;
		}

		// Prefer TOTP, then email, then others
		$priority = array( self::METHOD_TOTP, self::METHOD_EMAIL, self::METHOD_SMS, self::METHOD_BACKUP_CODES );

		foreach ( $priority as $method ) {
			if ( in_array( $method, $methods, true ) ) {
				return $method;
			}
		}

		return $methods[0];
	}

	private function is_2fa_required_for_user( $user ) {
		if ( ! $user ) {
			return false;
		}

		// Check if 2FA is required for any of the user's roles
		$user_roles = $user->roles;
		return ! empty( array_intersect( $user_roles, $this->required_roles ) );
	}

	private function set_2fa_session( $user_id ) {
		set_transient( 'wpmatch_2fa_session_' . $user_id, true, 900 ); // 15 minutes
	}

	private function has_2fa_session( $user_id ) {
		return get_transient( 'wpmatch_2fa_session_' . $user_id );
	}

	public function clear_2fa_session( $user_id = null ) {
		if ( $user_id ) {
			delete_transient( 'wpmatch_2fa_session_' . $user_id );
		} else {
			$current_user_id = get_current_user_id();
			if ( $current_user_id ) {
				delete_transient( 'wpmatch_2fa_session_' . $current_user_id );
			}
		}
	}

	public function user_profile_fields( $user ) {
		if ( ! $this->is_2fa_required_for_user( $user ) ) {
			return;
		}

		$enabled_methods = $this->get_user_2fa_methods( $user->ID );
		$backup_codes = $this->get_user_backup_codes( $user->ID );

		include_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/wpmatch-2fa-user-profile.php';
	}

	public function save_user_profile( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		// Handle method disabling
		if ( isset( $_POST['wpmatch_2fa_disable_method'] ) && isset( $_POST['wpmatch_2fa_nonce'] ) ) {
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpmatch_2fa_nonce'] ) ), 'wpmatch_2fa_profile' ) ) {
				$method_to_disable = sanitize_text_field( wp_unslash( $_POST['wpmatch_2fa_disable_method'] ) );
				$this->disable_user_2fa_method( $user_id, $method_to_disable );
			}
		}
	}

	private function disable_user_2fa_method( $user_id, $method ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'wpmatch_2fa_user_settings',
			array( 'enabled' => 0 ),
			array(
				'user_id' => $user_id,
				'method'  => $method,
			),
			array( '%d' ),
			array( '%d', '%s' )
		);
	}

	public function enqueue_login_scripts() {
		if ( isset( $_GET['wpmatch_2fa'] ) ) {
			wp_enqueue_script( 'wpmatch-2fa-login', plugins_url( 'public/js/wpmatch-2fa-login.js', dirname( __FILE__ ) ), array( 'jquery' ), '1.0.0', true );
			wp_localize_script( 'wpmatch-2fa-login', 'wpMatch2FA', array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wpmatch_2fa_nonce' ),
				'strings' => array(
					'sending'    => __( 'Sending...', 'wpmatch' ),
					'codeSent'   => __( 'Verification code sent!', 'wpmatch' ),
					'sendFailed' => __( 'Failed to send code. Please try again.', 'wpmatch' ),
				),
			) );
		}
	}

	public function enqueue_admin_scripts( $hook ) {
		if ( 'profile.php' === $hook || 'user-edit.php' === $hook ) {
			wp_enqueue_script( 'wpmatch-2fa-admin', plugins_url( 'admin/js/wpmatch-2fa-admin.js', dirname( __FILE__ ) ), array( 'jquery' ), '1.0.0', true );
			wp_localize_script( 'wpmatch-2fa-admin', 'wpMatch2FA', array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wpmatch_2fa_nonce' ),
				'strings' => array(
					'setupTotp'       => __( 'Setting up TOTP...', 'wpmatch' ),
					'verifyingCode'   => __( 'Verifying code...', 'wpmatch' ),
					'totpEnabled'     => __( 'TOTP authentication enabled successfully!', 'wpmatch' ),
					'invalidCode'     => __( 'Invalid verification code. Please try again.', 'wpmatch' ),
					'generatingCodes' => __( 'Generating backup codes...', 'wpmatch' ),
					'codesGenerated'  => __( 'Backup codes generated successfully!', 'wpmatch' ),
				),
			) );
		}
	}

	// AJAX handlers
	public function ajax_setup_totp() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpmatch_2fa_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => 'User not logged in' ) );
		}

		$setup_data = $this->setup_totp_for_user( $user_id );

		if ( $setup_data ) {
			wp_send_json_success( $setup_data );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to setup TOTP' ) );
		}
	}

	public function ajax_verify_totp_setup() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpmatch_2fa_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		$user_id = get_current_user_id();
		$code = sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) );

		if ( ! $user_id || ! $code ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters' ) );
		}

		if ( $this->verify_and_enable_totp( $user_id, $code ) ) {
			wp_send_json_success( array( 'message' => 'TOTP enabled successfully' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Invalid verification code' ) );
		}
	}

	public function ajax_generate_backup_codes() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpmatch_2fa_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => 'User not logged in' ) );
		}

		$codes = $this->generate_backup_codes( $user_id );
		wp_send_json_success( array( 'codes' => $codes ) );
	}

	public function ajax_send_2fa_code() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpmatch_2fa_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		$user_id = absint( $_POST['user_id'] ?? 0 );
		$method = sanitize_text_field( wp_unslash( $_POST['method'] ?? self::METHOD_EMAIL ) );

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => 'Invalid user' ) );
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => 'User not found' ) );
		}

		if ( self::METHOD_EMAIL === $method ) {
			$result = $this->send_email_code( $user );
			if ( $result ) {
				wp_send_json_success( array( 'message' => 'Verification code sent to your email' ) );
			} else {
				wp_send_json_error( array( 'message' => 'Failed to send email' ) );
			}
		} else {
			wp_send_json_error( array( 'message' => 'Unsupported method' ) );
		}
	}
}