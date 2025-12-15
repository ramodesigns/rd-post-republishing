(function($) {
	'use strict';

	/**
	 * WPR Republisher Admin JavaScript
	 *
	 * Handles all admin UI interactions including AJAX calls for dry-run,
	 * manual trigger, exports, and real-time UI updates.
	 *
	 * @since 1.0.0
	 */
	const WPRAdmin = {
		/**
		 * Initialize the admin functionality.
		 */
		init: function() {
			this.bindEvents();
			this.initCategorySelect();
		},

		/**
		 * Bind all event handlers.
		 */
		bindEvents: function() {
			// Quick action buttons
			$(document).on('click', '.wpr-dry-run-btn', this.handleDryRun.bind(this));
			$(document).on('click', '.wpr-manual-trigger-btn', this.handleManualTrigger.bind(this));
			$(document).on('click', '.wpr-export-history-btn', this.handleExportHistory.bind(this));
			$(document).on('click', '.wpr-export-audit-btn', this.handleExportAudit.bind(this));
			$(document).on('click', '.wpr-refresh-preview-btn', this.handleRefreshPreview.bind(this));

			// Category filter type change
			$(document).on('change', 'select[name="wpr_settings[category_filter_type]"]', this.toggleCategorySelect.bind(this));

			// Settings form enhancements
			$(document).on('change', 'select[name="wpr_settings[daily_quota_type]"]', this.updateQuotaLabel.bind(this));

			// Tab navigation enhancement
			$(document).on('click', '.nav-tab', this.handleTabClick.bind(this));

			// Modal close handlers
			$(document).on('click', '.wpr-modal-close, .wpr-modal-backdrop', this.closeModal.bind(this));
			$(document).on('keydown', this.handleEscapeKey.bind(this));
		},

		/**
		 * Initialize category multi-select.
		 */
		initCategorySelect: function() {
			this.toggleCategorySelect();
		},

		/**
		 * Toggle category select visibility based on filter type.
		 */
		toggleCategorySelect: function() {
			const filterType = $('select[name="wpr_settings[category_filter_type]"]').val();
			const categoryRow = $('.wpr-category-select-row');

			if (filterType === 'none') {
				categoryRow.hide();
			} else {
				categoryRow.show();
			}
		},

		/**
		 * Update quota label based on type.
		 */
		updateQuotaLabel: function() {
			const quotaType = $('select[name="wpr_settings[daily_quota_type]"]').val();
			const label = quotaType === 'percentage' ? '% of eligible posts' : 'posts per day';
			$('.wpr-quota-label').text(label);
		},

		/**
		 * Handle tab navigation.
		 */
		handleTabClick: function(e) {
			// Allow default behavior for actual links
			// This is just for visual enhancement
		},

		/**
		 * Handle dry-run button click.
		 */
		handleDryRun: function(e) {
			e.preventDefault();

			if (!confirm(wprRepublisher.i18n.confirmDryRun)) {
				return;
			}

			const $button = $(e.currentTarget);
			this.setButtonLoading($button, true);

			$.ajax({
				url: wprRepublisher.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpr_dry_run',
					nonce: wprRepublisher.nonce
				},
				success: (response) => {
					if (response.success) {
						this.showDryRunResults(response.data);
					} else {
						this.showNotice('error', response.data.message || wprRepublisher.i18n.error);
					}
				},
				error: () => {
					this.showNotice('error', wprRepublisher.i18n.error);
				},
				complete: () => {
					this.setButtonLoading($button, false);
				}
			});
		},

		/**
		 * Handle manual trigger button click.
		 */
		handleManualTrigger: function(e) {
			e.preventDefault();

			if (!confirm(wprRepublisher.i18n.confirmManual)) {
				return;
			}

			const $button = $(e.currentTarget);
			this.setButtonLoading($button, true);

			$.ajax({
				url: wprRepublisher.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpr_manual_trigger',
					nonce: wprRepublisher.nonce
				},
				success: (response) => {
					if (response.success) {
						this.showManualTriggerResults(response.data);
					} else {
						this.showNotice('error', response.data.message || wprRepublisher.i18n.error);
					}
				},
				error: () => {
					this.showNotice('error', wprRepublisher.i18n.error);
				},
				complete: () => {
					this.setButtonLoading($button, false);
				}
			});
		},

		/**
		 * Handle export history button click.
		 */
		handleExportHistory: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			this.setButtonLoading($button, true);

			$.ajax({
				url: wprRepublisher.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpr_export_history',
					nonce: wprRepublisher.nonce
				},
				success: (response) => {
					if (response.success) {
						this.downloadCSV(response.data.csv, response.data.filename);
						this.showNotice('success', 'History exported successfully.');
					} else {
						this.showNotice('error', response.data.message || wprRepublisher.i18n.error);
					}
				},
				error: () => {
					this.showNotice('error', wprRepublisher.i18n.error);
				},
				complete: () => {
					this.setButtonLoading($button, false);
				}
			});
		},

		/**
		 * Handle export audit button click.
		 */
		handleExportAudit: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			this.setButtonLoading($button, true);

			$.ajax({
				url: wprRepublisher.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpr_export_audit',
					nonce: wprRepublisher.nonce
				},
				success: (response) => {
					if (response.success) {
						this.downloadCSV(response.data.csv, response.data.filename);
						this.showNotice('success', 'Audit log exported successfully.');
					} else {
						this.showNotice('error', response.data.message || wprRepublisher.i18n.error);
					}
				},
				error: () => {
					this.showNotice('error', wprRepublisher.i18n.error);
				},
				complete: () => {
					this.setButtonLoading($button, false);
				}
			});
		},

		/**
		 * Handle refresh preview button click.
		 */
		handleRefreshPreview: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const $container = $('.wpr-preview-list');

			this.setButtonLoading($button, true);
			$container.addClass('wpr-loading');

			$.ajax({
				url: wprRepublisher.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpr_get_preview',
					nonce: wprRepublisher.nonce
				},
				success: (response) => {
					if (response.success) {
						this.updatePreviewList(response.data);
					} else {
						this.showNotice('error', response.data.message || wprRepublisher.i18n.error);
					}
				},
				error: () => {
					this.showNotice('error', wprRepublisher.i18n.error);
				},
				complete: () => {
					this.setButtonLoading($button, false);
					$container.removeClass('wpr-loading');
				}
			});
		},

		/**
		 * Show dry-run results in a modal.
		 */
		showDryRunResults: function(data) {
			let html = '<div class="wpr-modal">';
			html += '<div class="wpr-modal-backdrop"></div>';
			html += '<div class="wpr-modal-content">';
			html += '<button type="button" class="wpr-modal-close">&times;</button>';
			html += '<h2>Dry-Run Results</h2>';
			html += '<p class="wpr-modal-message">' + data.message + '</p>';

			if (data.posts && data.posts.length > 0) {
				html += '<table class="widefat striped">';
				html += '<thead><tr><th>ID</th><th>Title</th><th>Current Date</th><th>Actions</th></tr></thead>';
				html += '<tbody>';

				data.posts.forEach(function(post) {
					html += '<tr>';
					html += '<td>' + post.id + '</td>';
					html += '<td>' + post.title + '</td>';
					html += '<td>' + post.current_date + '</td>';
					html += '<td>';
					html += '<a href="' + post.edit_link + '" target="_blank" class="button button-small">Edit</a> ';
					html += '<a href="' + post.view_link + '" target="_blank" class="button button-small">View</a>';
					html += '</td>';
					html += '</tr>';
				});

				html += '</tbody></table>';
			} else {
				html += '<p>No posts would be republished with current settings.</p>';
			}

			html += '</div></div>';

			$('body').append(html);
		},

		/**
		 * Show manual trigger results.
		 */
		showManualTriggerResults: function(data) {
			let html = '<div class="wpr-modal">';
			html += '<div class="wpr-modal-backdrop"></div>';
			html += '<div class="wpr-modal-content">';
			html += '<button type="button" class="wpr-modal-close">&times;</button>';
			html += '<h2>Republishing Complete</h2>';
			html += '<p class="wpr-modal-message">' + data.message + '</p>';

			html += '<div class="wpr-results-summary">';
			html += '<div class="wpr-result-item success"><span class="count">' + data.republished.length + '</span><span class="label">Republished</span></div>';
			html += '<div class="wpr-result-item warning"><span class="count">' + data.skipped.length + '</span><span class="label">Skipped</span></div>';
			html += '<div class="wpr-result-item error"><span class="count">' + data.failed.length + '</span><span class="label">Failed</span></div>';
			html += '</div>';

			if (data.republished && data.republished.length > 0) {
				html += '<h3>Republished Posts</h3>';
				html += '<ul class="wpr-post-list">';
				data.republished.forEach(function(postId) {
					html += '<li>Post ID: ' + postId + '</li>';
				});
				html += '</ul>';
			}

			if (data.failed && data.failed.length > 0) {
				html += '<h3>Failed Posts</h3>';
				html += '<ul class="wpr-post-list error">';
				data.failed.forEach(function(postId) {
					html += '<li>Post ID: ' + postId + '</li>';
				});
				html += '</ul>';
			}

			html += '</div></div>';

			$('body').append(html);
		},

		/**
		 * Update preview list with new data.
		 */
		updatePreviewList: function(data) {
			const $container = $('.wpr-preview-list');

			if (!$container.length) {
				return;
			}

			let html = '';

			if (data.posts && data.posts.length > 0) {
				html += '<p class="wpr-preview-summary">Showing ' + data.posts.length + ' of ' + data.total_count + ' eligible posts (daily quota: ' + data.quota + ')</p>';
				html += '<table class="widefat striped">';
				html += '<thead><tr><th>ID</th><th>Title</th><th>Current Date</th><th>Categories</th></tr></thead>';
				html += '<tbody>';

				data.posts.forEach(function(post) {
					html += '<tr>';
					html += '<td>' + post.id + '</td>';
					html += '<td>' + post.title + '</td>';
					html += '<td>' + post.current_date + '</td>';
					html += '<td>' + (post.categories ? post.categories.join(', ') : '-') + '</td>';
					html += '</tr>';
				});

				html += '</tbody></table>';
			} else {
				html = '<p>No eligible posts found with current settings.</p>';
			}

			$container.html(html);
		},

		/**
		 * Close modal dialog.
		 */
		closeModal: function(e) {
			if (e) {
				e.preventDefault();
			}
			$('.wpr-modal').remove();
		},

		/**
		 * Handle escape key to close modal.
		 */
		handleEscapeKey: function(e) {
			if (e.key === 'Escape' && $('.wpr-modal').length) {
				this.closeModal();
			}
		},

		/**
		 * Download CSV file.
		 */
		downloadCSV: function(csvContent, filename) {
			const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
			const link = document.createElement('a');

			if (navigator.msSaveBlob) {
				// IE 10+
				navigator.msSaveBlob(blob, filename);
			} else {
				link.href = URL.createObjectURL(blob);
				link.setAttribute('download', filename);
				link.style.visibility = 'hidden';
				document.body.appendChild(link);
				link.click();
				document.body.removeChild(link);
			}
		},

		/**
		 * Set button loading state.
		 */
		setButtonLoading: function($button, isLoading) {
			if (isLoading) {
				$button.addClass('wpr-loading').prop('disabled', true);
				$button.data('original-text', $button.text());
				$button.html('<span class="spinner is-active"></span> Loading...');
			} else {
				$button.removeClass('wpr-loading').prop('disabled', false);
				$button.text($button.data('original-text') || 'Submit');
			}
		},

		/**
		 * Show admin notice.
		 */
		showNotice: function(type, message) {
			const noticeClass = type === 'error' ? 'notice-error' : 'notice-success';
			const $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');

			// Remove existing notices
			$('.wpr-admin-notice').remove();

			// Add notice class for easy removal
			$notice.addClass('wpr-admin-notice');

			// Insert after the page title
			$('.wrap h1').first().after($notice);

			// Trigger WordPress dismiss functionality
			$(document).trigger('wp-updates-notice-added');

			// Auto-dismiss after 5 seconds
			setTimeout(function() {
				$notice.fadeOut(function() {
					$(this).remove();
				});
			}, 5000);
		}
	};

	// Initialize when document is ready
	$(document).ready(function() {
		WPRAdmin.init();
	});

})(jQuery);
