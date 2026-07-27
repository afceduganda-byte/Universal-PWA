<?php
/**
 * Plugin Name:       Dokan Swatches Enhancer
 * Plugin URI:        https://github.com/afceduganda-byte/universal-pwa
 * Description:       Mobile-first color image swatches and size pill buttons for WooCommerce variation forms. Works for any variable product regardless of who created it - store admins editing products directly in WP Admin > Products, and Dokan vendors managing their own listings, both get swatches automatically. Also plays nicely with YayCurrency Pro and PesaPal on multi-vendor marketplaces. Zero configuration required.
 * Version:           1.3.0
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

	const VERSION = '1.3.0';
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
.dse-attr-label{font-size:14px;font-weight:600;margin:0;color:#111;line-height:1.3}

/* ---------- Color swatches: header row (label + arrows) + bordered native-scroll row ---------- */
/* contain:inline-size stops this group's natural (unscrolled) content width from
   bubbling up and forcing a themes flex column (e.g. .summary) to refuse to
   shrink below it - verified this can otherwise widen the whole page past the
   viewport on narrow screens, on themes whose product layout uses flexbox. */
.dse-color-group{margin:10px 0 18px;contain:inline-size}

.dse-color-header{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:0 0 10px}
.dse-color-arrows{display:flex;gap:8px;flex:0 0 auto}
.dse-color-arrows.dse-color-arrows--hidden{display:none}

.dse-scroll-arrow{flex:0 0 auto;width:34px;height:34px;border-radius:50%;border:1.5px solid #ddd;background:#fff;color:#111;font-size:18px;font-weight:700;line-height:1;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 1px 3px rgba(0,0,0,.12);transition:opacity .2s ease,background-color .15s ease;-webkit-tap-highlight-color:transparent;box-sizing:border-box}
.dse-scroll-arrow:hover{background:#f5f5f5}
.dse-scroll-arrow[disabled]{opacity:.25;cursor:default;pointer-events:none;animation:none!important}

/* Subtle attention-drawing pulse on the "next" arrow - only while it's actually
   usable. [disabled] above forces animation:none, so this stops automatically
   both when nothing needs scrolling at all and once the user reaches the end.
   Runs a handful of times rather than forever: enough to catch the eye without
   leaving a permanently-moving element on the page (an infinite animation is
   both a lingering distraction and something prefers-reduced-motion users -
   and motion-sensitive shoppers generally - should not be stuck with). */
@keyframes dse-arrow-pulse{0%,100%{transform:translateX(0)}50%{transform:translateX(3px)}}
.dse-scroll-arrow--next:not([disabled]){animation-name:dse-arrow-pulse;animation-duration:1.4s;animation-timing-function:ease-in-out;animation-iteration-count:4}
@media (prefers-reduced-motion:reduce){
	.dse-scroll-arrow--next{animation:none!important}
}

/* The swatch row IS the scroll container (native overflow-x:auto touch/swipe,
   no wrapper element) and the styled "dedicated container" (light border +
   subtle background) at the same time. */
.dse-color-swatches{display:flex;align-items:flex-start;gap:18px;overflow-x:auto;-webkit-overflow-scrolling:touch;overscroll-behavior-x:contain;scrollbar-width:none;-ms-overflow-style:none;border:1px solid #e8e8e8;background:#fafafa;border-radius:14px;padding:14px;margin:0;list-style:none;transition:border-color .15s ease,background-color .15s ease}
.dse-color-swatches::-webkit-scrollbar{display:none}
.dse-color-swatches.dse-attr-error{border-color:#d32f2f;background:#fff6f6}

.dse-color-swatch,.dse-color-swatch-thumb,.dse-size-pill{box-sizing:border-box}
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

@media (min-width:768px){
	.dse-color-swatch{width:88px}
	.dse-color-swatch-thumb{width:88px;height:88px}
}

/* ---------- Size pills ---------- */
.dse-size-pills{display:flex;flex-wrap:wrap;gap:8px;margin:10px 0 16px;list-style:none;padding:0;transition:outline-color .15s ease}
.dse-size-pill{min-width:44px;height:40px;padding:0 14px;border-radius:999px;border:1.5px solid #ccc;background:#fff;font-size:13px;font-weight:600;letter-spacing:.02em;cursor:pointer;color:#222;transition:background-color .15s ease,border-color .15s ease,color .15s ease;-webkit-tap-highlight-color:transparent}
.dse-size-pill:hover{border-color:#888}
.dse-size-pill.selected{background:#111;border-color:#111;color:#fff}
.dse-size-pill.dse-disabled{opacity:.45;cursor:not-allowed;text-decoration:line-through;color:#999;background:#f7f7f7}
.dse-size-pill.dse-disabled:hover{border-color:#ccc}
.dse-size-pills.dse-attr-error{outline:2px solid #d32f2f;outline-offset:6px;border-radius:12px}

/* ---------- "Please select a Colour/Size" validation notice ---------- */
.dse-validation-notice{background:#fdecea;border:1px solid #f5c2c0;color:#9a1c1c;font-size:13px;line-height:1.4;padding:10px 14px;border-radius:8px;margin:0 0 14px}

/* ---------- Smooth cross-fade when the gallery image swaps ---------- */
.dse-gallery-fade{transition:opacity .18s ease}

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
		$select.data('dse-label-text', labelInfo.text || 'Colour');

		var $label = $('<div>', { 'class': 'dse-attr-label' }).text(labelInfo.text || 'Colour').css(labelInfo.style);
		var $prevBtn = $('<button>', { type: 'button', 'class': 'dse-scroll-arrow dse-scroll-arrow--prev', 'aria-label': 'Scroll left' }).html('&laquo;');
		var $nextBtn = $('<button>', { type: 'button', 'class': 'dse-scroll-arrow dse-scroll-arrow--next', 'aria-label': 'Scroll right' }).html('&raquo;');
		var $arrowsWrap = $('<div>', { 'class': 'dse-color-arrows' }).append($prevBtn, $nextBtn);
		var $header = $('<div>', { 'class': 'dse-color-header' }).append($label, $arrowsWrap);

		var $row = $('<div>', {
			'class': 'dse-color-swatches',
			role: 'listbox',
			'aria-label': labelInfo.text || 'Colour'
		});
		$row.data('attribute', attrKey);
		$row.data('instance', self);

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

			$row.append($swatch);
		});

		var $group = $('<div>', { 'class': 'dse-color-group' }).append($header, $row);

		$select.addClass('dse-hidden-select').data('dse-wrap', $row);
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
			$group: $group,
			$row: $row,
			$prevBtn: $prevBtn,
			$nextBtn: $nextBtn,
			$arrowsWrap: $arrowsWrap
		});
	};

	/**
	 * Wires the swatch row - which is itself the native-scrolling element - up
	 * to: (a) left/right arrow buttons (hidden entirely when nothing needs
	 * scrolling, pulsing on the "next" arrow via CSS while it's usable), and
	 * (b) a deliberate "half swatch" cutoff at the trailing edge whenever the
	 * swatches overflow, so shoppers get a visual hint that more colors exist.
	 * All measurements are taken from the live DOM so it stays correct across
	 * breakpoints and whatever number of colors a vendor's product has.
	 */
	DSEProduct.prototype.initColorCarousel = function ($group, $row, $prevBtn, $nextBtn, $arrowsWrap) {
		var rowEl = $row.get(0);

		function updateArrowState() {
			var overflowing = rowEl.scrollWidth > rowEl.clientWidth + 1;

			if (!overflowing) {
				$arrowsWrap.addClass('dse-color-arrows--hidden');
				$prevBtn.prop('disabled', true);
				$nextBtn.prop('disabled', true);
				return;
			}

			$arrowsWrap.removeClass('dse-color-arrows--hidden');
			var scrollable = rowEl.scrollWidth - rowEl.clientWidth;
			$prevBtn.prop('disabled', rowEl.scrollLeft <= 1);
			$nextBtn.prop('disabled', rowEl.scrollLeft >= scrollable - 1);
		}

		function applyPartialCutoff() {
			var $items = $row.children('.dse-color-swatch');
			if ($items.length < 2) {
				return;
			}

			var containerWidth = $group.innerWidth();
			var itemOuter = $items.eq(0).outerWidth(true);
			if (!containerWidth || !itemOuter) {
				return;
			}

			var naturalWidth = 0;
			$items.each(function () {
				naturalWidth += $(this).outerWidth(true);
			});

			if (naturalWidth <= containerWidth) {
				$row.css('max-width', '');
			} else {
				var itemsFit = Math.max(1, Math.floor(containerWidth / itemOuter));
				var visible = Math.min(containerWidth, (itemsFit + 0.5) * itemOuter);
				$row.css('max-width', Math.floor(visible) + 'px');
			}

			updateArrowState();
		}

		$row.on('scroll', updateArrowState);

		$prevBtn.on('click', function () {
			$row.stop(true).animate({ scrollLeft: '-=' + (rowEl.clientWidth * 0.8) }, 250);
		});

		$nextBtn.on('click', function () {
			$row.stop(true).animate({ scrollLeft: '+=' + (rowEl.clientWidth * 0.8) }, 250);
		});

		$(window).on('resize.dse-color-carousel', applyPartialCutoff);
		$(window).on('load', applyPartialCutoff);

		applyPartialCutoff();
	};

	DSEProduct.prototype.buildSizePills = function ($select) {
		var self = this;
		var attrKey = $select.attr('name');
		// Captured for the "Please select a Size" validation message only - no
		// heading is injected here (unlike Colour), since the theme's own native
		// <label> for this row is left visible and already serves that purpose.
		var labelInfo = this.captureAttributeLabel($select, 'Size');
		$select.data('dse-label-text', labelInfo.text || 'Size');

		var $wrap = $('<div>', {
			'class': 'dse-size-pills',
			role: 'listbox',
			'aria-label': labelInfo.text || 'Size'
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
			this.initColorCarousel(refs.$group, refs.$row, refs.$prevBtn, refs.$nextBtn, refs.$arrowsWrap);
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

			var $wrap = $select.data('dse-wrap');
			if ($wrap && $select.val()) {
				$wrap.removeClass('dse-attr-error');
			}
			if (self.$noticeEl && self.getMissingAttributes().length === 0) {
				self.clearValidationNotice();
			}

			if (self.detectKind($select) === 'color') {
				self.maybeSwapForColor($select);
			}
		});

		// Covers a plain (non-AJAX) form submission. AJAX add-to-cart, which most
		// WooCommerce themes use, intercepts the *button click* instead of letting
		// the form submit natively - that path is covered separately, in capture
		// phase, in the bootstrap below.
		this.$form.on('submit', function (e) {
			var missing = self.getMissingAttributes();
			if (missing.length) {
				e.preventDefault();
				e.stopImmediatePropagation();
				self.showValidationNotice(missing);
			}
		});
	};

	/**
	 * @return {Array<{$select: jQuery, label: string}>}
	 */
	DSEProduct.prototype.getMissingAttributes = function () {
		var missing = [];
		this.$form.find('.variations select').each(function () {
			var $select = $(this);
			if (!$select.val()) {
				missing.push({
					$select: $select,
					label: $select.data('dse-label-text') || 'option'
				});
			}
		});
		return missing;
	};

	DSEProduct.prototype.showValidationNotice = function (missing) {
		this.clearValidationNotice();

		missing.forEach(function (m) {
			var $wrap = m.$select.data('dse-wrap');
			if ($wrap) {
				$wrap.addClass('dse-attr-error');
			}
		});

		var labels = missing.map(function (m) { return m.label; });
		var message = 'Please select a ' + labels.join(' and a ') + ' before adding to cart.';

		var $notice = $('<div>', { 'class': 'dse-validation-notice', role: 'alert' }).text(message);
		this.$noticeEl = $notice;

		// Anchored near the price/color carousel (not deep inside the form) so it
		// stays visible regardless of which specific attribute is missing - the
		// color group itself may have been relocated away from the form entirely.
		var $anchor = this.$form.closest('.summary').find('.dse-color-group').first();
		if (!$anchor.length) {
			$anchor = this.$form;
		}
		$anchor.before($notice);

		var $scrollTarget = missing[0].$select.data('dse-wrap');
		var scrollEl = ($scrollTarget && $scrollTarget.get(0)) || $notice.get(0);
		if (scrollEl && scrollEl.scrollIntoView) {
			scrollEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
		}
	};

	DSEProduct.prototype.clearValidationNotice = function () {
		if (this.$noticeEl) {
			this.$noticeEl.remove();
			this.$noticeEl = null;
		}
		this.$form.find('.variations select').each(function () {
			var $wrap = $(this).data('dse-wrap');
			if ($wrap) {
				$wrap.removeClass('dse-attr-error');
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

		// Smooth cross-fade instead of an abrupt pop: dim slightly, swap the
		// source, then fade back in once the new image has actually loaded (with
		// a timed fallback for cached images / data-URI sources where a fresh
		// 'load' event may not fire).
		$img.addClass('dse-gallery-fade').css('opacity', '0.35');

		$img.attr({
			src: image.src,
			srcset: image.srcset || '',
			sizes: image.sizes || '',
			alt: image.alt || $img.attr('alt') || ''
		});

		$img.off('load.dseFade').one('load.dseFade', function () {
			$img.css('opacity', '1');
		});
		setTimeout(function () {
			$img.css('opacity', '1');
		}, 220);

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
			$form.data('dse-instance', new DSEProduct($form));
		});

		/**
		 * Most WooCommerce themes use AJAX add-to-cart, which intercepts the
		 * button's *click* directly rather than letting the form's native
		 * `submit` event fire at all - so a plain form 'submit' handler alone
		 * (see bindFormEvents) isn't reliable for catching a missing selection.
		 * Listening in the CAPTURE phase on document guarantees this runs before
		 * any bubble-phase click handler bound elsewhere - including WooCommerce
		 * core's own add-to-cart script - regardless of script load order.
		 */
		document.addEventListener('click', function (e) {
			var target = e.target;
			var btn = target && target.closest ? target.closest('.single_add_to_cart_button') : null;
			if (!btn) {
				return;
			}

			var $form = $(btn).closest('.variations_form');
			var instance = $form.data('dse-instance');
			if (!instance) {
				return;
			}

			var missing = instance.getMissingAttributes();
			if (missing.length) {
				e.preventDefault();
				e.stopImmediatePropagation();
				instance.showValidationNotice(missing);
			}
		}, true);

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
