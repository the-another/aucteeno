/**
 * Query Loop View Script
 *
 * Handles REST API navigation for pagination without full page reload.
 *
 * @package Aucteeno
 * @since 2.0.0
 */

(function() {
	'use strict';

	// Wait for DOM to be ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	function init() {
		const queryLoops = document.querySelectorAll('.aucteeno-query-loop[data-query-type]');
		
		queryLoops.forEach(function(queryLoop) {
			const pagination = queryLoop.querySelector('.aucteeno-pagination, .aucteeno-query-loop__pagination');
			if (!pagination) {
				return;
			}

			// Intercept pagination link clicks.
			pagination.addEventListener('click', function(e) {
				const link = e.target.closest('a');
				if (!link || link.target === '_blank' || link.hasAttribute('download')) {
					return;
				}

				// Check if it's a pagination link (not a different link).
				const href = link.getAttribute('href');
				if (!href || !href.match(/[?&]paged?=\d+/)) {
					return;
				}

				// Prevent default navigation.
				e.preventDefault();

				// Extract page number from URL.
				const url = new URL(href, window.location.origin);
				const page = url.searchParams.get('paged') || url.searchParams.get('page') || '1';

				// Fetch content via REST API.
				fetchQueryLoopContent(queryLoop, parseInt(page, 10), href);
			});
		});
	}

	function fetchQueryLoopContent(queryLoop, page, newUrl) {
		// Get query parameters from data attributes.
		const queryType = queryLoop.getAttribute('data-query-type');
		const userId = queryLoop.getAttribute('data-user-id') || '0';
		const perPage = queryLoop.getAttribute('data-per-page') || '12';
		const orderBy = queryLoop.getAttribute('data-order-by') || 'ending_soon';
		const country = queryLoop.getAttribute('data-country') || '';
		const subdivision = queryLoop.getAttribute('data-subdivision') || '';

		// Build REST API URL using WordPress REST API pattern.
		const endpoint = queryType === 'items' ? '/items' : '/auctions';
		const apiUrl = new URL('/wp-json/aucteeno/v1' + endpoint, window.location.origin);
		
		apiUrl.searchParams.set('format', 'html');
		apiUrl.searchParams.set('page', page.toString());
		apiUrl.searchParams.set('per_page', perPage);
		apiUrl.searchParams.set('sort', orderBy);
		
		if (userId && userId !== '0') {
			apiUrl.searchParams.set('user_id', userId);
		}
		if (country) {
			apiUrl.searchParams.set('country', country);
		}
		if (subdivision) {
			apiUrl.searchParams.set('subdivision', subdivision);
		}

		// Show loading state.
		const itemsWrap = queryLoop.querySelector('.aucteeno-items-wrap, .aucteeno-query-loop__items');
		if (itemsWrap) {
			itemsWrap.style.opacity = '0.5';
		}

		// Fetch HTML fragments from REST API.
		fetch(apiUrl.toString(), {
			method: 'GET',
			headers: {
				'Content-Type': 'application/json',
			},
			credentials: 'same-origin',
		})
		.then(function(response) {
			if (!response.ok) {
				throw new Error('Network response was not ok');
			}
			return response.json();
		})
		.then(function(data) {
			// Update content with HTML fragments.
			// The REST API returns { html: '...', page: ..., pages: ..., total: ... }
			if (data.html && itemsWrap) {
				itemsWrap.innerHTML = data.html;
				itemsWrap.style.opacity = '1';
			}

			// Update URL without reload.
			if (window.history && window.history.pushState) {
				window.history.pushState({ page: page }, '', newUrl);
			}

			// Scroll to top of query loop.
			queryLoop.scrollIntoView({ behavior: 'smooth', block: 'start' });
		})
		.catch(function(error) {
			console.error('Error fetching query loop content:', error);
			// Restore opacity on error.
			if (itemsWrap) {
				itemsWrap.style.opacity = '1';
			}
			// Fallback to full page reload.
			window.location.href = newUrl;
		});
	}
})();