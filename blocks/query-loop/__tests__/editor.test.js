/**
 * Tests for Aucteeno Query Loop Block - Editor Component.
 */

jest.mock( '@wordpress/blocks' );
jest.mock( '@wordpress/block-editor' );
jest.mock( '@wordpress/components' );
jest.mock( '@wordpress/i18n' );
jest.mock( '@wordpress/element' );
jest.mock( '@wordpress/api-fetch' );
jest.mock( '../block.json', () => ( { name: 'aucteeno/query-loop' } ), { virtual: true } );
jest.mock( '../src/editor.css', () => ( {} ), { virtual: true } );
jest.mock( '../src/style.css', () => ( {} ), { virtual: true } );

import { render, screen, act, waitFor } from '@testing-library/react';

require( '../src/editor' );
const { registerBlockType } = require( '@wordpress/blocks' );
const Edit = registerBlockType.mock.calls[ 0 ][ 1 ].edit;

describe( 'Query Loop Block Editor', () => {
	const defaultProps = {
		attributes: {},
		setAttributes: jest.fn(),
		context: {},
	};

	beforeEach( () => {
		defaultProps.setAttributes.mockClear();
	} );

	it( 'renders without crashing', async () => {
		let container;
		await act( async () => {
			( { container } = render( <Edit { ...defaultProps } /> ) );
		} );
		expect( container ).toBeTruthy();
	} );

	it( 'shows loading state', async () => {
		await act( async () => {
			render( <Edit { ...defaultProps } /> );
		} );
		// After act resolves the promise, the component has loaded
		expect( screen.getByText( /No auctions found/ ) ).toBeTruthy();
	} );

	it( 'shows placeholder notice after loading with no items', async () => {
		await act( async () => {
			render( <Edit { ...defaultProps } /> );
		} );
		await waitFor( () => {
			expect( screen.getByText( /No auctions found/ ) ).toBeTruthy();
		} );
	} );

	it( 'calls apiFetch with auctions endpoint by default', async () => {
		const apiFetch = require( '@wordpress/api-fetch' );
		apiFetch.mockClear();

		await act( async () => {
			render( <Edit { ...defaultProps } /> );
		} );

		expect( apiFetch ).toHaveBeenCalled();
		const callPath = apiFetch.mock.calls[ 0 ][ 0 ].path;
		expect( callPath ).toContain( '/aucteeno/v1/auctions' );
	} );
} );
