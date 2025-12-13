<?php

declare(strict_types=1);

namespace WPR\Republisher\Admin;

use WPR\Republisher\Database\Repository;
use WPR\Republisher\Republisher\Engine;
use WPR\Republisher\Republisher\Query;
use WPR\Republisher\Scheduler\Cron;

/**
 * Grace period (in seconds) before considering a scheduled event as overdue.
 * Allows for normal WP Cron delays.
 *
 * @since    1.0.0
 */
const WPR_CRON_OVERDUE_THRESHOLD = 3600; // 1 hour

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
	 * @var      string|false
	 */
	private string|false $page_hook = '';

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
		if ( false === $this->page_hook || $this->page_hook !== $hook ) {
			return;
		}

		$suffix = $this->get_asset_suffix();

		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( dirname( __DIR__ ) ) . "admin/css/rd-post-republishing-admin{$suffix}.css",
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
		if ( false === $this->page_hook || $this->page_hook !== $hook ) {
			return;
		}

		$suffix = $this->get_asset_suffix();

		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( dirname( __DIR__ ) ) . "admin/js/rd-post-republishing-admin{$suffix}.js",
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
	 * Register AJAX handlers.
	 *
	 * @since    1.0.0
	 */
	public function register_ajax_handlers(): void {
		add_action( 'wp_ajax_wpr_dry_run', [ $this, 'ajax_dry_run' ] );
		add_action( 'wp_ajax_wpr_manual_trigger', [ $this, 'ajax_manual_trigger' ] );
		add_action( 'wp_ajax_wpr_export_history', [ $this, 'ajax_export_history' ] );
		add_action( 'wp_ajax_wpr_export_audit', [ $this, 'ajax_export_audit' ] );
		add_action( 'wp_ajax_wpr_get_preview', [ $this, 'ajax_get_preview' ] );
	}

	/**
	 * Register audit logging hooks.
	 *
	 * @since    1.0.0
	 */
	public function register_audit_hooks(): void {
		add_action( 'update_option_wpr_settings', [ $this, 'log_settings_change' ], 10, 2 );
		add_action( 'add_option_wpr_settings', [ $this, 'log_settings_added' ], 10, 2 );
	}

	/**
	 * Display admin notices for cron status issues.
	 *
	 * Shows warnings when WP Cron is disabled, events aren't scheduled,
	 * or scheduled events appear to be stalled.
	 *
	 * @since    1.0.0
	 */
	public function display_admin_notices(): void {
		// Only show notices on our settings page or the plugins page
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$show_on_screens = [ $this->page_hook, 'plugins' ];
		if ( ! in_array( $screen->id, $show_on_screens, true ) ) {
			return;
		}

		// Only show to users who can manage options
		if ( ! current_user_can( $this->get_required_capability() ) ) {
			return;
		}

		$cron_health = $this->check_cron_health();

		// Check for critical issues first
		if ( $cron_health['wp_cron_disabled'] && $cron_health['plugin_cron_enabled'] ) {
			$this->render_admin_notice(
				'error',
				sprintf(
					/* translators: %1$s: WP Cron constant name, %2$s: plugin settings URL */
					__( '<strong>WP Cron Disabled:</strong> The <code>%1$s</code> constant is set to true, but the Post Republishing plugin has WP Cron enabled. Scheduled republishing will not work. Either <a href="%2$s">disable WP Cron in plugin settings</a> and use the REST API or WP-CLI instead, or enable WP Cron at the system level.', 'rd-post-republishing' ),
					'DISABLE_WP_CRON',
					esc_url( admin_url( 'options-general.php?page=' . $this->plugin_name . '&tab=settings' ) )
				)
			);
			return; // Don't show other notices if this critical issue exists
		}

		// Check if plugin cron is enabled but events are not scheduled
		if ( $cron_health['plugin_cron_enabled'] && ! $cron_health['events_scheduled'] ) {
			$this->render_admin_notice(
				'warning',
				sprintf(
					/* translators: %s: plugin settings URL */
					__( '<strong>Cron Events Not Scheduled:</strong> Post Republishing has WP Cron enabled, but the scheduled events are missing. <a href="%s">Visit the settings page</a> to trigger rescheduling, or deactivate and reactivate the plugin.', 'rd-post-republishing' ),
					esc_url( admin_url( 'options-general.php?page=' . $this->plugin_name . '&tab=settings' ) )
				)
			);
			return;
		}

		// Check if scheduled event is overdue (possible stuck cron)
		if ( $cron_health['daily_event_overdue'] ) {
			$this->render_admin_notice(
				'warning',
				sprintf(
					/* translators: %1$s: scheduled time, %2$s: WP Cron info URL */
					__( '<strong>Scheduled Event Overdue:</strong> The daily republishing was scheduled for %1$s but hasn\'t run yet. This may indicate WP Cron isn\'t working correctly. Consider <a href="%2$s" target="_blank">setting up a real cron job</a> for better reliability.', 'rd-post-republishing' ),
					esc_html( $cron_health['daily_scheduled_time'] ?? __( 'unknown', 'rd-post-republishing' ) ),
					'https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/'
				)
			);
		}

		// Informational notice about alternate cron
		if ( $cron_health['alternate_cron'] && $cron_health['plugin_cron_enabled'] ) {
			$this->render_admin_notice(
				'info',
				__( '<strong>Alternate Cron Active:</strong> WordPress is using alternate cron mode. Scheduled republishing may be slightly delayed as it relies on site visits to trigger cron jobs.', 'rd-post-republishing' )
			);
		}
	}

	/**
	 * Check WP Cron health status.
	 *
	 * @since    1.0.0
	 * @return   array<string, mixed>  Array of cron health indicators.
	 */
	public function check_cron_health(): array {
		$cron       = new Cron();
		$status     = $cron->get_status();
		$repository = new Repository();
		$settings   = $repository->get_settings();

		$daily_timestamp      = $status['daily_republishing']['timestamp'] ?? null;
		$daily_overdue        = false;
		$daily_scheduled_time = null;

		if ( $daily_timestamp ) {
			$daily_scheduled_time = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $daily_timestamp );
			// Check if more than threshold seconds have passed since scheduled time
			$daily_overdue = ( $daily_timestamp + WPR_CRON_OVERDUE_THRESHOLD ) < time();
		}

		return [
			'wp_cron_disabled'     => $cron->is_wp_cron_disabled(),
			'alternate_cron'       => $cron->is_alternate_cron(),
			'plugin_cron_enabled'  => ! empty( $settings['wp_cron_enabled'] ),
			'events_scheduled'     => $status['daily_republishing']['scheduled'] ?? false,
			'daily_scheduled_time' => $daily_scheduled_time,
			'daily_event_overdue'  => $daily_overdue,
			'next_run_timestamp'   => $daily_timestamp,
		];
	}

	/**
	 * Render an admin notice.
	 *
	 * @since    1.0.0
	 * @param    string $type     Notice type: 'error', 'warning', 'success', 'info'.
	 * @param    string $message  The notice message (can contain HTML).
	 */
	private function render_admin_notice( string $type, string $message ): void {
		$allowed_types = [ 'error', 'warning', 'success', 'info' ];
		$type          = in_array( $type, $allowed_types, true ) ? $type : 'info';

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			wp_kses(
				$message,
				[
					'strong' => [],
					'code'   => [],
					'a'      => [
						'href'   => [],
						'target' => [],
					],
				]
			)
		);
	}

	/**
	 * Log settings changes to audit trail.
	 *
	 * @since    1.0.0
	 * @param    mixed $old_value  The old option value.
	 * @param    mixed $new_value  The new option value.
	 */
	public function log_settings_change( mixed $old_value, mixed $new_value ): void {
		// Don't log if values are the same
		if ( $old_value === $new_value ) {
			return;
		}

		$repository = new Repository();

		// Find what changed
		$changes = $this->get_settings_diff( $old_value, $new_value );

		if ( empty( $changes ) ) {
			return;
		}

		// Log individual changes for better audit trail
		foreach ( $changes as $key => $change ) {
			$old_json = is_array( $change['old'] ) ? wp_json_encode( $change['old'] ) : null;
			$new_json = is_array( $change['new'] ) ? wp_json_encode( $change['new'] ) : null;
			$repository->log_audit(
				'setting_changed',
				$key,
				false !== $old_json ? $old_json : ( is_scalar( $change['old'] ) ? (string) $change['old'] : null ),
				false !== $new_json ? $new_json : ( is_scalar( $change['new'] ) ? (string) $change['new'] : null )
			);
		}

		// Also log the full settings update as a single record
		$old_encoded = wp_json_encode( $old_value );
		$new_encoded = wp_json_encode( $new_value );
		$repository->log_audit(
			'settings_updated',
			null,
			false !== $old_encoded ? $old_encoded : null,
			false !== $new_encoded ? $new_encoded : null
		);
	}

	/**
	 * Log when settings are first added.
	 *
	 * @since    1.0.0
	 * @param    string $option  The option name.
	 * @param    mixed  $value   The option value.
	 */
	public function log_settings_added( string $option, mixed $value ): void {
		$repository = new Repository();
		$encoded    = wp_json_encode( $value );
		$repository->log_audit(
			'settings_created',
			null,
			null,
			false !== $encoded ? $encoded : null
		);
	}

	/**
	 * Get the differences between old and new settings.
	 *
	 * @since    1.0.0
	 * @param    mixed $old_value  The old settings array.
	 * @param    mixed $new_value  The new settings array.
	 * @return   array<string, array<string, mixed>>  Array of changed settings.
	 */
	private function get_settings_diff( mixed $old_value, mixed $new_value ): array {
		if ( ! is_array( $old_value ) || ! is_array( $new_value ) ) {
			return [];
		}

		$changes  = [];
		$all_keys = array_unique( array_merge( array_keys( $old_value ), array_keys( $new_value ) ) );

		foreach ( $all_keys as $key ) {
			$old = $old_value[ $key ] ?? null;
			$new = $new_value[ $key ] ?? null;

			if ( $old !== $new ) {
				$changes[ $key ] = [
					'old' => $old,
					'new' => $new,
				];
			}
		}

		return $changes;
	}

	/**
	 * AJAX handler for dry-run simulation.
	 *
	 * @since    1.0.0
	 */
	public function ajax_dry_run(): void {
		$this->verify_ajax_request();

		$repository = new Repository();
		$query      = new Query( $repository );
		$settings   = $repository->get_settings();

		// Get eligible posts for dry-run preview
		$eligible_posts     = $query->get_eligible_posts( $settings );
		$quota              = $query->calculate_quota( $settings );
		$posts_to_republish = array_slice( $eligible_posts, 0, $quota );

		$preview_data = [];
		foreach ( $posts_to_republish as $post_obj ) {
			$post = get_post( $post_obj->ID ?? $post_obj );
			if ( $post instanceof \WP_Post ) {
				$preview_data[] = [
					'id'           => $post->ID,
					'title'        => $post->post_title,
					'current_date' => $post->post_date,
					'edit_link'    => get_edit_post_link( $post->ID, 'raw' ),
					'view_link'    => get_permalink( $post->ID ),
				];
			}
		}

		wp_send_json_success(
			[
				'posts'       => $preview_data,
				'total_count' => count( $eligible_posts ),
				'quota'       => $quota,
				'message'     => sprintf(
					/* translators: %1$d: number of posts to republish, %2$d: total eligible posts */
					__( 'Dry-run complete. %1$d of %2$d eligible posts would be republished.', 'rd-post-republishing' ),
					count( $posts_to_republish ),
					count( $eligible_posts )
				),
			]
		);
	}

	/**
	 * AJAX handler for manual republishing trigger.
	 *
	 * @since    1.0.0
	 */
	public function ajax_manual_trigger(): void {
		$this->verify_ajax_request();

		$engine = new Engine();
		$result = $engine->execute_batch( 'manual' );

		if ( $result['success'] ) {
			wp_send_json_success(
				[
					'republished' => $result['republished'],
					'failed'      => $result['failed'],
					'skipped'     => $result['skipped'],
					'message'     => sprintf(
						/* translators: %d: number of republished posts */
						__( 'Successfully republished %d posts.', 'rd-post-republishing' ),
						count( $result['republished'] )
					),
				]
			);
		} else {
			wp_send_json_error(
				[
					'message' => $result['message'] ?? __( 'Republishing failed.', 'rd-post-republishing' ),
				]
			);
		}
	}

	/**
	 * AJAX handler for exporting history to CSV.
	 *
	 * @since    1.0.0
	 */
	public function ajax_export_history(): void {
		$this->verify_ajax_request();

		global $wpdb;
		$table_name = $wpdb->prefix . 'wpr_history';

		$results = $wpdb->get_results(
			"SELECT * FROM {$table_name} ORDER BY republished_at DESC LIMIT 10000",
			ARRAY_A
		);

		if ( empty( $results ) ) {
			wp_send_json_error(
				[
					'message' => __( 'No history records to export.', 'rd-post-republishing' ),
				]
			);
		}

		$csv_data = $this->generate_csv(
			$results,
			[
				'id'             => __( 'ID', 'rd-post-republishing' ),
				'post_id'        => __( 'Post ID', 'rd-post-republishing' ),
				'old_datetime'   => __( 'Old Date', 'rd-post-republishing' ),
				'new_datetime'   => __( 'New Date', 'rd-post-republishing' ),
				'triggered_by'   => __( 'Triggered By', 'rd-post-republishing' ),
				'status'         => __( 'Status', 'rd-post-republishing' ),
				'error_message'  => __( 'Error Message', 'rd-post-republishing' ),
				'execution_time' => __( 'Execution Time (ms)', 'rd-post-republishing' ),
				'republished_at' => __( 'Republished At', 'rd-post-republishing' ),
			]
		);

		wp_send_json_success(
			[
				'csv'      => $csv_data,
				'filename' => 'wpr-history-' . gmdate( 'Y-m-d' ) . '.csv',
			]
		);
	}

	/**
	 * AJAX handler for exporting audit log to CSV.
	 *
	 * @since    1.0.0
	 */
	public function ajax_export_audit(): void {
		$this->verify_ajax_request();

		global $wpdb;
		$table_name = $wpdb->prefix . 'wpr_audit';

		$results = $wpdb->get_results(
			"SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT 10000",
			ARRAY_A
		);

		if ( empty( $results ) ) {
			wp_send_json_error(
				[
					'message' => __( 'No audit records to export.', 'rd-post-republishing' ),
				]
			);
		}

		$csv_data = $this->generate_csv(
			$results,
			[
				'id'            => __( 'ID', 'rd-post-republishing' ),
				'event_type'    => __( 'Event Type', 'rd-post-republishing' ),
				'event_details' => __( 'Details', 'rd-post-republishing' ),
				'user_id'       => __( 'User ID', 'rd-post-republishing' ),
				'ip_address'    => __( 'IP Address', 'rd-post-republishing' ),
				'created_at'    => __( 'Created At', 'rd-post-republishing' ),
			]
		);

		wp_send_json_success(
			[
				'csv'      => $csv_data,
				'filename' => 'wpr-audit-' . gmdate( 'Y-m-d' ) . '.csv',
			]
		);
	}

	/**
	 * AJAX handler for getting preview posts.
	 *
	 * @since    1.0.0
	 */
	public function ajax_get_preview(): void {
		$this->verify_ajax_request();

		$repository = new Repository();
		$query      = new Query( $repository );
		$settings   = $repository->get_settings();

		$eligible_posts = $query->get_eligible_posts( $settings );
		$quota          = $query->calculate_quota( $settings );

		$preview_data = [];
		$count        = 0;
		foreach ( $eligible_posts as $post_obj ) {
			if ( $count >= $quota ) {
				break;
			}
			$post = get_post( $post_obj->ID ?? $post_obj );
			if ( $post instanceof \WP_Post ) {
				$categories     = wp_get_post_categories( $post->ID, [ 'fields' => 'names' ] );
				$preview_data[] = [
					'id'           => $post->ID,
					'title'        => $post->post_title,
					'current_date' => $post->post_date,
					'categories'   => is_array( $categories ) ? $categories : [],
				];
				++$count;
			}
		}

		wp_send_json_success(
			[
				'posts'       => $preview_data,
				'total_count' => count( $eligible_posts ),
				'quota'       => $quota,
			]
		);
	}

	/**
	 * Verify AJAX request has valid nonce and capabilities.
	 *
	 * @since    1.0.0
	 */
	private function verify_ajax_request(): void {
		if ( ! check_ajax_referer( 'wpr_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Security check failed.', 'rd-post-republishing' ),
				],
				403
			);
		}

		if ( ! current_user_can( $this->get_required_capability() ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to perform this action.', 'rd-post-republishing' ),
				],
				403
			);
		}
	}

	/**
	 * Generate CSV content from data.
	 *
	 * @since    1.0.0
	 * @param    array<int, array<string, mixed>> $data     The data rows.
	 * @param    array<string, string>            $headers  Column key => header label mapping.
	 * @return   string
	 */
	private function generate_csv( array $data, array $headers ): string {
		$output = fopen( 'php://temp', 'r+' );

		if ( false === $output ) {
			return '';
		}

		// Write header row
		fputcsv( $output, array_values( $headers ) );

		// Write data rows
		foreach ( $data as $row ) {
			$csv_row = [];
			foreach ( array_keys( $headers ) as $key ) {
				$csv_row[] = $row[ $key ] ?? '';
			}
			fputcsv( $output, $csv_row );
		}

		rewind( $output );
		$csv_content = stream_get_contents( $output );
		fclose( $output );

		return false !== $csv_content ? $csv_content : '';
	}

	/**
	 * Sanitize and validate plugin settings.
	 *
	 * Sanitizes input values and adds validation error messages for invalid data.
	 *
	 * @since    1.0.0
	 * @param    array<string, mixed> $input  The settings to sanitize.
	 * @return   array<string, mixed>
	 */
	public function sanitize_settings( array $input ): array {
		$sanitized = [];
		$defaults  = $this->get_default_settings();

		// === Post Types Validation ===
		$valid_post_types                = get_post_types( [ 'public' => true ], 'names' );
		$requested_types                 = (array) ( $input['enabled_post_types'] ?? [] );
		$sanitized['enabled_post_types'] = array_filter(
			array_map( 'sanitize_text_field', $requested_types ),
			fn( string $type ): bool => isset( $valid_post_types[ $type ] )
		);

		// Warn if no post types selected
		if ( empty( $sanitized['enabled_post_types'] ) ) {
			add_settings_error(
				'wpr_settings',
				'no_post_types',
				__( 'No post types selected. Please select at least one post type for republishing.', 'rd-post-republishing' ),
				'error'
			);
			$sanitized['enabled_post_types'] = $defaults['enabled_post_types'];
		}

		// Warn if invalid post types were submitted
		$invalid_types = array_diff( $requested_types, array_keys( $valid_post_types ) );
		if ( ! empty( $invalid_types ) ) {
			add_settings_error(
				'wpr_settings',
				'invalid_post_types',
				sprintf(
					/* translators: %s: comma-separated list of invalid post type names */
					__( 'Invalid post types removed: %s', 'rd-post-republishing' ),
					implode( ', ', $invalid_types )
				),
				'warning'
			);
		}

		// === Quota Type Validation ===
		$sanitized['daily_quota_type'] = in_array( $input['daily_quota_type'] ?? '', [ 'number', 'percentage' ], true )
			? $input['daily_quota_type']
			: 'number';

		// === Quota Value Validation ===
		$raw_quota   = $input['daily_quota_value'] ?? $defaults['daily_quota_value'];
		$quota_value = absint( $raw_quota );

		if ( $quota_value < 1 ) {
			add_settings_error(
				'wpr_settings',
				'quota_too_low',
				__( 'Daily quota must be at least 1. Value has been corrected.', 'rd-post-republishing' ),
				'warning'
			);
			$quota_value = 1;
		}

		if ( $quota_value > 50 ) {
			add_settings_error(
				'wpr_settings',
				'quota_too_high',
				sprintf(
					/* translators: %d: the value that was submitted */
					__( 'Daily quota cannot exceed 50 posts per day. Your value of %d has been reduced to 50.', 'rd-post-republishing' ),
					absint( $raw_quota )
				),
				'warning'
			);
			$quota_value = 50;
		}

		$sanitized['daily_quota_value'] = $quota_value;

		// === Time Range Validation ===
		$start_hour = absint( $input['republish_start_hour'] ?? $defaults['republish_start_hour'] );
		$end_hour   = absint( $input['republish_end_hour'] ?? $defaults['republish_end_hour'] );

		// Validate hour range (0-23)
		if ( $start_hour > 23 ) {
			add_settings_error(
				'wpr_settings',
				'invalid_start_hour',
				__( 'Start hour must be between 0 and 23. Value has been corrected.', 'rd-post-republishing' ),
				'warning'
			);
			$start_hour = min( $start_hour, 23 );
		}

		if ( $end_hour > 23 ) {
			add_settings_error(
				'wpr_settings',
				'invalid_end_hour',
				__( 'End hour must be between 0 and 23. Value has been corrected.', 'rd-post-republishing' ),
				'warning'
			);
			$end_hour = min( $end_hour, 23 );
		}

		// Warn if start hour is after end hour (unusual but allowed for overnight ranges)
		if ( $start_hour > $end_hour ) {
			add_settings_error(
				'wpr_settings',
				'time_range_inverted',
				sprintf(
					/* translators: %1$d: start hour, %2$d: end hour */
					__( 'Note: Start hour (%1$d:00) is after end hour (%2$d:00). This creates an overnight publishing window.', 'rd-post-republishing' ),
					$start_hour,
					$end_hour
				),
				'info'
			);
		}

		$sanitized['republish_start_hour'] = $start_hour;
		$sanitized['republish_end_hour']   = $end_hour;

		// === Minimum Age Validation ===
		$raw_age  = $input['minimum_age_days'] ?? $defaults['minimum_age_days'];
		$age_days = absint( $raw_age );

		if ( $age_days < 7 ) {
			add_settings_error(
				'wpr_settings',
				'age_too_low',
				__( 'Minimum post age must be at least 7 days. Value has been corrected.', 'rd-post-republishing' ),
				'warning'
			);
			$age_days = 7;
		}

		if ( $age_days > 180 ) {
			add_settings_error(
				'wpr_settings',
				'age_too_high',
				sprintf(
					/* translators: %d: the value that was submitted */
					__( 'Minimum post age cannot exceed 180 days. Your value of %d has been reduced to 180.', 'rd-post-republishing' ),
					absint( $raw_age )
				),
				'warning'
			);
			$age_days = 180;
		}

		$sanitized['minimum_age_days'] = $age_days;

		// === Boolean Options ===
		$sanitized['maintain_chronological_order'] = ! empty( $input['maintain_chronological_order'] );
		$sanitized['wp_cron_enabled']              = ! empty( $input['wp_cron_enabled'] );
		$sanitized['debug_mode']                   = ! empty( $input['debug_mode'] );
		$sanitized['dry_run_mode']                 = ! empty( $input['dry_run_mode'] );

		// Warn about debug mode in production
		if ( $sanitized['debug_mode'] && ! defined( 'WP_DEBUG' ) ) {
			add_settings_error(
				'wpr_settings',
				'debug_without_wp_debug',
				__( 'Debug mode is enabled, but WP_DEBUG is not defined. Logs will only appear if WP_DEBUG_LOG is also enabled.', 'rd-post-republishing' ),
				'info'
			);
		}

		// Warn about dry-run mode being active
		if ( $sanitized['dry_run_mode'] ) {
			add_settings_error(
				'wpr_settings',
				'dry_run_active',
				__( 'Dry-run mode is active. Posts will NOT actually be republished until this is disabled.', 'rd-post-republishing' ),
				'info'
			);
		}

		// === Category Filter Validation ===
		$filter_type                       = $input['category_filter_type'] ?? 'none';
		$sanitized['category_filter_type'] = in_array( $filter_type, [ 'none', 'whitelist', 'blacklist' ], true )
			? $filter_type
			: 'none';

		$category_ids                     = array_filter( array_map( 'absint', (array) ( $input['category_filter_ids'] ?? [] ) ) );
		$sanitized['category_filter_ids'] = $category_ids;

		// Warn if filter type selected but no categories chosen
		if ( in_array( $sanitized['category_filter_type'], [ 'whitelist', 'blacklist' ], true ) && empty( $category_ids ) ) {
			$filter_label = 'whitelist' === $sanitized['category_filter_type']
				? __( 'whitelist', 'rd-post-republishing' )
				: __( 'blacklist', 'rd-post-republishing' );

			add_settings_error(
				'wpr_settings',
				'no_categories_selected',
				sprintf(
					/* translators: %s: filter type (whitelist/blacklist) */
					__( 'Category %s is enabled but no categories are selected. All posts will be affected.', 'rd-post-republishing' ),
					$filter_label
				),
				'warning'
			);
		}

		// Validate category IDs exist
		if ( ! empty( $category_ids ) ) {
			$existing_cats = get_terms(
				[
					'taxonomy'   => 'category',
					'hide_empty' => false,
					'include'    => $category_ids,
					'fields'     => 'ids',
				]
			);

			if ( ! is_wp_error( $existing_cats ) ) {
				$invalid_cats = array_diff( $category_ids, $existing_cats );
				if ( ! empty( $invalid_cats ) ) {
					add_settings_error(
						'wpr_settings',
						'invalid_categories',
						sprintf(
							/* translators: %s: comma-separated list of invalid category IDs */
							__( 'Invalid category IDs removed: %s', 'rd-post-republishing' ),
							implode( ', ', $invalid_cats )
						),
						'warning'
					);
					$sanitized['category_filter_ids'] = array_intersect( $category_ids, $existing_cats );
				}
			}
		}

		// === API Rate Limit Validation ===
		$raw_rate_limit = $input['api_rate_limit_seconds'] ?? $defaults['api_rate_limit_seconds'];
		$rate_limit     = absint( $raw_rate_limit );

		if ( $rate_limit < 60 && $rate_limit !== 1 ) {
			add_settings_error(
				'wpr_settings',
				'rate_limit_low',
				__( 'API rate limit below 60 seconds may cause excessive server load. Consider increasing this value.', 'rd-post-republishing' ),
				'warning'
			);
		}

		$sanitized['api_rate_limit_seconds'] = max( $rate_limit, 1 );

		// === Success Message ===
		// Only show success if there were no errors
		$errors     = get_settings_errors( 'wpr_settings' );
		$has_errors = false;
		foreach ( $errors as $error ) {
			if ( 'error' === $error['type'] ) {
				$has_errors = true;
				break;
			}
		}

		if ( ! $has_errors ) {
			add_settings_error(
				'wpr_settings',
				'settings_saved',
				__( 'Settings saved successfully.', 'rd-post-republishing' ),
				'success'
			);
		}

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
		$tabs        = [
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

	/**
	 * Get the asset file suffix based on script debug mode.
	 *
	 * Returns '.min' for production (minified assets) or '' for development.
	 *
	 * @since    1.0.0
	 * @return   string  '.min' or empty string.
	 */
	private function get_asset_suffix(): string {
		return defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
	}
}
