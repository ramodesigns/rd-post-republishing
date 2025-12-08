<?php

declare(strict_types=1);

namespace WPR\Republisher\Database;

/**
 * Database schema management
 *
 * Handles all database schema operations including table creation,
 * version tracking, and schema validation.
 *
 * @link       https://www.paulramotowski.com
 * @since      1.0.0
 *
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Database
 */

/**
 * Schema class.
 *
 * Manages database table schemas and provides utilities for
 * schema verification and updates.
 *
 * @since      1.0.0
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Database
 * @author     Paul Ramotowski <paulramotowski@gmail.com>
 */
class Schema {

	/**
	 * Current schema version.
	 *
	 * @since    1.0.0
	 */
	public const VERSION = '1.0.0';

	/**
	 * Option key for storing schema version.
	 *
	 * @since    1.0.0
	 */
	private const VERSION_OPTION = 'wpr_db_version';

	/**
	 * WordPress database instance.
	 *
	 * @since    1.0.0
	 */
	private \wpdb $wpdb;

	/**
	 * Table name prefix.
	 *
	 * @since    1.0.0
	 */
	private readonly string $prefix;

	/**
	 * Table names.
	 *
	 * @since    1.0.0
	 * @var      array<string, string>
	 */
	private readonly array $tables;

	/**
	 * Initialize the schema manager.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->prefix = $wpdb->prefix . 'wpr_';

		$this->tables = [
			'history' => $this->prefix . 'history',
			'audit'   => $this->prefix . 'audit',
			'api_log' => $this->prefix . 'api_log',
		];
	}

	/**
	 * Create all plugin database tables.
	 *
	 * @since    1.0.0
	 * @return   bool  True if all tables were created successfully.
	 */
	public function create_tables(): bool {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $this->wpdb->get_charset_collate();

		$results = [];
		$results[] = $this->create_history_table( $charset_collate );
		$results[] = $this->create_audit_table( $charset_collate );
		$results[] = $this->create_api_log_table( $charset_collate );

		// Update schema version
		update_option( self::VERSION_OPTION, self::VERSION );

		return ! in_array( false, $results, true );
	}

	/**
	 * Create the history table.
	 *
	 * @since    1.0.0
	 * @param    string  $charset_collate  Database charset/collation.
	 */
	private function create_history_table( string $charset_collate ): bool {
		$table = $this->tables['history'];

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			post_type varchar(20) NOT NULL,
			original_date datetime NOT NULL,
			republish_date datetime NOT NULL,
			status enum('success', 'failed', 'retrying') NOT NULL DEFAULT 'success',
			error_message text,
			execution_time float DEFAULT NULL,
			triggered_by enum('cron', 'api', 'manual') NOT NULL DEFAULT 'cron',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_post_id (post_id),
			KEY idx_republish_date (republish_date),
			KEY idx_status (status),
			KEY idx_created_at (created_at),
			KEY idx_post_status_date (post_id, status, republish_date)
		) {$charset_collate};";

		dbDelta( $sql );

		return $this->table_exists( 'history' );
	}

	/**
	 * Create the audit table.
	 *
	 * @since    1.0.0
	 * @param    string  $charset_collate  Database charset/collation.
	 */
	private function create_audit_table( string $charset_collate ): bool {
		$table = $this->tables['audit'];

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(100) NOT NULL,
			setting_key varchar(255) DEFAULT NULL,
			old_value longtext,
			new_value longtext,
			ip_address varchar(45) DEFAULT NULL,
			user_agent text,
			timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_user_id (user_id),
			KEY idx_action (action),
			KEY idx_timestamp (timestamp),
			KEY idx_user_action (user_id, action)
		) {$charset_collate};";

		dbDelta( $sql );

		return $this->table_exists( 'audit' );
	}

	/**
	 * Create the API log table.
	 *
	 * @since    1.0.0
	 * @param    string  $charset_collate  Database charset/collation.
	 */
	private function create_api_log_table( string $charset_collate ): bool {
		$table = $this->tables['api_log'];

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ip_address varchar(45) NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			endpoint varchar(100) NOT NULL,
			response_code smallint(3) unsigned NOT NULL,
			request_timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_ip_address (ip_address),
			KEY idx_user_id (user_id),
			KEY idx_endpoint (endpoint),
			KEY idx_request_timestamp (request_timestamp),
			KEY idx_rate_limit (user_id, endpoint, response_code, request_timestamp),
			KEY idx_ip_rate_limit (ip_address, endpoint, response_code, request_timestamp)
		) {$charset_collate};";

		dbDelta( $sql );

		return $this->table_exists( 'api_log' );
	}

	/**
	 * Drop all plugin database tables.
	 *
	 * @since    1.0.0
	 */
	public function drop_tables(): bool {
		$results = [];

		foreach ( $this->tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$results[] = $this->wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		delete_option( self::VERSION_OPTION );

		return ! in_array( false, $results, true );
	}

	/**
	 * Check if a table exists.
	 *
	 * @since    1.0.0
	 * @param    string  $table_key  The table key (history, audit, api_log).
	 */
	public function table_exists( string $table_key ): bool {
		if ( ! isset( $this->tables[ $table_key ] ) ) {
			return false;
		}

		$table = $this->tables[ $table_key ];
		$query = $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $this->wpdb->get_var( $query ) === $table;
	}

	/**
	 * Check if all tables exist.
	 *
	 * @since    1.0.0
	 */
	public function all_tables_exist(): bool {
		foreach ( array_keys( $this->tables ) as $key ) {
			if ( ! $this->table_exists( $key ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Get the current installed schema version.
	 *
	 * @since    1.0.0
	 */
	public function get_installed_version(): string {
		return get_option( self::VERSION_OPTION, '0.0.0' );
	}

	/**
	 * Check if schema needs updating.
	 *
	 * @since    1.0.0
	 */
	public function needs_update(): bool {
		return version_compare( $this->get_installed_version(), self::VERSION, '<' );
	}

	/**
	 * Get table name by key.
	 *
	 * @since    1.0.0
	 * @param    string  $key  The table key.
	 */
	public function get_table_name( string $key ): ?string {
		return $this->tables[ $key ] ?? null;
	}

	/**
	 * Get all table names.
	 *
	 * @since    1.0.0
	 * @return   array<string, string>
	 */
	public function get_all_tables(): array {
		return $this->tables;
	}

	/**
	 * Get table status information.
	 *
	 * @since    1.0.0
	 * @return   array<string, array<string, mixed>>
	 */
	public function get_table_status(): array {
		$status = [];

		foreach ( $this->tables as $key => $table ) {
			$exists = $this->table_exists( $key );
			$row_count = 0;
			$data_size = 0;

			if ( $exists ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$row_count = (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

				// Get table size
				$size_query = $this->wpdb->prepare(
					"SELECT
						ROUND(((data_length + index_length) / 1024), 2) AS size_kb
					FROM information_schema.TABLES
					WHERE table_schema = %s
					AND table_name = %s",
					DB_NAME,
					$table
				);

				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$data_size = (float) $this->wpdb->get_var( $size_query );
			}

			$status[ $key ] = [
				'table'     => $table,
				'exists'    => $exists,
				'row_count' => $row_count,
				'size_kb'   => $data_size,
			];
		}

		return $status;
	}

	/**
	 * Verify table structure matches expected schema.
	 *
	 * @since    1.0.0
	 * @param    string  $table_key  The table key.
	 * @return   array<string, mixed>  Verification results.
	 */
	public function verify_table_structure( string $table_key ): array {
		if ( ! $this->table_exists( $table_key ) ) {
			return [
				'valid'   => false,
				'error'   => 'Table does not exist',
				'columns' => [],
			];
		}

		$table = $this->tables[ $table_key ];
		$expected_columns = $this->get_expected_columns( $table_key );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$actual_columns = $this->wpdb->get_results( "DESCRIBE {$table}", ARRAY_A );

		$actual_column_names = array_column( $actual_columns, 'Field' );
		$missing = array_diff( $expected_columns, $actual_column_names );
		$extra = array_diff( $actual_column_names, $expected_columns );

		return [
			'valid'           => empty( $missing ),
			'expected'        => $expected_columns,
			'actual'          => $actual_column_names,
			'missing_columns' => $missing,
			'extra_columns'   => $extra,
		];
	}

	/**
	 * Get expected columns for a table.
	 *
	 * @since    1.0.0
	 * @param    string  $table_key  The table key.
	 * @return   array<int, string>
	 */
	private function get_expected_columns( string $table_key ): array {
		$columns = [
			'history' => [
				'id', 'post_id', 'post_type', 'original_date', 'republish_date',
				'status', 'error_message', 'execution_time', 'triggered_by', 'created_at',
			],
			'audit' => [
				'id', 'user_id', 'action', 'setting_key', 'old_value',
				'new_value', 'ip_address', 'user_agent', 'timestamp',
			],
			'api_log' => [
				'id', 'ip_address', 'user_id', 'endpoint', 'response_code', 'request_timestamp',
			],
		];

		return $columns[ $table_key ] ?? [];
	}

	/**
	 * Optimize all plugin tables.
	 *
	 * @since    1.0.0
	 * @return   array<string, bool>  Optimization results per table.
	 */
	public function optimize_tables(): array {
		$results = [];

		foreach ( $this->tables as $key => $table ) {
			if ( $this->table_exists( $key ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$result = $this->wpdb->query( "OPTIMIZE TABLE {$table}" );
				$results[ $key ] = false !== $result;
			}
		}

		return $results;
	}

	/**
	 * Get table row counts.
	 *
	 * @since    1.0.0
	 * @return   array<string, int>
	 */
	public function get_row_counts(): array {
		$counts = [];

		foreach ( $this->tables as $key => $table ) {
			if ( $this->table_exists( $key ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$counts[ $key ] = (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
			} else {
				$counts[ $key ] = 0;
			}
		}

		return $counts;
	}
}
