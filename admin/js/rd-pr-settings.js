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
		var $slider = $('#rd-pr-posts-per-day');
		var $sliderValue = $('#rd-pr-posts-per-day-value');
		var $startTime = $('#rd-pr-start-time');
		var $endTime = $('#rd-pr-end-time');
		var $form = $('#rd-pr-settings-form');
		var $submitGroup = $('.rd-pr-submit-group');

		// Field groups for enabling/disabling
		var $postsPerDayGroup = $slider.closest('.rd-pr-field-group');
		var $startTimeGroup = $startTime.closest('.rd-pr-field-group');
		var $endTimeGroup = $endTime.closest('.rd-pr-field-group');

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
					}
				},
				error: function(xhr, status, error) {
					console.error('Failed to fetch preferences:', error);
				}
			});
		}

		/**
		 * Populate form fields with preferences data
		 */
		function populateFormFields(preferences) {
			// Create a lookup object for easier access
			var prefLookup = {};
			preferences.forEach(function(pref) {
				prefLookup[pref.key] = pref.value;
			});

			// Set Active toggle
			if (prefLookup.status !== undefined) {
				$activeToggle.prop('checked', prefLookup.status === 'active');
			}

			// Set Posts Per Day
			if (prefLookup.posts_per_day !== undefined) {
				$slider.val(prefLookup.posts_per_day);
				$sliderValue.text(prefLookup.posts_per_day);
			}

			// Set Publish Start Time
			if (prefLookup.publish_start_time !== undefined) {
				$startTime.val(prefLookup.publish_start_time);
			}

			// Set Publish End Time
			if (prefLookup.publish_end_time !== undefined) {
				$endTime.val(prefLookup.publish_end_time);
			}

			// Update field states after populating
			toggleFieldsState();
		}

		/**
		 * Toggle fields enabled/disabled state based on Active checkbox
		 */
		function toggleFieldsState() {
			var isActive = $activeToggle.is(':checked');

			$slider.prop('disabled', !isActive);
			$startTime.prop('disabled', !isActive);
			$endTime.prop('disabled', !isActive);

			// Toggle visual disabled state on field groups
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

		// Initialize state on page load
		toggleFieldsState();

		// Fetch preferences from API on page load
		fetchPreferences();

		// Form submission
		$form.on('submit', function(e) {
			e.preventDefault();

			// Only validate time range if active
			if ($activeToggle.is(':checked')) {
				if (!validateTimeRange()) {
					return false;
				}
			}

			// Save functionality will be implemented later
			console.log('Settings form submitted');
		});

	});

})( jQuery );
