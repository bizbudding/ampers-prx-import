<?php

namespace Ampers\PRXImport;

/**
 * PRX Content Types
 *
 * Handles content types for PRX API.
 *
 * @since 2.0.0
 */
class ContentTypes {
	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function __construct() {
		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'init', [ $this, 'register_content_types' ] );
	}

	/**
	 * Plugin activation hook.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function on_activate() {
		// Register content types.
		$this->register_content_types();

		// Flush rewrite rules to ensure new URLs work.
		flush_rewrite_rules();
	}

	/**
	 * Register content types.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function register_content_types() {
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
}