/* global localStorage */
const DEBOUNCE_MS_MAP = { instant: 0, fast: 150, normal: 250, relaxed: 500 };

const STORAGE_KEY_RECENT = 'aucteeno_search_recent_v1';
const STORAGE_KEY_LAST = 'aucteeno_search_last_v1';
const RECENT_CAP = 10;
const LAST_TTL_MS = 30 * 60 * 1000;

function readRecent() {
	try {
		const raw = JSON.parse(
			localStorage.getItem( STORAGE_KEY_RECENT ) || '[]'
		);
		return Array.isArray( raw ) ? raw : [];
	} catch ( _ ) {
		return [];
	}
}

function writeRecent( list ) {
	try {
		localStorage.setItem(
			STORAGE_KEY_RECENT,
			JSON.stringify( list.slice( 0, RECENT_CAP ) )
		);
	} catch ( _ ) {
		/* localStorage unavailable; degrade silently */
	}
}

function pushRecent( q, type ) {
	if ( ! q ) {
		return;
	}
	const list = readRecent().filter(
		( e ) => ! ( e.q === q && e.type === type )
	);
	list.unshift( { q, type, ts: Date.now() } );
	writeRecent( list );
}

function readLast() {
	try {
		const raw = JSON.parse(
			localStorage.getItem( STORAGE_KEY_LAST ) || 'null'
		);
		if ( ! raw ) {
			return null;
		}
		if ( Date.now() - raw.ts > LAST_TTL_MS ) {
			return null;
		}
		return raw;
	} catch ( _ ) {
		return null;
	}
}

function writeLast( q, type ) {
	if ( ! q ) {
		return;
	}
	try {
		localStorage.setItem(
			STORAGE_KEY_LAST,
			JSON.stringify( { q, type, ts: Date.now() } )
		);
	} catch ( _ ) {
		/* noop */
	}
}

function clearLast() {
	try {
		localStorage.removeItem( STORAGE_KEY_LAST );
	} catch ( _ ) {
		/* noop */
	}
}

class SearchBlock {
	constructor( root ) {
		this.root = root;
		this.trigger = root.querySelector(
			'.wp-block-aucteeno-search__trigger'
		);
		this.cfg = this.readConfig( root );
		this.modal = null;
		this.activeType = this.cfg.defaultType;
		this.lastFetchKey = null;
		this.pendingPauseTimer = null;
		this.debounceTimer = null;
		this.countdownInterval = null;
		this._returningFocus = false;
		this.bind();
	}

	readConfig( el ) {
		return {
			defaultType: el.dataset.defaultType || 'items',
			debounceMs: parseInt( el.dataset.debounceMs, 10 ) || 250,
			recentTimeoutSec: Math.max(
				1,
				Math.min(
					60,
					parseInt( el.dataset.recentTimeoutSec, 10 ) || 10
				)
			),
			perPage: {
				items: parseInt( el.dataset.itemsPerPage, 10 ) || 25,
				auctions: parseInt( el.dataset.auctionsPerPage, 10 ) || 25,
			},
			orderBy: {
				items: el.dataset.itemsOrderBy || 'ending_soon',
				auctions: el.dataset.auctionsOrderBy || 'ending_soon',
			},
			pageUrl: {
				items: el.dataset.itemsPageUrl || '',
				auctions: el.dataset.auctionsPageUrl || '',
			},
			restRoot: el.dataset.restRoot,
			restNonce: el.dataset.restNonce,
		};
	}

	bind() {
		if ( ! this.trigger ) {
			return;
		}
		this.trigger.addEventListener( 'focus', () => {
			if ( this._returningFocus ) {
				return;
			}
			this.open();
		} );
		this.trigger.addEventListener( 'click', () => this.open() );
	}

	open() {
		if ( SearchBlock.openInstance && SearchBlock.openInstance !== this ) {
			SearchBlock.openInstance.close();
		}
		if ( this.modal ) {
			return;
		}
		this.modal = this.buildModal();
		document.body.appendChild( this.modal.root );
		SearchBlock.openInstance = this;
		setTimeout( () => this.modal && this.modal.input.focus(), 0 );
		this.renderResults( [], '', this.activeType );
		this.renderRecent();
		document.addEventListener( 'keydown', this.onKeydown );
	}

	close() {
		if ( ! this.modal ) {
			return;
		}
		document.removeEventListener( 'keydown', this.onKeydown );
		if ( this.countdownInterval ) {
			clearInterval( this.countdownInterval );
			this.countdownInterval = null;
		}
		if ( this.pendingPauseTimer ) {
			clearTimeout( this.pendingPauseTimer );
			this.pendingPauseTimer = null;
		}
		if ( this.debounceTimer ) {
			clearTimeout( this.debounceTimer );
			this.debounceTimer = null;
		}
		this.modal.root.remove();
		this.modal = null;
		if ( SearchBlock.openInstance === this ) {
			SearchBlock.openInstance = null;
		}
		if ( this.trigger ) {
			// focus() fires synchronously; the flag is cleared before any user-triggered focus event can arrive.
			this._returningFocus = true;
			this.trigger.focus();
			this._returningFocus = false;
		}
	}

	onKeydown = ( e ) => {
		if ( ! this.modal ) {
			return;
		}
		if ( e.key === 'Escape' ) {
			e.preventDefault();
			this.close();
			return;
		}
		if ( e.key === 'Tab' ) {
			const focusables = this.modal.root.querySelectorAll(
				'input, button, a[href], [tabindex]:not([tabindex="-1"])'
			);
			const visible = [ ...focusables ].filter(
				( el ) => ! el.hidden && el.offsetParent !== null
			);
			if ( visible.length === 0 ) {
				return;
			}
			const first = visible[ 0 ];
			const last = visible[ visible.length - 1 ];
			const activeEl = this.modal.root.ownerDocument.activeElement;
			if ( e.shiftKey && activeEl === first ) {
				e.preventDefault();
				last.focus();
			} else if ( ! e.shiftKey && activeEl === last ) {
				e.preventDefault();
				first.focus();
			}
		}
	};

	buildModal() {
		const root = document.createElement( 'div' );
		root.className = 'aucteeno-search-modal';
		root.setAttribute( 'role', 'dialog' );
		root.setAttribute( 'aria-modal', 'true' );
		root.setAttribute( 'aria-label', 'Search' );
		// DOM order matches the spec's focus-trap boundary:
		// input → type-toggle → result rows → view-all → close.
		// CSS positions the close button visually top-right while keeping it last in the DOM tab sequence.
		root.innerHTML = `
			<div class="aucteeno-search-modal__backdrop" data-action="close"></div>
			<div class="aucteeno-search-modal__panel">
				<div class="aucteeno-search-modal__main">
					<div class="aucteeno-search-modal__header">
						<input type="search" class="aucteeno-search-modal__input" placeholder="Search…" autocomplete="off" />
						<div class="aucteeno-search-modal__type-toggle" role="radiogroup">
							<button type="button" data-type="items" role="radio">Items</button>
							<button type="button" data-type="auctions" role="radio">Auctions</button>
						</div>
					</div>
					<ul class="aucteeno-search-modal__results" role="listbox"></ul>
					<a class="aucteeno-search-modal__view-all" href="#" hidden>View all results</a>
					<button type="button" class="aucteeno-search-modal__close" aria-label="Close" data-action="close">✕</button>
				</div>
				<aside class="aucteeno-search-modal__recent" aria-label="Recent searches">
					<h3>Recent searches</h3>
					<ul class="aucteeno-search-modal__recent-list"></ul>
					<button type="button" class="aucteeno-search-modal__recent-clear">Clear all</button>
				</aside>
			</div>
		`;

		const input = root.querySelector( '.aucteeno-search-modal__input' );
		const results = root.querySelector( '.aucteeno-search-modal__results' );
		const viewAll = root.querySelector(
			'.aucteeno-search-modal__view-all'
		);
		const toggleBtns = root.querySelectorAll(
			'.aucteeno-search-modal__type-toggle button'
		);

		root.querySelectorAll( '[data-action="close"]' ).forEach( ( el ) =>
			el.addEventListener( 'click', () => this.close() )
		);

		toggleBtns.forEach( ( btn ) => {
			btn.setAttribute(
				'aria-checked',
				String( btn.dataset.type === this.activeType )
			);
			btn.addEventListener( 'click', () =>
				this.setActiveType( btn.dataset.type )
			);
		} );

		input.addEventListener( 'input', () =>
			this.onInputChange( input.value )
		);

		return { root, input, results, viewAll, toggleBtns };
	}

	setActiveType( t ) {
		if ( t !== 'items' && t !== 'auctions' ) {
			return;
		}
		this.activeType = t;
		this.modal.toggleBtns.forEach( ( b ) =>
			b.setAttribute( 'aria-checked', String( b.dataset.type === t ) )
		);
		this.fetchNow( this.modal.input.value );
	}

	onInputChange( value ) {
		if ( this.debounceTimer ) {
			clearTimeout( this.debounceTimer );
		}
		if ( this.pendingPauseTimer ) {
			clearTimeout( this.pendingPauseTimer );
			this.pendingPauseTimer = null;
		}
		this.debounceTimer = setTimeout(
			() => this.fetchNow( value ),
			this.cfg.debounceMs
		);
	}

	async fetchNow( value ) {
		// Toggle/refetch must cancel any pending pause-timer (spec: type-toggle clears the timer).
		if ( this.pendingPauseTimer ) {
			clearTimeout( this.pendingPauseTimer );
			this.pendingPauseTimer = null;
		}
		const q = ( value || '' ).trim();
		const type = this.activeType;
		const fetchKey = Symbol( 'fetch' );
		this.lastFetchKey = fetchKey;

		if ( q === '' ) {
			this.renderResults( [], q, type );
			return;
		}

		// REST internal params: `search`, `sort`, `format=search_row`, `per_page`.
		const url = new URL( this.cfg.restRoot + type, window.location.origin );
		url.searchParams.set( 'search', q );
		url.searchParams.set( 'format', 'search_row' );
		url.searchParams.set( 'sort', this.cfg.orderBy[ type ] );
		url.searchParams.set( 'per_page', String( this.cfg.perPage[ type ] ) );

		let data = [];
		try {
			const res = await fetch( url, {
				headers: { 'X-WP-Nonce': this.cfg.restNonce },
			} );
			if ( ! res.ok ) {
				throw new Error( 'fetch failed: ' + res.status );
			}
			data = await res.json();
		} catch ( err ) {
			// eslint-disable-next-line no-console
			console.warn( 'Aucteeno search fetch failed', err );
			data = [];
		}

		if ( this.lastFetchKey !== fetchKey ) {
			return; // stale
		}
		this.renderResults( Array.isArray( data ) ? data : [], q, type );
		if ( Array.isArray( data ) && data.length > 0 ) {
			this.armPauseTimer( q, type );
		}
	}

	armPauseTimer( q, type ) {
		if ( this.pendingPauseTimer ) {
			clearTimeout( this.pendingPauseTimer );
		}
		this.pendingPauseTimer = setTimeout( () => {
			pushRecent( q, type );
			this.renderRecent();
			this.pendingPauseTimer = null;
		}, this.cfg.recentTimeoutSec * 1000 );
	}

	renderRecent() {
		if ( ! this.modal ) {
			return;
		}
		const ul = this.modal.root.querySelector(
			'.aucteeno-search-modal__recent-list'
		);
		const clearBtn = this.modal.root.querySelector(
			'.aucteeno-search-modal__recent-clear'
		);
		ul.innerHTML = '';
		const list = readRecent();
		list.forEach( ( entry ) => {
			const li = document.createElement( 'li' );
			li.innerHTML = `
				<button type="button" class="recent-q">${ this.escape(
					entry.q
				) } <span class="type">(${ this.escape(
					entry.type
				) })</span></button>
				<button type="button" class="recent-x" aria-label="Remove">✕</button>
			`;
			li.querySelector( '.recent-q' ).addEventListener( 'click', () => {
				this.activeType = entry.type;
				this.modal.toggleBtns.forEach( ( b ) =>
					b.setAttribute(
						'aria-checked',
						String( b.dataset.type === entry.type )
					)
				);
				this.modal.input.value = entry.q;
				this.fetchNow( entry.q );
			} );
			li.querySelector( '.recent-x' ).addEventListener( 'click', () => {
				const remaining = readRecent().filter(
					( e ) => ! ( e.q === entry.q && e.type === entry.type )
				);
				writeRecent( remaining );
				this.renderRecent();
			} );
			ul.appendChild( li );
		} );
		clearBtn.onclick = () => {
			writeRecent( [] );
			this.renderRecent();
		};
	}

	renderResults( rows, q, type ) {
		const ul = this.modal.results;
		ul.innerHTML = '';

		if ( q === '' ) {
			ul.innerHTML =
				'<li class="aucteeno-search-modal__empty">Start typing to search…</li>';
		} else if ( ! rows || rows.length === 0 ) {
			ul.innerHTML = `<li class="aucteeno-search-modal__no-results">No results for "${ this.escape(
				q
			) }"</li>`;
		} else {
			rows.forEach( ( row ) => {
				const li = document.createElement( 'li' );
				li.className = 'aucteeno-search-modal__result';
				li.tabIndex = 0;
				li.innerHTML = `
				<img src="${ this.escape( row.image_url ) }" alt="" />
				<span class="aucteeno-search-modal__result-title">${ this.escape(
					row.title
				) }</span>
				<span class="aucteeno-search-modal__result-countdown" data-ends-at="${
					row.ends_at
				}"></span>
			`;
				const navigate = () => this.onResultClick( row, q, type );
				li.addEventListener( 'click', navigate );
				li.addEventListener( 'keydown', ( e ) => {
					if ( e.key === 'Enter' || e.key === ' ' ) {
						e.preventDefault();
						navigate();
					}
				} );
				ul.appendChild( li );
			} );
		}

		// View all link.
		const pageUrl = this.cfg.pageUrl[ type ];
		if ( rows && rows.length > 0 && pageUrl ) {
			const u = new URL( pageUrl, window.location.origin );
			u.searchParams.set( 's', q );
			this.modal.viewAll.href = u.toString();
			this.modal.viewAll.hidden = false;
		} else {
			this.modal.viewAll.hidden = true;
		}

		this.startCountdownTicker();
	}

	startCountdownTicker() {
		if ( this.countdownInterval ) {
			clearInterval( this.countdownInterval );
		}
		const tick = () => {
			if ( ! this.modal ) {
				return;
			}
			const now = Math.floor( Date.now() / 1000 );
			this.modal.results
				.querySelectorAll( '[data-ends-at]' )
				.forEach( ( el ) => {
					const endsAt = parseInt( el.dataset.endsAt, 10 );
					const diff = Math.max( 0, endsAt - now );
					el.textContent = this.formatCountdown( diff );
				} );
		};
		tick();
		this.countdownInterval = setInterval( tick, 1000 );
	}

	formatCountdown( seconds ) {
		if ( seconds <= 0 ) {
			return 'Ended';
		}
		const d = Math.floor( seconds / 86400 );
		if ( d > 0 ) {
			return `${ d }d ${ Math.floor( ( seconds % 86400 ) / 3600 ) }h`;
		}
		const h = Math.floor( seconds / 3600 );
		if ( h > 0 ) {
			return `${ h }h ${ Math.floor( ( seconds % 3600 ) / 60 ) }m`;
		}
		const m = Math.floor( seconds / 60 );
		if ( m > 0 ) {
			return `${ m }m ${ seconds % 60 }s`;
		}
		return `${ seconds }s`;
	}

	onResultClick( row, q, type ) {
		if ( q ) {
			pushRecent( q, type );
			writeLast( q, type );
		}
		if ( row && row.permalink ) {
			window.location.href = row.permalink;
		}
	}

	escape( s ) {
		const div = document.createElement( 'div' );
		div.textContent = String( s );
		return div.innerHTML;
	}
}

SearchBlock.openInstance = null;

document.addEventListener( 'DOMContentLoaded', () => {
	document
		.querySelectorAll( '.wp-block-aucteeno-search' )
		.forEach( ( el ) => new SearchBlock( el ) );
} );

export {
	SearchBlock,
	DEBOUNCE_MS_MAP,
	STORAGE_KEY_RECENT,
	STORAGE_KEY_LAST,
	pushRecent,
	readRecent,
	writeRecent,
	readLast,
	writeLast,
	clearLast,
};
