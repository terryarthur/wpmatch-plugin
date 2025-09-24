<?php
/**
 * WPMatch Security Logger
 *
 * Comprehensive security monitoring and logging system
 *
 * @package WPMatch
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPMatch_Security_Logger {

	private static $instance = null;

	const LOG_TABLE = 'wpmatch_security_logs';
	const ALERT_TABLE = 'wpmatch_security_alerts';

	const SEVERITY_LOW = 1;
	const SEVERITY_MEDIUM = 2;
	const SEVERITY_HIGH = 3;
	const SEVERITY_CRITICAL = 4;

	const STATUS_OPEN = 'open';
	const STATUS_INVESTIGATING = 'investigating';
	const STATUS_RESOLVED = 'resolved';
	const STATUS_FALSE_POSITIVE = 'false_positive';

	private $log_retention_days = 90;
	private $alert_thresholds = array();
	private $notification_emails = array();

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->init_alert_thresholds();
		$this->init_hooks();
	}

	private function init_hooks() {
		add_action( 'init', array( $this, 'create_security_tables' ) );
		add_action( 'wpmatch_security_event', array( $this, 'log_security_event' ), 10, 3 );
		add_action( 'wpmatch_daily_cleanup', array( $this, 'cleanup_old_logs' ) );
		add_action( 'wp_ajax_wpmatch_get_security_logs', array( $this, 'ajax_get_security_logs' ) );
		add_action( 'wp_ajax_wpmatch_update_alert_status', array( $this, 'ajax_update_alert_status' ) );
		add_action( 'wp_ajax_wpmatch_export_security_logs', array( $this, 'ajax_export_security_logs' ) );
	}

	private function init_alert_thresholds() {
		$this->alert_thresholds = array(
			'failed_login'     => array( 'count' => 5, 'window' => 300, 'severity' => self::SEVERITY_MEDIUM ),
			'sql_injection'    => array( 'count' => 1, 'window' => 3600, 'severity' => self::SEVERITY_HIGH ),
			'xss_attempt'      => array( 'count' => 3, 'window' => 3600, 'severity' => self::SEVERITY_HIGH ),
			'rate_limit'       => array( 'count' => 10, 'window' => 600, 'severity' => self::SEVERITY_MEDIUM ),
			'suspicious_file'  => array( 'count' => 1, 'window' => 3600, 'severity' => self::SEVERITY_CRITICAL ),
			'privilege_escalation' => array( 'count' => 1, 'window' => 3600, 'severity' => self::SEVERITY_CRITICAL ),
		);

		$this->notification_emails = array_filter( array(
			get_option( 'admin_email' ),
			get_option( 'wpmatch_security_admin_email' ),
		) );
	}

	public function create_security_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Security logs table
		$logs_table = $wpdb->prefix . self::LOG_TABLE;
		$logs_sql = "CREATE TABLE IF NOT EXISTS $logs_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			event_type varchar(50) NOT NULL,
			severity tinyint(1) NOT NULL DEFAULT 1,
			message text NOT NULL,
			context longtext DEFAULT NULL,
			ip_address varchar(45) NOT NULL,
			user_id bigint(20) DEFAULT NULL,
			user_agent text DEFAULT NULL,
			request_uri text DEFAULT NULL,
			request_method varchar(10) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY event_type (event_type),
			KEY severity (severity),
			KEY ip_address (ip_address),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) $charset_collate;";

		// Security alerts table
		$alerts_table = $wpdb->prefix . self::ALERT_TABLE;
		$alerts_sql = "CREATE TABLE IF NOT EXISTS $alerts_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			alert_type varchar(50) NOT NULL,
			severity tinyint(1) NOT NULL,
			title varchar(255) NOT NULL,
			description text NOT NULL,
			event_count int(11) NOT NULL DEFAULT 1,
			first_occurrence datetime NOT NULL,
			last_occurrence datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			status varchar(20) NOT NULL DEFAULT 'open',
			assigned_to bigint(20) DEFAULT NULL,
			resolution_notes text DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY alert_type (alert_type),
			KEY severity (severity),
			KEY status (status),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $logs_sql );
		dbDelta( $alerts_sql );
	}

	public function log_security_event( $event_type, $context = array(), $severity = self::SEVERITY_LOW ) {
		global $wpdb;

		$ip_address = $this->get_client_ip();
		$user_id = get_current_user_id();
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';

		$message = $this->generate_event_message( $event_type, $context );
		$context_json = wp_json_encode( $context );

		$result = $wpdb->insert(
			$wpdb->prefix . self::LOG_TABLE,
			array(
				'event_type'     => sanitize_text_field( $event_type ),
				'severity'       => absint( $severity ),
				'message'        => sanitize_text_field( $message ),
				'context'        => $context_json,
				'ip_address'     => sanitize_text_field( $ip_address ),
				'user_id'        => $user_id ? absint( $user_id ) : null,
				'user_agent'     => $user_agent,
				'request_uri'    => $request_uri,
				'request_method' => $request_method,
			),
			array( '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		if ( $result ) {
			$this->check_alert_thresholds( $event_type, $severity );
			$this->trigger_real_time_notifications( $event_type, $severity, $context );
		}

		return $result;
	}

	private function generate_event_message( $event_type, $context ) {
		$messages = array(
			'failed_login'        => 'Failed login attempt',
			'sql_injection'       => 'SQL injection attempt detected',
			'xss_attempt'         => 'Cross-site scripting attempt detected',
			'rate_limit'          => 'Rate limit exceeded',
			'suspicious_file'     => 'Suspicious file upload detected',
			'privilege_escalation' => 'Privilege escalation attempt detected',
			'blocked_ip'          => 'IP address blocked due to suspicious activity',
			'malware_detected'    => 'Malware signature detected',
			'brute_force'         => 'Brute force attack detected',
		);

		$base_message = isset( $messages[ $event_type ] ) ? $messages[ $event_type ] : 'Security event detected';

		if ( ! empty( $context['details'] ) ) {
			$base_message .= ': ' . sanitize_text_field( $context['details'] );
		}

		return $base_message;
	}

	private function check_alert_thresholds( $event_type, $severity ) {
		if ( ! isset( $this->alert_thresholds[ $event_type ] ) ) {
			return;
		}

		global $wpdb;
		$threshold = $this->alert_thresholds[ $event_type ];
		$time_window = gmdate( 'Y-m-d H:i:s', time() - $threshold['window'] );

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}" . self::LOG_TABLE . "
			WHERE event_type = %s AND created_at >= %s",
			$event_type,
			$time_window
		) );

		if ( $count >= $threshold['count'] ) {
			$this->create_security_alert( $event_type, $threshold['severity'], $count );
		}
	}

	private function create_security_alert( $alert_type, $severity, $event_count ) {
		global $wpdb;

		$existing_alert = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}" . self::ALERT_TABLE . "
			WHERE alert_type = %s AND status IN ('open', 'investigating')
			ORDER BY created_at DESC LIMIT 1",
			$alert_type
		) );

		if ( $existing_alert ) {
			$wpdb->update(
				$wpdb->prefix . self::ALERT_TABLE,
				array(
					'event_count'     => absint( $existing_alert->event_count ) + 1,
					'last_occurrence' => current_time( 'mysql' ),
				),
				array( 'id' => absint( $existing_alert->id ) ),
				array( '%d', '%s' ),
				array( '%d' )
			);
		} else {
			$title = $this->generate_alert_title( $alert_type, $severity );
			$description = $this->generate_alert_description( $alert_type, $event_count );

			$wpdb->insert(
				$wpdb->prefix . self::ALERT_TABLE,
				array(
					'alert_type'       => sanitize_text_field( $alert_type ),
					'severity'         => absint( $severity ),
					'title'            => sanitize_text_field( $title ),
					'description'      => sanitize_text_field( $description ),
					'event_count'      => absint( $event_count ),
					'first_occurrence' => current_time( 'mysql' ),
					'last_occurrence'  => current_time( 'mysql' ),
				),
				array( '%s', '%d', '%s', '%s', '%d', '%s', '%s' )
			);

			$this->send_alert_notification( $alert_type, $severity, $title, $description );
		}
	}

	private function generate_alert_title( $alert_type, $severity ) {
		$severity_labels = array(
			self::SEVERITY_LOW      => 'Low',
			self::SEVERITY_MEDIUM   => 'Medium',
			self::SEVERITY_HIGH     => 'High',
			self::SEVERITY_CRITICAL => 'Critical',
		);

		$titles = array(
			'failed_login'         => 'Multiple Failed Login Attempts',
			'sql_injection'        => 'SQL Injection Attack Detected',
			'xss_attempt'          => 'Cross-Site Scripting Attack',
			'rate_limit'           => 'Rate Limiting Triggered',
			'suspicious_file'      => 'Suspicious File Activity',
			'privilege_escalation' => 'Privilege Escalation Attempt',
		);

		$title = isset( $titles[ $alert_type ] ) ? $titles[ $alert_type ] : 'Security Alert';
		$severity_label = isset( $severity_labels[ $severity ] ) ? $severity_labels[ $severity ] : 'Unknown';

		return sprintf( '[%s] %s', $severity_label, $title );
	}

	private function generate_alert_description( $alert_type, $event_count ) {
		$descriptions = array(
			'failed_login'         => sprintf( 'Detected %d failed login attempts within the alert threshold window.', $event_count ),
			'sql_injection'        => sprintf( 'Detected %d SQL injection attempts. Immediate investigation recommended.', $event_count ),
			'xss_attempt'          => sprintf( 'Detected %d XSS attempts targeting your application.', $event_count ),
			'rate_limit'           => sprintf( 'Rate limiting has been triggered %d times, indicating potential abuse.', $event_count ),
			'suspicious_file'      => sprintf( 'Detected %d suspicious file activities. Review file uploads and modifications.', $event_count ),
			'privilege_escalation' => sprintf( 'Detected %d privilege escalation attempts. Critical security review required.', $event_count ),
		);

		return isset( $descriptions[ $alert_type ] ) ? $descriptions[ $alert_type ] : sprintf( 'Security alert triggered %d times.', $event_count );
	}

	private function send_alert_notification( $alert_type, $severity, $title, $description ) {
		if ( empty( $this->notification_emails ) || $severity < self::SEVERITY_MEDIUM ) {
			return;
		}

		$subject = sprintf( '[WPMatch Security] %s', $title );
		$message = $this->generate_alert_email( $alert_type, $severity, $title, $description );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: WPMatch Security <' . get_option( 'admin_email' ) . '>',
		);

		foreach ( $this->notification_emails as $email ) {
			if ( is_email( $email ) ) {
				wp_mail( $email, $subject, $message, $headers );
			}
		}
	}

	private function generate_alert_email( $alert_type, $severity, $title, $description ) {
		$site_name = get_bloginfo( 'name' );
		$site_url = get_site_url();
		$admin_url = admin_url( 'admin.php?page=wpmatch-security' );

		$severity_colors = array(
			self::SEVERITY_LOW      => '#28a745',
			self::SEVERITY_MEDIUM   => '#ffc107',
			self::SEVERITY_HIGH     => '#fd7e14',
			self::SEVERITY_CRITICAL => '#dc3545',
		);

		$color = isset( $severity_colors[ $severity ] ) ? $severity_colors[ $severity ] : '#6c757d';

		ob_start();
		?>
		<html>
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php echo esc_html( $title ); ?></title>
		</head>
		<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
			<div style="background: <?php echo esc_attr( $color ); ?>; color: white; padding: 20px; border-radius: 5px 5px 0 0;">
				<h1 style="margin: 0; font-size: 24px;"><?php echo esc_html( $title ); ?></h1>
			</div>

			<div style="background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; border-top: none;">
				<p><strong>Site:</strong> <?php echo esc_html( $site_name ); ?> (<?php echo esc_url( $site_url ); ?>)</p>
				<p><strong>Alert Type:</strong> <?php echo esc_html( $alert_type ); ?></p>
				<p><strong>Time:</strong> <?php echo esc_html( current_time( 'Y-m-d H:i:s' ) ); ?></p>

				<h3>Description:</h3>
				<p><?php echo esc_html( $description ); ?></p>

				<h3>Recommended Actions:</h3>
				<ul>
					<li>Review security logs immediately</li>
					<li>Check for any unauthorized access</li>
					<li>Verify all user accounts and permissions</li>
					<li>Consider implementing additional security measures</li>
				</ul>

				<div style="margin-top: 30px; text-align: center;">
					<a href="<?php echo esc_url( $admin_url ); ?>" style="background: #007cba; color: white; padding: 12px 30px; text-decoration: none; border-radius: 3px; display: inline-block;">
						View Security Dashboard
					</a>
				</div>
			</div>

			<div style="background: #e9ecef; padding: 15px; border-radius: 0 0 5px 5px; font-size: 12px; color: #6c757d;">
				<p style="margin: 0;">This is an automated security alert from WPMatch. Please do not reply to this email.</p>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	private function trigger_real_time_notifications( $event_type, $severity, $context ) {
		if ( $severity >= self::SEVERITY_HIGH ) {
			do_action( 'wpmatch_critical_security_event', $event_type, $context );
		}

		do_action( 'wpmatch_security_event_logged', $event_type, $severity, $context );
	}

	public function get_security_logs( $limit = 50, $offset = 0, $filters = array() ) {
		global $wpdb;

		$where_conditions = array( '1=1' );
		$where_values = array();

		if ( ! empty( $filters['event_type'] ) ) {
			$where_conditions[] = 'event_type = %s';
			$where_values[] = sanitize_text_field( $filters['event_type'] );
		}

		if ( ! empty( $filters['severity'] ) ) {
			$where_conditions[] = 'severity = %d';
			$where_values[] = absint( $filters['severity'] );
		}

		if ( ! empty( $filters['ip_address'] ) ) {
			$where_conditions[] = 'ip_address = %s';
			$where_values[] = sanitize_text_field( $filters['ip_address'] );
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where_conditions[] = 'created_at >= %s';
			$where_values[] = sanitize_text_field( $filters['date_from'] );
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where_conditions[] = 'created_at <= %s';
			$where_values[] = sanitize_text_field( $filters['date_to'] );
		}

		$where_clause = implode( ' AND ', $where_conditions );

		$query = "SELECT * FROM {$wpdb->prefix}" . self::LOG_TABLE . "
				 WHERE $where_clause
				 ORDER BY created_at DESC
				 LIMIT %d OFFSET %d";

		$where_values[] = absint( $limit );
		$where_values[] = absint( $offset );

		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( $query, $where_values );
		}

		return $wpdb->get_results( $query );
	}

	public function get_security_alerts( $status = null ) {
		global $wpdb;

		$where_clause = '1=1';
		$where_values = array();

		if ( $status ) {
			$where_clause = 'status = %s';
			$where_values[] = sanitize_text_field( $status );
		}

		$query = "SELECT * FROM {$wpdb->prefix}" . self::ALERT_TABLE . "
				 WHERE $where_clause
				 ORDER BY severity DESC, created_at DESC";

		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( $query, $where_values );
		}

		return $wpdb->get_results( $query );
	}

	public function update_alert_status( $alert_id, $status, $resolution_notes = '' ) {
		global $wpdb;

		$valid_statuses = array( self::STATUS_OPEN, self::STATUS_INVESTIGATING, self::STATUS_RESOLVED, self::STATUS_FALSE_POSITIVE );

		if ( ! in_array( $status, $valid_statuses, true ) ) {
			return false;
		}

		return $wpdb->update(
			$wpdb->prefix . self::ALERT_TABLE,
			array(
				'status'           => sanitize_text_field( $status ),
				'resolution_notes' => sanitize_textarea_field( $resolution_notes ),
				'assigned_to'      => get_current_user_id(),
			),
			array( 'id' => absint( $alert_id ) ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);
	}

	public function cleanup_old_logs() {
		global $wpdb;

		$cutoff_date = gmdate( 'Y-m-d H:i:s', time() - ( $this->log_retention_days * DAY_IN_SECONDS ) );

		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}" . self::LOG_TABLE . " WHERE created_at < %s",
			$cutoff_date
		) );
	}

	public function get_security_statistics( $days = 7 ) {
		global $wpdb;

		$start_date = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$stats = array();

		$stats['total_events'] = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}" . self::LOG_TABLE . " WHERE created_at >= %s",
			$start_date
		) );

		$stats['critical_events'] = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}" . self::LOG_TABLE . " WHERE created_at >= %s AND severity = %d",
			$start_date,
			self::SEVERITY_CRITICAL
		) );

		$stats['unique_ips'] = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT ip_address) FROM {$wpdb->prefix}" . self::LOG_TABLE . " WHERE created_at >= %s",
			$start_date
		) );

		$stats['open_alerts'] = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}" . self::ALERT_TABLE . " WHERE status = %s",
			self::STATUS_OPEN
		) );

		$stats['events_by_type'] = $wpdb->get_results( $wpdb->prepare(
			"SELECT event_type, COUNT(*) as count FROM {$wpdb->prefix}" . self::LOG_TABLE . "
			WHERE created_at >= %s GROUP BY event_type ORDER BY count DESC",
			$start_date
		), ARRAY_A );

		$stats['events_by_severity'] = $wpdb->get_results( $wpdb->prepare(
			"SELECT severity, COUNT(*) as count FROM {$wpdb->prefix}" . self::LOG_TABLE . "
			WHERE created_at >= %s GROUP BY severity ORDER BY severity DESC",
			$start_date
		), ARRAY_A );

		return $stats;
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

	public function ajax_get_security_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpmatch_security_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 20;
		$filters = isset( $_POST['filters'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['filters'] ) ) : array();

		$offset = ( $page - 1 ) * $per_page;
		$logs = $this->get_security_logs( $per_page, $offset, $filters );

		wp_send_json_success( array(
			'logs'     => $logs,
			'page'     => $page,
			'per_page' => $per_page,
		) );
	}

	public function ajax_update_alert_status() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpmatch_security_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		$alert_id = isset( $_POST['alert_id'] ) ? absint( $_POST['alert_id'] ) : 0;
		$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
		$resolution_notes = isset( $_POST['resolution_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['resolution_notes'] ) ) : '';

		if ( ! $alert_id || ! $status ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters' ) );
		}

		$result = $this->update_alert_status( $alert_id, $status, $resolution_notes );

		if ( $result ) {
			wp_send_json_success( array( 'message' => 'Alert status updated successfully' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to update alert status' ) );
		}
	}

	public function ajax_export_security_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpmatch_security_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		$filters = isset( $_POST['filters'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['filters'] ) ) : array();
		$format = isset( $_POST['format'] ) ? sanitize_text_field( wp_unslash( $_POST['format'] ) ) : 'csv';

		$logs = $this->get_security_logs( 10000, 0, $filters );

		if ( 'csv' === $format ) {
			$this->export_logs_csv( $logs );
		} else {
			$this->export_logs_json( $logs );
		}
	}

	private function export_logs_csv( $logs ) {
		$filename = 'wpmatch-security-logs-' . gmdate( 'Y-m-d-H-i-s' ) . '.csv';

		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );

		fputcsv( $output, array(
			'ID',
			'Event Type',
			'Severity',
			'Message',
			'IP Address',
			'User ID',
			'User Agent',
			'Request URI',
			'Request Method',
			'Created At',
		) );

		foreach ( $logs as $log ) {
			fputcsv( $output, array(
				$log->id,
				$log->event_type,
				$log->severity,
				$log->message,
				$log->ip_address,
				$log->user_id,
				$log->user_agent,
				$log->request_uri,
				$log->request_method,
				$log->created_at,
			) );
		}

		fclose( $output );
		exit;
	}

	private function export_logs_json( $logs ) {
		$filename = 'wpmatch-security-logs-' . gmdate( 'Y-m-d-H-i-s' ) . '.json';

		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo wp_json_encode( $logs, JSON_PRETTY_PRINT );
		exit;
	}
}