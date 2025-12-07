<?php

declare(strict_types=1);

namespace WPR\Republisher\Database;

/**
 * Database repository for plugin data access
 *
 * Handles all database operations for settings, history, audit logs, and API logs.
 *
 * @link       https://www.paulramotowski.com
 * @since      1.0.0
 *
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Database
 */

/**
 * Database Repository class.
 *
 * Provides a centralized data access layer for all plugin database operations.
 *
 * @since      1.0.0
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Database
 * @author     Paul Ramotowski <paulramotowski@gmail.com>
 */
class Repository {

	/**
	 * WordPress database instance.
	 *
	 * @since    1.0.0
	 */
	private \wpdb $wpdb;

	/**
	 * Table name for republishing history.
	 *
	 * @since    1.0.0
	 */
	private readonly string $history_table;

	/**
	 * Table name for audit logs.
	 *
	 * @since    1.0.0
	 */
	private readonly string $audit_table;

	/**
	 * Table name for API logs.
	 *
	 * @since    1.0.0
	 */
	private readonly string $api_log_table;

	/**
	 * Cached settings array.
	 *
	 * @since    1.0.0
	 * @var      array<string, mixed>|null
	 */
	private ?array $settings_cache = null;

	/**
	 * Initialize the repository.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->history_table = $wpdb->prefix . 'wpr_history';
		$this->audit_table = $wpdb->prefix . 'wpr_audit';
		$this->api_log_table = $wpdb->prefix . 'wpr_api_log';
	}

	/**
	 * Get plugin settings with caching.
	 *
	 * @since    1.0.0
	 * @return   array<string, mixed>
	 */
	public function get_settings(): array {
		if ( null !== $this->settings_cache ) {
			return $this->settings_cache;
		}

		$cached = wp_cache_get( 'wpr_settings', 'wpr_settings' );
		if ( false !== $cached && is_array( $cached ) ) {
			$this->settings_cache = $cached;
			return $this->settings_cache;
		}

		$settings = get_option( 'wpr_settings', $this->get_default_settings() );
		$this->settings_cache = is_array( $settings ) ? $settings : $this->get_default_settings();

		wp_cache_set( 'wpr_settings', $this->settings_cache, 'wpr_settings', HOUR_IN_SECONDS );

		return $this->settings_cache;
	}

	/**
	 * Update plugin settings.
	 *
	 * @since    1.0.0
	 * @param    array<string, mixed>  $settings  The settings to save.
	 */
	public function update_settings( array $settings ): bool {
		$old_settings = $this->get_settings();
		$result = update_option( 'wpr_settings', $settings );

		if ( $result ) {
			$this->settings_cache = $settings;
			wp_cache_set( 'wpr_settings', $settings, 'wpr_settings', HOUR_IN_SECONDS );

			// Log the settings change
			$this->log_audit(
				'settings_updated',
				null,
				wp_json_encode( $old_settings ),
				wp_json_encode( $settings )
			);
		}

		return $result;
	}

	/**
	 * Get default plugin settings.
	 *
	 * @since    1.0.0
	 * @return   array<string, mixed>
	 */
	public function get_default_settings(): array {
		return [
			'enabled_post_types'           => [ 'post' ],
			'daily_quota_type'             => 'number',
			'daily_quota_value'            => 5,
			'republish_start_hour'         => 9,
			'republish_end_hour'           => 17,
			'minimum_age_days'             => 30,
			'maintain_chronological_order' => true,
			'category_filter_type'         => 'none',
			'category_filter_ids'          => [],
			'wp_cron_enabled'              => true,
			'api_rate_limit_seconds'       => 86400,
			'debug_mode'                   => false,
			'dry_run_mode'                 => false,
		];
	}

	/**
	 * Log a republishing event to history.
	 *
	 * @since    1.0.0
	 * @param    int         $post_id        The post ID.
	 * @param    string      $post_type      The post type.
	 * @param    string      $original_date  Original publish date.
	 * @param    string      $republish_date New republish date.
	 * @param    string      $status         Status: success, failed, retrying.
	 * @param    string      $triggered_by   Trigger source: cron, api, manual.
	 * @param    string|null $error_message  Error message if failed.
	 * @param    float|null  $execution_time Execution time in seconds.
	 */
	public function log_history(
		int $post_id,
		string $post_type,
		string $original_date,
		string $republish_date,
		string $status,
		string $triggered_by,
		?string $error_message = null,
		?float $execution_time = null
	): int|false {
		$result = $this->wpdb->insert(
			$this->history_table,
			[
				'post_id'        => $post_id,
				'post_type'      => $post_type,
				'original_date'  => $original_date,
				'republish_date' => $republish_date,
				'status'         => $status,
				'error_message'  => $error_message,
				'execution_time' => $execution_time,
				'triggered_by'   => $triggered_by,
				'created_at'     => current_time( 'mysql' ),
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s' ]
		);

		return false !== $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Get republishing history with optional filters.
	 *
	 * @since    1.0.0
	 * @param    array<string, mixed>  $args  Query arguments.
	 * @return   array<int, object>
	 */
	public function get_history( array $args = [] ): array {
		$defaults = [
			'status'     => null,
			'post_type'  => null,
			'date_from'  => null,
			'date_to'    => null,
			'limit'      => 50,
			'offset'     => 0,
			'orderby'    => 'created_at',
			'order'      => 'DESC',
		];

		$args = wp_parse_args( $args, $defaults );

		$where = [ '1=1' ];
		$values = [];

		if ( null !== $args['status'] ) {
			$where[] = 'status = %s';
			$values[] = $args['status'];
		}

		if ( null !== $args['post_type'] ) {
			$where[] = 'post_type = %s';
			$values[] = $args['post_type'];
		}

		if ( null !== $args['date_from'] ) {
			$where[] = 'created_at >= %s';
			$values[] = $args['date_from'];
		}

		if ( null !== $args['date_to'] ) {
			$where[] = 'created_at <= %s';
			$values[] = $args['date_to'];
		}

		$allowed_orderby = [ 'created_at', 'republish_date', 'post_id', 'status' ];
		$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$where_clause = implode( ' AND ', $where );
		$limit = absint( $args['limit'] );
		$offset = absint( $args['offset'] );

		$query = "SELECT * FROM {$this->history_table} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$values[] = $limit;
		$values[] = $offset;

		if ( count( $values ) > 2 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$prepared = $this->wpdb->prepare( $query, $values );
		} else {
			$prepared = $this->wpdb->prepare( $query, $limit, $offset );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $this->wpdb->get_results( $prepared );
	}

	/**
	 * Get history count with optional filters.
	 *
	 * @since    1.0.0
	 * @param    array<string, mixed>  $args  Query arguments.
	 */
	public function get_history_count( array $args = [] ): int {
		$where = [ '1=1' ];
		$values = [];

		if ( isset( $args['status'] ) && null !== $args['status'] ) {
			$where[] = 'status = %s';
			$values[] = $args['status'];
		}

		if ( isset( $args['post_type'] ) && null !== $args['post_type'] ) {
			$where[] = 'post_type = %s';
			$values[] = $args['post_type'];
		}

		$where_clause = implode( ' AND ', $where );
		$query = "SELECT COUNT(*) FROM {$this->history_table} WHERE {$where_clause}";

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$prepared = $this->wpdb->prepare( $query, $values );
		} else {
			$prepared = $query;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $this->wpdb->get_var( $prepared );
	}

	/**
	 * Check if a post was republished today.
	 *
	 * @since    1.0.0
	 * @param    int  $post_id  The post ID.
	 */
	public function was_republished_today( int $post_id ): bool {
		$today_start = wp_date( 'Y-m-d 00:00:00' );
		$today_end = wp_date( 'Y-m-d 23:59:59' );

		$query = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->history_table}
			WHERE post_id = %d
			AND republish_date BETWEEN %s AND %s
			AND status = 'success'",
			$post_id,
			$today_start,
			$today_end
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $this->wpdb->get_var( $query ) > 0;
	}

	/**
	 * Get today's republished post count.
	 *
	 * @since    1.0.0
	 */
	public function get_today_republish_count(): int {
		$today_start = wp_date( 'Y-m-d 00:00:00' );
		$today_end = wp_date( 'Y-m-d 23:59:59' );

		$query = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->history_table}
			WHERE republish_date BETWEEN %s AND %s
			AND status = 'success'",
			$today_start,
			$today_end
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $this->wpdb->get_var( $query );
	}

	/**
	 * Get post IDs republished today.
	 *
	 * @since    1.0.0
	 * @return   array<int>
	 */
	public function get_today_republished_ids(): array {
		$today_start = wp_date( 'Y-m-d 00:00:00' );
		$today_end = wp_date( 'Y-m-d 23:59:59' );

		$query = $this->wpdb->prepare(
			"SELECT DISTINCT post_id FROM {$this->history_table}
			WHERE republish_date BETWEEN %s AND %s
			AND status = 'success'",
			$today_start,
			$today_end
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $this->wpdb->get_col( $query );

		return array_map( 'intval', $results );
	}

	/**
	 * Log an audit event.
	 *
	 * @since    1.0.0
	 * @param    string      $action       The action performed.
	 * @param    string|null $setting_key  The setting key if applicable.
	 * @param    string|null $old_value    The old value.
	 * @param    string|null $new_value    The new value.
	 */
	public function log_audit(
		string $action,
		?string $setting_key = null,
		?string $old_value = null,
		?string $new_value = null
	): int|false {
		$user_id = get_current_user_id();
		$ip_address = $this->get_client_ip();
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '';

		$result = $this->wpdb->insert(
			$this->audit_table,
			[
				'user_id'     => $user_id,
				'action'      => $action,
				'setting_key' => $setting_key,
				'old_value'   => $old_value,
				'new_value'   => $new_value,
				'ip_address'  => $ip_address,
				'user_agent'  => $user_agent,
				'timestamp'   => current_time( 'mysql' ),
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		return false !== $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Get audit logs with optional filters.
	 *
	 * @since    1.0.0
	 * @param    array<string, mixed>  $args  Query arguments.
	 * @return   array<int, object>
	 */
	public function get_audit_logs( array $args = [] ): array {
		$defaults = [
			'user_id'   => null,
			'action'    => null,
			'date_from' => null,
			'date_to'   => null,
			'limit'     => 50,
			'offset'    => 0,
		];

		$args = wp_parse_args( $args, $defaults );

		$where = [ '1=1' ];
		$values = [];

		if ( null !== $args['user_id'] ) {
			$where[] = 'user_id = %d';
			$values[] = $args['user_id'];
		}

		if ( null !== $args['action'] ) {
			$where[] = 'action = %s';
			$values[] = $args['action'];
		}

		if ( null !== $args['date_from'] ) {
			$where[] = 'timestamp >= %s';
			$values[] = $args['date_from'];
		}

		if ( null !== $args['date_to'] ) {
			$where[] = 'timestamp <= %s';
			$values[] = $args['date_to'];
		}

		$where_clause = implode( ' AND ', $where );
		$limit = absint( $args['limit'] );
		$offset = absint( $args['offset'] );

		$query = "SELECT * FROM {$this->audit_table} WHERE {$where_clause} ORDER BY timestamp DESC LIMIT %d OFFSET %d";
		$values[] = $limit;
		$values[] = $offset;

		if ( count( $values ) > 2 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$prepared = $this->wpdb->prepare( $query, $values );
		} else {
			$prepared = $this->wpdb->prepare( $query, $limit, $offset );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $this->wpdb->get_results( $prepared );
	}

	/**
	 * Get audit log count with optional filters.
	 *
	 * @since    1.0.0
	 * @param    array<string, mixed>  $args  Query arguments.
	 */
	public function get_audit_count( array $args = [] ): int {
		$where = [ '1=1' ];
		$values = [];

		if ( isset( $args['user_id'] ) && null !== $args['user_id'] ) {
			$where[] = 'user_id = %d';
			$values[] = $args['user_id'];
		}

		if ( isset( $args['action'] ) && null !== $args['action'] ) {
			$where[] = 'action = %s';
			$values[] = $args['action'];
		}

		$where_clause = implode( ' AND ', $where );
		$query = "SELECT COUNT(*) FROM {$this->audit_table} WHERE {$where_clause}";

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$prepared = $this->wpdb->prepare( $query, $values );
		} else {
			$prepared = $query;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $this->wpdb->get_var( $prepared );
	}

	/**
	 * Log an API request.
	 *
	 * @since    1.0.0
	 * @param    string   $endpoint       The API endpoint.
	 * @param    int      $response_code  The HTTP response code.
	 * @param    int|null $user_id        The user ID if authenticated.
	 */
	public function log_api_request(
		string $endpoint,
		int $response_code,
		?int $user_id = null
	): int|false {
		$ip_address = $this->get_client_ip();

		$result = $this->wpdb->insert(
			$this->api_log_table,
			[
				'ip_address'        => $ip_address,
				'user_id'           => $user_id,
				'endpoint'          => $endpoint,
				'response_code'     => $response_code,
				'request_timestamp' => current_time( 'mysql' ),
			],
			[ '%s', '%d', '%s', '%d', '%s' ]
		);

		return false !== $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Check if API rate limit is exceeded.
	 *
	 * @since    1.0.0
	 * @param    string   $endpoint          The API endpoint.
	 * @param    int|null $user_id           The user ID.
	 * @param    int      $rate_limit_seconds Rate limit in seconds.
	 */
	public function is_rate_limited( string $endpoint, ?int $user_id, int $rate_limit_seconds ): bool {
		$ip_address = $this->get_client_ip();
		$cutoff_time = wp_date( 'Y-m-d H:i:s', time() - $rate_limit_seconds );

		// Check by user ID if available, otherwise by IP
		if ( null !== $user_id && $user_id > 0 ) {
			$query = $this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->api_log_table}
				WHERE user_id = %d
				AND endpoint = %s
				AND response_code = 200
				AND request_timestamp > %s",
				$user_id,
				$endpoint,
				$cutoff_time
			);
		} else {
			$query = $this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->api_log_table}
				WHERE ip_address = %s
				AND endpoint = %s
				AND response_code = 200
				AND request_timestamp > %s",
				$ip_address,
				$endpoint,
				$cutoff_time
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $this->wpdb->get_var( $query ) > 0;
	}

	/**
	 * Purge old records based on retention policy.
	 *
	 * @since    1.0.0
	 * @param    int  $retention_days  Number of days to retain records.
	 * @return   array<string, int>  Count of deleted records per table.
	 */
	public function purge_old_records( int $retention_days = 365 ): array {
		$cutoff_date = wp_date( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );
		$deleted = [];

		// Purge history
		$deleted['history'] = (int) $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->history_table} WHERE created_at < %s",
				$cutoff_date
			)
		);

		// Purge audit logs
		$deleted['audit'] = (int) $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->audit_table} WHERE timestamp < %s",
				$cutoff_date
			)
		);

		// Purge API logs
		$deleted['api_log'] = (int) $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->api_log_table} WHERE request_timestamp < %s",
				$cutoff_date
			)
		);

		return $deleted;
	}

	/**
	 * Update history record status (for retry mechanism).
	 *
	 * @since    1.0.0
	 * @param    int    $history_id  The history record ID.
	 * @param    string $status      The new status.
	 */
	public function update_history_status( int $history_id, string $status ): bool {
		$result = $this->wpdb->update(
			$this->history_table,
			[ 'status' => $status ],
			[ 'id' => $history_id ],
			[ '%s' ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Get failed republishing records for retry.
	 *
	 * @since    1.0.0
	 * @return   array<int, object>
	 */
	public function get_failed_for_retry(): array {
		$today_start = wp_date( 'Y-m-d 00:00:00' );

		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->history_table}
			WHERE status = 'failed'
			AND created_at >= %s
			ORDER BY created_at ASC",
			$today_start
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $this->wpdb->get_results( $query );
	}

	/**
	 * Get client IP address.
	 *
	 * @since    1.0.0
	 */
	private function get_client_ip(): string {
		$ip_keys = [
			'HTTP_CF_CONNECTING_IP', // Cloudflare
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		];

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// Handle comma-separated IPs (X-Forwarded-For)
				if ( str_contains( $ip, ',' ) ) {
					$ips = explode( ',', $ip );
					$ip = trim( $ips[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}
}
