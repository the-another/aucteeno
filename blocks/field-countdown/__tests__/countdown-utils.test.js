/**
 * Tests for countdown utility functions.
 */
import {
	formatDate,
	calculateState,
	formatCountdown,
	getUpdateInterval,
	updateCardClasses,
	applyOverride,
} from '../src/countdown-utils';

// Use a fixed UTC timestamp for deterministic date tests: 2026-01-17 12:00:00 UTC
const FIXED_TIMESTAMP = 1768996800;

describe( 'formatDate', () => {
	test( 'mdy format returns MM/DD/YYYY', () => {
		const result = formatDate( FIXED_TIMESTAMP, 'mdy' );
		// Date in local timezone - check format pattern
		expect( result ).toMatch( /^\d{2}\/\d{2}\/\d{4}$/ );
	} );

	test( 'dmy format returns DD/MM/YYYY', () => {
		const result = formatDate( FIXED_TIMESTAMP, 'dmy' );
		expect( result ).toMatch( /^\d{2}\/\d{2}\/\d{4}$/ );
	} );

	test( 'ymd format returns YYYY-MM-DD', () => {
		const result = formatDate( FIXED_TIMESTAMP, 'ymd' );
		expect( result ).toMatch( /^\d{4}-\d{2}-\d{2}$/ );
	} );

	test( 'long format returns "Month D, YYYY"', () => {
		const result = formatDate( FIXED_TIMESTAMP, 'long' );
		expect( result ).toMatch( /^[A-Z][a-z]+ \d{1,2}, \d{4}$/ );
	} );

	test( 'long_eu format returns "D Month YYYY"', () => {
		const result = formatDate( FIXED_TIMESTAMP, 'long_eu' );
		expect( result ).toMatch( /^\d{1,2} [A-Z][a-z]+ \d{4}$/ );
	} );

	test( 'full format returns "Day, Month Dth YYYY"', () => {
		const result = formatDate( FIXED_TIMESTAMP, 'full' );
		expect( result ).toMatch( /^[A-Z][a-z]+, [A-Z][a-z]+ \d{1,2}(st|nd|rd|th) \d{4}$/ );
	} );

	test( 'default format returns toLocaleDateString output', () => {
		const result = formatDate( FIXED_TIMESTAMP, 'default' );
		// toLocaleDateString returns a non-empty string
		expect( result.length ).toBeGreaterThan( 0 );
	} );
} );

describe( 'calculateState', () => {
	const startsAt = 1000;
	const endsAt = 2000;

	test( 'returns upcoming when now < startsAt', () => {
		const result = calculateState( 500, startsAt, endsAt );
		expect( result ).toEqual( { state: 'upcoming', timestamp: startsAt } );
	} );

	test( 'returns running when now >= startsAt and now < endsAt', () => {
		const result = calculateState( 1000, startsAt, endsAt );
		expect( result ).toEqual( { state: 'running', timestamp: endsAt } );
	} );

	test( 'returns running at midpoint', () => {
		const result = calculateState( 1500, startsAt, endsAt );
		expect( result ).toEqual( { state: 'running', timestamp: endsAt } );
	} );

	test( 'returns expired when now >= endsAt', () => {
		const result = calculateState( 2000, startsAt, endsAt );
		expect( result ).toEqual( { state: 'expired', timestamp: endsAt } );
	} );

	test( 'returns expired when now is well past endsAt', () => {
		const result = calculateState( 5000, startsAt, endsAt );
		expect( result ).toEqual( { state: 'expired', timestamp: endsAt } );
	} );
} );

describe( 'formatCountdown', () => {
	describe( 'running state', () => {
		test( 'shows seconds when diff < 60', () => {
			const result = formatCountdown( 45, 0, 'running', 'default' );
			expect( result.displayValue ).toBe( '45 seconds' );
			expect( result.isShowingDate ).toBe( false );
		} );

		test( 'shows singular second', () => {
			const result = formatCountdown( 1, 0, 'running', 'default' );
			expect( result.displayValue ).toBe( '1 second' );
		} );

		test( 'shows minutes and seconds when diff < 3600', () => {
			const result = formatCountdown( 125, 0, 'running', 'default' );
			expect( result.displayValue ).toBe( '2 minutes 5 seconds' );
			expect( result.isShowingDate ).toBe( false );
		} );

		test( 'shows singular minute', () => {
			const result = formatCountdown( 90, 0, 'running', 'default' );
			expect( result.displayValue ).toBe( '1 minute 30 seconds' );
		} );

		test( 'shows hours when diff < 86400', () => {
			const result = formatCountdown( 7200, 0, 'running', 'default' );
			expect( result.displayValue ).toBe( '2 hours' );
			expect( result.isShowingDate ).toBe( false );
		} );

		test( 'shows singular hour', () => {
			const result = formatCountdown( 3600, 0, 'running', 'default' );
			expect( result.displayValue ).toBe( '1 hour' );
		} );

		test( 'shows days when diff < 604800', () => {
			const result = formatCountdown( 172800, 0, 'running', 'default' );
			expect( result.displayValue ).toBe( '2 days' );
			expect( result.isShowingDate ).toBe( false );
		} );

		test( 'shows singular day', () => {
			const result = formatCountdown( 86400, 0, 'running', 'default' );
			expect( result.displayValue ).toBe( '1 day' );
		} );

		test( 'shows date when diff >= 604800 (1 week)', () => {
			const result = formatCountdown( 700000, FIXED_TIMESTAMP, 'running', 'long' );
			expect( result.isShowingDate ).toBe( true );
			expect( result.displayValue ).toMatch( /[A-Z]/ ); // Contains a date string
		} );

		test( 'shows date when diff <= 0 (edge case)', () => {
			const result = formatCountdown( 0, FIXED_TIMESTAMP, 'running', 'default' );
			expect( result.isShowingDate ).toBe( true );
		} );
	} );

	describe( 'expired state', () => {
		test( 'shows elapsed seconds when < 1 hour ago', () => {
			const result = formatCountdown( -120, 0, 'expired', 'default' );
			expect( result.displayValue ).toBe( '2 minutes 0 seconds ago' );
			expect( result.isShowingDate ).toBe( false );
		} );

		test( 'shows elapsed hours when < 1 day ago', () => {
			const result = formatCountdown( -7200, 0, 'expired', 'default' );
			expect( result.displayValue ).toBe( '2 hours ago' );
			expect( result.isShowingDate ).toBe( false );
		} );

		test( 'shows singular hour ago', () => {
			const result = formatCountdown( -3600, 0, 'expired', 'default' );
			expect( result.displayValue ).toBe( '1 hour ago' );
		} );

		test( 'shows elapsed days when < 1 week ago', () => {
			const result = formatCountdown( -259200, 0, 'expired', 'default' );
			expect( result.displayValue ).toBe( '3 days ago' );
			expect( result.isShowingDate ).toBe( false );
		} );

		test( 'shows date when > 1 week ago', () => {
			const result = formatCountdown( -700000, FIXED_TIMESTAMP, 'expired', 'long' );
			expect( result.isShowingDate ).toBe( true );
		} );
	} );
} );

describe( 'getUpdateInterval', () => {
	test( 'returns 1000ms when diff < 3600 (< 1 hour)', () => {
		expect( getUpdateInterval( 500 ) ).toBe( 1000 );
	} );

	test( 'returns 60000ms when diff < 86400 (< 1 day)', () => {
		expect( getUpdateInterval( 5000 ) ).toBe( 60000 );
	} );

	test( 'returns 300000ms when diff >= 86400 (>= 1 day)', () => {
		expect( getUpdateInterval( 100000 ) ).toBe( 300000 );
	} );

	test( 'boundary: 3599 returns 1000ms', () => {
		expect( getUpdateInterval( 3599 ) ).toBe( 1000 );
	} );

	test( 'boundary: 3600 returns 60000ms', () => {
		expect( getUpdateInterval( 3600 ) ).toBe( 60000 );
	} );
} );

describe( 'updateCardClasses', () => {
	let element;

	beforeEach( () => {
		element = document.createElement( 'div' );
	} );

	test( 'adds new state class', () => {
		updateCardClasses( element, 'running', '' );
		expect( element.classList.contains( 'aucteeno-card--running' ) ).toBe( true );
	} );

	test( 'removes old state class and adds new one', () => {
		element.classList.add( 'aucteeno-card--upcoming' );
		updateCardClasses( element, 'running', 'upcoming' );
		expect( element.classList.contains( 'aucteeno-card--upcoming' ) ).toBe( false );
		expect( element.classList.contains( 'aucteeno-card--running' ) ).toBe( true );
	} );

	test( 'does nothing when newState === oldState', () => {
		element.classList.add( 'aucteeno-card--running' );
		updateCardClasses( element, 'running', 'running' );
		expect( element.classList.contains( 'aucteeno-card--running' ) ).toBe( true );
	} );

	test( 'does nothing when cardElement is null', () => {
		expect( () => updateCardClasses( null, 'running', 'upcoming' ) ).not.toThrow();
	} );
} );

describe( 'applyOverride', () => {
	const computed = {
		displayValue: '2 hours',
		isShowingDate: false,
		state: 'running',
	};
	const valid = { value: 'Closing', from: 1000, until: 2000, state: 'closing' };

	describe( 'rejects malformed overrides', () => {
		test( 'null override returns computed unchanged', () => {
			expect( applyOverride( 1500, null, computed ) ).toBe( computed );
		} );

		test( 'empty object returns computed unchanged', () => {
			expect( applyOverride( 1500, {}, computed ) ).toBe( computed );
		} );

		test( 'missing value returns computed unchanged', () => {
			const o = { from: 1000, until: 2000 };
			expect( applyOverride( 1500, o, computed ) ).toBe( computed );
		} );

		test( 'empty-string value returns computed unchanged', () => {
			const o = { ...valid, value: '' };
			expect( applyOverride( 1500, o, computed ) ).toBe( computed );
		} );

		test( 'non-string value returns computed unchanged', () => {
			const o = { ...valid, value: 42 };
			expect( applyOverride( 1500, o, computed ) ).toBe( computed );
		} );

		test( 'zero from returns computed unchanged', () => {
			const o = { ...valid, from: 0 };
			expect( applyOverride( 1500, o, computed ) ).toBe( computed );
		} );

		test( 'zero until returns computed unchanged', () => {
			const o = { ...valid, until: 0 };
			expect( applyOverride( 1500, o, computed ) ).toBe( computed );
		} );

		test( 'inverted window returns computed unchanged', () => {
			const o = { ...valid, from: 2000, until: 1000 };
			expect( applyOverride( 1500, o, computed ) ).toBe( computed );
		} );

		test( 'zero-length window returns computed unchanged', () => {
			const o = { ...valid, from: 1000, until: 1000 };
			expect( applyOverride( 1500, o, computed ) ).toBe( computed );
		} );
	} );

	describe( 'respects the window', () => {
		test( 'before the window returns computed unchanged', () => {
			expect( applyOverride( 999, valid, computed ) ).toBe( computed );
		} );

		test( 'exactly at from applies the override', () => {
			expect( applyOverride( 1000, valid, computed ).displayValue ).toBe(
				'Closing'
			);
		} );

		test( 'inside the window applies the override', () => {
			expect( applyOverride( 1500, valid, computed ).displayValue ).toBe(
				'Closing'
			);
		} );

		test( 'exactly at until returns computed unchanged', () => {
			expect( applyOverride( 2000, valid, computed ) ).toBe( computed );
		} );

		test( 'after the window returns computed unchanged', () => {
			expect( applyOverride( 5000, valid, computed ) ).toBe( computed );
		} );
	} );

	describe( 'when applied', () => {
		test( 'forces isShowingDate false', () => {
			const showingDate = { ...computed, isShowingDate: true };
			expect(
				applyOverride( 1500, valid, showingDate ).isShowingDate
			).toBe( false );
		} );

		test( 'uses the override state when given', () => {
			expect( applyOverride( 1500, valid, computed ).state ).toBe(
				'closing'
			);
		} );

		test( 'keeps the computed state when no override state is given', () => {
			const o = { value: 'Closing', from: 1000, until: 2000 };
			expect( applyOverride( 1500, o, computed ).state ).toBe( 'running' );
		} );

		test( 'does not mutate the computed object', () => {
			applyOverride( 1500, valid, computed );
			expect( computed.displayValue ).toBe( '2 hours' );
			expect( computed.state ).toBe( 'running' );
		} );
	} );
} );
