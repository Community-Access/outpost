<?php
/**
 * Fired during plugin activation.
 *
 * Creates all required database tables and schedules the daily digest cron.
 */
class OUTPOST_Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		self::create_tables();
		self::set_defaults();
		self::schedule_cron();
		// Flag so the admin sees the setup wizard on first load
		update_option( 'outpost_show_setup_wizard', true );
	}

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'outpost_daily_digest_event' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'outpost_daily_digest_event' );
		}
	}

	/**
	 * Create the plugin's custom database tables.
	 */
	private static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// Hashtag configurations
		$sql_hashtags = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}outpost_hashtags (
			id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			hashtag       VARCHAR(255)        NOT NULL,
			instance_url  VARCHAR(500)        NOT NULL DEFAULT 'https://mastodon.social',
			label         VARCHAR(255)        NOT NULL DEFAULT '',
			active        TINYINT(1)          NOT NULL DEFAULT 1,
			created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY hashtag_instance (hashtag, instance_url)
		) $charset_collate;";

		// Subscribers (per hashtag)
		$sql_subscribers = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}outpost_subscribers (
			id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			hashtag_id    BIGINT(20) UNSIGNED NOT NULL,
			email         VARCHAR(255)        NOT NULL,
			name          VARCHAR(255)        NOT NULL DEFAULT '',
			status        ENUM('pending','confirmed','unsubscribed') NOT NULL DEFAULT 'pending',
			token         VARCHAR(64)         NOT NULL,
			confirmed_at  DATETIME            NULL,
			created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY email_hashtag (email, hashtag_id),
			KEY hashtag_id (hashtag_id),
			KEY token (token),
			KEY status (status)
		) $charset_collate;";

		// Digest log - tracks which posts have already been sent
		$sql_digest_log = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}outpost_digest_log (
			id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			hashtag_id    BIGINT(20) UNSIGNED NOT NULL,
			post_uri      VARCHAR(500)        NOT NULL,
			sent_at       DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY hashtag_sent (hashtag_id, sent_at),
			UNIQUE KEY post_uri (hashtag_id, post_uri(255))
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_hashtags );
		dbDelta( $sql_subscribers );
		dbDelta( $sql_digest_log );

		update_option( 'outpost_db_version', '1.0.0' );
	}

	/**
	 * Set sensible default plugin options if they don't exist.
	 */
	private static function set_defaults() {
		$defaults = [
			'outpost_digest_send_hour'      => 8,   // 8 AM local time
			'outpost_digest_send_minute'    => 0,
			'outpost_from_name'             => get_bloginfo( 'name' ),
			'outpost_from_email'            => get_option( 'admin_email' ),
			'outpost_branding_text'         => '',
			'outpost_branding_url'          => '',
			'outpost_posts_per_digest'      => 10,
			'outpost_digest_batch_size'     => 50,  // subscribers per batch
			'outpost_cache_duration'        => 3600, // 1 hour
			'outpost_double_optin'          => true,
			'outpost_manage_page_id'        => 0,
		];

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				update_option( $key, $value );
			}
		}
	}

	/**
	 * Reschedule the daily digest cron to match the current send-time options.
	 *
	 * Safe to call on a settings save: it only touches the digest cron event and
	 * does not re-create tables, re-seed defaults, or reset the setup-wizard flag.
	 */
	public static function reschedule_digest_cron() {
		// Clear every scheduled instance of the hook, not just the next one, so
		// any accidental WP-Cron duplicates cannot run the digest more than once.
		wp_clear_scheduled_hook( 'outpost_daily_digest_event' );
		self::schedule_cron();
	}

	/**
	 * Schedule the daily digest cron event.
	 */
	private static function schedule_cron() {
		if ( ! wp_next_scheduled( 'outpost_daily_digest_event' ) ) {
			$hour   = (int) get_option( 'outpost_digest_send_hour', 8 );
			$minute = (int) get_option( 'outpost_digest_send_minute', 0 );

			// Schedule for next occurrence of the configured hour
			$now        = current_time( 'timestamp' );
			$today_send = mktime( $hour, $minute, 0, date( 'n', $now ), date( 'j', $now ), date( 'Y', $now ) );
			$start      = ( $today_send > $now ) ? $today_send : $today_send + DAY_IN_SECONDS;

			wp_schedule_event( $start, 'daily', 'outpost_daily_digest_event' );
		}
	}
}
