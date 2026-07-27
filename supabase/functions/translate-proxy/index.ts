// Supabase Edge Function: translate-proxy
//
// Auto-translates missing strings for a project/language pair using a free
// machine-translation backend (LibreTranslate — open source, no API cost).
// Results are written back with status='machine' so a human can review and
// approve them later in the admin dashboard; nothing here overwrites a string
// that a human has already edited or approved.
//
// Auth: caller must be a signed-in admin/editor of the target project. The
// function runs with the service role key so it can bypass RLS for the write,
// but only after verifying membership itself.
//
// Env vars (set via `supabase secrets set`):
//   SUPABASE_URL, SUPABASE_SERVICE_ROLE_KEY  (auto-injected by Supabase)
//   LIBRETRANSLATE_URL   default: https://libretranslate.com/translate
//   LIBRETRANSLATE_API_KEY  optional, blank works against the free public tier

import { createClient } from "npm:@supabase/supabase-js@2";

const LIBRETRANSLATE_URL =
  Deno.env.get("LIBRETRANSLATE_URL") ?? "https://libretranslate.com/translate";
const LIBRETRANSLATE_API_KEY = Deno.env.get("LIBRETRANSLATE_API_KEY") ?? "";

const CORS_HEADERS = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type",
  "Access-Control-Allow-Methods": "POST, OPTIONS",
};

async function libreTranslate(text: string, source: string, target: string) {
  const body: Record<string, string> = { q: text, source, target, format: "text" };
  if (LIBRETRANSLATE_API_KEY) body.api_key = LIBRETRANSLATE_API_KEY;

  const res = await fetch(LIBRETRANSLATE_URL, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });

  if (!res.ok) {
    throw new Error(`LibreTranslate request failed (${res.status}): ${await res.text()}`);
  }
  const data = await res.json();
  return data.translatedText as string;
}

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: CORS_HEADERS });

  try {
    const authHeader = req.headers.get("Authorization") ?? "";
    const { project_id, language_id } = await req.json();

    if (!project_id || !language_id) {
      return new Response(JSON.stringify({ error: "project_id and language_id are required" }), {
        status: 400,
        headers: { ...CORS_HEADERS, "Content-Type": "application/json" },
      });
    }

    const supabaseUrl = Deno.env.get("SUPABASE_URL")!;
    const serviceRoleKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;

    // Client scoped to the caller's JWT, used only to check membership.
    const callerClient = createClient(supabaseUrl, Deno.env.get("SUPABASE_ANON_KEY")!, {
      global: { headers: { Authorization: authHeader } },
    });
    const {
      data: { user },
    } = await callerClient.auth.getUser();

    if (!user) {
      return new Response(JSON.stringify({ error: "Not authenticated" }), {
        status: 401,
        headers: { ...CORS_HEADERS, "Content-Type": "application/json" },
      });
    }

    // Service-role client for the membership check + the actual writes.
    const admin = createClient(supabaseUrl, serviceRoleKey);

    const { data: membership } = await admin
      .from("admin_users")
      .select("role")
      .eq("project_id", project_id)
      .eq("user_id", user.id)
      .maybeSingle();

    if (!membership) {
      return new Response(JSON.stringify({ error: "Not a member of this project" }), {
        status: 403,
        headers: { ...CORS_HEADERS, "Content-Type": "application/json" },
      });
    }

    const { data: language, error: langErr } = await admin
      .from("languages")
      .select("id, code")
      .eq("id", language_id)
      .eq("project_id", project_id)
      .single();
    if (langErr || !language) throw new Error("Language not found for this project");

    const { data: defaultLanguage } = await admin
      .from("languages")
      .select("code")
      .eq("project_id", project_id)
      .eq("is_default", true)
      .single();
    const sourceCode = defaultLanguage?.code ?? "en";

    // All source strings for this project.
    const { data: keys, error: keysErr } = await admin
      .from("translation_keys")
      .select("id, source_text")
      .eq("project_id", project_id);
    if (keysErr) throw keysErr;

    // Existing translations for this language, so we only fill in the gaps.
    const { data: existing, error: existingErr } = await admin
      .from("translations")
      .select("translation_key_id")
      .eq("language_id", language_id)
      .in("translation_key_id", (keys ?? []).map((k) => k.id));
    if (existingErr) throw existingErr;

    const alreadyTranslated = new Set((existing ?? []).map((t) => t.translation_key_id));
    const missing = (keys ?? []).filter((k) => !alreadyTranslated.has(k.id));

    let translatedCount = 0;
    const errors: string[] = [];

    for (const k of missing) {
      try {
        const translatedText = await libreTranslate(k.source_text, sourceCode, language.code);
        const { error: upsertErr } = await admin.from("translations").upsert(
          {
            translation_key_id: k.id,
            language_id,
            translated_text: translatedText,
            status: "machine",
            updated_at: new Date().toISOString(),
          },
          { onConflict: "translation_key_id,language_id" },
        );
        if (upsertErr) throw upsertErr;
        translatedCount++;
      } catch (e) {
        errors.push(`${k.id}: ${(e as Error).message}`);
      }
    }

    return new Response(
      JSON.stringify({ translated: translatedCount, skipped: alreadyTranslated.size, errors }),
      { headers: { ...CORS_HEADERS, "Content-Type": "application/json" } },
    );
  } catch (e) {
    return new Response(JSON.stringify({ error: (e as Error).message }), {
      status: 500,
      headers: { ...CORS_HEADERS, "Content-Type": "application/json" },
    });
  }
});
