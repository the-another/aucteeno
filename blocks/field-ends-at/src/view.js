/* global MutationObserver, Node */

/**
 * Aucteeno Field Ends At Block - Frontend Script
 *
 * Hydrates `<time data-aucteeno-datetime>` elements to the visitor's local
 * browser timezone. SSR provides a WordPress-timezone initial value so
 * there is no layout shift; this script replaces the text content on load.
 */

import { formatDatetime } from '../../shared/src/datetime-utils';

/**
 * Hydrate one `<time data-aucteeno-datetime>` element to the visitor's local
 * timezone, unless it is already flagged as hydrated.
 *
 * That flag is also how render.php opts an element out of hydration
 * entirely: a `<time>` whose text came from the aucteeno_field_ends_at_value
 * filter carries `data-aucteeno-datetime-hydrated="true"` from the server,
 * so this function returns before ever touching it - the consumer-supplied
 * value must not be overwritten by the plain local-time date a moment later.
 *
 * Exported for direct unit testing; the module's own DOMContentLoaded /
 * MutationObserver wiring below is what actually drives it on a real page.
 *
 * @param {HTMLElement} element The `<time>` element to hydrate.
 */
export function hydrate( element ) {
	if ( element.dataset.aucteenoDatetimeHydrated === 'true' ) {
		return;
	}
	const timestamp = parseInt( element.dataset.timestamp, 10 );
	if ( ! timestamp ) {
		return;
	}
	const format = element.dataset.format || 'wp_default';
	const customFormat = element.dataset.customFormat || '';

	const value = formatDatetime( timestamp, format, customFormat );
	if ( value ) {
		element.textContent = value;
	}
	element.dataset.aucteenoDatetimeHydrated = 'true';
}

function hydrateAll( container = document ) {
	const elements = container.querySelectorAll( '[data-aucteeno-datetime]' );
	elements.forEach( hydrate );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', () => hydrateAll() );
} else {
	hydrateAll();
}

const observer = new MutationObserver( ( mutations ) => {
	mutations.forEach( ( mutation ) => {
		mutation.addedNodes.forEach( ( node ) => {
			if ( node.nodeType !== Node.ELEMENT_NODE ) {
				return;
			}
			if ( node.matches && node.matches( '[data-aucteeno-datetime]' ) ) {
				hydrate( node );
			}
			if ( node.querySelectorAll ) {
				hydrateAll( node );
			}
		} );
	} );
} );

observer.observe( document.body, { childList: true, subtree: true } );

document.addEventListener( 'aucteeno:contentLoaded', () => hydrateAll() );
