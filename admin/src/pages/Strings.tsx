import { FormEvent, useEffect, useMemo, useState } from "react";
import { supabase, functionsUrl } from "../supabaseClient";
import { useProjects } from "../lib/ProjectContext";
import type { Language, Translation, TranslationKey } from "../types";

function slugify(text: string) {
  return (
    text
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/(^-|-$)/g, "")
      .slice(0, 60) || "key"
  );
}

export default function Strings() {
  const { currentProject } = useProjects();
  const [keys, setKeys] = useState<TranslationKey[]>([]);
  const [languages, setLanguages] = useState<Language[]>([]);
  const [translations, setTranslations] = useState<Record<string, Translation>>({}); // `${keyId}:${langId}` -> row
  const [activeLangId, setActiveLangId] = useState<string>("");
  const [loading, setLoading] = useState(true);
  const [bulkText, setBulkText] = useState("");
  const [filterMissing, setFilterMissing] = useState(false);
  const [autoTranslating, setAutoTranslating] = useState(false);
  const [autoTranslateMsg, setAutoTranslateMsg] = useState("");

  async function load() {
    if (!currentProject) return;
    setLoading(true);
    const [{ data: langs }, { data: tkeys }] = await Promise.all([
      supabase.from("languages").select("*").eq("project_id", currentProject.id).order("is_default", { ascending: false }),
      supabase.from("translation_keys").select("*").eq("project_id", currentProject.id).order("created_at"),
    ]);
    setLanguages(langs ?? []);
    setKeys(tkeys ?? []);
    if (!activeLangId && langs && langs.length) {
      const nonDefault = langs.find((l) => !l.is_default) ?? langs[0];
      setActiveLangId(nonDefault.id);
    }

    const { data: trans } = await supabase
      .from("translations")
      .select("*")
      .in("translation_key_id", (tkeys ?? []).map((k) => k.id));
    const map: Record<string, Translation> = {};
    (trans ?? []).forEach((t) => {
      map[`${t.translation_key_id}:${t.language_id}`] = t;
    });
    setTranslations(map);
    setLoading(false);
  }

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [currentProject]);

  const activeLanguage = languages.find((l) => l.id === activeLangId);

  const visibleKeys = useMemo(() => {
    if (!filterMissing) return keys;
    return keys.filter((k) => !translations[`${k.id}:${activeLangId}`]);
  }, [keys, translations, activeLangId, filterMissing]);

  async function addBulkStrings(e: FormEvent) {
    e.preventDefault();
    if (!currentProject || !bulkText.trim()) return;
    const lines = bulkText.split("\n").map((l) => l.trim()).filter(Boolean);
    const rows = lines.map((line) => {
      const [maybeKey, ...rest] = line.split("|");
      const hasExplicitKey = rest.length > 0;
      const sourceText = hasExplicitKey ? rest.join("|").trim() : maybeKey;
      const key = hasExplicitKey ? maybeKey.trim() : slugify(maybeKey) + "-" + Math.random().toString(36).slice(2, 6);
      return { project_id: currentProject.id, key, source_text: sourceText };
    });
    await supabase.from("translation_keys").upsert(rows, { onConflict: "project_id,key", ignoreDuplicates: true });
    setBulkText("");
    load();
  }

  async function updateTranslation(keyId: string, langId: string, text: string) {
    if (!text.trim()) return;
    const existing = translations[`${keyId}:${langId}`];
    const { data } = await supabase
      .from("translations")
      .upsert(
        {
          id: existing?.id,
          translation_key_id: keyId,
          language_id: langId,
          translated_text: text,
          status: "edited",
          updated_at: new Date().toISOString(),
        },
        { onConflict: "translation_key_id,language_id" },
      )
      .select()
      .single();
    if (data) {
      setTranslations((prev) => ({ ...prev, [`${keyId}:${langId}`]: data }));
    }
  }

  async function approveTranslation(t: Translation) {
    const { data } = await supabase
      .from("translations")
      .update({ status: "approved" })
      .eq("id", t.id)
      .select()
      .single();
    if (data) setTranslations((prev) => ({ ...prev, [`${t.translation_key_id}:${t.language_id}`]: data }));
  }

  async function deleteKey(keyId: string) {
    if (!confirm("Delete this string and all its translations?")) return;
    await supabase.from("translation_keys").delete().eq("id", keyId);
    load();
  }

  async function autoTranslateMissing() {
    if (!currentProject || !activeLangId) return;
    setAutoTranslating(true);
    setAutoTranslateMsg("");
    try {
      const {
        data: { session },
      } = await supabase.auth.getSession();
      const res = await fetch(`${functionsUrl}/translate-proxy`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${session?.access_token}`,
        },
        body: JSON.stringify({ project_id: currentProject.id, language_id: activeLangId }),
      });
      const json = await res.json();
      if (!res.ok) throw new Error(json.error || "Auto-translate failed");
      setAutoTranslateMsg(`Translated ${json.translated} string(s).`);
      load();
    } catch (err: any) {
      setAutoTranslateMsg(err.message || "Auto-translate failed");
    } finally {
      setAutoTranslating(false);
    }
  }

  if (!currentProject) return <p className="text-slate-500">Select a project first.</p>;

  return (
    <div>
      <h1 className="text-2xl font-semibold mb-6">Strings</h1>

      <div className="bg-white border rounded-lg p-4 mb-6">
        <h2 className="text-sm font-medium text-slate-500 mb-2">Add strings</h2>
        <p className="text-xs text-slate-400 mb-2">
          One per line. Use <code>my.key | Source text</code> for a stable key, or just paste plain
          text lines and a key will be generated automatically.
        </p>
        <form onSubmit={addBulkStrings}>
          <textarea
            value={bulkText}
            onChange={(e) => setBulkText(e.target.value)}
            rows={4}
            placeholder={"home.hero.title | Discover Africa's Wild Side\nBook your safari today"}
            className="w-full border rounded-md px-3 py-2 text-sm font-mono mb-2"
          />
          <button type="submit" className="bg-slate-900 text-white rounded-md px-4 py-1.5 text-sm">
            Add strings
          </button>
        </form>
      </div>

      <div className="flex flex-wrap items-center gap-3 mb-4">
        <label className="text-sm text-slate-500">Editing language</label>
        <select
          value={activeLangId}
          onChange={(e) => setActiveLangId(e.target.value)}
          className="text-sm border rounded-md px-2 py-1"
        >
          {languages
            .filter((l) => !l.is_default)
            .map((l) => (
              <option key={l.id} value={l.id}>
                {l.flag_emoji} {l.name}
              </option>
            ))}
        </select>

        <label className="text-sm text-slate-500 flex items-center gap-1 ml-4">
          <input type="checkbox" checked={filterMissing} onChange={(e) => setFilterMissing(e.target.checked)} />
          Show only missing
        </label>

        <span className="flex-1" />

        <button
          onClick={autoTranslateMissing}
          disabled={autoTranslating || !activeLangId}
          className="text-sm border rounded-md px-3 py-1.5 hover:bg-slate-50 disabled:opacity-50"
        >
          {autoTranslating ? "Auto-translating…" : `Auto-translate missing (${activeLanguage?.name ?? ""})`}
        </button>
      </div>
      {autoTranslateMsg && <p className="text-xs text-slate-500 mb-4">{autoTranslateMsg}</p>}

      {loading ? (
        <p className="text-sm text-slate-400">Loading…</p>
      ) : (
        <div className="bg-white border rounded-lg divide-y">
          {visibleKeys.length === 0 && <p className="p-4 text-sm text-slate-400">No strings yet.</p>}
          {visibleKeys.map((k) => {
            const t = translations[`${k.id}:${activeLangId}`];
            return (
              <div key={k.id} className="p-4">
                <div className="flex items-start justify-between gap-4">
                  <div className="flex-1">
                    <div className="text-xs text-slate-400 mb-1">{k.key}</div>
                    <div className="text-sm text-slate-700 mb-2">{k.source_text}</div>
                    <input
                      defaultValue={t?.translated_text ?? ""}
                      key={t?.id ?? "empty"}
                      onBlur={(e) => updateTranslation(k.id, activeLangId, e.target.value)}
                      placeholder="Not translated yet"
                      className="w-full border rounded-md px-3 py-2 text-sm"
                    />
                  </div>
                  <div className="flex flex-col items-end gap-2 shrink-0">
                    {t && (
                      <span
                        className={`text-xs px-2 py-0.5 rounded-full ${
                          t.status === "approved"
                            ? "bg-green-100 text-green-700"
                            : t.status === "edited"
                              ? "bg-blue-100 text-blue-700"
                              : "bg-slate-100 text-slate-600"
                        }`}
                      >
                        {t.status}
                      </span>
                    )}
                    {t && t.status !== "approved" && (
                      <button
                        onClick={() => approveTranslation(t)}
                        className="text-xs text-green-700 hover:underline"
                      >
                        Approve
                      </button>
                    )}
                    <button onClick={() => deleteKey(k.id)} className="text-xs text-red-600 hover:underline">
                      Delete
                    </button>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
