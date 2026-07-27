# Universal-PWA Translator

A free, drop-in, multi-language translation system you can embed into
**any** web app — a plain HTML site, a WordPress theme, or a client-rendered
app like a Lovable/React project (e.g. Zuri Safari Navigator) — with a single
`<script>` tag, plus an admin dashboard for managing languages and strings.

It ships with 8 starter languages (English, French, Spanish, Swahili,
German, Arabic, Chinese, Portuguese) and lets you add more from the
dashboard in a couple of clicks.

## Why this design

- **Completely free stack.** Supabase's free tier (Postgres + Auth + Edge
  Functions) is the entire backend. LibreTranslate (open-source, free API)
  provides machine-translation fallback for strings nobody's reviewed yet. A
  free static host (Vercel/Netlify/Cloudflare Pages/GitHub Pages) serves the
  admin dashboard. The widget itself is a dependency-free JS file you can
  serve from GitHub via jsDelivr at no cost.
- **A real plugin, not a rewrite.** The widget script reads the page's
  visible text and swaps in translations — it does not require you to
  rewrite your site in a new framework or wrap every string in a helper
  function. Explicit `data-i18n="key"` markup is supported for strings you
  want to key by ID instead of exact text.
- **Multi-tenant from day one.** The same Supabase project can back
  multiple independent sites ("projects" in the schema) — for example Zuri
  Safari Navigator and a separate WordPress site — each with its own
  languages, strings, and API key.

## Repository layout

```
supabase/
  migrations/        SQL schema, RLS policies, seed data (8 languages)
  functions/
    translate-proxy/      auto-translates missing strings via LibreTranslate
    log-language-stat/    records visitor language picks for analytics
widget/
  translator.js      the embeddable widget (zero dependencies, ~10 KB)
  translator.css     floating language-switcher styles
admin/               React/Vite admin dashboard (languages, strings, analytics)
wordpress-plugin/    WordPress plugin wrapper around the same widget
docs/
  SETUP.md           step-by-step: Supabase project -> migrations -> dashboard
  EMBED.md           how to embed the widget in a Lovable/React app or WordPress
```

## Quick start

1. Read [`docs/SETUP.md`](docs/SETUP.md) — create a free Supabase project,
   run the migrations, deploy the two edge functions, and deploy the admin
   dashboard.
2. Sign in to the dashboard, pick (or create) a project, add strings, and
   click "Auto-translate missing" for each language.
3. Read [`docs/EMBED.md`](docs/EMBED.md) and paste the embed snippet from
   the dashboard's **Embed code** page into your site.

## A note on integrating with Zuri Safari Navigator specifically

This session could not reach `zuriafricaadventures.com` or your Supabase
project directly — this sandbox's network policy blocks arbitrary outbound
domains, and no Supabase/GitHub credentials for that project were available
here. Everything above was built as a **standalone, self-contained system**
so it works regardless: point it at a new (or your existing) Supabase
project, seed your real marketing copy as strings, and drop the embed
snippet into Zuri's `index.html`.

If you'd like tighter integration — e.g. reusing your existing Supabase
project instead of a new one, or wiring translations into content that's
already in your `tours`/`bookings`-style tables — share the Zuri GitHub repo
name (Lovable syncs to GitHub) or your Supabase project's schema, and this
can be adapted directly against it.
