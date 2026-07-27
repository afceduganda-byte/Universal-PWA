-- Bootstrapping a brand new project from the admin dashboard has a
-- chicken-and-egg problem: the admin_users insert policy requires the
-- caller to already be an admin of the project, but nobody is yet. This
-- security-definer function creates the project and the first admin_users
-- row (the caller, as 'owner') atomically.

create or replace function create_project_as_owner(
  p_name text,
  p_slug text,
  p_default_locale text default 'en'
)
returns projects
language plpgsql
security definer
set search_path = public
as $$
declare
  v_project projects;
begin
  if auth.uid() is null then
    raise exception 'Must be signed in to create a project';
  end if;

  insert into projects (name, slug, default_locale)
  values (p_name, p_slug, p_default_locale)
  returning * into v_project;

  insert into admin_users (user_id, project_id, role)
  values (auth.uid(), v_project.id, 'owner');

  insert into languages (project_id, code, name, native_name, flag_emoji, is_default, rtl)
  values (v_project.id, p_default_locale, initcap(p_default_locale), initcap(p_default_locale), null, true, false)
  on conflict (project_id, code) do nothing;

  return v_project;
end;
$$;

grant execute on function create_project_as_owner(text, text, text) to authenticated;
