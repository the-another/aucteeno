/**
 * Aucteeno Query Loop Block - Interactivity API
 *
 * Handles pagination and infinite scroll with WordPress Interactivity API.
 *
 * @package Aucteeno
 */

import { store, getContext, getElement } from '@wordpress/interactivity';

/**
 * Fetch items from REST API
 *
 * @param {Object} context Current context
 * @param {number} page    Page number to fetch
 * @return {Promise<Object>} API response data
 */
async function fetchItems( context, page ) {
	const url = new URL( context.restUrl, window.location.origin );
	url.searchParams.set( 'page', page );
	url.searchParams.set( 'per_page', context.perPage );
	url.searchParams.set( 'sort', context.orderBy );
	url.searchParams.set( 'format', 'html' );

	if ( context.userId ) {
		url.searchParams.set( 'user_id', context.userId );
	}
	if ( context.country ) {
		url.searchParams.set( 'country', context.country );
	}
	if ( context.subdivision ) {
		url.searchParams.set( 'subdivision', context.subdivision );
	}

	const response = await fetch( url.toString(), {
		headers: {
			'X-WP-Nonce': context.restNonce,
		},
	} );

	if ( ! response.ok ) {
		throw new Error( 'Failed to fetch items' );
	}

	return response.json();
}

/**
 * Build URL for a specific page
 *
 * @param {number} page Page number
 * @return {string} Page URL
 */
function buildPageUrl( page ) {
	const url = new URL( window.location );
	if ( page > 1 ) {
		url.searchParams.set( 'paged', page );
	} else {
		url.searchParams.delete( 'paged' );
	}
	return url.toString();
}

/**
 * Update browser URL
 *
 * @param {number} page Page number
 */
function updateURL( page ) {
	if ( ! window.history?.pushState ) {
		return;
	}

	const url = new URL( window.location );

	if ( page > 1 ) {
		url.searchParams.set( 'paged', page );
	} else {
		url.searchParams.delete( 'paged' );
	}

	window.history.pushState( { page }, '', url.toString() );
}

/**
 * Update pagination UI after AJAX navigation
 *
 * @param {Element} container Query loop container element
 * @param {number}  page      Current page number
 * @param {number}  pages     Total pages
 */
function updatePagination( container, page, pages ) {
	const pagination = container.querySelector(
		'.aucteeno-pagination, .aucteeno-query-loop__pagination'
	);

	if ( ! pagination ) {
		return;
	}

	// Remove current class from all elements.
	pagination
		.querySelectorAll( '.current' )
		.forEach( ( el ) => el.classList.remove( 'current' ) );

	// Add current class to the matching page number.
	const pageLinks = pagination.querySelectorAll( 'a[data-page]' );
	pageLinks.forEach( ( link ) => {
		const linkPage = parseInt( link.dataset.page, 10 );

		if ( linkPage === page ) {
			// Replace the link with a span for current page.
			const span = document.createElement( 'span' );
			span.className = 'page-numbers current';
			span.setAttribute( 'aria-current', 'page' );
			span.textContent = page;
			link.replaceWith( span );
		}
	} );

	// Convert current span back to link if it's no longer current.
	const currentSpans = pagination.querySelectorAll(
		'span.page-numbers.current'
	);
	currentSpans.forEach( ( span ) => {
		const spanPage = parseInt( span.textContent, 10 );
		if ( spanPage !== page && ! isNaN( spanPage ) ) {
			const link = document.createElement( 'a' );
			link.className = 'page-numbers';
			link.href = buildPageUrl( spanPage );
			link.textContent = spanPage;
			link.dataset.page = spanPage;
			link.setAttribute( 'data-wp-on--click', 'actions.loadPage' );
			span.replaceWith( link );
		}
	} );

	// Update prev/next links visibility.
	const prevLink = pagination.querySelector( '.prev' );
	const nextLink = pagination.querySelector( '.next' );

	if ( prevLink ) {
		if ( page <= 1 ) {
			prevLink.style.visibility = 'hidden';
		} else {
			prevLink.style.visibility = 'visible';
			prevLink.dataset.page = page - 1;
			prevLink.href = buildPageUrl( page - 1 );
		}
	}

	if ( nextLink ) {
		if ( page >= pages ) {
			nextLink.style.visibility = 'hidden';
		} else {
			nextLink.style.visibility = 'visible';
			nextLink.dataset.page = page + 1;
			nextLink.href = buildPageUrl( page + 1 );
		}
	}
}

/**
 * Replace items in the list (pagination)
 *
 * @param {Object}  context Current context
 * @param {Object}  data    API response data
 * @param {Element} ref     Container element reference
 */
function replaceItems( context, data, ref ) {
	const itemsList = ref.querySelector( '.aucteeno-items-wrap' );

	if ( itemsList && data.html ) {
		itemsList.innerHTML = data.html;
	}

	context.page = data.page;
	context.pages = data.pages;
	context.total = data.total;
	context.hasMore = data.page < data.pages;

	// Update pagination UI to reflect new current page.
	updatePagination( ref, data.page, data.pages );
}

/**
 * Append items to the list (infinite scroll)
 *
 * @param {Object}  context Current context
 * @param {Object}  data    API response data
 * @param {Element} ref     Container element reference
 */
function appendItems( context, data, ref ) {
	const itemsList = ref.querySelector( '.aucteeno-items-wrap' );

	if ( itemsList && data.html ) {
		// Create temporary container
		const temp = document.createElement( 'div' );
		temp.innerHTML = data.html;

		// Get article elements from REST response
		const articles = temp.querySelectorAll( 'article.aucteeno-card' );

		// Wrap each article in <li> and append
		articles.forEach( ( article ) => {
			const li = document.createElement( 'li' );
			li.className = 'aucteeno-query-loop__item';
			li.appendChild( article );
			itemsList.appendChild( li );
		} );
	}

	context.page = data.page;
	context.pages = data.pages;
	context.total = data.total;
	context.hasMore = data.page < data.pages;
}

// Store reference for use in callbacks.
const { state, actions } = store( 'aucteeno/query-loop', {
	state: {
		/**
		 * Computed state - can load more items
		 *
		 * @return {boolean} True if can load more
		 */
		get canLoadMore() {
			const context = getContext();
			return ! context.isLoading && context.hasMore;
		},
	},

	actions: {
		/**
		 * Load a specific page (pagination clicks)
		 *
		 * @param {Event} event Click event
		 */
		*loadPage( event ) {
			event.preventDefault();

			const context = getContext();
			const element = getElement();
			const page = parseInt( event.target.dataset.page, 10 );

			if ( ! page || page === context.page || context.isLoading ) {
				return;
			}

			context.isLoading = true;

			try {
				const data = yield fetchItems( context, page );
				replaceItems( context, data, element.ref );
				updateURL( page );

				// Scroll to top of query loop
				if ( element.ref ) {
					element.ref.scrollIntoView( {
						behavior: 'smooth',
						block: 'start',
					} );
				}
			} catch ( error ) {
				context.error = error.message;
				console.error( 'Failed to load page:', error );
			} finally {
				context.isLoading = false;
			}
		},

		/**
		 * Load next page (infinite scroll)
		 */
		*loadMore() {
			const context = getContext();
			const element = getElement();

			if ( ! context.hasMore || context.isLoading ) {
				return;
			}

			const nextPage = context.page + 1;
			context.isLoading = true;

			try {
				const data = yield fetchItems( context, nextPage );
				appendItems( context, data, element.ref );
				updateURL( nextPage );
			} catch ( error ) {
				context.error = error.message;
				console.error( 'Failed to load more items:', error );
			} finally {
				context.isLoading = false;
			}
		},
	},

	callbacks: {
		/**
		 * Initialize on mount
		 */
		onInit() {
			// Listen for browser back/forward
			window.addEventListener( 'popstate', ( event ) => {
				if ( event.state?.page ) {
					window.location.reload();
				}
			} );
		},
	},
} );