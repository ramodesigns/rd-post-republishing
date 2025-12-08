<?php

declare(strict_types=1);

namespace WPR\Republisher\Scheduler;

use WPR\Republisher\Database\Repository;
use WPR\Republisher\Republisher\Engine;

/**
 * Retry handler for failed republishing attempts
 *
 * Manages the retry queue and execution of failed post republishing.
 * Implements exponential backoff and maximum retry limits.
 *
 * @link       https://www.paulramotowski.com
 * @since      1.0.0
 *
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Scheduler
 */

/**
 * Retry class.
 *
 * Handles retry logic for posts that failed to republish.
 *
 * @since      1.0.0
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Scheduler
 * @author     Paul Ramotowski <paulramotowski@gmail.com>
 */
class Retry {

	/**
	 * Maximum number of retry attempts per post.
	 *
	 * @since    1.0.0
	 */
	public const MAX_RETRIES = 3;

	/**
	 * Base delay between retries in seconds (30 minutes).
	 *
	 * @since    1.0.0
	 */
	public const BASE_DELAY = 1800;

	/**
	 * Transient key for tracking retry counts.
	 *
	 * @since    1.0.0
	 */
	private const RETRY_COUNT_PREFIX = 'wpr_retry_count_';

	/**
	 * Repository instance.
	 *
	 * @since    1.0.0
	 */
	private Repository $repository;

	/**
	 * Engine instance.
	 *
	 * @since    1.0.0
	 */
	private Engine $engine;

	/**
	 * Initialize the retry handler.
	 *
	 * @since    1.0.0
	 * @param    Repository|null  $repository  Optional repository instance.
	 * @param    Engine|null      $engine      Optional engine instance.
	 */
	public function __construct( ?Repository $repository = null, ?Engine $engine = null ) {
		$this->repository = $repository ?? new Repository();
		$this->engine = $engine ?? new Engine( $this->repository );
	}

	/**
	 * Process failed posts for retry.
	 *
	 * Gets all failed posts from today and attempts to retry them,
	 * respecting the maximum retry limit.
	 *
	 * @since    1.0.0
	 * @return   array<string, mixed>  Results of retry attempts.
	 */
	public function process_failed(): array {
		$failed_records = $this->repository->get_failed_for_retry();

		if ( empty( $failed_records ) ) {
			return [
				'success'  => true,
				'message'  => __( 'No failed posts to retry.', 'rd-post-republishing' ),
				'retried'  => 0,
				'skipped'  => 0,
				'posts'    => [],
			];
		}

		$results = [];
		$retried = 0;
		$skipped = 0;

		foreach ( $failed_records as $record ) {
			$post_id = (int) $record->post_id;

			// Check retry count
			if ( ! $this->can_retry( $post_id ) ) {
				$skipped++;
				$this->log_debug( sprintf( 'Skipping post %d: max retries exceeded', $post_id ) );
				continue;
			}

			// Increment retry count
			$this->increment_retry_count( $post_id );

			// Mark as retrying in database
			$this->repository->update_history_status( (int) $record->id, 'retrying' );

			// Attempt republishing
			$settings = $this->repository->get_settings();
			$new_time = $this->generate_retry_time(
				(int) ( $settings['republish_start_hour'] ?? 9 ),
				(int) ( $settings['republish_end_hour'] ?? 17 )
			);

			$result = $this->engine->republish_single_post( $post_id, $new_time, 'cron' );
			$results[] = $result;

			if ( 'success' === $result['status'] ) {
				$retried++;
				// Clear retry count on success
				$this->clear_retry_count( $post_id );
				$this->log_debug( sprintf( 'Successfully retried post %d', $post_id ) );
			} else {
				$this->log_debug( sprintf( 'Retry failed for post %d: %s', $post_id, $result['error'] ?? 'Unknown error' ) );
			}
		}

		return [
			'success'  => true,
			'message'  => sprintf(
				/* translators: %1$d: retried count, %2$d: skipped count */
				__( 'Retry complete: %1$d successful, %2$d skipped (max retries exceeded).', 'rd-post-republishing' ),
				$retried,
				$skipped
			),
			'retried'  => $retried,
			'skipped'  => $skipped,
			'posts'    => $results,
		];
	}

	/**
	 * Check if a post can be retried.
	 *
	 * @since    1.0.0
	 * @param    int  $post_id  The post ID.
	 * @return   bool  True if retry is allowed.
	 */
	public function can_retry( int $post_id ): bool {
		$count = $this->get_retry_count( $post_id );
		return $count < self::MAX_RETRIES;
	}

	/**
	 * Get the current retry count for a post.
	 *
	 * @since    1.0.0
	 * @param    int  $post_id  The post ID.
	 * @return   int  Number of retry attempts.
	 */
	public function get_retry_count( int $post_id ): int {
		$count = get_transient( self::RETRY_COUNT_PREFIX . $post_id );
		return false === $count ? 0 : (int) $count;
	}

	/**
	 * Increment the retry count for a post.
	 *
	 * @since    1.0.0
	 * @param    int  $post_id  The post ID.
	 */
	private function increment_retry_count( int $post_id ): void {
		$count = $this->get_retry_count( $post_id ) + 1;
		// Store for 24 hours (resets daily)
		set_transient( self::RETRY_COUNT_PREFIX . $post_id, $count, DAY_IN_SECONDS );
	}

	/**
	 * Clear the retry count for a post (on success).
	 *
	 * @since    1.0.0
	 * @param    int  $post_id  The post ID.
	 */
	public function clear_retry_count( int $post_id ): void {
		delete_transient( self::RETRY_COUNT_PREFIX . $post_id );
	}

	/**
	 * Calculate the delay before next retry using exponential backoff.
	 *
	 * @since    1.0.0
	 * @param    int  $attempt  The current attempt number (1-based).
	 * @return   int  Delay in seconds.
	 */
	public function calculate_delay( int $attempt ): int {
		// Exponential backoff: 30min, 1hr, 2hr
		$multiplier = pow( 2, $attempt - 1 );
		return self::BASE_DELAY * $multiplier;
	}

	/**
	 * Schedule the next retry event.
	 *
	 * @since    1.0.0
	 * @param    int  $post_id   The post ID that failed.
	 * @param    int  $attempt   The attempt number.
	 * @return   int|false  Scheduled timestamp or false on failure.
	 */
	public function schedule_retry( int $post_id, int $attempt = 1 ): int|false {
		if ( $attempt > self::MAX_RETRIES ) {
			return false;
		}

		$delay = $this->calculate_delay( $attempt );
		$scheduled_time = time() + $delay;

		// Schedule single event for retry
		$scheduled = wp_schedule_single_event(
			$scheduled_time,
			Cron::RETRY_HOOK,
			[ 'post_id' => $post_id, 'attempt' => $attempt ]
		);

		if ( $scheduled ) {
			$this->log_debug( sprintf(
				'Scheduled retry for post %d at %s (attempt %d)',
				$post_id,
				wp_date( 'Y-m-d H:i:s', $scheduled_time ),
				$attempt
			) );
			return $scheduled_time;
		}

		return false;
	}

	/**
	 * Get retry status for all failed posts.
	 *
	 * @since    1.0.0
	 * @return   array<int, array<string, mixed>>  Array of post retry statuses.
	 */
	public function get_retry_status(): array {
		$failed_records = $this->repository->get_failed_for_retry();
		$status = [];

		foreach ( $failed_records as $record ) {
			$post_id = (int) $record->post_id;
			$retry_count = $this->get_retry_count( $post_id );

			$status[ $post_id ] = [
				'post_id'       => $post_id,
				'history_id'    => (int) $record->id,
				'retry_count'   => $retry_count,
				'max_retries'   => self::MAX_RETRIES,
				'can_retry'     => $retry_count < self::MAX_RETRIES,
				'last_error'    => $record->error_message ?? null,
				'failed_at'     => $record->created_at,
			];
		}

		return $status;
	}

	/**
	 * Generate a random time for retry within configured hours.
	 *
	 * @since    1.0.0
	 * @param    int  $start_hour  Start hour (0-23).
	 * @param    int  $end_hour    End hour (0-23).
	 * @return   string  Datetime string.
	 */
	private function generate_retry_time( int $start_hour, int $end_hour ): string {
		$timezone = wp_timezone();
		$now = new \DateTimeImmutable( 'now', $timezone );

		// Ensure valid range
		$start_hour = max( 0, min( 23, $start_hour ) );
		$end_hour = max( 0, min( 23, $end_hour ) );

		if ( $end_hour <= $start_hour ) {
			$end_hour = min( $start_hour + 1, 23 );
		}

		// Generate time within the configured window
		$random_hour = random_int( $start_hour, $end_hour );
		$random_minute = random_int( 0, 59 );
		$random_second = random_int( 0, 59 );

		$scheduled = $now->setTime( $random_hour, $random_minute, $random_second );

		return $scheduled->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Clear all retry counts (used during cleanup).
	 *
	 * @since    1.0.0
	 * @param    array<int>  $post_ids  Array of post IDs to clear.
	 */
	public function clear_all_retry_counts( array $post_ids ): void {
		foreach ( $post_ids as $post_id ) {
			$this->clear_retry_count( (int) $post_id );
		}
	}

	/**
	 * Log debug message if debug mode is enabled.
	 *
	 * @since    1.0.0
	 * @param    string  $message  The message to log.
	 */
	private function log_debug( string $message ): void {
		$settings = $this->repository->get_settings();

		if ( ! empty( $settings['debug_mode'] ) && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[WPR Retry] ' . $message );
		}
	}
}
