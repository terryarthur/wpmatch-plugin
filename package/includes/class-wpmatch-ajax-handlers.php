<?php
/**
 * WPMatch AJAX Handlers
 *
 * Handles all AJAX requests for the swipe interface.
 *
 * @package WPMatch
 * @subpackage AJAX
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPMatch AJAX Handlers class.
 *
 * @since 1.0.0
 */
class WPMatch_AJAX_Handlers {

	/**
	 * Initialize AJAX handlers.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		// Logged-in user actions.
		add_action( 'wp_ajax_wpmatch_process_swipe', array( __CLASS__, 'process_swipe' ) );
		add_action( 'wp_ajax_wpmatch_undo_swipe', array( __CLASS__, 'undo_swipe' ) );
		add_action( 'wp_ajax_wpmatch_load_more_cards', array( __CLASS__, 'load_more_cards' ) );
		add_action( 'wp_ajax_wpmatch_get_match_queue', array( __CLASS__, 'get_match_queue' ) );
		add_action( 'wp_ajax_wpmatch_mark_notification_read', array( __CLASS__, 'mark_notification_read' ) );
	}

	/**
	 * Process swipe action.
	 *
	 * @since 1.0.0
	 */
	public static function process_swipe() {
		// Check nonce.
		if ( ! wp_verify_nonce( $_POST['nonce'], 'wpmatch_swipe_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wpmatch' ) ) );
		}

		// Check if user is logged in.
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to swipe.', 'wpmatch' ) ) );
		}

		$user_id        = get_current_user_id();
		$target_user_id = absint( $_POST['target_user_id'] );
		$swipe_type     = sanitize_text_field( $_POST['swipe_type'] );

		// Validate inputs.
		if ( ! $target_user_id || ! in_array( $swipe_type, array( 'like', 'pass', 'super_like' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid swipe data.', 'wpmatch' ) ) );
		}

		// Check user capabilities.
		if ( ! current_user_can( 'wpmatch_view_profiles' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to swipe.', 'wpmatch' ) ) );
		}

		// Process the swipe using the matching algorithm.
		$result = WPMatch_Matching_Algorithm::process_swipe_action( $user_id, $target_user_id, $swipe_type );

		if ( $result['success'] ) {
			// Prepare response data.
			$response_data = array(
				'swipe_id'   => $result['swipe_id'],
				'is_match'   => $result['is_match'],
				'swipe_type' => $result['swipe_type'],
			);

			// Add match data if it's a match.
			if ( $result['is_match'] ) {
				$match_data    = self::get_match_data( $result['match_id'] );
				$response_data = array_merge( $response_data, $match_data );
			}

			wp_send_json_success( $response_data );
		} else {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
	}

	/**
	 * Undo last swipe.
	 *
	 * @since 1.0.0
	 */
	public static function undo_swipe() {
		// Check nonce.
		if ( ! wp_verify_nonce( $_POST['nonce'], 'wpmatch_swipe_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wpmatch' ) ) );
		}

		// Check if user is logged in.
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to undo.', 'wpmatch' ) ) );
		}

		$user_id = get_current_user_id();

		// Check user capabilities.
		if ( ! current_user_can( 'wpmatch_view_profiles' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to undo swipes.', 'wpmatch' ) ) );
		}

		// Perform undo.
		$result = WPMatch_Swipe_DB::undo_last_swipe( $user_id );

		if ( $result ) {
			wp_send_json_success(
				array(
					'message' => __( 'Swipe undone successfully.', 'wpmatch' ),
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Unable to undo swipe.', 'wpmatch' ),
				)
			);
		}
	}

	/**
	 * Load more cards for the queue.
	 *
	 * @since 1.0.0
	 */
	public static function load_more_cards() {
		// Check nonce.
		if ( ! wp_verify_nonce( $_POST['nonce'], 'wpmatch_swipe_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wpmatch' ) ) );
		}

		// Check if user is logged in.
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to load cards.', 'wpmatch' ) ) );
		}

		$user_id = get_current_user_id();
		$offset  = absint( $_POST['offset'] );

		// Check user capabilities.
		if ( ! current_user_can( 'wpmatch_view_profiles' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to view profiles.', 'wpmatch' ) ) );
		}

		// Rebuild user queue.
		$queue = WPMatch_Matching_Algorithm::build_user_queue( $user_id, true );

		if ( empty( $queue ) ) {
			wp_send_json_success(
				array(
					'cards'    => '',
					'has_more' => false,
				)
			);
		}

		// Prepare cards data.
		$cards_data = self::prepare_cards_data( $queue, array( 'cards_count' => 10 ) );

		// Render cards HTML.
		$cards_html = '';
		foreach ( $cards_data as $index => $card ) {
			$cards_html .= self::render_card_html( $card, $index );
		}

		wp_send_json_success(
			array(
				'cards'    => $cards_html,
				'has_more' => count( $queue ) > 10,
			)
		);
	}

	/**
	 * Get current match queue.
	 *
	 * @since 1.0.0
	 */
	public static function get_match_queue() {
		// Check nonce.
		if ( ! wp_verify_nonce( $_POST['nonce'], 'wpmatch_swipe_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wpmatch' ) ) );
		}

		// Check if user is logged in.
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to view queue.', 'wpmatch' ) ) );
		}

		$user_id = get_current_user_id();

		// Check user capabilities.
		if ( ! current_user_can( 'wpmatch_view_profiles' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to view profiles.', 'wpmatch' ) ) );
		}

		// Get queue from database.
		global $wpdb;
		$queue_table = $wpdb->prefix . 'wpmatch_match_queue';

		$queue = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$queue_table}
			WHERE user_id = %d
			ORDER BY priority DESC, compatibility_score DESC
			LIMIT 20",
				$user_id
			)
		);

		wp_send_json_success(
			array(
				'queue'      => $queue,
				'queue_size' => count( $queue ),
			)
		);
	}

	/**
	 * Mark notification as read.
	 *
	 * @since 1.0.0
	 */
	public static function mark_notification_read() {
		// Check nonce.
		if ( ! wp_verify_nonce( $_POST['nonce'], 'wpmatch_swipe_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wpmatch' ) ) );
		}

		// Check if user is logged in.
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'wpmatch' ) ) );
		}

		$user_id         = get_current_user_id();
		$notification_id = absint( $_POST['notification_id'] );

		// Get user notifications.
		$notifications = get_user_meta( $user_id, 'wpmatch_notifications', true );

		if ( is_array( $notifications ) && isset( $notifications[ $notification_id ] ) ) {
			$notifications[ $notification_id ]['read'] = true;
			update_user_meta( $user_id, 'wpmatch_notifications', $notifications );

			wp_send_json_success(
				array(
					'message' => __( 'Notification marked as read.', 'wpmatch' ),
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Notification not found.', 'wpmatch' ),
				)
			);
		}
	}

	/**
	 * Get match data for celebration.
	 *
	 * @since 1.0.0
	 * @param int $match_id Match ID.
	 * @return array Match data.
	 */
	private static function get_match_data( $match_id ) {
		global $wpdb;

		$matches_table = $wpdb->prefix . 'wpmatch_matches';

		$match = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$matches_table} WHERE match_id = %d",
				$match_id
			)
		);

		if ( ! $match ) {
			return array();
		}

		$current_user_id = get_current_user_id();
		$other_user_id   = ( $match->user1_id == $current_user_id ) ? $match->user2_id : $match->user1_id;

		// Get other user's data.
		$other_user    = get_userdata( $other_user_id );
		$other_profile = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpmatch_user_profiles WHERE user_id = %d",
				$other_user_id
			)
		);

		// Get profile photo.
		$photo_url = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT file_path FROM {$wpdb->prefix}wpmatch_user_media
			WHERE user_id = %d AND media_type = 'photo' AND is_primary = 1",
				$other_user_id
			)
		);

		return array(
			'match_id'           => $match_id,
			'matched_user_id'    => $other_user_id,
			'matched_user_name'  => $other_user->display_name,
			'matched_user_photo' => $photo_url ?: '',
			'message'            => sprintf(
				__( 'You and %s liked each other!', 'wpmatch' ),
				$other_user->display_name
			),
		);
	}

	/**
	 * Prepare cards data from queue.
	 *
	 * @since 1.0.0
	 * @param array $queue Match queue.
	 * @param array $atts Attributes.
	 * @return array Prepared cards data.
	 */
	private static function prepare_cards_data( $queue, $atts ) {
		global $wpdb;

		$cards_data      = array();
		$profiles_table  = $wpdb->prefix . 'wpmatch_user_profiles';
		$media_table     = $wpdb->prefix . 'wpmatch_user_media';
		$interests_table = $wpdb->prefix . 'wpmatch_user_interests';

		foreach ( $queue as $queue_item ) {
			$user_id = $queue_item->potential_match_id;

			// Get user profile.
			$profile = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT p.*, u.display_name, u.user_login
				FROM {$profiles_table} p
				INNER JOIN {$wpdb->users} u ON p.user_id = u.ID
				WHERE p.user_id = %d",
					$user_id
				),
				ARRAY_A
			);

			if ( ! $profile ) {
				continue;
			}

			// Get user photos.
			$photos = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT file_path FROM {$media_table}
				WHERE user_id = %d AND media_type = 'photo'
				ORDER BY is_primary DESC, display_order ASC
				LIMIT 5",
					$user_id
				)
			);

			// Get user interests.
			$interests = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT interest_name FROM {$interests_table}
				WHERE user_id = %d
				LIMIT 5",
					$user_id
				)
			);

			$profile['interests'] = $interests;
			$profile['distance']  = isset( $queue_item->distance ) ? $queue_item->distance : null;

			$cards_data[] = array(
				'user_id'             => $user_id,
				'profile'             => $profile,
				'photos'              => $photos,
				'compatibility_score' => $queue_item->compatibility_score,
			);

			if ( count( $cards_data ) >= $atts['cards_count'] ) {
				break;
			}
		}

		return $cards_data;
	}

	/**
	 * Render single card HTML.
	 *
	 * @since 1.0.0
	 * @param array $card_data Card data.
	 * @param int   $index Card index.
	 * @return string Card HTML.
	 */
	private static function render_card_html( $card_data, $index ) {
		$user_id       = $card_data['user_id'];
		$profile       = $card_data['profile'];
		$photos        = $card_data['photos'];
		$primary_photo = ! empty( $photos ) ? $photos[0] : '';

		$age      = isset( $profile['age'] ) ? $profile['age'] : '';
		$distance = isset( $profile['distance'] ) ? round( $profile['distance'] ) : null;

		ob_start();
		?>
		<div class="wpmatch-card"
			data-user-id="<?php echo esc_attr( $user_id ); ?>"
			data-index="<?php echo esc_attr( $index ); ?>"
			style="z-index: <?php echo esc_attr( 100 - $index ); ?>;"
			role="article"
			aria-label="<?php echo esc_attr( sprintf( __( 'Profile of %s', 'wpmatch' ), $profile['display_name'] ) ); ?>"
			tabindex="0">

			<div class="wpmatch-card-inner">
				<div class="wpmatch-card-photo-wrapper">
					<?php if ( $primary_photo ) : ?>
						<img src="<?php echo esc_url( $primary_photo ); ?>"
							alt="<?php echo esc_attr( $profile['display_name'] ); ?>"
							class="wpmatch-card-photo"
							loading="lazy">
					<?php else : ?>
						<div class="wpmatch-card-photo-placeholder">
							<span class="dashicons dashicons-admin-users"></span>
						</div>
					<?php endif; ?>

					<div class="wpmatch-card-gradient"></div>
				</div>

				<div class="wpmatch-card-info">
					<h3 class="wpmatch-card-name">
						<?php echo esc_html( $profile['display_name'] ); ?>
						<?php if ( $age ) : ?>
							<span class="wpmatch-card-age"><?php echo esc_html( $age ); ?></span>
						<?php endif; ?>
					</h3>

					<?php if ( $distance !== null ) : ?>
						<div class="wpmatch-card-distance">
							<span class="dashicons dashicons-location"></span>
							<?php echo esc_html( sprintf( __( '%d miles away', 'wpmatch' ), $distance ) ); ?>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $profile['about_me'] ) ) : ?>
						<div class="wpmatch-card-bio">
							<?php echo wp_kses_post( wp_trim_words( $profile['about_me'], 20 ) ); ?>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $profile['interests'] ) ) : ?>
						<div class="wpmatch-card-interests">
							<?php foreach ( array_slice( $profile['interests'], 0, 3 ) as $interest ) : ?>
								<span class="interest-tag"><?php echo esc_html( $interest ); ?></span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<div class="wpmatch-swipe-indicator like-indicator">
					<span>LIKE</span>
				</div>
				<div class="wpmatch-swipe-indicator nope-indicator">
					<span>NOPE</span>
				</div>
				<div class="wpmatch-swipe-indicator super-indicator">
					<span>SUPER LIKE</span>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}