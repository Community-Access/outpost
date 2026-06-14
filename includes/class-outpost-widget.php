<?php
/**
 * WordPress Widget for displaying a Mastodon hashtag feed.
 *
 * Each widget instance is tied to one hashtag. To display multiple hashtags
 * in the sidebar, add multiple instances of this widget.
 */
class OUTPOST_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'outpost_feed_widget',
			__( 'Mastodon Hashtag Feed', 'outpost' ),
			[
				'description'                 => __( 'Display posts from a Mastodon hashtag with a subscribe form.', 'outpost' ),
				'customize_selective_refresh' => true,
			]
		);
	}

	// -------------------------------------------------------------------------
	// Front-end display
	// -------------------------------------------------------------------------

	public function widget( $args, $instance ) {
		$hashtag_id      = ! empty( $instance['hashtag_id'] ) ? (int) $instance['hashtag_id'] : 0;
		$show_subscribe  = ! empty( $instance['show_subscribe'] );
		$limit           = ! empty( $instance['limit'] ) ? (int) $instance['limit'] : 5;
		$title           = ! empty( $instance['title'] ) ? $instance['title'] : '';

		if ( ! $hashtag_id ) {
			return;
		}

		$hashtag_row = OUTPOST_Hashtag_Manager::get( $hashtag_id );
		if ( ! $hashtag_row || ! $hashtag_row->active ) {
			return;
		}

		$posts    = OUTPOST_Feed_Fetcher::get_posts( $hashtag_id, $limit );
		$branding = OUTPOST_Settings::get_branding_html();

		echo $args['before_widget'];

		$widget_title = $title ?: '#' . $hashtag_row->hashtag;
		echo $args['before_title'] . esc_html( $widget_title ) . $args['after_title'];
		?>

		<div class="outpost-widget-feed" aria-label="<?php echo esc_attr( sprintf( __( '#%s feed', 'outpost' ), $hashtag_row->hashtag ) ); ?>">

			<?php if ( empty( $posts ) ) : ?>
				<p class="outpost-widget-feed__empty"><?php esc_html_e( 'No posts yet.', 'outpost' ); ?></p>
			<?php else : ?>
				<ul class="outpost-widget-feed__list" role="list">
					<?php foreach ( $posts as $post ) :
						$text = OUTPOST_Feed_Fetcher::post_to_plain_text( $post->content );
						$date = OUTPOST_Feed_Fetcher::format_date( $post->created_at );
						$url  = esc_url( $post->url );

						// Truncate for widget display
						$excerpt = mb_strlen( $text ) > 160 ? mb_substr( $text, 0, 157 ) . '...' : $text;
					?>
					<li class="outpost-widget-feed__item">
						<p class="outpost-widget-feed__text"><?php echo esc_html( $excerpt ); ?></p>
						<div class="outpost-widget-feed__meta">
							<?php if ( $date ) : ?>
							<time datetime="<?php echo esc_attr( $post->created_at ); ?>"><?php echo esc_html( $date ); ?></time>
							<?php endif; ?>
							<a href="<?php echo $url; ?>" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e( 'View post', 'outpost' ); ?>
								<span class="screen-reader-text"><?php esc_html_e( '(opens in new tab)', 'outpost' ); ?></span>
							</a>
						</div>
					</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( $show_subscribe ) :
				echo do_shortcode( '[outpost_subscribe tag="' . esc_attr( $hashtag_row->hashtag ) . '"]' );
			endif; ?>

			<?php if ( $branding ) : ?>
			<div class="outpost-widget-feed__branding">
				<?php echo $branding; ?>
			</div>
			<?php endif; ?>

		</div>

		<?php
		echo $args['after_widget'];
	}

	// -------------------------------------------------------------------------
	// Admin form
	// -------------------------------------------------------------------------

	public function form( $instance ) {
		$hashtag_id     = ! empty( $instance['hashtag_id'] ) ? (int) $instance['hashtag_id'] : 0;
		$show_subscribe = ! empty( $instance['show_subscribe'] );
		$limit          = ! empty( $instance['limit'] ) ? (int) $instance['limit'] : 5;
		$title          = ! empty( $instance['title'] ) ? $instance['title'] : '';

		$hashtags = OUTPOST_Hashtag_Manager::get_all( true );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'Widget title', 'outpost' ); ?>
			</label>
			<input
				type="text"
				id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				value="<?php echo esc_attr( $title ); ?>"
				class="widefat"
			/>
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'hashtag_id' ) ); ?>">
				<?php esc_html_e( 'Hashtag to display', 'outpost' ); ?>
			</label>
			<select
				id="<?php echo esc_attr( $this->get_field_id( 'hashtag_id' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'hashtag_id' ) ); ?>"
				class="widefat"
			>
				<option value=""><?php esc_html_e( '-- Choose a hashtag --', 'outpost' ); ?></option>
				<?php foreach ( $hashtags as $row ) : ?>
				<option value="<?php echo esc_attr( $row->id ); ?>" <?php selected( $hashtag_id, $row->id ); ?>>
					#<?php echo esc_html( $row->hashtag ); ?> (<?php echo esc_html( $row->instance_url ); ?>)
				</option>
				<?php endforeach; ?>
			</select>
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>">
				<?php esc_html_e( 'Number of posts to show', 'outpost' ); ?>
			</label>
			<input
				type="number"
				id="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'limit' ) ); ?>"
				value="<?php echo esc_attr( $limit ); ?>"
				min="1"
				max="20"
				class="small-text"
			/>
		</p>

		<p>
			<input
				type="checkbox"
				id="<?php echo esc_attr( $this->get_field_id( 'show_subscribe' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'show_subscribe' ) ); ?>"
				value="1"
				<?php checked( $show_subscribe ); ?>
			/>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_subscribe' ) ); ?>">
				<?php esc_html_e( 'Show subscription form below feed', 'outpost' ); ?>
			</label>
		</p>
		<?php
	}

	// -------------------------------------------------------------------------
	// Save
	// -------------------------------------------------------------------------

	public function update( $new_instance, $old_instance ) {
		return [
			'title'          => sanitize_text_field( $new_instance['title'] ),
			'hashtag_id'     => absint( $new_instance['hashtag_id'] ),
			'limit'          => min( 20, max( 1, absint( $new_instance['limit'] ) ) ),
			'show_subscribe' => ! empty( $new_instance['show_subscribe'] ),
		];
	}
}
