<?php

declare(strict_types=1);

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.paulramotowski.com
 * @since             1.0.0
 * @package           RD_Post_Republishing
 *
 * @wordpress-plugin
 * Plugin Name:       RD - Post Republishing
 * Plugin URI:        https://www.ramodesigns.co.uk
 * Description:       A WordPress Post Republishing Tool for SEO Benefit
 * Version:           1.0.0
 * Requires at least: 6.6
 * Requires PHP:      8.2
 * Author:            Paul Ramotowski
 * Author URI:        https://www.paulramotowski.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       rd-post-republishing
 * Domain Path:       /languages
 */

use WPR\Republisher\Core\Activator;
use WPR\Republisher\Core\Deactivator;
use WPR\Republisher\Core\Plugin;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Plugin version constant.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 */
define( 'RD_POST_REPUBLISHING_VERSION', '1.0.0' );

/**
 * Plugin base path constant.
 */
define( 'RD_POST_REPUBLISHING_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Plugin base URL constant.
 */
define( 'RD_POST_REPUBLISHING_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load Composer autoloader if available.
 */
$autoloader = RD_POST_REPUBLISHING_PATH . 'vendor/autoload.php';
if ( file_exists( $autoloader ) ) {
	require_once $autoloader;
} else {
	// Fallback manual autoloader for development without Composer
	spl_autoload_register( static function ( string $class ): void {
		$prefix = 'WPR\\Republisher\\';
		$base_dir = RD_POST_REPUBLISHING_PATH . 'includes/';

		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, $len );
		$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	} );
}

/**
 * The code that runs during plugin activation.
 *
 * @since    1.0.0
 */
function activate_rd_post_republishing(): void {
	Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 *
 * @since    1.0.0
 */
function deactivate_rd_post_republishing(): void {
	Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_rd_post_republishing' );
register_deactivation_hook( __FILE__, 'deactivate_rd_post_republishing' );

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_rd_post_republishing(): void {
	$plugin = new Plugin();
	$plugin->run();
}

run_rd_post_republishing();
