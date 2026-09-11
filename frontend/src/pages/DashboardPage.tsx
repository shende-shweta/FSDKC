import { useQuery } from '@tanstack/react-query';
import { api } from '../api/client';
import { endpoints } from '../api/endpoints';
import type { DashboardKpis } from '../types';

export default function DashboardPage() {
  const { data, isLoading, error } = useQuery({
    queryKey: ['dashboard', 'kpis'],
    queryFn: () => api.get<DashboardKpis>(endpoints.dashboard.kpis),
  });

  if (isLoading) return <div className="empty">Loading KPIs\u2026</div>;
  if (error) return <div className="error">Failed to load dashboard. Is the API running?</div>;

  const fmt = (v: number | null) => (v != null ? `${v}%` : '\u2014');

  return (
    <>
      <header className="page-header">
        <h1>Platform Overview</h1>
        <p>Voice observability across IVR discovery and toll-free monitoring</p>
      </header>

      <section>
        <h2 style={{ fontSize: '0.85rem', color: 'var(--muted)', marginBottom: '0.75rem', textTransform: 'uppercase', letterSpacing: '0.05em' }}>
          Availability KPIs
        </h2>
        <div className="kpi-grid">
          <KpiCard label="IVR Availability" value={fmt(data!.availability.ivr_availability_pct)} />
          <KpiCard label="Number Reachability" value={fmt(data!.availability.number_reachability_pct)} />
          <KpiCard label="Call Success Rate" value={fmt(data!.availability.call_success_rate_pct)} />
          <KpiCard label="Transfer Success" value={fmt(data!.availability.transfer_success_rate_pct)} />
        </div>
      </section>

      <section>
        <h2 style={{ fontSize: '0.85rem', color: 'var(--muted)', marginBottom: '0.75rem', textTransform: 'uppercase', letterSpacing: '0.05em' }}>
          Operational
        </h2>
        <div className="kpi-grid">
          <KpiCard label="Active Discovery Jobs" value={String(data!.operational.active_discovery_jobs)} />
          <KpiCard label="Active TFN Monitors" value={String(data!.operational.active_connect_monitors)} />
          <KpiCard label="Open Alerts" value={String(data!.operational.open_alerts)} highlight={data!.operational.open_alerts > 0} />
          <KpiCard label="Countries Monitored" value={String(data!.operational.countries_monitored)} />
        </div>
      </section>

      <section className="card" style={{ marginTop: '1rem' }}>
        <div className="card-header">
          <strong>Active Modules</strong>
          {data!.mongodb?.connected && (
            <span className="badge badge-active">
              MongoDB \u00b7 {data!.mongodb.collections?.transcripts ?? 0} transcripts
            </span>
          )}
        </div>
        <div style={{ padding: '1.25rem', display: 'flex', gap: '0.75rem' }}>
          {data!.modules.map((m) => (
            <span key={m} className="badge badge-active" style={{ textTransform: 'capitalize' }}>
              {m}
            </span>
          ))}
        </div>
      </section>
    </>
  );
}

function KpiCard({ label, value, highlight }: { label: string; value: string; highlight?: boolean }) {
  return (
    <div className="kpi-card" style={highlight ? { borderColor: 'var(--danger)' } : undefined}>
      <div className="label">{label}</div>
      <div className="value" style={highlight ? { color: 'var(--danger)' } : undefined}>{value}</div>
    </div>
  );
}
