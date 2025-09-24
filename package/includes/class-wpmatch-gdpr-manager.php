<?php
/**
 * WPMatch GDPR Compliance Manager
 *
 * Handles GDPR compliance including data protection, user rights,
 * consent management, and data processing transparency.
 *
 * @package WPMatch
 * @subpackage GDPR
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPMatch GDPR Manager class.
 *
 * @since 1.0.0
 */
class WPMatch_GDPR_Manager {

	/**
	 * Data retention periods (in days).
	 */
	const RETENTION_PROFILE_INACTIVE = 730;  // 2 years
	const RETENTION_MESSAGES         = 365;  // 1 year
	const RETENTION_SWIPE_DATA       = 180;  // 6 months
	const RETENTION_ANALYTICS        = 1095; // 3 years
	const RETENTION_SECURITY_LOGS    = 90;   // 3 months

	/**
	 * Consent types.
	 */
	const CONSENT_ESSENTIAL          = 'essential';
	const CONSENT_FUNCTIONALITY      = 'functionality';
	const CONSENT_ANALYTICS          = 'analytics';
	const CONSENT_MARKETING          = 'marketing';
	const CONSENT_THIRD_PARTY        = 'third_party';

	/**
	 * Data processing purposes.
	 */
	const PURPOSE_SERVICE_PROVISION  = 'service_provision';
	const PURPOSE_MATCHING           = 'matching';
	const PURPOSE_COMMUNICATION      = 'communication';
	const PURPOSE_ANALYTICS          = 'analytics';
	const PURPOSE_MARKETING          = 'marketing';
	const PURPOSE_LEGAL_COMPLIANCE   = 'legal_compliance';

	/**
	 * Instance of the class.
	 *
	 * @var WPMatch_GDPR_Manager
	 */
	private static $instance = null;

	/**
	 * Consent records cache.
	 *
	 * @var array
	 */
	private $consent_cache = array();

	/**
	 * Get the single instance.
	 *
	 * @return WPMatch_GDPR_Manager
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
		$this->init_hooks();
		$this->setup_data_retention();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// User data handling.
		add_action( 'user_register', array( $this, 'handle_new_user_registration' ) );
		add_action( 'delete_user', array( $this, 'handle_user_deletion' ) );

		// Data export/import hooks.
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_data_exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_data_erasers' ) );

		// Cookie consent.
		add_action( 'wp_head', array( $this, 'output_consent_script' ) );
		add_action( 'wp_footer', array( $this, 'output_consent_banner' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_wpmatch_update_consent', array( $this, 'handle_consent_update' ) );
		add_action( 'wp_ajax_nopriv_wpmatch_update_consent', array( $this, 'handle_consent_update' ) );

		// Admin hooks.
		add_action( 'admin_menu', array( $this, 'add_privacy_admin_menu' ) );

		// Scheduled cleanup.
		add_action( 'wpmatch_gdpr_cleanup', array( $this, 'run_data_retention_cleanup' ) );
	}

	/**
	 * Setup data retention policies.
	 */
	private function setup_data_retention() {
		if ( ! wp_next_scheduled( 'wpmatch_gdpr_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'wpmatch_gdpr_cleanup' );
		}
	}

	/**
	 * Handle new user registration.
	 *
	 * @param int $user_id User ID.
	 */
	public function handle_new_user_registration( $user_id ) {
		// Record initial consent (essential functions only).
		$this->record_consent( $user_id, self::CONSENT_ESSENTIAL, true, 'registration' );

		// Log data processing start.
		$this->log_data_processing_activity( $user_id, 'user_registration', array(
			'purpose'        => self::PURPOSE_SERVICE_PROVISION,
			'legal_basis'    => 'contract',
			'data_types'     => array( 'profile', 'authentication' ),
			'retention_period' => self::RETENTION_PROFILE_INACTIVE,
		) );
	}

	/**
	 * Handle user deletion.
	 *
	 * @param int $user_id User ID.
	 */
	public function handle_user_deletion( $user_id ) {
		// This will be called by the data eraser.
		$this->log_data_processing_activity( $user_id, 'user_deletion', array(
			'purpose'     => self::PURPOSE_LEGAL_COMPLIANCE,
			'legal_basis' => 'legitimate_interest',
			'action'      => 'complete_erasure',
		) );
	}

	/**
	 * Register personal data exporters.
	 *
	 * @param array $exporters Existing exporters.
	 * @return array Modified exporters.
	 */
	public function register_data_exporters( $exporters ) {
		$exporters['wpmatch-profile'] = array(
			'exporter_friendly_name' => __( 'WPMatch Profile Data', 'wpmatch' ),
			'callback'               => array( $this, 'export_profile_data' ),
		);

		$exporters['wpmatch-messages'] = array(
			'exporter_friendly_name' => __( 'WPMatch Messages', 'wpmatch' ),
			'callback'               => array( $this, 'export_message_data' ),
		);

		$exporters['wpmatch-matches'] = array(
			'exporter_friendly_name' => __( 'WPMatch Matches', 'wpmatch' ),
			'callback'               => array( $this, 'export_match_data' ),
		);

		$exporters['wpmatch-activity'] = array(
			'exporter_friendly_name' => __( 'WPMatch Activity', 'wpmatch' ),
			'callback'               => array( $this, 'export_activity_data' ),
		);

		$exporters['wpmatch-consent'] = array(
			'exporter_friendly_name' => __( 'WPMatch Consent Records', 'wpmatch' ),
			'callback'               => array( $this, 'export_consent_data' ),
		);

		return $exporters;
	}

	/**
	 * Register personal data erasers.
	 *
	 * @param array $erasers Existing erasers.
	 * @return array Modified erasers.
	 */
	public function register_data_erasers( $erasers ) {
		$erasers['wpmatch-profile'] = array(
			'eraser_friendly_name' => __( 'WPMatch Profile Data', 'wpmatch' ),
			'callback'             => array( $this, 'erase_profile_data' ),
		);

		$erasers['wpmatch-messages'] = array(
			'eraser_friendly_name' => __( 'WPMatch Messages', 'wpmatch' ),
			'callback'             => array( $this, 'erase_message_data' ),
		);

		$erasers['wpmatch-matches'] = array(
			'eraser_friendly_name' => __( 'WPMatch Matches', 'wpmatch' ),
			'callback'             => array( $this, 'erase_match_data' ),
		);

		$erasers['wpmatch-activity'] = array(
			'eraser_friendly_name' => __( 'WPMatch Activity', 'wpmatch' ),
			'callback'             => array( $this, 'erase_activity_data' ),
		);

		return $erasers;
	}

	/**
	 * Export profile data.
	 *
	 * @param string $email_address User email.
	 * @param int $page Page number.
	 * @return array Export data.
	 */
	public function export_profile_data( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		global $wpdb;

		$profile = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpmatch_user_profiles WHERE user_id = %d",
				$user->ID
			)
		);

		$export_items = array();

		if ( $profile ) {
			$export_items[] = array(
				'group_id'    => 'wpmatch_profile',
				'group_label' => __( 'Profile Information', 'wpmatch' ),
				'item_id'     => "profile-{$user->ID}",
				'data'        => array(
					array(
						'name'  => __( 'Age', 'wpmatch' ),
						'value' => $profile->age,
					),
					array(
						'name'  => __( 'Location', 'wpmatch' ),
						'value' => $profile->location,
					),
					array(
						'name'  => __( 'Gender', 'wpmatch' ),
						'value' => $profile->gender,
					),
					array(
						'name'  => __( 'Bio', 'wpmatch' ),
						'value' => $profile->about_me,
					),
					array(
						'name'  => __( 'Looking For', 'wpmatch' ),
						'value' => $profile->looking_for,
					),
					array(
						'name'  => __( 'Profile Created', 'wpmatch' ),
						'value' => $profile->created_at,
					),
					array(
						'name'  => __( 'Last Active', 'wpmatch' ),
						'value' => $profile->last_active,
					),
				),
			);
		}

		// Export preferences.
		$preferences = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpmatch_user_preferences WHERE user_id = %d",
				$user->ID
			)
		);

		if ( $preferences ) {
			$export_items[] = array(
				'group_id'    => 'wpmatch_preferences',
				'group_label' => __( 'Dating Preferences', 'wpmatch' ),
				'item_id'     => "preferences-{$user->ID}",
				'data'        => array(
					array(
						'name'  => __( 'Age Range', 'wpmatch' ),
						'value' => $preferences->min_age . ' - ' . $preferences->max_age,
					),
					array(
						'name'  => __( 'Maximum Distance', 'wpmatch' ),
						'value' => $preferences->max_distance . ' km',
					),
					array(
						'name'  => __( 'Preferred Gender', 'wpmatch' ),
						'value' => $preferences->preferred_gender,
					),
				),
			);
		}

		return array(
			'data' => $export_items,
			'done' => true,
		);
	}

	/**
	 * Export message data.
	 *
	 * @param string $email_address User email.
	 * @param int $page Page number.
	 * @return array Export data.
	 */
	public function export_message_data( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		global $wpdb;

		$per_page = 100;
		$offset = ( $page - 1 ) * $per_page;

		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpmatch_messages
				WHERE sender_id = %d OR recipient_id = %d
				ORDER BY created_at DESC
				LIMIT %d OFFSET %d",
				$user->ID,
				$user->ID,
				$per_page,
				$offset
			)
		);

		$export_items = array();

		foreach ( $messages as $message ) {
			$other_user_id = ( $message->sender_id == $user->ID ) ? $message->recipient_id : $message->sender_id;
			$other_user = get_user_by( 'ID', $other_user_id );
			$other_name = $other_user ? $other_user->display_name : __( 'Unknown User', 'wpmatch' );

			$export_items[] = array(
				'group_id'    => 'wpmatch_messages',
				'group_label' => __( 'Messages', 'wpmatch' ),
				'item_id'     => "message-{$message->message_id}",
				'data'        => array(
					array(
						'name'  => __( 'Conversation With', 'wpmatch' ),
						'value' => $other_name,
					),
					array(
						'name'  => __( 'Message Type', 'wpmatch' ),
						'value' => ( $message->sender_id == $user->ID ) ? __( 'Sent', 'wpmatch' ) : __( 'Received', 'wpmatch' ),
					),
					array(
						'name'  => __( 'Content', 'wpmatch' ),
						'value' => $message->message_content,
					),
					array(
						'name'  => __( 'Sent At', 'wpmatch' ),
						'value' => $message->created_at,
					),
					array(
						'name'  => __( 'Read', 'wpmatch' ),
						'value' => $message->is_read ? __( 'Yes', 'wpmatch' ) : __( 'No', 'wpmatch' ),
					),
				),
			);
		}

		$done = count( $messages ) < $per_page;

		return array(
			'data' => $export_items,
			'done' => $done,
		);
	}

	/**
	 * Export match data.
	 *
	 * @param string $email_address User email.
	 * @param int $page Page number.
	 * @return array Export data.
	 */
	public function export_match_data( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		global $wpdb;

		$matches = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpmatch_matches
				WHERE user1_id = %d OR user2_id = %d
				ORDER BY created_at DESC",
				$user->ID,
				$user->ID
			)
		);

		$export_items = array();

		foreach ( $matches as $match ) {
			$other_user_id = ( $match->user1_id == $user->ID ) ? $match->user2_id : $match->user1_id;
			$other_user = get_user_by( 'ID', $other_user_id );
			$other_name = $other_user ? $other_user->display_name : __( 'Unknown User', 'wpmatch' );

			$export_items[] = array(
				'group_id'    => 'wpmatch_matches',
				'group_label' => __( 'Matches', 'wpmatch' ),
				'item_id'     => "match-{$match->match_id}",
				'data'        => array(
					array(
						'name'  => __( 'Matched With', 'wpmatch' ),
						'value' => $other_name,
					),
					array(
						'name'  => __( 'Match Date', 'wpmatch' ),
						'value' => $match->created_at,
					),
					array(
						'name'  => __( 'Status', 'wpmatch' ),
						'value' => $match->status,
					),
				),
			);
		}

		return array(
			'data' => $export_items,
			'done' => true,
		);
	}

	/**
	 * Export activity data.
	 *
	 * @param string $email_address User email.
	 * @param int $page Page number.
	 * @return array Export data.
	 */
	public function export_activity_data( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		global $wpdb;

		$per_page = 100;
		$offset = ( $page - 1 ) * $per_page;

		// Export swipe data (anonymized).
		$swipes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT swipe_type, created_at FROM {$wpdb->prefix}wpmatch_swipes
				WHERE user_id = %d
				ORDER BY created_at DESC
				LIMIT %d OFFSET %d",
				$user->ID,
				$per_page,
				$offset
			)
		);

		$export_items = array();

		foreach ( $swipes as $swipe ) {
			$export_items[] = array(
				'group_id'    => 'wpmatch_activity',
				'group_label' => __( 'Dating Activity', 'wpmatch' ),
				'item_id'     => "swipe-{$swipe->created_at}",
				'data'        => array(
					array(
						'name'  => __( 'Action Type', 'wpmatch' ),
						'value' => $swipe->swipe_type,
					),
					array(
						'name'  => __( 'Date', 'wpmatch' ),
						'value' => $swipe->created_at,
					),
				),
			);
		}

		$done = count( $swipes ) < $per_page;

		return array(
			'data' => $export_items,
			'done' => $done,
		);
	}

	/**
	 * Export consent data.
	 *
	 * @param string $email_address User email.
	 * @param int $page Page number.
	 * @return array Export data.
	 */
	public function export_consent_data( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		global $wpdb;

		$consents = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpmatch_user_consent
				WHERE user_id = %d
				ORDER BY created_at DESC",
				$user->ID
			)
		);

		$export_items = array();

		foreach ( $consents as $consent ) {
			$export_items[] = array(
				'group_id'    => 'wpmatch_consent',
				'group_label' => __( 'Consent Records', 'wpmatch' ),
				'item_id'     => "consent-{$consent->consent_id}",
				'data'        => array(
					array(
						'name'  => __( 'Consent Type', 'wpmatch' ),
						'value' => $consent->consent_type,
					),
					array(
						'name'  => __( 'Status', 'wpmatch' ),
						'value' => $consent->consent_given ? __( 'Given', 'wpmatch' ) : __( 'Withdrawn', 'wpmatch' ),
					),
					array(
						'name'  => __( 'Date Given/Withdrawn', 'wpmatch' ),
						'value' => $consent->created_at,
					),
					array(
						'name'  => __( 'Source', 'wpmatch' ),
						'value' => $consent->consent_source,
					),
				),
			);
		}

		return array(
			'data' => $export_items,
			'done' => true,
		);
	}

	/**
	 * Erase profile data.
	 *
	 * @param string $email_address User email.
	 * @param int $page Page number.
	 * @return array Erasure result.
	 */
	public function erase_profile_data( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'items_removed'  => 0,
				'items_retained' => 0,
				'messages'       => array(),
				'done'           => true,
			);
		}

		global $wpdb;

		$items_removed = 0;

		// Delete profile data.
		$profile_deleted = $wpdb->delete(
			$wpdb->prefix . 'wpmatch_user_profiles',
			array( 'user_id' => $user->ID ),
			array( '%d' )
		);

		if ( $profile_deleted ) {
			++$items_removed;
		}

		// Delete preferences.
		$prefs_deleted = $wpdb->delete(
			$wpdb->prefix . 'wpmatch_user_preferences',
			array( 'user_id' => $user->ID ),
			array( '%d' )
		);

		if ( $prefs_deleted ) {
			++$items_removed;
		}

		// Delete interests.
		$interests_deleted = $wpdb->delete(
			$wpdb->prefix . 'wpmatch_user_interests',
			array( 'user_id' => $user->ID ),
			array( '%d' )
		);

		$items_removed += $interests_deleted;

		// Delete media files and records.
		$media_files = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpmatch_user_media WHERE user_id = %d",
				$user->ID
			)
		);

		foreach ( $media_files as $media ) {
			// Delete physical file.
			$file_path = wp_upload_dir()['basedir'] . '/' . $media->file_path;
			if ( file_exists( $file_path ) ) {
				wp_delete_file( $file_path );
			}
		}

		$media_deleted = $wpdb->delete(
			$wpdb->prefix . 'wpmatch_user_media',
			array( 'user_id' => $user->ID ),
			array( '%d' )
		);

		$items_removed += $media_deleted;

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => 0,
			'messages'       => array( __( 'Profile data has been erased.', 'wpmatch' ) ),
			'done'           => true,
		);
	}

	/**
	 * Erase message data.
	 *
	 * @param string $email_address User email.
	 * @param int $page Page number.
	 * @return array Erasure result.
	 */
	public function erase_message_data( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'items_removed'  => 0,
				'items_retained' => 0,
				'messages'       => array(),
				'done'           => true,
			);
		}

		global $wpdb;

		// Anonymize messages instead of deleting (to preserve conversation flow for other users).
		$messages_updated = $wpdb->update(
			$wpdb->prefix . 'wpmatch_messages',
			array( 'message_content' => __( '[Message deleted by user request]', 'wpmatch' ) ),
			array( 'sender_id' => $user->ID ),
			array( '%s' ),
			array( '%d' )
		);

		return array(
			'items_removed'  => $messages_updated,
			'items_retained' => 0,
			'messages'       => array( __( 'Messages have been anonymized.', 'wpmatch' ) ),
			'done'           => true,
		);
	}

	/**
	 * Erase match data.
	 *
	 * @param string $email_address User email.
	 * @param int $page Page number.
	 * @return array Erasure result.
	 */
	public function erase_match_data( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'items_removed'  => 0,
				'items_retained' => 0,
				'messages'       => array(),
				'done'           => true,
			);
		}

		global $wpdb;

		// Delete matches.
		$matches_deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}wpmatch_matches
				WHERE user1_id = %d OR user2_id = %d",
				$user->ID,
				$user->ID
			)
		);

		return array(
			'items_removed'  => $matches_deleted,
			'items_retained' => 0,
			'messages'       => array( __( 'Match data has been erased.', 'wpmatch' ) ),
			'done'           => true,
		);
	}

	/**
	 * Erase activity data.
	 *
	 * @param string $email_address User email.
	 * @param int $page Page number.
	 * @return array Erasure result.
	 */
	public function erase_activity_data( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'items_removed'  => 0,
				'items_retained' => 0,
				'messages'       => array(),
				'done'           => true,
			);
		}

		global $wpdb;

		// Delete swipe data.
		$swipes_deleted = $wpdb->delete(
			$wpdb->prefix . 'wpmatch_swipes',
			array( 'user_id' => $user->ID ),
			array( '%d' )
		);

		// Delete analytics data.
		$analytics_deleted = $wpdb->delete(
			$wpdb->prefix . 'wpmatch_user_analytics',
			array( 'user_id' => $user->ID ),
			array( '%d' )
		);

		$total_removed = $swipes_deleted + $analytics_deleted;

		return array(
			'items_removed'  => $total_removed,
			'items_retained' => 0,
			'messages'       => array( __( 'Activity data has been erased.', 'wpmatch' ) ),
			'done'           => true,
		);
	}

	/**
	 * Record user consent.
	 *
	 * @param int $user_id User ID.
	 * @param string $consent_type Consent type.
	 * @param bool $consent_given Whether consent was given.
	 * @param string $source Source of consent.
	 * @return bool Success status.
	 */
	public function record_consent( $user_id, $consent_type, $consent_given, $source = 'manual' ) {
		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'wpmatch_user_consent',
			array(
				'user_id'        => $user_id,
				'consent_type'   => $consent_type,
				'consent_given'  => $consent_given ? 1 : 0,
				'consent_source' => $source,
				'ip_address'     => $this->get_user_ip(),
				'user_agent'     => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( $result ) {
			// Update cache.
			if ( ! isset( $this->consent_cache[ $user_id ] ) ) {
				$this->consent_cache[ $user_id ] = array();
			}
			$this->consent_cache[ $user_id ][ $consent_type ] = $consent_given;

			// Trigger action.
			do_action( 'wpmatch_consent_updated', $user_id, $consent_type, $consent_given, $source );
		}

		return (bool) $result;
	}

	/**
	 * Check if user has given consent.
	 *
	 * @param int $user_id User ID.
	 * @param string $consent_type Consent type.
	 * @return bool Whether consent is given.
	 */
	public function has_consent( $user_id, $consent_type ) {
		// Check cache first.
		if ( isset( $this->consent_cache[ $user_id ][ $consent_type ] ) ) {
			return $this->consent_cache[ $user_id ][ $consent_type ];
		}

		global $wpdb;

		$consent = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT consent_given FROM {$wpdb->prefix}wpmatch_user_consent
				WHERE user_id = %d AND consent_type = %s
				ORDER BY created_at DESC
				LIMIT 1",
				$user_id,
				$consent_type
			)
		);

		$has_consent = (bool) $consent;

		// Cache result.
		if ( ! isset( $this->consent_cache[ $user_id ] ) ) {
			$this->consent_cache[ $user_id ] = array();
		}
		$this->consent_cache[ $user_id ][ $consent_type ] = $has_consent;

		return $has_consent;
	}

	/**
	 * Get all consent types and their status for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array Consent statuses.
	 */
	public function get_user_consent_status( $user_id ) {
		$consent_types = array(
			self::CONSENT_ESSENTIAL,
			self::CONSENT_FUNCTIONALITY,
			self::CONSENT_ANALYTICS,
			self::CONSENT_MARKETING,
			self::CONSENT_THIRD_PARTY,
		);

		$status = array();
		foreach ( $consent_types as $type ) {
			$status[ $type ] = $this->has_consent( $user_id, $type );
		}

		return $status;
	}

	/**
	 * Handle consent update AJAX request.
	 */
	public function handle_consent_update() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpmatch_consent' ) ) {
			wp_die( 'Security check failed' );
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_die( 'User not authenticated' );
		}

		$consent_type = isset( $_POST['consent_type'] ) ? sanitize_text_field( wp_unslash( $_POST['consent_type'] ) ) : '';
		$consent_given = isset( $_POST['consent_given'] ) ? (bool) $_POST['consent_given'] : false;

		if ( empty( $consent_type ) ) {
			wp_die( 'Invalid consent type' );
		}

		$result = $this->record_consent( $user_id, $consent_type, $consent_given, 'user_preference' );

		wp_send_json_success( array(
			'consent_type'  => $consent_type,
			'consent_given' => $consent_given,
			'recorded'      => $result,
		) );
	}

	/**
	 * Output consent management script.
	 */
	public function output_consent_script() {
		if ( is_admin() ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$consent_status = $this->get_user_consent_status( $user_id );

		?>
		<script type="text/javascript">
		window.wpMatchConsent = {
			status: <?php echo wp_json_encode( $consent_status ); ?>,
			nonce: '<?php echo esc_js( wp_create_nonce( 'wpmatch_consent' ) ); ?>',
			ajaxUrl: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',

			updateConsent: function(consentType, consentGiven) {
				fetch(this.ajaxUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: new URLSearchParams({
						action: 'wpmatch_update_consent',
						consent_type: consentType,
						consent_given: consentGiven ? '1' : '0',
						nonce: this.nonce
					})
				})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						this.status[consentType] = consentGiven;
						this.onConsentUpdated(consentType, consentGiven);
					}
				});
			},

			onConsentUpdated: function(consentType, consentGiven) {
				// Trigger custom event.
				window.dispatchEvent(new CustomEvent('wpMatchConsentUpdated', {
					detail: { consentType, consentGiven }
				}));
			},

			hasConsent: function(consentType) {
				return !!this.status[consentType];
			}
		};
		</script>
		<?php
	}

	/**
	 * Output consent banner.
	 */
	public function output_consent_banner() {
		if ( is_admin() ) {
			return;
		}

		$user_id = get_current_user_id();

		// Show banner for non-logged in users or users who haven't given essential consent.
		$show_banner = ! $user_id || ! $this->has_consent( $user_id, self::CONSENT_ESSENTIAL );

		if ( ! $show_banner ) {
			return;
		}

		?>
		<div id="wpmatch-consent-banner" style="position: fixed; bottom: 0; left: 0; right: 0; background: #333; color: white; padding: 20px; z-index: 9999; display: none;">
			<div style="max-width: 1200px; margin: 0 auto;">
				<p><?php esc_html_e( 'We use cookies and similar technologies to provide our dating services, analyze usage, and improve your experience. By continuing to use our service, you consent to our use of these technologies.', 'wpmatch' ); ?></p>
				<div style="margin-top: 15px;">
					<button id="wpmatch-accept-all" style="background: #007cba; color: white; padding: 10px 20px; border: none; margin-right: 10px; cursor: pointer;">
						<?php esc_html_e( 'Accept All', 'wpmatch' ); ?>
					</button>
					<button id="wpmatch-manage-consent" style="background: transparent; color: white; padding: 10px 20px; border: 1px solid white; margin-right: 10px; cursor: pointer;">
						<?php esc_html_e( 'Manage Preferences', 'wpmatch' ); ?>
					</button>
					<a href="<?php echo esc_url( get_privacy_policy_url() ); ?>" style="color: white; text-decoration: underline;">
						<?php esc_html_e( 'Privacy Policy', 'wpmatch' ); ?>
					</a>
				</div>
			</div>
		</div>

		<script type="text/javascript">
		document.addEventListener('DOMContentLoaded', function() {
			const banner = document.getElementById('wpmatch-consent-banner');
			const acceptAll = document.getElementById('wpmatch-accept-all');
			const manageConsent = document.getElementById('wpmatch-manage-consent');

			// Show banner.
			banner.style.display = 'block';

			// Accept all button.
			acceptAll.addEventListener('click', function() {
				if (window.wpMatchConsent) {
					const consentTypes = ['essential', 'functionality', 'analytics', 'marketing'];
					consentTypes.forEach(type => {
						wpMatchConsent.updateConsent(type, true);
					});
				}
				banner.style.display = 'none';
			});

			// Manage consent button.
			manageConsent.addEventListener('click', function() {
				// Open consent management modal.
				window.wpMatchConsentModal?.open();
			});
		});
		</script>
		<?php
	}

	/**
	 * Log data processing activity.
	 *
	 * @param int $user_id User ID.
	 * @param string $activity_type Activity type.
	 * @param array $data Activity data.
	 */
	public function log_data_processing_activity( $user_id, $activity_type, $data ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'wpmatch_data_processing_log',
			array(
				'user_id'        => $user_id,
				'activity_type'  => $activity_type,
				'purpose'        => $data['purpose'] ?? '',
				'legal_basis'    => $data['legal_basis'] ?? '',
				'data_types'     => wp_json_encode( $data['data_types'] ?? array() ),
				'retention_period' => $data['retention_period'] ?? 0,
				'processor_info' => wp_json_encode( $data['processor_info'] ?? array() ),
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * Run data retention cleanup.
	 */
	public function run_data_retention_cleanup() {
		global $wpdb;

		$current_time = current_time( 'mysql' );

		// Clean up inactive profiles.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE p FROM {$wpdb->prefix}wpmatch_user_profiles p
				INNER JOIN {$wpdb->users} u ON p.user_id = u.ID
				WHERE p.last_active < DATE_SUB(%s, INTERVAL %d DAY)
				AND NOT EXISTS (
					SELECT 1 FROM {$wpdb->usermeta} um
					WHERE um.user_id = p.user_id
					AND um.meta_key = 'wpmatch_retain_data'
					AND um.meta_value = '1'
				)",
				$current_time,
				self::RETENTION_PROFILE_INACTIVE
			)
		);

		// Clean up old messages.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}wpmatch_messages
				WHERE created_at < DATE_SUB(%s, INTERVAL %d DAY)",
				$current_time,
				self::RETENTION_MESSAGES
			)
		);

		// Clean up old swipe data.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}wpmatch_swipes
				WHERE created_at < DATE_SUB(%s, INTERVAL %d DAY)",
				$current_time,
				self::RETENTION_SWIPE_DATA
			)
		);

		// Clean up old security logs.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}wpmatch_security_logs
				WHERE created_at < DATE_SUB(%s, INTERVAL %d DAY)",
				$current_time,
				self::RETENTION_SECURITY_LOGS
			)
		);

		// Log cleanup activity.
		error_log( 'WPMatch GDPR: Data retention cleanup completed at ' . $current_time );
	}

	/**
	 * Get user IP address.
	 *
	 * @return string IP address.
	 */
	private function get_user_ip() {
		$ip_keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '127.0.0.1';
	}

	/**
	 * Add privacy admin menu.
	 */
	public function add_privacy_admin_menu() {
		// Disabled - Using WPMatch_Privacy_Manager menu instead to avoid duplicate
		// add_submenu_page(
		// 	'wpmatch',
		// 	__( 'Privacy & GDPR', 'wpmatch' ),
		// 	__( 'Privacy & GDPR', 'wpmatch' ),
		// 	'manage_options',
		// 	'wpmatch-privacy',
		// 	array( $this, 'render_privacy_admin_page' )
		// );
	}

	/**
	 * Render privacy admin page.
	 */
	public function render_privacy_admin_page() {
		$gdpr_stats = $this->get_gdpr_compliance_stats();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WPMatch Privacy & GDPR Compliance', 'wpmatch' ); ?></h1>

			<div class="wpmatch-privacy-dashboard">
				<div class="wpmatch-stats-grid">
					<div class="wpmatch-stat-card">
						<h3><?php esc_html_e( 'Data Subjects', 'wpmatch' ); ?></h3>
						<p class="wpmatch-stat-number"><?php echo esc_html( number_format( $gdpr_stats['total_users'] ) ); ?></p>
					</div>

					<div class="wpmatch-stat-card">
						<h3><?php esc_html_e( 'Consent Records', 'wpmatch' ); ?></h3>
						<p class="wpmatch-stat-number"><?php echo esc_html( number_format( $gdpr_stats['consent_records'] ) ); ?></p>
					</div>

					<div class="wpmatch-stat-card">
						<h3><?php esc_html_e( 'Data Requests', 'wpmatch' ); ?></h3>
						<p class="wpmatch-stat-number"><?php echo esc_html( number_format( $gdpr_stats['data_requests'] ) ); ?></p>
					</div>

					<div class="wpmatch-stat-card">
						<h3><?php esc_html_e( 'Retention Cleanups', 'wpmatch' ); ?></h3>
						<p class="wpmatch-stat-number"><?php echo esc_html( number_format( $gdpr_stats['cleanups_run'] ) ); ?></p>
					</div>
				</div>

				<h2><?php esc_html_e( 'Data Retention Policies', 'wpmatch' ); ?></h2>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Data Type', 'wpmatch' ); ?></th>
							<th><?php esc_html_e( 'Retention Period', 'wpmatch' ); ?></th>
							<th><?php esc_html_e( 'Legal Basis', 'wpmatch' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Inactive User Profiles', 'wpmatch' ); ?></td>
							<td><?php echo esc_html( self::RETENTION_PROFILE_INACTIVE ); ?> <?php esc_html_e( 'days', 'wpmatch' ); ?></td>
							<td><?php esc_html_e( 'Legitimate Interest', 'wpmatch' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Messages', 'wpmatch' ); ?></td>
							<td><?php echo esc_html( self::RETENTION_MESSAGES ); ?> <?php esc_html_e( 'days', 'wpmatch' ); ?></td>
							<td><?php esc_html_e( 'Contract Performance', 'wpmatch' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Swipe Data', 'wpmatch' ); ?></td>
							<td><?php echo esc_html( self::RETENTION_SWIPE_DATA ); ?> <?php esc_html_e( 'days', 'wpmatch' ); ?></td>
							<td><?php esc_html_e( 'Legitimate Interest', 'wpmatch' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Security Logs', 'wpmatch' ); ?></td>
							<td><?php echo esc_html( self::RETENTION_SECURITY_LOGS ); ?> <?php esc_html_e( 'days', 'wpmatch' ); ?></td>
							<td><?php esc_html_e( 'Legal Compliance', 'wpmatch' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Get GDPR compliance statistics.
	 *
	 * @return array Statistics.
	 */
	private function get_gdpr_compliance_stats() {
		global $wpdb;

		return array(
			'total_users'     => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wpmatch_user_profiles" ),
			'consent_records' => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wpmatch_user_consent" ),
			'data_requests'   => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = 'data_request'" ),
			'cleanups_run'    => get_option( 'wpmatch_gdpr_cleanups_run', 0 ),
		);
	}
}

// Initialize GDPR manager.
WPMatch_GDPR_Manager::get_instance();