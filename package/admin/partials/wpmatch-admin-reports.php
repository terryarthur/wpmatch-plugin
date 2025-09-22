<?php
/**
 * Provide an admin area view for WPMatch Reports/Analytics
 *
 * @package WPMatch
 * @subpackage WPMatch/admin/partials
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// Database tables.
$profile_table   = $wpdb->prefix . 'wpmatch_user_profiles';
$swipes_table    = $wpdb->prefix . 'wpmatch_swipes';
$matches_table   = $wpdb->prefix . 'wpmatch_matches';
$messages_table  = $wpdb->prefix . 'wpmatch_messages';

// Get current period (last 30 days by default).
$current_period = isset( $_GET['period'] ) ? sanitize_text_field( $_GET['period'] ) : '30';
$period_label   = array(
	'7'  => __( 'Last 7 Days', 'wpmatch' ),
	'30' => __( 'Last 30 Days', 'wpmatch' ),
	'90' => __( 'Last 90 Days', 'wpmatch' ),
	'365' => __( 'Last Year', 'wpmatch' ),
);

// Date calculations.
$end_date   = current_time( 'Y-m-d H:i:s' );
$start_date = date( 'Y-m-d H:i:s', strtotime( "-{$current_period} days" ) );

// Get overall statistics.
$total_users = $wpdb->get_var( "SELECT COUNT(*) FROM $profile_table" );
$active_users = $wpdb->get_var( "SELECT COUNT(*) FROM $profile_table WHERE status = 'active'" );
$verified_users = $wpdb->get_var( "SELECT COUNT(*) FROM $profile_table WHERE is_verified = 1" );

$new_users_period = $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM $profile_table WHERE created_at BETWEEN %s AND %s",
	$start_date,
	$end_date
) );

$total_swipes = $wpdb->get_var( "SELECT COUNT(*) FROM $swipes_table" );
$total_matches = $wpdb->get_var( "SELECT COUNT(*) FROM $matches_table" );

$swipes_period = $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM $swipes_table WHERE created_at BETWEEN %s AND %s",
	$start_date,
	$end_date
) );

$matches_period = $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM $matches_table WHERE created_at BETWEEN %s AND %s",
	$start_date,
	$end_date
) );

// Calculate match rate.
$match_rate = $total_swipes > 0 ? round( ( $total_matches * 2 / $total_swipes ) * 100, 2 ) : 0;

// Get engagement statistics.
$messages_period = $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM $messages_table WHERE created_at BETWEEN %s AND %s",
	$start_date,
	$end_date
) );

// Get swipe type breakdown.
$swipe_breakdown = $wpdb->get_results(
	"SELECT swipe_type as type, COUNT(*) as count
	FROM $swipes_table
	GROUP BY swipe_type
	ORDER BY count DESC"
);

// Get gender distribution.
$gender_distribution = $wpdb->get_results(
	"SELECT gender, COUNT(*) as count
	FROM $profile_table
	WHERE gender IS NOT NULL
	GROUP BY gender
	ORDER BY count DESC"
);

// Get age distribution.
$age_distribution = $wpdb->get_results(
	"SELECT
		CASE
			WHEN age < 25 THEN '18-24'
			WHEN age < 35 THEN '25-34'
			WHEN age < 45 THEN '35-44'
			WHEN age < 55 THEN '45-54'
			ELSE '55+'
		END as age_group,
		COUNT(*) as count
	FROM $profile_table
	WHERE age IS NOT NULL
	GROUP BY age_group
	ORDER BY age_group"
);

// Get daily activity for the last 30 days.
$daily_activity = $wpdb->get_results( $wpdb->prepare(
	"SELECT
		DATE(created_at) as date,
		COUNT(*) as swipes
	FROM $swipes_table
	WHERE created_at >= %s
	GROUP BY DATE(created_at)
	ORDER BY date DESC
	LIMIT 30",
	date( 'Y-m-d', strtotime( '-30 days' ) )
) );

// Get top performing users (most matches).
$top_users = $wpdb->get_results(
	"SELECT
		p.user_id,
		u.display_name,
		COUNT(DISTINCT m.match_id) as match_count,
		p.location
	FROM $profile_table p
	INNER JOIN {$wpdb->users} u ON p.user_id = u.ID
	LEFT JOIN $matches_table m ON (p.user_id = m.user1_id OR p.user_id = m.user2_id)
	WHERE p.status = 'active'
	GROUP BY p.user_id, u.display_name, p.location
	HAVING match_count > 0
	ORDER BY match_count DESC
	LIMIT 10"
);
?>

<div class="wrap wpmatch-reports">
	<h1><?php esc_html_e( 'WPMatch Analytics & Reports', 'wpmatch' ); ?></h1>

	<!-- Period Filter -->
	<div class="wpmatch-period-filter">
		<label for="period-select"><?php esc_html_e( 'Time Period:', 'wpmatch' ); ?></label>
		<select id="period-select" onchange="changePeriod(this.value)">
			<?php foreach ( $period_label as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_period, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</div>

	<!-- Key Metrics Dashboard -->
	<div class="wpmatch-metrics-grid">
		<!-- Overall Statistics -->
		<div class="metrics-section">
			<h2><?php esc_html_e( 'Overall Statistics', 'wpmatch' ); ?></h2>
			<div class="metrics-cards">
				<div class="metric-card total-users">
					<div class="metric-icon">
						<span class="dashicons dashicons-admin-users"></span>
					</div>
					<div class="metric-content">
						<h3><?php echo esc_html( number_format( $total_users ) ); ?></h3>
						<p><?php esc_html_e( 'Total Users', 'wpmatch' ); ?></p>
						<span class="metric-change">
							+<?php echo esc_html( number_format( $new_users_period ) ); ?> <?php echo esc_html( strtolower( $period_label[ $current_period ] ) ); ?>
						</span>
					</div>
				</div>

				<div class="metric-card active-users">
					<div class="metric-icon">
						<span class="dashicons dashicons-yes-alt"></span>
					</div>
					<div class="metric-content">
						<h3><?php echo esc_html( number_format( $active_users ) ); ?></h3>
						<p><?php esc_html_e( 'Active Users', 'wpmatch' ); ?></p>
						<span class="metric-change">
							<?php echo esc_html( round( ( $active_users / max( $total_users, 1 ) ) * 100, 1 ) ); ?>% <?php esc_html_e( 'of total', 'wpmatch' ); ?>
						</span>
					</div>
				</div>

				<div class="metric-card verified-users">
					<div class="metric-icon">
						<span class="dashicons dashicons-awards"></span>
					</div>
					<div class="metric-content">
						<h3><?php echo esc_html( number_format( $verified_users ) ); ?></h3>
						<p><?php esc_html_e( 'Verified Users', 'wpmatch' ); ?></p>
						<span class="metric-change">
							<?php echo esc_html( round( ( $verified_users / max( $total_users, 1 ) ) * 100, 1 ) ); ?>% <?php esc_html_e( 'verification rate', 'wpmatch' ); ?>
						</span>
					</div>
				</div>

				<div class="metric-card total-matches">
					<div class="metric-icon">
						<span class="dashicons dashicons-heart"></span>
					</div>
					<div class="metric-content">
						<h3><?php echo esc_html( number_format( $total_matches ) ); ?></h3>
						<p><?php esc_html_e( 'Total Matches', 'wpmatch' ); ?></p>
						<span class="metric-change">
							+<?php echo esc_html( number_format( $matches_period ) ); ?> <?php echo esc_html( strtolower( $period_label[ $current_period ] ) ); ?>
						</span>
					</div>
				</div>
			</div>
		</div>

		<!-- Engagement Metrics -->
		<div class="metrics-section">
			<h2><?php esc_html_e( 'Engagement Metrics', 'wpmatch' ); ?></h2>
			<div class="metrics-cards">
				<div class="metric-card total-swipes">
					<div class="metric-icon">
						<span class="dashicons dashicons-smartphone"></span>
					</div>
					<div class="metric-content">
						<h3><?php echo esc_html( number_format( $total_swipes ) ); ?></h3>
						<p><?php esc_html_e( 'Total Swipes', 'wpmatch' ); ?></p>
						<span class="metric-change">
							+<?php echo esc_html( number_format( $swipes_period ) ); ?> <?php echo esc_html( strtolower( $period_label[ $current_period ] ) ); ?>
						</span>
					</div>
				</div>

				<div class="metric-card match-rate">
					<div class="metric-icon">
						<span class="dashicons dashicons-chart-line"></span>
					</div>
					<div class="metric-content">
						<h3><?php echo esc_html( $match_rate ); ?>%</h3>
						<p><?php esc_html_e( 'Match Rate', 'wpmatch' ); ?></p>
						<span class="metric-change">
							<?php esc_html_e( 'Success rate', 'wpmatch' ); ?>
						</span>
					</div>
				</div>

				<div class="metric-card messages-sent">
					<div class="metric-icon">
						<span class="dashicons dashicons-email-alt"></span>
					</div>
					<div class="metric-content">
						<h3><?php echo esc_html( number_format( $messages_period ) ); ?></h3>
						<p><?php esc_html_e( 'Messages Sent', 'wpmatch' ); ?></p>
						<span class="metric-change">
							<?php echo esc_html( strtolower( $period_label[ $current_period ] ) ); ?>
						</span>
					</div>
				</div>

				<div class="metric-card avg-matches">
					<div class="metric-icon">
						<span class="dashicons dashicons-networking"></span>
					</div>
					<div class="metric-content">
						<h3><?php echo esc_html( $active_users > 0 ? round( $total_matches / $active_users, 1 ) : 0 ); ?></h3>
						<p><?php esc_html_e( 'Avg Matches/User', 'wpmatch' ); ?></p>
						<span class="metric-change">
							<?php esc_html_e( 'Per active user', 'wpmatch' ); ?>
						</span>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Charts and Detailed Analytics -->
	<div class="wpmatch-charts-section">
		<div class="charts-grid">
			<!-- Daily Activity Chart -->
			<div class="chart-container">
				<h3><?php esc_html_e( 'Daily Swipe Activity (Last 30 Days)', 'wpmatch' ); ?></h3>
				<div class="chart-placeholder">
					<canvas id="daily-activity-chart"></canvas>
				</div>
			</div>

			<!-- Swipe Type Breakdown -->
			<div class="chart-container">
				<h3><?php esc_html_e( 'Swipe Type Distribution', 'wpmatch' ); ?></h3>
				<div class="breakdown-stats">
					<?php if ( ! empty( $swipe_breakdown ) ) : ?>
						<?php foreach ( $swipe_breakdown as $swipe ) : ?>
							<div class="stat-item">
								<span class="stat-label"><?php echo esc_html( ucfirst( $swipe->type ) ); ?>:</span>
								<span class="stat-value"><?php echo esc_html( number_format( $swipe->count ) ); ?></span>
								<span class="stat-percentage">
									(<?php echo esc_html( round( ( $swipe->count / $total_swipes ) * 100, 1 ) ); ?>%)
								</span>
							</div>
						<?php endforeach; ?>
					<?php else : ?>
						<p><?php esc_html_e( 'No swipe data available yet.', 'wpmatch' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<!-- Gender Distribution -->
			<div class="chart-container">
				<h3><?php esc_html_e( 'Gender Distribution', 'wpmatch' ); ?></h3>
				<div class="breakdown-stats">
					<?php if ( ! empty( $gender_distribution ) ) : ?>
						<?php foreach ( $gender_distribution as $gender ) : ?>
							<div class="stat-item">
								<span class="stat-label"><?php echo esc_html( ucfirst( $gender->gender ) ); ?>:</span>
								<span class="stat-value"><?php echo esc_html( number_format( $gender->count ) ); ?></span>
								<span class="stat-percentage">
									(<?php echo esc_html( round( ( $gender->count / $total_users ) * 100, 1 ) ); ?>%)
								</span>
							</div>
						<?php endforeach; ?>
					<?php else : ?>
						<p><?php esc_html_e( 'No gender data available.', 'wpmatch' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<!-- Age Distribution -->
			<div class="chart-container">
				<h3><?php esc_html_e( 'Age Distribution', 'wpmatch' ); ?></h3>
				<div class="breakdown-stats">
					<?php if ( ! empty( $age_distribution ) ) : ?>
						<?php foreach ( $age_distribution as $age ) : ?>
							<div class="stat-item">
								<span class="stat-label"><?php echo esc_html( $age->age_group ); ?>:</span>
								<span class="stat-value"><?php echo esc_html( number_format( $age->count ) ); ?></span>
								<span class="stat-percentage">
									(<?php echo esc_html( round( ( $age->count / $total_users ) * 100, 1 ) ); ?>%)
								</span>
							</div>
						<?php endforeach; ?>
					<?php else : ?>
						<p><?php esc_html_e( 'No age data available.', 'wpmatch' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>

	<!-- Top Performing Users -->
	<?php if ( ! empty( $top_users ) ) : ?>
	<div class="wpmatch-top-users-section">
		<h3><?php esc_html_e( 'Top Performing Users (Most Matches)', 'wpmatch' ); ?></h3>
		<div class="top-users-table">
			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Rank', 'wpmatch' ); ?></th>
						<th><?php esc_html_e( 'User', 'wpmatch' ); ?></th>
						<th><?php esc_html_e( 'Location', 'wpmatch' ); ?></th>
						<th><?php esc_html_e( 'Matches', 'wpmatch' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'wpmatch' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $top_users as $index => $user ) : ?>
						<tr>
							<td><?php echo esc_html( $index + 1 ); ?></td>
							<td>
								<strong><?php echo esc_html( $user->display_name ); ?></strong>
								<span class="user-id">(ID: <?php echo esc_html( $user->user_id ); ?>)</span>
							</td>
							<td><?php echo esc_html( $user->location ?: __( 'Not specified', 'wpmatch' ) ); ?></td>
							<td>
								<span class="match-badge"><?php echo esc_html( $user->match_count ); ?></span>
							</td>
							<td>
								<button type="button" class="button button-small" onclick="showUserProfile(<?php echo esc_attr( $user->user_id ); ?>)">
									<?php esc_html_e( 'View Profile', 'wpmatch' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php endif; ?>

	<!-- Export Options -->
	<div class="wpmatch-export-section">
		<h3><?php esc_html_e( 'Export Reports', 'wpmatch' ); ?></h3>
		<div class="export-buttons">
			<button type="button" class="button button-primary" onclick="exportReport('csv')">
				<span class="dashicons dashicons-download"></span>
				<?php esc_html_e( 'Export CSV', 'wpmatch' ); ?>
			</button>
			<button type="button" class="button button-secondary" onclick="exportReport('pdf')">
				<span class="dashicons dashicons-pdf"></span>
				<?php esc_html_e( 'Export PDF', 'wpmatch' ); ?>
			</button>
		</div>
	</div>
</div>

<!-- Include the same modal from users page for profile viewing -->
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

<!-- Styles for Reports Page -->
<style>
.wpmatch-reports {
	background: #f1f1f1;
	margin: 0 -20px;
	padding: 20px;
	min-height: calc(100vh - 32px);
}

.wpmatch-period-filter {
	background: white;
	padding: 15px 20px;
	border-radius: 8px;
	margin-bottom: 20px;
	box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.wpmatch-period-filter label {
	font-weight: 600;
	margin-right: 10px;
}

.wpmatch-period-filter select {
	padding: 8px 12px;
	border-radius: 4px;
	border: 1px solid #ddd;
}

.wpmatch-metrics-grid {
	margin-bottom: 30px;
}

.metrics-section {
	background: white;
	padding: 20px;
	border-radius: 8px;
	margin-bottom: 20px;
	box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.metrics-section h2 {
	margin: 0 0 20px 0;
	color: #2c3e50;
	border-bottom: 2px solid #667eea;
	padding-bottom: 10px;
}

.metrics-cards {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
	gap: 20px;
}

.metric-card {
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	color: white;
	padding: 20px;
	border-radius: 12px;
	display: flex;
	align-items: center;
	box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
	transition: transform 0.3s ease;
}

.metric-card:hover {
	transform: translateY(-5px);
}

.metric-card.total-users { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.metric-card.active-users { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
.metric-card.verified-users { background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); }
.metric-card.total-matches { background: linear-gradient(135deg, #e91e63 0%, #ad1457 100%); }
.metric-card.total-swipes { background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); }
.metric-card.match-rate { background: linear-gradient(135deg, #6f42c1 0%, #495057 100%); }
.metric-card.messages-sent { background: linear-gradient(135deg, #fd7e14 0%, #e83e8c 100%); }
.metric-card.avg-matches { background: linear-gradient(135deg, #20c997 0%, #28a745 100%); }

.metric-icon {
	margin-right: 15px;
}

.metric-icon .dashicons {
	font-size: 40px;
	opacity: 0.8;
}

.metric-content h3 {
	font-size: 32px;
	font-weight: 700;
	margin: 0 0 5px 0;
	color: white;
}

.metric-content p {
	font-size: 14px;
	margin: 0 0 5px 0;
	opacity: 0.9;
}

.metric-change {
	font-size: 12px;
	opacity: 0.8;
}

.wpmatch-charts-section {
	background: white;
	padding: 20px;
	border-radius: 8px;
	margin-bottom: 20px;
	box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.charts-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
	gap: 20px;
}

.chart-container {
	background: #f8f9fa;
	padding: 20px;
	border-radius: 8px;
	border: 1px solid #e9ecef;
}

.chart-container h3 {
	margin: 0 0 15px 0;
	color: #2c3e50;
	font-size: 16px;
}

.chart-placeholder {
	height: 200px;
	background: #f1f3f4;
	border-radius: 4px;
	display: flex;
	align-items: center;
	justify-content: center;
	color: #666;
}

.breakdown-stats .stat-item {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 10px 0;
	border-bottom: 1px solid #eee;
}

.breakdown-stats .stat-item:last-child {
	border-bottom: none;
}

.stat-label {
	font-weight: 600;
	color: #2c3e50;
}

.stat-value {
	font-weight: 700;
	color: #667eea;
}

.stat-percentage {
	font-size: 12px;
	color: #666;
}

.wpmatch-top-users-section {
	background: white;
	padding: 20px;
	border-radius: 8px;
	margin-bottom: 20px;
	box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.wpmatch-top-users-section h3 {
	margin: 0 0 20px 0;
	color: #2c3e50;
	border-bottom: 2px solid #667eea;
	padding-bottom: 10px;
}

.top-users-table .user-id {
	font-size: 12px;
	color: #666;
	margin-left: 5px;
}

.match-badge {
	background: #667eea;
	color: white;
	padding: 4px 8px;
	border-radius: 12px;
	font-weight: 600;
	font-size: 12px;
}

.wpmatch-export-section {
	background: white;
	padding: 20px;
	border-radius: 8px;
	box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.wpmatch-export-section h3 {
	margin: 0 0 15px 0;
	color: #2c3e50;
}

.export-buttons {
	display: flex;
	gap: 10px;
}

.export-buttons .button .dashicons {
	margin-right: 5px;
}

/* Modal styles (reuse from users page) */
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

@media (max-width: 768px) {
	.metrics-cards {
		grid-template-columns: 1fr;
	}

	.charts-grid {
		grid-template-columns: 1fr;
	}

	.export-buttons {
		flex-direction: column;
	}
}
</style>

<!-- JavaScript for Reports Page -->
<script>
// Setup admin variables (reuse from users page)
var wpmatchAdmin = {
	nonce: '<?php echo wp_create_nonce( 'wpmatch_admin_nonce' ); ?>'
};

// Change period function
function changePeriod(period) {
	window.location.href = '<?php echo admin_url( 'admin.php?page=wpmatch-reports' ); ?>&period=' + period;
}

// Reuse user profile functions from users page
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
		</div>
	`;

	document.getElementById('user-profile-content').innerHTML = profileHtml;
}

function closeModal() {
	document.getElementById('user-profile-modal').style.display = 'none';
}

// Export functionality
function exportReport(format) {
	const currentPeriod = '<?php echo esc_js( $current_period ); ?>';
	const exportUrl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';

	// For now, show alert - would implement actual export in next phase
	if (format === 'csv') {
		alert('CSV export functionality will be implemented in the next phase. This would export all analytics data to a CSV file.');
	} else if (format === 'pdf') {
		alert('PDF export functionality will be implemented in the next phase. This would generate a comprehensive analytics report in PDF format.');
	}
}

// Close modal when clicking outside
window.onclick = function(event) {
	const modal = document.getElementById('user-profile-modal');
	if (event.target === modal) {
		closeModal();
	}
}

// Initialize Chart.js if available
document.addEventListener('DOMContentLoaded', function() {
	// Simple chart placeholder - would implement real charts in next phase
	const chartCanvas = document.getElementById('daily-activity-chart');
	if (chartCanvas) {
		const ctx = chartCanvas.getContext('2d');
		ctx.fillStyle = '#667eea';
		ctx.fillRect(0, 0, chartCanvas.width, chartCanvas.height);
		ctx.fillStyle = 'white';
		ctx.font = '16px Arial';
		ctx.textAlign = 'center';
		ctx.fillText('Chart visualization would be implemented', chartCanvas.width / 2, chartCanvas.height / 2 - 10);
		ctx.fillText('using Chart.js in the next phase', chartCanvas.width / 2, chartCanvas.height / 2 + 10);
	}
});
</script>