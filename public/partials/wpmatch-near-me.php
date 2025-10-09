<?php
/**
 * Near Me Feature - Local User Discovery
 *
 * Displays nearby users with location-based filtering and controls.
 *
 * @package WPMatch
 * @since 1.8.0
 */

// Security check
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_user_id = get_current_user_id();
if ( ! $current_user_id ) {
	echo '<p>' . esc_html__( 'Please log in to discover nearby users.', 'wpmatch' ) . '</p>';
	return;
}

// Check if location services are enabled
$location_enabled = get_option( 'wpmatch_enable_location_services', true );
if ( ! $location_enabled ) {
	echo '<div class="wpmatch-notice notice-info">';
	echo '<p>' . esc_html__( 'Location services are currently disabled.', 'wpmatch' ) . '</p>';
	echo '</div>';
	return;
}

// Get user's current location and privacy settings
$user_location = null;
$privacy_settings = null;

if ( class_exists( 'WPMatch_Location' ) ) {
	$user_location = WPMatch_Location::get_user_location( $current_user_id );
	$privacy_settings = WPMatch_Location::get_user_privacy_settings( $current_user_id );
}

$default_radius = get_option( 'wpmatch_default_search_radius', 50 );
$max_radius = get_option( 'wpmatch_max_search_radius', 500 );
?>

<div id="wpmatch-near-me" class="wpmatch-near-me">

	<!-- Header Section -->
	<div class="near-me-header">
		<div class="header-content">
			<h2>
				<i class="fas fa-map-marker-alt"></i>
				<?php esc_html_e( 'Near Me', 'wpmatch' ); ?>
			</h2>
			<p class="subtitle"><?php esc_html_e( 'Discover singles in your area', 'wpmatch' ); ?></p>
		</div>

		<!-- Location Status -->
		<div class="location-status" id="location-status">
			<?php if ( $user_location ): ?>
				<div class="status-item active">
					<i class="fas fa-check-circle"></i>
					<span><?php echo sprintf( esc_html__( 'Location: %s, %s', 'wpmatch' ), esc_html( $user_location['city'] ), esc_html( $user_location['state'] ) ); ?></span>
					<button type="button" class="btn-link update-location"><?php esc_html_e( 'Update', 'wpmatch' ); ?></button>
				</div>
			<?php else: ?>
				<div class="status-item inactive">
					<i class="fas fa-exclamation-triangle"></i>
					<span><?php esc_html_e( 'Location not set', 'wpmatch' ); ?></span>
					<button type="button" class="btn btn-primary get-location"><?php esc_html_e( 'Enable Location', 'wpmatch' ); ?></button>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<!-- Search Controls -->
	<div class="search-controls" id="search-controls">
		<div class="controls-row">
			<!-- Distance Slider -->
			<div class="control-group">
				<label for="distance-range">
					<?php esc_html_e( 'Search Radius', 'wpmatch' ); ?>
					<span class="distance-display" id="distance-display"><?php echo absint( $default_radius ); ?> km</span>
				</label>
				<input type="range"
					   id="distance-range"
					   min="1"
					   max="<?php echo absint( $max_radius ); ?>"
					   value="<?php echo absint( $default_radius ); ?>"
					   class="distance-slider">
				<div class="range-labels">
					<span>1 km</span>
					<span><?php echo absint( $max_radius ); ?> km</span>
				</div>
			</div>

			<!-- Age Range -->
			<div class="control-group">
				<label><?php esc_html_e( 'Age Range', 'wpmatch' ); ?></label>
				<div class="age-inputs">
					<input type="number" id="min-age" placeholder="18" min="18" max="99" value="18">
					<span>-</span>
					<input type="number" id="max-age" placeholder="99" min="18" max="99" value="99">
				</div>
			</div>

			<!-- Gender Filter -->
			<div class="control-group">
				<label for="gender-filter"><?php esc_html_e( 'Looking for', 'wpmatch' ); ?></label>
				<select id="gender-filter">
					<option value=""><?php esc_html_e( 'Everyone', 'wpmatch' ); ?></option>
					<option value="male"><?php esc_html_e( 'Men', 'wpmatch' ); ?></option>
					<option value="female"><?php esc_html_e( 'Women', 'wpmatch' ); ?></option>
					<option value="non-binary"><?php esc_html_e( 'Non-binary', 'wpmatch' ); ?></option>
				</select>
			</div>

			<!-- Search Button -->
			<div class="control-group">
				<button type="button" class="btn btn-primary search-nearby" id="search-nearby">
					<i class="fas fa-search"></i>
					<?php esc_html_e( 'Find Nearby', 'wpmatch' ); ?>
				</button>
			</div>
		</div>

		<!-- Quick Filters -->
		<div class="quick-filters">
			<button type="button" class="filter-chip" data-radius="5">
				<i class="fas fa-walking"></i>
				<?php esc_html_e( 'Walking Distance', 'wpmatch' ); ?>
				<small>5km</small>
			</button>
			<button type="button" class="filter-chip" data-radius="25">
				<i class="fas fa-car"></i>
				<?php esc_html_e( 'Driving Distance', 'wpmatch' ); ?>
				<small>25km</small>
			</button>
			<button type="button" class="filter-chip" data-radius="50">
				<i class="fas fa-city"></i>
				<?php esc_html_e( 'Same City', 'wpmatch' ); ?>
				<small>50km</small>
			</button>
			<button type="button" class="filter-chip" data-radius="100">
				<i class="fas fa-plane"></i>
				<?php esc_html_e( 'Extended Area', 'wpmatch' ); ?>
				<small>100km</small>
			</button>
		</div>

		<!-- Advanced Filters -->
		<div class="advanced-filters" id="advanced-filters">
			<div class="advanced-filters-header">
				<h4><?php esc_html_e( 'Advanced Filters', 'wpmatch' ); ?></h4>
				<button type="button" class="btn-link toggle-advanced-filters">
					<span class="show-text"><?php esc_html_e( 'Show More', 'wpmatch' ); ?></span>
					<span class="hide-text" style="display: none;"><?php esc_html_e( 'Show Less', 'wpmatch' ); ?></span>
					<i class="fas fa-chevron-down"></i>
				</button>
			</div>

			<div class="advanced-filters-panel" style="display: none;">
				<div class="filter-row">
					<!-- Online Status Filter -->
					<div class="filter-group">
						<label for="online-status-filter"><?php esc_html_e( 'Online Status', 'wpmatch' ); ?></label>
						<select id="online-status-filter">
							<option value=""><?php esc_html_e( 'Any', 'wpmatch' ); ?></option>
							<option value="online"><?php esc_html_e( 'Online now', 'wpmatch' ); ?></option>
							<option value="recent"><?php esc_html_e( 'Active today', 'wpmatch' ); ?></option>
							<option value="week"><?php esc_html_e( 'Active this week', 'wpmatch' ); ?></option>
						</select>
					</div>

					<!-- Location Precision Filter -->
					<div class="filter-group">
						<label for="location-precision-filter"><?php esc_html_e( 'Location Precision', 'wpmatch' ); ?></label>
						<select id="location-precision-filter">
							<option value=""><?php esc_html_e( 'Any', 'wpmatch' ); ?></option>
							<option value="exact"><?php esc_html_e( 'Exact location', 'wpmatch' ); ?></option>
							<option value="approximate"><?php esc_html_e( 'Approximate', 'wpmatch' ); ?></option>
							<option value="city-only"><?php esc_html_e( 'City only', 'wpmatch' ); ?></option>
						</select>
					</div>

					<!-- Travel Status Filter -->
					<div class="filter-group">
						<label for="travel-status-filter"><?php esc_html_e( 'Travel Status', 'wpmatch' ); ?></label>
						<select id="travel-status-filter">
							<option value=""><?php esc_html_e( 'Any', 'wpmatch' ); ?></option>
							<option value="local"><?php esc_html_e( 'Locals only', 'wpmatch' ); ?></option>
							<option value="traveling"><?php esc_html_e( 'Travelers only', 'wpmatch' ); ?></option>
						</select>
					</div>

					<!-- Verification Filter -->
					<div class="filter-group">
						<label for="verification-filter"><?php esc_html_e( 'Verification', 'wpmatch' ); ?></label>
						<select id="verification-filter">
							<option value=""><?php esc_html_e( 'Any', 'wpmatch' ); ?></option>
							<option value="verified"><?php esc_html_e( 'Verified only', 'wpmatch' ); ?></option>
							<option value="photo_verified"><?php esc_html_e( 'Photo verified', 'wpmatch' ); ?></option>
						</select>
					</div>
				</div>

				<div class="filter-row">
					<!-- Interests Filter -->
					<div class="filter-group full-width">
						<label for="interests-filter"><?php esc_html_e( 'Common Interests', 'wpmatch' ); ?></label>
						<div class="interests-filter-container">
							<input type="text" id="interests-filter" placeholder="<?php esc_attr_e( 'Type to search interests...', 'wpmatch' ); ?>">
							<div class="selected-interests" id="selected-interests"></div>
						</div>
					</div>
				</div>

				<div class="filter-row">
					<!-- Location Type Filters -->
					<div class="filter-group">
						<label><?php esc_html_e( 'Location Types', 'wpmatch' ); ?></label>
						<div class="checkbox-group">
							<label class="checkbox-label">
								<input type="checkbox" id="near-work" value="work">
								<span class="checkmark"></span>
								<?php esc_html_e( 'Near work areas', 'wpmatch' ); ?>
							</label>
							<label class="checkbox-label">
								<input type="checkbox" id="near-entertainment" value="entertainment">
								<span class="checkmark"></span>
								<?php esc_html_e( 'Near entertainment', 'wpmatch' ); ?>
							</label>
							<label class="checkbox-label">
								<input type="checkbox" id="near-transit" value="transit">
								<span class="checkmark"></span>
								<?php esc_html_e( 'Near public transit', 'wpmatch' ); ?>
							</label>
						</div>
					</div>

					<!-- Saved Locations -->
					<div class="filter-group">
						<label for="saved-locations-filter"><?php esc_html_e( 'Saved Locations', 'wpmatch' ); ?></label>
						<select id="saved-locations-filter">
							<option value=""><?php esc_html_e( 'Current location', 'wpmatch' ); ?></option>
							<option value="home"><?php esc_html_e( 'Near home', 'wpmatch' ); ?></option>
							<option value="work"><?php esc_html_e( 'Near work', 'wpmatch' ); ?></option>
							<option value="custom"><?php esc_html_e( 'Custom location...', 'wpmatch' ); ?></option>
						</select>
					</div>
				</div>

				<!-- Filter Actions -->
				<div class="filter-actions">
					<button type="button" class="btn btn-primary apply-filters">
						<i class="fas fa-search"></i>
						<?php esc_html_e( 'Apply Filters', 'wpmatch' ); ?>
					</button>
					<button type="button" class="btn btn-secondary reset-filters">
						<i class="fas fa-undo"></i>
						<?php esc_html_e( 'Reset All', 'wpmatch' ); ?>
					</button>
					<button type="button" class="btn btn-link save-filter-preset">
						<i class="fas fa-bookmark"></i>
						<?php esc_html_e( 'Save Preset', 'wpmatch' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- Results Section -->
	<div class="results-section">

		<!-- Loading State -->
		<div class="loading-state" id="loading-state" style="display: none;">
			<div class="loading-spinner"></div>
			<p><?php esc_html_e( 'Searching for nearby users...', 'wpmatch' ); ?></p>
		</div>

		<!-- Results Header -->
		<div class="results-header" id="results-header" style="display: none;">
			<h3>
				<span id="results-count">0</span>
				<?php esc_html_e( 'users found nearby', 'wpmatch' ); ?>
			</h3>
			<div class="view-toggle">
				<button type="button" class="view-btn active" data-view="grid">
					<i class="fas fa-th-large"></i>
				</button>
				<button type="button" class="view-btn" data-view="list">
					<i class="fas fa-list"></i>
				</button>
				<button type="button" class="view-btn" data-view="map">
					<i class="fas fa-map"></i>
				</button>
			</div>
		</div>

		<!-- User Results Grid -->
		<div class="users-grid" id="users-grid">
			<!-- Users will be loaded here via JavaScript -->
		</div>

		<!-- Map View -->
		<div class="map-view" id="map-view" style="display: none;">
			<div class="map-container" id="map-container">
				<div class="map-placeholder">
					<i class="fas fa-map"></i>
					<p><?php esc_html_e( 'Map view requires location permissions', 'wpmatch' ); ?></p>
				</div>
			</div>
		</div>

		<!-- Empty State -->
		<div class="empty-state" id="empty-state" style="display: none;">
			<div class="empty-icon">
				<i class="fas fa-map-marker-alt"></i>
			</div>
			<h3><?php esc_html_e( 'No users found in your area', 'wpmatch' ); ?></h3>
			<p><?php esc_html_e( 'Try expanding your search radius or updating your location.', 'wpmatch' ); ?></p>
			<button type="button" class="btn btn-secondary expand-search">
				<?php esc_html_e( 'Expand Search Area', 'wpmatch' ); ?>
			</button>
		</div>

		<!-- Location Permission Required -->
		<div class="permission-required" id="permission-required">
			<div class="permission-content">
				<div class="permission-icon">
					<i class="fas fa-location-arrow"></i>
				</div>
				<h3><?php esc_html_e( 'Location Access Required', 'wpmatch' ); ?></h3>
				<p><?php esc_html_e( 'To discover nearby users, we need access to your location. This helps us show you singles in your area.', 'wpmatch' ); ?></p>

				<div class="permission-benefits">
					<div class="benefit">
						<i class="fas fa-users"></i>
						<span><?php esc_html_e( 'Find local singles', 'wpmatch' ); ?></span>
					</div>
					<div class="benefit">
						<i class="fas fa-heart"></i>
						<span><?php esc_html_e( 'Better matches nearby', 'wpmatch' ); ?></span>
					</div>
					<div class="benefit">
						<i class="fas fa-shield-alt"></i>
						<span><?php esc_html_e( 'Privacy controls included', 'wpmatch' ); ?></span>
					</div>
				</div>

				<div class="permission-actions">
					<button type="button" class="btn btn-primary enable-location">
						<i class="fas fa-map-marker-alt"></i>
						<?php esc_html_e( 'Enable Location', 'wpmatch' ); ?>
					</button>
					<button type="button" class="btn btn-link privacy-settings">
						<?php esc_html_e( 'Privacy Settings', 'wpmatch' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- Travel Mode (Passport Feature) -->
	<div class="travel-mode-section" id="travel-mode-section" style="display: none;">
		<div class="travel-mode-header">
			<div class="travel-icon">
				<i class="fas fa-plane"></i>
			</div>
			<div class="travel-content">
				<h3><?php esc_html_e( 'Passport Travel Mode', 'wpmatch' ); ?></h3>
				<p class="travel-subtitle"><?php esc_html_e( 'Connect with singles in your next travel destination', 'wpmatch' ); ?></p>
			</div>
			<button type="button" class="btn btn-secondary exit-travel-mode">
				<i class="fas fa-times"></i>
				<?php esc_html_e( 'Exit Travel Mode', 'wpmatch' ); ?>
			</button>
		</div>

		<div class="travel-mode-controls">
			<div class="destination-search">
				<label for="travel-destination"><?php esc_html_e( 'Where are you traveling to?', 'wpmatch' ); ?></label>
				<div class="destination-input-group">
					<input type="text" id="travel-destination" placeholder="<?php esc_attr_e( 'Enter city, state, or country', 'wpmatch' ); ?>">
					<button type="button" class="btn btn-primary search-destination">
						<i class="fas fa-search"></i>
						<?php esc_html_e( 'Search', 'wpmatch' ); ?>
					</button>
				</div>
				<div class="destination-suggestions" id="destination-suggestions"></div>
			</div>

			<div class="travel-options">
				<div class="travel-option-group">
					<label for="travel-dates"><?php esc_html_e( 'Travel Dates', 'wpmatch' ); ?></label>
					<div class="date-inputs">
						<input type="date" id="travel-start-date" min="<?php echo esc_attr( date('Y-m-d') ); ?>">
						<span>to</span>
						<input type="date" id="travel-end-date" min="<?php echo esc_attr( date('Y-m-d') ); ?>">
					</div>
				</div>

				<div class="travel-option-group">
					<label for="travel-radius"><?php esc_html_e( 'Search Radius', 'wpmatch' ); ?></label>
					<div class="radius-control">
						<input type="range" id="travel-radius" min="1" max="100" value="25">
						<span class="radius-display" id="travel-radius-display">25 km</span>
					</div>
				</div>

				<div class="travel-option-group">
					<label><?php esc_html_e( 'Travel Preferences', 'wpmatch' ); ?></label>
					<div class="travel-preferences">
						<label class="travel-checkbox">
							<input type="checkbox" id="show-locals" checked>
							<span class="checkmark"></span>
							<?php esc_html_e( 'Show local residents', 'wpmatch' ); ?>
						</label>
						<label class="travel-checkbox">
							<input type="checkbox" id="show-travelers">
							<span class="checkmark"></span>
							<?php esc_html_e( 'Show other travelers', 'wpmatch' ); ?>
						</label>
						<label class="travel-checkbox">
							<input type="checkbox" id="notify-matches">
							<span class="checkmark"></span>
							<?php esc_html_e( 'Notify matches of travel plans', 'wpmatch' ); ?>
						</label>
					</div>
				</div>
			</div>

			<div class="travel-actions">
				<button type="button" class="btn btn-primary start-travel-search">
					<i class="fas fa-globe"></i>
					<?php esc_html_e( 'Start Travel Search', 'wpmatch' ); ?>
				</button>
				<button type="button" class="btn btn-secondary save-travel-plan">
					<i class="fas fa-bookmark"></i>
					<?php esc_html_e( 'Save Travel Plan', 'wpmatch' ); ?>
				</button>
			</div>
		</div>

		<!-- Active Travel Plans -->
		<div class="active-travel-plans" id="active-travel-plans">
			<h4><?php esc_html_e( 'Your Travel Plans', 'wpmatch' ); ?></h4>
			<div class="travel-plans-list" id="travel-plans-list">
				<!-- Travel plans will be loaded here -->
			</div>
		</div>
	</div>

	<!-- Travel Mode Toggle Button -->
	<div class="travel-mode-toggle">
		<button type="button" class="btn btn-travel" id="toggle-travel-mode">
			<i class="fas fa-plane"></i>
			<?php esc_html_e( 'Passport Travel Mode', 'wpmatch' ); ?>
		</button>
	</div>

	<!-- Enhanced Location Privacy Controls -->
	<div class="privacy-controls" id="privacy-controls">
		<div class="privacy-header">
			<div class="privacy-icon">
				<i class="fas fa-shield-alt"></i>
			</div>
			<div class="privacy-title">
				<h4><?php esc_html_e( 'Location Privacy & Security', 'wpmatch' ); ?></h4>
				<p class="privacy-subtitle"><?php esc_html_e( 'Control how your location is shared and who can see it', 'wpmatch' ); ?></p>
			</div>
			<button type="button" class="btn-link toggle-privacy">
				<span class="show-text"><?php esc_html_e( 'Manage Privacy', 'wpmatch' ); ?></span>
				<span class="hide-text" style="display: none;"><?php esc_html_e( 'Hide Settings', 'wpmatch' ); ?></span>
				<i class="fas fa-chevron-down"></i>
			</button>
		</div>

		<div class="privacy-settings-panel" style="display: none;">

			<!-- Location Sharing Controls -->
			<div class="privacy-section">
				<h5 class="section-title">
					<i class="fas fa-map-marker-alt"></i>
					<?php esc_html_e( 'Location Sharing', 'wpmatch' ); ?>
				</h5>

				<div class="setting-group">
					<label class="setting-label">
						<input type="checkbox" id="enable-location-sharing" <?php checked( $privacy_settings['enable_location_sharing'] ?? true ); ?>>
						<span class="checkmark"></span>
						<?php esc_html_e( 'Enable location-based features', 'wpmatch' ); ?>
					</label>
					<small><?php esc_html_e( 'Allow the app to use your location for matching and discovery features', 'wpmatch' ); ?></small>
				</div>

				<div class="setting-group">
					<label for="location-precision"><?php esc_html_e( 'Location Precision Level', 'wpmatch' ); ?></label>
					<select id="location-precision" class="privacy-select">
						<option value="exact" <?php selected( ( $privacy_settings['location_precision'] ?? 'approximate' ), 'exact' ); ?>>
							<?php esc_html_e( 'Exact location - Most accurate matching', 'wpmatch' ); ?>
						</option>
						<option value="approximate" <?php selected( ( $privacy_settings['location_precision'] ?? 'approximate' ), 'approximate' ); ?>>
							<?php esc_html_e( 'Approximate location (~1-5km radius)', 'wpmatch' ); ?>
						</option>
						<option value="city" <?php selected( ( $privacy_settings['location_precision'] ?? 'approximate' ), 'city' ); ?>>
							<?php esc_html_e( 'City level only', 'wpmatch' ); ?>
						</option>
						<option value="region" <?php selected( ( $privacy_settings['location_precision'] ?? 'approximate' ), 'region' ); ?>>
							<?php esc_html_e( 'Regional level only', 'wpmatch' ); ?>
						</option>
					</select>
					<div class="precision-indicator" id="precision-indicator">
						<div class="precision-level" data-level="<?php echo esc_attr( $privacy_settings['location_precision'] ?? 'approximate' ); ?>"></div>
					</div>
				</div>

				<div class="setting-group">
					<label class="setting-label">
						<input type="checkbox" id="auto-update-location" <?php checked( $privacy_settings['auto_update_location'] ?? false ); ?>>
						<span class="checkmark"></span>
						<?php esc_html_e( 'Automatically update location', 'wpmatch' ); ?>
					</label>
					<small><?php esc_html_e( 'Keep your location current as you move around', 'wpmatch' ); ?></small>
				</div>
			</div>

			<!-- Visibility Controls -->
			<div class="privacy-section">
				<h5 class="section-title">
					<i class="fas fa-eye"></i>
					<?php esc_html_e( 'Visibility Controls', 'wpmatch' ); ?>
				</h5>

				<div class="setting-group">
					<label class="setting-label">
						<input type="checkbox" id="show-distance" <?php checked( $privacy_settings['show_distance'] ?? true ); ?>>
						<span class="checkmark"></span>
						<?php esc_html_e( 'Show distance to other users', 'wpmatch' ); ?>
					</label>
					<small><?php esc_html_e( 'Display how far away you are from other users', 'wpmatch' ); ?></small>
				</div>

				<div class="setting-group">
					<label class="setting-label">
						<input type="checkbox" id="show-last-location-update" <?php checked( $privacy_settings['show_last_location_update'] ?? false ); ?>>
						<span class="checkmark"></span>
						<?php esc_html_e( 'Show when location was last updated', 'wpmatch' ); ?>
					</label>
					<small><?php esc_html_e( 'Let others see how recent your location information is', 'wpmatch' ); ?></small>
				</div>

				<div class="setting-group">
					<label class="setting-label">
						<input type="checkbox" id="hide-from-nearby" <?php checked( $privacy_settings['hide_from_nearby'] ?? false ); ?>>
						<span class="checkmark"></span>
						<?php esc_html_e( 'Hide from nearby searches', 'wpmatch' ); ?>
					</label>
					<small><?php esc_html_e( 'Prevent your profile from appearing in other users\' nearby searches', 'wpmatch' ); ?></small>
				</div>

				<div class="setting-group">
					<label for="visibility-radius"><?php esc_html_e( 'Visibility Radius', 'wpmatch' ); ?></label>
					<div class="slider-container">
						<input type="range" id="visibility-radius" min="1" max="500" value="<?php echo absint( $privacy_settings['visibility_radius_km'] ?? 50 ); ?>" class="privacy-slider">
						<span class="slider-value" id="visibility-radius-value"><?php echo absint( $privacy_settings['visibility_radius_km'] ?? 50 ); ?> km</span>
					</div>
					<small><?php esc_html_e( 'Maximum distance for appearing in other users\' searches', 'wpmatch' ); ?></small>
				</div>
			</div>

			<!-- Advanced Privacy -->
			<div class="privacy-section">
				<h5 class="section-title">
					<i class="fas fa-lock"></i>
					<?php esc_html_e( 'Advanced Privacy', 'wpmatch' ); ?>
				</h5>

				<div class="setting-group">
					<label class="setting-label">
						<input type="checkbox" id="hide-from-search-engines" <?php checked( $privacy_settings['hide_from_search_engines'] ?? true ); ?>>
						<span class="checkmark"></span>
						<?php esc_html_e( 'Hide location from search engines', 'wpmatch' ); ?>
					</label>
					<small><?php esc_html_e( 'Prevent search engines from indexing your location information', 'wpmatch' ); ?></small>
				</div>

				<div class="setting-group">
					<label class="setting-label">
						<input type="checkbox" id="require-match-for-location" <?php checked( $privacy_settings['require_match_for_location'] ?? false ); ?>>
						<span class="checkmark"></span>
						<?php esc_html_e( 'Only show exact location to matches', 'wpmatch' ); ?>
					</label>
					<small><?php esc_html_e( 'Your precise location will only be visible after mutual matching', 'wpmatch' ); ?></small>
				</div>

				<div class="setting-group">
					<label class="setting-label">
						<input type="checkbox" id="ghost-mode" <?php checked( $privacy_settings['ghost_mode'] ?? false ); ?>>
						<span class="checkmark"></span>
						<?php esc_html_e( 'Ghost Mode', 'wpmatch' ); ?>
						<span class="premium-badge"><?php esc_html_e( 'Premium', 'wpmatch' ); ?></span>
					</label>
					<small><?php esc_html_e( 'Browse anonymously - see others without appearing in their discovery', 'wpmatch' ); ?></small>
				</div>

				<div class="setting-group">
					<label for="location-schedule"><?php esc_html_e( 'Location Sharing Schedule', 'wpmatch' ); ?></label>
					<select id="location-schedule" class="privacy-select">
						<option value="always" <?php selected( ( $privacy_settings['location_schedule'] ?? 'always' ), 'always' ); ?>>
							<?php esc_html_e( 'Always share location', 'wpmatch' ); ?>
						</option>
						<option value="active_hours" <?php selected( ( $privacy_settings['location_schedule'] ?? 'always' ), 'active_hours' ); ?>>
							<?php esc_html_e( 'Only during active hours', 'wpmatch' ); ?>
						</option>
						<option value="custom" <?php selected( ( $privacy_settings['location_schedule'] ?? 'always' ), 'custom' ); ?>>
							<?php esc_html_e( 'Custom schedule', 'wpmatch' ); ?>
						</option>
						<option value="never" <?php selected( ( $privacy_settings['location_schedule'] ?? 'always' ), 'never' ); ?>>
							<?php esc_html_e( 'Never share automatically', 'wpmatch' ); ?>
						</option>
					</select>
				</div>
			</div>

			<!-- Location History -->
			<div class="privacy-section">
				<h5 class="section-title">
					<i class="fas fa-history"></i>
					<?php esc_html_e( 'Location Data Management', 'wpmatch' ); ?>
				</h5>

				<div class="setting-group">
					<label class="setting-label">
						<input type="checkbox" id="save-location-history" <?php checked( $privacy_settings['save_location_history'] ?? false ); ?>>
						<span class="checkmark"></span>
						<?php esc_html_e( 'Save location history', 'wpmatch' ); ?>
					</label>
					<small><?php esc_html_e( 'Keep a history of your locations for better matching suggestions', 'wpmatch' ); ?></small>
				</div>

				<div class="setting-group">
					<label for="location-retention"><?php esc_html_e( 'Location Data Retention', 'wpmatch' ); ?></label>
					<select id="location-retention" class="privacy-select">
						<option value="1_day" <?php selected( ( $privacy_settings['location_retention'] ?? '30_days' ), '1_day' ); ?>>
							<?php esc_html_e( '1 Day', 'wpmatch' ); ?>
						</option>
						<option value="7_days" <?php selected( ( $privacy_settings['location_retention'] ?? '30_days' ), '7_days' ); ?>>
							<?php esc_html_e( '7 Days', 'wpmatch' ); ?>
						</option>
						<option value="30_days" <?php selected( ( $privacy_settings['location_retention'] ?? '30_days' ), '30_days' ); ?>>
							<?php esc_html_e( '30 Days', 'wpmatch' ); ?>
						</option>
						<option value="90_days" <?php selected( ( $privacy_settings['location_retention'] ?? '30_days' ), '90_days' ); ?>>
							<?php esc_html_e( '90 Days', 'wpmatch' ); ?>
						</option>
						<option value="never_delete" <?php selected( ( $privacy_settings['location_retention'] ?? '30_days' ), 'never_delete' ); ?>>
							<?php esc_html_e( 'Keep indefinitely', 'wpmatch' ); ?>
						</option>
					</select>
					<small><?php esc_html_e( 'How long to keep your location data stored', 'wpmatch' ); ?></small>
				</div>

				<div class="location-data-actions">
					<button type="button" class="btn btn-secondary export-location-data">
						<i class="fas fa-download"></i>
						<?php esc_html_e( 'Export Location Data', 'wpmatch' ); ?>
					</button>
					<button type="button" class="btn btn-danger delete-location-data">
						<i class="fas fa-trash"></i>
						<?php esc_html_e( 'Delete All Location Data', 'wpmatch' ); ?>
					</button>
				</div>
			</div>

			<!-- Privacy Summary -->
			<div class="privacy-summary" id="privacy-summary">
				<div class="summary-header">
					<h5><?php esc_html_e( 'Privacy Summary', 'wpmatch' ); ?></h5>
					<div class="privacy-score" id="privacy-score">
						<div class="score-circle">
							<span class="score-value">85</span>
							<span class="score-label">%</span>
						</div>
					</div>
				</div>
				<div class="summary-items" id="summary-items">
					<!-- Summary items will be populated by JavaScript -->
				</div>
			</div>

			<!-- Action Buttons -->
			<div class="privacy-actions">
				<button type="button" class="btn btn-primary save-privacy-settings">
					<i class="fas fa-shield-alt"></i>
					<?php esc_html_e( 'Save Privacy Settings', 'wpmatch' ); ?>
				</button>
				<button type="button" class="btn btn-secondary reset-privacy-defaults">
					<i class="fas fa-undo"></i>
					<?php esc_html_e( 'Reset to Defaults', 'wpmatch' ); ?>
				</button>
				<button type="button" class="btn btn-link privacy-help">
					<i class="fas fa-question-circle"></i>
					<?php esc_html_e( 'Privacy Help', 'wpmatch' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>

<!-- User Profile Modal -->
<div id="user-profile-modal" class="modal user-profile-modal" style="display: none;">
	<div class="modal-overlay" id="modal-overlay"></div>
	<div class="modal-content">
		<button type="button" class="modal-close" id="modal-close">
			<i class="fas fa-times"></i>
		</button>
		<div class="modal-body" id="modal-body">
			<!-- User profile content will be loaded here -->
		</div>
	</div>
</div>

<script type="text/javascript">
// Configuration for the Near Me feature
window.WPMatchNearMe = {
	apiUrl: '<?php echo esc_url( rest_url( 'wpmatch/v1' ) ); ?>',
	nonce: '<?php echo wp_create_nonce( 'wp_rest' ); ?>',
	currentUserId: <?php echo absint( $current_user_id ); ?>,
	userLocation: <?php echo wp_json_encode( $user_location ); ?>,
	privacySettings: <?php echo wp_json_encode( $privacy_settings ); ?>,
	defaults: {
		radius: <?php echo absint( $default_radius ); ?>,
		maxRadius: <?php echo absint( $max_radius ); ?>,
		minAge: 18,
		maxAge: 99
	},
	strings: {
		permissionDenied: '<?php echo esc_js( __( 'Location permission denied. Please enable location access to use this feature.', 'wpmatch' ) ); ?>',
		locationError: '<?php echo esc_js( __( 'Error getting your location. Please try again.', 'wpmatch' ) ); ?>',
		locationUpdated: '<?php echo esc_js( __( 'Location updated successfully!', 'wpmatch' ) ); ?>',
		searchError: '<?php echo esc_js( __( 'Error searching for nearby users. Please try again.', 'wpmatch' ) ); ?>',
		noUsersFound: '<?php echo esc_js( __( 'No users found in your area.', 'wpmatch' ) ); ?>',
		privacyUpdated: '<?php echo esc_js( __( 'Privacy settings updated successfully!', 'wpmatch' ) ); ?>',
		kmAway: '<?php echo esc_js( __( '%s km away', 'wpmatch' ) ); ?>',
		milesAway: '<?php echo esc_js( __( '%s miles away', 'wpmatch' ) ); ?>',
		online: '<?php echo esc_js( __( 'Online', 'wpmatch' ) ); ?>',
		lastSeen: '<?php echo esc_js( __( 'Last seen %s ago', 'wpmatch' ) ); ?>',
		sendMessage: '<?php echo esc_js( __( 'Send Message', 'wpmatch' ) ); ?>',
		viewProfile: '<?php echo esc_js( __( 'View Profile', 'wpmatch' ) ); ?>',
		like: '<?php echo esc_js( __( 'Like', 'wpmatch' ) ); ?>',
		superLike: '<?php echo esc_js( __( 'Super Like', 'wpmatch' ) ); ?>',
		block: '<?php echo esc_js( __( 'Block User', 'wpmatch' ) ); ?>',
		report: '<?php echo esc_js( __( 'Report User', 'wpmatch' ) ); ?>',
		ageYears: '<?php echo esc_js( __( '%d years old', 'wpmatch' ) ); ?>',
		verified: '<?php echo esc_js( __( 'Verified', 'wpmatch' ) ); ?>'
	}
};
</script>