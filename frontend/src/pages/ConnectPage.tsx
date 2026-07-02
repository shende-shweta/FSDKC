import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { FormEvent, useState } from 'react';
import { api } from '../api/client';
import LiveTestFeed from '../components/LiveTestFeed';
import { useRealtimeTest } from '../hooks/useRealtimeTest';
import { useUiStore } from '../store/uiStore';
import type { ConnectCheckResult, ConnectMonitor, Transcript } from '../types';

export default function ConnectPage() {
  const queryClient   = useQueryClient();
  const selectedId    = useUiStore((s) => s.selectedMonitorId);
  const setSelectedId = useUiStore((s) => s.setSelectedMonitorId);
  const { events, isRunning, progress, error, startConnectCheck } = useRealtimeTest('connect');

  const [form, setForm] = useState({
    name:             '',
    toll_free_number: '',
    country_code:     'US',
    carrier:          '',
  });

  const monitorsQuery = useQuery({
    queryKey: ['connect', 'monitors'],
    queryFn:  () => api.get<{ data: ConnectMonitor[] }>('/connect/monitors'),
    refetchInterval: isRunning ? 2000 : false,
  });

  const checksQuery = useQuery({
    queryKey: ['connect', 'checks', selectedId],
    queryFn:  () =>
      api.get<{ data: ConnectCheckResult[] }>(`/connect/monitors/${selectedId}/checks`),
    enabled:  selectedId !== null,
    refetchInterval: isRunning ? 2000 : false,
  });

  const transcriptsQuery = useQuery({
    queryKey: ['mongodb', 'transcripts', 'connect', selectedId],
    queryFn:  () =>
      api.get<{ data: Transcript[] }>(
        `/mongodb/transcripts?module=connect&reference_id=${selectedId}`
      ),
    enabled:  selectedId !== null,
    refetchInterval: isRunning ? 1500 : false,
  });

  const createMutation = useMutation({
    mutationFn: (body: typeof form) => api.post('/connect/monitors', body),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['connect'] });
      setForm({ name: '', toll_free_number: '', country_code: 'US', carrier: '' });
    },
  });

  const handleRunCheck = async (monitorId: number) => {
    setSelectedId(monitorId);
    try {
      await startConnectCheck(monitorId);
    } finally {
      queryClient.invalidateQueries({ queryKey: ['connect'] });
      queryClient.invalidateQueries({ queryKey: ['mongodb'] });
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
    }
  };

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    createMutation.mutate(form);
  };

  return (
    <>
      <header className="page-header">
        <h1>Connect</h1>
        <p>TFN reachability testing with live MongoDB event streaming</p>
      </header>

      <section className="card" style={{ marginBottom: '1.5rem' }}>
        <div className="card-header">
          <strong>Add TFN Monitor</strong>
        </div>
        <form onSubmit={handleSubmit} style={{ padding: '1.25rem' }}>
          <div className="form-grid">
            <div className="form-group">
              <label htmlFor="conn-name">Monitor Name</label>
              <input
                id="conn-name"
                required
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
              />
            </div>
            <div className="form-group">
              <label htmlFor="conn-tfn">Toll-Free Number</label>
              <input
                id="conn-tfn"
                required
                value={form.toll_free_number}
                onChange={(e) => setForm({ ...form, toll_free_number: e.target.value })}
              />
            </div>
            <div className="form-group">
              <label htmlFor="conn-country">Country</label>
              <input
                id="conn-country"
                required
                value={form.country_code}
                onChange={(e) => setForm({ ...form, country_code: e.target.value })}
              />
            </div>
            <div className="form-group">
              <label htmlFor="conn-carrier">Carrier</label>
              <input
                id="conn-carrier"
                value={form.carrier}
                onChange={(e) => setForm({ ...form, carrier: e.target.value })}
                placeholder="Verizon"
              />
            </div>
          </div>
          {createMutation.isError && (
            <p role="alert" style={{ color: 'var(--danger)', marginBottom: '0.5rem' }}>
              {(createMutation.error as Error).message}
            </p>
          )}
          <button type="submit" className="btn btn-primary" disabled={createMutation.isPending}>
            {createMutation.isPending ? 'Adding…' : 'Add Monitor'}
          </button>
        </form>
      </section>

      {error && (
        <p role="alert" style={{ color: 'var(--danger)', marginBottom: '1rem' }}>
          Stream error: {error}
        </p>
      )}

      <section style={{ marginBottom: '1.5rem' }}>
        <LiveTestFeed events={events} isRunning={isRunning} progress={progress} title="Connect — Live Reachability Test" />
      </section>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1.5rem' }}>
        <section className="card">
          <div className="card-header">
            <strong>TFN Monitors</strong>
          </div>
          {monitorsQuery.isLoading ? (
            <div className="empty">Loading…</div>
          ) : monitorsQuery.isError ? (
            <div className="error">Failed to load monitors.</div>
          ) : (
            <table>
              <thead>
                <tr>
                  <th>Monitor</th>
                  <th>Reachability</th>
                  <th>Status</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {monitorsQuery.data?.data.map((m) => (
                  <tr
                    key={m.id}
                    onClick={() => setSelectedId(m.id)}
                    style={{ cursor: 'pointer', background: selectedId === m.id ? 'var(--surface-2)' : undefined }}
                  >
                    <td>
                      <div>{m.name}</div>
                      <div style={{ fontSize: '0.8rem', color: 'var(--muted)' }}>
                        {m.toll_free_number} · {m.country_code}
                      </div>
                    </td>
                    <td style={{ fontVariantNumeric: 'tabular-nums' }}>{m.reachability_pct}%</td>
                    <td><span className={`badge badge-${m.status}`}>{m.status}</span></td>
                    <td>
                      <button
                        className="btn btn-sm btn-secondary"
                        disabled={isRunning}
                        aria-label={`Run reachability test for ${m.name}`}
                        onClick={(e) => { e.stopPropagation(); void handleRunCheck(m.id); }}
                      >
                        {isRunning && selectedId === m.id ? 'Testing…' : 'Run Test'}
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </section>

        <section className="card">
          <div className="card-header">
            <strong>Check History</strong>
            {selectedId && (
              <button
                className="btn btn-sm btn-primary"
                disabled={isRunning}
                aria-label="Run reachability test for selected monitor"
                onClick={() => void handleRunCheck(selectedId)}
              >
                Run Test
              </button>
            )}
          </div>
          {!selectedId ? (
            <div className="empty">Select a monitor to view check history</div>
          ) : checksQuery.isLoading ? (
            <div className="empty">Loading checks…</div>
          ) : checksQuery.data?.data.length ? (
            <table>
              <thead>
                <tr>
                  <th>Result</th>
                  <th>Latency</th>
                  <th>Route</th>
                  <th>Time</th>
                </tr>
              </thead>
              <tbody>
                {checksQuery.data.data.map((c) => (
                  <tr key={c.id}>
                    <td>
                      <span className={`badge badge-${c.reachable ? 'active' : 'alert'}`}>
                        {c.reachable ? 'Reachable' : 'Failed'}
                      </span>
                      {!c.reachable && c.failure_reason && (
                        <div style={{ fontSize: '0.75rem', color: 'var(--danger)', marginTop: '0.25rem' }}>
                          {c.failure_reason}
                        </div>
                      )}
                    </td>
                    <td>{c.latency_ms ? `${c.latency_ms}ms` : '—'}</td>
                    <td style={{ fontSize: '0.8rem' }}>{c.carrier_route ?? '—'}</td>
                    <td style={{ fontSize: '0.8rem', color: 'var(--muted)' }}>
                      {new Date(c.checked_at).toLocaleString()}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : (
            <div className="empty">No checks yet. Run a reachability test.</div>
          )}
        </section>
      </div>

      {selectedId && transcriptsQuery.data?.data.length ? (
        <section className="card" style={{ marginTop: '1.5rem' }}>
          <div className="card-header">
            <strong>MongoDB Transcripts</strong>
          </div>
          <div className="transcript-list">
            {transcriptsQuery.data.data.map((t) => (
              <div key={t._id} className="transcript-item">
                <div className="event-type">{String(t.payload?.event ?? 'transcript')}</div>
                <div>
                  {t.payload?.latency_ms != null && `Latency: ${t.payload.latency_ms}ms · `}
                  {String(t.payload?.failure_reason ?? t.payload?.carrier_route ?? '')}
                </div>
              </div>
            ))}
          </div>
        </section>
      ) : null}
    </>
  );
}
