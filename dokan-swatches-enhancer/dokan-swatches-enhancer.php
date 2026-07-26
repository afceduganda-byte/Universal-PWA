<?php
/**
 * Plugin Name:       Dokan Swatches Enhancer
 * Plugin URI:        https://github.com/afceduganda-byte/universal-pwa
 * Description:       Mobile-first color image swatches and size pill buttons for WooCommerce variation forms. Works for any variable product regardless of who created it - store admins editing products directly in WP Admin > Products, and Dokan vendors managing their own listings, both get swatches automatically. Also plays nicely with YayCurrency Pro and PesaPal on multi-vendor marketplaces. Zero configuration required.
 * Version:           1.2.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 5.0
 * WC tested up to:   9.0
 * Author:            Universal PWA
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dokan-swatches-enhancer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

if ( ! class_exists( 'Dokan_Swatches_Enhancer' ) ) :

final class Dokan_Swatches_Enhancer {

	const VERSION = '1.2.1';
	const HANDLE  = 'dokan-swatches-enhancer';

	/** @var Dokan_Swatches_Enhancer|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		/**
		 * WooCommerce embeds all variation data (attributes, images, price_html,
		 * stock state) directly into the `data-product_variations` attribute of
		 * `form.variations_form` as long as the product's variation count stays
		 * under this threshold. Above it, WooCommerce switches to an AJAX lookup
		 * per attribute combination and this plugin has nothing to read from, so
		 * it falls back to the native dropdowns automatically (no breakage).
		 *
		 * Dokan vendors can end up with large variation matrices (many
		 * colors x many sizes), so we raise the default (30) to keep swatches
		 * working for realistic marketplace catalogs.
		 */
		add_filter( 'woocommerce_ajax_variation_threshold', array( $this, 'variation_threshold' ), 10, 2 );
	}

	/**
	 * @param int $threshold
	 * @return int
	 */
	public function variation_threshold( $threshold ) {
		return (int) apply_filters( 'dse_variation_threshold', 100 );
	}

	/**
	 * Intentionally has no Dokan-specific checks (vendor ID, store context,
	 * `dokan()` availability, etc). It only asks WooCommerce "is this a
	 * single variable product page?" - which is true whether the product
	 * was created by a store admin in WP Admin > Products or by a Dokan
	 * vendor in their own dashboard. Both get swatches with zero setup.
	 */
	private function is_target_page() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return false;
		}

		global $product;

		if ( ! $product instanceof WC_Product ) {
			$product = wc_get_product( get_queried_object_id() );
		}

		return $product instanceof WC_Product && $product->is_type( 'variable' );
	}

	public function enqueue_assets() {
		if ( ! class_exists( 'WooCommerce' ) || ! $this->is_target_page() ) {
			return;
		}

		// Style handle: registered with no src so we can attach inline CSS only (no extra HTTP request).
		wp_register_style( self::HANDLE, false, array(), self::VERSION );
		wp_enqueue_style( self::HANDLE );
		wp_add_inline_style( self::HANDLE, $this->inline_css() );

		// Depend on WooCommerce's own variation-form script so we always run after it is initialized.
		wp_register_script( self::HANDLE, false, array( 'jquery', 'wc-add-to-cart-variation' ), self::VERSION, true );
		wp_enqueue_script( self::HANDLE );
		wp_add_inline_script( self::HANDLE, $this->inline_js() );
	}

	private function inline_css() {
		return <<<'CSS'
.dse-hidden-select{position:absolute!important;width:1px!important;height:1px!important;padding:0!important;margin:-1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important;border:0!important}
.dse-hidden-select+.select2-container,.dse-hidden-select~.select2-container{display:none!important}
.dse-hide-row{display:none!important}

/* ---------- Attribute section heading (used for the Colour label) ---------- */
.dse-attr-label{font-size:14px;font-weight:600;margin:0 0 10px;color:#111;line-height:1.3}

/* ---------- Color swatches: label + arrows + scroll viewport + custom scrollbar ---------- */
/* contain:inline-size stops this group's natural (unscrolled) content width from
   bubbling up and forcing a themes flex column (e.g. .summary) to refuse to
   shrink below it - verified this can otherwise widen the whole page past the
   viewport on narrow screens, on themes whose product layout uses flexbox. */
.dse-color-group{margin:10px 0 18px;contain:inline-size}
.dse-color-carousel{position:relative;display:flex;align-items:flex-start;gap:8px}
.dse-color-scroller{flex:1 1 auto;min-width:0;overflow-x:auto;overflow-y:hidden;-webkit-overflow-scrolling:touch;scrollbar-width:none;-ms-overflow-style:none;overscroll-behavior-x:contain}
.dse-color-scroller::-webkit-scrollbar{display:none}
.dse-color-swatches{display:flex;align-items:flex-start;gap:18px;padding:6px 4px 10px;margin:0;list-style:none}

.dse-scroll-arrow{flex:0 0 auto;width:32px;height:32px;margin-top:12px;border-radius:50%;border:1px solid #ddd;background:#fff;color:#111;font-size:16px;line-height:1;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 1px 3px rgba(0,0,0,.12);-webkit-tap-highlight-color:transparent}
.dse-scroll-arrow:hover{background:#f5f5f5}
.dse-scroll-arrow[disabled]{opacity:.3;cursor:default;pointer-events:none}

.dse-scrollbar-track{position:relative;height:4px;border-radius:4px;background:rgba(0,0,0,.08);margin:4px 4px 0}
.dse-scrollbar-track.dse-scrollbar-hidden{display:none}
.dse-scrollbar-thumb{position:absolute;top:0;left:0;height:100%;min-width:24px;border-radius:4px;background:rgba(0,0,0,.35);cursor:grab;touch-action:none}
.dse-scrollbar-thumb:active{cursor:grabbing;background:rgba(0,0,0,.5)}

.dse-color-swatch,.dse-color-swatch-thumb,.dse-size-pill,.dse-scroll-arrow,.dse-scrollbar-thumb{box-sizing:border-box}
.dse-color-swatch{display:flex;flex-direction:column;align-items:center;gap:6px;flex:0 0 auto;width:78px;padding:0;border:0;background:transparent;cursor:pointer;-webkit-tap-highlight-color:transparent}
.dse-color-swatch-thumb{position:relative;width:78px;height:78px;border-radius:12px;border:2px solid transparent;background:#f2f2f2;overflow:hidden;transition:border-color .15s ease,transform .1s ease}
.dse-color-swatch-thumb img{width:100%;height:100%;object-fit:cover;display:block;pointer-events:none}
.dse-color-swatch-thumb--text{display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;color:#333;text-align:center;padding:4px;word-break:break-word}
.dse-color-swatch-caption{width:100%;font-size:11px;line-height:1.3;color:#333;text-align:center;white-space:normal;word-break:break-word}
.dse-color-swatch:active .dse-color-swatch-thumb{transform:scale(.94)}
.dse-color-swatch.selected .dse-color-swatch-thumb{border-color:#111}
.dse-color-swatch.selected .dse-color-swatch-thumb::after{content:"";position:absolute;inset:0;box-shadow:0 0 0 2px #fff inset;border-radius:10px;pointer-events:none}
.dse-color-swatch.selected .dse-color-swatch-caption{font-weight:700;color:#111}
.dse-color-swatch.dse-disabled{opacity:.4;cursor:not-allowed}
.dse-color-swatch.dse-disabled .dse-color-swatch-thumb::before{content:"";position:absolute;left:-6px;right:-6px;top:50%;border-top:1px solid rgba(0,0,0,.6);transform:rotate(-18deg)}

.dse-noselect{-webkit-user-select:none;user-select:none}

@media (min-width:768px){
	.dse-color-swatch{width:88px}
	.dse-color-swatch-thumb{width:88px;height:88px}
}

/* ---------- Size pills ---------- */
.dse-size-pills{display:flex;flex-wrap:wrap;gap:8px;margin:10px 0 16px;list-style:none;padding:0}
.dse-size-pill{min-width:44px;height:40px;padding:0 14px;border-radius:999px;border:1.5px solid #ccc;background:#fff;font-size:13px;font-weight:600;letter-spacing:.02em;cursor:pointer;color:#222;transition:background-color .15s ease,border-color .15s ease,color .15s ease;-webkit-tap-highlight-color:transparent}
.dse-size-pill:hover{border-color:#888}
.dse-size-pill.selected{background:#111;border-color:#111;color:#fff}
.dse-size-pill.dse-disabled{opacity:.45;cursor:not-allowed;text-decoration:line-through;color:#999;background:#f7f7f7}
.dse-size-pill.dse-disabled:hover{border-color:#ccc}

.dse-swatches-relocated{margin-top:8px;margin-bottom:18px}
CSS;
	}

	private function inline_js() {
		return <<<'JS'
(function ($) {
	'use strict';

	if (!$) {
		return;
	}

	function escapeAttrValue(value) {
		if (window.CSS && window.CSS.escape) {
			return window.CSS.escape(String(value));
		}
		return String(value).replace(/([ #;&,.+*~':"!^$\[\]()=>|\/@])/g, '\\$1');
	}

	function DSEProduct($form) {
		this.$form = $form;
		this.variations = [];

		try {
			var raw = $form.attr('data-product_variations');
			var parsed = raw ? JSON.parse(raw) : false;
			this.variations = Array.isArray(parsed) ? parsed : [];
		} catch (err) {
			this.variations = [];
		}

		this.$gallery = $('.woocommerce-product-gallery').first();
		this.originalImage = this.captureOriginalImage();

		this.init();
	}

	DSEProduct.prototype.captureOriginalImage = function () {
		var $slot = this.$gallery.find('.woocommerce-product-gallery__image').first();
		var $img = $slot.find('img').first();
		if (!$img.length) {
			return null;
		}
		var $link = $slot.is('a') ? $slot : $slot.find('a').first();
		return {
			src: $img.attr('src'),
			srcset: $img.attr('srcset') || '',
			sizes: $img.attr('sizes') || '',
			href: $link.length ? $link.attr('href') : null
		};
	};

	DSEProduct.prototype.init = function () {
		var self = this;

		// No embedded variation data (large catalog, AJAX mode) -> leave native dropdowns untouched.
		if (!this.variations.length) {
			return;
		}

		this.$form.find('.variations select').each(function () {
			var $select = $(this);
			var kind = self.detectKind($select);

			if (kind === 'color') {
				self.buildColorSwatches($select);
			} else if (kind === 'size') {
				self.buildSizePills($select);
			}
		});

		this.relocateColorSwatches();
		this.bindFormEvents();
		this.syncInitialState();
	};

	DSEProduct.prototype.detectKind = function ($select) {
		var name = ($select.attr('data-attribute_name') || $select.attr('name') || '').toLowerCase();

		if (name.indexOf('pa_color') !== -1 || name.indexOf('pa_colour') !== -1) {
			return 'color';
		}
		if (name.indexOf('pa_size') !== -1) {
			return 'size';
		}
		// Fallback for vendors using local (non-taxonomy) attributes literally named Color/Size.
		if (name.indexOf('color') !== -1 || name.indexOf('colour') !== -1) {
			return 'color';
		}
		if (name.indexOf('size') !== -1) {
			return 'size';
		}
		return null;
	};

	DSEProduct.prototype.findVariationImage = function (attrKey, value) {
		for (var i = 0; i < this.variations.length; i++) {
			var v = this.variations[i];
			if (v.attributes && v.attributes[attrKey] === value && v.image && v.image.src) {
				return v.image;
			}
		}
		return null;
	};

	DSEProduct.prototype.findVariation = function (attrs) {
		for (var i = 0; i < this.variations.length; i++) {
			var v = this.variations[i];
			var match = true;
			for (var key in attrs) {
				if (!attrs.hasOwnProperty(key)) {
					continue;
				}
				var vVal = v.attributes ? v.attributes[key] : undefined;
				// Empty string in a variation's attribute means "Any <Attribute>" - always matches.
				if (vVal !== undefined && vVal !== '' && vVal !== attrs[key]) {
					match = false;
					break;
				}
			}
			if (match) {
				return v;
			}
		}
		return null;
	};

	/**
	 * Reads the attribute's own WooCommerce-rendered <label> (text + computed
	 * typography) before we hide its table row, so the heading we inject above
	 * the swatches matches this theme's existing "Size" label pixel-for-pixel
	 * instead of us guessing at a hardcoded style.
	 */
	DSEProduct.prototype.captureAttributeLabel = function ($select, fallbackText) {
		var id = $select.attr('id');
		var $label = id ? $('label[for="' + escapeAttrValue(id) + '"]') : $();
		if (!$label.length) {
			$label = $select.closest('tr').find('label').first();
		}

		var style = {};
		if ($label.length && window.getComputedStyle) {
			var computed = window.getComputedStyle($label.get(0));
			['fontSize', 'fontWeight', 'fontFamily', 'color', 'letterSpacing', 'textTransform', 'lineHeight'].forEach(function (prop) {
				style[prop] = computed[prop];
			});
		}

		return {
			text: $label.length ? $label.text().trim() : fallbackText,
			style: style
		};
	};

	DSEProduct.prototype.buildColorSwatches = function ($select) {
		var self = this;
		var attrKey = $select.attr('name');
		var labelInfo = this.captureAttributeLabel($select, 'Colour');

		var $group = $('<div>', { 'class': 'dse-color-group' });
		$group.append(
			$('<div>', { 'class': 'dse-attr-label' }).text(labelInfo.text || 'Colour').css(labelInfo.style)
		);

		var $track = $('<div>', {
			'class': 'dse-color-swatches',
			role: 'listbox',
			'aria-label': labelInfo.text || 'Colour'
		});
		$track.data('attribute', attrKey);
		$track.data('instance', self);

		$select.find('option').each(function () {
			var $opt = $(this);
			var value = $opt.attr('value');
			if (!value) {
				return; // Skip the "Choose an option" placeholder.
			}

			var label = $opt.text();
			var image = self.findVariationImage(attrKey, value);

			var $swatch = $('<button>', {
				type: 'button',
				'class': 'dse-color-swatch',
				role: 'option',
				title: label,
				'aria-label': label,
				'aria-pressed': 'false'
			}).attr('data-value', value);

			var $thumb = $('<span>', { 'class': 'dse-color-swatch-thumb' });

			if (image && image.src) {
				$thumb.append($('<img>', {
					loading: 'lazy',
					alt: '',
					src: image.thumb_src || image.src
				}));
				$swatch.append($thumb);
				$swatch.append($('<span>', { 'class': 'dse-color-swatch-caption' }).text(label));
			} else {
				// No variation image found for this color -> degrade gracefully to a text swatch.
				$thumb.addClass('dse-color-swatch-thumb--text').text(label);
				$swatch.append($thumb);
			}

			if ($opt.prop('disabled')) {
				$swatch.addClass('dse-disabled').prop('disabled', true).attr('aria-disabled', 'true');
			}

			$track.append($swatch);
		});

		var $scroller = $('<div>', { 'class': 'dse-color-scroller' }).append($track);
		var $prevBtn = $('<button>', { type: 'button', 'class': 'dse-scroll-arrow dse-scroll-arrow--prev', 'aria-label': 'Scroll left' }).html('&#8249;');
		var $nextBtn = $('<button>', { type: 'button', 'class': 'dse-scroll-arrow dse-scroll-arrow--next', 'aria-label': 'Scroll right' }).html('&#8250;');
		var $carousel = $('<div>', { 'class': 'dse-color-carousel' }).append($prevBtn, $scroller, $nextBtn);
		var $scrollbarThumb = $('<div>', { 'class': 'dse-scrollbar-thumb' });
		var $scrollbarTrack = $('<div>', { 'class': 'dse-scrollbar-track' }).append($scrollbarThumb);

		$group.append($carousel, $scrollbarTrack);

		$select.addClass('dse-hidden-select').data('dse-wrap', $track);
		$select.after($group);

		// Deliberately NOT calling initColorCarousel() here yet. At this point the
		// group still sits inside the WooCommerce variations <table>, which uses
		// the browser default table-layout:auto - that sizes a table column to fit
		// its content's natural (unconstrained) width, not the space actually
		// available. With 6-8+ color swatches that natural width is easily
		// 700-800px+, and letting the browser lay that out even briefly can balloon
		// the whole table/summary column past the viewport (verified: on a 390px
		// mobile viewport this pushed window.innerWidth to 485px before the fix).
		// initColorCarousel() is called from relocateColorSwatches() instead, once
		// the group has already been moved out of the table and the measurements
		// it takes reflect real, final layout.
		$group.data('carousel-refs', {
			$carousel: $carousel,
			$scroller: $scroller,
			$track: $track,
			$prevBtn: $prevBtn,
			$nextBtn: $nextBtn,
			$scrollbarTrack: $scrollbarTrack,
			$scrollbarThumb: $scrollbarThumb
		});
	};

	/**
	 * Wires the scroll viewport up to: (a) left/right arrow buttons, (b) a
	 * custom draggable scrollbar thumb mirroring native scroll position, and
	 * (c) a deliberate "half swatch" cutoff at the trailing edge whenever the
	 * swatches overflow, so shoppers get a visual hint that more colors exist.
	 * All measurements are taken from the live DOM so it stays correct across
	 * breakpoints and whatever number of colors a vendor's product has.
	 */
	DSEProduct.prototype.initColorCarousel = function ($carousel, $scroller, $track, $prevBtn, $nextBtn, $scrollbarTrack, $scrollbarThumb) {
		var dragging = false;
		var dragStartX = 0;
		var dragStartScrollLeft = 0;
		var scrollerEl = $scroller.get(0);

		// scrollWidth reflects the true overflowing content extent. $track.outerWidth()
		// would not: .dse-color-swatches is a plain block box inside the scroller, so its
		// own width just fills the parent (normal block `width:auto` behavior) - it does
		// not grow to fit its nowrap flex children, even though they visually overflow it.
		function maxScroll() {
			return Math.max(0, scrollerEl.scrollWidth - $scroller.innerWidth());
		}

		function updateScrollbar() {
			var trackWidth = scrollerEl.scrollWidth;
			var viewportWidth = $scroller.innerWidth();

			if (trackWidth <= viewportWidth + 1) {
				$scrollbarTrack.addClass('dse-scrollbar-hidden');
				$prevBtn.prop('disabled', true);
				$nextBtn.prop('disabled', true);
				return;
			}

			$scrollbarTrack.removeClass('dse-scrollbar-hidden');

			var scrollable = trackWidth - viewportWidth;
			var scrollRatio = scrollable > 0 ? (scrollerEl.scrollLeft / scrollable) : 0;
			var trackBoxWidth = $scrollbarTrack.width();
			var thumbWidth = Math.max(24, (viewportWidth / trackWidth) * trackBoxWidth);
			var thumbLeft = scrollRatio * Math.max(0, trackBoxWidth - thumbWidth);

			$scrollbarThumb.css({ width: thumbWidth + 'px', left: thumbLeft + 'px' });
			$prevBtn.prop('disabled', scrollerEl.scrollLeft <= 1);
			$nextBtn.prop('disabled', scrollerEl.scrollLeft >= scrollable - 1);
		}

		function applyPartialCutoff() {
			var $items = $track.children('.dse-color-swatch');
			if ($items.length < 2) {
				return;
			}

			var containerWidth = $carousel.parent().innerWidth() || $carousel.innerWidth();
			var arrowsWidth = ($prevBtn.outerWidth(true) || 0) + ($nextBtn.outerWidth(true) || 0);
			var available = Math.max(120, containerWidth - arrowsWidth);
			var itemOuter = $items.eq(0).outerWidth(true);

			if (!itemOuter) {
				return;
			}

			var naturalTrackWidth = 0;
			$items.each(function () {
				naturalTrackWidth += $(this).outerWidth(true);
			});

			if (naturalTrackWidth <= available) {
				$scroller.css('max-width', '');
			} else {
				var itemsFit = Math.max(1, Math.floor(available / itemOuter));
				var visible = Math.min(available, (itemsFit + 0.5) * itemOuter);
				$scroller.css('max-width', Math.floor(visible) + 'px');
			}

			updateScrollbar();
		}

		$scroller.on('scroll', function () {
			if (!dragging) {
				updateScrollbar();
			}
		});

		$prevBtn.on('click', function () {
			$scroller.stop(true).animate({ scrollLeft: '-=' + ($scroller.innerWidth() * 0.8) }, 250);
		});

		$nextBtn.on('click', function () {
			$scroller.stop(true).animate({ scrollLeft: '+=' + ($scroller.innerWidth() * 0.8) }, 250);
		});

		$scrollbarThumb.on('mousedown touchstart', function (e) {
			dragging = true;
			dragStartX = (e.type === 'touchstart') ? e.originalEvent.touches[0].clientX : e.clientX;
			dragStartScrollLeft = scrollerEl.scrollLeft;
			$(document.body).addClass('dse-noselect');
			e.preventDefault();
		});

		$(document).on('mousemove touchmove', function (e) {
			if (!dragging) {
				return;
			}
			var clientX = (e.type === 'touchmove') ? e.originalEvent.touches[0].clientX : e.clientX;
			var trackBoxWidth = $scrollbarTrack.width();
			var thumbWidth = $scrollbarThumb.width();
			var deltaRatio = (clientX - dragStartX) / Math.max(1, (trackBoxWidth - thumbWidth));
			scrollerEl.scrollLeft = Math.min(maxScroll(), Math.max(0, dragStartScrollLeft + deltaRatio * maxScroll()));
			updateScrollbar();
		});

		$(document).on('mouseup touchend', function () {
			if (dragging) {
				dragging = false;
				$(document.body).removeClass('dse-noselect');
			}
		});

		$scrollbarTrack.on('mousedown', function (e) {
			if ($(e.target).is($scrollbarThumb)) {
				return;
			}
			var trackBoxWidth = $scrollbarTrack.width();
			var thumbWidth = $scrollbarThumb.width();
			var offsetX = e.pageX - $scrollbarTrack.offset().left - (thumbWidth / 2);
			var ratio = Math.min(1, Math.max(0, offsetX / Math.max(1, (trackBoxWidth - thumbWidth))));
			scrollerEl.scrollLeft = ratio * maxScroll();
			updateScrollbar();
		});

		$(window).on('resize.dse-color-carousel', applyPartialCutoff);
		$(window).on('load', applyPartialCutoff);

		applyPartialCutoff();
	};

	DSEProduct.prototype.buildSizePills = function ($select) {
		var self = this;
		var attrKey = $select.attr('name');
		var $wrap = $('<div>', {
			'class': 'dse-size-pills',
			role: 'listbox',
			'aria-label': 'Size'
		});
		$wrap.data('attribute', attrKey);
		$wrap.data('instance', self);

		$select.find('option').each(function () {
			var $opt = $(this);
			var value = $opt.attr('value');
			if (!value) {
				return;
			}

			var label = $opt.text();
			var $pill = $('<button>', {
				type: 'button',
				'class': 'dse-size-pill',
				role: 'option',
				'aria-pressed': 'false',
				text: label
			}).attr('data-value', value);

			if ($opt.prop('disabled')) {
				$pill.addClass('dse-disabled').prop('disabled', true).attr('aria-disabled', 'true');
			}

			$wrap.append($pill);
		});

		$select.addClass('dse-hidden-select').data('dse-wrap', $wrap);
		$select.after($wrap);
	};

	/**
	 * Moves the color group (heading + carousel) out of the variations table
	 * and up next to the price, matching the "carousel under the title/price"
	 * requirement. Only this wrapper moves; the underlying <select> stays put
	 * so WooCommerce's own variation-matching logic keeps working unmodified.
	 */
	DSEProduct.prototype.relocateColorSwatches = function () {
		var $colorGroup = this.$form.find('.dse-color-group').first();
		if (!$colorGroup.length) {
			return;
		}

		var $summary = this.$form.closest('.summary');
		if (!$summary.length) {
			$summary = $('.summary.entry-summary').first();
		}
		var $price = $summary.find('.price').first();

		if ($price.length) {
			var $select = $colorGroup.prev('select.dse-hidden-select');
			var $row = $select.length ? $select.closest('tr') : null;

			$colorGroup.addClass('dse-swatches-relocated');
			$price.after($colorGroup);

			if ($row && $row.length) {
				$row.addClass('dse-hide-row');
			}
		}
		// If no .price was found (unusual theme markup), the group is left where
		// it was built and initColorCarousel() still runs below - it just won't
		// be relocated next to the price.

		// initColorCarousel() (which measures real widths and applies the cutoff)
		// is deliberately called here, AFTER the group has reached its final
		// position, and not from buildColorSwatches() - see the note there on why
		// measuring while still inside the variations <table> is unsafe.
		var refs = $colorGroup.data('carousel-refs');
		if (refs) {
			this.initColorCarousel(refs.$carousel, refs.$scroller, refs.$track, refs.$prevBtn, refs.$nextBtn, refs.$scrollbarTrack, refs.$scrollbarThumb);
		}
	};

	DSEProduct.prototype.bindFormEvents = function () {
		var self = this;

		// WooCommerce fires this after it recalculates which options are still
		// selectable for the current combination - mirror that onto our swatches/pills.
		this.$form.on('woocommerce_update_variation_values', function () {
			self.syncDisabledStates();
		});

		this.$form.on('found_variation', function (e, variation) {
			if (variation && variation.image && variation.image.src) {
				self.swapGalleryImage(variation.image);
			}
		});

		this.$form.on('reset_data', function () {
			self.resetGalleryImage();
			// Not $form.find('.dse-color-swatch, ...') - the color group has been
			// relocated OUT of the form (next to the price), so it would silently
			// miss those buttons and leave the color swatch looking "selected"
			// after Clear. syncSelectedState uses the stored dse-wrap reference,
			// which stays valid regardless of where the element now lives in the DOM.
			self.$form.find('.variations select').each(function () {
				self.syncSelectedState($(this));
			});
		});

		this.$form.on('change', '.variations select', function () {
			var $select = $(this);
			self.syncSelectedState($select);

			if (self.detectKind($select) === 'color') {
				self.maybeSwapForColor($select);
			}
		});
	};

	DSEProduct.prototype.syncSelectedState = function ($select) {
		var $wrap = $select.data('dse-wrap');
		if (!$wrap) {
			return;
		}
		var value = $select.val();
		$wrap.find('button').removeClass('selected').attr('aria-pressed', 'false');
		if (value) {
			$wrap.find('[data-value="' + escapeAttrValue(value) + '"]').addClass('selected').attr('aria-pressed', 'true');
		}
	};

	DSEProduct.prototype.syncDisabledStates = function () {
		this.$form.find('.variations select').each(function () {
			var $select = $(this);
			var $wrap = $select.data('dse-wrap');
			if (!$wrap) {
				return;
			}
			$select.find('option').each(function () {
				var $opt = $(this);
				var value = $opt.attr('value');
				if (!value) {
					return;
				}
				var disabled = !!$opt.prop('disabled');
				$wrap
					.find('[data-value="' + escapeAttrValue(value) + '"]')
					.toggleClass('dse-disabled', disabled)
					.prop('disabled', disabled)
					.attr('aria-disabled', disabled ? 'true' : 'false');
			});
		});
	};

	/**
	 * Swaps the main gallery image the instant a color is picked, even if no
	 * size has been chosen yet (a plain WooCommerce `found_variation` only
	 * fires once every attribute is selected, which is too late for this UX).
	 */
	DSEProduct.prototype.maybeSwapForColor = function ($select) {
		var value = $select.val();

		if (!value) {
			this.resetGalleryImage();
			return;
		}

		var attrs = {};
		this.$form.find('.variations select').each(function () {
			var v = $(this).val();
			if (v) {
				attrs[$(this).attr('name')] = v;
			}
		});

		var variation = this.findVariation(attrs);
		var image = (variation && variation.image && variation.image.src)
			? variation.image
			: this.findVariationImage($select.attr('name'), value);

		if (image) {
			this.swapGalleryImage(image);
		}
	};

	DSEProduct.prototype.swapGalleryImage = function (image) {
		if (!image || !image.src || !this.$gallery.length) {
			return;
		}

		var $slot = this.$gallery.find('.woocommerce-product-gallery__image').first();
		var $img = $slot.find('img').first();
		if (!$img.length) {
			return;
		}

		$img.attr({
			src: image.src,
			srcset: image.srcset || '',
			sizes: image.sizes || '',
			alt: image.alt || $img.attr('alt') || ''
		});

		var $link = $slot.is('a') ? $slot : $slot.find('a').first();
		if ($link.length) {
			$link.attr('href', image.full_src || image.src);
		}

		// Best-effort: if a matching thumbnail exists in the nav strip, bring it into focus too.
		var thumbSrc = image.thumb_src || image.src;
		this.$gallery.find('.flex-control-nav img, .woocommerce-product-gallery__thumb img').each(function () {
			if (thumbSrc && this.src && this.src.split('?')[0] === thumbSrc.split('?')[0]) {
				$(this).closest('a, li').trigger('click');
			}
		});
	};

	DSEProduct.prototype.resetGalleryImage = function () {
		if (!this.originalImage || !this.$gallery.length) {
			return;
		}

		var $slot = this.$gallery.find('.woocommerce-product-gallery__image').first();
		var $img = $slot.find('img').first();
		if (!$img.length) {
			return;
		}

		$img.attr({
			src: this.originalImage.src,
			srcset: this.originalImage.srcset,
			sizes: this.originalImage.sizes
		});

		var $link = $slot.is('a') ? $slot : $slot.find('a').first();
		if ($link.length && this.originalImage.href) {
			$link.attr('href', this.originalImage.href);
		}
	};

	DSEProduct.prototype.syncInitialState = function () {
		var self = this;

		this.syncDisabledStates();

		this.$form.find('.variations select').each(function () {
			self.syncSelectedState($(this));
		});

		var $colorSelect = this.$form.find('.variations select').filter(function () {
			return self.detectKind($(this)) === 'color';
		}).first();

		if ($colorSelect.length && $colorSelect.val()) {
			self.maybeSwapForColor($colorSelect);
		}
	};

	$(function () {
		$('.variations_form').each(function () {
			var $form = $(this);
			if ($form.data('dse-initialized')) {
				return;
			}
			$form.data('dse-initialized', true);
			new DSEProduct($form);
		});

		// Single delegated handler (survives the color swatches being moved next to the price).
		$(document).on('click', '.dse-color-swatch, .dse-size-pill', function (e) {
			e.preventDefault();

			var $btn = $(this);
			if ($btn.prop('disabled') || $btn.hasClass('dse-disabled')) {
				return;
			}

			var $wrap = $btn.closest('.dse-color-swatches, .dse-size-pills');
			var instance = $wrap.data('instance');
			if (!instance) {
				return;
			}

			var attrName = $wrap.data('attribute');
			var $select = instance.$form.find('select[name="' + attrName + '"]');
			if (!$select.length) {
				return;
			}

			var value = $btn.attr('data-value');
			var next = ($select.val() === String(value)) ? '' : value;

			$select.val(next).trigger('change');
		});

		// No separate handler for WooCommerce's native "Clear" (.reset_variations)
		// link needed: clicking it empties every select, which WooCommerce always
		// follows with its own `reset_data` event - already handled above, and
		// correctly (see the dse-wrap-based fix note on that handler).
	});
})(window.jQuery);
JS;
	}
}

endif;

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-warning"><p>' .
				esc_html__( 'Dokan Swatches Enhancer requires WooCommerce to be installed and active.', 'dokan-swatches-enhancer' ) .
				'</p></div>';
		} );
		return;
	}

	Dokan_Swatches_Enhancer::instance();
} );
