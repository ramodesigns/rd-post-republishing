<?php
/**
 * Logs page display template.
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

<div class="wrap rd-pr-logs-wrap">
	<h1><?php esc_html_e( 'Logs', 'rd-post-republishing' ); ?></h1>
	<div class="rd-pr-logs-content">
		<div class="rd-pr-logs-filters">
			<div class="rd-pr-logs-filter-group">
				<label for="rd-pr-logs-type-filter"><?php esc_html_e( 'Type:', 'rd-post-republishing' ); ?></label>
				<select id="rd-pr-logs-type-filter" class="rd-pr-logs-select">
					<option value=""><?php esc_html_e( 'All', 'rd-post-republishing' ); ?></option>
				</select>
			</div>
			<div class="rd-pr-logs-filter-group">
				<label for="rd-pr-logs-limit"><?php esc_html_e( 'Show:', 'rd-post-republishing' ); ?></label>
				<select id="rd-pr-logs-limit" class="rd-pr-logs-select">
					<option value="100" selected><?php esc_html_e( '100 rows', 'rd-post-republishing' ); ?></option>
					<option value="200"><?php esc_html_e( '200 rows', 'rd-post-republishing' ); ?></option>
					<option value="500"><?php esc_html_e( '500 rows', 'rd-post-republishing' ); ?></option>
					<option value="1000"><?php esc_html_e( '1000 rows', 'rd-post-republishing' ); ?></option>
				</select>
			</div>
		</div>
		<div class="rd-pr-logs-table-container">
			<table id="rd-pr-logs-table" class="rd-pr-logs-table">
				<thead>
					<tr>
						<th class="rd-pr-logs-col-datetime"><?php esc_html_e( 'Date/Time', 'rd-post-republishing' ); ?></th>
						<th class="rd-pr-logs-col-type"><?php esc_html_e( 'Type', 'rd-post-republishing' ); ?></th>
						<th class="rd-pr-logs-col-entry"><?php esc_html_e( 'Entry', 'rd-post-republishing' ); ?></th>
					</tr>
				</thead>
				<tbody id="rd-pr-logs-tbody">
					<tr class="rd-pr-logs-loading">
						<td colspan="3"><?php esc_html_e( 'Loading logs...', 'rd-post-republishing' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>
