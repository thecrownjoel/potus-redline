<?php
defined( 'ABSPATH' ) || exit;

$api_key        = get_option( 'redline_api_key', '' );
$poll_interval  = get_option( 'redline_poll_interval', 300 );
$show_results   = get_option( 'redline_default_show_results', 1 );
$max_alerts     = get_option( 'redline_max_alerts', 20 );
$min_poh_vote   = get_option( 'redline_min_poh_vote', 30 );
$min_poh_verif  = get_option( 'redline_min_poh_verified', 80 );
$auto_flag      = get_option( 'redline_auto_flag', 20 );
$data_retention = get_option( 'redline_data_retention', 365 );
$rate_limit     = get_option( 'redline_rate_limit', 60 );
$cors_origins   = get_option( 'redline_cors_origins', '' );
?>
<div class="wrap redline-wrap">
	<h1 class="redline-title">
		<span class="redline-icon">⚙️</span> Red Line — Settings
	</h1>

	<?php if ( isset( $_GET['msg'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>Settings saved successfully.</p></div>
	<?php endif; ?>

	<form method="post">
		<?php wp_nonce_field( 'redline_admin_action' ); ?>
		<input type="hidden" name="redline_action" value="save_settings">

		<div class="rl-settings-section">
			<h2>API Configuration</h2>
			<table class="form-table">
				<tr>
					<th><label for="api_key">API Key</label></th>
					<td>
						<input type="text" name="api_key" id="api_key" class="large-text" value="<?php echo esc_attr( $api_key ); ?>">
						<p class="description">Used by the browser extension to authenticate requests. Include as <code>X-RedLine-Key</code> header.</p>
						<button type="button" class="button" id="regenerateKey">Regenerate Key</button>
					</td>
				</tr>
				<tr>
					<th><label for="rate_limit">Rate Limit</label></th>
					<td>
						<input type="number" name="rate_limit" id="rate_limit" min="10" max="1000" value="<?php echo esc_attr( $rate_limit ); ?>">
						<p class="description">Maximum requests per minute per IP.</p>
					</td>
				</tr>
				<tr>
					<th><label for="cors_origins">CORS Allowed Origins</label></th>
					<td>
						<textarea name="cors_origins" id="cors_origins" rows="3" class="large-text"><?php echo esc_textarea( $cors_origins ); ?></textarea>
						<p class="description">One origin per line (e.g., <code>chrome-extension://abc123</code>). Leave blank to allow all.</p>
					</td>
				</tr>
			</table>
		</div>

		<div class="rl-settings-section">
			<h2>Content Settings</h2>
			<table class="form-table">
				<tr>
					<th><label for="show_results_default">Default Poll Results Visibility</label></th>
					<td>
						<label>
							<input type="checkbox" name="default_show_results" id="show_results_default" <?php checked( $show_results ); ?>>
							Show results to public by default
						</label>
					</td>
				</tr>
				<tr>
					<th><label for="poll_interval">Alert Poll Interval (seconds)</label></th>
					<td>
						<input type="number" name="poll_interval" id="poll_interval" min="60" max="3600" value="<?php echo esc_attr( $poll_interval ); ?>">
						<p class="description">How often extensions check for new content.</p>
					</td>
				</tr>
				<tr>
					<th><label for="max_alerts">Max Alerts in Extension</label></th>
					<td>
						<input type="number" name="max_alerts" id="max_alerts" min="5" max="100" value="<?php echo esc_attr( $max_alerts ); ?>">
					</td>
				</tr>
			</table>
		</div>

		<div class="rl-settings-section">
			<h2>Proof of Human Settings</h2>
			<table class="form-table">
				<tr>
					<th><label for="min_poh_vote">Minimum PoH to Vote</label></th>
					<td>
						<input type="number" name="min_poh_vote" id="min_poh_vote" min="0" max="100" value="<?php echo esc_attr( $min_poh_vote ); ?>">
						<p class="description">Minimum trust score required to participate in polls.</p>
					</td>
				</tr>
				<tr>
					<th><label for="min_poh_verified">Minimum PoH for "Verified"</label></th>
					<td>
						<input type="number" name="min_poh_verified" id="min_poh_verified" min="0" max="100" value="<?php echo esc_attr( $min_poh_verif ); ?>">
						<p class="description">Minimum score to count as "Verified Human" in public displays.</p>
					</td>
				</tr>
				<tr>
					<th><label for="auto_flag">Auto-Flag Threshold</label></th>
					<td>
						<input type="number" name="auto_flag" id="auto_flag" min="0" max="100" value="<?php echo esc_attr( $auto_flag ); ?>">
						<p class="description">Automatically flag devices with PoH below this score.</p>
					</td>
				</tr>
			</table>
		</div>

		<div class="rl-settings-section">
			<h2>Data Retention</h2>
			<table class="form-table">
				<tr>
					<th><label for="data_retention">Keep Analytics (days)</label></th>
					<td>
						<input type="number" name="data_retention" id="data_retention" min="30" max="3650" value="<?php echo esc_attr( $data_retention ); ?>">
					</td>
				</tr>
				<tr>
					<th>Actions</th>
					<td>
						<form method="post" style="display:inline" onsubmit="return confirm('Purge analytics events older than <?php echo esc_attr( $data_retention ); ?> days?')">
							<?php wp_nonce_field( 'redline_admin_action' ); ?>
							<input type="hidden" name="redline_action" value="purge_data">
							<button type="submit" class="button">Purge Old Data</button>
						</form>
						<form method="post" style="display:inline;margin-left:10px" onsubmit="return confirm('Re-seed mock data?')">
							<?php wp_nonce_field( 'redline_admin_action' ); ?>
							<input type="hidden" name="redline_action" value="seed_mock">
							<button type="submit" class="button">Seed Mock Data</button>
						</form>
					</td>
				</tr>
			</table>
		</div>

		<?php submit_button( 'Save Settings' ); ?>
	</form>
</div>
