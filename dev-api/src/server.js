import cors from 'cors';
import express from 'express';
import rateLimit from 'express-rate-limit';
import Joi from 'joi';
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

const allowedOrigins = process.env.ALLOWED_ORIGINS?.split(',') ?? ['http://localhost:5173'];
app.use(cors({ origin: allowedOrigins }));
app.use(express.json());

// Global rate limiter: 100 requests per minute per IP
const globalLimiter = rateLimit({ windowMs: 60_000, max: 100 });
app.use(globalLimiter);

// Stricter limiter for test-trigger endpoints
const testTriggerLimiter = rateLimit({ windowMs: 60_000, max: 10 });

// Bulk-import item schema
const bulkImportItemSchema = Joi.object({
  name: Joi.string().required(),
  toll_free_number: Joi.string().required(),
  country_code: Joi.string().max(5).optional(),
  carrier: Joi.string().optional().allow(null, ''),
  reachability_pct: Joi.number().min(0).max(100).default(100),
});

// ── Health & MongoDB ──────────────────────────────────────────────

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
  const allowedModules = ['discovery', 'connect'];
  if (!allowedModules.includes(req.params.module)) {
    return res.status(400).json({ error: 'module must be discovery or connect' });
  }
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
  const discoveryTotal = store.discoveryJobs.length;
  const discoveryCompleted = store.discoveryJobs.filter((j) => j.status === 'completed').length;
  const avgReach = store.connectMonitors.length > 0
    ? store.connectMonitors.reduce((s, m) => s + m.reachability_pct, 0) / store.connectMonitors.length
    : 0;
  const mongo = await healthCheck();

  const last100Checks = store.connectChecks.slice(-100);
  const callSuccessRate = last100Checks.length > 0
    ? Math.round((last100Checks.filter((c) => c.reachable).length / last100Checks.length) * 1000) / 10
    : null;

  const withCarrier = last100Checks.filter((c) => c.carrier_route);
  const transferSuccessRate = withCarrier.length > 0
    ? Math.round((withCarrier.filter((c) => c.reachable).length / withCarrier.length) * 1000) / 10
    : null;

  res.json({
    availability: {
      ivr_availability_pct: discoveryTotal > 0 ? Math.round((discoveryCompleted / discoveryTotal) * 1000) / 10 : 0,
      number_reachability_pct: Math.round(avgReach * 10) / 10,
      call_success_rate_pct: callSuccessRate,
      transfer_success_rate_pct: transferSuccessRate,
    },
    operational: {
      active_discovery_jobs: store.discoveryJobs.filter((j) => j.status === 'running').length,
      active_connect_monitors: store.connectMonitors.filter((m) => m.status === 'active').length,
      open_alerts: store.connectMonitors.filter((m) => m.status === 'alert').length,
      countries_monitored: new Set(store.connectMonitors.map((m) => m.country_code)).size,
    },
    modules: ['discovery', 'connect'],
    mongodb: mongo,
  });
});

// ── Discovery ─────────────────────────────────────────────────────

app.get('/api/discovery/jobs', (_req, res) => {
  res.json({ data: store.discoveryJobs });
});

app.post('/api/discovery/jobs', (req, res) => {
  const job = {
    id: store.nextJobId++,
    name: req.body.name,
    phone_number: req.body.phone_number,
    country_code: req.body.country_code,
    status: 'pending',
    menu_depth: 0,
    nodes_discovered: 0,
    languages: req.body.languages ?? ['en'],
    started_at: null,
    completed_at: null,
  };
  store.discoveryJobs.unshift(job);
  res.status(201).json({ data: job });
});

app.get('/api/discovery/jobs/:id', async (req, res) => {
  const job = store.discoveryJobs.find((j) => j.id === Number(req.params.id));
  if (!job) return res.status(404).json({ error: 'Not found' });

  const nodes = store.discoveryNodes.filter((n) => n.discovery_job_id === job.id);
  const transcripts = await getTranscripts('discovery', job.id);

  res.json({ data: { ...job, nodes }, transcripts: transcripts.map(serializeDoc) });
});

app.get('/api/discovery/jobs/:id/tree', (req, res) => {
  const job = store.discoveryJobs.find((j) => j.id === Number(req.params.id));
  if (!job) return res.status(404).json({ error: 'Not found' });

  const nodes = store.discoveryNodes.filter((n) => n.discovery_job_id === job.id);
  res.json({ job_id: job.id, job_name: job.name, tree: buildTree(nodes) });
});

app.post('/api/discovery/jobs/:id/start', testTriggerLimiter, async (req, res) => {
  const jobId = Number(req.params.id);
  const job = store.discoveryJobs.find((j) => j.id === jobId);
  if (!job) return res.status(404).json({ error: 'Not found' });
  if (job.status === 'running') return res.status(409).json({ message: 'Job already running' });

  const sessionId = createSession();
  runDiscoveryTest(jobId, sessionId).catch(console.error);

  res.json({ session_id: sessionId, message: 'Discovery test started — connect to stream endpoint' });
});

app.get('/api/discovery/jobs/:id/stream', (req, res) => {
  const sessionId = req.query.session_id;
  if (!sessionId) return res.status(400).json({ error: 'session_id query param required' });
  streamSession(res, req, sessionId);
});

// ── Connect ───────────────────────────────────────────────────────

app.post('/api/connect/monitors/bulk-import', (req, res) => {
  const items = Array.isArray(req.body) ? req.body : req.body?.monitors ?? [];

  if (items.length > 100) {
    return res.status(400).json({ error: 'Bulk import limit is 100 items per request' });
  }

  const errors = [];
  const validated = [];

  for (let i = 0; i < items.length; i++) {
    const { error, value } = bulkImportItemSchema.validate(items[i]);
    if (error) {
      errors.push({ index: i, message: error.message });
    } else {
      validated.push(value);
    }
  }

  if (errors.length > 0) {
    return res.status(400).json({ error: 'Validation failed', details: errors });
  }

  const created = validated.map((item) => {
    const monitor = {
      id: store.nextMonitorId++,
      name: item.name,
      toll_free_number: item.toll_free_number,
      country_code: item.country_code ?? 'US',
      carrier: item.carrier ?? null,
      status: 'active',
      reachability_pct: item.reachability_pct,
      last_checked_at: null,
    };
    store.connectMonitors.unshift(monitor);
    return monitor;
  });

  res.status(201).json({ data: created, imported: created.length });
});

app.get('/api/connect/monitors', (_req, res) => {
  res.json({ data: store.connectMonitors });
});

app.post('/api/connect/monitors', (req, res) => {
  const monitor = {
    id: store.nextMonitorId++,
    name: req.body.name,
    toll_free_number: req.body.toll_free_number,
    country_code: req.body.country_code,
    carrier: req.body.carrier ?? null,
    status: 'active',
    reachability_pct: 100,
    last_checked_at: null,
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

app.post('/api/connect/monitors/:id/run-check', testTriggerLimiter, async (req, res) => {
  const monitorId = Number(req.params.id);
  const monitor = store.connectMonitors.find((m) => m.id === monitorId);
  if (!monitor) return res.status(404).json({ error: 'Not found' });

  const sessionId = createSession();
  runConnectTest(monitorId, sessionId).catch(console.error);

  res.json({ session_id: sessionId, message: 'Connect test started — connect to stream endpoint' });
});

app.get('/api/connect/monitors/:id/stream', (req, res) => {
  const sessionId = req.query.session_id;
  if (!sessionId) return res.status(400).json({ error: 'session_id query param required' });
  streamSession(res, req, sessionId);
});

// ── Helpers ───────────────────────────────────────────────────────

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
    } catch {
      clearInterval(poll);
      res.end();
    }
  }, 500);

  req.on('close', () => clearInterval(poll));
}

// ── Boot ──────────────────────────────────────────────────────────

await connectMongo();
await seedMongoData();

const dbInfo = getDbInfo();
app.listen(PORT, () => {
  console.log(`Klearcom dev API running on http://localhost:${PORT}`);
  console.log(`MongoDB: ${dbInfo.mode} → ${dbInfo.name}`);
  console.log(`Health: http://localhost:${PORT}/api/health`);
});
