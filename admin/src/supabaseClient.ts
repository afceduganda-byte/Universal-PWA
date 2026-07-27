import { createClient } from "@supabase/supabase-js";

const supabaseUrl = import.meta.env.VITE_SUPABASE_URL as string;
const supabaseAnonKey = import.meta.env.VITE_SUPABASE_ANON_KEY as string;

if (!supabaseUrl || !supabaseAnonKey) {
  throw new Error(
    "Missing VITE_SUPABASE_URL / VITE_SUPABASE_ANON_KEY. Copy admin/.env.example to admin/.env.local and fill it in.",
  );
}

export const supabase = createClient(supabaseUrl, supabaseAnonKey);

export const functionsUrl =
  (import.meta.env.VITE_FUNCTIONS_URL as string) || `${supabaseUrl}/functions/v1`;
