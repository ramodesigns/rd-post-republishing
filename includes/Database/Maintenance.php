<?php

declare(strict_types=1);

namespace WPR\Republisher\Database;

use WPR\Republisher\Logger\Logger;

/**
 * Database maintenance operations
 *
 * Handles cleanup, optimization, and maintenance tasks for plugin database tables.
 *
 * @link       https://www.paulramotowski.com
 * @since      1.0.0
 *
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Database
 */

/**
 * Maintenance class.
 *
 * Provides database maintenance functionality including data purging,
 * table optimization, and scheduled cleanup tasks.
 *
 * @since      1.0.0
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Database
 * @author     Paul Ramotowski <paulramotowski@gmail.com>
 */
class Maintenance {

	/**
	 * Default retention period in days.
	 *
	 * @since    1.0.0
	 */
	public const DEFAULT_RETENTION_DAYS = 365;

	/**
	 * Minimum retention period in days.
	 *
	 * @since    1.0.0
	 */
	public const MIN_RETENTION_DAYS = 30;

	/**
	 * Maximum retention period in days.
	 *
	 * @since    1.0.0
	 */
	public const MAX_RETENTION_DAYS = 730;

	/**
	 * Repository instance.
	 *
	 * @since    1.0.0
	 */
	private Repository $repository;

	/**
	 * Schema instance.
	 *
	 * @since    1.0.0
	 */
	private Schema $schema;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 */
	private Logger $logger;

	/**
	 * WordPress database instance.
	 *
	 * @since    1.0.0
	 */
	private \wpdb $wpdb;

	/**
	 * Initialize the maintenance handler.
	 *
	 * @since    1.0.0
	 * @param    Repository|null $repository  Optional repository instance.
	 * @param    Schema|null     $schema      Optional schema instance.
	 * @param    Logger|null     $logger      Optional logger instance.
	 */
	public function __construct(
		?Repository $repository = null,
		?Schema $schema = null,
		?Logger $logger = null
	) {
		global $wpdb;
		$this->wpdb       = $wpdb;
		$this->repository = $repository ?? new Repository();
		$this->schema     = $schema ?? new Schema();
		$this->logger     = $logger ?? Logger::get_instance( $this->repository );
	}

	/**
	 * Run all maintenance tasks.
	 *
	 * @since    1.0.0
	 * @param    int $retention_days  Days to retain data.
	 * @return   array<string, mixed>  Results of maintenance operations.
	 */
	public function run_maintenance( int $retention_days = self::DEFAULT_RETENTION_DAYS ): array {
		$start_time = $this->logger->start_timer();

		$results = [
			'purge'      => $this->purge_old_records( $retention_days ),
			'cleanup'    => $this->cleanup_orphaned_data(),
			'optimize'   => $this->optimize_tables(),
			'transients' => $this->cleanup_transients(),
		];

		$results['duration']  = $this->logger->end_timer( $start_time, 'Database maintenance' );
		$results['timestamp'] = current_time( 'mysql' );

		$this->logger->info( 'Database maintenance completed', $results );

		return $results;
	}

	/**
	 * Purge old records based on retention policy.
	 *
	 * @since    1.0.0
	 * @param    int $retention_days  Number of days to retain records.
	 * @return   array<string, int>  Count of deleted records per table.
	 */
	public function purge_old_records( int $retention_days = self::DEFAULT_RETENTION_DAYS ): array {
		$retention_days = max( self::MIN_RETENTION_DAYS, min( self::MAX_RETENTION_DAYS, $retention_days ) );
		$cutoff_date    = wp_date( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

		$this->logger->debug( sprintf( 'Purging records older than %s', $cutoff_date ) );

		$deleted = $this->repository->purge_old_records( $retention_days );

		$this->logger->db_event(
			'purge',
			'all',
			[
				'retention_days' => $retention_days,
				'cutoff_date'    => $cutoff_date,
				'deleted'        => $deleted,
			]
		);

		return $deleted;
	}

	/**
	 * Cleanup orphaned data.
	 *
	 * Removes history records for posts that no longer exist.
	 *
	 * @since    1.0.0
	 * @return   array<string, int>  Cleanup results.
	 */
	public function cleanup_orphaned_data(): array {
		$history_table = $this->schema->get_table_name( 'history' );
		$posts_table   = $this->wpdb->posts;

		if ( null === $history_table ) {
			return [ 'orphaned_history' => 0 ];
		}

		// Find and delete history records for non-existent posts
		$query = "DELETE h FROM {$history_table} h
			LEFT JOIN {$posts_table} p ON h.post_id = p.ID
			WHERE p.ID IS NULL";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$deleted = $this->wpdb->query( $query );

		$result = [
			'orphaned_history' => max( 0, (int) $deleted ),
		];

		if ( $deleted > 0 ) {
			$this->logger->db_event( 'cleanup_orphaned', $history_table, $result );
		}

		return $result;
	}

	/**
	 * Optimize all plugin tables.
	 *
	 * @since    1.0.0
	 * @return   array<string, bool>  Optimization results per table.
	 */
	public function optimize_tables(): array {
		$results = $this->schema->optimize_tables();

		$this->logger->db_event( 'optimize', 'all', $results );

		return $results;
	}

	/**
	 * Cleanup plugin transients.
	 *
	 * @since    1.0.0
	 * @return   array<string, int>  Cleanup results.
	 */
	public function cleanup_transients(): array {
		$deleted = 0;

		// Clean up expired retry count transients
		$transients_query = $this->wpdb->prepare(
			"SELECT option_name FROM {$this->wpdb->options}
			WHERE option_name LIKE %s
			AND option_name NOT LIKE %s",
			$this->wpdb->esc_like( '_transient_wpr_' ) . '%',
			$this->wpdb->esc_like( '_transient_timeout_wpr_' ) . '%'
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$transients = $this->wpdb->get_col( $transients_query );

		foreach ( $transients as $transient_option ) {
			$transient_name = str_replace( '_transient_', '', $transient_option );

			// Check if the transient is expired
			$timeout = get_option( '_transient_timeout_' . $transient_name );

			if ( false !== $timeout && (int) $timeout < time() ) {
				delete_transient( $transient_name );
				++$deleted;
			}
		}

		$result = [ 'expired_transients' => $deleted ];

		if ( $deleted > 0 ) {
			$this->logger->db_event( 'cleanup_transients', 'options', $result );
		}

		return $result;
	}

	/**
	 * Get maintenance status.
	 *
	 * @since    1.0.0
	 * @return   array<string, mixed>  Maintenance status information.
	 */
	public function get_status(): array {
		$table_status = $this->schema->get_table_status();
		$row_counts   = $this->schema->get_row_counts();

		$total_rows = array_sum( $row_counts );
		$total_size = array_sum( array_column( $table_status, 'size_kb' ) );

		// Get oldest record dates
		$oldest_history = $this->get_oldest_record( 'history', 'created_at' );
		$oldest_audit   = $this->get_oldest_record( 'audit', 'timestamp' );
		$oldest_api_log = $this->get_oldest_record( 'api_log', 'request_timestamp' );

		return [
			'tables'         => $table_status,
			'row_counts'     => $row_counts,
			'total_rows'     => $total_rows,
			'total_size_kb'  => $total_size,
			'oldest_records' => [
				'history' => $oldest_history,
				'audit'   => $oldest_audit,
				'api_log' => $oldest_api_log,
			],
			'schema_version' => $this->schema->get_installed_version(),
			'needs_update'   => $this->schema->needs_update(),
		];
	}

	/**
	 * Get the oldest record date from a table.
	 *
	 * @since    1.0.0
	 * @param    string $table_key   The table key.
	 * @param    string $date_column The date column name.
	 */
	private function get_oldest_record( string $table_key, string $date_column ): ?string {
		$table = $this->schema->get_table_name( $table_key );

		if ( null === $table || ! $this->schema->table_exists( $table_key ) ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $this->wpdb->get_var( "SELECT MIN({$date_column}) FROM {$table}" );

		return null !== $result ? (string) $result : null;
	}

	/**
	 * Estimate records to be purged.
	 *
	 * @since    1.0.0
	 * @param    int $retention_days  Retention period in days.
	 * @return   array<string, int>  Estimated record counts per table.
	 */
	public function estimate_purge( int $retention_days = self::DEFAULT_RETENTION_DAYS ): array {
		$retention_days = max( self::MIN_RETENTION_DAYS, min( self::MAX_RETENTION_DAYS, $retention_days ) );
		$cutoff_date    = wp_date( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

		$estimates = [];

		// History table
		$history_table = $this->schema->get_table_name( 'history' );
		if ( null !== $history_table && $this->schema->table_exists( 'history' ) ) {
			$query = $this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$history_table} WHERE created_at < %s",
				$cutoff_date
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$estimates['history'] = (int) $this->wpdb->get_var( $query );
		}

		// Audit table
		$audit_table = $this->schema->get_table_name( 'audit' );
		if ( null !== $audit_table && $this->schema->table_exists( 'audit' ) ) {
			$query = $this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$audit_table} WHERE timestamp < %s",
				$cutoff_date
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$estimates['audit'] = (int) $this->wpdb->get_var( $query );
		}

		// API log table
		$api_log_table = $this->schema->get_table_name( 'api_log' );
		if ( null !== $api_log_table && $this->schema->table_exists( 'api_log' ) ) {
			$query = $this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$api_log_table} WHERE request_timestamp < %s",
				$cutoff_date
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$estimates['api_log'] = (int) $this->wpdb->get_var( $query );
		}

		$estimates['total'] = array_sum( $estimates );

		return $estimates;
	}

	/**
	 * Get data retention settings.
	 *
	 * @since    1.0.0
	 * @return   array<string, mixed>  Retention settings.
	 */
	public function get_retention_settings(): array {
		$settings = $this->repository->get_settings();

		return [
			'retention_days' => $settings['data_retention_days'] ?? self::DEFAULT_RETENTION_DAYS,
			'min_days'       => self::MIN_RETENTION_DAYS,
			'max_days'       => self::MAX_RETENTION_DAYS,
			'default_days'   => self::DEFAULT_RETENTION_DAYS,
		];
	}

	/**
	 * Verify database integrity.
	 *
	 * @since    1.0.0
	 * @return   array<string, array<string, mixed>>  Verification results.
	 */
	public function verify_integrity(): array {
		$results = [];

		foreach ( [ 'history', 'audit', 'api_log' ] as $table_key ) {
			$results[ $table_key ] = $this->schema->verify_table_structure( $table_key );
		}

		return $results;
	}

	/**
	 * Truncate a specific table.
	 *
	 * @since    1.0.0
	 * @param    string $table_key  The table key.
	 * @return   bool  True if successful.
	 */
	public function truncate_table( string $table_key ): bool {
		$table = $this->schema->get_table_name( $table_key );

		if ( null === $table || ! $this->schema->table_exists( $table_key ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $this->wpdb->query( "TRUNCATE TABLE {$table}" );

		if ( false !== $result ) {
			$this->logger->warning( sprintf( 'Table %s truncated', $table_key ) );
		}

		return false !== $result;
	}

	/**
	 * Export table data as CSV.
	 *
	 * @since    1.0.0
	 * @param    string               $table_key  The table key.
	 * @param    array<string, mixed> $filters    Optional filters.
	 * @return   string|false  CSV content or false on failure.
	 */
	public function export_table_csv( string $table_key, array $filters = [] ): string|false {
		$table = $this->schema->get_table_name( $table_key );

		if ( null === $table || ! $this->schema->table_exists( $table_key ) ) {
			return false;
		}

		$where  = '1=1';
		$values = [];

		// Apply date filters if provided
		if ( ! empty( $filters['date_from'] ) ) {
			$date_column = $this->get_date_column( $table_key );
			$where      .= " AND {$date_column} >= %s";
			$values[]    = $filters['date_from'];
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$date_column = $this->get_date_column( $table_key );
			$where      .= " AND {$date_column} <= %s";
			$values[]    = $filters['date_to'];
		}

		$query = "SELECT * FROM {$table} WHERE {$where}";

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$query = $this->wpdb->prepare( $query, $values );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $this->wpdb->get_results( $query, ARRAY_A );

		if ( empty( $results ) ) {
			return '';
		}

		// Build CSV
		$output = fopen( 'php://temp', 'r+' );

		if ( false === $output ) {
			return false;
		}

		// Headers
		fputcsv( $output, array_keys( $results[0] ) );

		// Data rows
		foreach ( $results as $row ) {
			fputcsv( $output, $row );
		}

		rewind( $output );
		$csv = stream_get_contents( $output );
		fclose( $output );

		return false !== $csv ? $csv : false;
	}

	/**
	 * Get the date column for a table.
	 *
	 * @since    1.0.0
	 * @param    string $table_key  The table key.
	 */
	private function get_date_column( string $table_key ): string {
		$columns = [
			'history' => 'created_at',
			'audit'   => 'timestamp',
			'api_log' => 'request_timestamp',
		];

		return $columns[ $table_key ] ?? 'created_at';
	}
}
