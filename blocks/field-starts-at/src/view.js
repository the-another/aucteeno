/* global MutationObserver, Node */

/**
 * Aucteeno Field Starts At Block - Frontend Script
 *
 * Hydrates `<time data-aucteeno-datetime>` elements to the visitor's local
 * browser timezone. SSR provides a WordPress-timezone initial value so
 * there is no layout shift; this script replaces the text content on load.
 */

import { formatDatetime } from '../../shared/src/datetime-utils';

function hydrate( element ) {
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
