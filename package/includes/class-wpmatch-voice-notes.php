<?php
/**
 * WPMatch Voice Notes System
 *
 * Handles voice note recording, uploading, and playback functionality
 * for the messaging system.
 *
 * @package WPMatch
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPMatch Voice Notes class.
 */
class WPMatch_Voice_Notes {

	/**
	 * Initialize the voice notes system.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'create_database_tables' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );

		// File upload hooks.
		add_filter( 'upload_mimes', array( __CLASS__, 'allow_voice_upload_types' ) );
		add_action( 'wp_handle_upload_prefilter', array( __CLASS__, 'validate_voice_upload' ) );

		// Voice note processing hooks.
		add_action( 'wpmatch_voice_note_uploaded', array( __CLASS__, 'process_voice_note' ), 10, 2 );
		add_action( 'wpmatch_voice_note_transcribe', array( __CLASS__, 'transcribe_voice_note' ) );

		// Cleanup hooks.
		add_action( 'wpmatch_cleanup_temp_voice_files', array( __CLASS__, 'cleanup_temp_files' ) );
	}

	/**
	 * Create database tables for voice notes.
	 */
	public static function create_database_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Voice notes table.
		$table_name = $wpdb->prefix . 'wpmatch_voice_notes';
		$sql        = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			message_id bigint(20) NOT NULL,
			user_id bigint(20) NOT NULL,
			file_path varchar(500) NOT NULL,
			file_url varchar(500) NOT NULL,
			file_size bigint(20) DEFAULT 0,
			duration int DEFAULT 0,
			format varchar(10) DEFAULT 'webm',
			transcription text,
			transcription_status enum('pending','processing','completed','failed') DEFAULT 'pending',
			playback_count int DEFAULT 0,
			is_processed tinyint(1) DEFAULT 0,
			processing_status enum('pending','processing','completed','failed') DEFAULT 'pending',
			waveform_data longtext,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY message_id (message_id),
			KEY user_id (user_id),
			KEY transcription_status (transcription_status),
			KEY processing_status (processing_status),
			KEY created_at (created_at)
		) $charset_collate;";

		// Voice note analytics table.
		$table_name = $wpdb->prefix . 'wpmatch_voice_note_analytics';
		$sql       .= "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			voice_note_id bigint(20) NOT NULL,
			listener_id bigint(20) NOT NULL,
			play_duration int DEFAULT 0,
			played_to_end tinyint(1) DEFAULT 0,
			playback_speed decimal(3,2) DEFAULT 1.00,
			device_type varchar(20),
			played_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY voice_note_id (voice_note_id),
			KEY listener_id (listener_id),
			KEY played_at (played_at)
		) $charset_collate;";

		// Voice note reactions table.
		$table_name = $wpdb->prefix . 'wpmatch_voice_note_reactions';
		$sql       .= "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			voice_note_id bigint(20) NOT NULL,
			user_id bigint(20) NOT NULL,
			reaction_type enum('like','love','laugh','wow','sad','angry','heart_eyes') NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY voice_note_user_reaction (voice_note_id, user_id),
			KEY voice_note_id (voice_note_id),
			KEY user_id (user_id),
			KEY reaction_type (reaction_type)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Register REST API routes.
	 */
	public static function register_rest_routes() {
		// Voice note upload.
		register_rest_route(
			'wpmatch/v1',
			'/voice-notes/upload',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'api_upload_voice_note' ),
				'permission_callback' => array( __CLASS__, 'check_user_permission' ),
				'args'                => array(
					'recipient_id' => array( 'required' => true ),
					'duration'     => array( 'required' => false ),
				),
			)
		);

		// Send voice note message.
		register_rest_route(
			'wpmatch/v1',
			'/voice-notes/send',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'api_send_voice_note' ),
				'permission_callback' => array( __CLASS__, 'check_user_permission' ),
				'args'                => array(
					'recipient_id'  => array( 'required' => true ),
					'voice_note_id' => array( 'required' => true ),
					'text_fallback' => array( 'required' => false ),
				),
			)
		);

		// Voice note playback tracking.
		register_rest_route(
			'wpmatch/v1',
			'/voice-notes/(?P<voice_note_id>\d+)/play',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'api_track_playback' ),
				'permission_callback' => array( __CLASS__, 'check_user_permission' ),
				'args'                => array(
					'voice_note_id'  => array( 'required' => true ),
					'play_duration'  => array( 'required' => false ),
					'played_to_end'  => array( 'default' => false ),
					'playback_speed' => array( 'default' => 1.0 ),
				),
			)
		);

		// Voice note reactions.
		register_rest_route(
			'wpmatch/v1',
			'/voice-notes/(?P<voice_note_id>\d+)/react',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'api_add_reaction' ),
				'permission_callback' => array( __CLASS__, 'check_user_permission' ),
				'args'                => array(
					'voice_note_id' => array( 'required' => true ),
					'reaction_type' => array( 'required' => true ),
				),
			)
		);

		// Get voice note details.
		register_rest_route(
			'wpmatch/v1',
			'/voice-notes/(?P<voice_note_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'api_get_voice_note' ),
				'permission_callback' => array( __CLASS__, 'check_user_permission' ),
				'args'                => array(
					'voice_note_id' => array( 'required' => true ),
				),
			)
		);

		// Voice note transcription.
		register_rest_route(
			'wpmatch/v1',
			'/voice-notes/(?P<voice_note_id>\d+)/transcription',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'api_get_transcription' ),
				'permission_callback' => array( __CLASS__, 'check_user_permission' ),
				'args'                => array(
					'voice_note_id' => array( 'required' => true ),
				),
			)
		);

		// Voice note analytics.
		register_rest_route(
			'wpmatch/v1',
			'/voice-notes/analytics',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'api_get_analytics' ),
				'permission_callback' => array( __CLASS__, 'check_user_permission' ),
				'args'                => array(
					'date_from' => array( 'required' => false ),
					'date_to'   => array( 'required' => false ),
				),
			)
		);
	}

	/**
	 * Enqueue frontend scripts and styles.
	 */
	public static function enqueue_scripts() {
		if ( is_page() || is_singular() ) {
			wp_enqueue_script(
				'wpmatch-voice-notes',
				WPMATCH_PLUGIN_URL . 'public/js/wpmatch-voice-notes.js',
				array( 'jquery' ),
				WPMATCH_VERSION,
				true
			);

			wp_enqueue_style(
				'wpmatch-voice-notes',
				WPMATCH_PLUGIN_URL . 'public/css/wpmatch-voice-notes.css',
				array(),
				WPMATCH_VERSION
			);

			wp_localize_script(
				'wpmatch-voice-notes',
				'wpMatchVoiceNotes',
				array(
					'apiUrl'           => rest_url( 'wpmatch/v1' ),
					'nonce'            => wp_create_nonce( 'wp_rest' ),
					'maxDuration'      => apply_filters( 'wpmatch_voice_note_max_duration', 300 ), // 5 minutes.
					'maxFileSize'      => apply_filters( 'wpmatch_voice_note_max_size', 10 * 1024 * 1024 ), // 10MB.
					'supportedFormats' => array( 'webm', 'mp4', 'm4a', 'wav', 'ogg' ),
					'strings'          => array(
						'recording'            => __( 'Recording...', 'wpmatch' ),
						'recordingComplete'    => __( 'Recording complete', 'wpmatch' ),
						'uploadSuccess'        => __( 'Voice note sent successfully!', 'wpmatch' ),
						'uploadError'          => __( 'Failed to upload voice note.', 'wpmatch' ),
						'permissionDenied'     => __( 'Microphone permission denied.', 'wpmatch' ),
						'notSupported'         => __( 'Voice recording not supported in this browser.', 'wpmatch' ),
						'tooLong'              => __( 'Recording too long. Maximum duration is 5 minutes.', 'wpmatch' ),
						'tooLarge'             => __( 'File too large. Maximum size is 10MB.', 'wpmatch' ),
						'playbackError'        => __( 'Error playing voice note.', 'wpmatch' ),
						'transcriptionLoading' => __( 'Generating transcription...', 'wpmatch' ),
						'transcriptionError'   => __( 'Transcription failed.', 'wpmatch' ),
						'reactionAdded'        => __( 'Reaction added!', 'wpmatch' ),
						'tapToPlay'            => __( 'Tap to play', 'wpmatch' ),
						'playing'              => __( 'Playing...', 'wpmatch' ),
						'paused'               => __( 'Paused', 'wpmatch' ),
					),
				)
			);
		}
	}

	/**
	 * Allow voice file upload types.
	 */
	public static function allow_voice_upload_types( $mimes ) {
		$mimes['webm'] = 'audio/webm';
		$mimes['m4a']  = 'audio/mp4';
		$mimes['oga']  = 'audio/ogg';
		$mimes['wav']  = 'audio/wav';
		return $mimes;
	}

	/**
	 * Validate voice note upload.
	 */
	public static function validate_voice_upload( $file ) {
		// Only validate voice notes.
		if ( ! isset( $_POST['voice_note_upload'] ) ) {
			return $file;
		}

		$max_size      = apply_filters( 'wpmatch_voice_note_max_size', 10 * 1024 * 1024 ); // 10MB.
		$allowed_types = array( 'audio/webm', 'audio/mp4', 'audio/ogg', 'audio/wav', 'audio/mpeg' );

		if ( $file['size'] > $max_size ) {
			$file['error'] = __( 'Voice note file is too large.', 'wpmatch' );
			return $file;
		}

		if ( ! in_array( $file['type'], $allowed_types, true ) ) {
			$file['error'] = __( 'Invalid voice note format.', 'wpmatch' );
			return $file;
		}

		return $file;
	}

	/**
	 * Upload voice note API endpoint.
	 */
	public static function api_upload_voice_note( $request ) {
		$user_id      = get_current_user_id();
		$recipient_id = absint( $request->get_param( 'recipient_id' ) );
		$duration     = absint( $request->get_param( 'duration' ) );

		if ( ! $recipient_id ) {
			return new WP_Error( 'invalid_recipient', __( 'Invalid recipient.', 'wpmatch' ), array( 'status' => 400 ) );
		}

		// Check if users can message each other.
		if ( ! WPMatch_Message_Manager::can_users_message( $user_id, $recipient_id ) ) {
			return new WP_Error( 'messaging_not_allowed', __( 'You cannot send messages to this user.', 'wpmatch' ), array( 'status' => 403 ) );
		}

		// Handle file upload.
		if ( ! isset( $_FILES['voice_note'] ) ) {
			return new WP_Error( 'no_file', __( 'No voice note file provided.', 'wpmatch' ), array( 'status' => 400 ) );
		}

		// Set upload flag for validation.
		$_POST['voice_note_upload'] = true;

		// Upload file.
		$upload_dir      = wp_upload_dir();
		$voice_notes_dir = $upload_dir['basedir'] . '/wpmatch-voice-notes';

		if ( ! file_exists( $voice_notes_dir ) ) {
			wp_mkdir_p( $voice_notes_dir );
		}

		$uploaded_file = wp_handle_upload(
			$_FILES['voice_note'],
			array(
				'test_form'            => false,
				'upload_error_handler' => array( __CLASS__, 'handle_upload_error' ),
			)
		);

		if ( isset( $uploaded_file['error'] ) ) {
			return new WP_Error( 'upload_failed', $uploaded_file['error'], array( 'status' => 400 ) );
		}

		// Move to voice notes directory.
		$filename      = wp_unique_filename( $voice_notes_dir, basename( $uploaded_file['file'] ) );
		$new_file_path = $voice_notes_dir . '/' . $filename;

		if ( ! rename( $uploaded_file['file'], $new_file_path ) ) {
			return new WP_Error( 'move_failed', __( 'Failed to process voice note.', 'wpmatch' ), array( 'status' => 500 ) );
		}

		$file_url  = $upload_dir['baseurl'] . '/wpmatch-voice-notes/' . $filename;
		$file_size = filesize( $new_file_path );

		// Save voice note record.
		global $wpdb;
		$table_name = $wpdb->prefix . 'wpmatch_voice_notes';

		$result = $wpdb->insert(
			$table_name,
			array(
				'message_id' => 0, // Will be updated when message is sent.
				'user_id'    => $user_id,
				'file_path'  => $new_file_path,
				'file_url'   => $file_url,
				'file_size'  => $file_size,
				'duration'   => $duration,
				'format'     => pathinfo( $filename, PATHINFO_EXTENSION ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%d', '%s' )
		);

		if ( false === $result ) {
			// Clean up file if database insert failed.
			unlink( $new_file_path );
			return new WP_Error( 'save_failed', __( 'Failed to save voice note.', 'wpmatch' ), array( 'status' => 500 ) );
		}

		$voice_note_id = $wpdb->insert_id;

		// Schedule processing.
		wp_schedule_single_event( time(), 'wpmatch_voice_note_uploaded', array( $voice_note_id, $user_id ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'voice_note_id' => $voice_note_id,
					'file_url'      => $file_url,
					'duration'      => $duration,
					'file_size'     => $file_size,
					'message'       => __( 'Voice note uploaded successfully!', 'wpmatch' ),
				),
			)
		);
	}

	/**
	 * Send voice note message API endpoint.
	 */
	public static function api_send_voice_note( $request ) {
		$user_id       = get_current_user_id();
		$recipient_id  = absint( $request->get_param( 'recipient_id' ) );
		$voice_note_id = absint( $request->get_param( 'voice_note_id' ) );
		$text_fallback = sanitize_textarea_field( $request->get_param( 'text_fallback' ) );

		// Verify voice note belongs to user.
		global $wpdb;
		$table_name = $wpdb->prefix . 'wpmatch_voice_notes';
		$voice_note = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE id = %d AND user_id = %d",
				$voice_note_id,
				$user_id
			)
		);

		if ( ! $voice_note ) {
			return new WP_Error( 'voice_note_not_found', __( 'Voice note not found.', 'wpmatch' ), array( 'status' => 404 ) );
		}

		// Send message with voice note.
		$message_content = $text_fallback ? $text_fallback : '[Voice Note]';
		$result          = WPMatch_Message_Manager::send_message( $user_id, $recipient_id, $message_content, 'voice_note' );

		if ( ! $result['success'] ) {
			return new WP_Error( 'send_failed', $result['message'], array( 'status' => 400 ) );
		}

		$message_id = $result['message_id'];

		// Update voice note with message ID.
		$wpdb->update(
			$table_name,
			array( 'message_id' => $message_id ),
			array( 'id' => $voice_note_id ),
			array( '%d' ),
			array( '%d' )
		);

		// Update message with attachment URL.
		$messages_table = $wpdb->prefix . 'wpmatch_messages';
		$wpdb->update(
			$messages_table,
			array( 'attachment_url' => $voice_note->file_url ),
			array( 'message_id' => $message_id ),
			array( '%s' ),
			array( '%d' )
		);

		// Trigger gamification.
		if ( class_exists( 'WPMatch_Gamification' ) ) {
			WPMatch_Gamification::trigger_achievement( 'voice_message_sent', array( 'user_id' => $user_id ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'message_id'    => $message_id,
					'voice_note_id' => $voice_note_id,
					'message'       => __( 'Voice note sent successfully!', 'wpmatch' ),
				),
			)
		);
	}

	/**
	 * Track voice note playback API endpoint.
	 */
	public static function api_track_playback( $request ) {
		$user_id        = get_current_user_id();
		$voice_note_id  = absint( $request->get_param( 'voice_note_id' ) );
		$play_duration  = absint( $request->get_param( 'play_duration' ) );
		$played_to_end  = $request->get_param( 'played_to_end' ) ? 1 : 0;
		$playback_speed = floatval( $request->get_param( 'playback_speed' ) );

		// Verify voice note exists.
		global $wpdb;
		$voice_notes_table = $wpdb->prefix . 'wpmatch_voice_notes';
		$voice_note        = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $voice_notes_table WHERE id = %d",
				$voice_note_id
			)
		);

		if ( ! $voice_note ) {
			return new WP_Error( 'voice_note_not_found', __( 'Voice note not found.', 'wpmatch' ), array( 'status' => 404 ) );
		}

		// Record analytics.
		$analytics_table = $wpdb->prefix . 'wpmatch_voice_note_analytics';
		$wpdb->insert(
			$analytics_table,
			array(
				'voice_note_id'  => $voice_note_id,
				'listener_id'    => $user_id,
				'play_duration'  => $play_duration,
				'played_to_end'  => $played_to_end,
				'playback_speed' => $playback_speed,
				'device_type'    => wp_is_mobile() ? 'mobile' : 'desktop',
			),
			array( '%d', '%d', '%d', '%d', '%f', '%s' )
		);

		// Update playback count.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $voice_notes_table SET playback_count = playback_count + 1 WHERE id = %d",
				$voice_note_id
			)
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'message' => __( 'Playback tracked.', 'wpmatch' ),
				),
			)
		);
	}

	/**
	 * Add reaction to voice note API endpoint.
	 */
	public static function api_add_reaction( $request ) {
		$user_id       = get_current_user_id();
		$voice_note_id = absint( $request->get_param( 'voice_note_id' ) );
		$reaction_type = sanitize_text_field( $request->get_param( 'reaction_type' ) );

		$allowed_reactions = array( 'like', 'love', 'laugh', 'wow', 'sad', 'angry', 'heart_eyes' );
		if ( ! in_array( $reaction_type, $allowed_reactions, true ) ) {
			return new WP_Error( 'invalid_reaction', __( 'Invalid reaction type.', 'wpmatch' ), array( 'status' => 400 ) );
		}

		// Save reaction.
		global $wpdb;
		$reactions_table = $wpdb->prefix . 'wpmatch_voice_note_reactions';

		$result = $wpdb->replace(
			$reactions_table,
			array(
				'voice_note_id' => $voice_note_id,
				'user_id'       => $user_id,
				'reaction_type' => $reaction_type,
			),
			array( '%d', '%d', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'reaction_failed', __( 'Failed to add reaction.', 'wpmatch' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'message'       => __( 'Reaction added successfully!', 'wpmatch' ),
					'reaction_type' => $reaction_type,
				),
			)
		);
	}

	/**
	 * Get voice note details API endpoint.
	 */
	public static function api_get_voice_note( $request ) {
		return rest_ensure_response(
			array(
				'success' => false,
				'message' => 'Not implemented yet',
			)
		);
	}

	/**
	 * Get transcription API endpoint.
	 */
	public static function api_get_transcription( $request ) {
		return rest_ensure_response(
			array(
				'success' => false,
				'message' => 'Not implemented yet',
			)
		);
	}

	/**
	 * Get analytics API endpoint.
	 */
	public static function api_get_analytics( $request ) {
		return rest_ensure_response(
			array(
				'success' => false,
				'message' => 'Not implemented yet',
			)
		);
	}

	/**
	 * Process voice note after upload.
	 */
	public static function process_voice_note( $voice_note_id, $user_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_voice_notes';
		$voice_note = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE id = %d",
				$voice_note_id
			)
		);

		if ( ! $voice_note ) {
			return;
		}

		// Update processing status.
		$wpdb->update(
			$table_name,
			array( 'processing_status' => 'processing' ),
			array( 'id' => $voice_note_id ),
			array( '%s' ),
			array( '%d' )
		);

		// Generate waveform data (simplified).
		$waveform_data = self::generate_waveform_data( $voice_note->file_path );

		// Schedule transcription if enabled.
		$enable_transcription = get_option( 'wpmatch_voice_transcription_enabled', false );
		if ( $enable_transcription ) {
			wp_schedule_single_event( time() + 30, 'wpmatch_voice_note_transcribe', array( $voice_note_id ) );
		}

		// Update with processing results.
		$wpdb->update(
			$table_name,
			array(
				'waveform_data'     => wp_json_encode( $waveform_data ),
				'is_processed'      => 1,
				'processing_status' => 'completed',
			),
			array( 'id' => $voice_note_id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Transcribe voice note (placeholder for future AI integration).
	 */
	public static function transcribe_voice_note( $voice_note_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_voice_notes';

		// Update transcription status.
		$wpdb->update(
			$table_name,
			array( 'transcription_status' => 'processing' ),
			array( 'id' => $voice_note_id ),
			array( '%s' ),
			array( '%d' )
		);

		// Placeholder for AI transcription service integration.
		// This would integrate with services like OpenAI Whisper, Google Speech-to-Text, etc.
		$transcription = '[Transcription service not configured]';

		// Update with transcription.
		$wpdb->update(
			$table_name,
			array(
				'transcription'        => $transcription,
				'transcription_status' => 'completed',
			),
			array( 'id' => $voice_note_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Generate waveform data for visualization.
	 */
	private static function generate_waveform_data( $file_path ) {
		// Simplified waveform generation.
		// In production, this would use audio processing libraries.
		$waveform = array();
		for ( $i = 0; $i < 100; $i++ ) {
			$waveform[] = rand( 10, 100 );
		}
		return $waveform;
	}

	/**
	 * Clean up temporary voice files.
	 */
	public static function cleanup_temp_files() {
		global $wpdb;

		// Clean up temporary files older than 24 hours.
		$table_name = $wpdb->prefix . 'wpmatch_voice_notes';
		$old_files  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT file_path FROM $table_name WHERE message_id = 0 AND created_at < %s",
				date( 'Y-m-d H:i:s', strtotime( '-24 hours' ) )
			)
		);

		foreach ( $old_files as $file ) {
			if ( file_exists( $file->file_path ) ) {
				unlink( $file->file_path );
			}
		}

		// Remove database records.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $table_name WHERE message_id = 0 AND created_at < %s",
				date( 'Y-m-d H:i:s', strtotime( '-24 hours' ) )
			)
		);
	}

	/**
	 * Handle upload error.
	 */
	public static function handle_upload_error( $file, $message ) {
		return array( 'error' => $message );
	}

	/**
	 * Permission callback.
	 */
	public static function check_user_permission() {
		return is_user_logged_in();
	}
}
