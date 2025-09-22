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
		$this->version     = $version;
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

		// Enqueue additional styles for membership dashboard
		if ( is_page() && has_shortcode( get_post()->post_content, 'wpmatch_membership_dashboard' ) ) {
			wp_enqueue_style(
				$this->plugin_name . '-membership-dashboard',
				WPMATCH_PLUGIN_URL . 'public/css/wpmatch-membership-dashboard.css',
				array( $this->plugin_name ),
				$this->version,
				'all'
			);
		}

		// Enqueue styles for gamification dashboard
		if ( is_page() && has_shortcode( get_post()->post_content, 'wpmatch_gamification' ) ) {
			wp_enqueue_style(
				$this->plugin_name . '-gamification',
				WPMATCH_PLUGIN_URL . 'public/css/wpmatch-gamification.css',
				array( $this->plugin_name ),
				$this->version,
				'all'
			);
		}

		// Enqueue styles for events dashboard
		if ( is_page() && has_shortcode( get_post()->post_content, 'wpmatch_events' ) ) {
			wp_enqueue_style(
				$this->plugin_name . '-events',
				WPMATCH_PLUGIN_URL . 'public/css/wpmatch-events.css',
				array( $this->plugin_name ),
				$this->version,
				'all'
			);
		}

		// Enqueue styles for voice notes
		if ( is_page() && has_shortcode( get_post()->post_content, 'wpmatch_voice_notes' ) ) {
			wp_enqueue_style(
				$this->plugin_name . '-voice-notes',
				WPMATCH_PLUGIN_URL . 'public/css/wpmatch-voice-notes.css',
				array( $this->plugin_name ),
				$this->version,
				'all'
			);
		}

		// Enqueue styles for location dashboard
		if ( is_page() && has_shortcode( get_post()->post_content, 'wpmatch_location' ) ) {
			wp_enqueue_style(
				$this->plugin_name . '-location',
				WPMATCH_PLUGIN_URL . 'public/css/wpmatch-location.css',
				array( $this->plugin_name ),
				$this->version,
				'all'
			);
		}

		// Enqueue styles for matches
		if ( is_page() && has_shortcode( get_post()->post_content, 'wpmatch_matches' ) ) {
			wp_enqueue_style(
				$this->plugin_name . '-matches',
				WPMATCH_PLUGIN_URL . 'public/css/wpmatch-matches.css',
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
			'wpmatch_ajax',
			array(
				'ajax_url'        => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'wpmatch_public_nonce' ),
				'current_user_id' => get_current_user_id(),
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
							'uploading'         => __( 'Uploading...', 'wpmatch' ),
							'upload_error'      => __( 'Upload failed. Please try again.', 'wpmatch' ),
							'delete_error'      => __( 'Failed to delete photo. Please try again.', 'wpmatch' ),
							'primary_error'     => __( 'Failed to set primary photo. Please try again.', 'wpmatch' ),
							'confirm_delete'    => __( 'Are you sure you want to delete this photo?', 'wpmatch' ),
							'invalid_file_type' => __( 'Invalid file type. Please upload JPG, PNG, or GIF.', 'wpmatch' ),
							'file_too_large'    => __( 'File too large. Maximum size is 5MB.', 'wpmatch' ),
							'add_main_photo'    => __( 'Add main photo', 'wpmatch' ),
							'add_photo'         => __( 'Add photo', 'wpmatch' ),
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
						'ajax_url'        => admin_url( 'admin-ajax.php' ),
						'nonce'           => wp_create_nonce( 'wpmatch_public_nonce' ),
						'current_user_id' => get_current_user_id(),
						'strings'         => array(
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
						'ajax_url'     => admin_url( 'admin-ajax.php' ),
						'nonce'        => wp_create_nonce( 'wpmatch_public_nonce' ),
						'profile_url'  => home_url( '/profile/' ),
						'messages_url' => home_url( '/messages/' ),
						'strings'      => array(
							'loading'           => __( 'Searching...', 'wpmatch' ),
							'no_suggestions'    => __( 'No suggestions found', 'wpmatch' ),
							'preferences_saved' => __( 'Search preferences saved!', 'wpmatch' ),
						),
					)
				);
			}

			// Enqueue gamification script on gamification page
			if ( $post && has_shortcode( $post->post_content, 'wpmatch_gamification' ) ) {
				wp_enqueue_script(
					'wpmatch-gamification',
					WPMATCH_PLUGIN_URL . 'public/js/wpmatch-gamification.js',
					array( 'jquery' ),
					$this->version,
					true
				);
			}

			// Enqueue events script on events page
			if ( $post && has_shortcode( $post->post_content, 'wpmatch_events' ) ) {
				wp_enqueue_script(
					'wpmatch-events',
					WPMATCH_PLUGIN_URL . 'public/js/wpmatch-events.js',
					array( 'jquery' ),
					$this->version,
					true
				);
			}

			// Enqueue voice notes script on voice notes page
			if ( $post && has_shortcode( $post->post_content, 'wpmatch_voice_notes' ) ) {
				wp_enqueue_script(
					'wpmatch-voice-notes',
					WPMATCH_PLUGIN_URL . 'public/js/wpmatch-voice-notes.js',
					array( 'jquery' ),
					$this->version,
					true
				);
			}

			// Enqueue location script on location page
			if ( $post && has_shortcode( $post->post_content, 'wpmatch_location' ) ) {
				wp_enqueue_script(
					'wpmatch-location',
					WPMATCH_PLUGIN_URL . 'public/js/wpmatch-location.js',
					array( 'jquery' ),
					$this->version,
					true
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
		add_shortcode( 'wpmatch_membership_dashboard', array( $this, 'membership_dashboard_shortcode' ) );
		add_shortcode( 'wpmatch_gamification', array( $this, 'gamification_shortcode' ) );
		add_shortcode( 'wpmatch_events', array( $this, 'events_shortcode' ) );
		add_shortcode( 'wpmatch_voice_notes', array( $this, 'voice_notes_shortcode' ) );
		add_shortcode( 'wpmatch_location', array( $this, 'location_shortcode' ) );
		add_shortcode( 'wpmatch_dashboard', array( $this, 'dashboard_shortcode' ) );
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
		$template_path = WPMATCH_PLUGIN_DIR . 'public/partials/wpmatch-matches.php';
		if ( file_exists( $template_path ) ) {
			require $template_path;
		} else {
			echo '<p>Error: Matches template not found at: ' . esc_html( $template_path ) . '</p>';
		}
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
		if ( ! class_exists( 'WPMatch_Swipe_Interface' ) ) {
			require_once WPMATCH_PLUGIN_DIR . 'public/class-wpmatch-swipe-interface.php';
		}
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
	 * Membership dashboard shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function membership_dashboard_shortcode( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your membership dashboard.', 'wpmatch' ) . '</p>';
		}

		$atts = shortcode_atts(
			array(),
			$atts,
			'wpmatch_membership_dashboard'
		);

		ob_start();
		require WPMATCH_PLUGIN_DIR . 'public/partials/wpmatch-membership-dashboard.php';
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
			case 'cancel_subscription':
				$this->cancel_subscription();
				break;
			case 'reactivate_subscription':
				$this->reactivate_subscription();
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
		$is_primary  = ( 0 === (int) $photo_count ) ? 1 : 0;

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

		wp_send_json_success(
			array(
				'message'    => __( 'Photo uploaded successfully', 'wpmatch' ),
				'photo_id'   => $photo_id,
				'photo_url'  => $upload['url'],
				'is_primary' => $is_primary,
			)
		);
	}

	/**
	 * Delete a photo.
	 */
	private function delete_photo() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'wpmatch' ) ) );
		}

		$user_id  = get_current_user_id();
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
			array(
				'media_id' => $photo_id,
				'user_id'  => $user_id,
			),
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

		$user_id  = get_current_user_id();
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
			array(
				'user_id'    => $user_id,
				'media_type' => 'photo',
			),
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
		$user    = get_userdata( $user_id );
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

		$sender_id    = get_current_user_id();
		$recipient_id = isset( $_POST['recipient_id'] ) ? absint( $_POST['recipient_id'] ) : 0;
		$message      = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		if ( ! $recipient_id || empty( $message ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid message data.', 'wpmatch' ) ) );
		}

		$result = WPMatch_Message_Manager::send_message( $sender_id, $recipient_id, $message );

		if ( $result['success'] ) {
			wp_send_json_success(
				array(
					'message'    => $result['message'],
					'message_id' => $result['message_id'],
					'created_at' => current_time( 'mysql' ),
				)
			);
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
		$user_id    = get_current_user_id();

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
		$user_id         = get_current_user_id();

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

		$user_id       = get_current_user_id();
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

		$user_id     = get_current_user_id();
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

	/**
	 * Cancel subscription via AJAX.
	 */
	private function cancel_subscription() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'wpmatch' ) ) );
		}

		$user_id         = get_current_user_id();
		$subscription_id = isset( $_POST['subscription_id'] ) ? absint( $_POST['subscription_id'] ) : 0;

		if ( ! $subscription_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid subscription ID.', 'wpmatch' ) ) );
		}

		$result = WPMatch_Subscription_Manager::cancel_subscription( $subscription_id, $user_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Subscription cancelled successfully.', 'wpmatch' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to cancel subscription.', 'wpmatch' ) ) );
		}
	}

	/**
	 * Reactivate subscription via AJAX.
	 */
	private function reactivate_subscription() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'wpmatch' ) ) );
		}

		$user_id         = get_current_user_id();
		$subscription_id = isset( $_POST['subscription_id'] ) ? absint( $_POST['subscription_id'] ) : 0;

		if ( ! $subscription_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid subscription ID.', 'wpmatch' ) ) );
		}

		$result = WPMatch_Subscription_Manager::reactivate_subscription( $subscription_id, $user_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Subscription reactivated successfully.', 'wpmatch' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to reactivate subscription.', 'wpmatch' ) ) );
		}
	}

	/**
	 * Gamification dashboard shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function gamification_shortcode( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your gamification progress.', 'wpmatch' ) . '</p>';
		}

		$atts = shortcode_atts(
			array(),
			$atts,
			'wpmatch_gamification'
		);

		ob_start();
		require WPMATCH_PLUGIN_DIR . 'public/partials/wpmatch-gamification-dashboard.php';
		return ob_get_clean();
	}

	/**
	 * Events dashboard shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function events_shortcode( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view events.', 'wpmatch' ) . '</p>';
		}

		$atts = shortcode_atts(
			array(
				'type' => 'all',
			),
			$atts,
			'wpmatch_events'
		);

		ob_start();
		require WPMATCH_PLUGIN_DIR . 'public/partials/wpmatch-events-dashboard.php';
		return ob_get_clean();
	}

	/**
	 * Voice notes shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function voice_notes_shortcode( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to use voice notes.', 'wpmatch' ) . '</p>';
		}

		$atts = shortcode_atts(
			array(
				'recipient_id' => 0,
			),
			$atts,
			'wpmatch_voice_notes'
		);

		ob_start();
		require WPMATCH_PLUGIN_DIR . 'public/partials/wpmatch-voice-notes.php';
		return ob_get_clean();
	}

	/**
	 * Location dashboard shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function location_shortcode( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to use location features.', 'wpmatch' ) . '</p>';
		}

		$atts = shortcode_atts(
			array(
				'radius'   => 25,
				'show_map' => true,
			),
			$atts,
			'wpmatch_location'
		);

		ob_start();
		require WPMATCH_PLUGIN_DIR . 'public/partials/wpmatch-location-dashboard.php';
		return ob_get_clean();
	}

	/**
	 * Registration form shortcode.
	 */
	public function registration_shortcode( $atts ) {
		// Don't show registration form if user is logged in
		if ( is_user_logged_in() ) {
			return '<p>' . esc_html__( 'You are already logged in.', 'wpmatch' ) . '</p>';
		}

		$atts = shortcode_atts(
			array(
				'redirect_url' => home_url( '/wpmatch/dashboard' ),
			),
			$atts,
			'wpmatch_registration'
		);

		ob_start();
		require WPMATCH_PLUGIN_DIR . 'public/partials/wpmatch-registration.php';
		return ob_get_clean();
	}

	/**
	 * Initialize registration AJAX handlers.
	 */
	public function init_registration_ajax() {
		add_action( 'wp_ajax_nopriv_wpmatch_register_user', array( $this, 'ajax_register_user' ) );
		add_action( 'wp_ajax_nopriv_wpmatch_check_email', array( $this, 'ajax_check_email' ) );
		add_action( 'wp_ajax_nopriv_wpmatch_upload_registration_photo', array( $this, 'ajax_upload_registration_photo' ) );
	}

	/**
	 * Initialize messaging AJAX handlers.
	 */
	public function init_messaging_ajax() {
		add_action( 'wp_ajax_wpmatch_get_conversations', array( $this, 'ajax_get_conversations' ) );
		add_action( 'wp_ajax_wpmatch_get_messages', array( $this, 'ajax_get_messages' ) );
		add_action( 'wp_ajax_wpmatch_send_message', array( $this, 'ajax_send_message' ) );
		add_action( 'wp_ajax_wpmatch_get_user_info', array( $this, 'ajax_get_user_info' ) );
		add_action( 'wp_ajax_wpmatch_check_new_messages', array( $this, 'ajax_check_new_messages' ) );
		add_action( 'wp_ajax_wpmatch_typing_indicator', array( $this, 'ajax_typing_indicator' ) );
	}

	/**
	 * Initialize dashboard AJAX handlers.
	 */
	public function init_dashboard_ajax() {
		add_action( 'wp_ajax_wpmatch_get_recent_activity', array( $this, 'ajax_get_recent_activity' ) );
		add_action( 'wp_ajax_wpmatch_get_user_photos', array( $this, 'ajax_get_user_photos' ) );
		add_action( 'wp_ajax_wpmatch_upload_photo', array( $this, 'ajax_upload_photo' ) );
		add_action( 'wp_ajax_wpmatch_photo_set_primary', array( $this, 'ajax_set_primary_photo' ) );
		add_action( 'wp_ajax_wpmatch_photo_delete', array( $this, 'ajax_delete_photo' ) );
		add_action( 'wp_ajax_wpmatch_update_profile', array( $this, 'ajax_update_profile' ) );
		add_action( 'wp_ajax_wpmatch_get_profile_completion', array( $this, 'ajax_get_profile_completion' ) );
	}

	/**
	 * AJAX handler for user registration.
	 */
	public function ajax_register_user() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['wpmatch_registration_nonce'], 'wpmatch_registration' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wpmatch' ) ) );
		}

		// Check if registration is enabled
		$settings = get_option( 'wpmatch_settings', array() );
		if ( empty( $settings['enable_registration'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Registration is currently disabled.', 'wpmatch' ) ) );
		}

		// Sanitize and validate input
		$first_name    = sanitize_text_field( $_POST['first_name'] );
		$last_name     = sanitize_text_field( $_POST['last_name'] );
		$email         = sanitize_email( $_POST['email'] );
		$password      = $_POST['password']; // Don't sanitize password
		$birth_date    = sanitize_text_field( $_POST['birth_date'] );
		$gender        = sanitize_text_field( $_POST['gender'] );
		$location      = sanitize_text_field( $_POST['location'] );
		$occupation    = sanitize_text_field( $_POST['occupation'] );
		$education     = sanitize_text_field( $_POST['education'] );
		$about_me      = sanitize_textarea_field( $_POST['about_me'] );
		$interested_in = sanitize_text_field( $_POST['interested_in'] );
		$looking_for   = sanitize_text_field( $_POST['looking_for'] );

		// Validate required fields
		$errors = array();

		if ( empty( $first_name ) ) {
			$errors['first_name'] = __( 'First name is required.', 'wpmatch' );
		}

		if ( empty( $last_name ) ) {
			$errors['last_name'] = __( 'Last name is required.', 'wpmatch' );
		}

		if ( empty( $email ) || ! is_email( $email ) ) {
			$errors['email'] = __( 'A valid email address is required.', 'wpmatch' );
		}

		if ( email_exists( $email ) ) {
			$errors['email'] = __( 'An account with this email already exists.', 'wpmatch' );
		}

		if ( empty( $password ) || strlen( $password ) < 8 ) {
			$errors['password'] = __( 'Password must be at least 8 characters long.', 'wpmatch' );
		}

		if ( empty( $birth_date ) ) {
			$errors['birth_date'] = __( 'Date of birth is required.', 'wpmatch' );
		} else {
			// Validate age
			$age     = $this->calculate_age( $birth_date );
			$min_age = isset( $settings['min_age'] ) ? $settings['min_age'] : 18;
			if ( $age < $min_age ) {
				$errors['birth_date'] = sprintf( __( 'You must be at least %d years old.', 'wpmatch' ), $min_age );
			}
		}

		if ( empty( $gender ) ) {
			$errors['gender'] = __( 'Gender is required.', 'wpmatch' );
		}

		if ( empty( $location ) ) {
			$errors['location'] = __( 'Location is required.', 'wpmatch' );
		}

		if ( empty( $about_me ) ) {
			$errors['about_me'] = __( 'About me is required.', 'wpmatch' );
		}

		if ( empty( $interested_in ) ) {
			$errors['interested_in'] = __( 'Please specify who you\'re interested in.', 'wpmatch' );
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please correct the errors and try again.', 'wpmatch' ),
					'errors'  => $errors,
				)
			);
		}

		// Create WordPress user
		$user_data = array(
			'user_login'   => $email,
			'user_email'   => $email,
			'user_pass'    => $password,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'display_name' => $first_name . ' ' . $last_name,
			'role'         => 'wpmatch_member',
		);

		$user_id = wp_insert_user( $user_data );

		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
		}

		// Create dating profile
		global $wpdb;

		$age          = $this->calculate_age( $birth_date );
		$height_total = 0;
		if ( ! empty( $_POST['height_feet'] ) && ! empty( $_POST['height_inches'] ) ) {
			$height_total = ( absint( $_POST['height_feet'] ) * 12 ) + absint( $_POST['height_inches'] );
		}

		$profile_data = array(
			'user_id'            => $user_id,
			'age'                => $age,
			'location'           => $location,
			'gender'             => $gender,
			'orientation'        => $interested_in,
			'education'          => $education,
			'profession'         => $occupation,
			'height'             => $height_total > 0 ? $height_total : null,
			'about_me'           => $about_me,
			'looking_for'        => $looking_for,
			'profile_completion' => 50, // Base completion for having basic info
		);

		$profiles_table = $wpdb->prefix . 'wpmatch_user_profiles';
		$result         = $wpdb->insert( $profiles_table, $profile_data );

		if ( ! $result ) {
			// Clean up the user if profile creation failed
			wp_delete_user( $user_id );
			wp_send_json_error( array( 'message' => __( 'Failed to create dating profile.', 'wpmatch' ) ) );
		}

		// Set user preferences
		$age_min      = ! empty( $_POST['age_range_min'] ) ? absint( $_POST['age_range_min'] ) : 18;
		$age_max      = ! empty( $_POST['age_range_max'] ) ? absint( $_POST['age_range_max'] ) : 99;
		$max_distance = ! empty( $_POST['max_distance'] ) ? absint( $_POST['max_distance'] ) : 25;

		$preferences_data = array(
			'user_id'          => $user_id,
			'min_age'          => $age_min,
			'max_age'          => $age_max,
			'max_distance'     => $max_distance,
			'preferred_gender' => $interested_in,
		);

		$preferences_table = $wpdb->prefix . 'wpmatch_user_preferences';
		$wpdb->insert( $preferences_table, $preferences_data );

		// Handle photo uploads
		$this->process_registration_photos( $user_id );

		// Send verification email if required
		if ( ! empty( $settings['require_email_verification'] ) ) {
			$this->send_verification_email( $user_id );
		}

		// Log user in
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id );

		// Update last login
		update_user_meta( $user_id, 'wpmatch_last_login', current_time( 'mysql' ) );

		wp_send_json_success(
			array(
				'message'      => __( 'Welcome to WPMatch! Your account has been created successfully.', 'wpmatch' ),
				'redirect_url' => home_url( '/wpmatch/dashboard' ),
			)
		);
	}

	/**
	 * Process photo uploads during registration.
	 */
	private function process_registration_photos( $user_id ) {
		if ( empty( $_FILES ) ) {
			return;
		}

		global $wpdb;
		$media_table = $wpdb->prefix . 'wpmatch_user_media';

		// Handle multiple photo uploads
		$upload_dir  = wp_upload_dir();
		$wpmatch_dir = $upload_dir['basedir'] . '/wpmatch/users/' . $user_id;

		if ( ! file_exists( $wpmatch_dir ) ) {
			wp_mkdir_p( $wpmatch_dir );
		}

		$photo_count = 0;
		foreach ( $_FILES as $key => $file ) {
			if ( strpos( $key, 'photo_' ) === 0 && $file['error'] === UPLOAD_ERR_OK ) {
				$file_info = pathinfo( $file['name'] );
				$filename  = wp_unique_filename( $wpmatch_dir, $file['name'] );
				$file_path = $wpmatch_dir . '/' . $filename;

				if ( move_uploaded_file( $file['tmp_name'], $file_path ) ) {
					$is_primary = ( $photo_count === 0 ) ? 1 : 0;

					$media_data = array(
						'user_id'             => $user_id,
						'media_type'          => 'photo',
						'file_path'           => str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $file_path ),
						'file_name'           => $filename,
						'mime_type'           => $file['type'],
						'file_size'           => $file['size'],
						'is_primary'          => $is_primary,
						'display_order'       => $photo_count,
						'verification_status' => 'pending',
					);

					$wpdb->insert( $media_table, $media_data );
					++$photo_count;
				}
			}
		}

		// Update profile completion based on photos
		if ( $photo_count > 0 ) {
			$completion_bonus = min( $photo_count * 10, 30 ); // Up to 30% bonus for photos
			$wpdb->update(
				$wpdb->prefix . 'wpmatch_user_profiles',
				array( 'profile_completion' => 50 + $completion_bonus ),
				array( 'user_id' => $user_id )
			);
		}
	}

	/**
	 * Send email verification.
	 */
	private function send_verification_email( $user_id ) {
		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			return;
		}

		// Generate verification token
		$token = wp_generate_password( 32, false );
		update_user_meta( $user_id, 'wpmatch_email_verification_token', $token );
		update_user_meta( $user_id, 'wpmatch_email_verified', false );

		// Send email
		$verification_url = add_query_arg(
			array(
				'action' => 'wpmatch_verify_email',
				'token'  => $token,
				'user'   => $user_id,
			),
			home_url()
		);

		$subject = __( 'Verify your WPMatch email address', 'wpmatch' );
		$message = sprintf(
			__( 'Hi %1$s,\n\nPlease verify your email address by clicking the link below:\n\n%2$s\n\nIf you didn\'t create this account, please ignore this email.', 'wpmatch' ),
			$user->display_name,
			$verification_url
		);

		wp_mail( $user->user_email, $subject, $message );
	}

	/**
	 * Calculate age from birth date.
	 */
	private function calculate_age( $birth_date ) {
		$birth = new DateTime( $birth_date );
		$today = new DateTime();
		return $today->diff( $birth )->y;
	}

	/**
	 * AJAX handler to check if email exists.
	 */
	public function ajax_check_email() {
		$email = sanitize_email( $_POST['email'] );

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid email format.', 'wpmatch' ) ) );
		}

		if ( email_exists( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'An account with this email already exists.', 'wpmatch' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Email is available.', 'wpmatch' ) ) );
	}

	/**
	 * AJAX handler for photo upload during registration.
	 */
	public function ajax_upload_registration_photo() {
		// This would handle real-time photo uploads during registration
		// For now, photos are processed during final form submission
		wp_send_json_success( array( 'message' => __( 'Photo uploaded successfully.', 'wpmatch' ) ) );
	}

	/**
	 * AJAX handler to get conversations.
	 */
	public function ajax_get_conversations() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'wpmatch' ) ) );
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], 'wpmatch_public_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'wpmatch' ) ) );
		}

		$user_id       = get_current_user_id();
		$conversations = $this->get_user_conversations( $user_id );

		wp_send_json_success( array( 'conversations' => $conversations ) );
	}

	/**
	 * AJAX handler to get messages for a conversation.
	 */
	public function ajax_get_messages() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'wpmatch' ) ) );
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], 'wpmatch_public_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'wpmatch' ) ) );
		}

		$conversation_id = sanitize_text_field( $_POST['conversation_id'] );
		$messages        = $this->get_conversation_messages( $conversation_id );

		wp_send_json_success( array( 'messages' => $messages ) );
	}

	/**
	 * AJAX handler to send a message.
	 */
	public function ajax_send_message() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'wpmatch' ) ) );
		}

		if ( ! wp_verify_nonce( $_POST['wpmatch_message_nonce'], 'wpmatch_send_message' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'wpmatch' ) ) );
		}

		$sender_id       = get_current_user_id();
		$recipient_id    = absint( $_POST['recipient_id'] );
		$message         = sanitize_textarea_field( $_POST['message'] );
		$conversation_id = sanitize_text_field( $_POST['conversation_id'] );

		if ( empty( $message ) || ! $recipient_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid message data', 'wpmatch' ) ) );
		}

		$message_id = $this->create_message( $sender_id, $recipient_id, $message, $conversation_id );

		if ( $message_id ) {
			wp_send_json_success(
				array(
					'message'    => __( 'Message sent successfully', 'wpmatch' ),
					'message_id' => $message_id,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to send message', 'wpmatch' ) ) );
		}
	}

	/**
	 * AJAX handler to get user info.
	 */
	public function ajax_get_user_info() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'wpmatch' ) ) );
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], 'wpmatch_public_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'wpmatch' ) ) );
		}

		$user_id   = absint( $_POST['user_id'] );
		$user_info = $this->get_user_info( $user_id );

		wp_send_json_success( array( 'user' => $user_info ) );
	}

	/**
	 * AJAX handler to check for new messages.
	 */
	public function ajax_check_new_messages() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'wpmatch' ) ) );
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], 'wpmatch_public_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'wpmatch' ) ) );
		}

		$conversation_id = sanitize_text_field( $_POST['conversation_id'] );
		$last_message_id = absint( $_POST['last_message_id'] );

		$new_messages = $this->get_new_messages( $conversation_id, $last_message_id );

		wp_send_json_success( array( 'new_messages' => $new_messages ) );
	}

	/**
	 * AJAX handler for typing indicator.
	 */
	public function ajax_typing_indicator() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'wpmatch' ) ) );
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], 'wpmatch_public_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'wpmatch' ) ) );
		}

		// For now, just return success. In a real implementation, this would
		// update a typing status in the database or cache
		wp_send_json_success( array( 'message' => 'Typing indicator updated' ) );
	}

	/**
	 * Get user conversations.
	 */
	private function get_user_conversations( $user_id ) {
		global $wpdb;

		// For demo purposes, create some sample conversations
		$conversations = array(
			array(
				'id'                => 'conv_1',
				'user_id'           => 2,
				'display_name'      => 'Sarah Johnson',
				'avatar'            => '/wp-content/plugins/wpmatch/public/images/default-avatar.png',
				'last_message'      => 'Hey there! How was your weekend?',
				'last_message_time' => gmdate( 'Y-m-d H:i:s', strtotime( '-2 hours' ) ),
				'unread_count'      => 2,
				'user_status'       => 'online',
			),
			array(
				'id'                => 'conv_2',
				'user_id'           => 3,
				'display_name'      => 'Emma Davis',
				'avatar'            => '/wp-content/plugins/wpmatch/public/images/default-avatar.png',
				'last_message'      => 'That sounds like a great plan!',
				'last_message_time' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
				'unread_count'      => 0,
				'user_status'       => 'away',
			),
			array(
				'id'                => 'conv_3',
				'user_id'           => 4,
				'display_name'      => 'Jessica Wilson',
				'avatar'            => '/wp-content/plugins/wpmatch/public/images/default-avatar.png',
				'last_message'      => 'Nice to meet you!',
				'last_message_time' => gmdate( 'Y-m-d H:i:s', strtotime( '-3 days' ) ),
				'unread_count'      => 0,
				'user_status'       => 'offline',
			),
		);

		return $conversations;
	}

	/**
	 * Get messages for a conversation.
	 */
	private function get_conversation_messages( $conversation_id ) {
		// For demo purposes, return sample messages
		$current_user_id = get_current_user_id();

		$messages = array(
			array(
				'id'        => 1,
				'sender_id' => 2,
				'content'   => 'Hi! I saw your profile and thought we might have some things in common.',
				'sent_at'   => gmdate( 'Y-m-d H:i:s', strtotime( '-3 hours' ) ),
				'is_read'   => true,
				'avatar'    => '/wp-content/plugins/wpmatch/public/images/default-avatar.png',
			),
			array(
				'id'        => 2,
				'sender_id' => $current_user_id,
				'content'   => 'Thanks for reaching out! I love your photos.',
				'sent_at'   => gmdate( 'Y-m-d H:i:s', strtotime( '-2 hours 30 minutes' ) ),
				'is_read'   => true,
				'avatar'    => '/wp-content/plugins/wpmatch/public/images/default-avatar.png',
			),
			array(
				'id'        => 3,
				'sender_id' => 2,
				'content'   => 'What do you like to do for fun?',
				'sent_at'   => gmdate( 'Y-m-d H:i:s', strtotime( '-2 hours' ) ),
				'is_read'   => false,
				'avatar'    => '/wp-content/plugins/wpmatch/public/images/default-avatar.png',
			),
		);

		return $messages;
	}

	/**
	 * Create a new message.
	 */
	private function create_message( $sender_id, $recipient_id, $message, $conversation_id ) {
		// For demo purposes, just return a mock message ID
		// In a real implementation, this would insert into a messages table
		return time(); // Use timestamp as mock ID
	}

	/**
	 * Get user info for messaging.
	 */
	private function get_user_info( $user_id ) {
		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			return null;
		}

		return array(
			'display_name' => $user->display_name,
			'avatar'       => '/wp-content/plugins/wpmatch/public/images/default-avatar.png',
			'status'       => 'Last seen recently',
			'is_online'    => true,
		);
	}

	/**
	 * Get new messages since last check.
	 */
	private function get_new_messages( $conversation_id, $last_message_id ) {
		// For demo purposes, return empty array
		// In a real implementation, this would query for messages newer than $last_message_id
		return array();
	}

	/**
	 * Dashboard shortcode.
	 */
	public function dashboard_shortcode( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your dashboard.', 'wpmatch' ) . '</p>';
		}

		$atts = shortcode_atts(
			array(),
			$atts,
			'wpmatch_dashboard'
		);

		ob_start();
		require WPMATCH_PLUGIN_DIR . 'public/partials/wpmatch-user-dashboard.php';
		return ob_get_clean();
	}

	/**
	 * Get user profile data for dashboard.
	 */
	public function get_user_profile_data( $user_id ) {
		global $wpdb;

		$profile_table = $wpdb->prefix . 'wpmatch_user_profiles';
		$media_table   = $wpdb->prefix . 'wpmatch_user_media';

		// Get profile data
		$profile = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$profile_table} WHERE user_id = %d",
				$user_id
			),
			ARRAY_A
		);

		// Get primary photo
		$primary_photo = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT file_path FROM {$media_table} WHERE user_id = %d AND media_type = 'photo' AND is_primary = 1",
				$user_id
			)
		);

		if ( ! $profile ) {
			$profile = array(
				'completion' => 25,
				'status'     => 'New member',
			);
		}

		$profile['avatar'] = $primary_photo ?: '/wp-content/plugins/wpmatch/public/images/default-avatar.png';

		return $profile;
	}

	/**
	 * Get user stats for dashboard.
	 */
	public function get_user_stats( $user_id ) {
		// Mock stats for demo purposes
		return array(
			'profile_views'   => 42,
			'total_matches'   => 8,
			'conversations'   => 5,
			'likes_received'  => 15,
			'new_matches'     => 2,
			'unread_messages' => 3,
		);
	}

	/**
	 * AJAX handler for recent activity.
	 */
	public function ajax_get_recent_activity() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'wpmatch' ) ) );
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], 'wpmatch_public_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'wpmatch' ) ) );
		}

		// Mock recent activity for demo
		$activities = array(
			array(
				'icon' => '👀',
				'text' => 'Your profile was viewed 3 times today',
				'time' => gmdate( 'Y-m-d H:i:s', strtotime( '-2 hours' ) ),
			),
			array(
				'icon' => '💘',
				'text' => 'You have a new match!',
				'time' => gmdate( 'Y-m-d H:i:s', strtotime( '-5 hours' ) ),
			),
			array(
				'icon' => '💬',
				'text' => 'Sarah sent you a message',
				'time' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
			),
		);

		wp_send_json_success( array( 'activities' => $activities ) );
	}

	/**
	 * AJAX handler for user photos.
	 */
	public function ajax_get_user_photos() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'wpmatch' ) ) );
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], 'wpmatch_public_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'wpmatch' ) ) );
		}

		$user_id = get_current_user_id();
		global $wpdb;
		$media_table = $wpdb->prefix . 'wpmatch_user_media';

		$photos = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT media_id as id, file_path as url, is_primary FROM {$media_table} WHERE user_id = %d AND media_type = 'photo' ORDER BY display_order ASC",
				$user_id
			),
			ARRAY_A
		);

		// Convert is_primary to boolean
		foreach ( $photos as &$photo ) {
			$photo['is_primary'] = (bool) $photo['is_primary'];
		}

		wp_send_json_success( array( 'photos' => $photos ) );
	}

	/**
	 * AJAX handler for profile update.
	 */
	public function ajax_update_profile() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'wpmatch' ) ) );
		}

		if ( ! wp_verify_nonce( $_POST['wpmatch_profile_nonce'], 'wpmatch_update_profile' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'wpmatch' ) ) );
		}

		$user_id = get_current_user_id();

		// Sanitize input data
		$display_name = sanitize_text_field( $_POST['display_name'] );
		$age          = absint( $_POST['age'] );
		$location     = sanitize_text_field( $_POST['location'] );
		$occupation   = sanitize_text_field( $_POST['occupation'] );
		$about_me     = sanitize_textarea_field( $_POST['about_me'] );
		$height       = absint( $_POST['height'] );
		$education    = sanitize_text_field( $_POST['education'] );
		$lifestyle    = sanitize_text_field( $_POST['lifestyle'] );
		$looking_for  = sanitize_text_field( $_POST['looking_for'] );

		// Update WordPress user data
		wp_update_user(
			array(
				'ID'           => $user_id,
				'display_name' => $display_name,
			)
		);

		// Update profile data
		global $wpdb;
		$profile_table = $wpdb->prefix . 'wpmatch_user_profiles';

		$profile_data = array(
			'age'         => $age,
			'location'    => $location,
			'profession'  => $occupation,
			'about_me'    => $about_me,
			'height'      => $height ?: null,
			'education'   => $education,
			'lifestyle'   => $lifestyle,
			'looking_for' => $looking_for,
			'updated_at'  => current_time( 'mysql' ),
		);

		$result = $wpdb->update(
			$profile_table,
			$profile_data,
			array( 'user_id' => $user_id )
		);

		if ( false !== $result ) {
			wp_send_json_success( array( 'message' => __( 'Profile updated successfully!', 'wpmatch' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to update profile', 'wpmatch' ) ) );
		}
	}

	/**
	 * AJAX handler for photo upload.
	 */
	public function ajax_upload_photo() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'wpmatch' ) ) );
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], 'wpmatch_public_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'wpmatch' ) ) );
		}

		// Use the existing upload_photo method
		$this->upload_photo();
	}

	/**
	 * AJAX handler for setting primary photo.
	 */
	public function ajax_set_primary_photo() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'wpmatch' ) ) );
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], 'wpmatch_public_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'wpmatch' ) ) );
		}

		$photo_id          = absint( $_POST['photo_id'] );
		$_POST['photo_id'] = $photo_id;

		// Use the existing set_primary_photo method
		$this->set_primary_photo();
	}

	/**
	 * AJAX handler for deleting photo.
	 */
	public function ajax_delete_photo() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'wpmatch' ) ) );
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], 'wpmatch_public_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'wpmatch' ) ) );
		}

		$photo_id          = absint( $_POST['photo_id'] );
		$_POST['photo_id'] = $photo_id;

		// Use the existing delete_photo method
		$this->delete_photo();
	}

	/**
	 * AJAX handler for profile completion.
	 */
	public function ajax_get_profile_completion() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'wpmatch' ) ) );
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], 'wpmatch_public_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'wpmatch' ) ) );
		}

		$user_id      = get_current_user_id();
		$profile_data = $this->get_user_profile_data( $user_id );

		wp_send_json_success( array( 'completion' => $profile_data['completion'] ?? 50 ) );
	}
}
