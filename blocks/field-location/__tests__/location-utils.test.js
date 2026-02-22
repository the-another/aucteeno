/**
 * Tests for location utility functions.
 */
import {
	COUNTRY_NAMES,
	US_STATES,
	getSubdivisionName,
	formatSmartLocation,
	formatLocation,
} from '../src/location-utils';

describe( 'getSubdivisionName', () => {
	test( 'returns US state name for valid state code', () => {
		expect( getSubdivisionName( 'US', 'NY' ) ).toBe( 'New York' );
	} );

	test( 'returns US state name for California', () => {
		expect( getSubdivisionName( 'US', 'CA' ) ).toBe( 'California' );
	} );

	test( 'returns code for non-US country subdivision', () => {
		expect( getSubdivisionName( 'CA', 'ON' ) ).toBe( 'ON' );
	} );

	test( 'parses COUNTRY:SUBDIVISION format', () => {
		expect( getSubdivisionName( 'US', 'US:TX' ) ).toBe( 'Texas' );
	} );

	test( 'parses non-US COUNTRY:SUBDIVISION format', () => {
		expect( getSubdivisionName( 'CA', 'CA:ON' ) ).toBe( 'ON' );
	} );

	test( 'returns empty string for empty subdivisionCode', () => {
		expect( getSubdivisionName( 'US', '' ) ).toBe( '' );
	} );

	test( 'returns empty string for null subdivisionCode', () => {
		expect( getSubdivisionName( 'US', null ) ).toBe( '' );
	} );

	test( 'returns empty string for undefined subdivisionCode', () => {
		expect( getSubdivisionName( 'US', undefined ) ).toBe( '' );
	} );
} );

describe( 'formatSmartLocation', () => {
	test( 'city + US state + country', () => {
		expect( formatSmartLocation( 'Austin', 'TX', 'US' ) ).toBe( 'Austin, Texas, US' );
	} );

	test( 'city + country (no subdivision)', () => {
		expect( formatSmartLocation( 'London', '', 'GB' ) ).toBe( 'London, United Kingdom' );
	} );

	test( 'city + non-US subdivision + country', () => {
		expect( formatSmartLocation( 'Toronto', 'ON', 'CA' ) ).toBe( 'Toronto, ON, CA' );
	} );

	test( 'city only with country code (no known country name)', () => {
		expect( formatSmartLocation( 'Abuja', '', 'NG' ) ).toBe( 'Abuja, NG' );
	} );

	test( 'no city, with subdivision and country', () => {
		expect( formatSmartLocation( '', 'NY', 'US' ) ).toBe( 'New York, US' );
	} );

	test( 'handles COUNTRY:SUBDIVISION format in subdivision param', () => {
		expect( formatSmartLocation( 'Dallas', 'US:TX', 'US' ) ).toBe( 'Dallas, Texas, US' );
	} );

	test( 'all empty values', () => {
		expect( formatSmartLocation( '', '', '' ) ).toBe( '' );
	} );
} );

describe( 'formatLocation', () => {
	test( 'smart format delegates to formatSmartLocation', () => {
		expect( formatLocation( 'smart', 'Boston', 'MA', 'US' ) ).toBe( 'Boston, Massachusetts, US' );
	} );

	test( 'city_only returns just the city', () => {
		expect( formatLocation( 'city_only', 'Boston', 'MA', 'US' ) ).toBe( 'Boston' );
	} );

	test( 'city_only returns empty string when no city', () => {
		expect( formatLocation( 'city_only', '', 'MA', 'US' ) ).toBe( '' );
	} );

	test( 'country_only returns full country name', () => {
		expect( formatLocation( 'country_only', 'Boston', 'MA', 'US' ) ).toBe( 'United States' );
	} );

	test( 'country_only falls back to country code for unknown countries', () => {
		expect( formatLocation( 'country_only', '', '', 'ZW' ) ).toBe( 'ZW' );
	} );

	test( 'city_subdivision returns city and state', () => {
		expect( formatLocation( 'city_subdivision', 'Dallas', 'TX', 'US' ) ).toBe( 'Dallas, Texas' );
	} );

	test( 'city_subdivision with no city', () => {
		expect( formatLocation( 'city_subdivision', '', 'TX', 'US' ) ).toBe( 'Texas' );
	} );

	test( 'city_country returns city and country code', () => {
		expect( formatLocation( 'city_country', 'Toronto', 'ON', 'CA' ) ).toBe( 'Toronto, CA' );
	} );

	test( 'city_country with no city', () => {
		expect( formatLocation( 'city_country', '', '', 'US' ) ).toBe( 'US' );
	} );

	test( 'unknown format defaults to smart', () => {
		expect( formatLocation( 'unknown_format', 'Boston', 'MA', 'US' ) ).toBe( 'Boston, Massachusetts, US' );
	} );
} );

describe( 'COUNTRY_NAMES', () => {
	test( 'contains expected countries', () => {
		expect( COUNTRY_NAMES.US ).toBe( 'United States' );
		expect( COUNTRY_NAMES.CA ).toBe( 'Canada' );
		expect( COUNTRY_NAMES.GB ).toBe( 'United Kingdom' );
	} );

	test( 'has 20 countries', () => {
		expect( Object.keys( COUNTRY_NAMES ).length ).toBe( 20 );
	} );
} );

describe( 'US_STATES', () => {
	test( 'contains all 50 states plus DC', () => {
		expect( Object.keys( US_STATES ).length ).toBe( 51 );
	} );

	test( 'contains expected states', () => {
		expect( US_STATES.NY ).toBe( 'New York' );
		expect( US_STATES.CA ).toBe( 'California' );
		expect( US_STATES.TX ).toBe( 'Texas' );
		expect( US_STATES.DC ).toBe( 'District of Columbia' );
	} );
} );
