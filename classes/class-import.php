<?php

namespace Ampers\PRXImport;

/**
 * PRX Content Import Handler.
 *
 * Handles importing PRX stories, series, and related content into WordPress.
 *
 * @since 2.0.0
 */
class Import {

	/**
	 * Auth instance.
	 *
	 * @since 2.0.0
	 *
	 * @var Auth
	 */
	private $auth;

	/**
	 * Import options.
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	private $options = [
		'dry_run' => false,
	];

	/**
	 * Logger instance.
	 *
	 * @since 2.0.0
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * ACF field keys (from old code).
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	private $acf_keys = [
		'prx_id'       => 'field_57a3a542d7360',
		'series_id'    => 'field_57d703d134c8f',
		'duration'     => 'field_57d6f183900d3',
		'audio'        => 'field_579b5d6e65222',
		'audio_mp3'    => 'field_579b5d9265223',
		'audio_label'  => 'field_57d7503068f70',
		'audio_length' => 'field_57c476f75d6e0',
		'transcript'   => 'field_57d6f1271aae3',
	];

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param Auth $auth Authentication instance.
	 * @param array $args Import options.
	 */
	public function __construct( Auth $auth, $args = [] ) {
		$this->auth    = $auth;
		$this->options = array_merge( $this->options, $args );
		$this->logger  = Logger::get_instance();
	}

	/**
	 * Get stories from a specific account.
	 *
	 * @since 2.0.0
	 *
	 * @param $args The arguments to pass to the API.
	 *
	 * @return array|WP_Error Stories data on success, WP_Error on failure
	 */
	public function get_account_stories( $args = [] ) {
		$args = wp_parse_args( $args, [
			'account_id' => account_id(),
			'page'       => 1,
			'per_page'   => 10,
		] );

		$endpoint     = "/authorization/accounts/{$args['account_id']}/stories";
		$request_args = [
			'method' => 'GET',
			'body'   => [
				'page' => $args['page'],
				'per'  => $args['per_page'],
			],
		];

		return $this->auth->make_request( $endpoint, $request_args );
	}

	/**
	 * Import a single story.
	 *
	 * @since 2.0.0
	 *
	 * @param array $story_data Story data from PRX API.
	 *
	 * @return int|WP_Error Post ID on success, WP_Error on failure
	 */
	public function import_story( $story_data ) {

		try {
			// Start the post data.
			$post_data = [
				'post_title'        => $story_data['title'],
				'post_date_gmt'     => $story_data['publishedAt'],
				'post_modified_gmt' => $story_data['updatedAt'],
				'tags_input'        => $story_data['tags'] ?? [],
				'post_status'       => 'publish',
			];

			// If we only have a short description or a description, that should be post_content.
			// If we have both, we need to check if they are the same.
			// If not the same, short desc is excerpt and description is content.
			// If they are the same, only set post_content with the description.
			// If we only have one, set it as post_content.
			if ( $story_data['shortDescription'] && $story_data['description'] ) {
				if ( $story_data['shortDescription'] === $story_data['description'] ) {
					$post_data['post_content'] = $story_data['description'];
				} else {
					$post_data['post_excerpt'] = $story_data['shortDescription'];
					$post_data['post_content'] = $story_data['description'];
				}
			} else {
				$post_data['post_content'] = $story_data['shortDescription'] ?? $story_data['description'];
			}

			// Check for existing post.
			$existing_post = $this->get_post_by_prx_id( $story_data['id'] );
			$post_id       = 0;
			$action_text   = 'Imported';

			// If the post already exists.
			if ( $existing_post ) {
				if ( $this->options['dry_run'] ) {
					$this->logger->info( "DRY RUN: Found existing post ID {$existing_post->ID} for PRX story {$story_data['id']} - would update" );
					$post_id = $existing_post->ID;
				} else {
					$post_data['ID'] = $existing_post->ID;
					$action_text = 'Updated';
				}
			} else {
				if ( $this->options['dry_run'] ) {
					$this->logger->info( "DRY RUN: Would create new post for PRX story {$story_data['id']} - '{$story_data['title']}'" );
				}
			}

			// If not a dry run, update the post.
			if ( ! $this->options['dry_run'] ) {
				$post_id = \wp_update_post( $post_data );

				if ( ! \is_wp_error( $post_id ) ) {
					$post_url = \get_permalink( $post_id );
					$this->logger->success( "{$action_text} existing post for PRX story {$story_data['id']}" );
					$this->logger->info( "  Post URL: {$post_url}" );
					$this->log_stored_data( $story_data, $post_id );
				}
			}

			// Set ACF fields.
			$this->set_acf_fields( $post_id, $story_data );

			// Set category to series.
			if ( ! empty( $story_data['_embedded']['prx:series']['title'] ) ) {
				if ( $this->options['dry_run'] ) {
					$this->logger->info( "DRY RUN: Would set category to {$story_data['_embedded']['prx:series']['title']}" );
				} else {
					wp_set_object_terms( $post_id, $story_data['_embedded']['prx:series']['title'], 'category' );
				}
			}

			// Set station taxonomy.
			if ( ! empty( $story_data['_embedded']['prx:account']['shortName'] ) ) {
				if ( $this->options['dry_run'] ) {
					$this->logger->info( "DRY RUN: Would set station to {$story_data['_embedded']['prx:account']['shortName']}" );
				} else {
					wp_set_object_terms( $post_id, $story_data['_embedded']['prx:account']['shortName'], 'stations' );
				}
			}

			// Import image.
			$this->import_story_image( $post_id, $story_data );

			// Import audio files.
			$this->import_story_audio( $post_id, $story_data );

			return $post_id;

		} catch ( \Exception $e ) {
			return new \WP_Error( 'import_failed', $e->getMessage() );
		}
	}

	/**
	 * Get WordPress post by PRX ID.
	 *
	 * @since 2.0.0
	 *
	 * @param int $prx_id PRX story ID.
	 *
	 * @return WP_Post|null Post object if found, null otherwise.
	 */
	public function get_post_by_prx_id( $prx_id ) {
		$posts = get_posts( [
			'numberposts' => 1,
			'post_type'   => 'post',
			'meta_key'    => 'prx_id',
			'meta_value'  => $prx_id,
		] );

		return $posts[0] ?? null;
	}

	/**
	 * Set ACF fields for a story.
	 *
	 * @since 2.0.0
	 *
	 * @param int   $post_id    Post ID.
	 * @param array $story_data Story data from PRX API.
	 *
	 * @return void
	 */
	private function set_acf_fields( $post_id, $story_data ) {
		if ( $this->options['dry_run'] ) {
			$this->logger->info( "DRY RUN: Would set ACF fields for post {$post_id}" );
			$this->logger->info( "  PRX ID: {$story_data['id']}" );
			$this->logger->info( "  Duration: {$story_data['duration']} seconds" );
			$this->logger->info( "  Series: {$story_data['_embedded']['prx:series']['title']}" );
			$this->logger->info( "  Station: {$story_data['_embedded']['prx:account']['shortName']}" );
			return;
		}

		\update_field( $this->acf_keys['prx_id'], $story_data['id'], $post_id );
		\update_field( $this->acf_keys['duration'], $story_data['duration'], $post_id );
		\update_field( $this->acf_keys['series_id'], $story_data['_embedded']['prx:series']['id'], $post_id );
		\update_field( $this->acf_keys['series'], $story_data['_embedded']['prx:series']['title'], $post_id );
		\update_field( $this->acf_keys['station'], $story_data['_embedded']['prx:account']['shortName'], $post_id );
		\update_field( $this->acf_keys['transcript'], $story_data['transcript'] ?? '', $post_id );
	}

	/**
	 * Import story image.
	 *
	 * @since 2.0.0
	 *
	 * @param int   $post_id    Post ID.
	 * @param array $story_data Story data from PRX API.
	 *
	 * @return void
	 */
	private function import_story_image( $post_id, $story_data ) {
		$img_url     = '';
		$img_caption = '';
		$img_credit  = '';

		// Get image URL from story or series
		if ( ! empty( $story_data['_embedded']['prx:image']['_links']['original']['href'] ) ) {
			$img_url     = $story_data['_embedded']['prx:image']['_links']['original']['href'];
			$img_caption = $story_data['_embedded']['prx:image']['caption'] ?? '';
			$img_credit  = $story_data['_embedded']['prx:image']['credit'] ?? '';
		} elseif ( ! empty( $story_data['_embedded']['prx:series']['_embedded']['prx:image']['_links']['enclosure']['href'] ) ) {
			$img_url = $story_data['_embedded']['prx:series']['_embedded']['prx:image']['_links']['enclosure']['href'];
		}

		if ( $img_url ) {
			// Check if image already exists in media library
			$existing_image = $this->get_media_by_url( $img_url );

			if ( $existing_image ) {
				$img_id = $existing_image->ID;
				if ( $this->options['dry_run'] ) {
					$this->logger->info( "DRY RUN: Found existing image ID {$img_id} for URL: {$img_url}" );
					$this->logger->info( "DRY RUN: Would set image ID {$img_id} as featured image" );
				}
			} else {
				if ( $this->options['dry_run'] ) {
					$this->logger->info( "DRY RUN: Would download new image for URL: {$img_url}" );
					$this->logger->info( "DRY RUN: Would set new image as featured image" );
				} else {
					$img_title = 'IMG: ' . $story_data['title'] . ' - prx_id:' . $story_data['id'] . ', series:' . $story_data['_embedded']['prx:series']['title'] . ', station:' . $story_data['_embedded']['prx:account']['shortName'];
					$img_id    = $this->download_to_media_library( $img_url, $img_title, $post_id );
				}
			}

			if ( ! $this->options['dry_run'] && ! is_wp_error( $img_id ) && $img_id ) {
				// Set as featured image.
				\set_post_thumbnail( $post_id, $img_id );

				// Start post args.
				$img_args = [
					'ID' => $img_id,
				];

				if ( ! empty( $img_caption ) ) {
					$img_args['post_excerpt'] = $img_caption;
				}

				if ( ! empty( $img_credit ) ) {
					$img_args['meta_input'] = [
						'media_credit' => $img_credit,
					];
				}

				if ( ! empty( $img_args ) || ! empty( $img_args['meta_input'] ) ) {
					\wp_update_post( $img_args );
				}
			}
		}
	}

	/**
	 * Import story audio files.
	 *
	 * @since 2.0.0
	 *
	 * @param int   $post_id    Post ID.
	 * @param array $story_data Story data from PRX API.
	 *
	 * @return void
	 */
	private function import_story_audio( $post_id, $story_data ) {
		if ( empty( $story_data['_embedded']['prx:audio']['_embedded']['prx:items'] ) ) {
			return;
		}

		$field_key   = $this->acf_keys['audio'];
		$audio_array = [];

		foreach ( $story_data['_embedded']['prx:audio']['_embedded']['prx:items'] as $audio ) {
			$audio_url   = $audio['_links']['enclosure']['href'];

			// Check if audio already exists in media library
			$existing_audio = $this->get_media_by_url( $audio_url );

			if ( $existing_audio ) {
				$audio_id = $existing_audio->ID;
				if ( $this->options['dry_run'] ) {
					$this->logger->info( "DRY RUN: Found existing audio ID {$audio_id} for URL: {$audio_url}" );
				}
			} else {
				if ( $this->options['dry_run'] ) {
					$this->logger->info( "DRY RUN: Would download new audio for URL: {$audio_url}" );
				} else {
					$audio_title = 'MP3: ' . $story_data['title'] . ' - prx_id:' . $story_data['id'] . ', series:' . $story_data['_embedded']['prx:series']['title'] . ', station:' . $story_data['_embedded']['prx:account']['shortName'];
					$audio_id    = $this->download_to_media_library( $audio_url, $audio_title, $post_id );
				}
			}

			if ( ! $this->options['dry_run'] && ! is_wp_error( $audio_id ) && $audio_id ) {
				$audio_array[] = [
					$this->acf_keys['audio_mp3']    => $audio_id,
					$this->acf_keys['audio_label']  => $audio['label'],
					$this->acf_keys['audio_length'] => $audio['duration'],
				];
			}
		}

		if ( ! $this->options['dry_run'] ) {
			// Clear existing audio array and set new one.
			\update_field( $field_key, $audio_array, $post_id );
		}
	}

	/**
	 * Download file to media library.
	 *
	 * @since 2.0.0
	 *
	 * @param string $url     File URL.
	 * @param string $title   File title.
	 * @param int    $post_id Post ID to attach to.
	 *
	 * @return int|WP_Error Media ID on success, WP_Error on failure.
	 */
	private function download_to_media_library( $url, $title, $post_id ) {
		if ( empty( $url ) ) {
			return false;
		}

		// Make sure we have the functions we need.
		if ( ! function_exists( 'download_url' ) || ! function_exists( 'media_handle_sideload' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/media.php' );
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
			require_once( ABSPATH . 'wp-admin/includes/image.php' );
		}

		// Build a temp url.
		$tmp = download_url( $url );

		// Bail if error.
		if ( is_wp_error( $tmp ) ) {
			// Remove the original image and return the error.
			@unlink( $tmp );
			return $tmp;
		}

		// Build the file array.
		$file_array = [
			'name'     => basename( parse_url( $url, PHP_URL_PATH ) ),
			'tmp_name' => $tmp,
		];

		// Add the image to the media library.
		$id = media_handle_sideload( $file_array, $post_id, $title );

		// Bail if error.
		if ( is_wp_error( $id ) ) {
			// Remove the original image and return the error.
			@unlink( $file_array[ 'tmp_name' ] );
			return $id;
		}

		// Clean up the temporary file.
		if ( file_exists( $tmp ) ) {
			unlink( $tmp );
		}

		// Store the original URL for future reference
		if ( ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_prx_original_url', $url );
		}

		return $id;
	}

	/**
	 * Get media attachment by URL.
	 *
	 * @since 2.0.0
	 *
	 * @param string $url Media URL.
	 *
	 * @return WP_Post|null Post object if found, null otherwise.
	 */
	private function get_media_by_url( $url ) {
		global $wpdb;

		// First, check by _prx_original_url meta.
		$attachment = $wpdb->get_row( $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = 'attachment'
			AND pm.meta_key = '_prx_original_url'
			AND pm.meta_value = %s",
			$url
		) );

		if ( $attachment ) {
			return get_post( $attachment->ID );
		}

		// Fallback: check by exact filename.
		$filename    = basename( parse_url( $url, PHP_URL_PATH ) );
		$attachment  = $wpdb->get_row( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts}
			WHERE post_type = 'attachment'
			AND guid LIKE %s",
			'%' . $wpdb->esc_like( $filename ) . '%'
		) );

		if ( $attachment ) {
			$post = get_post( $attachment->ID );
			// Update with _prx_original_url meta for future imports.
			update_post_meta( $post->ID, '_prx_original_url', $url );
			return $post;
		}

		return null;
	}

	/**
	 * Log stored story data in a formatted way.
	 *
	 * @since 2.0.0
	 *
	 * @param array $story_data Story data from PRX API.
	 * @param int   $post_id    Post ID.
	 *
	 * @return void
	 */
	private function log_stored_data( $story_data, $post_id = null ) {
		$log_data = [
			'prx_id'    => $story_data['id'],
			'title'     => $story_data['title'],
			'duration'  => $story_data['duration'] . ' seconds',
			'series'    => $story_data['_embedded']['prx:series']['title'],
			'station'   => $story_data['_embedded']['prx:account']['shortName'],
		];

		if ( $post_id ) {
			$log_data['post_id']        = $post_id;
			$featured_image_url         = \get_the_post_thumbnail_url( $post_id );
			$log_data['featured_image'] = $featured_image_url ?: 'None';
		}

		if ( ! empty( $story_data['description'] ) ) {
			$log_data['description'] = strip_tags( $story_data['description'] );
		}

		if ( ! empty( $story_data['transcript'] ) ) {
			$log_data['transcript'] = substr( $story_data['transcript'], 0, 100 ) . '...';
		}

		$this->logger->info( 'Story data: ' . json_encode( $log_data, JSON_PRETTY_PRINT ) );
	}
}