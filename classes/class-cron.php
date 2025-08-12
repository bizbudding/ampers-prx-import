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
	 * Account ID to check for new stories.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	private $account_id;

	/**
	 * Number of stories to check per cron run.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	private $stories_per_run = 50;

	/**
	 * Default interval in hours for checking new stories.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	private $interval_hours = 3;

	/**
	 * Cron key for the cron job.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private $cron_key;

	/**
	 * Interval key for cron.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private $interval_key;

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
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args( $args, [
			'account_id'     => account_id(),
			'interval_hours' => 3,
		] );

		$this->account_id     = $args['account_id'];
		$this->interval_hours = $args['interval_hours'];
		$this->cron_key       = 'prx_import_check_new_stories';
		$this->interval_key   = 'prx_import_every_' . $this->interval_hours . '_hours';
		$this->logger         = Logger::get_instance();

		// Add custom cron interval.
		add_filter( 'cron_schedules', [ $this, 'add_cron_interval' ] );

		// Hook into WordPress cron.
		add_action( 'init',          [ $this, 'schedule_cron' ] );
		add_action( $this->cron_key, [ $this, 'check_new_stories' ] );

		// Handle plugin deactivation.
		register_deactivation_hook( __FILE__, [ $this, 'clear_schedule' ] );
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
		$schedules[ $this->interval_key ] = [
			'interval' => $this->interval_hours * HOUR_IN_SECONDS,
			'display'  => sprintf( __( 'Every %d hours', 'prx-import' ), $this->interval_hours ),
		];

		return $schedules;
	}

	/**
	 * Schedule the cron job.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function schedule_cron() {
		// Bail if the cron job is already scheduled.
		if ( wp_next_scheduled( $this->cron_key ) ) {
			return;
		}

		// Schedule the cron job.
		wp_schedule_event( time(), $this->interval_key, $this->cron_key );
	}

	/**
	 * Clear the cron schedule.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function clear_schedule() {
		wp_clear_scheduled_hook( $this->cron_key );
	}

	/**
	 * Check for new stories from the PRX API.
	 *
	 * @since 2.0.0
	 *
	 * @return array|WP_Error Results array on success, WP_Error on failure.
	 */
	public function check_new_stories() {
		$this->logger->info( "Cron job started" );

		$results = [
			'new_stories'     => 0,
			'updated_stories' => 0,
			'errors'          => [],
			'last_run'        => current_time( 'mysql' ),
		];

		try {
			// Initialize auth and import (like in CLI).
			$auth   = new Auth();
			$import = new Import( $auth );

			// Get stories from the API (most recent stories).
			$stories_response = $import->get_account_stories( [
				'account_id' => $this->account_id,
				'page'       => 1,
				'per_page'   => $this->stories_per_run,
			] );

			// Bail if there was an error.
			if ( is_wp_error( $stories_response ) ) {
				$results['errors'][] = 'Failed to fetch stories from PRX API: ' . $stories_response->get_error_message();
				$this->logger->error( 'Failed to fetch stories from PRX API: ' . $stories_response->get_error_message() );
				return $results;
			}

			// Get stories.
			$stories = $stories_response['_embedded']['prx:items'] ?? [];

			// Bail if there are no stories.
			if ( empty( $stories ) ) {
				$this->logger->info( 'No stories found in PRX API response' );
				return $results;
			}

			// Import stories.
			foreach ( $stories as $story ) {
				$import->import_story( $story );
			}

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
