/**
 * Tests for the field-ends-at frontend hydration.
 *
 * There was previously no JS test for this block at all. This closes that
 * gap and, specifically, locks in the mechanism the aucteeno_field_ends_at_value
 * PHP filter depends on: an element already carrying
 * data-aucteeno-datetime-hydrated="true" must never have its text rewritten
 * by hydrate() - that flag is how a server-filtered value survives past
 * page load instead of being overwritten by the plain local-time date a
 * few hundred milliseconds later.
 */
import { hydrate } from '../src/view';

/**
 * Build a `<time>` element shaped like render.php's output, detached from
 * the document (hydrate() takes an element directly, so nothing here needs
 * to be attached or trigger the module's own DOMContentLoaded wiring).
 *
 * @param {Object}  options
 * @param {boolean} options.hydrated Whether to set the hydrated flag, as
 *                                   render.php now does for an overridden value.
 * @return {HTMLElement} The `<time>` element.
 */
function createTimeElement( { hydrated = false } = {} ) {
	const time = document.createElement( 'time' );
	time.dataset.timestamp = '1768996800'; // 2026-01-17 12:00:00 UTC.
	time.dataset.format = 'wp_default';
	time.dataset.customFormat = '';
	time.textContent = 'SERVER RENDERED';
	if ( hydrated ) {
		time.dataset.aucteenoDatetimeHydrated = 'true';
	}
	return time;
}

describe( 'hydrate', () => {
	test( 'rewrites the text to local time when the hydrated flag is absent', () => {
		const element = createTimeElement();

		hydrate( element );

		expect( element.textContent ).not.toBe( 'SERVER RENDERED' );
		expect( element.dataset.aucteenoDatetimeHydrated ).toBe( 'true' );
	} );

	test( 'leaves a consumer-supplied value alone when the hydrated flag is present', () => {
		// This is exactly what render.php now emits for an active
		// aucteeno_field_ends_at_value filter: the flag must stop hydration
		// from ever running, so the server-rendered value survives untouched.
		const element = createTimeElement( { hydrated: true } );

		hydrate( element );

		expect( element.textContent ).toBe( 'SERVER RENDERED' );
	} );

	test( 'does nothing and does not set the flag when the timestamp is missing', () => {
		const element = createTimeElement();
		delete element.dataset.timestamp;

		hydrate( element );

		expect( element.textContent ).toBe( 'SERVER RENDERED' );
		expect( element.dataset.aucteenoDatetimeHydrated ).toBeUndefined();
	} );

	test( 'calling hydrate twice is idempotent', () => {
		const element = createTimeElement();

		hydrate( element );
		const afterFirstHydration = element.textContent;
		hydrate( element );

		expect( element.textContent ).toBe( afterFirstHydration );
	} );
} );
