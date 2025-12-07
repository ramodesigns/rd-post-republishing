<?php

declare(strict_types=1);

namespace WPR\Republisher\Core;

use WPR\Republisher\Admin\Admin;
use WPR\Republisher\Frontend\Frontend;
use WPR\Republisher\Scheduler\Cron;
use WPR\Republisher\Api\RestController;

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://www.paulramotowski.com
 * @since      1.0.0
 *
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Core
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Core
 * @author     Paul Ramotowski <paulramotowski@gmail.com>
 */
class Plugin {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 */
	protected Loader $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 */
	protected readonly string $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 */
	protected readonly string $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->version = defined( 'RD_POST_REPUBLISHING_VERSION' )
			? RD_POST_REPUBLISHING_VERSION
			: '1.0.0';
		$this->plugin_name = 'rd-post-republishing';

		$this->loader = new Loader();

		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_scheduler_hooks();
		$this->define_api_hooks();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the I18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 */
	private function set_locale(): void {
		$plugin_i18n = new I18n();
		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 */
	private function define_admin_hooks(): void {
		$plugin_admin = new Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'register_settings' );

		// Register AJAX handlers
		$plugin_admin->register_ajax_handlers();

		// Register audit logging hooks for settings changes
		$plugin_admin->register_audit_hooks();
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 */
	private function define_public_hooks(): void {
		$plugin_public = new Frontend( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
	}

	/**
	 * Register WP Cron scheduler hooks.
	 *
	 * @since    1.0.0
	 */
	private function define_scheduler_hooks(): void {
		$scheduler = new Cron();
		$scheduler->register_hooks();
	}

	/**
	 * Register REST API hooks.
	 *
	 * @since    1.0.0
	 */
	private function define_api_hooks(): void {
		$api_controller = new RestController();
		$this->loader->add_action( 'rest_api_init', $api_controller, 'register_routes' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run(): void {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 */
	public function get_plugin_name(): string {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 */
	public function get_loader(): Loader {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 */
	public function get_version(): string {
		return $this->version;
	}
}
