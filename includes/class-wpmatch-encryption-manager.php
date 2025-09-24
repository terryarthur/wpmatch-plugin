<?php
/**
 * WPMatch Encryption Manager
 *
 * Handles data encryption, privacy controls, and secure data storage.
 *
 * @package WPMatch
 * @subpackage Security
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPMatch Encryption Manager class.
 *
 * @since 1.0.0
 */
class WPMatch_Encryption_Manager {

	/**
	 * Encryption method.
	 */
	const ENCRYPTION_METHOD = 'AES-256-GCM';

	/**
	 * Key derivation method.
	 */
	const KEY_DERIVATION_METHOD = 'PBKDF2';

	/**
	 * Hash algorithm.
	 */
	const HASH_ALGORITHM = 'sha256';

	/**
	 * Salt length.
	 */
	const SALT_LENGTH = 32;

	/**
	 * IV length.
	 */
	const IV_LENGTH = 12;

	/**
	 * Tag length.
	 */
	const TAG_LENGTH = 16;

	/**
	 * Key iteration count.
	 */
	const KEY_ITERATIONS = 10000;

	/**
	 * Instance of the class.
	 *
	 * @var WPMatch_Encryption_Manager
	 */
	private static $instance = null;

	/**
	 * Master encryption key.
	 *
	 * @var string
	 */
	private $master_key;

	/**
	 * Encryption keys cache.
	 *
	 * @var array
	 */
	private $key_cache = array();

	/**
	 * Get the single instance.
	 *
	 * @return WPMatch_Encryption_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_encryption();
		$this->init_hooks();
	}

	/**
	 * Initialize encryption system.
	 */
	private function init_encryption() {
		// Load or generate master key.
		$this->load_master_key();

		// Verify encryption capability.
		if ( ! $this->is_encryption_available() ) {
			add_action( 'admin_notices', array( $this, 'encryption_unavailable_notice' ) );
		}
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Encrypt sensitive data on save.
		add_filter( 'wpmatch_before_save_profile', array( $this, 'encrypt_profile_data' ) );
		add_filter( 'wpmatch_before_save_message', array( $this, 'encrypt_message_data' ) );
		add_filter( 'wpmatch_before_save_user_data', array( $this, 'encrypt_sensitive_user_data' ) );

		// Decrypt sensitive data on load.
		add_filter( 'wpmatch_after_load_profile', array( $this, 'decrypt_profile_data' ) );
		add_filter( 'wpmatch_after_load_message', array( $this, 'decrypt_message_data' ) );
		add_filter( 'wpmatch_after_load_user_data', array( $this, 'decrypt_sensitive_user_data' ) );

		// Privacy controls.
		add_action( 'wpmatch_user_privacy_settings_update', array( $this, 'update_user_privacy_settings' ) );
	}

	/**
	 * Check if encryption is available.
	 *
	 * @return bool True if encryption is available.
	 */
	public function is_encryption_available() {
		return extension_loaded( 'openssl' ) && in_array( self::ENCRYPTION_METHOD, openssl_get_cipher_methods(), true );
	}

	/**
	 * Load or generate master encryption key.
	 */
	private function load_master_key() {
		$key = get_option( 'wpmatch_master_encryption_key' );

		if ( empty( $key ) ) {
			// Generate new master key.
			$this->master_key = $this->generate_secure_key( 32 );
			update_option( 'wpmatch_master_encryption_key', base64_encode( $this->master_key ) );
		} else {
			$this->master_key = base64_decode( $key );
		}

		// Validate key length.
		if ( strlen( $this->master_key ) !== 32 ) {
			wp_die( 'Invalid encryption key. Please contact administrator.' );
		}
	}

	/**
	 * Generate a secure random key.
	 *
	 * @param int $length Key length in bytes.
	 * @return string Random key.
	 */
	private function generate_secure_key( $length ) {
		if ( function_exists( 'random_bytes' ) ) {
			return random_bytes( $length );
		} elseif ( function_exists( 'openssl_random_pseudo_bytes' ) ) {
			return openssl_random_pseudo_bytes( $length );
		} else {
			// Fallback (less secure).
			$key = '';
			for ( $i = 0; $i < $length; $i++ ) {
				$key .= chr( wp_rand( 0, 255 ) );
			}
			return $key;
		}
	}

	/**
	 * Derive encryption key for specific purpose.
	 *
	 * @param string $purpose Key purpose/context.
	 * @param string $salt Optional salt.
	 * @return string Derived key.
	 */
	private function derive_key( $purpose, $salt = '' ) {
		$cache_key = md5( $purpose . $salt );

		if ( isset( $this->key_cache[ $cache_key ] ) ) {
			return $this->key_cache[ $cache_key ];
		}

		if ( empty( $salt ) ) {
			$salt = hash( self::HASH_ALGORITHM, $purpose . 'wpmatch_salt', true );
		}

		$derived_key = hash_pbkdf2( self::HASH_ALGORITHM, $this->master_key, $salt, self::KEY_ITERATIONS, 32, true );

		$this->key_cache[ $cache_key ] = $derived_key;

		return $derived_key;
	}

	/**
	 * Encrypt data.
	 *
	 * @param string $data Data to encrypt.
	 * @param string $purpose Encryption purpose.
	 * @return string|false Encrypted data or false on failure.
	 */
	public function encrypt( $data, $purpose = 'general' ) {
		if ( ! $this->is_encryption_available() || empty( $data ) ) {
			return $data;
		}

		try {
			// Generate salt and IV.
			$salt = $this->generate_secure_key( self::SALT_LENGTH );
			$iv   = $this->generate_secure_key( self::IV_LENGTH );

			// Derive encryption key.
			$key = $this->derive_key( $purpose, $salt );

			// Encrypt data.
			$encrypted = openssl_encrypt( $data, self::ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv, $tag );

			if ( false === $encrypted ) {
				return false;
			}

			// Combine salt, IV, tag, and encrypted data.
			$result = base64_encode( $salt . $iv . $tag . $encrypted );

			return $result;

		} catch ( Exception $e ) {
			error_log( 'WPMatch Encryption Error: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Decrypt data.
	 *
	 * @param string $encrypted_data Encrypted data.
	 * @param string $purpose Encryption purpose.
	 * @return string|false Decrypted data or false on failure.
	 */
	public function decrypt( $encrypted_data, $purpose = 'general' ) {
		if ( ! $this->is_encryption_available() || empty( $encrypted_data ) ) {
			return $encrypted_data;
		}

		try {
			// Decode base64.
			$data = base64_decode( $encrypted_data );

			if ( false === $data || strlen( $data ) < ( self::SALT_LENGTH + self::IV_LENGTH + self::TAG_LENGTH ) ) {
				return false;
			}

			// Extract components.
			$salt      = substr( $data, 0, self::SALT_LENGTH );
			$iv        = substr( $data, self::SALT_LENGTH, self::IV_LENGTH );
			$tag       = substr( $data, self::SALT_LENGTH + self::IV_LENGTH, self::TAG_LENGTH );
			$encrypted = substr( $data, self::SALT_LENGTH + self::IV_LENGTH + self::TAG_LENGTH );

			// Derive decryption key.
			$key = $this->derive_key( $purpose, $salt );

			// Decrypt data.
			$decrypted = openssl_decrypt( $encrypted, self::ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv, $tag );

			if ( false === $decrypted ) {
				return false;
			}

			return $decrypted;

		} catch ( Exception $e ) {
			error_log( 'WPMatch Decryption Error: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Hash sensitive data for comparison.
	 *
	 * @param string $data Data to hash.
	 * @param string $salt Optional salt.
	 * @return string Hashed data.
	 */
	public function hash_data( $data, $salt = '' ) {
		if ( empty( $salt ) ) {
			$salt = $this->generate_secure_key( 16 );
		}

		$hash = hash_pbkdf2( self::HASH_ALGORITHM, $data, $salt, self::KEY_ITERATIONS, 64 );

		return base64_encode( $salt . hex2bin( $hash ) );
	}

	/**
	 * Verify hashed data.
	 *
	 * @param string $data Original data.
	 * @param string $hash Hashed data.
	 * @return bool True if verification succeeds.
	 */
	public function verify_hash( $data, $hash ) {
		$decoded = base64_decode( $hash );

		if ( strlen( $decoded ) < 16 ) {
			return false;
		}

		$salt        = substr( $decoded, 0, 16 );
		$stored_hash = substr( $decoded, 16 );

		$computed_hash = hex2bin( hash_pbkdf2( self::HASH_ALGORITHM, $data, $salt, self::KEY_ITERATIONS, 64 ) );

		return hash_equals( $stored_hash, $computed_hash );
	}

	/**
	 * Encrypt profile data.
	 *
	 * @param array $profile_data Profile data.
	 * @return array Encrypted profile data.
	 */
	public function encrypt_profile_data( $profile_data ) {
		$sensitive_fields = array(
			'about_me',
			'looking_for',
			'phone_number',
			'email_private',
			'social_media_profiles',
		);

		foreach ( $sensitive_fields as $field ) {
			if ( isset( $profile_data[ $field ] ) && ! empty( $profile_data[ $field ] ) ) {
				$encrypted = $this->encrypt( $profile_data[ $field ], 'profile_' . $field );
				if ( false !== $encrypted ) {
					$profile_data[ $field ]                = $encrypted;
					$profile_data[ $field . '_encrypted' ] = true;
				}
			}
		}

		return $profile_data;
	}

	/**
	 * Decrypt profile data.
	 *
	 * @param array $profile_data Profile data.
	 * @return array Decrypted profile data.
	 */
	public function decrypt_profile_data( $profile_data ) {
		$sensitive_fields = array(
			'about_me',
			'looking_for',
			'phone_number',
			'email_private',
			'social_media_profiles',
		);

		foreach ( $sensitive_fields as $field ) {
			if ( isset( $profile_data[ $field . '_encrypted' ] ) && $profile_data[ $field . '_encrypted' ] ) {
				$decrypted = $this->decrypt( $profile_data[ $field ], 'profile_' . $field );
				if ( false !== $decrypted ) {
					$profile_data[ $field ] = $decrypted;
				}
				unset( $profile_data[ $field . '_encrypted' ] );
			}
		}

		return $profile_data;
	}

	/**
	 * Encrypt message data.
	 *
	 * @param array $message_data Message data.
	 * @return array Encrypted message data.
	 */
	public function encrypt_message_data( $message_data ) {
		if ( isset( $message_data['message_content'] ) ) {
			$encrypted = $this->encrypt( $message_data['message_content'], 'message_content' );
			if ( false !== $encrypted ) {
				$message_data['message_content']   = $encrypted;
				$message_data['content_encrypted'] = true;
			}
		}

		if ( isset( $message_data['attachment_data'] ) ) {
			$encrypted = $this->encrypt( $message_data['attachment_data'], 'message_attachment' );
			if ( false !== $encrypted ) {
				$message_data['attachment_data']      = $encrypted;
				$message_data['attachment_encrypted'] = true;
			}
		}

		return $message_data;
	}

	/**
	 * Decrypt message data.
	 *
	 * @param array $message_data Message data.
	 * @return array Decrypted message data.
	 */
	public function decrypt_message_data( $message_data ) {
		if ( isset( $message_data['content_encrypted'] ) && $message_data['content_encrypted'] ) {
			$decrypted = $this->decrypt( $message_data['message_content'], 'message_content' );
			if ( false !== $decrypted ) {
				$message_data['message_content'] = $decrypted;
			}
			unset( $message_data['content_encrypted'] );
		}

		if ( isset( $message_data['attachment_encrypted'] ) && $message_data['attachment_encrypted'] ) {
			$decrypted = $this->decrypt( $message_data['attachment_data'], 'message_attachment' );
			if ( false !== $decrypted ) {
				$message_data['attachment_data'] = $decrypted;
			}
			unset( $message_data['attachment_encrypted'] );
		}

		return $message_data;
	}

	/**
	 * Encrypt sensitive user data.
	 *
	 * @param array $user_data User data.
	 * @return array Encrypted user data.
	 */
	public function encrypt_sensitive_user_data( $user_data ) {
		$sensitive_fields = array(
			'ip_address',
			'device_fingerprint',
			'location_data',
			'payment_info',
		);

		foreach ( $sensitive_fields as $field ) {
			if ( isset( $user_data[ $field ] ) && ! empty( $user_data[ $field ] ) ) {
				$encrypted = $this->encrypt( $user_data[ $field ], 'user_' . $field );
				if ( false !== $encrypted ) {
					$user_data[ $field ]                = $encrypted;
					$user_data[ $field . '_encrypted' ] = true;
				}
			}
		}

		return $user_data;
	}

	/**
	 * Decrypt sensitive user data.
	 *
	 * @param array $user_data User data.
	 * @return array Decrypted user data.
	 */
	public function decrypt_sensitive_user_data( $user_data ) {
		$sensitive_fields = array(
			'ip_address',
			'device_fingerprint',
			'location_data',
			'payment_info',
		);

		foreach ( $sensitive_fields as $field ) {
			if ( isset( $user_data[ $field . '_encrypted' ] ) && $user_data[ $field . '_encrypted' ] ) {
				$decrypted = $this->decrypt( $user_data[ $field ], 'user_' . $field );
				if ( false !== $decrypted ) {
					$user_data[ $field ] = $decrypted;
				}
				unset( $user_data[ $field . '_encrypted' ] );
			}
		}

		return $user_data;
	}

	/**
	 * Encrypt file contents.
	 *
	 * @param string $file_path File path.
	 * @param string $purpose Encryption purpose.
	 * @return bool True on success.
	 */
	public function encrypt_file( $file_path, $purpose = 'file' ) {
		if ( ! file_exists( $file_path ) ) {
			return false;
		}

		$content = file_get_contents( $file_path );
		if ( false === $content ) {
			return false;
		}

		$encrypted = $this->encrypt( $content, $purpose );
		if ( false === $encrypted ) {
			return false;
		}

		return false !== file_put_contents( $file_path, $encrypted );
	}

	/**
	 * Decrypt file contents.
	 *
	 * @param string $file_path File path.
	 * @param string $purpose Encryption purpose.
	 * @return bool True on success.
	 */
	public function decrypt_file( $file_path, $purpose = 'file' ) {
		if ( ! file_exists( $file_path ) ) {
			return false;
		}

		$encrypted_content = file_get_contents( $file_path );
		if ( false === $encrypted_content ) {
			return false;
		}

		$decrypted = $this->decrypt( $encrypted_content, $purpose );
		if ( false === $decrypted ) {
			return false;
		}

		return false !== file_put_contents( $file_path, $decrypted );
	}

	/**
	 * Anonymize user data.
	 *
	 * @param int $user_id User ID.
	 * @return bool True on success.
	 */
	public function anonymize_user_data( $user_id ) {
		global $wpdb;

		// Anonymize profile data.
		$anonymized_data = array(
			'about_me'    => '[ANONYMIZED]',
			'looking_for' => '[ANONYMIZED]',
			'location'    => 'Unknown',
			'latitude'    => null,
			'longitude'   => null,
		);

		$updated = $wpdb->update(
			$wpdb->prefix . 'wpmatch_user_profiles',
			$anonymized_data,
			array( 'user_id' => $user_id ),
			array( '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		// Anonymize messages.
		$wpdb->update(
			$wpdb->prefix . 'wpmatch_messages',
			array( 'message_content' => '[ANONYMIZED MESSAGE]' ),
			array( 'sender_id' => $user_id ),
			array( '%s' ),
			array( '%d' )
		);

		// Log anonymization.
		$this->log_data_operation(
			$user_id,
			'anonymize',
			array(
				'fields_anonymized' => array_keys( $anonymized_data ),
				'timestamp'         => current_time( 'mysql' ),
			)
		);

		return false !== $updated;
	}

	/**
	 * Update user privacy settings.
	 *
	 * @param int   $user_id User ID.
	 * @param array $privacy_settings Privacy settings.
	 */
	public function update_user_privacy_settings( $user_id, $privacy_settings ) {
		$default_settings = array(
			'profile_visibility'      => 'public',
			'show_age'                => true,
			'show_location'           => true,
			'allow_messages'          => true,
			'show_online_status'      => true,
			'data_processing_consent' => false,
			'marketing_consent'       => false,
			'analytics_consent'       => false,
		);

		$settings = wp_parse_args( $privacy_settings, $default_settings );

		// Encrypt privacy settings.
		$encrypted_settings = $this->encrypt( wp_json_encode( $settings ), 'privacy_settings' );

		if ( false !== $encrypted_settings ) {
			update_user_meta( $user_id, 'wpmatch_privacy_settings_encrypted', $encrypted_settings );
			update_user_meta( $user_id, 'wpmatch_privacy_settings_hash', $this->hash_data( wp_json_encode( $settings ) ) );
		}

		// Log privacy update.
		$this->log_data_operation(
			$user_id,
			'privacy_update',
			array(
				'settings_updated' => array_keys( $settings ),
				'timestamp'        => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Get user privacy settings.
	 *
	 * @param int $user_id User ID.
	 * @return array Privacy settings.
	 */
	public function get_user_privacy_settings( $user_id ) {
		$encrypted_settings = get_user_meta( $user_id, 'wpmatch_privacy_settings_encrypted', true );

		if ( empty( $encrypted_settings ) ) {
			return array();
		}

		$decrypted = $this->decrypt( $encrypted_settings, 'privacy_settings' );

		if ( false === $decrypted ) {
			return array();
		}

		$settings = json_decode( $decrypted, true );

		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Generate secure token for password reset, email verification, etc.
	 *
	 * @param int $length Token length.
	 * @return string Secure token.
	 */
	public function generate_secure_token( $length = 32 ) {
		$bytes = $this->generate_secure_key( $length );
		return bin2hex( $bytes );
	}

	/**
	 * Create data breach response plan.
	 *
	 * @param array $breach_data Breach information.
	 * @return bool True if response initiated.
	 */
	public function initiate_breach_response( $breach_data ) {
		$response_plan = array(
			'breach_id'      => uniqid( 'breach_', true ),
			'detected_at'    => current_time( 'mysql' ),
			'breach_type'    => $breach_data['type'] ?? 'unknown',
			'affected_users' => $breach_data['affected_users'] ?? array(),
			'data_types'     => $breach_data['data_types'] ?? array(),
			'severity'       => $breach_data['severity'] ?? 'medium',
			'status'         => 'investigating',
		);

		// Log breach incident.
		update_option( 'wpmatch_security_breach_' . $response_plan['breach_id'], $response_plan );

		// Notify administrators immediately.
		$this->send_breach_notification( $response_plan );

		// Start automated response.
		$this->execute_automated_breach_response( $response_plan );

		return true;
	}

	/**
	 * Send breach notification.
	 *
	 * @param array $breach_data Breach data.
	 */
	private function send_breach_notification( $breach_data ) {
		$admin_email = get_option( 'admin_email' );

		$subject = sprintf(
			/* translators: %s: Site name */
			__( '[URGENT] Security Breach Detected - %s', 'wpmatch' ),
			get_bloginfo( 'name' )
		);

		$message = sprintf(
			/* translators: 1: Breach ID, 2: Breach type, 3: Severity, 4: Detection time */
			__( "A security breach has been detected on your WPMatch installation.\n\nBreach ID: %1\$s\nBreach Type: %2\$s\nSeverity: %3\$s\nDetected At: %4\$s\n\nImmediate action is required. Please log into your admin panel to review the incident.", 'wpmatch' ),
			$breach_data['breach_id'],
			$breach_data['breach_type'],
			$breach_data['severity'],
			$breach_data['detected_at']
		);

		wp_mail( $admin_email, $subject, $message );
	}

	/**
	 * Execute automated breach response.
	 *
	 * @param array $breach_data Breach data.
	 */
	private function execute_automated_breach_response( $breach_data ) {
		// Lock affected user accounts.
		if ( ! empty( $breach_data['affected_users'] ) ) {
			foreach ( $breach_data['affected_users'] as $user_id ) {
				update_user_meta( $user_id, 'wpmatch_account_locked_breach', $breach_data['breach_id'] );
			}
		}

		// Force password reset for all users if critical breach.
		if ( 'critical' === $breach_data['severity'] ) {
			$this->force_global_password_reset( $breach_data['breach_id'] );
		}

		// Invalidate all sessions.
		$this->invalidate_all_sessions();

		// Generate new encryption keys if data encryption was compromised.
		if ( in_array( 'encryption', $breach_data['data_types'], true ) ) {
			$this->rotate_encryption_keys();
		}
	}

	/**
	 * Force global password reset.
	 *
	 * @param string $breach_id Breach ID.
	 */
	private function force_global_password_reset( $breach_id ) {
		global $wpdb;

		$users = $wpdb->get_col(
			"SELECT DISTINCT user_id FROM {$wpdb->prefix}wpmatch_user_profiles"
		);

		foreach ( $users as $user_id ) {
			update_user_meta( $user_id, 'wpmatch_force_password_reset', $breach_id );
			// Send password reset email.
			wp_password_change_notification( get_user_by( 'ID', $user_id ) );
		}
	}

	/**
	 * Invalidate all user sessions.
	 */
	private function invalidate_all_sessions() {
		global $wpdb;

		// Clear all session tokens.
		$wpdb->query(
			"DELETE FROM {$wpdb->usermeta}
			WHERE meta_key = 'session_tokens'"
		);
	}

	/**
	 * Rotate encryption keys.
	 */
	private function rotate_encryption_keys() {
		// Generate new master key.
		$old_master_key   = $this->master_key;
		$this->master_key = $this->generate_secure_key( 32 );

		// Update stored key.
		update_option( 'wpmatch_master_encryption_key', base64_encode( $this->master_key ) );

		// Schedule re-encryption of existing data.
		wp_schedule_single_event( time() + 60, 'wpmatch_reencrypt_data', array( $old_master_key ) );

		// Clear key cache.
		$this->key_cache = array();
	}

	/**
	 * Log data operation.
	 *
	 * @param int    $user_id User ID.
	 * @param string $operation Operation type.
	 * @param array  $data Operation data.
	 */
	private function log_data_operation( $user_id, $operation, $data ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'wpmatch_data_operations_log',
			array(
				'user_id'    => $user_id,
				'operation'  => $operation,
				'data'       => wp_json_encode( $data ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get encryption statistics.
	 *
	 * @return array Encryption statistics.
	 */
	public function get_encryption_stats() {
		global $wpdb;

		$stats = array(
			'encrypted_profiles' => 0,
			'encrypted_messages' => 0,
			'encryption_errors'  => 0,
			'key_rotations'      => 0,
		);

		// Count encrypted profiles.
		$stats['encrypted_profiles'] = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->usermeta}
			WHERE meta_key = 'wpmatch_privacy_settings_encrypted'"
		);

		// Count encrypted messages.
		$stats['encrypted_messages'] = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}wpmatch_messages
			WHERE content_encrypted = 1"
		);

		// Get error count from logs.
		$stats['encryption_errors'] = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}wpmatch_data_operations_log
			WHERE operation = 'encryption_error'
			AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)"
		);

		return $stats;
	}

	/**
	 * Display encryption unavailable notice as modal popup.
	 */
	public function encryption_unavailable_notice() {
		?>
		<div id="wpmatch-ssl-warning-modal" class="wpmatch-modal" style="display: none;">
			<div class="wpmatch-modal-overlay"></div>
			<div class="wpmatch-modal-content">
				<div class="wpmatch-modal-header">
					<span class="wpmatch-modal-icon">⚠️</span>
					<h3><?php esc_html_e( 'WPMatch Encryption Warning', 'wpmatch' ); ?></h3>
					<button class="wpmatch-modal-close" onclick="wpMatchCloseSSLModal()">&times;</button>
				</div>
				<div class="wpmatch-modal-body">
					<p><?php esc_html_e( 'OpenSSL extension is not available. Sensitive data encryption is disabled. Please contact your hosting provider to enable OpenSSL.', 'wpmatch' ); ?></p>
					<div class="wpmatch-modal-countdown">
						<small><?php esc_html_e( 'This message will close automatically in', 'wpmatch' ); ?> <span id="ssl-countdown">5</span> <?php esc_html_e( 'seconds', 'wpmatch' ); ?>.</small>
					</div>
				</div>
			</div>
		</div>

		<style>
		.wpmatch-modal {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			z-index: 999999;
		}
		.wpmatch-modal-overlay {
			position: absolute;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: rgba(0, 0, 0, 0.7);
			backdrop-filter: blur(4px);
		}
		.wpmatch-modal-content {
			position: relative;
			width: 90%;
			max-width: 500px;
			margin: 10% auto;
			background: #fff;
			border-radius: 12px;
			box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
			animation: wpMatchModalSlideIn 0.3s ease-out;
		}
		.wpmatch-modal-header {
			display: flex;
			align-items: center;
			padding: 20px 25px;
			border-bottom: 1px solid #eee;
			background: linear-gradient(135deg, #ff6b6b 0%, #ff8e53 100%);
			color: white;
			border-radius: 12px 12px 0 0;
		}
		.wpmatch-modal-icon {
			font-size: 24px;
			margin-right: 12px;
		}
		.wpmatch-modal-header h3 {
			margin: 0;
			flex: 1;
			font-size: 18px;
			font-weight: 600;
		}
		.wpmatch-modal-close {
			background: none;
			border: none;
			color: white;
			font-size: 28px;
			cursor: pointer;
			padding: 0;
			width: 30px;
			height: 30px;
			display: flex;
			align-items: center;
			justify-content: center;
			border-radius: 50%;
			transition: background 0.2s;
		}
		.wpmatch-modal-close:hover {
			background: rgba(255, 255, 255, 0.2);
		}
		.wpmatch-modal-body {
			padding: 25px;
		}
		.wpmatch-modal-body p {
			margin: 0 0 15px 0;
			font-size: 15px;
			line-height: 1.6;
			color: #555;
		}
		.wpmatch-modal-countdown {
			text-align: center;
			margin-top: 15px;
		}
		.wpmatch-modal-countdown small {
			color: #888;
			font-size: 13px;
		}
		.wpmatch-modal-countdown span {
			font-weight: bold;
			color: #ff6b6b;
		}
		@keyframes wpMatchModalSlideIn {
			from {
				transform: translateY(-50px);
				opacity: 0;
			}
			to {
				transform: translateY(0);
				opacity: 1;
			}
		}
		@keyframes wpMatchModalFadeOut {
			to {
				opacity: 0;
				transform: translateY(-20px);
			}
		}
		.wpmatch-modal.closing .wpmatch-modal-content {
			animation: wpMatchModalFadeOut 0.3s ease-in forwards;
		}
		</style>

		<script>
		let sslWarningTimeout;
		let sslCountdownInterval;

		function wpMatchShowSSLModal() {
			const modal = document.getElementById('wpmatch-ssl-warning-modal');
			if (modal) {
				modal.style.display = 'block';

				// Start countdown
				let countdown = 5;
				const countdownElement = document.getElementById('ssl-countdown');

				sslCountdownInterval = setInterval(() => {
					countdown--;
					if (countdownElement) {
						countdownElement.textContent = countdown;
					}
					if (countdown <= 0) {
						wpMatchCloseSSLModal();
					}
				}, 1000);

				// Auto-close after 5 seconds
				sslWarningTimeout = setTimeout(wpMatchCloseSSLModal, 5000);
			}
		}

		function wpMatchCloseSSLModal() {
			const modal = document.getElementById('wpmatch-ssl-warning-modal');
			if (modal) {
				// Clear timeouts
				if (sslWarningTimeout) clearTimeout(sslWarningTimeout);
				if (sslCountdownInterval) clearInterval(sslCountdownInterval);

				// Add closing animation class
				modal.classList.add('closing');

				// Hide after animation
				setTimeout(() => {
					modal.style.display = 'none';
					modal.classList.remove('closing');
				}, 300);
			}
		}

		// Show modal when page loads (only once per session)
		document.addEventListener('DOMContentLoaded', function() {
			// Check if modal has already been shown this session
			if (!sessionStorage.getItem('wpmatch_ssl_warning_shown')) {
				// Small delay to ensure page is fully loaded
				setTimeout(wpMatchShowSSLModal, 500);
				// Mark as shown for this session
				sessionStorage.setItem('wpmatch_ssl_warning_shown', 'true');
			}
		});

		// Close on overlay click
		document.addEventListener('click', function(e) {
			if (e.target && e.target.classList.contains('wpmatch-modal-overlay')) {
				wpMatchCloseSSLModal();
			}
		});

		// Close on Escape key
		document.addEventListener('keydown', function(e) {
			if (e.key === 'Escape') {
				wpMatchCloseSSLModal();
			}
		});
		</script>
		<?php
	}
}

// Initialize encryption manager.
WPMatch_Encryption_Manager::get_instance();