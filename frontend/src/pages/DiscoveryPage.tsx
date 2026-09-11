import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { FormEvent, useState } from 'react';
import { api } from '../api/client';
import { endpoints } from '../api/endpoints';
import LiveTestFeed from '../components/LiveTestFeed';
import { useRealtimeTest } from '../hooks/useRealtimeTest';
import { useUiStore } from '../store/uiStore';
import type { DiscoveryJob, DiscoveryNode, Transcript } from '../types';
import IvrTree from '../components/IvrTree';

export default function DiscoveryPage() {
  const queryClient = useQueryClient();
  const selectedId = useUiStore((s) => s.selectedDiscoveryId);
  const setSelectedId = useUiStore((s) => s.setSelectedDiscoveryId);
  const { events, isRunning, progress, startDiscovery } = useRealtimeTest('discovery');

  const [form, setForm] = useState({ name: '', phone_number: '', country_code: 'US' });

  const jobsQuery = useQuery({
    queryKey: ['discovery', 'jobs'],
    queryFn: () => api.get<{ data: DiscoveryJob[] }>(endpoints.discovery.jobs),
    refetchInterval: isRunning ? 2000 : false,
  });

  const treeQuery = useQuery({
    queryKey: ['discovery', 'tree', selectedId],
    queryFn: () => api.get<{ tree: DiscoveryNode[] }>(endpoints.discovery.tree(selectedId!)),
    enabled: selectedId !== null,
    refetchInterval: isRunning ? 2000 : false,
  });

  const transcriptsQuery = useQuery({
    queryKey: ['mongodb', 'transcripts', 'discovery', selectedId],
    queryFn: () => api.get<{ data: Transcript[] }>(endpoints.mongodb.transcripts('discovery', selectedId!)),
    enabled: selectedId !== null,
    refetchInterval: isRunning ? 1500 : false,
  });

  const createMutation = useMutation({
    mutationFn: (body: typeof form) => api.post(endpoints.discovery.jobs, { ...body, languages: ['en'] }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['discovery'] });
      setForm({ name: '', phone_number: '', country_code: 'US' });
    },
  });

  const handleStart = async (jobId: number) => {
    setSelectedId(jobId);
    await startDiscovery(jobId);
    queryClient.invalidateQueries({ queryKey: ['discovery'] });
    queryClient.invalidateQueries({ queryKey: ['mongodb'] });
    queryClient.invalidateQueries({ queryKey: ['dashboard'] });
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

      <section className="card" style={{ marginBottom: '1.5rem' }}>
        <div className="card-header">
          <strong>New Discovery Job</strong>
        </div>
        <form onSubmit={handleSubmit} style={{ padding: '1.25rem' }}>
          <div className="form-grid">
            <div className="form-group">
              <label>Job Name</label>
              <input required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="Bank IVR - US" />
            </div>
            <div className="form-group">
              <label>Phone Number</label>
              <input required value={form.phone_number} onChange={(e) => setForm({ ...form, phone_number: e.target.value })} placeholder="+18005551234" />
            </div>
            <div className="form-group">
              <label>Country</label>
              <input required value={form.country_code} onChange={(e) => setForm({ ...form, country_code: e.target.value })} placeholder="US" />
            </div>
          </div>
          <button type="submit" className="btn btn-primary" disabled={createMutation.isPending}>
            {createMutation.isPending ? 'Creating\u2026' : 'Create Job'}
          </button>
        </form>
      </section>

      <section style={{ marginBottom: '1.5rem' }}>
        <LiveTestFeed events={events} isRunning={isRunning} progress={progress} title="Discovery \u2014 Live IVR Test" />
      </section>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1.5rem' }}>
        <section className="card">
          <div className="card-header">
            <strong>Discovery Jobs</strong>
          </div>
          {jobsQuery.isLoading ? (
            <div className="empty">Loading\u2026</div>
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
                          disabled={isRunning}
                          onClick={(e) => { e.stopPropagation(); handleStart(job.id); }}
                        >
                          {isRunning && selectedId === job.id ? 'Running\u2026' : 'Start Test'}
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
            <div className="empty">Loading tree\u2026</div>
          ) : treeQuery.data?.tree.length ? (
            <IvrTree nodes={treeQuery.data.tree} />
          ) : (
            <div className="empty">No nodes yet \u2014 run a discovery test</div>
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
