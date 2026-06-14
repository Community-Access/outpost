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

// Autoload classes
spl_autoload_register( function ( $class ) {
	$prefix = 'OUTPOST_';
	if ( strpos( $class, $prefix ) !== 0 ) {
		return;
	}
	$map = [
		'Outpost_Activator'         => OUTPOST_PLUGIN_DIR . 'includes/class-outpost-activator.php',
		'Outpost_Settings'          => OUTPOST_PLUGIN_DIR . 'includes/class-outpost-settings.php',
		'Outpost_Hashtag_Manager'   => OUTPOST_PLUGIN_DIR . 'includes/class-outpost-hashtag-manager.php',
		'Outpost_Feed_Fetcher'      => OUTPOST_PLUGIN_DIR . 'includes/class-outpost-feed-fetcher.php',
		'Outpost_Subscriber'        => OUTPOST_PLUGIN_DIR . 'includes/class-outpost-subscriber.php',
		'Outpost_Email_Digest'      => OUTPOST_PLUGIN_DIR . 'includes/class-outpost-email-digest.php',
		'Outpost_Shortcodes'        => OUTPOST_PLUGIN_DIR . 'includes/class-outpost-shortcodes.php',
		'OUTPOST_Blocks'            => OUTPOST_PLUGIN_DIR . 'includes/class-outpost-blocks.php',
		'Outpost_Widget'            => OUTPOST_PLUGIN_DIR . 'includes/class-outpost-widget.php',
		'Outpost_Admin'             => OUTPOST_PLUGIN_DIR . 'admin/class-outpost-admin.php',
		'Outpost_Public_Page'       => OUTPOST_PLUGIN_DIR . 'public/class-outpost-public-page.php',
	];
	if ( isset( $map[ $class ] ) ) {
		require_once $map[ $class ];
	}
} );

// Activation / deactivation hooks
register_activation_hook( __FILE__,  [ 'Outpost_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Outpost_Activator', 'deactivate' ] );

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
		register_widget( 'Outpost_Widget' );
	} );
}
add_action( 'plugins_loaded', 'outpost_init' );
