<?php
/**
 * Fetches posts from the Mastodon API and caches results.
 */
class OUTPOST_Feed_Fetcher {

	public static function init() {
		// Schedule cache refresh via WP-Cron if needed
		add_action( 'outpost_refresh_feed_cache', [ __CLASS__, 'refresh_all_caches' ] );

		if ( ! wp_next_scheduled( 'outpost_refresh_feed_cache' ) ) {
			wp_schedule_event( time(), 'hourly', 'outpost_refresh_feed_cache' );
		}
	}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Get posts for a hashtag. Returns cached results if fresh.
	 *
	 * @param int  $hashtag_id   ID from outpost_hashtags table.
	 * @param int  $limit        Max posts to return.
	 * @param bool $force        Skip cache and force a fresh fetch.
	 * @return array  Array of post objects, empty array on failure.
	 */
	public static function get_posts( $hashtag_id, $limit = 20, $force = false ) {
		$hashtag_row = OUTPOST_Hashtag_Manager::get( $hashtag_id );
		if ( ! $hashtag_row ) {
			return [];
		}

		$cache_key = 'outpost_feed_' . $hashtag_id;
		$posts     = false;

		if ( ! $force ) {
			$cached = get_transient( $cache_key );
			if ( $cached !== false ) {
				$posts = $cached;
			}
		}

		if ( $posts === false ) {
			$fetched = self::fetch_from_api( $hashtag_row );
			if ( ! is_wp_error( $fetched ) ) {
				set_transient( $cache_key, $fetched, OUTPOST_Settings::get_cache_duration() );
				$posts = $fetched;
			} else {
				// On error, fall back to stale cache if available.
				$stale = get_transient( $cache_key );
				$posts = $stale ? $stale : [];
			}
		}

		$posts = self::apply_account_filter( $posts, $hashtag_row );

		return array_slice( $posts, 0, $limit );
	}

	/**
	 * Restrict posts to the hashtag's account_filter when one is set.
	 *
	 * @param array  $posts
	 * @param object $hashtag_row
	 * @return array
	 */
	private static function apply_account_filter( $posts, $hashtag_row ) {
		$filter = isset( $hashtag_row->account_filter ) ? $hashtag_row->account_filter : '';
		if ( '' === $filter ) {
			return $posts;
		}

		$matched = array_filter( $posts, function ( $post ) use ( $filter ) {
			$acct = isset( $post->account->acct ) ? $post->account->acct : '';
			$url  = isset( $post->account->url ) ? $post->account->url : '';
			return OUTPOST_Hashtag_Manager::post_matches_filter( $filter, $acct, $url );
		} );

		return array_values( $matched );
	}

	/**
	 * Refresh caches for all active hashtags.
	 */
	public static function refresh_all_caches() {
		$hashtags = OUTPOST_Hashtag_Manager::get_all( true );
		foreach ( $hashtags as $row ) {
			self::get_posts( $row->id, 40, true );
		}
	}

	/**
	 * Get posts from the past 24 hours for digest use.
	 *
	 * @param int $hashtag_id
	 * @return array
	 */
	public static function get_posts_since_yesterday( $hashtag_id ) {
		$all       = self::get_posts( $hashtag_id, 40, true );
		$yesterday = time() - DAY_IN_SECONDS;
		$filtered  = [];

		foreach ( $all as $post ) {
			$created = strtotime( $post->created_at );
			if ( $created && $created >= $yesterday ) {
				$filtered[] = $post;
			}
		}

		return $filtered;
	}

	// -------------------------------------------------------------------------
	// Internal
	// -------------------------------------------------------------------------

	/**
	 * Make the actual HTTP request to the Mastodon API.
	 *
	 * @param object $hashtag_row
	 * @return array|WP_Error
	 */
	private static function fetch_from_api( $hashtag_row ) {
		$url = add_query_arg(
			[ 'limit' => 40 ],
			OUTPOST_Hashtag_Manager::get_api_url( $hashtag_row )
		);

		$response = wp_remote_get( $url, [
			'timeout'    => 15,
			'user-agent' => 'MastodonHashtagDigest/' . OUTPOST_VERSION . ' WordPress/' . get_bloginfo( 'version' ),
		] );

		if ( is_wp_error( $response ) ) {
			error_log( '[Outpost] Feed fetch error for hashtag #' . $hashtag_row->hashtag . ': ' . $response->get_error_message() );
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			$msg = 'HTTP ' . $code . ' for ' . $url;
			error_log( '[Outpost] Feed fetch error: ' . $msg );
			return new WP_Error( 'api_error', $msg );
		}

		$body  = wp_remote_retrieve_body( $response );
		$posts = json_decode( $body );

		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $posts ) ) {
			error_log( '[Outpost] JSON decode error for hashtag #' . $hashtag_row->hashtag );
			return new WP_Error( 'json_error', 'Invalid JSON from Mastodon API' );
		}

		return $posts;
	}

	// -------------------------------------------------------------------------
	// Post formatting helpers
	// -------------------------------------------------------------------------

	/**
	 * Strip HTML from a Mastodon post's content and return plain text.
	 *
	 * @param string $html
	 * @return string
	 */
	public static function post_to_plain_text( $html ) {
		// Convert <br> and <p> to newlines before stripping tags
		$text = preg_replace( '/<br\s*\/?>/i', "\n", $html );
		$text = preg_replace( '/<\/p>/i', "\n\n", $text );
		$text = wp_strip_all_tags( $text );
		return trim( html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Format a Mastodon post's created_at timestamp for display.
	 *
	 * @param string $iso8601
	 * @return string
	 */
	public static function format_date( $iso8601 ) {
		$ts = strtotime( $iso8601 );
		if ( ! $ts ) {
			return '';
		}
		return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts );
	}
}
