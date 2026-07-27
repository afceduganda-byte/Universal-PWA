-- Universal-PWA Translator: core schema
-- Multi-project translation backend. Any site (Lovable/React app, WordPress,
-- plain HTML) embeds the widget against a "project" row here.

create extension if not exists "pgcrypto";

-- ---------------------------------------------------------------------------
-- projects: one row per site that embeds the translator widget
-- ---------------------------------------------------------------------------
create table if not exists projects (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  slug text not null unique,
  allowed_domains text[] not null default '{}',
  default_locale text not null default 'en',
  public_key text not null unique default encode(gen_random_bytes(16), 'hex'),
  created_at timestamptz not null default now()
);

-- ---------------------------------------------------------------------------
-- languages: which locales are enabled per project
-- ---------------------------------------------------------------------------
create table if not exists languages (
  id uuid primary key default gen_random_uuid(),
  project_id uuid not null references projects(id) on delete cascade,
  code text not null,          -- BCP-47, e.g. 'fr', 'sw', 'zh-Hans'
  name text not null,          -- 'French'
  native_name text not null,   -- 'Français'
  flag_emoji text,
  is_default boolean not null default false,
  enabled boolean not null default true,
  rtl boolean not null default false,
  created_at timestamptz not null default now(),
  unique (project_id, code)
);

-- ---------------------------------------------------------------------------
-- translation_keys: one row per distinct source string in a project
-- ---------------------------------------------------------------------------
create table if not exists translation_keys (
  id uuid primary key default gen_random_uuid(),
  project_id uuid not null references projects(id) on delete cascade,
  key text not null,           -- stable id, e.g. "home.hero.title" or a text hash
  source_text text not null,
  context text,                -- optional hint: page/section, for translators
  created_at timestamptz not null default now(),
  unique (project_id, key)
);

-- ---------------------------------------------------------------------------
-- translations: the actual per-language strings
-- ---------------------------------------------------------------------------
create table if not exists translations (
  id uuid primary key default gen_random_uuid(),
  translation_key_id uuid not null references translation_keys(id) on delete cascade,
  language_id uuid not null references languages(id) on delete cascade,
  translated_text text not null,
  status text not null default 'machine' check (status in ('machine', 'edited', 'approved')),
  updated_at timestamptz not null default now(),
  unique (translation_key_id, language_id)
);

-- ---------------------------------------------------------------------------
-- admin_users: who can manage a project from the admin dashboard
-- ---------------------------------------------------------------------------
create table if not exists admin_users (
  user_id uuid not null references auth.users(id) on delete cascade,
  project_id uuid not null references projects(id) on delete cascade,
  role text not null default 'editor' check (role in ('owner', 'admin', 'editor')),
  created_at timestamptz not null default now(),
  primary key (user_id, project_id)
);

-- ---------------------------------------------------------------------------
-- language_stats: lightweight, write-only analytics of visitor language picks
-- ---------------------------------------------------------------------------
create table if not exists language_stats (
  id bigint generated always as identity primary key,
  project_id uuid not null references projects(id) on delete cascade,
  language_code text not null,
  page_url text,
  created_at timestamptz not null default now()
);

create index if not exists idx_languages_project on languages(project_id);
create index if not exists idx_translation_keys_project on translation_keys(project_id);
create index if not exists idx_translations_key on translations(translation_key_id);
create index if not exists idx_translations_language on translations(language_id);
create index if not exists idx_language_stats_project on language_stats(project_id, created_at desc);

-- ---------------------------------------------------------------------------
-- Row Level Security
-- ---------------------------------------------------------------------------
alter table projects enable row level security;
alter table languages enable row level security;
alter table translation_keys enable row level security;
alter table translations enable row level security;
alter table admin_users enable row level security;
alter table language_stats enable row level security;

-- helper: is the current auth user an admin/owner/editor of a given project?
create or replace function is_project_member(p_project_id uuid)
returns boolean
language sql
security definer
stable
as $$
  select exists (
    select 1 from admin_users
    where project_id = p_project_id
      and user_id = auth.uid()
  );
$$;

create or replace function is_project_admin(p_project_id uuid)
returns boolean
language sql
security definer
stable
as $$
  select exists (
    select 1 from admin_users
    where project_id = p_project_id
      and user_id = auth.uid()
      and role in ('owner', 'admin')
  );
$$;

-- projects: publicly readable (needed so the widget can resolve a slug -> id),
-- writable only by owners/admins of that project.
create policy "projects_public_read" on projects
  for select using (true);

create policy "projects_admin_write" on projects
  for update using (is_project_admin(id));

create policy "projects_admin_insert" on projects
  for insert with check (auth.uid() is not null);

-- languages/translation_keys/translations: public read (the whole point is
-- that visitors on the embedding site can fetch them anonymously), writes
-- restricted to project members.
create policy "languages_public_read" on languages
  for select using (true);

create policy "languages_member_write" on languages
  for insert with check (is_project_member(project_id));

create policy "languages_member_update" on languages
  for update using (is_project_member(project_id));

create policy "languages_member_delete" on languages
  for delete using (is_project_admin(project_id));

create policy "translation_keys_public_read" on translation_keys
  for select using (true);

create policy "translation_keys_member_write" on translation_keys
  for insert with check (is_project_member(project_id));

create policy "translation_keys_member_update" on translation_keys
  for update using (is_project_member(project_id));

create policy "translation_keys_member_delete" on translation_keys
  for delete using (is_project_admin(project_id));

create policy "translations_public_read" on translations
  for select using (true);

create policy "translations_member_write" on translations
  for insert with check (
    is_project_member((select project_id from translation_keys where id = translation_key_id))
  );

create policy "translations_member_update" on translations
  for update using (
    is_project_member((select project_id from translation_keys where id = translation_key_id))
  );

create policy "translations_member_delete" on translations
  for delete using (
    is_project_admin((select project_id from translation_keys where id = translation_key_id))
  );

-- admin_users: members can see their own project's roster; only owners/admins
-- can add or remove members.
create policy "admin_users_self_read" on admin_users
  for select using (is_project_member(project_id));

create policy "admin_users_admin_write" on admin_users
  for insert with check (is_project_admin(project_id));

create policy "admin_users_admin_update" on admin_users
  for update using (is_project_admin(project_id));

create policy "admin_users_admin_delete" on admin_users
  for delete using (is_project_admin(project_id));

-- language_stats: anyone (anon widget) may insert a stat row; only project
-- members may read the aggregated data back in the admin dashboard.
create policy "language_stats_public_insert" on language_stats
  for insert with check (true);

create policy "language_stats_member_read" on language_stats
  for select using (is_project_member(project_id));
