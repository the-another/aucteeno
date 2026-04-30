/**
 * Tests for shared datetime utilities.
 */
import {
	formatDatetime,
	translateCustomFormat,
} from '../src/datetime-utils';

// Fixed UTC timestamp: 2026-01-21 12:00:00 UTC
const FIXED_TS = 1768996800;

describe( 'formatDatetime', () => {
	test( 'long format returns a non-empty string', () => {
		const out = formatDatetime( FIXED_TS, 'long' );
		expect( typeof out ).toBe( 'string' );
		expect( out.length ).toBeGreaterThan( 0 );
	} );

	test( 'medium format returns a non-empty string', () => {
		const out = formatDatetime( FIXED_TS, 'medium' );
		expect( out.length ).toBeGreaterThan( 0 );
	} );

	test( 'wp_default format returns a non-empty string', () => {
		const out = formatDatetime( FIXED_TS, 'wp_default' );
		expect( out.length ).toBeGreaterThan( 0 );
	} );

	test( 'unknown format falls back to wp_default', () => {
		const fallback = formatDatetime( FIXED_TS, 'bogus' );
		const wp = formatDatetime( FIXED_TS, 'wp_default' );
		expect( fallback ).toBe( wp );
	} );

	test( 'custom format delegates to translateCustomFormat', () => {
		const out = formatDatetime( FIXED_TS, 'custom', 'Y-m-d' );
		expect( out ).toMatch( /^\d{4}-\d{2}-\d{2}$/ );
	} );

	test( 'missing or zero timestamp returns empty string', () => {
		expect( formatDatetime( 0, 'long' ) ).toBe( '' );
		expect( formatDatetime( null, 'long' ) ).toBe( '' );
	} );
} );

describe( 'translateCustomFormat', () => {
	// Use UTC-agnostic assertion: build the Date from a fixed timestamp
	// but check only pattern shape (local timezone may vary in CI).
	const date = new Date( FIXED_TS * 1000 );

	test( 'Y token returns 4-digit year', () => {
		expect( translateCustomFormat( 'Y', date ) ).toMatch( /^\d{4}$/ );
	} );

	test( 'y token returns 2-digit year', () => {
		expect( translateCustomFormat( 'y', date ) ).toMatch( /^\d{2}$/ );
	} );

	test( 'm token returns 2-digit month', () => {
		expect( translateCustomFormat( 'm', date ) ).toMatch( /^\d{2}$/ );
	} );

	test( 'n token returns 1-or-2-digit month', () => {
		expect( translateCustomFormat( 'n', date ) ).toMatch( /^\d{1,2}$/ );
	} );

	test( 'd token returns 2-digit day', () => {
		expect( translateCustomFormat( 'd', date ) ).toMatch( /^\d{2}$/ );
	} );

	test( 'j token returns 1-or-2-digit day', () => {
		expect( translateCustomFormat( 'j', date ) ).toMatch( /^\d{1,2}$/ );
	} );

	test( 'H token returns 2-digit 24-hour hour', () => {
		expect( translateCustomFormat( 'H', date ) ).toMatch( /^\d{2}$/ );
	} );

	test( 'i token returns 2-digit minutes', () => {
		expect( translateCustomFormat( 'i', date ) ).toMatch( /^\d{2}$/ );
	} );

	test( 'A token returns AM or PM', () => {
		expect( [ 'AM', 'PM' ] ).toContain( translateCustomFormat( 'A', date ) );
	} );

	test( 'a token returns am or pm', () => {
		expect( [ 'am', 'pm' ] ).toContain( translateCustomFormat( 'a', date ) );
	} );

	test( 'combines tokens and literal text', () => {
		const out = translateCustomFormat( 'Y-m-d H:i', date );
		expect( out ).toMatch( /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/ );
	} );

	test( 'backslash escapes next character literally', () => {
		// \\Y should produce literal 'Y', not the 4-digit year
		expect( translateCustomFormat( '\\Y', date ) ).toBe( 'Y' );
	} );
} );
