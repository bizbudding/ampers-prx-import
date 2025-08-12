<?php

namespace Ampers\PRXImport;

/**
 * PRX Cron Job Handler
 *
 * Handles the cron job for the PRX Import plugin.
 *
 * @since 2.0.0
 */
class Cron {

	/**
	 * Default interval in hours for checking new stories.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	private $interval_hours = 3;

	/**
	 * Number of stories to check per cron run.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	private $stories_per_run = 50;

	/**
	 * Account ID to check for new stories.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	private $account_id;

	/**
	 * Logger instance.
	 *
	 * @since 2.0.0
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param int $account_id Account ID to check for new stories.
	 * @param int $interval_hours Interval in hours (default: 3).
	 */
	public function __construct( $account_id, $interval_hours = 3 ) {
		$this->account_id    = $account_id;
		$this->interval_hours = $interval_hours;
		$this->logger        = Logger::get_instance();

		// Hook into WordPress cron
		add_action( 'init', [ $this, 'schedule_cron' ] );
		add_action( 'prx_import_check_new_stories', [ $this, 'check_new_stories' ] );

		// Handle plugin deactivation
		register_deactivation_hook( __FILE__, [ $this, 'clear_schedule' ] );
	}

	/**
	 * Schedule the cron job.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function schedule_cron() {
		if ( ! wp_next_scheduled( 'prx_import_check_new_stories' ) ) {
			wp_schedule_event( time(), 'custom_interval', 'prx_import_check_new_stories' );
		}

		// Add custom cron interval
		add_filter( 'cron_schedules', [ $this, 'add_cron_interval' ] );
	}

	/**
	 * Add custom cron interval.
	 *
	 * @since 2.0.0
	 *
	 * @param array $schedules Existing cron schedules.
	 *
	 * @return array Modified cron schedules.
	 */
	public function add_cron_interval( $schedules ) {
		$schedules['custom_interval'] = [
			'interval' => $this->interval_hours * HOUR_IN_SECONDS,
			'display'  => sprintf( __( 'Every %d hours', 'prx-import' ), $this->interval_hours ),
		];

		return $schedules;
	}

	/**
	 * Clear the cron schedule.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function clear_schedule() {
		wp_clear_scheduled_hook( 'prx_import_check_new_stories' );
	}

	/**
	 * Check for new stories from the PRX API.
	 *
	 * @since 2.0.0
	 *
	 * @return array|WP_Error Results array on success, WP_Error on failure.
	 */
	public function check_new_stories() {
		$results = [
			'new_stories' => 0,
			'updated_stories' => 0,
			'errors' => [],
			'last_run' => current_time( 'mysql' ),
		];

		try {
			// Initialize auth and import (like in CLI)
			$auth = new Auth();
			$import = new Import( $auth );

			// Get stories from the API (most recent stories)
			$stories_response = $import->get_account_stories( $this->account_id, 1, $this->stories_per_run );

			if ( is_wp_error( $stories_response ) ) {
				$results['errors'][] = 'Failed to fetch stories from PRX API: ' . $stories_response->get_error_message();
				$this->logger->error( 'Failed to fetch stories from PRX API: ' . $stories_response->get_error_message() );
				return $results;
			}

			// Check if we have stories
			if ( empty( $stories_response['_embedded']['prx:stories'] ) ) {
				$this->logger->info( 'No stories found in PRX API response' );
				return $results;
			}

			$stories = $stories_response['_embedded']['prx:stories'];
			$new_stories_count = 0;
			$updated_stories_count = 0;

			foreach ( $stories as $story ) {
				$import_result = $import->import_story( $story );

				if ( ! is_wp_error( $import_result ) ) {
					// Check if this was a new story or update by looking at the existing post
					$existing_post = $import->get_post_by_prx_id( $story['id'] );
					if ( $existing_post && $existing_post->ID == $import_result ) {
						$updated_stories_count++;
						$this->logger->success( "Updated story: {$story['title']} (PRX ID: {$story['id']})" );
					} else {
						$new_stories_count++;
						$this->logger->success( "Imported new story: {$story['title']} (PRX ID: {$story['id']})" );
					}
				} else {
					$results['errors'][] = "Failed to import story {$story['id']}: " . $import_result->get_error_message();
					$this->logger->error( "Failed to import story {$story['id']}: " . $import_result->get_error_message() );
				}
			}

			$results['new_stories'] = $new_stories_count;
			$results['updated_stories'] = $updated_stories_count;

			// Log summary
			$this->logger->info( "Cron job completed: {$new_stories_count} new stories, {$updated_stories_count} updated stories" );

		} catch ( \Exception $e ) {
			$error_message = 'Cron job failed with exception: ' . $e->getMessage();
			$results['errors'][] = $error_message;
			$this->logger->error( $error_message );
		}

		return $results;
	}




}
