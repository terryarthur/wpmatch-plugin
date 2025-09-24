<?php
/**
 * WPMatch Privacy Manager
 *
 * Handles GDPR compliance, consent management, and privacy controls.
 *
 * @package WPMatch
 * @since   1.0.0
 */

// Security check.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPMatch Privacy Manager Class
 *
 * Manages GDPR compliance, user consent, data export/deletion, and privacy controls.
 */
class WPMatch_Privacy_Manager {

	/**
	 * Data types we collect and process.
	 *
	 * @var array
	 */
	private static $data_types = array(
		'profile_data'   => array(
			'name'        => 'Profile Information',
			'description' => 'Basic profile data including photos, preferences, and biography',
			'retention'   => '2 years after account deletion',
			'purpose'     => 'Matching and profile display',
		),
		'matching_data'  => array(
			'name'        => 'Matching Preferences',
			'description' => 'Age range, location, interests, and matching criteria',
			'retention'   => '1 year after last activity',
			'purpose'     => 'Algorithmic matching and recommendations',
		),
		'communication'  => array(
			'name'        => 'Messages and Communications',
			'description' => 'Chat messages, video calls, and communication history',
			'retention'   => '3 years for safety and moderation',
			'purpose'     => 'Communication between users and safety monitoring',
		),
		'location_data'  => array(
			'name'        => 'Location Information',
			'description' => 'GPS coordinates, city, and proximity data',
			'retention'   => '30 days unless specifically saved',
			'purpose'     => 'Location-based matching and distance calculations',
		),
		'payment_data'   => array(
			'name'        => 'Payment Information',
			'description' => 'Subscription status, payment history (not card details)',
			'retention'   => '7 years for tax and legal compliance',
			'purpose'     => 'Billing, subscription management, and fraud prevention',
		),
		'analytics_data' => array(
			'name'        => 'Usage Analytics',
			'description' => 'App usage patterns, feature interactions, and performance data',
			'retention'   => '2 years in aggregated form',
			'purpose'     => 'Service improvement and feature development',
		),
	);

	/**
	 * Initialize the privacy manager.
	 */
	public static function init() {
		// WordPress privacy hooks.
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_data_exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_data_erasers' ) );
		add_action( 'wp_privacy_personal_data_export_file', array( __CLASS__, 'privacy_export_file_generated' ), 10, 3 );

		// Admin interface.
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_privacy_settings' ) );

		// Frontend consent management.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_consent_scripts' ) );
		add_action( 'wp_ajax_wpmatch_update_consent', array( __CLASS__, 'handle_consent_update' ) );
		add_action( 'wp_ajax_nopriv_wpmatch_update_consent', array( __CLASS__, 'handle_consent_update' ) );

		// Cookie consent banner.
		add_action( 'wp_footer', array( __CLASS__, 'render_cookie_consent_banner' ) );

		// Data retention cleanup.
		add_action( 'wpmatch_daily_cron', array( __CLASS__, 'cleanup_expired_data' ) );

		// User data deletion hooks.
		add_action( 'delete_user', array( __CLASS__, 'handle_user_deletion' ) );
	}

	/**
	 * Register WordPress privacy data exporters.
	 *
	 * @param array $exporters Existing exporters.
	 * @return array
	 */
	public static function register_data_exporters( $exporters ) {
		$exporters['wpmatch-profile'] = array(
			'exporter_friendly_name' => esc_html__( 'WPMatch Profile Data', 'wpmatch' ),
			'callback'               => array( __CLASS__, 'export_profile_data' ),
		);

		$exporters['wpmatch-messages'] = array(
			'exporter_friendly_name' => esc_html__( 'WPMatch Messages', 'wpmatch' ),
			'callback'               => array( __CLASS__, 'export_messages_data' ),
		);

		$exporters['wpmatch-matches'] = array(
			'exporter_friendly_name' => esc_html__( 'WPMatch Matches and Interactions', 'wpmatch' ),
			'callback'               => array( __CLASS__, 'export_matches_data' ),
		);

		$exporters['wpmatch-payments'] = array(
			'exporter_friendly_name' => esc_html__( 'WPMatch Payment History', 'wpmatch' ),
			'callback'               => array( __CLASS__, 'export_payment_data' ),
		);

		return $exporters;
	}

	/**
	 * Register WordPress privacy data erasers.
	 *
	 * @param array $erasers Existing erasers.
	 * @return array
	 */
	public static function register_data_erasers( $erasers ) {
		$erasers['wpmatch-profile'] = array(
			'eraser_friendly_name' => esc_html__( 'WPMatch Profile Data', 'wpmatch' ),
			'callback'             => array( __CLASS__, 'erase_profile_data' ),
		);

		$erasers['wpmatch-messages'] = array(
			'eraser_friendly_name' => esc_html__( 'WPMatch Messages', 'wpmatch' ),
			'callback'             => array( __CLASS__, 'erase_messages_data' ),
		);

		$erasers['wpmatch-matches'] = array(
			'eraser_friendly_name' => esc_html__( 'WPMatch Matches and Interactions', 'wpmatch' ),
			'callback'             => array( __CLASS__, 'erase_matches_data' ),
		);

		return $erasers;
	}

	/**
	 * Export user profile data.
	 *
	 * @param string $email_address User email.
	 * @param int    $page Page number.
	 * @return array
	 */
	public static function export_profile_data( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$export_items = array();

		// Basic profile data.
		$profile_data = array(
			array(
				'name'  => esc_html__( 'Display Name', 'wpmatch' ),
				'value' => $user->display_name,
			),
			array(
				'name'  => esc_html__( 'Email', 'wpmatch' ),
				'value' => $user->user_email,
			),
			array(
				'name'  => esc_html__( 'Registration Date', 'wpmatch' ),
				'value' => $user->user_registered,
			),
		);

		// WPMatch specific profile fields.
		$wpmatch_fields = array(
			'_wpmatch_age'                 => esc_html__( 'Age', 'wpmatch' ),
			'_wpmatch_location'            => esc_html__( 'Location', 'wpmatch' ),
			'_wpmatch_bio'                 => esc_html__( 'Biography', 'wpmatch' ),
			'_wpmatch_interests'           => esc_html__( 'Interests', 'wpmatch' ),
			'_wpmatch_looking_for'         => esc_html__( 'Looking For', 'wpmatch' ),
			'_wpmatch_verification_status' => esc_html__( 'Verification Status', 'wpmatch' ),
		);

		foreach ( $wpmatch_fields as $meta_key => $label ) {
			$value = get_user_meta( $user->ID, $meta_key, true );
			if ( ! empty( $value ) ) {
				$profile_data[] = array(
					'name'  => $label,
					'value' => is_array( $value ) ? wp_json_encode( $value ) : $value,
				);
			}
		}

		$export_items[] = array(
			'group_id'    => 'wpmatch-profile',
			'group_label' => esc_html__( 'WPMatch Profile', 'wpmatch' ),
			'item_id'     => 'wpmatch-profile-' . $user->ID,
			'data'        => $profile_data,
		);

		return array(
			'data' => $export_items,
			'done' => true,
		);
	}

	/**
	 * Export user messages data.
	 *
	 * @param string $email_address User email.
	 * @param int    $page Page number.
	 * @return array
	 */
	public static function export_messages_data( $email_address, $page = 1 ) {
		global $wpdb;

		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$export_items   = array();
		$messages_table = $wpdb->prefix . 'wpmatch_messages';

		// Check if messages table exists.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $messages_table ) ) !== $messages_table ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$limit  = 500; // Process in batches.
		$offset = ( $page - 1 ) * $limit;

		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->prefix}wpmatch_messages`
				WHERE sender_id = %d OR receiver_id = %d
				ORDER BY created_at ASC
				LIMIT %d OFFSET %d",
				$user->ID,
				$user->ID,
				$limit,
				$offset
			)
		);

		foreach ( $messages as $message ) {
			$message_data = array(
				array(
					'name'  => esc_html__( 'Date', 'wpmatch' ),
					'value' => $message->created_at,
				),
				array(
					'name'  => esc_html__( 'Type', 'wpmatch' ),
					'value' => $message->sender_id === $user->ID ? esc_html__( 'Sent', 'wpmatch' ) : esc_html__( 'Received', 'wpmatch' ),
				),
				array(
					'name'  => esc_html__( 'Content', 'wpmatch' ),
					'value' => $message->content,
				),
			);

			$export_items[] = array(
				'group_id'    => 'wpmatch-messages',
				'group_label' => esc_html__( 'WPMatch Messages', 'wpmatch' ),
				'item_id'     => 'wpmatch-message-' . $message->id,
				'data'        => $message_data,
			);
		}

		$done = count( $messages ) < $limit;

		return array(
			'data' => $export_items,
			'done' => $done,
		);
	}

	/**
	 * Export user matches and interactions data.
	 *
	 * @param string $email_address User email.
	 * @param int    $page Page number.
	 * @return array
	 */
	public static function export_matches_data( $email_address, $page = 1 ) {
		global $wpdb;

		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$export_items = array();

		// Export matches.
		$matches_table = $wpdb->prefix . 'wpmatch_matches';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $matches_table ) ) === $matches_table ) {
			$matches = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM `{$wpdb->prefix}wpmatch_matches` WHERE user_id = %d OR matched_user_id = %d",
					$user->ID,
					$user->ID
				)
			);

			foreach ( $matches as $match ) {
				$match_data = array(
					array(
						'name'  => esc_html__( 'Match Date', 'wpmatch' ),
						'value' => $match->created_at,
					),
					array(
						'name'  => esc_html__( 'Match Status', 'wpmatch' ),
						'value' => $match->status,
					),
					array(
						'name'  => esc_html__( 'Compatibility Score', 'wpmatch' ),
						'value' => $match->compatibility_score . '%',
					),
				);

				$export_items[] = array(
					'group_id'    => 'wpmatch-matches',
					'group_label' => esc_html__( 'WPMatch Matches', 'wpmatch' ),
					'item_id'     => 'wpmatch-match-' . $match->id,
					'data'        => $match_data,
				);
			}
		}

		return array(
			'data' => $export_items,
			'done' => true,
		);
	}

	/**
	 * Export user payment data.
	 *
	 * @param string $email_address User email.
	 * @param int    $page Page number.
	 * @return array
	 */
	public static function export_payment_data( $email_address, $page = 1 ) {
		global $wpdb;

		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$export_items = array();

		// Check for subscription data.
		$subscriptions_table = $wpdb->prefix . 'wpmatch_subscriptions';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $subscriptions_table ) ) === $subscriptions_table ) {
			$subscriptions = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM `{$wpdb->prefix}wpmatch_subscriptions` WHERE user_id = %d",
					$user->ID
				)
			);

			foreach ( $subscriptions as $subscription ) {
				$subscription_data = array(
					array(
						'name'  => esc_html__( 'Subscription Start', 'wpmatch' ),
						'value' => $subscription->created_at,
					),
					array(
						'name'  => esc_html__( 'Status', 'wpmatch' ),
						'value' => $subscription->status,
					),
					array(
						'name'  => esc_html__( 'Current Period', 'wpmatch' ),
						'value' => $subscription->current_period_start . ' to ' . $subscription->current_period_end,
					),
				);

				$export_items[] = array(
					'group_id'    => 'wpmatch-payments',
					'group_label' => esc_html__( 'WPMatch Payments', 'wpmatch' ),
					'item_id'     => 'wpmatch-subscription-' . $subscription->id,
					'data'        => $subscription_data,
				);
			}
		}

		return array(
			'data' => $export_items,
			'done' => true,
		);
	}

	/**
	 * Erase user profile data.
	 *
	 * @param string $email_address User email.
	 * @param int    $page Page number.
	 * @return array
	 */
	public static function erase_profile_data( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		$items_removed = false;
		$messages      = array();

		// Remove WPMatch specific user meta.
		$wpmatch_meta_keys = array(
			'_wpmatch_age',
			'_wpmatch_location',
			'_wpmatch_bio',
			'_wpmatch_interests',
			'_wpmatch_looking_for',
			'_wpmatch_photos',
			'_wpmatch_preferences',
			'_wpmatch_verification_status',
			'_wpmatch_profile_completion',
		);

		foreach ( $wpmatch_meta_keys as $meta_key ) {
			if ( get_user_meta( $user->ID, $meta_key, true ) ) {
				delete_user_meta( $user->ID, $meta_key );
				$items_removed = true;
			}
		}

		if ( $items_removed ) {
			$messages[] = esc_html__( 'WPMatch profile data removed.', 'wpmatch' );
		}

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => false,
			'messages'       => $messages,
			'done'           => true,
		);
	}

	/**
	 * Erase user messages data.
	 *
	 * @param string $email_address User email.
	 * @param int    $page Page number.
	 * @return array
	 */
	public static function erase_messages_data( $email_address, $page = 1 ) {
		global $wpdb;

		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		$items_removed = false;
		$messages      = array();

		$messages_table = $wpdb->prefix . 'wpmatch_messages';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $messages_table ) ) === $messages_table ) {
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM `{$wpdb->prefix}wpmatch_messages` WHERE sender_id = %d OR receiver_id = %d",
					$user->ID,
					$user->ID
				)
			);

			if ( $deleted > 0 ) {
				$items_removed = true;
				$messages[]    = sprintf(
					/* translators: %d: Number of messages */
					esc_html__( '%d messages removed.', 'wpmatch' ),
					$deleted
				);
			}
		}

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => false,
			'messages'       => $messages,
			'done'           => true,
		);
	}

	/**
	 * Erase user matches data.
	 *
	 * @param string $email_address User email.
	 * @param int    $page Page number.
	 * @return array
	 */
	public static function erase_matches_data( $email_address, $page = 1 ) {
		global $wpdb;

		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		$items_removed = false;
		$messages      = array();

		// Remove matches.
		$matches_table = $wpdb->prefix . 'wpmatch_matches';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $matches_table ) ) === $matches_table ) {
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM `{$wpdb->prefix}wpmatch_matches` WHERE user_id = %d OR matched_user_id = %d",
					$user->ID,
					$user->ID
				)
			);

			if ( $deleted > 0 ) {
				$items_removed = true;
				$messages[]    = sprintf(
					/* translators: %d: Number of matches */
					esc_html__( '%d matches removed.', 'wpmatch' ),
					$deleted
				);
			}
		}

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => false,
			'messages'       => $messages,
			'done'           => true,
		);
	}

	/**
	 * Get user consent preferences.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public static function get_user_consent( $user_id ) {
		$defaults = array(
			'analytics'     => false,
			'marketing'     => false,
			'communication' => true,
			'location'      => false,
			'cookies'       => false,
			'updated_at'    => '',
		);

		$consent = get_user_meta( $user_id, '_wpmatch_privacy_consent', true );
		return wp_parse_args( $consent, $defaults );
	}

	/**
	 * Update user consent preferences.
	 *
	 * @param int   $user_id User ID.
	 * @param array $consent Consent settings.
	 * @return bool
	 */
	public static function update_user_consent( $user_id, $consent ) {
		$current_consent               = self::get_user_consent( $user_id );
		$updated_consent               = wp_parse_args( $consent, $current_consent );
		$updated_consent['updated_at'] = current_time( 'mysql' );

		$result = update_user_meta( $user_id, '_wpmatch_privacy_consent', $updated_consent );

		// Log consent changes.
		if ( $result ) {
			self::log_consent_change( $user_id, $current_consent, $updated_consent );
		}

		return $result;
	}

	/**
	 * Log consent changes for audit trail.
	 *
	 * @param int   $user_id User ID.
	 * @param array $old_consent Previous consent.
	 * @param array $new_consent New consent.
	 */
	private static function log_consent_change( $user_id, $old_consent, $new_consent ) {
		$changes = array();

		foreach ( $new_consent as $key => $value ) {
			if ( isset( $old_consent[ $key ] ) && $old_consent[ $key ] !== $value ) {
				$changes[ $key ] = array(
					'from' => $old_consent[ $key ],
					'to'   => $value,
				);
			}
		}

		if ( ! empty( $changes ) ) {
			$log_entry = array(
				'user_id'    => $user_id,
				'timestamp'  => current_time( 'mysql' ),
				'changes'    => $changes,
				'ip'         => sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ),
				'user_agent' => sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ),
			);

			// Store in log table or file.
			$existing_log   = get_option( 'wpmatch_consent_log', array() );
			$existing_log[] = $log_entry;

			// Keep only last 1000 entries.
			if ( count( $existing_log ) > 1000 ) {
				$existing_log = array_slice( $existing_log, -1000 );
			}

			update_option( 'wpmatch_consent_log', $existing_log );
		}
	}

	/**
	 * Handle AJAX consent updates.
	 */
	public static function handle_consent_update() {
		check_ajax_referer( 'wpmatch_consent_nonce', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( esc_html__( 'User not logged in.', 'wpmatch' ) );
		}

		$consent = array(
			'analytics'     => isset( $_POST['analytics'] ) && 'true' === $_POST['analytics'],
			'marketing'     => isset( $_POST['marketing'] ) && 'true' === $_POST['marketing'],
			'communication' => isset( $_POST['communication'] ) && 'true' === $_POST['communication'],
			'location'      => isset( $_POST['location'] ) && 'true' === $_POST['location'],
			'cookies'       => isset( $_POST['cookies'] ) && 'true' === $_POST['cookies'],
		);

		$result = self::update_user_consent( $user_id, $consent );

		if ( $result ) {
			wp_send_json_success(
				array(
					'message' => esc_html__( 'Privacy preferences updated successfully.', 'wpmatch' ),
				)
			);
		} else {
			wp_send_json_error( esc_html__( 'Failed to update privacy preferences.', 'wpmatch' ) );
		}
	}

	/**
	 * Enqueue consent management scripts.
	 */
	public static function enqueue_consent_scripts() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		wp_enqueue_script(
			'wpmatch-consent',
			WPMATCH_PLUGIN_URL . 'public/js/wpmatch-consent.js',
			array( 'jquery' ),
			WPMATCH_VERSION,
			true
		);

		wp_localize_script(
			'wpmatch-consent',
			'wpmatchConsent',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wpmatch_consent_nonce' ),
				'strings' => array(
					'consentUpdated' => esc_html__( 'Privacy preferences updated.', 'wpmatch' ),
					'consentError'   => esc_html__( 'Error updating preferences.', 'wpmatch' ),
				),
			)
		);
	}

	/**
	 * Render cookie consent banner.
	 */
	public static function render_cookie_consent_banner() {
		// Only show to non-logged in users or users who haven't set cookie consent.
		$user_id = get_current_user_id();
		if ( $user_id ) {
			$consent = self::get_user_consent( $user_id );
			if ( ! empty( $consent['updated_at'] ) ) {
				return; // User has already set preferences.
			}
		} elseif ( isset( $_COOKIE['wpmatch_cookie_consent'] ) ) {
			// Check if visitor has already accepted cookies.
			return;
		}

		include WPMATCH_PLUGIN_DIR . 'public/partials/wpmatch-cookie-consent-banner.php';
	}

	/**
	 * Clean up expired data based on retention policies.
	 */
	public static function cleanup_expired_data() {
		global $wpdb;

		// Clean up location data older than 30 days.
		$location_meta_deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->usermeta}
				WHERE meta_key = '_wpmatch_location_data'
				AND DATE(meta_value) < DATE_SUB(NOW(), INTERVAL 30 DAY)"
			)
		);

		// Clean up analytics data older than 2 years.
		$analytics_table = $wpdb->prefix . 'wpmatch_analytics';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $analytics_table ) ) === $analytics_table ) {
			$analytics_deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM `{$wpdb->prefix}wpmatch_analytics`
					WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 YEAR)"
				)
			);
		}

		// Log cleanup activities.
		if ( $location_meta_deleted > 0 || ( isset( $analytics_deleted ) && $analytics_deleted > 0 ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// Only log in debug mode.
				error_log(
					sprintf(
						'WPMatch Privacy Cleanup: %d location records, %d analytics records removed',
						$location_meta_deleted,
						$analytics_deleted ?? 0
					)
				);
			}
		}
	}

	/**
	 * Handle complete user deletion.
	 *
	 * @param int $user_id User ID being deleted.
	 */
	public static function handle_user_deletion( $user_id ) {
		global $wpdb;

		// Remove from all WPMatch tables.
		$tables_to_clean = array(
			$wpdb->prefix . 'wpmatch_matches',
			$wpdb->prefix . 'wpmatch_messages',
			$wpdb->prefix . 'wpmatch_subscriptions',
			$wpdb->prefix . 'wpmatch_subscription_transactions',
		);

		foreach ( $tables_to_clean as $table ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
				$wpdb->delete( $table, array( 'user_id' => $user_id ), array( '%d' ) );

				// Also clean references in match tables.
				if ( strpos( $table, 'matches' ) !== false ) {
					$wpdb->delete( $table, array( 'matched_user_id' => $user_id ), array( '%d' ) );
				}
				if ( strpos( $table, 'messages' ) !== false ) {
					$wpdb->delete( $table, array( 'sender_id' => $user_id ), array( '%d' ) );
					$wpdb->delete( $table, array( 'receiver_id' => $user_id ), array( '%d' ) );
				}
			}
		}

		// Remove WPMatch specific user meta.
		$wpdb->delete(
			$wpdb->usermeta,
			array( 'user_id' => $user_id ),
			array( '%d' )
		);

		// Log the deletion.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "WPMatch: Complete data deletion for user ID {$user_id}" );
		}
	}

	/**
	 * Add admin menu for privacy management.
	 */
	public static function add_admin_menu() {
		add_submenu_page(
			'wpmatch',
			esc_html__( 'Privacy & GDPR', 'wpmatch' ),
			esc_html__( 'Privacy & GDPR', 'wpmatch' ),
			'manage_options',
			'wpmatch-privacy',
			array( __CLASS__, 'render_privacy_page' )
		);
	}

	/**
	 * Register privacy settings.
	 */
	public static function register_privacy_settings() {
		register_setting( 'wpmatch_privacy_settings', 'wpmatch_privacy_policy_url' );
		register_setting( 'wpmatch_privacy_settings', 'wpmatch_data_retention_days' );
		register_setting( 'wpmatch_privacy_settings', 'wpmatch_cookie_consent_enabled' );
		register_setting( 'wpmatch_privacy_settings', 'wpmatch_analytics_enabled' );
	}

	/**
	 * Render privacy admin page.
	 */
	public static function render_privacy_page() {
		$consent_log = get_option( 'wpmatch_consent_log', array() );
		$data_types  = self::$data_types;
		include WPMATCH_PLUGIN_DIR . 'admin/partials/wpmatch-admin-privacy.php';
	}

	/**
	 * Get privacy policy content for WPMatch.
	 *
	 * @return string
	 */
	public static function get_privacy_policy_content() {
		$content = sprintf(
			'<h2>%s</h2>',
			esc_html__( 'WPMatch Dating Plugin', 'wpmatch' )
		);

		$content .= '<p>' . esc_html__(
			'This website uses the WPMatch dating plugin which collects and processes personal data to provide dating and matchmaking services.',
			'wpmatch'
		) . '</p>';

		$content .= sprintf( '<h3>%s</h3>', esc_html__( 'What Data We Collect', 'wpmatch' ) );
		$content .= '<ul>';

		foreach ( self::$data_types as $type_key => $type_data ) {
			$content .= sprintf(
				'<li><strong>%s:</strong> %s (Retained: %s)</li>',
				esc_html( $type_data['name'] ),
				esc_html( $type_data['description'] ),
				esc_html( $type_data['retention'] )
			);
		}

		$content .= '</ul>';

		$content .= sprintf( '<h3>%s</h3>', esc_html__( 'Your Rights', 'wpmatch' ) );
		$content .= '<p>' . esc_html__(
			'You have the right to access, update, or delete your personal data. You can manage your privacy preferences in your account settings or contact us to exercise these rights.',
			'wpmatch'
		) . '</p>';

		return $content;
	}
}

// Initialize privacy manager.
WPMatch_Privacy_Manager::init();
