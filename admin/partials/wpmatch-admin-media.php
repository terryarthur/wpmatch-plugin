<?php
/**
 * Admin Media Management Interface
 *
 * Provides admin interface for reviewing and managing user media.
 *
 * @package WPMatch
 * @since 1.8.0
 */

// Security check
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check user permissions
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wpmatch' ) );
}

// Get media manager instance
$media_manager = null;
if ( class_exists( 'WPMatch_User_Media' ) ) {
	$media_manager = new WPMatch_User_Media( 'wpmatch', '1.8.0' );
}

// Handle bulk actions
if ( isset( $_POST['bulk_action'] ) && isset( $_POST['media_ids'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'bulk_media_action' ) ) {
	$action = sanitize_text_field( $_POST['bulk_action'] );
	$media_ids = array_map( 'absint', $_POST['media_ids'] );
	$results = array();

	foreach ( $media_ids as $media_id ) {
		switch ( $action ) {
			case 'approve':
				if ( $media_manager ) {
					$result = $media_manager->verify_media( $media_id );
					$results[] = ! is_wp_error( $result );
				}
				break;
			case 'reject':
				if ( $media_manager ) {
					$reason = sanitize_text_field( $_POST['rejection_reason'] ?? '' );
					$result = $media_manager->reject_media( $media_id, $reason );
					$results[] = ! is_wp_error( $result );
				}
				break;
			case 'delete':
				if ( $media_manager ) {
					$result = $media_manager->delete_media( $media_id );
					$results[] = ! is_wp_error( $result );
				}
				break;
		}
	}

	$success_count = count( array_filter( $results ) );
	$total_count = count( $media_ids );

	if ( $success_count > 0 ) {
		echo '<div class="notice notice-success is-dismissible"><p>';
		echo sprintf(
			esc_html__( '%d of %d media items processed successfully.', 'wpmatch' ),
			$success_count,
			$total_count
		);
		echo '</p></div>';
	}

	if ( $success_count < $total_count ) {
		echo '<div class="notice notice-error is-dismissible"><p>';
		echo sprintf(
			esc_html__( '%d media items failed to process.', 'wpmatch' ),
			$total_count - $success_count
		);
		echo '</p></div>';
	}
}

// Get filter parameters
$status_filter = sanitize_text_field( $_GET['status'] ?? 'all' );
$media_type_filter = sanitize_text_field( $_GET['media_type'] ?? 'all' );
$user_filter = absint( $_GET['user_id'] ?? 0 );
$page = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page = 20;

// Get media statistics
$stats = array(
	'total' => 0,
	'pending' => 0,
	'approved' => 0,
	'rejected' => 0,
	'photos' => 0,
	'videos' => 0,
);

global $wpdb;

// Get stats from database
$stats_query = $wpdb->get_results(
	"SELECT
		COUNT(*) as total,
		SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending,
		SUM(CASE WHEN verification_status = 'approved' THEN 1 ELSE 0 END) as approved,
		SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
		SUM(CASE WHEN media_type = 'photo' THEN 1 ELSE 0 END) as photos,
		SUM(CASE WHEN media_type = 'video' THEN 1 ELSE 0 END) as videos
	FROM {$wpdb->prefix}wpmatch_user_media"
);

if ( ! empty( $stats_query ) ) {
	$stats = (array) $stats_query[0];
}

// Build query for media list
$where_conditions = array( '1=1' );
$where_values = array();

if ( 'all' !== $status_filter ) {
	$where_conditions[] = 'verification_status = %s';
	$where_values[] = $status_filter;
}

if ( 'all' !== $media_type_filter ) {
	$where_conditions[] = 'media_type = %s';
	$where_values[] = $media_type_filter;
}

if ( $user_filter ) {
	$where_conditions[] = 'm.user_id = %d';
	$where_values[] = $user_filter;
}

$where_sql = implode( ' AND ', $where_conditions );
$offset = ( $page - 1 ) * $per_page;

// Get media records
$media_records = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT m.*, u.display_name, u.user_email, u.user_login
		FROM {$wpdb->prefix}wpmatch_user_media m
		LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
		WHERE {$where_sql}
		ORDER BY m.created_at DESC
		LIMIT %d OFFSET %d",
		array_merge( $where_values, array( $per_page, $offset ) )
	)
);

// Get total count for pagination
$total_items = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*)
		FROM {$wpdb->prefix}wpmatch_user_media m
		LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
		WHERE {$where_sql}",
		$where_values
	)
);

$total_pages = ceil( $total_items / $per_page );
?>

<div class="wrap wpmatch-admin-media">
	<h1>
		<?php esc_html_e( 'Media Management', 'wpmatch' ); ?>
		<span class="subtitle"><?php echo sprintf( esc_html__( '(%d total)', 'wpmatch' ), $stats['total'] ); ?></span>
	</h1>

	<!-- Statistics Cards -->
	<div class="media-stats">
		<div class="stat-card pending">
			<div class="stat-number"><?php echo absint( $stats['pending'] ); ?></div>
			<div class="stat-label"><?php esc_html_e( 'Pending Review', 'wpmatch' ); ?></div>
		</div>
		<div class="stat-card approved">
			<div class="stat-number"><?php echo absint( $stats['approved'] ); ?></div>
			<div class="stat-label"><?php esc_html_e( 'Approved', 'wpmatch' ); ?></div>
		</div>
		<div class="stat-card rejected">
			<div class="stat-number"><?php echo absint( $stats['rejected'] ); ?></div>
			<div class="stat-label"><?php esc_html_e( 'Rejected', 'wpmatch' ); ?></div>
		</div>
		<div class="stat-card photos">
			<div class="stat-number"><?php echo absint( $stats['photos'] ); ?></div>
			<div class="stat-label"><?php esc_html_e( 'Photos', 'wpmatch' ); ?></div>
		</div>
		<div class="stat-card videos">
			<div class="stat-number"><?php echo absint( $stats['videos'] ); ?></div>
			<div class="stat-label"><?php esc_html_e( 'Videos', 'wpmatch' ); ?></div>
		</div>
	</div>

	<!-- Filters -->
	<div class="tablenav top">
		<div class="alignleft actions">
			<form method="get" action="">
				<input type="hidden" name="page" value="<?php echo esc_attr( $_GET['page'] ); ?>">

				<select name="status">
					<option value="all"><?php esc_html_e( 'All Statuses', 'wpmatch' ); ?></option>
					<option value="pending" <?php selected( $status_filter, 'pending' ); ?>><?php esc_html_e( 'Pending', 'wpmatch' ); ?></option>
					<option value="approved" <?php selected( $status_filter, 'approved' ); ?>><?php esc_html_e( 'Approved', 'wpmatch' ); ?></option>
					<option value="rejected" <?php selected( $status_filter, 'rejected' ); ?>><?php esc_html_e( 'Rejected', 'wpmatch' ); ?></option>
				</select>

				<select name="media_type">
					<option value="all"><?php esc_html_e( 'All Types', 'wpmatch' ); ?></option>
					<option value="photo" <?php selected( $media_type_filter, 'photo' ); ?>><?php esc_html_e( 'Photos', 'wpmatch' ); ?></option>
					<option value="video" <?php selected( $media_type_filter, 'video' ); ?>><?php esc_html_e( 'Videos', 'wpmatch' ); ?></option>
				</select>

				<?php if ( $user_filter ): ?>
				<input type="hidden" name="user_id" value="<?php echo absint( $user_filter ); ?>">
				<span class="filter-info">
					<?php echo sprintf( esc_html__( 'Filtered by user ID: %d', 'wpmatch' ), $user_filter ); ?>
					<a href="<?php echo esc_url( remove_query_arg( 'user_id' ) ); ?>" class="remove-filter">×</a>
				</span>
				<?php endif; ?>

				<?php submit_button( __( 'Filter', 'wpmatch' ), 'secondary', 'filter_action', false ); ?>
			</form>
		</div>

		<!-- Pagination -->
		<?php if ( $total_pages > 1 ): ?>
		<div class="tablenav-pages">
			<span class="displaying-num">
				<?php echo sprintf( esc_html__( '%d items', 'wpmatch' ), $total_items ); ?>
			</span>
			<span class="pagination-links">
				<?php
				$page_links = paginate_links( array(
					'base' => add_query_arg( 'paged', '%#%' ),
					'format' => '',
					'prev_text' => __( '&laquo; Previous' ),
					'next_text' => __( 'Next &raquo;' ),
					'total' => $total_pages,
					'current' => $page,
					'type' => 'array'
				) );

				if ( $page_links ) {
					echo implode( "\n", $page_links );
				}
				?>
			</span>
		</div>
		<?php endif; ?>
	</div>

	<!-- Media List -->
	<form method="post" id="media-list-form">
		<?php wp_nonce_field( 'bulk_media_action' ); ?>

		<?php if ( empty( $media_records ) ): ?>
		<div class="no-media-found">
			<div class="no-media-icon">
				<span class="dashicons dashicons-format-image"></span>
			</div>
			<h3><?php esc_html_e( 'No media found', 'wpmatch' ); ?></h3>
			<p><?php esc_html_e( 'No media matches your current filters.', 'wpmatch' ); ?></p>
		</div>
		<?php else: ?>

		<!-- Bulk Actions -->
		<div class="tablenav top">
			<div class="alignleft actions bulkactions">
				<select name="bulk_action" id="bulk-action-selector-top">
					<option value=""><?php esc_html_e( 'Bulk Actions', 'wpmatch' ); ?></option>
					<option value="approve"><?php esc_html_e( 'Approve', 'wpmatch' ); ?></option>
					<option value="reject"><?php esc_html_e( 'Reject', 'wpmatch' ); ?></option>
					<option value="delete"><?php esc_html_e( 'Delete', 'wpmatch' ); ?></option>
				</select>

				<div class="rejection-reason" style="display:none;">
					<input type="text" name="rejection_reason" placeholder="<?php esc_attr_e( 'Reason for rejection (optional)', 'wpmatch' ); ?>">
				</div>

				<?php submit_button( __( 'Apply', 'wpmatch' ), 'action', 'bulk_apply', false ); ?>
			</div>
		</div>

		<!-- Media Grid -->
		<div class="media-grid">
			<?php foreach ( $media_records as $media ): ?>
			<div class="media-item <?php echo esc_attr( $media->media_type ); ?> <?php echo esc_attr( $media->verification_status ); ?>"
				 data-media-id="<?php echo absint( $media->media_id ); ?>">

				<div class="media-checkbox">
					<input type="checkbox" name="media_ids[]" value="<?php echo absint( $media->media_id ); ?>" id="media-<?php echo absint( $media->media_id ); ?>">
					<label for="media-<?php echo absint( $media->media_id ); ?>"></label>
				</div>

				<div class="media-thumbnail">
					<?php
					$upload_dir = wp_upload_dir();
					$media_url = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $media->file_path );
					?>

					<?php if ( 'photo' === $media->media_type ): ?>
						<img src="<?php echo esc_url( $media_url ); ?>" alt="<?php esc_attr_e( 'User photo', 'wpmatch' ); ?>" loading="lazy">
					<?php else: ?>
						<video src="<?php echo esc_url( $media_url ); ?>" preload="metadata" muted></video>
						<div class="play-overlay">
							<span class="dashicons dashicons-controls-play"></span>
						</div>
					<?php endif; ?>

					<div class="media-status <?php echo esc_attr( $media->verification_status ); ?>">
						<?php if ( 'pending' === $media->verification_status ): ?>
							<span class="dashicons dashicons-clock"></span>
						<?php elseif ( 'approved' === $media->verification_status ): ?>
							<span class="dashicons dashicons-yes-alt"></span>
						<?php else: ?>
							<span class="dashicons dashicons-dismiss"></span>
						<?php endif; ?>
					</div>

					<?php if ( $media->is_primary ): ?>
					<div class="primary-badge">
						<span class="dashicons dashicons-star-filled"></span>
					</div>
					<?php endif; ?>
				</div>

				<div class="media-info">
					<div class="media-details">
						<strong>
							<?php if ( $media->display_name ): ?>
								<?php echo esc_html( $media->display_name ); ?>
							<?php else: ?>
								<?php echo esc_html( $media->user_login ); ?>
							<?php endif; ?>
						</strong>
						<small><?php echo esc_html( $media->user_email ); ?></small>
					</div>

					<div class="media-meta">
						<div class="meta-row">
							<span class="label"><?php esc_html_e( 'Type:', 'wpmatch' ); ?></span>
							<span class="value"><?php echo esc_html( ucfirst( $media->media_type ) ); ?></span>
						</div>
						<div class="meta-row">
							<span class="label"><?php esc_html_e( 'Size:', 'wpmatch' ); ?></span>
							<span class="value"><?php echo esc_html( size_format( $media->file_size ) ); ?></span>
						</div>
						<div class="meta-row">
							<span class="label"><?php esc_html_e( 'Status:', 'wpmatch' ); ?></span>
							<span class="value status-<?php echo esc_attr( $media->verification_status ); ?>">
								<?php echo esc_html( ucfirst( $media->verification_status ) ); ?>
							</span>
						</div>
						<div class="meta-row">
							<span class="label"><?php esc_html_e( 'Uploaded:', 'wpmatch' ); ?></span>
							<span class="value"><?php echo esc_html( mysql2date( 'M j, Y g:i A', $media->created_at ) ); ?></span>
						</div>
					</div>

					<div class="media-actions">
						<?php if ( 'pending' === $media->verification_status ): ?>
						<button type="button" class="button button-primary approve-media" data-media-id="<?php echo absint( $media->media_id ); ?>">
							<span class="dashicons dashicons-yes"></span>
							<?php esc_html_e( 'Approve', 'wpmatch' ); ?>
						</button>
						<button type="button" class="button reject-media" data-media-id="<?php echo absint( $media->media_id ); ?>">
							<span class="dashicons dashicons-no"></span>
							<?php esc_html_e( 'Reject', 'wpmatch' ); ?>
						</button>
						<?php endif; ?>

						<button type="button" class="button view-fullsize" data-url="<?php echo esc_url( $media_url ); ?>" data-type="<?php echo esc_attr( $media->media_type ); ?>">
							<span class="dashicons dashicons-search"></span>
							<?php esc_html_e( 'View', 'wpmatch' ); ?>
						</button>

						<button type="button" class="button delete-media" data-media-id="<?php echo absint( $media->media_id ); ?>">
							<span class="dashicons dashicons-trash"></span>
							<?php esc_html_e( 'Delete', 'wpmatch' ); ?>
						</button>

						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpmatch-users&user=' . $media->user_id ) ); ?>" class="button">
							<span class="dashicons dashicons-admin-users"></span>
							<?php esc_html_e( 'User Profile', 'wpmatch' ); ?>
						</a>
					</div>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
	</form>

	<!-- Bottom Pagination -->
	<?php if ( $total_pages > 1 ): ?>
	<div class="tablenav bottom">
		<div class="tablenav-pages">
			<span class="displaying-num">
				<?php echo sprintf( esc_html__( '%d items', 'wpmatch' ), $total_items ); ?>
			</span>
			<span class="pagination-links">
				<?php
				if ( $page_links ) {
					echo implode( "\n", $page_links );
				}
				?>
			</span>
		</div>
	</div>
	<?php endif; ?>
</div>

<!-- Modal for viewing media -->
<div id="media-modal" class="media-modal" style="display: none;">
	<div class="modal-backdrop"></div>
	<div class="modal-content">
		<div class="modal-header">
			<h3><?php esc_html_e( 'Media Preview', 'wpmatch' ); ?></h3>
			<button type="button" class="modal-close">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>
		<div class="modal-body">
			<!-- Dynamic content will be inserted here -->
		</div>
	</div>
</div>

<!-- Rejection Reason Modal -->
<div id="rejection-modal" class="media-modal" style="display: none;">
	<div class="modal-backdrop"></div>
	<div class="modal-content small">
		<div class="modal-header">
			<h3><?php esc_html_e( 'Reject Media', 'wpmatch' ); ?></h3>
			<button type="button" class="modal-close">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>
		<div class="modal-body">
			<form id="rejection-form">
				<p>
					<label for="rejection-reason"><?php esc_html_e( 'Reason for rejection (optional):', 'wpmatch' ); ?></label>
					<textarea name="rejection_reason" id="rejection-reason" rows="4" style="width: 100%;"></textarea>
				</p>
				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Reject Media', 'wpmatch' ); ?></button>
					<button type="button" class="button modal-close"><?php esc_html_e( 'Cancel', 'wpmatch' ); ?></button>
				</p>
			</form>
		</div>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// Handle bulk action selection
	$('#bulk-action-selector-top').on('change', function() {
		const action = $(this).val();
		if (action === 'reject') {
			$('.rejection-reason').show();
		} else {
			$('.rejection-reason').hide();
		}
	});

	// Handle individual approve/reject actions
	$('.approve-media').on('click', function() {
		const mediaId = $(this).data('media-id');
		performMediaAction('approve', mediaId);
	});

	$('.reject-media').on('click', function() {
		const mediaId = $(this).data('media-id');
		showRejectionModal(mediaId);
	});

	// Handle delete actions
	$('.delete-media').on('click', function() {
		const mediaId = $(this).data('media-id');
		if (confirm('<?php echo esc_js( __( 'Are you sure you want to delete this media? This action cannot be undone.', 'wpmatch' ) ); ?>')) {
			performMediaAction('delete', mediaId);
		}
	});

	// Handle view fullsize
	$('.view-fullsize').on('click', function() {
		const url = $(this).data('url');
		const type = $(this).data('type');
		showMediaModal(url, type);
	});

	// Modal functions
	function showMediaModal(url, type) {
		const modalBody = $('#media-modal .modal-body');
		if (type === 'photo') {
			modalBody.html('<img src="' + url + '" style="max-width: 100%; height: auto;">');
		} else {
			modalBody.html('<video controls style="max-width: 100%; height: auto;"><source src="' + url + '" type="video/mp4"></video>');
		}
		$('#media-modal').fadeIn();
	}

	function showRejectionModal(mediaId) {
		$('#rejection-modal').fadeIn();
		$('#rejection-form').data('media-id', mediaId);
	}

	// Handle rejection form submission
	$('#rejection-form').on('submit', function(e) {
		e.preventDefault();
		const mediaId = $(this).data('media-id');
		const reason = $('#rejection-reason').val();
		performMediaAction('reject', mediaId, reason);
		$('#rejection-modal').fadeOut();
	});

	// Close modals
	$('.modal-close, .modal-backdrop').on('click', function() {
		$('.media-modal').fadeOut();
	});

	// Perform AJAX actions
	function performMediaAction(action, mediaId, reason) {
		const data = {
			action: 'wpmatch_admin_' + action + '_media',
			media_id: mediaId,
			nonce: '<?php echo wp_create_nonce( "wpmatch_admin_media_action" ); ?>'
		};

		if (reason) {
			data.reason = reason;
		}

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: data,
			success: function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert('<?php echo esc_js( __( 'Action failed. Please try again.', 'wpmatch' ) ); ?>');
				}
			},
			error: function() {
				alert('<?php echo esc_js( __( 'Action failed. Please try again.', 'wpmatch' ) ); ?>');
			}
		});
	}

	// Handle select all checkbox
	$('#cb-select-all-1').on('change', function() {
		$('input[name="media_ids[]"]').prop('checked', $(this).is(':checked'));
	});
});
</script>