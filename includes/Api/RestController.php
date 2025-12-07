<?php

declare(strict_types=1);

namespace WPR\Republisher\Api;

use WPR\Republisher\Database\Repository;
use WPR\Republisher\Republisher\Engine;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST API controller class
 *
 * Handles REST API endpoints for external republishing triggers.
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
	 * Repository instance.
	 *
	 * @since    1.0.0
	 */
	private Repository $repository;

	/**
	 * Initialize the controller.
	 *
	 * @since    1.0.0
	 * @param    Repository|null  $repository  Optional repository instance.
	 */
	public function __construct( ?Repository $repository = null ) {
		$this->repository = $repository ?? new Repository();
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
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Force republishing even if daily quota is met (debug mode only).', 'rd-post-republishing' ),
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
						'type'        => 'integer',
						'default'     => 7,
						'minimum'     => 1,
						'maximum'     => 7,
						'description' => __( 'Number of days to preview.', 'rd-post-republishing' ),
					],
				],
			]
		);
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
			return new WP_Error(
				'rest_forbidden',
				__( 'Authentication required. Use WordPress Application Passwords.', 'rd-post-republishing' ),
				[ 'status' => 401 ]
			);
		}

		// Check for required capability
		$required_cap = $this->get_required_capability();
		if ( ! user_can( $current_user, $required_cap ) ) {
			$this->log_api_request( $request, 403 );
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this endpoint.', 'rd-post-republishing' ),
				[ 'status' => 403 ]
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
	public function handle_trigger( WP_REST_Request $request ): WP_REST_Response {
		$settings = $this->repository->get_settings();
		$user_id = get_current_user_id();
		$force = $request->get_param( 'force' );

		// Check rate limiting
		$rate_limit = (int) ( $settings['api_rate_limit_seconds'] ?? 86400 );
		if ( $this->repository->is_rate_limited( '/trigger', $user_id, $rate_limit ) ) {
			$this->log_api_request( $request, 429 );

			return new WP_REST_Response(
				[
					'success' => false,
					'code'    => 'rate_limited',
					'message' => __( 'Rate limit exceeded. Please try again later.', 'rd-post-republishing' ),
				],
				429
			);
		}

		// Force parameter only works in debug mode
		if ( $force && empty( $settings['debug_mode'] ) ) {
			$force = false;
		}

		// Execute republishing
		$engine = new Engine( $this->repository );
		$result = $engine->execute_batch( 'api' );

		// Log successful API request
		$this->log_api_request( $request, 200 );

		// Format response based on debug mode
		if ( ! empty( $settings['debug_mode'] ) ) {
			return new WP_REST_Response( $result, 200 );
		}

		// Simple response for production
		return new WP_REST_Response(
			[
				'success' => $result['success'],
				'message' => $result['message'] ?? null,
			],
			200
		);
	}

	/**
	 * Handle the status endpoint.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request  $request  The request object.
	 */
	public function handle_status( WP_REST_Request $request ): WP_REST_Response {
		$settings = $this->repository->get_settings();

		$status = [
			'success'            => true,
			'today_count'        => $this->repository->get_today_republish_count(),
			'quota_remaining'    => $this->get_remaining_quota(),
			'cron_enabled'       => ! empty( $settings['wp_cron_enabled'] ),
			'dry_run_mode'       => ! empty( $settings['dry_run_mode'] ),
			'debug_mode'         => ! empty( $settings['debug_mode'] ),
		];

		// Add detailed info in debug mode
		if ( ! empty( $settings['debug_mode'] ) ) {
			$cron = new \WPR\Republisher\Scheduler\Cron( $this->repository );
			$status['cron_status'] = $cron->get_status();
			$status['settings'] = $settings;
		}

		$this->log_api_request( $request, 200 );

		return new WP_REST_Response( $status, 200 );
	}

	/**
	 * Handle the preview endpoint.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request  $request  The request object.
	 */
	public function handle_preview( WP_REST_Request $request ): WP_REST_Response {
		$days = (int) $request->get_param( 'days' );
		$days = max( 1, min( 7, $days ) );

		$query = new \WPR\Republisher\Republisher\Query( $this->repository );
		$preview = $query->get_republishing_preview( $days );

		// Format preview for response
		$formatted = [];
		foreach ( $preview as $date => $posts ) {
			$formatted[ $date ] = array_map(
				fn( $post ) => [
					'ID'         => $post->ID,
					'title'      => $post->post_title,
					'post_type'  => $post->post_type,
					'post_date'  => $post->post_date,
				],
				$posts
			);
		}

		$this->log_api_request( $request, 200 );

		return new WP_REST_Response(
			[
				'success' => true,
				'preview' => $formatted,
			],
			200
		);
	}

	/**
	 * Get remaining quota for today.
	 *
	 * @since    1.0.0
	 */
	private function get_remaining_quota(): int {
		$query = new \WPR\Republisher\Republisher\Query( $this->repository );
		return $query->calculate_quota( $this->repository->get_settings() );
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
