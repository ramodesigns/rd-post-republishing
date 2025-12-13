<?php

declare(strict_types=1);

namespace WPR\Republisher\Logger;

use WPR\Republisher\Database\Repository;

/**
 * Centralized logging utility
 *
 * Provides a unified interface for logging debug messages, errors,
 * and informational messages throughout the plugin.
 *
 * @link       https://www.paulramotowski.com
 * @since      1.0.0
 *
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Logger
 */

/**
 * Logger class.
 *
 * Handles all logging operations with support for multiple log levels
 * and conditional logging based on debug mode settings.
 *
 * @since      1.0.0
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Logger
 * @author     Paul Ramotowski <paulramotowski@gmail.com>
 */
class Logger {

	/**
	 * Log level constants.
	 *
	 * @since    1.0.0
	 */
	public const LEVEL_DEBUG   = 'debug';
	public const LEVEL_INFO    = 'info';
	public const LEVEL_WARNING = 'warning';
	public const LEVEL_ERROR   = 'error';

	/**
	 * Log prefix for identifying plugin logs.
	 *
	 * @since    1.0.0
	 */
	private const LOG_PREFIX = '[WPR]';

	/**
	 * Repository instance.
	 *
	 * @since    1.0.0
	 */
	private Repository $repository;

	/**
	 * Whether debug mode is enabled.
	 *
	 * @since    1.0.0
	 */
	private bool $debug_mode;

	/**
	 * Singleton instance.
	 *
	 * @since    1.0.0
	 */
	private static ?Logger $instance = null;

	/**
	 * Initialize the logger.
	 *
	 * @since    1.0.0
	 * @param    Repository|null $repository  Optional repository instance.
	 */
	public function __construct( ?Repository $repository = null ) {
		$this->repository = $repository ?? new Repository();
		$settings         = $this->repository->get_settings();
		$this->debug_mode = ! empty( $settings['debug_mode'] );
	}

	/**
	 * Get singleton instance.
	 *
	 * @since    1.0.0
	 * @param    Repository|null $repository  Optional repository instance.
	 */
	public static function get_instance( ?Repository $repository = null ): self {
		if ( null === self::$instance ) {
			self::$instance = new self( $repository );
		}
		return self::$instance;
	}

	/**
	 * Reset the singleton instance (for testing).
	 *
	 * @since    1.0.0
	 */
	public static function reset(): void {
		self::$instance = null;
	}

	/**
	 * Log a debug message.
	 *
	 * Only logs when debug mode is enabled and WP_DEBUG_LOG is true.
	 *
	 * @since    1.0.0
	 * @param    string               $message  The message to log.
	 * @param    array<string, mixed> $context  Additional context data.
	 */
	public function debug( string $message, array $context = [] ): void {
		if ( $this->debug_mode ) {
			$this->log( self::LEVEL_DEBUG, $message, $context );
		}
	}

	/**
	 * Log an info message.
	 *
	 * @since    1.0.0
	 * @param    string               $message  The message to log.
	 * @param    array<string, mixed> $context  Additional context data.
	 */
	public function info( string $message, array $context = [] ): void {
		if ( $this->debug_mode ) {
			$this->log( self::LEVEL_INFO, $message, $context );
		}
	}

	/**
	 * Log a warning message.
	 *
	 * @since    1.0.0
	 * @param    string               $message  The message to log.
	 * @param    array<string, mixed> $context  Additional context data.
	 */
	public function warning( string $message, array $context = [] ): void {
		$this->log( self::LEVEL_WARNING, $message, $context );
	}

	/**
	 * Log an error message.
	 *
	 * @since    1.0.0
	 * @param    string               $message  The message to log.
	 * @param    array<string, mixed> $context  Additional context data.
	 */
	public function error( string $message, array $context = [] ): void {
		$this->log( self::LEVEL_ERROR, $message, $context );
	}

	/**
	 * Log a republishing event.
	 *
	 * @since    1.0.0
	 * @param    int    $post_id  The post ID.
	 * @param    string $action   The action performed.
	 * @param    string $status   The result status.
	 */
	public function republish_event( int $post_id, string $action, string $status ): void {
		$this->debug(
			sprintf( 'Post %d: %s - %s', $post_id, $action, $status ),
			[
				'post_id' => $post_id,
				'action'  => $action,
				'status'  => $status,
			]
		);
	}

	/**
	 * Log a cron event.
	 *
	 * @since    1.0.0
	 * @param    string               $event    The cron event name.
	 * @param    string               $message  The message.
	 * @param    array<string, mixed> $context  Additional context.
	 */
	public function cron_event( string $event, string $message, array $context = [] ): void {
		$this->debug(
			sprintf( 'Cron [%s]: %s', $event, $message ),
			array_merge( [ 'cron_event' => $event ], $context )
		);
	}

	/**
	 * Log an API event.
	 *
	 * @since    1.0.0
	 * @param    string               $endpoint  The API endpoint.
	 * @param    int                  $status    The HTTP status code.
	 * @param    array<string, mixed> $context   Additional context.
	 */
	public function api_event( string $endpoint, int $status, array $context = [] ): void {
		$this->debug(
			sprintf( 'API [%s]: HTTP %d', $endpoint, $status ),
			array_merge(
				[
					'endpoint'    => $endpoint,
					'http_status' => $status,
				],
				$context
			)
		);
	}

	/**
	 * Log a cache operation.
	 *
	 * @since    1.0.0
	 * @param    string               $operation  The cache operation.
	 * @param    int|null             $post_id    The post ID if applicable.
	 * @param    array<string, mixed> $results    Cache clearing results.
	 */
	public function cache_event( string $operation, ?int $post_id, array $results = [] ): void {
		$message = null !== $post_id
			? sprintf( 'Cache [%s] for post %d', $operation, $post_id )
			: sprintf( 'Cache [%s]', $operation );

		$this->debug(
			$message,
			array_merge(
				[
					'operation' => $operation,
					'post_id'   => $post_id,
				],
				$results
			)
		);
	}

	/**
	 * Log a database operation.
	 *
	 * @since    1.0.0
	 * @param    string               $operation  The database operation.
	 * @param    string               $table      The table name.
	 * @param    array<string, mixed> $context    Additional context.
	 */
	public function db_event( string $operation, string $table, array $context = [] ): void {
		$this->debug(
			sprintf( 'DB [%s]: %s', $operation, $table ),
			array_merge(
				[
					'operation' => $operation,
					'table'     => $table,
				],
				$context
			)
		);
	}

	/**
	 * Core logging method.
	 *
	 * @since    1.0.0
	 * @param    string               $level    Log level.
	 * @param    string               $message  The message to log.
	 * @param    array<string, mixed> $context  Additional context data.
	 */
	private function log( string $level, string $message, array $context = [] ): void {
		if ( ! $this->should_log() ) {
			return;
		}

		$formatted = $this->format_message( $level, $message, $context );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $formatted );

		/**
		 * Fires after a log entry is written.
		 *
		 * @since 1.0.0
		 * @param string $level   The log level.
		 * @param string $message The log message.
		 * @param array  $context Additional context data.
		 */
		do_action( 'wpr_log_entry', $level, $message, $context );
	}

	/**
	 * Check if logging should proceed.
	 *
	 * @since    1.0.0
	 */
	private function should_log(): bool {
		return defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
	}

	/**
	 * Format a log message.
	 *
	 * @since    1.0.0
	 * @param    string               $level    Log level.
	 * @param    string               $message  The message.
	 * @param    array<string, mixed> $context  Additional context.
	 */
	private function format_message( string $level, string $message, array $context = [] ): string {
		$timestamp   = wp_date( 'Y-m-d H:i:s' );
		$level_upper = strtoupper( $level );

		$formatted = sprintf(
			'%s %s [%s] %s',
			self::LOG_PREFIX,
			$timestamp,
			$level_upper,
			$message
		);

		if ( ! empty( $context ) ) {
			$formatted .= ' | Context: ' . wp_json_encode( $context, JSON_UNESCAPED_SLASHES );
		}

		return $formatted;
	}

	/**
	 * Check if debug mode is enabled.
	 *
	 * @since    1.0.0
	 */
	public function is_debug_mode(): bool {
		return $this->debug_mode;
	}

	/**
	 * Set debug mode.
	 *
	 * @since    1.0.0
	 * @param    bool $enabled  Whether to enable debug mode.
	 */
	public function set_debug_mode( bool $enabled ): void {
		$this->debug_mode = $enabled;
	}

	/**
	 * Get memory usage for logging.
	 *
	 * @since    1.0.0
	 */
	public function get_memory_usage(): string {
		$bytes = memory_get_usage( true );
		$units = [ 'B', 'KB', 'MB', 'GB' ];
		$index = 0;

		while ( $bytes >= 1024 && $index < count( $units ) - 1 ) {
			$bytes /= 1024;
			++$index;
		}

		return round( $bytes, 2 ) . ' ' . $units[ $index ];
	}

	/**
	 * Get peak memory usage for logging.
	 *
	 * @since    1.0.0
	 */
	public function get_peak_memory_usage(): string {
		$bytes = memory_get_peak_usage( true );
		$units = [ 'B', 'KB', 'MB', 'GB' ];
		$index = 0;

		while ( $bytes >= 1024 && $index < count( $units ) - 1 ) {
			$bytes /= 1024;
			++$index;
		}

		return round( $bytes, 2 ) . ' ' . $units[ $index ];
	}

	/**
	 * Start a timer for performance logging.
	 *
	 * @since    1.0.0
	 * @return   float  Start time.
	 */
	public function start_timer(): float {
		return microtime( true );
	}

	/**
	 * End a timer and log the duration.
	 *
	 * @since    1.0.0
	 * @param    float  $start_time  The start time from start_timer().
	 * @param    string $operation   Description of the operation.
	 */
	public function end_timer( float $start_time, string $operation ): float {
		$duration = microtime( true ) - $start_time;

		$this->debug(
			sprintf( 'Performance: %s completed in %.4f seconds', $operation, $duration ),
			[
				'operation' => $operation,
				'duration'  => $duration,
				'memory'    => $this->get_memory_usage(),
			]
		);

		return $duration;
	}
}
