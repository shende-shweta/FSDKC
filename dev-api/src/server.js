import cors from 'cors';
import express from 'express';
import {
  connectMongo,
  getDbInfo,
  getTranscripts,
  healthCheck,
  seedMongoData,
  getTestEvents,
} from './mongo.js';
import { store, buildTree } from './store.js';
import { createSession, runConnectTest, runDiscoveryTest } from './realtime.js';

const PORT = process.env.PORT || 8080;
const app = express();

/** Maximum monitors accepted in a single bulk-import request (AC-B01). */
const BULK_IMPORT_MAX = 100;

app.use(cors());
app.use(express.json());

// ── Health & MongoDB ────────────────────────────────────────────────

app.get('/api/health', async (_req, res) => {
  const mongo = await healthCheck();
  res.json({
    status: 'ok',
    platform: 'Klearcom',
    version: '1.0.0',
    mongodb: mongo,
  });
});

app.get('/api/mongodb/status', async (_req, res) => {
  res.json(await healthCheck());
});

app.get('/api/mongodb/transcripts', async (req, res) => {
  const { module, reference_id } = req.query;
  if (!module || !reference_id) {
    return res.status(400).json({ error: 'module and reference_id required' });
  }
  const data = await getTranscripts(module, Number(reference_id));
  res.json({ data: data.map(serializeDoc) });
});

app.get('/api/mongodb/diagnostics/:module/:referenceId', async (req, res) => {
  const { getDb } = await import('./mongo.js');
  const data = await getDb()
    .collection('call_diagnostics')
    .find({ module: req.params.module, reference_id: Number(req.params.referenceId) })
    .sort({ created_at: -1 })
    .limit(10)
    .toArray();
  res.json({ data: data.map(serializeDoc) });
});

// ── Dashboard ───────────────────────────────────────────────────

app.get('/api/dashboard/kpis', async (_req, res) => {
  const discoveryTotal     = store.discoveryJobs.length;
  const discoveryCompleted = store.discoveryJobs.filter((j) => j.status === 'completed').length;
  const totalMonitors      = store.connectMonitors.length;

  // AC-B01: guard divide-by-zero when monitor list is empty
  const avgReach = totalMonitors > 0
    ? store.connectMonitors.reduce((s, m) => s + m.reachability_pct, 0) / totalMonitors
    : 0;

  const mongo = await healthCheck();

  res.json({
    availability: {
      ivr_availability_pct:    discoveryTotal > 0 ? Math.round((discoveryCompleted / discoveryTotal) * 1000) / 10 : 0,
      number_reachability_pct: Math.round(avgReach * 10) / 10,
      call_success_rate_pct:     94.2,
      transfer_success_rate_pct: 97.8,
    },
    operational: {
      active_discovery_jobs:   store.discoveryJobs.filter((j) => j.status === 'running').length,
      active_connect_monitors: store.connectMonitors.filter((m) => m.status === 'active').length,
      open_alerts:             store.connectMonitors.filter((m) => m.status === 'alert').length,
      countries_monitored:     new Set(store.connectMonitors.map((m) => m.country_code)).size,
    },
    modules: ['discovery', 'connect'],
    mongodb: mongo,
  });
});

// ── Discovery ─────────────────────────────────────────────────────

app.get('/api/discovery/jobs', (_req, res) => {
  res.json({ data: store.discoveryJobs });
});

// AC-B01: validate required fields before creating a discovery job
app.post('/api/discovery/jobs', (req, res) => {
  const { name, phone_number, country_code } = req.body ?? {};

  if (!name || !phone_number || !country_code) {
    return res
      .status(422)
      .json({ error: 'name, phone_number and country_code are required' });
  }

  const job = {
    id:              store.nextJobId++,
    name:            String(name),
    phone_number:    String(phone_number),
    country_code:    String(country_code),
    status:          'pending',
    menu_depth:      0,
    nodes_discovered: 0,
    languages:       Array.isArray(req.body.languages) ? req.body.languages : ['en'],
    started_at:      null,
    completed_at:    null,
  };
  store.discoveryJobs.unshift(job);
  res.status(201).json({ data: job });
});

app.get('/api/discovery/jobs/:id', async (req, res) => {
  const job = store.discoveryJobs.find((j) => j.id === Number(req.params.id));
  if (!job) return res.status(404).json({ error: 'Not found' });

  const nodes      = store.discoveryNodes.filter((n) => n.discovery_job_id === job.id);
  const transcripts = await getTranscripts('discovery', job.id);

  res.json({ data: { ...job, nodes }, transcripts: transcripts.map(serializeDoc) });
});

app.get('/api/discovery/jobs/:id/tree', (req, res) => {
  const job = store.discoveryJobs.find((j) => j.id === Number(req.params.id));
  if (!job) return res.status(404).json({ error: 'Not found' });

  const nodes = store.discoveryNodes.filter((n) => n.discovery_job_id === job.id);
  res.json({ job_id: job.id, job_name: job.name, tree: buildTree(nodes) });
});

app.post('/api/discovery/jobs/:id/start', async (req, res) => {
  const jobId = Number(req.params.id);
  const job   = store.discoveryJobs.find((j) => j.id === jobId);
  if (!job) return res.status(404).json({ error: 'Not found' });
  if (job.status === 'running') return res.status(409).json({ message: 'Job already running' });

  const sessionId = createSession();
  runDiscoveryTest(jobId, sessionId).catch((err) => {
    console.error('runDiscoveryTest error:', err);
  });

  res.json({ session_id: sessionId, message: 'Discovery test started — connect to stream endpoint' });
});

app.get('/api/discovery/jobs/:id/stream', (req, res) => {
  const sessionId = req.query.session_id;
  if (!sessionId) return res.status(400).json({ error: 'session_id query param required' });
  streamSession(res, req, sessionId);
});

// ── Connect ─────────────────────────────────────────────────────────

// AC-B01: validate each item; reject empty batch; cap at BULK_IMPORT_MAX
app.post('/api/connect/monitors/bulk-import', (req, res) => {
  const raw = Array.isArray(req.body) ? req.body : (req.body?.monitors ?? []);

  if (!Array.isArray(raw) || raw.length === 0) {
    return res.status(422).json({ error: 'monitors array must not be empty' });
  }

  if (raw.length > BULK_IMPORT_MAX) {
    return res.status(422).json({ error: `batch size exceeds maximum of ${BULK_IMPORT_MAX}` });
  }

  const invalidIdx = raw.findIndex((item) => !item?.toll_free_number);
  if (invalidIdx !== -1) {
    return res.status(422).json({ error: `item at index ${invalidIdx} is missing toll_free_number` });
  }

  const created = raw.map((item) => {
    const monitor = {
      id:                store.nextMonitorId++,
      name:              item.name ?? 'Imported',
      toll_free_number:  String(item.toll_free_number),
      country_code:      item.country_code ?? 'US',
      carrier:           item.carrier ?? null,
      status:            'active',
      reachability_pct:  typeof item.reachability_pct === 'number' ? item.reachability_pct : 100,
      last_checked_at:   null,
    };
    store.connectMonitors.unshift(monitor);
    return monitor;
  });

  res.status(201).json({ data: created, imported: created.length });
});

app.get('/api/connect/monitors', (_req, res) => {
  res.json({ data: store.connectMonitors });
});

// AC-B01: validate required fields before creating a connect monitor
app.post('/api/connect/monitors', (req, res) => {
  const { name, toll_free_number, country_code } = req.body ?? {};

  if (!name || !toll_free_number || !country_code) {
    return res
      .status(422)
      .json({ error: 'name, toll_free_number and country_code are required' });
  }

  const monitor = {
    id:                store.nextMonitorId++,
    name:              String(name),
    toll_free_number:  String(toll_free_number),
    country_code:      String(country_code),
    carrier:           req.body.carrier ?? null,
    status:            'active',
    reachability_pct:  100,
    last_checked_at:   null,
  };
  store.connectMonitors.unshift(monitor);
  res.status(201).json({ data: monitor });
});

app.get('/api/connect/monitors/:id', async (req, res) => {
  const monitor = store.connectMonitors.find((m) => m.id === Number(req.params.id));
  if (!monitor) return res.status(404).json({ error: 'Not found' });

  const checks = store.connectChecks
    .filter((c) => c.connect_monitor_id === monitor.id)
    .slice(0, 10);

  res.json({ data: { ...monitor, check_results: checks } });
});

app.get('/api/connect/monitors/:id/checks', (req, res) => {
  const monitor = store.connectMonitors.find((m) => m.id === Number(req.params.id));
  if (!monitor) return res.status(404).json({ error: 'Not found' });

  const checks = store.connectChecks.filter((c) => c.connect_monitor_id === monitor.id);
  res.json({
    monitor: { id: monitor.id, name: monitor.name, toll_free_number: monitor.toll_free_number, country_code: monitor.country_code },
    data: checks,
  });
});

app.post('/api/connect/monitors/:id/run-check', async (req, res) => {
  const monitorId = Number(req.params.id);
  const monitor   = store.connectMonitors.find((m) => m.id === monitorId);
  if (!monitor) return res.status(404).json({ error: 'Not found' });

  const sessionId = createSession();
  runConnectTest(monitorId, sessionId).catch((err) => {
    console.error('runConnectTest error:', err);
  });

  res.json({ session_id: sessionId, message: 'Connect test started — connect to stream endpoint' });
});

app.get('/api/connect/monitors/:id/stream', (req, res) => {
  const sessionId = req.query.session_id;
  if (!sessionId) return res.status(400).json({ error: 'session_id query param required' });
  streamSession(res, req, sessionId);
});

// ── Helpers ─────────────────────────────────────────────────────────

function serializeDoc(doc) {
  if (!doc) return doc;
  const out = { ...doc };
  if (out._id) out._id = out._id.toString();
  if (out.created_at instanceof Date) out.created_at = out.created_at.toISOString();
  return out;
}

function setupSse(res) {
  res.setHeader('Content-Type', 'text/event-stream');
  res.setHeader('Cache-Control', 'no-cache');
  res.setHeader('Connection', 'keep-alive');
  res.flushHeaders();
}

function sendSse(res, data) {
  res.write(`data: ${JSON.stringify(data)}\n\n`);
}

async function streamSession(res, req, sessionId) {
  setupSse(res);
  let sent = 0;

  const poll = setInterval(async () => {
    try {
      const events = await getTestEvents(sessionId);
      for (const evt of events.slice(sent)) {
        sendSse(res, serializeDoc(evt));
        sent++;
        if (evt.event?.type === 'complete') {
          clearInterval(poll);
          setTimeout(() => res.end(), 400);
        }
      }
    } catch (err) {
      console.error('SSE poll error:', err);
      clearInterval(poll);
      res.end();
    }
  }, 500);

  req.on('close', () => clearInterval(poll));
}

// ── Boot ─────────────────────────────────────────────────────────────

await connectMongo();
await seedMongoData();

const dbInfo = getDbInfo();
app.listen(PORT, () => {
  console.log(`Klearcom dev API running on http://localhost:${PORT}`);
  console.log(`MongoDB: ${dbInfo.mode} → ${dbInfo.name}`);
  console.log(`Health: http://localhost:${PORT}/api/health`);
});
