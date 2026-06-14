<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap outpost-admin">
	<h1><?php esc_html_e( 'Manage Hashtags', 'outpost' ); ?></h1>

	<?php if ( $edit_row ) : ?>
	<h2><?php printf( esc_html__( 'Edit #%s', 'outpost' ), esc_html( $edit_row->hashtag ) ); ?></h2>
	<form method="post">
		<?php wp_nonce_field( 'outpost_update_hashtag' ); ?>
		<input type="hidden" name="outpost_action" value="update_hashtag" />
		<input type="hidden" name="hashtag_id" value="<?php echo esc_attr( $edit_row->id ); ?>" />
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="edit-hashtag"><?php esc_html_e( 'Hashtag', 'outpost' ); ?></label></th>
				<td>
					<input type="text" id="edit-hashtag" name="hashtag" value="<?php echo esc_attr( $edit_row->hashtag ); ?>" class="regular-text" required />
					<p class="description"><?php esc_html_e( 'Without the # symbol.', 'outpost' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="edit-instance"><?php esc_html_e( 'Instance URL', 'outpost' ); ?></label></th>
				<td>
					<input type="url" id="edit-instance" name="instance_url" value="<?php echo esc_attr( $edit_row->instance_url ); ?>" class="regular-text" required />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="edit-label"><?php esc_html_e( 'Label', 'outpost' ); ?></label></th>
				<td>
					<input type="text" id="edit-label" name="label" value="<?php echo esc_attr( $edit_row->label ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Human-readable name shown in the admin. Example: BitsTips Daily', 'outpost' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Active', 'outpost' ); ?></th>
				<td>
					<input type="checkbox" id="edit-active" name="active" value="1" <?php checked( $edit_row->active ); ?> />
					<label for="edit-active"><?php esc_html_e( 'Enable this hashtag feed and digest', 'outpost' ); ?></label>
				</td>
			</tr>
		</table>
		<p>
			<?php submit_button( __( 'Save changes', 'outpost' ), 'primary', 'submit', false ); ?>
			&nbsp;
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=outpost-hashtags' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'outpost' ); ?></a>
		</p>
	</form>

	<?php else : ?>

	<h2><?php esc_html_e( 'Add a hashtag', 'outpost' ); ?></h2>
	<form method="post">
		<?php wp_nonce_field( 'outpost_add_hashtag' ); ?>
		<input type="hidden" name="outpost_action" value="add_hashtag" />
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="new-hashtag"><?php esc_html_e( 'Hashtag', 'outpost' ); ?></label></th>
				<td>
					<input type="text" id="new-hashtag" name="hashtag" value="" class="regular-text" placeholder="BitsTips" required />
					<p class="description"><?php esc_html_e( 'Without the # symbol.', 'outpost' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="new-instance"><?php esc_html_e( 'Instance URL', 'outpost' ); ?></label></th>
				<td>
					<input type="url" id="new-instance" name="instance_url" value="https://mastodon.social" class="regular-text" required />
					<p class="description"><?php esc_html_e( 'The Mastodon server to search. Example: https://dragonscave.space', 'outpost' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="new-label"><?php esc_html_e( 'Label', 'outpost' ); ?></label></th>
				<td>
					<input type="text" id="new-label" name="label" value="" class="regular-text" placeholder="BitsTips Daily" />
					<p class="description"><?php esc_html_e( 'Optional. Defaults to the hashtag name.', 'outpost' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Add hashtag', 'outpost' ) ); ?>
	</form>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Current hashtags', 'outpost' ); ?></h2>

	<?php if ( empty( $hashtags ) ) : ?>
	<p><?php esc_html_e( 'No hashtags added yet.', 'outpost' ); ?></p>
	<?php else : ?>
	<table class="wp-list-table widefat fixed striped">
		<caption class="screen-reader-text"><?php esc_html_e( 'Configured hashtags', 'outpost' ); ?></caption>
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Hashtag', 'outpost' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Instance', 'outpost' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Label', 'outpost' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'outpost' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Actions', 'outpost' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $hashtags as $row ) : ?>
			<tr>
				<td><strong>#<?php echo esc_html( $row->hashtag ); ?></strong></td>
				<td><?php echo esc_html( $row->instance_url ); ?></td>
				<td><?php echo esc_html( $row->label ); ?></td>
				<td><?php echo $row->active ? esc_html__( 'Active', 'outpost' ) : esc_html__( 'Inactive', 'outpost' ); ?></td>
				<td>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=outpost-hashtags&edit=' . $row->id ) ); ?>">
						<?php esc_html_e( 'Edit', 'outpost' ); ?>
					</a>
					&nbsp;|&nbsp;
					<a href="<?php echo esc_url( wp_nonce_url(
						admin_url( 'admin.php?outpost_action=delete_hashtag&id=' . $row->id ),
						'outpost_delete_hashtag_' . $row->id
					) ); ?>"
					onclick="return confirm('<?php esc_attr_e( 'Delete this hashtag and all its subscribers? This cannot be undone.', 'outpost' ); ?>');"
					class="outpost-delete-link">
						<?php esc_html_e( 'Delete', 'outpost' ); ?>
					</a>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>
</div>
