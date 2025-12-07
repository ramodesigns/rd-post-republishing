<?php

declare(strict_types=1);

/**
 * Overview tab template
 *
 * Displays dashboard overview with statistics and system status.
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
use WPR\Republisher\Republisher\Query;
use WPR\Republisher\Scheduler\Cron;

$repository = new Repository();
$query = new Query( $repository );
$cron = new Cron( $repository );

$settings = $repository->get_settings();
$cron_status = $cron->get_status();
$eligible_stats = $query->get_eligible_stats();
$today_count = $repository->get_today_republish_count();
$remaining_quota = $query->calculate_quota( $settings );

// Get recent history
$recent_history = $repository->get_history( [
	'limit' => 5,
	'orderby' => 'created_at',
	'order' => 'DESC',
] );
?>

<div class="wpr-overview">
	<!-- Statistics Cards -->
	<div class="wpr-stats-grid">
		<div class="wpr-stat-card">
			<span class="wpr-stat-icon dashicons dashicons-admin-post"></span>
			<div class="wpr-stat-content">
				<span class="wpr-stat-number"><?php echo esc_html( (string) array_sum( $eligible_stats ) ); ?></span>
				<span class="wpr-stat-label"><?php esc_html_e( 'Eligible Posts', 'rd-post-republishing' ); ?></span>
			</div>
		</div>

		<div class="wpr-stat-card">
			<span class="wpr-stat-icon dashicons dashicons-update"></span>
			<div class="wpr-stat-content">
				<span class="wpr-stat-number"><?php echo esc_html( (string) $today_count ); ?></span>
				<span class="wpr-stat-label"><?php esc_html_e( 'Republished Today', 'rd-post-republishing' ); ?></span>
			</div>
		</div>

		<div class="wpr-stat-card">
			<span class="wpr-stat-icon dashicons dashicons-clock"></span>
			<div class="wpr-stat-content">
				<span class="wpr-stat-number"><?php echo esc_html( (string) $remaining_quota ); ?></span>
				<span class="wpr-stat-label"><?php esc_html_e( 'Remaining Quota', 'rd-post-republishing' ); ?></span>
			</div>
		</div>

		<div class="wpr-stat-card">
			<span class="wpr-stat-icon dashicons dashicons-calendar-alt"></span>
			<div class="wpr-stat-content">
				<span class="wpr-stat-number"><?php echo esc_html( (string) ( $settings['minimum_age_days'] ?? 30 ) ); ?></span>
				<span class="wpr-stat-label"><?php esc_html_e( 'Min Age (Days)', 'rd-post-republishing' ); ?></span>
			</div>
		</div>
	</div>

	<div class="wpr-overview-columns">
		<!-- Post Type Breakdown -->
		<div class="wpr-overview-section">
			<h3><?php esc_html_e( 'Eligible Posts by Type', 'rd-post-republishing' ); ?></h3>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Post Type', 'rd-post-republishing' ); ?></th>
						<th><?php esc_html_e( 'Count', 'rd-post-republishing' ); ?></th>
						<th><?php esc_html_e( 'Status', 'rd-post-republishing' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $eligible_stats ) ) : ?>
						<tr>
							<td colspan="3"><?php esc_html_e( 'No post types configured.', 'rd-post-republishing' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $eligible_stats as $post_type => $count ) : ?>
							<?php $type_obj = get_post_type_object( $post_type ); ?>
							<tr>
								<td><?php echo esc_html( $type_obj ? $type_obj->labels->singular_name : $post_type ); ?></td>
								<td><?php echo esc_html( (string) $count ); ?></td>
								<td>
									<span class="wpr-status wpr-status-enabled">
										<?php esc_html_e( 'Enabled', 'rd-post-republishing' ); ?>
									</span>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<!-- System Status -->
		<div class="wpr-overview-section">
			<h3><?php esc_html_e( 'System Status', 'rd-post-republishing' ); ?></h3>
			<table class="widefat striped wpr-status-table">
				<tbody>
					<tr>
						<td><?php esc_html_e( 'WP Cron', 'rd-post-republishing' ); ?></td>
						<td>
							<?php if ( $cron_status['wp_cron_disabled'] ) : ?>
								<span class="wpr-status wpr-status-error">
									<?php esc_html_e( 'Disabled (DISABLE_WP_CRON)', 'rd-post-republishing' ); ?>
								</span>
							<?php elseif ( $cron_status['wp_cron_enabled'] ) : ?>
								<span class="wpr-status wpr-status-success">
									<?php esc_html_e( 'Active', 'rd-post-republishing' ); ?>
								</span>
							<?php else : ?>
								<span class="wpr-status wpr-status-warning">
									<?php esc_html_e( 'Disabled in Settings', 'rd-post-republishing' ); ?>
								</span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Next Scheduled Run', 'rd-post-republishing' ); ?></td>
						<td>
							<?php if ( $cron_status['daily_republishing']['scheduled'] ) : ?>
								<?php echo esc_html( $cron_status['daily_republishing']['next_run'] ); ?>
							<?php else : ?>
								<span class="wpr-status wpr-status-warning">
									<?php esc_html_e( 'Not Scheduled', 'rd-post-republishing' ); ?>
								</span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Retry Pending', 'rd-post-republishing' ); ?></td>
						<td>
							<?php if ( $cron_status['retry']['scheduled'] ) : ?>
								<?php echo esc_html( $cron_status['retry']['next_run'] ); ?>
							<?php else : ?>
								<?php esc_html_e( 'None', 'rd-post-republishing' ); ?>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Dry Run Mode', 'rd-post-republishing' ); ?></td>
						<td>
							<?php if ( ! empty( $settings['dry_run_mode'] ) ) : ?>
								<span class="wpr-status wpr-status-warning">
									<?php esc_html_e( 'Enabled', 'rd-post-republishing' ); ?>
								</span>
							<?php else : ?>
								<?php esc_html_e( 'Disabled', 'rd-post-republishing' ); ?>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Debug Mode', 'rd-post-republishing' ); ?></td>
						<td>
							<?php if ( ! empty( $settings['debug_mode'] ) ) : ?>
								<span class="wpr-status wpr-status-warning">
									<?php esc_html_e( 'Enabled', 'rd-post-republishing' ); ?>
								</span>
							<?php else : ?>
								<?php esc_html_e( 'Disabled', 'rd-post-republishing' ); ?>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

	<!-- Recent Activity -->
	<div class="wpr-overview-section wpr-full-width">
		<h3><?php esc_html_e( 'Recent Activity', 'rd-post-republishing' ); ?></h3>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Post', 'rd-post-republishing' ); ?></th>
					<th><?php esc_html_e( 'Type', 'rd-post-republishing' ); ?></th>
					<th><?php esc_html_e( 'Republished', 'rd-post-republishing' ); ?></th>
					<th><?php esc_html_e( 'Status', 'rd-post-republishing' ); ?></th>
					<th><?php esc_html_e( 'Trigger', 'rd-post-republishing' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $recent_history ) ) : ?>
					<tr>
						<td colspan="5"><?php esc_html_e( 'No republishing history yet.', 'rd-post-republishing' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $recent_history as $record ) : ?>
						<?php $post = get_post( $record->post_id ); ?>
						<tr>
							<td>
								<?php if ( $post ) : ?>
									<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>">
										<?php echo esc_html( $post->post_title ); ?>
									</a>
								<?php else : ?>
									<?php echo esc_html( sprintf( __( 'Post #%d (deleted)', 'rd-post-republishing' ), $record->post_id ) ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $record->post_type ); ?></td>
							<td><?php echo esc_html( $record->republish_date ); ?></td>
							<td>
								<span class="wpr-status wpr-status-<?php echo esc_attr( $record->status ); ?>">
									<?php echo esc_html( ucfirst( $record->status ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( ucfirst( $record->triggered_by ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<p class="wpr-view-all">
			<a href="<?php echo esc_url( add_query_arg( 'tab', 'logs', admin_url( 'options-general.php?page=rd-post-republishing' ) ) ); ?>">
				<?php esc_html_e( 'View All History', 'rd-post-republishing' ); ?> &rarr;
			</a>
		</p>
	</div>

	<!-- Quick Actions -->
	<div class="wpr-overview-section wpr-full-width">
		<h3><?php esc_html_e( 'Quick Actions', 'rd-post-republishing' ); ?></h3>
		<div class="wpr-quick-actions">
			<button type="button" class="button button-secondary" id="wpr-dry-run">
				<span class="dashicons dashicons-visibility"></span>
				<?php esc_html_e( 'Run Dry-Run', 'rd-post-republishing' ); ?>
			</button>
			<button type="button" class="button button-primary" id="wpr-manual-trigger">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Trigger Now', 'rd-post-republishing' ); ?>
			</button>
		</div>
		<div id="wpr-action-result" class="wpr-action-result" style="display: none;"></div>
	</div>
</div>
