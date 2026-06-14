<?php
/**
 * PHPUnit bootstrap for OutPost unit tests.
 *
 * These are isolated unit tests (Brain Monkey mocks WordPress core
 * functions) - no WordPress install or database is required.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
