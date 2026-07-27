import { createContext, useContext, useEffect, useState, ReactNode } from "react";
import { supabase } from "../supabaseClient";
import type { Project } from "../types";
import { useAuth } from "./AuthContext";

interface ProjectContextValue {
  projects: Project[];
  currentProject: Project | null;
  setCurrentProjectId: (id: string) => void;
  loading: boolean;
  refresh: () => Promise<void>;
}

const ProjectContext = createContext<ProjectContextValue | undefined>(undefined);
const STORAGE_KEY = "upwa_admin_current_project";

export function ProjectProvider({ children }: { children: ReactNode }) {
  const { user } = useAuth();
  const [projects, setProjects] = useState<Project[]>([]);
  const [currentProjectId, setCurrentProjectIdState] = useState<string | null>(
    localStorage.getItem(STORAGE_KEY),
  );
  const [loading, setLoading] = useState(true);

  async function refresh() {
    if (!user) {
      setProjects([]);
      setLoading(false);
      return;
    }
    setLoading(true);
    const { data, error } = await supabase
      .from("admin_users")
      .select("role, projects(*)")
      .eq("user_id", user.id);

    if (!error && data) {
      const rows = data
        .map((row: any) => row.projects as Project)
        .filter(Boolean);
      setProjects(rows);
      if (rows.length && !rows.find((p) => p.id === currentProjectId)) {
        setCurrentProjectId(rows[0].id);
      }
    }
    setLoading(false);
  }

  useEffect(() => {
    refresh();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [user]);

  function setCurrentProjectId(id: string) {
    setCurrentProjectIdState(id);
    localStorage.setItem(STORAGE_KEY, id);
  }

  const currentProject = projects.find((p) => p.id === currentProjectId) ?? projects[0] ?? null;

  return (
    <ProjectContext.Provider
      value={{ projects, currentProject, setCurrentProjectId, loading, refresh }}
    >
      {children}
    </ProjectContext.Provider>
  );
}

export function useProjects() {
  const ctx = useContext(ProjectContext);
  if (!ctx) throw new Error("useProjects must be used within ProjectProvider");
  return ctx;
}
