<?php
/**
 * Manages hashtag configurations stored in the database.
 */
class OUTPOST_Hashtag_Manager {

	public static function init() {
		// Nothing to hook at runtime; methods are called directly.
	}

	// -------------------------------------------------------------------------
	// Getters
	// -------------------------------------------------------------------------

	/**
	 * Get all hashtag configurations.
	 *
	 * @param bool $active_only  If true, only return active hashtags.
	 * @return array
	 */
	public static function get_all( $active_only = false ) {
		global $wpdb;
		$table = $wpdb->prefix . 'outpost_hashtags';

		if ( $active_only ) {
			return $wpdb->get_results( "SELECT * FROM $table WHERE active = 1 ORDER BY hashtag ASC" );
		}
		return $wpdb->get_results( "SELECT * FROM $table ORDER BY hashtag ASC" );
	}

	/**
	 * Get a single hashtag by ID.
	 *
	 * @param int $id
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'outpost_hashtags';
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
	}

	/**
	 * Get a hashtag by hashtag slug and instance URL.
	 *
	 * @param string $hashtag
	 * @param string $instance_url
	 * @return object|null
	 */
	public static function get_by_tag( $hashtag, $instance_url ) {
		global $wpdb;
		$table = $wpdb->prefix . 'outpost_hashtags';
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table WHERE hashtag = %s AND instance_url = %s",
			self::normalize_tag( $hashtag ),
			self::normalize_instance( $instance_url )
		) );
	}

	// -------------------------------------------------------------------------
	// CRUD
	// -------------------------------------------------------------------------

	/**
	 * Add a new hashtag configuration.
	 *
	 * @param string $hashtag       Hashtag without the # symbol.
	 * @param string $instance_url  Full URL of the Mastodon instance.
	 * @param string $label         Human-readable label.
	 * @return int|WP_Error  New row ID on success, WP_Error on failure.
	 */
	public static function add( $hashtag, $instance_url, $label = '', $account_filter = '' ) {
		global $wpdb;

		$hashtag      = self::normalize_tag( $hashtag );
		$instance_url = self::normalize_instance( $instance_url );

		if ( empty( $hashtag ) ) {
			return new WP_Error( 'invalid_hashtag', __( 'Hashtag cannot be empty.', 'outpost' ) );
		}

		if ( ! filter_var( $instance_url, FILTER_VALIDATE_URL ) ) {
			return new WP_Error( 'invalid_url', __( 'Instance URL is not valid.', 'outpost' ) );
		}

		// Check for duplicate
		if ( self::get_by_tag( $hashtag, $instance_url ) ) {
			return new WP_Error( 'duplicate', __( 'This hashtag is already configured for that instance.', 'outpost' ) );
		}

		$result = $wpdb->insert(
			$wpdb->prefix . 'outpost_hashtags',
			[
				'hashtag'        => $hashtag,
				'instance_url'   => $instance_url,
				'label'          => sanitize_text_field( $label ?: '#' . $hashtag ),
				'account_filter' => self::normalize_handle( $account_filter ),
				'active'         => 1,
			],
			[ '%s', '%s', '%s', '%s', '%d' ]
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Could not save hashtag to database.', 'outpost' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an existing hashtag configuration.
	 *
	 * @param int    $id
	 * @param array  $data  Keys: hashtag, instance_url, label, active.
	 * @return bool|WP_Error
	 */
	public static function update( $id, array $data ) {
		global $wpdb;

		$allowed = [];

		if ( isset( $data['hashtag'] ) ) {
			$allowed['hashtag'] = self::normalize_tag( $data['hashtag'] );
		}
		if ( isset( $data['instance_url'] ) ) {
			$allowed['instance_url'] = self::normalize_instance( $data['instance_url'] );
		}
		if ( isset( $data['label'] ) ) {
			$allowed['label'] = sanitize_text_field( $data['label'] );
		}
		if ( isset( $data['account_filter'] ) ) {
			$allowed['account_filter'] = self::normalize_handle( $data['account_filter'] );
		}
		if ( isset( $data['active'] ) ) {
			$allowed['active'] = (int) (bool) $data['active'];
		}

		if ( empty( $allowed ) ) {
			return true;
		}

		$result = $wpdb->update(
			$wpdb->prefix . 'outpost_hashtags',
			$allowed,
			[ 'id' => (int) $id ]
		);

		return ( $result !== false );
	}

	/**
	 * Delete a hashtag and all associated subscribers and digest logs.
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		$id = (int) $id;

		// Delete subscribers
		$wpdb->delete( $wpdb->prefix . 'outpost_subscribers', [ 'hashtag_id' => $id ], [ '%d' ] );

		// Delete digest log
		$wpdb->delete( $wpdb->prefix . 'outpost_digest_log', [ 'hashtag_id' => $id ], [ '%d' ] );

		// Delete hashtag
		return (bool) $wpdb->delete( $wpdb->prefix . 'outpost_hashtags', [ 'id' => $id ], [ '%d' ] );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Normalize a hashtag: strip leading #, lowercase, trim.
	 */
	public static function normalize_tag( $tag ) {
		return strtolower( trim( ltrim( trim( $tag ), '#' ) ) );
	}

	/**
	 * Normalize an instance URL: lowercase, strip trailing slash.
	 */
	public static function normalize_instance( $url ) {
		$url = strtolower( trim( $url ) );
		$url = rtrim( $url, '/' );

		// Add https:// if missing
		if ( $url && ! preg_match( '/^https?:\/\//', $url ) ) {
			$url = 'https://' . $url;
		}

		return $url;
	}

	/**
	 * Normalize a Mastodon account handle: trim, strip one leading @, lowercase.
	 *
	 * @param string $handle
	 * @return string
	 */
	public static function normalize_handle( $handle ) {
		return strtolower( ltrim( trim( (string) $handle ), '@' ) );
	}

	/**
	 * Whether a post matches a stored account filter.
	 *
	 * @param string $filter      Stored filter (any casing; may be blank).
	 * @param string $acct        Post's account->acct ("user" if local to the
	 *                             hashtag instance, "user@host" if remote).
	 * @param string $account_url Post's account->url (used to derive the host
	 *                             when the acct is local).
	 * @return bool
	 */
	public static function post_matches_filter( $filter, $acct, $account_url ) {
		$filter = self::normalize_handle( $filter );
		if ( '' === $filter ) {
			return true;
		}

		$acct = strtolower( (string) $acct );
		if ( $filter === $acct ) {
			return true;
		}

		// Filter carries a host but the post is local to the hashtag instance
		// (acct has no host): compare against username@<host of account url>.
		if ( false !== strpos( $filter, '@' ) && false === strpos( $acct, '@' ) ) {
			$host = strtolower( (string) wp_parse_url( $account_url, PHP_URL_HOST ) );
			if ( $host && $filter === $acct . '@' . $host ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Return the Mastodon API endpoint for a hashtag.
	 *
	 * @param object $hashtag_row  Row from outpost_hashtags table.
	 * @return string
	 */
	public static function get_api_url( $hashtag_row ) {
		return trailingslashit( $hashtag_row->instance_url )
			. 'api/v1/timelines/tag/'
			. rawurlencode( $hashtag_row->hashtag );
	}
}
