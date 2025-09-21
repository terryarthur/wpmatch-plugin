<?php
/**
 * Search interface template
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
	echo '<div class="wpmatch-notice error"><p>' . esc_html__( 'Please log in to search for matches.', 'wpmatch' ) . '</p></div>';
	return;
}

// Get user's search preferences.
$search_preferences = WPMatch_Search_Manager::get_search_preferences( $current_user->ID );

// Handle search form submission.
$search_results = array();
$search_performed = false;

if ( isset( $_GET['wpmatch_search'] ) && isset( $_GET['search_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['search_nonce'] ) ), 'wpmatch_search' ) ) {
	$search_criteria = array(
		'min_age'      => isset( $_GET['min_age'] ) ? absint( $_GET['min_age'] ) : $search_preferences['min_age'],
		'max_age'      => isset( $_GET['max_age'] ) ? absint( $_GET['max_age'] ) : $search_preferences['max_age'],
		'max_distance' => isset( $_GET['max_distance'] ) ? absint( $_GET['max_distance'] ) : $search_preferences['max_distance'],
		'gender'       => isset( $_GET['gender'] ) ? sanitize_text_field( wp_unslash( $_GET['gender'] ) ) : '',
		'location'     => isset( $_GET['location'] ) ? sanitize_text_field( wp_unslash( $_GET['location'] ) ) : '',
		'keywords'     => isset( $_GET['keywords'] ) ? sanitize_text_field( wp_unslash( $_GET['keywords'] ) ) : '',
		'limit'        => 20,
	);

	$search_results = WPMatch_Search_Manager::search_matches( $current_user->ID, $search_criteria );
	$search_performed = true;
}

// Get popular filters for suggestions.
$popular_filters = WPMatch_Search_Manager::get_popular_filters();
?>

<div class="wpmatch-search-container">
	<div class="wpmatch-search-header">
		<h2><?php esc_html_e( 'Find Your Perfect Match', 'wpmatch' ); ?></h2>
		<p><?php esc_html_e( 'Use the filters below to discover people who match your preferences.', 'wpmatch' ); ?></p>
	</div>

	<!-- Search Form -->
	<div class="wpmatch-search-form">
		<form method="get" id="wpmatch-search-form">
			<?php wp_nonce_field( 'wpmatch_search', 'search_nonce' ); ?>
			<input type="hidden" name="wpmatch_search" value="1">

			<div class="wpmatch-search-filters">
				<!-- Age Range -->
				<div class="wpmatch-filter-group">
					<label><?php esc_html_e( 'Age Range', 'wpmatch' ); ?></label>
					<div class="wpmatch-age-range">
						<input type="number" name="min_age" min="18" max="120"
							value="<?php echo esc_attr( isset( $_GET['min_age'] ) ? $_GET['min_age'] : $search_preferences['min_age'] ); ?>"
							placeholder="18">
						<span><?php esc_html_e( 'to', 'wpmatch' ); ?></span>
						<input type="number" name="max_age" min="18" max="120"
							value="<?php echo esc_attr( isset( $_GET['max_age'] ) ? $_GET['max_age'] : $search_preferences['max_age'] ); ?>"
							placeholder="99">
					</div>
				</div>

				<!-- Gender -->
				<div class="wpmatch-filter-group">
					<label for="gender"><?php esc_html_e( 'Gender', 'wpmatch' ); ?></label>
					<select name="gender" id="gender">
						<option value=""><?php esc_html_e( 'Any Gender', 'wpmatch' ); ?></option>
						<option value="male" <?php selected( isset( $_GET['gender'] ) ? $_GET['gender'] : '', 'male' ); ?>><?php esc_html_e( 'Male', 'wpmatch' ); ?></option>
						<option value="female" <?php selected( isset( $_GET['gender'] ) ? $_GET['gender'] : '', 'female' ); ?>><?php esc_html_e( 'Female', 'wpmatch' ); ?></option>
						<option value="non-binary" <?php selected( isset( $_GET['gender'] ) ? $_GET['gender'] : '', 'non-binary' ); ?>><?php esc_html_e( 'Non-Binary', 'wpmatch' ); ?></option>
					</select>
				</div>

				<!-- Distance -->
				<div class="wpmatch-filter-group">
					<label for="max_distance"><?php esc_html_e( 'Max Distance', 'wpmatch' ); ?></label>
					<div class="wpmatch-distance-slider">
						<input type="range" name="max_distance" id="max_distance" min="1" max="500"
							value="<?php echo esc_attr( isset( $_GET['max_distance'] ) ? $_GET['max_distance'] : $search_preferences['max_distance'] ); ?>">
						<span class="wpmatch-distance-value"><?php echo esc_html( isset( $_GET['max_distance'] ) ? $_GET['max_distance'] : $search_preferences['max_distance'] ); ?> <?php esc_html_e( 'miles', 'wpmatch' ); ?></span>
					</div>
				</div>

				<!-- Location -->
				<div class="wpmatch-filter-group">
					<label for="location"><?php esc_html_e( 'Location', 'wpmatch' ); ?></label>
					<input type="text" name="location" id="location"
						value="<?php echo esc_attr( isset( $_GET['location'] ) ? $_GET['location'] : '' ); ?>"
						placeholder="<?php esc_attr_e( 'City, State', 'wpmatch' ); ?>"
						autocomplete="off">
					<div class="wpmatch-suggestions" id="location-suggestions"></div>
				</div>

				<!-- Keywords -->
				<div class="wpmatch-filter-group">
					<label for="keywords"><?php esc_html_e( 'Keywords', 'wpmatch' ); ?></label>
					<input type="text" name="keywords" id="keywords"
						value="<?php echo esc_attr( isset( $_GET['keywords'] ) ? $_GET['keywords'] : '' ); ?>"
						placeholder="<?php esc_attr_e( 'Interests, hobbies, etc.', 'wpmatch' ); ?>">
				</div>
			</div>

			<div class="wpmatch-search-actions">
				<button type="submit" class="wpmatch-button primary">
					<span class="dashicons dashicons-search"></span>
					<?php esc_html_e( 'Search', 'wpmatch' ); ?>
				</button>
				<button type="button" class="wpmatch-button secondary" id="clear-filters">
					<?php esc_html_e( 'Clear Filters', 'wpmatch' ); ?>
				</button>
				<button type="button" class="wpmatch-button secondary" id="save-preferences">
					<?php esc_html_e( 'Save as Default', 'wpmatch' ); ?>
				</button>
			</div>
		</form>
	</div>

	<!-- Popular Filters -->
	<?php if ( ! empty( $popular_filters['locations'] ) ) : ?>
		<div class="wpmatch-popular-filters">
			<h3><?php esc_html_e( 'Popular Locations', 'wpmatch' ); ?></h3>
			<div class="wpmatch-filter-tags">
				<?php foreach ( array_slice( $popular_filters['locations'], 0, 8 ) as $location ) : ?>
					<button type="button" class="wpmatch-filter-tag" data-filter="location" data-value="<?php echo esc_attr( $location->location ); ?>">
						<?php echo esc_html( $location->location ); ?>
						<span class="count">(<?php echo esc_html( $location->count ); ?>)</span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>

	<!-- Search Results -->
	<div class="wpmatch-search-results">
		<?php if ( $search_performed ) : ?>
			<div class="wpmatch-results-header">
				<h3>
					<?php
					/* translators: %d: number of search results */
					printf( esc_html( _n( '%d person found', '%d people found', count( $search_results ), 'wpmatch' ) ), count( $search_results ) );
					?>
				</h3>
				<div class="wpmatch-view-toggle">
					<button type="button" class="wpmatch-view-btn active" data-view="grid">
						<span class="dashicons dashicons-grid-view"></span>
					</button>
					<button type="button" class="wpmatch-view-btn" data-view="list">
						<span class="dashicons dashicons-list-view"></span>
					</button>
				</div>
			</div>

			<?php if ( empty( $search_results ) ) : ?>
				<div class="wpmatch-no-results">
					<div class="wpmatch-empty-state">
						<span class="dashicons dashicons-search"></span>
						<h3><?php esc_html_e( 'No matches found', 'wpmatch' ); ?></h3>
						<p><?php esc_html_e( 'Try adjusting your search filters to find more potential matches.', 'wpmatch' ); ?></p>
						<button type="button" class="wpmatch-button primary" id="expand-search">
							<?php esc_html_e( 'Expand Search Area', 'wpmatch' ); ?>
						</button>
					</div>
				</div>
			<?php else : ?>
				<div class="wpmatch-results-grid active" data-view="grid">
					<?php foreach ( $search_results as $profile ) : ?>
						<div class="wpmatch-profile-card" data-user-id="<?php echo esc_attr( $profile->user_id ); ?>">
							<div class="wpmatch-profile-image">
								<?php if ( $profile->primary_photo ) : ?>
									<img src="<?php echo esc_url( $profile->primary_photo ); ?>" alt="<?php echo esc_attr( $profile->display_name ); ?>">
								<?php else : ?>
									<div class="wpmatch-no-photo">
										<span class="dashicons dashicons-admin-users"></span>
									</div>
								<?php endif; ?>
								<?php if ( isset( $profile->distance ) && $profile->distance ) : ?>
									<div class="wpmatch-distance-badge">
										<?php echo esc_html( round( $profile->distance ) ); ?> <?php esc_html_e( 'mi', 'wpmatch' ); ?>
									</div>
								<?php endif; ?>
							</div>
							<div class="wpmatch-profile-info">
								<h3><?php echo esc_html( $profile->display_name ); ?></h3>
								<div class="wpmatch-profile-meta">
									<?php if ( $profile->age ) : ?>
										<span class="wpmatch-age"><?php echo esc_html( $profile->age ); ?></span>
									<?php endif; ?>
									<?php if ( $profile->location ) : ?>
										<span class="wpmatch-location"><?php echo esc_html( $profile->location ); ?></span>
									<?php endif; ?>
								</div>
								<?php if ( $profile->about_me ) : ?>
									<p class="wpmatch-profile-excerpt">
										<?php echo esc_html( wp_trim_words( $profile->about_me, 15 ) ); ?>
									</p>
								<?php endif; ?>
								<div class="wpmatch-profile-completion">
									<div class="wpmatch-completion-bar">
										<div class="wpmatch-completion-fill" style="width: <?php echo esc_attr( $profile->profile_completion ); ?>%"></div>
									</div>
									<span class="wpmatch-completion-text"><?php echo esc_html( $profile->profile_completion ); ?>% <?php esc_html_e( 'complete', 'wpmatch' ); ?></span>
								</div>
							</div>
							<div class="wpmatch-profile-actions">
								<button type="button" class="wpmatch-button secondary wpmatch-view-profile" data-user-id="<?php echo esc_attr( $profile->user_id ); ?>">
									<?php esc_html_e( 'View Profile', 'wpmatch' ); ?>
								</button>
								<button type="button" class="wpmatch-button primary wpmatch-send-message" data-user-id="<?php echo esc_attr( $profile->user_id ); ?>">
									<?php esc_html_e( 'Send Message', 'wpmatch' ); ?>
								</button>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="wpmatch-results-list" data-view="list">
					<?php foreach ( $search_results as $profile ) : ?>
						<div class="wpmatch-profile-row" data-user-id="<?php echo esc_attr( $profile->user_id ); ?>">
							<div class="wpmatch-profile-avatar">
								<?php if ( $profile->primary_photo ) : ?>
									<img src="<?php echo esc_url( $profile->primary_photo ); ?>" alt="<?php echo esc_attr( $profile->display_name ); ?>">
								<?php else : ?>
									<div class="wpmatch-no-photo">
										<span class="dashicons dashicons-admin-users"></span>
									</div>
								<?php endif; ?>
							</div>
							<div class="wpmatch-profile-details">
								<h3><?php echo esc_html( $profile->display_name ); ?></h3>
								<div class="wpmatch-profile-meta">
									<?php if ( $profile->age ) : ?>
										<span class="wpmatch-age"><?php echo esc_html( $profile->age ); ?> <?php esc_html_e( 'years old', 'wpmatch' ); ?></span>
									<?php endif; ?>
									<?php if ( $profile->location ) : ?>
										<span class="wpmatch-location"><?php echo esc_html( $profile->location ); ?></span>
									<?php endif; ?>
									<?php if ( isset( $profile->distance ) && $profile->distance ) : ?>
										<span class="wpmatch-distance"><?php echo esc_html( round( $profile->distance ) ); ?> <?php esc_html_e( 'miles away', 'wpmatch' ); ?></span>
									<?php endif; ?>
								</div>
								<?php if ( $profile->about_me ) : ?>
									<p class="wpmatch-profile-excerpt">
										<?php echo esc_html( wp_trim_words( $profile->about_me, 25 ) ); ?>
									</p>
								<?php endif; ?>
							</div>
							<div class="wpmatch-profile-actions">
								<button type="button" class="wpmatch-button secondary small wpmatch-view-profile" data-user-id="<?php echo esc_attr( $profile->user_id ); ?>">
									<?php esc_html_e( 'View', 'wpmatch' ); ?>
								</button>
								<button type="button" class="wpmatch-button primary small wpmatch-send-message" data-user-id="<?php echo esc_attr( $profile->user_id ); ?>">
									<?php esc_html_e( 'Message', 'wpmatch' ); ?>
								</button>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>

<!-- JavaScript for search functionality -->
<script type="text/javascript">
	// Pass data to JavaScript.
	var wpmatch_search = {
		ajax_url: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
		nonce: '<?php echo esc_js( wp_create_nonce( 'wpmatch_search_nonce' ) ); ?>',
		strings: {
			loading: '<?php echo esc_js( __( 'Searching...', 'wpmatch' ) ); ?>',
			no_suggestions: '<?php echo esc_js( __( 'No suggestions found', 'wpmatch' ) ); ?>',
			preferences_saved: '<?php echo esc_js( __( 'Search preferences saved!', 'wpmatch' ) ); ?>',
		}
	};
</script>