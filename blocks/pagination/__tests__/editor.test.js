/**
 * Tests for Aucteeno Pagination Block - Editor Component.
 */

jest.mock( '@wordpress/blocks' );
jest.mock( '@wordpress/block-editor' );
jest.mock( '@wordpress/components' );
jest.mock( '@wordpress/i18n' );
jest.mock( '@wordpress/element' );
jest.mock( '../block.json', () => ( { name: 'aucteeno/pagination' } ), { virtual: true } );

import { render, screen } from '@testing-library/react';

require( '../src/editor' );
const { registerBlockType } = require( '@wordpress/blocks' );
const Edit = registerBlockType.mock.calls[ 0 ][ 1 ].edit;

describe( 'Pagination Block Editor', () => {
	const defaultProps = {
		attributes: {},
		setAttributes: jest.fn(),
		context: {},
	};

	it( 'renders without crashing', () => {
		const { container } = render( <Edit { ...defaultProps } /> );
		expect( container ).toBeTruthy();
	} );

	it( 'renders page numbers by default', () => {
		render( <Edit { ...defaultProps } /> );
		expect( screen.getByText( '1' ) ).toBeTruthy();
		expect( screen.getByText( '5' ) ).toBeTruthy();
	} );

	it( 'renders Next link by default (page 1 of 5)', () => {
		const { container } = render( <Edit { ...defaultProps } /> );
		const nextLink = container.querySelector( '.aucteeno-pagination__next' );
		expect( nextLink ).toBeTruthy();
		expect( nextLink.textContent ).toContain( 'Next' );
	} );
} );
