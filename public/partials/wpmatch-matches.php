<?php
/**
 * WPMatch Matches Discovery & Browsing Interface Template
 *
 * This template provides a comprehensive match discovery and browsing system
 * with advanced filtering, grid/list views, and interactive match cards.
 *
 * @package WPMatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_user_id = get_current_user_id();
if ( ! $current_user_id ) {
	wp_safe_redirect( wp_login_url() );
	exit;
}

// Get user preferences for filtering.
$user_preferences = $this->get_user_preferences( $current_user_id );
?>

<div id="wpmatch-matches-container" class="wpmatch-matches-interface">
	<div class="matches-layout">
		<!-- Filters Sidebar -->
		<div class="filters-sidebar">
			<div class="filters-header">
				<h3><?php esc_html_e( 'Filter Matches', 'wpmatch' ); ?></h3>
				<button class="reset-filters" id="reset-filters">
					<?php esc_html_e( 'Reset', 'wpmatch' ); ?>
				</button>
			</div>

			<form id="matches-filter-form" class="filters-form">
				<div class="filter-section">
					<h4><?php esc_html_e( 'Match Status', 'wpmatch' ); ?></h4>
					<div class="filter-options">
						<label class="filter-checkbox">
							<input type="checkbox" name="status[]" value="new" checked>
							<span class="checkbox-label"><?php esc_html_e( 'New Matches', 'wpmatch' ); ?></span>
							<span class="filter-count">12</span>
						</label>
						<label class="filter-checkbox">
							<input type="checkbox" name="status[]" value="mutual" checked>
							<span class="checkbox-label"><?php esc_html_e( 'Mutual Likes', 'wpmatch' ); ?></span>
							<span class="filter-count">8</span>
						</label>
						<label class="filter-checkbox">
							<input type="checkbox" name="status[]" value="liked_me">
							<span class="checkbox-label"><?php esc_html_e( 'Liked Me', 'wpmatch' ); ?></span>
							<span class="filter-count">15</span>
						</label>
						<label class="filter-checkbox">
							<input type="checkbox" name="status[]" value="i_liked">
							<span class="checkbox-label"><?php esc_html_e( 'I Liked', 'wpmatch' ); ?></span>
							<span class="filter-count">23</span>
						</label>
					</div>
				</div>

				<div class="filter-section">
					<h4><?php esc_html_e( 'Age Range', 'wpmatch' ); ?></h4>
					<div class="range-slider-container">
						<div class="range-values">
							<span id="age-min-value">18</span> - <span id="age-max-value">99</span>
						</div>
						<div class="range-slider">
							<input type="range" id="age-min" name="age_min" min="18" max="99" value="<?php echo esc_attr( $user_preferences['min_age'] ?? 18 ); ?>" class="range-input">
							<input type="range" id="age-max" name="age_max" min="18" max="99" value="<?php echo esc_attr( $user_preferences['max_age'] ?? 99 ); ?>" class="range-input">
							<div class="slider-track"></div>
						</div>
					</div>
				</div>

				<div class="filter-section">
					<h4><?php esc_html_e( 'Distance', 'wpmatch' ); ?></h4>
					<div class="distance-slider-container">
						<div class="distance-value">
							<span id="distance-value">25</span> <?php esc_html_e( 'miles', 'wpmatch' ); ?>
						</div>
						<input type="range" id="distance" name="distance" min="1" max="100" value="<?php echo esc_attr( $user_preferences['max_distance'] ?? 25 ); ?>" class="distance-input">
					</div>
				</div>

				<div class="filter-section">
					<h4><?php esc_html_e( 'Interests', 'wpmatch' ); ?></h4>
					<div class="filter-tags">
						<button type="button" class="filter-tag" data-interest="travel">
							<?php esc_html_e( 'Travel', 'wpmatch' ); ?>
						</button>
						<button type="button" class="filter-tag" data-interest="music">
							<?php esc_html_e( 'Music', 'wpmatch' ); ?>
						</button>
						<button type="button" class="filter-tag" data-interest="sports">
							<?php esc_html_e( 'Sports', 'wpmatch' ); ?>
						</button>
						<button type="button" class="filter-tag" data-interest="food">
							<?php esc_html_e( 'Food', 'wpmatch' ); ?>
						</button>
						<button type="button" class="filter-tag" data-interest="art">
							<?php esc_html_e( 'Art', 'wpmatch' ); ?>
						</button>
						<button type="button" class="filter-tag" data-interest="tech">
							<?php esc_html_e( 'Technology', 'wpmatch' ); ?>
						</button>
						<button type="button" class="filter-tag" data-interest="nature">
							<?php esc_html_e( 'Nature', 'wpmatch' ); ?>
						</button>
						<button type="button" class="filter-tag" data-interest="fitness">
							<?php esc_html_e( 'Fitness', 'wpmatch' ); ?>
						</button>
					</div>
				</div>

				<div class="filter-section">
					<h4><?php esc_html_e( 'Looking For', 'wpmatch' ); ?></h4>
					<div class="filter-options">
						<label class="filter-radio">
							<input type="radio" name="looking_for" value="all" checked>
							<span class="radio-label"><?php esc_html_e( 'All', 'wpmatch' ); ?></span>
						</label>
						<label class="filter-radio">
							<input type="radio" name="looking_for" value="relationship">
							<span class="radio-label"><?php esc_html_e( 'Relationship', 'wpmatch' ); ?></span>
						</label>
						<label class="filter-radio">
							<input type="radio" name="looking_for" value="dating">
							<span class="radio-label"><?php esc_html_e( 'Dating', 'wpmatch' ); ?></span>
						</label>
						<label class="filter-radio">
							<input type="radio" name="looking_for" value="friends">
							<span class="radio-label"><?php esc_html_e( 'Friends', 'wpmatch' ); ?></span>
						</label>
					</div>
				</div>

				<div class="filter-section">
					<h4><?php esc_html_e( 'Online Status', 'wpmatch' ); ?></h4>
					<label class="toggle-switch">
						<input type="checkbox" name="online_only" id="online-only">
						<span class="toggle-slider"></span>
						<span class="toggle-label"><?php esc_html_e( 'Online now', 'wpmatch' ); ?></span>
					</label>
				</div>

				<div class="filter-section">
					<h4><?php esc_html_e( 'Verified Profiles', 'wpmatch' ); ?></h4>
					<label class="toggle-switch">
						<input type="checkbox" name="verified_only" id="verified-only">
						<span class="toggle-slider"></span>
						<span class="toggle-label"><?php esc_html_e( 'Verified only', 'wpmatch' ); ?></span>
					</label>
				</div>

				<div class="filter-actions">
					<button type="submit" class="btn btn-primary btn-block">
						<?php esc_html_e( 'Apply Filters', 'wpmatch' ); ?>
					</button>
				</div>

				<?php wp_nonce_field( 'wpmatch_filter_matches', 'wpmatch_filter_nonce' ); ?>
			</form>
		</div>

		<!-- Main Content Area -->
		<div class="matches-content">
			<!-- Content Header -->
			<div class="content-header">
				<div class="header-left">
					<h2><?php esc_html_e( 'Your Matches', 'wpmatch' ); ?></h2>
					<p class="matches-count">
						<?php /* translators: %d: number of matches */ ?>
						<?php printf( esc_html__( 'Showing %d matches', 'wpmatch' ), 24 ); ?>
					</p>
				</div>
				<div class="header-right">
					<div class="sort-options">
						<label for="sort-by"><?php esc_html_e( 'Sort by:', 'wpmatch' ); ?></label>
						<select id="sort-by" name="sort_by">
							<option value="newest"><?php esc_html_e( 'Newest First', 'wpmatch' ); ?></option>
							<option value="compatibility"><?php esc_html_e( 'Best Match', 'wpmatch' ); ?></option>
							<option value="distance"><?php esc_html_e( 'Distance', 'wpmatch' ); ?></option>
							<option value="last_active"><?php esc_html_e( 'Recently Active', 'wpmatch' ); ?></option>
							<option value="popularity"><?php esc_html_e( 'Most Popular', 'wpmatch' ); ?></option>
						</select>
					</div>
					<div class="view-toggles">
						<button class="view-toggle active" data-view="grid" title="<?php esc_attr_e( 'Grid View', 'wpmatch' ); ?>">
							<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
								<rect x="2" y="2" width="7" height="7"/>
								<rect x="11" y="2" width="7" height="7"/>
								<rect x="2" y="11" width="7" height="7"/>
								<rect x="11" y="11" width="7" height="7"/>
							</svg>
						</button>
						<button class="view-toggle" data-view="list" title="<?php esc_attr_e( 'List View', 'wpmatch' ); ?>">
							<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
								<rect x="2" y="3" width="16" height="2"/>
								<rect x="2" y="9" width="16" height="2"/>
								<rect x="2" y="15" width="16" height="2"/>
							</svg>
						</button>
					</div>
				</div>
			</div>

			<!-- Matches Grid/List -->
			<div id="matches-container" class="matches-grid view-grid">
				<div class="loading-matches">
					<div class="loading-spinner"></div>
					<p><?php esc_html_e( 'Loading your matches...', 'wpmatch' ); ?></p>
				</div>
			</div>

			<!-- Pagination -->
			<div class="matches-pagination" id="matches-pagination" style="display: none;">
				<button class="pagination-btn" id="prev-page" disabled>
					<span>←</span> <?php esc_html_e( 'Previous', 'wpmatch' ); ?>
				</button>
				<div class="pagination-numbers" id="pagination-numbers">
					<!-- Page numbers will be generated here -->
				</div>
				<button class="pagination-btn" id="next-page">
					<?php esc_html_e( 'Next', 'wpmatch' ); ?> <span>→</span>
				</button>
			</div>
		</div>
	</div>

	<!-- Match Details Modal -->
	<div id="match-modal" class="match-modal" style="display: none;">
		<div class="modal-overlay"></div>
		<div class="modal-content">
			<button class="modal-close" id="close-modal">×</button>
			<div class="modal-body" id="modal-body">
				<!-- Match details will be loaded here -->
			</div>
		</div>
	</div>
</div>

<style>
.wpmatch-matches-interface {
	max-width: 1400px;
	margin: 0 auto;
	padding: 20px;
	font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	min-height: 100vh;
	box-sizing: border-box;
}

.matches-layout {
	display: grid;
	grid-template-columns: 300px 1fr;
	gap: 30px;
}

/* Filters Sidebar */
.filters-sidebar {
	background: white;
	border-radius: 20px;
	padding: 30px;
	box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
	height: fit-content;
	position: sticky;
	top: 20px;
}

.filters-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 30px;
}

.filters-header h3 {
	margin: 0;
	font-size: 20px;
	font-weight: 600;
	color: #1a202c;
}

.reset-filters {
	background: none;
	border: none;
	color: #667eea;
	font-size: 14px;
	font-weight: 500;
	cursor: pointer;
	padding: 5px 10px;
	border-radius: 6px;
	transition: all 0.2s ease;
}

.reset-filters:hover {
	background: #f7fafc;
}

.filter-section {
	margin-bottom: 30px;
	padding-bottom: 30px;
	border-bottom: 1px solid #e2e8f0;
}

.filter-section:last-child {
	border-bottom: none;
	margin-bottom: 0;
	padding-bottom: 0;
}

.filter-section h4 {
	margin: 0 0 15px 0;
	font-size: 14px;
	font-weight: 600;
	color: #4a5568;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}

.filter-options {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.filter-checkbox,
.filter-radio {
	display: flex;
	align-items: center;
	cursor: pointer;
	padding: 8px 10px;
	border-radius: 8px;
	transition: all 0.2s ease;
}

.filter-checkbox:hover,
.filter-radio:hover {
	background: #f7fafc;
}

.filter-checkbox input[type="checkbox"],
.filter-radio input[type="radio"] {
	margin-right: 10px;
	width: 18px;
	height: 18px;
	cursor: pointer;
}

.checkbox-label,
.radio-label {
	flex: 1;
	font-size: 14px;
	color: #2d3748;
}

.filter-count {
	background: #edf2f7;
	color: #4a5568;
	font-size: 12px;
	font-weight: 600;
	padding: 2px 8px;
	border-radius: 12px;
}

/* Range Sliders */
.range-slider-container,
.distance-slider-container {
	padding: 10px 0;
}

.range-values,
.distance-value {
	display: flex;
	justify-content: center;
	margin-bottom: 15px;
	font-size: 16px;
	font-weight: 600;
	color: #2d3748;
}

.range-slider {
	position: relative;
	height: 6px;
}

.range-input,
.distance-input {
	position: absolute;
	width: 100%;
	height: 6px;
	background: transparent;
	pointer-events: none;
	-webkit-appearance: none;
	appearance: none;
}

.range-input::-webkit-slider-thumb,
.distance-input::-webkit-slider-thumb {
	pointer-events: all;
	width: 20px;
	height: 20px;
	border-radius: 50%;
	background: #667eea;
	border: 2px solid white;
	box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
	cursor: pointer;
	-webkit-appearance: none;
	appearance: none;
}

.slider-track {
	position: absolute;
	height: 6px;
	background: #e2e8f0;
	border-radius: 3px;
	width: 100%;
}

.distance-input {
	position: relative;
	width: 100%;
}

/* Filter Tags */
.filter-tags {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
}

.filter-tag {
	padding: 6px 12px;
	border: 1px solid #e2e8f0;
	border-radius: 20px;
	background: white;
	font-size: 13px;
	color: #4a5568;
	cursor: pointer;
	transition: all 0.2s ease;
}

.filter-tag:hover {
	border-color: #667eea;
	color: #667eea;
}

.filter-tag.active {
	background: #667eea;
	color: white;
	border-color: #667eea;
}

/* Toggle Switch */
.toggle-switch {
	display: flex;
	align-items: center;
	cursor: pointer;
}

.toggle-switch input[type="checkbox"] {
	display: none;
}

.toggle-slider {
	position: relative;
	width: 44px;
	height: 24px;
	background: #e2e8f0;
	border-radius: 12px;
	margin-right: 10px;
	transition: all 0.3s ease;
}

.toggle-slider::before {
	content: '';
	position: absolute;
	width: 18px;
	height: 18px;
	border-radius: 50%;
	background: white;
	top: 3px;
	left: 3px;
	transition: all 0.3s ease;
	box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.toggle-switch input:checked + .toggle-slider {
	background: #667eea;
}

.toggle-switch input:checked + .toggle-slider::before {
	transform: translateX(20px);
}

.toggle-label {
	font-size: 14px;
	color: #4a5568;
}

/* Filter Actions */
.filter-actions {
	margin-top: 20px;
	padding-top: 20px;
	border-top: 1px solid #e2e8f0;
}

.btn-block {
	width: 100%;
}

/* Main Content Area */
.matches-content {
	background: white;
	border-radius: 20px;
	padding: 30px;
	box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.content-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 30px;
	padding-bottom: 20px;
	border-bottom: 1px solid #e2e8f0;
}

.header-left h2 {
	margin: 0 0 5px 0;
	font-size: 28px;
	font-weight: 700;
	color: #1a202c;
}

.matches-count {
	margin: 0;
	font-size: 14px;
	color: #718096;
}

.header-right {
	display: flex;
	align-items: center;
	gap: 20px;
}

.sort-options {
	display: flex;
	align-items: center;
	gap: 10px;
}

.sort-options label {
	font-size: 14px;
	color: #4a5568;
}

.sort-options select {
	padding: 8px 12px;
	border: 1px solid #e2e8f0;
	border-radius: 8px;
	font-size: 14px;
	background: white;
	cursor: pointer;
}

.view-toggles {
	display: flex;
	gap: 5px;
	background: #f7fafc;
	padding: 4px;
	border-radius: 8px;
}

.view-toggle {
	padding: 8px;
	border: none;
	background: transparent;
	color: #718096;
	cursor: pointer;
	border-radius: 6px;
	transition: all 0.2s ease;
	display: flex;
	align-items: center;
	justify-content: center;
}

.view-toggle:hover {
	background: white;
}

.view-toggle.active {
	background: white;
	color: #667eea;
	box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* Matches Grid */
.matches-grid {
	min-height: 400px;
}

.matches-grid.view-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
	gap: 20px;
}

.matches-grid.view-list {
	display: flex;
	flex-direction: column;
	gap: 15px;
}

.loading-matches {
	grid-column: 1 / -1;
	display: flex;
	flex-direction: column;
	align-items: center;
	padding: 60px 20px;
	color: #718096;
}

.loading-spinner {
	width: 30px;
	height: 30px;
	border: 3px solid #e2e8f0;
	border-top: 3px solid #667eea;
	border-radius: 50%;
	animation: spin 1s linear infinite;
	margin-bottom: 15px;
}

@keyframes spin {
	0% { transform: rotate(0deg); }
	100% { transform: rotate(360deg); }
}

/* Match Cards - Grid View */
.match-card {
	background: white;
	border-radius: 16px;
	overflow: hidden;
	box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
	transition: all 0.3s ease;
	cursor: pointer;
	position: relative;
}

.match-card:hover {
	transform: translateY(-4px);
	box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
}

.match-card-image {
	position: relative;
	height: 280px;
	background-size: cover;
	background-position: center;
}

.match-status-badge {
	position: absolute;
	top: 10px;
	right: 10px;
	background: rgba(255, 255, 255, 0.9);
	padding: 5px 10px;
	border-radius: 20px;
	font-size: 12px;
	font-weight: 600;
	display: flex;
	align-items: center;
	gap: 5px;
}

.match-status-badge.mutual {
	background: #48bb78;
	color: white;
}

.match-status-badge.new {
	background: #667eea;
	color: white;
}

.online-indicator {
	position: absolute;
	bottom: 10px;
	right: 10px;
	width: 12px;
	height: 12px;
	border-radius: 50%;
	background: #48bb78;
	border: 2px solid white;
}

.match-card-info {
	padding: 15px;
}

.match-name {
	font-size: 18px;
	font-weight: 600;
	color: #1a202c;
	margin: 0 0 5px 0;
}

.match-details {
	display: flex;
	align-items: center;
	gap: 10px;
	font-size: 14px;
	color: #718096;
	margin-bottom: 10px;
}

.match-bio {
	font-size: 14px;
	color: #4a5568;
	line-height: 1.4;
	margin-bottom: 10px;
	display: -webkit-box;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
	overflow: hidden;
}

.match-interests {
	display: flex;
	flex-wrap: wrap;
	gap: 5px;
	margin-bottom: 10px;
}

.interest-tag {
	padding: 3px 8px;
	background: #edf2f7;
	color: #4a5568;
	font-size: 11px;
	border-radius: 10px;
}

.match-compatibility {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding-top: 10px;
	border-top: 1px solid #e2e8f0;
}

.compatibility-score {
	display: flex;
	align-items: center;
	gap: 5px;
}

.score-bar {
	width: 60px;
	height: 4px;
	background: #e2e8f0;
	border-radius: 2px;
	overflow: hidden;
}

.score-fill {
	height: 100%;
	background: linear-gradient(90deg, #667eea, #764ba2);
	border-radius: 2px;
}

.score-text {
	font-size: 12px;
	font-weight: 600;
	color: #667eea;
}

.match-actions {
	display: flex;
	gap: 8px;
}

.action-btn {
	width: 32px;
	height: 32px;
	border: 1px solid #e2e8f0;
	border-radius: 50%;
	background: white;
	color: #4a5568;
	cursor: pointer;
	display: flex;
	align-items: center;
	justify-content: center;
	transition: all 0.2s ease;
	font-size: 14px;
}

.action-btn:hover {
	background: #f7fafc;
	border-color: #cbd5e0;
}

.action-btn.like:hover {
	background: #fff5f5;
	color: #e53e3e;
	border-color: #e53e3e;
}

.action-btn.message:hover {
	background: #eef2ff;
	color: #667eea;
	border-color: #667eea;
}

/* Match Cards - List View */
.matches-grid.view-list .match-card {
	display: flex;
	height: 160px;
}

.matches-grid.view-list .match-card-image {
	width: 160px;
	height: 160px;
	flex-shrink: 0;
}

.matches-grid.view-list .match-card-info {
	flex: 1;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	padding: 20px;
}

.matches-grid.view-list .match-bio {
	-webkit-line-clamp: 3;
}

/* Pagination */
.matches-pagination {
	display: flex;
	justify-content: center;
	align-items: center;
	gap: 10px;
	margin-top: 40px;
	padding-top: 30px;
	border-top: 1px solid #e2e8f0;
}

.pagination-btn {
	padding: 8px 16px;
	border: 1px solid #e2e8f0;
	background: white;
	border-radius: 8px;
	font-size: 14px;
	color: #4a5568;
	cursor: pointer;
	transition: all 0.2s ease;
	display: flex;
	align-items: center;
	gap: 5px;
}

.pagination-btn:hover:not(:disabled) {
	background: #f7fafc;
	border-color: #667eea;
	color: #667eea;
}

.pagination-btn:disabled {
	opacity: 0.5;
	cursor: not-allowed;
}

.pagination-numbers {
	display: flex;
	gap: 5px;
}

.page-number {
	width: 36px;
	height: 36px;
	border: 1px solid #e2e8f0;
	background: white;
	border-radius: 8px;
	font-size: 14px;
	color: #4a5568;
	cursor: pointer;
	display: flex;
	align-items: center;
	justify-content: center;
	transition: all 0.2s ease;
}

.page-number:hover {
	background: #f7fafc;
	border-color: #667eea;
	color: #667eea;
}

.page-number.active {
	background: #667eea;
	color: white;
	border-color: #667eea;
}

/* Modal */
.match-modal {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	z-index: 1000;
}

.modal-overlay {
	position: absolute;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background: rgba(0, 0, 0, 0.5);
	animation: fadeIn 0.3s ease;
}

.modal-content {
	position: absolute;
	top: 50%;
	left: 50%;
	transform: translate(-50%, -50%);
	background: white;
	border-radius: 20px;
	max-width: 800px;
	width: 90%;
	max-height: 90vh;
	overflow: auto;
	box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
	animation: slideUp 0.3s ease;
}

@keyframes fadeIn {
	from { opacity: 0; }
	to { opacity: 1; }
}

@keyframes slideUp {
	from {
		opacity: 0;
		transform: translate(-50%, -45%);
	}
	to {
		opacity: 1;
		transform: translate(-50%, -50%);
	}
}

.modal-close {
	position: absolute;
	top: 20px;
	right: 20px;
	width: 40px;
	height: 40px;
	border: none;
	background: #f7fafc;
	border-radius: 50%;
	font-size: 24px;
	color: #4a5568;
	cursor: pointer;
	z-index: 1;
	transition: all 0.2s ease;
}

.modal-close:hover {
	background: #e2e8f0;
	color: #1a202c;
}

.modal-body {
	padding: 40px;
}

/* Buttons */
.btn {
	padding: 12px 24px;
	border: none;
	border-radius: 8px;
	font-size: 14px;
	font-weight: 500;
	cursor: pointer;
	transition: all 0.2s ease;
	display: inline-flex;
	align-items: center;
	justify-content: center;
}

.btn-primary {
	background: #667eea;
	color: white;
}

.btn-primary:hover {
	background: #5a67d8;
	transform: translateY(-1px);
}

/* Responsive Design */
@media (max-width: 1024px) {
	.matches-layout {
		grid-template-columns: 250px 1fr;
		gap: 20px;
	}

	.filters-sidebar {
		padding: 20px;
	}

	.matches-content {
		padding: 25px;
	}

	.matches-grid.view-grid {
		grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
	}
}

@media (max-width: 768px) {
	.wpmatch-matches-interface {
		padding: 10px;
	}

	.matches-layout {
		grid-template-columns: 1fr;
		gap: 20px;
	}

	.filters-sidebar {
		position: static;
		margin-bottom: 20px;
	}

	.content-header {
		flex-direction: column;
		align-items: flex-start;
		gap: 15px;
	}

	.header-right {
		width: 100%;
		justify-content: space-between;
	}

	.matches-grid.view-grid {
		grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
		gap: 15px;
	}

	.match-card-image {
		height: 200px;
	}

	.matches-grid.view-list .match-card {
		flex-direction: column;
		height: auto;
	}

	.matches-grid.view-list .match-card-image {
		width: 100%;
		height: 200px;
	}

	.modal-content {
		width: 95%;
		max-height: 95vh;
	}

	.modal-body {
		padding: 20px;
	}
}

@media (max-width: 480px) {
	.filters-sidebar {
		padding: 15px;
	}

	.matches-content {
		padding: 15px;
	}

	.header-left h2 {
		font-size: 22px;
	}

	.matches-grid.view-grid {
		grid-template-columns: 1fr;
	}

	.pagination-numbers {
		display: none;
	}

	.modal-close {
		top: 10px;
		right: 10px;
		width: 32px;
		height: 32px;
		font-size: 20px;
	}
}
</style>

<script>
class MatchesInterface {
	constructor() {
		this.currentPage = 1;
		this.itemsPerPage = 12;
		this.totalMatches = 0;
		this.currentView = 'grid';
		this.filters = {
			status: ['new', 'mutual'],
			age_min: 18,
			age_max: 99,
			distance: 25,
			interests: [],
			looking_for: 'all',
			online_only: false,
			verified_only: false
		};
		this.matches = [];
		this.sortBy = 'newest';

		this.init();
	}

	init() {
		this.bindEvents();
		this.loadMatches();
		this.initRangeSliders();
	}

	bindEvents() {
		// Filter form submission
		document.getElementById('matches-filter-form').addEventListener('submit', (e) => {
			e.preventDefault();
			this.applyFilters();
		});

		// Reset filters
		document.getElementById('reset-filters').addEventListener('click', () => {
			this.resetFilters();
		});

		// View toggles
		document.querySelectorAll('.view-toggle').forEach(btn => {
			btn.addEventListener('click', () => {
				this.switchView(btn.dataset.view);
			});
		});

		// Sort options
		document.getElementById('sort-by').addEventListener('change', (e) => {
			this.sortBy = e.target.value;
			this.loadMatches();
		});

		// Interest tags
		document.querySelectorAll('.filter-tag').forEach(tag => {
			tag.addEventListener('click', () => {
				tag.classList.toggle('active');
				const interest = tag.dataset.interest;
				if (tag.classList.contains('active')) {
					this.filters.interests.push(interest);
				} else {
					const index = this.filters.interests.indexOf(interest);
					if (index > -1) {
						this.filters.interests.splice(index, 1);
					}
				}
			});
		});

		// Pagination
		document.getElementById('prev-page').addEventListener('click', () => {
			if (this.currentPage > 1) {
				this.currentPage--;
				this.loadMatches();
			}
		});

		document.getElementById('next-page').addEventListener('click', () => {
			const totalPages = Math.ceil(this.totalMatches / this.itemsPerPage);
			if (this.currentPage < totalPages) {
				this.currentPage++;
				this.loadMatches();
			}
		});

		// Modal close
		document.getElementById('close-modal').addEventListener('click', () => {
			this.closeModal();
		});

		document.querySelector('.modal-overlay').addEventListener('click', () => {
			this.closeModal();
		});
	}

	initRangeSliders() {
		const ageMin = document.getElementById('age-min');
		const ageMax = document.getElementById('age-max');
		const ageMinValue = document.getElementById('age-min-value');
		const ageMaxValue = document.getElementById('age-max-value');

		const updateAgeRange = () => {
			const minVal = parseInt(ageMin.value);
			const maxVal = parseInt(ageMax.value);

			if (minVal > maxVal) {
				ageMin.value = maxVal;
			}
			if (maxVal < minVal) {
				ageMax.value = minVal;
			}

			ageMinValue.textContent = ageMin.value;
			ageMaxValue.textContent = ageMax.value;

			this.filters.age_min = parseInt(ageMin.value);
			this.filters.age_max = parseInt(ageMax.value);
		};

		ageMin.addEventListener('input', updateAgeRange);
		ageMax.addEventListener('input', updateAgeRange);

		// Distance slider
		const distance = document.getElementById('distance');
		const distanceValue = document.getElementById('distance-value');

		distance.addEventListener('input', () => {
			distanceValue.textContent = distance.value;
			this.filters.distance = parseInt(distance.value);
		});
	}

	async loadMatches() {
		const container = document.getElementById('matches-container');
		container.innerHTML = `
			<div class="loading-matches">
				<div class="loading-spinner"></div>
				<p>Loading your matches...</p>
			</div>
		`;

		try {
			// For demo, create mock data
			await this.loadMockMatches();
			this.renderMatches();
			this.renderPagination();
		} catch (error) {
			console.error('Error loading matches:', error);
			container.innerHTML = '<p>Error loading matches</p>';
		}
	}

	async loadMockMatches() {
		// Generate mock match data for demo
		const names = ['Sarah Johnson', 'Emma Davis', 'Jessica Wilson', 'Ashley Brown', 'Amanda Taylor', 'Michelle Lee'];
		const locations = ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'Philadelphia'];
		const occupations = ['Designer', 'Engineer', 'Teacher', 'Artist', 'Doctor', 'Writer'];
		const interests = ['Travel', 'Music', 'Sports', 'Food', 'Art', 'Technology', 'Nature', 'Fitness'];

		this.totalMatches = 24;
		this.matches = [];

		for (let i = 0; i < this.itemsPerPage; i++) {
			this.matches.push({
				id: i + 1,
				name: names[i % names.length],
				age: 25 + Math.floor(Math.random() * 15),
				distance: Math.floor(Math.random() * 50) + 1,
				location: locations[i % locations.length],
				occupation: occupations[i % occupations.length],
				bio: 'I love exploring new places and meeting interesting people. Looking for someone who shares my passion for adventure.',
				photo: `/wp-content/plugins/wpmatch/public/images/default-avatar.png`,
				is_online: Math.random() > 0.5,
				is_mutual: Math.random() > 0.6,
				is_new: Math.random() > 0.7,
				compatibility: Math.floor(Math.random() * 30) + 70,
				interests: interests.slice(0, Math.floor(Math.random() * 4) + 2),
				last_seen: '2 hours ago'
			});
		}
	}

	renderMatches() {
		const container = document.getElementById('matches-container');

		if (this.matches.length === 0) {
			container.innerHTML = `
				<div style="grid-column: 1 / -1; text-align: center; padding: 60px 20px; color: #718096;">
					<div style="font-size: 48px; margin-bottom: 16px;">💔</div>
					<h3>No matches found</h3>
					<p>Try adjusting your filters to see more matches.</p>
				</div>
			`;
			return;
		}

		const matchesHTML = this.matches.map(match => {
			if (this.currentView === 'grid') {
				return this.renderMatchCardGrid(match);
			} else {
				return this.renderMatchCardList(match);
			}
		}).join('');

		container.innerHTML = matchesHTML;

		// Bind card click events
		container.querySelectorAll('.match-card').forEach(card => {
			card.addEventListener('click', (e) => {
				if (!e.target.closest('.match-actions')) {
					const matchId = card.dataset.matchId;
					this.openMatchDetails(matchId);
				}
			});
		});

		// Bind action button events
		container.querySelectorAll('.action-btn').forEach(btn => {
			btn.addEventListener('click', (e) => {
				e.stopPropagation();
				const action = btn.dataset.action;
				const matchId = btn.closest('.match-card').dataset.matchId;
				this.handleMatchAction(action, matchId);
			});
		});
	}

	renderMatchCardGrid(match) {
		return `
			<div class="match-card" data-match-id="${match.id}">
				<div class="match-card-image" style="background-image: url('${match.photo}');">
					${match.is_mutual ? '<div class="match-status-badge mutual">💘 Mutual</div>' : ''}
					${match.is_new ? '<div class="match-status-badge new">✨ New</div>' : ''}
					${match.is_online ? '<div class="online-indicator"></div>' : ''}
				</div>
				<div class="match-card-info">
					<h3 class="match-name">${match.name}, ${match.age}</h3>
					<div class="match-details">
						<span>📍 ${match.distance} miles</span>
						<span>💼 ${match.occupation}</span>
					</div>
					<p class="match-bio">${match.bio}</p>
					<div class="match-interests">
						${match.interests.slice(0, 3).map(interest =>
							`<span class="interest-tag">${interest}</span>`
						).join('')}
					</div>
					<div class="match-compatibility">
						<div class="compatibility-score">
							<div class="score-bar">
								<div class="score-fill" style="width: ${match.compatibility}%"></div>
							</div>
							<span class="score-text">${match.compatibility}% Match</span>
						</div>
						<div class="match-actions">
							<button class="action-btn like" data-action="like" title="Like">❤️</button>
							<button class="action-btn message" data-action="message" title="Message">💬</button>
						</div>
					</div>
				</div>
			</div>
		`;
	}

	renderMatchCardList(match) {
		return `
			<div class="match-card" data-match-id="${match.id}">
				<div class="match-card-image" style="background-image: url('${match.photo}');">
					${match.is_online ? '<div class="online-indicator"></div>' : ''}
				</div>
				<div class="match-card-info">
					<div>
						<h3 class="match-name">${match.name}, ${match.age}</h3>
						<div class="match-details">
							<span>📍 ${match.distance} miles</span>
							<span>💼 ${match.occupation}</span>
							${match.is_mutual ? '<span>💘 Mutual Match</span>' : ''}
						</div>
						<p class="match-bio">${match.bio}</p>
					</div>
					<div class="match-compatibility">
						<div class="compatibility-score">
							<div class="score-bar">
								<div class="score-fill" style="width: ${match.compatibility}%"></div>
							</div>
							<span class="score-text">${match.compatibility}% Match</span>
						</div>
						<div class="match-actions">
							<button class="action-btn like" data-action="like" title="Like">❤️</button>
							<button class="action-btn message" data-action="message" title="Message">💬</button>
						</div>
					</div>
				</div>
			</div>
		`;
	}

	renderPagination() {
		const totalPages = Math.ceil(this.totalMatches / this.itemsPerPage);

		if (totalPages <= 1) {
			document.getElementById('matches-pagination').style.display = 'none';
			return;
		}

		document.getElementById('matches-pagination').style.display = 'flex';

		// Update prev/next buttons
		document.getElementById('prev-page').disabled = this.currentPage === 1;
		document.getElementById('next-page').disabled = this.currentPage === totalPages;

		// Generate page numbers
		const paginationNumbers = document.getElementById('pagination-numbers');
		let pagesHTML = '';

		const maxVisible = 5;
		let startPage = Math.max(1, this.currentPage - Math.floor(maxVisible / 2));
		let endPage = Math.min(totalPages, startPage + maxVisible - 1);

		if (endPage - startPage < maxVisible - 1) {
			startPage = Math.max(1, endPage - maxVisible + 1);
		}

		for (let i = startPage; i <= endPage; i++) {
			pagesHTML += `
				<button class="page-number ${i === this.currentPage ? 'active' : ''}" data-page="${i}">
					${i}
				</button>
			`;
		}

		paginationNumbers.innerHTML = pagesHTML;

		// Bind page number clicks
		paginationNumbers.querySelectorAll('.page-number').forEach(btn => {
			btn.addEventListener('click', () => {
				this.currentPage = parseInt(btn.dataset.page);
				this.loadMatches();
			});
		});

		// Update matches count
		const start = (this.currentPage - 1) * this.itemsPerPage + 1;
		const end = Math.min(this.currentPage * this.itemsPerPage, this.totalMatches);
		document.querySelector('.matches-count').textContent = `Showing ${start}-${end} of ${this.totalMatches} matches`;
	}

	switchView(view) {
		this.currentView = view;

		// Update toggle buttons
		document.querySelectorAll('.view-toggle').forEach(btn => {
			btn.classList.toggle('active', btn.dataset.view === view);
		});

		// Update container class
		const container = document.getElementById('matches-container');
		container.className = `matches-grid view-${view}`;

		// Re-render matches
		this.renderMatches();
	}

	applyFilters() {
		// Collect filter values from form
		const form = document.getElementById('matches-filter-form');
		const formData = new FormData(form);

		// Update status filters
		this.filters.status = Array.from(formData.getAll('status[]'));

		// Update other filters
		this.filters.looking_for = formData.get('looking_for');
		this.filters.online_only = formData.get('online_only') === 'on';
		this.filters.verified_only = formData.get('verified_only') === 'on';

		// Reset to first page and reload
		this.currentPage = 1;
		this.loadMatches();
	}

	resetFilters() {
		// Reset form
		document.getElementById('matches-filter-form').reset();

		// Reset filter tags
		document.querySelectorAll('.filter-tag.active').forEach(tag => {
			tag.classList.remove('active');
		});

		// Reset filter object
		this.filters = {
			status: ['new', 'mutual'],
			age_min: 18,
			age_max: 99,
			distance: 25,
			interests: [],
			looking_for: 'all',
			online_only: false,
			verified_only: false
		};

		// Update UI
		document.getElementById('age-min-value').textContent = '18';
		document.getElementById('age-max-value').textContent = '99';
		document.getElementById('distance-value').textContent = '25';

		// Reload matches
		this.currentPage = 1;
		this.loadMatches();
	}

	async openMatchDetails(matchId) {
		const modal = document.getElementById('match-modal');
		const modalBody = document.getElementById('modal-body');

		modalBody.innerHTML = '<p>Loading...</p>';
		modal.style.display = 'block';

		// Find match in our data
		const match = this.matches.find(m => m.id == matchId);
		if (match) {
			this.renderMatchDetails(match);
		} else {
			modalBody.innerHTML = '<p>Match not found</p>';
		}
	}

	renderMatchDetails(match) {
		const modalBody = document.getElementById('modal-body');

		modalBody.innerHTML = `
			<div class="match-profile">
				<div class="profile-header">
					<img src="${match.photo}" alt="${match.name}" class="profile-photo">
					<div class="profile-info">
						<h2>${match.name}, ${match.age}</h2>
						<p class="profile-location">📍 ${match.location} • ${match.distance} miles away</p>
						<p class="profile-status">${match.is_online ? '🟢 Online now' : '⭕ Last seen ' + match.last_seen}</p>
					</div>
				</div>
				<div class="profile-content">
					<section class="profile-section">
						<h3>About</h3>
						<p>${match.bio}</p>
					</section>
					<section class="profile-section">
						<h3>Details</h3>
						<div class="profile-details">
							<div class="detail-item">
								<span class="detail-label">Occupation:</span>
								<span class="detail-value">${match.occupation}</span>
							</div>
							<div class="detail-item">
								<span class="detail-label">Education:</span>
								<span class="detail-value">Bachelor's Degree</span>
							</div>
							<div class="detail-item">
								<span class="detail-label">Looking for:</span>
								<span class="detail-value">Long-term relationship</span>
							</div>
						</div>
					</section>
					<section class="profile-section">
						<h3>Interests</h3>
						<div class="profile-interests">
							${match.interests.map(interest =>
								`<span class="interest-badge">${interest}</span>`
							).join('')}
						</div>
					</section>
				</div>
				<div class="profile-actions">
					<button class="btn btn-primary" onclick="matchesInterface.sendMessage('${match.id}')">Send Message</button>
					<button class="btn btn-secondary" onclick="matchesInterface.likeMatch('${match.id}')">Like Profile</button>
				</div>
			</div>
		`;
	}

	closeModal() {
		document.getElementById('match-modal').style.display = 'none';
	}

	async handleMatchAction(action, matchId) {
		switch (action) {
			case 'like':
				await this.likeMatch(matchId);
				break;
			case 'message':
				await this.sendMessage(matchId);
				break;
		}
	}

	async likeMatch(matchId) {
		this.showNotification('Match liked!', 'success');
		// Update UI to reflect like
		const card = document.querySelector(`[data-match-id="${matchId}"]`);
		if (card) {
			const likeBtn = card.querySelector('.action-btn.like');
			if (likeBtn) {
				likeBtn.style.background = '#fee';
				likeBtn.style.color = '#e53e3e';
			}
		}
	}

	async sendMessage(matchId) {
		// Navigate to messages page with recipient pre-selected
		window.location.href = `/messages?recipient=${matchId}`;
	}

	showNotification(message, type = 'info') {
		const notification = document.createElement('div');
		notification.className = `notification notification-${type}`;
		notification.style.cssText = `
			position: fixed;
			top: 20px;
			right: 20px;
			background: ${type === 'error' ? '#fee' : type === 'success' ? '#f0fff4' : '#eff'};
			color: ${type === 'error' ? '#c53030' : type === 'success' ? '#38a169' : '#2d3748'};
			padding: 15px 20px;
			border-radius: 8px;
			border-left: 4px solid ${type === 'error' ? '#e53e3e' : type === 'success' ? '#48bb78' : '#667eea'};
			box-shadow: 0 4px 12px rgba(0,0,0,0.1);
			z-index: 2000;
			max-width: 300px;
			transform: translateX(100%);
			transition: transform 0.3s ease;
		`;
		notification.textContent = message;

		document.body.appendChild(notification);

		setTimeout(() => {
			notification.style.transform = 'translateX(0)';
		}, 100);

		setTimeout(() => {
			notification.style.transform = 'translateX(100%)';
			setTimeout(() => {
				if (notification.parentNode) {
					notification.parentNode.removeChild(notification);
				}
			}, 300);
		}, 5000);
	}
}

// Additional CSS for modal and profile details
const additionalCSS = `
.match-profile {
	max-width: 600px;
	margin: 0 auto;
}

.profile-header {
	display: flex;
	gap: 30px;
	margin-bottom: 30px;
	padding-bottom: 30px;
	border-bottom: 1px solid #e2e8f0;
}

.profile-photo {
	width: 120px;
	height: 120px;
	border-radius: 50%;
	object-fit: cover;
	border: 3px solid #667eea;
}

.profile-info h2 {
	margin: 0 0 10px 0;
	font-size: 28px;
	color: #1a202c;
}

.profile-location,
.profile-status {
	margin: 5px 0;
	font-size: 14px;
	color: #718096;
}

.profile-section {
	margin-bottom: 30px;
}

.profile-section h3 {
	margin: 0 0 15px 0;
	font-size: 18px;
	font-weight: 600;
	color: #1a202c;
}

.profile-details {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.detail-item {
	display: flex;
	padding: 10px;
	background: #f7fafc;
	border-radius: 8px;
}

.detail-label {
	font-weight: 500;
	color: #4a5568;
	margin-right: 10px;
	min-width: 100px;
}

.detail-value {
	color: #2d3748;
}

.profile-interests {
	display: flex;
	flex-wrap: wrap;
	gap: 10px;
}

.interest-badge {
	padding: 8px 16px;
	background: #edf2ff;
	color: #667eea;
	border-radius: 20px;
	font-size: 14px;
	font-weight: 500;
}

.profile-actions {
	display: flex;
	gap: 15px;
	margin-top: 30px;
	padding-top: 30px;
	border-top: 1px solid #e2e8f0;
}

.btn-secondary {
	background: white;
	color: #667eea;
	border: 2px solid #667eea;
}

.btn-secondary:hover {
	background: #eef2ff;
}

@media (max-width: 768px) {
	.profile-header {
		flex-direction: column;
		align-items: center;
		text-align: center;
	}

	.profile-actions {
		flex-direction: column;
	}
}
`;

// Inject additional CSS
const style = document.createElement('style');
style.textContent = additionalCSS;
document.head.appendChild(style);

// Initialize matches interface when DOM is ready
let matchesInterface;
document.addEventListener('DOMContentLoaded', () => {
	if (typeof wpmatch_ajax !== 'undefined') {
		matchesInterface = new MatchesInterface();
	}
});
</script>