-- Seed data: one starter project ("Zuri Safari Navigator") with 8 languages.
-- Safe to run once; re-running is idempotent thanks to the unique constraints
-- + ON CONFLICT DO NOTHING guards below.

insert into projects (name, slug, allowed_domains, default_locale)
values (
  'Zuri Safari Navigator',
  'zuri-safari-navigator',
  array['zuriafricaadventures.com', 'www.zuriafricaadventures.com', 'localhost'],
  'en'
)
on conflict (slug) do nothing;

-- Grab the project id for the inserts below.
do $$
declare
  v_project_id uuid;
begin
  select id into v_project_id from projects where slug = 'zuri-safari-navigator';

  insert into languages (project_id, code, name, native_name, flag_emoji, is_default, rtl)
  values
    (v_project_id, 'en', 'English',    'English',   '🇬🇧', true,  false),
    (v_project_id, 'fr', 'French',     'Français',  '🇫🇷', false, false),
    (v_project_id, 'es', 'Spanish',    'Español',   '🇪🇸', false, false),
    (v_project_id, 'sw', 'Swahili',    'Kiswahili', '🇰🇪', false, false),
    (v_project_id, 'de', 'German',     'Deutsch',   '🇩🇪', false, false),
    (v_project_id, 'ar', 'Arabic',     'العربية',   '🇸🇦', false, true),
    (v_project_id, 'zh', 'Chinese',    '中文',       '🇨🇳', false, false),
    (v_project_id, 'pt', 'Portuguese', 'Português', '🇵🇹', false, false)
  on conflict (project_id, code) do nothing;
end $$;

-- A second project so you can try the WordPress plugin against a separate
-- site without touching the Zuri data.
insert into projects (name, slug, allowed_domains, default_locale)
values ('Demo WordPress Site', 'demo-wordpress', array['localhost'], 'en')
on conflict (slug) do nothing;

do $$
declare
  v_project_id uuid;
begin
  select id into v_project_id from projects where slug = 'demo-wordpress';

  insert into languages (project_id, code, name, native_name, flag_emoji, is_default, rtl)
  values
    (v_project_id, 'en', 'English',  'English',   '🇬🇧', true,  false),
    (v_project_id, 'fr', 'French',   'Français',  '🇫🇷', false, false),
    (v_project_id, 'es', 'Spanish',  'Español',   '🇪🇸', false, false),
    (v_project_id, 'sw', 'Swahili',  'Kiswahili', '🇰🇪', false, false),
    (v_project_id, 'de', 'German',   'Deutsch',   '🇩🇪', false, false),
    (v_project_id, 'ar', 'Arabic',   'العربية',   '🇸🇦', false, true),
    (v_project_id, 'zh', 'Chinese',  '中文',       '🇨🇳', false, false)
  on conflict (project_id, code) do nothing;
end $$;
