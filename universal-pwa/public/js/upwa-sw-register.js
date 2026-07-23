/**
 * Registers the pass-through service worker from the site root so the
 * browser's install (installability) criteria are satisfied.
 */
( function () {
	if ( ! ( 'serviceWorker' in navigator ) ) {
		return;
	}

	window.addEventListener( 'load', function () {
		navigator.serviceWorker.register( '/sw.js', { scope: '/' } ).catch( function ( error ) {
			// Registration failures shouldn't break the page; just skip the
			// install-prompt benefits silently.
			if ( window.console && console.warn ) {
				console.warn( 'Universal PWA: service worker registration failed.', error );
			}
		} );
	} );
} )();
