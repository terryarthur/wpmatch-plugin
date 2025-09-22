<?php
/**
 * Events Dashboard Template
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

<div class="wpmatch-events-dashboard">
	<!-- Header -->
	<div class="events-header">
		<h2><?php esc_html_e( 'Dating Events', 'wpmatch' ); ?></h2>
		<p class="events-subtitle"><?php esc_html_e( 'Join exciting events and meet new people', 'wpmatch' ); ?></p>
	</div>

	<!-- Event Stats -->
	<div class="event-stats">
		<div class="stat-card">
			<div class="stat-value" id="upcoming-events-count">0</div>
			<div class="stat-label"><?php esc_html_e( 'Upcoming Events', 'wpmatch' ); ?></div>
		</div>
		<div class="stat-card">
			<div class="stat-value" id="registered-events-count">0</div>
			<div class="stat-label"><?php esc_html_e( 'Registered', 'wpmatch' ); ?></div>
		</div>
		<div class="stat-card">
			<div class="stat-value" id="completed-events-count">0</div>
			<div class="stat-label"><?php esc_html_e( 'Attended', 'wpmatch' ); ?></div>
		</div>
		<div class="stat-card">
			<div class="stat-value" id="hosted-events-count">0</div>
			<div class="stat-label"><?php esc_html_e( 'Hosted', 'wpmatch' ); ?></div>
		</div>
	</div>

	<!-- Event Filters -->
	<div class="events-filters">
		<button class="event-filter-btn active" data-filter="upcoming"><?php esc_html_e( 'Upcoming', 'wpmatch' ); ?></button>
		<button class="event-filter-btn" data-filter="ongoing"><?php esc_html_e( 'Live Now', 'wpmatch' ); ?></button>
		<button class="event-filter-btn" data-filter="speed_dating"><?php esc_html_e( 'Speed Dating', 'wpmatch' ); ?></button>
		<button class="event-filter-btn" data-filter="virtual"><?php esc_html_e( 'Virtual', 'wpmatch' ); ?></button>
		<button class="event-filter-btn" data-filter="my_events"><?php esc_html_e( 'My Events', 'wpmatch' ); ?></button>
	</div>

	<!-- Events Grid -->
	<div class="events-grid">
		<!-- Events will be loaded via AJAX -->
		<div class="loading-events">
			<div class="loading-spinner"></div>
			<p><?php esc_html_e( 'Loading events...', 'wpmatch' ); ?></p>
		</div>
	</div>

	<!-- My Events Section -->
	<div class="my-events-section" style="display: none;">
		<div class="section-header">
			<h3><?php esc_html_e( 'My Events', 'wpmatch' ); ?></h3>
		</div>
		<div class="my-events-tabs">
			<button class="my-events-tab active" data-type="registered"><?php esc_html_e( 'Registered', 'wpmatch' ); ?></button>
			<button class="my-events-tab" data-type="hosted"><?php esc_html_e( 'Hosted', 'wpmatch' ); ?></button>
			<button class="my-events-tab" data-type="attended"><?php esc_html_e( 'Attended', 'wpmatch' ); ?></button>
		</div>
		<div class="my-events-content">
			<!-- User's events will be loaded here -->
		</div>
	</div>

	<!-- Create Event Button -->
	<?php if ( current_user_can( 'wpmatch_create_events' ) || current_user_can( 'manage_options' ) ) : ?>
		<div class="create-event-section">
			<button class="event-btn event-btn-primary create-event-btn">
				<?php esc_html_e( 'Create New Event', 'wpmatch' ); ?>
			</button>
		</div>
	<?php endif; ?>
</div>

<!-- Create Event Form Modal (Hidden by default) -->
<div class="create-event-modal" style="display: none;">
	<div class="create-event-form">
		<form class="create-event-form" enctype="multipart/form-data">
			<div class="form-section">
				<h3><?php esc_html_e( 'Basic Information', 'wpmatch' ); ?></h3>

				<div class="form-row">
					<div class="form-group">
						<label class="form-label" for="event-title"><?php esc_html_e( 'Event Title', 'wpmatch' ); ?> *</label>
						<input type="text" id="event-title" name="title" class="form-input" required>
					</div>
					<div class="form-group">
						<label class="form-label" for="event-type"><?php esc_html_e( 'Event Type', 'wpmatch' ); ?> *</label>
						<select id="event-type" name="event_type" class="form-select" required>
							<option value=""><?php esc_html_e( 'Select type...', 'wpmatch' ); ?></option>
							<option value="speed_dating"><?php esc_html_e( 'Speed Dating', 'wpmatch' ); ?></option>
							<option value="virtual_meetup"><?php esc_html_e( 'Virtual Meetup', 'wpmatch' ); ?></option>
							<option value="group_activity"><?php esc_html_e( 'Group Activity', 'wpmatch' ); ?></option>
							<option value="workshop"><?php esc_html_e( 'Workshop', 'wpmatch' ); ?></option>
							<option value="social_mixer"><?php esc_html_e( 'Social Mixer', 'wpmatch' ); ?></option>
						</select>
					</div>
				</div>

				<div class="form-group full-width">
					<label class="form-label" for="event-description"><?php esc_html_e( 'Description', 'wpmatch' ); ?> *</label>
					<textarea id="event-description" name="description" class="form-textarea" required></textarea>
				</div>
			</div>

			<div class="form-section">
				<h3><?php esc_html_e( 'Date & Time', 'wpmatch' ); ?></h3>

				<div class="form-row">
					<div class="form-group">
						<label class="form-label" for="start-time"><?php esc_html_e( 'Start Date & Time', 'wpmatch' ); ?> *</label>
						<input type="datetime-local" id="start-time" name="start_time" class="form-input" required>
					</div>
					<div class="form-group">
						<label class="form-label" for="duration"><?php esc_html_e( 'Duration (minutes)', 'wpmatch' ); ?> *</label>
						<input type="number" id="duration" name="duration_minutes" class="form-input" min="15" max="480" required>
					</div>
				</div>
			</div>

			<div class="form-section">
				<h3><?php esc_html_e( 'Location & Participants', 'wpmatch' ); ?></h3>

				<div class="form-row">
					<div class="form-group">
						<label class="form-label" for="location"><?php esc_html_e( 'Location', 'wpmatch' ); ?></label>
						<input type="text" id="location" name="location" class="form-input">
					</div>
					<div class="form-group">
						<label class="form-label" for="max-participants"><?php esc_html_e( 'Max Participants', 'wpmatch' ); ?> *</label>
						<input type="number" id="max-participants" name="max_participants" class="form-input" min="2" max="100" required>
					</div>
				</div>

				<div class="form-group full-width">
					<label class="form-label" for="requirements"><?php esc_html_e( 'Requirements', 'wpmatch' ); ?></label>
					<textarea id="requirements" name="requirements" class="form-textarea" placeholder="<?php esc_attr_e( 'Any special requirements or instructions...', 'wpmatch' ); ?>"></textarea>
				</div>
			</div>

			<div class="form-section">
				<h3><?php esc_html_e( 'Additional Options', 'wpmatch' ); ?></h3>

				<div class="form-checkbox">
					<input type="checkbox" id="is-featured" name="is_featured" value="1">
					<label for="is-featured"><?php esc_html_e( 'Feature this event', 'wpmatch' ); ?></label>
				</div>

				<div class="form-checkbox">
					<input type="checkbox" id="requires-approval" name="requires_approval" value="1">
					<label for="requires-approval"><?php esc_html_e( 'Require approval for registration', 'wpmatch' ); ?></label>
				</div>

				<div class="form-group">
					<label class="form-label" for="event-image"><?php esc_html_e( 'Event Image', 'wpmatch' ); ?></label>
					<input type="file" id="event-image" name="event_image" class="form-input" accept="image/*">
				</div>
			</div>

			<div class="form-actions">
				<button type="button" class="event-btn event-btn-secondary cancel-create-btn"><?php esc_html_e( 'Cancel', 'wpmatch' ); ?></button>
				<button type="submit" class="event-btn event-btn-primary"><?php esc_html_e( 'Create Event', 'wpmatch' ); ?></button>
			</div>
		</form>
	</div>
</div>

<script type="text/javascript">
// Localize script data
var wpmatch_events = {
	rest_url: '<?php echo esc_url( rest_url() ); ?>',
	nonce: '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>',
	current_user_id: <?php echo esc_js( $current_user_id ); ?>,
	default_avatar: '<?php echo esc_url( get_avatar_url( $current_user_id, array( 'size' => 40 ) ) ); ?>',
	strings: {
		loading: '<?php esc_html_e( 'Loading...', 'wpmatch' ); ?>',
		error: '<?php esc_html_e( 'Error loading data', 'wpmatch' ); ?>',
		retry: '<?php esc_html_e( 'Try Again', 'wpmatch' ); ?>',
		no_events: '<?php esc_html_e( 'No events found', 'wpmatch' ); ?>',
		registering: '<?php esc_html_e( 'Registering...', 'wpmatch' ); ?>',
		cancelling: '<?php esc_html_e( 'Cancelling...', 'wpmatch' ); ?>',
		creating: '<?php esc_html_e( 'Creating...', 'wpmatch' ); ?>',
		registration_success: '<?php esc_html_e( 'Successfully registered for event!', 'wpmatch' ); ?>',
		registration_error: '<?php esc_html_e( 'Failed to register for event', 'wpmatch' ); ?>',
		cancellation_success: '<?php esc_html_e( 'Registration cancelled successfully', 'wpmatch' ); ?>',
		cancellation_error: '<?php esc_html_e( 'Failed to cancel registration', 'wpmatch' ); ?>',
		create_success: '<?php esc_html_e( 'Event created successfully!', 'wpmatch' ); ?>',
		create_error: '<?php esc_html_e( 'Failed to create event', 'wpmatch' ); ?>',
		cancel_confirm: '<?php esc_html_e( 'Are you sure you want to cancel your registration?', 'wpmatch' ); ?>',
		validation_error: '<?php esc_html_e( 'Please fill in all required fields', 'wpmatch' ); ?>'
	}
};
</script>