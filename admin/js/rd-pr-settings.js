/**
 * Settings page JavaScript.
 *
 * @package    Rd_Post_Republishing
 * @subpackage Rd_Post_Republishing/admin/js
 */

(function( $ ) {
	'use strict';

	$(document).ready(function() {

		var $activeToggle = $('#rd-pr-active');
		var $wpCronToggle = $('#rd-pr-wp-cron');
		var $slider = $('#rd-pr-posts-per-day');
		var $sliderValue = $('#rd-pr-posts-per-day-value');
		var $startTime = $('#rd-pr-start-time');
		var $endTime = $('#rd-pr-end-time');
		var $debugToggle = $('#rd-pr-debug');
		var $debugTimestampValue = $('#rd-pr-debug-timestamp-value');
		var $debugTimestampContainer = $('#rd-pr-debug-timestamp-container');
		var $cronTokenInput = $('#rd-pr-cron-token');
		var $generateTokenButton = $('#rd-pr-generate-token');
		var $form = $('#rd-pr-settings-form');
		var $submitGroup = $('.rd-pr-submit-group');

		// Field groups for enabling/disabling
		var $wpCronGroup = $wpCronToggle.closest('.rd-pr-field-group');
		var $postsPerDayGroup = $slider.closest('.rd-pr-field-group');
		var $startTimeGroup = $startTime.closest('.rd-pr-field-group');
		var $endTimeGroup = $endTime.closest('.rd-pr-field-group');

		/**
		 * Tab Switching Logic
		 */
		$('.rd-pr-nav-tab-wrapper a').on('click', function(e) {
			e.preventDefault();
			var tabId = $(this).data('tab');

			// Update tabs
			$('.rd-pr-nav-tab-wrapper a').removeClass('nav-tab-active');
			$(this).addClass('nav-tab-active');

			// Update content
			$('.rd-pr-tab-content').removeClass('rd-pr-tab-active');
			$('#rd-pr-tab-' + tabId).addClass('rd-pr-tab-active');

			// Show/Hide Posting Calendar based on tab
			if (tabId === 'preferences') {
				$('.rd-pr-calendar-panel').show();
			} else {
				$('.rd-pr-calendar-panel').hide();
			}

			// Update URL hash without jumping
			if (history.pushState) {
				history.pushState(null, null, '#' + tabId);
			} else {
				location.hash = '#' + tabId;
			}
		});

		// Handle direct link to tab via hash
		var hash = window.location.hash;
		if (hash) {
			var tabName = hash.substring(1);
			var $targetTab = $('.rd-pr-nav-tab-wrapper a[data-tab="' + tabName + '"]');
			if ($targetTab.length) {
				$targetTab.trigger('click');
			}
		}

		/**
		 * Fetch preferences from the API
		 */
		function fetchPreferences() {
			$.ajax({
				url: rdPrSettings.restUrl + '/retrieve',
				method: 'GET',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', rdPrSettings.nonce);
				},
				success: function(response) {
					if (response.success && response.data) {
						populateFormFields(response.data);
					} else {
						// Apply defaults if no data returned
						populateFormFields([]);
					}
				},
				error: function(xhr, status, error) {
					console.error('Failed to fetch preferences:', error);
					// Apply defaults on error
					populateFormFields([]);
				}
			});
		}

		/**
		 * Default preference values
		 */
		var defaults = {
			status: 'active',
			wp_cron: 'active',
			posts_per_day: '1',
			publish_start_time: '9',
			publish_end_time: '17',
			debug_timestamp: '',
			cron_secret_token: ''
		};

		/**
		 * Populate form fields with preferences data
		 */
		function populateFormFields(preferences) {
			// Create a lookup object for easier access
			var prefLookup = {};
			preferences.forEach(function(pref) {
				prefLookup[pref.key] = pref.value;
			});

			// Set Active toggle (default: active)
			var status = prefLookup.status !== undefined ? prefLookup.status : defaults.status;
			$activeToggle.prop('checked', status === 'active');

			// Set WP Cron toggle (default: active)
			var wpCron = prefLookup.wp_cron !== undefined ? prefLookup.wp_cron : defaults.wp_cron;
			$wpCronToggle.prop('checked', wpCron === 'active');

			// Set Posts Per Day (default: 1)
			var postsPerDay = prefLookup.posts_per_day !== undefined ? prefLookup.posts_per_day : defaults.posts_per_day;
			$slider.val(postsPerDay);
			$sliderValue.text(postsPerDay);

			// Set Publish Start Time (default: 9)
			var startTime = prefLookup.publish_start_time !== undefined ? prefLookup.publish_start_time : defaults.publish_start_time;
			$startTime.val(startTime);

			// Set Publish End Time (default: 17)
			var endTime = prefLookup.publish_end_time !== undefined ? prefLookup.publish_end_time : defaults.publish_end_time;
			$endTime.val(endTime);

			// Set Debug toggle based on debug_timestamp
			var debugTimestamp = prefLookup.debug_timestamp !== undefined ? prefLookup.debug_timestamp : defaults.debug_timestamp;
			var isDebugOn = false;
			var debugDisplay = '';

			if (debugTimestamp && !isNaN(debugTimestamp)) {
				var now = Math.floor(Date.now() / 1000);
				var timestamp = parseInt(debugTimestamp, 10);
				if (timestamp > now) {
					isDebugOn = true;
					// Format timestamp for display
					var date = new Date(timestamp * 1000);
					debugDisplay = date.toLocaleString();
				}
			}

			$debugToggle.prop('checked', isDebugOn);
			if (isDebugOn) {
				$debugTimestampValue.text(debugDisplay);
				$debugTimestampContainer.show();
			} else {
				$debugTimestampContainer.hide();
			}

			// Set Cron Secret Token
			var cronToken = prefLookup.cron_secret_token !== undefined ? prefLookup.cron_secret_token : defaults.cron_secret_token;
			$cronTokenInput.val(cronToken);

			// Update field states after populating
			toggleFieldsState();

			// Load posting calendar after preferences are set
			loadPostingCalendar();
		}

		/**
		 * Toggle fields enabled/disabled state based on Active checkbox
		 */
		function toggleFieldsState() {
			var isActive = $activeToggle.is(':checked');

			$wpCronToggle.prop('disabled', !isActive);
			$slider.prop('disabled', !isActive);
			$startTime.prop('disabled', !isActive);
			$endTime.prop('disabled', !isActive);

			// Toggle visual disabled state on field groups
			$wpCronGroup.toggleClass('rd-pr-field-disabled', !isActive);
			$postsPerDayGroup.toggleClass('rd-pr-field-disabled', !isActive);
			$startTimeGroup.toggleClass('rd-pr-field-disabled', !isActive);
			$endTimeGroup.toggleClass('rd-pr-field-disabled', !isActive);

			// Revalidate when toggling
			if (isActive) {
				validateTimeRange();
			} else {
				hideValidationError();
			}
		}

		/**
		 * Validate that end time is later than start time
		 */
		function validateTimeRange() {
			var startValue = parseInt($startTime.val(), 10);
			var endValue = parseInt($endTime.val(), 10);

			if (startValue >= endValue) {
				showValidationError('Publish End Time must be later than Publish Start Time.');
				return false;
			} else {
				hideValidationError();
				return true;
			}
		}

		/**
		 * Show validation error message
		 */
		function showValidationError(message) {
			var $error = $('#rd-pr-validation-error');

			if ($error.length === 0) {
				$error = $('<div id="rd-pr-validation-error" class="rd-pr-validation-error"></div>');
				$submitGroup.before($error);
			}

			$error.text(message).show();
		}

		/**
		 * Hide validation error message
		 */
		function hideValidationError() {
			$('#rd-pr-validation-error').hide();
		}

		// Slider value display
		if ($slider.length && $sliderValue.length) {
			$slider.on('input', function() {
				$sliderValue.text(this.value);
			});
			$sliderValue.text($slider.val());
		}

		// Active toggle change handler
		$activeToggle.on('change', toggleFieldsState);

		// Time dropdowns change handler
		$startTime.on('change', validateTimeRange);
		$endTime.on('change', validateTimeRange);

		// Generate Token button handler
		$generateTokenButton.on('click', function() {
			var $btn = $(this);
			var originalText = $btn.text();
			$btn.prop('disabled', true).text('Generating...');

			$.ajax({
				url: rdPrSettings.restUrl + '/generate_token',
				method: 'POST',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', rdPrSettings.nonce);
				},
				success: function(response) {
					if (response.success && response.token) {
						$cronTokenInput.val(response.token);
						showSuccessMessage('New token generated. Don\'t forget to Save settings.');
					} else {
						showValidationError('Failed to generate token.');
					}
				},
				error: function() {
					showValidationError('Failed to generate token.');
				},
				complete: function() {
					$btn.prop('disabled', false).text(originalText);
				}
			});
		});

		// Initialize state on page load
		toggleFieldsState();

		// Fetch preferences from API on page load
		fetchPreferences();

		/**
		 * Save preferences to the API
		 */
		function savePreferences() {
			var $saveButton = $('#rd-pr-save-settings');
			var originalText = $saveButton.text();

			// Disable button and show saving state
			$saveButton.prop('disabled', true).text('Saving...');

			// Build preferences payload
			var preferences = [
				{
					key: 'status',
					value: $activeToggle.is(':checked') ? 'active' : 'inactive'
				},
				{
					key: 'wp_cron',
					value: $wpCronToggle.is(':checked') ? 'active' : 'inactive'
				},
				{
					key: 'posts_per_day',
					value: $slider.val()
				},
				{
					key: 'publish_start_time',
					value: $startTime.val()
				},
				{
					key: 'publish_end_time',
					value: $endTime.val()
				},
				{
					key: 'debug_timestamp',
					value: $debugToggle.is(':checked') ? Math.floor(Date.now() / 1000 + 12 * 3600).toString() : ''
				},
				{
					key: 'cron_secret_token',
					value: $cronTokenInput.val()
				}
			];

			$.ajax({
				url: rdPrSettings.restUrl + '/update',
				method: 'POST',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', rdPrSettings.nonce);
				},
				contentType: 'application/json',
				data: JSON.stringify({ preferences: preferences }),
				success: function(response) {
			if (response.success) {
				showSuccessMessage('Settings saved successfully.');
				// Refresh the posting calendar with new settings
				loadPostingCalendar();
				// Re-fetch preferences to update debug timestamp display
				fetchPreferences();
			} else {
				showValidationError(response.message || 'Failed to save settings.');
			}
		},
				error: function(xhr, status, error) {
					var message = 'Failed to save settings.';
					if (xhr.responseJSON && xhr.responseJSON.message) {
						message = xhr.responseJSON.message;
					}
					showValidationError(message);
				},
				complete: function() {
					// Re-enable button and restore text
					$saveButton.prop('disabled', false).text(originalText);
				}
			});
		}

		/**
		 * Show success message
		 */
		function showSuccessMessage(message) {
			hideValidationError();
			var $success = $('#rd-pr-success-message');

			if ($success.length === 0) {
				$success = $('<div id="rd-pr-success-message" class="rd-pr-success-message"></div>');
				$submitGroup.before($success);
			}

			$success.text(message).show();

			// Auto-hide after 3 seconds
			setTimeout(function() {
				$success.fadeOut();
			}, 3000);
		}

		// Form submission
		$form.on('submit', function(e) {
			e.preventDefault();

			// Hide any existing success message
			$('#rd-pr-success-message').hide();

			// Only validate time range if active
			if ($activeToggle.is(':checked')) {
				if (!validateTimeRange()) {
					return false;
				}
			}

			// Save preferences to API
			savePreferences();
		});

		// =====================================================
		// Posting Calendar functionality
		// =====================================================

		var $calendarGrid = $('#rd-pr-calendar-grid');

		/**
		 * Format a Date object to dd-mm-yyyy string
		 */
		function formatDateToDDMMYYYY(date) {
			var day = String(date.getDate()).padStart(2, '0');
			var month = String(date.getMonth() + 1).padStart(2, '0');
			var year = date.getFullYear();
			return day + '-' + month + '-' + year;
		}

		/**
		 * Format a Date object for display (e.g., "23/01/26")
		 */
		function formatDateForDisplay(date) {
			var day = String(date.getDate()).padStart(2, '0');
			var month = String(date.getMonth() + 1).padStart(2, '0');
			var year = String(date.getFullYear()).slice(-2);
			return day + '/' + month + '/' + year;
		}

		/**
		 * Get an array of 7 dates starting from today
		 */
		function getNext7Days() {
			var dates = [];
			var today = new Date();

			for (var i = 0; i < 7; i++) {
				var date = new Date(today);
				date.setDate(today.getDate() + i);
				dates.push(date);
			}

			return dates;
		}

		/**
		 * Fetch posttimes for a single date
		 */
		function fetchPosttimesForDate(date) {
			return $.ajax({
				url: rdPrSettings.calculationUrl + '/posttimes',
				method: 'POST',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', rdPrSettings.nonce);
				},
				contentType: 'application/json',
				data: JSON.stringify({ date: formatDateToDDMMYYYY(date) })
			});
		}

		/**
		 * Render the calendar with the fetched times
		 */
		function renderCalendar(datesWithTimes) {
			var html = '';

			datesWithTimes.forEach(function(item, index) {
				var isToday = index === 0;
				var dayLabel = isToday ? 'Today' : formatDateForDisplay(item.date);
				var allTimes = [];

				// Combine previous_times and future_times
				if (item.times && item.times.previous_times) {
					allTimes = allTimes.concat(item.times.previous_times);
				}
				if (item.times && item.times.future_times) {
					allTimes = allTimes.concat(item.times.future_times);
				}

				html += '<div class="rd-pr-calendar-day' + (isToday ? ' rd-pr-calendar-today' : '') + '">';
				html += '<div class="rd-pr-calendar-day-header">' + dayLabel + '</div>';
				html += '<div class="rd-pr-calendar-times">';

				if (allTimes.length > 0) {
					allTimes.forEach(function(time) {
						html += '<span class="rd-pr-calendar-time">' + time + '</span>';
					});
				} else {
					html += '<span class="rd-pr-calendar-no-times">No times scheduled</span>';
				}

				html += '</div>';
				html += '</div>';
			});

			$calendarGrid.html(html);
		}

		/**
		 * Show calendar loading state
		 */
		function showCalendarLoading() {
			$calendarGrid.html('<div class="rd-pr-calendar-loading">Loading posting schedule...</div>');
		}

		/**
		 * Show calendar error state
		 */
		function showCalendarError() {
			$calendarGrid.html('<div class="rd-pr-calendar-error">Failed to load posting schedule.</div>');
		}

		/**
		 * Render calendar with inactive state (no times)
		 */
		function renderInactiveCalendar() {
			var dates = getNext7Days();
			var html = '';

			dates.forEach(function(date, index) {
				var isToday = index === 0;
				var dayLabel = isToday ? 'Today' : formatDateForDisplay(date);

				html += '<div class="rd-pr-calendar-day rd-pr-calendar-inactive' + (isToday ? ' rd-pr-calendar-today' : '') + '">';
				html += '<div class="rd-pr-calendar-day-header">' + dayLabel + '</div>';
				html += '<div class="rd-pr-calendar-times">';
				html += '<span class="rd-pr-calendar-no-times">Republishing disabled</span>';
				html += '</div>';
				html += '</div>';
			});

			$calendarGrid.html(html);
		}

		/**
		 * Fetch and display posttimes for all 7 days
		 */
		function loadPostingCalendar() {
			// If Active toggle is disabled, show inactive calendar
			if (!$activeToggle.is(':checked')) {
				renderInactiveCalendar();
				return;
			}

			showCalendarLoading();

			var dates = getNext7Days();
			var requests = dates.map(function(date) {
				return fetchPosttimesForDate(date);
			});

			$.when.apply($, requests)
				.done(function() {
					var results = requests.length === 1 ? [arguments] : arguments;
					var datesWithTimes = [];

					dates.forEach(function(date, index) {
						var response = results[index][0];
						datesWithTimes.push({
							date: date,
							times: response.success ? {
								previous_times: response.previous_times || [],
								future_times: response.future_times || []
							} : null
						});
					});

					renderCalendar(datesWithTimes);
				})
				.fail(function() {
					showCalendarError();
				});
		}

	});

})( jQuery );
