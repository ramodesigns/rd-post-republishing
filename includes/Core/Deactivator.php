<?php

declare(strict_types=1);

namespace WPR\Republisher\Core;

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
	}

	/**
	 * Clear all scheduled WP Cron events for this plugin.
	 *
	 * @since    1.0.0
	 */
	private static function clear_cron_events(): void {
		wp_clear_scheduled_hook( 'wpr_daily_republishing' );
		wp_clear_scheduled_hook( 'wpr_daily_cleanup' );
		wp_clear_scheduled_hook( 'wpr_retry_republishing' );
	}
}
