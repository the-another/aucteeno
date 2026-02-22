/**
 * Tests for Aucteeno Field Current Bid Block - Editor Component.
 */

jest.mock( '@wordpress/blocks' );
jest.mock( '@wordpress/block-editor' );
jest.mock( '@wordpress/components' );
jest.mock( '@wordpress/i18n' );
jest.mock( '@wordpress/element' );
jest.mock( '../block.json', () => ( { name: 'aucteeno/field-current-bid' } ), { virtual: true } );
jest.mock( '../src/style.css', () => ( {} ), { virtual: true } );

import { render, screen } from '@testing-library/react';

require( '../src/editor' );
const { registerBlockType } = require( '@wordpress/blocks' );
const Edit = registerBlockType.mock.calls[ 0 ][ 1 ].edit;

describe( 'Field Current Bid Block Editor', () => {
	const defaultProps = {
		attributes: {},
		setAttributes: jest.fn(),
		context: {},
	};

	it( 'renders without crashing', () => {
		const { container } = render( <Edit { ...defaultProps } /> );
		expect( container ).toBeTruthy();
	} );

	it( 'shows default bid "$0.00" when no context', () => {
		render( <Edit { ...defaultProps } /> );
		expect( screen.getByText( '$0.00' ) ).toBeTruthy();
	} );

	it( 'shows bid from context', () => {
		const props = {
			...defaultProps,
			context: { 'aucteeno/item': { current_bid: 150 } },
		};
		render( <Edit { ...props } /> );
		expect( screen.getByText( '$150.00' ) ).toBeTruthy();
	} );

	it( 'shows label "Current Bid" by default', () => {
		render( <Edit { ...defaultProps } /> );
		expect( screen.getByText( 'Current Bid' ) ).toBeTruthy();
	} );
} );
