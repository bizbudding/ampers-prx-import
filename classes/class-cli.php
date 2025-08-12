<?php

namespace Ampers\PRXImport;

use WP_CLI;

defined( 'ABSPATH' ) || exit;

add_action( 'cli_init', __NAMESPACE__ . '\register_cli_commands' );
/**
 * Register CLI commands.
 *
 * @since 2.0.0
 *
 * @return void
 */
function register_cli_commands() {
	WP_CLI::add_command( 'ampers', CLI::class );
}

/**
 * Custom WP-CLI Commands for Ampers PRX Import
 *
 * @since 2.0.0
 */
class CLI {

	/**
	 * Test PRX API authentication
	 *
	 * @subcommand test-auth
	 *
	 * ## EXAMPLES
	 *
	 *     wp ampers test-auth
	 *
	 * @since 2.0.0
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command options.
	 *
	 * @return void
	 */
	public function test_auth( $args, $assoc_args ) {
		WP_CLI::log( "Testing PRX API authentication..." );

		$auth = new Auth();
		$result = $auth->test_connection();

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( 'Authentication failed: ' . $result->get_error_message() );
			return;
		}

		WP_CLI::success( 'Authentication successful!' );

		// Get and display authorization info
		$auth_info = $auth->get_authorization();
		if ( ! is_wp_error( $auth_info ) ) {
			WP_CLI::log( print_r( $auth_info, true ) );
		}
	}

	/**
	 * Test and log PRX stories
	 *
	 * @subcommand test-stories
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<limit>]
	 * : Number of stories to test. Default: 3
	 *
	 * ## EXAMPLES
	 *
	 *     wp ampers test-stories --limit=2
	 *
	 * @since 2.0.0
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command options.
	 *
	 * @return void
	 */
	public function test_stories( $args, $assoc_args ) {
		$limit = $assoc_args['limit'] ?? 3;

		WP_CLI::log( "Testing PRX stories (limit: {$limit})..." );

		// Initialize auth and import
		$auth = new Auth();
		$import = new Import( $auth );

		// Test network stories (Network ID 7)
		WP_CLI::log( "Testing Network ID 7 stories..." );
		$network_stories = $import->get_network_stories( 7, 1, $limit );

		if ( is_wp_error( $network_stories ) ) {
			WP_CLI::error( 'Network stories failed: ' . $network_stories->get_error_message() );
		} else {
			$network_count = count( $network_stories['_embedded']['prx:items'] ?? [] );
			WP_CLI::success( "Network stories: {$network_count}" );

			if ( $network_count > 0 ) {
				WP_CLI::log( "First network story:" );
				$first_story = $network_stories['_embedded']['prx:items'][0];
				WP_CLI::log( "  ID: {$first_story['id']}" );
				WP_CLI::log( "  Title: {$first_story['title']}" );
				WP_CLI::log( "  Series: {$first_story['_embedded']['prx:series']['title']}" );
			}
		}

		// Test account stories (Ampers account 197472)
		WP_CLI::log( "Testing Account ID 197472 stories..." );
		$account_stories = $import->get_account_stories( 197472, 1, $limit );

		if ( is_wp_error( $account_stories ) ) {
			WP_CLI::error( 'Account stories failed: ' . $account_stories->get_error_message() );
		} else {
			$account_count = count( $account_stories['_embedded']['prx:items'] ?? [] );
			WP_CLI::success( "Account stories: {$account_count}" );

			if ( $account_count > 0 ) {
				WP_CLI::log( "First account story:" );
				$first_story = $account_stories['_embedded']['prx:items'][0];
				WP_CLI::log( "  ID: {$first_story['id']}" );
				WP_CLI::log( "  Title: {$first_story['title']}" );
				WP_CLI::log( "  Series: {$first_story['_embedded']['prx:series']['title']}" );
				WP_CLI::log( "  Audio files: " . count( $first_story['_embedded']['prx:audio']['_embedded']['prx:items'] ?? [] ) );
				WP_CLI::log( "  Images: " . ( ! empty( $first_story['_embedded']['prx:image'] ) ? '1' : '0' ) );

				// Log ACF-relevant data
				WP_CLI::log( "" );
				WP_CLI::log( "ACF Field Data:" );
				WP_CLI::log( "  prx_id: {$first_story['id']}" );
				WP_CLI::log( "  duration: {$first_story['duration']}" );
				WP_CLI::log( "  series_id: {$first_story['_embedded']['prx:series']['id']}" );
				WP_CLI::log( "  series: {$first_story['_embedded']['prx:series']['title']}" );
				WP_CLI::log( "  station: {$first_story['_embedded']['prx:account']['shortName']}" );
				WP_CLI::log( "  long_description: " . ( ! empty( $first_story['description'] ) ? 'Has content' : 'Empty' ) );
				WP_CLI::log( "  transcript: " . ( ! empty( $first_story['transcript'] ) ? 'Has content' : 'Empty' ) );

				// Audio file details
				if ( ! empty( $first_story['_embedded']['prx:audio']['_embedded']['prx:items'] ) ) {
					$audio = $first_story['_embedded']['prx:audio']['_embedded']['prx:items'][0];
					WP_CLI::log( "  audio_mp3_url: {$audio['_links']['enclosure']['href']}" );
					WP_CLI::log( "  audio_label: {$audio['label']}" );
					WP_CLI::log( "  audio_length: {$audio['duration']}" );
				}

				// Image details
				if ( ! empty( $first_story['_embedded']['prx:image'] ) ) {
					$image = $first_story['_embedded']['prx:image'];
					WP_CLI::log( "  image_url: {$image['_links']['original']['href']}" );
					WP_CLI::log( "  image_caption: " . ( ! empty( $image['caption'] ) ? $image['caption'] : 'Empty' ) );
					WP_CLI::log( "  image_credit: " . ( ! empty( $image['credit'] ) ? $image['credit'] : 'Empty' ) );
				}

				// Publication date
				WP_CLI::log( "  publishedAt: {$first_story['publishedAt']}" );
			}
		}
	}

	/**
	 * List available accounts
	 *
	 * @subcommand list-accounts
	 *
	 * ## EXAMPLES
	 *
	 *     wp ampers list-accounts
	 *
	 * @since 2.0.0
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command options.
	 *
	 * @return void
	 */
	public function list_accounts( $args, $assoc_args ) {
		WP_CLI::log( "Fetching available accounts..." );

		$auth = new Auth();
		$accounts = $auth->make_request( '/authorization/accounts' );

		if ( is_wp_error( $accounts ) ) {
			WP_CLI::error( 'Failed to fetch accounts: ' . $accounts->get_error_message() );
			return;
		}

		// Debug: Show the full response structure
		WP_CLI::log( "Full accounts response:" );
		WP_CLI::log( print_r( $accounts, true ) );

						// Check for the authorization accounts structure
		if ( ! empty( $accounts['_embedded']['prx:items'] ) ) {
			WP_CLI::success( 'Available accounts:' );
			foreach ( $accounts['_embedded']['prx:items'] as $account ) {
				WP_CLI::log( '' );
				WP_CLI::log( "Account ID: {$account['id']}" );
				WP_CLI::log( "Name: {$account['name']}" );
				WP_CLI::log( "Short Name: {$account['shortName']}" );
				WP_CLI::log( "Type: {$account['type']}" );

				// Show series information if available
				if ( ! empty( $account['_embedded']['prx:series']['_embedded']['prx:items'] ) ) {
					WP_CLI::log( "Series:" );
					foreach ( $account['_embedded']['prx:series']['_embedded']['prx:items'] as $series ) {
						WP_CLI::log( "  - {$series['title']} (ID: {$series['id']}, Stories: {$series['_links']['prx:stories']['count']})" );
					}
				}
			}
		} else {
			WP_CLI::warning( 'No accounts found in expected structure.' );
			WP_CLI::log( 'Available keys in response:' );
			WP_CLI::log( print_r( array_keys( $accounts ), true ) );
			if ( ! empty( $accounts['_embedded'] ) ) {
				WP_CLI::log( 'Available embedded keys:' );
				WP_CLI::log( print_r( array_keys( $accounts['_embedded'] ), true ) );
			}
			return;
		}
	}

	/**
	 * Import PRX content
	 *
	 * @subcommand import-prx
	 *
	 * ## OPTIONS
	 *
	 * [--account-id=<account-id>]
	 * : PRX Account ID to import from. Default: 197472 (Ampers)
	 *
	 * [--per-page=<per-page>]
	 * : Number of stories per page to fetch from PRX API. Default: 10
	 *
	 * [--page=<page>]
	 * : Page number to fetch from PRX API. Default: 1
	 *
	 * [--dry-run]
	 * : Perform a dry run without making any changes
	 *
	 * ## EXAMPLES
	 *
	 *     wp ampers import-prx --per-page=25 --page=1
	 *     wp ampers import-prx --per-page=10 --page=1 --dry-run
	 *     wp ampers import-prx --account-id=197472 --per-page=50 --page=2
	 *
	 * @since 2.0.0
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command options.
	 *
	 * @return void
	 */
	public function import_prx( $args, $assoc_args ) {
		$account_id = isset( $assoc_args['account-id'] ) ? intval( $assoc_args['account-id'] ) : 197472;
		$per_page   = isset( $assoc_args['per-page'] ) ? intval( $assoc_args['per-page'] ) : 10;
		$page       = isset( $assoc_args['page'] ) ? intval( $assoc_args['page'] ) : 1;
		$dry_run    = isset( $assoc_args['dry-run'] );

		WP_CLI::log( "Starting PRX import..." );
		WP_CLI::log( "Account ID: {$account_id}, Per Page: {$per_page}, Page: {$page}" . ( $dry_run ? ', DRY RUN' : '' ) );

		// Test authentication first
		$auth = new Auth();
		$auth_result = $auth->test_connection();

		if ( is_wp_error( $auth_result ) ) {
			WP_CLI::error( 'Authentication failed: ' . $auth_result->get_error_message() );
			return;
		}

		WP_CLI::success( 'Authentication successful!' );

		// Initialize import class with options
		$import_args = [
			'dry_run' => $dry_run,
		];
		$import = new Import( $auth, $import_args );

		// Get stories from account
		$stories = $import->get_account_stories( $account_id, $page, $per_page );

		if ( is_wp_error( $stories ) ) {
			WP_CLI::error( 'Failed to fetch stories: ' . $stories->get_error_message() );
			return;
		}

		if ( empty( $stories['_embedded']['prx:items'] ) ) {
			WP_CLI::warning( 'No stories found for this account.' );
			return;
		}

		$story_count = count( $stories['_embedded']['prx:items'] );
		WP_CLI::log( "Found {$story_count} stories to process..." );

		$success = 0;
		$failed  = 0;
		$errors  = [];

		// Process each story
		foreach ( $stories['_embedded']['prx:items'] as $story ) {
			$result = $import->import_story( $story );

			if ( is_wp_error( $result ) ) {
				$failed++;
				$errors[] = "Story {$story['id']}: " . $result->get_error_message();
			} else {
				$success++;
			}
		}

		// Display results
		$action = $dry_run ? 'Dry run completed' : 'Import completed';
		WP_CLI::success( "{$action}! Success: {$success}, Failed: {$failed}" );

		if ( ! empty( $errors ) ) {
			WP_CLI::warning( 'Errors encountered:' );
			foreach ( $errors as $error ) {
				WP_CLI::log( "  - {$error}" );
			}
		}
	}

	/**
	 * Delete removed ACF fields.
	 *
	 * @subcommand delete-removed-acf-fields
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Show what would be done without making changes.
	 *
	 * [--offset=<number>]
	 * : Start from this offset (default: 0).
	 *
	 * [--per_page=<number>]
	 * : Number of posts to process per batch (default: 50).
	 *
	 * [--post_status=<status>]
	 * : Post status to process (default: any).
	 *
	 * ## EXAMPLES
	 *
	 *     wp ampers delete-removed-acf-fields --dry-run
	 *     wp ampers delete-removed-acf-fields --per_page=30000
	 *     wp ampers delete-removed-acf-fields --offset=100 --per_page=25
	 *     wp ampers delete-removed-acf-fields --post_status=publish
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command options.
	 *
	 * @return void
	 */
	public function delete_removed_acf_fields( $args, $assoc_args ) {
		$dry_run     = isset( $assoc_args['dry-run'] );
		$offset      = isset( $assoc_args['offset'] ) ? (int) $assoc_args['offset'] : 0;
		$per_page    = isset( $assoc_args['per_page'] ) ? (int) $assoc_args['per_page'] : 20;
		$post_status = isset( $assoc_args['post_status'] ) ? $assoc_args['post_status'] : 'any';

		// Validate post status.
		$valid_statuses = ['any', 'publish', 'draft', 'pending', 'private', 'trash'];
		if ( ! in_array( $post_status, $valid_statuses ) ) {
			\WP_CLI::error( sprintf( 'Invalid post status: %s. Valid statuses: %s', $post_status, implode( ', ', $valid_statuses ) ) );
		}

		try {
			$query = new \WP_Query(
				[
					'post_type'              => 'post',
					'posts_per_page'         => $per_page,
					'offset'                 => $offset,
					'post_status'            => $post_status,
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				]
			);

			// Initialize counters
			$processed = 0;
			$deleted   = 0;

			if ( $query->have_posts() ) {
				WP_CLI::log( sprintf( 'Processing %d posts starting from offset %d...', $query->post_count, $offset ) );

				while ( $query->have_posts() ) : $query->the_post();
					$processed++;
					$post_id = get_the_ID();
					delete_post_meta( $post_id, 'images' );
					delete_post_meta( $post_id, '_images' );
					delete_post_meta( $post_id, 'series' );
					delete_post_meta( $post_id, '_series' );
					delete_post_meta( $post_id, 'station' );
					delete_post_meta( $post_id, '_station' );
					delete_post_meta( $post_id, 'long_description' );
					delete_post_meta( $post_id, '_long_description' );

					$deleted++;
				endwhile;
			}
			wp_reset_postdata();

			WP_CLI::log( sprintf( 'Processed %d posts, deleted post meta from %d posts', $processed, $deleted ) );
		} catch ( \Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}
	}

	/**
	 * Check meta fields.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Show what would be done without making changes.
	 *
	 * [--offset=<number>]
	 * : Start from this offset (default: 0).
	 *
	 * [--per_page=<number>]
	 * : Number of posts to process per batch (default: 50).
	 *
	 * [--post_status=<status>]
	 * : Post status to process (default: any).
	 *
	 * ## EXAMPLES
	 *
	 *     wp ampers check-meta --per_page=30
	 *     wp ampers check-meta --dry-run
	 *     wp ampers check-meta --offset=100 --per_page=25
	 *     wp ampers check-meta --post_status=publish
	 *
	 * @subcommand check-meta
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function check_meta( $args, $assoc_args ) {
		$offset      = isset( $assoc_args['offset'] ) ? (int) $assoc_args['offset'] : 0;
		$per_page    = isset( $assoc_args['per_page'] ) ? (int) $assoc_args['per_page'] : 20;
		$post_status = isset( $assoc_args['post_status'] ) ? $assoc_args['post_status'] : 'any';

		// Validate post status.
		$valid_statuses = ['any', 'publish', 'draft', 'pending', 'private', 'trash'];
		if ( ! in_array( $post_status, $valid_statuses ) ) {
			\WP_CLI::error( sprintf( 'Invalid post status: %s. Valid statuses: %s', $post_status, implode( ', ', $valid_statuses ) ) );
		}

		try {
			$query = new \WP_Query(
				[
					'post_type'              => 'post',
					'posts_per_page'         => $per_page,
					'offset'                 => $offset,
					'post_status'            => $post_status,
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				]
			);

			// Initialize counters
			$processed = 0;
			$updated   = 0;

			if ( $query->have_posts() ) {
				WP_CLI::log( sprintf( 'Processing %d posts starting from offset %d...', $query->post_count, $offset ) );

				while ( $query->have_posts() ) : $query->the_post();
					$processed++;
					$post_id = get_the_ID();
					$key     = 'long_description';
					// $meta    = \get_field( $key, $post_id );
					$meta    = \get_post_meta( $post_id, $key, true );

					if ( ! $meta ) {
						// WP_CLI::log( "Post ID: {$post_id} - {$key} is empty" );
						continue;
					}

					WP_CLI::log( "Post ID: {$post_id}" );
					WP_CLI::log( "{$key}: " . print_r( $meta, true ) );

					$updated++;
				endwhile;
			}
			wp_reset_postdata();

			WP_CLI::log( sprintf( 'Processed %d posts, updated %d', $processed, $updated ) );
		} catch ( \Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}
	}
}