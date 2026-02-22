/**
 * Tests for Aucteeno Field Bidding Status Block - Editor Component.
 */

jest.mock( '@wordpress/blocks' );
jest.mock( '@wordpress/block-editor' );
jest.mock( '@wordpress/components' );
jest.mock( '@wordpress/i18n' );
jest.mock( '@wordpress/element' );
jest.mock( '../block.json', () => ( { name: 'aucteeno/field-bidding-status' } ), { virtual: true } );
jest.mock( '../src/style.css', () => ( {} ), { virtual: true } );

import { render, screen } from '@testing-library/react';

require( '../src/editor' );
const { registerBlockType } = require( '@wordpress/blocks' );
const Edit = registerBlockType.mock.calls[ 0 ][ 1 ].edit;

describe( 'Field Bidding Status Block Editor', () => {
	const defaultProps = {
		context: {},
	};

	it( 'renders without crashing', () => {
		const { container } = render( <Edit { ...defaultProps } /> );
		expect( container ).toBeTruthy();
	} );

	it( 'shows "Running" by default (status 10)', () => {
		render( <Edit { ...defaultProps } /> );
		expect( screen.getByText( 'Running' ) ).toBeTruthy();
	} );

	it( 'shows "Upcoming" for status 20', () => {
		const props = {
			context: { 'aucteeno/item': { bidding_status: 20 } },
		};
		render( <Edit { ...props } /> );
		expect( screen.getByText( 'Upcoming' ) ).toBeTruthy();
	} );

	it( 'shows "Expired" for status 30', () => {
		const props = {
			context: { 'aucteeno/item': { bidding_status: 30 } },
		};
		render( <Edit { ...props } /> );
		expect( screen.getByText( 'Expired' ) ).toBeTruthy();
	} );
} );
