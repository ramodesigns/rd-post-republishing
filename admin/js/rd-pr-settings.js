/**
 * Settings page JavaScript.
 *
 * @package    Rd_Post_Republishing
 * @subpackage Rd_Post_Republishing/admin/js
 */

(function( $ ) {
	'use strict';

	$(document).ready(function() {

		// Slider value display
		var $slider = $('#rd-pr-posts-per-day');
		var $sliderValue = $('#rd-pr-posts-per-day-value');

		if ($slider.length && $sliderValue.length) {
			// Update value display on input
			$slider.on('input', function() {
				$sliderValue.text(this.value);
			});

			// Set initial value
			$sliderValue.text($slider.val());
		}

		// Form submission placeholder
		$('#rd-pr-settings-form').on('submit', function(e) {
			e.preventDefault();
			// Save functionality will be implemented later
			console.log('Settings form submitted');
		});

	});

})( jQuery );
