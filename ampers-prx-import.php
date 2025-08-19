<?php

/**
 * Plugin Name:       AMPERS PRX Import
 * Plugin URI:        http://ampers.org/
 * Description:       Handles importing PRX stories, series, and related content into WordPress Posts.
 * Version:           2.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Requires Plugins:  wp-crontrol
 *
 * Author:            BizBudding w/ Flying Orange
 * Author URI:        https://bizbudding.com/
 *
 * Text Domain:       ampers-prx-import
 * License:           GPL-2.0-or-later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.html
 */

namespace Ampers\PRXImport;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Load classes.
require_once __DIR__ . '/classes/class-auth.php';
require_once __DIR__ . '/classes/class-cron.php';
require_once __DIR__ . '/classes/class-import.php';
require_once __DIR__ . '/classes/class-cli.php';
require_once __DIR__ . '/classes/class-logger.php';

// Initialize cron.
new Cron( [
	'account_id'     => account_id(),
	'interval_hours' => 3,
] );

/**
 * The account ID to import from.
 *
 * @return int
 */
function account_id() {
	return 197472;
}

/**
 * The network ID to import from.
 *
 * @return int
 */
function network_id() {
	return 7;
}

// /**
//  * Dump all WP_Error instances in Spatie Ray app.
//  *
//  * @param string|ing $code     Error code.
//  * @param string     $message  Error message.
//  * @param mixed      $data     Error data. Might be empty.
//  * @param WP_Error   $wp_error The WP_Error object.
//  *
//  * @return void
//  */
// add_action( 'wp_error_added', function( $code, $message, $data, $wp_error ) {
// 	if ( ! function_exists( 'ray' ) ) {
// 		return;
// 	}

// 	ray( $wp_error );
// 	ray()->trace();

// }, 10, 4 );