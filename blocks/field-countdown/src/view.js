/**
 * Aucteeno Field Countdown Block - Frontend Script
 *
 * Live countdown timer with smart scaling.
 *
 * @package Aucteeno
 */

/**
 * Format countdown display with smart scaling.
 *
 * @param {number} diff      Difference in seconds.
 * @param {number} timestamp Unix timestamp.
 * @param {number} status    Bidding status (10=running, 20=upcoming, 30=expired).
 * @return {string} Formatted display value.
 */
function formatCountdown( diff, timestamp, status ) {
	// Expired items show the end date.
	if ( status === 30 || diff <= 0 ) {
		return new Date( timestamp * 1000 ).toLocaleDateString();
	}
	if ( diff < 60 ) {
		// Less than 1 minute - show seconds.
		return `${ Math.max( 0, Math.floor( diff ) ) }s`;
	}
	if ( diff < 3600 ) {
		// Less than 1 hour - show minutes.
		return `${ Math.floor( diff / 60 ) }m`;
	}
	if ( diff < 86400 ) {
		// Less than 1 day - show hours.
		return `${ Math.floor( diff / 3600 ) }h`;
	}
	if ( diff < 604800 ) {
		// Less than 1 week - show days.
		return `${ Math.floor( diff / 86400 ) }d`;
	}
	// More than 1 week - show date in browser's local timezone.
	return new Date( timestamp * 1000 ).toLocaleDateString();
}

/**
 * Get update interval based on time remaining.
 *
 * @param {number} diff Difference in seconds.
 * @return {number} Interval in milliseconds.
 */
function getUpdateInterval( diff ) {
	if ( diff < 60 ) {
		return 1000; // Update every second.
	}
	if ( diff < 3600 ) {
		return 10000; // Update every 10 seconds.
	}
	if ( diff < 86400 ) {
		return 60000; // Update every minute.
	}
	return 300000; // Update every 5 minutes.
}

/**
 * Update a single countdown element.
 *
 * @param {HTMLElement} element The countdown element.
 */
function updateCountdown( element ) {
	const timestamp = parseInt( element.dataset.timestamp, 10 );
	const status = parseInt( element.dataset.status, 10 ) || 0;
	if ( ! timestamp ) {
		return;
	}

	const now = Math.floor( Date.now() / 1000 );
	const diff = timestamp - now;
	const displayValue = formatCountdown( diff, timestamp, status );

	const valueEl = element.querySelector( '.aucteeno-field-countdown__value' );
	if ( valueEl ) {
		valueEl.textContent = displayValue;
	}

	// Update status class if ended.
	if ( diff <= 0 && ! element.classList.contains( 'aucteeno-field-countdown--expired' ) ) {
		element.classList.remove( 'aucteeno-field-countdown--running', 'aucteeno-field-countdown--upcoming' );
		element.classList.add( 'aucteeno-field-countdown--expired' );
	}

	// Schedule next update if not ended and not expired.
	if ( diff > 0 && status !== 30 ) {
		const interval = getUpdateInterval( diff );
		setTimeout( () => updateCountdown( element ), interval );
	}
}

/**
 * Initialize all countdown elements on the page.
 */
function initCountdowns() {
	const elements = document.querySelectorAll( '[data-aucteeno-countdown]' );
	elements.forEach( ( element ) => {
		updateCountdown( element );
	} );
}

// Initialize on DOM ready.
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initCountdowns );
} else {
	initCountdowns();
}
