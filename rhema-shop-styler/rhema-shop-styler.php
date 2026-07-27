<?php
/**
 * Plugin Name:       Rhema Fashion Shop Grid Styler
 * Plugin URI:        https://github.com/afceduganda-byte/universal-pwa
 * Description:       Pure-CSS visual styling for the ShopEngine product grid (cards, "View Product" buttons, Sale badges, titles and prices). Injects styles only via wp_head - no existing functionality, markup, or behavior is changed.
 * Version:           1.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Rhema Fashion
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rhema-shop-styler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

/**
 * Everything below is styling only (a single <style> block echoed into
 * wp_head). Nothing here touches templates, hooks into WooCommerce/ShopEngine
 * logic, or alters markup - so it cannot change any existing functionality.
 *
 * Selector strategy (v1.1.0): v1.0.0 guessed at ShopEngine's class names and
 * guessed wrong (".shopengine-product-item" instead of the real
 * ".shopengine-single-product-item", ".onsale" instead of the real
 * "li.badge.sale", etc). These selectors were confirmed directly against
 * rhemafashion.com's actual rendered HTML - the site's "Filterable Product
 * List" ShopEngine widget does NOT reuse WooCommerce's default
 * ul.products/li.product/onsale markup at all; it has its own:
 *   card:    div.shopengine-single-product-item
 *   button:  a.button.view-product
 *   badge:   li.badge.sale (inside div.product-tag-sale-badge)
 *   title:   h3.product-title
 *   price:   div.product-price .price
 * The old v1.0.0 selectors are kept alongside as a harmless fallback in case
 * a different part of the site ever renders a plain WooCommerce shortcode
 * grid instead of this ShopEngine widget.
 *
 * !important is used deliberately on the key visual properties: this plugin
 * exists specifically to override the Astra theme's and ShopEngine's own
 * default styling on these elements (confirmed e.g. Astra's
 * ".woocommerce-js a.button" rule, which already carries higher specificity
 * than a bare element+class selector), and the site also runs LiteSpeed
 * Cache's CSS optimizer, which can reorder/combine stylesheets in ways that
 * make relying on source order alone unreliable.
 */
add_action( 'wp_head', 'rhema_shop_styler_output_css', 999 );

function rhema_shop_styler_output_css() {
	?>
	<style id="rhema-shop-styler-css">
	/* ==========================================================================
	   1. Product cards
	   ========================================================================== */
	.shopengine-single-product-item,
	.woocommerce ul.products li.product,
	.shopengine-product-item {
		background-color: #ffffff !important;
		border: 1px solid rgba(0,0,0,0.08) !important;
		border-radius: 8px !important;
		box-shadow: 0 4px 12px rgba(0,0,0,0.05) !important;
		transition: transform 0.25s ease, box-shadow 0.25s ease;
	}
	.shopengine-single-product-item:hover,
	.woocommerce ul.products li.product:hover,
	.shopengine-product-item:hover {
		transform: translateY(-2px);
		box-shadow: 0 8px 20px rgba(0,0,0,0.1) !important;
	}

	/* ==========================================================================
	   2. "View Product" buttons
	   ========================================================================== */
	.shopengine-single-product-item a.button.view-product,
	.shopengine-single-product-item a.view-product,
	.woocommerce ul.products li.product a.button,
	.shopengine-product-btn {
		display: inline-block;
		border: none !important;
		border-radius: 0 !important;
		background-color: #0047AB !important;
		color: #ffffff !important;
		padding: 10px 22px;
		transition: background-color 0.3s ease, box-shadow 0.3s ease;
	}
	.shopengine-single-product-item a.button.view-product:hover,
	.shopengine-single-product-item a.button.view-product:focus,
	.shopengine-single-product-item a.view-product:hover,
	.shopengine-single-product-item a.view-product:focus,
	.woocommerce ul.products li.product a.button:hover,
	.woocommerce ul.products li.product a.button:focus,
	.shopengine-product-btn:hover,
	.shopengine-product-btn:focus {
		background-color: #E65C00 !important;
		color: #ffffff !important;
		box-shadow: 0 0 12px rgba(230, 92, 0, 0.5) !important;
	}

	/* ==========================================================================
	   3. "Sale!" badge
	   ========================================================================== */
	.product-tag-sale-badge li.badge.sale,
	li.badge.sale,
	.onsale,
	.shopengine-sale-badge {
		background-color: #E65C00 !important;
		color: #ffffff !important;
		font-weight: bold !important;
		text-transform: uppercase !important;
		border-radius: 3px !important;
		padding: 4px 8px !important;
	}

	/* ==========================================================================
	   4. Typography & icons (titles, prices, and any inline icon glyphs)
	   ========================================================================== */
	.shopengine-single-product-item .product-title,
	.shopengine-single-product-item .product-title a,
	.shopengine-single-product-item .product-price,
	.shopengine-single-product-item .product-price .price,
	.woocommerce ul.products li.product .woocommerce-loop-product__title,
	.woocommerce ul.products li.product .price,
	.shopengine-product-title,
	.shopengine-product-price {
		color: #333333 !important;
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
	}
	.shopengine-single-product-item i,
	.shopengine-single-product-item svg {
		color: #333333;
		fill: #333333;
	}
	</style>
	<?php
}
