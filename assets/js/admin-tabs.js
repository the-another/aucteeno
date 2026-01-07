/**
 * Aucteeno Admin Tabs JavaScript
 *
 * @package Aucteeno
 * @since 1.0.0
 */
(function ($) {
	'use strict';

	/**
	 * Initialize Aucteeno tabs.
	 */
	function initAucteenoTabs() {
		var $tabsWrapper = $('.aucteeno-tabs-wrapper');
		
		if (!$tabsWrapper.length) {
			return;
		}

		// Handle tab clicks
		$tabsWrapper.on('click', '.aucteeno-tab-link', function (e) {
			e.preventDefault();
			
			var $link = $(this);
			var $tab = $link.closest('.aucteeno-tab');
			var tabKey = $link.data('tab');
			var $targetPanel = $('#aucteeno-tab-panel-' + tabKey);

			// Return if panel doesn't exist
			if (!$targetPanel.length) {
				return;
			}

			// Remove active class from all tabs and panels
			$tabsWrapper.find('.aucteeno-tab').removeClass('active');
			$tabsWrapper.find('.aucteeno-tab-link').attr('aria-selected', 'false');
			$tabsWrapper.find('.aucteeno-tab-panel').removeClass('active').hide();

			// Add active class to clicked tab and show panel
			$tab.addClass('active');
			$link.attr('aria-selected', 'true');
			$targetPanel.addClass('active').show();
		});

		// Handle hash navigation (for direct links to tabs)
		if (window.location.hash) {
			var hash = window.location.hash.substring(1);
			var $hashTab = $('.aucteeno-tab-link[href="#' + hash + '"]');
			if ($hashTab.length) {
				$hashTab.trigger('click');
			}
		}
	}

	// Initialize on document ready
	$(document).ready(function () {
		initAucteenoTabs();
	});

	// Re-initialize when WooCommerce product type changes (for show_if_* classes)
	$(document).on('woocommerce-product-type-change', function () {
		// Tabs are already initialized, but this ensures they work after type change
		initAucteenoTabs();
	});

})(jQuery);

