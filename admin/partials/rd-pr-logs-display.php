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
