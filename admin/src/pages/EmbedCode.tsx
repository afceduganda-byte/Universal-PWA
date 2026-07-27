import { useState } from "react";
import { useProjects } from "../lib/ProjectContext";

const WIDGET_BASE =
  (import.meta.env.VITE_WIDGET_BASE_URL as string) ||
  "https://cdn.jsdelivr.net/gh/afceduganda-byte/Universal-PWA@main/widget";

export default function EmbedCode() {
  const { currentProject } = useProjects();
  const [copied, setCopied] = useState(false);

  if (!currentProject) return <p className="text-slate-500">Select a project first.</p>;

  const supabaseUrl = import.meta.env.VITE_SUPABASE_URL as string;
  const anonKey = import.meta.env.VITE_SUPABASE_ANON_KEY as string;
  const functionsUrl = (import.meta.env.VITE_FUNCTIONS_URL as string) || `${supabaseUrl}/functions/v1`;

  const snippet = `<!-- Universal-PWA Translator: paste before </body> -->
<link rel="stylesheet" href="${WIDGET_BASE}/translator.css" />
<script
  src="${WIDGET_BASE}/translator.js"
  data-supabase-url="${supabaseUrl}"
  data-supabase-anon-key="${anonKey}"
  data-project-slug="${currentProject.slug}"
  data-position="bottom-right"
  data-auto-scan="true"
  data-log-endpoint="${functionsUrl}/log-language-stat"
></script>`;

  function copy() {
    navigator.clipboard.writeText(snippet);
    setCopied(true);
    setTimeout(() => setCopied(false), 1500);
  }

  return (
    <div>
      <h1 className="text-2xl font-semibold mb-2">Embed code</h1>
      <p className="text-slate-500 mb-6">
        Paste this once, right before the closing <code>&lt;/body&gt;</code> tag, on any site — a
        plain HTML page, a Lovable/React app's <code>index.html</code>, or via the WordPress plugin's
        settings page.
      </p>

      <div className="bg-slate-900 text-slate-100 rounded-lg p-4 relative">
        <pre className="text-xs overflow-x-auto whitespace-pre-wrap">{snippet}</pre>
        <button
          onClick={copy}
          className="absolute top-3 right-3 text-xs bg-slate-700 hover:bg-slate-600 text-white rounded-md px-2 py-1"
        >
          {copied ? "Copied!" : "Copy"}
        </button>
      </div>

      <div className="mt-6 text-sm text-slate-600 space-y-2">
        <p>
          <strong>Lovable / React apps:</strong> add the snippet directly to{" "}
          <code>index.html</code>, or into a small effect in your root component if you prefer
          keeping it in JSX.
        </p>
        <p>
          <strong>WordPress:</strong> install <code>wordpress-plugin/universal-pwa-translator.php</code>,
          then paste the Supabase URL, anon key, and this project's slug into its settings page — no
          need to touch theme files.
        </p>
        <p>
          The anon key is safe to expose publicly: Supabase Row Level Security only allows public{" "}
          <em>reads</em> of languages/translations, and requires a signed-in project member for any
          writes.
        </p>
      </div>
    </div>
  );
}
