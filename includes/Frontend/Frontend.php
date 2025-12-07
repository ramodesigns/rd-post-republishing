<?php

declare(strict_types=1);

namespace WPR\Republisher\Frontend;

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://www.paulramotowski.com
 * @since      1.0.0
 *
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Frontend
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for the public-facing
 * side of the site. Note: This plugin primarily operates in the admin
 * and via cron/API, so public functionality is minimal.
 *
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Frontend
 * @author     Paul Ramotowski <paulramotowski@gmail.com>
 */
class Frontend {

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct(
		private readonly string $plugin_name,
		private readonly string $version
	) {
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles(): void {
		// This plugin doesn't require public-facing styles
		// Method kept for potential future use
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts(): void {
		// This plugin doesn't require public-facing scripts
		// Method kept for potential future use
	}
}
