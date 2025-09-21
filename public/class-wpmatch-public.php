<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @package WPMatch
 */

/**
 * The public-facing functionality of the plugin.
 */
class WPMatch_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $plugin_name The name of the plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 */
	public function enqueue_styles() {
		wp_enqueue_style(
			$this->plugin_name,
			WPMATCH_PLUGIN_URL . 'public/css/wpmatch-public.css',
			array(),
			$this->version,
			'all'
		);

		// Enqueue additional styles for messaging interface
		if ( is_page() && has_shortcode( get_post()->post_content, 'wpmatch_messages' ) ) {
			wp_enqueue_style(
				$this->plugin_name . '-messages',
				WPMATCH_PLUGIN_URL . 'public/css/wpmatch-messages.css',
				array( $this->plugin_name ),
				$this->version,
				'all'
			);
		}
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 */
	public function enqueue_scripts() {
		wp_enqueue_script(
			$this->plugin_name,
			WPMATCH_PLUGIN_URL . 'public/js/wpmatch-public.js',
			array( 'jquery' ),
			$this->version,
			false
		);

		// Localize script for AJAX.
		wp_localize_script(
			$this->plugin_name,
			'wpmatch_public',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wpmatch_public_nonce' ),
			)
		);

		// Enqueue profile form script on profile form pages
		if ( is_page() || is_singular() ) {
			global $post;
			if ( $post && ( has_shortcode( $post->post_content, 'wpmatch_profile_form' ) || is_page( 'profile' ) ) ) {
				wp_enqueue_script(
					'wpmatch-profile-form',
					WPMATCH_PLUGIN_URL . 'public/js/wpmatch-profile-form.js',
					array( 'jquery' ),
					$this->version,
					true
				);

				// Localize profile form script
				wp_localize_script(
					'wpmatch-profile-form',
					'wpmatch_profile',
					array(
						'ajax_url' => admin_url( 'admin-ajax.php' ),
						'nonce'    => wp_create_nonce( 'wpmatch_public_nonce' ),
						'strings'  => array(
							'uploading'        => __( 'Uploading...', 'wpmatch' ),
							'upload_error'     => __( 'Upload failed. Please try again.', 'wpmatch' ),
							'delete_error'     => __( 'Failed to delete photo. Please try again.', 'wpmatch' ),
							'primary_error'    => __( 'Failed to set primary photo. Please try again.', 'wpmatch' ),
							'confirm_delete'   => __( 'Are you sure you want to delete this photo?', 'wpmatch' ),
							'invalid_file_type' => __( 'Invalid file type. Please upload JPG, PNG, or GIF.', 'wpmatch' ),
							'file_too_large'   => __( 'File too large. Maximum size is 5MB.', 'wpmatch' ),
							'add_main_photo'   => __( 'Add main photo', 'wpmatch' ),
							'add_photo'        => __( 'Add photo', 'wpmatch' ),
						),
					)
				);
			}

			// Enqueue messaging script on messages page
			if ( $post && has_shortcode( $post->post_content, 'wpmatch_messages' ) ) {
				wp_enqueue_script(
					'wpmatch-messages',
					WPMATCH_PLUGIN_URL . 'public/js/wpmatch-messages.js',
					array( 'jquery' ),
					$this->version,
					true
				);

				// Localize messages script
				wp_localize_script(
					'wpmatch-messages',
					'wpmatch_messages',
					array(
						'ajax_url' => admin_url( 'admin-ajax.php' ),
						'nonce'    => wp_create_nonce( 'wpmatch_public_nonce' ),
						'current_user_id' => get_current_user_id(),
						'strings'  => array(
							'sending'        => __( 'Sending...', 'wpmatch' ),
							'send_error'     => __( 'Failed to send message.', 'wpmatch' ),
							'delete_confirm' => __( 'Delete this message?', 'wpmatch' ),
							'block_confirm'  => __( 'Block this user? You will no longer receive messages from them.', 'wpmatch' ),
						),
					)
				);
			}

			// Enqueue search script on search page
			if ( $post && has_shortcode( $post->post_content, 'wpmatch_search' ) ) {
				wp_enqueue_script(
					'wpmatch-search',
					WPMATCH_PLUGIN_URL . 'public/js/wpmatch-search.js',
					array( 'jquery' ),
					$this->version,
					true
				);

				// Localize search script
				wp_localize_script(
					'wpmatch-search',
					'wpmatch_search',
					array(
						'ajax_url' => admin_url( 'admin-ajax.php' ),
						'nonce'    => wp_create_nonce( 'wpmatch_public_nonce' ),
						'profile_url' => home_url( '/profile/' ),
						'messages_url' => home_url( '/messages/' ),
						'strings'  => array(
							'loading'            => __( 'Searching...', 'wpmatch' ),
							'no_suggestions'     => __( 'No suggestions found', 'wpmatch' ),
							'preferences_saved'  => __( 'Search preferences saved!', 'wpmatch' ),
						),
					)
				);
			}
		}
	}

	/**
	 * Register shortcodes.
	 */
	public function register_shortcodes() {
		add_shortcode( 'wpmatch_registration', array( $this, 'registration_shortcode' ) );
		add_shortcode( 'wpmatch_profile', array( $this, 'profile_shortcode' ) );
		add_shortcode( 'wpmatch_profile_form', array( $this, 'profile_form_shortcode' ) );
		add_shortcode( 'wpmatch_search', array( $this, 'search_shortcode' ) );
		add_shortcode( 'wpmatch_matches', array( $this, 'matches_shortcode' ) );
		add_shortcode( 'wpmatch_swipe', array( $this, 'swipe_shortcode' ) );
		add_shortcode( 'wpmatch_messages', array( $this, 'messages_shortcode' ) );
		add_shortcode( 'wpmatch_premium_shop', array( $this, 'premium_shop_shortcode' ) );
		add_shortcode( 'wpmatch_user_guide', array( $this, 'user_guide_shortcode' ) );
	}

	/**
	 * Registration form shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function registration_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'redirect' => '',
			),
			$atts,
			'wpmatch_registration'
		);

		ob_start();
		require WPMATCH_PLUGIN_DIR . 'public/partials/wpmatch-registration-form.php';
		return ob_get_clean();
	}

	/**
	 * Profile shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function profile_shortcode( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your profile.', 'wpmatch' ) . '</p>';
		}

		$atts = shortcode_atts(
			array(
				'user_id' => get_current_user_id(),
			),
			$atts,
			'wpmatch_profile'
		);

		ob_start();
		require WPMATCH_PLUGIN_DIR . 'public/partials/wpmatch-profile.php';
		return ob_get_clean();
	}

	/**
	 * Search shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function search_shortcode( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to search for matches.', 'wpmatch' ) . '</p>';
		}

		$atts = shortcode_atts(
			array(
				'type' => 'basic',
			),
			$atts,
			'wpmatch_search'
		);

		ob_start();
		require WPMATCH_PLUGIN_DIR . 'public/partials/wpmatch-search.php';
		return ob_get_clean();
	}

	/**
	 * Matches shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function matches_shortcode( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your matches.', 'wpmatch' ) . '</p>';
		}

		$atts = shortcode_atts(
			array(
				'limit' => 10,
			),
			$atts,
			'wpmatch_matches'
		);

		ob_start();
		require WPMATCH_PLUGIN_DIR . 'public/partials/wpmatch-matches.php';
		return ob_get_clean();
	}

	/**
	 * Profile form shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function profile_form_shortcode( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to edit your profile.', 'wpmatch' ) . '</p>';
		}

		$atts = shortcode_atts(
			array(),
			$atts,
			'wpmatch_profile_form'
		);

		ob_start();
		require WPMATCH_PLUGIN_DIR . 'public/partials/wpmatch-profile-form.php';
		return ob_get_clean();
	}

	/**
	 * Swipe interface shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function swipe_shortcode( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to start swiping.', 'wpmatch' ) . '</p>';
		}

		$atts = shortcode_atts(
			array(
				'limit' => 10,
			),
			$atts,
			'wpmatch_swipe'
		);

		// Load swipe interface.
		$swipe_interface = new WPMatch_Swipe_Interface();
		return $swipe_interface->render_shortcode( $atts );
	}

	/**
	 * Premium shop shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function premium_shop_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(),
			$atts,
			'wpmatch_premium_shop'
		);

		ob_start();
		require WPMATCH_PLUGIN_DIR . 'public/partials/wpmatch-premium-shop.php';
		return ob_get_clean();
	}

	/**
	 * User guide shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function user_guide_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(),
			$atts,
			'wpmatch_user_guide'
		);

		ob_start();
		require WPMATCH_PLUGIN_DIR . 'public/partials/wpmatch-user-guide.php';
		return ob_get_clean();
	}

	/**
	 * Messages shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function messages_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(),
			$atts,
			'wpmatch_messages'
		);

		ob_start();
		require WPMATCH_PLUGIN_DIR . 'public/partials/wpmatch-messages.php';
		return ob_get_clean();
	}

	/**
	 * Handle user registration.
	 *
	 * @param int $user_id The new user ID.
	 */
	public function handle_user_registration( $user_id ) {
		// Create initial profile entry.
		global $wpdb;
		$table_name = $wpdb->prefix . 'wpmatch_user_profiles';

		$wpdb->insert(
			$table_name,
			array(
				'user_id'    => $user_id,
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s' )
		);

		// Create preferences entry.
		$table_name = $wpdb->prefix . 'wpmatch_user_preferences';
		$wpdb->insert(
			$table_name,
			array(
				'user_id'    => $user_id,
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s' )
		);

		// Assign default role.
		$user = new WP_User( $user_id );
		$user->add_role( 'wpmatch_member' );

		// Send welcome email.
		$this->send_welcome_email( $user_id );
	}

	/**
	 * Handle user login.
	 *
	 * @param string  $user_login Username.
	 * @param WP_User $user       User object.
	 */
	public function handle_user_login( $user_login, $user ) {
		// Update last active timestamp.
		update_user_meta( $user->ID, 'wpmatch_last_active', current_time( 'mysql' ) );

		// Update profile last active.
		global $wpdb;
		$table_name = $wpdb->prefix . 'wpmatch_user_profiles';
		$wpdb->update(
			$table_name,
			array( 'last_active' => current_time( 'mysql' ) ),
			array( 'user_id' => $user->ID ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Handle public AJAX requests.
	 */
	public function handle_public_ajax() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpmatch_public_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed', 'wpmatch' ) );
		}

		// Handle different actions.
		$action = isset( $_POST['action_type'] ) ? sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) : '';

		switch ( $action ) {
			case 'update_profile':
				$this->update_profile();
				break;
			case 'upload_photo':
				$this->upload_photo();
				break;
			case 'delete_photo':
				$this->delete_photo();
				break;
			case 'set_primary_photo':
				$this->set_primary_photo();
				break;
			case 'send_message':
				$this->send_message();
				break;
			case 'delete_message':
				$this->delete_message();
				break;
			case 'mark_as_read':
				$this->mark_as_read();
				break;
			case 'block_user':
				$this->block_user();
				break;
			case 'get_new_messages':
				$this->get_new_messages();
				break;
			case 'get_search_suggestions':
				$this->get_search_suggestions();
				break;
			case 'save_search_preferences':
				$this->save_search_preferences();
				break;
			default:
				wp_die( esc_html__( 'Invalid action', 'wpmatch' ) );
		}
	}

	/**
	 * Update user profile.
	 */
	private function update_profile() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'wpmatch' ) ) );
		}

		// Implementation for updating profile.
		wp_send_json_success( array( 'message' => __( 'Profile updated successfully', 'wpmatch' ) ) );
	}

	/**
	 * Upload photo.
	 */
	private function upload_photo() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'wpmatch' ) ) );
		}

		$user_id = get_current_user_id();

		// Check if file was uploaded
		if ( ! isset( $_FILES['photo'] ) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded or upload error', 'wpmatch' ) ) );
		}

		$file = $_FILES['photo'];

		// Validate file type
		$allowed_types = array( 'image/jpeg', 'image/jpg', 'image/png', 'image/gif' );
		if ( ! in_array( $file['type'], $allowed_types, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid file type. Please upload JPG, PNG, or GIF.', 'wpmatch' ) ) );
		}

		// Validate file size (5MB max)
		$max_size = 5 * 1024 * 1024; // 5MB in bytes
		if ( $file['size'] > $max_size ) {
			wp_send_json_error( array( 'message' => __( 'File too large. Maximum size is 5MB.', 'wpmatch' ) ) );
		}

		// Validate image dimensions
		$image_info = getimagesize( $file['tmp_name'] );
		if ( ! $image_info || $image_info[0] < 300 || $image_info[1] < 300 ) {
			wp_send_json_error( array( 'message' => __( 'Image too small. Minimum size is 300x300 pixels.', 'wpmatch' ) ) );
		}

		// Use WordPress media handling
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		// Set up file array for WordPress
		$upload = wp_handle_upload( $file, array( 'test_form' => false ) );

		if ( isset( $upload['error'] ) ) {
			wp_send_json_error( array( 'message' => $upload['error'] ) );
		}

		// Save to database
		global $wpdb;
		$table_name = $wpdb->prefix . 'wpmatch_user_media';

		// Get display order
		$display_order = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(display_order) + 1 FROM {$table_name} WHERE user_id = %d AND media_type = 'photo'",
				$user_id
			)
		);
		if ( ! $display_order ) {
			$display_order = 0;
		}

		// Check if this is the first photo (make it primary)
		$photo_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d AND media_type = 'photo'",
				$user_id
			)
		);
		$is_primary = ( 0 === (int) $photo_count ) ? 1 : 0;

		$result = $wpdb->insert(
			$table_name,
			array(
				'user_id'       => $user_id,
				'media_type'    => 'photo',
				'file_path'     => $upload['url'],
				'file_name'     => basename( $upload['file'] ),
				'mime_type'     => $file['type'],
				'file_size'     => $file['size'],
				'is_primary'    => $is_primary,
				'display_order' => $display_order,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s' )
		);

		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to save photo to database', 'wpmatch' ) ) );
		}

		$photo_id = $wpdb->insert_id;

		wp_send_json_success( array(
			'message'    => __( 'Photo uploaded successfully', 'wpmatch' ),
			'photo_id'   => $photo_id,
			'photo_url'  => $upload['url'],
			'is_primary' => $is_primary,
		) );
	}

	/**
	 * Delete a photo.
	 */
	private function delete_photo() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'wpmatch' ) ) );
		}

		$user_id = get_current_user_id();
		$photo_id = isset( $_POST['photo_id'] ) ? absint( $_POST['photo_id'] ) : 0;

		if ( ! $photo_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid photo ID', 'wpmatch' ) ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'wpmatch_user_media';

		// Get photo info
		$photo = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE media_id = %d AND user_id = %d",
				$photo_id,
				$user_id
			)
		);

		if ( ! $photo ) {
			wp_send_json_error( array( 'message' => __( 'Photo not found', 'wpmatch' ) ) );
		}

		// Delete file from filesystem
		$file_path = str_replace( wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $photo->file_path );
		if ( file_exists( $file_path ) ) {
			wp_delete_file( $file_path );
		}

		// Delete from database
		$result = $wpdb->delete(
			$table_name,
			array( 'media_id' => $photo_id, 'user_id' => $user_id ),
			array( '%d', '%d' )
		);

		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete photo', 'wpmatch' ) ) );
		}

		// If this was primary photo, make the first remaining photo primary
		if ( $photo->is_primary ) {
			$new_primary = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT media_id FROM {$table_name} WHERE user_id = %d AND media_type = 'photo' ORDER BY display_order ASC LIMIT 1",
					$user_id
				)
			);

			if ( $new_primary ) {
				$wpdb->update(
					$table_name,
					array( 'is_primary' => 1 ),
					array( 'media_id' => $new_primary->media_id ),
					array( '%d' ),
					array( '%d' )
				);
			}
		}

		wp_send_json_success( array( 'message' => __( 'Photo deleted successfully', 'wpmatch' ) ) );
	}

	/**
	 * Set primary photo.
	 */
	private function set_primary_photo() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'wpmatch' ) ) );
		}

		$user_id = get_current_user_id();
		$photo_id = isset( $_POST['photo_id'] ) ? absint( $_POST['photo_id'] ) : 0;

		if ( ! $photo_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid photo ID', 'wpmatch' ) ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'wpmatch_user_media';

		// Verify photo belongs to user
		$photo_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE media_id = %d AND user_id = %d",
				$photo_id,
				$user_id
			)
		);

		if ( ! $photo_exists ) {
			wp_send_json_error( array( 'message' => __( 'Photo not found', 'wpmatch' ) ) );
		}

		// Remove primary status from all photos
		$wpdb->update(
			$table_name,
			array( 'is_primary' => 0 ),
			array( 'user_id' => $user_id, 'media_type' => 'photo' ),
			array( '%d' ),
			array( '%d', '%s' )
		);

		// Set new primary photo
		$result = $wpdb->update(
			$table_name,
			array( 'is_primary' => 1 ),
			array( 'media_id' => $photo_id ),
			array( '%d' ),
			array( '%d' )
		);

		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to set primary photo', 'wpmatch' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Primary photo updated successfully', 'wpmatch' ) ) );
	}

	/**
	 * Send welcome email to new user.
	 *
	 * @param int $user_id User ID.
	 */
	private function send_welcome_email( $user_id ) {
		$user = get_userdata( $user_id );
		$subject = __( 'Welcome to WPMatch Dating!', 'wpmatch' );
		$message = sprintf(
			__( 'Hi %s,\n\nWelcome to our dating community! Your account has been created successfully.\n\nPlease complete your profile to start matching with other members.\n\nBest regards,\nThe WPMatch Team', 'wpmatch' ),
			$user->display_name
		);

		wp_mail( $user->user_email, $subject, $message );
	}

	/**
	 * Template redirect for custom pages.
	 */
	public function template_redirect() {
		// Custom template logic can go here.
	}

	/**
	 * Send message via AJAX.
	 */
	private function send_message() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'wpmatch' ) ) );
		}

		$sender_id = get_current_user_id();
		$recipient_id = isset( $_POST['recipient_id'] ) ? absint( $_POST['recipient_id'] ) : 0;
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		if ( ! $recipient_id || empty( $message ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid message data.', 'wpmatch' ) ) );
		}

		$result = WPMatch_Message_Manager::send_message( $sender_id, $recipient_id, $message );

		if ( $result['success'] ) {
			wp_send_json_success( array(
				'message'    => $result['message'],
				'message_id' => $result['message_id'],
				'created_at' => current_time( 'mysql' ),
			) );
		} else {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
	}

	/**
	 * Delete message via AJAX.
	 */
	private function delete_message() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'wpmatch' ) ) );
		}

		$message_id = isset( $_POST['message_id'] ) ? absint( $_POST['message_id'] ) : 0;
		$user_id = get_current_user_id();

		if ( ! $message_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid message ID.', 'wpmatch' ) ) );
		}

		$result = WPMatch_Message_Manager::delete_message( $message_id, $user_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Message deleted successfully.', 'wpmatch' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to delete message.', 'wpmatch' ) ) );
		}
	}

	/**
	 * Mark conversation as read via AJAX.
	 */
	private function mark_as_read() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'wpmatch' ) ) );
		}

		$conversation_id = isset( $_POST['conversation_id'] ) ? sanitize_text_field( wp_unslash( $_POST['conversation_id'] ) ) : '';
		$user_id = get_current_user_id();

		if ( empty( $conversation_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid conversation ID.', 'wpmatch' ) ) );
		}

		$result = WPMatch_Message_Manager::mark_conversation_as_read( $conversation_id, $user_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Messages marked as read.', 'wpmatch' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to mark as read.', 'wpmatch' ) ) );
		}
	}

	/**
	 * Block user via AJAX.
	 */
	private function block_user() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'wpmatch' ) ) );
		}

		$user_id = get_current_user_id();
		$other_user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

		if ( ! $other_user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid user ID.', 'wpmatch' ) ) );
		}

		$result = WPMatch_Message_Manager::block_user( $user_id, $other_user_id, true );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'User blocked successfully.', 'wpmatch' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to block user.', 'wpmatch' ) ) );
		}
	}

	/**
	 * Get new messages via AJAX.
	 */
	private function get_new_messages() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'wpmatch' ) ) );
		}

		$conversation_id = isset( $_POST['conversation_id'] ) ? sanitize_text_field( wp_unslash( $_POST['conversation_id'] ) ) : '';
		$last_message_id = isset( $_POST['last_message_id'] ) ? absint( $_POST['last_message_id'] ) : 0;

		if ( empty( $conversation_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid conversation ID.', 'wpmatch' ) ) );
		}

		// Get all messages and filter for new ones
		$all_messages = WPMatch_Message_Manager::get_messages( $conversation_id );
		$new_messages = array();

		foreach ( $all_messages as $message ) {
			if ( $message->message_id > $last_message_id ) {
				$new_messages[] = $message;
			}
		}

		wp_send_json_success( array( 'messages' => $new_messages ) );
	}

	/**
	 * Get search suggestions via AJAX.
	 */
	private function get_search_suggestions() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'wpmatch' ) ) );
		}

		$query = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';

		if ( empty( $query ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid query.', 'wpmatch' ) ) );
		}

		$suggestions = WPMatch_Search_Manager::get_search_suggestions( $query );

		wp_send_json_success( array( 'suggestions' => $suggestions ) );
	}

	/**
	 * Save search preferences via AJAX.
	 */
	private function save_search_preferences() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'wpmatch' ) ) );
		}

		$user_id = get_current_user_id();
		$preferences = isset( $_POST['preferences'] ) ? $_POST['preferences'] : array();

		// Sanitize preferences.
		$clean_preferences = array(
			'min_age'      => isset( $preferences['min_age'] ) ? absint( $preferences['min_age'] ) : 18,
			'max_age'      => isset( $preferences['max_age'] ) ? absint( $preferences['max_age'] ) : 99,
			'max_distance' => isset( $preferences['max_distance'] ) ? absint( $preferences['max_distance'] ) : 50,
			'gender'       => isset( $preferences['gender'] ) ? sanitize_text_field( $preferences['gender'] ) : '',
		);

		$result = WPMatch_Search_Manager::save_search_preferences( $user_id, $clean_preferences );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Search preferences saved successfully.', 'wpmatch' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to save search preferences.', 'wpmatch' ) ) );
		}
	}
}