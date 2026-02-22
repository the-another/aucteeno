/**
 * Aucteeno Field Countdown Block - Pure Utility Functions
 *
 * Extracted from view.js for testability and reuse.
 */

/**
 * Format a date based on the selected format.
 *
 * The timestamp is in UTC (Unix timestamp). We convert it to the user's local timezone for display.
 *
 * @param {number} timestamp  Unix timestamp in seconds (UTC-based).
 * @param {string} dateFormat Date format setting.
 * @return {string} Formatted date string in user's local timezone.
 */
export function formatDate( timestamp, dateFormat ) {
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
		case 'full': {
			const dayOfWeek = date.getDay();
			return `${ days[ dayOfWeek ] }, ${ months[ month ] } ${
				day + getOrdinalSuffix( day )
			} ${ year }`;
		}
		case 'default':
		default:
			return date.toLocaleDateString();
	}
}

/**
 * Calculate current state based on start and end times.
 * All timestamps are in UTC (Unix timestamps), so comparisons are timezone-agnostic.
 *
 * @param {number} now      Current UTC timestamp in seconds.
 * @param {number} startsAt Start UTC timestamp in seconds.
 * @param {number} endsAt   End UTC timestamp in seconds.
 * @return {Object} State object with state and timestamp.
 */
export function calculateState( now, startsAt, endsAt ) {
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
export function formatCountdown( diff, timestamp, state, dateFormat ) {
	// For expired items, show elapsed time.
	if ( state === 'expired' ) {
		const elapsed = Math.abs( diff );

		if ( elapsed < 3600 ) {
			// Less than 1 hour ago - show minutes and seconds elapsed.
			const minutes = Math.floor( elapsed / 60 );
			const seconds = Math.floor( elapsed % 60 );
			const parts = [];

			if ( minutes > 0 ) {
				parts.push(
					`${ minutes } ${ minutes === 1 ? 'minute' : 'minutes' }`
				);
			}

			parts.push(
				`${ seconds } ${ seconds === 1 ? 'second' : 'seconds' }`
			);

			return {
				displayValue: `${ parts.join( ' ' ) } ago`,
				isShowingDate: false,
			};
		}
		if ( elapsed < 86400 ) {
			// Less than 1 day ago - show hours elapsed.
			const hours = Math.floor( elapsed / 3600 );
			return {
				displayValue: `${ hours } ${
					hours === 1 ? 'hour' : 'hours'
				} ago`,
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
			parts.push(
				`${ minutes } ${ minutes === 1 ? 'minute' : 'minutes' }`
			);
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
export function getUpdateInterval( diff ) {
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
export function updateCardClasses( cardElement, newState, oldState ) {
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
