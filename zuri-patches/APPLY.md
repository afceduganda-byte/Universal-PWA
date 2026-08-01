# Zuri Africa Adventures — Security & SEO Patch Guide

Apply these patches to the Zuri Africa Adventures codebase (Lovable + Supabase).
Each section is independent — apply in any order, but run the SQL migration first
since it closes a live security hole.

---

## 1. Critical RLS Fix — Anonymous Write Hole in Posts Table

**File:** `security/supabase/migrations/20260801000000_fix_posts_rls_anon_hole.sql`

**What it fixes:** The existing posts RLS policy allowed `auth.uid() IS NULL`
as a valid condition, meaning any unauthenticated caller could insert, update,
or delete posts. This migration removes that escape hatch.

**How to apply:**
1. Open your Supabase project dashboard → SQL Editor
2. Paste the contents of `security/supabase/migrations/20260801000000_fix_posts_rls_anon_hole.sql`
3. Run it
4. Verify: run `SELECT count(*) FROM posts` as anon role (should succeed — public read preserved).
   Run `INSERT INTO posts (title) VALUES ('test')` as anon — should fail with RLS violation.

---

## 2. HTTP Security Headers

Two formats are provided — use whichever matches your hosting platform.

### Netlify / Lovable (Netlify-backed)

**File:** `security/public/_headers`

**How to apply:**
1. Copy `security/public/_headers` into your project's `public/` directory.
   Vite copies everything in `public/` into `dist/` at build time.
2. Redeploy. Netlify reads `_headers` from the published `dist/` root.
3. Verify with: `curl -sI https://zuriafricaadventures.com | grep -i "x-frame\|content-security\|strict-transport"`

### Vercel

**File:** `security/vercel.json`

**How to apply:**
1. Copy `security/vercel.json` to the project root (alongside `package.json`).
2. Redeploy. Vercel reads `vercel.json` automatically.
3. Same curl verification as above.

**What the headers do:**
- `X-Frame-Options: DENY` — prevents clickjacking (your site can't be embedded in iframes)
- `X-Content-Type-Options: nosniff` — prevents MIME-type confusion attacks
- `Content-Security-Policy` — blocks XSS, inline script injection, unauthorized origins
- `Strict-Transport-Security` — forces HTTPS for 1 year across all subdomains
- `Referrer-Policy` — controls what referrer info leaves your site
- `Permissions-Policy` — disables camera, microphone, USB access from your pages

---

## 3. SEO Fix — Remove Gated Pages from Structured Data & Build Output

Three files need to be replaced. All three remove `/my-itinerary` and `/portal`
from places where Google's crawler can read them, which was causing those auth-gated
pages to surface in search previews despite carrying a `noindex` meta tag.

### 3a. Vite Build Config

**File:** `seo/vite.config.ts`  
**Replaces:** `vite.config.ts` in the project root

**What changed:** Removed `/my-itinerary` and `/portal` from `SEO_ROUTES`.
The `seoMetaPlugin` was generating static HTML files for those routes at build time —
files that Google could crawl and index before the React JS ran and applied `noindex`.

**How to apply:**
1. Open `vite.config.ts` in your project root.
2. Find the `SEO_ROUTES` object and remove the `/my-itinerary` and `/portal` entries.
   Or replace the whole file with `seo/vite.config.ts`.
3. Rebuild: `npm run build` — confirm no `dist/my-itinerary/` or `dist/portal/` directories exist.

### 3b. index.html Structured Data

**File:** `seo/index.html`  
**Replaces:** `index.html` in the project root

**What changed:**
- `BreadcrumbList` JSON-LD: removed My Itinerary (position 2) and My Safari Portal (position 3).
  Remaining items renumbered (Community → 2, About → 3).
- `SiteNavigationElement ItemList` JSON-LD: same removals. `numberOfItems` updated from 5 → 3.
- `<noscript>` body links: removed the two gated page links that crawlers could follow.
- Removed two `console.log` lines from the SW cleanup script (minor cleanliness).

**How to apply:**
1. Replace `index.html` in your project root with `seo/index.html`.
2. Rebuild and redeploy.
3. Validate with [Google Rich Results Test](https://search.google.com/test/rich-results):
   paste `https://zuriafricaadventures.com` — `/my-itinerary` and `/portal` should not appear.

### 3c. ProtectedRoute — Strip Production Console Logs

**File:** `auth/src/components/auth/ProtectedRoute.tsx`  
**Replaces:** `src/components/auth/ProtectedRoute.tsx`

**What changed:** Removed all `console.log` statements that were logging the user's
email address, access decision, and route path in plain text to the browser console.
This information is visible to anyone who opens DevTools, including:
- `user?.email` on every route evaluation
- Explicit "access granted" / "access denied" messages with the reason

`console.warn` for genuine operational errors (check-access failures, timeouts) is retained.

**How to apply:**
1. Replace `src/components/auth/ProtectedRoute.tsx` with `auth/src/components/auth/ProtectedRoute.tsx`.
2. Run `tsc --noEmit` to confirm no type errors.
3. Rebuild and redeploy.

---

## 4. check-access Edge Function — Restrict CORS Origin

**File:** `security/supabase/functions/check-access/index.ts`  
**Replaces:** `supabase/functions/check-access/index.ts`

**What changed:** `Access-Control-Allow-Origin` changed from `'*'` (any origin)
to `'https://zuriafricaadventures.com'` only. A `Vary: Origin` header is added
so CDN caches don't serve the wrong CORS headers to different origins.

With `*`, a script on any website could call your `check-access` function and
read the JSON response, potentially leaking which user IDs have paid/have plans.
With the origin restriction, only requests from your own domain get valid CORS headers.

**How to apply:**
1. Replace `supabase/functions/check-access/index.ts` with `security/supabase/functions/check-access/index.ts`.
2. Deploy the function:
   ```bash
   supabase functions deploy check-access --project-ref pajzbkrjvhiebfolbyoc
   ```
3. Test from your live site: open DevTools → Network → navigate to `/my-itinerary`.
   The `check-access` call should succeed (200) with `Access-Control-Allow-Origin: https://zuriafricaadventures.com`.

---

## Summary Checklist

| # | File | Status | Priority |
|---|------|--------|----------|
| 1 | SQL migration — posts RLS anon hole | Apply in Supabase SQL Editor | **CRITICAL** |
| 2a | `public/_headers` or `vercel.json` | Add to project & redeploy | **HIGH** |
| 3a | `vite.config.ts` | Edit/replace & rebuild | **HIGH** |
| 3b | `index.html` | Replace & rebuild | **HIGH** |
| 3c | `src/components/auth/ProtectedRoute.tsx` | Replace & rebuild | **MEDIUM** |
| 4 | `supabase/functions/check-access/index.ts` | Replace & deploy function | **MEDIUM** |
