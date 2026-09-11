import { randomUUID } from 'crypto';
import { store } from './store.js';
import {
  storeDiagnostic,
  storeTestEvent,
  storeTranscript,
} from './mongo.js';

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const ALERT_THRESHOLD = 90;

const DISCOVERY_STEPS = [
  { event: 'call_initiated', message: 'Placing test call to IVR endpoint…', progress: 10 },
  { event: 'call_connected', message: 'Call connected — analyzing audio stream', progress: 20 },
  { event: 'prompt_detected', message: 'Welcome prompt detected', transcript: 'Welcome. Press 1 for accounts, 2 for support.', progress: 35 },
  { event: 'dtmf_sent', message: 'Sending DTMF: 1', dtmf: '1', progress: 45 },
  { event: 'dtmf_recognized', message: 'DTMF input recognized', dtmf: '1', progress: 55 },
  { event: 'menu_discovered', message: 'Sub-menu mapped: Accounts', node_type: 'menu', progress: 70 },
  { event: 'speech_transcribed', message: 'Speech-to-text complete', transcript: 'Press 1 for balance inquiry.', progress: 82 },
  { event: 'transfer_validated', message: 'Agent transfer path validated', progress: 92 },
  { event: 'traversal_complete', message: 'IVR discovery complete', progress: 100 },
];

const CONNECT_STEPS = [
  { event: 'check_initiated', message: 'Starting TFN reachability check…', progress: 10 },
  { event: 'dns_lookup', message: 'Resolving toll-free routing tables', progress: 25 },
  { event: 'carrier_selected', message: 'Carrier route selected', progress: 40 },
  { event: 'sip_invite', message: 'Sending SIP INVITE to carrier gateway', progress: 55 },
  { event: 'ringing', message: 'Remote endpoint ringing…', progress: 70 },
  { event: 'media_negotiation', message: 'RTP media stream established', progress: 85 },
  { event: 'quality_analysis', message: 'Running MOS & latency analysis', progress: 95 },
  { event: 'check_complete', message: 'Reachability check complete', progress: 100 },
];

export async function runDiscoveryTest(jobId, sessionId) {
  const job = store.discoveryJobs.find((j) => j.id === jobId);
  if (!job) return;

  job.status = 'running';
  job.started_at = new Date().toISOString();

  await storeTestEvent(sessionId, 'discovery', jobId, {
    type: 'status',
    status: 'running',
    message: 'Discovery test started',
    progress: 0,
  });

  let parentNodeId = null;

  for (const step of DISCOVERY_STEPS) {
    await sleep(800 + Math.random() * 700);

    await storeTestEvent(sessionId, 'discovery', jobId, {
      type: 'step',
      ...step,
      timestamp: new Date().toISOString(),
    });

    if (step.transcript) {
      await storeTranscript('discovery', jobId, {
        event: step.event,
        transcript: step.transcript,
        session_id: sessionId,
      });
    }

    if (step.event === 'menu_discovered') {
      const node = {
        id: store.nextNodeId++,
        discovery_job_id: jobId,
        parent_id: parentNodeId,
        prompt_text: step.transcript ?? 'Accounts menu discovered',
        dtmf_option: step.dtmf ?? null,
        node_type: step.node_type ?? 'menu',
        depth: parentNodeId ? 1 : 0,
      };
      store.discoveryNodes.push(node);
      parentNodeId = node.id;
      job.nodes_discovered = store.discoveryNodes.filter((n) => n.discovery_job_id === jobId).length;
    }
  }

  await storeDiagnostic('discovery', jobId, {
    session_id: sessionId,
    mos_score: 4.0 + Math.random() * 0.5,
    latency_ms: Math.floor(100 + Math.random() * 80),
    packet_loss_pct: 0,
    jitter_ms: Math.floor(5 + Math.random() * 10),
  });

  job.status = 'completed';
  job.completed_at = new Date().toISOString();
  job.menu_depth = Math.max(...store.discoveryNodes.filter((n) => n.discovery_job_id === jobId).map((n) => n.depth), 0);

  await storeTestEvent(sessionId, 'discovery', jobId, {
    type: 'complete',
    status: 'completed',
    message: `Discovery finished — ${job.nodes_discovered} nodes mapped`,
    progress: 100,
    nodes_discovered: job.nodes_discovered,
  });
}

export async function runConnectTest(monitorId, sessionId) {
  const monitor = store.connectMonitors.find((m) => m.id === monitorId);
  if (!monitor) return;

  await storeTestEvent(sessionId, 'connect', monitorId, {
    type: 'status',
    status: 'running',
    message: 'Connect test started',
    progress: 0,
  });

  const reachable = Math.random() > 0.2;

  for (const step of CONNECT_STEPS) {
    await sleep(600 + Math.random() * 500);

    const stepPayload = {
      type: 'step',
      ...step,
      timestamp: new Date().toISOString(),
    };

    if (step.event === 'check_complete') {
      stepPayload.reachable = reachable;
      stepPayload.latency_ms = reachable ? Math.floor(180 + Math.random() * 300) : null;
    }

    await storeTestEvent(sessionId, 'connect', monitorId, stepPayload);
  }

  const latency = reachable ? Math.floor(180 + Math.random() * 300) : null;
  const check = {
    id: store.nextCheckId++,
    connect_monitor_id: monitorId,
    reachable,
    latency_ms: latency,
    carrier_route: monitor.carrier ? `${monitor.country_code} -> ${monitor.carrier} SIP` : null,
    failure_reason: reachable ? null : 'Carrier routing failure',
    checked_at: new Date().toISOString(),
  };
  store.connectChecks.unshift(check);

  const recent = store.connectChecks.filter((c) => c.connect_monitor_id === monitorId).slice(0, 20);
  const successRate = recent.length > 0
    ? (recent.filter((c) => c.reachable).length / recent.length) * 100
    : 100;

  monitor.reachability_pct = Math.round(successRate * 100) / 100;
  monitor.status = successRate < ALERT_THRESHOLD ? 'alert' : 'active';
  monitor.last_checked_at = check.checked_at;

  await storeTranscript('connect', monitorId, {
    event: reachable ? 'reachability_check_passed' : 'reachability_check_failed',
    session_id: sessionId,
    toll_free_number: monitor.toll_free_number,
    latency_ms: latency,
    failure_reason: check.failure_reason,
  });

  await storeDiagnostic('connect', monitorId, {
    session_id: sessionId,
    mos_score: reachable ? 3.8 + Math.random() * 0.8 : 2.5 + Math.random() * 0.5,
    latency_ms: latency ?? 0,
    packet_loss_pct: reachable ? 0 : 2.5 + Math.random() * 2,
  });

  await storeTestEvent(sessionId, 'connect', monitorId, {
    type: 'complete',
    status: reachable ? 'reachable' : 'failed',
    message: reachable ? 'TFN is reachable' : 'TFN reachability check failed',
    progress: 100,
    reachable,
    latency_ms: latency,
    check_id: check.id,
  });
}

export function createSession() {
  return randomUUID();
}
