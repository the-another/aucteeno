/**
 * Auction Listing Block - Frontend Script
 *
 * Handles infinite scroll and location filtering for auction listings.
 *
 * @package Aucteeno
 */

import './style.css';

/**
 * Initialize all auction listing blocks on the page.
 */
function initAuctionListings() {
	const listings = document.querySelectorAll(
		'.wp-block-aucteeno-auction-listing[data-aucteeno-listing]'
	);

	listings.forEach( initListing );
}

/**
 * Initialize a single auction listing block.
 *
 * @param {HTMLElement} container The listing container element.
 */
function initListing( container ) {
	const config = JSON.parse( container.dataset.auctenoListing || '{}' );
	const grid = container.querySelector( '.aucteeno-listing__grid' );
	const sentinel = container.querySelector( '.aucteeno-listing__sentinel' );
	const locationFilter = container.querySelector(
		'.aucteeno-listing__location-filter'
	);

	if ( ! grid || ! sentinel ) {
		return;
	}

	const state = {
		page: config.page || 1,
		pages: config.pages || 1,
		loading: false,
		location: config.location || '',
		perPage: config.perPage || 10,
		sort: config.sort || 'ending_soon',
	};

	// Set up location filter.
	if ( locationFilter ) {
		locationFilter.addEventListener( 'change', ( e ) => {
			state.location = e.target.value;
			state.page = 1;
			grid.innerHTML = '';
			loadMore();
		} );
	}

	// Set up infinite scroll observer.
	const observer = new IntersectionObserver(
		( entries ) => {
			entries.forEach( ( entry ) => {
				if (
					entry.isIntersecting &&
					! state.loading &&
					state.page < state.pages
				) {
					loadMore();
				}
			} );
		},
		{
			rootMargin: '200px',
		}
	);

	observer.observe( sentinel );

	/**
	 * Load more auctions via REST API.
	 */
	async function loadMore() {
		if ( state.loading || state.page >= state.pages ) {
			return;
		}

		state.loading = true;
		state.page++;

		sentinel.classList.add( 'aucteeno-listing__sentinel--loading' );

		try {
			const params = new URLSearchParams( {
				page: state.page,
				per_page: state.perPage,
				sort: state.sort,
				format: 'html',
			} );

			if ( state.location ) {
				params.append( 'location', state.location );
			}

			const response = await fetch(
				`${ config.restUrl }?${ params.toString() }`,
				{
					headers: {
						'X-WP-Nonce': config.nonce,
					},
				}
			);

			if ( ! response.ok ) {
				throw new Error( 'Failed to load auctions' );
			}

			const data = await response.json();

			// Append new items to grid.
			if ( data.html ) {
				grid.insertAdjacentHTML( 'beforeend', data.html );
			}

			// Update pagination state.
			state.pages = data.pages || state.pages;

			// Hide sentinel if no more pages.
			if ( state.page >= state.pages ) {
				sentinel.style.display = 'none';
			}
		} catch ( error ) {
			console.error( 'Aucteeno: Failed to load auctions', error );
		} finally {
			state.loading = false;
			sentinel.classList.remove( 'aucteeno-listing__sentinel--loading' );
		}
	}
}

// Initialize on DOM ready.
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initAuctionListings );
} else {
	initAuctionListings();
}
