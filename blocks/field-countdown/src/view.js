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

/**
 * Format a date based on the selected format.
 *
 * The timestamp is in UTC (Unix timestamp). We convert it to the user's local timezone for display.
 *
 * @param {number} timestamp   Unix timestamp in seconds (UTC-based).
 * @param {string} dateFormat  Date format setting.
 * @return {string} Formatted date string in user's local timezone.
 */
function formatDate( timestamp, dateFormat ) {
	// Create Date object from UTC timestamp - Date will automatically convert to local timezone
	const date = new Date( timestamp * 1000 );
	const months = [
		'January',
		'February',
		'March',
		'April',
		'May',
		'June',
		'July',
		'August',
		'September',
		'October',
		'November',
		'December',
	];
	const days = [
		'Sunday',
		'Monday',
		'Tuesday',
		'Wednesday',
		'Thursday',
		'Friday',
		'Saturday',
	];

	const getOrdinalSuffix = ( day ) => {
		if ( day > 3 && day < 21 ) {
			return 'th';
		}
		switch ( day % 10 ) {
			case 1:
				return 'st';
			case 2:
				return 'nd';
			case 3:
				return 'rd';
			default:
				return 'th';
		}
	};

	// Get date components in user's local timezone
	const year = date.getFullYear();
	const month = date.getMonth();
	const day = date.getDate();
	const dayOfWeek = date.getDay();

	switch ( dateFormat ) {
		case 'mdy':
			return `${ String( month + 1 ).padStart( 2, '0' ) }/${ String(
				day
			).padStart( 2, '0' ) }/${ year }`;
		case 'dmy':
			return `${ String( day ).padStart( 2, '0' ) }/${ String(
				month + 1
			).padStart( 2, '0' ) }/${ year }`;
		case 'ymd':
			return `${ year }-${ String( month + 1 ).padStart(
				2,
				'0'
			) }-${ String( day ).padStart( 2, '0' ) }`;
		case 'long':
			return `${ months[ month ] } ${ day }, ${ year }`;
		case 'long_eu':
			return `${ day } ${ months[ month ] } ${ year }`;
		case 'full':
			return `${ days[ dayOfWeek ] }, ${ months[ month ] } ${
				day + getOrdinalSuffix( day )
			} ${ year }`;
		case 'default':
		default:
			return date.toLocaleDateString();
	}
}

/**
 * Calculate current state based on start and end times.
 * All timestamps are in UTC (Unix timestamps), so comparisons are timezone-agnostic.
 *
 * @param {number} now        Current UTC timestamp in seconds.
 * @param {number} startsAt   Start UTC timestamp in seconds.
 * @param {number} endsAt     End UTC timestamp in seconds.
 * @return {Object} State object with state and timestamp.
 */
function calculateState( now, startsAt, endsAt ) {
	if ( now < startsAt ) {
		return {
			state: 'upcoming',
			timestamp: startsAt,
		};
	}
	if ( now >= startsAt && now < endsAt ) {
		return {
			state: 'running',
			timestamp: endsAt,
		};
	}
	return {
		state: 'expired',
		timestamp: endsAt,
	};
}

/**
 * Format countdown display with smart scaling.
 *
 * @param {number} diff       Difference in seconds.
 * @param {number} timestamp  Unix timestamp.
 * @param {string} state      Current state (upcoming/running/expired).
 * @param {string} dateFormat Date format setting.
 * @return {Object} Object with displayValue and isShowingDate.
 */
function formatCountdown( diff, timestamp, state, dateFormat ) {
	// For expired items, show elapsed time.
	if ( state === 'expired' ) {
		const elapsed = Math.abs( diff );

		if ( elapsed < 3600 ) {
			// Less than 1 hour ago - show minutes and seconds elapsed.
			const minutes = Math.floor( elapsed / 60 );
			const seconds = Math.floor( elapsed % 60 );
			const parts = [];

			if ( minutes > 0 ) {
				parts.push( `${ minutes } ${ minutes === 1 ? 'minute' : 'minutes' }` );
			}

			parts.push( `${ seconds } ${ seconds === 1 ? 'second' : 'seconds' }` );

			return {
				displayValue: `${ parts.join( ' ' ) } ago`,
				isShowingDate: false,
			};
		}
		if ( elapsed < 86400 ) {
			// Less than 1 day ago - show hours elapsed.
			const hours = Math.floor( elapsed / 3600 );
			return {
				displayValue: `${ hours } ${ hours === 1 ? 'hour' : 'hours' } ago`,
				isShowingDate: false,
			};
		}
		if ( elapsed < 604800 ) {
			// Less than 1 week ago - show days elapsed.
			const days = Math.floor( elapsed / 86400 );
			return {
				displayValue: `${ days } ${ days === 1 ? 'day' : 'days' } ago`,
				isShowingDate: false,
			};
		}
		// More than 1 week ago - show the end date.
		return {
			displayValue: formatDate( timestamp, dateFormat ),
			isShowingDate: true,
		};
	}

	// For upcoming/running items, show countdown.
	if ( diff <= 0 ) {
		return {
			displayValue: formatDate( timestamp, dateFormat ),
			isShowingDate: true,
		};
	}
	if ( diff < 3600 ) {
		// Less than 1 hour - show minutes and seconds.
		const minutes = Math.floor( diff / 60 );
		const seconds = Math.floor( diff % 60 );
		const parts = [];

		if ( minutes > 0 ) {
			parts.push( `${ minutes } ${ minutes === 1 ? 'minute' : 'minutes' }` );
		}

		parts.push( `${ seconds } ${ seconds === 1 ? 'second' : 'seconds' }` );

		return {
			displayValue: parts.join( ' ' ),
			isShowingDate: false,
		};
	}
	if ( diff < 86400 ) {
		// Less than 1 day - show hours.
		const hours = Math.floor( diff / 3600 );
		return {
			displayValue: `${ hours } ${ hours === 1 ? 'hour' : 'hours' }`,
			isShowingDate: false,
		};
	}
	if ( diff < 604800 ) {
		// Less than 1 week - show days.
		const days = Math.floor( diff / 86400 );
		return {
			displayValue: `${ days } ${ days === 1 ? 'day' : 'days' }`,
			isShowingDate: false,
		};
	}
	// More than 1 week - show date.
	return {
		displayValue: formatDate( timestamp, dateFormat ),
		isShowingDate: true,
	};
}

/**
 * Get update interval based on time remaining.
 *
 * @param {number} diff Difference in seconds.
 * @return {number} Interval in milliseconds.
 */
function getUpdateInterval( diff ) {
	if ( diff < 3600 ) {
		// Less than 1 hour - update every second for live countdown.
		return 1000;
	}
	if ( diff < 86400 ) {
		return 60000; // Update every minute.
	}
	return 300000; // Update every 5 minutes.
}

/**
 * Update card classes based on state.
 *
 * @param {HTMLElement} cardElement The card element.
 * @param {string}      newState    The new state (upcoming/running/expired).
 * @param {string}      oldState    The previous state.
 */
function updateCardClasses( cardElement, newState, oldState ) {
	if ( ! cardElement || newState === oldState ) {
		return;
	}

	// Remove old state class.
	if ( oldState ) {
		cardElement.classList.remove( `aucteeno-card--${ oldState }` );
	}

	// Add new state class.
	cardElement.classList.add( `aucteeno-card--${ newState }` );
}

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
