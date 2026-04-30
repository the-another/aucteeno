/**
 * Aucteeno Shared Datetime Utilities
 *
 * Pure functions for formatting Unix timestamps in the visitor's local
 * timezone. Shared by the aucteeno/field-starts-at and aucteeno/field-ends-at
 * blocks. Not owned by either block — deleting or renaming one block does
 * not affect the other.
 */

/**
 * Intl.DateTimeFormat option maps matching the PHP `wp_date` format tokens
 * defined in each block's render.php.
 */
const INTL_OPTIONS = {
	long: {
		weekday: 'long',
		day: 'numeric',
		month: 'long',
		year: 'numeric',
		hour: '2-digit',
		minute: '2-digit',
	},
	medium: {
		month: 'short',
		day: 'numeric',
		year: 'numeric',
		hour: 'numeric',
		minute: '2-digit',
	},
	wp_default: {
		dateStyle: 'medium',
		timeStyle: 'short',
	},
};

/**
 * Translate a PHP date() format string into the corresponding local-time
 * output for the given Date. Covers the token set d, j, m, n, Y, y, H, i,
 * A, a. Any other character passes through literally.
 *
 * @param {string} phpFormat PHP date() format string (from the `customFormat` attribute).
 * @param {Date}   date      Date instance to format (already in local TZ via the browser).
 * @return {string} Formatted string in the browser's local timezone.
 */
export function translateCustomFormat( phpFormat, date ) {
	if ( ! phpFormat ) {
		return '';
	}

	const pad2 = ( n ) => String( n ).padStart( 2, '0' );
	const hours24 = date.getHours();
	const isPm = hours24 >= 12;

	const tokens = {
		d: pad2( date.getDate() ),
		j: String( date.getDate() ),
		m: pad2( date.getMonth() + 1 ),
		n: String( date.getMonth() + 1 ),
		Y: String( date.getFullYear() ),
		y: String( date.getFullYear() ).slice( -2 ),
		H: pad2( hours24 ),
		i: pad2( date.getMinutes() ),
		A: isPm ? 'PM' : 'AM',
		a: isPm ? 'pm' : 'am',
	};

	let out = '';
	for ( let i = 0; i < phpFormat.length; i++ ) {
		const ch = phpFormat[ i ];
		if ( ch === '\\' && i + 1 < phpFormat.length ) {
			// Backslash escapes the next character (PHP date() semantics).
			out += phpFormat[ i + 1 ];
			i++;
			continue;
		}
		out += Object.prototype.hasOwnProperty.call( tokens, ch )
			? tokens[ ch ]
			: ch;
	}
	return out;
}

/**
 * Format a Unix timestamp in the visitor's local timezone according to the
 * block's `dateTimeFormat` attribute. Matches the PHP-side `wp_date` output
 * conceptually — exact character-for-character parity is not guaranteed
 * because Intl.DateTimeFormat is locale-aware.
 *
 * @param {number|null} timestamp    Unix timestamp (UTC seconds).
 * @param {string}      format       One of 'long' | 'medium' | 'wp_default' | 'custom'.
 * @param {string}      customFormat PHP date() format string; used when format === 'custom'.
 * @return {string} Local-timezone-formatted string, or '' for falsy timestamps.
 */
export function formatDatetime( timestamp, format, customFormat = '' ) {
	if ( ! timestamp ) {
		return '';
	}

	const date = new Date( timestamp * 1000 );

	if ( 'custom' === format ) {
		return translateCustomFormat( customFormat, date );
	}

	const options = INTL_OPTIONS[ format ] || INTL_OPTIONS.wp_default;
	return new Intl.DateTimeFormat( undefined, options ).format( date );
}
