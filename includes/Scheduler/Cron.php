<?php

declare(strict_types=1);

namespace WPR\Republisher\Scheduler;

use WPR\Republisher\Database\Repository;
use WPR\Republisher\Republisher\Engine;

/**
 * WP Cron scheduler class
 *
 * Manages all WordPress cron events for the plugin including
 * daily republishing, retry attempts, and database cleanup.
 *
 * @link       https://www.paulramotowski.com
 * @since      1.0.0
 *
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Scheduler
 */

/**
 * Cron scheduler class.
 *
 * Handles registration and execution of WP Cron events.
 *
 * @since      1.0.0
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Scheduler
 * @author     Paul Ramotowski <paulramotowski@gmail.com>
 */
class Cron {

	/**
	 * Repository instance.
	 *
	 * @since    1.0.0
	 * @var      Repository
	 */
	private Repository $repository;

	/**
	 * Cron hook for daily republishing.
	 *
	 * @since    1.0.0
	 */
	public const DAILY_HOOK = 'wpr_daily_republishing';

	/**
	 * Cron hook for retry attempts.
	 *
	 * @since    1.0.0
	 */
	public const RETRY_HOOK = 'wpr_retry_republishing';

	/**
	 * Cron hook for database cleanup.
	 *
	 * @since    1.0.0
	 */
	public const CLEANUP_HOOK = 'wpr_daily_cleanup';

	/**
	 * Retry delay in seconds (30 minutes).
	 *
	 * @since    1.0.0
	 */
	private const RETRY_DELAY = 30 * MINUTE_IN_SECONDS;

	/**
	 * Initialize the scheduler.
	 *
	 * @since    1.0.0
	 * @param    Repository|null $repository  Optional repository instance.
	 */
	public function __construct( ?Repository $repository = null ) {
		$this->repository = $repository ?? new Repository();
	}

	/**
	 * Register all cron hooks.
	 *
	 * Should be called during plugin initialization.
	 *
	 * @since    1.0.0
	 */
	public function register_hooks(): void {
		// Register custom cron schedules
		add_filter( 'cron_schedules', [ $this, 'add_cron_schedules' ] );

		// Register cron event handlers
		add_action( self::DAILY_HOOK, [ $this, 'execute_daily_republishing' ] );
		add_action( self::RETRY_HOOK, [ $this, 'execute_retry' ] );
		add_action( self::CLEANUP_HOOK, [ $this, 'execute_cleanup' ] );
	}

	/**
	 * Add custom cron schedules.
	 *
	 * @since    1.0.0
	 * @param    array<string, array<string, mixed>> $schedules  Existing schedules.
	 * @return   array<string, array<string, mixed>>
	 */
	public function add_cron_schedules( array $schedules ): array {
		$schedules['wpr_thirty_minutes'] = [
			'interval' => 30 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 30 Minutes (WPR Retry)', 'rd-post-republishing' ),
		];

		return $schedules;
	}

	/**
	 * Schedule all cron events.
	 *
	 * Called during plugin activation.
	 *
	 * @since    1.0.0
	 */
	public function schedule_events(): void {
		$settings = $this->repository->get_settings();

		// Only schedule if WP Cron is enabled in settings
		if ( empty( $settings['wp_cron_enabled'] ) ) {
			return;
		}

		// Check if WP Cron is disabled at the system level
		if ( $this->is_wp_cron_disabled() ) {
			$this->log_debug( 'WP Cron is disabled at system level. Events not scheduled.' );
			return;
		}

		// Schedule daily republishing
		if ( ! wp_next_scheduled( self::DAILY_HOOK ) ) {
			// Schedule for the configured start hour
			$start_hour = (int) ( $settings['republish_start_hour'] ?? 9 );
			$first_run  = $this->get_next_scheduled_time( $start_hour );
			wp_schedule_event( $first_run, 'daily', self::DAILY_HOOK );
		}

		// Schedule daily cleanup (run at 3 AM)
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			$cleanup_time = $this->get_next_scheduled_time( 3 );
			wp_schedule_event( $cleanup_time, 'daily', self::CLEANUP_HOOK );
		}
	}

	/**
	 * Unschedule all cron events.
	 *
	 * Called during plugin deactivation.
	 *
	 * @since    1.0.0
	 */
	public function unschedule_events(): void {
		wp_clear_scheduled_hook( self::DAILY_HOOK );
		wp_clear_scheduled_hook( self::RETRY_HOOK );
		wp_clear_scheduled_hook( self::CLEANUP_HOOK );
	}

	/**
	 * Execute the daily republishing batch.
	 *
	 * @since    1.0.0
	 */
	public function execute_daily_republishing(): void {
		$settings = $this->repository->get_settings();

		// Check if WP Cron mode is enabled
		if ( empty( $settings['wp_cron_enabled'] ) ) {
			$this->log_debug( 'Daily republishing skipped: WP Cron disabled in settings.' );
			return;
		}

		$this->log_debug( 'Starting daily republishing batch via WP Cron.' );

		$engine = new Engine( $this->repository );
		$result = $engine->execute_batch( 'cron' );

		$this->log_debug(
			sprintf(
				'Daily republishing completed: %s - %d successful, %d failed.',
				$result['success'] ? 'SUCCESS' : 'FAILED',
				$result['successful'] ?? 0,
				$result['failed'] ?? 0
			)
		);

		// Schedule retry if there were failures
		if ( ! empty( $result['failed'] ) && $result['failed'] > 0 ) {
			$this->schedule_retry();
		}

		/**
		 * Fires after daily republishing batch completes.
		 *
		 * @since 1.0.0
		 * @param array $result The batch execution result.
		 */
		do_action( 'wpr_daily_batch_complete', $result );
	}

	/**
	 * Execute retry for failed posts.
	 *
	 * @since    1.0.0
	 */
	public function execute_retry(): void {
		$this->log_debug( 'Starting retry for failed republishing attempts.' );

		$engine = new Engine( $this->repository );
		$result = $engine->retry_failed();

		$this->log_debug(
			sprintf(
				'Retry completed: %d posts retried.',
				count( $result['posts'] ?? [] )
			)
		);

		// Clear the retry schedule (single retry only)
		wp_clear_scheduled_hook( self::RETRY_HOOK );

		/**
		 * Fires after retry batch completes.
		 *
		 * @since 1.0.0
		 * @param array $result The retry execution result.
		 */
		do_action( 'wpr_retry_batch_complete', $result );
	}

	/**
	 * Execute database cleanup.
	 *
	 * @since    1.0.0
	 */
	public function execute_cleanup(): void {
		$this->log_debug( 'Starting database cleanup.' );

		$deleted = $this->repository->purge_old_records( 365 );

		$this->log_debug(
			sprintf(
				'Cleanup completed: %d history, %d audit, %d API log records deleted.',
				$deleted['history'] ?? 0,
				$deleted['audit'] ?? 0,
				$deleted['api_log'] ?? 0
			)
		);

		/**
		 * Fires after database cleanup completes.
		 *
		 * @since 1.0.0
		 * @param array $deleted Count of deleted records per table.
		 */
		do_action( 'wpr_cleanup_complete', $deleted );
	}

	/**
	 * Schedule a retry attempt.
	 *
	 * @since    1.0.0
	 */
	public function schedule_retry(): void {
		// Only schedule if not already scheduled
		if ( ! wp_next_scheduled( self::RETRY_HOOK ) ) {
			$retry_time = time() + self::RETRY_DELAY;
			wp_schedule_single_event( $retry_time, self::RETRY_HOOK );
			$this->log_debug( 'Retry scheduled for ' . wp_date( 'Y-m-d H:i:s', $retry_time ) );
		}
	}

	/**
	 * Get the next scheduled run time for a given hour.
	 *
	 * @since    1.0.0
	 * @param    int $hour  Hour of day (0-23).
	 */
	private function get_next_scheduled_time( int $hour ): int {
		$timezone  = wp_timezone();
		$now       = new \DateTimeImmutable( 'now', $timezone );
		$scheduled = $now->setTime( $hour, 0, 0 );

		// If the time has already passed today, schedule for tomorrow
		if ( $scheduled <= $now ) {
			$scheduled = $scheduled->modify( '+1 day' );
		}

		return $scheduled->getTimestamp();
	}

	/**
	 * Check if WP Cron is disabled at system level.
	 *
	 * @since    1.0.0
	 */
	public function is_wp_cron_disabled(): bool {
		return defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
	}

	/**
	 * Check if alternate cron is enabled.
	 *
	 * @since    1.0.0
	 */
	public function is_alternate_cron(): bool {
		return defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON;
	}

	/**
	 * Get status information about scheduled events.
	 *
	 * @since    1.0.0
	 * @return   array<string, mixed>
	 */
	public function get_status(): array {
		$settings = $this->repository->get_settings();

		$daily_next   = wp_next_scheduled( self::DAILY_HOOK );
		$retry_next   = wp_next_scheduled( self::RETRY_HOOK );
		$cleanup_next = wp_next_scheduled( self::CLEANUP_HOOK );

		return [
			'wp_cron_enabled'    => ! empty( $settings['wp_cron_enabled'] ),
			'wp_cron_disabled'   => $this->is_wp_cron_disabled(),
			'alternate_cron'     => $this->is_alternate_cron(),
			'daily_republishing' => [
				'scheduled' => false !== $daily_next,
				'next_run'  => $daily_next ? wp_date( 'Y-m-d H:i:s', $daily_next ) : null,
				'timestamp' => $daily_next ?: null,
			],
			'retry'              => [
				'scheduled' => false !== $retry_next,
				'next_run'  => $retry_next ? wp_date( 'Y-m-d H:i:s', $retry_next ) : null,
				'timestamp' => $retry_next ?: null,
			],
			'cleanup'            => [
				'scheduled' => false !== $cleanup_next,
				'next_run'  => $cleanup_next ? wp_date( 'Y-m-d H:i:s', $cleanup_next ) : null,
				'timestamp' => $cleanup_next ?: null,
			],
		];
	}

	/**
	 * Manually trigger republishing (for testing or manual execution).
	 *
	 * @since    1.0.0
	 * @return   array<string, mixed>
	 */
	public function trigger_manual(): array {
		$engine = new Engine( $this->repository );
		return $engine->execute_batch( 'manual' );
	}

	/**
	 * Log debug message if debug mode is enabled.
	 *
	 * @since    1.0.0
	 * @param    string $message  The message to log.
	 */
	private function log_debug( string $message ): void {
		$settings = $this->repository->get_settings();

		if ( ! empty( $settings['debug_mode'] ) && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[WPR Republisher] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Reschedule events (e.g., when settings change).
	 *
	 * @since    1.0.0
	 */
	public function reschedule_events(): void {
		$this->unschedule_events();
		$this->schedule_events();
	}
}
