/**
 * Logs page JavaScript.
 *
 * @package    Rd_Post_Republishing
 * @subpackage Rd_Post_Republishing/admin/js
 */

(function( $ ) {
	'use strict';

	$(document).ready(function() {

		var $tbody = $('#rd-pr-logs-tbody');

		/**
		 * Fetch logs from the API
		 */
		function fetchLogs() {
			showLoading();

			$.ajax({
				url: rdPrLogs.restUrl + '/retrieve',
				method: 'GET',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', rdPrLogs.nonce);
				},
				success: function(response) {
					if (response.success && response.data) {
						renderLogs(response.data);
					} else {
						showError('No logs found.');
					}
				},
				error: function(xhr, status, error) {
					var message = 'Failed to load logs.';
					if (xhr.responseJSON && xhr.responseJSON.message) {
						message = xhr.responseJSON.message;
					}
					showError(message);
				}
			});
		}

		/**
		 * Sort logs by datetime descending (most recent first)
		 */
		function sortLogsByDatetime(logs) {
			return logs.sort(function(a, b) {
				var dateA = new Date(a.datetime);
				var dateB = new Date(b.datetime);
				return dateB - dateA;
			});
		}

		/**
		 * Format datetime for display
		 */
		function formatDatetime(datetime) {
			var date = new Date(datetime);

			// Format: DD/MM/YYYY HH:MM:SS
			var day = String(date.getDate()).padStart(2, '0');
			var month = String(date.getMonth() + 1).padStart(2, '0');
			var year = date.getFullYear();
			var hours = String(date.getHours()).padStart(2, '0');
			var minutes = String(date.getMinutes()).padStart(2, '0');
			var seconds = String(date.getSeconds()).padStart(2, '0');

			return day + '/' + month + '/' + year + ' ' + hours + ':' + minutes + ':' + seconds;
		}

		/**
		 * Escape HTML to prevent XSS
		 */
		function escapeHtml(text) {
			var div = document.createElement('div');
			div.textContent = text;
			return div.innerHTML;
		}

		/**
		 * Render logs in the table
		 */
		function renderLogs(logs) {
			if (logs.length === 0) {
				showEmpty();
				return;
			}

			// Sort by datetime descending
			var sortedLogs = sortLogsByDatetime(logs);

			var html = '';
			sortedLogs.forEach(function(log) {
				html += '<tr>';
				html += '<td class="rd-pr-logs-col-datetime">' + formatDatetime(log.datetime) + '</td>';
				html += '<td class="rd-pr-logs-col-type"><span class="rd-pr-log-type rd-pr-log-type-' + escapeHtml(log.type) + '">' + escapeHtml(log.type) + '</span></td>';
				html += '<td class="rd-pr-logs-col-entry">' + escapeHtml(log.entry) + '</td>';
				html += '</tr>';
			});

			$tbody.html(html);
		}

		/**
		 * Show loading state
		 */
		function showLoading() {
			$tbody.html('<tr class="rd-pr-logs-loading"><td colspan="3">Loading logs...</td></tr>');
		}

		/**
		 * Show error state
		 */
		function showError(message) {
			$tbody.html('<tr class="rd-pr-logs-error"><td colspan="3">' + escapeHtml(message) + '</td></tr>');
		}

		/**
		 * Show empty state
		 */
		function showEmpty() {
			$tbody.html('<tr class="rd-pr-logs-empty"><td colspan="3">No logs found.</td></tr>');
		}

		// Fetch logs on page load
		fetchLogs();

	});

})( jQuery );
