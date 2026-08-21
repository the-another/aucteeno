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
	} catch {
		return [];
	}
}

function writeRecent( list ) {
	try {
		localStorage.setItem(
			STORAGE_KEY_RECENT,
			JSON.stringify( list.slice( 0, RECENT_CAP ) )
		);
	} catch {
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
	} catch {
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
	} catch {
		/* noop */
	}
}

function clearLast() {
	try {
		localStorage.removeItem( STORAGE_KEY_LAST );
	} catch {
		/* noop */
	}
}

// `parseInt( value, 10 ) || fallback` treats an explicit "0" as falsy and
// silently substitutes the fallback — wrong for debounceMs, where 0 is a
// legitimate "instant" setting (see DEBOUNCE_MS_MAP.instant). Only fall back
// when parsing actually failed.
function parseIntOrDefault( value, fallback ) {
	const parsed = parseInt( value, 10 );
	return Number.isNaN( parsed ) ? fallback : parsed;
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
		this.abortController = null;
		this.countdownInterval = null;
		this._returningFocus = false;
		this.lastChip = null;
		this.busy = false;
		this.onStorageEvent = ( e ) => {
			if ( e.key === STORAGE_KEY_LAST ) {
				this.renderChip();
			}
			if ( e.key === STORAGE_KEY_RECENT && this.modal ) {
				this.renderRecent();
			}
		};
		window.addEventListener( 'storage', this.onStorageEvent );
		// A bfcache restore (back button after submit) revives the page with the
		// pre-navigation busy state frozen in place; reset it so the modal is
		// usable again.
		this.onPageShow = () => this.clearBusy();
		window.addEventListener( 'pageshow', this.onPageShow );
		this.bind();
		this.renderChip();
	}

	readConfig( el ) {
		return {
			defaultType: el.dataset.defaultType || 'items',
			debounceMs: parseIntOrDefault( el.dataset.debounceMs, 250 ),
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
			disableLive: el.dataset.disableLiveResults === '1',
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

	renderChip() {
		if ( ! this.trigger ) {
			return;
		}
		const placeholderEl = this.trigger.querySelector(
			'.wp-block-aucteeno-search__placeholder'
		);
		if ( ! placeholderEl ) {
			return;
		}

		const last = readLast();
		if ( ! last ) {
			// Restore original placeholder if a chip was previously rendered (e.g. cross-tab clear).
			placeholderEl.textContent =
				this.trigger.dataset.originalPlaceholder || '';
			this.lastChip = null;
			return;
		}

		placeholderEl.innerHTML = `
			<span class="aucteeno-search-chip">
				<span class="aucteeno-search-chip__text">${ this.escape( last.q ) }</span>
				<button type="button" class="aucteeno-search-chip__x" aria-label="Clear">✕</button>
			</span>
		`;
		const xBtn = placeholderEl.querySelector( '.aucteeno-search-chip__x' );
		xBtn.addEventListener( 'click', ( e ) => {
			e.stopPropagation();
			clearLast();
			placeholderEl.textContent =
				this.trigger.dataset.originalPlaceholder || '';
			this.lastChip = null;
		} );

		this.activeType = last.type;
		this.lastChip = last; // consumed by open() to pre-fill input
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
		if ( ! this.cfg.disableLive ) {
			this.renderResults( [], '', this.activeType );
		}
		this.renderRecent();
		// Re-read the stored chip on every open so reopening the modal still
		// pre-fills the term (don't rely on this.lastChip which is consumed once).
		const last = readLast();
		if ( last ) {
			this.activeType = last.type;
			this.modal.toggleBtns.forEach( ( b ) =>
				b.setAttribute(
					'aria-checked',
					String( b.dataset.type === last.type )
				)
			);
			this.modal.input.value = last.q;
			if ( ! this.cfg.disableLive ) {
				this.fetchNow( last.q );
			}
		}
		this.lastChip = null;
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
		this.abortInFlight();
		this.modal.root.remove();
		this.modal = null;
		// The busy DOM just left with the modal; a reopen builds a fresh,
		// enabled one, so the flag must not survive to block its submits.
		this.busy = false;
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
		if ( e.key === 'ArrowDown' || e.key === 'ArrowUp' ) {
			const rows = [
				...this.modal.results.querySelectorAll(
					'.aucteeno-search-modal__result'
				),
			];
			if ( rows.length === 0 ) {
				return;
			}
			const active = this.modal.root.ownerDocument.activeElement;
			const idx = rows.indexOf( active );
			if ( e.key === 'ArrowDown' ) {
				if ( active === this.modal.input ) {
					e.preventDefault();
					rows[ 0 ].focus();
				} else if ( idx > -1 && idx < rows.length - 1 ) {
					e.preventDefault();
					rows[ idx + 1 ].focus();
				} else if ( idx === rows.length - 1 ) {
					e.preventDefault();
				}
			} else if ( idx === 0 ) {
				e.preventDefault();
				this.modal.input.focus();
			} else if ( idx > 0 ) {
				e.preventDefault();
				rows[ idx - 1 ].focus();
			}
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
		// input → submit button → type-toggle → result rows → view-all → close.
		// CSS positions the close button visually top-right while keeping it last in the DOM tab sequence.
		root.innerHTML = `
			<div class="aucteeno-search-modal__backdrop" data-action="close"></div>
			<div class="aucteeno-search-modal__panel">
				<div class="aucteeno-search-modal__main">
					<div class="aucteeno-search-modal__header">
						<input type="search" class="aucteeno-search-modal__input" placeholder="Search…" autocomplete="off" />
						<button type="button" class="aucteeno-search-modal__submit" aria-label="Submit search">
							<span class="aucteeno-search-modal__submit-icon" aria-hidden="true"><svg focusable="false" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M15.5 14h-.79l-.28-.27a6.5 6.5 0 1 0-.7.7l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0A4.5 4.5 0 1 1 14 9.5 4.5 4.5 0 0 1 9.5 14z"/></svg></span>
							<span class="aucteeno-search-modal__spinner" aria-hidden="true" hidden></span>
						</button>
						<div class="aucteeno-search-modal__type-toggle" role="radiogroup">
							<button type="button" data-type="auctions" role="radio">Auctions</button>
							<button type="button" data-type="items" role="radio">Items</button>
						</div>
					</div>
					<p class="aucteeno-search-modal__status" role="status" hidden>Searching…</p>
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
		const submit = root.querySelector( '.aucteeno-search-modal__submit' );
		const submitIcon = root.querySelector(
			'.aucteeno-search-modal__submit-icon'
		);
		const spinner = root.querySelector( '.aucteeno-search-modal__spinner' );
		const status = root.querySelector( '.aucteeno-search-modal__status' );

		if ( this.cfg.disableLive ) {
			root.classList.add( 'is-live-disabled' );
			results.hidden = true;
			viewAll.hidden = true;
		}

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
		submit.addEventListener( 'click', () => this.submitSearch() );
		input.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'Enter' ) {
				e.preventDefault();
				this.submitSearch();
			}
		} );

		return {
			root,
			input,
			results,
			viewAll,
			toggleBtns,
			submit,
			submitIcon,
			spinner,
			status,
		};
	}

	setActiveType( t ) {
		if ( t !== 'items' && t !== 'auctions' ) {
			return;
		}
		this.activeType = t;
		this.modal.toggleBtns.forEach( ( b ) =>
			b.setAttribute( 'aria-checked', String( b.dataset.type === t ) )
		);
		if ( ! this.cfg.disableLive ) {
			this.fetchNow( this.modal.input.value );
		}
	}

	onInputChange( value ) {
		if ( this.cfg.disableLive ) {
			return;
		}
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
		// Load-bearing, not redundant: the only guard stopping submitSearch()'s
		// deliberately-ungated fallback from reaching restRoot below, which
		// render.php omits entirely (undefined) when live results are off.
		if ( this.cfg.disableLive ) {
			return;
		}
		if ( this.abortController ) {
			this.abortController.abort();
		}
		const controller = new AbortController();
		this.abortController = controller;
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
				signal: controller.signal,
			} );
			if ( ! res.ok ) {
				throw new Error( 'fetch failed: ' + res.status );
			}
			data = await res.json();
		} catch ( err ) {
			if ( err && err.name === 'AbortError' ) {
				return; // Intentional cancel (new keystroke or submit); not a failure.
			}
			// eslint-disable-next-line no-console
			console.warn( 'Aucteeno search fetch failed', err );
			data = [];
		}

		if ( this.lastFetchKey !== fetchKey ) {
			return; // stale
		}
		if ( ! this.modal ) {
			return; // modal closed during fetch
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
				if ( this.busy ) {
					return;
				}
				this.activeType = entry.type;
				this.modal.toggleBtns.forEach( ( b ) =>
					b.setAttribute(
						'aria-checked',
						String( b.dataset.type === entry.type )
					)
				);
				this.modal.input.value = entry.q;
				if ( this.cfg.disableLive ) {
					this.submitSearch();
				} else {
					this.fetchNow( entry.q );
				}
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

	viewAllUrl( q, type ) {
		const pageUrl = this.cfg.pageUrl[ type ];
		if ( ! pageUrl ) {
			return '';
		}
		const u = new URL( pageUrl, window.location.origin );
		u.searchParams.set( 'keyword', q );
		return u.toString();
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
				const location = row.location
					? `<span class="aucteeno-search-modal__result-location">${ this.escape(
							row.location
					  ) }</span>`
					: '';
				li.innerHTML = `
				<img src="${ this.escape( row.image_url ) }" alt="" />
				<div class="aucteeno-search-modal__result-text">
					<span class="aucteeno-search-modal__result-title">${ this.escape(
						row.title
					) }</span>
					${ location }
				</div>
				<span class="aucteeno-search-modal__result-countdown" data-ends-at="${
					row.ends_at
				}">
					<span class="aucteeno-search-modal__result-countdown-label">Ends in</span>
					<span class="aucteeno-search-modal__result-countdown-value"></span>
				</span>
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
		const viewAllUrl =
			rows && rows.length > 0 ? this.viewAllUrl( q, type ) : '';
		if ( viewAllUrl ) {
			this.modal.viewAll.href = viewAllUrl;
			this.modal.viewAll.textContent =
				type === 'auctions' ? 'View all auctions' : 'View all items';
			this.modal.viewAll.hidden = false;
		} else {
			this.modal.viewAll.hidden = true;
			this.modal.viewAll.removeAttribute( 'href' );
		}

		if ( rows && rows.length > 0 ) {
			this.startCountdownTicker();
		} else if ( this.countdownInterval ) {
			clearInterval( this.countdownInterval );
			this.countdownInterval = null;
		}
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
					const value = el.querySelector(
						'.aucteeno-search-modal__result-countdown-value'
					);
					if ( ! value ) {
						return;
					}
					const endsAt = parseInt( el.dataset.endsAt, 10 );
					const diff = Math.max( 0, endsAt - now );
					const label = el.querySelector(
						'.aucteeno-search-modal__result-countdown-label'
					);
					if ( diff <= 0 ) {
						el.classList.add( 'is-ended' );
						if ( label ) {
							label.hidden = true;
						}
						value.textContent = 'Ended';
					} else {
						el.classList.remove( 'is-ended' );
						if ( label ) {
							label.hidden = false;
						}
						value.textContent = this.formatDuration( diff );
					}
				} );
		};
		tick();
		this.countdownInterval = setInterval( tick, 1000 );
	}

	formatCountdown( seconds ) {
		if ( seconds <= 0 ) {
			return 'Ended';
		}
		return `Ends in ${ this.formatDuration( seconds ) }`;
	}

	formatDuration( seconds ) {
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

	// Navigation wrapper: jsdom forbids replacing window.location, so tests
	// stub this method instead.
	navigate( url ) {
		window.location.href = url;
	}

	// Cancels everything a pending search has in flight. Called before navigating
	// so the block stops doing work whose result is about to be discarded.
	abortInFlight() {
		if ( this.abortController ) {
			this.abortController.abort();
			this.abortController = null;
		}
		if ( this.debounceTimer ) {
			clearTimeout( this.debounceTimer );
			this.debounceTimer = null;
		}
		if ( this.pendingPauseTimer ) {
			clearTimeout( this.pendingPauseTimer );
			this.pendingPauseTimer = null;
		}
		this.lastFetchKey = null;
	}

	// Freezes the modal while a submit-triggered navigation is in flight:
	// spinner in place of the submit icon, controls disabled, status line
	// visible. Deliberately sticky — it holds until the browser unloads;
	// only a bfcache restore (pageshow) or close() undoes it.
	setBusy() {
		if ( ! this.modal || this.busy ) {
			return;
		}
		this.busy = true;
		const m = this.modal;
		m.root.setAttribute( 'aria-busy', 'true' );
		m.root.classList.add( 'is-busy' );
		m.input.disabled = true;
		m.submit.disabled = true;
		m.toggleBtns.forEach( ( b ) => {
			b.disabled = true;
		} );
		m.submitIcon.hidden = true;
		m.spinner.hidden = false;
		m.status.hidden = false;
	}

	clearBusy() {
		this.busy = false;
		if ( ! this.modal ) {
			return;
		}
		const m = this.modal;
		m.root.removeAttribute( 'aria-busy' );
		m.root.classList.remove( 'is-busy' );
		m.input.disabled = false;
		m.submit.disabled = false;
		m.toggleBtns.forEach( ( b ) => {
			b.disabled = false;
		} );
		m.submitIcon.hidden = false;
		m.spinner.hidden = true;
		m.status.hidden = true;
	}

	submitSearch() {
		if ( ! this.modal || this.busy ) {
			return;
		}
		const q = ( this.modal.input.value || '' ).trim();
		const type = this.activeType;
		if ( q === '' ) {
			// True no-op: leave any pending debounced fetchNow('') to run on its
			// own schedule and clear the list. Aborting it here (as an earlier
			// version of this method did) kills that pending reset without
			// putting anything in its place, leaving stale rows and a stale
			// "View all results" href on screen after an empty Enter.
			return;
		}
		this.abortInFlight();
		let url = this.viewAllUrl( q, type );
		if ( ! url && this.cfg.disableLive ) {
			// No results page resolved and no in-modal results to fall back to.
			// Never dead-end the submit: hand off to core WP search, and name
			// the unresolved attribute so operators can spot the misconfigured
			// page ID in the console.
			const attr =
				type === 'auctions'
					? 'viewAllAuctionsPageId'
					: 'viewAllItemsPageId';
			// eslint-disable-next-line no-console
			console.warn(
				`Aucteeno search: no published results page for type "${ type }" ` +
					`(block attribute ${ attr }); falling back to core WP search.`
			);
			const fallback = new URL( '/', window.location.origin );
			fallback.searchParams.set( 's', q );
			url = fallback.toString();
		}
		if ( url ) {
			pushRecent( q, type );
			writeLast( q, type );
			this.setBusy();
			this.navigate( url );
			return;
		}
		// No results page configured but live results are on: force an immediate
		// (non-debounced) in-modal fetch. Term is intentionally NOT persisted here
		// (persist happens on navigate or result click), mirroring live-typing
		// behavior. No busy state either — results render in this modal, so
		// freezing the controls would fight the interaction it feeds.
		this.fetchNow( q );
	}

	onResultClick( row, q, type ) {
		if ( this.busy ) {
			return;
		}
		if ( q ) {
			pushRecent( q, type );
			writeLast( q, type );
		}
		if ( row && row.permalink ) {
			this.navigate( row.permalink );
		}
	}

	escape( s ) {
		const div = document.createElement( 'div' );
		div.textContent = String( s );
		return div.innerHTML;
	}
}

SearchBlock.openInstance = null;

function initSearchBlocks() {
	document
		.querySelectorAll( '.wp-block-aucteeno-search' )
		.forEach( ( el ) => new SearchBlock( el ) );
}

// Module scripts have implicit `defer`, so DOMContentLoaded may already have
// fired by the time this code runs. Handle both cases.
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initSearchBlocks );
} else {
	initSearchBlocks();
}

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
