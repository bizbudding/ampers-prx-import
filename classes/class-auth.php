<?php

namespace Ampers\PRXImport;

/**
 * PRX API Authentication Handler
 *
 * Handles OAuth2 client credentials flow for PRX API access.
 * Supports both staging and production environments.
 *
 * @since 2.0.0
 */
class Auth {

	/**
	 * Staging environment URLs
	 */
	const STAGING_ID_URL  = 'https://id.staging.prx.tech';
	const STAGING_CMS_URL = 'https://cms.staging.prx.tech/api/v1';

	/**
	 * Production environment URLs
	 */
	const PRODUCTION_ID_URL  = 'https://id.prx.org';
	const PRODUCTION_CMS_URL = 'https://cms.prx.org/api/v1';

	/**
	 * Current environment (staging or production)
	 *
	 * @var string
	 */
	private $environment;

	/**
	 * Client ID for OAuth2
	 *
	 * @var string
	 */
	private $client_id;

	/**
	 * Client Secret for OAuth2
	 *
	 * @var string
	 */
	private $client_secret;

	/**
	 * Current access token
	 *
	 * @var string|null
	 */
	private $access_token;

	/**
	 * Token expiration timestamp
	 *
	 * @var int|null
	 */
	private $token_expires_at;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param string $environment Environment to use ('staging' or 'production'). If empty, will use WP_ENVIRONMENT_TYPE.
	 */
	public function __construct( $environment = '' ) {
		$this->environment = $environment ?? $this->get_environment_from_wp();

		// Get credentials from constants if not provided
		$this->client_id     = $this->get_client_id();
		$this->client_secret = $this->get_client_secret();
	}

	/**
	 * Get the ID server URL for the current environment
	 *
	 * @since 2.0.0
	 *
	 * @return string The ID server URL for the current environment.
	 */
	public function get_id_url() {
		return $this->environment === 'staging' ? self::STAGING_ID_URL : self::PRODUCTION_ID_URL;
	}

	/**
	 * Get the CMS API URL for the current environment
	 *
	 * @return string
	 */
	public function get_cms_url() {
		return $this->environment === 'staging' ? self::STAGING_CMS_URL : self::PRODUCTION_CMS_URL;
	}

	/**
	 * Get an access token using OAuth2 client credentials flow
	 *
	 * @since 2.0.0
	 *
	 * @return string|WP_Error Access token on success, WP_Error on failure
	 */
	public function get_access_token() {
		// Check if we have a valid cached token
		if ( $this->has_valid_token() ) {
			return $this->access_token;
		}

		// Validate credentials
		$validation = $this->validate_credentials();
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$token_url = $this->get_id_url() . '/token';
		$post_data = [
			'grant_type'    => 'client_credentials',
			'client_id'     => $this->client_id,
			'client_secret' => $this->client_secret,
		];

		$response = wp_remote_post( $token_url, [
			'body'    => $post_data,
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'request_failed', 'Failed to request access token: ' . $response->get_error_message() );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data ) || ! isset( $data['access_token'] ) ) {
			return new \WP_Error( 'invalid_response', 'Invalid response from PRX authentication server: ' . $body );
		}

		// Store the token and set expiration (tokens typically last 1 hour)
		$this->access_token = $data['access_token'];
		$this->token_expires_at = time() + 3600; // 1 hour from now

		return $this->access_token;
	}

	/**
	 * Check if we have a valid cached token
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	private function has_valid_token() {
		return ! empty( $this->access_token ) &&
			   ! empty( $this->token_expires_at ) &&
			   $this->token_expires_at > time();
	}

	/**
	 * Make an authenticated request to the PRX CMS API
	 *
	 * @since 2.0.0
	 *
	 * @param string $endpoint API endpoint (e.g., '/authorization').
	 * @param array  $args     Request arguments.
	 *
	 * @return array|WP_Error Response data on success, WP_Error on failure
	 */
	public function make_request( $endpoint, $args = [] ) {
		$token = $this->get_access_token();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$url = $this->get_cms_url() . $endpoint;

		$defaults = [
			'method'  => 'GET',
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			],
			'timeout' => 30,
		];

		$args     = wp_parse_args( $args, $defaults );
		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'request_failed', 'Failed to make API request: ' . $response->get_error_message() );
		}

		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );
		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code >= 400 ) {
			return new \WP_Error( 'api_error', 'API request failed with status ' . $status_code . ': ' . $body );
		}

		return $data;
	}

	/**
	 * Test the authentication by making a simple API call
	 *
	 * @since 2.0.0
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function test_connection() {
		$response = $this->make_request( '/authorization' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Check if we got a valid authorization response
		if ( isset( $response['id'] ) && isset( $response['_links'] ) ) {
			return true;
		}

		return new \WP_Error( 'invalid_response', 'Unexpected response format from authorization endpoint' );
	}

	/**
	 * Get authorization information (available resources)
	 *
	 * @since 2.0.0
	 *
	 * @return array|WP_Error Authorization data on success, WP_Error on failure
	 */
	public function get_authorization() {
		return $this->make_request( '/authorization' );
	}

	/**
	 * Get environment from WordPress constants
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	private function get_environment_from_wp() {
		// if ( defined( 'WP_ENVIRONMENT_TYPE' ) ) {
		// 	$wp_env = \WP_ENVIRONMENT_TYPE;
		// 	// Map WordPress environment types to PRX environments
		// 	switch ( $wp_env ) {
		// 		case 'local':
		// 		case 'development':
		// 		case 'staging':
		// 			return 'staging';
		// 		case 'production':
		// 		default:
		// 			return 'production';
		// 	}
		// }
		return 'production'; // Default fallback
	}

	/**
	 * Get client ID from constant
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	private function get_client_id() {
		return defined( 'PRX_CLIENT_ID' ) ? \PRX_CLIENT_ID : '';
	}

	/**
	 * Get client secret from constant
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	private function get_client_secret() {
		return defined( 'PRX_CLIENT_SECRET' ) ? \PRX_CLIENT_SECRET : '';
	}

	/**
	 * Validate that required credentials are available
	 *
	 * @since 2.0.0
	 *
	 * @return bool|WP_Error True if valid, WP_Error if missing credentials
	 */
	public function validate_credentials() {
		if ( empty( $this->client_id ) || empty( $this->client_secret ) ) {
			return new \WP_Error( 'missing_credentials', 'PRX_CLIENT_ID and PRX_CLIENT_SECRET constants must be defined in wp-config.php' );
		}
		return true;
	}
}