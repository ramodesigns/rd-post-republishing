<?php

declare(strict_types=1);

/**
 * Fired when the plugin is uninstalled.
 *
 * This file is called when the plugin is uninstalled (deleted) from WordPress.
 * It cleans up all plugin data including database tables, options, and transients.
 *
 * For security, this file checks that it was called by WordPress, not directly.
 *
 * @link       https://www.paulramotowski.com
 * @since      1.0.0
 *
 * @package    RD_Post_Republishing
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Main uninstall class to handle all cleanup operations.
 *
 * Uses a class to avoid polluting the global namespace and
 * to organize cleanup methods logically.
 *
 * @since 1.0.0
 */
class WPR_Uninstaller {

	/**
	 * WordPress database instance.
	 *
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Initialize the uninstaller.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * Run the complete uninstall process.
	 *
	 * Handles both single site and multisite installations.
	 */
	public function run(): void {
		if ( is_multisite() ) {
			$this->uninstall_multisite();
		} else {
			$this->uninstall_single_site();
		}
	}

	/**
	 * Uninstall from a multisite network.
	 *
	 * Iterates through all sites in the network and cleans up each one.
	 */
	private function uninstall_multisite(): void {
		// Get all blog IDs
		$blog_ids = $this->wpdb->get_col( "SELECT blog_id FROM {$this->wpdb->blogs}" );

		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( (int) $blog_id );
			$this->cleanup_site_data();
			restore_current_blog();
		}

		// Clean up network-level data if any
		$this->cleanup_network_data();
	}

	/**
	 * Uninstall from a single site.
	 */
	private function uninstall_single_site(): void {
		$this->cleanup_site_data();
	}

	/**
	 * Clean up all site-specific data.
	 */
	private function cleanup_site_data(): void {
		$this->drop_database_tables();
		$this->delete_options();
		$this->delete_transients();
		$this->clear_scheduled_events();
		$this->cleanup_post_meta();
		$this->cleanup_user_meta();
	}

	/**
	 * Drop custom database tables.
	 */
	private function drop_database_tables(): void {
		$tables = [
			$this->wpdb->prefix . 'wpr_history',
			$this->wpdb->prefix . 'wpr_audit',
			$this->wpdb->prefix . 'wpr_api_log',
		];

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$this->wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}
	}

	/**
	 * Delete all plugin options.
	 */
	private function delete_options(): void {
		$options = [
			'wpr_settings',
			'wpr_db_version',
			'wpr_activation_time',
			'wpr_last_run',
			'wpr_last_cleanup',
		];

		foreach ( $options as $option ) {
			delete_option( $option );
		}

		// Also delete any options that might have been added with a prefix pattern
		$this->wpdb->query(
			"DELETE FROM {$this->wpdb->options} WHERE option_name LIKE 'wpr_%'"
		);
	}

	/**
	 * Delete all plugin transients.
	 */
	private function delete_transients(): void {
		$transients = [
			'wpr_republishing_lock',
			'wpr_settings_cache',
			'wpr_eligible_posts_cache',
			'wpr_last_api_call',
		];

		foreach ( $transients as $transient ) {
			delete_transient( $transient );
		}

		// Clean up any transients with our prefix from the database
		// Transients are stored as '_transient_' . name and '_transient_timeout_' . name
		$this->wpdb->query(
			"DELETE FROM {$this->wpdb->options}
			WHERE option_name LIKE '_transient_wpr_%'
			OR option_name LIKE '_transient_timeout_wpr_%'"
		);
	}

	/**
	 * Clear all scheduled cron events.
	 */
	private function clear_scheduled_events(): void {
		$hooks = [
			'wpr_daily_republishing',
			'wpr_daily_cleanup',
			'wpr_retry_republishing',
		];

		foreach ( $hooks as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
			// Also clear any remaining scheduled instances
			wp_clear_scheduled_hook( $hook );
		}
	}

	/**
	 * Clean up any post meta we may have added.
	 *
	 * Currently, the plugin doesn't add post meta, but this is here
	 * for future compatibility if meta is added.
	 */
	private function cleanup_post_meta(): void {
		$meta_keys = [
			'_wpr_last_republished',
			'_wpr_republish_count',
			'_wpr_exclude_from_republish',
		];

		foreach ( $meta_keys as $meta_key ) {
			$this->wpdb->delete(
				$this->wpdb->postmeta,
				[ 'meta_key' => $meta_key ],
				[ '%s' ]
			);
		}
	}

	/**
	 * Clean up any user meta we may have added.
	 *
	 * Currently, the plugin doesn't add user meta, but this is here
	 * for future compatibility if meta is added.
	 */
	private function cleanup_user_meta(): void {
		$meta_keys = [
			'wpr_dismissed_notices',
			'wpr_user_preferences',
		];

		foreach ( $meta_keys as $meta_key ) {
			$this->wpdb->delete(
				$this->wpdb->usermeta,
				[ 'meta_key' => $meta_key ],
				[ '%s' ]
			);
		}
	}

	/**
	 * Clean up network-level data for multisite.
	 */
	private function cleanup_network_data(): void {
		// Delete network-level options if any were added
		delete_site_option( 'wpr_network_settings' );
		delete_site_option( 'wpr_network_version' );
	}
}

// Run the uninstaller
$uninstaller = new WPR_Uninstaller();
$uninstaller->run();

// Clear object cache after all cleanup
if ( function_exists( 'wp_cache_flush' ) ) {
	wp_cache_flush();
}
