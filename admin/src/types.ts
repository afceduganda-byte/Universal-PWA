export interface Project {
  id: string;
  name: string;
  slug: string;
  allowed_domains: string[];
  default_locale: string;
  public_key: string;
  created_at: string;
}

export interface Language {
  id: string;
  project_id: string;
  code: string;
  name: string;
  native_name: string;
  flag_emoji: string | null;
  is_default: boolean;
  enabled: boolean;
  rtl: boolean;
  created_at: string;
}

export interface TranslationKey {
  id: string;
  project_id: string;
  key: string;
  source_text: string;
  context: string | null;
  created_at: string;
}

export type TranslationStatus = "machine" | "edited" | "approved";

export interface Translation {
  id: string;
  translation_key_id: string;
  language_id: string;
  translated_text: string;
  status: TranslationStatus;
  updated_at: string;
}

export interface AdminUser {
  user_id: string;
  project_id: string;
  role: "owner" | "admin" | "editor";
  created_at: string;
}

export interface LanguageStat {
  id: number;
  project_id: string;
  language_code: string;
  page_url: string | null;
  created_at: string;
}
