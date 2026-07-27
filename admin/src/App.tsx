import { Navigate, Route, Routes } from "react-router-dom";
import { useAuth } from "./lib/AuthContext";
import Layout from "./components/Layout";
import Login from "./pages/Login";
import Dashboard from "./pages/Dashboard";
import Languages from "./pages/Languages";
import Strings from "./pages/Strings";
import Projects from "./pages/Projects";
import Analytics from "./pages/Analytics";
import EmbedCode from "./pages/EmbedCode";

function ProtectedRoute({ children }: { children: JSX.Element }) {
  const { session, loading } = useAuth();
  if (loading) return <div className="p-8 text-slate-500">Loading…</div>;
  if (!session) return <Navigate to="/login" replace />;
  return children;
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route
        path="/"
        element={
          <ProtectedRoute>
            <Layout />
          </ProtectedRoute>
        }
      >
        <Route index element={<Dashboard />} />
        <Route path="languages" element={<Languages />} />
        <Route path="strings" element={<Strings />} />
        <Route path="projects" element={<Projects />} />
        <Route path="analytics" element={<Analytics />} />
        <Route path="embed" element={<EmbedCode />} />
      </Route>
    </Routes>
  );
}
