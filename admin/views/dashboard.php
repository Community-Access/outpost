<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap outpost-admin">
	<h1><?php esc_html_e( 'Hashtag Digest: Dashboard', 'outpost' ); ?></h1>

	<?php if ( empty( $hashtags ) ) : ?>
	<div class="notice notice-warning">
		<p>
			<?php printf(
				wp_kses(
					__( 'No hashtags configured yet. <a href="%s">Add your first hashtag</a> or <a href="%s">run the setup wizard</a>.', 'outpost' ),
					[ 'a' => [ 'href' => [] ] ]
				),
				esc_url( admin_url( 'admin.php?page=outpost-hashtags' ) ),
				esc_url( admin_url( 'admin.php?page=outpost-setup' ) )
			); ?>
		</p>
	</div>
	<?php else : ?>

	<table class="wp-list-table widefat fixed striped outpost-dashboard-table">
		<caption class="screen-reader-text"><?php esc_html_e( 'Hashtag digest summary', 'outpost' ); ?></caption>
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Hashtag', 'outpost' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Instance', 'outpost' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'outpost' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Confirmed subscribers', 'outpost' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Shortcodes', 'outpost' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Actions', 'outpost' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $hashtags as $row ) :
				$count = OUTPOST_Subscriber::count( $row->id );
			?>
			<tr>
				<td><strong>#<?php echo esc_html( $row->hashtag ); ?></strong></td>
				<td><?php echo esc_html( $row->instance_url ); ?></td>
				<td>
					<?php if ( $row->active ) : ?>
					<span class="outpost-status outpost-status--active"><?php esc_html_e( 'Active', 'outpost' ); ?></span>
					<?php else : ?>
					<span class="outpost-status outpost-status--inactive"><?php esc_html_e( 'Inactive', 'outpost' ); ?></span>
					<?php endif; ?>
				</td>
				<td><?php echo (int) $count; ?></td>
				<td>
					<code>[outpost_feed tag="<?php echo esc_attr( $row->hashtag ); ?>"]</code><br>
					<code>[outpost_subscribe tag="<?php echo esc_attr( $row->hashtag ); ?>"]</code>
				</td>
				<td>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=outpost-hashtags&edit=' . $row->id ) ); ?>">
						<?php esc_html_e( 'Edit', 'outpost' ); ?>
					</a>
					&nbsp;|&nbsp;
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=outpost-subscribers&hashtag_id=' . $row->id ) ); ?>">
						<?php esc_html_e( 'Subscribers', 'outpost' ); ?>
					</a>

					<?php if ( $row->active ) : ?>
					&nbsp;|&nbsp;
					<form method="post" style="display:inline;">
						<?php wp_nonce_field( 'outpost_send_test_digest' ); ?>
						<input type="hidden" name="outpost_action" value="send_test_digest" />
						<input type="hidden" name="hashtag_id" value="<?php echo esc_attr( $row->id ); ?>" />
						<button type="submit" class="button-link">
							<?php esc_html_e( 'Send digest now', 'outpost' ); ?>
						</button>
					</form>
					<?php endif; ?>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Next scheduled digest', 'outpost' ); ?></h2>
	<?php
		$next = wp_next_scheduled( 'outpost_daily_digest_event' );
		if ( $next ) {
			echo '<p>' . sprintf(
				esc_html__( 'Next digest will send at %s.', 'outpost' ),
				esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next ) )
			) . '</p>';
		} else {
			echo '<p class="outpost-warning">' . esc_html__( 'Digest cron is not scheduled. Deactivate and reactivate the plugin to fix this.', 'outpost' ) . '</p>';
		}
	?>
	<?php endif; ?>
</div>
