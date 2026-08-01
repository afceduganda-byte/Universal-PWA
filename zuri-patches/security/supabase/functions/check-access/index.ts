// Server-side gating: returns the canonical truth for what the calling user can access.
// Trust nothing from the client. Every protected page asks this function.

import { createClient } from 'jsr:@supabase/supabase-js@2';

// SECURITY: Restrict CORS to the production origin only.
// Browsers enforce this — cross-origin scripts from other domains cannot read responses.
const ALLOWED_ORIGIN = 'https://zuriafricaadventures.com';

const getCorsHeaders = (requestOrigin: string | null) => ({
  'Access-Control-Allow-Origin': requestOrigin === ALLOWED_ORIGIN ? ALLOWED_ORIGIN : '',
  'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type',
  'Vary': 'Origin',
});

const denied = (requestOrigin: string | null, extra: Record<string, unknown> = {}) =>
  new Response(
    JSON.stringify({
      authenticated: false,
      canViewItinerary: false,
      canViewPortal: false,
      isAdmin: false,
      ...extra,
    }),
    { status: 200, headers: { ...getCorsHeaders(requestOrigin), 'Content-Type': 'application/json' } },
  );

Deno.serve(async (req) => {
  const requestOrigin = req.headers.get('Origin');

  if (req.method === 'OPTIONS') {
    return new Response('ok', { headers: getCorsHeaders(requestOrigin) });
  }

  const supabaseUrl = Deno.env.get('SUPABASE_URL')!;
  const serviceKey = Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!;
  const admin = createClient(supabaseUrl, serviceKey);

  try {
    const authHeader = req.headers.get('Authorization') ?? '';
    const jwt = authHeader.replace('Bearer ', '');
    if (!jwt) {
      return denied(requestOrigin);
    }

    // Validate JWT
    const userClient = createClient(supabaseUrl, Deno.env.get('SUPABASE_ANON_KEY')!, {
      global: { headers: { Authorization: `Bearer ${jwt}` } },
    });
    const { data: userData, error: userErr } = await userClient.auth.getUser();
    if (userErr || !userData?.user) {
      return denied(requestOrigin);
    }
    const userId = userData.user.id;

    // Single source of truth: canonical wrappers in DB
    const [itinRes, portalRes, adminRes] = await Promise.all([
      admin.rpc('can_view_itinerary', { check_user_id: userId }),
      admin.rpc('can_view_portal', { check_user_id: userId }),
      admin.rpc('is_admin', { user_id: userId }),
    ]);

    const isAdmin = adminRes.data === true;
    const canViewItinerary = itinRes.data === true;
    const canViewPortal = portalRes.data === true;

    // Audit log (fire-and-forget)
    admin.from('portal_access_log').insert([
      { user_id: userId, scope: 'itinerary', granted: canViewItinerary, reason: itinRes.error?.message ?? null },
      { user_id: userId, scope: 'portal', granted: canViewPortal, reason: portalRes.error?.message ?? null },
    ]).then(() => {}, () => {});

    return new Response(
      JSON.stringify({
        authenticated: true,
        userId,
        isAdmin,
        canViewItinerary,
        canViewPortal,
      }),
      { status: 200, headers: { ...getCorsHeaders(requestOrigin), 'Content-Type': 'application/json' } },
    );
  } catch (e) {
    console.error('check-access error', e);
    // Fail CLOSED — never grant on error
    return denied(requestOrigin, { error: String(e) });
  }
});
