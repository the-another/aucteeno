/**
 * Tests for Aucteeno Field Image Block - Editor Component.
 */

jest.mock( '@wordpress/blocks' );
jest.mock( '@wordpress/block-editor' );
jest.mock( '@wordpress/components' );
jest.mock( '@wordpress/i18n' );
jest.mock( '@wordpress/element' );
jest.mock( '../block.json', () => ( { name: 'aucteeno/field-image' } ), { virtual: true } );
jest.mock( '../src/style.css', () => ( {} ), { virtual: true } );

import { render, screen } from '@testing-library/react';

require( '../src/editor' );
const { registerBlockType } = require( '@wordpress/blocks' );
const Edit = registerBlockType.mock.calls[ 0 ][ 1 ].edit;

describe( 'Field Image Block Editor', () => {
	const defaultProps = {
		attributes: {},
		setAttributes: jest.fn(),
		context: {},
	};

	it( 'renders without crashing', () => {
		const { container } = render( <Edit { ...defaultProps } /> );
		expect( container ).toBeTruthy();
	} );

	it( 'shows placeholder "No image" when no image URL', () => {
		render( <Edit { ...defaultProps } /> );
		expect( screen.getByText( 'No image' ) ).toBeTruthy();
	} );

	it( 'renders img tag when image URL is provided', () => {
		const props = {
			...defaultProps,
			context: {
				'aucteeno/item': {
					image_url: 'https://example.com/photo.jpg',
					title: 'Farm Equipment',
				},
			},
		};
		const { container } = render( <Edit { ...props } /> );
		const img = container.querySelector( 'img' );
		expect( img ).toBeTruthy();
		expect( img.getAttribute( 'src' ) ).toBe( 'https://example.com/photo.jpg' );
		expect( img.getAttribute( 'alt' ) ).toBe( 'Farm Equipment' );
	} );
} );
