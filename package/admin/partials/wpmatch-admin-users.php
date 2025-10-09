<?php
/**
 * Admin users view with comprehensive user management
 *
 * @package WPMatch
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get user statistics.
global $wpdb;

// Get total users with profiles.
$profile_table = $wpdb->prefix . 'wpmatch_user_profiles';
$total_users   = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wpmatch_user_profiles" );

// Get users by status.
$active_users  = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wpmatch_user_profiles WHERE status = %s", 'active' ) );
$pending_users = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wpmatch_user_profiles WHERE status = %s", 'pending' ) );
$blocked_users = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wpmatch_user_profiles WHERE status = %s", 'blocked' ) );

// Get recent registrations (last 7 days).
$recent_users = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}wpmatch_user_profiles WHERE created_at >= %s",
		gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) )
	)
);

// Get verified users.
$verified_users = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wpmatch_user_profiles WHERE is_verified = %d", 1 ) );

// Handle filters and pagination.
$status_filter  = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
$search_query   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$items_per_page = 20;
$current_page   = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$offset         = ( $current_page - 1 ) * $items_per_page;

// Build query.
$where_conditions = array( '1=1' );
$query_params     = array();

if ( $status_filter ) {
	$where_conditions[] = 'p.status = %s';
	$query_params[]     = $status_filter;
}

if ( $search_query ) {
	$where_conditions[] = '(u.display_name LIKE %s OR u.user_email LIKE %s OR p.location LIKE %s)';
	$query_params[]     = '%' . $wpdb->esc_like( $search_query ) . '%';
	$query_params[]     = '%' . $wpdb->esc_like( $search_query ) . '%';
	$query_params[]     = '%' . $wpdb->esc_like( $search_query ) . '%';
}

$where_clause = implode( ' AND ', $where_conditions );

// Get users for current page.
$users_query = "
	SELECT p.*, u.display_name, u.user_email, u.user_registered
	FROM {$wpdb->prefix}wpmatch_user_profiles p
	LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
	WHERE {$where_clause}
	ORDER BY p.created_at DESC
	LIMIT %d OFFSET %d
";

$query_params[] = $items_per_page;
$query_params[] = $offset;

$users = $wpdb->get_results( $wpdb->prepare( $users_query, ...$query_params ) );

// Get total count for pagination.
$count_query = "
	SELECT COUNT(*)
	FROM {$wpdb->prefix}wpmatch_user_profiles p
	LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
	WHERE {$where_clause}
";

// Remove limit and offset params for count query.
if ( count( $query_params ) > 2 ) {
	$count_params   = array_slice( $query_params, 0, -2 );
	$total_filtered = $wpdb->get_var( $wpdb->prepare( $count_query, ...$count_params ) );
} else {
	$total_filtered = $wpdb->get_var( $count_query );
}

$total_pages = ceil( $total_filtered / $items_per_page );
?>

<div class="wrap wpmatch-admin">
	<!-- Admin Header -->
	<div class="wpmatch-admin-header">
		<div class="wpmatch-header-content">
			<div class="wpmatch-header-main">
				<h1>
					<span class="dashicons dashicons-admin-users"></span>
					<?php esc_html_e( 'User Management', 'wpmatch' ); ?>
				</h1>
				<p><?php esc_html_e( 'Manage your dating community members, moderate profiles, and monitor user activity. Build a safe and engaging dating environment.', 'wpmatch' ); ?></p>
			</div>
			<div class="wpmatch-header-actions">
				<a href="#" class="wpmatch-button secondary" onclick="showUserModal()">
					<span class="dashicons dashicons-plus-alt"></span>
					<?php esc_html_e( 'Add User', 'wpmatch' ); ?>
				</a>
				<a href="#" class="wpmatch-button wpmatch-button-upgrade">
					<span class="dashicons dashicons-star-filled"></span>
					<?php esc_html_e( 'Upgrade Pro', 'wpmatch' ); ?>
				</a>
			</div>
		</div>
		<div class="wpmatch-upgrade-notice">
			<div class="wpmatch-upgrade-content">
				<span class="dashicons dashicons-info"></span>
				<div class="wpmatch-upgrade-text">
					<strong><?php esc_html_e( 'Pro Features Available', 'wpmatch' ); ?>:</strong>
					<?php esc_html_e( 'Advanced moderation tools, bulk operations, automated safety filters, and detailed user analytics.', 'wpmatch' ); ?>
				</div>
				<a href="#" class="wpmatch-upgrade-link"><?php esc_html_e( 'Learn More', 'wpmatch' ); ?></a>
			</div>
		</div>
	</div>

	<!-- Statistics Dashboard -->
	<div class="wpmatch-stats-grid">
		<div class="wpmatch-stat-card">
			<div class="wpmatch-stat-header">
				<span class="dashicons dashicons-admin-users"></span>
				<span class="wpmatch-stat-label"><?php esc_html_e( 'Total Users', 'wpmatch' ); ?></span>
			</div>
			<div class="wpmatch-stat-number"><?php echo esc_html( number_format( $total_users ? $total_users : 0 ) ); ?></div>
			<div class="wpmatch-stat-change">
				<span class="dashicons dashicons-admin-users"></span>
				<?php esc_html_e( 'All registered users', 'wpmatch' ); ?>
			</div>
		</div>

		<div class="wpmatch-stat-card">
			<div class="wpmatch-stat-header">
				<span class="dashicons dashicons-yes-alt"></span>
				<span class="wpmatch-stat-label"><?php esc_html_e( 'Active Users', 'wpmatch' ); ?></span>
			</div>
			<div class="wpmatch-stat-number"><?php echo esc_html( number_format( $active_users ? $active_users : 0 ) ); ?></div>
			<div class="wpmatch-stat-change positive">
				<span class="dashicons dashicons-arrow-up-alt"></span>
				<?php esc_html_e( 'Currently active', 'wpmatch' ); ?>
			</div>
		</div>

		<div class="wpmatch-stat-card">
			<div class="wpmatch-stat-header">
				<span class="dashicons dashicons-awards"></span>
				<span class="wpmatch-stat-label"><?php esc_html_e( 'Verified Users', 'wpmatch' ); ?></span>
			</div>
			<div class="wpmatch-stat-number"><?php echo esc_html( number_format( $verified_users ? $verified_users : 0 ) ); ?></div>
			<div class="wpmatch-stat-change positive">
				<span class="dashicons dashicons-yes"></span>
				<?php esc_html_e( 'Identity confirmed', 'wpmatch' ); ?>
			</div>
		</div>

		<div class="wpmatch-stat-card">
			<div class="wpmatch-stat-header">
				<span class="dashicons dashicons-calendar-alt"></span>
				<span class="wpmatch-stat-label"><?php esc_html_e( 'New This Week', 'wpmatch' ); ?></span>
			</div>
			<div class="wpmatch-stat-number"><?php echo esc_html( number_format( $recent_users ? $recent_users : 0 ) ); ?></div>
			<div class="wpmatch-stat-change positive">
				<span class="dashicons dashicons-arrow-up-alt"></span>
				<?php esc_html_e( 'Recent signups', 'wpmatch' ); ?>
			</div>
		</div>
	</div>

	<!-- Users Table -->
	<div class="wpmatch-users-table-container">
		<?php if ( ! empty( $users ) ) : ?>
			<div class="wpmatch-table-toolbar">
				<div class="wpmatch-filters-inline">
					<span class="directory-title"><?php esc_html_e( 'User Directory', 'wpmatch' ); ?></span>
					<div class="wpmatch-filter-tabs-inline">
						<a href="<?php echo esc_url( remove_query_arg( 'status' ) ); ?>" class="filter-tab-inline <?php echo empty( $status_filter ) ? 'active' : ''; ?>">
							<?php esc_html_e( 'All Users', 'wpmatch' ); ?>
							<span class="tab-count"><?php echo esc_html( $total_users ); ?></span>
						</a>
						<a href="<?php echo esc_url( add_query_arg( 'status', 'active' ) ); ?>" class="filter-tab-inline <?php echo 'active' === $status_filter ? 'active' : ''; ?>">
							<?php esc_html_e( 'Active', 'wpmatch' ); ?>
							<span class="tab-count"><?php echo esc_html( $active_users ); ?></span>
						</a>
						<a href="<?php echo esc_url( add_query_arg( 'status', 'pending' ) ); ?>" class="filter-tab-inline <?php echo 'pending' === $status_filter ? 'active' : ''; ?>">
							<?php esc_html_e( 'Pending', 'wpmatch' ); ?>
							<span class="tab-count"><?php echo esc_html( $pending_users ); ?></span>
						</a>
						<a href="<?php echo esc_url( add_query_arg( 'status', 'blocked' ) ); ?>" class="filter-tab-inline <?php echo 'blocked' === $status_filter ? 'active' : ''; ?>">
							<?php esc_html_e( 'Blocked', 'wpmatch' ); ?>
							<span class="tab-count"><?php echo esc_html( $blocked_users ); ?></span>
						</a>
					</div>
				</div>
				<div class="showing-results">
					<?php
					printf(
						/* translators: 1: first result number, 2: last result number, 3: total results */
						esc_html__( 'Showing %1$d-%2$d of %3$d users', 'wpmatch' ),
						esc_html( ( ( $current_page - 1 ) * $items_per_page ) + 1 ),
						esc_html( min( $current_page * $items_per_page, $total_filtered ) ),
						esc_html( $total_filtered )
					);
					?>
				</div>
				<div class="wpmatch-search-box-inline">
					<form method="get" action="">
						<?php foreach ( $_GET as $key => $value ) : ?>
							<?php if ( 's' !== $key ) : ?>
								<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>">
							<?php endif; ?>
						<?php endforeach; ?>
						<input type="search" name="s" value="<?php echo esc_attr( $search_query ); ?>" placeholder="<?php esc_attr_e( 'Search users...', 'wpmatch' ); ?>" class="search-input-compact">
						<button type="submit" class="search-button-compact">
							<span class="dashicons dashicons-search"></span>
						</button>
					</form>
				</div>
			</div>

			<div class="wpmatch-bulk-actions-bar">
				<div class="bulk-actions">
					<select name="bulk_action" id="bulk-action-select">
						<option value=""><?php esc_html_e( 'Bulk Actions', 'wpmatch' ); ?></option>
						<option value="activate"><?php esc_html_e( 'Activate', 'wpmatch' ); ?></option>
						<option value="deactivate"><?php esc_html_e( 'Deactivate', 'wpmatch' ); ?></option>
						<option value="verify"><?php esc_html_e( 'Verify', 'wpmatch' ); ?></option>
						<option value="block"><?php esc_html_e( 'Block', 'wpmatch' ); ?></option>
					</select>
					<button type="button" class="wpmatch-button secondary" onclick="performBulkAction()"><?php esc_html_e( 'Apply', 'wpmatch' ); ?></button>
				</div>
			</div>

			<table class="wpmatch-users-table">
				<thead>
					<tr>
						<th class="check-column">
							<input type="checkbox" id="select-all-users">
						</th>
						<th><?php esc_html_e( 'User', 'wpmatch' ); ?></th>
						<th><?php esc_html_e( 'Profile Info', 'wpmatch' ); ?></th>
						<th><?php esc_html_e( 'Status', 'wpmatch' ); ?></th>
						<th><?php esc_html_e( 'Joined', 'wpmatch' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'wpmatch' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $users as $user ) : ?>
						<?php
						$avatar_url     = $user->profile_picture ? esc_url( $user->profile_picture ) : get_avatar_url( $user->user_id, array( 'size' => 50 ) );
						$user_wordpress = get_user_by( 'ID', $user->user_id );
						$joined_date    = human_time_diff( strtotime( $user->user_registered ) );
						?>
						<tr data-user-id="<?php echo esc_attr( $user->user_id ); ?>">
							<th class="check-column">
								<input type="checkbox" name="user_ids[]" value="<?php echo esc_attr( $user->user_id ); ?>" class="user-checkbox">
							</th>
							<td class="user-info">
								<div class="user-avatar">
									<img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $user->display_name ); ?>" class="avatar">
									<?php if ( $user->is_verified ) : ?>
										<span class="verified-badge" title="<?php esc_attr_e( 'Verified User', 'wpmatch' ); ?>">✓</span>
									<?php endif; ?>
								</div>
								<div class="user-details">
									<strong class="user-name">
										<a href="#" onclick="showUserProfile(<?php echo esc_attr( $user->user_id ); ?>)">
											<?php echo esc_html( $user->display_name ); ?>
										</a>
									</strong>
									<div class="user-email"><?php echo esc_html( $user->user_email ); ?></div>
									<div class="user-id">ID: <?php echo esc_html( $user->user_id ); ?></div>
								</div>
							</td>
							<td class="profile-info">
								<div class="profile-details">
									<?php if ( $user->age ) : ?>
										<span class="detail-item">
											<span class="dashicons dashicons-calendar-alt"></span>
											<?php echo esc_html( $user->age ); ?> <?php esc_html_e( 'years old', 'wpmatch' ); ?>
										</span>
									<?php endif; ?>
									<?php if ( $user->location ) : ?>
										<span class="detail-item">
											<span class="dashicons dashicons-location"></span>
											<?php echo esc_html( $user->location ); ?>
										</span>
									<?php endif; ?>
									<?php if ( $user->profession ) : ?>
										<span class="detail-item">
											<span class="dashicons dashicons-businessman"></span>
											<?php echo esc_html( $user->profession ); ?>
										</span>
									<?php endif; ?>
								</div>
							</td>
							<td class="user-status">
								<span class="status-badge status-<?php echo esc_attr( $user->status ); ?>">
									<?php echo esc_html( ucfirst( $user->status ) ); ?>
								</span>
								<?php if ( $user->is_verified ) : ?>
									<span class="status-badge status-verified"><?php esc_html_e( 'Verified', 'wpmatch' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="joined-date">
								<?php
								/* translators: %s: time ago */
								printf( esc_html__( '%s ago', 'wpmatch' ), esc_html( $joined_date ) );
								?>
								<div class="exact-date"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $user->user_registered ) ) ); ?></div>
							</td>
							<td class="user-actions">
								<div class="action-buttons">
									<button type="button" class="action-btn view-btn" onclick="showUserProfile(<?php echo esc_attr( $user->user_id ); ?>)" title="<?php esc_attr_e( 'View Profile', 'wpmatch' ); ?>">
										<span class="dashicons dashicons-visibility"></span>
									</button>
									<button type="button" class="action-btn edit-btn" onclick="editUser(<?php echo esc_attr( $user->user_id ); ?>)" title="<?php esc_attr_e( 'Edit User', 'wpmatch' ); ?>">
										<span class="dashicons dashicons-edit"></span>
									</button>
									<?php if ( 'active' === $user->status ) : ?>
										<button type="button" class="action-btn block-btn" onclick="toggleUserStatus(<?php echo esc_attr( $user->user_id ); ?>, 'blocked')" title="<?php esc_attr_e( 'Block User', 'wpmatch' ); ?>">
											<span class="dashicons dashicons-dismiss"></span>
										</button>
									<?php else : ?>
										<button type="button" class="action-btn activate-btn" onclick="toggleUserStatus(<?php echo esc_attr( $user->user_id ); ?>, 'active')" title="<?php esc_attr_e( 'Activate User', 'wpmatch' ); ?>">
											<span class="dashicons dashicons-yes-alt"></span>
										</button>
									<?php endif; ?>
									<?php if ( ! $user->is_verified ) : ?>
										<button type="button" class="action-btn verify-btn" onclick="verifyUser(<?php echo esc_attr( $user->user_id ); ?>)" title="<?php esc_attr_e( 'Verify User', 'wpmatch' ); ?>">
											<span class="dashicons dashicons-awards"></span>
										</button>
									<?php endif; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<!-- Pagination -->
			<?php if ( $total_pages > 1 ) : ?>
				<div class="wpmatch-pagination">
					<?php
					$pagination_args = array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'current'   => $current_page,
						'total'     => $total_pages,
						'prev_text' => '&laquo; ' . __( 'Previous', 'wpmatch' ),
						'next_text' => __( 'Next', 'wpmatch' ) . ' &raquo;',
					);
					echo wp_kses_post( paginate_links( $pagination_args ) );
					?>
				</div>
			<?php endif; ?>

		<?php else : ?>
			<div class="wpmatch-empty-state">
				<div class="empty-icon">
					<span class="dashicons dashicons-admin-users"></span>
				</div>
				<h3><?php esc_html_e( 'No Users Found', 'wpmatch' ); ?></h3>
				<p>
					<?php if ( $search_query ) : ?>
						<?php
						/* translators: %s: search query */
						printf( esc_html__( 'No users found matching "%s". Try a different search term.', 'wpmatch' ), esc_html( $search_query ) );
						?>
					<?php elseif ( $status_filter ) : ?>
						<?php
						/* translators: %s: status filter */
						printf( esc_html__( 'No %s users found.', 'wpmatch' ), esc_html( $status_filter ) );
						?>
					<?php else : ?>
						<?php esc_html_e( 'No users have joined your dating site yet.', 'wpmatch' ); ?>
					<?php endif; ?>
				</p>
				<?php if ( ! $search_query && ! $status_filter ) : ?>
					<button type="button" class="wpmatch-button" onclick="generateDemoUsers()"><?php esc_html_e( 'Generate Demo Users', 'wpmatch' ); ?></button>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</div>

<!-- User Profile Modal -->
<div id="user-profile-modal" class="wpmatch-modal" style="display: none;">
	<div class="modal-content">
		<div class="modal-header">
			<h2><?php esc_html_e( 'User Profile', 'wpmatch' ); ?></h2>
			<button type="button" class="modal-close" onclick="closeModal()">&times;</button>
		</div>
		<div class="modal-body" id="user-profile-content">
			<!-- Profile content will be loaded here -->
		</div>
	</div>
</div>

<!-- Styles for User Management Modal -->
<style>
.wpmatch-modal {
	position: fixed;
	z-index: 9999;
	left: 0;
	top: 0;
	width: 100%;
	height: 100%;
	background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
	background-color: #fff;
	margin: 5% auto;
	padding: 0;
	border-radius: 8px;
	width: 80%;
	max-width: 800px;
	max-height: 90vh;
	overflow-y: auto;
	box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.modal-header {
	padding: 20px;
	border-bottom: 1px solid #ddd;
	display: flex;
	justify-content: space-between;
	align-items: center;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	color: white;
	border-radius: 8px 8px 0 0;
}

.modal-header h2 {
	margin: 0;
	color: white;
}

.modal-close {
	background: none;
	border: none;
	font-size: 24px;
	cursor: pointer;
	color: white;
	padding: 0;
	width: 30px;
	height: 30px;
	display: flex;
	align-items: center;
	justify-content: center;
}

.modal-body {
	padding: 20px;
}

.user-profile-details .profile-header {
	margin-bottom: 20px;
	padding-bottom: 15px;
	border-bottom: 1px solid #eee;
}

.user-profile-details .profile-photos {
	margin: 20px 0;
}

.user-profile-details .profile-info {
	margin: 20px 0;
}

.user-profile-details .profile-bio {
	margin: 20px 0;
	padding: 15px;
	background: #f9f9f9;
	border-radius: 8px;
}

.user-profile-details .profile-stats {
	margin: 20px 0;
}

.stats-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
	gap: 15px;
	margin-top: 10px;
}

.stat-item {
	text-align: center;
	padding: 15px;
	background: #f9f9f9;
	border-radius: 8px;
}

.stat-item strong {
	display: block;
	font-size: 24px;
	color: #667eea;
	margin-bottom: 5px;
}

.stat-item span {
	font-size: 12px;
	color: #666;
	text-transform: uppercase;
}

.profile-actions {
	margin-top: 20px;
	padding-top: 15px;
	border-top: 1px solid #eee;
	display: flex;
	gap: 10px;
	flex-wrap: wrap;
}

.status-badge {
	padding: 4px 8px;
	border-radius: 4px;
	font-size: 12px;
	font-weight: bold;
	text-transform: uppercase;
}

.status-active { background: #d4edda; color: #155724; }
.status-pending { background: #fff3cd; color: #856404; }
.status-blocked { background: #f8d7da; color: #721c24; }
.status-inactive { background: #e2e3e5; color: #383d41; }

.loading {
	text-align: center;
	padding: 40px;
	color: #666;
}

.error {
	padding: 15px;
	background: #f8d7da;
	color: #721c24;
	border-radius: 4px;
	margin: 10px 0;
}
</style>

<!-- JavaScript for User Management -->
<script>
// Setup admin variables
var wpmatchAdmin = {
	nonce: '<?php echo esc_js( wp_create_nonce( 'wpmatch_admin_nonce' ) ); ?>'
};
document.addEventListener('DOMContentLoaded', function() {
	// Select all functionality
	document.getElementById('select-all-users').addEventListener('change', function() {
		const checkboxes = document.querySelectorAll('.user-checkbox');
		checkboxes.forEach(checkbox => {
			checkbox.checked = this.checked;
		});
	});

	// Update select all when individual checkboxes change
	document.querySelectorAll('.user-checkbox').forEach(checkbox => {
		checkbox.addEventListener('change', function() {
			const allCheckboxes = document.querySelectorAll('.user-checkbox');
			const checkedCheckboxes = document.querySelectorAll('.user-checkbox:checked');
			const selectAllCheckbox = document.getElementById('select-all-users');

			if (checkedCheckboxes.length === 0) {
				selectAllCheckbox.indeterminate = false;
				selectAllCheckbox.checked = false;
			} else if (checkedCheckboxes.length === allCheckboxes.length) {
				selectAllCheckbox.indeterminate = false;
				selectAllCheckbox.checked = true;
			} else {
				selectAllCheckbox.indeterminate = true;
			}
		});
	});
});

// User management functions
function showUserProfile(userId) {
	// Show loading
	document.getElementById('user-profile-content').innerHTML = '<div class="loading">Loading profile...</div>';
	document.getElementById('user-profile-modal').style.display = 'block';

	// Load user profile data via AJAX
	fetch(ajaxurl, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
		},
		body: new URLSearchParams({
			action: 'wpmatch_get_user_profile',
			user_id: userId,
			nonce: wpmatchAdmin.nonce
		})
	})
	.then(response => response.json())
	.then(data => {
		if (data.success) {
			displayUserProfile(data.data);
		} else {
			document.getElementById('user-profile-content').innerHTML = '<div class="error">Error: ' + data.data.message + '</div>';
		}
	})
	.catch(error => {
		document.getElementById('user-profile-content').innerHTML = '<div class="error">Failed to load profile.</div>';
	});
}

function displayUserProfile(profileData) {
	const user = profileData.user;
	const profile = profileData.profile;
	const photos = profileData.photos;
	const stats = profileData.statistics;

	let photosHtml = '';
	if (photos && photos.length > 0) {
		photosHtml = photos.map(photo => `
			<img src="${photo.file_path}" alt="Profile photo" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px; margin-right: 10px;">
		`).join('');
	}

	const profileHtml = `
		<div class="user-profile-details">
			<div class="profile-header">
				<h3>${user.display_name} (${user.user_login})</h3>
				<p><strong>Email:</strong> ${user.user_email}</p>
				<p><strong>Registered:</strong> ${new Date(user.user_registered).toLocaleDateString()}</p>
			</div>

			${photosHtml ? `<div class="profile-photos"><h4>Photos</h4>${photosHtml}</div>` : ''}

			<div class="profile-info">
				<h4>Profile Information</h4>
				<p><strong>Age:</strong> ${profile.age || 'Not specified'}</p>
				<p><strong>Gender:</strong> ${profile.gender || 'Not specified'}</p>
				<p><strong>Orientation:</strong> ${profile.orientation || 'Not specified'}</p>
				<p><strong>Location:</strong> ${profile.location || 'Not specified'}</p>
				<p><strong>Status:</strong> <span class="status-badge status-${profile.status}">${profile.status}</span></p>
				<p><strong>Verified:</strong> ${profile.is_verified ? 'Yes' : 'No'}</p>
			</div>

			${profile.about_me ? `
				<div class="profile-bio">
					<h4>About</h4>
					<p>${profile.about_me}</p>
				</div>
			` : ''}

			<div class="profile-stats">
				<h4>Statistics</h4>
				<div class="stats-grid">
					<div class="stat-item">
						<strong>${stats.matches}</strong>
						<span>Matches</span>
					</div>
					<div class="stat-item">
						<strong>${stats.total_swipes}</strong>
						<span>Total Swipes</span>
					</div>
					<div class="stat-item">
						<strong>${stats.likes_given}</strong>
						<span>Likes Given</span>
					</div>
					<div class="stat-item">
						<strong>${stats.received_likes}</strong>
						<span>Likes Received</span>
					</div>
				</div>
			</div>

			<div class="profile-actions">
				<button type="button" class="wpmatch-button secondary" onclick="editUserProfile(${user.ID})">Edit Profile</button>
				<button type="button" class="wpmatch-button primary" onclick="toggleUserStatus(${user.ID}, '${profile.status === 'active' ? 'blocked' : 'active'}')">
					${profile.status === 'active' ? 'Block User' : 'Activate User'}
				</button>
				${!profile.is_verified ? `<button type="button" class="wpmatch-button" onclick="verifyUser(${user.ID})">Verify User</button>` : ''}
			</div>
		</div>
	`;

	document.getElementById('user-profile-content').innerHTML = profileHtml;
}

function editUser(userId) {
	// For now, show profile modal which has edit functionality
	showUserProfile(userId);
}

function editUserProfile(userId) {
	// Simple prompt-based editing for now
	const newAge = prompt('Enter new age:');
	const newLocation = prompt('Enter new location:');
	const newAbout = prompt('Enter new bio:');

	if (newAge || newLocation || newAbout) {
		const updateData = {
			action: 'wpmatch_update_user_profile',
			user_id: userId,
			nonce: wpmatchAdmin.nonce
		};

		if (newAge) updateData.age = newAge;
		if (newLocation) updateData.location = newLocation;
		if (newAbout) updateData.about_me = newAbout;

		fetch(ajaxurl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: new URLSearchParams(updateData)
		})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				alert('Profile updated successfully!');
				showUserProfile(userId); // Refresh the profile display
			} else {
				alert('Error: ' + data.data.message);
			}
		});
	}
}

function toggleUserStatus(userId, newStatus) {
	if (confirm('Are you sure you want to change this user\'s status to ' + newStatus + '?')) {
		fetch(ajaxurl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: new URLSearchParams({
				action: 'wpmatch_update_user_status',
				user_id: userId,
				status: newStatus,
				nonce: wpmatchAdmin.nonce
			})
		})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				alert('User status updated successfully!');
				location.reload();
			} else {
				alert('Error: ' + data.data.message);
			}
		});
	}
}

function verifyUser(userId) {
	if (confirm('Are you sure you want to verify this user?')) {
		fetch(ajaxurl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: new URLSearchParams({
				action: 'wpmatch_verify_user',
				user_id: userId,
				nonce: wpmatchAdmin.nonce
			})
		})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				alert('User verified successfully!');
				location.reload();
			} else {
				alert('Error: ' + data.data.message);
			}
		});
	}
}

function performBulkAction() {
	const action = document.getElementById('bulk-action-select').value;
	const selectedUsers = document.querySelectorAll('.user-checkbox:checked');

	if (!action) {
		alert('Please select an action.');
		return;
	}

	if (selectedUsers.length === 0) {
		alert('Please select at least one user.');
		return;
	}

	if (confirm(`Are you sure you want to ${action} ${selectedUsers.length} user(s)?`)) {
		const userIds = Array.from(selectedUsers).map(checkbox => checkbox.value);

		fetch(ajaxurl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: new URLSearchParams({
				action: 'wpmatch_bulk_user_action',
				user_ids: userIds,
				bulk_action: action,
				nonce: wpmatchAdmin.nonce
			})
		})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				alert(data.data.message);
				location.reload();
			} else {
				alert('Error: ' + data.data.message);
			}
		})
		.catch(error => {
			alert('Failed to perform bulk action.');
		});
	}
}

function closeModal() {
	document.getElementById('user-profile-modal').style.display = 'none';
}

function generateDemoUsers() {
	if (confirm('This will create demo users for testing. Continue?')) {
		// Trigger the existing demo user creation
		window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=wpmatch-admin&action=create_demo_users' ) ); ?>';
	}
}

// Close modal when clicking outside
window.onclick = function(event) {
	const modal = document.getElementById('user-profile-modal');
	if (event.target === modal) {
		closeModal();
	}
}
</script>