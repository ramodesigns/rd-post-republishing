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
