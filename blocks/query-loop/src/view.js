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
	if ( context.auctionId ) {
		url.searchParams.set( 'auction_id', context.auctionId );
	}
	if ( context.country ) {
		url.searchParams.set( 'country', context.country );
	}
	if ( context.subdivision ) {
		url.searchParams.set( 'subdivision', context.subdivision );
	}
	if ( context.search ) {
		url.searchParams.set( 'search', context.search );
	}
	if ( context.productIds && context.productIds.length ) {
		url.searchParams.set( 'product_ids', context.productIds.join( ',' ) );
	}

	// Pass block template so REST API can render cards with same structure.
	if ( context.blockTemplate ) {
		url.searchParams.set( 'block_template', context.blockTemplate );
	}

	// Pass page URL for pagination link generation.
	if ( context.pageUrl ) {
		url.searchParams.set( 'page_url', context.pageUrl );
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
 * Update browser URL
 *
 * @param {number} page Page number
 */
function updateURL( page ) {
	if ( ! window.history?.pushState ) {
		return;
	}

	// Get base URL without /page/X/ and without ?paged query param.
	let baseUrl = window.location.origin + window.location.pathname;
	baseUrl = baseUrl.replace( /\/page\/\d+\/?$/, '/' );

	// Preserve search query parameter.
	const urlParams = new URLSearchParams( window.location.search );
	const searchParam = urlParams.get( 's' );

	let newUrl;
	if ( page > 1 ) {
		// Build URL with /page/X/ format (clean permalink).
		newUrl = baseUrl.replace( /\/$/, '' ) + '/page/' + page + '/';
	} else {
		// Page 1: just the base URL without pagination.
		newUrl = baseUrl;
	}

	// Re-add search parameter if present.
	if ( searchParam ) {
		newUrl += '?s=' + encodeURIComponent( searchParam );
	}

	window.history.pushState( { page }, '', newUrl );
}

/**
 * Replace pagination HTML from REST API response
 *
 * @param {Element} container      Query loop container element
 * @param {string}  paginationHtml New pagination HTML from REST API
 */
function replacePagination( container, paginationHtml ) {
	const pagination = container.querySelector(
		'.aucteeno-pagination, .aucteeno-query-loop__pagination'
	);

	if ( ! pagination ) {
		return;
	}

	// Replace pagination content with server-rendered HTML.
	pagination.innerHTML = paginationHtml;
}

/**
 * Find the query-loop container from any element inside it
 *
 * @param {Element} element Any element inside the query-loop
 * @return {Element|null} The query-loop container or null
 */
function findContainer( element ) {
	return element.closest( '.aucteeno-query-loop' );
}

/**
 * Replace items in the list (pagination)
 *
 * @param {Object}  context Current context
 * @param {Object}  data    API response data
 * @param {Element} ref     Element reference (may be clicked element or container)
 */
function replaceItems( context, data, ref ) {
	// Find the container - ref might be the clicked link, not the container.
	const container = findContainer( ref ) || ref;
	const itemsList = container.querySelector( '.aucteeno-items-wrap' );

	if ( itemsList && data.html ) {
		itemsList.innerHTML = data.html;
	}

	context.page = data.page;
	context.pages = data.pages;
	context.total = data.total;
	context.hasMore = data.page < data.pages;

	// Replace pagination with server-rendered HTML.
	if ( data.pagination ) {
		replacePagination( container, data.pagination );
	}

	// Dispatch event for other scripts to re-initialize on new content
	document.dispatchEvent( new CustomEvent( 'aucteeno:contentLoaded' ) );
}

/**
 * Append items to the list (infinite scroll)
 *
 * @param {Object}  context Current context
 * @param {Object}  data    API response data
 * @param {Element} ref     Element reference (may be sentinel or container)
 */
function appendItems( context, data, ref ) {
	// Find the container - ref might be the sentinel element, not the container.
	const container = findContainer( ref ) || ref;
	const itemsList = container.querySelector( '.aucteeno-items-wrap' );

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

	// Dispatch event for other scripts to re-initialize on new content
	document.dispatchEvent( new CustomEvent( 'aucteeno:contentLoaded' ) );
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

			// Find container BEFORE replacing items (element may be detached after DOM updates).
			const container = findContainer( element.ref );

			context.isLoading = true;

			try {
				const data = yield fetchItems( context, page );
				replaceItems( context, data, element.ref );

				// Update URL only if enabled
				if ( context.updateUrl ) {
					updateURL( page );
				}

				// Scroll to top of query loop container with offset
				if ( container ) {
					const offset = 50;
					const top = container.getBoundingClientRect().top + window.scrollY - offset;
					window.scrollTo( {
						top,
						behavior: 'smooth',
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

				// Update URL only if enabled
				if ( context.updateUrl ) {
					updateURL( nextPage );
				}
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
		 * Initialize infinite scroll observer on sentinel element
		 */
		initInfiniteScroll() {
			const element = getElement();
			const sentinel = element.ref;
			// Capture context during init - getContext() only works in Interactivity API callbacks.
			const context = getContext();
			const container = findContainer( sentinel );

			// Create IntersectionObserver to detect when sentinel enters viewport.
			const observer = new IntersectionObserver(
				( entries ) => {
					const entry = entries[ 0 ];
					if (
						entry.isIntersecting &&
						context.hasMore &&
						! context.isLoading
					) {
						// Load more items directly (can't use generator action from observer).
						const nextPage = context.page + 1;
						context.isLoading = true;

						fetchItems( context, nextPage )
							.then( ( data ) => {
								appendItems( context, data, container );

								// Update URL only if enabled
								if ( context.updateUrl ) {
									updateURL( nextPage );
								}
							} )
							.catch( ( error ) => {
								context.error = error.message;
								// eslint-disable-next-line no-console
								console.error(
									'Failed to load more items:',
									error
								);
							} )
							.finally( () => {
								context.isLoading = false;
							} );
					}
				},
				{
					// Trigger when sentinel is 200px from entering viewport.
					rootMargin: '200px',
					threshold: 0,
				}
			);

			observer.observe( sentinel );

			// Store observer reference for cleanup if needed.
			sentinel._infiniteScrollObserver = observer;
		},

		/**
		 * Initialize on mount
		 */
		onInit() {
			const element = getElement();
			const container = element.ref;
			// Capture context during init - getContext() only works in Interactivity API callbacks.
			const context = getContext();

			// Event delegation for pagination clicks.
			// This handles clicks on dynamically replaced pagination HTML
			// where Interactivity API directives aren't automatically re-bound.
			container.addEventListener( 'click', ( event ) => {
				const link = event.target.closest( 'a[data-page]' );
				if ( ! link ) {
					return;
				}

				event.preventDefault();

				const page = parseInt( link.dataset.page, 10 );

				if ( ! page || page === context.page || context.isLoading ) {
					return;
				}

				context.isLoading = true;

				fetchItems( context, page )
					.then( ( data ) => {
						replaceItems( context, data, container );

						// Update URL only if enabled
						if ( context.updateUrl ) {
							updateURL( page );
						}

						// Scroll to top of query loop container with offset.
						const offset = 50;
						const top =
							container.getBoundingClientRect().top +
							window.scrollY -
							offset;
						window.scrollTo( {
							top,
							behavior: 'smooth',
						} );
					} )
					.catch( ( error ) => {
						context.error = error.message;
						console.error( 'Failed to load page:', error );
					} )
					.finally( () => {
						context.isLoading = false;
					} );
			} );

			// Listen for browser back/forward.
			window.addEventListener( 'popstate', ( event ) => {
				if ( event.state?.page ) {
					window.location.reload();
				}
			} );
		},
	},
} );
