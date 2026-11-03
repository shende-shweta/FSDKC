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
import { calculatePct, statusFromPct } from './utils/reachability.js';

const PORT = process.env.PORT || 8080;
const app = express();

const ALLOWED_ORIGINS = (process.env.APP_ALLOWED_ORIGINS ?? 'http://localhost:5173')
  .split(',')
  .map((o) => o.trim())
  .filter(Boolean);

app.use(cors({ origin: ALLOWED_ORIGINS }));
app.use(express.json());

// Simple in-memory rate limiter for trigger endpoints (5 requests / 60 s per IP).
const rateLimitStore = new Map();
function rateLimit(maxRequests = 5, windowMs = 60_000) {
  return (req, res, next) => {
    const key = req.ip ?? 'unknown';
    const now = Date.now();
    const entry = rateLimitStore.get(key) ?? { count: 0, resetAt: now + windowMs };

    if (now > entry.resetAt) {
      entry.count = 0;
      entry.resetAt = now + windowMs;
    }

    entry.count += 1;
    rateLimitStore.set(key, entry);

    if (entry.count > maxRequests) {
      res.setHeader('Retry-After', Math.ceil((entry.resetAt - now) / 1000));
      return res.status(429).json({ error: 'Too many requests — try again shortly.' });
    }

    next();
  };
}

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
  const avgReach = store.connectMonitors.reduce((s, m) => s + m.reachability_pct, 0) / store.connectMonitors.length;
  const mongo = await healthCheck();

  res.json({
    availability: {
      ivr_availability_pct: discoveryTotal > 0 ? Math.round((discoveryCompleted / discoveryTotal) * 1000) / 10 : 0,
      number_reachability_pct: Math.round(avgReach * 10) / 10,
      call_success_rate_pct: 94.2,
      transfer_success_rate_pct: 97.8,
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
  const { name, phone_number, country_code, languages } = req.body ?? {};

  const errors = {};
  if (!name || typeof name !== 'string' || name.trim() === '') {
    errors.name = 'The name field is required.';
  }
  if (!phone_number || !/^\+[1-9]\d{6,14}$/.test(phone_number)) {
    errors.phone_number = 'The phone number must be in E.164 format (e.g. +18005551234).';
  }
  if (!country_code || !/^[A-Z]{2}$/.test(country_code)) {
    errors.country_code = 'The country code must be a 2-letter ISO 3166-1 alpha-2 code (e.g. US, GB).';
  }
  if (languages !== undefined && (!Array.isArray(languages) || languages.length > 10)) {
    errors.languages = 'languages must be an array with at most 10 items.';
  }

  if (Object.keys(errors).length > 0) {
    return res.status(422).json({ errors });
  }

  const job = {
    id: store.nextJobId++,
    name: name.trim(),
    phone_number,
    country_code,
    status: 'pending',
    menu_depth: 0,
    nodes_discovered: 0,
    languages: languages ?? ['en'],
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

app.post('/api/discovery/jobs/:id/start', rateLimit(5, 60_000), async (req, res) => {
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

app.post('/api/connect/monitors/bulk-import', rateLimit(5, 60_000), (req, res) => {
  const items = Array.isArray(req.body) ? req.body : req.body?.monitors ?? [];

  if (items.length === 0) {
    return res.status(422).json({ error: 'monitors array must not be empty.' });
  }
  if (items.length > 100) {
    return res.status(422).json({ error: 'A maximum of 100 monitors may be imported at once.' });
  }

  const errors = [];
  for (let i = 0; i < items.length; i++) {
    const item = items[i];
    const itemErrors = {};
    if (!item.name || typeof item.name !== 'string' || item.name.trim() === '') {
      itemErrors.name = 'required';
    }
    if (!item.toll_free_number || typeof item.toll_free_number !== 'string') {
      itemErrors.toll_free_number = 'required';
    }
    if (!item.country_code || !/^[A-Z]{2}$/.test(item.country_code)) {
      itemErrors.country_code = 'must be a 2-letter ISO code (e.g. US)';
    }
    if (Object.keys(itemErrors).length > 0) {
      errors.push({ index: i, errors: itemErrors });
    }
  }

  if (errors.length > 0) {
    return res.status(422).json({ message: 'Validation failed for one or more items.', errors });
  }

  const created = items.map((item) => {
    const monitor = {
      id: store.nextMonitorId++,
      name: item.name.trim(),
      toll_free_number: item.toll_free_number,
      country_code: item.country_code,
      carrier: item.carrier ?? null,
      status: 'active',
      reachability_pct: typeof item.reachability_pct === 'number' ? item.reachability_pct : 100,
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
  const { name, toll_free_number, country_code, carrier } = req.body ?? {};

  const errors = {};
  if (!name || typeof name !== 'string' || name.trim() === '') {
    errors.name = 'The name field is required.';
  }
  if (!toll_free_number || !/^\+?[0-9\s\-()]{7,20}$/.test(toll_free_number)) {
    errors.toll_free_number = 'The toll free number must be 7–20 digits.';
  }
  if (!country_code || !/^[A-Z]{2}$/.test(country_code)) {
    errors.country_code = 'The country code must be a 2-letter ISO code (e.g. US).';
  }

  if (Object.keys(errors).length > 0) {
    return res.status(422).json({ errors });
  }

  const monitor = {
    id: store.nextMonitorId++,
    name: name.trim(),
    toll_free_number,
    country_code,
    carrier: carrier ?? null,
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
  const recent = checks.slice(0, 20);
  const pct = calculatePct(recent);

  res.json({
    monitor: { id: monitor.id, name: monitor.name, toll_free_number: monitor.toll_free_number, country_code: monitor.country_code },
    data: checks,
    computed: {
      reachability_pct: pct,
      status: statusFromPct(pct),
    },
  });
});

app.post('/api/connect/monitors/:id/run-check', rateLimit(5, 60_000), async (req, res) => {
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
