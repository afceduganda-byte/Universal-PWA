/**
 * Universal PWA admin screen: media uploader for the logo, WP color
 * picker init, and a client-side live preview of the banner + manifest
 * JSON that updates as the admin types (icons themselves only refresh
 * after saving, since they're generated server-side).
 */
( function ( $ ) {
	'use strict';

	$( function () {
		initColorPickers();
		initMediaUploader();
		initLivePreview();
	} );

	function initColorPickers() {
		if ( $.fn.wpColorPicker ) {
			$( '.upwa-color-field' ).wpColorPicker( {
				change: function () {
					// wpColorPicker fires this async; defer so the input's
					// value is already updated before we read it.
					window.setTimeout( updatePreview, 10 );
				},
			} );
		}
	}

	function initMediaUploader() {
		var frame;
		var $chooseBtn = $( '#upwa_choose_logo' );
		var $removeBtn = $( '#upwa_remove_logo' );
		var $preview   = $( '#upwa_logo_preview' );
		var $hiddenId  = $( '#upwa_logo_id' );

		$chooseBtn.on( 'click', function ( event ) {
			event.preventDefault();

			if ( frame ) {
				frame.open();
				return;
			}

			frame = wp.media( {
				title: window.upwaAdmin && upwaAdmin.chooseLogoTitle,
				button: { text: window.upwaAdmin && upwaAdmin.chooseLogoButton },
				library: { type: 'image' },
				multiple: false,
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				$hiddenId.val( attachment.id );

				var thumb = ( attachment.sizes && ( attachment.sizes.thumbnail || attachment.sizes.full ) ) || attachment;
				$preview.attr( 'src', thumb.url ).show();
				$removeBtn.show();
			} );

			frame.open();
		} );

		$removeBtn.on( 'click', function ( event ) {
			event.preventDefault();
			$hiddenId.val( '' );
			$preview.hide().attr( 'src', '' );
			$removeBtn.hide();
		} );
	}

	function initLivePreview() {
		$( document ).on( 'input change', '.upwa-preview-field', updatePreview );
	}

	function updatePreview() {
		var name        = $( '#upwa_app_name' ).val();
		var themeColor  = $( 'input[data-preview="theme_color"]' ).val();
		var bgColor     = $( 'input[data-preview="background_color"]' ).val();
		var bannerText  = $( 'textarea[data-preview="banner_text"]' ).val();

		$( '#upwa-banner-preview-name' ).text( name );
		$( '#upwa-banner-preview-desc' ).text( bannerText );

		var $bannerPreview = $( '#upwa-banner-preview' );
		if ( themeColor ) {
			$bannerPreview.css( '--upwa-theme', themeColor );
		}
		if ( bgColor ) {
			$bannerPreview.css( '--upwa-bg', bgColor );
		}

		var $json = $( '#upwa-manifest-preview' );
		try {
			var manifest = JSON.parse( $json.text() );
			manifest.name = name;
			manifest.short_name = $( '#upwa_short_name' ).val();
			if ( themeColor ) {
				manifest.theme_color = themeColor;
			}
			if ( bgColor ) {
				manifest.background_color = bgColor;
			}
			$json.text( JSON.stringify( manifest, null, 2 ) );
		} catch ( e ) {
			// Preview text isn't valid JSON yet (e.g. no icons generated); skip.
		}
	}
} )( jQuery );
