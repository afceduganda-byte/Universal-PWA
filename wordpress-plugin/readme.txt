=== Universal-PWA Translator ===
Contributors: universal-pwa
Tags: translation, multilingual, i18n, language switcher
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Drop-in, multi-language translation widget for any WordPress site, backed by
a free Supabase project shared with the Universal-PWA admin dashboard.

== Description ==

This plugin does not translate your theme's PHP templates directly. Instead
it enqueues a small front-end script (`widget/translator.js` from the
Universal-PWA project) that:

* Shows a floating language switcher on every page.
* Translates visible text automatically (no shortcodes or template edits).
* Falls back to free machine translation (LibreTranslate) for strings your
  editors haven't reviewed yet, via the companion admin dashboard.

All content lives in your Supabase project, so the *same* backend can also
power a non-WordPress site (e.g. a Lovable/React app) side by side, as a
second "project" row.

== Installation ==

1. Create a free Supabase project and run the SQL migrations in
   `supabase/migrations/` from the Universal-PWA repo.
2. Deploy the admin dashboard (`admin/`) somewhere free (Vercel/Netlify) and
   sign in to create a project + languages for this site.
3. Upload this plugin folder to `wp-content/plugins/universal-pwa-translator`
   and activate it.
4. Go to Settings -> Translator and paste in your Supabase URL, anon key,
   and this site's project slug.

== Frequently Asked Questions ==

= Does this cost anything? =

No. Supabase's free tier, LibreTranslate's free API, and a free static host
for the admin dashboard are all sufficient to run this at small-to-medium
traffic.

= Does it work with page builders / headless WordPress? =

Yes — the widget only needs the script tag to load somewhere on the
rendered page, so it works regardless of which builder generated the HTML.
