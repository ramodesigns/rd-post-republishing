<?php

declare(strict_types=1);

namespace WPR\Republisher\Admin;

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.paulramotowski.com
 * @since      1.0.0
 *
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for the admin area
 * including the settings page with tabbed interface.
 *
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Admin
 * @author     Paul Ramotowski <paulramotowski@gmail.com>
 */
class Admin {

	/**
	 * The plugin settings page hook suffix.
	 *
	 * @since    1.0.0
	 */
	private string $page_hook = '';

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
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles( string $hook ): void {
		// Only load on our settings page
		if ( $this->page_hook !== $hook ) {
			return;
		}

		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( dirname( __DIR__ ) ) . 'admin/css/rd-post-republishing-admin.css',
			[],
			$this->version,
			'all'
		);
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts( string $hook ): void {
		// Only load on our settings page
		if ( $this->page_hook !== $hook ) {
			return;
		}

		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( dirname( __DIR__ ) ) . 'admin/js/rd-post-republishing-admin.js',
			[ 'jquery' ],
			$this->version,
			true
		);

		wp_localize_script(
			$this->plugin_name,
			'wprRepublisher',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wpr_admin_nonce' ),
				'i18n'    => [
					'confirmDryRun' => __( 'Run dry-run simulation?', 'rd-post-republishing' ),
					'confirmManual' => __( 'Trigger manual republishing now?', 'rd-post-republishing' ),
					'success'       => __( 'Operation completed successfully.', 'rd-post-republishing' ),
					'error'         => __( 'An error occurred. Please try again.', 'rd-post-republishing' ),
				],
			]
		);
	}

	/**
	 * Add the plugin admin menu.
	 *
	 * @since    1.0.0
	 */
	public function add_admin_menu(): void {
		$this->page_hook = add_options_page(
			__( 'Post Republishing', 'rd-post-republishing' ),
			__( 'Post Republishing', 'rd-post-republishing' ),
			$this->get_required_capability(),
			$this->plugin_name,
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @since    1.0.0
	 */
	public function register_settings(): void {
		register_setting(
			'wpr_settings_group',
			'wpr_settings',
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
				'default'           => $this->get_default_settings(),
			]
		);
	}

	/**
	 * Sanitize plugin settings.
	 *
	 * @since    1.0.0
	 * @param    array<string, mixed>  $input  The settings to sanitize.
	 * @return   array<string, mixed>
	 */
	public function sanitize_settings( array $input ): array {
		$sanitized = [];

		// Post types - validate against registered post types
		$valid_post_types = get_post_types( [ 'public' => true ], 'names' );
		$sanitized['enabled_post_types'] = array_filter(
			array_map( 'sanitize_text_field', (array) ( $input['enabled_post_types'] ?? [] ) ),
			fn( string $type ): bool => isset( $valid_post_types[ $type ] )
		);

		// Quota type
		$sanitized['daily_quota_type'] = in_array( $input['daily_quota_type'] ?? '', [ 'number', 'percentage' ], true )
			? $input['daily_quota_type']
			: 'number';

		// Quota value (1-50 for number, 1-100 for percentage, capped at 50 posts)
		$quota_value = absint( $input['daily_quota_value'] ?? 5 );
		$sanitized['daily_quota_value'] = min( max( $quota_value, 1 ), 50 );

		// Time range (0-23)
		$sanitized['republish_start_hour'] = min( max( absint( $input['republish_start_hour'] ?? 9 ), 0 ), 23 );
		$sanitized['republish_end_hour'] = min( max( absint( $input['republish_end_hour'] ?? 17 ), 0 ), 23 );

		// Minimum age (7-180 days)
		$sanitized['minimum_age_days'] = min( max( absint( $input['minimum_age_days'] ?? 30 ), 7 ), 180 );

		// Boolean options
		$sanitized['maintain_chronological_order'] = ! empty( $input['maintain_chronological_order'] );
		$sanitized['wp_cron_enabled'] = ! empty( $input['wp_cron_enabled'] );
		$sanitized['debug_mode'] = ! empty( $input['debug_mode'] );
		$sanitized['dry_run_mode'] = ! empty( $input['dry_run_mode'] );

		// Category filter
		$sanitized['category_filter_type'] = in_array( $input['category_filter_type'] ?? '', [ 'none', 'whitelist', 'blacklist' ], true )
			? $input['category_filter_type']
			: 'none';
		$sanitized['category_filter_ids'] = array_map( 'absint', (array) ( $input['category_filter_ids'] ?? [] ) );

		// API rate limit (minimum 1 second for testing, default 86400 = 1 day)
		$sanitized['api_rate_limit_seconds'] = max( absint( $input['api_rate_limit_seconds'] ?? 86400 ), 1 );

		return $sanitized;
	}

	/**
	 * Render the settings page.
	 *
	 * @since    1.0.0
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( $this->get_required_capability() ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'rd-post-republishing' ) );
		}

		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'overview';
		$tabs = [
			'overview' => __( 'Overview', 'rd-post-republishing' ),
			'schedule' => __( 'Schedule', 'rd-post-republishing' ),
			'settings' => __( 'Settings', 'rd-post-republishing' ),
			'logs'     => __( 'History & Logs', 'rd-post-republishing' ),
		];

		include plugin_dir_path( dirname( __DIR__ ) ) . 'admin/partials/rd-post-republishing-admin-display.php';
	}

	/**
	 * Get the required capability for accessing plugin settings.
	 *
	 * @since    1.0.0
	 */
	private function get_required_capability(): string {
		return apply_filters( 'wpr_required_cap', 'manage_options' );
	}

	/**
	 * Get default plugin settings.
	 *
	 * @since    1.0.0
	 * @return   array<string, mixed>
	 */
	private function get_default_settings(): array {
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
}
