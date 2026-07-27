import { FormEvent, useState } from "react";
import { supabase } from "../supabaseClient";
import { useProjects } from "../lib/ProjectContext";

function slugify(text: string) {
  return text
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/(^-|-$)/g, "");
}

export default function Projects() {
  const { projects, currentProject, setCurrentProjectId, refresh } = useProjects();
  const [name, setName] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [domains, setDomains] = useState(currentProject?.allowed_domains.join(", ") ?? "");
  const [savingDomains, setSavingDomains] = useState(false);

  async function createProject(e: FormEvent) {
    e.preventDefault();
    setError(null);
    if (!name.trim()) return;
    const { data, error } = await supabase.rpc("create_project_as_owner", {
      p_name: name.trim(),
      p_slug: slugify(name),
      p_default_locale: "en",
    });
    if (error) {
      setError(error.message);
      return;
    }
    setName("");
    await refresh();
    if (data?.id) setCurrentProjectId(data.id);
  }

  async function saveDomains() {
    if (!currentProject) return;
    setSavingDomains(true);
    const list = domains.split(",").map((d) => d.trim()).filter(Boolean);
    await supabase.from("projects").update({ allowed_domains: list }).eq("id", currentProject.id);
    setSavingDomains(false);
    refresh();
  }

  return (
    <div>
      <h1 className="text-2xl font-semibold mb-6">Projects</h1>

      <div className="bg-white border rounded-lg divide-y mb-8">
        {projects.map((p) => (
          <div key={p.id} className="flex items-center gap-4 px-4 py-3">
            <span className="font-medium">{p.name}</span>
            <code className="text-xs text-slate-400">{p.slug}</code>
            {currentProject?.id === p.id && (
              <span className="text-xs bg-slate-100 px-2 py-0.5 rounded-full">current</span>
            )}
          </div>
        ))}
        {projects.length === 0 && <p className="p-4 text-sm text-slate-400">No projects yet.</p>}
      </div>

      {currentProject && (
        <div className="bg-white border rounded-lg p-4 mb-8">
          <h2 className="text-sm font-medium text-slate-500 mb-2">
            Allowed domains for {currentProject.name}
          </h2>
          <p className="text-xs text-slate-400 mb-2">
            Informational allowlist — document which sites embed this project. Comma-separated.
          </p>
          <div className="flex gap-2">
            <input
              value={domains}
              onChange={(e) => setDomains(e.target.value)}
              className="flex-1 border rounded-md px-3 py-1.5 text-sm"
              placeholder="zuriafricaadventures.com, www.zuriafricaadventures.com"
            />
            <button
              onClick={saveDomains}
              disabled={savingDomains}
              className="bg-slate-900 text-white rounded-md px-4 py-1.5 text-sm disabled:opacity-50"
            >
              Save
            </button>
          </div>
        </div>
      )}

      <h2 className="text-sm font-medium text-slate-500 mb-2">Create a new project</h2>
      <form onSubmit={createProject} className="bg-white border rounded-lg p-4 flex gap-3 items-end">
        <div className="flex-1">
          <label className="block text-xs text-slate-500 mb-1">Name</label>
          <input
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="e.g. My WordPress Blog"
            className="w-full border rounded-md px-3 py-1.5 text-sm"
          />
        </div>
        <button type="submit" className="bg-slate-900 text-white rounded-md px-4 py-1.5 text-sm">
          Create
        </button>
      </form>
      {error && <p className="text-sm text-red-600 mt-2">{error}</p>}
    </div>
  );
}
