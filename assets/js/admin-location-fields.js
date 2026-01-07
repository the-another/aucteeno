/**
 * Aucteeno Admin Location Fields JavaScript
 *
 * Handles dynamic state dropdown updates when country changes.
 *
 * @package Aucteeno
 * @since 1.0.0
 */
(function ($) {
	'use strict';

	var allStates = {};

	/**
	 * Initialize location field handlers.
	 */
	function initLocationFields() {
		// Store all states data if provided.
		if (typeof aucteenoLocationFields !== 'undefined' && aucteenoLocationFields.states) {
			allStates = aucteenoLocationFields.states;
		}
	}

	/**
	 * Handle country field change.
	 */
	function handleCountryChange() {
		var $countryField = $(this);
		var countryCode = $countryField.val();
		
		// Find state field in the same options group.
		var $stateField = $countryField.closest('.options_group').find('.aucteeno-state-select');
		
		if (!$stateField.length) {
			// Try to find state field by ID pattern.
			var countryFieldId = $countryField.attr('id');
			if (countryFieldId) {
				// For auction: aucteeno_auction_location_country -> aucteeno_auction_location_state
				// For item: aucteeno_item_location_country -> aucteeno_item_location_state
				var stateFieldId = countryFieldId.replace('_country', '_state');
				$stateField = $('#' + stateFieldId);
			}
		}

		if (!$stateField.length) {
			return;
		}

		var $stateFieldWrapper = $stateField.closest('p.form-field');

		if (!countryCode || countryCode === '') {
			// No country selected, hide state field and clear value.
			$stateFieldWrapper.hide();
			$stateField.val('').trigger('change');
			return;
		}

		// Get states for selected country.
		var states = allStates[countryCode] || {};

		// Clear existing options.
		$stateField.empty();

		// Add placeholder option.
		$stateField.append($('<option></option>').attr('value', '').text('Select an option…'));

		// Add state options if available.
		if (Object.keys(states).length > 0) {
			$.each(states, function (code, name) {
				var $option = $('<option></option>').attr('value', code).text(name);
				$stateField.append($option);
			});

			// Show state field.
			$stateFieldWrapper.show();
			// Clear selection when country changes (user must re-select state).
			$stateField.val('').trigger('change');
		} else {
			// No states for this country, hide field and clear value.
			$stateFieldWrapper.hide();
			$stateField.val('').trigger('change');
		}
	}

	// Initialize on document ready.
	$(document).ready(function () {
		initLocationFields();

		// Use event delegation to handle country field changes.
		$(document).on('change', '.aucteeno-country-select', handleCountryChange);

		// Re-initialize when WooCommerce product type changes (tabs shown/hidden).
		$(document).on('woocommerce-product-type-change', function () {
			setTimeout(initLocationFields, 100);
		});
	});
})(jQuery);

