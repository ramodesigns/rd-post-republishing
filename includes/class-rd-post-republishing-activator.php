<?php

/**
 * Fired during plugin activation
 *
 * @link       https://www.paulramotowski.com
 * @since      1.0.0
 *
 * @package    Rd_Post_Republishing
 * @subpackage Rd_Post_Republishing/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Rd_Post_Republishing
 * @subpackage Rd_Post_Republishing/includes
 * @author     Paul Ramotowski <paulramotowski@gmail.com>
 */
class Rd_Post_Republishing_Activator {

	/**
	 * Plugin activation tasks.
	 *
	 * Creates the database tables (preferences and log).
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'setup/Init_Setup.php';
		Init_Setup::create_preferences_table();
		Init_Setup::create_log_table();
	}

}
