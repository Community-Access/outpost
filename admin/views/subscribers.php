<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;} ?>
<div class="wrap outpost-admin">
	<h1><?php esc_html_e( 'Subscribers', 'outpost' ); ?></h1>

	<form method="get" action="">
		<input type="hidden" name="page" value="outpost-subscribers" />
		<label for="outpost-filter-hashtag"><?php esc_html_e( 'Filter by hashtag:', 'outpost' ); ?></label>
		<select id="outpost-filter-hashtag" name="hashtag_id">
			<option value="0"><?php esc_html_e( '-- All hashtags --', 'outpost' ); ?></option>
			<?php foreach ( $hashtags as $row ) : ?>
			<option value="<?php echo esc_attr( $row->id ); ?>" <?php selected( $hashtag_id, $row->id ); ?>>
				#<?php echo esc_html( $row->hashtag ); ?>
			</option>
			<?php endforeach; ?>
		</select>
		<?php submit_button( __( 'Filter', 'outpost' ), 'secondary', 'filter', false ); ?>
	</form>

	<?php
	global $wpdb;

	if ( $hashtag_id ) {
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*, h.hashtag FROM {$wpdb->prefix}outpost_subscribers s
			 JOIN {$wpdb->prefix}outpost_hashtags h ON h.id = s.hashtag_id
			 WHERE s.hashtag_id = %d ORDER BY s.created_at DESC",
				$hashtag_id
			)
		);
	} else {
		$rows = $wpdb->get_results(
			"SELECT s.*, h.hashtag FROM {$wpdb->prefix}outpost_subscribers s
			 JOIN {$wpdb->prefix}outpost_hashtags h ON h.id = s.hashtag_id
			 ORDER BY s.created_at DESC"
		);
	}
	?>

	<?php if ( empty( $rows ) ) : ?>
	<p><?php esc_html_e( 'No subscribers found.', 'outpost' ); ?></p>
	<?php else : ?>
	<p><?php printf( esc_html__( '%d subscriber(s) found.', 'outpost' ), count( $rows ) ); ?></p>
	<table class="wp-list-table widefat fixed striped">
		<caption class="screen-reader-text"><?php esc_html_e( 'Subscriber list', 'outpost' ); ?></caption>
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Email', 'outpost' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Name', 'outpost' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Hashtag', 'outpost' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'outpost' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Subscribed', 'outpost' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $rows as $row ) : ?>
			<tr>
				<td><?php echo esc_html( $row->email ); ?></td>
				<td><?php echo esc_html( $row->name ?: '—' ); ?></td>
				<td>#<?php echo esc_html( $row->hashtag ); ?></td>
				<td><?php echo esc_html( ucfirst( $row->status ) ); ?></td>
				<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $row->created_at ) ) ); ?></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>
</div>
