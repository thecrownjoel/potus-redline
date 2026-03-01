<?php
defined( 'ABSPATH' ) || exit;

$editing = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
$creating = isset( $_GET['action'] ) && 'new' === $_GET['action'];
$alert = $editing ? RedLine_Alerts::get( $editing ) : null;

$alerts = RedLine_Alerts::get_all();
$categories = array( 'Executive Order', 'Press Briefing', 'Statement', 'Event', 'Urgent' );
?>
<div class="wrap redline-wrap">
	<h1 class="redline-title">
		<span class="redline-icon">⚡</span> Red Line — Alerts
		<?php if ( ! $creating && ! $editing ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=red-line-alerts&action=new' ) ); ?>" class="page-title-action">Add New Alert</a>
		<?php endif; ?>
	</h1>

	<?php if ( isset( $_GET['msg'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>Alert <?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['msg'] ) ) ); ?> successfully.</p></div>
	<?php endif; ?>

	<?php if ( $creating || $editing ) : ?>
		<!-- Create/Edit Form -->
		<div class="rl-form-wrap">
			<h2><?php echo $editing ? 'Edit Alert' : 'New Alert'; ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'redline_admin_action' ); ?>
				<input type="hidden" name="redline_action" value="save_alert">
				<?php if ( $editing ) : ?>
					<input type="hidden" name="alert_id" value="<?php echo esc_attr( $editing ); ?>">
				<?php endif; ?>

				<table class="form-table">
					<tr>
						<th><label for="headline">Headline</label></th>
						<td>
							<input type="text" name="headline" id="headline" class="large-text" maxlength="120"
								value="<?php echo esc_attr( $alert['headline'] ?? '' ); ?>" required>
							<p class="description">Max 120 characters.</p>
						</td>
					</tr>
					<tr>
						<th><label for="body">Body</label></th>
						<td>
							<textarea name="body" id="body" rows="5" class="large-text" maxlength="500"><?php echo esc_textarea( $alert['body'] ?? '' ); ?></textarea>
							<p class="description">Max 500 characters.</p>
						</td>
					</tr>
					<tr>
						<th><label for="category">Category</label></th>
						<td>
							<select name="category" id="category">
								<?php foreach ( $categories as $cat ) : ?>
									<option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $alert['category'] ?? '', $cat ); ?>><?php echo esc_html( $cat ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="link_url">Link URL</label></th>
						<td><input type="url" name="link_url" id="link_url" class="large-text" value="<?php echo esc_url( $alert['link_url'] ?? '' ); ?>"></td>
					</tr>
					<tr>
						<th><label for="priority">Priority</label></th>
						<td>
							<select name="priority" id="priority">
								<option value="normal" <?php selected( $alert['priority'] ?? '', 'normal' ); ?>>Normal</option>
								<option value="urgent" <?php selected( $alert['priority'] ?? '', 'urgent' ); ?>>Urgent (triggers push notification)</option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="status">Status</label></th>
						<td>
							<select name="status" id="status">
								<option value="draft" <?php selected( $alert['status'] ?? '', 'draft' ); ?>>Draft</option>
								<option value="published" <?php selected( $alert['status'] ?? '', 'published' ); ?>>Publish Now</option>
								<option value="scheduled" <?php selected( $alert['status'] ?? '', 'scheduled' ); ?>>Schedule</option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="scheduled_at">Schedule Date</label></th>
						<td><input type="datetime-local" name="scheduled_at" id="scheduled_at" value="<?php echo esc_attr( $alert['scheduled_at'] ?? '' ); ?>"></td>
					</tr>
				</table>

				<?php submit_button( $editing ? 'Update Alert' : 'Create Alert' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=red-line-alerts' ) ); ?>" class="button">Cancel</a>
			</form>
		</div>

	<?php else : ?>
		<!-- List View -->
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width:35%">Headline</th>
					<th>Category</th>
					<th>Priority</th>
					<th>Status</th>
					<th>Impressions</th>
					<th>Clicks</th>
					<th>CTR</th>
					<th>Created</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $alerts as $a ) :
					$ctr = $a['impressions'] > 0 ? round( $a['clicks'] / $a['impressions'] * 100, 1 ) : 0;
				?>
					<tr>
						<td><strong><?php echo esc_html( $a['headline'] ); ?></strong></td>
						<td><span class="rl-cat-badge"><?php echo esc_html( $a['category'] ); ?></span></td>
						<td><?php echo 'urgent' === $a['priority'] ? '<span class="rl-urgent">🔴 Urgent</span>' : 'Normal'; ?></td>
						<td><span class="rl-status-badge rl-status-<?php echo esc_attr( $a['status'] ); ?>"><?php echo esc_html( ucfirst( $a['status'] ) ); ?></span></td>
						<td><?php echo esc_html( number_format( $a['impressions'] ) ); ?></td>
						<td><?php echo esc_html( number_format( $a['clicks'] ) ); ?></td>
						<td><?php echo esc_html( $ctr ); ?>%</td>
						<td><?php echo esc_html( $a['created_at'] ); ?></td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=red-line-alerts&edit=' . $a['id'] ) ); ?>">Edit</a> |
							<form method="post" style="display:inline" onsubmit="return confirm('Delete this alert?')">
								<?php wp_nonce_field( 'redline_admin_action' ); ?>
								<input type="hidden" name="redline_action" value="delete_alert">
								<input type="hidden" name="alert_id" value="<?php echo esc_attr( $a['id'] ); ?>">
								<button type="submit" class="button-link rl-delete-link">Delete</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if ( empty( $alerts ) ) : ?>
					<tr><td colspan="9">No alerts yet. <a href="<?php echo esc_url( admin_url( 'admin.php?page=red-line-alerts&action=new' ) ); ?>">Create your first alert</a>.</td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
