/**
 * Tests for Aucteeno Field Countdown Block - Editor Component.
 */

jest.mock( '@wordpress/blocks' );
jest.mock( '@wordpress/block-editor' );
jest.mock( '@wordpress/components' );
jest.mock( '@wordpress/i18n' );
jest.mock( '@wordpress/element' );
jest.mock( '../block.json', () => ( { name: 'aucteeno/field-countdown' } ), { virtual: true } );
jest.mock( '../src/style.css', () => ( {} ), { virtual: true } );

import { render, screen } from '@testing-library/react';

require( '../src/editor' );
const { registerBlockType } = require( '@wordpress/blocks' );
const Edit = registerBlockType.mock.calls[ 0 ][ 1 ].edit;

describe( 'Field Countdown Block Editor', () => {
	const defaultProps = {
		attributes: {},
		setAttributes: jest.fn(),
		context: {},
	};

	it( 'renders without crashing', () => {
		const { container } = render( <Edit { ...defaultProps } /> );
		expect( container ).toBeTruthy();
	} );

	it( 'shows countdown label by default', () => {
		render( <Edit { ...defaultProps } /> );
		expect( screen.getByText( 'Bidding ends in' ) ).toBeTruthy();
	} );

	it( 'shows "Bidding starts in" for upcoming status', () => {
		const props = {
			...defaultProps,
			context: {
				'aucteeno/item': {
					bidding_status: 20,
					bidding_starts_at: Math.floor( Date.now() / 1000 ) + 86400,
					bidding_ends_at: Math.floor( Date.now() / 1000 ) + 172800,
				},
			},
		};
		render( <Edit { ...props } /> );
		expect( screen.getByText( 'Bidding starts in' ) ).toBeTruthy();
	} );

	it( 'shows "Bidding ended" for expired status', () => {
		const props = {
			...defaultProps,
			context: {
				'aucteeno/item': {
					bidding_status: 30,
					bidding_starts_at: Math.floor( Date.now() / 1000 ) - 172800,
					bidding_ends_at: Math.floor( Date.now() / 1000 ) - 86400,
				},
			},
		};
		render( <Edit { ...props } /> );
		expect( screen.getByText( 'Bidding ended' ) ).toBeTruthy();
	} );
} );
