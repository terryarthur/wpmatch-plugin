<?php
/**
 * WPMatch User Media Management
 *
 * Handles user photo and video uploads, management, and verification.
 *
 * @package WPMatch
 * @since 1.8.0
 */

/**
 * User Media Management class.
 */
class WPMatch_User_Media {

	/**
	 * Plugin name.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Allowed image types.
	 *
	 * @var array
	 */
	private $allowed_image_types = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );

	/**
	 * Allowed video types.
	 *
	 * @var array
	 */
	private $allowed_video_types = array( 'video/mp4', 'video/webm', 'video/ogg' );

	/**
	 * Maximum file sizes (in bytes).
	 *
	 * @var array
	 */
	private $max_file_sizes = array(
		'photo' => 10485760,  // 10MB.
		'video' => 104857600, // 100MB.
	);

	/**
	 * Image dimensions.
	 *
	 * @var array
	 */
	private $image_sizes = array(
		'thumbnail' => array( 150, 150 ),
		'medium'    => array( 300, 300 ),
		'large'     => array( 800, 800 ),
		'profile'   => array( 400, 600 ),
	);

	/**
	 * Constructor.
	 *
	 * @param string $plugin_name Plugin name.
	 * @param string $version Plugin version.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		$this->init_hooks();
		$this->load_settings();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// AJAX handlers.
		add_action( 'wp_ajax_wpmatch_upload_media', array( $this, 'ajax_upload_media' ) );
		add_action( 'wp_ajax_wpmatch_delete_media', array( $this, 'ajax_delete_media' ) );
		add_action( 'wp_ajax_wpmatch_reorder_media', array( $this, 'ajax_reorder_media' ) );
		add_action( 'wp_ajax_wpmatch_set_primary_media', array( $this, 'ajax_set_primary_media' ) );
		add_action( 'wp_ajax_wpmatch_get_user_media', array( $this, 'ajax_get_user_media' ) );

		// REST API endpoints.
		add_action( 'rest_api_init', array( $this, 'register_rest_endpoints' ) );

		// File cleanup hooks.
		add_action( 'wpmatch_media_deleted', array( $this, 'cleanup_media_files' ), 10, 1 );
		add_action( 'delete_user', array( $this, 'cleanup_user_media' ), 10, 1 );

		// Media processing hooks.
		add_action( 'wpmatch_media_uploaded', array( $this, 'queue_media_processing' ), 10, 2 );

		// Admin hooks.
		add_action( 'wp_ajax_wpmatch_admin_verify_media', array( $this, 'admin_verify_media' ) );
		add_action( 'wp_ajax_wpmatch_admin_reject_media', array( $this, 'admin_reject_media' ) );
	}

	/**
	 * Load settings.
	 */
	private function load_settings() {
		$settings = get_option( 'wpmatch_media_settings', array() );

		if ( isset( $settings['max_photos'] ) ) {
			$this->max_photos = absint( $settings['max_photos'] );
		}

		if ( isset( $settings['max_videos'] ) ) {
			$this->max_videos = absint( $settings['max_videos'] );
		}

		if ( isset( $settings['max_photo_size'] ) ) {
			$this->max_file_sizes['photo'] = absint( $settings['max_photo_size'] ) * 1024 * 1024;
		}

		if ( isset( $settings['max_video_size'] ) ) {
			$this->max_file_sizes['video'] = absint( $settings['max_video_size'] ) * 1024 * 1024;
		}

		if ( isset( $settings['require_verification'] ) ) {
			$this->require_verification = (bool) $settings['require_verification'];
		}

		if ( isset( $settings['auto_approve'] ) ) {
			$this->auto_approve = (bool) $settings['auto_approve'];
		}
	}

	/**
	 * Register REST API endpoints.
	 */
	public function register_rest_endpoints() {
		// Upload media endpoint.
		register_rest_route(
			'wpmatch/v1',
			'/media/upload',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_upload_media' ),
				'permission_callback' => function() {
					return is_user_logged_in();
				},
				'args'                => array(
					'media_type' => array(
						'required' => true,
						'type'     => 'string',
						'enum'     => array( 'photo', 'video' ),
					),
					'is_primary' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);

		// Get user media endpoint.
		register_rest_route(
			'wpmatch/v1',
			'/media/user/(?P<user_id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_get_user_media' ),
				'permission_callback' => array( $this, 'can_view_user_media' ),
				'args'                => array(
					'media_type' => array(
						'type' => 'string',
						'enum' => array( 'photo', 'video', 'all' ),
						'default' => 'all',
					),
					'verified_only' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);

		// Delete media endpoint.
		register_rest_route(
			'wpmatch/v1',
			'/media/(?P<media_id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'rest_delete_media' ),
				'permission_callback' => array( $this, 'can_delete_media' ),
			)
		);

		// Update media endpoint.
		register_rest_route(
			'wpmatch/v1',
			'/media/(?P<media_id>\d+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'rest_update_media' ),
				'permission_callback' => array( $this, 'can_edit_media' ),
				'args'                => array(
					'is_primary'     => array(
						'type' => 'boolean',
					),
					'display_order'  => array(
						'type' => 'integer',
					),
				),
			)
		);

		// Reorder media endpoint.
		register_rest_route(
			'wpmatch/v1',
			'/media/reorder',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_reorder_media' ),
				'permission_callback' => function() {
					return is_user_logged_in();
				},
				'args'                => array(
					'media_ids' => array(
						'required' => true,
						'type'     => 'array',
						'items'    => array(
							'type' => 'integer',
						),
					),
				),
			)
		);

		// Media verification endpoints (admin only).
		register_rest_route(
			'wpmatch/v1',
			'/media/(?P<media_id>\d+)/verify',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_verify_media' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			'wpmatch/v1',
			'/media/(?P<media_id>\d+)/reject',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_reject_media' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
				'args'                => array(
					'reason' => array(
						'type' => 'string',
					),
				),
			)
		);

		// Pending media endpoint (admin only).
		register_rest_route(
			'wpmatch/v1',
			'/media/pending',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_get_pending_media' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
				'args'                => array(
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 20,
					),
				),
			)
		);
	}

	/**
	 * Upload media file.
	 *
	 * @param array $file File data from $_FILES.
	 * @param int   $user_id User ID.
	 * @param string $media_type Media type (photo/video).
	 * @param bool  $is_primary Whether this is the primary media.
	 * @return array|WP_Error Upload result or error.
	 */
	public function upload_media( $file, $user_id, $media_type = 'photo', $is_primary = false ) {
		// Validate file.
		$validation = $this->validate_upload( $file, $media_type, $user_id );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Check user media limits.
		$media_count = $this->get_user_media_count( $user_id, $media_type );
		$max_allowed = 'photo' === $media_type ?
			get_option( 'wpmatch_max_photos', 6 ) :
			get_option( 'wpmatch_max_videos', 3 );

		if ( $media_count >= $max_allowed ) {
			return new WP_Error(
				'media_limit_exceeded',
				sprintf(
					__( 'Maximum %s limit exceeded. You can upload up to %d %ss.', 'wpmatch' ),
					$media_type,
					$max_allowed,
					$media_type
				)
			);
		}

		// Handle file upload.
		$upload_result = $this->handle_file_upload( $file, $user_id, $media_type );
		if ( is_wp_error( $upload_result ) ) {
			return $upload_result;
		}

		// If setting as primary, unset current primary.
		if ( $is_primary ) {
			$this->unset_primary_media( $user_id, $media_type );
		}

		// Get next display order.
		$display_order = $this->get_next_display_order( $user_id );

		// Insert media record.
		$media_id = $this->insert_media_record(
			$user_id,
			$media_type,
			$upload_result,
			$is_primary,
			$display_order
		);

		if ( ! $media_id ) {
			// Clean up uploaded file if database insert failed.
			if ( file_exists( $upload_result['file_path'] ) ) {
				unlink( $upload_result['file_path'] );
			}
			return new WP_Error( 'database_error', __( 'Failed to save media record.', 'wpmatch' ) );
		}

		// Queue background processing.
		do_action( 'wpmatch_media_uploaded', $media_id, $upload_result );

		// Get the complete media record.
		$media_record = $this->get_media_by_id( $media_id );

		return array(
			'success'    => true,
			'media_id'   => $media_id,
			'media_data' => $media_record,
			'message'    => __( 'Media uploaded successfully.', 'wpmatch' ),
		);
	}

	/**
	 * Validate file upload.
	 *
	 * @param array  $file File data.
	 * @param string $media_type Media type.
	 * @param int    $user_id User ID.
	 * @return true|WP_Error True if valid, WP_Error if invalid.
	 */
	private function validate_upload( $file, $media_type, $user_id ) {
		// Check if file was uploaded.
		if ( ! isset( $file['tmp_name'] ) || empty( $file['tmp_name'] ) ) {
			return new WP_Error( 'no_file', __( 'No file was uploaded.', 'wpmatch' ) );
		}

		// Check for upload errors.
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			return new WP_Error( 'upload_error', $this->get_upload_error_message( $file['error'] ) );
		}

		// Check file size.
		if ( $file['size'] > $this->max_file_sizes[ $media_type ] ) {
			$max_size = size_format( $this->max_file_sizes[ $media_type ] );
			return new WP_Error(
				'file_too_large',
				sprintf( __( 'File size exceeds maximum allowed size of %s.', 'wpmatch' ), $max_size )
			);
		}

		// Check MIME type.
		$allowed_types = 'photo' === $media_type ? $this->allowed_image_types : $this->allowed_video_types;
		$file_info = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );

		if ( ! in_array( $file_info['type'], $allowed_types, true ) ) {
			return new WP_Error(
				'invalid_file_type',
				sprintf( __( 'Invalid file type. Allowed types: %s', 'wpmatch' ), implode( ', ', $allowed_types ) )
			);
		}

		// Additional security checks.
		if ( ! $this->is_safe_file( $file['tmp_name'], $file_info['type'] ) ) {
			return new WP_Error( 'unsafe_file', __( 'File failed security checks.', 'wpmatch' ) );
		}

		return true;
	}

	/**
	 * Handle file upload and processing.
	 *
	 * @param array  $file File data.
	 * @param int    $user_id User ID.
	 * @param string $media_type Media type.
	 * @return array|WP_Error Upload result or error.
	 */
	private function handle_file_upload( $file, $user_id, $media_type ) {
		// Create upload directory.
		$upload_dir = $this->get_upload_directory( $user_id, $media_type );
		if ( ! wp_mkdir_p( $upload_dir ) ) {
			return new WP_Error( 'directory_error', __( 'Failed to create upload directory.', 'wpmatch' ) );
		}

		// Generate unique filename.
		$file_extension = pathinfo( $file['name'], PATHINFO_EXTENSION );
		$file_name = sprintf(
			'%s_%d_%s.%s',
			$media_type,
			$user_id,
			wp_generate_password( 12, false ),
			$file_extension
		);

		$file_path = $upload_dir . '/' . $file_name;

		// Move uploaded file.
		if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) {
			return new WP_Error( 'move_failed', __( 'Failed to move uploaded file.', 'wpmatch' ) );
		}

		// Set proper file permissions.
		chmod( $file_path, 0644 );

		// Process file based on type.
		$processed_files = array();
		if ( 'photo' === $media_type ) {
			$processed_files = $this->process_image( $file_path, $file_name );
		} else {
			$processed_files = $this->process_video( $file_path, $file_name );
		}

		return array(
			'file_path'       => $file_path,
			'file_name'       => $file_name,
			'original_name'   => sanitize_file_name( $file['name'] ),
			'mime_type'       => $file['type'],
			'file_size'       => $file['size'],
			'processed_files' => $processed_files,
		);
	}

	/**
	 * Process uploaded image.
	 *
	 * @param string $file_path Original file path.
	 * @param string $file_name File name.
	 * @return array Processed image sizes.
	 */
	private function process_image( $file_path, $file_name ) {
		$processed = array();
		$image = wp_get_image_editor( $file_path );

		if ( is_wp_error( $image ) ) {
			return $processed;
		}

		$file_info = pathinfo( $file_name );
		$base_name = $file_info['filename'];
		$extension = $file_info['extension'];
		$upload_dir = dirname( $file_path );

		// Create different sizes.
		foreach ( $this->image_sizes as $size_name => $dimensions ) {
			$resized_image = clone $image;

			// Resize image.
			$resize_result = $resized_image->resize( $dimensions[0], $dimensions[1], true );

			if ( ! is_wp_error( $resize_result ) ) {
				$size_filename = sprintf( '%s_%s.%s', $base_name, $size_name, $extension );
				$size_path = $upload_dir . '/' . $size_filename;

				$save_result = $resized_image->save( $size_path );

				if ( ! is_wp_error( $save_result ) ) {
					$processed[ $size_name ] = array(
						'file_path' => $size_path,
						'file_name' => $size_filename,
						'width'     => $save_result['width'],
						'height'    => $save_result['height'],
						'url'       => $this->get_media_url( $size_path ),
					);
				}
			}
		}

		return $processed;
	}

	/**
	 * Process uploaded video.
	 *
	 * @param string $file_path Original file path.
	 * @param string $file_name File name.
	 * @return array Processed video data.
	 */
	private function process_video( $file_path, $file_name ) {
		$processed = array();

		// Queue video processing job for background processing.
		if ( class_exists( 'WPMatch_Job_Queue' ) ) {
			WPMatch_Job_Queue::get_instance()->queue_job(
				'process_video',
				array(
					'file_path' => $file_path,
					'file_name' => $file_name,
				),
				3 // High priority.
			);
		}

		// For now, just return basic info.
		$processed['original'] = array(
			'file_path' => $file_path,
			'file_name' => $file_name,
			'url'       => $this->get_media_url( $file_path ),
		);

		return $processed;
	}

	/**
	 * Insert media record into database.
	 *
	 * @param int    $user_id User ID.
	 * @param string $media_type Media type.
	 * @param array  $upload_result Upload result.
	 * @param bool   $is_primary Is primary media.
	 * @param int    $display_order Display order.
	 * @return int|false Media ID or false on failure.
	 */
	private function insert_media_record( $user_id, $media_type, $upload_result, $is_primary, $display_order ) {
		global $wpdb;

		$verification_status = get_option( 'wpmatch_auto_approve_media', false ) ? 'approved' : 'pending';

		$result = $wpdb->insert(
			$wpdb->prefix . 'wpmatch_user_media',
			array(
				'user_id'             => $user_id,
				'media_type'          => $media_type,
				'file_path'           => $upload_result['file_path'],
				'file_name'           => $upload_result['file_name'],
				'mime_type'           => $upload_result['mime_type'],
				'file_size'           => $upload_result['file_size'],
				'is_primary'          => $is_primary ? 1 : 0,
				'display_order'       => $display_order,
				'is_verified'         => 'approved' === $verification_status ? 1 : 0,
				'verification_status' => $verification_status,
				'created_at'          => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		$media_id = $wpdb->insert_id;

		// Cache invalidation.
		if ( class_exists( 'WPMatch_Cache_Manager' ) ) {
			$cache = WPMatch_Cache_Manager::get_instance();
			$cache->delete( "user_media_{$user_id}", 'media' );
			$cache->delete( "profile_{$user_id}", 'profiles' );
		}

		return $media_id;
	}

	/**
	 * Get user media.
	 *
	 * @param int    $user_id User ID.
	 * @param string $media_type Media type filter.
	 * @param bool   $verified_only Only verified media.
	 * @return array Media records.
	 */
	public function get_user_media( $user_id, $media_type = 'all', $verified_only = false ) {
		// Try cache first.
		if ( class_exists( 'WPMatch_Cache_Manager' ) ) {
			$cache_key = "user_media_{$user_id}_{$media_type}_" . ( $verified_only ? 'verified' : 'all' );
			$cache = WPMatch_Cache_Manager::get_instance();
			$cached_data = $cache->get( $cache_key, 'media' );

			if ( false !== $cached_data ) {
				return $cached_data;
			}
		}

		global $wpdb;

		$where_clauses = array( 'user_id = %d' );
		$where_values = array( $user_id );

		if ( 'all' !== $media_type ) {
			$where_clauses[] = 'media_type = %s';
			$where_values[] = $media_type;
		}

		if ( $verified_only ) {
			$where_clauses[] = 'is_verified = 1';
		}

		$where_sql = implode( ' AND ', $where_clauses );

		$media_records = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpmatch_user_media
				WHERE {$where_sql}
				ORDER BY is_primary DESC, display_order ASC, created_at ASC",
				...$where_values
			)
		);

		// Process media records to add URLs and processed files info.
		$processed_media = array();
		foreach ( $media_records as $media ) {
			$media_data = $this->format_media_record( $media );
			$processed_media[] = $media_data;
		}

		// Cache the result.
		if ( class_exists( 'WPMatch_Cache_Manager' ) && isset( $cache ) ) {
			$cache->set( $cache_key, $processed_media, 'media' );
		}

		return $processed_media;
	}

	/**
	 * Format media record with URLs and additional data.
	 *
	 * @param object $media Media record.
	 * @return array Formatted media data.
	 */
	private function format_media_record( $media ) {
		$media_data = (array) $media;

		// Add primary URL.
		$media_data['url'] = $this->get_media_url( $media->file_path );

		// Add processed files URLs for images.
		if ( 'photo' === $media->media_type ) {
			$media_data['sizes'] = $this->get_image_sizes_urls( $media->file_path, $media->file_name );
		}

		// Add verification badge.
		$media_data['is_verified'] = (bool) $media->is_verified;
		$media_data['verification_status'] = $media->verification_status;

		// Add human-readable file size.
		$media_data['file_size_formatted'] = size_format( $media->file_size );

		return $media_data;
	}

	/**
	 * Get image size URLs.
	 *
	 * @param string $original_path Original file path.
	 * @param string $file_name File name.
	 * @return array Size URLs.
	 */
	private function get_image_sizes_urls( $original_path, $file_name ) {
		$sizes = array();
		$file_info = pathinfo( $file_name );
		$base_name = $file_info['filename'];
		$extension = $file_info['extension'];
		$upload_dir = dirname( $original_path );

		foreach ( $this->image_sizes as $size_name => $dimensions ) {
			$size_filename = sprintf( '%s_%s.%s', $base_name, $size_name, $extension );
			$size_path = $upload_dir . '/' . $size_filename;

			if ( file_exists( $size_path ) ) {
				$sizes[ $size_name ] = array(
					'url'    => $this->get_media_url( $size_path ),
					'width'  => $dimensions[0],
					'height' => $dimensions[1],
				);
			}
		}

		return $sizes;
	}

	/**
	 * Delete media.
	 *
	 * @param int $media_id Media ID.
	 * @param int $user_id User ID (for permission check).
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete_media( $media_id, $user_id = null ) {
		global $wpdb;

		// Get media record.
		$media = $this->get_media_by_id( $media_id );
		if ( ! $media ) {
			return new WP_Error( 'media_not_found', __( 'Media not found.', 'wpmatch' ) );
		}

		// Permission check.
		if ( $user_id && $media->user_id != $user_id && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'permission_denied', __( 'Permission denied.', 'wpmatch' ) );
		}

		// Delete from database.
		$deleted = $wpdb->delete(
			$wpdb->prefix . 'wpmatch_user_media',
			array( 'media_id' => $media_id ),
			array( '%d' )
		);

		if ( false === $deleted ) {
			return new WP_Error( 'database_error', __( 'Failed to delete media record.', 'wpmatch' ) );
		}

		// Queue file cleanup.
		do_action( 'wpmatch_media_deleted', $media );

		// Cache invalidation.
		if ( class_exists( 'WPMatch_Cache_Manager' ) ) {
			$cache = WPMatch_Cache_Manager::get_instance();
			$cache->delete( "user_media_{$media->user_id}", 'media' );
			$cache->delete( "profile_{$media->user_id}", 'profiles' );
		}

		return true;
	}

	/**
	 * Set primary media.
	 *
	 * @param int $media_id Media ID.
	 * @param int $user_id User ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function set_primary_media( $media_id, $user_id ) {
		global $wpdb;

		// Get media record.
		$media = $this->get_media_by_id( $media_id );
		if ( ! $media || $media->user_id != $user_id ) {
			return new WP_Error( 'media_not_found', __( 'Media not found.', 'wpmatch' ) );
		}

		// Start transaction.
		$wpdb->query( 'START TRANSACTION' );

		// Unset current primary.
		$unset_result = $wpdb->update(
			$wpdb->prefix . 'wpmatch_user_media',
			array( 'is_primary' => 0 ),
			array( 'user_id' => $user_id, 'media_type' => $media->media_type ),
			array( '%d' ),
			array( '%d', '%s' )
		);

		if ( false === $unset_result ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'database_error', __( 'Failed to update primary media.', 'wpmatch' ) );
		}

		// Set new primary.
		$set_result = $wpdb->update(
			$wpdb->prefix . 'wpmatch_user_media',
			array( 'is_primary' => 1 ),
			array( 'media_id' => $media_id ),
			array( '%d' ),
			array( '%d' )
		);

		if ( false === $set_result ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'database_error', __( 'Failed to set primary media.', 'wpmatch' ) );
		}

		$wpdb->query( 'COMMIT' );

		// Cache invalidation.
		if ( class_exists( 'WPMatch_Cache_Manager' ) ) {
			$cache = WPMatch_Cache_Manager::get_instance();
			$cache->delete( "user_media_{$user_id}", 'media' );
			$cache->delete( "profile_{$user_id}", 'profiles' );
		}

		return true;
	}

	/**
	 * Reorder user media.
	 *
	 * @param array $media_ids Array of media IDs in new order.
	 * @param int   $user_id User ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function reorder_media( $media_ids, $user_id ) {
		global $wpdb;

		// Validate that all media belongs to user.
		$user_media = $this->get_user_media( $user_id );
		$user_media_ids = wp_list_pluck( $user_media, 'media_id' );

		foreach ( $media_ids as $media_id ) {
			if ( ! in_array( $media_id, $user_media_ids, true ) ) {
				return new WP_Error( 'invalid_media', __( 'One or more media items do not belong to this user.', 'wpmatch' ) );
			}
		}

		// Update display order.
		foreach ( $media_ids as $index => $media_id ) {
			$wpdb->update(
				$wpdb->prefix . 'wpmatch_user_media',
				array( 'display_order' => $index + 1 ),
				array( 'media_id' => $media_id ),
				array( '%d' ),
				array( '%d' )
			);
		}

		// Cache invalidation.
		if ( class_exists( 'WPMatch_Cache_Manager' ) ) {
			$cache = WPMatch_Cache_Manager::get_instance();
			$cache->delete( "user_media_{$user_id}", 'media' );
		}

		return true;
	}

	/**
	 * Verify media (admin function).
	 *
	 * @param int $media_id Media ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function verify_media( $media_id ) {
		global $wpdb;

		$result = $wpdb->update(
			$wpdb->prefix . 'wpmatch_user_media',
			array(
				'is_verified'         => 1,
				'verification_status' => 'approved',
			),
			array( 'media_id' => $media_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'database_error', __( 'Failed to verify media.', 'wpmatch' ) );
		}

		// Get media record to invalidate cache.
		$media = $this->get_media_by_id( $media_id );
		if ( $media ) {
			// Cache invalidation.
			if ( class_exists( 'WPMatch_Cache_Manager' ) ) {
				$cache = WPMatch_Cache_Manager::get_instance();
				$cache->delete( "user_media_{$media->user_id}", 'media' );
			}

			// Notify user.
			do_action( 'wpmatch_media_verified', $media_id, $media->user_id );
		}

		return true;
	}

	/**
	 * Reject media (admin function).
	 *
	 * @param int    $media_id Media ID.
	 * @param string $reason Rejection reason.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function reject_media( $media_id, $reason = '' ) {
		global $wpdb;

		$result = $wpdb->update(
			$wpdb->prefix . 'wpmatch_user_media',
			array(
				'is_verified'         => 0,
				'verification_status' => 'rejected',
			),
			array( 'media_id' => $media_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'database_error', __( 'Failed to reject media.', 'wpmatch' ) );
		}

		// Get media record.
		$media = $this->get_media_by_id( $media_id );
		if ( $media ) {
			// Cache invalidation.
			if ( class_exists( 'WPMatch_Cache_Manager' ) ) {
				$cache = WPMatch_Cache_Manager::get_instance();
				$cache->delete( "user_media_{$media->user_id}", 'media' );
			}

			// Notify user.
			do_action( 'wpmatch_media_rejected', $media_id, $media->user_id, $reason );
		}

		return true;
	}

	/**
	 * Utility methods.
	 */

	/**
	 * Get media by ID.
	 *
	 * @param int $media_id Media ID.
	 * @return object|null Media record or null.
	 */
	public function get_media_by_id( $media_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpmatch_user_media WHERE media_id = %d",
				$media_id
			)
		);
	}

	/**
	 * Get user media count.
	 *
	 * @param int    $user_id User ID.
	 * @param string $media_type Media type.
	 * @return int Media count.
	 */
	public function get_user_media_count( $user_id, $media_type = 'all' ) {
		global $wpdb;

		$where_clause = 'user_id = %d';
		$where_values = array( $user_id );

		if ( 'all' !== $media_type ) {
			$where_clause .= ' AND media_type = %s';
			$where_values[] = $media_type;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wpmatch_user_media WHERE {$where_clause}",
				...$where_values
			)
		);
	}

	/**
	 * Get pending media for admin review.
	 *
	 * @param int $page Page number.
	 * @param int $per_page Items per page.
	 * @return array Pending media records.
	 */
	public function get_pending_media( $page = 1, $per_page = 20 ) {
		global $wpdb;

		$offset = ( $page - 1 ) * $per_page;

		$media_records = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.*, u.display_name, u.user_email
				FROM {$wpdb->prefix}wpmatch_user_media m
				LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
				WHERE m.verification_status = 'pending'
				ORDER BY m.created_at ASC
				LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);

		$formatted_media = array();
		foreach ( $media_records as $media ) {
			$formatted_media[] = $this->format_media_record( $media );
		}

		return $formatted_media;
	}

	/**
	 * Get upload directory for user.
	 *
	 * @param int    $user_id User ID.
	 * @param string $media_type Media type.
	 * @return string Upload directory path.
	 */
	private function get_upload_directory( $user_id, $media_type ) {
		$upload_dir = wp_upload_dir();
		$base_dir = $upload_dir['basedir'] . '/wpmatch';

		// Create subdirectories by user ID (for organization and privacy).
		$user_subdir = sprintf( '%s/%d', $media_type, $user_id );

		return $base_dir . '/' . $user_subdir;
	}

	/**
	 * Get media URL.
	 *
	 * @param string $file_path File path.
	 * @return string Media URL.
	 */
	private function get_media_url( $file_path ) {
		$upload_dir = wp_upload_dir();
		$base_dir = $upload_dir['basedir'];
		$base_url = $upload_dir['baseurl'];

		// Replace base directory with base URL.
		return str_replace( $base_dir, $base_url, $file_path );
	}

	/**
	 * Get next display order for user.
	 *
	 * @param int $user_id User ID.
	 * @return int Next display order.
	 */
	private function get_next_display_order( $user_id ) {
		global $wpdb;

		$max_order = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(display_order) FROM {$wpdb->prefix}wpmatch_user_media WHERE user_id = %d",
				$user_id
			)
		);

		return $max_order ? $max_order + 1 : 1;
	}

	/**
	 * Unset primary media for user.
	 *
	 * @param int    $user_id User ID.
	 * @param string $media_type Media type.
	 */
	private function unset_primary_media( $user_id, $media_type ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'wpmatch_user_media',
			array( 'is_primary' => 0 ),
			array( 'user_id' => $user_id, 'media_type' => $media_type ),
			array( '%d' ),
			array( '%d', '%s' )
		);
	}

	/**
	 * Check if file is safe.
	 *
	 * @param string $file_path File path.
	 * @param string $mime_type MIME type.
	 * @return bool Whether file is safe.
	 */
	private function is_safe_file( $file_path, $mime_type ) {
		// Check for embedded PHP code in images.
		if ( strpos( $mime_type, 'image/' ) === 0 ) {
			$file_contents = file_get_contents( $file_path, false, null, 0, 1024 );
			if ( strpos( $file_contents, '<?php' ) !== false ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get upload error message.
	 *
	 * @param int $error_code Error code.
	 * @return string Error message.
	 */
	private function get_upload_error_message( $error_code ) {
		switch ( $error_code ) {
			case UPLOAD_ERR_INI_SIZE:
				return __( 'File size exceeds the maximum allowed by server configuration.', 'wpmatch' );
			case UPLOAD_ERR_FORM_SIZE:
				return __( 'File size exceeds the maximum allowed by form.', 'wpmatch' );
			case UPLOAD_ERR_PARTIAL:
				return __( 'File was only partially uploaded.', 'wpmatch' );
			case UPLOAD_ERR_NO_FILE:
				return __( 'No file was uploaded.', 'wpmatch' );
			case UPLOAD_ERR_NO_TMP_DIR:
				return __( 'Missing temporary upload directory.', 'wpmatch' );
			case UPLOAD_ERR_CANT_WRITE:
				return __( 'Failed to write file to disk.', 'wpmatch' );
			case UPLOAD_ERR_EXTENSION:
				return __( 'File upload stopped by extension.', 'wpmatch' );
			default:
				return __( 'Unknown upload error.', 'wpmatch' );
		}
	}

	/**
	 * Queue media processing job.
	 *
	 * @param int   $media_id Media ID.
	 * @param array $upload_result Upload result.
	 */
	public function queue_media_processing( $media_id, $upload_result ) {
		if ( class_exists( 'WPMatch_Job_Queue' ) ) {
			WPMatch_Job_Queue::get_instance()->queue_job(
				'process_image',
				array(
					'media_id'    => $media_id,
					'image_path'  => $upload_result['file_path'],
					'operations'  => array(
						array( 'type' => 'optimize' ),
						array( 'type' => 'watermark' ),
					),
				),
				3 // High priority.
			);
		}
	}

	/**
	 * Cleanup media files when deleted.
	 *
	 * @param object $media Media record.
	 */
	public function cleanup_media_files( $media ) {
		// Delete original file.
		if ( file_exists( $media->file_path ) ) {
			unlink( $media->file_path );
		}

		// Delete processed files for images.
		if ( 'photo' === $media->media_type ) {
			$file_info = pathinfo( $media->file_name );
			$base_name = $file_info['filename'];
			$extension = $file_info['extension'];
			$upload_dir = dirname( $media->file_path );

			foreach ( array_keys( $this->image_sizes ) as $size_name ) {
				$size_filename = sprintf( '%s_%s.%s', $base_name, $size_name, $extension );
				$size_path = $upload_dir . '/' . $size_filename;

				if ( file_exists( $size_path ) ) {
					unlink( $size_path );
				}
			}
		}
	}

	/**
	 * Cleanup all media for user when deleted.
	 *
	 * @param int $user_id User ID.
	 */
	public function cleanup_user_media( $user_id ) {
		$user_media = $this->get_user_media( $user_id );

		foreach ( $user_media as $media ) {
			$this->delete_media( $media['media_id'] );
		}
	}

	/**
	 * AJAX handlers.
	 */

	/**
	 * AJAX handler for media upload.
	 */
	public function ajax_upload_media() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to upload media.', 'wpmatch' ) ) );
		}

		check_ajax_referer( 'wpmatch_upload_media', 'nonce' );

		if ( empty( $_FILES['media_file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'wpmatch' ) ) );
		}

		$media_type = sanitize_text_field( $_POST['media_type'] ?? 'photo' );
		$is_primary = ! empty( $_POST['is_primary'] );
		$user_id = get_current_user_id();

		$result = $this->upload_media( $_FILES['media_file'], $user_id, $media_type, $is_primary );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler for media deletion.
	 */
	public function ajax_delete_media() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'wpmatch' ) ) );
		}

		check_ajax_referer( 'wpmatch_delete_media', 'nonce' );

		$media_id = absint( $_POST['media_id'] ?? 0 );
		$user_id = get_current_user_id();

		$result = $this->delete_media( $media_id, $user_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Media deleted successfully.', 'wpmatch' ) ) );
	}

	/**
	 * AJAX handler for media reordering.
	 */
	public function ajax_reorder_media() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'wpmatch' ) ) );
		}

		check_ajax_referer( 'wpmatch_reorder_media', 'nonce' );

		$media_ids = array_map( 'absint', $_POST['media_ids'] ?? array() );
		$user_id = get_current_user_id();

		$result = $this->reorder_media( $media_ids, $user_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Media reordered successfully.', 'wpmatch' ) ) );
	}

	/**
	 * AJAX handler for setting primary media.
	 */
	public function ajax_set_primary_media() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'wpmatch' ) ) );
		}

		check_ajax_referer( 'wpmatch_set_primary_media', 'nonce' );

		$media_id = absint( $_POST['media_id'] ?? 0 );
		$user_id = get_current_user_id();

		$result = $this->set_primary_media( $media_id, $user_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Primary media updated successfully.', 'wpmatch' ) ) );
	}

	/**
	 * AJAX handler for getting user media.
	 */
	public function ajax_get_user_media() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'wpmatch' ) ) );
		}

		$user_id = get_current_user_id();
		$media_type = sanitize_text_field( $_GET['media_type'] ?? 'all' );
		$verified_only = ! empty( $_GET['verified_only'] );

		$media = $this->get_user_media( $user_id, $media_type, $verified_only );

		wp_send_json_success( array( 'media' => $media ) );
	}

	/**
	 * Permission callbacks for REST API.
	 */

	/**
	 * Check if user can view media.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool Permission granted.
	 */
	public function can_view_user_media( $request ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user_id = absint( $request->get_param( 'user_id' ) );
		$current_user_id = get_current_user_id();

		// Users can view their own media or admins can view any.
		return $user_id === $current_user_id || current_user_can( 'manage_options' );
	}

	/**
	 * Check if user can delete media.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool Permission granted.
	 */
	public function can_delete_media( $request ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$media_id = absint( $request->get_param( 'media_id' ) );
		$media = $this->get_media_by_id( $media_id );

		if ( ! $media ) {
			return false;
		}

		$current_user_id = get_current_user_id();

		// Users can delete their own media or admins can delete any.
		return $media->user_id == $current_user_id || current_user_can( 'manage_options' );
	}

	/**
	 * Check if user can edit media.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool Permission granted.
	 */
	public function can_edit_media( $request ) {
		return $this->can_delete_media( $request );
	}

	/**
	 * REST API endpoints.
	 */

	/**
	 * REST endpoint for media upload.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_upload_media( $request ) {
		if ( empty( $_FILES['file'] ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => __( 'No file uploaded.', 'wpmatch' ),
				)
			);
		}

		$media_type = $request->get_param( 'media_type' );
		$is_primary = $request->get_param( 'is_primary' );
		$user_id = get_current_user_id();

		$result = $this->upload_media( $_FILES['file'], $user_id, $media_type, $is_primary );

		if ( is_wp_error( $result ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => $result->get_error_message(),
				)
			);
		}

		return rest_ensure_response( $result );
	}

	/**
	 * REST endpoint for getting user media.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_get_user_media( $request ) {
		$user_id = absint( $request->get_param( 'user_id' ) );
		$media_type = $request->get_param( 'media_type' );
		$verified_only = $request->get_param( 'verified_only' );

		$media = $this->get_user_media( $user_id, $media_type, $verified_only );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $media,
			)
		);
	}

	/**
	 * REST endpoint for deleting media.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_delete_media( $request ) {
		$media_id = absint( $request->get_param( 'media_id' ) );

		$result = $this->delete_media( $media_id );

		if ( is_wp_error( $result ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => $result->get_error_message(),
				)
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Media deleted successfully.', 'wpmatch' ),
			)
		);
	}

	/**
	 * REST endpoint for updating media.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_update_media( $request ) {
		$media_id = absint( $request->get_param( 'media_id' ) );
		$is_primary = $request->get_param( 'is_primary' );
		$display_order = $request->get_param( 'display_order' );

		// Get media record.
		$media = $this->get_media_by_id( $media_id );
		if ( ! $media ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => __( 'Media not found.', 'wpmatch' ),
				)
			);
		}

		global $wpdb;
		$updates = array();
		$formats = array();

		if ( null !== $is_primary ) {
			if ( $is_primary ) {
				// Unset current primary first.
				$this->unset_primary_media( $media->user_id, $media->media_type );
			}
			$updates['is_primary'] = $is_primary ? 1 : 0;
			$formats[] = '%d';
		}

		if ( null !== $display_order ) {
			$updates['display_order'] = absint( $display_order );
			$formats[] = '%d';
		}

		if ( ! empty( $updates ) ) {
			$result = $wpdb->update(
				$wpdb->prefix . 'wpmatch_user_media',
				$updates,
				array( 'media_id' => $media_id ),
				$formats,
				array( '%d' )
			);

			if ( false === $result ) {
				return rest_ensure_response(
					array(
						'success' => false,
						'message' => __( 'Failed to update media.', 'wpmatch' ),
					)
				);
			}

			// Cache invalidation.
			if ( class_exists( 'WPMatch_Cache_Manager' ) ) {
				$cache = WPMatch_Cache_Manager::get_instance();
				$cache->delete( "user_media_{$media->user_id}", 'media' );
			}
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Media updated successfully.', 'wpmatch' ),
			)
		);
	}

	/**
	 * REST endpoint for reordering media.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_reorder_media( $request ) {
		$media_ids = $request->get_param( 'media_ids' );
		$user_id = get_current_user_id();

		$result = $this->reorder_media( $media_ids, $user_id );

		if ( is_wp_error( $result ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => $result->get_error_message(),
				)
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Media reordered successfully.', 'wpmatch' ),
			)
		);
	}

	/**
	 * REST endpoint for verifying media.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_verify_media( $request ) {
		$media_id = absint( $request->get_param( 'media_id' ) );

		$result = $this->verify_media( $media_id );

		if ( is_wp_error( $result ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => $result->get_error_message(),
				)
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Media verified successfully.', 'wpmatch' ),
			)
		);
	}

	/**
	 * REST endpoint for rejecting media.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_reject_media( $request ) {
		$media_id = absint( $request->get_param( 'media_id' ) );
		$reason = sanitize_text_field( $request->get_param( 'reason' ) );

		$result = $this->reject_media( $media_id, $reason );

		if ( is_wp_error( $result ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => $result->get_error_message(),
				)
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Media rejected successfully.', 'wpmatch' ),
			)
		);
	}

	/**
	 * REST endpoint for getting pending media.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_get_pending_media( $request ) {
		$page = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );

		$media = $this->get_pending_media( $page, $per_page );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $media,
			)
		);
	}
}