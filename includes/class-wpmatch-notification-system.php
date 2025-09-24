<?php
/**
 * WPMatch Comprehensive Notification System
 *
 * Multi-channel notification system with push notifications, email, SMS, and in-app alerts
 *
 * @package WPMatch
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPMatch_Notification_System {

	private static $instance = null;

	const NOTIFICATIONS_TABLE = 'wpmatch_notifications';
	const PREFERENCES_TABLE = 'wpmatch_notification_preferences';
	const PUSH_SUBSCRIPTIONS_TABLE = 'wpmatch_push_subscriptions';

	private $notification_types = array();
	private $channels = array();

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->init_notification_types();
		$this->init_channels();
		$this->init_hooks();
	}

	private function init_notification_types() {
		$this->notification_types = array(
			'new_match'       => array(
				'title'       => __( 'New Match', 'wpmatch' ),
				'description' => __( 'When someone likes you back', 'wpmatch' ),
				'default'     => array( 'push', 'email', 'in_app' ),
				'priority'    => 'high',
			),
			'new_message'     => array(
				'title'       => __( 'New Message', 'wpmatch' ),
				'description' => __( 'When you receive a new message', 'wpmatch' ),
				'default'     => array( 'push', 'in_app' ),
				'priority'    => 'high',
			),
			'incoming_call'   => array(
				'title'       => __( 'Incoming Call', 'wpmatch' ),
				'description' => __( 'When someone is calling you', 'wpmatch' ),
				'default'     => array( 'push', 'in_app' ),
				'priority'    => 'urgent',
			),
			'profile_view'    => array(
				'title'       => __( 'Profile View', 'wpmatch' ),
				'description' => __( 'When someone views your profile', 'wpmatch' ),
				'default'     => array( 'in_app' ),
				'priority'    => 'low',
			),
			'like_received'   => array(
				'title'       => __( 'Someone Liked You', 'wpmatch' ),
				'description' => __( 'When someone likes your profile', 'wpmatch' ),
				'default'     => array( 'push', 'in_app' ),
				'priority'    => 'medium',
			),
			'super_like'      => array(
				'title'       => __( 'Super Like', 'wpmatch' ),
				'description' => __( 'When someone super likes you', 'wpmatch' ),
				'default'     => array( 'push', 'email', 'in_app' ),
				'priority'    => 'high',
			),
			'verification_complete' => array(
				'title'       => __( 'Verification Complete', 'wpmatch' ),
				'description' => __( 'When your verification is approved', 'wpmatch' ),
				'default'     => array( 'push', 'email', 'in_app' ),
				'priority'    => 'medium',
			),
			'subscription_expiry' => array(
				'title'       => __( 'Subscription Expiry', 'wpmatch' ),
				'description' => __( 'When your subscription is about to expire', 'wpmatch' ),
				'default'     => array( 'email', 'in_app' ),
				'priority'    => 'medium',
			),
			'safety_alert'    => array(
				'title'       => __( 'Safety Alert', 'wpmatch' ),
				'description' => __( 'Important safety and security alerts', 'wpmatch' ),
				'default'     => array( 'push', 'email', 'sms', 'in_app' ),
				'priority'    => 'urgent',
			),
			'weekly_digest'   => array(
				'title'       => __( 'Weekly Digest', 'wpmatch' ),
				'description' => __( 'Weekly summary of your activity', 'wpmatch' ),
				'default'     => array( 'email' ),
				'priority'    => 'low',
			),
		);
	}

	private function init_channels() {
		$this->channels = array(
			'push'   => array(
				'name'     => __( 'Push Notifications', 'wpmatch' ),
				'enabled'  => true,
				'handler'  => array( $this, 'send_push_notification' ),
			),
			'email'  => array(
				'name'     => __( 'Email', 'wpmatch' ),
				'enabled'  => true,
				'handler'  => array( $this, 'send_email_notification' ),
			),
			'sms'    => array(
				'name'     => __( 'SMS', 'wpmatch' ),
				'enabled'  => false, // Requires SMS service configuration
				'handler'  => array( $this, 'send_sms_notification' ),
			),
			'in_app' => array(
				'name'     => __( 'In-App', 'wpmatch' ),
				'enabled'  => true,
				'handler'  => array( $this, 'send_in_app_notification' ),
			),
		);
	}

	private function init_hooks() {
		add_action( 'init', array( $this, 'create_notification_tables' ) );

		// Core notification triggers
		add_action( 'wpmatch_send_notification', array( $this, 'send_notification' ), 10, 2 );
		add_action( 'wpmatch_match_created', array( $this, 'handle_new_match' ), 10, 2 );
		add_action( 'wpmatch_message_sent', array( $this, 'handle_new_message' ), 10, 3 );
		add_action( 'wpmatch_profile_viewed', array( $this, 'handle_profile_view' ), 10, 2 );
		add_action( 'wpmatch_like_received', array( $this, 'handle_like_received' ), 10, 2 );

		// AJAX handlers
		add_action( 'wp_ajax_wpmatch_get_notifications', array( $this, 'ajax_get_notifications' ) );
		add_action( 'wp_ajax_wpmatch_mark_notification_read', array( $this, 'ajax_mark_notification_read' ) );
		add_action( 'wp_ajax_wpmatch_update_notification_preferences', array( $this, 'ajax_update_preferences' ) );
		add_action( 'wp_ajax_wpmatch_subscribe_push', array( $this, 'ajax_subscribe_push' ) );
		add_action( 'wp_ajax_wpmatch_unsubscribe_push', array( $this, 'ajax_unsubscribe_push' ) );
		add_action( 'wp_ajax_wpmatch_clear_all_notifications', array( $this, 'ajax_clear_all_notifications' ) );

		// Frontend scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_notification_scripts' ) );

		// Scheduled tasks
		add_action( 'wpmatch_send_weekly_digest', array( $this, 'send_weekly_digest' ) );
		add_action( 'wpmatch_cleanup_notifications', array( $this, 'cleanup_old_notifications' ) );

		// Schedule events
		if ( ! wp_next_scheduled( 'wpmatch_send_weekly_digest' ) ) {
			wp_schedule_event( strtotime( 'next monday 9:00' ), 'weekly', 'wpmatch_send_weekly_digest' );
		}

		if ( ! wp_next_scheduled( 'wpmatch_cleanup_notifications' ) ) {
			wp_schedule_event( time(), 'daily', 'wpmatch_cleanup_notifications' );
		}
	}

	public function create_notification_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Notifications table
		$notifications_table = $wpdb->prefix . self::NOTIFICATIONS_TABLE;
		$notifications_sql = "CREATE TABLE IF NOT EXISTS $notifications_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			type varchar(50) NOT NULL,
			title varchar(255) NOT NULL,
			message text NOT NULL,
			data longtext DEFAULT NULL,
			channels varchar(255) NOT NULL,
			priority varchar(20) NOT NULL DEFAULT 'medium',
			is_read tinyint(1) NOT NULL DEFAULT 0,
			read_at datetime DEFAULT NULL,
			expires_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY type (type),
			KEY is_read (is_read),
			KEY priority (priority),
			KEY created_at (created_at),
			KEY expires_at (expires_at)
		) $charset_collate;";

		// Notification preferences table
		$preferences_table = $wpdb->prefix . self::PREFERENCES_TABLE;
		$preferences_sql = "CREATE TABLE IF NOT EXISTS $preferences_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			notification_type varchar(50) NOT NULL,
			channels longtext NOT NULL,
			enabled tinyint(1) NOT NULL DEFAULT 1,
			quiet_hours_start time DEFAULT NULL,
			quiet_hours_end time DEFAULT NULL,
			timezone varchar(50) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_type (user_id, notification_type),
			KEY enabled (enabled)
		) $charset_collate;";

		// Push subscriptions table
		$push_table = $wpdb->prefix . self::PUSH_SUBSCRIPTIONS_TABLE;
		$push_sql = "CREATE TABLE IF NOT EXISTS $push_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			endpoint varchar(500) NOT NULL,
			p256dh_key varchar(255) NOT NULL,
			auth_key varchar(255) NOT NULL,
			user_agent text DEFAULT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			last_used datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_endpoint (user_id, endpoint(191)),
			KEY is_active (is_active),
			KEY last_used (last_used)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $notifications_sql );
		dbDelta( $preferences_sql );
		dbDelta( $push_sql );
	}

	public function send_notification( $user_id, $notification_data ) {
		if ( ! $user_id || ! is_array( $notification_data ) ) {
			return false;
		}

		$type = $notification_data['type'] ?? 'general';
		$title = $notification_data['title'] ?? '';
		$message = $notification_data['message'] ?? '';
		$data = $notification_data['data'] ?? array();
		$priority = $notification_data['priority'] ?? 'medium';
		$expires_at = $notification_data['expires_at'] ?? null;

		// Get user preferences for this notification type
		$user_preferences = $this->get_user_preferences( $user_id, $type );

		if ( ! $user_preferences['enabled'] ) {
			return false; // User has disabled this notification type
		}

		// Check quiet hours
		if ( $this->is_quiet_hours( $user_id ) && 'urgent' !== $priority ) {
			// Store notification for later delivery
			return $this->store_notification( $user_id, $type, $title, $message, $data, array(), $priority, $expires_at );
		}

		$channels_to_send = array_intersect( $user_preferences['channels'], array_keys( $this->channels ) );
		$delivered_channels = array();

		// Send through each enabled channel
		foreach ( $channels_to_send as $channel ) {
			if ( ! $this->channels[ $channel ]['enabled'] ) {
				continue;
			}

			$handler = $this->channels[ $channel ]['handler'];
			if ( is_callable( $handler ) ) {
				$result = call_user_func( $handler, $user_id, $title, $message, $data, $priority );
				if ( $result ) {
					$delivered_channels[] = $channel;
				}
			}
		}

		// Store notification record
		$notification_id = $this->store_notification(
			$user_id,
			$type,
			$title,
			$message,
			$data,
			$delivered_channels,
			$priority,
			$expires_at
		);

		return $notification_id;
	}

	private function store_notification( $user_id, $type, $title, $message, $data, $channels, $priority, $expires_at ) {
		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . self::NOTIFICATIONS_TABLE,
			array(
				'user_id'    => $user_id,
				'type'       => $type,
				'title'      => $title,
				'message'    => $message,
				'data'       => wp_json_encode( $data ),
				'channels'   => implode( ',', $channels ),
				'priority'   => $priority,
				'expires_at' => $expires_at,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	public function send_push_notification( $user_id, $title, $message, $data, $priority ) {
		$subscriptions = $this->get_user_push_subscriptions( $user_id );

		if ( empty( $subscriptions ) ) {
			return false;
		}

		$payload = array(
			'title'   => $title,
			'body'    => $message,
			'icon'    => get_site_icon_url( 192 ),
			'badge'   => get_site_icon_url( 96 ),
			'data'    => $data,
			'actions' => $this->get_notification_actions( $data['type'] ?? 'general' ),
			'tag'     => $data['type'] ?? 'general',
			'renotify' => 'urgent' === $priority,
		);

		$success_count = 0;

		foreach ( $subscriptions as $subscription ) {
			if ( $this->send_web_push( $subscription, $payload ) ) {
				$success_count++;
				$this->update_subscription_usage( $subscription['id'] );
			} else {
				$this->deactivate_subscription( $subscription['id'] );
			}
		}

		return $success_count > 0;
	}

	public function send_email_notification( $user_id, $title, $message, $data, $priority ) {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}

		$template = $this->get_email_template( $data['type'] ?? 'general' );
		$email_content = $this->build_email_content( $title, $message, $data, $template );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
		);

		// Add priority headers for urgent notifications
		if ( 'urgent' === $priority ) {
			$headers[] = 'X-Priority: 1';
			$headers[] = 'X-MSMail-Priority: High';
		}

		return wp_mail( $user->user_email, $title, $email_content, $headers );
	}

	public function send_sms_notification( $user_id, $title, $message, $data, $priority ) {
		// SMS implementation would integrate with services like Twilio, AWS SNS, etc.
		$phone_number = get_user_meta( $user_id, 'wpmatch_phone_number', true );

		if ( ! $phone_number ) {
			return false;
		}

		// For demonstration - in production, integrate with actual SMS service
		$sms_content = sprintf( '%s: %s', $title, $message );

		// Mock SMS sending
		do_action( 'wpmatch_send_sms', $phone_number, $sms_content, $priority );

		return true;
	}

	public function send_in_app_notification( $user_id, $title, $message, $data, $priority ) {
		// In-app notifications are stored in database and shown in UI
		// Real-time delivery would be handled by WebSocket system
		do_action( 'wpmatch_realtime_notification', $user_id, array(
			'title'    => $title,
			'message'  => $message,
			'data'     => $data,
			'priority' => $priority,
		) );

		return true;
	}

	private function send_web_push( $subscription, $payload ) {
		// Web Push implementation using VAPID keys
		$vapid_keys = get_option( 'wpmatch_vapid_keys', array() );

		if ( empty( $vapid_keys['public_key'] ) || empty( $vapid_keys['private_key'] ) ) {
			return false;
		}

		$headers = array(
			'Content-Type'  => 'application/json',
			'TTL'           => '3600',
			'Urgency'       => $this->get_push_urgency( $payload['data']['priority'] ?? 'medium' ),
		);

		// Add VAPID authentication
		$headers['Authorization'] = $this->generate_vapid_auth( $subscription['endpoint'], $vapid_keys );

		$response = wp_remote_post( $subscription['endpoint'], array(
			'body'    => wp_json_encode( $payload ),
			'headers' => $headers,
			'timeout' => 10,
		) );

		return ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) < 400;
	}

	private function generate_vapid_auth( $endpoint, $vapid_keys ) {
		// Generate VAPID JWT token for push authentication
		// This is a simplified version - production should use proper JWT library
		$header = array( 'typ' => 'JWT', 'alg' => 'ES256' );
		$payload = array(
			'aud' => parse_url( $endpoint, PHP_URL_SCHEME ) . '://' . parse_url( $endpoint, PHP_URL_HOST ),
			'exp' => time() + 3600,
			'sub' => 'mailto:' . get_option( 'admin_email' ),
		);

		$jwt = base64_encode( wp_json_encode( $header ) ) . '.' . base64_encode( wp_json_encode( $payload ) );
		// In production, sign with private key

		return 'vapid t=' . $jwt . ', k=' . $vapid_keys['public_key'];
	}

	private function get_push_urgency( $priority ) {
		switch ( $priority ) {
			case 'urgent':
				return 'high';
			case 'high':
				return 'normal';
			case 'medium':
				return 'normal';
			case 'low':
			default:
				return 'low';
		}
	}

	public function get_user_notifications( $user_id, $limit = 50, $offset = 0, $unread_only = false ) {
		global $wpdb;

		$where_conditions = array( 'user_id = %d' );
		$where_values = array( $user_id );

		if ( $unread_only ) {
			$where_conditions[] = 'is_read = 0';
		}

		// Exclude expired notifications
		$where_conditions[] = '(expires_at IS NULL OR expires_at > NOW())';

		$where_clause = implode( ' AND ', $where_conditions );

		$notifications = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}wpmatch_notifications
			WHERE $where_clause
			ORDER BY created_at DESC
			LIMIT %d OFFSET %d",
			array_merge( $where_values, array( $limit, $offset ) )
		) );

		return array_map( array( $this, 'format_notification' ), $notifications );
	}

	private function format_notification( $notification ) {
		return array(
			'id'         => $notification->id,
			'type'       => $notification->type,
			'title'      => $notification->title,
			'message'    => $notification->message,
			'data'       => json_decode( $notification->data, true ),
			'priority'   => $notification->priority,
			'is_read'    => (bool) $notification->is_read,
			'read_at'    => $notification->read_at,
			'created_at' => $notification->created_at,
			'timestamp'  => strtotime( $notification->created_at ),
		);
	}

	public function mark_notification_read( $notification_id, $user_id ) {
		global $wpdb;

		return $wpdb->update(
			$wpdb->prefix . self::NOTIFICATIONS_TABLE,
			array(
				'is_read' => 1,
				'read_at' => current_time( 'mysql' ),
			),
			array(
				'id'      => $notification_id,
				'user_id' => $user_id,
			),
			array( '%d', '%s' ),
			array( '%d', '%d' )
		);
	}

	public function get_user_preferences( $user_id, $notification_type ) {
		global $wpdb;

		$preferences = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}wpmatch_notification_preferences
			WHERE user_id = %d AND notification_type = %s",
			$user_id,
			$notification_type
		) );

		if ( $preferences ) {
			return array(
				'enabled'  => (bool) $preferences->enabled,
				'channels' => json_decode( $preferences->channels, true ),
			);
		}

		// Return defaults if no preferences set
		$default_config = $this->notification_types[ $notification_type ] ?? array();
		return array(
			'enabled'  => true,
			'channels' => $default_config['default'] ?? array( 'in_app' ),
		);
	}

	public function update_user_preferences( $user_id, $preferences ) {
		global $wpdb;

		foreach ( $preferences as $notification_type => $settings ) {
			$channels = $settings['channels'] ?? array();
			$enabled = isset( $settings['enabled'] ) ? (bool) $settings['enabled'] : true;

			$wpdb->replace(
				$wpdb->prefix . self::PREFERENCES_TABLE,
				array(
					'user_id'           => $user_id,
					'notification_type' => $notification_type,
					'channels'          => wp_json_encode( $channels ),
					'enabled'           => $enabled,
				),
				array( '%d', '%s', '%s', '%d' )
			);
		}

		return true;
	}

	public function subscribe_push_notifications( $user_id, $subscription_data ) {
		global $wpdb;

		return $wpdb->replace(
			$wpdb->prefix . self::PUSH_SUBSCRIPTIONS_TABLE,
			array(
				'user_id'    => $user_id,
				'endpoint'   => $subscription_data['endpoint'],
				'p256dh_key' => $subscription_data['keys']['p256dh'],
				'auth_key'   => $subscription_data['keys']['auth'],
				'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
				'is_active'  => 1,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d' )
		);
	}

	private function get_user_push_subscriptions( $user_id ) {
		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}wpmatch_push_subscriptions
			WHERE user_id = %d AND is_active = 1",
			$user_id
		), ARRAY_A );
	}

	private function update_subscription_usage( $subscription_id ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . self::PUSH_SUBSCRIPTIONS_TABLE,
			array( 'last_used' => current_time( 'mysql' ) ),
			array( 'id' => $subscription_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	private function deactivate_subscription( $subscription_id ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . self::PUSH_SUBSCRIPTIONS_TABLE,
			array( 'is_active' => 0 ),
			array( 'id' => $subscription_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	private function is_quiet_hours( $user_id ) {
		global $wpdb;

		$quiet_hours = $wpdb->get_row( $wpdb->prepare(
			"SELECT quiet_hours_start, quiet_hours_end, timezone
			FROM {$wpdb->prefix}wpmatch_notification_preferences
			WHERE user_id = %d AND quiet_hours_start IS NOT NULL
			LIMIT 1",
			$user_id
		) );

		if ( ! $quiet_hours ) {
			return false;
		}

		$timezone = $quiet_hours->timezone ?: 'UTC';
		$user_time = new DateTime( 'now', new DateTimeZone( $timezone ) );
		$current_time = $user_time->format( 'H:i:s' );

		return $current_time >= $quiet_hours->quiet_hours_start && $current_time <= $quiet_hours->quiet_hours_end;
	}

	private function get_notification_actions( $type ) {
		$actions = array();

		switch ( $type ) {
			case 'new_message':
				$actions[] = array(
					'action' => 'reply',
					'title'  => __( 'Reply', 'wpmatch' ),
				);
				$actions[] = array(
					'action' => 'view',
					'title'  => __( 'View', 'wpmatch' ),
				);
				break;

			case 'new_match':
				$actions[] = array(
					'action' => 'message',
					'title'  => __( 'Message', 'wpmatch' ),
				);
				$actions[] = array(
					'action' => 'view_profile',
					'title'  => __( 'View Profile', 'wpmatch' ),
				);
				break;

			case 'incoming_call':
				$actions[] = array(
					'action' => 'answer',
					'title'  => __( 'Answer', 'wpmatch' ),
				);
				$actions[] = array(
					'action' => 'decline',
					'title'  => __( 'Decline', 'wpmatch' ),
				);
				break;
		}

		return $actions;
	}

	private function get_email_template( $type ) {
		$templates = array(
			'new_match'   => 'email-new-match',
			'new_message' => 'email-new-message',
			'weekly_digest' => 'email-weekly-digest',
		);

		return $templates[ $type ] ?? 'email-default';
	}

	private function build_email_content( $title, $message, $data, $template ) {
		$site_name = get_bloginfo( 'name' );
		$site_url = get_site_url();

		ob_start();
		?>
		<html>
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php echo esc_html( $title ); ?></title>
		</head>
		<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
			<div style="background: #e91e63; color: white; padding: 20px; border-radius: 5px 5px 0 0; text-align: center;">
				<h1 style="margin: 0; font-size: 24px;"><?php echo esc_html( $title ); ?></h1>
			</div>

			<div style="background: #f9f9f9; padding: 30px; border: 1px solid #ddd; border-top: none;">
				<p><?php echo esc_html( $message ); ?></p>

				<?php if ( isset( $data['action_url'] ) ) : ?>
					<div style="text-align: center; margin: 30px 0;">
						<a href="<?php echo esc_url( $data['action_url'] ); ?>"
						   style="background: #e91e63; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">
							<?php echo esc_html( $data['action_text'] ?? __( 'View Now', 'wpmatch' ) ); ?>
						</a>
					</div>
				<?php endif; ?>

				<hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">

				<p style="font-size: 12px; color: #666;">
					<?php echo esc_html( sprintf( __( 'This email was sent from %s. To manage your notification preferences, visit your account settings.', 'wpmatch' ), $site_name ) ); ?>
				</p>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	// Event handlers
	public function handle_new_match( $user1_id, $user2_id ) {
		// Send notification to both users
		$user1 = get_user_by( 'id', $user1_id );
		$user2 = get_user_by( 'id', $user2_id );

		if ( $user1 && $user2 ) {
			$this->send_notification( $user1_id, array(
				'type'    => 'new_match',
				'title'   => __( 'It\'s a Match!', 'wpmatch' ),
				'message' => sprintf( __( 'You and %s liked each other!', 'wpmatch' ), $user2->display_name ),
				'data'    => array(
					'match_user_id' => $user2_id,
					'action_url'    => get_site_url() . '/chat/' . min( $user1_id, $user2_id ) . '_' . max( $user1_id, $user2_id ),
					'action_text'   => __( 'Start Chatting', 'wpmatch' ),
				),
				'priority' => 'high',
			) );

			$this->send_notification( $user2_id, array(
				'type'    => 'new_match',
				'title'   => __( 'It\'s a Match!', 'wpmatch' ),
				'message' => sprintf( __( 'You and %s liked each other!', 'wpmatch' ), $user1->display_name ),
				'data'    => array(
					'match_user_id' => $user1_id,
					'action_url'    => get_site_url() . '/chat/' . min( $user1_id, $user2_id ) . '_' . max( $user1_id, $user2_id ),
					'action_text'   => __( 'Start Chatting', 'wpmatch' ),
				),
				'priority' => 'high',
			) );
		}
	}

	public function handle_new_message( $sender_id, $recipient_id, $message_content ) {
		$sender = get_user_by( 'id', $sender_id );

		if ( $sender ) {
			$this->send_notification( $recipient_id, array(
				'type'    => 'new_message',
				'title'   => sprintf( __( 'New message from %s', 'wpmatch' ), $sender->display_name ),
				'message' => wp_trim_words( $message_content, 10 ),
				'data'    => array(
					'sender_id'  => $sender_id,
					'action_url' => get_site_url() . '/chat/' . min( $sender_id, $recipient_id ) . '_' . max( $sender_id, $recipient_id ),
					'action_text' => __( 'Reply', 'wpmatch' ),
				),
				'priority' => 'high',
			) );
		}
	}

	public function handle_profile_view( $viewer_id, $viewed_user_id ) {
		$viewer = get_user_by( 'id', $viewer_id );

		if ( $viewer ) {
			$this->send_notification( $viewed_user_id, array(
				'type'    => 'profile_view',
				'title'   => __( 'Someone viewed your profile', 'wpmatch' ),
				'message' => sprintf( __( '%s checked out your profile', 'wpmatch' ), $viewer->display_name ),
				'data'    => array(
					'viewer_id'  => $viewer_id,
					'action_url' => get_site_url() . '/profile/' . $viewer_id,
					'action_text' => __( 'View Profile', 'wpmatch' ),
				),
				'priority' => 'low',
			) );
		}
	}

	public function handle_like_received( $liker_id, $liked_user_id ) {
		$liker = get_user_by( 'id', $liker_id );

		if ( $liker ) {
			$this->send_notification( $liked_user_id, array(
				'type'    => 'like_received',
				'title'   => __( 'Someone likes you!', 'wpmatch' ),
				'message' => sprintf( __( '%s liked your profile', 'wpmatch' ), $liker->display_name ),
				'data'    => array(
					'liker_id'   => $liker_id,
					'action_url' => get_site_url() . '/discover',
					'action_text' => __( 'See Who', 'wpmatch' ),
				),
				'priority' => 'medium',
			) );
		}
	}

	public function send_weekly_digest() {
		$users = get_users( array(
			'meta_key'   => 'wpmatch_profile_complete',
			'meta_value' => '1',
		) );

		foreach ( $users as $user ) {
			$digest_data = $this->generate_weekly_digest_data( $user->ID );

			if ( ! empty( $digest_data ) ) {
				$this->send_notification( $user->ID, array(
					'type'    => 'weekly_digest',
					'title'   => __( 'Your Weekly WPMatch Summary', 'wpmatch' ),
					'message' => $this->format_digest_message( $digest_data ),
					'data'    => $digest_data,
					'priority' => 'low',
				) );
			}
		}
	}

	private function generate_weekly_digest_data( $user_id ) {
		global $wpdb;

		$week_ago = gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );

		$data = array(
			'profile_views' => $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wpmatch_profile_views WHERE viewed_user_id = %d AND created_at >= %s",
				$user_id,
				$week_ago
			) ),
			'likes_received' => $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wpmatch_swipes WHERE target_user_id = %d AND action = 'like' AND created_at >= %s",
				$user_id,
				$week_ago
			) ),
			'new_matches' => $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wpmatch_matches WHERE (user1_id = %d OR user2_id = %d) AND created_at >= %s",
				$user_id,
				$user_id,
				$week_ago
			) ),
			'messages_sent' => $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wpmatch_chat_messages WHERE sender_id = %d AND created_at >= %s",
				$user_id,
				$week_ago
			) ),
		);

		return array_filter( $data ); // Remove zero values
	}

	private function format_digest_message( $data ) {
		$messages = array();

		if ( isset( $data['profile_views'] ) ) {
			$messages[] = sprintf( _n( '%d profile view', '%d profile views', $data['profile_views'], 'wpmatch' ), $data['profile_views'] );
		}

		if ( isset( $data['likes_received'] ) ) {
			$messages[] = sprintf( _n( '%d like received', '%d likes received', $data['likes_received'], 'wpmatch' ), $data['likes_received'] );
		}

		if ( isset( $data['new_matches'] ) ) {
			$messages[] = sprintf( _n( '%d new match', '%d new matches', $data['new_matches'], 'wpmatch' ), $data['new_matches'] );
		}

		return implode( ', ', $messages );
	}

	public function cleanup_old_notifications() {
		global $wpdb;

		// Remove notifications older than 30 days
		$thirty_days_ago = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );

		$wpdb->delete(
			$wpdb->prefix . self::NOTIFICATIONS_TABLE,
			array( 'created_at <' => $thirty_days_ago ),
			array( '%s' )
		);

		// Remove expired notifications
		$wpdb->delete(
			$wpdb->prefix . self::NOTIFICATIONS_TABLE,
			array( 'expires_at <' => current_time( 'mysql' ) ),
			array( '%s' )
		);

		// Remove inactive push subscriptions
		$ninety_days_ago = gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS );

		$wpdb->delete(
			$wpdb->prefix . self::PUSH_SUBSCRIPTIONS_TABLE,
			array(
				'is_active'     => 0,
				'last_used <'   => $ninety_days_ago,
			),
			array( '%d', '%s' )
		);
	}

	public function enqueue_notification_scripts() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		wp_enqueue_script( 'wpmatch-notifications',
			plugins_url( 'public/js/wpmatch-notifications.js', dirname( __FILE__ ) ),
			array( 'jquery' ), '1.0.0', true
		);

		wp_localize_script( 'wpmatch-notifications', 'wpMatchNotifications', array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'wpmatch_notifications_nonce' ),
			'userId'      => get_current_user_id(),
			'vapidPublicKey' => get_option( 'wpmatch_vapid_public_key', '' ),
			'strings'     => array(
				'permissionDenied'    => __( 'Notification permission denied', 'wpmatch' ),
				'subscriptionFailed'  => __( 'Failed to subscribe to notifications', 'wpmatch' ),
				'subscriptionSuccess' => __( 'Successfully subscribed to notifications', 'wpmatch' ),
			),
		) );
	}

	// AJAX handlers
	public function ajax_get_notifications() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpmatch_notifications_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => 'User not logged in' ) );
		}

		$limit = absint( $_POST['limit'] ?? 20 );
		$offset = absint( $_POST['offset'] ?? 0 );
		$unread_only = isset( $_POST['unread_only'] ) && $_POST['unread_only'] === 'true';

		$notifications = $this->get_user_notifications( $user_id, $limit, $offset, $unread_only );

		wp_send_json_success( array(
			'notifications' => $notifications,
			'count'         => count( $notifications ),
		) );
	}

	public function ajax_mark_notification_read() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpmatch_notifications_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		$user_id = get_current_user_id();
		$notification_id = absint( $_POST['notification_id'] ?? 0 );

		if ( ! $user_id || ! $notification_id ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters' ) );
		}

		$result = $this->mark_notification_read( $notification_id, $user_id );

		if ( $result ) {
			wp_send_json_success();
		} else {
			wp_send_json_error( array( 'message' => 'Failed to mark notification as read' ) );
		}
	}

	public function ajax_update_preferences() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpmatch_notifications_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		$user_id = get_current_user_id();
		$preferences = isset( $_POST['preferences'] ) ? array_map( 'sanitize_text_field', $_POST['preferences'] ) : array();

		if ( ! $user_id || empty( $preferences ) ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters' ) );
		}

		$result = $this->update_user_preferences( $user_id, $preferences );

		if ( $result ) {
			wp_send_json_success( array( 'message' => 'Preferences updated successfully' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to update preferences' ) );
		}
	}

	public function ajax_subscribe_push() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpmatch_notifications_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		$user_id = get_current_user_id();
		$subscription = isset( $_POST['subscription'] ) ? array_map( 'sanitize_text_field', $_POST['subscription'] ) : array();

		if ( ! $user_id || empty( $subscription ) ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters' ) );
		}

		$result = $this->subscribe_push_notifications( $user_id, $subscription );

		if ( $result ) {
			wp_send_json_success( array( 'message' => 'Push notifications enabled' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to enable push notifications' ) );
		}
	}

	public function ajax_clear_all_notifications() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wpmatch_notifications_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => 'User not logged in' ) );
		}

		global $wpdb;

		$result = $wpdb->update(
			$wpdb->prefix . self::NOTIFICATIONS_TABLE,
			array( 'is_read' => 1, 'read_at' => current_time( 'mysql' ) ),
			array( 'user_id' => $user_id, 'is_read' => 0 ),
			array( '%d', '%s' ),
			array( '%d', '%d' )
		);

		if ( $result !== false ) {
			wp_send_json_success( array( 'message' => 'All notifications marked as read' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to clear notifications' ) );
		}
	}
}