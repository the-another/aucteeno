/**
 * Tests for Aucteeno Search Block - View runtime (modal scaffold).
 */

jest.mock( '../src/style.css', () => ( {} ), { virtual: true } );

import { SearchBlock } from '../src/view';

function makeRoot() {
	const root = document.createElement( 'div' );
	root.className = 'wp-block-aucteeno-search';
	root.dataset.defaultType = 'items';
	root.dataset.debounceMs = '0';
	root.dataset.recentTimeoutSec = '10';
	root.dataset.itemsPerPage = '25';
	root.dataset.itemsOrderBy = 'ending_soon';
	root.dataset.itemsPageUrl = '';
	root.dataset.auctionsPerPage = '25';
	root.dataset.auctionsOrderBy = 'ending_soon';
	root.dataset.auctionsPageUrl = '';
	root.dataset.restRoot = '/wp-json/aucteeno/v1/';
	root.dataset.restNonce = 'x';
	root.innerHTML = '<button class="wp-block-aucteeno-search__trigger">Open</button>';
	document.body.appendChild( root );
	return root;
}

describe( 'Aucteeno Search modal scaffold', () => {
	afterEach( () => {
		document.body.innerHTML = '';
		SearchBlock.openInstance = null;
	} );

	it( 'opens a modal on open()', () => {
		const block = new SearchBlock( makeRoot() );
		block.open();
		expect( document.querySelector( '.aucteeno-search-modal' ) ).not.toBeNull();
	} );

	it( 'closes on Escape', () => {
		const block = new SearchBlock( makeRoot() );
		block.open();
		document.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Escape' } ) );
		expect( document.querySelector( '.aucteeno-search-modal' ) ).toBeNull();
	} );

	it( 'returns focus to trigger on close', () => {
		const root = makeRoot();
		const block = new SearchBlock( root );
		block.open();
		block.close();
		expect( document.activeElement ).toBe(
			root.querySelector( '.wp-block-aucteeno-search__trigger' )
		);
	} );

	it( 'closes any other open modal when a second block opens (singleton)', () => {
		const a = new SearchBlock( makeRoot() );
		const b = new SearchBlock( makeRoot() );
		a.open();
		b.open();
		expect( document.querySelectorAll( '.aucteeno-search-modal' ).length ).toBe( 1 );
	} );

	it( 'shows empty state when input is empty', () => {
		const block = new SearchBlock( makeRoot() );
		block.open();
		expect( document.querySelector( '.aucteeno-search-modal__empty' ) ).not.toBeNull();
	} );

	it( 'modal DOM tab order matches: input → type-toggle → results → view-all → close', () => {
		const block = new SearchBlock( makeRoot() );
		block.open();
		// Inject a fake result row.
		block.modal.results.innerHTML = '<li class="aucteeno-search-modal__result" tabindex="0">row</li>';
		block.modal.viewAll.hidden = false;

		const focusables = [
			...block.modal.root.querySelectorAll(
				'input, button, a, [tabindex]:not([tabindex="-1"])'
			),
		];
		const labels = focusables
			.map( ( el ) => {
				if ( el.matches( '.aucteeno-search-modal__input' ) ) return 'input';
				if ( el.matches( '[role="radio"]' ) ) return 'toggle';
				if ( el.matches( '.aucteeno-search-modal__result' ) ) return 'result';
				if ( el.matches( '.aucteeno-search-modal__view-all' ) ) return 'view-all';
				if ( el.matches( '.aucteeno-search-modal__close' ) ) return 'close';
				return null;
			} )
			.filter( Boolean );

		expect( labels.indexOf( 'input' ) ).toBeLessThan( labels.indexOf( 'toggle' ) );
		expect( labels.indexOf( 'toggle' ) ).toBeLessThan( labels.indexOf( 'result' ) );
		expect( labels.indexOf( 'result' ) ).toBeLessThan( labels.indexOf( 'view-all' ) );
		expect( labels.indexOf( 'view-all' ) ).toBeLessThan( labels.indexOf( 'close' ) );
	} );
} );
