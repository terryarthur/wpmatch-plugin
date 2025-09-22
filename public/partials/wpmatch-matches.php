<?php
/**
 * Matches display template
 *
 * @package WPMatch
 */

// Security check.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get current user.
$current_user = wp_get_current_user();
if ( ! $current_user->ID ) {
	echo '<div class="wpmatch-notice error"><p>' . esc_html__( 'Please log in to view your matches.', 'wpmatch' ) . '</p></div>';
	return;
}

// Get user's matches.
$matches = array();
if ( class_exists( 'WPMatch_Swipe_DB' ) ) {
	$raw_matches = WPMatch_Swipe_DB::get_matches_for_user( $current_user->ID );

	if ( $raw_matches ) {
		foreach ( $raw_matches as $match ) {
			// Determine which user is the match (not current user)
			$match_user_id = ( $match->user1_id == $current_user->ID ) ? $match->user2_id : $match->user1_id;
			$match_user = get_user_by( 'ID', $match_user_id );

			if ( $match_user ) {
				// Get additional user profile data
				global $wpdb;
				$profile_table = $wpdb->prefix . 'wpmatch_user_profiles';
				$profile_data = $wpdb->get_row( $wpdb->prepare(
					"SELECT * FROM $profile_table WHERE user_id = %d",
					$match_user_id
				) );

				$matches[] = (object) array(
					'match_id'     => $match->match_id,
					'user_id'      => $match_user_id,
					'user'         => $match_user,
					'profile'      => $profile_data,
					'matched_at'   => $match->matched_at,
					'last_activity' => $match->last_activity,
					'status'       => $match->status
				);
			}
		}
	}
}
?>

<div class="wpmatch-matches-container">
	<div class="wpmatch-matches-header">
		<h2><?php esc_html_e( 'Your Matches', 'wpmatch' ); ?></h2>
		<p><?php esc_html_e( 'These are people who liked you back! Start a conversation and see where it leads.', 'wpmatch' ); ?></p>
	</div>

	<?php if ( empty( $matches ) ) : ?>
		<div class="wpmatch-no-matches">
			<div class="wpmatch-empty-state">
				<span class="dashicons dashicons-heart"></span>
				<h3><?php esc_html_e( 'No matches yet', 'wpmatch' ); ?></h3>
				<p><?php esc_html_e( 'Keep swiping to find your perfect match!', 'wpmatch' ); ?></p>
				<a href="#" class="wpmatch-button primary">
					<?php esc_html_e( 'Start Swiping', 'wpmatch' ); ?>
				</a>
			</div>
		</div>
	<?php else : ?>
		<div class="wpmatch-matches-grid">
			<?php foreach ( $matches as $match ) :
				$profile_picture = '';
				$age = '';
				$location = '';
				$bio = '';

				// Get profile picture
				if ( $match->profile && $match->profile->profile_picture ) {
					$profile_picture = esc_url( $match->profile->profile_picture );
				} else {
					$profile_picture = get_avatar_url( $match->user_id, array( 'size' => 300 ) );
				}

				// Get age
				if ( $match->profile && $match->profile->age ) {
					$age = absint( $match->profile->age );
				}

				// Get location
				if ( $match->profile && $match->profile->location ) {
					$location = esc_html( $match->profile->location );
				}

				// Get bio
				if ( $match->profile && $match->profile->bio ) {
					$bio = esc_html( wp_trim_words( $match->profile->bio, 15 ) );
				}

				// Format match date
				$matched_time = human_time_diff( strtotime( $match->matched_at ) );
			?>
				<div class="wpmatch-match-card" data-user-id="<?php echo esc_attr( $match->user_id ); ?>" data-match-id="<?php echo esc_attr( $match->match_id ); ?>">
					<div class="match-card-image">
						<img src="<?php echo esc_url( $profile_picture ); ?>" alt="<?php echo esc_attr( $match->user->display_name ); ?>" class="match-avatar">
						<div class="match-status-badge">
							<span class="status-indicator active"></span>
						</div>
					</div>

					<div class="match-card-content">
						<div class="match-basic-info">
							<h3 class="match-name">
								<?php echo esc_html( $match->user->display_name ); ?>
								<?php if ( $age ) : ?>
									<span class="match-age">, <?php echo esc_html( $age ); ?></span>
								<?php endif; ?>
							</h3>

							<?php if ( $location ) : ?>
								<p class="match-location">
									<span class="dashicons dashicons-location"></span>
									<?php echo esc_html( $location ); ?>
								</p>
							<?php endif; ?>
						</div>

						<?php if ( $bio ) : ?>
							<p class="match-bio"><?php echo esc_html( $bio ); ?></p>
						<?php endif; ?>

						<div class="match-meta">
							<span class="match-time">
								<span class="dashicons dashicons-heart"></span>
								<?php
								/* translators: %s: time ago */
								printf( esc_html__( 'Matched %s ago', 'wpmatch' ), esc_html( $matched_time ) );
								?>
							</span>
						</div>

						<div class="match-actions">
							<a href="<?php echo esc_url( home_url( '/messages/?user=' . $match->user_id ) ); ?>" class="wpmatch-button primary match-message-btn">
								<span class="dashicons dashicons-email-alt"></span>
								<?php esc_html_e( 'Start Conversation', 'wpmatch' ); ?>
							</a>

							<a href="<?php echo esc_url( home_url( '/profile/?user=' . $match->user_id ) ); ?>" class="wpmatch-button secondary match-profile-btn">
								<span class="dashicons dashicons-admin-users"></span>
								<?php esc_html_e( 'View Profile', 'wpmatch' ); ?>
							</a>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>