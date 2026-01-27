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
		var $typeFilter = $('#rd-pr-logs-type-filter');
		var $limitFilter = $('#rd-pr-logs-limit');

		// Store all logs for filtering
		var allLogs = [];

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
						allLogs = response.data;
						populateTypeFilter(allLogs);
						applyFiltersAndRender();
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
		 * Populate type filter dropdown with unique types
		 */
		function populateTypeFilter(logs) {
			var types = [];

			logs.forEach(function(log) {
				if (log.type && types.indexOf(log.type) === -1) {
					types.push(log.type);
				}
			});

			// Sort types alphabetically
			types.sort();

			// Clear existing options except "All"
			$typeFilter.find('option:not(:first)').remove();

			// Add type options
			types.forEach(function(type) {
				$typeFilter.append('<option value="' + escapeHtml(type) + '">' + escapeHtml(type) + '</option>');
			});
		}

		/**
		 * Apply filters and render the table
		 */
		function applyFiltersAndRender() {
			var selectedType = $typeFilter.val();
			var limit = parseInt($limitFilter.val(), 10);

			// Filter by type
			var filteredLogs = allLogs;
			if (selectedType !== '') {
				filteredLogs = allLogs.filter(function(log) {
					return log.type === selectedType;
				});
			}

			// Sort by timestamp descending (most recent first)
			var sortedLogs = sortLogsByTimestamp(filteredLogs);

			// Limit rows
			var limitedLogs = sortedLogs.slice(0, limit);

			renderLogs(limitedLogs);
		}

		/**
		 * Sort logs by date descending (newest first), then by time ascending within each date
		 */
		function sortLogsByTimestamp(logs) {
			return logs.slice().sort(function(a, b) {
				var dateA = parseTimestamp(a.timestamp);
				var dateB = parseTimestamp(b.timestamp);

				// Get date-only strings for comparison (YYYY-MM-DD)
				var dateOnlyA = getDateOnly(dateA);
				var dateOnlyB = getDateOnly(dateB);

				// First sort by date descending (newest date first)
				if (dateOnlyA !== dateOnlyB) {
					return dateOnlyB.localeCompare(dateOnlyA);
				}

				// Within the same date, sort by time ascending (earliest time first)
				return dateA - dateB;
			});
		}

		/**
		 * Get date-only string from Date object (YYYY-MM-DD format for comparison)
		 */
		function getDateOnly(date) {
			if (isNaN(date.getTime())) {
				return '';
			}
			var year = date.getFullYear();
			var month = String(date.getMonth() + 1).padStart(2, '0');
			var day = String(date.getDate()).padStart(2, '0');
			return year + '-' + month + '-' + day;
		}

		/**
		 * Parse timestamp string to Date object
		 * Format: "2026-01-26 14:05:02.189"
		 */
		function parseTimestamp(timestamp) {
			if (!timestamp) {
				return new Date(0);
			}

			// Replace space with T for ISO format compatibility
			var isoString = timestamp.replace(' ', 'T');
			return new Date(isoString);
		}

		/**
		 * Format date for display (DD/MM/YY)
		 */
		function formatDate(timestamp) {
			var date = parseTimestamp(timestamp);

			if (isNaN(date.getTime())) {
				return timestamp || 'Invalid date';
			}

			var day = String(date.getDate()).padStart(2, '0');
			var month = String(date.getMonth() + 1).padStart(2, '0');
			var year = String(date.getFullYear()).slice(-2);

			return day + '/' + month + '/' + year;
		}

		/**
		 * Format time for display (HH:MM:SS)
		 */
		function formatTime(timestamp) {
			var date = parseTimestamp(timestamp);

			if (isNaN(date.getTime())) {
				return '';
			}

			var hours = String(date.getHours()).padStart(2, '0');
			var minutes = String(date.getMinutes()).padStart(2, '0');
			var seconds = String(date.getSeconds()).padStart(2, '0');

			return hours + ':' + minutes + ':' + seconds;
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
		 * Render logs in the table
		 */
		function renderLogs(logs) {
			if (logs.length === 0) {
				showEmpty();
				return;
			}

			var html = '';
			var previousDate = '';

			logs.forEach(function(log) {
				var currentDate = formatDate(log.timestamp);
				var showDate = (currentDate !== previousDate);

				html += '<tr>';
				html += '<td class="rd-pr-logs-col-date">' + (showDate ? currentDate : '') + '</td>';
				html += '<td class="rd-pr-logs-col-time">' + formatTime(log.timestamp) + '</td>';
				html += '<td class="rd-pr-logs-col-type"><span class="rd-pr-log-type rd-pr-log-type-' + escapeHtml(log.type) + '">' + escapeHtml(log.type) + '</span></td>';
				html += '<td class="rd-pr-logs-col-entry">' + escapeHtml(log.entry) + '</td>';
				html += '</tr>';

				previousDate = currentDate;
			});

			$tbody.html(html);
		}

		/**
		 * Show loading state
		 */
		function showLoading() {
			$tbody.html('<tr class="rd-pr-logs-loading"><td colspan="4">Loading logs...</td></tr>');
		}

		/**
		 * Show error state
		 */
		function showError(message) {
			$tbody.html('<tr class="rd-pr-logs-error"><td colspan="4">' + escapeHtml(message) + '</td></tr>');
		}

		/**
		 * Show empty state
		 */
		function showEmpty() {
			$tbody.html('<tr class="rd-pr-logs-empty"><td colspan="4">No logs found.</td></tr>');
		}

		// Event handlers for filters
		$typeFilter.on('change', applyFiltersAndRender);
		$limitFilter.on('change', applyFiltersAndRender);

		// Fetch logs on page load
		fetchLogs();

	});

})( jQuery );
