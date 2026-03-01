<?php
defined( 'ABSPATH' ) || exit;

$stats       = RedLine_Devices::get_stats();
$poh_dist    = RedLine_Devices::get_poh_distribution();
$daily       = RedLine_Devices::get_daily_active( 30 );
$alert_counts = RedLine_Alerts::count_by_status();
$events      = RedLine_Analytics::get_event_counts( 7 );
$recent      = RedLine_Analytics::get_recent_events( 20 );

$total_alerts = array_sum( $alert_counts );
$alert_clicks = $events['alert_click'] ?? 0;
$alert_impr   = $events['alert_impression'] ?? 0;
$ctr          = $alert_impr > 0 ? round( $alert_clicks / $alert_impr * 100, 1 ) : 0;
$active_polls = count( RedLine_Polls::get_active() );
?>
<div class="wrap redline-wrap">
	<h1 class="redline-title">
		<span class="redline-icon">📞</span> Red Line — Dashboard
	</h1>

	<?php if ( isset( $_GET['msg'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>Action completed successfully.</p></div>
	<?php endif; ?>

	<div class="rl-cards">
		<div class="rl-card">
			<div class="rl-card-label">Total Installs</div>
			<div class="rl-card-value"><?php echo esc_html( number_format( $stats['total_installs'] ) ); ?></div>
		</div>
		<div class="rl-card">
			<div class="rl-card-label">Active (7 days)</div>
			<div class="rl-card-value"><?php echo esc_html( number_format( $stats['active_7d'] ) ); ?></div>
		</div>
		<div class="rl-card">
			<div class="rl-card-label">Verified Humans</div>
			<div class="rl-card-value">
				<?php echo esc_html( number_format( $stats['verified'] ) ); ?>
				<small>(<?php echo esc_html( $stats['verified_pct'] ); ?>%)</small>
			</div>
		</div>
		<div class="rl-card">
			<div class="rl-card-label">Avg PoH Score</div>
			<div class="rl-card-value"><?php echo esc_html( $stats['avg_poh'] ); ?></div>
		</div>
		<div class="rl-card">
			<div class="rl-card-label">Active Polls</div>
			<div class="rl-card-value"><?php echo esc_html( $active_polls ); ?></div>
		</div>
		<div class="rl-card">
			<div class="rl-card-label">Alert CTR</div>
			<div class="rl-card-value"><?php echo esc_html( $ctr ); ?>%</div>
		</div>
	</div>

	<div class="rl-charts-grid">
		<div class="rl-chart-box">
			<h3>Daily Active Users (30 days)</h3>
			<canvas id="chartDAU" height="250"></canvas>
		</div>
		<div class="rl-chart-box">
			<h3>PoH Score Distribution</h3>
			<canvas id="chartPoH" height="250"></canvas>
		</div>
	</div>

	<div class="rl-section">
		<h3>Recent Activity</h3>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th>Time</th>
					<th>Event</th>
					<th>Device</th>
					<th>PoH</th>
					<th>Region</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $recent as $event ) : ?>
					<tr>
						<td><?php echo esc_html( $event['created_at'] ); ?></td>
						<td><span class="rl-event-badge"><?php echo esc_html( $event['event_type'] ); ?></span></td>
						<td><code><?php echo esc_html( substr( $event['device_hash'], 0, 12 ) ); ?>…</code></td>
						<td><?php echo esc_html( $event['poh_score'] ); ?></td>
						<td><?php echo esc_html( $event['geo_region'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				<?php if ( empty( $recent ) ) : ?>
					<tr><td colspan="5">No recent events.</td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	var dauData = <?php echo wp_json_encode( $daily ); ?>;
	var pohData = <?php echo wp_json_encode( $poh_dist ); ?>;

	if (typeof RedLineCharts !== 'undefined') {
		RedLineCharts.lineChart('chartDAU', dauData.map(d => d.date), dauData.map(d => d.value), 'Active Users');
		RedLineCharts.doughnutChart('chartPoH', ['Verified (80+)', 'Likely (50-79)', 'Suspicious (20-49)', 'Likely Bot (<20)'],
			[pohData.verified, pohData.likely, pohData.suspicious, pohData.bot],
			['#2E7D32', '#F9A825', '#E65100', '#C62828']);
	}
});
</script>
