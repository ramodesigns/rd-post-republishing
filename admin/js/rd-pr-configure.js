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

		// Store all history entries
		var allHistory = [];

		/**
		 * Fetch republish logs from the API
		 */
		function fetchHistory() {
			showLoading();

			$.ajax({
				url: rdPrHistory.restUrl + '/retrieve?type=republish',
				method: 'GET',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', rdPrHistory.nonce);
				},
				success: function(response) {
					if (response.success && response.data) {
						processLogs(response.data);
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
		 * Process logs to extract unique posts with latest republish time
		 */
		function processLogs(logs) {
			var postMap = {};

			logs.forEach(function(log) {
				var postId = extractPostId(log.entry);
				if (postId) {
					var timestamp = parseTimestamp(log.timestamp);

					// Keep only the latest entry for each post
					if (!postMap[postId] || timestamp > postMap[postId].timestamp) {
						postMap[postId] = {
							postId: postId,
							timestamp: timestamp,
							rawTimestamp: log.timestamp
						};
					}
				}
			});

			// Convert map to array
			allHistory = Object.values(postMap);

			// Fetch post titles and then render
			fetchPostTitles(allHistory);
		}

		/**
		 * Extract post ID from log entry text
		 * Format: "Successfully Republished Post 123"
		 */
		function extractPostId(entry) {
			if (!entry) {
				return null;
			}

			var match = entry.match(/Post\s+(\d+)/i);
			return match ? parseInt(match[1], 10) : null;
		}

		/**
		 * Fetch post titles from WordPress REST API
		 */
		function fetchPostTitles(history) {
			if (history.length === 0) {
				applyFiltersAndRender();
				return;
			}

			var postIds = history.map(function(item) {
				return item.postId;
			});

			// Fetch posts in batches to avoid URL length issues
			var batchSize = 100;
			var batches = [];
			for (var i = 0; i < postIds.length; i += batchSize) {
				batches.push(postIds.slice(i, i + batchSize));
			}

			var completedBatches = 0;
			var postTitles = {};

			batches.forEach(function(batch) {
				$.ajax({
					url: '/wp-json/wp/v2/posts?include=' + batch.join(',') + '&per_page=' + batch.length + '&_fields=id,title',
					method: 'GET',
					beforeSend: function(xhr) {
						xhr.setRequestHeader('X-WP-Nonce', rdPrHistory.nonce);
					},
					success: function(posts) {
						posts.forEach(function(post) {
							postTitles[post.id] = post.title.rendered;
						});
					},
					complete: function() {
						completedBatches++;
						if (completedBatches === batches.length) {
							// All batches complete, update history with titles
							allHistory.forEach(function(item) {
								item.postTitle = postTitles[item.postId] || '(Post not found)';
							});
							applyFiltersAndRender();
						}
					}
				});
			});
		}

		/**
		 * Apply filters and render the table
		 */
		function applyFiltersAndRender() {
			var limit = parseInt($limitFilter.val(), 10);

			// Sort by timestamp descending (most recent first)
			var sortedHistory = allHistory.slice().sort(function(a, b) {
				return b.timestamp - a.timestamp;
			});

			// Limit rows
			var limitedHistory = sortedHistory.slice(0, limit);

			renderHistory(limitedHistory);
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
		 * Format timestamp for display (DD/MM/YY HH:MM:SS)
		 */
		function formatTimestamp(timestamp) {
			if (!timestamp || isNaN(timestamp.getTime())) {
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

			var html = '';

			history.forEach(function(item) {
				html += '<tr>';
				html += '<td class="rd-pr-history-col-id">' + escapeHtml(item.postId) + '</td>';
				html += '<td class="rd-pr-history-col-title">' + escapeHtml(item.postTitle) + '</td>';
				html += '<td class="rd-pr-history-col-datetime">' + formatTimestamp(item.timestamp) + '</td>';
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

		// Event handler for limit filter
		$limitFilter.on('change', applyFiltersAndRender);

		// Fetch history on page load
		fetchHistory();

	});

})( jQuery );
