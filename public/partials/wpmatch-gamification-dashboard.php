<?php
/**
 * Gamification Dashboard Template
 *
 * @package WPMatch
 * @since 1.6.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_user_id = get_current_user_id();
?>

<div class="wpmatch-gamification-dashboard">
	<!-- Header -->
	<div class="gamification-header">
		<h2><?php esc_html_e( 'Your Gaming Progress', 'wpmatch' ); ?></h2>
		<div class="user-level-badge">
			<div class="level-icon">🏆</div>
			<span class="level-text"><?php esc_html_e( 'Level 1', 'wpmatch' ); ?></span>
		</div>
	</div>

	<!-- Progress Overview -->
	<div class="progress-overview">
		<div class="progress-card">
			<h3><?php esc_html_e( 'Points', 'wpmatch' ); ?></h3>
			<div class="progress-value points">0</div>
			<div class="progress-bar">
				<div class="progress-fill" style="width: 0%"></div>
			</div>
			<div class="progress-text"><?php esc_html_e( 'Loading...', 'wpmatch' ); ?></div>
		</div>

		<div class="progress-card">
			<h3><?php esc_html_e( 'Level', 'wpmatch' ); ?></h3>
			<div class="progress-value level">1</div>
		</div>

		<div class="progress-card">
			<h3><?php esc_html_e( 'Streak', 'wpmatch' ); ?></h3>
			<div class="progress-value streak">0</div>
		</div>

		<div class="progress-card">
			<h3><?php esc_html_e( 'Achievements', 'wpmatch' ); ?></h3>
			<div class="progress-value achievements">0</div>
		</div>
	</div>

	<!-- Achievements Section -->
	<div class="achievements-section">
		<div class="section-header">
			<h3><?php esc_html_e( 'Achievements', 'wpmatch' ); ?></h3>
			<a href="#" class="view-all-btn view-all-achievements"><?php esc_html_e( 'View All', 'wpmatch' ); ?></a>
		</div>
		<div class="achievements-grid">
			<!-- Achievements will be loaded via AJAX -->
			<div class="loading-placeholder">
				<p><?php esc_html_e( 'Loading achievements...', 'wpmatch' ); ?></p>
			</div>
		</div>
	</div>

	<!-- Daily Challenges Section -->
	<div class="challenges-section">
		<div class="section-header">
			<h3><?php esc_html_e( 'Daily Challenges', 'wpmatch' ); ?></h3>
		</div>
		<div class="challenges-grid">
			<!-- Challenges will be loaded via AJAX -->
			<div class="loading-placeholder">
				<p><?php esc_html_e( 'Loading challenges...', 'wpmatch' ); ?></p>
			</div>
		</div>
	</div>

	<!-- Leaderboard Section -->
	<div class="leaderboard-section">
		<div class="section-header">
			<h3><?php esc_html_e( 'Leaderboard', 'wpmatch' ); ?></h3>
		</div>
		<div class="leaderboard-tabs">
			<button class="leaderboard-tab active" data-type="weekly"><?php esc_html_e( 'Weekly', 'wpmatch' ); ?></button>
			<button class="leaderboard-tab" data-type="monthly"><?php esc_html_e( 'Monthly', 'wpmatch' ); ?></button>
			<button class="leaderboard-tab" data-type="all_time"><?php esc_html_e( 'All Time', 'wpmatch' ); ?></button>
		</div>
		<div class="leaderboard-list">
			<!-- Leaderboard will be loaded via AJAX -->
			<div class="loading-placeholder">
				<p><?php esc_html_e( 'Loading leaderboard...', 'wpmatch' ); ?></p>
			</div>
		</div>
	</div>

	<!-- Rewards Section -->
	<div class="rewards-section">
		<div class="section-header">
			<h3><?php esc_html_e( 'Rewards Store', 'wpmatch' ); ?></h3>
		</div>
		<div class="rewards-filter">
			<button class="filter-btn active" data-filter="all"><?php esc_html_e( 'All', 'wpmatch' ); ?></button>
			<button class="filter-btn" data-filter="available"><?php esc_html_e( 'Available', 'wpmatch' ); ?></button>
			<button class="filter-btn" data-filter="claimed"><?php esc_html_e( 'Claimed', 'wpmatch' ); ?></button>
			<button class="filter-btn" data-filter="locked"><?php esc_html_e( 'Locked', 'wpmatch' ); ?></button>
		</div>
		<div class="rewards-grid">
			<!-- Rewards will be loaded via AJAX -->
			<div class="loading-placeholder">
				<p><?php esc_html_e( 'Loading rewards...', 'wpmatch' ); ?></p>
			</div>
		</div>
	</div>

	<!-- Streaks Section -->
	<div class="streaks-section">
		<h3><?php esc_html_e( 'Your Streaks', 'wpmatch' ); ?></h3>
		<div class="streaks-grid">
			<!-- Streaks will be loaded via AJAX -->
			<div class="loading-placeholder" style="color: #fff;">
				<p><?php esc_html_e( 'Loading streaks...', 'wpmatch' ); ?></p>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
// Localize script data
var wpmatch_gamification = {
	rest_url: '<?php echo esc_url( rest_url() ); ?>',
	nonce: '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>',
	current_user_id: <?php echo esc_js( $current_user_id ); ?>,
	default_avatar: '<?php echo esc_url( get_avatar_url( $current_user_id, array( 'size' => 40 ) ) ); ?>',
	strings: {
		loading: '<?php esc_html_e( 'Loading...', 'wpmatch' ); ?>',
		error: '<?php esc_html_e( 'Error loading data', 'wpmatch' ); ?>',
		success: '<?php esc_html_e( 'Success!', 'wpmatch' ); ?>',
		claim_reward: '<?php esc_html_e( 'Claim Reward', 'wpmatch' ); ?>',
		claimed: '<?php esc_html_e( 'Claimed', 'wpmatch' ); ?>',
		claiming: '<?php esc_html_e( 'Claiming...', 'wpmatch' ); ?>'
	}
};
</script>