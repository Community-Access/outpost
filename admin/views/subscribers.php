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

	$outpost_per_page  = 50;
	$sub_table = $wpdb->prefix . 'outpost_subscribers';
	$tag_table = $wpdb->prefix . 'outpost_hashtags';

	// Total count (respects the optional hashtag filter), used for the count
	// line and to bound pagination.
	if ( $hashtag_id ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted table name; id is placeheld.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$sub_table} WHERE hashtag_id = %d", $hashtag_id ) );
	} else {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- trusted table name; simple count.
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$sub_table}" );
	}

	$total_pages = (int) max( 1, ceil( $total / $outpost_per_page ) );
	$outpost_paged       = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only paging of an admin list.
	$outpost_paged       = min( $outpost_paged, $total_pages ); // Clamp so an out-of-range page never shows empty.
	$offset      = ( $outpost_paged - 1 ) * $outpost_per_page;

	if ( $hashtag_id ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted table names; values are placeheld.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT s.*, h.hashtag FROM {$sub_table} s JOIN {$tag_table} h ON h.id = s.hashtag_id WHERE s.hashtag_id = %d ORDER BY s.created_at DESC LIMIT %d OFFSET %d", $hashtag_id, $outpost_per_page, $offset ) );
	} else {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted table names; values are placeheld.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT s.*, h.hashtag FROM {$sub_table} s JOIN {$tag_table} h ON h.id = s.hashtag_id ORDER BY s.created_at DESC LIMIT %d OFFSET %d", $outpost_per_page, $offset ) );
	}
	?>

	<?php if ( 0 === $total ) : ?>
	<p><?php esc_html_e( 'No subscribers found.', 'outpost' ); ?></p>
	<?php else : ?>
	<p><?php echo esc_html( sprintf( _n( '%d subscriber found.', '%d subscribers found.', $total, 'outpost' ), $total ) ); ?></p>
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

		<?php
		$links = paginate_links(
			array(
				'base'      => add_query_arg( 'paged', '%#%', admin_url( 'admin.php' ) ),
				'format'    => '',
				'current'   => $outpost_paged,
				'total'     => $total_pages,
				'add_args'  => array_merge( array( 'page' => 'outpost-subscribers' ), $hashtag_id ? array( 'hashtag_id' => $hashtag_id ) : array() ),
				'prev_text' => __( 'Previous', 'outpost' ),
				'next_text' => __( 'Next', 'outpost' ),
			)
		);
		if ( $links ) :
			?>
	<nav class="outpost-pagination" aria-label="<?php esc_attr_e( 'Subscribers pagination', 'outpost' ); ?>">
			<?php
			// paginate_links() returns pre-escaped markup (including aria-current on the
			// current page); echo as-is so wp_kses does not strip the ARIA attribute.
			echo $links; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links() output is pre-escaped.
			?>
	</nav>
			<?php endif; ?>
	<?php endif; ?>
</div>
