<?php

declare(strict_types=1);

namespace WPR\Republisher\Republisher;

use WPR\Republisher\Database\Repository;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Republishing engine class
 *
 * Handles the core republishing logic including timestamp updates,
 * time randomization, and WordPress hook triggering.
 *
 * @link       https://www.paulramotowski.com
 * @since      1.0.0
 *
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Republisher
 */

/**
 * Engine class for post republishing.
 *
 * Implements the republishing process that updates post timestamps
 * to make posts appear as freshly published content.
 *
 * @since      1.0.0
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Republisher
 * @author     Paul Ramotowski <paulramotowski@gmail.com>
 */
class Engine {

	/**
	 * WordPress database instance.
	 *
	 * @since    1.0.0
	 */
	private \wpdb $wpdb;

	/**
	 * Repository instance for data access.
	 *
	 * @since    1.0.0
	 */
	private Repository $repository;

	/**
	 * Query instance for post selection.
	 *
	 * @since    1.0.0
	 */
	private Query $query;

	/**
	 * Site timezone.
	 *
	 * @since    1.0.0
	 */
	private DateTimeZone $timezone;

	/**
	 * Initialize the engine.
	 *
	 * @since    1.0.0
	 * @param    Repository|null  $repository  Optional repository instance.
	 * @param    Query|null       $query       Optional query instance.
	 */
	public function __construct( ?Repository $repository = null, ?Query $query = null ) {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->repository = $repository ?? new Repository();
		$this->query = $query ?? new Query( $this->repository );
		$this->timezone = wp_timezone();
	}

	/**
	 * Execute the daily republishing batch.
	 *
	 * Main entry point for cron and API triggered republishing.
	 *
	 * @since    1.0.0
	 * @param    string  $triggered_by  Trigger source: cron, api, manual.
	 * @return   array<string, mixed>  Results array with success/failure details.
	 */
	public function execute_batch( string $triggered_by = 'cron' ): array {
		$settings = $this->repository->get_settings();
		$start_time = microtime( true );

		// Check dry-run mode
		if ( ! empty( $settings['dry_run_mode'] ) ) {
			return $this->execute_dry_run();
		}

		// Acquire lock to prevent concurrent execution
		if ( ! $this->acquire_lock() ) {
			return [
				'success'  => false,
				'message'  => 'Another republishing process is already running.',
				'posts'    => [],
				'duration' => 0,
			];
		}

		try {
			// Get eligible posts
			$posts = $this->query->get_eligible_posts( $settings );

			if ( empty( $posts ) ) {
				$this->release_lock();
				return [
					'success'  => true,
					'message'  => 'No eligible posts found for republishing.',
					'posts'    => [],
					'duration' => microtime( true ) - $start_time,
				];
			}

			// Generate random times for all posts
			$scheduled_times = $this->generate_scheduled_times(
				count( $posts ),
				(int) ( $settings['republish_start_hour'] ?? 9 ),
				(int) ( $settings['republish_end_hour'] ?? 17 ),
				! empty( $settings['maintain_chronological_order'] )
			);

			// Process each post
			$results = [];
			foreach ( $posts as $index => $post ) {
				$new_time = $scheduled_times[ $index ] ?? $scheduled_times[0];
				$result = $this->republish_single_post(
					(int) $post->ID,
					$new_time,
					$triggered_by
				);
				$results[] = $result;

				/**
				 * Fires after a post republishing attempt.
				 *
				 * @since 1.0.0
				 * @param int    $post_id  The post ID.
				 * @param array  $result   The result of the republishing attempt.
				 * @param string $trigger  The trigger source.
				 */
				do_action( 'wpr_post_republished', (int) $post->ID, $result, $triggered_by );
			}

			$this->release_lock();

			$successful = array_filter( $results, fn( $r ) => 'success' === $r['status'] );
			$failed = array_filter( $results, fn( $r ) => 'failed' === $r['status'] );

			return [
				'success'        => true,
				'message'        => sprintf(
					'Republished %d posts successfully, %d failed.',
					count( $successful ),
					count( $failed )
				),
				'posts'          => $results,
				'total'          => count( $results ),
				'successful'     => count( $successful ),
				'failed'         => count( $failed ),
				'duration'       => microtime( true ) - $start_time,
			];
		} catch ( \Exception $e ) {
			$this->release_lock();
			return [
				'success'  => false,
				'message'  => 'Error during republishing: ' . $e->getMessage(),
				'posts'    => [],
				'duration' => microtime( true ) - $start_time,
			];
		}
	}

	/**
	 * Execute a dry-run simulation.
	 *
	 * @since    1.0.0
	 * @return   array<string, mixed>  Simulation results.
	 */
	public function execute_dry_run(): array {
		$settings = $this->repository->get_settings();
		$posts = $this->query->get_eligible_posts( $settings );

		$scheduled_times = $this->generate_scheduled_times(
			count( $posts ),
			(int) ( $settings['republish_start_hour'] ?? 9 ),
			(int) ( $settings['republish_end_hour'] ?? 17 ),
			! empty( $settings['maintain_chronological_order'] )
		);

		$preview = [];
		foreach ( $posts as $index => $post ) {
			$new_time = $scheduled_times[ $index ] ?? $scheduled_times[0];
			$preview[] = [
				'post_id'       => (int) $post->ID,
				'post_title'    => $post->post_title,
				'post_type'     => $post->post_type,
				'original_date' => $post->post_date,
				'new_date'      => $new_time,
				'status'        => 'preview',
			];
		}

		return [
			'success'  => true,
			'dry_run'  => true,
			'message'  => sprintf( 'Dry-run: %d posts would be republished.', count( $preview ) ),
			'posts'    => $preview,
			'total'    => count( $preview ),
		];
	}

	/**
	 * Republish a single post.
	 *
	 * @since    1.0.0
	 * @param    int     $post_id       The post ID to republish.
	 * @param    string  $new_datetime  The new publish datetime.
	 * @param    string  $triggered_by  Trigger source: cron, api, manual.
	 * @return   array<string, mixed>  Result array.
	 */
	public function republish_single_post(
		int $post_id,
		string $new_datetime,
		string $triggered_by = 'manual'
	): array {
		$start_time = microtime( true );
		$post = get_post( $post_id );

		if ( ! $post ) {
			return [
				'post_id' => $post_id,
				'status'  => 'failed',
				'error'   => 'Post not found.',
			];
		}

		$original_date = $post->post_date;

		/**
		 * Fires before a post is republished.
		 *
		 * @since 1.0.0
		 * @param int      $post_id  The post ID.
		 * @param \WP_Post $post     The post object.
		 * @param string   $new_datetime The new datetime.
		 */
		do_action( 'wpr_before_republish', $post_id, $post, $new_datetime );

		// Calculate GMT datetime
		$local_datetime = new DateTimeImmutable( $new_datetime, $this->timezone );
		$gmt_datetime = $local_datetime->setTimezone( new DateTimeZone( 'UTC' ) );
		$new_date_gmt = $gmt_datetime->format( 'Y-m-d H:i:s' );

		// Current time for post_modified
		$now = new DateTimeImmutable( 'now', $this->timezone );
		$now_gmt = $now->setTimezone( new DateTimeZone( 'UTC' ) );
		$modified_date = $now->format( 'Y-m-d H:i:s' );
		$modified_date_gmt = $now_gmt->format( 'Y-m-d H:i:s' );

		// Update the post in the database
		$update_result = $this->wpdb->update(
			$this->wpdb->posts,
			[
				'post_date'         => $new_datetime,
				'post_date_gmt'     => $new_date_gmt,
				'post_modified'     => $modified_date,
				'post_modified_gmt' => $modified_date_gmt,
			],
			[ 'ID' => $post_id ],
			[ '%s', '%s', '%s', '%s' ],
			[ '%d' ]
		);

		$execution_time = microtime( true ) - $start_time;

		if ( false === $update_result ) {
			$error_message = 'Database update failed: ' . $this->wpdb->last_error;

			// Log failure
			$this->repository->log_history(
				$post_id,
				$post->post_type,
				$original_date,
				$new_datetime,
				'failed',
				$triggered_by,
				$error_message,
				$execution_time
			);

			/**
			 * Fires when post republishing fails.
			 *
			 * @since 1.0.0
			 * @param int    $post_id The post ID.
			 * @param string $error   The error message.
			 */
			do_action( 'wpr_republish_failed', $post_id, $error_message );

			return [
				'post_id' => $post_id,
				'status'  => 'failed',
				'error'   => $error_message,
			];
		}

		// Clear WordPress caches
		$this->clear_post_caches( $post_id );

		// Trigger WordPress publication hooks to notify other plugins/themes
		$this->trigger_publication_hooks( $post_id, $post );

		// Log success
		$this->repository->log_history(
			$post_id,
			$post->post_type,
			$original_date,
			$new_datetime,
			'success',
			$triggered_by,
			null,
			$execution_time
		);

		/**
		 * Fires after a post is successfully republished.
		 *
		 * @since 1.0.0
		 * @param int    $post_id       The post ID.
		 * @param string $original_date The original publish date.
		 * @param string $new_datetime  The new publish datetime.
		 */
		do_action( 'wpr_after_republish', $post_id, $original_date, $new_datetime );

		return [
			'post_id'        => $post_id,
			'post_title'     => $post->post_title,
			'post_type'      => $post->post_type,
			'original_date'  => $original_date,
			'new_date'       => $new_datetime,
			'status'         => 'success',
			'execution_time' => $execution_time,
		];
	}

	/**
	 * Retry failed republishing attempts.
	 *
	 * Called by the retry cron event.
	 *
	 * @since    1.0.0
	 * @return   array<string, mixed>  Retry results.
	 */
	public function retry_failed(): array {
		$failed_records = $this->repository->get_failed_for_retry();

		if ( empty( $failed_records ) ) {
			return [
				'success' => true,
				'message' => 'No failed posts to retry.',
				'posts'   => [],
			];
		}

		$settings = $this->repository->get_settings();
		$results = [];

		foreach ( $failed_records as $record ) {
			// Mark as retrying
			$this->repository->update_history_status( (int) $record->id, 'retrying' );

			// Generate a new random time
			$new_time = $this->generate_random_time(
				(int) ( $settings['republish_start_hour'] ?? 9 ),
				(int) ( $settings['republish_end_hour'] ?? 17 )
			);

			$result = $this->republish_single_post(
				(int) $record->post_id,
				$new_time,
				'cron'
			);

			$results[] = $result;
		}

		return [
			'success' => true,
			'message' => sprintf( 'Retried %d failed posts.', count( $results ) ),
			'posts'   => $results,
		];
	}

	/**
	 * Generate scheduled times for a batch of posts.
	 *
	 * @since    1.0.0
	 * @param    int   $count            Number of times to generate.
	 * @param    int   $start_hour       Start hour (0-23).
	 * @param    int   $end_hour         End hour (0-23).
	 * @param    bool  $maintain_order   Whether to maintain chronological order.
	 * @return   array<int, string>      Array of datetime strings.
	 */
	private function generate_scheduled_times(
		int $count,
		int $start_hour,
		int $end_hour,
		bool $maintain_order
	): array {
		if ( $count <= 0 ) {
			return [];
		}

		$times = [];

		for ( $i = 0; $i < $count; $i++ ) {
			$times[] = $this->generate_random_time( $start_hour, $end_hour );
		}

		if ( $maintain_order ) {
			// Sort times chronologically so posts maintain their relative order
			sort( $times );
		}

		return $times;
	}

	/**
	 * Generate a random time within the configured hours.
	 *
	 * Uses site timezone and handles DST properly.
	 *
	 * @since    1.0.0
	 * @param    int  $start_hour  Start hour (0-23).
	 * @param    int  $end_hour    End hour (0-23).
	 */
	private function generate_random_time( int $start_hour, int $end_hour ): string {
		// Ensure valid range
		$start_hour = max( 0, min( 23, $start_hour ) );
		$end_hour = max( 0, min( 23, $end_hour ) );

		// Handle case where end is before start (e.g., 22:00 to 06:00)
		if ( $end_hour <= $start_hour ) {
			$end_hour = $start_hour + 1;
			if ( $end_hour > 23 ) {
				$end_hour = 23;
			}
		}

		// Generate random hour first (handles DST edge cases better)
		$random_hour = random_int( $start_hour, $end_hour );

		// Generate random minute and second
		$random_minute = random_int( 0, 59 );
		$random_second = random_int( 0, 59 );

		// Create datetime in site timezone
		$now = new DateTimeImmutable( 'now', $this->timezone );
		$scheduled = $now->setTime( $random_hour, $random_minute, $random_second );

		return $scheduled->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Clear all caches related to a post.
	 *
	 * @since    1.0.0
	 * @param    int  $post_id  The post ID.
	 */
	private function clear_post_caches( int $post_id ): void {
		// WordPress core cache
		clean_post_cache( $post_id );
		wp_cache_delete( $post_id, 'posts' );
		wp_cache_delete( $post_id, 'post_meta' );

		// Clear related caches
		$post = get_post( $post_id );
		if ( $post ) {
			// Clear author cache
			clean_post_cache( $post_id );

			// Clear taxonomy caches
			$taxonomies = get_object_taxonomies( $post->post_type );
			foreach ( $taxonomies as $taxonomy ) {
				wp_cache_delete( $post_id, "{$taxonomy}_relationships" );
			}
		}

		// Third-party cache plugin support
		$this->clear_third_party_caches( $post_id );

		/**
		 * Fires after post caches are cleared.
		 *
		 * Allows themes and plugins to clear their own caches.
		 *
		 * @since 1.0.0
		 * @param int $post_id The post ID.
		 */
		do_action( 'wpr_clear_post_cache', $post_id );
	}

	/**
	 * Clear third-party caching plugin caches.
	 *
	 * @since    1.0.0
	 * @param    int  $post_id  The post ID.
	 */
	private function clear_third_party_caches( int $post_id ): void {
		// WP Rocket
		if ( function_exists( 'rocket_clean_post' ) ) {
			rocket_clean_post( $post_id );
		}

		// W3 Total Cache
		if ( function_exists( 'w3tc_pgcache_flush_post' ) ) {
			w3tc_pgcache_flush_post( $post_id );
		}

		// WP Super Cache
		if ( function_exists( 'wp_cache_post_change' ) ) {
			wp_cache_post_change( $post_id );
		}

		// LiteSpeed Cache
		if ( class_exists( 'LiteSpeed_Cache_API' ) && method_exists( 'LiteSpeed_Cache_API', 'purge_post' ) ) {
			\LiteSpeed_Cache_API::purge_post( $post_id );
		}

		// WP Fastest Cache
		if ( function_exists( 'wpfc_clear_post_cache_by_id' ) ) {
			wpfc_clear_post_cache_by_id( $post_id );
		}

		// Autoptimize
		if ( class_exists( 'autoptimizeCache' ) && method_exists( 'autoptimizeCache', 'clearall' ) ) {
			// Only clear if post has been significantly changed
			// Note: This is aggressive; consider if needed
		}

		// Cache Enabler
		if ( class_exists( 'Cache_Enabler' ) && method_exists( 'Cache_Enabler', 'clear_page_cache_by_post_id' ) ) {
			\Cache_Enabler::clear_page_cache_by_post_id( $post_id );
		}

		// Nginx Helper (for Nginx FastCGI cache)
		if ( class_exists( 'Jeena\\Jeena_Starter\\FastCGI_Cache' ) ) {
			// Implementation varies by setup
		}

		// Redis Object Cache - object cache is already handled by clean_post_cache()
	}

	/**
	 * Trigger WordPress publication hooks.
	 *
	 * Notifies WordPress and other plugins that a post has been "republished".
	 *
	 * @since    1.0.0
	 * @param    int       $post_id  The post ID.
	 * @param    \WP_Post  $post     The post object.
	 */
	private function trigger_publication_hooks( int $post_id, \WP_Post $post ): void {
		// Refresh post object from database
		$updated_post = get_post( $post_id );
		if ( ! $updated_post ) {
			return;
		}

		// Trigger edit_post action
		do_action( 'edit_post', $post_id, $updated_post );

		// Trigger post type specific action
		do_action( "save_post_{$updated_post->post_type}", $post_id, $updated_post, true );

		// Trigger general save_post action
		do_action( 'save_post', $post_id, $updated_post, true );

		// Trigger wp_insert_post action
		do_action( 'wp_insert_post', $post_id, $updated_post, true );

		// Trigger transition_post_status (publish to publish)
		do_action( 'transition_post_status', 'publish', 'publish', $updated_post );

		// Trigger publish action
		do_action( "publish_{$updated_post->post_type}", $post_id, $updated_post );
	}

	/**
	 * Acquire a lock to prevent concurrent execution.
	 *
	 * @since    1.0.0
	 */
	private function acquire_lock(): bool {
		$lock_key = 'wpr_republishing_lock';
		$lock_timeout = 10 * MINUTE_IN_SECONDS; // 10 minutes max

		// Check if lock exists and is still valid
		$existing_lock = get_transient( $lock_key );
		if ( false !== $existing_lock ) {
			// Lock exists, another process is running
			return false;
		}

		// Set the lock
		return set_transient( $lock_key, time(), $lock_timeout );
	}

	/**
	 * Release the execution lock.
	 *
	 * @since    1.0.0
	 */
	private function release_lock(): void {
		delete_transient( 'wpr_republishing_lock' );
	}

	/**
	 * Get the repository instance.
	 *
	 * @since    1.0.0
	 */
	public function get_repository(): Repository {
		return $this->repository;
	}

	/**
	 * Get the query instance.
	 *
	 * @since    1.0.0
	 */
	public function get_query(): Query {
		return $this->query;
	}
}
