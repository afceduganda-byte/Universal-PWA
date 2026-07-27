import { useEffect, useState } from "react";
import { supabase } from "../supabaseClient";
import { useProjects } from "../lib/ProjectContext";

interface Row {
  language_code: string;
  count: number;
}

export default function Analytics() {
  const { currentProject } = useProjects();
  const [rows, setRows] = useState<Row[]>([]);
  const [total, setTotal] = useState(0);
  const [days, setDays] = useState(30);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!currentProject) return;
    setLoading(true);
    const since = new Date(Date.now() - days * 24 * 60 * 60 * 1000).toISOString();

    supabase
      .from("language_stats")
      .select("language_code")
      .eq("project_id", currentProject.id)
      .gte("created_at", since)
      .then(({ data }) => {
        const counts: Record<string, number> = {};
        (data ?? []).forEach((r) => {
          counts[r.language_code] = (counts[r.language_code] ?? 0) + 1;
        });
        const sorted = Object.entries(counts)
          .map(([language_code, count]) => ({ language_code, count }))
          .sort((a, b) => b.count - a.count);
        setRows(sorted);
        setTotal(sorted.reduce((s, r) => s + r.count, 0));
        setLoading(false);
      });
  }, [currentProject, days]);

  if (!currentProject) return <p className="text-slate-500">Select a project first.</p>;

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-2xl font-semibold">Analytics</h1>
        <select
          value={days}
          onChange={(e) => setDays(Number(e.target.value))}
          className="text-sm border rounded-md px-2 py-1"
        >
          <option value={7}>Last 7 days</option>
          <option value={30}>Last 30 days</option>
          <option value={90}>Last 90 days</option>
        </select>
      </div>

      <p className="text-sm text-slate-500 mb-4">
        {total} language selection{total === 1 ? "" : "s"} logged by the embedded widget.
      </p>

      {loading ? (
        <p className="text-sm text-slate-400">Loading…</p>
      ) : rows.length === 0 ? (
        <p className="text-sm text-slate-400">
          No data yet. Make sure the widget's <code>data-log-endpoint</code> points at your deployed{" "}
          <code>log-language-stat</code> edge function.
        </p>
      ) : (
        <div className="bg-white border rounded-lg divide-y">
          {rows.map((r) => {
            const pct = total ? Math.round((r.count / total) * 100) : 0;
            return (
              <div key={r.language_code} className="flex items-center gap-4 px-4 py-3">
                <span className="w-16 text-sm font-medium uppercase">{r.language_code}</span>
                <div className="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
                  <div className="h-full bg-slate-900" style={{ width: `${pct}%` }} />
                </div>
                <span className="w-24 text-right text-sm text-slate-500">
                  {r.count} ({pct}%)
                </span>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
