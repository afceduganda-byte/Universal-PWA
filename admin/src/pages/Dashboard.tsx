import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { supabase } from "../supabaseClient";
import { useProjects } from "../lib/ProjectContext";
import type { Language } from "../types";

interface Coverage {
  language: Language;
  translated: number;
}

export default function Dashboard() {
  const { currentProject } = useProjects();
  const [languages, setLanguages] = useState<Language[]>([]);
  const [keyCount, setKeyCount] = useState(0);
  const [coverage, setCoverage] = useState<Coverage[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!currentProject) return;
    setLoading(true);

    (async () => {
      const [{ data: langs }, { count }] = await Promise.all([
        supabase.from("languages").select("*").eq("project_id", currentProject.id).order("name"),
        supabase
          .from("translation_keys")
          .select("*", { count: "exact", head: true })
          .eq("project_id", currentProject.id),
      ]);

      setLanguages(langs ?? []);
      setKeyCount(count ?? 0);

      const results: Coverage[] = [];
      for (const lang of langs ?? []) {
        const { count: translatedCount } = await supabase
          .from("translations")
          .select("id, translation_keys!inner(project_id)", { count: "exact", head: true })
          .eq("language_id", lang.id)
          .eq("translation_keys.project_id", currentProject.id);
        results.push({ language: lang, translated: translatedCount ?? 0 });
      }
      setCoverage(results);
      setLoading(false);
    })();
  }, [currentProject]);

  if (!currentProject) {
    return (
      <p className="text-slate-500">
        No project selected yet. Head to <Link className="underline" to="/projects">Projects</Link> to
        create one, or ask a project owner to add you as an admin/editor.
      </p>
    );
  }

  return (
    <div>
      <h1 className="text-2xl font-semibold mb-1">{currentProject.name}</h1>
      <p className="text-slate-500 mb-6">
        Slug: <code>{currentProject.slug}</code> · Default language:{" "}
        <code>{currentProject.default_locale}</code>
      </p>

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <StatCard label="Languages" value={languages.length} />
        <StatCard label="Translatable strings" value={keyCount} />
        <StatCard
          label="Avg. coverage"
          value={
            coverage.length
              ? Math.round(
                  (coverage.reduce((sum, c) => sum + (keyCount ? c.translated / keyCount : 0), 0) /
                    coverage.length) *
                    100,
                ) + "%"
              : "—"
          }
        />
      </div>

      <h2 className="text-lg font-semibold mb-3">Coverage by language</h2>
      {loading ? (
        <p className="text-slate-400 text-sm">Loading…</p>
      ) : (
        <div className="bg-white border rounded-lg divide-y">
          {coverage.map(({ language, translated }) => {
            const pct = keyCount ? Math.round((translated / keyCount) * 100) : 0;
            return (
              <div key={language.id} className="flex items-center gap-4 px-4 py-3">
                <span className="w-28 text-sm font-medium">
                  {language.flag_emoji} {language.name}
                </span>
                <div className="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
                  <div className="h-full bg-slate-900" style={{ width: `${pct}%` }} />
                </div>
                <span className="w-24 text-right text-sm text-slate-500">
                  {translated}/{keyCount} ({pct}%)
                </span>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}

function StatCard({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="bg-white border rounded-lg p-4">
      <div className="text-sm text-slate-500">{label}</div>
      <div className="text-2xl font-semibold mt-1">{value}</div>
    </div>
  );
}
