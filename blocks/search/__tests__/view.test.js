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

describe( 'Aucteeno Search modal: debounce + fetch + render', () => {
	afterEach( () => {
		document.body.innerHTML = '';
		SearchBlock.openInstance = null;
		jest.useRealTimers();
		delete global.fetch;
	} );

	it( 'debounces input and fires one fetch', async () => {
		const root = makeRoot();
		root.dataset.debounceMs = '50';
		const block = new SearchBlock( root );
		block.open();
		global.fetch = jest.fn().mockResolvedValue( { ok: true, json: async () => [] } );
		block.onInputChange( 'a' );
		block.onInputChange( 'ab' );
		block.onInputChange( 'abc' );
		await new Promise( ( r ) => setTimeout( r, 80 ) );
		expect( global.fetch ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'switching type re-runs fetch with current text', async () => {
		const root = makeRoot();
		root.dataset.debounceMs = '0';
		const block = new SearchBlock( root );
		block.open();
		block.modal.input.value = 'foo';
		global.fetch = jest.fn().mockResolvedValue( { ok: true, json: async () => [] } );
		block.setActiveType( 'auctions' );
		await new Promise( ( r ) => setTimeout( r, 5 ) );
		expect( global.fetch.mock.calls[ 0 ][ 0 ].toString() ).toMatch( /\/auctions\?.*search=foo/ );
	} );

	it( 'discards stale fetch responses', async () => {
		const root = makeRoot();
		root.dataset.debounceMs = '0';
		const block = new SearchBlock( root );
		block.open();
		let resolveFirst;
		global.fetch = jest
			.fn()
			.mockImplementationOnce( () => new Promise( ( r ) => { resolveFirst = r; } ) )
			.mockResolvedValueOnce( {
				ok: true,
				json: async () => [
					{ id: 2, title: 'Two', image_url: '', ends_at: 0, permalink: '#two' },
				],
			} );
		block.fetchNow( 'first' );
		await Promise.resolve();
		block.fetchNow( 'second' );
		await new Promise( ( r ) => setTimeout( r, 10 ) );
		resolveFirst( {
			ok: true,
			json: async () => [
				{ id: 1, title: 'One', image_url: '', ends_at: 0, permalink: '#one' },
			],
		} );
		await new Promise( ( r ) => setTimeout( r, 10 ) );
		const titles = [
			...document.querySelectorAll( '.aucteeno-search-modal__result-title' ),
		].map( ( e ) => e.textContent );
		expect( titles ).toEqual( [ 'Two' ] );
	} );

	it( 'sets view-all href with ?s= when results present and page configured', async () => {
		const root = makeRoot();
		root.dataset.debounceMs = '0';
		root.dataset.itemsPageUrl = 'https://example.com/search-items/';
		const block = new SearchBlock( root );
		block.open();
		global.fetch = jest.fn().mockResolvedValue( {
			ok: true,
			json: async () => [
				{ id: 1, title: 'X', image_url: '', ends_at: 0, permalink: '#x' },
			],
		} );
		await block.fetchNow( 'widget' );
		expect( block.modal.viewAll.hidden ).toBe( false );
		expect( block.modal.viewAll.href ).toContain( '/search-items/' );
		expect( block.modal.viewAll.href ).toContain( 's=widget' );
	} );

	it( 'hides view-all when no page configured', async () => {
		const root = makeRoot();
		root.dataset.debounceMs = '0';
		root.dataset.itemsPageUrl = '';
		const block = new SearchBlock( root );
		block.open();
		global.fetch = jest.fn().mockResolvedValue( {
			ok: true,
			json: async () => [
				{ id: 1, title: 'X', image_url: '', ends_at: 0, permalink: '#x' },
			],
		} );
		await block.fetchNow( 'widget' );
		expect( block.modal.viewAll.hidden ).toBe( true );
	} );

	it( 'renders no-results state for empty result set with non-empty query', async () => {
		const root = makeRoot();
		root.dataset.debounceMs = '0';
		const block = new SearchBlock( root );
		block.open();
		global.fetch = jest.fn().mockResolvedValue( { ok: true, json: async () => [] } );
		await block.fetchNow( 'nope' );
		expect( document.querySelector( '.aucteeno-search-modal__no-results' ) ).not.toBeNull();
	} );
} );
