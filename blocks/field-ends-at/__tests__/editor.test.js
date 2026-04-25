/**
 * Tests for Aucteeno Field Ends At Block - Editor Component.
 */

jest.mock( '@wordpress/blocks' );
jest.mock( '@wordpress/block-editor' );
jest.mock( '@wordpress/components' );
jest.mock( '@wordpress/i18n' );
jest.mock( '@wordpress/element' );
jest.mock( '../block.json', () => ( { name: 'aucteeno/field-ends-at' } ), { virtual: true } );
jest.mock( '../src/style.css', () => ( {} ), { virtual: true } );

import { render, screen } from '@testing-library/react';

require( '../src/editor' );
const { registerBlockType } = require( '@wordpress/blocks' );
const Edit = registerBlockType.mock.calls[ 0 ][ 1 ].edit;

// Fixed timestamps for deterministic state computation.
// Use values far in the future/past so tests don't depend on wall-clock time.
const FAR_FUTURE = Math.floor( Date.now() / 1000 ) + 86400 * 365; // ~1 year from now
const FAR_PAST   = Math.floor( Date.now() / 1000 ) - 86400 * 365; // ~1 year ago

describe( 'Field Ends At Block Editor', () => {
	it( 'renders without crashing', () => {
		const { container } = render(
			<Edit
				attributes={ {} }
				setAttributes={ () => {} }
				context={ {} }
			/>
		);
		expect( container ).toBeTruthy();
	} );

	it( 'shows "Bidding ends" label by default (upcoming state)', () => {
		render(
			<Edit
				attributes={ {} }
				setAttributes={ () => {} }
				context={ {
					'aucteeno/item': {
						bidding_starts_at: FAR_FUTURE,
						bidding_ends_at: FAR_FUTURE + 86400,
					},
				} }
			/>
		);
		expect( screen.getByText( 'Bidding ends' ) ).toBeTruthy();
	} );

	it( 'hides the label when showLabel is false', () => {
		render(
			<Edit
				attributes={ { showLabel: false } }
				setAttributes={ () => {} }
				context={ {
					'aucteeno/item': {
						bidding_starts_at: FAR_FUTURE,
						bidding_ends_at: FAR_FUTURE + 86400,
					},
				} }
			/>
		);
		expect( screen.queryByText( 'Bidding ends' ) ).toBeNull();
		expect( screen.queryByText( 'Bidding ended' ) ).toBeNull();
	} );

	it( 'shows "Bidding ends" for running state', () => {
		render(
			<Edit
				attributes={ { respectBiddingStatus: true } }
				setAttributes={ () => {} }
				context={ {
					'aucteeno/item': {
						bidding_starts_at: FAR_PAST,
						bidding_ends_at: FAR_FUTURE,
					},
				} }
			/>
		);
		expect( screen.getByText( 'Bidding ends' ) ).toBeTruthy();
	} );

	it( 'shows "Bidding ended" for expired state', () => {
		render(
			<Edit
				attributes={ { respectBiddingStatus: true } }
				setAttributes={ () => {} }
				context={ {
					'aucteeno/item': {
						bidding_starts_at: FAR_PAST - 86400,
						bidding_ends_at: FAR_PAST,
					},
				} }
			/>
		);
		expect( screen.getByText( 'Bidding ended' ) ).toBeTruthy();
	} );

	it( 'shows "Bidding ends" for upcoming state', () => {
		render(
			<Edit
				attributes={ { respectBiddingStatus: true } }
				setAttributes={ () => {} }
				context={ {
					'aucteeno/item': {
						bidding_starts_at: FAR_FUTURE,
						bidding_ends_at: FAR_FUTURE + 86400,
					},
				} }
			/>
		);
		expect( screen.getByText( 'Bidding ends' ) ).toBeTruthy();
	} );

	it( 'uses custom label attribute when respectBiddingStatus is false', () => {
		render(
			<Edit
				attributes={ { respectBiddingStatus: false, label: 'Custom label' } }
				setAttributes={ () => {} }
				context={ {
					'aucteeno/item': {
						bidding_starts_at: FAR_PAST,
						bidding_ends_at: FAR_FUTURE,
					},
				} }
			/>
		);
		expect( screen.getByText( 'Custom label' ) ).toBeTruthy();
	} );

	it( 'treats bidding_ends_at=0 as running (not expired) when past start', () => {
		render(
			<Edit
				attributes={ { respectBiddingStatus: true } }
				setAttributes={ () => {} }
				context={ {
					'aucteeno/item': {
						bidding_starts_at: FAR_PAST,
						bidding_ends_at: 0,
					},
				} }
			/>
		);
		// State should be 'running' (not 'expired') so label is 'Bidding ends'
		expect( screen.getByText( 'Bidding ends' ) ).toBeTruthy();
	} );

	it( 'formats a context timestamp when provided', () => {
		render(
			<Edit
				attributes={ { dateTimeFormat: 'wp_default' } }
				setAttributes={ () => {} }
				context={ {
					'aucteeno/item': { bidding_ends_at: FAR_FUTURE },
				} }
			/>
		);
		const times = document.querySelectorAll( 'time' );
		expect( times.length ).toBeGreaterThan( 0 );
	} );
} );
