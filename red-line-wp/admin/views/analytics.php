<?php
defined( 'ABSPATH' ) || exit;

$stats      = RedLine_Devices::get_stats();
$poh_dist   = RedLine_Devices::get_poh_distribution();
$daily      = RedLine_Devices::get_daily_active( 30 );
$events     = RedLine_Analytics::get_event_counts( 30 );
$daily_events = RedLine_Analytics::get_daily_events( 30 );
$top_pages  = RedLine_Analytics::get_top_pages( 10, 30 );
$geo_data   = RedLine_Devices::get_geo_distribution();
$recent     = RedLine_Analytics::get_recent_events( 30 );

$alerts     = RedLine_Alerts::get_all( 'published' );
?>
<div class="wrap redline-wrap">
	<h1 class="redline-title">
		<span class="redline-icon">📈</span> Red Line — Analytics
	</h1>

	<!-- User Growth -->
	<div class="rl-charts-grid">
		<div class="rl-chart-box rl-chart-wide">
			<h3>User Growth (30 days)</h3>
			<canvas id="chartGrowth" height="200"></canvas>
		</div>
	</div>

	<!-- Engagement -->
	<div class="rl-charts-grid">
		<div class="rl-chart-box">
			<h3>Engagement (30 days)</h3>
			<canvas id="chartEngagement" height="250"></canvas>
		</div>
		<div class="rl-chart-box">
			<h3>PoH Distribution</h3>
			<canvas id="chartPoHDist" height="250"></canvas>
		</div>
	</div>

	<!-- Geographic -->
	<div class="rl-section">
		<h3>Geographic Distribution (US)</h3>
		<div class="rl-geo-grid">
			<?php
			arsort( $geo_data );
			$top_states = array_slice( $geo_data, 0, 20, true );
			$max_val = ! empty( $top_states ) ? max( $top_states ) : 1;
			foreach ( $top_states as $state => $count ) :
				$pct = round( $count / $max_val * 100 );
			?>
				<div class="rl-geo-row">
					<span class="rl-geo-state"><?php echo esc_html( $state ); ?></span>
					<div class="rl-geo-bar-wrap">
						<div class="rl-geo-bar" style="width:<?php echo esc_attr( $pct ); ?>%"></div>
					</div>
					<span class="rl-geo-count"><?php echo esc_html( number_format( $count ) ); ?></span>
				</div>
			<?php endforeach; ?>
			<?php if ( empty( $geo_data ) ) : ?>
				<p>No geographic data yet.</p>
			<?php endif; ?>
		</div>
	</div>

	<!-- Top Pages -->
	<div class="rl-section">
		<h3>Top WhiteHouse.gov Pages</h3>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width:40%">URL</th>
					<th>Views</th>
					<th>Avg Time (sec)</th>
					<th>Avg Scroll Depth</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $top_pages as $page ) : ?>
					<tr>
						<td><code><?php echo esc_html( $page['url'] ); ?></code></td>
						<td><?php echo esc_html( number_format( $page['views'] ) ); ?></td>
						<td><?php echo esc_html( round( $page['avg_time'] ) ); ?>s</td>
						<td><?php echo esc_html( round( $page['avg_scroll'] * 100 ) ); ?>%</td>
					</tr>
				<?php endforeach; ?>
				<?php if ( empty( $top_pages ) ) : ?>
					<tr><td colspan="4">No page visit data yet.</td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<!-- Alert Performance -->
	<div class="rl-section">
		<h3>Alert Performance</h3>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width:40%">Headline</th>
					<th>Category</th>
					<th>Impressions</th>
					<th>Clicks</th>
					<th>CTR</th>
				</tr>
			</thead>
			<tbody>
				<?php
				usort( $alerts, function( $a, $b ) {
					$ctr_a = $a['impressions'] > 0 ? $a['clicks'] / $a['impressions'] : 0;
					$ctr_b = $b['impressions'] > 0 ? $b['clicks'] / $b['impressions'] : 0;
					return $ctr_b <=> $ctr_a;
				});
				foreach ( $alerts as $a ) :
					$ctr = $a['impressions'] > 0 ? round( $a['clicks'] / $a['impressions'] * 100, 1 ) : 0;
				?>
					<tr>
						<td><?php echo esc_html( $a['headline'] ); ?></td>
						<td><?php echo esc_html( $a['category'] ); ?></td>
						<td><?php echo esc_html( number_format( $a['impressions'] ) ); ?></td>
						<td><?php echo esc_html( number_format( $a['clicks'] ) ); ?></td>
						<td><?php echo esc_html( $ctr ); ?>%</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<!-- Bot Detection -->
	<div class="rl-cards" style="margin-top:20px">
		<div class="rl-card rl-card-danger">
			<div class="rl-card-label">Flagged Suspicious</div>
			<div class="rl-card-value"><?php echo esc_html( number_format( $stats['flagged'] ) ); ?></div>
		</div>
		<div class="rl-card">
			<div class="rl-card-label">Likely Bots</div>
			<div class="rl-card-value"><?php echo esc_html( number_format( $poh_dist['bot'] ) ); ?></div>
		</div>
		<div class="rl-card">
			<div class="rl-card-label">Suspicious</div>
			<div class="rl-card-value"><?php echo esc_html( number_format( $poh_dist['suspicious'] ) ); ?></div>
		</div>
	</div>

	<!-- Real-time Feed -->
	<div class="rl-section">
		<h3>Real-time Event Feed <small style="color:#999">(refreshes every 30s)</small></h3>
		<div id="realtimeFeed">
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr><th>Time</th><th>Event</th><th>Device</th><th>PoH</th><th>Region</th></tr>
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
				</tbody>
			</table>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	var dailyData = <?php echo wp_json_encode( $daily ); ?>;
	var dailyEvents = <?php echo wp_json_encode( $daily_events ); ?>;
	var pohDist = <?php echo wp_json_encode( $poh_dist ); ?>;
	var eventCounts = <?php echo wp_json_encode( $events ); ?>;

	if (typeof RedLineCharts !== 'undefined') {
		RedLineCharts.lineChart('chartGrowth', dailyData.map(d => d.date), dailyData.map(d => d.value), 'Active Users');

		var engLabels = Object.keys(eventCounts);
		var engValues = Object.values(eventCounts);
		RedLineCharts.barChart('chartEngagement', engLabels, engValues, 'Events (30 days)');

		RedLineCharts.doughnutChart('chartPoHDist',
			['Verified (80+)', 'Likely (50-79)', 'Suspicious (20-49)', 'Likely Bot (<20)'],
			[pohDist.verified, pohDist.likely, pohDist.suspicious, pohDist.bot],
			['#2E7D32', '#F9A825', '#E65100', '#C62828']);
	}
});
</script>
