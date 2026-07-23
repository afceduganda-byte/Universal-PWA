=== Universal PWA ===
Contributors: universalpwa
Tags: pwa, progressive web app, manifest, installable, service worker
Requires at least: 5.6
Tested up to: 6.6
Requires PHP: 7.2
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turns any WordPress site into an installable Progressive Web App: a dynamic manifest, a minimal service worker, and a smart install-prompt banner.

== Description ==

Universal PWA is a drop-in plugin with zero site-specific code. All values (logo, name, colors) come from the WordPress Site Icon and a single settings screen — never hardcoded.

**v1 scope is intentionally lean:**

* No offline caching, no offline page, no push notifications.
* The only goal: the site becomes installable, gets a proper home-screen icon, and prompts visitors to install it.

**Features**

* Dynamic `manifest.json` served live at the site root, generated from your settings.
* Icons (192x192, 512x512, and a maskable 512x512) auto-generated from your uploaded logo, the WordPress Site Icon, or your favicon — resized/padded server-side with GD or Imagick, whichever is available.
* A minimal, pure pass-through service worker served at `/sw.js` (root scope) purely to satisfy Chrome/Android's install criteria. No caching, nothing stored.
* A custom install banner: a branded bottom sheet on Android/Chrome/Edge (mobile) with a working Install button, a small corner card on desktop, and an instructional "tap Share → Add to Home Screen" banner on iOS (where no native install prompt exists).
* Settings → Universal PWA: app name, short name, logo upload, theme/background color pickers, custom banner text, live manifest/icon preview, and an enable/disable toggle.

**Platform limits (not bugs — enforced by the browser):**

* Chrome/Edge only fire the real install prompt after their own anti-spam check passes (a tap plus 30+ cumulative seconds on the page, possibly across visits).
* iOS Safari, and Chrome/Edge on iPhone, never expose a native install event — there is no functional "Install" button possible there, ever. The banner shows instructions instead.

== Installation ==

1. Upload the `universal-pwa` folder to `/wp-content/plugins/`, or install the zip via Plugins → Add New → Upload Plugin.
2. Activate the plugin.
3. Go to Settings → Universal PWA to set your app name, logo, colors, and banner text.
4. Visit the site; the custom install banner will appear after a short delay for eligible visitors.

== Frequently Asked Questions ==

= Why don't I see an Install button on my iPhone? =

Apple's WebKit engine (used by Safari and by Chrome/Edge on iOS) doesn't support the install prompt API. This is a permanent platform limitation, not something any plugin can work around. The banner shows "tap Share → Add to Home Screen" instead.

= I changed my logo/colors but nothing changed. =

Icons regenerate automatically on save. If you still see an old icon, that's very likely a *browser* cache of the manifest/icon files rather than the plugin — hard refresh, or reinstall the PWA.

== Changelog ==

= 1.1.0 =
* Simplified the Settings → Universal PWA screen to focus on the essentials: app icon (with recommended-size guidance), app name, and a compact preview. The manifest.json detail is now tucked behind an "Advanced" toggle.
* Redesigned the front-end install banner with a more premium look: frosted-glass card, refined shadows/typography, smoother animation, and a subtle "waiting for browser" state on the Install button.
* Removed a hardcoded plugin URI so the plugin list's "Visit plugin site" link no longer points anywhere — this is a fully site-agnostic, universal plugin.

= 1.0.0 =
* Initial release.
