<?php
/**
 * Settings page display template.
 *
 * @link       https://www.paulramotowski.com
 * @since      1.0.0
 *
 * @package    Rd_Post_Republishing
 * @subpackage Rd_Post_Republishing/admin/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Generate time options for dropdowns (2am - 10pm, values 2-22)
$time_options = array();
for ( $hour = 2; $hour <= 22; $hour++ ) {
	if ( $hour < 12 ) {
		$display = $hour . 'am';
	} elseif ( $hour === 12 ) {
		$display = '12pm';
	} else {
		$display = ( $hour - 12 ) . 'pm';
	}
	$time_options[ $hour ] = $display;
}
?>

<div class="wrap rd-pr-settings-wrap">
	<h1><?php esc_html_e( 'Settings', 'rd-post-republishing' ); ?></h1>

	<div class="rd-pr-settings-content">
		<form id="rd-pr-settings-form" class="rd-pr-form">

			<!-- Active Checkbox -->
			<div class="rd-pr-field-group">
				<label class="rd-pr-field-label" for="rd-pr-active">
					<?php esc_html_e( 'Active', 'rd-post-republishing' ); ?>
				</label>
				<div class="rd-pr-field-input">
					<label class="rd-pr-toggle">
						<input type="checkbox" id="rd-pr-active" name="active" value="1">
						<span class="rd-pr-toggle-slider"></span>
					</label>
					<span class="rd-pr-field-description">
						<?php esc_html_e( 'Enable or disable automatic post republishing', 'rd-post-republishing' ); ?>
					</span>
				</div>
			</div>

			<!-- Posts Per Day Slider -->
			<div class="rd-pr-field-group">
				<label class="rd-pr-field-label" for="rd-pr-posts-per-day">
					<?php esc_html_e( 'Posts Per Day', 'rd-post-republishing' ); ?>
				</label>
				<div class="rd-pr-field-input">
					<div class="rd-pr-slider-container">
						<input type="range" id="rd-pr-posts-per-day" name="posts_per_day" min="1" max="10" value="1" class="rd-pr-slider">
						<output id="rd-pr-posts-per-day-value" class="rd-pr-slider-value">1</output>
					</div>
					<span class="rd-pr-field-description">
						<?php esc_html_e( 'Number of posts to republish per day (1-10)', 'rd-post-republishing' ); ?>
					</span>
				</div>
			</div>

			<!-- Publish Start Time Dropdown -->
			<div class="rd-pr-field-group">
				<label class="rd-pr-field-label" for="rd-pr-start-time">
					<?php esc_html_e( 'Publish Start Time', 'rd-post-republishing' ); ?>
				</label>
				<div class="rd-pr-field-input">
					<select id="rd-pr-start-time" name="publish_start_time" class="rd-pr-select">
						<?php foreach ( $time_options as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>">
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<span class="rd-pr-field-description">
						<?php esc_html_e( 'Earliest time of day to republish posts', 'rd-post-republishing' ); ?>
					</span>
				</div>
			</div>

			<!-- Publish End Time Dropdown -->
			<div class="rd-pr-field-group">
				<label class="rd-pr-field-label" for="rd-pr-end-time">
					<?php esc_html_e( 'Publish End Time', 'rd-post-republishing' ); ?>
				</label>
				<div class="rd-pr-field-input">
					<select id="rd-pr-end-time" name="publish_end_time" class="rd-pr-select">
						<?php foreach ( $time_options as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>">
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<span class="rd-pr-field-description">
						<?php esc_html_e( 'Latest time of day to republish posts', 'rd-post-republishing' ); ?>
					</span>
				</div>
			</div>

			<!-- Save Button -->
			<div class="rd-pr-field-group rd-pr-submit-group">
				<button type="submit" id="rd-pr-save-settings" class="rd-pr-button rd-pr-button-primary">
					<?php esc_html_e( 'Save', 'rd-post-republishing' ); ?>
				</button>
			</div>

		</form>
	</div>

	<!-- Posting Calendar Panel -->
	<div class="rd-pr-calendar-panel">
		<h2><?php esc_html_e( 'Posting Calendar', 'rd-post-republishing' ); ?></h2>
		<div class="rd-pr-calendar-content">
			<div id="rd-pr-calendar-grid" class="rd-pr-calendar-grid">
				<!-- Calendar days will be populated via JavaScript -->
				<div class="rd-pr-calendar-loading">
					<?php esc_html_e( 'Loading posting schedule...', 'rd-post-republishing' ); ?>
				</div>
			</div>
		</div>
	</div>
</div>
