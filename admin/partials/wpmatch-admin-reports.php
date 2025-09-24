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
	"SELECT action as type, COUNT(*) as count
	FROM $swipes_table
	GROUP BY action
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
	ORDER BY date ASC
	LIMIT 30",
	date( 'Y-m-d', strtotime( '-30 days' ) )
) );

// Get top performing users (most matches).
$top_users = $wpdb->get_results(
	"SELECT
		p.user_id,
		u.display_name,
		COUNT(DISTINCT m.id) as match_count,
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

// Additional advanced analytics
// Conversion funnel analysis
$registered_users = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );
$users_with_profiles = $wpdb->get_var( "SELECT COUNT(*) FROM $profile_table" );
$users_with_photos = $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}wpmatch_user_photos" );
$users_who_swiped = $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM $swipes_table" );
$users_with_matches = $wpdb->get_var( "SELECT COUNT(DISTINCT user1_id) FROM $matches_table UNION SELECT COUNT(DISTINCT user2_id) FROM $matches_table" );

// Most active hours analysis
$activity_by_hour = $wpdb->get_results( $wpdb->prepare(
	"SELECT
		HOUR(created_at) as hour,
		COUNT(*) as activity_count
	FROM $swipes_table
	WHERE created_at >= %s
	GROUP BY HOUR(created_at)
	ORDER BY hour",
	date( 'Y-m-d', strtotime( '-7 days' ) )
) );

// Location-based analytics
$top_locations = $wpdb->get_results(
	"SELECT
		location,
		COUNT(*) as user_count
	FROM $profile_table
	WHERE location IS NOT NULL AND location != ''
	GROUP BY location
	ORDER BY user_count DESC
	LIMIT 10"
);

// Message response rates
$total_conversations = $wpdb->get_var( "SELECT COUNT(DISTINCT conversation_id) FROM $messages_table" );
$active_conversations = $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(DISTINCT conversation_id)
	FROM $messages_table
	WHERE created_at >= %s",
	date( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
) );

// Photo impact analysis
$users_with_photos_matches = $wpdb->get_var(
	"SELECT COUNT(DISTINCT p.user_id)
	FROM $profile_table p
	INNER JOIN {$wpdb->prefix}wpmatch_user_photos ph ON p.user_id = ph.user_id
	INNER JOIN $matches_table m ON (p.user_id = m.user1_id OR p.user_id = m.user2_id)"
);

$users_without_photos_matches = $wpdb->get_var(
	"SELECT COUNT(DISTINCT p.user_id)
	FROM $profile_table p
	LEFT JOIN {$wpdb->prefix}wpmatch_user_photos ph ON p.user_id = ph.user_id
	INNER JOIN $matches_table m ON (p.user_id = m.user1_id OR p.user_id = m.user2_id)
	WHERE ph.user_id IS NULL"
);

// Calculate conversion rates
$profile_completion_rate = $registered_users > 0 ? round( ( $users_with_profiles / $registered_users ) * 100, 1 ) : 0;
$photo_upload_rate = $users_with_profiles > 0 ? round( ( $users_with_photos / $users_with_profiles ) * 100, 1 ) : 0;
$swipe_activation_rate = $users_with_profiles > 0 ? round( ( $users_who_swiped / $users_with_profiles ) * 100, 1 ) : 0;
$match_success_rate = $users_who_swiped > 0 ? round( ( $users_with_matches / $users_who_swiped ) * 100, 1 ) : 0;
$conversation_rate = $total_matches > 0 ? round( ( $total_conversations / $total_matches ) * 100, 1 ) : 0;
?>

<div class="wrap wpmatch-admin">
	<div class="wpmatch-admin-header">
		<div class="wpmatch-header-content">
			<div class="wpmatch-header-main">
				<h1>
					<span class="dashicons dashicons-chart-bar"></span>
					<?php esc_html_e( 'Analytics & Reports', 'wpmatch' ); ?>
				</h1>
				<p><?php esc_html_e( 'Track user engagement, match success rates, and platform performance with detailed analytics and reports.', 'wpmatch' ); ?></p>
			</div>
			<div class="wpmatch-header-actions">
				<div class="wpmatch-period-filter">
					<label for="period-select"><?php esc_html_e( 'Period:', 'wpmatch' ); ?></label>
					<select id="period-select" onchange="changePeriod(this.value)">
						<?php foreach ( $period_label as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_period, $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<a href="#" class="wpmatch-button secondary" onclick="window.print()">
					<span class="dashicons dashicons-printer"></span>
					<?php esc_html_e( 'Print Report', 'wpmatch' ); ?>
				</a>
			</div>
		</div>
	</div>

	<!-- Tabbed Interface Navigation -->
	<div class="wpmatch-tabs-container">
		<div class="wpmatch-tabs-nav">
			<button class="wpmatch-tab-button active" data-tab="overview">
				<span class="dashicons dashicons-chart-bar"></span>
				<?php esc_html_e( 'Overview & Engagement', 'wpmatch' ); ?>
			</button>
			<button class="wpmatch-tab-button" data-tab="activity">
				<span class="dashicons dashicons-calendar-alt"></span>
				<?php esc_html_e( 'Daily Activity', 'wpmatch' ); ?>
			</button>
			<button class="wpmatch-tab-button" data-tab="conversions">
				<span class="dashicons dashicons-chart-line"></span>
				<?php esc_html_e( 'User Conversions', 'wpmatch' ); ?>
			</button>
		</div>

		<!-- Tab Content: Overview & Engagement -->
		<div class="wpmatch-tab-content active" id="tab-overview">
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
		</div>

		<!-- Tab Content: Daily Activity -->
		<div class="wpmatch-tab-content" id="tab-activity">
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
		</div>

		<!-- Tab Content: User Conversions -->
		<div class="wpmatch-tab-content" id="tab-conversions">
			<div class="wpmatch-conversion-analysis">
		<h3><?php esc_html_e( 'User Conversion Funnel', 'wpmatch' ); ?></h3>
		<div class="conversion-funnel">
			<div class="funnel-step">
				<div class="step-number">1</div>
				<div class="step-content">
					<h4><?php esc_html_e( 'User Registration', 'wpmatch' ); ?></h4>
					<div class="step-count"><?php echo esc_html( number_format( $registered_users ) ); ?></div>
					<div class="step-rate">100%</div>
				</div>
			</div>
			<div class="funnel-arrow">→</div>
			<div class="funnel-step">
				<div class="step-number">2</div>
				<div class="step-content">
					<h4><?php esc_html_e( 'Profile Creation', 'wpmatch' ); ?></h4>
					<div class="step-count"><?php echo esc_html( number_format( $users_with_profiles ) ); ?></div>
					<div class="step-rate"><?php echo esc_html( $profile_completion_rate ); ?>%</div>
				</div>
			</div>
			<div class="funnel-arrow">→</div>
			<div class="funnel-step">
				<div class="step-number">3</div>
				<div class="step-content">
					<h4><?php esc_html_e( 'Photo Upload', 'wpmatch' ); ?></h4>
					<div class="step-count"><?php echo esc_html( number_format( $users_with_photos ) ); ?></div>
					<div class="step-rate"><?php echo esc_html( $photo_upload_rate ); ?>%</div>
				</div>
			</div>
			<div class="funnel-arrow">→</div>
			<div class="funnel-step">
				<div class="step-number">4</div>
				<div class="step-content">
					<h4><?php esc_html_e( 'Started Swiping', 'wpmatch' ); ?></h4>
					<div class="step-count"><?php echo esc_html( number_format( $users_who_swiped ) ); ?></div>
					<div class="step-rate"><?php echo esc_html( $swipe_activation_rate ); ?>%</div>
				</div>
			</div>
			<div class="funnel-arrow">→</div>
			<div class="funnel-step">
				<div class="step-number">5</div>
				<div class="step-content">
					<h4><?php esc_html_e( 'Got Matches', 'wpmatch' ); ?></h4>
					<div class="step-count"><?php echo esc_html( number_format( $users_with_matches ) ); ?></div>
					<div class="step-rate"><?php echo esc_html( $match_success_rate ); ?>%</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Advanced Analytics Section -->
	<div class="wpmatch-advanced-analytics">
		<div class="analytics-grid">
			<!-- Peak Activity Hours -->
			<div class="analytics-container">
				<h3><?php esc_html_e( 'Peak Activity Hours (Last 7 Days)', 'wpmatch' ); ?></h3>
				<div class="activity-hours">
					<?php if ( ! empty( $activity_by_hour ) ) : ?>
						<?php foreach ( $activity_by_hour as $hour_data ) : ?>
							<?php
							$hour_12 = ( $hour_data->hour == 0 ) ? '12 AM' :
									   ( ( $hour_data->hour < 12 ) ? $hour_data->hour . ' AM' :
									   ( ( $hour_data->hour == 12 ) ? '12 PM' :
									   ( $hour_data->hour - 12 ) . ' PM' ) );
							$max_activity = max( wp_list_pluck( $activity_by_hour, 'activity_count' ) );
							$bar_width = $max_activity > 0 ? ( $hour_data->activity_count / $max_activity ) * 100 : 0;
							?>
							<div class="hour-activity">
								<span class="hour-label"><?php echo esc_html( $hour_12 ); ?></span>
								<div class="activity-bar">
									<div class="activity-fill" style="width: <?php echo esc_attr( $bar_width ); ?>%"></div>
								</div>
								<span class="activity-count"><?php echo esc_html( $hour_data->activity_count ); ?></span>
							</div>
						<?php endforeach; ?>
					<?php else : ?>
						<p><?php esc_html_e( 'No activity data available for the last 7 days.', 'wpmatch' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<!-- Top Locations -->
			<div class="analytics-container">
				<h3><?php esc_html_e( 'Top User Locations', 'wpmatch' ); ?></h3>
				<div class="location-stats">
					<?php if ( ! empty( $top_locations ) ) : ?>
						<?php foreach ( $top_locations as $location ) : ?>
							<div class="location-item">
								<span class="location-name"><?php echo esc_html( $location->location ); ?></span>
								<span class="location-count"><?php echo esc_html( number_format( $location->user_count ) ); ?></span>
							</div>
						<?php endforeach; ?>
					<?php else : ?>
						<p><?php esc_html_e( 'No location data available.', 'wpmatch' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<!-- Conversation Analytics -->
			<div class="analytics-container">
				<h3><?php esc_html_e( 'Conversation Analytics', 'wpmatch' ); ?></h3>
				<div class="conversation-stats">
					<div class="conversation-metric">
						<div class="metric-value"><?php echo esc_html( number_format( $total_conversations ) ); ?></div>
						<div class="metric-label"><?php esc_html_e( 'Total Conversations', 'wpmatch' ); ?></div>
					</div>
					<div class="conversation-metric">
						<div class="metric-value"><?php echo esc_html( number_format( $active_conversations ) ); ?></div>
						<div class="metric-label"><?php esc_html_e( 'Active (30 days)', 'wpmatch' ); ?></div>
					</div>
					<div class="conversation-metric">
						<div class="metric-value"><?php echo esc_html( $conversation_rate ); ?>%</div>
						<div class="metric-label"><?php esc_html_e( 'Match → Chat Rate', 'wpmatch' ); ?></div>
					</div>
				</div>
			</div>

			<!-- Photo Impact Analysis -->
			<div class="analytics-container">
				<h3><?php esc_html_e( 'Photo Impact on Matches', 'wpmatch' ); ?></h3>
				<div class="photo-impact">
					<div class="impact-stat">
						<div class="impact-label"><?php esc_html_e( 'Users with Photos', 'wpmatch' ); ?></div>
						<div class="impact-value"><?php echo esc_html( number_format( $users_with_photos_matches ) ); ?></div>
						<div class="impact-description"><?php esc_html_e( 'users got matches', 'wpmatch' ); ?></div>
					</div>
					<div class="impact-stat">
						<div class="impact-label"><?php esc_html_e( 'Users without Photos', 'wpmatch' ); ?></div>
						<div class="impact-value"><?php echo esc_html( number_format( $users_without_photos_matches ) ); ?></div>
						<div class="impact-description"><?php esc_html_e( 'users got matches', 'wpmatch' ); ?></div>
					</div>
					<?php if ( $users_with_photos > 0 && $users_without_photos_matches >= 0 ) : ?>
						<?php
						$photo_success_rate = round( ( $users_with_photos_matches / max( $users_with_photos, 1 ) ) * 100, 1 );
						$no_photo_users = $users_with_profiles - $users_with_photos;
						$no_photo_success_rate = $no_photo_users > 0 ? round( ( $users_without_photos_matches / $no_photo_users ) * 100, 1 ) : 0;
						?>
						<div class="impact-comparison">
							<p><strong><?php esc_html_e( 'Success Rates:', 'wpmatch' ); ?></strong></p>
							<p><?php esc_html_e( 'With Photos:', 'wpmatch' ); ?> <?php echo esc_html( $photo_success_rate ); ?>%</p>
							<p><?php esc_html_e( 'Without Photos:', 'wpmatch' ); ?> <?php echo esc_html( $no_photo_success_rate ); ?>%</p>
						</div>
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
			<button type="button" class="wpmatch-button primary" onclick="exportReport('csv')">
				<span class="dashicons dashicons-media-spreadsheet"></span>
				<?php esc_html_e( 'Export CSV', 'wpmatch' ); ?>
			</button>
			<button type="button" class="wpmatch-button secondary" onclick="exportReport('pdf')">
				<span class="dashicons dashicons-media-document"></span>
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
	/* Remove negative margins to respect WordPress admin padding */
	margin: 0;
	padding: 20px;
	min-height: calc(100vh - 32px);
}

/* Ensure Reports page header follows WordPress admin standards */
.wpmatch-reports-header {
	margin-left: 0 !important;
	margin-right: 0 !important;
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
	background: #fff;
	color: #555;
	padding: 24px 20px;
	border-radius: 12px;
	display: flex;
	align-items: center;
	box-shadow: 0 2px 4px rgba(0,0,0,0.1);
	transition: transform 0.3s ease;
	border-top: 4px solid #a8b5e8;
	min-height: 120px;
}

.metric-card:hover {
	transform: translateY(-2px);
}

.metric-card.total-users { border-top-color: #a8b5e8; }
.metric-card.active-users { border-top-color: #a8b5e8; }
.metric-card.verified-users { border-top-color: #a8b5e8; }
.metric-card.total-matches { border-top-color: #a8b5e8; }
.metric-card.total-swipes { border-top-color: #7ec699; }
.metric-card.match-rate { border-top-color: #7ec699; }
.metric-card.messages-sent { border-top-color: #7ec699; }
.metric-card.avg-matches { border-top-color: #7ec699; }

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
	margin: 0 0 8px 0;
	color: #333;
	line-height: 1.2;
	padding: 4px 0;
}

.metric-content p {
	font-size: 14px;
	margin: 0 0 5px 0;
	color: #666;
}

.metric-change {
	font-size: 12px;
	color: #888;
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
	color: #333;
}

.stat-value {
	font-weight: 700;
	color: #a8b5e8;
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

/* Advanced Analytics Styles */
.wpmatch-conversion-analysis {
	background: white;
	padding: 20px;
	border-radius: 8px;
	margin-bottom: 20px;
	box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.wpmatch-conversion-analysis h3 {
	margin: 0 0 20px 0;
	color: #2c3e50;
	border-bottom: 2px solid #667eea;
	padding-bottom: 10px;
}

.conversion-funnel {
	display: flex;
	align-items: center;
	justify-content: space-between;
	flex-wrap: wrap;
	gap: 10px;
}

.funnel-step {
	background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
	border: 2px solid #667eea;
	border-radius: 12px;
	padding: 15px;
	text-align: center;
	flex: 1;
	min-width: 120px;
	position: relative;
}

.step-number {
	background: #667eea;
	color: white;
	width: 30px;
	height: 30px;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	font-weight: bold;
	margin: 0 auto 10px;
}

.step-content h4 {
	margin: 0 0 8px 0;
	font-size: 14px;
	color: #2c3e50;
}

.step-count {
	font-size: 20px;
	font-weight: bold;
	color: #667eea;
	margin-bottom: 5px;
}

.step-rate {
	font-size: 12px;
	color: #666;
	font-weight: 600;
}

.funnel-arrow {
	font-size: 24px;
	color: #667eea;
	font-weight: bold;
}

.wpmatch-advanced-analytics {
	margin-bottom: 20px;
}

.analytics-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
	gap: 20px;
}

.analytics-container {
	background: #fff;
	padding: 20px;
	border-radius: 12px;
	box-shadow: 0 2px 4px rgba(0,0,0,0.1);
	border-top: 4px solid #a8b5e8;
}

.analytics-container h3 {
	margin: 0 0 15px 0;
	color: #333;
	font-size: 16px;
	font-weight: 600;
}

/* Activity Hours Styles */
.activity-hours {
	max-height: 200px;
	overflow-y: auto;
}

.hour-activity {
	display: flex;
	align-items: center;
	margin-bottom: 8px;
	gap: 10px;
}

.hour-label {
	width: 60px;
	font-size: 12px;
	color: #666;
}

.activity-bar {
	flex: 1;
	height: 20px;
	background: #e9ecef;
	border-radius: 10px;
	overflow: hidden;
}

.activity-fill {
	height: 100%;
	background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
	border-radius: 10px;
}

.activity-count {
	width: 40px;
	text-align: right;
	font-size: 12px;
	font-weight: 600;
	color: #2c3e50;
}

/* Location Stats Styles */
.location-stats {
	max-height: 200px;
	overflow-y: auto;
}

.location-item {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 8px 0;
	border-bottom: 1px solid #eee;
}

.location-item:last-child {
	border-bottom: none;
}

.location-name {
	font-weight: 600;
	color: #2c3e50;
}

.location-count {
	background: #667eea;
	color: white;
	padding: 2px 8px;
	border-radius: 12px;
	font-size: 12px;
	font-weight: 600;
}

/* Conversation Stats Styles */
.conversation-stats {
	display: flex;
	justify-content: space-around;
	gap: 15px;
}

.conversation-metric {
	text-align: center;
	flex: 1;
}

.conversation-metric .metric-value {
	font-size: 24px;
	font-weight: bold;
	color: #667eea;
	margin-bottom: 5px;
}

.conversation-metric .metric-label {
	font-size: 12px;
	color: #666;
}

/* Photo Impact Styles */
.photo-impact {
	display: flex;
	flex-direction: column;
	gap: 15px;
}

.impact-stat {
	padding: 15px;
	background: white;
	border-radius: 8px;
	border: 1px solid #e9ecef;
	text-align: center;
}

.impact-label {
	font-size: 12px;
	color: #666;
	margin-bottom: 5px;
}

.impact-value {
	font-size: 20px;
	font-weight: bold;
	color: #667eea;
	margin-bottom: 5px;
}

.impact-description {
	font-size: 12px;
	color: #666;
}

.impact-comparison {
	padding: 15px;
	background: #e9ecef;
	border-radius: 8px;
	font-size: 14px;
}

.impact-comparison p {
	margin: 5px 0;
}

@media (max-width: 768px) {
	.metrics-cards {
		grid-template-columns: 1fr;
	}

	.charts-grid {
		grid-template-columns: 1fr;
	}

	.analytics-grid {
		grid-template-columns: 1fr;
	}

	.export-buttons {
		flex-direction: column;
	}

	.conversion-funnel {
		flex-direction: column;
	}

	.funnel-arrow {
		transform: rotate(90deg);
	}

	.conversation-stats {
		flex-direction: column;
		gap: 10px;
	}
}
</style>

		</div>
	</div>

<!-- Tabbed Interface CSS -->
<style>
/* Tabs Container */
.wpmatch-tabs-container {
	background: white;
	border-radius: 12px;
	box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
	margin-bottom: 30px;
	overflow: hidden;
}

/* Tab Navigation */
.wpmatch-tabs-nav {
	display: flex;
	background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
	border-bottom: 1px solid #dee2e6;
}

.wpmatch-tab-button {
	flex: 1;
	background: none;
	border: none;
	padding: 20px 25px;
	font-size: 15px;
	font-weight: 600;
	color: #6c757d;
	cursor: pointer;
	transition: all 0.3s ease;
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 8px;
	position: relative;
	border-bottom: 3px solid transparent;
}

.wpmatch-tab-button:hover {
	background: rgba(102, 126, 234, 0.1);
	color: #495057;
}

.wpmatch-tab-button.active {
	background: white;
	color: #667eea;
	border-bottom-color: #667eea;
}

.wpmatch-tab-button .dashicons {
	font-size: 18px;
}

/* Tab Content */
.wpmatch-tab-content {
	display: none;
	padding: 20px;
	animation: fadeIn 0.3s ease-in;
	box-sizing: border-box;
	max-width: 100%;
	overflow-x: auto;
}

.wpmatch-tab-content.active {
	display: block;
}

/* Fix content within tabs to prevent overflow */
.wpmatch-tab-content .wpmatch-metrics-grid,
.wpmatch-tab-content .wpmatch-charts-section,
.wpmatch-tab-content .wpmatch-conversion-analysis {
	max-width: 100%;
	box-sizing: border-box;
}

.wpmatch-tab-content .charts-grid,
.wpmatch-tab-content .metrics-cards {
	max-width: 100%;
	box-sizing: border-box;
}

@keyframes fadeIn {
	from { opacity: 0; transform: translateY(10px); }
	to { opacity: 1; transform: translateY(0); }
}

/* Responsive Design */
@media (max-width: 768px) {
	.wpmatch-tabs-nav {
		flex-direction: column;
	}

	.wpmatch-tab-button {
		text-align: center;
		padding: 15px 20px;
		border-bottom: 1px solid #dee2e6;
		border-right: none;
	}

	.wpmatch-tab-button.active {
		border-bottom: 1px solid #dee2e6;
		border-left: 3px solid #667eea;
	}

	.wpmatch-tab-content {
		padding: 20px;
	}
}
</style>

<!-- Tab Switching JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
	// Tab switching functionality
	const tabButtons = document.querySelectorAll('.wpmatch-tab-button');
	const tabContents = document.querySelectorAll('.wpmatch-tab-content');

	tabButtons.forEach(button => {
		button.addEventListener('click', function() {
			const targetTab = this.getAttribute('data-tab');

			// Remove active class from all buttons and contents
			tabButtons.forEach(btn => btn.classList.remove('active'));
			tabContents.forEach(content => content.classList.remove('active'));

			// Add active class to clicked button
			this.classList.add('active');

			// Show target tab content
			const targetContent = document.getElementById('tab-' + targetTab);
			if (targetContent) {
				targetContent.classList.add('active');
			}

			// Save active tab to localStorage
			localStorage.setItem('wpmatch-active-tab', targetTab);
		});
	});

	// Restore active tab from localStorage
	const savedTab = localStorage.getItem('wpmatch-active-tab');
	if (savedTab) {
		const savedButton = document.querySelector(`[data-tab="${savedTab}"]`);
		const savedContent = document.getElementById(`tab-${savedTab}`);

		if (savedButton && savedContent) {
			// Remove active from all
			tabButtons.forEach(btn => btn.classList.remove('active'));
			tabContents.forEach(content => content.classList.remove('active'));

			// Activate saved tab
			savedButton.classList.add('active');
			savedContent.classList.add('active');
		}
	}
});
</script>

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