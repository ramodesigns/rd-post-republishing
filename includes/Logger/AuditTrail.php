<?php

declare(strict_types=1);

namespace WPR\Republisher\Logger;

use WPR\Republisher\Database\Repository;

/**
 * Audit trail logger for admin changes
 *
 * Provides detailed tracking of all administrative actions and settings
 * changes with full context including user, IP, and user agent.
 *
 * @link       https://www.paulramotowski.com
 * @since      1.0.0
 *
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Logger
 */

/**
 * AuditTrail class.
 *
 * Handles audit logging for all administrative operations.
 *
 * @since      1.0.0
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Logger
 * @author     Paul Ramotowski <paulramotowski@gmail.com>
 */
class AuditTrail {

	/**
	 * Action types for audit logging.
	 *
	 * @since    1.0.0
	 */
	public const ACTION_SETTINGS_UPDATED   = 'settings_updated';
	public const ACTION_SETTINGS_RESET     = 'settings_reset';
	public const ACTION_MANUAL_TRIGGER     = 'manual_trigger';
	public const ACTION_DRY_RUN            = 'dry_run';
	public const ACTION_CRON_TOGGLE        = 'cron_toggle';
	public const ACTION_DATA_EXPORT        = 'data_export';
	public const ACTION_DATA_PURGE         = 'data_purge';
	public const ACTION_PLUGIN_ACTIVATED   = 'plugin_activated';
	public const ACTION_PLUGIN_DEACTIVATED = 'plugin_deactivated';

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
	 * Initialize the audit trail.
	 *
	 * @since    1.0.0
	 * @param    Repository|null $repository  Optional repository instance.
	 * @param    Logger|null     $logger      Optional logger instance.
	 */
	public function __construct( ?Repository $repository = null, ?Logger $logger = null ) {
		$this->repository = $repository ?? new Repository();
		$this->logger     = $logger ?? Logger::get_instance( $this->repository );
	}

	/**
	 * Log a settings update.
	 *
	 * @since    1.0.0
	 * @param    array<string, mixed> $old_settings  Previous settings.
	 * @param    array<string, mixed> $new_settings  New settings.
	 * @return   int|false  Audit log ID or false on failure.
	 */
	public function log_settings_update( array $old_settings, array $new_settings ): int|false {
		$changes = $this->diff_settings( $old_settings, $new_settings );

		if ( empty( $changes ) ) {
			return false;
		}

		$this->logger->info(
			'Settings updated',
			[
				'changes' => $changes,
				'user_id' => get_current_user_id(),
			]
		);

		return $this->repository->log_audit(
			self::ACTION_SETTINGS_UPDATED,
			null,
			wp_json_encode( $old_settings ) ?: null,
			wp_json_encode( $new_settings ) ?: null
		);
	}

	/**
	 * Log a specific setting change.
	 *
	 * @since    1.0.0
	 * @param    string $setting_key  The setting key.
	 * @param    mixed  $old_value    Previous value.
	 * @param    mixed  $new_value    New value.
	 * @return   int|false  Audit log ID or false on failure.
	 */
	public function log_setting_change( string $setting_key, mixed $old_value, mixed $new_value ): int|false {
		$this->logger->debug(
			sprintf( 'Setting changed: %s', $setting_key ),
			[
				'key'       => $setting_key,
				'old_value' => $old_value,
				'new_value' => $new_value,
			]
		);

		$old_encoded = is_scalar( $old_value ) ? (string) $old_value : wp_json_encode( $old_value );
		$new_encoded = is_scalar( $new_value ) ? (string) $new_value : wp_json_encode( $new_value );
		return $this->repository->log_audit(
			self::ACTION_SETTINGS_UPDATED,
			$setting_key,
			false !== $old_encoded ? $old_encoded : null,
			false !== $new_encoded ? $new_encoded : null
		);
	}

	/**
	 * Log a manual republishing trigger.
	 *
	 * @since    1.0.0
	 * @param    int                  $post_count  Number of posts republished.
	 * @param    array<string, mixed> $context     Additional context.
	 * @return   int|false  Audit log ID or false on failure.
	 */
	public function log_manual_trigger( int $post_count, array $context = [] ): int|false {
		$this->logger->info(
			sprintf( 'Manual republishing triggered: %d posts', $post_count ),
			$context
		);

		return $this->repository->log_audit(
			self::ACTION_MANUAL_TRIGGER,
			null,
			null,
			wp_json_encode( array_merge( [ 'post_count' => $post_count ], $context ) ) ?: null
		);
	}

	/**
	 * Log a dry run execution.
	 *
	 * @since    1.0.0
	 * @param    int                  $post_count  Number of posts that would be republished.
	 * @param    array<string, mixed> $context     Additional context.
	 * @return   int|false  Audit log ID or false on failure.
	 */
	public function log_dry_run( int $post_count, array $context = [] ): int|false {
		$this->logger->info(
			sprintf( 'Dry run executed: %d posts would be republished', $post_count ),
			$context
		);

		return $this->repository->log_audit(
			self::ACTION_DRY_RUN,
			null,
			null,
			wp_json_encode( array_merge( [ 'post_count' => $post_count ], $context ) ) ?: null
		);
	}

	/**
	 * Log a cron toggle action.
	 *
	 * @since    1.0.0
	 * @param    bool $enabled  Whether cron was enabled or disabled.
	 * @return   int|false  Audit log ID or false on failure.
	 */
	public function log_cron_toggle( bool $enabled ): int|false {
		$status = $enabled ? 'enabled' : 'disabled';

		$this->logger->info( sprintf( 'WP Cron %s', $status ) );

		return $this->repository->log_audit(
			self::ACTION_CRON_TOGGLE,
			'wp_cron_enabled',
			$enabled ? '0' : '1',
			$enabled ? '1' : '0'
		);
	}

	/**
	 * Log a data export action.
	 *
	 * @since    1.0.0
	 * @param    string $export_type  Type of export (history, audit, etc.).
	 * @param    int    $record_count Number of records exported.
	 * @return   int|false  Audit log ID or false on failure.
	 */
	public function log_data_export( string $export_type, int $record_count ): int|false {
		$this->logger->info(
			sprintf( 'Data export: %d %s records', $record_count, $export_type ),
			[
				'type'  => $export_type,
				'count' => $record_count,
			]
		);

		return $this->repository->log_audit(
			self::ACTION_DATA_EXPORT,
			$export_type,
			null,
			wp_json_encode(
				[
					'type'  => $export_type,
					'count' => $record_count,
				]
			) ?: null
		);
	}

	/**
	 * Log a data purge action.
	 *
	 * @since    1.0.0
	 * @param    array<string, int> $deleted_counts  Counts of deleted records by table.
	 * @return   int|false  Audit log ID or false on failure.
	 */
	public function log_data_purge( array $deleted_counts ): int|false {
		$total = array_sum( $deleted_counts );

		$this->logger->info(
			sprintf( 'Data purge: %d total records deleted', $total ),
			$deleted_counts
		);

		return $this->repository->log_audit(
			self::ACTION_DATA_PURGE,
			null,
			null,
			wp_json_encode( $deleted_counts ) ?: null
		);
	}

	/**
	 * Log plugin activation.
	 *
	 * @since    1.0.0
	 * @return   int|false  Audit log ID or false on failure.
	 */
	public function log_plugin_activated(): int|false {
		$this->logger->info( 'Plugin activated' );

		return $this->repository->log_audit(
			self::ACTION_PLUGIN_ACTIVATED,
			null,
			null,
			wp_json_encode(
				[
					'version'     => defined( 'WPR_VERSION' ) ? WPR_VERSION : '1.0.0',
					'php_version' => PHP_VERSION,
					'wp_version'  => get_bloginfo( 'version' ),
				]
			) ?: null
		);
	}

	/**
	 * Log plugin deactivation.
	 *
	 * @since    1.0.0
	 * @return   int|false  Audit log ID or false on failure.
	 */
	public function log_plugin_deactivated(): int|false {
		$this->logger->info( 'Plugin deactivated' );

		return $this->repository->log_audit(
			self::ACTION_PLUGIN_DEACTIVATED,
			null,
			null,
			null
		);
	}

	/**
	 * Get a human-readable action label.
	 *
	 * @since    1.0.0
	 * @param    string $action  The action constant.
	 */
	public function get_action_label( string $action ): string {
		$labels = [
			self::ACTION_SETTINGS_UPDATED   => __( 'Settings Updated', 'rd-post-republishing' ),
			self::ACTION_SETTINGS_RESET     => __( 'Settings Reset', 'rd-post-republishing' ),
			self::ACTION_MANUAL_TRIGGER     => __( 'Manual Trigger', 'rd-post-republishing' ),
			self::ACTION_DRY_RUN            => __( 'Dry Run', 'rd-post-republishing' ),
			self::ACTION_CRON_TOGGLE        => __( 'Cron Toggle', 'rd-post-republishing' ),
			self::ACTION_DATA_EXPORT        => __( 'Data Export', 'rd-post-republishing' ),
			self::ACTION_DATA_PURGE         => __( 'Data Purge', 'rd-post-republishing' ),
			self::ACTION_PLUGIN_ACTIVATED   => __( 'Plugin Activated', 'rd-post-republishing' ),
			self::ACTION_PLUGIN_DEACTIVATED => __( 'Plugin Deactivated', 'rd-post-republishing' ),
		];

		return $labels[ $action ] ?? $action;
	}

	/**
	 * Get all available action types.
	 *
	 * @since    1.0.0
	 * @return   array<string, string>  Action constants mapped to labels.
	 */
	public function get_action_types(): array {
		return [
			self::ACTION_SETTINGS_UPDATED   => $this->get_action_label( self::ACTION_SETTINGS_UPDATED ),
			self::ACTION_SETTINGS_RESET     => $this->get_action_label( self::ACTION_SETTINGS_RESET ),
			self::ACTION_MANUAL_TRIGGER     => $this->get_action_label( self::ACTION_MANUAL_TRIGGER ),
			self::ACTION_DRY_RUN            => $this->get_action_label( self::ACTION_DRY_RUN ),
			self::ACTION_CRON_TOGGLE        => $this->get_action_label( self::ACTION_CRON_TOGGLE ),
			self::ACTION_DATA_EXPORT        => $this->get_action_label( self::ACTION_DATA_EXPORT ),
			self::ACTION_DATA_PURGE         => $this->get_action_label( self::ACTION_DATA_PURGE ),
			self::ACTION_PLUGIN_ACTIVATED   => $this->get_action_label( self::ACTION_PLUGIN_ACTIVATED ),
			self::ACTION_PLUGIN_DEACTIVATED => $this->get_action_label( self::ACTION_PLUGIN_DEACTIVATED ),
		];
	}

	/**
	 * Calculate differences between old and new settings.
	 *
	 * @since    1.0.0
	 * @param    array<string, mixed> $old_settings  Previous settings.
	 * @param    array<string, mixed> $new_settings  New settings.
	 * @return   array<string, array<string, mixed>>  Array of changed settings.
	 */
	private function diff_settings( array $old_settings, array $new_settings ): array {
		$changes = [];

		$all_keys = array_unique( array_merge( array_keys( $old_settings ), array_keys( $new_settings ) ) );

		foreach ( $all_keys as $key ) {
			$old_value = $old_settings[ $key ] ?? null;
			$new_value = $new_settings[ $key ] ?? null;

			// Handle array comparison
			if ( is_array( $old_value ) && is_array( $new_value ) ) {
				if ( $old_value !== $new_value ) {
					$changes[ $key ] = [
						'old' => $old_value,
						'new' => $new_value,
					];
				}
			} elseif ( $old_value !== $new_value ) {
				$changes[ $key ] = [
					'old' => $old_value,
					'new' => $new_value,
				];
			}
		}

		return $changes;
	}

	/**
	 * Format audit log entry for display.
	 *
	 * @since    1.0.0
	 * @param    object $log_entry  The audit log entry from database.
	 * @return   array<string, mixed>  Formatted log entry.
	 */
	public function format_log_entry( object $log_entry ): array {
		$user = get_userdata( (int) $log_entry->user_id );

		return [
			'id'                  => (int) $log_entry->id,
			'user_id'             => (int) $log_entry->user_id,
			'user_name'           => $user ? $user->display_name : __( 'Unknown', 'rd-post-republishing' ),
			'user_email'          => $user ? $user->user_email : '',
			'action'              => $log_entry->action,
			'action_label'        => $this->get_action_label( $log_entry->action ),
			'setting_key'         => $log_entry->setting_key,
			'old_value'           => $log_entry->old_value,
			'new_value'           => $log_entry->new_value,
			'ip_address'          => $log_entry->ip_address,
			'user_agent'          => $log_entry->user_agent,
			'timestamp'           => $log_entry->timestamp,
			'timestamp_formatted' => wp_date(
				get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
				strtotime( $log_entry->timestamp )
			),
		];
	}

	/**
	 * Get recent audit logs formatted for display.
	 *
	 * @since    1.0.0
	 * @param    int $limit  Maximum number of entries.
	 * @return   array<int, array<string, mixed>>  Formatted audit logs.
	 */
	public function get_recent_formatted( int $limit = 10 ): array {
		$logs = $this->repository->get_audit_logs( [ 'limit' => $limit ] );

		return array_map( [ $this, 'format_log_entry' ], $logs );
	}
}
