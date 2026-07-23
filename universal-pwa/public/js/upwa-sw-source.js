/**
 * Universal PWA - minimal service worker.
 *
 * This exists ONLY to satisfy the browser's install criteria
 * (Chrome/Android requires a registered service worker with a fetch
 * handler before it will fire the install prompt). It intentionally
 * does no caching and stores nothing.
 */

self.addEventListener( 'install', function () {
	// Activate immediately instead of waiting for old tabs to close.
	self.skipWaiting();
} );

self.addEventListener( 'activate', function ( event ) {
	event.waitUntil( self.clients.claim() );
} );

self.addEventListener( 'fetch', function ( event ) {
	event.respondWith( fetch( event.request ) );
} );
