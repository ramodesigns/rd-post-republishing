<?php

declare(strict_types=1);

/**
 * Schedule tab template
 *
 * Displays the 7-day republishing outlook with post previews.
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

$repository = new Repository();
$query = new Query( $repository );
$settings = $repository->get_settings();

// Get 7-day preview
$preview = $query->get_republishing_preview( 7 );

// Get today's already republished posts
$today = wp_date( 'Y-m-d' );
$today_completed = $repository->get_history( [
	'date_from' => $today . ' 00:00:00',
	'date_to'   => $today . ' 23:59:59',
	'status'    => 'success',
	'limit'     => 50,
] );
?>

<div class="wpr-schedule">
	<div class="wpr-schedule-header">
		<h2><?php esc_html_e( '7-Day Republishing Outlook', 'rd-post-republishing' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Preview of posts scheduled for republishing. Future days are estimates based on current settings.', 'rd-post-republishing' ); ?>
		</p>
	</div>

	<div class="wpr-schedule-grid">
		<?php
		$day_index = 0;
		foreach ( $preview as $date => $posts ) :
			$is_today = ( $date === $today );
			$date_obj = new DateTimeImmutable( $date, wp_timezone() );
			$day_name = $is_today ? __( 'Today', 'rd-post-republishing' ) : $date_obj->format( 'l' );
			?>
			<div class="wpr-schedule-day <?php echo $is_today ? 'wpr-schedule-today' : ''; ?>">
				<div class="wpr-schedule-day-header">
					<span class="wpr-day-name"><?php echo esc_html( $day_name ); ?></span>
					<span class="wpr-day-date"><?php echo esc_html( $date_obj->format( 'M j, Y' ) ); ?></span>
					<span class="wpr-day-count">
						<?php
						$count = count( $posts );
						if ( $is_today ) {
							$completed = count( $today_completed );
							printf(
								/* translators: 1: completed count, 2: total count */
								esc_html__( '%1$d / %2$d posts', 'rd-post-republishing' ),
								$completed,
								$completed + $count
							);
						} else {
							printf(
								/* translators: %d: post count */
								esc_html( _n( '%d post', '%d posts', $count, 'rd-post-republishing' ) ),
								$count
							);
						}
						?>
					</span>
				</div>

				<div class="wpr-schedule-day-posts">
					<?php if ( $is_today && ! empty( $today_completed ) ) : ?>
						<?php foreach ( $today_completed as $record ) : ?>
							<?php $post = get_post( $record->post_id ); ?>
							<div class="wpr-schedule-post wpr-schedule-post-completed">
								<span class="wpr-post-status dashicons dashicons-yes-alt"></span>
								<div class="wpr-post-info">
									<?php if ( $post ) : ?>
										<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>" class="wpr-post-title">
											<?php echo esc_html( $post->post_title ); ?>
										</a>
									<?php else : ?>
										<span class="wpr-post-title">
											<?php echo esc_html( sprintf( __( 'Post #%d', 'rd-post-republishing' ), $record->post_id ) ); ?>
										</span>
									<?php endif; ?>
									<span class="wpr-post-meta">
										<?php echo esc_html( sprintf(
											/* translators: %s: time */
											__( 'Republished at %s', 'rd-post-republishing' ),
											wp_date( 'g:i A', strtotime( $record->republish_date ) )
										) ); ?>
									</span>
								</div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>

					<?php if ( empty( $posts ) && ( ! $is_today || empty( $today_completed ) ) ) : ?>
						<div class="wpr-schedule-empty">
							<?php esc_html_e( 'No posts scheduled', 'rd-post-republishing' ); ?>
						</div>
					<?php else : ?>
						<?php foreach ( $posts as $post ) : ?>
							<div class="wpr-schedule-post wpr-schedule-post-pending">
								<span class="wpr-post-status dashicons dashicons-clock"></span>
								<div class="wpr-post-info">
									<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>" class="wpr-post-title">
										<?php echo esc_html( $post->post_title ); ?>
									</a>
									<span class="wpr-post-meta">
										<?php
										$type_obj = get_post_type_object( $post->post_type );
										$type_name = $type_obj ? $type_obj->labels->singular_name : $post->post_type;
										echo esc_html( sprintf(
											/* translators: 1: post type, 2: original date */
											__( '%1$s · Originally: %2$s', 'rd-post-republishing' ),
											$type_name,
											wp_date( 'M j, Y', strtotime( $post->post_date ) )
										) );
										?>
									</span>
								</div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>
			<?php
			$day_index++;
		endforeach;
		?>
	</div>

	<!-- Schedule Legend -->
	<div class="wpr-schedule-legend">
		<div class="wpr-legend-item">
			<span class="dashicons dashicons-yes-alt wpr-legend-completed"></span>
			<span><?php esc_html_e( 'Completed', 'rd-post-republishing' ); ?></span>
		</div>
		<div class="wpr-legend-item">
			<span class="dashicons dashicons-clock wpr-legend-pending"></span>
			<span><?php esc_html_e( 'Pending', 'rd-post-republishing' ); ?></span>
		</div>
		<div class="wpr-legend-item">
			<span class="wpr-legend-note">
				<?php esc_html_e( 'Note: Future schedules are estimates and may change based on settings.', 'rd-post-republishing' ); ?>
			</span>
		</div>
	</div>

	<!-- Current Settings Summary -->
	<div class="wpr-schedule-settings">
		<h3><?php esc_html_e( 'Current Schedule Settings', 'rd-post-republishing' ); ?></h3>
		<table class="widefat striped">
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Daily Quota', 'rd-post-republishing' ); ?></td>
					<td>
						<?php
						if ( 'percentage' === ( $settings['daily_quota_type'] ?? 'number' ) ) {
							printf(
								/* translators: %d: percentage */
								esc_html__( '%d%% of eligible posts (max 50)', 'rd-post-republishing' ),
								(int) ( $settings['daily_quota_value'] ?? 5 )
							);
						} else {
							printf(
								/* translators: %d: number of posts */
								esc_html( _n( '%d post per day', '%d posts per day', (int) ( $settings['daily_quota_value'] ?? 5 ), 'rd-post-republishing' ) ),
								(int) ( $settings['daily_quota_value'] ?? 5 )
							);
						}
						?>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Publish Time Window', 'rd-post-republishing' ); ?></td>
					<td>
						<?php
						printf(
							/* translators: 1: start time, 2: end time */
							esc_html__( '%1$s to %2$s', 'rd-post-republishing' ),
							sprintf( '%02d:00', (int) ( $settings['republish_start_hour'] ?? 9 ) ),
							sprintf( '%02d:00', (int) ( $settings['republish_end_hour'] ?? 17 ) )
						);
						?>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Minimum Post Age', 'rd-post-republishing' ); ?></td>
					<td>
						<?php
						printf(
							/* translators: %d: number of days */
							esc_html( _n( '%d day', '%d days', (int) ( $settings['minimum_age_days'] ?? 30 ), 'rd-post-republishing' ) ),
							(int) ( $settings['minimum_age_days'] ?? 30 )
						);
						?>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Order Preservation', 'rd-post-republishing' ); ?></td>
					<td>
						<?php
						echo ! empty( $settings['maintain_chronological_order'] )
							? esc_html__( 'Maintaining chronological order', 'rd-post-republishing' )
							: esc_html__( 'Random order', 'rd-post-republishing' );
						?>
					</td>
				</tr>
			</tbody>
		</table>
		<p class="wpr-settings-link">
			<a href="<?php echo esc_url( add_query_arg( 'tab', 'settings', admin_url( 'options-general.php?page=rd-post-republishing' ) ) ); ?>">
				<?php esc_html_e( 'Modify Settings', 'rd-post-republishing' ); ?> &rarr;
			</a>
		</p>
	</div>
</div>
