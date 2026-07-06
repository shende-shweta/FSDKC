import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { FormEvent, useState } from 'react';
import { api } from '../api/client';
import IvrTree from '../components/IvrTree';
import LiveTestFeed from '../components/LiveTestFeed';
import { useRealtimeTest } from '../hooks/useRealtimeTest';
import { useUiStore } from '../store/uiStore';
import type { DiscoveryJob, DiscoveryNode, Transcript } from '../types';

/**
 * AC-C02 improvements applied:
 *   - SSE error rendered as <p role="alert">.
 *   - Form <label> elements linked via htmlFor / id pairs (WCAG 1.3.1).
 *   - Floating async Promise fixed with void keyword.
 *   - jobsQuery.isError fallback rendered in jobs table.
 *   - Cache invalidation moved to finally block.
 */
export default function DiscoveryPage() {
  const queryClient = useQueryClient();
  const selectedId  = useUiStore((s) => s.selectedDiscoveryId);
  const setSelectedId = useUiStore((s) => s.setSelectedDiscoveryId);
  const { events, isRunning, progress, error, startDiscovery } = useRealtimeTest('discovery');

  const [form, setForm] = useState({ name: '', phone_number: '', country_code: 'US' });

  const jobsQuery = useQuery({
    queryKey: ['discovery', 'jobs'],
    queryFn: () => api.get<{ data: DiscoveryJob[] }>('/discovery/jobs'),
    refetchInterval: isRunning ? 2000 : false,
  });

  const treeQuery = useQuery({
    queryKey: ['discovery', 'tree', selectedId],
    queryFn: () => api.get<{ tree: DiscoveryNode[] }>(`/discovery/jobs/${selectedId}/tree`),
    enabled: selectedId !== null,
    refetchInterval: isRunning ? 2000 : false,
  });

  const transcriptsQuery = useQuery({
    queryKey: ['mongodb', 'transcripts', 'discovery', selectedId],
    queryFn: () =>
      api.get<{ data: Transcript[] }>(
        `/mongodb/transcripts?module=discovery&reference_id=${selectedId}`,
      ),
    enabled: selectedId !== null,
    refetchInterval: isRunning ? 1500 : false,
  });

  const createMutation = useMutation({
    mutationFn: (body: typeof form) => api.post('/discovery/jobs', { ...body, languages: ['en'] }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['discovery'] });
      setForm({ name: '', phone_number: '', country_code: 'US' });
    },
  });

  // AC-C02: cache invalidation in finally so it runs even on error
  const handleStart = async (jobId: number) => {
    setSelectedId(jobId);
    try {
      await startDiscovery(jobId);
    } finally {
      queryClient.invalidateQueries({ queryKey: ['discovery'] });
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
        <h1>Discovery</h1>
        <p>Automated IVR discovery with real-time MongoDB event streaming</p>
      </header>

      {/* AC-C02: SSE error banner */}
      {error && (
        <p role="alert" style={{ color: 'var(--danger)', marginBottom: '1rem' }}>
          {error}
        </p>
      )}

      <section className="card" style={{ marginBottom: '1.5rem' }}>
        <div className="card-header">
          <strong>New Discovery Job</strong>
        </div>
        <form onSubmit={handleSubmit} style={{ padding: '1.25rem' }}>
          <div className="form-grid">
            {/* AC-C02: htmlFor / id pairs for screen-reader association */}
            <div className="form-group">
              <label htmlFor="disc-name">Job Name</label>
              <input
                id="disc-name"
                required
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
                placeholder="Bank IVR - US"
              />
            </div>
            <div className="form-group">
              <label htmlFor="disc-phone">Phone Number</label>
              <input
                id="disc-phone"
                required
                value={form.phone_number}
                onChange={(e) => setForm({ ...form, phone_number: e.target.value })}
                placeholder="+18005551234"
              />
            </div>
            <div className="form-group">
              <label htmlFor="disc-country">Country</label>
              <input
                id="disc-country"
                required
                value={form.country_code}
                onChange={(e) => setForm({ ...form, country_code: e.target.value })}
                placeholder="US"
              />
            </div>
          </div>
          <button type="submit" className="btn btn-primary" disabled={createMutation.isPending}>
            {createMutation.isPending ? 'Creating…' : 'Create Job'}
          </button>
        </form>
      </section>

      <section style={{ marginBottom: '1.5rem' }}>
        <LiveTestFeed events={events} isRunning={isRunning} progress={progress} title="Discovery — Live IVR Test" />
      </section>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1.5rem' }}>
        <section className="card">
          <div className="card-header">
            <strong>Discovery Jobs</strong>
          </div>
          {jobsQuery.isLoading ? (
            <div className="empty">Loading…</div>
          ) : jobsQuery.isError ? (
            /* AC-C02: render error state instead of silent empty table */
            <div className="empty" style={{ color: 'var(--danger)' }}>Failed to load jobs.</div>
          ) : (
            <table>
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Status</th>
                  <th>Nodes</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {jobsQuery.data?.data.map((job) => (
                  <tr
                    key={job.id}
                    onClick={() => setSelectedId(job.id)}
                    style={{ cursor: 'pointer', background: selectedId === job.id ? 'var(--surface-2)' : undefined }}
                  >
                    <td>
                      <div>{job.name}</div>
                      <div style={{ fontSize: '0.8rem', color: 'var(--muted)' }}>{job.phone_number}</div>
                    </td>
                    <td><span className={`badge badge-${job.status}`}>{job.status}</span></td>
                    <td>{job.nodes_discovered}</td>
                    <td>
                      {(job.status === 'pending' || job.status === 'completed') && (
                        <button
                          className="btn btn-sm btn-primary"
                          aria-label={`Start test for ${job.name}`}
                          disabled={isRunning}
                          // AC-C02: void prevents floating-Promise lint warning
                          onClick={(e) => { e.stopPropagation(); void handleStart(job.id); }}
                        >
                          {isRunning && selectedId === job.id ? 'Running…' : 'Start Test'}
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </section>

        <section className="card">
          <div className="card-header">
            <strong>IVR Tree View</strong>
          </div>
          {!selectedId ? (
            <div className="empty">Select a job to view its IVR tree</div>
          ) : treeQuery.isLoading ? (
            <div className="empty">Loading tree…</div>
          ) : treeQuery.data?.tree.length ? (
            <IvrTree nodes={treeQuery.data.tree} />
          ) : (
            <div className="empty">No nodes yet — run a discovery test</div>
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
                <div>{String(t.payload?.transcript ?? JSON.stringify(t.payload))}</div>
              </div>
            ))}
          </div>
        </section>
      ) : null}
    </>
  );
}
