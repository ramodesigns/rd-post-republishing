<?php

declare(strict_types=1);

/**
 * History & Logs tab template
 *
 * Displays republishing history and audit logs with filtering.
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

// Get filter parameters
$log_type = isset( $_GET['log_type'] ) ? sanitize_text_field( wp_unslash( $_GET['log_type'] ) ) : 'history';
$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
$post_type_filter = isset( $_GET['post_type'] ) ? sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) : '';
$trigger_filter = isset( $_GET['trigger'] ) ? sanitize_text_field( wp_unslash( $_GET['trigger'] ) ) : '';
$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$per_page = 50;
$offset = ( $current_page - 1 ) * $per_page;

// Get data based on log type
if ( 'audit' === $log_type ) {
	$audit_action_filter = isset( $_GET['action_type'] ) ? sanitize_text_field( wp_unslash( $_GET['action_type'] ) ) : '';
	$records = $repository->get_audit_logs( [
		'action' => $audit_action_filter ?: null,
		'limit'  => $per_page,
		'offset' => $offset,
	] );
	$total_records = $repository->get_audit_count( $audit_action_filter ? [ 'action' => $audit_action_filter ] : [] );
} else {
	$filter_args = [
		'limit'  => $per_page,
		'offset' => $offset,
	];
	if ( $status_filter ) {
		$filter_args['status'] = $status_filter;
	}
	if ( $post_type_filter ) {
		$filter_args['post_type'] = $post_type_filter;
	}
	$records = $repository->get_history( $filter_args );
	$total_records = $repository->get_history_count( array_filter( [
		'status'    => $status_filter ?: null,
		'post_type' => $post_type_filter ?: null,
	] ) );
}

$total_pages = (int) ceil( $total_records / $per_page );

// Build base URL for pagination
$base_url = add_query_arg( array_filter( [
	'page'        => 'rd-post-republishing',
	'tab'         => 'logs',
	'log_type'    => $log_type,
	'status'      => $status_filter,
	'post_type'   => $post_type_filter,
	'action_type' => $audit_action_filter ?? '',
] ), admin_url( 'options-general.php' ) );

// Get available post types for filter
$settings = $repository->get_settings();
$enabled_post_types = $settings['enabled_post_types'] ?? [ 'post' ];
?>

<div class="wpr-logs">
	<!-- Log Type Tabs -->
	<div class="wpr-logs-tabs">
		<a href="<?php echo esc_url( add_query_arg( 'log_type', 'history', remove_query_arg( [ 'paged', 'status' ] ) ) ); ?>"
		   class="wpr-logs-tab <?php echo 'history' === $log_type ? 'active' : ''; ?>">
			<?php esc_html_e( 'Republishing History', 'rd-post-republishing' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'log_type', 'audit', remove_query_arg( [ 'paged', 'status' ] ) ) ); ?>"
		   class="wpr-logs-tab <?php echo 'audit' === $log_type ? 'active' : ''; ?>">
			<?php esc_html_e( 'Audit Log', 'rd-post-republishing' ); ?>
		</a>
	</div>

	<?php if ( 'history' === $log_type ) : ?>
		<!-- History Filters -->
		<div class="wpr-logs-filters">
			<form method="get" action="" class="wpr-filter-form">
				<input type="hidden" name="page" value="rd-post-republishing">
				<input type="hidden" name="tab" value="logs">
				<input type="hidden" name="log_type" value="history">

				<label for="wpr-status-filter"><?php esc_html_e( 'Status:', 'rd-post-republishing' ); ?></label>
				<select name="status" id="wpr-status-filter">
					<option value=""><?php esc_html_e( 'All Statuses', 'rd-post-republishing' ); ?></option>
					<option value="success" <?php selected( $status_filter, 'success' ); ?>><?php esc_html_e( 'Success', 'rd-post-republishing' ); ?></option>
					<option value="failed" <?php selected( $status_filter, 'failed' ); ?>><?php esc_html_e( 'Failed', 'rd-post-republishing' ); ?></option>
					<option value="retrying" <?php selected( $status_filter, 'retrying' ); ?>><?php esc_html_e( 'Retrying', 'rd-post-republishing' ); ?></option>
				</select>

				<label for="wpr-post-type-filter"><?php esc_html_e( 'Post Type:', 'rd-post-republishing' ); ?></label>
				<select name="post_type" id="wpr-post-type-filter">
					<option value=""><?php esc_html_e( 'All Types', 'rd-post-republishing' ); ?></option>
					<?php foreach ( $enabled_post_types as $pt ) : ?>
						<?php $pt_obj = get_post_type_object( $pt ); ?>
						<?php if ( $pt_obj ) : ?>
							<option value="<?php echo esc_attr( $pt ); ?>" <?php selected( $post_type_filter, $pt ); ?>>
								<?php echo esc_html( $pt_obj->labels->singular_name ); ?>
							</option>
						<?php endif; ?>
					<?php endforeach; ?>
				</select>

				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'rd-post-republishing' ); ?></button>

				<?php if ( $status_filter || $post_type_filter ) : ?>
					<a href="<?php echo esc_url( remove_query_arg( [ 'status', 'post_type', 'paged' ] ) ); ?>" class="button">
						<?php esc_html_e( 'Clear Filters', 'rd-post-republishing' ); ?>
					</a>
				<?php endif; ?>
			</form>

			<div class="wpr-export-buttons">
				<button type="button" class="button wpr-export-history-btn">
					<span class="dashicons dashicons-download"></span>
					<?php esc_html_e( 'Export CSV', 'rd-post-republishing' ); ?>
				</button>
			</div>
		</div>

		<!-- History Table -->
		<table class="widefat striped wpr-logs-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Post', 'rd-post-republishing' ); ?></th>
					<th><?php esc_html_e( 'Type', 'rd-post-republishing' ); ?></th>
					<th><?php esc_html_e( 'Original Date', 'rd-post-republishing' ); ?></th>
					<th><?php esc_html_e( 'Republish Date', 'rd-post-republishing' ); ?></th>
					<th><?php esc_html_e( 'Status', 'rd-post-republishing' ); ?></th>
					<th><?php esc_html_e( 'Trigger', 'rd-post-republishing' ); ?></th>
					<th><?php esc_html_e( 'Time', 'rd-post-republishing' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $records ) ) : ?>
					<tr>
						<td colspan="7"><?php esc_html_e( 'No history records found.', 'rd-post-republishing' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $records as $record ) : ?>
						<?php $post = get_post( $record->post_id ); ?>
						<tr>
							<td>
								<?php if ( $post ) : ?>
									<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>">
										<?php echo esc_html( $post->post_title ); ?>
									</a>
									<br>
									<small class="wpr-post-id">#<?php echo esc_html( (string) $record->post_id ); ?></small>
								<?php else : ?>
									<span class="wpr-deleted-post">
										<?php echo esc_html( sprintf( __( 'Post #%d (deleted)', 'rd-post-republishing' ), $record->post_id ) ); ?>
									</span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $record->post_type ); ?></td>
							<td><?php echo esc_html( wp_date( 'M j, Y g:i A', strtotime( $record->original_date ) ) ); ?></td>
							<td><?php echo esc_html( wp_date( 'M j, Y g:i A', strtotime( $record->republish_date ) ) ); ?></td>
							<td>
								<span class="wpr-status wpr-status-<?php echo esc_attr( $record->status ); ?>">
									<?php echo esc_html( ucfirst( $record->status ) ); ?>
								</span>
								<?php if ( $record->error_message ) : ?>
									<span class="wpr-error-tooltip" title="<?php echo esc_attr( $record->error_message ); ?>">
										<span class="dashicons dashicons-warning"></span>
									</span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( ucfirst( $record->triggered_by ) ); ?></td>
							<td>
								<?php if ( $record->execution_time ) : ?>
									<?php echo esc_html( number_format( $record->execution_time, 3 ) ); ?>s
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

	<?php else : ?>
		<!-- Audit Log Table -->
		<div class="wpr-logs-filters">
			<form method="get" action="" class="wpr-filter-form">
				<input type="hidden" name="page" value="rd-post-republishing">
				<input type="hidden" name="tab" value="logs">
				<input type="hidden" name="log_type" value="audit">

				<label for="wpr-action-type-filter"><?php esc_html_e( 'Action:', 'rd-post-republishing' ); ?></label>
				<select name="action_type" id="wpr-action-type-filter">
					<option value=""><?php esc_html_e( 'All Actions', 'rd-post-republishing' ); ?></option>
					<option value="settings_updated" <?php selected( $audit_action_filter ?? '', 'settings_updated' ); ?>><?php esc_html_e( 'Settings Updated', 'rd-post-republishing' ); ?></option>
					<option value="setting_changed" <?php selected( $audit_action_filter ?? '', 'setting_changed' ); ?>><?php esc_html_e( 'Setting Changed', 'rd-post-republishing' ); ?></option>
					<option value="settings_created" <?php selected( $audit_action_filter ?? '', 'settings_created' ); ?>><?php esc_html_e( 'Settings Created', 'rd-post-republishing' ); ?></option>
				</select>

				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'rd-post-republishing' ); ?></button>

				<?php if ( ! empty( $audit_action_filter ) ) : ?>
					<a href="<?php echo esc_url( remove_query_arg( [ 'action_type', 'paged' ] ) ); ?>" class="button">
						<?php esc_html_e( 'Clear Filter', 'rd-post-republishing' ); ?>
					</a>
				<?php endif; ?>
			</form>

			<div class="wpr-export-buttons">
				<button type="button" class="button wpr-export-audit-btn">
					<span class="dashicons dashicons-download"></span>
					<?php esc_html_e( 'Export CSV', 'rd-post-republishing' ); ?>
				</button>
			</div>
		</div>

		<table class="widefat striped wpr-logs-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date/Time', 'rd-post-republishing' ); ?></th>
					<th><?php esc_html_e( 'User', 'rd-post-republishing' ); ?></th>
					<th><?php esc_html_e( 'Action', 'rd-post-republishing' ); ?></th>
					<th><?php esc_html_e( 'Setting', 'rd-post-republishing' ); ?></th>
					<th><?php esc_html_e( 'Details', 'rd-post-republishing' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $records ) ) : ?>
					<tr>
						<td colspan="5"><?php esc_html_e( 'No audit records found.', 'rd-post-republishing' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $records as $record ) : ?>
						<?php $user = get_user_by( 'id', $record->user_id ); ?>
						<tr>
							<td><?php echo esc_html( wp_date( 'M j, Y g:i A', strtotime( $record->timestamp ) ) ); ?></td>
							<td>
								<?php if ( $user ) : ?>
									<?php echo esc_html( $user->display_name ); ?>
									<br>
									<small><?php echo esc_html( $user->user_email ); ?></small>
								<?php else : ?>
									<?php echo esc_html( sprintf( __( 'User #%d', 'rd-post-republishing' ), $record->user_id ) ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( str_replace( '_', ' ', ucfirst( $record->action ) ) ); ?></td>
							<td><?php echo esc_html( $record->setting_key ?? '—' ); ?></td>
							<td>
								<?php if ( $record->old_value || $record->new_value ) : ?>
									<button type="button" class="button button-small wpr-view-diff"
											data-old="<?php echo esc_attr( $record->old_value ?? '' ); ?>"
											data-new="<?php echo esc_attr( $record->new_value ?? '' ); ?>">
										<?php esc_html_e( 'View Changes', 'rd-post-republishing' ); ?>
									</button>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<!-- Pagination -->
	<?php if ( $total_pages > 1 ) : ?>
		<div class="wpr-pagination tablenav">
			<div class="tablenav-pages">
				<span class="displaying-num">
					<?php
					printf(
						/* translators: %s: number of items */
						esc_html( _n( '%s item', '%s items', $total_records, 'rd-post-republishing' ) ),
						number_format_i18n( $total_records )
					);
					?>
				</span>
				<span class="pagination-links">
					<?php if ( $current_page > 1 ) : ?>
						<a class="first-page button" href="<?php echo esc_url( add_query_arg( 'paged', 1, $base_url ) ); ?>">
							<span class="screen-reader-text"><?php esc_html_e( 'First page', 'rd-post-republishing' ); ?></span>
							<span aria-hidden="true">&laquo;</span>
						</a>
						<a class="prev-page button" href="<?php echo esc_url( add_query_arg( 'paged', $current_page - 1, $base_url ) ); ?>">
							<span class="screen-reader-text"><?php esc_html_e( 'Previous page', 'rd-post-republishing' ); ?></span>
							<span aria-hidden="true">&lsaquo;</span>
						</a>
					<?php else : ?>
						<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>
						<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>
					<?php endif; ?>

					<span class="paging-input">
						<span class="tablenav-paging-text">
							<?php echo esc_html( $current_page ); ?>
							<?php esc_html_e( 'of', 'rd-post-republishing' ); ?>
							<span class="total-pages"><?php echo esc_html( (string) $total_pages ); ?></span>
						</span>
					</span>

					<?php if ( $current_page < $total_pages ) : ?>
						<a class="next-page button" href="<?php echo esc_url( add_query_arg( 'paged', $current_page + 1, $base_url ) ); ?>">
							<span class="screen-reader-text"><?php esc_html_e( 'Next page', 'rd-post-republishing' ); ?></span>
							<span aria-hidden="true">&rsaquo;</span>
						</a>
						<a class="last-page button" href="<?php echo esc_url( add_query_arg( 'paged', $total_pages, $base_url ) ); ?>">
							<span class="screen-reader-text"><?php esc_html_e( 'Last page', 'rd-post-republishing' ); ?></span>
							<span aria-hidden="true">&raquo;</span>
						</a>
					<?php else : ?>
						<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>
						<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>
					<?php endif; ?>
				</span>
			</div>
		</div>
	<?php endif; ?>

	<!-- Retention Notice -->
	<div class="wpr-retention-notice">
		<p>
			<span class="dashicons dashicons-info"></span>
			<?php esc_html_e( 'History and audit logs are automatically purged after 365 days.', 'rd-post-republishing' ); ?>
		</p>
	</div>
</div>

<!-- Diff Modal -->
<div id="wpr-diff-modal" class="wpr-modal" style="display: none;">
	<div class="wpr-modal-content">
		<span class="wpr-modal-close">&times;</span>
		<h3><?php esc_html_e( 'Setting Changes', 'rd-post-republishing' ); ?></h3>
		<div class="wpr-diff-container">
			<div class="wpr-diff-column">
				<h4><?php esc_html_e( 'Previous Value', 'rd-post-republishing' ); ?></h4>
				<pre id="wpr-diff-old"></pre>
			</div>
			<div class="wpr-diff-column">
				<h4><?php esc_html_e( 'New Value', 'rd-post-republishing' ); ?></h4>
				<pre id="wpr-diff-new"></pre>
			</div>
		</div>
	</div>
</div>
