<?php
/**
 * Revenue Tracking Dashboard
 *
 * @package WPMatch
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// Date range handling
$date_range = isset( $_GET['date_range'] ) ? sanitize_text_field( $_GET['date_range'] ) : '30_days';
$custom_start = isset( $_GET['custom_start'] ) ? sanitize_text_field( $_GET['custom_start'] ) : '';
$custom_end = isset( $_GET['custom_end'] ) ? sanitize_text_field( $_GET['custom_end'] ) : '';

// Calculate date range
switch ( $date_range ) {
	case '7_days':
		$start_date = date( 'Y-m-d', strtotime( '-7 days' ) );
		$end_date = date( 'Y-m-d' );
		break;
	case '30_days':
		$start_date = date( 'Y-m-d', strtotime( '-30 days' ) );
		$end_date = date( 'Y-m-d' );
		break;
	case '90_days':
		$start_date = date( 'Y-m-d', strtotime( '-90 days' ) );
		$end_date = date( 'Y-m-d' );
		break;
	case 'custom':
		$start_date = $custom_start ? $custom_start : date( 'Y-m-d', strtotime( '-30 days' ) );
		$end_date = $custom_end ? $custom_end : date( 'Y-m-d' );
		break;
	default:
		$start_date = date( 'Y-m-d', strtotime( '-30 days' ) );
		$end_date = date( 'Y-m-d' );
}

// Revenue calculations
$total_revenue = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT SUM(amount)
		FROM {$wpdb->prefix}wpmatch_transactions
		WHERE status = 'completed'
		AND payment_date BETWEEN %s AND %s",
		$start_date . ' 00:00:00',
		$end_date . ' 23:59:59'
	)
);

$total_revenue = $total_revenue ? floatval( $total_revenue ) : 0;

// Previous period comparison
$prev_start = date( 'Y-m-d', strtotime( $start_date . ' - ' . ( strtotime( $end_date ) - strtotime( $start_date ) ) . ' seconds' ) );
$prev_end = date( 'Y-m-d', strtotime( $start_date . ' - 1 day' ) );

$prev_revenue = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT SUM(amount)
		FROM {$wpdb->prefix}wpmatch_transactions
		WHERE status = 'completed'
		AND payment_date BETWEEN %s AND %s",
		$prev_start . ' 00:00:00',
		$prev_end . ' 23:59:59'
	)
);

$prev_revenue = $prev_revenue ? floatval( $prev_revenue ) : 0;
$revenue_change = $prev_revenue > 0 ? ( ( $total_revenue - $prev_revenue ) / $prev_revenue ) * 100 : 0;

// Subscription metrics
$active_subscriptions = $wpdb->get_var(
	"SELECT COUNT(*)
	FROM {$wpdb->prefix}wpmatch_subscriptions
	WHERE status = 'active'"
);

$cancelled_subscriptions = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*)
		FROM {$wpdb->prefix}wpmatch_subscriptions
		WHERE status = 'cancelled'
		AND cancelled_date BETWEEN %s AND %s",
		$start_date . ' 00:00:00',
		$end_date . ' 23:59:59'
	)
);

$new_subscriptions = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*)
		FROM {$wpdb->prefix}wpmatch_subscriptions
		WHERE created_date BETWEEN %s AND %s",
		$start_date . ' 00:00:00',
		$end_date . ' 23:59:59'
	)
);

// Monthly Recurring Revenue (MRR)
$mrr = $wpdb->get_var(
	"SELECT SUM(
		CASE
			WHEN billing_period = 'monthly' THEN amount
			WHEN billing_period = 'yearly' THEN amount / 12
			WHEN billing_period = 'quarterly' THEN amount / 3
			ELSE amount
		END
	) as mrr
	FROM {$wpdb->prefix}wpmatch_subscriptions
	WHERE status = 'active'"
);

$mrr = $mrr ? floatval( $mrr ) : 0;

// Revenue by plan type
$revenue_by_plan = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT
			s.plan_type,
			COUNT(*) as subscriber_count,
			SUM(t.amount) as total_revenue,
			AVG(t.amount) as avg_revenue
		FROM {$wpdb->prefix}wpmatch_subscriptions s
		LEFT JOIN {$wpdb->prefix}wpmatch_transactions t ON s.id = t.subscription_id
		WHERE t.status = 'completed'
		AND t.payment_date BETWEEN %s AND %s
		GROUP BY s.plan_type
		ORDER BY total_revenue DESC",
		$start_date . ' 00:00:00',
		$end_date . ' 23:59:59'
	)
);

// Daily revenue chart data
$daily_revenue = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT
			DATE(payment_date) as date,
			SUM(amount) as revenue,
			COUNT(*) as transactions
		FROM {$wpdb->prefix}wpmatch_transactions
		WHERE status = 'completed'
		AND payment_date BETWEEN %s AND %s
		GROUP BY DATE(payment_date)
		ORDER BY date ASC",
		$start_date . ' 00:00:00',
		$end_date . ' 23:59:59'
	)
);

// Top revenue-generating users
$top_users = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT
			u.ID,
			u.display_name,
			u.user_email,
			SUM(t.amount) as total_spent,
			COUNT(t.id) as transaction_count,
			MAX(t.payment_date) as last_payment
		FROM {$wpdb->users} u
		JOIN {$wpdb->prefix}wpmatch_transactions t ON u.ID = t.user_id
		WHERE t.status = 'completed'
		AND t.payment_date BETWEEN %s AND %s
		GROUP BY u.ID
		ORDER BY total_spent DESC
		LIMIT 10",
		$start_date . ' 00:00:00',
		$end_date . ' 23:59:59'
	)
);

// Failed transactions analysis
$failed_transactions = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*)
		FROM {$wpdb->prefix}wpmatch_transactions
		WHERE status = 'failed'
		AND created_date BETWEEN %s AND %s",
		$start_date . ' 00:00:00',
		$end_date . ' 23:59:59'
	)
);

$total_transactions = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*)
		FROM {$wpdb->prefix}wpmatch_transactions
		WHERE created_date BETWEEN %s AND %s",
		$start_date . ' 00:00:00',
		$end_date . ' 23:59:59'
	)
);

$success_rate = $total_transactions > 0 ? ( ( $total_transactions - $failed_transactions ) / $total_transactions ) * 100 : 0;
?>

<div class="wrap wpmatch-admin">
	<div class="wpmatch-admin-header">
		<div class="wpmatch-header-content">
			<div class="wpmatch-header-main">
				<h1>
					<span class="dashicons dashicons-chart-line"></span>
					<?php esc_html_e( 'Revenue Analytics', 'wpmatch' ); ?>
				</h1>
				<p><?php esc_html_e( 'Comprehensive revenue tracking and subscription analytics for your dating platform.', 'wpmatch' ); ?></p>
			</div>
			<div class="wpmatch-header-actions">
				<button type="button" class="wpmatch-button" id="export-revenue-report">
					<span class="dashicons dashicons-download"></span>
					<?php esc_html_e( 'Export Report', 'wpmatch' ); ?>
				</button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpmatch' ) ); ?>" class="wpmatch-button secondary">
					<span class="dashicons dashicons-analytics"></span>
					<?php esc_html_e( 'View Dashboard', 'wpmatch' ); ?>
				</a>
			</div>
		</div>
	</div>

	<!-- Date Range Selector -->
	<div class="revenue-filters">
		<form method="GET" action="" class="filter-form">
			<input type="hidden" name="page" value="wpmatch-revenue">

			<div class="filter-group">
				<label for="date_range"><?php esc_html_e( 'Date Range:', 'wpmatch' ); ?></label>
				<select name="date_range" id="date_range" onchange="toggleCustomDates()">
					<option value="7_days" <?php selected( $date_range, '7_days' ); ?>><?php esc_html_e( 'Last 7 Days', 'wpmatch' ); ?></option>
					<option value="30_days" <?php selected( $date_range, '30_days' ); ?>><?php esc_html_e( 'Last 30 Days', 'wpmatch' ); ?></option>
					<option value="90_days" <?php selected( $date_range, '90_days' ); ?>><?php esc_html_e( 'Last 90 Days', 'wpmatch' ); ?></option>
					<option value="custom" <?php selected( $date_range, 'custom' ); ?>><?php esc_html_e( 'Custom Range', 'wpmatch' ); ?></option>
				</select>
			</div>

			<div class="filter-group custom-dates" style="<?php echo $date_range === 'custom' ? '' : 'display: none;'; ?>">
				<label for="custom_start"><?php esc_html_e( 'Start Date:', 'wpmatch' ); ?></label>
				<input type="date" name="custom_start" id="custom_start" value="<?php echo esc_attr( $custom_start ); ?>">

				<label for="custom_end"><?php esc_html_e( 'End Date:', 'wpmatch' ); ?></label>
				<input type="date" name="custom_end" id="custom_end" value="<?php echo esc_attr( $custom_end ); ?>">
			</div>

			<button type="submit" class="wpmatch-button"><?php esc_html_e( 'Apply Filter', 'wpmatch' ); ?></button>
		</form>
	</div>

	<!-- Revenue Overview Cards -->
	<div class="revenue-overview">
		<div class="revenue-card">
			<div class="card-header">
				<h3><?php esc_html_e( 'Total Revenue', 'wpmatch' ); ?></h3>
				<span class="revenue-change <?php echo $revenue_change >= 0 ? 'positive' : 'negative'; ?>">
					<?php echo $revenue_change >= 0 ? '+' : ''; ?><?php echo number_format( $revenue_change, 1 ); ?>%
				</span>
			</div>
			<div class="card-value">$<?php echo number_format( $total_revenue, 2 ); ?></div>
			<div class="card-subtitle"><?php printf( esc_html__( '%s to %s', 'wpmatch' ), $start_date, $end_date ); ?></div>
		</div>

		<div class="revenue-card">
			<div class="card-header">
				<h3><?php esc_html_e( 'Monthly Recurring Revenue', 'wpmatch' ); ?></h3>
			</div>
			<div class="card-value">$<?php echo number_format( $mrr, 2 ); ?></div>
			<div class="card-subtitle"><?php esc_html_e( 'Estimated monthly income', 'wpmatch' ); ?></div>
		</div>

		<div class="revenue-card">
			<div class="card-header">
				<h3><?php esc_html_e( 'Active Subscriptions', 'wpmatch' ); ?></h3>
			</div>
			<div class="card-value"><?php echo number_format( $active_subscriptions ); ?></div>
			<div class="card-subtitle">
				<span class="new-subs">+<?php echo $new_subscriptions; ?> <?php esc_html_e( 'new', 'wpmatch' ); ?></span>
				<span class="cancelled-subs">-<?php echo $cancelled_subscriptions; ?> <?php esc_html_e( 'cancelled', 'wpmatch' ); ?></span>
			</div>
		</div>

		<div class="revenue-card">
			<div class="card-header">
				<h3><?php esc_html_e( 'Payment Success Rate', 'wpmatch' ); ?></h3>
			</div>
			<div class="card-value"><?php echo number_format( $success_rate, 1 ); ?>%</div>
			<div class="card-subtitle"><?php printf( esc_html__( '%d successful / %d total', 'wpmatch' ), $total_transactions - $failed_transactions, $total_transactions ); ?></div>
		</div>
	</div>

	<!-- Revenue Chart -->
	<div class="revenue-chart-section">
		<div class="card">
			<h2><?php esc_html_e( 'Daily Revenue Trend', 'wpmatch' ); ?></h2>
			<div class="chart-container">
				<canvas id="revenueChart" width="400" height="200"></canvas>
			</div>
		</div>
	</div>

	<!-- Revenue by Plan & Top Users -->
	<div class="revenue-details">
		<div class="card revenue-by-plan">
			<h2><?php esc_html_e( 'Revenue by Plan Type', 'wpmatch' ); ?></h2>
			<?php if ( ! empty( $revenue_by_plan ) ) : ?>
				<div class="plan-revenue-table">
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Plan Type', 'wpmatch' ); ?></th>
								<th><?php esc_html_e( 'Subscribers', 'wpmatch' ); ?></th>
								<th><?php esc_html_e( 'Total Revenue', 'wpmatch' ); ?></th>
								<th><?php esc_html_e( 'Avg. Revenue', 'wpmatch' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $revenue_by_plan as $plan ) : ?>
								<tr>
									<td><strong><?php echo esc_html( ucfirst( $plan->plan_type ) ); ?></strong></td>
									<td><?php echo number_format( $plan->subscriber_count ); ?></td>
									<td><strong>$<?php echo number_format( $plan->total_revenue, 2 ); ?></strong></td>
									<td>$<?php echo number_format( $plan->avg_revenue, 2 ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else : ?>
				<p><?php esc_html_e( 'No revenue data available for the selected period.', 'wpmatch' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="card top-users">
			<h2><?php esc_html_e( 'Top Revenue Users', 'wpmatch' ); ?></h2>
			<?php if ( ! empty( $top_users ) ) : ?>
				<div class="top-users-list">
					<?php foreach ( $top_users as $index => $user ) : ?>
						<div class="user-revenue-item">
							<div class="user-rank">#<?php echo $index + 1; ?></div>
							<div class="user-info">
								<strong><?php echo esc_html( $user->display_name ); ?></strong>
								<span class="user-email"><?php echo esc_html( $user->user_email ); ?></span>
							</div>
							<div class="user-stats">
								<div class="total-spent">$<?php echo number_format( $user->total_spent, 2 ); ?></div>
								<div class="transaction-count"><?php echo $user->transaction_count; ?> <?php esc_html_e( 'transactions', 'wpmatch' ); ?></div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p><?php esc_html_e( 'No user revenue data available for the selected period.', 'wpmatch' ); ?></p>
			<?php endif; ?>
		</div>
	</div>

	<!-- Revenue Insights -->
	<div class="revenue-insights">
		<div class="card">
			<h2><?php esc_html_e( 'Revenue Insights', 'wpmatch' ); ?></h2>
			<div class="insights-grid">
				<div class="insight-item">
					<h4><?php esc_html_e( 'Revenue Growth', 'wpmatch' ); ?></h4>
					<p>
						<?php if ( $revenue_change > 0 ) : ?>
							<?php printf( esc_html__( 'Revenue has increased by %s%% compared to the previous period.', 'wpmatch' ), number_format( $revenue_change, 1 ) ); ?>
						<?php elseif ( $revenue_change < 0 ) : ?>
							<?php printf( esc_html__( 'Revenue has decreased by %s%% compared to the previous period.', 'wpmatch' ), number_format( abs( $revenue_change ), 1 ) ); ?>
						<?php else : ?>
							<?php esc_html_e( 'Revenue has remained stable compared to the previous period.', 'wpmatch' ); ?>
						<?php endif; ?>
					</p>
				</div>

				<div class="insight-item">
					<h4><?php esc_html_e( 'Subscription Health', 'wpmatch' ); ?></h4>
					<p>
						<?php if ( $new_subscriptions > $cancelled_subscriptions ) : ?>
							<?php printf( esc_html__( 'Positive growth with %d new subscriptions vs %d cancellations.', 'wpmatch' ), $new_subscriptions, $cancelled_subscriptions ); ?>
						<?php elseif ( $new_subscriptions < $cancelled_subscriptions ) : ?>
							<?php printf( esc_html__( 'Concerning trend: %d cancellations vs %d new subscriptions.', 'wpmatch' ), $cancelled_subscriptions, $new_subscriptions ); ?>
						<?php else : ?>
							<?php esc_html_e( 'Subscription growth is stable with equal new subscriptions and cancellations.', 'wpmatch' ); ?>
						<?php endif; ?>
					</p>
				</div>

				<div class="insight-item">
					<h4><?php esc_html_e( 'Payment Performance', 'wpmatch' ); ?></h4>
					<p>
						<?php if ( $success_rate >= 95 ) : ?>
							<?php printf( esc_html__( 'Excellent payment success rate of %s%%.', 'wpmatch' ), number_format( $success_rate, 1 ) ); ?>
						<?php elseif ( $success_rate >= 85 ) : ?>
							<?php printf( esc_html__( 'Good payment success rate of %s%%. Room for improvement.', 'wpmatch' ), number_format( $success_rate, 1 ) ); ?>
						<?php else : ?>
							<?php printf( esc_html__( 'Low payment success rate of %s%%. Investigate payment issues.', 'wpmatch' ), number_format( $success_rate, 1 ) ); ?>
						<?php endif; ?>
					</p>
				</div>

				<div class="insight-item">
					<h4><?php esc_html_e( 'Revenue Forecast', 'wpmatch' ); ?></h4>
					<p>
						<?php
						$annual_forecast = $mrr * 12;
						printf( esc_html__( 'Based on current MRR, projected annual revenue: $%s', 'wpmatch' ), number_format( $annual_forecast, 2 ) );
						?>
					</p>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
// Chart.js implementation for revenue chart
document.addEventListener('DOMContentLoaded', function() {
	const ctx = document.getElementById('revenueChart').getContext('2d');

	const chartData = {
		labels: [
			<?php
			foreach ( $daily_revenue as $day ) {
				echo '"' . date( 'M j', strtotime( $day->date ) ) . '",';
			}
			?>
		],
		datasets: [{
			label: 'Daily Revenue',
			data: [
				<?php
				foreach ( $daily_revenue as $day ) {
					echo floatval( $day->revenue ) . ',';
				}
				?>
			],
			borderColor: '#667eea',
			backgroundColor: 'rgba(102, 126, 234, 0.1)',
			borderWidth: 2,
			fill: true,
			tension: 0.4
		}]
	};

	const chart = new Chart(ctx, {
		type: 'line',
		data: chartData,
		options: {
			responsive: true,
			maintainAspectRatio: false,
			scales: {
				y: {
					beginAtZero: true,
					ticks: {
						callback: function(value) {
							return '$' + value.toFixed(2);
						}
					}
				}
			},
			plugins: {
				tooltip: {
					callbacks: {
						label: function(context) {
							return 'Revenue: $' + context.parsed.y.toFixed(2);
						}
					}
				}
			}
		}
	});
});

function toggleCustomDates() {
	const dateRange = document.getElementById('date_range').value;
	const customDates = document.querySelector('.custom-dates');

	if (dateRange === 'custom') {
		customDates.style.display = 'block';
	} else {
		customDates.style.display = 'none';
	}
}

// Export revenue report
document.getElementById('export-revenue-report').addEventListener('click', function() {
	const button = this;
	const originalText = button.innerHTML;

	button.disabled = true;
	button.innerHTML = '<span class="dashicons dashicons-update-alt"></span> <?php esc_html_e( 'Exporting...', 'wpmatch' ); ?>';

	// Create export data
	const exportData = {
		period: '<?php echo esc_js( $start_date . ' to ' . $end_date ); ?>',
		total_revenue: <?php echo $total_revenue; ?>,
		mrr: <?php echo $mrr; ?>,
		active_subscriptions: <?php echo $active_subscriptions; ?>,
		success_rate: <?php echo $success_rate; ?>,
		revenue_by_plan: <?php echo wp_json_encode( $revenue_by_plan ); ?>,
		daily_revenue: <?php echo wp_json_encode( $daily_revenue ); ?>,
		top_users: <?php echo wp_json_encode( $top_users ); ?>
	};

	// Create and download CSV file
	const csv = convertToCSV(exportData);
	const blob = new Blob([csv], { type: 'text/csv' });
	const url = window.URL.createObjectURL(blob);
	const a = document.createElement('a');
	a.href = url;
	a.download = 'wpmatch-revenue-report-' + new Date().toISOString().split('T')[0] + '.csv';
	document.body.appendChild(a);
	a.click();
	document.body.removeChild(a);
	window.URL.revokeObjectURL(url);

	button.disabled = false;
	button.innerHTML = originalText;
});

function convertToCSV(data) {
	let csv = 'WPMatch Revenue Report\n';
	csv += 'Period,' + data.period + '\n';
	csv += 'Total Revenue,$' + data.total_revenue.toFixed(2) + '\n';
	csv += 'Monthly Recurring Revenue,$' + data.mrr.toFixed(2) + '\n';
	csv += 'Active Subscriptions,' + data.active_subscriptions + '\n';
	csv += 'Payment Success Rate,' + data.success_rate.toFixed(1) + '%\n\n';

	csv += 'Daily Revenue\n';
	csv += 'Date,Revenue,Transactions\n';
	data.daily_revenue.forEach(function(day) {
		csv += day.date + ',$' + parseFloat(day.revenue).toFixed(2) + ',' + day.transactions + '\n';
	});

	return csv;
}
</script>

<style>
.revenue-filters {
	background: white;
	padding: 20px;
	border-radius: 8px;
	margin-bottom: 20px;
	box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.filter-form {
	display: flex;
	align-items: center;
	gap: 20px;
	flex-wrap: wrap;
}

.filter-group {
	display: flex;
	align-items: center;
	gap: 10px;
}

.filter-group label {
	font-weight: 600;
	color: #2c3e50;
}

.filter-group select,
.filter-group input {
	padding: 8px 12px;
	border: 1px solid #ddd;
	border-radius: 4px;
}

.revenue-overview {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
	gap: 20px;
	margin-bottom: 30px;
}

.revenue-card {
	background: white;
	border-radius: 12px;
	padding: 24px;
	box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
	border: 1px solid #e1e8ed;
}

.card-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 16px;
}

.card-header h3 {
	margin: 0;
	font-size: 14px;
	font-weight: 600;
	color: #64748b;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}

.revenue-change {
	font-size: 14px;
	font-weight: 600;
	padding: 4px 8px;
	border-radius: 6px;
}

.revenue-change.positive {
	color: #10b981;
	background: #ecfdf5;
}

.revenue-change.negative {
	color: #ef4444;
	background: #fef2f2;
}

.card-value {
	font-size: 32px;
	font-weight: 700;
	color: #1e293b;
	margin-bottom: 8px;
}

.card-subtitle {
	font-size: 14px;
	color: #64748b;
}

.new-subs {
	color: #10b981;
	margin-right: 12px;
}

.cancelled-subs {
	color: #ef4444;
}

.revenue-chart-section {
	margin-bottom: 30px;
}

.chart-container {
	position: relative;
	height: 300px;
	margin-top: 20px;
}

.revenue-details {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 20px;
	margin-bottom: 30px;
}

.plan-revenue-table {
	margin-top: 20px;
}

.plan-revenue-table table {
	width: 100%;
	border-collapse: collapse;
}

.plan-revenue-table th,
.plan-revenue-table td {
	padding: 12px;
	text-align: left;
	border-bottom: 1px solid #e1e8ed;
}

.plan-revenue-table th {
	background: #f8fafc;
	font-weight: 600;
	color: #374151;
}

.top-users-list {
	margin-top: 20px;
}

.user-revenue-item {
	display: flex;
	align-items: center;
	padding: 16px;
	border-bottom: 1px solid #e1e8ed;
	transition: background-color 0.2s;
}

.user-revenue-item:hover {
	background: #f8fafc;
}

.user-rank {
	width: 40px;
	height: 40px;
	border-radius: 50%;
	background: #667eea;
	color: white;
	display: flex;
	align-items: center;
	justify-content: center;
	font-weight: 600;
	margin-right: 16px;
}

.user-info {
	flex: 1;
	margin-right: 16px;
}

.user-info strong {
	display: block;
	color: #1e293b;
	margin-bottom: 4px;
}

.user-email {
	font-size: 14px;
	color: #64748b;
}

.user-stats {
	text-align: right;
}

.total-spent {
	font-size: 18px;
	font-weight: 600;
	color: #1e293b;
	margin-bottom: 4px;
}

.transaction-count {
	font-size: 14px;
	color: #64748b;
}

.revenue-insights {
	margin-bottom: 30px;
}

.insights-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
	gap: 24px;
	margin-top: 20px;
}

.insight-item {
	padding: 20px;
	background: #f8fafc;
	border-radius: 8px;
	border-left: 4px solid #667eea;
}

.insight-item h4 {
	margin: 0 0 12px 0;
	color: #1e293b;
	font-size: 16px;
	font-weight: 600;
}

.insight-item p {
	margin: 0;
	color: #475569;
	line-height: 1.6;
}

@media (max-width: 768px) {
	.revenue-details {
		grid-template-columns: 1fr;
	}

	.filter-form {
		flex-direction: column;
		align-items: stretch;
	}

	.filter-group {
		flex-direction: column;
		align-items: stretch;
	}

	.insights-grid {
		grid-template-columns: 1fr;
	}
}
</style>

<!-- Chart.js should be included locally or use WordPress-included charting solutions -->
<!-- TODO: Include local Chart.js library or use WordPress admin charting components -->