<?php

declare(strict_types=1);

/**
 * Fired when the plugin is uninstalled.
 *
 * This file is called when the plugin is uninstalled (deleted) from WordPress.
 * It cleans up all plugin data including database tables and options.
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
 * Remove all plugin data on uninstall.
 */
function wpr_uninstall_cleanup(): void {
	global $wpdb;

	// Delete custom database tables
	$tables = [
		$wpdb->prefix . 'wpr_history',
		$wpdb->prefix . 'wpr_audit',
		$wpdb->prefix . 'wpr_api_log',
	];

	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	// Delete plugin options
	delete_option( 'wpr_settings' );
	delete_option( 'wpr_db_version' );

	// Clear any scheduled cron events
	wp_clear_scheduled_hook( 'wpr_daily_republishing' );
	wp_clear_scheduled_hook( 'wpr_daily_cleanup' );
	wp_clear_scheduled_hook( 'wpr_retry_republishing' );

	// Clean up any transients
	delete_transient( 'wpr_republishing_lock' );

	// Clear object cache if available
	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}
}

wpr_uninstall_cleanup();
