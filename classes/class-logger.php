<?php

namespace Ampers\PRXImport;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * PRX Import Logger class.
 *
 * @version 0.1.0 Taken from Mai Performance Images plugin.
 *
 * @since 0.1.0
 */
class Logger {
	/**
	 * The singleton instance.
	 *
	 * @since 0.1.0
	 *
	 * @var Logger|null
	 */
	private static $instance = null;

	/**
	 * The plugin name.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Get the singleton instance.
	 *
	 * @since 0.1.0
	 *
	 * @return Logger
	 */
	public static function get_instance(): Logger {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function __construct() {}

	/**
	 * Log a message.
	 *
	 * @since 0.1.0
	 *
	 * @param string $message The message to log.
	 * @param string $type    The type of message (error, warning, info, success).
	 *
	 * @return void
	 */
	private function log( string $message, string $type = 'error' ): void {
		// Always log errors, for other types only log if debugging is enabled.
		if ( 'error' !== $type && ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) ) {
			return;
		}

		// Format the message.
		$formatted = sprintf( '%s [%s]: %s', $this->get_plugin_name(), strtoupper( $type ), $message );

		// If logging.
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// Log the message.
			error_log( $formatted );
		}

		// If displaying.
		if ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ) {
			// If ray is available, use it for additional debugging.
			if ( function_exists( '\ray' ) ) {
				/** @disregard P1010 */
				\ray( $formatted )->label( $this->get_plugin_name() );
			}
		}
	}

	/**
	 * Log an error message.
	 * Always logs regardless of debug settings.
	 *
	 * @since 0.1.0
	 *
	 * @param string $message The message to log.
	 *
	 * @return void
	 */
	public function error( string $message ): void {
		$this->log( $message, 'error' );
	}

	/**
	 * Log a warning message.
	 * Only logs if debugging is enabled.
	 *
	 * @since 0.1.0
	 *
	 * @param string $message The message to log.
	 *
	 * @return void
	 */
	public function warning( string $message ): void {
		$this->log( $message, 'warning' );
	}

	/**
	 * Log a success message.
	 * Only logs if debugging is enabled.
	 *
	 * @since 0.1.0
	 *
	 * @param string $message The message to log.
	 *
	 * @return void
	 */
	public function success( string $message ): void {
		$this->log( $message, 'success' );
	}

	/**
	 * Log an info message.
	 * Only logs if debugging is enabled.
	 *
	 * @since 0.1.0
	 *
	 * @param string $message The message to log.
	 *
	 * @return void
	 */
	public function info( string $message ): void {
		$this->log( $message, 'info' );
	}

	/**
	 * Get the plugin name.
	 *
	 * @since 0.1.0
	 *
	 * @param string $name The plugin name.
	 *
	 * @return string
	 */
	public function get_plugin_name(): string {
		if ( $this->plugin_name ) {
			return $this->plugin_name;
		}

		$this->plugin_name = plugin_basename( dirname( dirname( __FILE__ ) ) );

		return $this->plugin_name;
	}
}