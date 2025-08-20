<?php

/**
 * Plugin Name:       AMPERS PRX Import
 * Plugin URI:        http://ampers.org/
 * Description:       Handles importing PRX stories, series, and related content into WordPress Posts
 * Version:           2.1.0
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

register_activation_hook( __FILE__, __NAMESPACE__ . '\on_activate' );
/**
 * Plugin activation hook.
 *
 * @since 2.1.0
 *
 * @return void
 */
function on_activate() {
	flush_rewrite_rules();
}

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

add_action( 'init', __NAMESPACE__ . '\register_content_types' );
/**
 * Register content types.
 *
 * @since 2.1.0
 *
 * @return void
 */
function register_content_types() {
	register_taxonomy( 'stations', [ 'post' ], [
		'hierarchical'               => false,
		'labels'                     => [
			'name'                       => _x( 'Stations', 'Station General Name', 'ampers' ),
			'singular_name'              => _x( 'Station', 'Station Singular Name', 'ampers' ),
			'menu_name'                  => __( 'Stations', 'ampers' ),
			'all_items'                  => __( 'All Items', 'ampers' ),
			'parent_item'                => __( 'Parent Station', 'ampers' ),
			'parent_item_colon'          => __( 'Parent Station:', 'ampers' ),
			'new_item_name'              => __( 'New Station Name', 'ampers' ),
			'add_new_item'               => __( 'Add New Station', 'ampers' ),
			'edit_item'                  => __( 'Edit Station', 'ampers' ),
			'update_item'                => __( 'Update Station', 'ampers' ),
			'view_item'                  => __( 'View Station', 'ampers' ),
			'separate_items_with_commas' => __( 'Separate stations with commas', 'ampers' ),
			'add_or_remove_items'        => __( 'Add or remove stations', 'ampers' ),
			'choose_from_most_used'      => __( 'Choose from the most used', 'ampers' ),
			'popular_items'              => __( 'Popular Stations', 'ampers' ),
			'search_items'               => __( 'Search Stations', 'ampers' ),
			'not_found'                  => __( 'Not Found', 'ampers' ),
		],
		'public'                     => true,
		'show_admin_column'          => true,
		'show_in_nav_menus'          => true,
		'show_in_rest'               => true,
		'show_in_quick_edit'         => true,
		'show_tagcloud'              => true,
		'show_ui'                    => true,
		'rewrite'                    => [ 'slug' => 'stations', 'with_front' => false ],
	] );
}