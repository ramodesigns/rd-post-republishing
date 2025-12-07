<?php

declare(strict_types=1);

namespace WPR\Republisher\Core;

use WPR\Republisher\Scheduler\Cron;

/**
 * Fired during plugin deactivation
 *
 * @link       https://www.paulramotowski.com
 * @since      1.0.0
 *
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Core
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Core
 * @author     Paul Ramotowski <paulramotowski@gmail.com>
 */
class Deactivator {

	/**
	 * Plugin deactivation handler.
	 *
	 * Clears scheduled cron events but preserves data.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate(): void {
		self::clear_cron_events();
		self::clear_transients();
	}

	/**
	 * Clear all scheduled WP Cron events for this plugin.
	 *
	 * Uses the Cron class constants to ensure hook names are consistent.
	 *
	 * @since    1.0.0
	 */
	private static function clear_cron_events(): void {
		$scheduler = new Cron();
		$scheduler->unschedule_events();
	}

	/**
	 * Clear plugin transients.
	 *
	 * Removes lock transients and cached data.
	 *
	 * @since    1.0.0
	 */
	private static function clear_transients(): void {
		delete_transient( 'wpr_republishing_lock' );
		delete_transient( 'wpr_settings_cache' );
	}
}
