/**
 * Tests for Aucteeno Field Reserve Price Block - Editor Component.
 */

jest.mock( '@wordpress/blocks' );
jest.mock( '@wordpress/block-editor' );
jest.mock( '@wordpress/components' );
jest.mock( '@wordpress/i18n' );
jest.mock( '@wordpress/element' );
jest.mock( '../block.json', () => ( { name: 'aucteeno/field-reserve-price' } ), { virtual: true } );
jest.mock( '../src/style.css', () => ( {} ), { virtual: true } );

import { render, screen } from '@testing-library/react';

require( '../src/editor' );
const { registerBlockType } = require( '@wordpress/blocks' );
const Edit = registerBlockType.mock.calls[ 0 ][ 1 ].edit;

describe( 'Field Reserve Price Block Editor', () => {
	const defaultProps = {
		attributes: {},
		setAttributes: jest.fn(),
		context: {},
	};

	it( 'renders without crashing', () => {
		const { container } = render( <Edit { ...defaultProps } /> );
		expect( container ).toBeTruthy();
	} );

	it( 'shows default reserve price "$500.00"', () => {
		render( <Edit { ...defaultProps } /> );
		expect( screen.getByText( '$500.00' ) ).toBeTruthy();
	} );

	it( 'shows "Reserve" label by default', () => {
		render( <Edit { ...defaultProps } /> );
		expect( screen.getByText( 'Reserve' ) ).toBeTruthy();
	} );
} );
