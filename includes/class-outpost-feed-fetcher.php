<?php
/**
 * Fetches posts from the Mastodon API and caches results.
 */
class OUTPOST_Feed_Fetcher {

	public static function init() {
		// Schedule cache refresh via WP-Cron if needed
		add_action( 'outpost_refresh_feed_cache', array( __CLASS__, 'refresh_all_caches' ) );

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
			return array();
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
				$posts = $stale ? $stale : array();
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

		$matched = array_filter(
			$posts,
			function ( $post ) use ( $filter ) {
				$acct = isset( $post->account->acct ) ? $post->account->acct : '';
				$url  = isset( $post->account->url ) ? $post->account->url : '';
				return OUTPOST_Hashtag_Manager::post_matches_filter( $filter, $acct, $url );
			}
		);

		return array_values( $matched );
	}

	/**
	 * Get the configured brand account's own posts (original posts only).
	 *
	 * @param int  $limit
	 * @param bool $force
	 * @return array Post objects, empty array on failure / no account.
	 */
	public static function get_account_posts( $limit = 20, $force = false ) {
		$handle = OUTPOST_Settings::get_brand_account();
		if ( '' === $handle || false === strpos( $handle, '@' ) ) {
			return array();
		}

		list( $username, $host ) = explode( '@', $handle, 2 );
		if ( '' === $username || '' === $host ) {
			return array();
		}

		$cache_key = 'outpost_account_feed';

		if ( ! $force ) {
			$cached = get_transient( $cache_key );
			if ( $cached !== false ) {
				return array_slice( $cached, 0, $limit );
			}
		}

		$account_id = self::resolve_account_id( $host, $username );
		if ( ! $account_id ) {
			$stale = get_transient( $cache_key );
			return $stale ? array_slice( $stale, 0, $limit ) : array();
		}

		$url = 'https://' . $host . '/api/v1/accounts/' . rawurlencode( $account_id ) . '/statuses';
		$url = add_query_arg(
			array(
				'limit'           => 40,
				'exclude_replies' => 'true',
				'exclude_reblogs' => 'true',
			),
			$url
		);

		$posts = self::request_json( $url );
		if ( is_wp_error( $posts ) || ! is_array( $posts ) ) {
			$stale = get_transient( $cache_key );
			return $stale ? array_slice( $stale, 0, $limit ) : array();
		}

		set_transient( $cache_key, $posts, OUTPOST_Settings::get_cache_duration() );
		return array_slice( $posts, 0, $limit );
	}

	/**
	 * Resolve and cache a Mastodon account id from its host + username.
	 *
	 * @param string $host
	 * @param string $username
	 * @return string|false
	 */
	private static function resolve_account_id( $host, $username ) {
		$key    = 'outpost_account_id_' . md5( $host . '|' . $username );
		$cached = get_transient( $key );
		if ( $cached !== false ) {
			return $cached;
		}

		$url     = 'https://' . $host . '/api/v1/accounts/lookup?acct=' . rawurlencode( $username );
		$account = self::request_json( $url );
		if ( is_wp_error( $account ) || ! isset( $account->id ) ) {
			return false;
		}

		set_transient( $key, $account->id, DAY_IN_SECONDS );
		return $account->id;
	}

	/**
	 * Shared JSON GET against the Mastodon API.
	 *
	 * @param string $url
	 * @return mixed|WP_Error Decoded JSON, or WP_Error on failure.
	 */
	private static function request_json( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 15,
				'user-agent' => 'MastodonHashtagDigest/' . OUTPOST_VERSION . ' WordPress/' . get_bloginfo( 'version' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return new WP_Error( 'api_error', 'Non-200 from ' . $url );
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ) );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'json_error', 'Invalid JSON from ' . $url );
		}
		return $decoded;
	}

	/**
	 * Refresh caches for all active hashtags.
	 */
	public static function refresh_all_caches() {
		$hashtags = OUTPOST_Hashtag_Manager::get_all( true );
		foreach ( $hashtags as $row ) {
			self::get_posts( $row->id, 40, true );
		}

		// Keep the brand-account feed warm too.
		self::get_account_posts( 40, true );
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
		$filtered  = array();

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
			array( 'limit' => 40 ),
			OUTPOST_Hashtag_Manager::get_api_url( $hashtag_row )
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 15,
				'user-agent' => 'MastodonHashtagDigest/' . OUTPOST_VERSION . ' WordPress/' . get_bloginfo( 'version' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			return new WP_Error( 'api_error', 'HTTP ' . $code . ' for ' . $url );
		}

		$body  = wp_remote_retrieve_body( $response );
		$posts = json_decode( $body );

		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $posts ) ) {
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
