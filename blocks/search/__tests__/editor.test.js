/**
 * Tests for Aucteeno Search Block - Editor Component.
 */

jest.mock( '@wordpress/blocks' );
jest.mock( '@wordpress/block-editor' );
jest.mock( '@wordpress/components' );
jest.mock( '@wordpress/i18n' );
jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn( ( fn ) => {
		try {
			return fn( ( storeName ) => ( {
				getEntityRecords: jest.fn( () => [] ),
			} ) );
		} catch ( e ) {
			return undefined;
		}
	} ),
} ), { virtual: true } );
jest.mock( '../block.json', () => ( { name: 'aucteeno/search' } ), { virtual: true } );
jest.mock( '../src/editor.css', () => ( {} ), { virtual: true } );

import { render, screen, act } from '@testing-library/react';

require( '../src/editor' );
const { registerBlockType } = require( '@wordpress/blocks' );
const Edit = registerBlockType.mock.calls[ 0 ][ 1 ].edit;

describe( 'Aucteeno Search Block Editor', () => {
	const defaultProps = {
		attributes: {
			defaultType: 'items',
			debouncePreset: 'normal',
			countCacheMinutes: 5,
			recentSearchTimeoutSec: 10,
			viewAllItemsPageId: 0,
			viewAllAuctionsPageId: 0,
			placeholderTemplate: '%d items to search from',
		},
		setAttributes: jest.fn(),
	};

	beforeEach( () => {
		defaultProps.setAttributes.mockClear();
	} );

	it( 'renders the placeholder template with stub count', async () => {
		let container;
		await act( async () => {
			( { container } = render( <Edit { ...defaultProps } /> ) );
		} );
		expect( container ).toBeTruthy();
		expect( screen.getByText( /12,345 items to search from/ ) ).toBeTruthy();
	} );

	it( 'registers the block with the correct name', () => {
		expect( registerBlockType ).toHaveBeenCalled();
		expect( registerBlockType.mock.calls[ 0 ][ 0 ] ).toBe( 'aucteeno/search' );
	} );
} );
