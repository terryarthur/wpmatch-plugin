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
$matches = array(); // TODO: Implement actual matches retrieval
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
			<?php foreach ( $matches as $match ) : ?>
				<div class="wpmatch-match-card" data-user-id="<?php echo esc_attr( $match->user_id ); ?>">
					<!-- Match card content will go here -->
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>