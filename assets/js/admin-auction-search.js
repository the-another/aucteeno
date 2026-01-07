/**
 * Aucteeno Admin Auction Search JavaScript
 *
 * Initializes Select2 on the parent auction field for searchable auction selection.
 *
 * @package Aucteeno
 * @since 1.0.0
 */
(function ($) {
	'use strict';

	/**
	 * Initialize Select2 on parent auction field.
	 */
	function initAuctionSelect2() {
		var $auctionField = $('#aucteeno_item_parent_auction_id');

		if (!$auctionField.length) {
			return;
		}

		// Check if Select2 is available.
		if (typeof $.fn.select2 === 'undefined') {
			console.warn('Select2 is not available. Please ensure it is enqueued.');
			return;
		}

		// Initialize Select2 with search functionality.
		$auctionField.select2({
			width: '100%',
			placeholder: $auctionField.find('option[value=""]').text(),
			allowClear: false,
			minimumResultsForSearch: 0,
			language: {
				noResults: function () {
					return 'No auctions found';
				},
				searching: function () {
					return 'Searching...';
				}
			}
		});

		// Validate Select2 field on form submission.
		// This handles the case where Select2 hides the original select element.
		// Only validate if this is an item product.
		var $form = $auctionField.closest('form');
		if ($form.length && !$form.data('aucteeno-select2-validated')) {
			// Mark form as having validation handler to prevent duplicates.
			$form.data('aucteeno-select2-validated', true);
			
			$form.on('submit', function (e) {
				// Check if the current product type is item.
				var productType = $('#product-type').val();
				if (productType !== 'aucteeno-ext-item') {
					// Not an item product, skip validation.
					return;
				}
				
				var $field = $('#aucteeno_item_parent_auction_id');
				// Validate only if field exists and is required.
				if ($field.length && $field.data('required')) {
					var selectedValue = $field.val();
					if (!selectedValue || selectedValue === '') {
						e.preventDefault();
						e.stopImmediatePropagation();
						
						// Show validation error on Select2 container.
						var $select2Container = $field.next('.select2-container');
						if ($select2Container.length) {
							$select2Container.addClass('select2-container--error');
						}
						
						// Focus the Select2 dropdown.
						$field.select2('open');
						
						// Show error message.
						alert('Items must belong to exactly one auction. Please select a parent auction.');
						
						return false;
					} else {
						// Remove error styling if value is selected.
						var $select2Container = $field.next('.select2-container');
						if ($select2Container.length) {
							$select2Container.removeClass('select2-container--error');
						}
					}
				}
			});
		}
	}

	/**
	 * Destroy Select2 instance (for re-initialization).
	 */
	function destroyAuctionSelect2() {
		var $auctionField = $('#aucteeno_item_parent_auction_id');
		if ($auctionField.length && typeof $.fn.select2 !== 'undefined') {
			$auctionField.select2('destroy');
		}
	}

	// Initialize on document ready.
	$(document).ready(function () {
		initAuctionSelect2();
	});

	// Re-initialize when WooCommerce product type changes.
	$(document).on('woocommerce-product-type-change', function () {
		destroyAuctionSelect2();
		// Small delay to ensure DOM is updated.
		setTimeout(function () {
			initAuctionSelect2();
		}, 100);
	});

	// Re-initialize when Aucteeno tabs are switched (in case field is in a tab).
	$(document).on('click', '.aucteeno-tab-link', function () {
		setTimeout(function () {
			initAuctionSelect2();
		}, 100);
	});

})(jQuery);

