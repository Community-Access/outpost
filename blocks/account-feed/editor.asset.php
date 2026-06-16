<?php
/**
 * Script dependencies for blocks/account-feed/editor.js.
 *
 * Hand-written here because this plugin has no JS build step (no
 *
 * @wordpress/scripts), so there is no generated .asset.php to read
 * dependencies/version from.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'dependencies' => array(
		'wp-blocks',
		'wp-element',
		'wp-block-editor',
		'wp-components',
		'wp-server-side-render',
		'wp-i18n',
	),
	'version'      => OUTPOST_VERSION,
);
