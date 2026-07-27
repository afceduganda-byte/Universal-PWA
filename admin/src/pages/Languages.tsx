import { FormEvent, useEffect, useState } from "react";
import { supabase } from "../supabaseClient";
import { useProjects } from "../lib/ProjectContext";
import type { Language } from "../types";

const COMMON_LANGUAGES: Array<Pick<Language, "code" | "name" | "native_name" | "flag_emoji" | "rtl">> = [
  { code: "en", name: "English", native_name: "English", flag_emoji: "🇬🇧", rtl: false },
  { code: "fr", name: "French", native_name: "Français", flag_emoji: "🇫🇷", rtl: false },
  { code: "es", name: "Spanish", native_name: "Español", flag_emoji: "🇪🇸", rtl: false },
  { code: "sw", name: "Swahili", native_name: "Kiswahili", flag_emoji: "🇰🇪", rtl: false },
  { code: "de", name: "German", native_name: "Deutsch", flag_emoji: "🇩🇪", rtl: false },
  { code: "ar", name: "Arabic", native_name: "العربية", flag_emoji: "🇸🇦", rtl: true },
  { code: "zh", name: "Chinese", native_name: "中文", flag_emoji: "🇨🇳", rtl: false },
  { code: "pt", name: "Portuguese", native_name: "Português", flag_emoji: "🇵🇹", rtl: false },
  { code: "it", name: "Italian", native_name: "Italiano", flag_emoji: "🇮🇹", rtl: false },
  { code: "hi", name: "Hindi", native_name: "हिन्दी", flag_emoji: "🇮🇳", rtl: false },
];

export default function Languages() {
  const { currentProject } = useProjects();
  const [languages, setLanguages] = useState<Language[]>([]);
  const [loading, setLoading] = useState(true);
  const [customCode, setCustomCode] = useState("");
  const [customName, setCustomName] = useState("");
  const [customNative, setCustomNative] = useState("");

  async function load() {
    if (!currentProject) return;
    setLoading(true);
    const { data } = await supabase
      .from("languages")
      .select("*")
      .eq("project_id", currentProject.id)
      .order("is_default", { ascending: false })
      .order("name");
    setLanguages(data ?? []);
    setLoading(false);
  }

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [currentProject]);

  const existingCodes = new Set(languages.map((l) => l.code));

  async function addLanguage(lang: Pick<Language, "code" | "name" | "native_name" | "flag_emoji" | "rtl">) {
    if (!currentProject) return;
    await supabase.from("languages").insert({
      project_id: currentProject.id,
      code: lang.code,
      name: lang.name,
      native_name: lang.native_name,
      flag_emoji: lang.flag_emoji,
      rtl: lang.rtl,
      enabled: true,
    });
    load();
  }

  async function toggleEnabled(lang: Language) {
    await supabase.from("languages").update({ enabled: !lang.enabled }).eq("id", lang.id);
    load();
  }

  async function removeLanguage(lang: Language) {
    if (!confirm(`Remove ${lang.name} and all its translations?`)) return;
    await supabase.from("languages").delete().eq("id", lang.id);
    load();
  }

  async function handleCustomSubmit(e: FormEvent) {
    e.preventDefault();
    if (!customCode || !customName) return;
    await addLanguage({
      code: customCode.trim(),
      name: customName.trim(),
      native_name: customNative.trim() || customName.trim(),
      flag_emoji: null,
      rtl: false,
    });
    setCustomCode("");
    setCustomName("");
    setCustomNative("");
  }

  if (!currentProject) return <p className="text-slate-500">Select a project first.</p>;

  return (
    <div>
      <h1 className="text-2xl font-semibold mb-6">Languages</h1>

      <h2 className="text-sm font-medium text-slate-500 mb-2">Enabled for this project</h2>
      <div className="bg-white border rounded-lg divide-y mb-8">
        {loading && <p className="p-4 text-sm text-slate-400">Loading…</p>}
        {!loading && languages.length === 0 && (
          <p className="p-4 text-sm text-slate-400">No languages yet — add some below.</p>
        )}
        {languages.map((lang) => (
          <div key={lang.id} className="flex items-center gap-4 px-4 py-3">
            <span className="text-lg">{lang.flag_emoji}</span>
            <span className="font-medium w-32">{lang.name}</span>
            <span className="text-slate-500 text-sm w-32">{lang.native_name}</span>
            <span className="text-xs uppercase text-slate-400 w-12">{lang.code}</span>
            {lang.is_default && (
              <span className="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full">default</span>
            )}
            {lang.rtl && (
              <span className="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full">RTL</span>
            )}
            <span className="flex-1" />
            <button
              onClick={() => toggleEnabled(lang)}
              className="text-xs px-2 py-1 rounded-md border hover:bg-slate-50"
            >
              {lang.enabled ? "Enabled" : "Disabled"}
            </button>
            {!lang.is_default && (
              <button
                onClick={() => removeLanguage(lang)}
                className="text-xs px-2 py-1 rounded-md border border-red-200 text-red-600 hover:bg-red-50"
              >
                Remove
              </button>
            )}
          </div>
        ))}
      </div>

      <h2 className="text-sm font-medium text-slate-500 mb-2">Quick add</h2>
      <div className="flex flex-wrap gap-2 mb-8">
        {COMMON_LANGUAGES.filter((l) => !existingCodes.has(l.code)).map((lang) => (
          <button
            key={lang.code}
            onClick={() => addLanguage(lang)}
            className="text-sm border rounded-full px-3 py-1.5 bg-white hover:bg-slate-50"
          >
            {lang.flag_emoji} {lang.name}
          </button>
        ))}
      </div>

      <h2 className="text-sm font-medium text-slate-500 mb-2">Add a custom language</h2>
      <form onSubmit={handleCustomSubmit} className="bg-white border rounded-lg p-4 flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-xs text-slate-500 mb-1">Code (e.g. "yo")</label>
          <input
            value={customCode}
            onChange={(e) => setCustomCode(e.target.value)}
            className="border rounded-md px-2 py-1.5 text-sm w-24"
          />
        </div>
        <div>
          <label className="block text-xs text-slate-500 mb-1">Name (e.g. "Yoruba")</label>
          <input
            value={customName}
            onChange={(e) => setCustomName(e.target.value)}
            className="border rounded-md px-2 py-1.5 text-sm w-40"
          />
        </div>
        <div>
          <label className="block text-xs text-slate-500 mb-1">Native name</label>
          <input
            value={customNative}
            onChange={(e) => setCustomNative(e.target.value)}
            className="border rounded-md px-2 py-1.5 text-sm w-40"
          />
        </div>
        <button type="submit" className="bg-slate-900 text-white rounded-md px-4 py-1.5 text-sm">
          Add
        </button>
      </form>
    </div>
  );
}
