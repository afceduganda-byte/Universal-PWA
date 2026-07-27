<?php
/**
 * Plugin Name:       Rhema Fashion Shop Grid Styler
 * Plugin URI:        https://github.com/afceduganda-byte/universal-pwa
 * Description:       Pure-CSS visual styling for the ShopEngine product grid (cards, "View Product" buttons, Sale badges, titles and prices). Injects styles only via wp_head - no existing functionality, markup, or behavior is changed.
 * Version:           1.0.0
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
 * Selector strategy: ShopEngine is an Elementor add-on and, like most
 * WooCommerce page-builder widgets, layers its own "shopengine-*" classes
 * on top of (rather than instead of) WooCommerce's own default loop markup
 * (ul.products li.product, .onsale, .woocommerce-loop-product__title, .price,
 * a.button). Every rule below targets both, so styling applies whether a
 * given ShopEngine version/skin renders one, the other, or both. Class names
 * on a premium third-party plugin can still vary by version or per-widget
 * style setting - if a card on your site doesn't pick up these styles,
 * inspect it in your browser's DevTools and add the exact class you find to
 * the matching selector group below.
 */
add_action( 'wp_head', 'rhema_shop_styler_output_css', 999 );

function rhema_shop_styler_output_css() {
	?>
	<style id="rhema-shop-styler-css">
	/* ==========================================================================
	   1. Product cards
	   ========================================================================== */
	.woocommerce ul.products li.product,
	.shopengine-product,
	.shopengine-product-inner,
	.shopengine-product-grid .product,
	.shopengine-product-item {
		background-color: #ffffff;
		border: 1px solid rgba(0,0,0,0.08);
		border-radius: 8px;
		box-shadow: 0 4px 12px rgba(0,0,0,0.05);
		transition: transform 0.25s ease, box-shadow 0.25s ease;
	}
	.woocommerce ul.products li.product:hover,
	.shopengine-product:hover,
	.shopengine-product-inner:hover,
	.shopengine-product-grid .product:hover,
	.shopengine-product-item:hover {
		transform: translateY(-2px);
		box-shadow: 0 8px 20px rgba(0,0,0,0.1);
	}

	/* ==========================================================================
	   2. "View Product" buttons
	   ========================================================================== */
	.woocommerce ul.products li.product a.button,
	.shopengine-product a.button,
	.shopengine-product-btn,
	.shopengine-view-product,
	.shopengine-add-to-cart a {
		display: inline-block;
		border: none;
		border-radius: 0;
		background-color: #0047AB;
		color: #ffffff;
		padding: 10px 22px;
		transition: background-color 0.3s ease, box-shadow 0.3s ease;
	}
	.woocommerce ul.products li.product a.button:hover,
	.woocommerce ul.products li.product a.button:focus,
	.shopengine-product a.button:hover,
	.shopengine-product a.button:focus,
	.shopengine-product-btn:hover,
	.shopengine-product-btn:focus,
	.shopengine-view-product:hover,
	.shopengine-view-product:focus,
	.shopengine-add-to-cart a:hover,
	.shopengine-add-to-cart a:focus {
		background-color: #E65C00;
		color: #ffffff;
		box-shadow: 0 0 12px rgba(230, 92, 0, 0.5);
	}

	/* ==========================================================================
	   3. "Sale!" badge
	   ========================================================================== */
	.onsale,
	.shopengine-sale-badge,
	.shopengine-product .onsale {
		background-color: #E65C00;
		color: #ffffff;
		font-weight: bold;
		text-transform: uppercase;
		border-radius: 3px;
	}

	/* ==========================================================================
	   4. Typography & icons (titles, prices, and any inline icon glyphs)
	   ========================================================================== */
	.woocommerce ul.products li.product .woocommerce-loop-product__title,
	.shopengine-product-title,
	.woocommerce ul.products li.product .price,
	.shopengine-product-price,
	.woocommerce ul.products li.product .price .amount {
		color: #333333;
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
	}
	.shopengine-product-inner i,
	.shopengine-product-inner svg,
	.shopengine-product-item i,
	.shopengine-product-item svg {
		color: #333333;
		fill: #333333;
	}
	</style>
	<?php
}
