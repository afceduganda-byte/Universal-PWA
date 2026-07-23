/**
 * Universal PWA - custom install prompt.
 *
 * Handles three cases:
 *  - Android/Chrome/Edge: capture the native beforeinstallprompt event
 *    and drive it from our own branded banner.
 *  - iOS Safari (and Chrome/Edge on iPhone, which use WebKit): there is
 *    no native install event, ever. Show instructions instead.
 *  - Already installed (standalone) on any platform: never show anything.
 *
 * Settings are provided by wp_localize_script() as window.upwaSettings.
 */
( function () {
	'use strict';

	if ( typeof window.upwaSettings === 'undefined' || ! window.upwaSettings.enabled ) {
		return;
	}

	var settings = window.upwaSettings;
	var SESSION_KEY = 'upwaBannerShown';
	var deferredPrompt = null;
	var bannerEl = null;
	var installBtn = null;

	function isStandalone() {
		return (
			( window.matchMedia && window.matchMedia( '(display-mode: standalone)' ).matches ) ||
			window.navigator.standalone === true
		);
	}

	function isIOS() {
		var ua = window.navigator.userAgent || '';
		if ( /iPad|iPhone|iPod/.test( ua ) && ! window.MSStream ) {
			return true;
		}
		// iPadOS 13+ reports as a Mac but exposes multitouch.
		return window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1;
	}

	function alreadyShownThisSession() {
		try {
			return sessionStorage.getItem( SESSION_KEY ) === '1';
		} catch ( e ) {
			return false;
		}
	}

	function markShownThisSession() {
		try {
			sessionStorage.setItem( SESSION_KEY, '1' );
		} catch ( e ) {
			// sessionStorage unavailable (private mode, etc): degrade to
			// "show every load" rather than throwing.
		}
	}

	// Capture the native prompt as early as possible; the banner itself
	// still only appears after the delay below.
	window.addEventListener( 'beforeinstallprompt', function ( event ) {
		event.preventDefault();
		deferredPrompt = event;
		if ( installBtn ) {
			enableInstallButton();
		}
	} );

	window.addEventListener( 'appinstalled', function () {
		hideBanner();
		deferredPrompt = null;
	} );

	function enableInstallButton() {
		installBtn.disabled = false;
		installBtn.textContent = settings.installLabel;
	}

	function buildBanner( variant ) {
		var wrapper = document.createElement( 'div' );
		wrapper.id = 'upwa-banner';
		wrapper.className = 'upwa-banner upwa-banner--' + variant;
		wrapper.setAttribute( 'role', 'dialog' );
		wrapper.setAttribute( 'aria-label', settings.appName );

		var html = '';
		html += '<button type="button" class="upwa-banner__close" aria-label="' + escapeAttr( settings.dismissLabel ) + '">&times;</button>';
		html += '<div class="upwa-banner__row">';

		if ( settings.logoUrl ) {
			html += '<img class="upwa-banner__icon" src="' + escapeAttr( settings.logoUrl ) + '" alt="" width="48" height="48">';
		}

		html += '<div class="upwa-banner__text">';
		html += '<p class="upwa-banner__title">' + escapeHtml( settings.appName ) + '</p>';

		if ( 'ios' === variant ) {
			html += '<p class="upwa-banner__desc">' + escapeHtml( settings.bannerText ) + ' ' + settings.iosInstruction + '</p>';
		} else {
			html += '<p class="upwa-banner__desc">' + escapeHtml( settings.bannerText ) + '</p>';
		}

		html += '</div>'; // .upwa-banner__text

		if ( 'ios' !== variant ) {
			html += '<button type="button" class="upwa-banner__action" id="upwa-install-btn" disabled>' + escapeHtml( settings.waitingLabel ) + '</button>';
		}

		html += '</div>'; // .upwa-banner__row

		wrapper.innerHTML = html;
		return wrapper;
	}

	function escapeHtml( str ) {
		var div = document.createElement( 'div' );
		div.textContent = String( str || '' );
		return div.innerHTML;
	}

	function escapeAttr( str ) {
		return escapeHtml( str );
	}

	function hideBanner() {
		if ( bannerEl && bannerEl.parentNode ) {
			bannerEl.parentNode.removeChild( bannerEl );
		}
		bannerEl = null;
		installBtn = null;
	}

	function showBanner() {
		if ( isStandalone() || alreadyShownThisSession() ) {
			return;
		}

		var variant = isIOS() ? 'ios' : 'default';
		bannerEl = buildBanner( variant );
		document.body.appendChild( bannerEl );
		markShownThisSession();

		bannerEl.querySelector( '.upwa-banner__close' ).addEventListener( 'click', hideBanner );

		if ( 'ios' !== variant ) {
			installBtn = bannerEl.querySelector( '#upwa-install-btn' );

			if ( deferredPrompt ) {
				enableInstallButton();
			}

			installBtn.addEventListener( 'click', function () {
				if ( ! deferredPrompt ) {
					return;
				}
				var promptEvent = deferredPrompt;
				deferredPrompt = null;
				promptEvent.prompt();
				promptEvent.userChoice.finally( function () {
					hideBanner();
				} );
			} );
		}

		// Give the browser a frame to apply initial styles before animating in.
		window.requestAnimationFrame( function () {
			if ( bannerEl ) {
				bannerEl.classList.add( 'upwa-banner--visible' );
			}
		} );
	}

	function init() {
		if ( isStandalone() || alreadyShownThisSession() ) {
			return;
		}
		window.setTimeout( showBanner, settings.bannerDelay || 6000 );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
