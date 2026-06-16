<?php
/**
 * Registers Gutenberg blocks and their server-side render callbacks.
 *
 * Each render callback maps block attributes onto the existing shortcodes
 * and renders via do_shortcode(), so blocks and shortcodes share the same
 * markup (no drift between editor preview and front-end output).
 */
class OUTPOST_Blocks {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_blocks' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_assets' ) );
	}

	/**
	 * Register block types from their block.json metadata.
	 */
	public static function register_blocks() {
		register_block_type(
			OUTPOST_PLUGIN_DIR . 'blocks/feed',
			array(
				'render_callback' => array( __CLASS__, 'render_feed_block' ),
			)
		);

		register_block_type(
			OUTPOST_PLUGIN_DIR . 'blocks/account-feed',
			array(
				'render_callback' => array( __CLASS__, 'render_account_feed_block' ),
			)
		);
	}

	/**
	 * Make hashtag options available to the block editor.
	 */
	public static function enqueue_editor_assets() {
		wp_localize_script(
			'outpost-feed-editor-script',
			'outpostBlockData',
			array(
				'hashtagOptions' => self::get_hashtag_options( OUTPOST_Hashtag_Manager::get_all( true ) ),
			)
		);
	}

	/**
	 * Render callback for the outpost/feed block.
	 *
	 * @param array $attributes Block attributes (tag, limit, showSubscribe).
	 * @return string
	 */
	public static function render_feed_block( $attributes ) {
		$tag = isset( $attributes['tag'] ) ? trim( (string) $attributes['tag'] ) : '';

		if ( '' === $tag ) {
			return '';
		}

		$limit = isset( $attributes['limit'] ) ? absint( $attributes['limit'] ) : 20;

		$output = do_shortcode( sprintf( '[outpost_feed tag="%s" limit="%d"]', esc_attr( $tag ), $limit ) );

		if ( ! empty( $attributes['showSubscribe'] ) ) {
			$output .= do_shortcode( sprintf( '[outpost_subscribe tag="%s"]', esc_attr( $tag ) ) );
		}

		return $output;
	}

	/**
	 * Render callback for the outpost/account-feed block.
	 *
	 * @param array $attributes Block attributes (limit).
	 * @return string
	 */
	public static function render_account_feed_block( $attributes ) {
		$limit = isset( $attributes['limit'] ) ? absint( $attributes['limit'] ) : 20;
		return do_shortcode( sprintf( '[outpost_account_feed limit="%d"]', $limit ) );
	}

	/**
	 * Map hashtag rows to SelectControl options for the block editor.
	 *
	 * @param object[] $hashtag_rows Rows from OUTPOST_Hashtag_Manager::get_all().
	 * @return array[] List of [ 'label' => string, 'value' => string ].
	 */
	public static function get_hashtag_options( array $hashtag_rows ) {
		$options = array(
			array(
				'label' => __( 'Select a hashtag…', 'outpost' ),
				'value' => '',
			),
		);

		foreach ( $hashtag_rows as $row ) {
			$options[] = array(
				'label' => ! empty( $row->label ) ? $row->label : '#' . $row->hashtag,
				'value' => $row->hashtag,
			);
		}

		return $options;
	}
}
