const DEBOUNCE_MS_MAP = { instant: 0, fast: 150, normal: 250, relaxed: 500 };

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
		setTimeout( () => this.modal.input.focus(), 0 );
		this.renderResults( [], '', this.activeType );
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
					<a class="aucteeno-search-modal__view-all" hidden>View all results</a>
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

	onInputChange() {
		// Debounce + fetch implemented in Task 3.2.
	}

	fetchNow() {
		// Implemented in Task 3.2.
	}

	renderResults( rows, q ) {
		const ul = this.modal.results;
		ul.innerHTML = '';
		if ( q === '' ) {
			ul.innerHTML =
				'<li class="aucteeno-search-modal__empty">Start typing to search…</li>';
		} else if ( ! rows || rows.length === 0 ) {
			ul.innerHTML = `<li class="aucteeno-search-modal__no-results">No results for "${ this.escape(
				q
			) }"</li>`;
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

export { SearchBlock, DEBOUNCE_MS_MAP };
