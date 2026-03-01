<?php
defined( 'ABSPATH' ) || exit;

$editing  = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
$creating = isset( $_GET['action'] ) && 'new' === $_GET['action'];
$viewing  = isset( $_GET['view'] ) ? absint( $_GET['view'] ) : 0;
$poll     = $editing ? RedLine_Polls::get( $editing ) : null;

$polls = RedLine_Polls::get_all();
?>
<div class="wrap redline-wrap">
	<h1 class="redline-title">
		<span class="redline-icon">📊</span> Red Line — Polls
		<?php if ( ! $creating && ! $editing && ! $viewing ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=red-line-polls&action=new' ) ); ?>" class="page-title-action">Add New Poll</a>
		<?php endif; ?>
	</h1>

	<?php if ( isset( $_GET['msg'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>Poll <?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['msg'] ) ) ); ?> successfully.</p></div>
	<?php endif; ?>

	<?php if ( $viewing ) :
		$vpoll = RedLine_Polls::get( $viewing );
		if ( $vpoll ) :
			$results = $vpoll['results'];
			$total = $results['total'] ?: 1;
	?>
		<!-- Results View -->
		<div class="rl-form-wrap">
			<h2>Poll Results: <?php echo esc_html( $vpoll['question'] ); ?></h2>
			<p>
				<strong>Status:</strong> <?php echo esc_html( ucfirst( $vpoll['status'] ) ); ?> |
				<strong>Show Results to Public:</strong> <?php echo $vpoll['show_results'] ? 'Yes' : 'No'; ?> |
				<strong>Total Responses:</strong> <?php echo esc_html( number_format( $results['total'] ) ); ?> |
				<strong>Verified:</strong> <?php echo esc_html( number_format( $results['verified'] ) ); ?>
			</p>

			<div class="rl-charts-grid">
				<div class="rl-chart-box">
					<h3>Response Distribution</h3>
					<canvas id="chartPollResults" height="250"></canvas>
				</div>
				<div class="rl-chart-box">
					<h3>Breakdown</h3>
					<table class="wp-list-table widefat striped">
						<thead><tr><th>Option</th><th>Votes</th><th>Percentage</th></tr></thead>
						<tbody>
							<?php foreach ( $vpoll['options'] as $i => $opt ) :
								$count = $results['choices'][ $i ] ?? 0;
								$pct = round( $count / $total * 100, 1 );
							?>
								<tr>
									<td><?php echo esc_html( $opt ); ?></td>
									<td><?php echo esc_html( number_format( $count ) ); ?></td>
									<td><?php echo esc_html( $pct ); ?>%</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>

			<?php if ( ! empty( $results['by_region'] ) ) : ?>
				<h3>Geographic Breakdown</h3>
				<table class="wp-list-table widefat striped">
					<thead><tr><th>Region</th><th>Responses</th></tr></thead>
					<tbody>
						<?php foreach ( $results['by_region'] as $region => $count ) : ?>
							<tr>
								<td><?php echo esc_html( $region ); ?></td>
								<td><?php echo esc_html( number_format( $count ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<p style="margin-top:16px">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=red-line-polls' ) ); ?>" class="button">← Back to Polls</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=red-line-polls&export=' . $viewing ) ); ?>" class="button">Export CSV</a>
			</p>
		</div>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			var labels = <?php echo wp_json_encode( $vpoll['options'] ); ?>;
			var values = <?php echo wp_json_encode( array_values( $results['choices'] ) ); ?>;
			if (typeof RedLineCharts !== 'undefined') {
				RedLineCharts.barChart('chartPollResults', labels, values, 'Responses');
			}
		});
		</script>
	<?php endif; ?>

	<?php elseif ( $creating || $editing ) : ?>
		<!-- Create/Edit Form -->
		<div class="rl-form-wrap">
			<h2><?php echo $editing ? 'Edit Poll' : 'New Poll'; ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'redline_admin_action' ); ?>
				<input type="hidden" name="redline_action" value="save_poll">
				<?php if ( $editing ) : ?>
					<input type="hidden" name="poll_id" value="<?php echo esc_attr( $editing ); ?>">
				<?php endif; ?>

				<table class="form-table">
					<tr>
						<th><label for="question">Question</label></th>
						<td>
							<input type="text" name="question" id="question" class="large-text" maxlength="200"
								value="<?php echo esc_attr( $poll['question'] ?? '' ); ?>" required>
						</td>
					</tr>
					<tr>
						<th>Options (2-6)</th>
						<td>
							<div id="pollOptions">
								<?php
								$options = $poll['options'] ?? array( '', '' );
								foreach ( $options as $i => $opt ) :
								?>
									<div class="rl-option-row">
										<input type="text" name="options[]" class="regular-text" value="<?php echo esc_attr( $opt ); ?>" placeholder="Option <?php echo esc_attr( $i + 1 ); ?>">
										<?php if ( $i >= 2 ) : ?>
											<button type="button" class="button rl-remove-option">×</button>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							</div>
							<button type="button" class="button" id="addOption">+ Add Option</button>
						</td>
					</tr>
					<tr>
						<th><label for="show_results">Show Results to Public</label></th>
						<td>
							<label>
								<input type="checkbox" name="show_results" id="show_results" <?php checked( $poll['show_results'] ?? true ); ?>>
								When ON, users see live results after voting. When OFF, they see "Thank you — your voice has been heard."
							</label>
						</td>
					</tr>
					<tr>
						<th><label for="status">Status</label></th>
						<td>
							<select name="status" id="status">
								<option value="draft" <?php selected( $poll['status'] ?? '', 'draft' ); ?>>Draft</option>
								<option value="active" <?php selected( $poll['status'] ?? '', 'active' ); ?>>Active</option>
								<option value="closed" <?php selected( $poll['status'] ?? '', 'closed' ); ?>>Closed</option>
								<option value="scheduled" <?php selected( $poll['status'] ?? '', 'scheduled' ); ?>>Scheduled</option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="min_poh_to_vote">Minimum PoH to Vote</label></th>
						<td><input type="number" name="min_poh_to_vote" id="min_poh_to_vote" min="0" max="100" value="<?php echo esc_attr( $poll['min_poh_to_vote'] ?? 30 ); ?>"></td>
					</tr>
					<tr>
						<th><label for="opens_at">Opens At</label></th>
						<td><input type="datetime-local" name="opens_at" id="opens_at" value="<?php echo esc_attr( $poll['opens_at'] ?? '' ); ?>"></td>
					</tr>
					<tr>
						<th><label for="closes_at">Closes At</label></th>
						<td><input type="datetime-local" name="closes_at" id="closes_at" value="<?php echo esc_attr( $poll['closes_at'] ?? '' ); ?>"></td>
					</tr>
				</table>

				<?php submit_button( $editing ? 'Update Poll' : 'Create Poll' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=red-line-polls' ) ); ?>" class="button">Cancel</a>
			</form>
		</div>

	<?php else : ?>
		<!-- List View -->
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width:30%">Question</th>
					<th>Status</th>
					<th>Show Results</th>
					<th>Total Votes</th>
					<th>Verified</th>
					<th>Created</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $polls as $p ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $p['question'] ); ?></strong></td>
						<td><span class="rl-status-badge rl-status-<?php echo esc_attr( $p['status'] ); ?>"><?php echo esc_html( ucfirst( $p['status'] ) ); ?></span></td>
						<td><?php echo $p['show_results'] ? '✅ Yes' : '🔒 No'; ?></td>
						<td><?php echo esc_html( number_format( $p['results']['total'] ?? 0 ) ); ?></td>
						<td><?php echo esc_html( number_format( $p['results']['verified'] ?? 0 ) ); ?></td>
						<td><?php echo esc_html( $p['created_at'] ); ?></td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=red-line-polls&view=' . $p['id'] ) ); ?>">Results</a> |
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=red-line-polls&edit=' . $p['id'] ) ); ?>">Edit</a> |
							<form method="post" style="display:inline" onsubmit="return confirm('Delete this poll and all votes?')">
								<?php wp_nonce_field( 'redline_admin_action' ); ?>
								<input type="hidden" name="redline_action" value="delete_poll">
								<input type="hidden" name="poll_id" value="<?php echo esc_attr( $p['id'] ); ?>">
								<button type="submit" class="button-link rl-delete-link">Delete</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if ( empty( $polls ) ) : ?>
					<tr><td colspan="7">No polls yet. <a href="<?php echo esc_url( admin_url( 'admin.php?page=red-line-polls&action=new' ) ); ?>">Create your first poll</a>.</td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
