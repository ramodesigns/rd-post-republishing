<?php

declare(strict_types=1);

namespace WPR\Republisher\Api;

use WPR\Republisher\Database\Repository;
use WPR\Republisher\Republisher\Engine;
use WPR\Republisher\Logger\Logger;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST API controller class
 *
 * Handles REST API endpoints for external republishing triggers.
 * Includes comprehensive error handling and rate limiting.
 *
 * @link       https://www.paulramotowski.com
 * @since      1.0.0
 *
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Api
 */

/**
 * REST API Controller.
 *
 * Provides the /wp-json/republish/v1/trigger endpoint for external
 * scheduling systems to trigger republishing.
 *
 * @since      1.0.0
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Api
 * @author     Paul Ramotowski <paulramotowski@gmail.com>
 */
class RestController {

	/**
	 * API namespace.
	 *
	 * @since    1.0.0
	 */
	public const NAMESPACE = 'republish/v1';

	/**
	 * Error codes.
	 *
	 * @since    1.0.0
	 */
	public const ERROR_UNAUTHORIZED = 'wpr_unauthorized';
	public const ERROR_FORBIDDEN = 'wpr_forbidden';
	public const ERROR_RATE_LIMITED = 'wpr_rate_limited';
	public const ERROR_INTERNAL = 'wpr_internal_error';
	public const ERROR_INVALID_PARAMS = 'wpr_invalid_params';
	public const ERROR_LOCKED = 'wpr_locked';

	/**
	 * Repository instance.
	 *
	 * @since    1.0.0
	 */
	private Repository $repository;

	/**
	 * Rate limiter instance.
	 *
	 * @since    1.0.0
	 */
	private RateLimiter $rate_limiter;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 */
	private Logger $logger;

	/**
	 * Initialize the controller.
	 *
	 * @since    1.0.0
	 * @param    Repository|null   $repository    Optional repository instance.
	 * @param    RateLimiter|null  $rate_limiter  Optional rate limiter instance.
	 * @param    Logger|null       $logger        Optional logger instance.
	 */
	public function __construct(
		?Repository $repository = null,
		?RateLimiter $rate_limiter = null,
		?Logger $logger = null
	) {
		$this->repository = $repository ?? new Repository();
		$this->rate_limiter = $rate_limiter ?? new RateLimiter( $this->repository );
		$this->logger = $logger ?? Logger::get_instance( $this->repository );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since    1.0.0
	 */
	public function register_routes(): void {
		// Main trigger endpoint
		register_rest_route(
			self::NAMESPACE,
			'/trigger',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_trigger' ],
				'permission_callback' => [ $this, 'check_permissions' ],
				'args'                => [
					'force' => [
						'type'              => 'boolean',
						'default'           => false,
						'description'       => __( 'Force republishing even if daily quota is met (debug mode only).', 'rd-post-republishing' ),
						'validate_callback' => [ $this, 'validate_boolean' ],
					],
				],
			]
		);

		// Status endpoint
		register_rest_route(
			self::NAMESPACE,
			'/status',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'handle_status' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			]
		);

		// Preview endpoint
		register_rest_route(
			self::NAMESPACE,
			'/preview',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'handle_preview' ],
				'permission_callback' => [ $this, 'check_permissions' ],
				'args'                => [
					'days' => [
						'type'              => 'integer',
						'default'           => 7,
						'minimum'           => 1,
						'maximum'           => 7,
						'description'       => __( 'Number of days to preview.', 'rd-post-republishing' ),
						'validate_callback' => [ $this, 'validate_days' ],
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		// Health check endpoint (no auth required)
		register_rest_route(
			self::NAMESPACE,
			'/health',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'handle_health' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * Validate boolean parameter.
	 *
	 * @since    1.0.0
	 * @param    mixed           $value    The parameter value.
	 * @param    WP_REST_Request $request  The request object.
	 * @param    string          $param    The parameter name.
	 */
	public function validate_boolean( mixed $value, WP_REST_Request $request, string $param ): bool {
		return is_bool( $value ) || in_array( $value, [ 'true', 'false', '1', '0', 1, 0 ], true );
	}

	/**
	 * Validate days parameter.
	 *
	 * @since    1.0.0
	 * @param    mixed           $value    The parameter value.
	 * @param    WP_REST_Request $request  The request object.
	 * @param    string          $param    The parameter name.
	 */
	public function validate_days( mixed $value, WP_REST_Request $request, string $param ): bool|WP_Error {
		$days = absint( $value );
		if ( $days < 1 || $days > 7 ) {
			return new WP_Error(
				self::ERROR_INVALID_PARAMS,
				__( 'Days parameter must be between 1 and 7.', 'rd-post-republishing' ),
				[ 'status' => 400 ]
			);
		}
		return true;
	}

	/**
	 * Check if the request has proper permissions.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request  $request  The request object.
	 * @return   bool|WP_Error
	 */
	public function check_permissions( WP_REST_Request $request ): bool|WP_Error {
		// Check if user is authenticated (Application Password or logged in)
		$current_user = wp_get_current_user();

		if ( ! $current_user->exists() ) {
			$this->log_api_request( $request, 401 );
			$this->logger->api_event( $request->get_route(), 401, [ 'reason' => 'not_authenticated' ] );

			return new WP_Error(
				self::ERROR_UNAUTHORIZED,
				__( 'Authentication required. Use WordPress Application Passwords.', 'rd-post-republishing' ),
				[
					'status' => 401,
					'hint'   => __( 'Generate an Application Password in Users > Profile.', 'rd-post-republishing' ),
				]
			);
		}

		// Check for required capability
		$required_cap = $this->get_required_capability();
		if ( ! user_can( $current_user, $required_cap ) ) {
			$this->log_api_request( $request, 403 );
			$this->logger->api_event( $request->get_route(), 403, [
				'reason'       => 'insufficient_capability',
				'user_id'      => $current_user->ID,
				'required_cap' => $required_cap,
			] );

			return new WP_Error(
				self::ERROR_FORBIDDEN,
				__( 'You do not have permission to access this endpoint.', 'rd-post-republishing' ),
				[
					'status'      => 403,
					'required'    => $required_cap,
				]
			);
		}

		return true;
	}

	/**
	 * Handle the trigger endpoint.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request  $request  The request object.
	 */
	public function handle_trigger( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$settings = $this->repository->get_settings();
			$user_id = get_current_user_id();
			$force = (bool) $request->get_param( 'force' );
			$endpoint = '/trigger';

			// Check rate limiting
			if ( $this->rate_limiter->is_limited( $endpoint, $user_id ) && ! $this->rate_limiter->should_bypass() ) {
				$this->log_api_request( $request, 429 );
				$status = $this->rate_limiter->get_status( $endpoint, $user_id );

				$response = new WP_REST_Response(
					[
						'success'     => false,
						'code'        => self::ERROR_RATE_LIMITED,
						'message'     => sprintf(
							/* translators: %d: seconds until rate limit resets */
							__( 'Rate limit exceeded. Please try again in %d seconds.', 'rd-post-republishing' ),
							$status['retry_after']
						),
						'retry_after' => $status['retry_after'],
					],
					429
				);

				// Add rate limit headers
				foreach ( $this->rate_limiter->get_rate_limit_headers( $endpoint, $user_id ) as $header => $value ) {
					$response->header( $header, $value );
				}
				$response->header( 'Retry-After', (string) $status['retry_after'] );

				return $response;
			}

			// Force parameter only works in debug mode
			if ( $force && empty( $settings['debug_mode'] ) ) {
				$this->logger->debug( 'Force parameter ignored (debug mode disabled)' );
				$force = false;
			}

			// Check for dry-run mode
			if ( ! empty( $settings['dry_run_mode'] ) ) {
				$this->log_api_request( $request, 200 );

				return $this->success_response( [
					'dry_run'   => true,
					'message'   => __( 'Dry-run mode is active. No posts were republished.', 'rd-post-republishing' ),
				] );
			}

			// Execute republishing
			$engine = new Engine( $this->repository );

			// Check if engine is locked
			if ( $engine->is_locked() ) {
				$lock_status = $engine->get_lock_status();
				$this->log_api_request( $request, 423 );

				return new WP_REST_Response(
					[
						'success'     => false,
						'code'        => self::ERROR_LOCKED,
						'message'     => __( 'Another republishing operation is in progress.', 'rd-post-republishing' ),
						'lock_status' => $lock_status,
					],
					423
				);
			}

			$result = $engine->execute_batch( 'api' );

			// Log successful API request
			$this->log_api_request( $request, 200 );
			$this->logger->api_event( $request->get_route(), 200, [
				'republished' => count( $result['republished'] ?? [] ),
				'failed'      => count( $result['failed'] ?? [] ),
			] );

			// Format response based on debug mode
			if ( ! empty( $settings['debug_mode'] ) ) {
				return $this->success_response( $result );
			}

			// Simple response for production
			return $this->success_response( [
				'success' => $result['success'],
				'message' => $result['message'] ?? null,
				'count'   => count( $result['republished'] ?? [] ),
			] );

		} catch ( \Throwable $e ) {
			$this->logger->error( 'API trigger error: ' . $e->getMessage(), [
				'exception' => get_class( $e ),
				'file'      => $e->getFile(),
				'line'      => $e->getLine(),
			] );

			$this->log_api_request( $request, 500 );

			return $this->error_response(
				self::ERROR_INTERNAL,
				__( 'An internal error occurred while processing your request.', 'rd-post-republishing' ),
				500,
				! empty( $settings['debug_mode'] ?? false ) ? [ 'error' => $e->getMessage() ] : []
			);
		}
	}

	/**
	 * Handle the status endpoint.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request  $request  The request object.
	 */
	public function handle_status( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$settings = $this->repository->get_settings();

			$status = [
				'success'            => true,
				'today_count'        => $this->repository->get_today_republish_count(),
				'quota_remaining'    => $this->get_remaining_quota(),
				'cron_enabled'       => ! empty( $settings['wp_cron_enabled'] ),
				'dry_run_mode'       => ! empty( $settings['dry_run_mode'] ),
				'debug_mode'         => ! empty( $settings['debug_mode'] ),
				'version'            => defined( 'WPR_VERSION' ) ? WPR_VERSION : '1.0.0',
			];

			// Add detailed info in debug mode
			if ( ! empty( $settings['debug_mode'] ) ) {
				$cron = new \WPR\Republisher\Scheduler\Cron( $this->repository );
				$status['cron_status'] = $cron->get_status();
				$status['settings'] = $this->sanitize_settings_for_output( $settings );
				$status['memory_usage'] = $this->logger->get_memory_usage();
			}

			$this->log_api_request( $request, 200 );

			return $this->success_response( $status );

		} catch ( \Throwable $e ) {
			$this->logger->error( 'API status error: ' . $e->getMessage() );
			$this->log_api_request( $request, 500 );

			return $this->error_response(
				self::ERROR_INTERNAL,
				__( 'Failed to retrieve status.', 'rd-post-republishing' ),
				500
			);
		}
	}

	/**
	 * Handle the preview endpoint.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request  $request  The request object.
	 */
	public function handle_preview( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$days = (int) $request->get_param( 'days' );
			$days = max( 1, min( 7, $days ) );

			$query = new \WPR\Republisher\Republisher\Query( $this->repository );
			$preview = $query->get_republishing_preview( $days );

			// Format preview for response
			$formatted = [];
			$total_posts = 0;

			foreach ( $preview as $date => $posts ) {
				$formatted[ $date ] = array_map(
					function ( $post ) {
						return [
							'ID'         => $post->ID,
							'title'      => $post->post_title,
							'post_type'  => $post->post_type,
							'post_date'  => $post->post_date,
							'age_days'   => $this->calculate_post_age( $post->post_date ),
						];
					},
					$posts
				);
				$total_posts += count( $posts );
			}

			$this->log_api_request( $request, 200 );

			return $this->success_response( [
				'success'     => true,
				'preview'     => $formatted,
				'total_posts' => $total_posts,
				'days'        => $days,
			] );

		} catch ( \Throwable $e ) {
			$this->logger->error( 'API preview error: ' . $e->getMessage() );
			$this->log_api_request( $request, 500 );

			return $this->error_response(
				self::ERROR_INTERNAL,
				__( 'Failed to generate preview.', 'rd-post-republishing' ),
				500
			);
		}
	}

	/**
	 * Handle the health check endpoint.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request  $request  The request object.
	 */
	public function handle_health( WP_REST_Request $request ): WP_REST_Response {
		$healthy = true;
		$checks = [];

		// Check database tables
		$schema = new \WPR\Republisher\Database\Schema();
		$tables_exist = $schema->all_tables_exist();
		$checks['database'] = $tables_exist ? 'ok' : 'error';
		$healthy = $healthy && $tables_exist;

		// Check settings
		$settings = $this->repository->get_settings();
		$checks['settings'] = ! empty( $settings ) ? 'ok' : 'warning';

		// Check cron
		$cron = new \WPR\Republisher\Scheduler\Cron( $this->repository );
		$cron_status = $cron->get_status();
		$checks['cron'] = ( $cron_status['daily_republishing']['scheduled'] ?? false ) ? 'ok' : 'warning';

		return new WP_REST_Response(
			[
				'status'  => $healthy ? 'healthy' : 'unhealthy',
				'checks'  => $checks,
				'version' => defined( 'WPR_VERSION' ) ? WPR_VERSION : '1.0.0',
			],
			$healthy ? 200 : 503
		);
	}

	/**
	 * Create a success response.
	 *
	 * @since    1.0.0
	 * @param    array<string, mixed>  $data  Response data.
	 */
	private function success_response( array $data ): WP_REST_Response {
		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Create an error response.
	 *
	 * @since    1.0.0
	 * @param    string               $code     Error code.
	 * @param    string               $message  Error message.
	 * @param    int                  $status   HTTP status code.
	 * @param    array<string, mixed> $extra    Extra data.
	 */
	private function error_response( string $code, string $message, int $status = 400, array $extra = [] ): WP_REST_Response {
		$response = [
			'success' => false,
			'code'    => $code,
			'message' => $message,
		];

		return new WP_REST_Response( array_merge( $response, $extra ), $status );
	}

	/**
	 * Get remaining quota for today.
	 *
	 * @since    1.0.0
	 */
	private function get_remaining_quota(): int {
		$query = new \WPR\Republisher\Republisher\Query( $this->repository );
		$settings = $this->repository->get_settings();
		$quota = $query->calculate_quota( $settings );
		$used = $this->repository->get_today_republish_count();

		return max( 0, $quota - $used );
	}

	/**
	 * Calculate post age in days.
	 *
	 * @since    1.0.0
	 * @param    string  $post_date  The post date.
	 */
	private function calculate_post_age( string $post_date ): int {
		$post_time = strtotime( $post_date );
		$now = time();

		return (int) floor( ( $now - $post_time ) / DAY_IN_SECONDS );
	}

	/**
	 * Sanitize settings for API output.
	 *
	 * Removes sensitive data before returning in debug mode.
	 *
	 * @since    1.0.0
	 * @param    array<string, mixed>  $settings  The settings array.
	 * @return   array<string, mixed>
	 */
	private function sanitize_settings_for_output( array $settings ): array {
		// Remove any potentially sensitive keys
		unset( $settings['api_key'] );
		unset( $settings['secret'] );

		return $settings;
	}

	/**
	 * Get the required capability for API access.
	 *
	 * @since    1.0.0
	 */
	private function get_required_capability(): string {
		/**
		 * Filter the required capability for API access.
		 *
		 * @since 1.0.0
		 * @param string $capability The capability required. Default 'manage_options'.
		 */
		return apply_filters( 'wpr_required_cap', 'manage_options' );
	}

	/**
	 * Log an API request.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request  $request        The request object.
	 * @param    int              $response_code  HTTP response code.
	 */
	private function log_api_request( WP_REST_Request $request, int $response_code ): void {
		$endpoint = $request->get_route();
		$user_id = get_current_user_id();

		$this->repository->log_api_request(
			$endpoint,
			$response_code,
			$user_id > 0 ? $user_id : null
		);
	}
}
