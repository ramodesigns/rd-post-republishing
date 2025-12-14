<?php

declare(strict_types=1);

namespace WPR\Republisher\Api;

use WPR\Republisher\Database\Repository;

/**
 * API Rate Limiter
 *
 * Provides rate limiting functionality for the REST API endpoints.
 * Supports both IP-based and user-based rate limiting with configurable windows.
 *
 * @link       https://www.paulramotowski.com
 * @since      1.0.0
 *
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Api
 */

/**
 * RateLimiter class.
 *
 * Implements sliding window rate limiting for API requests.
 *
 * @since      1.0.0
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Api
 * @author     Paul Ramotowski <paulramotowski@gmail.com>
 */
class RateLimiter {

	/**
	 * Default rate limit window in seconds (1 day).
	 *
	 * @since    1.0.0
	 */
	public const DEFAULT_WINDOW = 86400;

	/**
	 * Minimum rate limit window in seconds (1 second for testing).
	 *
	 * @since    1.0.0
	 */
	public const MIN_WINDOW = 1;

	/**
	 * Maximum requests per window (for non-configured limits).
	 *
	 * @since    1.0.0
	 */
	public const DEFAULT_MAX_REQUESTS = 1;

	/**
	 * Repository instance.
	 *
	 * @since    1.0.0
	 * @var      Repository
	 */
	private Repository $repository;

	/**
	 * Rate limit window in seconds.
	 *
	 * @since    1.0.0
	 * @var      int
	 */
	private int $window_seconds;

	/**
	 * Maximum requests allowed per window.
	 *
	 * @since    1.0.0
	 * @var      int
	 */
	private int $max_requests;

	/**
	 * Initialize the rate limiter.
	 *
	 * @since    1.0.0
	 * @param    Repository|null $repository   Optional repository instance.
	 * @param    int|null        $window       Optional rate limit window in seconds.
	 * @param    int             $max_requests Maximum requests per window.
	 */
	public function __construct(
		?Repository $repository = null,
		?int $window = null,
		int $max_requests = self::DEFAULT_MAX_REQUESTS
	) {
		$this->repository   = $repository ?? new Repository();
		$this->max_requests = max( 1, $max_requests );

		if ( null === $window ) {
			// Load from settings
			$settings             = $this->repository->get_settings();
			$this->window_seconds = max(
				self::MIN_WINDOW,
				(int) ( $settings['api_rate_limit_seconds'] ?? self::DEFAULT_WINDOW )
			);
		} else {
			$this->window_seconds = max( self::MIN_WINDOW, $window );
		}
	}

	/**
	 * Check if a request should be rate limited.
	 *
	 * @since    1.0.0
	 * @param    string   $endpoint  The API endpoint being accessed.
	 * @param    int|null $user_id   The authenticated user ID, if any.
	 * @return   bool  True if rate limited (request should be denied).
	 */
	public function is_limited( string $endpoint, ?int $user_id = null ): bool {
		return $this->repository->is_rate_limited(
			$endpoint,
			$user_id,
			$this->window_seconds
		);
	}

	/**
	 * Record an API request.
	 *
	 * Should be called after successfully processing a request.
	 *
	 * @since    1.0.0
	 * @param    string   $endpoint       The API endpoint.
	 * @param    int      $response_code  The HTTP response code.
	 * @param    int|null $user_id        The authenticated user ID.
	 * @return   int|false  Log entry ID or false on failure.
	 */
	public function record_request( string $endpoint, int $response_code, ?int $user_id = null ): int|false {
		return $this->repository->log_api_request( $endpoint, $response_code, $user_id );
	}

	/**
	 * Get rate limit status for an endpoint.
	 *
	 * @since    1.0.0
	 * @param    string   $endpoint  The API endpoint.
	 * @param    int|null $user_id   The authenticated user ID.
	 * @return   array<string, mixed>  Rate limit status information.
	 */
	public function get_status( string $endpoint, ?int $user_id = null ): array {
		$is_limited   = $this->is_limited( $endpoint, $user_id );
		$next_allowed = $this->get_next_allowed_time( $endpoint, $user_id );

		return [
			'limited'        => $is_limited,
			'window_seconds' => $this->window_seconds,
			'max_requests'   => $this->max_requests,
			'next_allowed'   => $next_allowed,
			'retry_after'    => $is_limited ? max( 0, $next_allowed - time() ) : 0,
		];
	}

	/**
	 * Get the timestamp when the next request will be allowed.
	 *
	 * @since    1.0.0
	 * @param    string   $endpoint  The API endpoint.
	 * @param    int|null $user_id   The authenticated user ID.
	 * @return   int  Unix timestamp.
	 */
	public function get_next_allowed_time( string $endpoint, ?int $user_id = null ): int {
		$last_request = $this->get_last_request_time( $endpoint, $user_id );

		if ( 0 === $last_request ) {
			return time(); // No previous request, allowed now
		}

		return $last_request + $this->window_seconds;
	}

	/**
	 * Get the timestamp of the last successful request.
	 *
	 * @since    1.0.0
	 * @param    string   $endpoint  The API endpoint.
	 * @param    int|null $user_id   The authenticated user ID.
	 * @return   int  Unix timestamp or 0 if no previous request.
	 */
	private function get_last_request_time( string $endpoint, ?int $user_id = null ): int {
		global $wpdb;

		$table      = $wpdb->prefix . 'wpr_api_log';
		$ip_address = $this->get_client_ip();

		if ( null !== $user_id && $user_id > 0 ) {
			$query = $wpdb->prepare(
				"SELECT UNIX_TIMESTAMP(request_timestamp) FROM {$table}
				WHERE user_id = %d
				AND endpoint = %s
				AND response_code = 200
				ORDER BY request_timestamp DESC
				LIMIT 1",
				$user_id,
				$endpoint
			);
		} else {
			$query = $wpdb->prepare(
				"SELECT UNIX_TIMESTAMP(request_timestamp) FROM {$table}
				WHERE ip_address = %s
				AND endpoint = %s
				AND response_code = 200
				ORDER BY request_timestamp DESC
				LIMIT 1",
				$ip_address,
				$endpoint
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Rate limit checks require fresh data.
		$result = $wpdb->get_var( $query );

		return null === $result ? 0 : (int) $result;
	}

	/**
	 * Get HTTP headers for rate limit response.
	 *
	 * @since    1.0.0
	 * @param    string   $endpoint  The API endpoint.
	 * @param    int|null $user_id   The authenticated user ID.
	 * @return   array<string, string>  Headers to include in response.
	 */
	public function get_rate_limit_headers( string $endpoint, ?int $user_id = null ): array {
		$status = $this->get_status( $endpoint, $user_id );

		return [
			'X-RateLimit-Limit'     => (string) $this->max_requests,
			'X-RateLimit-Remaining' => $status['limited'] ? '0' : '1',
			'X-RateLimit-Reset'     => (string) $status['next_allowed'],
		];
	}

	/**
	 * Create a rate limited error response.
	 *
	 * @since    1.0.0
	 * @param    string   $endpoint  The API endpoint.
	 * @param    int|null $user_id   The authenticated user ID.
	 * @return   \WP_Error  Error object for rate limited response.
	 */
	public function create_rate_limit_error( string $endpoint, ?int $user_id = null ): \WP_Error {
		$status = $this->get_status( $endpoint, $user_id );

		return new \WP_Error(
			'rate_limit_exceeded',
			sprintf(
				/* translators: %d: number of seconds until rate limit resets */
				__( 'Rate limit exceeded. Please try again in %d seconds.', 'rd-post-republishing' ),
				$status['retry_after']
			),
			[
				'status'      => 429,
				'retry_after' => $status['retry_after'],
			]
		);
	}

	/**
	 * Get the current rate limit window in seconds.
	 *
	 * @since    1.0.0
	 * @return   int  Window in seconds.
	 */
	public function get_window(): int {
		return $this->window_seconds;
	}

	/**
	 * Set the rate limit window.
	 *
	 * @since    1.0.0
	 * @param    int $seconds  Window in seconds.
	 */
	public function set_window( int $seconds ): void {
		$this->window_seconds = max( self::MIN_WINDOW, $seconds );
	}

	/**
	 * Check if debug/testing mode allows bypassing rate limits.
	 *
	 * @since    1.0.0
	 * @return   bool  True if rate limiting should be bypassed.
	 */
	public function should_bypass(): bool {
		$settings = $this->repository->get_settings();

		// Allow bypass in debug mode with minimum rate limit
		if ( ! empty( $settings['debug_mode'] ) && $this->window_seconds <= 1 ) {
			return true;
		}

		return false;
	}

	/**
	 * Get client IP address.
	 *
	 * @since    1.0.0
	 * @return   string  Client IP address.
	 */
	private function get_client_ip(): string {
		$ip_keys = [
			'HTTP_CF_CONNECTING_IP', // Cloudflare
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		];

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// Handle comma-separated IPs
				if ( str_contains( $ip, ',' ) ) {
					$ips = explode( ',', $ip );
					$ip  = trim( $ips[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}
}
