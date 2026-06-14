<?php
/**
 * Builds and sends the daily digest email to confirmed subscribers.
 *
 * Uses a batched WP-Cron approach to avoid PHP timeout on large lists.
 * Flow:
 *   1. outpost_daily_digest_event fires once per day.
 *   2. For each active hashtag, fetches new posts and schedules
 *      outpost_digest_batch_event with offset=0.
 *   3. Each batch event sends emails to $batch_size subscribers,
 *      then schedules the next batch if more remain.
 *   4. When the final batch completes, posts are logged as sent.
 */
class Outpost_Email_Digest {

	public static function init() {
		add_action( 'outpost_daily_digest_event', [ __CLASS__, 'start_all_digests' ] );
		add_action( 'outpost_digest_batch_event',  [ __CLASS__, 'process_batch' ] );
	}

	// -------------------------------------------------------------------------
	// Step 1: Daily trigger — one batch job per active hashtag
	// -------------------------------------------------------------------------

	/**
	 * Called by daily WP-Cron. Kicks off a batch chain for each active hashtag.
	 */
	public static function start_all_digests() {
		$hashtags = Outpost_Hashtag_Manager::get_all( true );

		foreach ( $hashtags as $hashtag_row ) {
			$subscribers = Outpost_Subscriber::get_confirmed( $hashtag_row->id );
			if ( empty( $subscribers ) ) {
				continue;
			}

			$posts = self::get_new_posts( $hashtag_row );
			if ( empty( $posts ) ) {
				continue;
			}

			// Cache posts for this run so all batches use the same set
			set_transient(
				'outpost_digest_posts_' . $hashtag_row->id,
				$posts,
				6 * HOUR_IN_SECONDS
			);

			// Schedule the first batch immediately
			wp_schedule_single_event(
				time(),
				'outpost_digest_batch_event',
				[ $hashtag_row->id, 0 ]
			);
		}
	}

	// -------------------------------------------------------------------------
	// Step 2: Batch processor
	// -------------------------------------------------------------------------

	/**
	 * Process one batch of subscribers for a hashtag digest.
	 *
	 * @param int $hashtag_id
	 * @param int $offset     Subscriber offset for this batch.
	 */
	public static function process_batch( $hashtag_id, $offset ) {
		$hashtag_id  = (int) $hashtag_id;
		$offset      = (int) $offset;
		$batch_size  = (int) get_option( 'outpost_digest_batch_size', 50 );

		$hashtag_row = Outpost_Hashtag_Manager::get( $hashtag_id );
		if ( ! $hashtag_row ) {
			return;
		}

		// Retrieve cached posts for this run
		$posts = get_transient( 'outpost_digest_posts_' . $hashtag_id );
		if ( empty( $posts ) ) {
			// Posts transient expired or was cleared; abort this run
			return;
		}

		// Get one batch of confirmed subscribers
		$batch = self::get_subscriber_batch( $hashtag_id, $offset, $batch_size );

		if ( empty( $batch ) ) {
			// No more subscribers — this run is complete, log the posts
			self::log_sent_posts( $hashtag_id, $posts );
			delete_transient( 'outpost_digest_posts_' . $hashtag_id );
			return;
		}

		// Send emails for this batch
		foreach ( $batch as $subscriber ) {
			self::send_digest( $subscriber, $hashtag_row, $posts );
		}

		// If this batch was full, schedule the next one
		if ( count( $batch ) === $batch_size ) {
			wp_schedule_single_event(
				time() + 30, // 30-second gap between batches
				'outpost_digest_batch_event',
				[ $hashtag_id, $offset + $batch_size ]
			);
		} else {
			// Last batch — log posts and clean up
			self::log_sent_posts( $hashtag_id, $posts );
			delete_transient( 'outpost_digest_posts_' . $hashtag_id );
		}
	}

	// -------------------------------------------------------------------------
	// Manual send from admin (sends all subscribers, bypasses batching)
	// -------------------------------------------------------------------------

	/**
	 * Trigger a digest send immediately for testing. Uses batching.
	 *
	 * @param int $hashtag_id
	 * @return int|WP_Error  Number of subscribers queued, or error.
	 */
	public static function send_digest_now( $hashtag_id ) {
		$hashtag_row = Outpost_Hashtag_Manager::get( $hashtag_id );
		if ( ! $hashtag_row ) {
			return new WP_Error( 'not_found', 'Hashtag not found.' );
		}

		$subscribers = Outpost_Subscriber::get_confirmed( $hashtag_id );
		if ( empty( $subscribers ) ) {
			return new WP_Error( 'no_subscribers', 'No confirmed subscribers for #' . $hashtag_row->hashtag );
		}

		$posts = Outpost_Feed_Fetcher::get_posts( $hashtag_id, Outpost_Settings::get_posts_per_digest(), true );
		if ( empty( $posts ) ) {
			return new WP_Error( 'no_posts', 'No posts found for #' . $hashtag_row->hashtag );
		}

		// Cache and kick off batch chain
		set_transient(
			'outpost_digest_posts_' . $hashtag_id,
			$posts,
			6 * HOUR_IN_SECONDS
		);

		wp_schedule_single_event(
			time(),
			'outpost_digest_batch_event',
			[ $hashtag_id, 0 ]
		);

		return count( $subscribers );
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	/**
	 * Get posts for a hashtag that have not yet been sent in a digest.
	 *
	 * @param object $hashtag_row
	 * @return array
	 */
	private static function get_new_posts( $hashtag_row ) {
		global $wpdb;

		$posts = Outpost_Feed_Fetcher::get_posts_since_yesterday( $hashtag_row->id );
		if ( empty( $posts ) ) {
			return [];
		}

		$sent_uris = $wpdb->get_col( $wpdb->prepare(
			"SELECT post_uri FROM {$wpdb->prefix}outpost_digest_log WHERE hashtag_id = %d",
			$hashtag_row->id
		) );

		$new_posts = array_filter( $posts, function ( $post ) use ( $sent_uris ) {
			return ! in_array( $post->uri, $sent_uris, true );
		} );

		return array_slice(
			array_values( $new_posts ),
			0,
			Outpost_Settings::get_posts_per_digest()
		);
	}

	/**
	 * Fetch one batch of confirmed subscribers by offset.
	 *
	 * @param int $hashtag_id
	 * @param int $offset
	 * @param int $limit
	 * @return array
	 */
	private static function get_subscriber_batch( $hashtag_id, $offset, $limit ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}outpost_subscribers
			 WHERE hashtag_id = %d AND status = 'confirmed'
			 ORDER BY id ASC
			 LIMIT %d OFFSET %d",
			(int) $hashtag_id,
			(int) $limit,
			(int) $offset
		) );
	}

	/**
	 * Send a single digest email to one subscriber.
	 *
	 * @param object $subscriber
	 * @param object $hashtag_row
	 * @param array  $posts
	 */
	private static function send_digest( $subscriber, $hashtag_row, $posts ) {
		$subject = sprintf(
			__( 'Daily digest: #%s', 'outpost' ),
			$hashtag_row->hashtag
		);

		$unsubscribe_url = Outpost_Subscriber::unsubscribe_url( $subscriber );
		$name            = $subscriber->name ?: '';
		$branding_html   = Outpost_Settings::get_branding_html( false );

		ob_start();
		include OUTPOST_PLUGIN_DIR . 'templates/email/digest.php';
		$body = ob_get_clean();

		Outpost_Subscriber::send_email( $subscriber->email, $subject, $body );
	}

	/**
	 * Record sent post URIs in the digest log.
	 *
	 * @param int   $hashtag_id
	 * @param array $posts
	 */
	private static function log_sent_posts( $hashtag_id, $posts ) {
		global $wpdb;
		foreach ( $posts as $post ) {
			$wpdb->replace(
				$wpdb->prefix . 'outpost_digest_log',
				[
					'hashtag_id' => (int) $hashtag_id,
					'post_uri'   => esc_url_raw( $post->uri ),
				],
				[ '%d', '%s' ]
			);
		}
	}
}
