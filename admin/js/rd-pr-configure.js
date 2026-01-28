/**
 * History page JavaScript.
 *
 * @package    Rd_Post_Republishing
 * @subpackage Rd_Post_Republishing/admin/js
 */

(function( $ ) {
	'use strict';

	$(document).ready(function() {

		var $tbody = $('#rd-pr-history-tbody');
		var $limitFilter = $('#rd-pr-history-limit');

		/**
		 * Fetch history from the API with limit parameter
		 */
		function fetchHistory() {
			var limit = parseInt($limitFilter.val(), 10);
			showLoading();

			$.ajax({
				url: rdPrHistory.restUrl + '/retrieve?limit=' + limit,
				method: 'GET',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', rdPrHistory.nonce);
				},
				success: function(response) {
					if (response.success && response.data) {
						renderHistory(response.data);
					} else {
						showError('No history found.');
					}
				},
				error: function(xhr, status, error) {
					var message = 'Failed to load history.';
					if (xhr.responseJSON && xhr.responseJSON.message) {
						message = xhr.responseJSON.message;
					}
					showError(message);
				}
			});
		}

		/**
		 * Format timestamp for display (DD/MM/YY HH:MM:SS)
		 */
		function formatTimestamp(dateString) {
			if (!dateString) {
				return 'Invalid date';
			}

			// Parse the date string (format: "2026-01-26 14:05:02")
			var timestamp = new Date(dateString.replace(' ', 'T'));

			if (isNaN(timestamp.getTime())) {
				return 'Invalid date';
			}

			var day = String(timestamp.getDate()).padStart(2, '0');
			var month = String(timestamp.getMonth() + 1).padStart(2, '0');
			var year = String(timestamp.getFullYear()).slice(-2);
			var hours = String(timestamp.getHours()).padStart(2, '0');
			var minutes = String(timestamp.getMinutes()).padStart(2, '0');
			var seconds = String(timestamp.getSeconds()).padStart(2, '0');

			return day + '/' + month + '/' + year + ' ' + hours + ':' + minutes + ':' + seconds;
		}

		/**
		 * Escape HTML to prevent XSS
		 */
		function escapeHtml(text) {
			if (text === null || text === undefined) {
				return '';
			}
			var div = document.createElement('div');
			div.textContent = String(text);
			return div.innerHTML;
		}

		/**
		 * Render history in the table
		 */
		function renderHistory(history) {
			if (history.length === 0) {
				showEmpty();
				return;
			}

			// Sort by post_publish_datetime descending (latest first)
			var sortedHistory = history.slice().sort(function(a, b) {
				var dateA = new Date(a.post_publish_datetime.replace(' ', 'T'));
				var dateB = new Date(b.post_publish_datetime.replace(' ', 'T'));
				return dateB - dateA;
			});

			var html = '';

			sortedHistory.forEach(function(item) {
				html += '<tr>';
				html += '<td class="rd-pr-history-col-datetime">' + formatTimestamp(item.post_publish_datetime) + '</td>';
				html += '<td class="rd-pr-history-col-id">' + escapeHtml(item.post_id) + '</td>';
				html += '<td class="rd-pr-history-col-title"><a href="' + escapeHtml(item.post_url) + '" target="_blank">' + escapeHtml(item.post_title) + '</a></td>';
				html += '</tr>';
			});

			$tbody.html(html);
		}

		/**
		 * Show loading state
		 */
		function showLoading() {
			$tbody.html('<tr class="rd-pr-history-loading"><td colspan="3">Loading history...</td></tr>');
		}

		/**
		 * Show error state
		 */
		function showError(message) {
			$tbody.html('<tr class="rd-pr-history-error"><td colspan="3">' + escapeHtml(message) + '</td></tr>');
		}

		/**
		 * Show empty state
		 */
		function showEmpty() {
			$tbody.html('<tr class="rd-pr-history-empty"><td colspan="3">No republish history found.</td></tr>');
		}

		// Event handler for limit filter - re-fetch with new limit
		$limitFilter.on('change', fetchHistory);

		// Fetch history on page load
		fetchHistory();

	});

})( jQuery );
