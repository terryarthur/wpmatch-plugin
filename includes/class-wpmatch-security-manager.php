<?php
/**
 * WPMatch Security Manager
 *
 * Handles comprehensive security measures including vulnerability scanning,
 * threat detection, and security hardening.
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
 * WPMatch Security Manager class.
 *
 * @since 1.0.0
 */
class WPMatch_Security_Manager {

	/**
	 * Security event types.
	 */
	const EVENT_LOGIN_FAILED     = 'login_failed';
	const EVENT_LOGIN_SUCCESS    = 'login_success';
	const EVENT_SUSPICIOUS_REQUEST = 'suspicious_request';
	const EVENT_RATE_LIMIT_EXCEEDED = 'rate_limit_exceeded';
	const EVENT_CONTENT_BLOCKED   = 'content_blocked';
	const EVENT_IP_BLOCKED       = 'ip_blocked';
	const EVENT_SQL_INJECTION    = 'sql_injection_attempt';
	const EVENT_XSS_ATTEMPT     = 'xss_attempt';
	const EVENT_FILE_UPLOAD      = 'file_upload';

	/**
	 * Rate limiting constants.
	 */
	const RATE_LIMIT_LOGIN       = 5;   // 5 attempts per 15 minutes
	const RATE_LIMIT_API         = 100; // 100 requests per minute
	const RATE_LIMIT_MESSAGE     = 50;  // 50 messages per hour
	const RATE_LIMIT_SWIPE       = 200; // 200 swipes per hour

	/**
	 * Instance of the class.
	 *
	 * @var WPMatch_Security_Manager
	 */
	private static $instance = null;

	/**
	 * Blocked IPs cache.
	 *
	 * @var array
	 */
	private $blocked_ips = array();

	/**
	 * Security rules cache.
	 *
	 * @var array
	 */
	private $security_rules = array();

	/**
	 * Get the single instance.
	 *
	 * @return WPMatch_Security_Manager
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
		$this->init_security();
		$this->init_hooks();
		$this->load_security_rules();
	}

	/**
	 * Initialize security measures.
	 */
	private function init_security() {
		// Load blocked IPs from database.
		$this->load_blocked_ips();

		// Initialize security headers.
		add_action( 'init', array( $this, 'set_security_headers' ) );

		// Initialize input validation.
		add_action( 'init', array( $this, 'validate_requests' ), 1 );
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Authentication security.
		add_action( 'wp_login_failed', array( $this, 'handle_login_failure' ) );
		add_action( 'wp_login', array( $this, 'handle_login_success' ), 10, 2 );

		// File upload security.
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'validate_file_upload' ) );

		// API security.
		add_action( 'rest_api_init', array( $this, 'secure_rest_api' ) );

		// Content security.
		add_filter( 'content_save_pre', array( $this, 'scan_content' ) );

		// Rate limiting.
		add_action( 'wp_ajax_wpmatch_rate_limit_check', array( $this, 'check_rate_limits' ) );
		add_action( 'wp_ajax_nopriv_wpmatch_rate_limit_check', array( $this, 'check_rate_limits' ) );

		// Security cleanup.
		add_action( 'wpmatch_security_cleanup', array( $this, 'cleanup_security_logs' ) );
	}

	/**
	 * Load security rules.
	 */
	private function load_security_rules() {
		$this->security_rules = array(
			'sql_patterns' => array(
				'/(\bunion\b.*\bselect\b)/i',
				'/(\bselect\b.*\bfrom\b.*\bwhere\b.*\bor\b.*\b=\b)/i',
				'/(\bdrop\b.*\btable\b)/i',
				'/(\binsert\b.*\binto\b.*\bvalues\b)/i',
				'/(\bexec\b|\bexecute\b)/i',
				'/(\bsp_\w+)/i',
				'/(\bxp_\w+)/i',
			),
			'xss_patterns' => array(
				'/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
				'/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/mi',
				'/javascript:/i',
				'/vbscript:/i',
				'/onload\s*=/i',
				'/onerror\s*=/i',
				'/onclick\s*=/i',
			),
			'file_extensions' => array(
				'allowed' => array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'avi' ),
				'blocked' => array( 'php', 'exe', 'bat', 'com', 'scr', 'vbs', 'js', 'html', 'htm' ),
			),
			'suspicious_params' => array(
				'eval',
				'base64_decode',
				'file_get_contents',
				'shell_exec',
				'system',
				'passthru',
				'exec',
			),
		);
	}

	/**
	 * Load blocked IPs from database.
	 */
	private function load_blocked_ips() {
		$blocked_ips = get_option( 'wpmatch_blocked_ips', array() );
		$this->blocked_ips = is_array( $blocked_ips ) ? $blocked_ips : array();
	}

	/**
	 * Set security headers.
	 */
	public function set_security_headers() {
		if ( ! is_admin() && ! wp_doing_ajax() ) {
			// Content Security Policy.
			header( "Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data: https:; connect-src 'self' https:; frame-ancestors 'none';" );

			// XSS Protection.
			header( 'X-XSS-Protection: 1; mode=block' );

			// Prevent MIME type sniffing.
			header( 'X-Content-Type-Options: nosniff' );

			// Prevent clickjacking.
			header( 'X-Frame-Options: DENY' );

			// HSTS (if HTTPS).
			if ( is_ssl() ) {
				header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains; preload' );
			}

			// Referrer Policy.
			header( 'Referrer-Policy: strict-origin-when-cross-origin' );

			// Permissions Policy.
			header( 'Permissions-Policy: geolocation=(self), microphone=(), camera=()' );
		}
	}

	/**
	 * Validate incoming requests.
	 */
	public function validate_requests() {
		$ip_address = $this->get_user_ip();

		// Check if IP is blocked.
		if ( $this->is_ip_blocked( $ip_address ) ) {
			$this->block_request( 'IP address blocked' );
		}

		// Check for suspicious patterns in request.
		$this->scan_request_for_threats();
	}

	/**
	 * Scan request for security threats.
	 */
	private function scan_request_for_threats() {
		$request_data = array_merge( $_GET, $_POST );

		foreach ( $request_data as $key => $value ) {
			if ( is_string( $value ) ) {
				// Check for SQL injection patterns.
				foreach ( $this->security_rules['sql_patterns'] as $pattern ) {
					if ( preg_match( $pattern, $value ) ) {
						$this->log_security_event( self::EVENT_SQL_INJECTION, array(
							'pattern' => $pattern,
							'value'   => $value,
							'param'   => $key,
						) );
						$this->block_request( 'SQL injection attempt detected' );
					}
				}

				// Check for XSS patterns.
				foreach ( $this->security_rules['xss_patterns'] as $pattern ) {
					if ( preg_match( $pattern, $value ) ) {
						$this->log_security_event( self::EVENT_XSS_ATTEMPT, array(
							'pattern' => $pattern,
							'value'   => $value,
							'param'   => $key,
						) );
						$this->block_request( 'XSS attempt detected' );
					}
				}

				// Check for suspicious function calls.
				foreach ( $this->security_rules['suspicious_params'] as $func ) {
					if ( stripos( $value, $func ) !== false ) {
						$this->log_security_event( self::EVENT_SUSPICIOUS_REQUEST, array(
							'function' => $func,
							'value'    => $value,
							'param'    => $key,
						) );
					}
				}
			}
		}
	}

	/**
	 * Handle login failures.
	 *
	 * @param string $username Failed username.
	 */
	public function handle_login_failure( $username ) {
		$ip_address = $this->get_user_ip();

		// Log the failed login.
		$this->log_security_event( self::EVENT_LOGIN_FAILED, array(
			'username'   => $username,
			'ip_address' => $ip_address,
		) );

		// Check if IP should be blocked.
		$this->check_login_rate_limit( $ip_address );
	}

	/**
	 * Handle successful login.
	 *
	 * @param string $user_login Username.
	 * @param WP_User $user User object.
	 */
	public function handle_login_success( $user_login, $user ) {
		$ip_address = $this->get_user_ip();

		// Log successful login.
		$this->log_security_event( self::EVENT_LOGIN_SUCCESS, array(
			'user_id'    => $user->ID,
			'username'   => $user_login,
			'ip_address' => $ip_address,
		) );

		// Reset failed login count for this IP.
		$this->reset_failed_login_count( $ip_address );
	}

	/**
	 * Check login rate limits.
	 *
	 * @param string $ip_address IP address.
	 */
	private function check_login_rate_limit( $ip_address ) {
		$transient_key = 'wpmatch_login_attempts_' . md5( $ip_address );
		$attempts = get_transient( $transient_key );

		if ( false === $attempts ) {
			$attempts = 1;
		} else {
			++$attempts;
		}

		// Set/update the transient for 15 minutes.
		set_transient( $transient_key, $attempts, 15 * MINUTE_IN_SECONDS );

		if ( $attempts >= self::RATE_LIMIT_LOGIN ) {
			$this->block_ip_temporarily( $ip_address, 'Too many failed login attempts' );
		}
	}

	/**
	 * Reset failed login count.
	 *
	 * @param string $ip_address IP address.
	 */
	private function reset_failed_login_count( $ip_address ) {
		$transient_key = 'wpmatch_login_attempts_' . md5( $ip_address );
		delete_transient( $transient_key );
	}

	/**
	 * Validate file uploads.
	 *
	 * @param array $file File data.
	 * @return array Modified file data.
	 */
	public function validate_file_upload( $file ) {
		if ( empty( $file['name'] ) ) {
			return $file;
		}

		$filename = sanitize_file_name( $file['name'] );
		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		// Check if extension is blocked.
		if ( in_array( $extension, $this->security_rules['file_extensions']['blocked'], true ) ) {
			$file['error'] = __( 'File type not allowed for security reasons.', 'wpmatch' );
			$this->log_security_event( self::EVENT_FILE_UPLOAD, array(
				'filename'  => $filename,
				'extension' => $extension,
				'status'    => 'blocked',
				'reason'    => 'blocked_extension',
			) );
			return $file;
		}

		// Check if extension is in allowed list (for media uploads).
		if ( ! in_array( $extension, $this->security_rules['file_extensions']['allowed'], true ) ) {
			$file['error'] = __( 'File type not allowed.', 'wpmatch' );
			return $file;
		}

		// Additional security checks for images.
		if ( in_array( $extension, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true ) ) {
			$this->validate_image_file( $file );
		}

		// Log successful upload.
		$this->log_security_event( self::EVENT_FILE_UPLOAD, array(
			'filename'  => $filename,
			'extension' => $extension,
			'size'      => $file['size'],
			'status'    => 'allowed',
		) );

		return $file;
	}

	/**
	 * Validate image files for security threats.
	 *
	 * @param array $file File data.
	 * @return array Modified file data.
	 */
	private function validate_image_file( &$file ) {
		if ( empty( $file['tmp_name'] ) ) {
			return $file;
		}

		// Verify it's actually an image.
		$image_info = getimagesize( $file['tmp_name'] );
		if ( false === $image_info ) {
			$file['error'] = __( 'Invalid image file.', 'wpmatch' );
			return $file;
		}

		// Check for suspicious content in image files.
		$file_content = file_get_contents( $file['tmp_name'] );
		$suspicious_patterns = array(
			'<?php',
			'<script',
			'eval(',
			'base64_decode',
		);

		foreach ( $suspicious_patterns as $pattern ) {
			if ( stripos( $file_content, $pattern ) !== false ) {
				$file['error'] = __( 'File contains suspicious content.', 'wpmatch' );
				$this->log_security_event( self::EVENT_FILE_UPLOAD, array(
					'filename' => $file['name'],
					'status'   => 'blocked',
					'reason'   => 'suspicious_content',
					'pattern'  => $pattern,
				) );
				return $file;
			}
		}

		return $file;
	}

	/**
	 * Secure REST API endpoints.
	 */
	public function secure_rest_api() {
		// Add authentication check for WPMatch endpoints.
		add_filter( 'rest_pre_dispatch', array( $this, 'validate_api_request' ), 10, 3 );
	}

	/**
	 * Validate API requests.
	 *
	 * @param mixed $result Dispatch result.
	 * @param WP_REST_Server $server Server instance.
	 * @param WP_REST_Request $request Request object.
	 * @return mixed
	 */
	public function validate_api_request( $result, $server, $request ) {
		$route = $request->get_route();

		// Only check WPMatch API endpoints.
		if ( strpos( $route, '/wpmatch/v1/' ) !== 0 ) {
			return $result;
		}

		$ip_address = $this->get_user_ip();

		// Check IP blocking.
		if ( $this->is_ip_blocked( $ip_address ) ) {
			return new WP_Error(
				'ip_blocked',
				'Access denied',
				array( 'status' => 403 )
			);
		}

		// Check API rate limits.
		if ( ! $this->check_api_rate_limit( $ip_address ) ) {
			$this->log_security_event( self::EVENT_RATE_LIMIT_EXCEEDED, array(
				'ip_address' => $ip_address,
				'endpoint'   => $route,
				'type'       => 'api',
			) );

			return new WP_Error(
				'rate_limit_exceeded',
				'Too many requests',
				array( 'status' => 429 )
			);
		}

		return $result;
	}

	/**
	 * Check API rate limits.
	 *
	 * @param string $ip_address IP address.
	 * @return bool True if within limits.
	 */
	private function check_api_rate_limit( $ip_address ) {
		$transient_key = 'wpmatch_api_requests_' . md5( $ip_address );
		$requests = get_transient( $transient_key );

		if ( false === $requests ) {
			$requests = 1;
		} else {
			++$requests;
		}

		// Set/update the transient for 1 minute.
		set_transient( $transient_key, $requests, MINUTE_IN_SECONDS );

		return $requests <= self::RATE_LIMIT_API;
	}

	/**
	 * Scan content for malicious patterns.
	 *
	 * @param string $content Content to scan.
	 * @return string Cleaned content.
	 */
	public function scan_content( $content ) {
		// Check for XSS patterns.
		foreach ( $this->security_rules['xss_patterns'] as $pattern ) {
			if ( preg_match( $pattern, $content ) ) {
				$this->log_security_event( self::EVENT_CONTENT_BLOCKED, array(
					'pattern' => $pattern,
					'content' => substr( $content, 0, 200 ),
				) );

				// Strip the malicious content.
				$content = preg_replace( $pattern, '', $content );
			}
		}

		return $content;
	}

	/**
	 * Block an IP address temporarily.
	 *
	 * @param string $ip_address IP address.
	 * @param string $reason Reason for blocking.
	 * @param int $duration Duration in seconds.
	 */
	public function block_ip_temporarily( $ip_address, $reason, $duration = 3600 ) {
		$transient_key = 'wpmatch_blocked_ip_' . md5( $ip_address );
		set_transient( $transient_key, $reason, $duration );

		$this->log_security_event( self::EVENT_IP_BLOCKED, array(
			'ip_address' => $ip_address,
			'reason'     => $reason,
			'duration'   => $duration,
			'type'       => 'temporary',
		) );
	}

	/**
	 * Block an IP address permanently.
	 *
	 * @param string $ip_address IP address.
	 * @param string $reason Reason for blocking.
	 */
	public function block_ip_permanently( $ip_address, $reason ) {
		$this->blocked_ips[ $ip_address ] = array(
			'reason'    => $reason,
			'timestamp' => time(),
		);

		update_option( 'wpmatch_blocked_ips', $this->blocked_ips );

		$this->log_security_event( self::EVENT_IP_BLOCKED, array(
			'ip_address' => $ip_address,
			'reason'     => $reason,
			'type'       => 'permanent',
		) );
	}

	/**
	 * Check if IP is blocked.
	 *
	 * @param string $ip_address IP address.
	 * @return bool True if blocked.
	 */
	public function is_ip_blocked( $ip_address ) {
		// Check temporary blocks.
		$transient_key = 'wpmatch_blocked_ip_' . md5( $ip_address );
		if ( false !== get_transient( $transient_key ) ) {
			return true;
		}

		// Check permanent blocks.
		return isset( $this->blocked_ips[ $ip_address ] );
	}

	/**
	 * Block current request.
	 *
	 * @param string $message Block message.
	 */
	private function block_request( $message ) {
		status_header( 403 );
		wp_die(
			esc_html( $message ),
			esc_html__( 'Access Denied', 'wpmatch' ),
			array( 'response' => 403 )
		);
	}

	/**
	 * Get user IP address.
	 *
	 * @return string IP address.
	 */
	public function get_user_ip() {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP',     // Cloudflare
			'HTTP_CLIENT_IP',            // Proxy
			'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',               // Standard
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) );
				$ip = trim( $ips[0] );

				// Validate IP address.
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return '127.0.0.1'; // Fallback.
	}

	/**
	 * Log security event.
	 *
	 * @param string $event_type Event type.
	 * @param array $data Event data.
	 */
	public function log_security_event( $event_type, $data = array() ) {
		// Determine severity based on event type
		$severity = WPMatch_Security_Logger::SEVERITY_LOW;

		switch ( $event_type ) {
			case self::EVENT_SQL_INJECTION:
			case self::EVENT_XSS_ATTEMPT:
				$severity = WPMatch_Security_Logger::SEVERITY_HIGH;
				break;
			case self::EVENT_LOGIN_FAILED:
			case self::EVENT_RATE_LIMIT_EXCEEDED:
				$severity = WPMatch_Security_Logger::SEVERITY_MEDIUM;
				break;
			case self::EVENT_SUSPICIOUS_REQUEST:
			case self::EVENT_CONTENT_BLOCKED:
			case self::EVENT_IP_BLOCKED:
				$severity = WPMatch_Security_Logger::SEVERITY_MEDIUM;
				break;
			case self::EVENT_FILE_UPLOAD:
				$severity = isset( $data['threat_detected'] ) && $data['threat_detected'] ?
					WPMatch_Security_Logger::SEVERITY_CRITICAL : WPMatch_Security_Logger::SEVERITY_LOW;
				break;
		}

		// Use the security logger to log the event
		$security_logger = WPMatch_Security_Logger::get_instance();
		$security_logger->log_security_event( $event_type, $data, $severity );

		// Trigger WordPress action for other plugins to hook into
		do_action( 'wpmatch_security_event', $event_type, $data, $severity );
	}

	/**
	 * Send security alert to administrators.
	 *
	 * @param string $event_type Event type.
	 * @param array $data Event data.
	 */
	private function send_security_alert( $event_type, $data ) {
		$admin_email = get_option( 'admin_email' );

		$subject = sprintf(
			/* translators: %s: Site name */
			__( '[SECURITY ALERT] %s - Potential Attack Detected', 'wpmatch' ),
			get_bloginfo( 'name' )
		);

		$message = sprintf(
			/* translators: 1: Event type, 2: IP address, 3: Timestamp */
			__( "A potential security threat has been detected on your WPMatch installation.\n\nEvent Type: %1\$s\nIP Address: %2\$s\nTimestamp: %3\$s\n\nPlease review your security logs for more details.", 'wpmatch' ),
			$event_type,
			$this->get_user_ip(),
			current_time( 'mysql' )
		);

		wp_mail( $admin_email, $subject, $message );
	}

	/**
	 * Get security statistics.
	 *
	 * @return array Security statistics.
	 */
	public function get_security_stats() {
		global $wpdb;

		$stats = array(
			'total_events'    => 0,
			'blocked_ips'     => count( $this->blocked_ips ),
			'recent_attacks'  => 0,
			'login_failures'  => 0,
			'blocked_uploads' => 0,
		);

		// Get event counts.
		$event_counts = $wpdb->get_results(
			"SELECT event_type, COUNT(*) as count
			FROM {$wpdb->prefix}wpmatch_security_logs
			WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
			GROUP BY event_type"
		);

		foreach ( $event_counts as $event ) {
			switch ( $event->event_type ) {
				case self::EVENT_LOGIN_FAILED:
					$stats['login_failures'] = (int) $event->count;
					break;
				case self::EVENT_SQL_INJECTION:
				case self::EVENT_XSS_ATTEMPT:
					$stats['recent_attacks'] += (int) $event->count;
					break;
			}

			$stats['total_events'] += (int) $event->count;
		}

		return $stats;
	}

	/**
	 * Clean up old security logs.
	 */
	public function cleanup_security_logs() {
		global $wpdb;

		// Delete logs older than 30 days.
		$wpdb->query(
			"DELETE FROM {$wpdb->prefix}wpmatch_security_logs
			WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
		);

		// Clean up temporary IP blocks.
		$this->cleanup_temporary_blocks();
	}

	/**
	 * Clean up temporary IP blocks.
	 */
	private function cleanup_temporary_blocks() {
		global $wpdb;

		// Get all transients that match our pattern.
		$transients = $wpdb->get_col(
			"SELECT option_name FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_wpmatch_blocked_ip_%'"
		);

		foreach ( $transients as $transient ) {
			$key = str_replace( '_transient_', '', $transient );
			if ( false === get_transient( $key ) ) {
				delete_transient( $key );
			}
		}
	}

	/**
	 * Generate security report.
	 *
	 * @param int $days Number of days to include in report.
	 * @return array Security report data.
	 */
	public function generate_security_report( $days = 7 ) {
		global $wpdb;

		$report = array(
			'period'       => $days,
			'total_events' => 0,
			'events_by_type' => array(),
			'top_ips'      => array(),
			'blocked_ips'  => count( $this->blocked_ips ),
			'recommendations' => array(),
		);

		// Get events by type.
		$events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_type, ip_address, COUNT(*) as count
				FROM {$wpdb->prefix}wpmatch_security_logs
				WHERE created_at > DATE_SUB(NOW(), INTERVAL %d DAY)
				GROUP BY event_type, ip_address
				ORDER BY count DESC",
				$days
			)
		);

		foreach ( $events as $event ) {
			$report['total_events'] += (int) $event->count;

			if ( ! isset( $report['events_by_type'][ $event->event_type ] ) ) {
				$report['events_by_type'][ $event->event_type ] = 0;
			}
			$report['events_by_type'][ $event->event_type ] += (int) $event->count;

			// Track top IPs.
			if ( ! isset( $report['top_ips'][ $event->ip_address ] ) ) {
				$report['top_ips'][ $event->ip_address ] = 0;
			}
			$report['top_ips'][ $event->ip_address ] += (int) $event->count;
		}

		// Sort top IPs.
		arsort( $report['top_ips'] );
		$report['top_ips'] = array_slice( $report['top_ips'], 0, 10, true );

		// Generate recommendations.
		$report['recommendations'] = $this->generate_security_recommendations( $report );

		return $report;
	}

	/**
	 * Generate security recommendations.
	 *
	 * @param array $report Security report data.
	 * @return array Recommendations.
	 */
	private function generate_security_recommendations( $report ) {
		$recommendations = array();

		// High number of login failures.
		if ( isset( $report['events_by_type'][ self::EVENT_LOGIN_FAILED ] ) &&
			$report['events_by_type'][ self::EVENT_LOGIN_FAILED ] > 50 ) {
			$recommendations[] = __( 'Consider implementing two-factor authentication due to high login failure rate.', 'wpmatch' );
		}

		// SQL injection attempts.
		if ( isset( $report['events_by_type'][ self::EVENT_SQL_INJECTION ] ) ) {
			$recommendations[] = __( 'SQL injection attempts detected. Review form validation and input sanitization.', 'wpmatch' );
		}

		// XSS attempts.
		if ( isset( $report['events_by_type'][ self::EVENT_XSS_ATTEMPT ] ) ) {
			$recommendations[] = __( 'Cross-site scripting attempts detected. Review output escaping practices.', 'wpmatch' );
		}

		// Rate limit exceeded.
		if ( isset( $report['events_by_type'][ self::EVENT_RATE_LIMIT_EXCEEDED ] ) &&
			$report['events_by_type'][ self::EVENT_RATE_LIMIT_EXCEEDED ] > 100 ) {
			$recommendations[] = __( 'High rate limiting triggers suggest potential abuse. Consider tightening rate limits.', 'wpmatch' );
		}

		return $recommendations;
	}
}

// Initialize security manager.
WPMatch_Security_Manager::get_instance();