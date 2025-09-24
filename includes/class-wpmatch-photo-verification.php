<?php
/**
 * WPMatch Photo Verification System
 *
 * Handles photo verification functionality including AI-powered verification,
 * manual moderation, fake photo detection, and user trust scoring.
 *
 * @package WPMatch
 * @subpackage PhotoVerification
 * @since 1.3.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPMatch Photo Verification class.
 *
 * @since 1.3.0
 */
class WPMatch_Photo_Verification {

	/**
	 * Verification constants.
	 */
	const MAX_UPLOAD_SIZE       = 5242880; // 5MB
	const MIN_IMAGE_WIDTH       = 200;
	const MIN_IMAGE_HEIGHT      = 200;
	const MAX_VERIFICATION_AGE  = 90; // days
	const TRUST_SCORE_THRESHOLD = 70;

	/**
	 * Initialize photo verification features.
	 */
	public static function init() {
		// Create necessary database tables.
		self::create_tables();

		// Register REST API endpoints.
		add_action( 'rest_api_init', array( __CLASS__, 'register_api_routes' ) );

		// Handle AJAX requests for photo verification.
		add_action( 'wp_ajax_wpmatch_upload_verification_photo', array( __CLASS__, 'handle_photo_upload' ) );
		add_action( 'wp_ajax_wpmatch_submit_verification', array( __CLASS__, 'handle_verification_submission' ) );
		add_action( 'wp_ajax_wpmatch_get_verification_status', array( __CLASS__, 'handle_get_status' ) );

		// Admin AJAX handlers.
		add_action( 'wp_ajax_wpmatch_moderate_verification', array( __CLASS__, 'handle_admin_moderation' ) );
		add_action( 'wp_ajax_wpmatch_bulk_moderate', array( __CLASS__, 'handle_bulk_moderation' ) );

		// Hook into profile completion checks.
		add_filter( 'wpmatch_profile_completion_factors', array( __CLASS__, 'add_verification_factor' ) );

		// Hook into user trust score calculation.
		add_filter( 'wpmatch_user_trust_score', array( __CLASS__, 'calculate_verification_trust_score' ), 10, 2 );

		// Scheduled cleanup of old verification data.
		if ( ! wp_next_scheduled( 'wpmatch_cleanup_verification_data' ) ) {
			wp_schedule_event( time(), 'weekly', 'wpmatch_cleanup_verification_data' );
		}
		add_action( 'wpmatch_cleanup_verification_data', array( __CLASS__, 'cleanup_old_data' ) );

		// Admin notifications for pending verifications.
		add_action( 'wpmatch_verification_submitted', array( __CLASS__, 'notify_admin_new_submission' ) );

		// User notifications for verification status changes.
		add_action( 'wpmatch_verification_approved', array( __CLASS__, 'notify_user_approved' ), 10, 2 );
		add_action( 'wpmatch_verification_rejected', array( __CLASS__, 'notify_user_rejected' ), 10, 3 );
	}

	/**
	 * Register REST API routes for photo verification.
	 */
	public static function register_api_routes() {
		register_rest_route(
			'wpmatch/v1',
			'/verification/upload',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'api_upload_photo' ),
				'permission_callback' => array( __CLASS__, 'check_verification_permissions' ),
			)
		);

		register_rest_route(
			'wpmatch/v1',
			'/verification/submit',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'api_submit_verification' ),
				'permission_callback' => array( __CLASS__, 'check_verification_permissions' ),
			)
		);

		register_rest_route(
			'wpmatch/v1',
			'/verification/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'api_get_status' ),
				'permission_callback' => array( __CLASS__, 'check_verification_permissions' ),
			)
		);

		// Admin routes.
		register_rest_route(
			'wpmatch/v1',
			'/verification/pending',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'api_get_pending_verifications' ),
				'permission_callback' => array( __CLASS__, 'check_admin_permissions' ),
			)
		);

		register_rest_route(
			'wpmatch/v1',
			'/verification/moderate',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'api_moderate_verification' ),
				'permission_callback' => array( __CLASS__, 'check_admin_permissions' ),
				'args'                => array(
					'verification_id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'action'          => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return in_array( $param, array( 'approve', 'reject' ), true );
						},
					),
					'reason'          => array(
						'validate_callback' => function ( $param ) {
							return is_string( $param );
						},
					),
				),
			)
		);
	}

	/**
	 * Upload verification photo via API.
	 *
	 * @since 1.3.0
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public static function api_upload_photo( $request ) {
		$user_id = get_current_user_id();

		// Check if user already has pending verification.
		if ( self::has_pending_verification( $user_id ) ) {
			return new WP_Error( 'verification_pending', 'You already have a verification request pending.', array( 'status' => 400 ) );
		}

		// Handle file upload.
		$files = $request->get_file_params();
		if ( empty( $files['verification_photo'] ) ) {
			return new WP_Error( 'no_file', 'No photo file was uploaded.', array( 'status' => 400 ) );
		}

		$file = $files['verification_photo'];

		// Validate file.
		$validation_result = self::validate_photo_file( $file );
		if ( is_wp_error( $validation_result ) ) {
			return $validation_result;
		}

		// Process and store the photo.
		$photo_data = self::process_verification_photo( $file, $user_id );
		if ( is_wp_error( $photo_data ) ) {
			return $photo_data;
		}

		return new WP_REST_Response(
			array(
				'success'  => true,
				'photo_id' => $photo_data['photo_id'],
				'message'  => 'Photo uploaded successfully. Please proceed with verification submission.',
			),
			200
		);
	}

	/**
	 * Submit verification request via API.
	 *
	 * @since 1.3.0
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public static function api_submit_verification( $request ) {
		$user_id         = get_current_user_id();
		$photo_id        = (int) $request->get_param( 'photo_id' );
		$pose_type       = sanitize_text_field( $request->get_param( 'pose_type' ) );
		$additional_info = sanitize_textarea_field( $request->get_param( 'additional_info' ) );

		// Validate photo belongs to user.
		if ( ! self::user_owns_photo( $user_id, $photo_id ) ) {
			return new WP_Error( 'invalid_photo', 'Invalid photo specified.', array( 'status' => 400 ) );
		}

		// Create verification request.
		$verification_id = self::create_verification_request( $user_id, $photo_id, $pose_type, $additional_info );

		if ( ! $verification_id ) {
			return new WP_Error( 'submission_failed', 'Failed to submit verification request.', array( 'status' => 500 ) );
		}

		// Run automated checks.
		self::run_automated_verification_checks( $verification_id );

		// Trigger hooks.
		do_action( 'wpmatch_verification_submitted', $verification_id, $user_id );

		return new WP_REST_Response(
			array(
				'success'         => true,
				'verification_id' => $verification_id,
				'message'         => 'Verification request submitted successfully. You will be notified of the result.',
			),
			200
		);
	}

	/**
	 * Get verification status via API.
	 *
	 * @since 1.3.0
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response Response object.
	 */
	public static function api_get_status( $request ) {
		$user_id = get_current_user_id();
		$status  = self::get_user_verification_status( $user_id );

		return new WP_REST_Response(
			array(
				'status'            => $status['status'],
				'verification_date' => $status['verification_date'],
				'trust_score'       => $status['trust_score'],
				'can_reverify'      => $status['can_reverify'],
				'next_verification' => $status['next_verification'],
			),
			200
		);
	}

	/**
	 * Get pending verifications for admin.
	 *
	 * @since 1.3.0
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response Response object.
	 */
	public static function api_get_pending_verifications( $request ) {
		$page     = (int) $request->get_param( 'page' ) ?: 1;
		$per_page = (int) $request->get_param( 'per_page' ) ?: 20;

		global $wpdb;
		$verifications_table = $wpdb->prefix . 'wpmatch_photo_verifications';

		$offset = ( $page - 1 ) * $per_page;

		$verifications = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT v.*, u.display_name, u.user_email,
					   p.file_path, p.ai_analysis
				FROM {$verifications_table} v
				LEFT JOIN {$wpdb->users} u ON v.user_id = u.ID
				LEFT JOIN {$wpdb->prefix}wpmatch_verification_photos p ON v.photo_id = p.id
				WHERE v.status = 'pending'
				ORDER BY v.submitted_at ASC
				LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);

		$total = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$verifications_table} WHERE status = 'pending'"
		);

		return new WP_REST_Response(
			array(
				'verifications' => $verifications,
				'total'         => (int) $total,
				'page'          => $page,
				'per_page'      => $per_page,
				'total_pages'   => ceil( $total / $per_page ),
			),
			200
		);
	}

	/**
	 * Moderate verification via API.
	 *
	 * @since 1.3.0
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public static function api_moderate_verification( $request ) {
		$verification_id = (int) $request->get_param( 'verification_id' );
		$action          = sanitize_text_field( $request->get_param( 'action' ) );
		$reason          = sanitize_textarea_field( $request->get_param( 'reason' ) );
		$moderator_id    = get_current_user_id();

		$result = self::moderate_verification( $verification_id, $action, $moderator_id, $reason );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => $action === 'approve' ? 'Verification approved successfully.' : 'Verification rejected.',
			),
			200
		);
	}

	/**
	 * Validate uploaded photo file.
	 *
	 * @since 1.3.0
	 * @param array $file File data from $_FILES.
	 * @return true|WP_Error True if valid, WP_Error otherwise.
	 */
	private static function validate_photo_file( $file ) {
		// Check for upload errors.
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			return new WP_Error( 'upload_error', 'File upload failed.', array( 'status' => 400 ) );
		}

		// Check file size.
		if ( $file['size'] > self::MAX_UPLOAD_SIZE ) {
			return new WP_Error( 'file_too_large', 'File size exceeds maximum limit of 5MB.', array( 'status' => 400 ) );
		}

		// Check file type.
		$allowed_types = array( 'image/jpeg', 'image/jpg', 'image/png' );
		$file_type     = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );

		if ( ! in_array( $file_type['type'], $allowed_types, true ) ) {
			return new WP_Error( 'invalid_file_type', 'Only JPEG and PNG images are allowed.', array( 'status' => 400 ) );
		}

		// Check image dimensions.
		$image_info = getimagesize( $file['tmp_name'] );
		if ( ! $image_info ) {
			return new WP_Error( 'invalid_image', 'Invalid image file.', array( 'status' => 400 ) );
		}

		$width  = $image_info[0];
		$height = $image_info[1];

		if ( $width < self::MIN_IMAGE_WIDTH || $height < self::MIN_IMAGE_HEIGHT ) {
			return new WP_Error( 'image_too_small', sprintf( 'Image must be at least %dx%d pixels.', self::MIN_IMAGE_WIDTH, self::MIN_IMAGE_HEIGHT ), array( 'status' => 400 ) );
		}

		return true;
	}

	/**
	 * Process and store verification photo.
	 *
	 * @since 1.3.0
	 * @param array $file File data.
	 * @param int   $user_id User ID.
	 * @return array|WP_Error Photo data or error.
	 */
	private static function process_verification_photo( $file, $user_id ) {
		// Create upload directory.
		$upload_dir  = wp_upload_dir();
		$wpmatch_dir = $upload_dir['basedir'] . '/wpmatch/verification/';

		if ( ! file_exists( $wpmatch_dir ) ) {
			wp_mkdir_p( $wpmatch_dir );
		}

		// Generate unique filename.
		$file_extension = pathinfo( $file['name'], PATHINFO_EXTENSION );
		$filename       = sprintf( 'verification_%d_%s.%s', $user_id, uniqid(), $file_extension );
		$file_path      = $wpmatch_dir . $filename;

		// Move uploaded file.
		if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) {
			return new WP_Error( 'file_move_failed', 'Failed to save uploaded file.', array( 'status' => 500 ) );
		}

		// Run AI analysis.
		$ai_analysis = self::analyze_photo_with_ai( $file_path );

		// Store photo data in database.
		global $wpdb;
		$photos_table = $wpdb->prefix . 'wpmatch_verification_photos';

		$result = $wpdb->insert(
			$photos_table,
			array(
				'user_id'     => $user_id,
				'file_path'   => $filename,
				'file_size'   => $file['size'],
				'mime_type'   => $file['type'],
				'ai_analysis' => wp_json_encode( $ai_analysis ),
				'uploaded_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s' )
		);

		if ( ! $result ) {
			// Clean up file if database insert failed.
			unlink( $file_path );
			return new WP_Error( 'db_insert_failed', 'Failed to save photo data.', array( 'status' => 500 ) );
		}

		return array(
			'photo_id'    => $wpdb->insert_id,
			'filename'    => $filename,
			'ai_analysis' => $ai_analysis,
		);
	}

	/**
	 * Analyze photo using AI/ML services.
	 *
	 * @since 1.3.0
	 * @param string $file_path Path to photo file.
	 * @return array Analysis results.
	 */
	private static function analyze_photo_with_ai( $file_path ) {
		$analysis = array(
			'face_detected'    => false,
			'face_count'       => 0,
			'confidence_score' => 0,
			'is_nsfw'          => false,
			'is_fake'          => false,
			'quality_score'    => 0,
			'age_estimate'     => null,
			'gender_estimate'  => null,
			'emotions'         => array(),
			'landmarks'        => array(),
		);

		// Basic image analysis using PHP's image functions.
		$image_info = getimagesize( $file_path );
		if ( $image_info ) {
			$analysis['quality_score'] = self::calculate_image_quality( $file_path, $image_info );
		}

		// Simple face detection using pattern matching.
		$analysis['face_detected'] = self::detect_face_simple( $file_path );
		if ( $analysis['face_detected'] ) {
			$analysis['face_count']       = 1;
			$analysis['confidence_score'] = 0.7; // Basic confidence for simple detection.
		}

		// NSFW content detection (simplified).
		$analysis['is_nsfw'] = self::detect_nsfw_content( $file_path );

		// Fake/edited image detection (basic checks).
		$analysis['is_fake'] = self::detect_fake_image( $file_path );

		// Hook for third-party AI services integration.
		$analysis = apply_filters( 'wpmatch_ai_photo_analysis', $analysis, $file_path );

		return $analysis;
	}

	/**
	 * Calculate basic image quality score.
	 *
	 * @since 1.3.0
	 * @param string $file_path Path to image file.
	 * @param array  $image_info Image information from getimagesize().
	 * @return int Quality score 0-100.
	 */
	private static function calculate_image_quality( $file_path, $image_info ) {
		$score = 50; // Base score

		// Resolution score.
		$width  = $image_info[0];
		$height = $image_info[1];
		$pixels = $width * $height;

		if ( $pixels >= 1920 * 1080 ) {
			$score += 30;
		} elseif ( $pixels >= 1280 * 720 ) {
			$score += 20;
		} elseif ( $pixels >= 640 * 480 ) {
			$score += 10;
		}

		// File size to resolution ratio (compression quality).
		$file_size = filesize( $file_path );
		$ratio     = $file_size / $pixels;

		if ( $ratio > 0.5 ) {
			$score += 15;
		} elseif ( $ratio > 0.3 ) {
			$score += 10;
		} elseif ( $ratio > 0.1 ) {
			$score += 5;
		}

		// Brightness and contrast check (basic).
		$brightness_score = self::analyze_image_brightness( $file_path );
		$score           += $brightness_score;

		return min( 100, max( 0, $score ) );
	}

	/**
	 * Simple face detection using basic pattern matching.
	 *
	 * @since 1.3.0
	 * @param string $file_path Path to image file.
	 * @return bool Whether face was detected.
	 */
	private static function detect_face_simple( $file_path ) {
		// This is a very basic implementation.
		// In production, you'd use OpenCV, Google Vision API, AWS Rekognition, etc.

		$image = imagecreatefromstring( file_get_contents( $file_path ) );
		if ( ! $image ) {
			return false;
		}

		$width  = imagesx( $image );
		$height = imagesy( $image );

		// Look for skin-tone colors in expected face regions.
		$face_region_found = false;
		$skin_pixels       = 0;

		// Check upper center region (typical face location).
		$start_x = $width * 0.3;
		$end_x   = $width * 0.7;
		$start_y = $height * 0.2;
		$end_y   = $height * 0.6;

		for ( $x = $start_x; $x < $end_x; $x += 5 ) {
			for ( $y = $start_y; $y < $end_y; $y += 5 ) {
				$rgb = imagecolorat( $image, $x, $y );
				$r   = ( $rgb >> 16 ) & 0xFF;
				$g   = ( $rgb >> 8 ) & 0xFF;
				$b   = $rgb & 0xFF;

				// Simple skin tone detection.
				if ( self::is_skin_tone( $r, $g, $b ) ) {
					++$skin_pixels;
				}
			}
		}

		imagedestroy( $image );

		// If enough skin-tone pixels found, assume face is present.
		$total_checked = ( ( $end_x - $start_x ) / 5 ) * ( ( $end_y - $start_y ) / 5 );
		$skin_ratio    = $skin_pixels / $total_checked;

		return $skin_ratio > 0.15; // 15% skin tone pixels indicates likely face.
	}

	/**
	 * Check if RGB values represent skin tone.
	 *
	 * @since 1.3.0
	 * @param int $r Red value.
	 * @param int $g Green value.
	 * @param int $b Blue value.
	 * @return bool Whether color is skin tone.
	 */
	private static function is_skin_tone( $r, $g, $b ) {
		// Simple skin tone detection algorithm.
		return ( $r > 95 && $g > 40 && $b > 20 &&
				max( $r, $g, $b ) - min( $r, $g, $b ) > 15 &&
				abs( $r - $g ) > 15 && $r > $g && $r > $b );
	}

	/**
	 * Analyze image brightness.
	 *
	 * @since 1.3.0
	 * @param string $file_path Path to image file.
	 * @return int Brightness score 0-15.
	 */
	private static function analyze_image_brightness( $file_path ) {
		$image = imagecreatefromstring( file_get_contents( $file_path ) );
		if ( ! $image ) {
			return 0;
		}

		$width  = imagesx( $image );
		$height = imagesy( $image );

		$brightness_sum = 0;
		$pixel_count    = 0;

		// Sample pixels for brightness calculation.
		for ( $x = 0; $x < $width; $x += 10 ) {
			for ( $y = 0; $y < $height; $y += 10 ) {
				$rgb = imagecolorat( $image, $x, $y );
				$r   = ( $rgb >> 16 ) & 0xFF;
				$g   = ( $rgb >> 8 ) & 0xFF;
				$b   = $rgb & 0xFF;

				// Calculate perceived brightness.
				$brightness      = ( 0.299 * $r + 0.587 * $g + 0.114 * $b );
				$brightness_sum += $brightness;
				++$pixel_count;
			}
		}

		imagedestroy( $image );

		$avg_brightness = $brightness_sum / $pixel_count;

		// Score based on optimal brightness range (80-180).
		if ( $avg_brightness >= 80 && $avg_brightness <= 180 ) {
			return 15;
		} elseif ( $avg_brightness >= 60 && $avg_brightness <= 200 ) {
			return 10;
		} elseif ( $avg_brightness >= 40 && $avg_brightness <= 220 ) {
			return 5;
		}

		return 0;
	}

	/**
	 * Detect NSFW content (simplified).
	 *
	 * @since 1.3.0
	 * @param string $file_path Path to image file.
	 * @return bool Whether NSFW content detected.
	 */
	private static function detect_nsfw_content( $file_path ) {
		// This is a very basic implementation.
		// In production, you'd use services like Google Vision API, AWS Rekognition, or specialized NSFW detection APIs.

		$image = imagecreatefromstring( file_get_contents( $file_path ) );
		if ( ! $image ) {
			return false;
		}

		$width  = imagesx( $image );
		$height = imagesy( $image );

		$skin_pixels  = 0;
		$total_pixels = 0;

		// Sample pixels to detect skin tone percentage.
		for ( $x = 0; $x < $width; $x += 15 ) {
			for ( $y = 0; $y < $height; $y += 15 ) {
				$rgb = imagecolorat( $image, $x, $y );
				$r   = ( $rgb >> 16 ) & 0xFF;
				$g   = ( $rgb >> 8 ) & 0xFF;
				$b   = $rgb & 0xFF;

				if ( self::is_skin_tone( $r, $g, $b ) ) {
					++$skin_pixels;
				}
				++$total_pixels;
			}
		}

		imagedestroy( $image );

		$skin_ratio = $skin_pixels / $total_pixels;

		// High skin tone ratio might indicate NSFW content (very basic heuristic).
		return $skin_ratio > 0.6;
	}

	/**
	 * Detect fake/edited image (basic checks).
	 *
	 * @since 1.3.0
	 * @param string $file_path Path to image file.
	 * @return bool Whether image appears to be fake/edited.
	 */
	private static function detect_fake_image( $file_path ) {
		// Basic EXIF data analysis.
		$exif = @exif_read_data( $file_path );

		$fake_indicators = 0;

		// Check for missing camera information.
		if ( empty( $exif['Make'] ) || empty( $exif['Model'] ) ) {
			++$fake_indicators;
		}

		// Check for suspicious software.
		if ( ! empty( $exif['Software'] ) ) {
			$suspicious_software = array( 'photoshop', 'gimp', 'paint.net', 'faceapp', 'facetune' );
			foreach ( $suspicious_software as $software ) {
				if ( stripos( $exif['Software'], $software ) !== false ) {
					$fake_indicators += 2;
					break;
				}
			}
		}

		// Check for modified timestamps.
		if ( ! empty( $exif['DateTime'] ) && ! empty( $exif['DateTimeOriginal'] ) ) {
			$datetime = strtotime( $exif['DateTime'] );
			$original = strtotime( $exif['DateTimeOriginal'] );

			// Significant time difference might indicate editing.
			if ( abs( $datetime - $original ) > 3600 ) { // 1 hour difference.
				++$fake_indicators;
			}
		}

		// Return true if multiple indicators present.
		return $fake_indicators >= 2;
	}

	/**
	 * Create verification request.
	 *
	 * @since 1.3.0
	 * @param int    $user_id User ID.
	 * @param int    $photo_id Photo ID.
	 * @param string $pose_type Pose type.
	 * @param string $additional_info Additional information.
	 * @return int|false Verification ID or false on failure.
	 */
	private static function create_verification_request( $user_id, $photo_id, $pose_type, $additional_info ) {
		global $wpdb;
		$verifications_table = $wpdb->prefix . 'wpmatch_photo_verifications';

		$result = $wpdb->insert(
			$verifications_table,
			array(
				'user_id'         => $user_id,
				'photo_id'        => $photo_id,
				'pose_type'       => $pose_type,
				'additional_info' => $additional_info,
				'status'          => 'pending',
				'submitted_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Run automated verification checks.
	 *
	 * @since 1.3.0
	 * @param int $verification_id Verification ID.
	 */
	private static function run_automated_verification_checks( $verification_id ) {
		global $wpdb;

		$verification = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT v.*, p.ai_analysis FROM {$wpdb->prefix}wpmatch_photo_verifications v
				LEFT JOIN {$wpdb->prefix}wpmatch_verification_photos p ON v.photo_id = p.id
				WHERE v.id = %d",
				$verification_id
			)
		);

		if ( ! $verification ) {
			return;
		}

		$ai_analysis   = json_decode( $verification->ai_analysis, true );
		$auto_decision = null;
		$confidence    = 0;

		// Auto-reject if NSFW content detected.
		if ( ! empty( $ai_analysis['is_nsfw'] ) && $ai_analysis['is_nsfw'] ) {
			$auto_decision = 'rejected';
			$confidence    = 95;
		}

		// Auto-reject if fake image detected with high confidence.
		if ( ! empty( $ai_analysis['is_fake'] ) && $ai_analysis['is_fake'] ) {
			$auto_decision = 'rejected';
			$confidence    = 85;
		}

		// Auto-reject if no face detected.
		if ( empty( $ai_analysis['face_detected'] ) || ! $ai_analysis['face_detected'] ) {
			$auto_decision = 'rejected';
			$confidence    = 90;
		}

		// Auto-approve if high quality and high confidence face detection.
		if ( ! $auto_decision &&
			! empty( $ai_analysis['face_detected'] ) &&
			! empty( $ai_analysis['confidence_score'] ) &&
			! empty( $ai_analysis['quality_score'] ) &&
			$ai_analysis['confidence_score'] > 0.8 &&
			$ai_analysis['quality_score'] > 75 ) {
			$auto_decision = 'approved';
			$confidence    = 80;
		}

		// Store automated analysis results.
		$wpdb->update(
			$wpdb->prefix . 'wpmatch_photo_verifications',
			array(
				'auto_decision'   => $auto_decision,
				'auto_confidence' => $confidence,
				'analyzed_at'     => current_time( 'mysql' ),
			),
			array( 'id' => $verification_id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);

		// Auto-process if confidence is high enough.
		if ( $auto_decision && $confidence >= 90 ) {
			self::moderate_verification( $verification_id, $auto_decision, 0, 'Automated decision based on AI analysis' );
		}
	}

	/**
	 * Moderate verification request.
	 *
	 * @since 1.3.0
	 * @param int    $verification_id Verification ID.
	 * @param string $action Action: 'approve' or 'reject'.
	 * @param int    $moderator_id Moderator user ID (0 for automated).
	 * @param string $reason Reason for decision.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	private static function moderate_verification( $verification_id, $action, $moderator_id, $reason = '' ) {
		global $wpdb;

		$verification = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpmatch_photo_verifications WHERE id = %d",
				$verification_id
			)
		);

		if ( ! $verification ) {
			return new WP_Error( 'verification_not_found', 'Verification request not found.', array( 'status' => 404 ) );
		}

		if ( $verification->status !== 'pending' ) {
			return new WP_Error( 'already_processed', 'Verification request has already been processed.', array( 'status' => 400 ) );
		}

		// Update verification status.
		$result = $wpdb->update(
			$wpdb->prefix . 'wpmatch_photo_verifications',
			array(
				'status'       => $action === 'approve' ? 'approved' : 'rejected',
				'moderator_id' => $moderator_id,
				'reason'       => $reason,
				'reviewed_at'  => current_time( 'mysql' ),
			),
			array( 'id' => $verification_id ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		if ( ! $result ) {
			return new WP_Error( 'update_failed', 'Failed to update verification status.', array( 'status' => 500 ) );
		}

		// Update user verification status.
		if ( $action === 'approve' ) {
			update_user_meta( $verification->user_id, 'wpmatch_verified', true );
			update_user_meta( $verification->user_id, 'wpmatch_verification_date', current_time( 'mysql' ) );

			// Trigger approval hook.
			do_action( 'wpmatch_verification_approved', $verification->user_id, $verification_id );
		} else {
			// Trigger rejection hook.
			do_action( 'wpmatch_verification_rejected', $verification->user_id, $verification_id, $reason );
		}

		return true;
	}

	/**
	 * Get user verification status.
	 *
	 * @since 1.3.0
	 * @param int $user_id User ID.
	 * @return array Verification status data.
	 */
	public static function get_user_verification_status( $user_id ) {
		$verified          = get_user_meta( $user_id, 'wpmatch_verified', true );
		$verification_date = get_user_meta( $user_id, 'wpmatch_verification_date', true );

		global $wpdb;
		$pending_verification = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}wpmatch_photo_verifications
				WHERE user_id = %d AND status = 'pending'
				ORDER BY submitted_at DESC LIMIT 1",
				$user_id
			)
		);

		$status = 'unverified';
		if ( $pending_verification ) {
			$status = 'pending';
		} elseif ( $verified ) {
			// Check if verification is still valid.
			if ( $verification_date && strtotime( $verification_date ) > strtotime( '-' . self::MAX_VERIFICATION_AGE . ' days' ) ) {
				$status = 'verified';
			} else {
				$status = 'expired';
			}
		}

		$trust_score = self::calculate_verification_trust_score( 50, $user_id ); // Base score 50.

		return array(
			'status'            => $status,
			'verification_date' => $verification_date,
			'trust_score'       => $trust_score,
			'can_reverify'      => $status === 'unverified' || $status === 'expired',
			'next_verification' => $status === 'verified' ? date( 'Y-m-d', strtotime( $verification_date . ' +' . self::MAX_VERIFICATION_AGE . ' days' ) ) : null,
		);
	}

	/**
	 * Calculate verification-based trust score.
	 *
	 * @since 1.3.0
	 * @param int $base_score Base trust score.
	 * @param int $user_id User ID.
	 * @return int Enhanced trust score.
	 */
	public static function calculate_verification_trust_score( $base_score, $user_id ) {
		$verification_status = self::get_user_verification_status( $user_id );

		switch ( $verification_status['status'] ) {
			case 'verified':
				$base_score += 30;
				break;
			case 'pending':
				$base_score += 10;
				break;
			case 'expired':
				$base_score += 5;
				break;
		}

		// Check verification history.
		global $wpdb;
		$rejection_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wpmatch_photo_verifications
				WHERE user_id = %d AND status = 'rejected'",
				$user_id
			)
		);

		// Reduce score for multiple rejections.
		$base_score -= $rejection_count * 5;

		return max( 0, min( 100, $base_score ) );
	}

	/**
	 * Check permissions for verification endpoints.
	 *
	 * @since 1.3.0
	 * @param WP_REST_Request $request The request object.
	 * @return bool Whether user has permission.
	 */
	public static function check_verification_permissions( $request ) {
		return is_user_logged_in();
	}

	/**
	 * Check admin permissions.
	 *
	 * @since 1.3.0
	 * @param WP_REST_Request $request The request object.
	 * @return bool Whether user has admin permission.
	 */
	public static function check_admin_permissions( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Create database tables for photo verification.
	 *
	 * @since 1.3.0
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Verification photos table.
		$photos_table = $wpdb->prefix . 'wpmatch_verification_photos';
		$sql_photos   = "CREATE TABLE $photos_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			file_path varchar(255) NOT NULL,
			file_size bigint(20) NOT NULL,
			mime_type varchar(50) NOT NULL,
			ai_analysis longtext,
			uploaded_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY uploaded_at (uploaded_at)
		) $charset_collate;";

		// Photo verifications table.
		$verifications_table = $wpdb->prefix . 'wpmatch_photo_verifications';
		$sql_verifications   = "CREATE TABLE $verifications_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			photo_id bigint(20) NOT NULL,
			pose_type varchar(50) DEFAULT 'selfie',
			additional_info text,
			status enum('pending', 'approved', 'rejected') DEFAULT 'pending',
			auto_decision enum('approved', 'rejected') DEFAULT NULL,
			auto_confidence tinyint(3) DEFAULT 0,
			moderator_id bigint(20) DEFAULT 0,
			reason text,
			submitted_at datetime NOT NULL,
			analyzed_at datetime DEFAULT NULL,
			reviewed_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY photo_id (photo_id),
			KEY status (status),
			KEY submitted_at (submitted_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_photos );
		dbDelta( $sql_verifications );
	}

	/**
	 * Helper methods.
	 */

	/**
	 * Check if user has pending verification.
	 *
	 * @since 1.3.0
	 * @param int $user_id User ID.
	 * @return bool Whether user has pending verification.
	 */
	private static function has_pending_verification( $user_id ) {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wpmatch_photo_verifications
				WHERE user_id = %d AND status = 'pending'",
				$user_id
			)
		);

		return $count > 0;
	}

	/**
	 * Check if user owns photo.
	 *
	 * @since 1.3.0
	 * @param int $user_id User ID.
	 * @param int $photo_id Photo ID.
	 * @return bool Whether user owns photo.
	 */
	private static function user_owns_photo( $user_id, $photo_id ) {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wpmatch_verification_photos
				WHERE id = %d AND user_id = %d",
				$photo_id,
				$user_id
			)
		);

		return $count > 0;
	}

	/**
	 * Add verification factor to profile completion.
	 *
	 * @since 1.3.0
	 * @param array $factors Existing completion factors.
	 * @return array Updated factors.
	 */
	public static function add_verification_factor( $factors ) {
		$factors['photo_verification'] = array(
			'weight'      => 15,
			'description' => 'Photo verification completed',
		);

		return $factors;
	}

	/**
	 * Notification methods.
	 */

	/**
	 * Notify admin of new verification submission.
	 *
	 * @since 1.3.0
	 * @param int $verification_id Verification ID.
	 * @param int $user_id User ID.
	 */
	public static function notify_admin_new_submission( $verification_id, $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$admin_email = get_option( 'admin_email' );
		$subject     = sprintf( '[%s] New Photo Verification Submission', get_bloginfo( 'name' ) );

		$message = sprintf(
			"A new photo verification has been submitted:\n\n" .
			"User: %s (%s)\n" .
			"Submitted: %s\n\n" .
			"Review the submission: %s\n",
			$user->display_name,
			$user->user_email,
			current_time( 'mysql' ),
			admin_url( 'admin.php?page=wpmatch-verifications' )
		);

		wp_mail( $admin_email, $subject, $message );
	}

	/**
	 * Notify user of verification approval.
	 *
	 * @since 1.3.0
	 * @param int $user_id User ID.
	 * @param int $verification_id Verification ID.
	 */
	public static function notify_user_approved( $user_id, $verification_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$subject = sprintf( '[%s] Photo Verification Approved', get_bloginfo( 'name' ) );

		$message = sprintf(
			"Congratulations! Your photo verification has been approved.\n\n" .
			"You now have a verified profile badge that will help you stand out to other members.\n\n" .
			"View your profile: %s\n",
			home_url( '/profile/' . $user->user_login )
		);

		wp_mail( $user->user_email, $subject, $message );
	}

	/**
	 * Notify user of verification rejection.
	 *
	 * @since 1.3.0
	 * @param int    $user_id User ID.
	 * @param int    $verification_id Verification ID.
	 * @param string $reason Rejection reason.
	 */
	public static function notify_user_rejected( $user_id, $verification_id, $reason ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$subject = sprintf( '[%s] Photo Verification Update', get_bloginfo( 'name' ) );

		$message = sprintf(
			"Your photo verification submission has been reviewed.\n\n" .
			"Unfortunately, we were unable to approve your verification at this time.\n\n" .
			"%s\n\n" .
			"You can submit a new verification photo at any time: %s\n",
			$reason ? "Reason: $reason" : '',
			home_url( '/verification/' )
		);

		wp_mail( $user->user_email, $subject, $message );
	}

	/**
	 * Clean up old verification data.
	 *
	 * @since 1.3.0
	 */
	public static function cleanup_old_data() {
		global $wpdb;

		// Delete rejected verifications older than 30 days.
		$wpdb->query(
			"DELETE FROM {$wpdb->prefix}wpmatch_photo_verifications
			WHERE status = 'rejected' AND reviewed_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
		);

		// Delete orphaned photos.
		$wpdb->query(
			"DELETE p FROM {$wpdb->prefix}wpmatch_verification_photos p
			LEFT JOIN {$wpdb->prefix}wpmatch_photo_verifications v ON p.id = v.photo_id
			WHERE v.id IS NULL AND p.uploaded_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
		);

		// Clean up physical files for deleted records.
		$upload_dir       = wp_upload_dir();
		$verification_dir = $upload_dir['basedir'] . '/wpmatch/verification/';

		if ( is_dir( $verification_dir ) ) {
			$files = scandir( $verification_dir );
			foreach ( $files as $file ) {
				if ( $file === '.' || $file === '..' ) {
					continue;
				}

				$file_path = $verification_dir . $file;
				$file_age  = time() - filemtime( $file_path );

				// Check if file exists in database.
				$exists = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->prefix}wpmatch_verification_photos WHERE file_path = %s",
						$file
					)
				);

				// Delete files older than 7 days that don't exist in database.
				if ( ! $exists && $file_age > ( 7 * 24 * 60 * 60 ) ) {
					unlink( $file_path );
				}
			}
		}
	}

	/**
	 * AJAX handler methods.
	 */

	/**
	 * Handle photo upload via AJAX.
	 */
	public static function handle_photo_upload() {
		check_ajax_referer( 'wpmatch_verification', 'nonce' );

		if ( ! isset( $_FILES['verification_photo'] ) ) {
			wp_send_json_error( 'No photo file uploaded.' );
		}

		$user_id = get_current_user_id();
		$file    = $_FILES['verification_photo'];

		$validation_result = self::validate_photo_file( $file );
		if ( is_wp_error( $validation_result ) ) {
			wp_send_json_error( $validation_result->get_error_message() );
		}

		$photo_data = self::process_verification_photo( $file, $user_id );
		if ( is_wp_error( $photo_data ) ) {
			wp_send_json_error( $photo_data->get_error_message() );
		}

		wp_send_json_success(
			array(
				'photo_id' => $photo_data['photo_id'],
				'message'  => 'Photo uploaded successfully.',
			)
		);
	}

	/**
	 * Handle verification submission via AJAX.
	 */
	public static function handle_verification_submission() {
		check_ajax_referer( 'wpmatch_verification', 'nonce' );

		$user_id         = get_current_user_id();
		$photo_id        = (int) $_POST['photo_id'];
		$pose_type       = sanitize_text_field( $_POST['pose_type'] );
		$additional_info = sanitize_textarea_field( $_POST['additional_info'] );

		if ( ! self::user_owns_photo( $user_id, $photo_id ) ) {
			wp_send_json_error( 'Invalid photo specified.' );
		}

		$verification_id = self::create_verification_request( $user_id, $photo_id, $pose_type, $additional_info );

		if ( ! $verification_id ) {
			wp_send_json_error( 'Failed to submit verification request.' );
		}

		self::run_automated_verification_checks( $verification_id );
		do_action( 'wpmatch_verification_submitted', $verification_id, $user_id );

		wp_send_json_success(
			array(
				'verification_id' => $verification_id,
				'message'         => 'Verification submitted successfully.',
			)
		);
	}

	/**
	 * Handle get verification status via AJAX.
	 */
	public static function handle_get_status() {
		check_ajax_referer( 'wpmatch_verification', 'nonce' );

		$user_id = get_current_user_id();
		$status  = self::get_user_verification_status( $user_id );

		wp_send_json_success( $status );
	}

	/**
	 * Handle admin moderation via AJAX.
	 */
	public static function handle_admin_moderation() {
		check_ajax_referer( 'wpmatch_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
		}

		$verification_id = (int) $_POST['verification_id'];
		$action          = sanitize_text_field( $_POST['action'] );
		$reason          = sanitize_textarea_field( $_POST['reason'] );
		$moderator_id    = get_current_user_id();

		$result = self::moderate_verification( $verification_id, $action, $moderator_id, $reason );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success(
			array(
				'message' => $action === 'approve' ? 'Verification approved.' : 'Verification rejected.',
			)
		);
	}

	/**
	 * Handle bulk moderation via AJAX.
	 */
	public static function handle_bulk_moderation() {
		check_ajax_referer( 'wpmatch_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
		}

		$verification_ids = array_map( 'intval', $_POST['verification_ids'] );
		$action           = sanitize_text_field( $_POST['action'] );
		$reason           = sanitize_textarea_field( $_POST['reason'] );
		$moderator_id     = get_current_user_id();

		$processed = 0;
		$errors    = 0;

		foreach ( $verification_ids as $verification_id ) {
			$result = self::moderate_verification( $verification_id, $action, $moderator_id, $reason );
			if ( is_wp_error( $result ) ) {
				++$errors;
			} else {
				++$processed;
			}
		}

		wp_send_json_success(
			array(
				'processed' => $processed,
				'errors'    => $errors,
				'message'   => sprintf( '%d verifications processed, %d errors.', $processed, $errors ),
			)
		);
	}
}
