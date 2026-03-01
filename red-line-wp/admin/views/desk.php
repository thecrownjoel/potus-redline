<?php
defined( 'ABSPATH' ) || exit;

$editing  = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
$creating = isset( $_GET['action'] ) && 'new' === $_GET['action'];
$msg      = $editing ? RedLine_Desk::get( $editing ) : null;

$messages = RedLine_Desk::get_all();
?>
<div class="wrap redline-wrap">
	<h1 class="redline-title">
		<span class="redline-icon">🏛️</span> Red Line — From the President's Desk
		<?php if ( ! $creating && ! $editing ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=red-line-desk&action=new' ) ); ?>" class="page-title-action">Add New Message</a>
		<?php endif; ?>
	</h1>

	<?php if ( isset( $_GET['msg'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>Message <?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['msg'] ) ) ); ?> successfully.</p></div>
	<?php endif; ?>

	<?php if ( $creating || $editing ) : ?>
		<div class="rl-form-wrap">
			<h2><?php echo $editing ? 'Edit Message' : 'New Message'; ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'redline_admin_action' ); ?>
				<input type="hidden" name="redline_action" value="save_desk">
				<?php if ( $editing ) : ?>
					<input type="hidden" name="desk_id" value="<?php echo esc_attr( $editing ); ?>">
				<?php endif; ?>

				<table class="form-table">
					<tr>
						<th><label for="title">Title</label></th>
						<td><input type="text" name="title" id="title" class="large-text" maxlength="100" value="<?php echo esc_attr( $msg['title'] ?? '' ); ?>" required></td>
					</tr>
					<tr>
						<th><label for="body">Message Body</label></th>
						<td>
							<?php
							wp_editor(
								$msg['body'] ?? '',
								'body',
								array(
									'textarea_rows' => 12,
									'media_buttons' => false,
								)
							);
							?>
						</td>
					</tr>
					<tr>
						<th><label for="image_url">Image URL (optional)</label></th>
						<td><input type="url" name="image_url" id="image_url" class="large-text" value="<?php echo esc_url( $msg['image_url'] ?? '' ); ?>"></td>
					</tr>
					<tr>
						<th><label for="status">Status</label></th>
						<td>
							<select name="status" id="status">
								<option value="draft" <?php selected( $msg['status'] ?? '', 'draft' ); ?>>Draft</option>
								<option value="published" <?php selected( $msg['status'] ?? '', 'published' ); ?>>Publish Now</option>
								<option value="scheduled" <?php selected( $msg['status'] ?? '', 'scheduled' ); ?>>Schedule</option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="scheduled_at">Schedule Date</label></th>
						<td><input type="datetime-local" name="scheduled_at" id="scheduled_at" value="<?php echo esc_attr( $msg['scheduled_at'] ?? '' ); ?>"></td>
					</tr>
				</table>

				<?php submit_button( $editing ? 'Update Message' : 'Create Message' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=red-line-desk' ) ); ?>" class="button">Cancel</a>
			</form>
		</div>

	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width:35%">Title</th>
					<th>Status</th>
					<th>Views</th>
					<th>Read-throughs</th>
					<th>Read Rate</th>
					<th>Created</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $messages as $m ) :
					$rate = $m['views'] > 0 ? round( $m['read_throughs'] / $m['views'] * 100, 1 ) : 0;
				?>
					<tr>
						<td><strong><?php echo esc_html( $m['title'] ); ?></strong></td>
						<td><span class="rl-status-badge rl-status-<?php echo esc_attr( $m['status'] ); ?>"><?php echo esc_html( ucfirst( $m['status'] ) ); ?></span></td>
						<td><?php echo esc_html( number_format( $m['views'] ) ); ?></td>
						<td><?php echo esc_html( number_format( $m['read_throughs'] ) ); ?></td>
						<td><?php echo esc_html( $rate ); ?>%</td>
						<td><?php echo esc_html( $m['created_at'] ); ?></td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=red-line-desk&edit=' . $m['id'] ) ); ?>">Edit</a> |
							<form method="post" style="display:inline" onsubmit="return confirm('Delete this message?')">
								<?php wp_nonce_field( 'redline_admin_action' ); ?>
								<input type="hidden" name="redline_action" value="delete_desk">
								<input type="hidden" name="desk_id" value="<?php echo esc_attr( $m['id'] ); ?>">
								<button type="submit" class="button-link rl-delete-link">Delete</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if ( empty( $messages ) ) : ?>
					<tr><td colspan="7">No messages yet. <a href="<?php echo esc_url( admin_url( 'admin.php?page=red-line-desk&action=new' ) ); ?>">Create your first message</a>.</td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
