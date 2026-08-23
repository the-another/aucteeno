/* global MutationObserver, Node */

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
 */

import {
	calculateState,
	formatCountdown,
	getUpdateInterval,
	updateCardClasses,
	applyOverride,
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
	const targetDate = element.dataset.targetDate || 'auto';
	const respectStatus = element.dataset.respectBiddingStatus !== '0';
	const singleLabel = element.dataset.label || 'Bidding ends in';
	const labelUpcomingTime =
		element.dataset.labelUpcomingTime || 'Bidding starts in';
	const labelUpcomingDate =
		element.dataset.labelUpcomingDate || 'Bidding starts on';
	const labelRunningTime =
		element.dataset.labelRunningTime || 'Bidding ends in';
	const labelRunningDate =
		element.dataset.labelRunningDate || 'Bidding ends on';
	const labelExpired = element.dataset.labelExpired || 'Bidding ended';
	const overrideValue = element.dataset.overrideValue || '';
	const overrideFrom = parseInt( element.dataset.overrideFrom, 10 ) || 0;
	const overrideUntil = parseInt( element.dataset.overrideUntil, 10 ) || 0;
	const overrideState = element.dataset.overrideState || '';
	const overrideLabel = element.dataset.overrideLabel || '';
	const overrideSince = parseInt( element.dataset.overrideSince, 10 ) || 0;

	if ( ! startsAt || ! endsAt ) {
		return;
	}

	// Get current UTC timestamp (Date.now() returns milliseconds since Unix epoch in UTC)
	// Calculations are done in UTC to ensure consistency regardless of user's timezone
	const now = Math.floor( Date.now() / 1000 );
	const stateInfo = calculateState( now, startsAt, endsAt );
	const { state } = stateInfo;
	let { timestamp } = stateInfo;
	let effectiveState = state;

	if ( targetDate === 'starts_at' ) {
		timestamp = startsAt;
		effectiveState = now < startsAt ? 'upcoming' : 'expired';
	} else if ( targetDate === 'ends_at' ) {
		timestamp = endsAt;
		effectiveState = now < endsAt ? 'running' : 'expired';
	}

	// Calculate countdown display.
	const diff = timestamp - now;
	const computed = formatCountdown(
		diff,
		timestamp,
		effectiveState,
		dateFormat
	);

	// classList throws InvalidCharacterError on a token containing whitespace,
	// and this runs before the tick is rescheduled — a throw would freeze the
	// countdown permanently. sanitize_html_class() is the only writer today, but
	// it ends in a filter a site can hook, so re-check the token here.
	const safeOverrideState = /^[A-Za-z0-9_-]*$/.test( overrideState )
		? overrideState
		: '';

	// A starts_at pin is an explicit request to count to the start, so an
	// override must not hijack it. An ends_at pin only selects which timestamp
	// to count to — the override's window sits entirely inside that mode's
	// running span, so it applies there just as it does under 'auto'.
	const override =
		targetDate === 'starts_at'
			? null
			: {
					value: overrideValue,
					from: overrideFrom,
					until: overrideUntil,
					state: safeOverrideState,
					label: overrideLabel,
					since: overrideSince,
			  };

	const computedWithContext = { ...computed, state, dateFormat };
	const overrideResult = applyOverride( now, override, computedWithContext );
	// applyOverride() returns the exact object passed in when it rejects the
	// override (out of window, malformed, none supplied) - reference identity
	// is a free, always-correct signal that the override is currently active,
	// without re-deriving the from/until window check a third time here.
	const overrideApplied = overrideResult !== computedWithContext;
	const {
		displayValue,
		isShowingDate,
		state: displayState,
		label: overrideAppliedLabel,
	} = overrideResult;

	// Determine label based on effective state and whether showing date.
	// An override's own label, when supplied, wins outright.
	let label;
	if ( overrideAppliedLabel ) {
		label = overrideAppliedLabel;
	} else if ( ! respectStatus ) {
		label = singleLabel;
	} else if ( effectiveState === 'expired' ) {
		label = labelExpired;
	} else if ( isShowingDate ) {
		label =
			effectiveState === 'upcoming'
				? labelUpcomingDate
				: labelRunningDate;
	} else {
		label =
			effectiveState === 'upcoming'
				? labelUpcomingTime
				: labelRunningTime;
	}

	// Update the countdown value. A static override string does not need
	// rewriting sixty times a minute.
	const valueEl = element.querySelector( '.aucteeno-field-countdown__value' );
	if ( valueEl && valueEl.textContent !== displayValue ) {
		valueEl.textContent = displayValue;
	}

	// Update the label if it changed.
	const labelEl = element.querySelector( '.aucteeno-field-countdown__label' );
	if ( labelEl && labelEl.textContent !== label ) {
		labelEl.textContent = label;
	}

	// Update the modifier class. An override may supply a suffix this file has
	// never heard of, and the override can start or stop without the computed
	// state changing, so match by prefix rather than by a hardcoded list.
	const desiredClass = `aucteeno-field-countdown--${ displayState }`;
	if ( ! element.classList.contains( desiredClass ) ) {
		Array.from( element.classList )
			.filter( ( name ) =>
				name.startsWith( 'aucteeno-field-countdown--' )
			)
			.forEach( ( name ) => element.classList.remove( name ) );
		element.classList.add( desiredClass );
	}

	// Card classes follow the computed state only — an override never reaches
	// them, so a card keeps the class it was rendered with.
	if ( state !== previousState ) {
		element.dataset.currentState = state;

		// Find and update parent card element.
		const cardElement = element.closest( '.aucteeno-card' );
		if ( cardElement ) {
			updateCardClasses( cardElement, state, previousState );
		}
	}

	// Schedule next update.
	// For expired items, continue updating if less than 1 week ago - unless
	// an override is currently active, which must keep ticking regardless
	// (e.g. a `since` counter opened on an item expired more than a week
	// would otherwise freeze the moment this function next runs).
	const shouldContinue =
		state !== 'expired' || Math.abs( diff ) < 604800 || overrideApplied;
	if ( shouldContinue ) {
		// When `since` is actively driving the displayed value, the tick
		// interval must track that elapsed value instead of the countdown
		// diff, or a freshly-started elapsed counter won't tick every second.
		const intervalBasis =
			typeof overrideResult.elapsed === 'number'
				? overrideResult.elapsed
				: Math.abs( diff );
		let interval = getUpdateInterval( intervalBasis );

		// An override's own window can open or close sooner than the next
		// scheduled tick would otherwise land - clamp to the nearest
		// upcoming boundary so the value/class don't linger past `until`
		// (or fail to apply right at `from`/`since`) for as long as
		// getUpdateInterval() would otherwise allow.
		if ( override ) {
			const boundaries = [ override.from, override.until, override.since ]
				.filter( ( t ) => t > now )
				.map( ( t ) => ( t - now ) * 1000 );
			if ( boundaries.length > 0 ) {
				interval = Math.min( interval, ...boundaries );
			}
		}

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
