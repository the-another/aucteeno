/**
 * Tests for Aucteeno Field Lot Number Block - Editor Component.
 */

jest.mock( '@wordpress/blocks' );
jest.mock( '@wordpress/block-editor' );
jest.mock( '@wordpress/components' );
jest.mock( '@wordpress/i18n' );
jest.mock( '@wordpress/element' );
jest.mock( '../block.json', () => ( { name: 'aucteeno/field-lot-number' } ), { virtual: true } );
jest.mock( '../src/style.css', () => ( {} ), { virtual: true } );

import { render, screen } from '@testing-library/react';

require( '../src/editor' );
const { registerBlockType } = require( '@wordpress/blocks' );
const Edit = registerBlockType.mock.calls[ 0 ][ 1 ].edit;

describe( 'Field Lot Number Block Editor', () => {
	const defaultProps = {
		attributes: {},
		setAttributes: jest.fn(),
		context: {},
	};

	it( 'renders without crashing', () => {
		const { container } = render( <Edit { ...defaultProps } /> );
		expect( container ).toBeTruthy();
	} );

	it( 'shows items-only message when itemType is not "items"', () => {
		render( <Edit { ...defaultProps } /> );
		expect( screen.getByText( 'Lot # (items only)' ) ).toBeTruthy();
	} );

	it( 'shows lot number with prefix when itemType is "items"', () => {
		const props = {
			...defaultProps,
			context: {
				'aucteeno/itemType': 'items',
				'aucteeno/item': { lot_no: '042' },
			},
		};
		render( <Edit { ...props } /> );
		expect( screen.getByText( 'Lot #' ) ).toBeTruthy();
		expect( screen.getByText( '042' ) ).toBeTruthy();
	} );
} );
