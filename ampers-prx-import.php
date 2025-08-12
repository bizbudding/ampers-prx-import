<?php

/**
 * Plugin Name:      AMPERS PRX Import
 * Plugin URI:       http://ampers.org/
 * Description:      A plugin that enables an bulk options pulldown to delete and reimport prx content from the main WordPress edit posts page.
 * Author:           BizBudding
 * Author URI:       https://bizbudding.com/
 * Version:          2.0.0
 * Text Domain:      prx-import
 * License:          GPL-2.0-or-later
 * License URI:      http://www.gnu.org/licenses/gpl-2.0.html
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