<?php

declare(strict_types=1);

namespace WPR\Republisher\Core;

use WPR\Republisher\Scheduler\Cron;

/**
 * Fired during plugin activation
 *
 * @link       https://www.paulramotowski.com
 * @since      1.0.0
 *
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Core
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Core
 * @author     Paul Ramotowski <paulramotowski@gmail.com>
 */
class Activator {

	/**
	 * Plugin activation handler.
	 *
	 * Creates database tables, sets default options, and schedules cron events.
	 *
	 * @since    1.0.0
	 */
	public static function activate(): void {
		self::create_database_tables();
		self::set_default_options();
		self::schedule_cron_events();

		// Flush rewrite rules for REST API
		flush_rewrite_rules();
	}

	/**
	 * Create custom database tables.
	 *
	 * @since    1.0.0
	 */
	private static function create_database_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// History table
		$table_history = $wpdb->prefix . 'wpr_history';
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
		$table_audit = $wpdb->prefix . 'wpr_audit';
		$sql_audit = "CREATE TABLE {$table_audit} (
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
		$table_api_log = $wpdb->prefix . 'wpr_api_log';
		$sql_api_log = "CREATE TABLE {$table_api_log} (
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

		// Store database version for future migrations
		update_option( 'wpr_db_version', '1.0.0' );
	}

	/**
	 * Set default plugin options.
	 *
	 * @since    1.0.0
	 */
	private static function set_default_options(): void {
		$defaults = [
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
			'api_rate_limit_seconds'       => 86400, // 1 day
			'debug_mode'                   => false,
			'dry_run_mode'                 => false,
		];

		// Only add if option doesn't exist (preserve existing settings on reactivation)
		if ( false === get_option( 'wpr_settings' ) ) {
			add_option( 'wpr_settings', $defaults );
		}
	}

	/**
	 * Schedule WP Cron events.
	 *
	 * Uses the Cron class to schedule events at the configured times
	 * rather than scheduling immediately.
	 *
	 * @since    1.0.0
	 */
	private static function schedule_cron_events(): void {
		$scheduler = new Cron();
		$scheduler->schedule_events();
	}
}
