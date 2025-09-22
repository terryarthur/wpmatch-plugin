<?php
/**
 * Dating Interface - Swipe/Browse System
 *
 * @package WPMatch
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check if user is logged in
if ( ! is_user_logged_in() ) {
	echo '<p>' . esc_html__( 'Please log in to start browsing profiles.', 'wpmatch' ) . '</p>';
	return;
}

$current_user_id = get_current_user_id();

// Get user's preferences
global $wpdb;
$preferences = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$wpdb->prefix}wpmatch_user_preferences WHERE user_id = %d",
	$current_user_id
) );

// Get user's current profile
$current_profile = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$wpdb->prefix}wpmatch_user_profiles WHERE user_id = %d",
	$current_user_id
) );

$settings = get_option( 'wpmatch_settings', array() );
$daily_suggestions = isset( $settings['daily_match_suggestions'] ) ? $settings['daily_match_suggestions'] : 5;
?>

<div class="wpmatch-dating-interface">
	<!-- Header -->
	<div class="dating-header">
		<div class="header-content">
			<div class="logo-section">
				<h1 class="app-logo">WPMatch</h1>
			</div>

			<div class="header-actions">
				<button class="header-btn" id="filters-btn" title="<?php esc_attr_e( 'Filters', 'wpmatch' ); ?>">
					<span class="dashicons dashicons-filter"></span>
				</button>
				<button class="header-btn" id="messages-btn" title="<?php esc_attr_e( 'Messages', 'wpmatch' ); ?>">
					<span class="dashicons dashicons-format-chat"></span>
					<span class="notification-badge" id="message-count" style="display: none;">0</span>
				</button>
				<button class="header-btn" id="profile-btn" title="<?php esc_attr_e( 'Profile', 'wpmatch' ); ?>">
					<span class="dashicons dashicons-admin-users"></span>
				</button>
			</div>
		</div>
	</div>

	<!-- Main Content -->
	<div class="dating-content">
		<!-- Left Sidebar - Quick Actions (Desktop Only) -->
		<div class="dating-sidebar">
			<div class="sidebar-section">
				<h3><?php esc_html_e( 'Quick Actions', 'wpmatch' ); ?></h3>

				<div class="quick-action-card" id="daily-matches">
					<div class="action-icon">
						<span class="dashicons dashicons-heart"></span>
					</div>
					<div class="action-content">
						<h4><?php esc_html_e( 'Daily Matches', 'wpmatch' ); ?></h4>
						<p><?php printf( esc_html__( '%d new matches today', 'wpmatch' ), $daily_suggestions ); ?></p>
					</div>
				</div>

				<div class="quick-action-card" id="nearby-users">
					<div class="action-icon">
						<span class="dashicons dashicons-location"></span>
					</div>
					<div class="action-content">
						<h4><?php esc_html_e( 'Nearby', 'wpmatch' ); ?></h4>
						<p><?php esc_html_e( 'People in your area', 'wpmatch' ); ?></p>
					</div>
				</div>

				<div class="quick-action-card" id="recently-active">
					<div class="action-icon">
						<span class="dashicons dashicons-clock"></span>
					</div>
					<div class="action-content">
						<h4><?php esc_html_e( 'Recently Active', 'wpmatch' ); ?></h4>
						<p><?php esc_html_e( 'Active in last 24h', 'wpmatch' ); ?></p>
					</div>
				</div>

				<div class="quick-action-card" id="who-liked-me">
					<div class="action-icon">
						<span class="dashicons dashicons-thumbs-up"></span>
					</div>
					<div class="action-content">
						<h4><?php esc_html_e( 'Who Liked Me', 'wpmatch' ); ?></h4>
						<p class="premium-feature"><?php esc_html_e( 'Premium Feature', 'wpmatch' ); ?></p>
					</div>
				</div>
			</div>

			<div class="sidebar-section">
				<h3><?php esc_html_e( 'Activity', 'wpmatch' ); ?></h3>
				<div class="activity-stats">
					<div class="stat-item">
						<span class="stat-number" id="likes-given">0</span>
						<span class="stat-label"><?php esc_html_e( 'Likes Given', 'wpmatch' ); ?></span>
					</div>
					<div class="stat-item">
						<span class="stat-number" id="matches-made">0</span>
						<span class="stat-label"><?php esc_html_e( 'Matches', 'wpmatch' ); ?></span>
					</div>
					<div class="stat-item">
						<span class="stat-number" id="profile-views">0</span>
						<span class="stat-label"><?php esc_html_e( 'Profile Views', 'wpmatch' ); ?></span>
					</div>
				</div>
			</div>
		</div>

		<!-- Center - Card Stack -->
		<div class="dating-main">
			<div class="card-stack-container">
				<div class="card-stack" id="profile-stack">
					<!-- Profile cards will be dynamically loaded here -->
					<div class="loading-card">
						<div class="loading-spinner">
							<div class="spinner"></div>
						</div>
						<p><?php esc_html_e( 'Finding amazing people for you...', 'wpmatch' ); ?></p>
					</div>
				</div>

				<!-- Action Buttons -->
				<div class="action-buttons">
					<button class="action-btn pass-btn" id="pass-btn" title="<?php esc_attr_e( 'Pass', 'wpmatch' ); ?>">
						<span class="dashicons dashicons-no-alt"></span>
					</button>

					<button class="action-btn super-like-btn" id="super-like-btn" title="<?php esc_attr_e( 'Super Like', 'wpmatch' ); ?>">
						<span class="dashicons dashicons-star-filled"></span>
					</button>

					<button class="action-btn like-btn" id="like-btn" title="<?php esc_attr_e( 'Like', 'wpmatch' ); ?>">
						<span class="dashicons dashicons-heart"></span>
					</button>

					<button class="action-btn boost-btn" id="boost-btn" title="<?php esc_attr_e( 'Boost', 'wpmatch' ); ?>">
						<span class="dashicons dashicons-performance"></span>
					</button>
				</div>

				<!-- Keyboard Shortcuts Info -->
				<div class="keyboard-shortcuts">
					<p><?php esc_html_e( 'Use arrow keys: ← Pass • ↑ Super Like • → Like', 'wpmatch' ); ?></p>
				</div>
			</div>

			<!-- No More Cards Message -->
			<div class="no-more-cards" id="no-more-cards" style="display: none;">
				<div class="no-cards-content">
					<div class="no-cards-icon">
						<span class="dashicons dashicons-search"></span>
					</div>
					<h3><?php esc_html_e( "You've seen everyone!", 'wpmatch' ); ?></h3>
					<p><?php esc_html_e( "Check back later for new profiles, or expand your preferences to see more people.", 'wpmatch' ); ?></p>
					<button class="btn btn-primary" id="expand-preferences">
						<?php esc_html_e( 'Expand Preferences', 'wpmatch' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Right Sidebar - Match Info (Desktop Only) -->
		<div class="dating-info-panel">
			<div class="info-section">
				<h3><?php esc_html_e( 'Match Insights', 'wpmatch' ); ?></h3>
				<div class="insight-card" id="compatibility-insight" style="display: none;">
					<div class="insight-header">
						<span class="insight-icon">💖</span>
						<h4><?php esc_html_e( 'Compatibility', 'wpmatch' ); ?></h4>
					</div>
					<div class="compatibility-score">
						<div class="score-circle">
							<span class="score-number" id="compatibility-score">85</span>
							<span class="score-percent">%</span>
						</div>
					</div>
					<div class="compatibility-factors">
						<div class="factor">
							<span class="factor-label"><?php esc_html_e( 'Common Interests', 'wpmatch' ); ?></span>
							<div class="factor-bar">
								<div class="factor-fill" style="width: 70%"></div>
							</div>
						</div>
						<div class="factor">
							<span class="factor-label"><?php esc_html_e( 'Location', 'wpmatch' ); ?></span>
							<div class="factor-bar">
								<div class="factor-fill" style="width: 90%"></div>
							</div>
						</div>
						<div class="factor">
							<span class="factor-label"><?php esc_html_e( 'Age Range', 'wpmatch' ); ?></span>
							<div class="factor-bar">
								<div class="factor-fill" style="width: 95%"></div>
							</div>
						</div>
					</div>
				</div>

				<div class="insight-card">
					<div class="insight-header">
						<span class="insight-icon">🔥</span>
						<h4><?php esc_html_e( 'Hot Tips', 'wpmatch' ); ?></h4>
					</div>
					<div class="tips-list">
						<p><?php esc_html_e( 'Add more photos to get 3x more matches!', 'wpmatch' ); ?></p>
						<button class="btn btn-small btn-secondary"><?php esc_html_e( 'Add Photos', 'wpmatch' ); ?></button>
					</div>
				</div>

				<div class="insight-card">
					<div class="insight-header">
						<span class="insight-icon">⚡</span>
						<h4><?php esc_html_e( 'Boost Your Profile', 'wpmatch' ); ?></h4>
					</div>
					<div class="boost-info">
						<p><?php esc_html_e( 'Be seen by 10x more people for the next hour', 'wpmatch' ); ?></p>
						<button class="btn btn-small btn-premium"><?php esc_html_e( 'Boost Now', 'wpmatch' ); ?></button>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Filters Modal -->
	<div class="modal-overlay" id="filters-modal" style="display: none;">
		<div class="modal-content filters-modal">
			<div class="modal-header">
				<h2><?php esc_html_e( 'Discovery Settings', 'wpmatch' ); ?></h2>
				<button class="modal-close" onclick="closeModal('filters-modal')">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>
			<div class="modal-body">
				<form id="filters-form">
					<div class="filter-group">
						<label><?php esc_html_e( 'Maximum Distance', 'wpmatch' ); ?></label>
						<div class="distance-slider">
							<input type="range" id="distance-range" min="1" max="100" value="<?php echo esc_attr( $preferences->max_distance ?? 25 ); ?>">
							<div class="distance-display">
								<span id="distance-value"><?php echo esc_html( $preferences->max_distance ?? 25 ); ?></span> <?php esc_html_e( 'miles', 'wpmatch' ); ?>
							</div>
						</div>
					</div>

					<div class="filter-group">
						<label><?php esc_html_e( 'Age Range', 'wpmatch' ); ?></label>
						<div class="age-range-sliders">
							<div class="age-input">
								<label for="min-age"><?php esc_html_e( 'Min Age', 'wpmatch' ); ?></label>
								<input type="range" id="min-age" min="18" max="80" value="<?php echo esc_attr( $preferences->min_age ?? 18 ); ?>">
								<span id="min-age-value"><?php echo esc_html( $preferences->min_age ?? 18 ); ?></span>
							</div>
							<div class="age-input">
								<label for="max-age"><?php esc_html_e( 'Max Age', 'wpmatch' ); ?></label>
								<input type="range" id="max-age" min="18" max="99" value="<?php echo esc_attr( $preferences->max_age ?? 99 ); ?>">
								<span id="max-age-value"><?php echo esc_html( $preferences->max_age ?? 99 ); ?></span>
							</div>
						</div>
					</div>

					<div class="filter-group">
						<label><?php esc_html_e( 'Show Me', 'wpmatch' ); ?></label>
						<div class="gender-options">
							<label class="option-button">
								<input type="radio" name="show_me" value="men" <?php checked( $preferences->preferred_gender ?? '', 'men' ); ?>>
								<span><?php esc_html_e( 'Men', 'wpmatch' ); ?></span>
							</label>
							<label class="option-button">
								<input type="radio" name="show_me" value="women" <?php checked( $preferences->preferred_gender ?? '', 'women' ); ?>>
								<span><?php esc_html_e( 'Women', 'wpmatch' ); ?></span>
							</label>
							<label class="option-button">
								<input type="radio" name="show_me" value="everyone" <?php checked( $preferences->preferred_gender ?? '', 'everyone' ); ?>>
								<span><?php esc_html_e( 'Everyone', 'wpmatch' ); ?></span>
							</label>
						</div>
					</div>

					<div class="filter-group">
						<label><?php esc_html_e( 'Recently Active', 'wpmatch' ); ?></label>
						<div class="toggle-switch">
							<input type="checkbox" id="recently-active-toggle">
							<label for="recently-active-toggle" class="toggle-label"></label>
							<span><?php esc_html_e( 'Show only people active in the last week', 'wpmatch' ); ?></span>
						</div>
					</div>

					<div class="filter-group premium-filter">
						<label><?php esc_html_e( 'Advanced Filters', 'wpmatch' ); ?> <span class="premium-badge">PREMIUM</span></label>
						<div class="premium-filters">
							<p><?php esc_html_e( 'Filter by education, height, interests, and more with Premium', 'wpmatch' ); ?></p>
							<button type="button" class="btn btn-premium"><?php esc_html_e( 'Upgrade Now', 'wpmatch' ); ?></button>
						</div>
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button class="btn btn-secondary" onclick="resetFilters()"><?php esc_html_e( 'Reset', 'wpmatch' ); ?></button>
				<button class="btn btn-primary" onclick="applyFilters()"><?php esc_html_e( 'Apply Filters', 'wpmatch' ); ?></button>
			</div>
		</div>
	</div>

	<!-- Match Modal -->
	<div class="modal-overlay" id="match-modal" style="display: none;">
		<div class="modal-content match-modal">
			<div class="match-celebration">
				<div class="celebration-animation">
					<div class="heart heart-1">💖</div>
					<div class="heart heart-2">💕</div>
					<div class="heart heart-3">💖</div>
					<div class="heart heart-4">💕</div>
				</div>
				<h2><?php esc_html_e( "It's a Match!", 'wpmatch' ); ?></h2>
				<p><?php esc_html_e( 'You and this person liked each other!', 'wpmatch' ); ?></p>

				<div class="match-profiles">
					<div class="match-profile my-profile">
						<img src="" alt="<?php esc_attr_e( 'Your photo', 'wpmatch' ); ?>" id="match-my-photo">
						<span><?php esc_html_e( 'You', 'wpmatch' ); ?></span>
					</div>
					<div class="match-icon">💖</div>
					<div class="match-profile their-profile">
						<img src="" alt="<?php esc_attr_e( 'Their photo', 'wpmatch' ); ?>" id="match-their-photo">
						<span id="match-their-name"></span>
					</div>
				</div>

				<div class="match-actions">
					<button class="btn btn-secondary" onclick="closeModal('match-modal')"><?php esc_html_e( 'Keep Playing', 'wpmatch' ); ?></button>
					<button class="btn btn-primary" onclick="startConversation()"><?php esc_html_e( 'Send a Message', 'wpmatch' ); ?></button>
				</div>
			</div>
		</div>
	</div>
</div>

<style>
/* Main Dating Interface Layout */
.wpmatch-dating-interface {
	min-height: 100vh;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* Header */
.dating-header {
	background: rgba(255, 255, 255, 0.95);
	backdrop-filter: blur(10px);
	border-bottom: 1px solid rgba(255, 255, 255, 0.2);
	position: sticky;
	top: 0;
	z-index: 100;
}

.header-content {
	max-width: 1400px;
	margin: 0 auto;
	padding: 0 20px;
	display: flex;
	justify-content: space-between;
	align-items: center;
	height: 70px;
}

.app-logo {
	font-size: 28px;
	font-weight: 700;
	background: linear-gradient(45deg, #667eea, #764ba2);
	-webkit-background-clip: text;
	-webkit-text-fill-color: transparent;
	background-clip: text;
	margin: 0;
}

.header-actions {
	display: flex;
	gap: 15px;
}

.header-btn {
	width: 50px;
	height: 50px;
	border-radius: 50%;
	border: none;
	background: linear-gradient(135deg, #667eea, #764ba2);
	color: white;
	cursor: pointer;
	display: flex;
	align-items: center;
	justify-content: center;
	position: relative;
	transition: all 0.3s ease;
	font-size: 18px;
}

.header-btn:hover {
	transform: translateY(-2px);
	box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
}

.notification-badge {
	position: absolute;
	top: -5px;
	right: -5px;
	background: #ff4757;
	color: white;
	border-radius: 50%;
	width: 20px;
	height: 20px;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 12px;
	font-weight: 600;
}

/* Main Content Layout */
.dating-content {
	max-width: 1400px;
	margin: 0 auto;
	padding: 30px 20px;
	display: grid;
	grid-template-columns: 300px 1fr 300px;
	gap: 30px;
	min-height: calc(100vh - 100px);
}

/* Left Sidebar */
.dating-sidebar {
	background: rgba(255, 255, 255, 0.95);
	border-radius: 20px;
	padding: 30px;
	height: fit-content;
	backdrop-filter: blur(10px);
	box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.sidebar-section {
	margin-bottom: 40px;
}

.sidebar-section:last-child {
	margin-bottom: 0;
}

.sidebar-section h3 {
	margin: 0 0 20px 0;
	color: #2c3e50;
	font-size: 18px;
	font-weight: 600;
}

.quick-action-card {
	display: flex;
	align-items: center;
	padding: 20px;
	border-radius: 12px;
	margin-bottom: 15px;
	cursor: pointer;
	transition: all 0.3s ease;
	background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
	border: 1px solid rgba(102, 126, 234, 0.2);
}

.quick-action-card:hover {
	transform: translateY(-2px);
	box-shadow: 0 8px 25px rgba(102, 126, 234, 0.2);
	background: linear-gradient(135deg, rgba(102, 126, 234, 0.15), rgba(118, 75, 162, 0.15));
}

.action-icon {
	width: 50px;
	height: 50px;
	border-radius: 50%;
	background: linear-gradient(135deg, #667eea, #764ba2);
	display: flex;
	align-items: center;
	justify-content: center;
	color: white;
	margin-right: 15px;
	flex-shrink: 0;
}

.action-icon .dashicons {
	font-size: 20px;
}

.action-content h4 {
	margin: 0 0 5px 0;
	color: #2c3e50;
	font-size: 16px;
	font-weight: 600;
}

.action-content p {
	margin: 0;
	color: #718096;
	font-size: 14px;
}

.premium-feature {
	color: #f39c12 !important;
	font-weight: 600;
}

.activity-stats {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 15px;
}

.stat-item {
	text-align: center;
	padding: 15px;
	background: rgba(102, 126, 234, 0.1);
	border-radius: 12px;
}

.stat-number {
	display: block;
	font-size: 24px;
	font-weight: 700;
	color: #667eea;
	margin-bottom: 5px;
}

.stat-label {
	font-size: 12px;
	color: #718096;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}

/* Main Card Stack Area */
.dating-main {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	position: relative;
}

.card-stack-container {
	width: 100%;
	max-width: 400px;
	aspect-ratio: 3/4;
	position: relative;
}

.card-stack {
	position: absolute;
	inset: 0;
	perspective: 1000px;
}

.profile-card {
	position: absolute;
	inset: 0;
	background: white;
	border-radius: 20px;
	box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
	overflow: hidden;
	cursor: pointer;
	transform-origin: center bottom;
	transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
	user-select: none;
}

.profile-card.dragging {
	transition: none;
	z-index: 10;
}

.profile-card.swiped-left {
	transform: translateX(-100%) rotate(-30deg);
	opacity: 0;
}

.profile-card.swiped-right {
	transform: translateX(100%) rotate(30deg);
	opacity: 0;
}

.profile-card.swiped-up {
	transform: translateY(-100%) scale(1.1);
	opacity: 0;
}

.card-background {
	position: absolute;
	inset: 0;
	background-size: cover;
	background-position: center;
	background-repeat: no-repeat;
}

.card-gradient {
	position: absolute;
	inset: 0;
	background: linear-gradient(transparent 0%, transparent 40%, rgba(0,0,0,0.3) 70%, rgba(0,0,0,0.8) 100%);
}

.card-content {
	position: absolute;
	bottom: 0;
	left: 0;
	right: 0;
	color: white;
	padding: 30px;
}

.card-name {
	font-size: 28px;
	font-weight: 700;
	margin: 0 0 8px 0;
	text-shadow: 0 2px 4px rgba(0,0,0,0.5);
}

.card-age {
	font-size: 24px;
	font-weight: 400;
	opacity: 0.9;
}

.card-location {
	font-size: 16px;
	opacity: 0.8;
	margin: 8px 0;
	display: flex;
	align-items: center;
	gap: 5px;
}

.card-bio {
	font-size: 16px;
	line-height: 1.4;
	margin: 15px 0 0 0;
	opacity: 0.9;
}

.card-interests {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	margin-top: 15px;
}

.interest-tag {
	background: rgba(255, 255, 255, 0.2);
	backdrop-filter: blur(10px);
	padding: 6px 12px;
	border-radius: 20px;
	font-size: 14px;
	border: 1px solid rgba(255, 255, 255, 0.3);
}

.card-photos-indicator {
	position: absolute;
	top: 20px;
	left: 20px;
	right: 20px;
	display: flex;
	gap: 5px;
}

.photo-dot {
	flex: 1;
	height: 3px;
	background: rgba(255, 255, 255, 0.3);
	border-radius: 2px;
}

.photo-dot.active {
	background: white;
}

.loading-card {
	position: absolute;
	inset: 0;
	background: white;
	border-radius: 20px;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	text-align: center;
	padding: 40px;
	box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.loading-spinner {
	margin-bottom: 20px;
}

.spinner {
	width: 50px;
	height: 50px;
	border: 4px solid #e3e7ed;
	border-top: 4px solid #667eea;
	border-radius: 50%;
	animation: spin 1s linear infinite;
}

@keyframes spin {
	to { transform: rotate(360deg); }
}

/* Action Buttons */
.action-buttons {
	display: flex;
	justify-content: center;
	gap: 20px;
	margin-top: 30px;
}

.action-btn {
	width: 60px;
	height: 60px;
	border-radius: 50%;
	border: none;
	cursor: pointer;
	display: flex;
	align-items: center;
	justify-content: center;
	transition: all 0.3s ease;
	font-size: 24px;
	position: relative;
	overflow: hidden;
}

.action-btn::before {
	content: '';
	position: absolute;
	inset: 0;
	border-radius: 50%;
	padding: 3px;
	background: linear-gradient(135deg, transparent, rgba(255,255,255,0.3));
	-webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
	-webkit-mask-composite: exclude;
}

.action-btn:hover {
	transform: translateY(-3px) scale(1.1);
	box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}

.pass-btn {
	background: linear-gradient(135deg, #ff6b6b, #ee5a24);
	color: white;
}

.like-btn {
	background: linear-gradient(135deg, #2ed573, #1dd1a1);
	color: white;
}

.super-like-btn {
	background: linear-gradient(135deg, #ffa502, #ff6348);
	color: white;
}

.boost-btn {
	background: linear-gradient(135deg, #a55eea, #8b5fbf);
	color: white;
}

.keyboard-shortcuts {
	margin-top: 20px;
	text-align: center;
}

.keyboard-shortcuts p {
	color: rgba(255, 255, 255, 0.8);
	font-size: 14px;
	margin: 0;
}

/* Right Info Panel */
.dating-info-panel {
	background: rgba(255, 255, 255, 0.95);
	border-radius: 20px;
	padding: 30px;
	height: fit-content;
	backdrop-filter: blur(10px);
	box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.info-section h3 {
	margin: 0 0 20px 0;
	color: #2c3e50;
	font-size: 18px;
	font-weight: 600;
}

.insight-card {
	background: rgba(102, 126, 234, 0.1);
	border-radius: 15px;
	padding: 20px;
	margin-bottom: 20px;
	border: 1px solid rgba(102, 126, 234, 0.2);
}

.insight-header {
	display: flex;
	align-items: center;
	margin-bottom: 15px;
}

.insight-icon {
	font-size: 24px;
	margin-right: 10px;
}

.insight-header h4 {
	margin: 0;
	color: #2c3e50;
	font-size: 16px;
	font-weight: 600;
}

.compatibility-score {
	text-align: center;
	margin: 20px 0;
}

.score-circle {
	width: 80px;
	height: 80px;
	border-radius: 50%;
	background: linear-gradient(135deg, #667eea, #764ba2);
	display: flex;
	align-items: center;
	justify-content: center;
	margin: 0 auto;
	color: white;
	position: relative;
}

.score-number {
	font-size: 24px;
	font-weight: 700;
}

.score-percent {
	font-size: 14px;
	position: absolute;
	right: 15px;
	top: 20px;
}

.compatibility-factors {
	margin-top: 15px;
}

.factor {
	margin-bottom: 12px;
}

.factor-label {
	font-size: 14px;
	color: #718096;
	display: block;
	margin-bottom: 5px;
}

.factor-bar {
	height: 6px;
	background: rgba(102, 126, 234, 0.2);
	border-radius: 3px;
	overflow: hidden;
}

.factor-fill {
	height: 100%;
	background: linear-gradient(135deg, #667eea, #764ba2);
	border-radius: 3px;
	transition: width 0.3s ease;
}

.no-more-cards {
	text-align: center;
	padding: 60px 40px;
	color: white;
}

.no-cards-content {
	max-width: 400px;
	margin: 0 auto;
}

.no-cards-icon {
	font-size: 80px;
	margin-bottom: 20px;
	opacity: 0.8;
}

.no-cards-content h3 {
	font-size: 32px;
	margin: 0 0 15px 0;
	font-weight: 700;
}

.no-cards-content p {
	font-size: 18px;
	line-height: 1.6;
	margin: 0 0 30px 0;
	opacity: 0.9;
}

/* Modals */
.modal-overlay {
	position: fixed;
	inset: 0;
	background: rgba(0, 0, 0, 0.8);
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 1000;
	backdrop-filter: blur(10px);
}

.modal-content {
	background: white;
	border-radius: 20px;
	max-width: 500px;
	width: 90%;
	max-height: 90vh;
	overflow-y: auto;
	position: relative;
}

.modal-header {
	padding: 30px 30px 0 30px;
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.modal-header h2 {
	margin: 0;
	color: #2c3e50;
	font-size: 24px;
	font-weight: 700;
}

.modal-close {
	width: 40px;
	height: 40px;
	border-radius: 50%;
	border: none;
	background: #f8f9fa;
	cursor: pointer;
	display: flex;
	align-items: center;
	justify-content: center;
	transition: all 0.3s ease;
}

.modal-close:hover {
	background: #e9ecef;
	transform: scale(1.1);
}

.modal-body {
	padding: 30px;
}

.modal-footer {
	padding: 0 30px 30px 30px;
	display: flex;
	gap: 15px;
	justify-content: flex-end;
}

/* Filter Modal Styles */
.filter-group {
	margin-bottom: 30px;
}

.filter-group label {
	display: block;
	font-weight: 600;
	color: #2c3e50;
	margin-bottom: 15px;
	font-size: 16px;
}

.distance-slider {
	position: relative;
}

.distance-display {
	text-align: center;
	margin-top: 10px;
	font-weight: 600;
	color: #667eea;
	font-size: 18px;
}

.age-range-sliders {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 20px;
}

.age-input label {
	font-size: 14px;
	font-weight: 500;
	margin-bottom: 8px;
}

.gender-options {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 10px;
}

.option-button {
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 15px;
	border: 2px solid #e2e8f0;
	border-radius: 12px;
	cursor: pointer;
	transition: all 0.3s ease;
	font-weight: 600;
}

.option-button input[type="radio"] {
	display: none;
}

.option-button:hover {
	border-color: #667eea;
	background: rgba(102, 126, 234, 0.1);
}

.option-button input[type="radio"]:checked + span {
	color: #667eea;
}

.option-button:has(input[type="radio"]:checked) {
	border-color: #667eea;
	background: rgba(102, 126, 234, 0.1);
}

.toggle-switch {
	display: flex;
	align-items: center;
	gap: 15px;
}

.toggle-label {
	width: 50px;
	height: 25px;
	background: #e2e8f0;
	border-radius: 25px;
	position: relative;
	cursor: pointer;
	transition: all 0.3s ease;
}

.toggle-label::before {
	content: '';
	position: absolute;
	top: 2px;
	left: 2px;
	width: 21px;
	height: 21px;
	background: white;
	border-radius: 50%;
	transition: all 0.3s ease;
}

input[type="checkbox"]:checked + .toggle-label {
	background: #667eea;
}

input[type="checkbox"]:checked + .toggle-label::before {
	left: 27px;
}

.premium-filter {
	opacity: 0.6;
}

.premium-badge {
	background: linear-gradient(135deg, #ffa502, #ff6348);
	color: white;
	padding: 3px 8px;
	border-radius: 12px;
	font-size: 12px;
	font-weight: 600;
	text-transform: uppercase;
	margin-left: 10px;
}

.premium-filters {
	text-align: center;
	padding: 20px;
	background: rgba(255, 165, 2, 0.1);
	border-radius: 12px;
	border: 1px solid rgba(255, 165, 2, 0.2);
}

/* Match Modal */
.match-modal {
	text-align: center;
	padding: 40px;
	background: linear-gradient(135deg, #667eea, #764ba2);
	color: white;
}

.celebration-animation {
	position: relative;
	height: 100px;
	margin-bottom: 30px;
}

.heart {
	position: absolute;
	font-size: 30px;
	animation: float 2s ease-in-out infinite;
}

.heart-1 { left: 10%; animation-delay: 0s; }
.heart-2 { left: 30%; animation-delay: 0.5s; }
.heart-3 { right: 30%; animation-delay: 1s; }
.heart-4 { right: 10%; animation-delay: 1.5s; }

@keyframes float {
	0%, 100% { transform: translateY(0px); }
	50% { transform: translateY(-20px); }
}

.match-modal h2 {
	color: white;
	font-size: 36px;
	margin: 0 0 15px 0;
	font-weight: 700;
}

.match-modal p {
	font-size: 18px;
	margin: 0 0 40px 0;
	opacity: 0.9;
}

.match-profiles {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 30px;
	margin-bottom: 40px;
}

.match-profile {
	text-align: center;
}

.match-profile img {
	width: 100px;
	height: 100px;
	border-radius: 50%;
	border: 4px solid white;
	object-fit: cover;
	margin-bottom: 10px;
}

.match-icon {
	font-size: 40px;
	animation: pulse 1.5s ease-in-out infinite;
}

@keyframes pulse {
	0%, 100% { transform: scale(1); }
	50% { transform: scale(1.2); }
}

.match-actions {
	display: flex;
	gap: 20px;
	justify-content: center;
}

/* Buttons */
.btn {
	padding: 12px 24px;
	border-radius: 25px;
	border: none;
	font-weight: 600;
	cursor: pointer;
	transition: all 0.3s ease;
	text-decoration: none;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: 8px;
	font-size: 16px;
}

.btn-primary {
	background: linear-gradient(135deg, #667eea, #764ba2);
	color: white;
}

.btn-primary:hover {
	transform: translateY(-2px);
	box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
}

.btn-secondary {
	background: transparent;
	color: #667eea;
	border: 2px solid #667eea;
}

.btn-secondary:hover {
	background: #667eea;
	color: white;
}

.btn-premium {
	background: linear-gradient(135deg, #ffa502, #ff6348);
	color: white;
}

.btn-premium:hover {
	transform: translateY(-2px);
	box-shadow: 0 8px 20px rgba(255, 165, 2, 0.3);
}

.btn-small {
	padding: 8px 16px;
	font-size: 14px;
}

/* Responsive Design */
@media (max-width: 1200px) {
	.dating-content {
		grid-template-columns: 250px 1fr 250px;
		gap: 20px;
	}

	.dating-sidebar,
	.dating-info-panel {
		padding: 20px;
	}
}

@media (max-width: 1024px) {
	.dating-content {
		grid-template-columns: 1fr;
		gap: 20px;
		padding: 20px;
	}

	.dating-sidebar,
	.dating-info-panel {
		display: none;
	}

	.card-stack-container {
		max-width: 350px;
	}

	.action-buttons {
		gap: 15px;
	}

	.action-btn {
		width: 55px;
		height: 55px;
		font-size: 20px;
	}
}

@media (max-width: 768px) {
	.header-content {
		padding: 0 15px;
	}

	.app-logo {
		font-size: 24px;
	}

	.header-btn {
		width: 45px;
		height: 45px;
		font-size: 16px;
	}

	.dating-content {
		padding: 15px;
	}

	.card-stack-container {
		max-width: 320px;
	}

	.card-content {
		padding: 20px;
	}

	.card-name {
		font-size: 24px;
	}

	.card-age {
		font-size: 20px;
	}

	.action-buttons {
		gap: 10px;
	}

	.action-btn {
		width: 50px;
		height: 50px;
		font-size: 18px;
	}

	.modal-content {
		margin: 20px;
		width: calc(100% - 40px);
	}

	.modal-header,
	.modal-body,
	.modal-footer {
		padding: 20px;
	}

	.gender-options {
		grid-template-columns: 1fr;
		gap: 10px;
	}

	.age-range-sliders {
		grid-template-columns: 1fr;
		gap: 15px;
	}

	.match-profiles {
		gap: 20px;
	}

	.match-profile img {
		width: 80px;
		height: 80px;
	}

	.match-actions {
		flex-direction: column;
		align-items: stretch;
	}
}

@media (max-width: 480px) {
	.card-stack-container {
		max-width: 280px;
	}

	.no-cards-content h3 {
		font-size: 24px;
	}

	.no-cards-content p {
		font-size: 16px;
	}

	.match-modal h2 {
		font-size: 28px;
	}

	.celebration-animation {
		height: 80px;
	}

	.heart {
		font-size: 24px;
	}
}

/* Touch/Swipe Indicators */
.swipe-indicator {
	position: absolute;
	top: 50%;
	transform: translateY(-50%);
	font-size: 60px;
	font-weight: 700;
	opacity: 0;
	transition: opacity 0.2s ease;
	pointer-events: none;
	text-shadow: 0 2px 4px rgba(0,0,0,0.5);
}

.swipe-indicator.like {
	right: 20px;
	color: #2ed573;
}

.swipe-indicator.pass {
	left: 20px;
	color: #ff6b6b;
}

.swipe-indicator.super-like {
	top: 20px;
	left: 50%;
	transform: translateX(-50%);
	color: #ffa502;
}

.profile-card.swipe-like .swipe-indicator.like,
.profile-card.swipe-pass .swipe-indicator.pass,
.profile-card.swipe-super .swipe-indicator.super-like {
	opacity: 1;
}

/* Animation Classes */
.bounce-in {
	animation: bounceIn 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

@keyframes bounceIn {
	0% {
		opacity: 0;
		transform: scale(0.3) translateY(50px);
	}
	50% {
		opacity: 1;
		transform: scale(1.05);
	}
	70% {
		transform: scale(0.9);
	}
	100% {
		opacity: 1;
		transform: scale(1) translateY(0);
	}
}

.shake {
	animation: shake 0.5s ease-in-out;
}

@keyframes shake {
	0%, 100% { transform: translateX(0); }
	25% { transform: translateX(-10px); }
	75% { transform: translateX(10px); }
}
</style>

<script>
class SwipeInterface {
	constructor() {
		this.currentProfileIndex = 0;
		this.profiles = [];
		this.isLoading = false;
		this.isDragging = false;
		this.startX = 0;
		this.startY = 0;
		this.currentX = 0;
		this.currentY = 0;
		this.currentCard = null;

		this.init();
	}

	async init() {
		await this.loadProfiles();
		this.setupEventListeners();
		this.loadUserStats();
		this.renderCurrentProfile();
	}

	async loadProfiles() {
		this.isLoading = true;
		try {
			const response = await fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'wpmatch_get_profiles',
					nonce: '<?php echo wp_create_nonce( 'wpmatch_swipe_nonce' ); ?>'
				})
			});

			const data = await response.json();
			if (data.success) {
				this.profiles = data.data.profiles;
				document.querySelector('.loading-card').style.display = 'none';
			} else {
				this.showNoMoreCards();
			}
		} catch (error) {
			console.error('Error loading profiles:', error);
			this.showNoMoreCards();
		}
		this.isLoading = false;
	}

	renderCurrentProfile() {
		if (this.currentProfileIndex >= this.profiles.length) {
			this.showNoMoreCards();
			return;
		}

		const profile = this.profiles[this.currentProfileIndex];
		const cardStack = document.getElementById('profile-stack');

		// Create new card
		const card = this.createProfileCard(profile);
		cardStack.appendChild(card);

		// Remove loading card if it exists
		const loadingCard = cardStack.querySelector('.loading-card');
		if (loadingCard) {
			loadingCard.remove();
		}

		// Load next card in background
		if (this.currentProfileIndex + 1 < this.profiles.length) {
			const nextProfile = this.profiles[this.currentProfileIndex + 1];
			const nextCard = this.createProfileCard(nextProfile);
			nextCard.style.transform = 'scale(0.95) translateY(10px)';
			nextCard.style.zIndex = '1';
			cardStack.appendChild(nextCard);
		}

		// Add entrance animation
		setTimeout(() => {
			card.classList.add('bounce-in');
		}, 100);

		// Update compatibility insight
		this.updateCompatibilityInsight(profile);
	}

	createProfileCard(profile) {
		const card = document.createElement('div');
		card.className = 'profile-card';
		card.dataset.profileId = profile.user_id;

		const photos = profile.photos || [];
		const primaryPhoto = photos.find(p => p.is_primary) || photos[0];

		card.innerHTML = `
			<div class="card-background" style="background-image: url('${primaryPhoto?.file_path || ''}')"></div>
			<div class="card-gradient"></div>

			${photos.length > 1 ? `
			<div class="card-photos-indicator">
				${photos.map((_, index) => `<div class="photo-dot ${index === 0 ? 'active' : ''}"></div>`).join('')}
			</div>
			` : ''}

			<div class="card-content">
				<div class="card-name">
					${profile.first_name || 'Unknown'} <span class="card-age">${profile.age || ''}</span>
				</div>
				<div class="card-location">
					<span class="dashicons dashicons-location"></span>
					${profile.location || 'Location not specified'}
				</div>
				<div class="card-bio">${profile.about_me || ''}</div>
				${profile.interests ? `
				<div class="card-interests">
					${profile.interests.split(',').slice(0, 3).map(interest =>
						`<span class="interest-tag">${interest.trim()}</span>`
					).join('')}
				</div>
				` : ''}
			</div>

			<div class="swipe-indicator like">LIKE</div>
			<div class="swipe-indicator pass">PASS</div>
			<div class="swipe-indicator super-like">SUPER LIKE</div>
		`;

		// Add swipe event listeners
		this.addSwipeListeners(card);

		return card;
	}

	addSwipeListeners(card) {
		// Mouse events
		card.addEventListener('mousedown', (e) => this.handleStart(e, e.clientX, e.clientY));
		document.addEventListener('mousemove', (e) => this.handleMove(e, e.clientX, e.clientY));
		document.addEventListener('mouseup', () => this.handleEnd());

		// Touch events
		card.addEventListener('touchstart', (e) => {
			const touch = e.touches[0];
			this.handleStart(e, touch.clientX, touch.clientY);
		});
		document.addEventListener('touchmove', (e) => {
			if (!this.isDragging) return;
			e.preventDefault();
			const touch = e.touches[0];
			this.handleMove(e, touch.clientX, touch.clientY);
		});
		document.addEventListener('touchend', () => this.handleEnd());

		// Photo navigation
		card.addEventListener('click', (e) => {
			if (!this.isDragging) {
				this.handlePhotoNavigation(e, card);
			}
		});
	}

	handleStart(e, clientX, clientY) {
		this.isDragging = true;
		this.startX = clientX;
		this.startY = clientY;
		this.currentCard = e.target.closest('.profile-card');

		if (this.currentCard) {
			this.currentCard.classList.add('dragging');
		}
	}

	handleMove(e, clientX, clientY) {
		if (!this.isDragging || !this.currentCard) return;

		this.currentX = clientX - this.startX;
		this.currentY = clientY - this.startY;

		const rotation = this.currentX * 0.1;
		const opacity = 1 - Math.abs(this.currentX) * 0.002;

		this.currentCard.style.transform = `translateX(${this.currentX}px) translateY(${this.currentY}px) rotate(${rotation}deg)`;
		this.currentCard.style.opacity = opacity;

		// Show swipe indicators
		if (Math.abs(this.currentX) > 50) {
			if (this.currentX > 0) {
				this.currentCard.classList.add('swipe-like');
				this.currentCard.classList.remove('swipe-pass');
			} else {
				this.currentCard.classList.add('swipe-pass');
				this.currentCard.classList.remove('swipe-like');
			}
		} else {
			this.currentCard.classList.remove('swipe-like', 'swipe-pass');
		}

		if (this.currentY < -50) {
			this.currentCard.classList.add('swipe-super');
		} else {
			this.currentCard.classList.remove('swipe-super');
		}
	}

	handleEnd() {
		if (!this.isDragging || !this.currentCard) return;

		this.isDragging = false;
		this.currentCard.classList.remove('dragging');

		const threshold = 100;
		const superLikeThreshold = -80;

		if (this.currentY < superLikeThreshold) {
			this.swipeAction('super_like');
		} else if (this.currentX > threshold) {
			this.swipeAction('like');
		} else if (this.currentX < -threshold) {
			this.swipeAction('pass');
		} else {
			// Snap back
			this.currentCard.style.transform = '';
			this.currentCard.style.opacity = '';
			this.currentCard.classList.remove('swipe-like', 'swipe-pass', 'swipe-super');
		}

		this.currentCard = null;
		this.currentX = 0;
		this.currentY = 0;
	}

	handlePhotoNavigation(e, card) {
		const rect = card.getBoundingClientRect();
		const x = e.clientX - rect.left;
		const cardWidth = rect.width;

		const photos = card.querySelectorAll('.photo-dot');
		if (photos.length <= 1) return;

		let currentPhotoIndex = Array.from(photos).findIndex(dot => dot.classList.contains('active'));

		if (x < cardWidth / 2 && currentPhotoIndex > 0) {
			// Previous photo
			photos[currentPhotoIndex].classList.remove('active');
			photos[currentPhotoIndex - 1].classList.add('active');
			this.updateCardBackground(card, currentPhotoIndex - 1);
		} else if (x > cardWidth / 2 && currentPhotoIndex < photos.length - 1) {
			// Next photo
			photos[currentPhotoIndex].classList.remove('active');
			photos[currentPhotoIndex + 1].classList.add('active');
			this.updateCardBackground(card, currentPhotoIndex + 1);
		}
	}

	updateCardBackground(card, photoIndex) {
		const profile = this.profiles[this.currentProfileIndex];
		const photos = profile.photos || [];
		const photo = photos[photoIndex];

		if (photo) {
			const background = card.querySelector('.card-background');
			background.style.backgroundImage = `url('${photo.file_path}')`;
		}
	}

	async swipeAction(action) {
		if (!this.currentCard) return;

		const profileId = this.currentCard.dataset.profileId;
		const profile = this.profiles[this.currentProfileIndex];

		// Animate card out
		this.animateCardOut(action);

		// Send action to server
		try {
			const response = await fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'wpmatch_swipe_action',
					profile_id: profileId,
					swipe_action: action,
					nonce: '<?php echo wp_create_nonce( 'wpmatch_swipe_nonce' ); ?>'
				})
			});

			const data = await response.json();
			if (data.success && data.data.is_match) {
				this.showMatchModal(profile);
			}
		} catch (error) {
			console.error('Error sending swipe action:', error);
		}

		// Update stats
		this.updateStats(action);

		// Move to next profile
		setTimeout(() => {
			this.nextProfile();
		}, 300);
	}

	animateCardOut(action) {
		if (!this.currentCard) return;

		switch (action) {
			case 'like':
				this.currentCard.classList.add('swiped-right');
				break;
			case 'pass':
				this.currentCard.classList.add('swiped-left');
				break;
			case 'super_like':
				this.currentCard.classList.add('swiped-up');
				break;
		}
	}

	nextProfile() {
		// Remove current card
		if (this.currentCard) {
			this.currentCard.remove();
		}

		this.currentProfileIndex++;

		// Load more profiles if running low
		if (this.profiles.length - this.currentProfileIndex <= 2) {
			this.loadMoreProfiles();
		}

		this.renderCurrentProfile();
	}

	async loadMoreProfiles() {
		if (this.isLoading) return;

		// This would load more profiles from the server
		// Implementation would depend on pagination strategy
	}

	showMatchModal(profile) {
		const modal = document.getElementById('match-modal');
		const myPhoto = document.getElementById('match-my-photo');
		const theirPhoto = document.getElementById('match-their-photo');
		const theirName = document.getElementById('match-their-name');

		// Set photos and name
		if (profile.photos && profile.photos.length > 0) {
			const primaryPhoto = profile.photos.find(p => p.is_primary) || profile.photos[0];
			theirPhoto.src = primaryPhoto.file_path;
		}
		theirName.textContent = profile.first_name || 'Unknown';

		// Get current user's photo (this would be loaded separately)
		this.loadCurrentUserPhoto().then(photoUrl => {
			myPhoto.src = photoUrl;
		});

		modal.style.display = 'flex';
	}

	async loadCurrentUserPhoto() {
		// Implementation to get current user's primary photo
		return '<?php echo get_avatar_url( get_current_user_id(), array( 'size' => 100 ) ); ?>';
	}

	showNoMoreCards() {
		document.getElementById('profile-stack').style.display = 'none';
		document.getElementById('no-more-cards').style.display = 'block';
	}

	updateCompatibilityInsight(profile) {
		const insightCard = document.getElementById('compatibility-insight');
		const scoreElement = document.getElementById('compatibility-score');

		// Calculate compatibility score (simplified)
		const score = this.calculateCompatibilityScore(profile);
		scoreElement.textContent = score;

		insightCard.style.display = 'block';
	}

	calculateCompatibilityScore(profile) {
		// Simplified compatibility calculation
		// In a real implementation, this would use the matching algorithm
		return Math.floor(Math.random() * 20) + 80; // 80-99%
	}

	updateStats(action) {
		if (action === 'like' || action === 'super_like') {
			const likesGiven = document.getElementById('likes-given');
			likesGiven.textContent = parseInt(likesGiven.textContent) + 1;
		}
	}

	async loadUserStats() {
		try {
			const response = await fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'wpmatch_get_user_stats',
					nonce: '<?php echo wp_create_nonce( 'wpmatch_swipe_nonce' ); ?>'
				})
			});

			const data = await response.json();
			if (data.success) {
				const stats = data.data;
				document.getElementById('likes-given').textContent = stats.likes_given || 0;
				document.getElementById('matches-made').textContent = stats.matches || 0;
				document.getElementById('profile-views').textContent = stats.profile_views || 0;

				if (stats.unread_messages > 0) {
					document.getElementById('message-count').textContent = stats.unread_messages;
					document.getElementById('message-count').style.display = 'flex';
				}
			}
		} catch (error) {
			console.error('Error loading user stats:', error);
		}
	}

	setupEventListeners() {
		// Action buttons
		document.getElementById('pass-btn').addEventListener('click', () => {
			if (this.currentCard) this.swipeAction('pass');
		});

		document.getElementById('like-btn').addEventListener('click', () => {
			if (this.currentCard) this.swipeAction('like');
		});

		document.getElementById('super-like-btn').addEventListener('click', () => {
			if (this.currentCard) this.swipeAction('super_like');
		});

		// Keyboard shortcuts
		document.addEventListener('keydown', (e) => {
			if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

			switch (e.key) {
				case 'ArrowLeft':
					e.preventDefault();
					if (this.currentCard) this.swipeAction('pass');
					break;
				case 'ArrowRight':
					e.preventDefault();
					if (this.currentCard) this.swipeAction('like');
					break;
				case 'ArrowUp':
					e.preventDefault();
					if (this.currentCard) this.swipeAction('super_like');
					break;
			}
		});

		// Header buttons
		document.getElementById('filters-btn').addEventListener('click', () => {
			document.getElementById('filters-modal').style.display = 'flex';
		});

		// Quick actions
		document.getElementById('daily-matches').addEventListener('click', () => {
			this.filterProfiles('daily');
		});

		document.getElementById('nearby-users').addEventListener('click', () => {
			this.filterProfiles('nearby');
		});

		document.getElementById('recently-active').addEventListener('click', () => {
			this.filterProfiles('recent');
		});

		// Distance slider
		const distanceRange = document.getElementById('distance-range');
		const distanceValue = document.getElementById('distance-value');

		distanceRange.addEventListener('input', () => {
			distanceValue.textContent = distanceRange.value;
		});

		// Age range sliders
		const minAge = document.getElementById('min-age');
		const maxAge = document.getElementById('max-age');
		const minAgeValue = document.getElementById('min-age-value');
		const maxAgeValue = document.getElementById('max-age-value');

		minAge.addEventListener('input', () => {
			minAgeValue.textContent = minAge.value;
			if (parseInt(minAge.value) >= parseInt(maxAge.value)) {
				maxAge.value = parseInt(minAge.value) + 1;
				maxAgeValue.textContent = maxAge.value;
			}
		});

		maxAge.addEventListener('input', () => {
			maxAgeValue.textContent = maxAge.value;
			if (parseInt(maxAge.value) <= parseInt(minAge.value)) {
				minAge.value = parseInt(maxAge.value) - 1;
				minAgeValue.textContent = minAge.value;
			}
		});
	}

	async filterProfiles(filterType) {
		// Implementation for different filter types
		console.log(`Filtering profiles by: ${filterType}`);
		// This would reload profiles with specific filters
	}
}

// Utility functions
function closeModal(modalId) {
	document.getElementById(modalId).style.display = 'none';
}

function applyFilters() {
	// Get filter values and apply them
	const filters = {
		max_distance: document.getElementById('distance-range').value,
		min_age: document.getElementById('min-age').value,
		max_age: document.getElementById('max-age').value,
		preferred_gender: document.querySelector('input[name="show_me"]:checked')?.value,
		recently_active: document.getElementById('recently-active-toggle').checked
	};

	// Save preferences and reload profiles
	console.log('Applying filters:', filters);
	closeModal('filters-modal');
}

function resetFilters() {
	// Reset all filter inputs to defaults
	document.getElementById('distance-range').value = 25;
	document.getElementById('distance-value').textContent = 25;
	document.getElementById('min-age').value = 18;
	document.getElementById('min-age-value').textContent = 18;
	document.getElementById('max-age').value = 99;
	document.getElementById('max-age-value').textContent = 99;
	document.getElementById('recently-active-toggle').checked = false;

	// Uncheck all radio buttons
	document.querySelectorAll('input[name="show_me"]').forEach(radio => {
		radio.checked = false;
	});
}

function startConversation() {
	closeModal('match-modal');
	// Redirect to messages or open messaging interface
	window.location.href = '/messages/';
}

// Initialize the swipe interface when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
	new SwipeInterface();
});
</script>