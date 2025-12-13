<?php

declare(strict_types=1);

namespace WPR\Republisher\Admin;

use WPR\Republisher\Database\Repository;
use WPR\Republisher\Database\Maintenance;
use WPR\Republisher\Republisher\Engine;
use WPR\Republisher\Republisher\Query;
use WPR\Republisher\Logger\Logger;
use WPR\Republisher\Logger\AuditTrail;

/**
 * AJAX handler for admin operations
 *
 * Provides centralized AJAX request handling for all admin operations
 * with proper security, validation, and error handling.
 *
 * @link       https://www.paulramotowski.com
 * @since      1.0.0
 *
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Admin
 */

/**
 * Ajax class.
 *
 * Handles all AJAX requests from the admin interface.
 *
 * @since      1.0.0
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Admin
 * @author     Paul Ramotowski <paulramotowski@gmail.com>
 */
class Ajax {

	/**
	 * Nonce action name.
	 *
	 * @since    1.0.0
	 */
	public const NONCE_ACTION = 'wpr_admin_nonce';

	/**
	 * Repository instance.
	 *
	 * @since    1.0.0
	 */
	private Repository $repository;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 */
	private Logger $logger;

	/**
	 * Audit trail instance.
	 *
	 * @since    1.0.0
	 */
	private AuditTrail $audit_trail;

	/**
	 * Initialize the AJAX handler.
	 *
	 * @since    1.0.0
	 * @param    Repository|null $repository   Optional repository instance.
	 * @param    Logger|null     $logger       Optional logger instance.
	 * @param    AuditTrail|null $audit_trail  Optional audit trail instance.
	 */
	public function __construct(
		?Repository $repository = null,
		?Logger $logger = null,
		?AuditTrail $audit_trail = null
	) {
		$this->repository  = $repository ?? new Repository();
		$this->logger      = $logger ?? Logger::get_instance( $this->repository );
		$this->audit_trail = $audit_trail ?? new AuditTrail( $this->repository, $this->logger );
	}

	/**
	 * Register AJAX handlers.
	 *
	 * @since    1.0.0
	 */
	public function register_handlers(): void {
		// Republishing operations
		add_action( 'wp_ajax_wpr_dry_run', [ $this, 'handle_dry_run' ] );
		add_action( 'wp_ajax_wpr_manual_trigger', [ $this, 'handle_manual_trigger' ] );
		add_action( 'wp_ajax_wpr_get_preview', [ $this, 'handle_get_preview' ] );

		// Export operations
		add_action( 'wp_ajax_wpr_export_history', [ $this, 'handle_export_history' ] );
		add_action( 'wp_ajax_wpr_export_audit', [ $this, 'handle_export_audit' ] );

		// Maintenance operations
		add_action( 'wp_ajax_wpr_run_maintenance', [ $this, 'handle_run_maintenance' ] );
		add_action( 'wp_ajax_wpr_get_status', [ $this, 'handle_get_status' ] );
	}

	/**
	 * Handle dry-run AJAX request.
	 *
	 * @since    1.0.0
	 */
	public function handle_dry_run(): void {
		$this->verify_request();

		try {
			$settings = $this->repository->get_settings();
			$query    = new Query( $this->repository );

			$eligible_posts     = $query->get_eligible_posts( $settings );
			$quota              = $query->calculate_quota( $settings );
			$posts_to_republish = array_slice( $eligible_posts, 0, $quota );

			$preview_data = $this->format_posts_for_preview( $posts_to_republish );

			// Log the dry run
			$this->audit_trail->log_dry_run(
				count( $posts_to_republish ),
				[
					'total_eligible' => count( $eligible_posts ),
					'quota'          => $quota,
				]
			);

			$this->success(
				[
					'posts'       => $preview_data,
					'total_count' => count( $eligible_posts ),
					'quota'       => $quota,
					'message'     => sprintf(
						/* translators: %1$d: number of posts to republish, %2$d: total eligible posts */
						__( 'Dry-run complete. %1$d of %2$d eligible posts would be republished.', 'rd-post-republishing' ),
						count( $posts_to_republish ),
						count( $eligible_posts )
					),
				]
			);

		} catch ( \Throwable $e ) {
			$this->logger->error( 'Dry-run failed: ' . $e->getMessage() );
			$this->error( __( 'Failed to execute dry-run.', 'rd-post-republishing' ) );
		}
	}

	/**
	 * Handle manual trigger AJAX request.
	 *
	 * @since    1.0.0
	 */
	public function handle_manual_trigger(): void {
		$this->verify_request();

		try {
			$engine = new Engine( $this->repository );

			// Check for lock
			if ( $engine->is_locked() ) {
				$this->error(
					__( 'Another republishing operation is in progress. Please wait.', 'rd-post-republishing' ),
					[ 'locked' => true ]
				);
				return;
			}

			$result = $engine->execute_batch( 'manual' );

			// Log the manual trigger
			$this->audit_trail->log_manual_trigger(
				count( $result['republished'] ?? [] ),
				[
					'failed'  => count( $result['failed'] ?? [] ),
					'skipped' => count( $result['skipped'] ?? [] ),
				]
			);

			if ( $result['success'] ) {
				$this->success(
					[
						'republished' => $result['republished'],
						'failed'      => $result['failed'],
						'skipped'     => $result['skipped'],
						'message'     => sprintf(
							/* translators: %d: number of republished posts */
							__( 'Successfully republished %d posts.', 'rd-post-republishing' ),
							count( $result['republished'] )
						),
					]
				);
			} else {
				$this->error( $result['message'] ?? __( 'Republishing failed.', 'rd-post-republishing' ) );
			}
		} catch ( \Throwable $e ) {
			$this->logger->error( 'Manual trigger failed: ' . $e->getMessage() );
			$this->error( __( 'Failed to trigger republishing.', 'rd-post-republishing' ) );
		}
	}

	/**
	 * Handle get preview AJAX request.
	 *
	 * @since    1.0.0
	 */
	public function handle_get_preview(): void {
		$this->verify_request();

		try {
			$settings = $this->repository->get_settings();
			$query    = new Query( $this->repository );

			$eligible_posts = $query->get_eligible_posts( $settings );
			$quota          = $query->calculate_quota( $settings );
			$posts_to_show  = array_slice( $eligible_posts, 0, $quota );

			$preview_data = $this->format_posts_for_preview( $posts_to_show, true );

			$this->success(
				[
					'posts'       => $preview_data,
					'total_count' => count( $eligible_posts ),
					'quota'       => $quota,
				]
			);

		} catch ( \Throwable $e ) {
			$this->logger->error( 'Get preview failed: ' . $e->getMessage() );
			$this->error( __( 'Failed to get preview.', 'rd-post-republishing' ) );
		}
	}

	/**
	 * Handle export history AJAX request.
	 *
	 * @since    1.0.0
	 */
	public function handle_export_history(): void {
		$this->verify_request();

		try {
			$maintenance = new Maintenance( $this->repository );
			$csv         = $maintenance->export_table_csv( 'history' );

			if ( false === $csv || '' === $csv ) {
				$this->error( __( 'No history records to export.', 'rd-post-republishing' ) );
				return;
			}

			// Log the export
			$this->audit_trail->log_data_export( 'history', substr_count( $csv, "\n" ) );

			$this->success(
				[
					'csv'      => $csv,
					'filename' => 'wpr-history-' . wp_date( 'Y-m-d' ) . '.csv',
				]
			);

		} catch ( \Throwable $e ) {
			$this->logger->error( 'Export history failed: ' . $e->getMessage() );
			$this->error( __( 'Failed to export history.', 'rd-post-republishing' ) );
		}
	}

	/**
	 * Handle export audit AJAX request.
	 *
	 * @since    1.0.0
	 */
	public function handle_export_audit(): void {
		$this->verify_request();

		try {
			$maintenance = new Maintenance( $this->repository );
			$csv         = $maintenance->export_table_csv( 'audit' );

			if ( false === $csv || '' === $csv ) {
				$this->error( __( 'No audit records to export.', 'rd-post-republishing' ) );
				return;
			}

			// Log the export
			$this->audit_trail->log_data_export( 'audit', substr_count( $csv, "\n" ) );

			$this->success(
				[
					'csv'      => $csv,
					'filename' => 'wpr-audit-' . wp_date( 'Y-m-d' ) . '.csv',
				]
			);

		} catch ( \Throwable $e ) {
			$this->logger->error( 'Export audit failed: ' . $e->getMessage() );
			$this->error( __( 'Failed to export audit log.', 'rd-post-republishing' ) );
		}
	}

	/**
	 * Handle run maintenance AJAX request.
	 *
	 * @since    1.0.0
	 */
	public function handle_run_maintenance(): void {
		$this->verify_request();

		try {
			$maintenance = new Maintenance( $this->repository );
			$result      = $maintenance->run_maintenance();

			// Log the purge
			if ( ! empty( $result['purge'] ) ) {
				$this->audit_trail->log_data_purge( $result['purge'] );
			}

			$this->success(
				[
					'results' => $result,
					'message' => sprintf(
						/* translators: %d: total records deleted */
						__( 'Maintenance complete. %d records cleaned up.', 'rd-post-republishing' ),
						array_sum( $result['purge'] ?? [] )
					),
				]
			);

		} catch ( \Throwable $e ) {
			$this->logger->error( 'Maintenance failed: ' . $e->getMessage() );
			$this->error( __( 'Failed to run maintenance.', 'rd-post-republishing' ) );
		}
	}

	/**
	 * Handle get status AJAX request.
	 *
	 * @since    1.0.0
	 */
	public function handle_get_status(): void {
		$this->verify_request();

		try {
			$maintenance = new Maintenance( $this->repository );
			$status      = $maintenance->get_status();

			$this->success(
				[
					'status' => $status,
				]
			);

		} catch ( \Throwable $e ) {
			$this->logger->error( 'Get status failed: ' . $e->getMessage() );
			$this->error( __( 'Failed to get status.', 'rd-post-republishing' ) );
		}
	}

	/**
	 * Verify AJAX request has valid nonce and capabilities.
	 *
	 * @since    1.0.0
	 */
	private function verify_request(): void {
		// Verify nonce
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			$this->logger->warning( 'AJAX request failed: invalid nonce' );
			$this->error( __( 'Security check failed. Please refresh the page and try again.', 'rd-post-republishing' ), [], 403 );
		}

		// Verify capability
		$required_cap = $this->get_required_capability();
		if ( ! current_user_can( $required_cap ) ) {
			$this->logger->warning(
				'AJAX request failed: insufficient capability',
				[
					'user_id'      => get_current_user_id(),
					'required_cap' => $required_cap,
				]
			);
			$this->error( __( 'You do not have permission to perform this action.', 'rd-post-republishing' ), [], 403 );
		}
	}

	/**
	 * Format posts for preview display.
	 *
	 * @since    1.0.0
	 * @param    array<int, int|object> $post_ids        Array of post IDs or post objects.
	 * @param    bool                   $include_cats    Include category names.
	 * @return   array<int, array<string, mixed>>
	 */
	private function format_posts_for_preview( array $post_ids, bool $include_cats = false ): array {
		$preview_data = [];

		foreach ( $post_ids as $item ) {
			// Handle both integer IDs and objects with ID property
			if ( is_object( $item ) ) {
				/** @var object{ID: int} $item */
				$post_id = (int) $item->ID;
			} else {
				$post_id = (int) $item;
			}
			$post = get_post( $post_id );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$data = [
				'id'           => $post->ID,
				'title'        => $post->post_title,
				'current_date' => $post->post_date,
				'post_type'    => $post->post_type,
				'edit_link'    => get_edit_post_link( $post->ID, 'raw' ),
				'view_link'    => get_permalink( $post->ID ),
			];

			if ( $include_cats && 'post' === $post->post_type ) {
				$categories         = wp_get_post_categories( $post->ID, [ 'fields' => 'names' ] );
				$data['categories'] = is_array( $categories ) ? $categories : [];
			}

			$preview_data[] = $data;
		}

		return $preview_data;
	}

	/**
	 * Get the required capability for admin operations.
	 *
	 * @since    1.0.0
	 */
	private function get_required_capability(): string {
		/**
		 * Filter the required capability for admin AJAX operations.
		 *
		 * @since 1.0.0
		 * @param string $capability The capability required. Default 'manage_options'.
		 */
		return apply_filters( 'wpr_required_cap', 'manage_options' );
	}

	/**
	 * Send a success response.
	 *
	 * @since    1.0.0
	 * @param    array<string, mixed> $data  Response data.
	 */
	private function success( array $data ): void {
		wp_send_json_success( $data );
	}

	/**
	 * Send an error response.
	 *
	 * @since    1.0.0
	 * @param    string               $message     Error message.
	 * @param    array<string, mixed> $extra       Additional data.
	 * @param    int                  $status_code HTTP status code.
	 */
	private function error( string $message, array $extra = [], int $status_code = 400 ): void {
		wp_send_json_error(
			array_merge( [ 'message' => $message ], $extra ),
			$status_code
		);
	}

	/**
	 * Create a nonce for admin AJAX requests.
	 *
	 * @since    1.0.0
	 */
	public static function create_nonce(): string {
		return wp_create_nonce( self::NONCE_ACTION );
	}

	/**
	 * Get localized script data for AJAX.
	 *
	 * @since    1.0.0
	 * @return   array<string, mixed>
	 */
	public static function get_script_data(): array {
		return [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => self::create_nonce(),
			'i18n'    => [
				'confirmDryRun' => __( 'Run dry-run simulation?', 'rd-post-republishing' ),
				'confirmManual' => __( 'Trigger manual republishing now?', 'rd-post-republishing' ),
				'confirmMaint'  => __( 'Run database maintenance?', 'rd-post-republishing' ),
				'success'       => __( 'Operation completed successfully.', 'rd-post-republishing' ),
				'error'         => __( 'An error occurred. Please try again.', 'rd-post-republishing' ),
				'loading'       => __( 'Loading...', 'rd-post-republishing' ),
				'exporting'     => __( 'Exporting...', 'rd-post-republishing' ),
				'noData'        => __( 'No data to export.', 'rd-post-republishing' ),
			],
		];
	}
}
