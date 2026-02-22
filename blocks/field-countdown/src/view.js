/**
 * Aucteeno Field Countdown Block - Frontend Script
 *
 * Live countdown timer with smart scaling and dynamic state calculation.
 *
 * TIMEZONE HANDLING STRATEGY:
 * ---------------------------
 * 1. STORAGE: Timestamps are stored as Unix timestamps (seconds since 1970-01-01 00:00:00 UTC)
 *    - These are timezone-agnostic numbers representing a specific moment in time
 *    - Example: 1737158400 represents 2026-01-17 00:00:00 UTC regardless of timezone
 *
 * 2. CALCULATIONS: All time calculations (comparisons, differences) use UTC
 *    - Date.now() returns milliseconds since Unix epoch in UTC
 *    - timestamp - now gives the difference in seconds (timezone-agnostic)
 *    - This ensures consistent behavior regardless of user's timezone
 *
 * 3. DISPLAY: Dates are converted to user's local timezone for display
 *    - new Date(timestamp * 1000) creates a Date object
 *    - JavaScript Date methods (getFullYear, getMonth, etc.) automatically use local timezone
 *    - Users see dates/times in their browser's timezone
 *
 * EXAMPLE:
 * - Server stores: 1737158400 (Unix timestamp for 2026-01-17 00:00:00 UTC)
 * - User in EST (UTC-5): Sees "January 16, 2026" (2026-01-16 19:00:00 EST)
 * - User in JST (UTC+9): Sees "January 17, 2026" (2026-01-17 09:00:00 JST)
 * - Both users see the SAME moment in time, just in their local timezone
 *
 * @package Aucteeno
 */

import {
	formatDate,
	calculateState,
	formatCountdown,
	getUpdateInterval,
	updateCardClasses,
} from './countdown-utils';

/**
 * Update a single countdown element.
 *
 * @param {HTMLElement} element The countdown element.
 */
function updateCountdown( element ) {
	const startsAt = parseInt( element.dataset.startsAt, 10 );
	const endsAt = parseInt( element.dataset.endsAt, 10 );
	const previousState = element.dataset.currentState || '';
	const dateFormat = element.dataset.dateFormat || 'default';

	if ( ! startsAt || ! endsAt ) {
		return;
	}

	// Get current UTC timestamp (Date.now() returns milliseconds since Unix epoch in UTC)
	// Calculations are done in UTC to ensure consistency regardless of user's timezone
	const now = Math.floor( Date.now() / 1000 );
	const stateInfo = calculateState( now, startsAt, endsAt );
	const { state, timestamp } = stateInfo;

	// Calculate countdown display.
	const diff = timestamp - now;
	const { displayValue, isShowingDate } = formatCountdown(
		diff,
		timestamp,
		state,
		dateFormat
	);

	// Determine label based on state and whether showing date.
	let label;
	if ( state === 'expired' ) {
		label = 'Bidding ended';
	} else if ( isShowingDate ) {
		// When showing a date, use "on" instead of "in".
		label = state === 'upcoming' ? 'Bidding starts on' : 'Bidding ends on';
	} else {
		// When showing time intervals, use "in".
		label = state === 'upcoming' ? 'Bidding starts in' : 'Bidding ends in';
	}

	// Update the countdown value.
	const valueEl = element.querySelector( '.aucteeno-field-countdown__value' );
	if ( valueEl ) {
		valueEl.textContent = displayValue;
	}

	// Update the label if it changed.
	const labelEl = element.querySelector( '.aucteeno-field-countdown__label' );
	if ( labelEl && labelEl.textContent !== label ) {
		labelEl.textContent = label;
	}

	// Update countdown element classes if state changed.
	if ( state !== previousState ) {
		element.classList.remove(
			'aucteeno-field-countdown--upcoming',
			'aucteeno-field-countdown--running',
			'aucteeno-field-countdown--expired'
		);
		element.classList.add( `aucteeno-field-countdown--${ state }` );
		element.dataset.currentState = state;

		// Find and update parent card element.
		const cardElement = element.closest( '.aucteeno-card' );
		if ( cardElement ) {
			updateCardClasses( cardElement, state, previousState );
		}
	}

	// Schedule next update.
	// For expired items, continue updating if less than 1 week ago.
	const shouldContinue = state !== 'expired' || Math.abs( diff ) < 604800;
	if ( shouldContinue ) {
		const interval = getUpdateInterval( Math.abs( diff ) );
		setTimeout( () => updateCountdown( element ), interval );
	}
}

/**
 * Initialize all countdown elements on the page.
 *
 * @param {Element} container Optional container to search within. If not provided, searches entire document.
 */
function initCountdowns( container = document ) {
	const elements = container.querySelectorAll( '[data-aucteeno-countdown]' );
	elements.forEach( ( element ) => {
		// Skip if already initialized (has currentState).
		if ( ! element.dataset.initialized ) {
			element.dataset.initialized = 'true';
			updateCountdown( element );
		}
	} );
}

// Initialize on DOM ready.
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', () => initCountdowns() );
} else {
	initCountdowns();
}

// Watch for dynamically added countdown elements (infinite scroll, AJAX pagination).
const observer = new MutationObserver( ( mutations ) => {
	mutations.forEach( ( mutation ) => {
		mutation.addedNodes.forEach( ( node ) => {
			// Only process element nodes.
			if ( node.nodeType !== Node.ELEMENT_NODE ) {
				return;
			}

			// Check if the added node itself is a countdown element.
			if ( node.matches && node.matches( '[data-aucteeno-countdown]' ) ) {
				if ( ! node.dataset.initialized ) {
					node.dataset.initialized = 'true';
					updateCountdown( node );
				}
			}

			// Check for countdown elements within the added node.
			if ( node.querySelectorAll ) {
				initCountdowns( node );
			}
		} );
	} );
} );

// Start observing the document for added nodes.
observer.observe( document.body, {
	childList: true,
	subtree: true,
} );

// Re-initialize on dynamic content loads (infinite scroll, AJAX pagination).
document.addEventListener( 'aucteeno:contentLoaded', () => initCountdowns() );
