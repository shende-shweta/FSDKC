import { NavLink, Route, Routes } from 'react-router-dom';
import ErrorBoundary from './components/ErrorBoundary';
import MongoStatus from './components/MongoStatus';
import DashboardPage from './pages/DashboardPage';
import DiscoveryPage from './pages/DiscoveryPage';
import ConnectPage from './pages/ConnectPage';

export default function App() {
  return (
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
        <ErrorBoundary>
          <Routes>
            <Route path="/" element={<ErrorBoundary><DashboardPage /></ErrorBoundary>} />
            <Route path="/discovery" element={<ErrorBoundary><DiscoveryPage /></ErrorBoundary>} />
            <Route path="/connect" element={<ErrorBoundary><ConnectPage /></ErrorBoundary>} />
          </Routes>
        </ErrorBoundary>
      </main>
    </div>
  );
}
