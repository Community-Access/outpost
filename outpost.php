<?php
/**
 * Plugin Name: OutPost
 * Plugin URI:  https://github.com/Community-Access/outpost
 * Description: Bridge your Mastodon hashtag content to your WordPress site and email subscribers. Display accessible feeds, send daily digests, and manage subscribers. Fully open source.
 * Version:     1.0.0
 * Requires at least: 7.0
 * Requires PHP: 8.0
 * Author:      Community Access
 * Author URI:  https://community-access.org
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: outpost
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OUTPOST_VERSION',     '1.0.0' );
define( 'OUTPOST_PLUGIN_FILE', __FILE__ );
define( 'OUTPOST_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'OUTPOST_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

// Autoload classes. PHP class names are case-insensitive, so the lookup is
// keyed on a lowercased class name to stay robust regardless of the casing
// used at any given call site.
spl_autoload_register( function ( $class ) {
	$map = [
		'outpost_activator'       => OUTPOST_PLUGIN_DIR . 'includes/class-outpost-activator.php',
		'outpost_settings'        => OUTPOST_PLUGIN_DIR . 'includes/class-outpost-settings.php',
		'outpost_hashtag_manager' => OUTPOST_PLUGIN_DIR . 'includes/class-outpost-hashtag-manager.php',
		'outpost_feed_fetcher'    => OUTPOST_PLUGIN_DIR . 'includes/class-outpost-feed-fetcher.php',
		'outpost_subscriber'      => OUTPOST_PLUGIN_DIR . 'includes/class-outpost-subscriber.php',
		'outpost_email_digest'    => OUTPOST_PLUGIN_DIR . 'includes/class-outpost-email-digest.php',
		'outpost_shortcodes'      => OUTPOST_PLUGIN_DIR . 'includes/class-outpost-shortcodes.php',
		'outpost_blocks'          => OUTPOST_PLUGIN_DIR . 'includes/class-outpost-blocks.php',
		'outpost_widget'          => OUTPOST_PLUGIN_DIR . 'includes/class-outpost-widget.php',
		'outpost_admin'           => OUTPOST_PLUGIN_DIR . 'admin/class-outpost-admin.php',
		'outpost_public_page'     => OUTPOST_PLUGIN_DIR . 'public/class-outpost-public-page.php',
	];
	$key = strtolower( $class );
	if ( isset( $map[ $key ] ) ) {
		require_once $map[ $key ];
	}
} );

// Activation / deactivation hooks
register_activation_hook( __FILE__,  [ 'OUTPOST_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'OUTPOST_Activator', 'deactivate' ] );

/**
 * Bootstrap the plugin after all plugins are loaded.
 */
function outpost_init() {
	// Load translations
	load_plugin_textdomain( 'outpost', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	// Core services
	OUTPOST_Settings::init();
	OUTPOST_Hashtag_Manager::init();
	OUTPOST_Feed_Fetcher::init();
	OUTPOST_Subscriber::init();
	OUTPOST_Email_Digest::init();
	OUTPOST_Shortcodes::init();
	OUTPOST_Blocks::init();

	// Admin
	if ( is_admin() ) {
		OUTPOST_Admin::init();
	}

	// Public-facing pages
	OUTPOST_Public_Page::init();

	// Register widget
	add_action( 'widgets_init', function () {
		register_widget( 'OUTPOST_Widget' );
	} );
}
add_action( 'plugins_loaded', 'outpost_init' );
