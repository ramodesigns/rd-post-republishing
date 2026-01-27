<?php
/**
 * History page display template.
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
?>

<div class="wrap rd-pr-history-wrap">
	<h1><?php esc_html_e( 'History', 'rd-post-republishing' ); ?></h1>
	<div class="rd-pr-history-content">
		<div class="rd-pr-history-filters">
			<div class="rd-pr-history-filter-group">
				<label for="rd-pr-history-limit"><?php esc_html_e( 'Show:', 'rd-post-republishing' ); ?></label>
				<select id="rd-pr-history-limit" class="rd-pr-history-select">
					<option value="100" selected><?php esc_html_e( '100 rows', 'rd-post-republishing' ); ?></option>
					<option value="200"><?php esc_html_e( '200 rows', 'rd-post-republishing' ); ?></option>
					<option value="500"><?php esc_html_e( '500 rows', 'rd-post-republishing' ); ?></option>
				</select>
			</div>
		</div>
		<div class="rd-pr-history-table-container">
			<table class="rd-pr-history-table">
				<thead>
					<tr>
						<th class="rd-pr-history-col-id"><?php esc_html_e( 'Post ID', 'rd-post-republishing' ); ?></th>
						<th class="rd-pr-history-col-title"><?php esc_html_e( 'Post Title', 'rd-post-republishing' ); ?></th>
						<th class="rd-pr-history-col-datetime"><?php esc_html_e( 'Republished Date/Time', 'rd-post-republishing' ); ?></th>
					</tr>
				</thead>
				<tbody id="rd-pr-history-tbody">
					<tr class="rd-pr-history-loading">
						<td colspan="3"><?php esc_html_e( 'Loading history...', 'rd-post-republishing' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>
