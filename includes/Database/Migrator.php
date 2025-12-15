<?php

declare(strict_types=1);

namespace WPR\Republisher\Database;

/**
 * Database migration handler
 *
 * Manages database schema updates when the plugin is updated.
 * Uses a version-based migration system to apply changes sequentially.
 *
 * @link       https://www.paulramotowski.com
 * @since      1.0.0
 *
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Database
 */

/**
 * Migrator class.
 *
 * Handles database migrations for plugin updates.
 *
 * @since      1.0.0
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Database
 * @author     Paul Ramotowski <paulramotowski@gmail.com>
 */
class Migrator {

	/**
	 * The option name for storing the database version.
	 *
	 * @since    1.0.0
	 */
	private const DB_VERSION_OPTION = 'wpr_db_version';

	/**
	 * The current database schema version.
	 *
	 * This should be updated when new migrations are added.
	 *
	 * @since    1.0.0
	 */
	public const CURRENT_DB_VERSION = '1.0.0';

	/**
	 * List of migrations in order of execution.
	 *
	 * Each migration is keyed by version number and contains a callable.
	 * Migrations are run sequentially for versions greater than the installed version.
	 *
	 * @since    1.0.0
	 * @var      array<string, callable>
	 */
	private array $migrations = [];

	/**
	 * WordPress database instance.
	 *
	 * @since    1.0.0
	 * @var      \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Initialize the migrator.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;

		$this->register_migrations();
	}

	/**
	 * Register all available migrations.
	 *
	 * Add new migrations here as the schema evolves. Each migration should
	 * be idempotent - safe to run multiple times without side effects.
	 *
	 * Example for adding a new migration:
	 * ```php
	 * $this->migrations['1.1.0'] = [ $this, 'migrate_to_1_1_0' ];
	 * ```
	 *
	 * @since    1.0.0
	 */
	private function register_migrations(): void {
		// Initial version - creates base tables (handled by Activator)
		$this->migrations['1.0.0'] = [ $this, 'migrate_to_1_0_0' ];

		// Future migrations would be registered here:
		// $this->migrations['1.1.0'] = [ $this, 'migrate_to_1_1_0' ];
		// $this->migrations['1.2.0'] = [ $this, 'migrate_to_1_2_0' ];
	}

	/**
	 * Check if migrations are needed and run them.
	 *
	 * This should be called on plugin initialization (not just activation)
	 * to ensure migrations run when the plugin is updated.
	 *
	 * @since    1.0.0
	 * @return   array<string, mixed>  Migration result with keys: 'migrated', 'from_version', 'to_version', 'migrations_run'.
	 */
	public function maybe_migrate(): array {
		$installed_version = $this->get_installed_version();
		$current_version   = self::CURRENT_DB_VERSION;

		$result = [
			'migrated'       => false,
			'from_version'   => $installed_version,
			'to_version'     => $current_version,
			'migrations_run' => [],
		];

		// No migration needed
		if ( version_compare( $installed_version, $current_version, '>=' ) ) {
			return $result;
		}

		// Run migrations
		$migrations_to_run = $this->get_pending_migrations( $installed_version );

		foreach ( $migrations_to_run as $version => $callback ) {
			$migration_result                     = $this->run_migration( $version, $callback );
			$result['migrations_run'][ $version ] = $migration_result;

			if ( ! $migration_result['success'] ) {
				// Stop on first failed migration
				$this->log_migration_error( $version, $migration_result['error'] ?? 'Unknown error' );
				break;
			}

			// Update version after each successful migration
			$this->update_installed_version( $version );
		}

		// Final version update
		if ( ! empty( $result['migrations_run'] ) ) {
			$result['migrated'] = true;
			$this->update_installed_version( $current_version );
		}

		return $result;
	}

	/**
	 * Get the currently installed database version.
	 *
	 * @since    1.0.0
	 * @return   string  The installed version, or '0.0.0' if not set.
	 */
	public function get_installed_version(): string {
		return get_option( self::DB_VERSION_OPTION, '0.0.0' );
	}

	/**
	 * Update the installed database version.
	 *
	 * @since    1.0.0
	 * @param    string $version  The version to set.
	 */
	private function update_installed_version( string $version ): void {
		update_option( self::DB_VERSION_OPTION, $version );
	}

	/**
	 * Get migrations that need to be run.
	 *
	 * @since    1.0.0
	 * @param    string $from_version  The currently installed version.
	 * @return   array<string, callable>  Migrations to run, sorted by version.
	 */
	private function get_pending_migrations( string $from_version ): array {
		$pending = [];

		foreach ( $this->migrations as $version => $callback ) {
			if ( version_compare( $version, $from_version, '>' ) ) {
				$pending[ $version ] = $callback;
			}
		}

		// Sort by version number
		uksort( $pending, 'version_compare' );

		return $pending;
	}

	/**
	 * Run a single migration.
	 *
	 * @since    1.0.0
	 * @param    string   $version   The migration version.
	 * @param    callable $callback  The migration callback.
	 * @return   array<string, mixed>  Result with 'success' and optional 'error' keys.
	 */
	private function run_migration( string $version, callable $callback ): array {
		try {
			$this->log_migration_start( $version );

			call_user_func( $callback );

			$this->log_migration_success( $version );

			return [ 'success' => true ];
		} catch ( \Throwable $e ) {
			return [
				'success' => false,
				'error'   => $e->getMessage(),
			];
		}
	}

	/**
	 * Migration to version 1.0.0 (initial schema).
	 *
	 * This migration creates the initial database tables if they don't exist.
	 * It's primarily for ensuring tables exist even if activation didn't run properly.
	 *
	 * @since    1.0.0
	 */
	private function migrate_to_1_0_0(): void {
		$charset_collate = $this->wpdb->get_charset_collate();

		// History table
		$table_history = $this->wpdb->prefix . 'wpr_history';
		if ( $this->table_exists( $table_history ) ) {
			return; // Tables already exist
		}

		$sql_history = "CREATE TABLE {$table_history} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			post_id bigint(20) NOT NULL,
			post_type varchar(20) NOT NULL,
			original_date datetime NOT NULL,
			republish_date datetime NOT NULL,
			status enum('success', 'failed', 'retrying') NOT NULL,
			error_message text,
			execution_time float,
			triggered_by enum('cron', 'api', 'manual') NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY republish_date (republish_date),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		// Audit table
		$table_audit = $this->wpdb->prefix . 'wpr_audit';
		$sql_audit   = "CREATE TABLE {$table_audit} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			action varchar(100) NOT NULL,
			setting_key varchar(255),
			old_value text,
			new_value text,
			ip_address varchar(45),
			user_agent text,
			timestamp datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY action (action),
			KEY timestamp (timestamp)
		) {$charset_collate};";

		// API log table
		$table_api_log = $this->wpdb->prefix . 'wpr_api_log';
		$sql_api_log   = "CREATE TABLE {$table_api_log} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			ip_address varchar(45) NOT NULL,
			user_id bigint(20),
			endpoint varchar(100) NOT NULL,
			response_code int(3) NOT NULL,
			request_timestamp datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY ip_address (ip_address),
			KEY request_timestamp (request_timestamp)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_history );
		dbDelta( $sql_audit );
		dbDelta( $sql_api_log );
	}

	/**
	 * Check if a table exists.
	 *
	 * @since    1.0.0
	 * @param    string $table_name  The table name to check.
	 * @return   bool
	 */
	private function table_exists( string $table_name ): bool {
		$result = $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		return null !== $result;
	}

	/**
	 * Check if a column exists in a table.
	 *
	 * Useful for migrations that add new columns.
	 *
	 * @since    1.0.0
	 * @param    string $table_name   The table name.
	 * @param    string $column_name  The column name.
	 * @return   bool
	 */
	protected function column_exists( string $table_name, string $column_name ): bool {
		$columns = $this->wpdb->get_col(
			$this->wpdb->prepare(
				'SHOW COLUMNS FROM %1$s LIKE %s',
				$column_name
			),
			0
		);

		return in_array( $column_name, $columns, true );
	}

	/**
	 * Add a column to a table if it doesn't exist.
	 *
	 * Utility method for migrations that add new columns.
	 *
	 * @since    1.0.0
	 * @param    string $table_name   The table name.
	 * @param    string $column_name  The column name.
	 * @param    string $column_def   The column definition (e.g., "VARCHAR(255) DEFAULT NULL").
	 * @param    string $after        Optional column to add after.
	 * @return   bool    True if column was added or already exists.
	 */
	protected function add_column_if_not_exists( string $table_name, string $column_name, string $column_def, string $after = '' ): bool {
		if ( $this->column_exists( $table_name, $column_name ) ) {
			return true;
		}

		$after_clause = $after ? " AFTER {$after}" : '';
		$sql          = "ALTER TABLE {$table_name} ADD COLUMN {$column_name} {$column_def}{$after_clause}";

		return false !== $this->wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Log migration start.
	 *
	 * @since    1.0.0
	 * @param    string $version  The migration version.
	 */
	private function log_migration_start( string $version ): void {
		$this->log( sprintf( 'Starting migration to version %s', $version ) );
	}

	/**
	 * Log migration success.
	 *
	 * @since    1.0.0
	 * @param    string $version  The migration version.
	 */
	private function log_migration_success( string $version ): void {
		$this->log( sprintf( 'Successfully migrated to version %s', $version ) );
	}

	/**
	 * Log migration error.
	 *
	 * @since    1.0.0
	 * @param    string $version  The migration version.
	 * @param    string $error    The error message.
	 */
	private function log_migration_error( string $version, string $error ): void {
		$this->log( sprintf( 'Migration to version %s failed: %s', $version, $error ), 'error' );
	}

	/**
	 * Log a message.
	 *
	 * @since    1.0.0
	 * @param    string $message  The message to log.
	 * @param    string $level    Log level ('info' or 'error').
	 */
	private function log( string $message, string $level = 'info' ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$prefix = 'error' === $level ? '[WPR Migration ERROR]' : '[WPR Migration]';
			error_log( $prefix . ' ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Get migration status information.
	 *
	 * Useful for admin display and debugging.
	 *
	 * @since    1.0.0
	 * @return   array<string, mixed>
	 */
	public function get_status(): array {
		$installed = $this->get_installed_version();
		$current   = self::CURRENT_DB_VERSION;

		return [
			'installed_version'    => $installed,
			'current_version'      => $current,
			'needs_migration'      => version_compare( $installed, $current, '<' ),
			'available_migrations' => array_keys( $this->migrations ),
			'pending_migrations'   => array_keys( $this->get_pending_migrations( $installed ) ),
		];
	}

	/**
	 * Force re-run all migrations.
	 *
	 * Use with caution - primarily for development/debugging.
	 *
	 * @since    1.0.0
	 * @return   array<string, mixed>
	 */
	public function force_migrate_all(): array {
		delete_option( self::DB_VERSION_OPTION );
		return $this->maybe_migrate();
	}
}
