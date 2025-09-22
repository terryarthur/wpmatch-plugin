<?php
/**
 * Location Dashboard Template
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

<div class="wpmatch-location-dashboard">
	<!-- Header -->
	<div class="location-header">
		<h2><?php esc_html_e( 'Location & Nearby Matches', 'wpmatch' ); ?></h2>
		<p class="location-subtitle"><?php esc_html_e( 'Find matches and events near you', 'wpmatch' ); ?></p>
	</div>

	<!-- Location Status -->
	<div class="location-status">
		<span class="status-icon">📍</span>
		<div class="status-text"><?php esc_html_e( 'Location Access Required', 'wpmatch' ); ?></div>
		<div class="status-details"><?php esc_html_e( 'Allow location access to find matches and events near you', 'wpmatch' ); ?></div>

		<div class="location-controls">
			<button class="location-btn primary enable-location-btn">
				<span>📍</span> <?php esc_html_e( 'Enable Location', 'wpmatch' ); ?>
			</button>
		</div>
	</div>

	<!-- Location Settings -->
	<div class="location-settings">
		<div class="settings-header">
			<h3><?php esc_html_e( 'Privacy & Settings', 'wpmatch' ); ?></h3>
			<div class="privacy-indicator">
				<span><?php esc_html_e( 'Privacy Level:', 'wpmatch' ); ?></span>
				<span class="privacy-level approximate"><?php esc_html_e( 'Approximate', 'wpmatch' ); ?></span>
			</div>
		</div>

		<div class="settings-grid">
			<!-- Location Sharing Settings -->
			<div class="setting-group">
				<h4><?php esc_html_e( 'Location Sharing', 'wpmatch' ); ?></h4>

				<div class="setting-option">
					<div class="setting-label">
						<h5><?php esc_html_e( 'Share Location', 'wpmatch' ); ?></h5>
						<p><?php esc_html_e( 'Allow other users to see your location', 'wpmatch' ); ?></p>
					</div>
					<div class="setting-control">
						<div class="toggle-switch" data-setting="share_location">
							<div class="toggle-handle"></div>
						</div>
					</div>
				</div>

				<div class="setting-option">
					<div class="setting-label">
						<h5><?php esc_html_e( 'Auto Update', 'wpmatch' ); ?></h5>
						<p><?php esc_html_e( 'Automatically update location when you move', 'wpmatch' ); ?></p>
					</div>
					<div class="setting-control">
						<div class="toggle-switch" data-setting="auto_update">
							<div class="toggle-handle"></div>
						</div>
					</div>
				</div>

				<div class="setting-option">
					<div class="setting-label">
						<h5><?php esc_html_e( 'Privacy Level', 'wpmatch' ); ?></h5>
						<p><?php esc_html_e( 'How precise your location appears to others', 'wpmatch' ); ?></p>
					</div>
					<div class="setting-control">
						<div class="custom-select">
							<select class="privacy-select">
								<option value="exact"><?php esc_html_e( 'Exact Location', 'wpmatch' ); ?></option>
								<option value="approximate" selected><?php esc_html_e( 'Approximate (±1km)', 'wpmatch' ); ?></option>
								<option value="city"><?php esc_html_e( 'City Only', 'wpmatch' ); ?></option>
								<option value="hidden"><?php esc_html_e( 'Hidden', 'wpmatch' ); ?></option>
							</select>
						</div>
					</div>
				</div>
			</div>

			<!-- Search Settings -->
			<div class="setting-group">
				<h4><?php esc_html_e( 'Search Preferences', 'wpmatch' ); ?></h4>

				<div class="setting-option">
					<div class="setting-label">
						<h5><?php esc_html_e( 'Search Radius', 'wpmatch' ); ?></h5>
						<p><?php esc_html_e( 'How far to search for matches', 'wpmatch' ); ?></p>
					</div>
					<div class="setting-control">
						<input type="range" class="range-slider" data-setting="search_radius" min="1" max="100" value="25">
						<div class="range-value">25 km</div>
					</div>
				</div>

				<div class="setting-option">
					<div class="setting-label">
						<h5><?php esc_html_e( 'Show in Search', 'wpmatch' ); ?></h5>
						<p><?php esc_html_e( 'Appear in other users\' location searches', 'wpmatch' ); ?></p>
					</div>
					<div class="setting-control">
						<div class="toggle-switch" data-setting="show_in_search">
							<div class="toggle-handle"></div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Nearby Users Section -->
	<div class="nearby-users-section">
		<div class="section-header">
			<h3><?php esc_html_e( 'Nearby Users', 'wpmatch' ); ?></h3>
		</div>

		<div class="nearby-controls">
			<div class="distance-filter">
				<label for="distance-range"><?php esc_html_e( 'Distance:', 'wpmatch' ); ?></label>
				<input type="range" id="distance-range" min="1" max="100" value="25">
				<span class="distance-value">25 km</span>
			</div>
			<button class="refresh-btn refresh-nearby-btn">
				<span>🔄</span> <?php esc_html_e( 'Refresh', 'wpmatch' ); ?>
			</button>
		</div>

		<div class="nearby-users-grid">
			<!-- Users will be loaded via AJAX -->
			<div class="location-loading">
				<div class="loading-spinner"></div>
				<span><?php esc_html_e( 'Loading nearby users...', 'wpmatch' ); ?></span>
			</div>
		</div>
	</div>

	<!-- Location Map -->
	<div class="location-map-container">
		<div class="map-header">
			<h3><?php esc_html_e( 'Location Map', 'wpmatch' ); ?></h3>
			<div class="map-controls">
				<button class="map-btn active" data-view="users"><?php esc_html_e( 'Users', 'wpmatch' ); ?></button>
				<button class="map-btn" data-view="events"><?php esc_html_e( 'Events', 'wpmatch' ); ?></button>
				<button class="map-btn" data-view="hybrid"><?php esc_html_e( 'Both', 'wpmatch' ); ?></button>
			</div>
		</div>
		<div class="location-map">
			<?php esc_html_e( 'Interactive map will be displayed here', 'wpmatch' ); ?>
		</div>
	</div>

	<!-- Location Events Section -->
	<div class="location-events-section">
		<div class="section-header">
			<h3><?php esc_html_e( 'Nearby Events', 'wpmatch' ); ?></h3>
		</div>

		<div class="events-filter">
			<button class="event-filter-btn active" data-filter="nearby"><?php esc_html_e( 'Nearby', 'wpmatch' ); ?></button>
			<button class="event-filter-btn" data-filter="today"><?php esc_html_e( 'Today', 'wpmatch' ); ?></button>
			<button class="event-filter-btn" data-filter="this_week"><?php esc_html_e( 'This Week', 'wpmatch' ); ?></button>
			<button class="event-filter-btn" data-filter="speed_dating"><?php esc_html_e( 'Speed Dating', 'wpmatch' ); ?></button>
		</div>

		<div class="location-events-grid">
			<!-- Events will be loaded via AJAX -->
			<div class="location-loading">
				<div class="loading-spinner"></div>
				<span><?php esc_html_e( 'Loading nearby events...', 'wpmatch' ); ?></span>
			</div>
		</div>
	</div>

	<!-- Search History Section -->
	<div class="search-history-section">
		<div class="search-history-header">
			<h3><?php esc_html_e( 'Search History', 'wpmatch' ); ?></h3>
			<button class="clear-history-btn"><?php esc_html_e( 'Clear All', 'wpmatch' ); ?></button>
		</div>
		<div class="search-history-list">
			<!-- Search history will be loaded via AJAX -->
			<div class="location-loading">
				<div class="loading-spinner"></div>
				<span><?php esc_html_e( 'Loading search history...', 'wpmatch' ); ?></span>
			</div>
		</div>
	</div>

	<!-- Location Tips -->
	<div class="location-tips">
		<h4><?php esc_html_e( 'Privacy Tips', 'wpmatch' ); ?></h4>
		<ul>
			<li><?php esc_html_e( 'Use "Approximate" privacy level for better security', 'wpmatch' ); ?></li>
			<li><?php esc_html_e( 'Disable auto-update to control when your location is shared', 'wpmatch' ); ?></li>
			<li><?php esc_html_e( 'You can always disable location sharing completely', 'wpmatch' ); ?></li>
			<li><?php esc_html_e( 'Location data is only shared with matched users', 'wpmatch' ); ?></li>
		</ul>
	</div>
</div>

<script type="text/javascript">
// Localize script data
var wpmatch_location = {
	rest_url: '<?php echo esc_url( rest_url() ); ?>',
	nonce: '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>',
	current_user_id: <?php echo esc_js( $current_user_id ); ?>,
	default_avatar: '<?php echo esc_url( get_avatar_url( $current_user_id, array( 'size' => 60 ) ) ); ?>',
	profile_url: '<?php echo esc_url( home_url( '/profile/' ) ); ?>',
	messages_url: '<?php echo esc_url( home_url( '/messages/' ) ); ?>',
	strings: {
		loading: '<?php esc_html_e( 'Loading...', 'wpmatch' ); ?>',
		error: '<?php esc_html_e( 'Error occurred', 'wpmatch' ); ?>',
		load_error: '<?php esc_html_e( 'Failed to load data', 'wpmatch' ); ?>',
		no_nearby_users: '<?php esc_html_e( 'No nearby users found. Try increasing your search radius.', 'wpmatch' ); ?>',
		no_events: '<?php esc_html_e( 'No events found in your area', 'wpmatch' ); ?>',
		no_history: '<?php esc_html_e( 'No search history found', 'wpmatch' ); ?>',
		disable_confirm: '<?php esc_html_e( 'Are you sure you want to disable location sharing?', 'wpmatch' ); ?>',
		clear_history_confirm: '<?php esc_html_e( 'Clear all search history? This action cannot be undone.', 'wpmatch' ); ?>',
		location_enabled: '<?php esc_html_e( 'Location access enabled successfully!', 'wpmatch' ); ?>',
		location_disabled: '<?php esc_html_e( 'Location sharing has been disabled', 'wpmatch' ); ?>',
		location_updated: '<?php esc_html_e( 'Your location has been updated', 'wpmatch' ); ?>',
		permission_denied: '<?php esc_html_e( 'Location access denied. Please enable location access in your browser settings.', 'wpmatch' ); ?>',
		position_unavailable: '<?php esc_html_e( 'Location information is unavailable', 'wpmatch' ); ?>',
		timeout: '<?php esc_html_e( 'Location request timed out', 'wpmatch' ); ?>',
		not_supported: '<?php esc_html_e( 'Geolocation is not supported by this browser', 'wpmatch' ); ?>'
	}
};
</script>

<style>
/* Inline critical styles for immediate rendering */
.location-tips {
	background: #f8f9fa;
	border-radius: 8px;
	padding: 15px;
	margin-top: 20px;
}

.location-tips h4 {
	margin: 0 0 10px 0;
	color: #333;
	font-size: 16px;
}

.location-tips ul {
	margin: 0;
	padding-left: 20px;
}

.location-tips li {
	margin-bottom: 8px;
	color: #666;
	font-size: 14px;
	line-height: 1.4;
}

.no-nearby-users,
.no-location-events,
.no-search-history {
	text-align: center;
	padding: 40px 20px;
	color: #666;
}

.no-nearby-users p,
.no-location-events p,
.no-search-history p {
	margin: 0;
	font-size: 16px;
}

@media (max-width: 768px) {
	.location-tips {
		padding: 12px;
	}

	.location-tips h4 {
		font-size: 14px;
	}

	.location-tips li {
		font-size: 13px;
	}
}
</style>