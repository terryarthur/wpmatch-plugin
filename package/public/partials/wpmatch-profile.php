<?php
/**
 * User profile template
 *
 * @package WPMatch
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user_id = isset( $atts['user_id'] ) ? absint( $atts['user_id'] ) : get_current_user_id();
$user = get_userdata( $user_id );

if ( ! $user ) {
	echo '<p>' . esc_html__( 'User not found.', 'wpmatch' ) . '</p>';
	return;
}
?>

<div class="wpmatch-public">
	<div class="wpmatch-profile-card">
		<div class="wpmatch-profile-header">
			<?php
			$avatar_url = get_avatar_url( $user_id, array( 'size' => 80 ) );
			?>
			<img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $user->display_name ); ?>" class="wpmatch-profile-avatar" />
			<div class="wpmatch-profile-info">
				<h3><?php echo esc_html( $user->display_name ); ?></h3>
				<div class="wpmatch-profile-meta">
					<?php
					global $wpdb;
					$table_name = $wpdb->prefix . 'wpmatch_user_profiles';
					$profile = $wpdb->get_row( $wpdb->prepare(
						"SELECT * FROM $table_name WHERE user_id = %d",
						$user_id
					) );

					if ( $profile ) {
						if ( $profile->age ) {
							echo esc_html( sprintf( __( 'Age: %d', 'wpmatch' ), $profile->age ) );
						}
						if ( $profile->location ) {
							echo ' • ' . esc_html( $profile->location );
						}
					}
					?>
				</div>
			</div>
		</div>

		<?php if ( $profile && $profile->about_me ) : ?>
			<div class="wpmatch-profile-about">
				<h4><?php esc_html_e( 'About Me', 'wpmatch' ); ?></h4>
				<p><?php echo esc_html( $profile->about_me ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( $user_id === get_current_user_id() ) : ?>
			<div class="wpmatch-profile-actions">
				<a href="#" class="wpmatch-button"><?php esc_html_e( 'Edit Profile', 'wpmatch' ); ?></a>
				<a href="#" class="wpmatch-button secondary"><?php esc_html_e( 'Upload Photos', 'wpmatch' ); ?></a>
			</div>
		<?php else : ?>
			<div class="wpmatch-profile-actions">
				<button type="button" class="wpmatch-button wpmatch-like-btn" data-profile-id="<?php echo esc_attr( $user_id ); ?>">
					<?php esc_html_e( 'Like', 'wpmatch' ); ?>
				</button>
				<button type="button" class="wpmatch-button secondary wpmatch-message-btn" data-profile-id="<?php echo esc_attr( $user_id ); ?>">
					<?php esc_html_e( 'Message', 'wpmatch' ); ?>
				</button>
			</div>
		<?php endif; ?>
	</div>
</div>