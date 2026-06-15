import { NavLink, Navigate, Route, Routes } from 'react-router-dom';
import MongoStatus from './components/MongoStatus';
import DashboardPage from './pages/DashboardPage';
import DiscoveryPage from './pages/DiscoveryPage';
import ConnectPage from './pages/ConnectPage';
import LoginPage from './pages/LoginPage';

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route
        path="/*"
        element={
          <div className="app-layout">
            <aside className="sidebar">
              <div className="logo">xy<span>z</span></div>
              <nav>
                <NavLink to="/" end className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`}>
                  Dashboard
                </NavLink>
                <NavLink to="/discovery" className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`}>
                  Discovery
                </NavLink>
                <NavLink to="/connect" className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`}>
                  Connect
                </NavLink>
              </nav>
              <div style={{ marginTop: 'auto' }}>
                <MongoStatus />
                <div style={{ marginTop: '0.75rem', fontSize: '0.75rem', color: 'var(--muted)' }}>
                  Voice Observability Platform
                </div>
              </div>
            </aside>
            <main className="main">
              <Routes>
                <Route path="/" element={<DashboardPage />} />
                <Route path="/discovery" element={<DiscoveryPage />} />
                <Route path="/connect" element={<ConnectPage />} />
                <Route path="*" element={<Navigate to="/" replace />} />
              </Routes>
            </main>
          </div>
        }
      />
    </Routes>
  );
}
