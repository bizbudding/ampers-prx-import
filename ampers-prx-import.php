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

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/classes/class-auth.php';
require_once __DIR__ . '/classes/class-cron.php';
require_once __DIR__ . '/classes/class-import.php';
require_once __DIR__ . '/classes/class-cli.php';
require_once __DIR__ . '/classes/class-logger.php';

/**
 * Initialize the PRX Import plugin.
 *
 * @since 2.0.0
 */
function init_prx_import() {
		// Initialize cron job (replace 123 with your actual account ID)
	// The cron job will run every 3 hours by default and check 50 stories per run
	$cron = new Cron( 123 );

	// Example: Change the interval to 6 hours if needed
	// $cron->set_interval( 6 );

	// Example: Change the number of stories to check per run
	// $cron->set_stories_per_run( 25 );

	// Example: Manually trigger the cron job
	// $results = $cron->manual_check();
	// if ( ! is_wp_error( $results ) ) {
	//     echo "New stories: " . $results['new_stories'] . "\n";
	//     echo "Updated stories: " . $results['updated_stories'] . "\n";
	// }
}

// Initialize the plugin
add_action( 'init', __NAMESPACE__ . '\\init_prx_import' );
