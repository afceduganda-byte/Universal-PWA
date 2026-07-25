<?php
/**
 * Plugin Name:       Dokan Swatches Enhancer
 * Plugin URI:        https://github.com/afceduganda-byte/universal-pwa
 * Description:       Mobile-first color image swatches and size pill buttons for WooCommerce variation forms. Built for multi-vendor marketplaces running Dokan Pro, YayCurrency Pro and PesaPal. Zero configuration for vendors.
 * Version:           1.0.0
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

	const VERSION = '1.0.0';
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

/* ---------- Color swatches: mobile-first swipeable strip ---------- */
.dse-color-swatches{display:flex;align-items:flex-start;gap:10px;overflow-x:auto;overscroll-behavior-x:contain;-webkit-overflow-scrolling:touch;scroll-snap-type:x mandatory;padding:12px 2px 16px;margin:10px 0 16px;list-style:none;scrollbar-width:thin}
.dse-color-swatches::-webkit-scrollbar{height:4px}
.dse-color-swatches::-webkit-scrollbar-thumb{background:rgba(0,0,0,.2);border-radius:4px}
.dse-color-swatch{flex:0 0 auto;scroll-snap-align:start;width:56px;height:56px;padding:0;border-radius:10px;border:2px solid transparent;background:#f2f2f2;cursor:pointer;overflow:hidden;position:relative;line-height:0;transition:border-color .15s ease,transform .1s ease;-webkit-tap-highlight-color:transparent}
.dse-color-swatch img{width:100%;height:100%;object-fit:cover;display:block;pointer-events:none}
.dse-color-swatch--text{display:flex;align-items:center;justify-content:center;font-size:11px;line-height:1.25;padding:4px;text-align:center;color:#333;font-weight:600}
.dse-color-swatch:active{transform:scale(.94)}
.dse-color-swatch.selected{border-color:#111}
.dse-color-swatch.selected::after{content:"";position:absolute;inset:0;box-shadow:0 0 0 2px #fff inset;border-radius:8px;pointer-events:none}
.dse-color-swatch.dse-disabled{opacity:.35;cursor:not-allowed}
.dse-color-swatch.dse-disabled::before{content:"";position:absolute;left:-4px;right:-4px;top:50%;border-top:1px solid rgba(0,0,0,.6);transform:rotate(-20deg)}

@media (min-width:768px){
	.dse-color-swatches{flex-wrap:wrap;overflow-x:visible;scroll-snap-type:none;padding:8px 0 16px}
	.dse-color-swatch{width:48px;height:48px}
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

	DSEProduct.prototype.buildColorSwatches = function ($select) {
		var self = this;
		var attrKey = $select.attr('name');
		var $wrap = $('<div>', {
			'class': 'dse-color-swatches',
			role: 'listbox',
			'aria-label': 'Color'
		});
		$wrap.data('attribute', attrKey);
		$wrap.data('instance', self);

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

			if (image && image.src) {
				$swatch.append($('<img>', {
					loading: 'lazy',
					alt: '',
					src: image.thumb_src || image.src
				}));
			} else {
				// No variation image found for this color -> degrade gracefully to a text swatch.
				$swatch.addClass('dse-color-swatch--text').text(label);
			}

			if ($opt.prop('disabled')) {
				$swatch.addClass('dse-disabled').prop('disabled', true).attr('aria-disabled', 'true');
			}

			$wrap.append($swatch);
		});

		$select.addClass('dse-hidden-select').data('dse-wrap', $wrap);
		$select.after($wrap);
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
	 * Moves the color swatches out of the variations table and up next to the
	 * price, matching the "carousel under the title/price" requirement. Only
	 * the swatches element moves; the underlying <select> stays put so
	 * WooCommerce's own variation-matching logic keeps working unmodified.
	 */
	DSEProduct.prototype.relocateColorSwatches = function () {
		var $summary = this.$form.closest('.summary');
		if (!$summary.length) {
			$summary = $('.summary.entry-summary').first();
		}
		var $price = $summary.find('.price').first();
		var $colorWrap = this.$form.find('.dse-color-swatches').first();

		if (!$price.length || !$colorWrap.length) {
			return;
		}

		var $select = $colorWrap.prev('select.dse-hidden-select');
		var $row = $select.length ? $select.closest('tr') : null;

		$colorWrap.addClass('dse-swatches-relocated');
		$price.after($colorWrap);

		if ($row && $row.length) {
			$row.addClass('dse-hide-row');
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
			self.$form.find('.dse-color-swatch, .dse-size-pill').removeClass('selected').attr('aria-pressed', 'false');
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

		// Clicking WooCommerce's native "Clear" link should also clear our swatch UI.
		$(document).on('click', '.reset_variations', function () {
			var $form = $(this).closest('.variations_form');
			$form.find('.dse-color-swatch, .dse-size-pill').removeClass('selected').attr('aria-pressed', 'false');
		});
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
