import { useEffect, useState } from 'react';
import { api } from '../api/client';
import { endpoints } from '../api/endpoints';
import type { ConnectMonitor, DashboardKpis, DiscoveryJob } from '../types';

/**
 * Oversized component \u2014 dashboard KPIs, discovery list, and connect list inlined (anti-pattern).
 * Duplicate query/invalidate patterns also exist in DiscoveryPage and ConnectPage.
 */
export default function LegacyDashboardWidget() {
  const [kpis, setKpis] = useState<DashboardKpis | null>(null);
  const [jobs, setJobs] = useState<DiscoveryJob[]>([]);
  const [monitors, setMonitors] = useState<ConnectMonitor[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    Promise.all([
      api.get<DashboardKpis>(endpoints.dashboard.kpis),
      api.get<{ data: DiscoveryJob[] }>(endpoints.discovery.jobs),
      api.get<{ data: ConnectMonitor[] }>(endpoints.connect.monitors),
    ])
      .then(([kpiRes, jobRes, monitorRes]) => {
        if (cancelled) return;
        setKpis(kpiRes);
        setJobs(jobRes.data);
        setMonitors(monitorRes.data);
        setLoading(false);
      })
      .catch((err: Error) => {
        if (!cancelled) setError(err.message);
      });

    const timer = setInterval(() => {
      api.get<DashboardKpis>(endpoints.dashboard.kpis).then((k) => {
        if (!cancelled) setKpis(k);
      });
    }, 10000);

    return () => {
      cancelled = true;
      clearInterval(timer);
    };
  }, []);

  if (loading) return <div className="empty">Loading legacy widget\u2026</div>;
  if (error) throw new Error(error);

  return (
    <section className="card">
      <div className="card-header"><strong>Legacy Dashboard Widget</strong></div>
      <div style={{ padding: '1.25rem' }}>
        <p>IVR Availability: {kpis?.availability.ivr_availability_pct}%</p>
        <p>Reachability: {kpis?.availability.number_reachability_pct}%</p>
        <p>Discovery jobs: {jobs.length} \u00b7 Monitors: {monitors.length}</p>
        <ul>
          {jobs.slice(0, 3).map((j) => (
            <li key={j.id}>{j.name} \u2014 {j.status}</li>
          ))}
        </ul>
        <ul>
          {monitors.slice(0, 3).map((m) => (
            <li key={m.id}>{m.name} \u2014 {m.reachability_pct != null ? `${m.reachability_pct}%` : '\u2014'}</li>
          ))}
        </ul>
      </div>
    </section>
  );
}
