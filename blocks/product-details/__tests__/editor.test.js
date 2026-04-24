/**
 * Tests for Aucteeno Product Details Block - Editor Component.
 */

jest.mock( '@wordpress/blocks' );
jest.mock( '@wordpress/block-editor' );
jest.mock( '@wordpress/components' );
jest.mock( '@wordpress/i18n' );
jest.mock( '@wordpress/element' );
jest.mock( '../block.json', () => ( { name: 'aucteeno/product-details' } ), { virtual: true } );
jest.mock( '../src/editor.css', () => ( {} ), { virtual: true } );

import { render } from '@testing-library/react';

require( '../src/editor' );
const { registerBlockType } = require( '@wordpress/blocks' );
const call = registerBlockType.mock.calls[ 0 ];
const Edit = call[ 1 ].edit;
const Save = call[ 1 ].save;

describe( 'Product Details Block Editor', () => {
	it( 'renders the edit component without crashing', () => {
		const setAttributes = jest.fn();
		const { container } = render(
			<Edit attributes={ {} } setAttributes={ setAttributes } />
		);
		expect( container ).toBeTruthy();
	} );

	it( 'populates previewItem on mount so inner blocks get context', () => {
		const setAttributes = jest.fn();
		render(
			<Edit attributes={ {} } setAttributes={ setAttributes } />
		);
		expect( setAttributes ).toHaveBeenCalledWith(
			expect.objectContaining( {
				previewItem: expect.objectContaining( {
					bidding_starts_at: expect.any( Number ),
					bidding_ends_at: expect.any( Number ),
				} ),
				itemType: 'auctions',
			} )
		);
	} );

	it( 'registers a Save component that renders InnerBlocks.Content', () => {
		expect( typeof Save ).toBe( 'function' );
		expect( () => render( <Save /> ) ).not.toThrow();
	} );
} );
