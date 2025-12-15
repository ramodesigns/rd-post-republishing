<?php

declare(strict_types=1);

namespace WPR\Republisher\CLI;

use WPR\Republisher\Database\Migrator;
use WPR\Republisher\Database\Repository;
use WPR\Republisher\Republisher\Engine;
use WPR\Republisher\Republisher\Query;
use WPR\Republisher\Scheduler\Cron;
use WP_CLI;
use WP_CLI\Utils;

/**
 * WP-CLI commands for Post Republishing plugin.
 *
 * @link       https://www.paulramotowski.com
 * @since      1.0.0
 *
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/cli
 */

/**
 * Manage post republishing operations via command line.
 *
 * ## EXAMPLES
 *
 *     # Run the republishing batch
 *     $ wp wpr run
 *
 *     # Run a dry-run simulation
 *     $ wp wpr dry-run
 *
 *     # Clean up old records
 *     $ wp wpr cleanup
 *
 *     # Show current status
 *     $ wp wpr status
 *
 * @since      1.0.0
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/cli
 * @author     Paul Ramotowski <paulramotowski@gmail.com>
 */
class Commands {

	/**
	 * Repository instance.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Engine instance.
	 *
	 * @var Engine
	 */
	private Engine $engine;

	/**
	 * Query instance.
	 *
	 * @var Query
	 */
	private Query $query;

	/**
	 * Initialize the CLI commands.
	 */
	public function __construct() {
		$this->repository = new Repository();
		$this->engine     = new Engine( $this->repository );
		$this->query      = new Query( $this->repository );
	}

	/**
	 * Execute today's republishing batch.
	 *
	 * Runs the same process as the daily WP Cron job, republishing eligible
	 * posts based on the configured settings.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Bypass the dry-run mode setting and force actual republishing.
	 *
	 * [--quiet]
	 * : Suppress progress output.
	 *
	 * ## EXAMPLES
	 *
	 *     # Run republishing batch
	 *     $ wp wpr run
	 *     Success: Republished 5 posts.
	 *
	 *     # Force run even if dry-run mode is enabled
	 *     $ wp wpr run --force
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public function run( array $args, array $assoc_args ): void {
		$force = Utils\get_flag_value( $assoc_args, 'force', false );
		$quiet = Utils\get_flag_value( $assoc_args, 'quiet', false );

		// Temporarily disable dry-run mode if force flag is set
		if ( $force ) {
			$settings         = $this->repository->get_settings();
			$original_dry_run = $settings['dry_run_mode'] ?? false;
			if ( $original_dry_run ) {
				$settings['dry_run_mode'] = false;
				update_option( 'wpr_settings', $settings );
			}
		}

		if ( ! $quiet ) {
			WP_CLI::log( 'Starting republishing batch...' );
		}

		$result = $this->engine->execute_batch( 'cli' );

		// Restore dry-run mode if it was changed
		if ( $force && isset( $original_dry_run ) && $original_dry_run ) {
			$settings['dry_run_mode'] = true;
			update_option( 'wpr_settings', $settings );
		}

		if ( $result['success'] ) {
			$message = sprintf(
				'Republished %d posts successfully, %d failed.',
				$result['successful'] ?? 0,
				$result['failed'] ?? 0
			);

			if ( ! empty( $result['dry_run'] ) ) {
				WP_CLI::warning( 'Dry-run mode is enabled. No posts were actually republished.' );
				WP_CLI::log( $result['message'] );
			} else {
				WP_CLI::success( $message );
			}

			// Show details if not quiet
			if ( ! $quiet && ! empty( $result['posts'] ) ) {
				$this->display_results_table( $result['posts'] );
			}
		} else {
			WP_CLI::error( $result['message'] ?? 'Republishing failed.' );
		}
	}

	/**
	 * Run a dry-run simulation.
	 *
	 * Shows which posts would be republished without making any changes.
	 * This is useful for testing configuration before enabling auto-republishing.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json, csv. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     # Show dry-run preview
	 *     $ wp wpr dry-run
	 *
	 *     # Output as JSON
	 *     $ wp wpr dry-run --format=json
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public function dry_run( array $args, array $assoc_args ): void {
		$format = Utils\get_flag_value( $assoc_args, 'format', 'table' );

		WP_CLI::log( 'Running dry-run simulation...' );

		$result = $this->engine->execute_dry_run();

		if ( empty( $result['posts'] ) ) {
			WP_CLI::warning( 'No eligible posts found for republishing.' );
			return;
		}

		WP_CLI::success( sprintf( '%d posts would be republished.', count( $result['posts'] ) ) );

		// Prepare data for display
		$display_data = array_map(
			function ( $post ) {
				return [
					'ID'            => $post['post_id'],
					'Title'         => substr( $post['post_title'], 0, 40 ),
					'Type'          => $post['post_type'],
					'Original Date' => $post['original_date'],
					'New Date'      => $post['new_date'],
				];
			},
			$result['posts']
		);

		Utils\format_items( $format, $display_data, [ 'ID', 'Title', 'Type', 'Original Date', 'New Date' ] );
	}

	/**
	 * Clean up old records from the database.
	 *
	 * Purges history, audit, and API log records older than the retention period.
	 * By default, records older than 365 days are removed.
	 *
	 * ## OPTIONS
	 *
	 * [--days=<days>]
	 * : Number of days to retain. Default: 365.
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     # Clean up records older than 365 days
	 *     $ wp wpr cleanup
	 *
	 *     # Clean up records older than 90 days
	 *     $ wp wpr cleanup --days=90
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public function cleanup( array $args, array $assoc_args ): void {
		$days         = (int) Utils\get_flag_value( $assoc_args, 'days', 365 );
		$skip_confirm = Utils\get_flag_value( $assoc_args, 'yes', false );

		if ( $days < 1 ) {
			WP_CLI::error( 'Days must be a positive integer.' );
		}

		if ( ! $skip_confirm ) {
			WP_CLI::confirm( sprintf( 'This will delete all records older than %d days. Continue?', $days ) );
		}

		WP_CLI::log( sprintf( 'Cleaning up records older than %d days...', $days ) );

		$deleted = $this->repository->purge_old_records( $days );

		$total = ( $deleted['history'] ?? 0 ) + ( $deleted['audit'] ?? 0 ) + ( $deleted['api_log'] ?? 0 );

		WP_CLI::success(
			sprintf(
				'Cleanup complete. Deleted %d history, %d audit, %d API log records (total: %d).',
				$deleted['history'] ?? 0,
				$deleted['audit'] ?? 0,
				$deleted['api_log'] ?? 0,
				$total
			)
		);
	}

	/**
	 * Show current plugin status.
	 *
	 * Displays information about the plugin configuration, scheduled events,
	 * and recent activity.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json, yaml. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     # Show status
	 *     $ wp wpr status
	 *
	 *     # Output as JSON
	 *     $ wp wpr status --format=json
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public function status( array $args, array $assoc_args ): void {
		$format = Utils\get_flag_value( $assoc_args, 'format', 'table' );

		$settings    = $this->repository->get_settings();
		$cron        = new Cron( $this->repository );
		$cron_status = $cron->get_status();

		// Basic settings
		WP_CLI::log( WP_CLI::colorize( '%BPlugin Settings:%n' ) );
		$settings_data = [
			[
				'Setting' => 'Enabled Post Types',
				'Value'   => implode( ', ', $settings['enabled_post_types'] ?? [] ),
			],
			[
				'Setting' => 'Daily Quota',
				'Value'   => sprintf( '%d (%s)', $settings['daily_quota_value'] ?? 5, $settings['daily_quota_type'] ?? 'number' ),
			],
			[
				'Setting' => 'Time Range',
				'Value'   => sprintf( '%02d:00 - %02d:00', $settings['republish_start_hour'] ?? 9, $settings['republish_end_hour'] ?? 17 ),
			],
			[
				'Setting' => 'Minimum Age',
				'Value'   => sprintf( '%d days', $settings['minimum_age_days'] ?? 30 ),
			],
			[
				'Setting' => 'WP Cron Enabled',
				'Value'   => ( $settings['wp_cron_enabled'] ?? true ) ? 'Yes' : 'No',
			],
			[
				'Setting' => 'Debug Mode',
				'Value'   => ( $settings['debug_mode'] ?? false ) ? 'Yes' : 'No',
			],
			[
				'Setting' => 'Dry-Run Mode',
				'Value'   => ( $settings['dry_run_mode'] ?? false ) ? 'Yes' : 'No',
			],
		];
		Utils\format_items( $format, $settings_data, [ 'Setting', 'Value' ] );

		WP_CLI::log( '' );

		// Cron status
		WP_CLI::log( WP_CLI::colorize( '%BScheduled Events:%n' ) );
		$cron_data = [
			[
				'Event'    => 'Daily Republishing',
				'Status'   => $cron_status['daily_republishing']['scheduled'] ? 'Scheduled' : 'Not scheduled',
				'Next Run' => $cron_status['daily_republishing']['next_run'] ?? 'N/A',
			],
			[
				'Event'    => 'Retry Failed',
				'Status'   => $cron_status['retry']['scheduled'] ? 'Scheduled' : 'Not scheduled',
				'Next Run' => $cron_status['retry']['next_run'] ?? 'N/A',
			],
			[
				'Event'    => 'Daily Cleanup',
				'Status'   => $cron_status['cleanup']['scheduled'] ? 'Scheduled' : 'Not scheduled',
				'Next Run' => $cron_status['cleanup']['next_run'] ?? 'N/A',
			],
		];
		Utils\format_items( $format, $cron_data, [ 'Event', 'Status', 'Next Run' ] );

		WP_CLI::log( '' );

		// Recent activity
		WP_CLI::log( WP_CLI::colorize( '%BRecent Activity:%n' ) );
		$today_count   = $this->repository->get_today_republish_count();
		$total_history = $this->repository->get_history_count();

		$activity_data = [
			[
				'Metric' => 'Republished Today',
				'Value'  => (string) $today_count,
			],
			[
				'Metric' => 'Total History Records',
				'Value'  => (string) $total_history,
			],
		];
		Utils\format_items( $format, $activity_data, [ 'Metric', 'Value' ] );

		// Eligible posts preview
		$eligible = $this->query->get_eligible_posts( $settings );
		$quota    = $this->query->calculate_quota( $settings );

		WP_CLI::log( '' );
		WP_CLI::log(
			sprintf(
				'Eligible posts: %d (quota: %d)',
				count( $eligible ),
				$quota
			)
		);
	}

	/**
	 * List republishing history.
	 *
	 * Shows the history of republished posts with optional filtering.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]
	 * : Filter by status. Options: success, failed, retrying.
	 *
	 * [--limit=<limit>]
	 * : Number of records to show. Default: 20.
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json, csv. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     # Show recent history
	 *     $ wp wpr history
	 *
	 *     # Show only failed attempts
	 *     $ wp wpr history --status=failed
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public function history( array $args, array $assoc_args ): void {
		$status = Utils\get_flag_value( $assoc_args, 'status', null );
		$limit  = (int) Utils\get_flag_value( $assoc_args, 'limit', 20 );
		$format = Utils\get_flag_value( $assoc_args, 'format', 'table' );

		$history = $this->repository->get_history(
			[
				'status' => $status,
				'limit'  => $limit,
			]
		);

		if ( empty( $history ) ) {
			WP_CLI::warning( 'No history records found.' );
			return;
		}

		$display_data = array_map(
			function ( object $record ): array {
				/** @var object{post_id: int|string, id: int|string, status: string, triggered_by: string, created_at: string} $record */
				$post = get_post( (int) $record->post_id );
				return [
					'ID'      => $record->id,
					'Post ID' => $record->post_id,
					'Title'   => $post instanceof \WP_Post ? substr( $post->post_title, 0, 30 ) : '(deleted)',
					'Status'  => $record->status,
					'Trigger' => $record->triggered_by,
					'Date'    => $record->created_at,
				];
			},
			$history
		);

		Utils\format_items( $format, $display_data, [ 'ID', 'Post ID', 'Title', 'Status', 'Trigger', 'Date' ] );
	}

	/**
	 * Reschedule cron events.
	 *
	 * Clears and re-schedules all plugin cron events based on current settings.
	 *
	 * ## EXAMPLES
	 *
	 *     # Reschedule cron events
	 *     $ wp wpr reschedule
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public function reschedule( array $args, array $assoc_args ): void {
		$cron = new Cron( $this->repository );
		$cron->reschedule_events();

		$status = $cron->get_status();

		WP_CLI::success( 'Cron events rescheduled.' );
		WP_CLI::log(
			sprintf(
				'Next daily republishing: %s',
				$status['daily_republishing']['next_run'] ?? 'Not scheduled'
			)
		);
	}

	/**
	 * Show database migration status.
	 *
	 * Displays the current database version and any pending migrations.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json, yaml. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     # Show database status
	 *     $ wp wpr db status
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public function db_status( array $args, array $assoc_args ): void {
		$format = Utils\get_flag_value( $assoc_args, 'format', 'table' );

		$migrator = new Migrator();
		$status   = $migrator->get_status();

		WP_CLI::log( WP_CLI::colorize( '%BDatabase Migration Status:%n' ) );

		$data = [
			[
				'Property' => 'Installed Version',
				'Value'    => $status['installed_version'],
			],
			[
				'Property' => 'Current Version',
				'Value'    => $status['current_version'],
			],
			[
				'Property' => 'Needs Migration',
				'Value'    => $status['needs_migration'] ? 'Yes' : 'No',
			],
			[
				'Property' => 'Pending Migrations',
				'Value'    => empty( $status['pending_migrations'] ) ? 'None' : implode( ', ', $status['pending_migrations'] ),
			],
		];

		Utils\format_items( $format, $data, [ 'Property', 'Value' ] );

		if ( $status['needs_migration'] ) {
			WP_CLI::warning( 'Database migrations are pending. Run "wp wpr db migrate" to update.' );
		} else {
			WP_CLI::success( 'Database is up to date.' );
		}
	}

	/**
	 * Run pending database migrations.
	 *
	 * Updates the database schema to the current version.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Force re-run all migrations from the beginning.
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     # Run pending migrations
	 *     $ wp wpr db migrate
	 *
	 *     # Force re-run all migrations
	 *     $ wp wpr db migrate --force --yes
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public function db_migrate( array $args, array $assoc_args ): void {
		$force        = Utils\get_flag_value( $assoc_args, 'force', false );
		$skip_confirm = Utils\get_flag_value( $assoc_args, 'yes', false );

		$migrator = new Migrator();

		if ( $force ) {
			if ( ! $skip_confirm ) {
				WP_CLI::confirm( 'This will reset and re-run all migrations. Are you sure?' );
			}

			WP_CLI::log( 'Forcing re-run of all migrations...' );
			$result = $migrator->force_migrate_all();
		} else {
			$status = $migrator->get_status();

			if ( ! $status['needs_migration'] ) {
				WP_CLI::success( 'Database is already up to date.' );
				return;
			}

			WP_CLI::log(
				sprintf(
					'Migrating from version %s to %s...',
					$status['installed_version'],
					$status['current_version']
				)
			);

			$result = $migrator->maybe_migrate();
		}

		if ( ! $result['migrated'] ) {
			WP_CLI::warning( 'No migrations were performed.' );
			return;
		}

		// Report results
		$successful = 0;
		$failed     = 0;

		foreach ( $result['migrations_run'] as $version => $migration_result ) {
			if ( $migration_result['success'] ) {
				WP_CLI::log( sprintf( '  ✓ Migrated to %s', $version ) );
				++$successful;
			} else {
				WP_CLI::warning( sprintf( '  ✗ Migration to %s failed: %s', $version, $migration_result['error'] ?? 'Unknown error' ) );
				++$failed;
			}
		}

		WP_CLI::log( '' );

		if ( $failed > 0 ) {
			WP_CLI::error( sprintf( 'Migration completed with errors. %d successful, %d failed.', $successful, $failed ) );
		} else {
			WP_CLI::success( sprintf( 'Database migrated successfully. %d migrations completed.', $successful ) );
		}
	}

	/**
	 * Display results in a formatted table.
	 *
	 * @param array<int, array<string, mixed>> $posts Results to display.
	 */
	private function display_results_table( array $posts ): void {
		$display_data = [];

		foreach ( $posts as $post ) {
			$status_color   = 'success' === $post['status'] ? '%G' : '%R';
			$display_data[] = [
				'ID'     => $post['post_id'],
				'Title'  => substr( $post['post_title'] ?? '', 0, 40 ),
				'Status' => WP_CLI::colorize( $status_color . $post['status'] . '%n' ),
				'Date'   => $post['new_date'] ?? 'N/A',
			];
		}

		Utils\format_items( 'table', $display_data, [ 'ID', 'Title', 'Status', 'Date' ] );
	}

	/**
	 * Register CLI commands.
	 *
	 * This method should be called during plugin initialization
	 * when WP-CLI is available.
	 */
	public static function register(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		$instance = new self();

		WP_CLI::add_command( 'wpr run', [ $instance, 'run' ] );
		WP_CLI::add_command( 'wpr dry-run', [ $instance, 'dry_run' ] );
		WP_CLI::add_command( 'wpr cleanup', [ $instance, 'cleanup' ] );
		WP_CLI::add_command( 'wpr status', [ $instance, 'status' ] );
		WP_CLI::add_command( 'wpr history', [ $instance, 'history' ] );
		WP_CLI::add_command( 'wpr reschedule', [ $instance, 'reschedule' ] );
		WP_CLI::add_command( 'wpr db status', [ $instance, 'db_status' ] );
		WP_CLI::add_command( 'wpr db migrate', [ $instance, 'db_migrate' ] );
	}
}
