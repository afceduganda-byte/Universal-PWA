# Setup guide

Every piece of this stack has a free tier. Total ongoing cost: $0 for small
to medium traffic.

## 1. Create a Supabase project

1. Go to https://supabase.com, sign up free, and create a new project.
2. In **Project Settings -> API**, note down:
   - **Project URL** (`https://xxxx.supabase.co`)
   - **anon public key**
   - **service_role key** (keep this one secret — only used server-side)

## 2. Run the database migrations

Easiest path (no CLI install): open the Supabase dashboard's **SQL Editor**
and run these files in order, pasting each one's contents and clicking "Run":

1. `supabase/migrations/0001_init.sql` — tables, indexes, RLS policies
2. `supabase/migrations/0002_seed.sql` — seeds a "Zuri Safari Navigator"
   project and 8 languages (edit the slug/domains first if you want a
   different starter project name)
3. `supabase/migrations/0003_create_project_function.sql` — a helper
   function the admin dashboard uses to safely bootstrap new projects

If you prefer the CLI: `supabase login`, `supabase link --project-ref xxxx`,
then `supabase db push`.

## 3. Deploy the two edge functions

Using the Supabase CLI (`npm install -g supabase`):

```bash
supabase functions deploy translate-proxy
supabase functions deploy log-language-stat

# translate-proxy calls the free LibreTranslate API by default (no key
# needed). Only set these if you're self-hosting LibreTranslate or using a
# paid tier with higher limits:
supabase secrets set LIBRETRANSLATE_URL=https://libretranslate.com/translate
supabase secrets set LIBRETRANSLATE_API_KEY=
```

Both functions read `SUPABASE_URL`, `SUPABASE_ANON_KEY`, and
`SUPABASE_SERVICE_ROLE_KEY` — Supabase injects these automatically, you
don't need to set them yourself.

> The public LibreTranslate.com API has a modest free rate limit. For
> heavier use, self-host LibreTranslate for free on a Render/Fly.io free
> instance and point `LIBRETRANSLATE_URL` at it — the rest of this system
> doesn't change.

## 4. Create your first admin login

1. In the Supabase dashboard: **Authentication -> Users -> Add user**,
   create yourself an account with an email + password.
2. In the **SQL Editor**, link that user to a project as owner:

   ```sql
   insert into admin_users (user_id, project_id, role)
   values (
     '<your-auth-user-id>',
     (select id from projects where slug = 'zuri-safari-navigator'),
     'owner'
   );
   ```

   (Find your user's id in **Authentication -> Users**.)

   Once you're signed in to the dashboard, you can create *further*
   projects yourself from the **Projects** page — that path bootstraps the
   `admin_users` row for you automatically.

## 5. Deploy the admin dashboard

The dashboard is a static Vite build — any free static host works.

```bash
cd admin
cp .env.example .env.local   # fill in your Supabase URL + anon key
npm install
npm run build                # outputs admin/dist
```

Deploy `admin/dist` to **Vercel**, **Netlify**, or **Cloudflare Pages**
(all have generous free tiers for a low-traffic internal tool). Set the same
three env vars (`VITE_SUPABASE_URL`, `VITE_SUPABASE_ANON_KEY`,
`VITE_FUNCTIONS_URL`) in the host's dashboard so the production build picks
them up.

Sign in with the account you created in step 4.

## 6. Add your content and translate it

1. **Languages** page: enable/add the languages you want.
2. **Strings** page: paste your site's copy (one line per string, or
   `key | text` for a stable key), then click **Auto-translate missing**
   for each language.
3. Review the machine translations and click **Approve** once they look
   right.

## 7. Embed the widget on your site

See [`EMBED.md`](EMBED.md).
