import { NavLink, Outlet } from "react-router-dom";
import { useAuth } from "../lib/AuthContext";
import { useProjects } from "../lib/ProjectContext";

const navItems = [
  { to: "/", label: "Dashboard", end: true },
  { to: "/languages", label: "Languages" },
  { to: "/strings", label: "Strings" },
  { to: "/analytics", label: "Analytics" },
  { to: "/embed", label: "Embed code" },
  { to: "/projects", label: "Projects" },
];

export default function Layout() {
  const { user, signOut } = useAuth();
  const { projects, currentProject, setCurrentProjectId } = useProjects();

  return (
    <div className="min-h-screen flex">
      <aside className="w-60 shrink-0 bg-slate-900 text-slate-100 flex flex-col">
        <div className="px-4 py-5 text-lg font-semibold border-b border-slate-800">
          Universal-PWA
        </div>
        <nav className="flex-1 py-4 space-y-1">
          {navItems.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.end}
              className={({ isActive }) =>
                `block px-4 py-2 text-sm rounded-md mx-2 ${
                  isActive ? "bg-slate-800 text-white" : "text-slate-300 hover:bg-slate-800/60"
                }`
              }
            >
              {item.label}
            </NavLink>
          ))}
        </nav>
        <div className="px-4 py-4 border-t border-slate-800 text-xs text-slate-400">
          <div className="truncate mb-2">{user?.email}</div>
          <button onClick={() => signOut()} className="text-slate-300 hover:text-white underline">
            Sign out
          </button>
        </div>
      </aside>

      <div className="flex-1 flex flex-col">
        <header className="h-14 border-b bg-white flex items-center justify-between px-6">
          <div className="text-sm text-slate-500">Project</div>
          {projects.length > 0 ? (
            <select
              className="text-sm border rounded-md px-2 py-1"
              value={currentProject?.id ?? ""}
              onChange={(e) => setCurrentProjectId(e.target.value)}
            >
              {projects.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </select>
          ) : (
            <span className="text-sm text-slate-400">No projects yet</span>
          )}
        </header>
        <main className="flex-1 p-6 overflow-y-auto">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
