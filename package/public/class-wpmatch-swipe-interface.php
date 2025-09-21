<?php
/**
 * WPMatch Swipe Interface
 *
 * Handles the Tinder-style swipe interface rendering and functionality.
 *
 * @package WPMatch
 * @subpackage Public
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPMatch Swipe Interface class.
 *
 * @since 1.0.0
 */
class WPMatch_Swipe_Interface {

	/**
	 * Render the swipe interface.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function render_swipe_interface( $atts = array() ) {
		// Check if user is logged in.
		if ( ! is_user_logged_in() ) {
			return self::render_login_prompt();
		}

		$user_id = get_current_user_id();

		// Parse attributes.
		$atts = shortcode_atts(
			array(
				'cards_count'       => 10,
				'show_distance'     => true,
				'show_bio'          => true,
				'enable_super_like' => true,
				'enable_undo'       => true,
			),
			$atts
		);

		// Get user's match queue.
		$queue = WPMatch_Matching_Algorithm::build_user_queue( $user_id );

		if ( empty( $queue ) ) {
			return self::render_empty_state();
		}

		// Prepare cards data.
		$cards_data = self::prepare_cards_data( $queue, $atts );

		// Enqueue scripts and styles.
		self::enqueue_assets();

		// Localize script data.
		self::localize_script_data( $user_id, $atts );

		// Render the interface.
		ob_start();
		?>
		<div class="wpmatch-swipe-container" data-user-id="<?php echo esc_attr( $user_id ); ?>">
			<?php echo wp_kses_post( self::render_loading_state() ); ?>

			<div class="wpmatch-swipe-wrapper">
				<?php echo wp_kses_post( self::render_card_stack( $cards_data, $atts ) ); ?>
				<?php echo wp_kses_post( self::render_action_buttons( $atts ) ); ?>
			</div>

			<?php echo wp_kses_post( self::render_match_celebration() ); ?>
			<?php echo wp_kses_post( self::render_feedback_indicators() ); ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the card stack.
	 *
	 * @since 1.0.0
	 * @param array $cards_data Cards data array.
	 * @param array $atts Attributes.
	 * @return string HTML output.
	 */
	private static function render_card_stack( $cards_data, $atts ) {
		ob_start();
		?>
		<div class="wpmatch-card-stack" role="region" aria-label="<?php esc_attr_e( 'Profile cards', 'wpmatch' ); ?>">
			<?php
			$card_index = 0;
			$max_cards  = count( $cards_data );
			foreach ( $cards_data as $card ) {
				echo wp_kses_post( self::render_single_card( $card, $card_index, $atts ) );
				++$card_index;
				if ( 3 <= $card_index ) {
					break; // Only render 3 cards at a time for performance.
				}
			}
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a single card.
	 *
	 * @since 1.0.0
	 * @param array $card_data Card data.
	 * @param int   $index Card index.
	 * @param array $atts Attributes.
	 * @return string HTML output.
	 */
	private static function render_single_card( $card_data, $index, $atts ) {
		$user_id       = $card_data['user_id'];
		$profile       = $card_data['profile'];
		$photos        = $card_data['photos'];
		$primary_photo = ! empty( $photos ) ? $photos[0] : '';

		// Calculate age from birthdate or use provided age.
		$age      = ! empty( $profile['age'] ) ? $profile['age'] : '';
		$distance = ! empty( $profile['distance'] ) ? round( $profile['distance'] ) : null;

		ob_start();
		?>
		<div class="wpmatch-card"
			data-user-id="<?php echo esc_attr( $user_id ); ?>"
			data-index="<?php echo esc_attr( $index ); ?>"
			style="z-index: <?php echo esc_attr( 100 - $index ); ?>;"
			role="article"
			aria-label="
			<?php
			// translators: %s is the user's display name.
			echo esc_attr( sprintf( __( 'Profile of %s', 'wpmatch' ), $profile['display_name'] ) );
			?>
			"
			tabindex="0">

			<div class="wpmatch-card-inner">
				<!-- Photo Section -->
				<div class="wpmatch-card-photo-wrapper">
					<?php if ( ! empty( $primary_photo ) ) : ?>
						<img src="<?php echo esc_url( $primary_photo ); ?>"
							alt="<?php echo esc_attr( $profile['display_name'] ); ?>"
							class="wpmatch-card-photo"
							loading="<?php echo 0 === $index ? 'eager' : 'lazy'; ?>">
					<?php else : ?>
						<div class="wpmatch-card-photo-placeholder">
							<span class="dashicons dashicons-admin-users"></span>
						</div>
					<?php endif; ?>

					<!-- Photo navigation dots -->
					<?php if ( count( $photos ) > 1 ) : ?>
						<div class="wpmatch-photo-dots">
							<?php
							$photo_count = count( $photos );
							for ( $i = 0; $i < $photo_count; $i++ ) :
								?>
								<span class="photo-dot <?php echo 0 === $i ? 'active' : ''; ?>"
									data-photo-index="<?php echo esc_attr( $i ); ?>"></span>
							<?php endfor; ?>
						</div>
					<?php endif; ?>

					<!-- Gradient overlay -->
					<div class="wpmatch-card-gradient"></div>
				</div>

				<!-- Info Section -->
				<div class="wpmatch-card-info">
					<h3 class="wpmatch-card-name">
						<?php echo esc_html( $profile['display_name'] ); ?>
						<?php if ( $age ) : ?>
							<span class="wpmatch-card-age"><?php echo esc_html( $age ); ?></span>
						<?php endif; ?>
					</h3>

					<?php if ( $atts['show_distance'] && null !== $distance ) : ?>
						<div class="wpmatch-card-distance">
							<span class="dashicons dashicons-location"></span>
							<?php
							// translators: %d is the distance in miles.
							echo esc_html( sprintf( __( '%d miles away', 'wpmatch' ), $distance ) );
							?>
						</div>
					<?php endif; ?>

					<?php if ( $atts['show_bio'] && ! empty( $profile['about_me'] ) ) : ?>
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

				<!-- Swipe indicators -->
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

	/**
	 * Render action buttons.
	 *
	 * @since 1.0.0
	 * @param array $atts Attributes.
	 * @return string HTML output.
	 */
	private static function render_action_buttons( $atts ) {
		ob_start();
		?>
		<div class="wpmatch-action-buttons">
			<?php if ( $atts['enable_undo'] ) : ?>
				<button class="wpmatch-action-btn wpmatch-action-undo"
					aria-label="<?php esc_attr_e( 'Undo last swipe', 'wpmatch' ); ?>"
					disabled>
					<span class="dashicons dashicons-undo"></span>
				</button>
			<?php endif; ?>

			<button class="wpmatch-action-btn wpmatch-action-pass"
				aria-label="<?php esc_attr_e( 'Pass on this profile', 'wpmatch' ); ?>">
				<span class="dashicons dashicons-no"></span>
			</button>

			<?php if ( $atts['enable_super_like'] ) : ?>
				<button class="wpmatch-action-btn wpmatch-action-super-like"
					aria-label="<?php esc_attr_e( 'Super like this profile', 'wpmatch' ); ?>">
					<span class="dashicons dashicons-star-filled"></span>
				</button>
			<?php endif; ?>

			<button class="wpmatch-action-btn wpmatch-action-like"
				aria-label="<?php esc_attr_e( 'Like this profile', 'wpmatch' ); ?>">
				<span class="dashicons dashicons-heart"></span>
			</button>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render match celebration overlay.
	 *
	 * @since 1.0.0
	 * @return string HTML output.
	 */
	private static function render_match_celebration() {
		ob_start();
		?>
		<div class="wpmatch-match-celebration" style="display: none;" role="dialog" aria-label="<?php esc_attr_e( 'Match notification', 'wpmatch' ); ?>">
			<div class="celebration-overlay"></div>
			<div class="celebration-content">
				<div class="celebration-hearts">
					<span class="heart"></span>
					<span class="heart"></span>
					<span class="heart"></span>
				</div>

				<h2 class="celebration-title"><?php esc_html_e( "It's a Match!", 'wpmatch' ); ?></h2>

				<div class="celebration-photos">
					<div class="photo-left"></div>
					<div class="photo-right"></div>
				</div>

				<p class="celebration-message"></p>

				<div class="celebration-actions">
					<button class="btn-send-message">
						<?php esc_html_e( 'Send Message', 'wpmatch' ); ?>
					</button>
					<button class="btn-keep-swiping">
						<?php esc_html_e( 'Keep Swiping', 'wpmatch' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render feedback indicators.
	 *
	 * @since 1.0.0
	 * @return string HTML output.
	 */
	private static function render_feedback_indicators() {
		ob_start();
		?>
		<div class="wpmatch-feedback-indicators">
			<div class="feedback-like">
				<span>LIKE</span>
			</div>
			<div class="feedback-nope">
				<span>NOPE</span>
			</div>
			<div class="feedback-super">
				<span>SUPER LIKE</span>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render empty state.
	 *
	 * @since 1.0.0
	 * @return string HTML output.
	 */
	private static function render_empty_state() {
		ob_start();
		?>
		<div class="wpmatch-empty-state">
			<div class="empty-state-icon">
				<span class="dashicons dashicons-search"></span>
			</div>
			<h3><?php esc_html_e( 'No more profiles', 'wpmatch' ); ?></h3>
			<p><?php esc_html_e( "That's everyone for now! Check back later for more profiles.", 'wpmatch' ); ?></p>

			<div class="empty-state-actions">
				<button class="btn-expand-preferences">
					<?php esc_html_e( 'Expand Search Preferences', 'wpmatch' ); ?>
				</button>
				<button class="btn-view-matches">
					<?php esc_html_e( 'View Your Matches', 'wpmatch' ); ?>
				</button>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render loading state.
	 *
	 * @since 1.0.0
	 * @return string HTML output.
	 */
	private static function render_loading_state() {
		ob_start();
		?>
		<div class="wpmatch-loading-state" style="display: none;">
			<div class="wpmatch-spinner">
				<div class="spinner-pulse"></div>
			</div>
			<p><?php esc_html_e( 'Loading profiles...', 'wpmatch' ); ?></p>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render login prompt.
	 *
	 * @since 1.0.0
	 * @return string HTML output.
	 */
	private static function render_login_prompt() {
		ob_start();
		?>
		<div class="wpmatch-login-prompt">
			<h3><?php esc_html_e( 'Please log in to start swiping', 'wpmatch' ); ?></h3>
			<p><?php esc_html_e( 'You need to be logged in to use the swipe feature.', 'wpmatch' ); ?></p>
			<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="btn-login">
				<?php esc_html_e( 'Log In', 'wpmatch' ); ?>
			</a>
		</div>
		<?php
		return ob_get_clean();
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
				FROM {$wpdb->prefix}wpmatch_user_profiles p
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
					"SELECT file_path FROM {$wpdb->prefix}wpmatch_user_media
				WHERE user_id = %d AND media_type = 'photo'
				ORDER BY is_primary DESC, display_order ASC
				LIMIT 5",
					$user_id
				)
			);

			// Get user interests.
			$interests = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT interest_name FROM {$wpdb->prefix}wpmatch_user_interests
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
	 * Enqueue required assets.
	 *
	 * @since 1.0.0
	 */
	private static function enqueue_assets() {
		// Enqueue CSS.
		wp_enqueue_style(
			'wpmatch-swipe-interface',
			plugin_dir_url( __DIR__ ) . 'public/css/swipe-interface.css',
			array(),
			WPMATCH_VERSION
		);

		// Enqueue JavaScript.
		wp_enqueue_script(
			'wpmatch-swipe-interface',
			plugin_dir_url( __DIR__ ) . 'public/js/swipe-interface.js',
			array( 'jquery' ),
			WPMATCH_VERSION,
			true
		);
	}

	/**
	 * Localize script data.
	 *
	 * @since 1.0.0
	 * @param int   $user_id User ID.
	 * @param array $atts Attributes.
	 */
	private static function localize_script_data( $user_id, $atts ) {
		$localize_data = array(
			'ajax_url'           => admin_url( 'admin-ajax.php' ),
			'rest_url'           => rest_url( 'wpmatch/v1/' ),
			'nonce'              => wp_create_nonce( 'wpmatch_swipe_nonce' ),
			'user_id'            => $user_id,
			'swipe_threshold'    => 100,
			'rotation_factor'    => 0.2,
			'animation_duration' => 300,
			'enable_super_like'  => $atts['enable_super_like'],
			'enable_undo'        => $atts['enable_undo'],
			'strings'            => array(
				'loading'       => __( 'Loading...', 'wpmatch' ),
				'error'         => __( 'An error occurred. Please try again.', 'wpmatch' ),
				'no_more_cards' => __( 'No more profiles available.', 'wpmatch' ),
				'match_title'   => __( "It's a Match!", 'wpmatch' ),
				'rate_limit'    => __( 'You have reached your swipe limit. Please wait a moment.', 'wpmatch' ),
			),
		);

		wp_localize_script( 'wpmatch-swipe-interface', 'wpmatchSwipe', $localize_data );
	}

	/**
	 * Instance method wrapper for shortcode compatibility.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_shortcode( $atts = array() ) {
		return self::render_swipe_interface( $atts );
	}
}