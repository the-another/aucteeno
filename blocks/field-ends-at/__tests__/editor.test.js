/**
 * Tests for Aucteeno Field Ends At Block - Editor Component.
 */

jest.mock( '@wordpress/blocks' );
jest.mock( '@wordpress/block-editor' );
jest.mock( '@wordpress/components' );
jest.mock( '@wordpress/i18n' );
jest.mock( '@wordpress/element' );
jest.mock( '../block.json', () => ( { name: 'aucteeno/field-ends-at' } ), { virtual: true } );
jest.mock( '../src/style.css', () => ( {} ), { virtual: true } );

import { render, screen } from '@testing-library/react';

require( '../src/editor' );
const { registerBlockType } = require( '@wordpress/blocks' );
const Edit = registerBlockType.mock.calls[ 0 ][ 1 ].edit;

describe( 'Field Ends At Block Editor', () => {
	it( 'renders without crashing', () => {
		const { container } = render(
			<Edit
				attributes={ {} }
				setAttributes={ () => {} }
				context={ {} }
			/>
		);
		expect( container ).toBeTruthy();
	} );

	it( 'shows the "Ends" label by default', () => {
		render(
			<Edit
				attributes={ {} }
				setAttributes={ () => {} }
				context={ {} }
			/>
		);
		expect( screen.getByText( 'Ends' ) ).toBeTruthy();
	} );

	it( 'hides the label when showLabel is false', () => {
		render(
			<Edit
				attributes={ { showLabel: false } }
				setAttributes={ () => {} }
				context={ {} }
			/>
		);
		expect( screen.queryByText( 'Ends' ) ).toBeNull();
	} );

	it( 'formats a context timestamp when provided', () => {
		const ts = 1768996800; // 2026-01-17 12:00:00 UTC
		render(
			<Edit
				attributes={ { dateTimeFormat: 'wp_default' } }
				setAttributes={ () => {} }
				context={ {
					'aucteeno/item': { bidding_ends_at: ts },
				} }
			/>
		);
		const times = document.querySelectorAll( 'time' );
		expect( times.length ).toBeGreaterThan( 0 );
		expect( times[ 0 ].textContent.length ).toBeGreaterThan( 0 );
	} );
} );
