# Embedding the widget

Grab your exact snippet from the admin dashboard's **Embed code** page — it
already has your Supabase URL, anon key, and project slug filled in. The
general shape is:

```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/afceduganda-byte/Universal-PWA@main/widget/translator.css" />
<script
  src="https://cdn.jsdelivr.net/gh/afceduganda-byte/Universal-PWA@main/widget/translator.js"
  data-supabase-url="https://YOUR-PROJECT.supabase.co"
  data-supabase-anon-key="YOUR-ANON-KEY"
  data-project-slug="zuri-safari-navigator"
  data-position="bottom-right"
  data-auto-scan="true"
  data-log-endpoint="https://YOUR-PROJECT.supabase.co/functions/v1/log-language-stat"
></script>
```

Serving the widget straight from GitHub via jsDelivr is free and fine for
getting started; for production you may prefer copying `widget/` into your
own site's `public/` folder and hosting it yourself (one less external
dependency).

## Lovable / React / Vite apps (e.g. Zuri Safari Navigator)

Paste the snippet into `index.html`, right before `</body>`. Because the
widget uses a `MutationObserver`, it re-applies translations automatically
as React renders new content and as users navigate between client-side
routes — no extra wiring needed in your components.

If you'd rather not touch `index.html`, you can inject it from your root
component instead:

```tsx
useEffect(() => {
  const script = document.createElement("script");
  script.src = "https://cdn.jsdelivr.net/gh/afceduganda-byte/Universal-PWA@main/widget/translator.js";
  script.dataset.supabaseUrl = import.meta.env.VITE_SUPABASE_URL;
  script.dataset.supabaseAnonKey = import.meta.env.VITE_SUPABASE_ANON_KEY;
  script.dataset.projectSlug = "zuri-safari-navigator";
  script.dataset.autoScan = "true";
  document.body.appendChild(script);
}, []);
```

## WordPress

Install `wordpress-plugin/universal-pwa-translator.php` as a plugin, then
fill in Settings -> Translator. No template edits required.

## Two ways to mark up strings

1. **Auto-scan (default, zero markup).** The widget walks the page's text
   nodes and swaps in a translation whenever it finds an *exact* match for a
   string you've registered in the Strings page. Simplest to start with, but
   breaks if the source text changes slightly (extra whitespace is
   normalized, but wording changes are not).
2. **Explicit keys (`data-i18n="key"`).** Wrap a string once:
   ```html
   <h1 data-i18n="home.hero.title">Discover Africa's Wild Side</h1>
   ```
   and register `home.hero.title` in the Strings page. This survives
   copy changes to the source text and is recommended for your most
   important headlines/CTAs.

Both mechanisms can be used together on the same page.

## RTL languages

Arabic (and any language you flag `rtl = true` in the Languages page)
automatically sets `<html dir="rtl">` when selected — make sure your CSS
doesn't hard-code `left`/`right` in ways that fight this (prefer logical
properties like `margin-inline-start` where you can).
