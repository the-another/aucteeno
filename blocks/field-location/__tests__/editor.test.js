/**
 * Tests for Aucteeno Field Location Block - Editor Component.
 */

jest.mock( '@wordpress/blocks' );
jest.mock( '@wordpress/block-editor' );
jest.mock( '@wordpress/components' );
jest.mock( '@wordpress/i18n' );
jest.mock( '@wordpress/element' );
jest.mock( '../block.json', () => ( { name: 'aucteeno/field-location' } ), { virtual: true } );
jest.mock( '../src/style.css', () => ( {} ), { virtual: true } );

import { render, screen } from '@testing-library/react';

require( '../src/editor' );
const { registerBlockType } = require( '@wordpress/blocks' );
const Edit = registerBlockType.mock.calls[ 0 ][ 1 ].edit;

describe( 'Field Location Block Editor', () => {
	const defaultProps = {
		attributes: {},
		setAttributes: jest.fn(),
		context: {},
	};

	it( 'renders without crashing', () => {
		const { container } = render( <Edit { ...defaultProps } /> );
		expect( container ).toBeTruthy();
	} );

	it( 'renders default location with "City" placeholder', () => {
		render( <Edit { ...defaultProps } /> );
		expect( screen.getByText( 'City, United States' ) ).toBeTruthy();
	} );

	it( 'renders location from context', () => {
		const props = {
			...defaultProps,
			context: {
				'aucteeno/item': {
					location_city: 'Austin',
					location_subdivision: 'TX',
					location_country: 'US',
				},
			},
		};
		render( <Edit { ...props } /> );
		expect( screen.getByText( 'Austin, Texas, US' ) ).toBeTruthy();
	} );

	it( 'shows icon by default', () => {
		const { container } = render( <Edit { ...defaultProps } /> );
		const icon = container.querySelector( '.aucteeno-field-location__icon' );
		expect( icon ).toBeTruthy();
	} );
} );
