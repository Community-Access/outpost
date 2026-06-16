<?php
/**
 * Runs when the plugin is deleted via the WordPress admin.
 *
 * Removes all plugin data: cron events, transients, options, and database tables.
 * Data is preserved on deactivation -- only wiped here on full delete.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// 1. Clear all plugin cron events.
wp_clear_scheduled_hook( 'outpost_daily_digest_event' );
wp_clear_scheduled_hook( 'outpost_refresh_feed_cache' );
wp_clear_scheduled_hook( 'outpost_digest_batch_event' );

// 2. Delete all plugin transients (feed cache and digest staging).
//    Keys are dynamic (per hashtag ID), so a direct LIKE query is simpler
//    than looping over hashtag IDs after the table may already be dropped.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '_transient_outpost\_%'
	    OR option_name LIKE '_transient_timeout_outpost\_%'"
);

// 3. Delete all plugin options.
$options = [
	'outpost_show_setup_wizard',
	'outpost_db_version',
	'outpost_digest_send_hour',
	'outpost_digest_send_minute',
	'outpost_from_name',
	'outpost_from_email',
	'outpost_branding_text',
	'outpost_branding_url',
	'outpost_posts_per_digest',
	'outpost_digest_batch_size',
	'outpost_cache_duration',
	'outpost_double_optin',
	'outpost_manage_page_id',
	'outpost_brand_account',
];

foreach ( $options as $option ) {
	delete_option( $option );
}

// 4. Drop custom database tables.
//    Drop in dependency order: log and subscribers reference hashtags.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}outpost_digest_log" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}outpost_subscribers" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}outpost_hashtags" );
