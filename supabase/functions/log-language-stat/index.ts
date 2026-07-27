// Supabase Edge Function: log-language-stat
//
// Records which language a visitor picked, for the admin dashboard's
// analytics tab. Public (anon) endpoint — no auth required, since it's called
// straight from the embedded widget on any visitor's browser. Deliberately
// tiny: validates input, throttles obvious junk, writes one row.

import { createClient } from "npm:@supabase/supabase-js@2";

const CORS_HEADERS = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type",
  "Access-Control-Allow-Methods": "POST, OPTIONS",
};

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: CORS_HEADERS });

  try {
    const { project_slug, language_code, page_url } = await req.json();

    if (!project_slug || !language_code) {
      return new Response(JSON.stringify({ error: "project_slug and language_code are required" }), {
        status: 400,
        headers: { ...CORS_HEADERS, "Content-Type": "application/json" },
      });
    }
    if (typeof language_code !== "string" || language_code.length > 10) {
      return new Response(JSON.stringify({ error: "invalid language_code" }), {
        status: 400,
        headers: { ...CORS_HEADERS, "Content-Type": "application/json" },
      });
    }

    const admin = createClient(
      Deno.env.get("SUPABASE_URL")!,
      Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!,
    );

    const { data: project } = await admin
      .from("projects")
      .select("id")
      .eq("slug", project_slug)
      .single();
    if (!project) {
      return new Response(JSON.stringify({ error: "unknown project" }), {
        status: 404,
        headers: { ...CORS_HEADERS, "Content-Type": "application/json" },
      });
    }

    const { error } = await admin.from("language_stats").insert({
      project_id: project.id,
      language_code,
      page_url: typeof page_url === "string" ? page_url.slice(0, 500) : null,
    });
    if (error) throw error;

    return new Response(JSON.stringify({ ok: true }), {
      headers: { ...CORS_HEADERS, "Content-Type": "application/json" },
    });
  } catch (e) {
    return new Response(JSON.stringify({ error: (e as Error).message }), {
      status: 500,
      headers: { ...CORS_HEADERS, "Content-Type": "application/json" },
    });
  }
});
