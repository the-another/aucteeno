/**
 * Tests for Aucteeno Card Block - Editor Component.
 */

jest.mock( '@wordpress/blocks' );
jest.mock( '@wordpress/block-editor' );
jest.mock( '@wordpress/components' );
jest.mock( '@wordpress/i18n' );
jest.mock( '@wordpress/element' );
jest.mock( '../block.json', () => ( { name: 'aucteeno/card' } ), { virtual: true } );
jest.mock( '../src/editor.css', () => ( {} ), { virtual: true } );
jest.mock( '../src/style.css', () => ( {} ), { virtual: true } );

import { render } from '@testing-library/react';

require( '../src/editor' );
const { registerBlockType } = require( '@wordpress/blocks' );
const Edit = registerBlockType.mock.calls[ 0 ][ 1 ].edit;

describe( 'Card Block Editor', () => {
	const defaultProps = {
		attributes: {},
		setAttributes: jest.fn(),
		context: {},
	};

	it( 'renders without crashing', () => {
		const { container } = render( <Edit { ...defaultProps } /> );
		expect( container ).toBeTruthy();
	} );

	it( 'renders as article element with inner blocks props', () => {
		const { container } = render( <Edit { ...defaultProps } /> );
		const article = container.querySelector( 'article' );
		expect( article ).toBeTruthy();
	} );

	it( 'applies status class from context', () => {
		const props = {
			...defaultProps,
			context: { 'aucteeno/item': { bidding_status: 20 } },
		};
		const { container } = render( <Edit { ...props } /> );
		const article = container.querySelector( 'article' );
		expect( article.className ).toContain( 'aucteeno-card--upcoming' );
	} );
} );
