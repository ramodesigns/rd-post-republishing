<?php

declare(strict_types=1);

/**
 * Settings tab template
 *
 * Displays all plugin configuration options.
 *
 * @link       https://www.paulramotowski.com
 * @since      1.0.0
 *
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/admin/views
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPR\Republisher\Database\Repository;

$repository = new Repository();
$settings = $repository->get_settings();

// Get available post types
$post_types = get_post_types( [ 'public' => true ], 'objects' );
unset( $post_types['attachment'] ); // Remove media

// Get categories for filtering
$categories = get_categories( [
	'hide_empty' => false,
	'orderby'    => 'name',
	'order'      => 'ASC',
] );
?>

<div class="wpr-settings">
	<form method="post" action="options.php" id="wpr-settings-form">
		<?php settings_fields( 'wpr_settings_group' ); ?>

		<!-- Post Type Selection -->
		<div class="wpr-settings-section">
			<h3><?php esc_html_e( 'Post Types', 'rd-post-republishing' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Select which post types should be included in republishing.', 'rd-post-republishing' ); ?></p>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enabled Post Types', 'rd-post-republishing' ); ?></th>
					<td>
						<fieldset>
							<?php foreach ( $post_types as $type ) : ?>
								<label>
									<input type="checkbox"
										   name="wpr_settings[enabled_post_types][]"
										   value="<?php echo esc_attr( $type->name ); ?>"
										   <?php checked( in_array( $type->name, $settings['enabled_post_types'] ?? [], true ) ); ?>>
									<?php echo esc_html( $type->labels->name ); ?>
									<span class="description">(<?php echo esc_html( $type->name ); ?>)</span>
								</label><br>
							<?php endforeach; ?>
						</fieldset>
					</td>
				</tr>
			</table>
		</div>

		<!-- Daily Quota -->
		<div class="wpr-settings-section">
			<h3><?php esc_html_e( 'Daily Quota', 'rd-post-republishing' ); ?></h3>
			<p class="description"><?php esc_html_e( 'How many posts to republish per day. Maximum is 50 posts.', 'rd-post-republishing' ); ?></p>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Quota Type', 'rd-post-republishing' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="radio"
									   name="wpr_settings[daily_quota_type]"
									   value="number"
									   <?php checked( ( $settings['daily_quota_type'] ?? 'number' ), 'number' ); ?>
									   class="wpr-quota-type">
								<?php esc_html_e( 'Fixed Number', 'rd-post-republishing' ); ?>
							</label><br>
							<label>
								<input type="radio"
									   name="wpr_settings[daily_quota_type]"
									   value="percentage"
									   <?php checked( ( $settings['daily_quota_type'] ?? 'number' ), 'percentage' ); ?>
									   class="wpr-quota-type">
								<?php esc_html_e( 'Percentage of Eligible Posts', 'rd-post-republishing' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wpr_quota_value"><?php esc_html_e( 'Quota Value', 'rd-post-republishing' ); ?></label>
					</th>
					<td>
						<input type="number"
							   id="wpr_quota_value"
							   name="wpr_settings[daily_quota_value]"
							   value="<?php echo esc_attr( (string) ( $settings['daily_quota_value'] ?? 5 ) ); ?>"
							   min="1"
							   max="50"
							   class="small-text">
						<span id="wpr-quota-suffix">
							<?php echo ( $settings['daily_quota_type'] ?? 'number' ) === 'percentage' ? '%' : esc_html__( 'posts', 'rd-post-republishing' ); ?>
						</span>
						<p class="description" id="wpr-quota-description">
							<?php esc_html_e( 'Maximum 50 posts per day regardless of setting.', 'rd-post-republishing' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Time Configuration -->
		<div class="wpr-settings-section">
			<h3><?php esc_html_e( 'Publish Time Window', 'rd-post-republishing' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Posts will be republished with random times within this window.', 'rd-post-republishing' ); ?></p>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Time Range', 'rd-post-republishing' ); ?></th>
					<td>
						<select name="wpr_settings[republish_start_hour]" id="wpr_start_hour">
							<?php for ( $h = 0; $h < 24; $h++ ) : ?>
								<option value="<?php echo esc_attr( (string) $h ); ?>"
										<?php selected( (int) ( $settings['republish_start_hour'] ?? 9 ), $h ); ?>>
									<?php echo esc_html( sprintf( '%02d:00', $h ) ); ?>
								</option>
							<?php endfor; ?>
						</select>
						<span><?php esc_html_e( 'to', 'rd-post-republishing' ); ?></span>
						<select name="wpr_settings[republish_end_hour]" id="wpr_end_hour">
							<?php for ( $h = 0; $h < 24; $h++ ) : ?>
								<option value="<?php echo esc_attr( (string) $h ); ?>"
										<?php selected( (int) ( $settings['republish_end_hour'] ?? 17 ), $h ); ?>>
									<?php echo esc_html( sprintf( '%02d:00', $h ) ); ?>
								</option>
							<?php endfor; ?>
						</select>
						<p class="description">
							<?php
							printf(
								/* translators: %s: timezone name */
								esc_html__( 'Times are in site timezone (%s).', 'rd-post-republishing' ),
								esc_html( wp_timezone_string() )
							);
							?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Minimum Age -->
		<div class="wpr-settings-section">
			<h3><?php esc_html_e( 'Post Age Requirements', 'rd-post-republishing' ); ?></h3>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="wpr_min_age"><?php esc_html_e( 'Minimum Age', 'rd-post-republishing' ); ?></label>
					</th>
					<td>
						<input type="range"
							   id="wpr_min_age"
							   name="wpr_settings[minimum_age_days]"
							   value="<?php echo esc_attr( (string) ( $settings['minimum_age_days'] ?? 30 ) ); ?>"
							   min="7"
							   max="180"
							   step="1"
							   class="wpr-range-slider">
						<span id="wpr-min-age-display"><?php echo esc_html( (string) ( $settings['minimum_age_days'] ?? 30 ) ); ?></span>
						<?php esc_html_e( 'days', 'rd-post-republishing' ); ?>
						<p class="description">
							<?php esc_html_e( 'Posts must be at least this old before becoming eligible for republishing.', 'rd-post-republishing' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Chronological Order', 'rd-post-republishing' ); ?></th>
					<td>
						<label>
							<input type="checkbox"
								   name="wpr_settings[maintain_chronological_order]"
								   value="1"
								   <?php checked( ! empty( $settings['maintain_chronological_order'] ) ); ?>>
							<?php esc_html_e( 'Maintain chronological order of selected posts within the day', 'rd-post-republishing' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'When enabled, posts will be republished in the same relative order they were originally published.', 'rd-post-republishing' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Category Filtering -->
		<div class="wpr-settings-section">
			<h3><?php esc_html_e( 'Category Filtering', 'rd-post-republishing' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Optionally filter posts by category.', 'rd-post-republishing' ); ?></p>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Filter Type', 'rd-post-republishing' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="radio"
									   name="wpr_settings[category_filter_type]"
									   value="none"
									   <?php checked( ( $settings['category_filter_type'] ?? 'none' ), 'none' ); ?>
									   class="wpr-filter-type">
								<?php esc_html_e( 'No Filtering (Include All)', 'rd-post-republishing' ); ?>
							</label><br>
							<label>
								<input type="radio"
									   name="wpr_settings[category_filter_type]"
									   value="whitelist"
									   <?php checked( ( $settings['category_filter_type'] ?? 'none' ), 'whitelist' ); ?>
									   class="wpr-filter-type">
								<?php esc_html_e( 'Whitelist (Only Selected Categories)', 'rd-post-republishing' ); ?>
							</label><br>
							<label>
								<input type="radio"
									   name="wpr_settings[category_filter_type]"
									   value="blacklist"
									   <?php checked( ( $settings['category_filter_type'] ?? 'none' ), 'blacklist' ); ?>
									   class="wpr-filter-type">
								<?php esc_html_e( 'Blacklist (Exclude Selected Categories)', 'rd-post-republishing' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<tr id="wpr-category-select-row" style="<?php echo ( $settings['category_filter_type'] ?? 'none' ) === 'none' ? 'display:none;' : ''; ?>">
					<th scope="row">
						<label for="wpr_categories"><?php esc_html_e( 'Select Categories', 'rd-post-republishing' ); ?></label>
					</th>
					<td>
						<select name="wpr_settings[category_filter_ids][]"
								id="wpr_categories"
								multiple
								class="wpr-category-select"
								style="width: 300px; height: 150px;">
							<?php foreach ( $categories as $category ) : ?>
								<option value="<?php echo esc_attr( (string) $category->term_id ); ?>"
										<?php selected( in_array( $category->term_id, $settings['category_filter_ids'] ?? [], true ) ); ?>>
									<?php echo esc_html( $category->name ); ?>
									(<?php echo esc_html( (string) $category->count ); ?>)
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Hold Ctrl/Cmd to select multiple categories.', 'rd-post-republishing' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Advanced Options -->
		<div class="wpr-settings-section">
			<h3><?php esc_html_e( 'Advanced Options', 'rd-post-republishing' ); ?></h3>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'WP Cron', 'rd-post-republishing' ); ?></th>
					<td>
						<label>
							<input type="checkbox"
								   name="wpr_settings[wp_cron_enabled]"
								   value="1"
								   <?php checked( ! empty( $settings['wp_cron_enabled'] ) ); ?>>
							<?php esc_html_e( 'Enable automatic daily republishing via WP Cron', 'rd-post-republishing' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Disable this if you prefer to use the API endpoint with an external scheduler.', 'rd-post-republishing' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wpr_rate_limit"><?php esc_html_e( 'API Rate Limit', 'rd-post-republishing' ); ?></label>
					</th>
					<td>
						<input type="number"
							   id="wpr_rate_limit"
							   name="wpr_settings[api_rate_limit_seconds]"
							   value="<?php echo esc_attr( (string) ( $settings['api_rate_limit_seconds'] ?? 86400 ) ); ?>"
							   min="1"
							   class="regular-text">
						<?php esc_html_e( 'seconds', 'rd-post-republishing' ); ?>
						<p class="description">
							<?php esc_html_e( 'Minimum time between API trigger requests. Default: 86400 (1 day). Minimum: 1 second for testing.', 'rd-post-republishing' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Debug Mode', 'rd-post-republishing' ); ?></th>
					<td>
						<label>
							<input type="checkbox"
								   name="wpr_settings[debug_mode]"
								   value="1"
								   <?php checked( ! empty( $settings['debug_mode'] ) ); ?>>
							<?php esc_html_e( 'Enable debug mode', 'rd-post-republishing' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Shows detailed API responses and logs to error_log when WP_DEBUG_LOG is enabled.', 'rd-post-republishing' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Dry-Run Mode', 'rd-post-republishing' ); ?></th>
					<td>
						<label>
							<input type="checkbox"
								   name="wpr_settings[dry_run_mode]"
								   value="1"
								   <?php checked( ! empty( $settings['dry_run_mode'] ) ); ?>>
							<?php esc_html_e( 'Enable dry-run mode (simulation only)', 'rd-post-republishing' ); ?>
						</label>
						<p class="description wpr-warning">
							<?php esc_html_e( 'When enabled, no actual republishing occurs. Use for testing configurations.', 'rd-post-republishing' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- API Endpoint Info -->
		<div class="wpr-settings-section wpr-api-info">
			<h3><?php esc_html_e( 'API Endpoint', 'rd-post-republishing' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Use this endpoint to trigger republishing from external systems.', 'rd-post-republishing' ); ?></p>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Endpoint URL', 'rd-post-republishing' ); ?></th>
					<td>
						<code><?php echo esc_html( rest_url( 'republish/v1/trigger' ) ); ?></code>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Method', 'rd-post-republishing' ); ?></th>
					<td><code>POST</code></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Authentication', 'rd-post-republishing' ); ?></th>
					<td>
						<?php esc_html_e( 'WordPress Application Password (Basic Auth)', 'rd-post-republishing' ); ?>
						<br>
						<a href="<?php echo esc_url( admin_url( 'profile.php#application-passwords-section' ) ); ?>">
							<?php esc_html_e( 'Manage Application Passwords', 'rd-post-republishing' ); ?>
						</a>
					</td>
				</tr>
			</table>
		</div>

		<?php submit_button( __( 'Save Settings', 'rd-post-republishing' ) ); ?>
	</form>
</div>
