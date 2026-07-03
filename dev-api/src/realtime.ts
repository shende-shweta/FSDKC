/**
 * RealTimeTestService — Async test orchestration for Discovery and Connect modules
 * AC-C03: TypeScript migration — uses ReachabilityService for rate calculation
 * 
 * Replaces dev-api/src/realtime.js with typed async implementation
 */

import { randomUUID } from 'crypto';
import type { Store, MongoFacade } from './types.js';
import { ReachabilityService } from './reachability.js';

const sleep = (ms: number): Promise<void> =>
  new Promise((resolve) => setTimeout(resolve, ms));

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
] as const;

const CONNECT_STEPS = [
  { event: 'check_initiated', message: 'Starting TFN reachability check…', progress: 10 },
  { event: 'dns_lookup', message: 'Resolving toll-free routing tables', progress: 25 },
  { event: 'carrier_selected', message: 'Carrier route selected', progress: 40 },
  { event: 'sip_invite', message: 'Sending SIP INVITE to carrier gateway', progress: 55 },
  { event: 'ringing', message: 'Remote endpoint ringing…', progress: 70 },
  { event: 'media_negotiation', message: 'RTP media stream established', progress: 85 },
  { event: 'quality_analysis', message: 'Running MOS & latency analysis', progress: 95 },
  { event: 'check_complete', message: 'Reachability check complete', progress: 100 },
] as const;

export class RealTimeTestService {
  private readonly reachabilityService: ReachabilityService;

  constructor(
    private readonly store: Store,
    private readonly mongo: MongoFacade
  ) {
    this.reachabilityService = new ReachabilityService();
  }

  createSession(): string {
    return randomUUID();
  }

  async runDiscoveryTest(jobId: number, sessionId: string): Promise<void> {
    const job = this.store.discoveryJobs.find((j) => j.id === jobId);
    if (!job) return;

    job.status = 'running';
    job.started_at = new Date().toISOString();

    await this.mongo.storeTestEvent(sessionId, 'discovery', jobId, {
      type: 'status',
      status: 'running',
      message: 'Discovery test started',
      progress: 0,
    });

    let parentNodeId: number | null = null;

    for (const step of DISCOVERY_STEPS) {
      await sleep(800 + Math.random() * 700);

      await this.mongo.storeTestEvent(sessionId, 'discovery', jobId, {
        type: 'step',
        ...step,
        timestamp: new Date().toISOString(),
      });

      if (step.transcript) {
        await this.mongo.storeTranscript('discovery', jobId, {
          event: step.event,
          transcript: step.transcript,
          session_id: sessionId,
        });
      }

      if (step.event === 'menu_discovered') {
        const node = {
          id: this.store.nextNodeId++,
          discovery_job_id: jobId,
          parent_id: parentNodeId,
          prompt_text: step.transcript ?? 'Accounts menu discovered',
          dtmf_option: step.dtmf ?? null,
          node_type: step.node_type ?? 'menu',
          depth: parentNodeId ? 1 : 0,
        };
        this.store.discoveryNodes.push(node);
        parentNodeId = node.id;
        job.nodes_discovered = this.store.discoveryNodes.filter(
          (n) => n.discovery_job_id === jobId
        ).length;
      }
    }

    await this.mongo.storeDiagnostic('discovery', jobId, {
      session_id: sessionId,
      mos_score: 4.0 + Math.random() * 0.5,
      latency_ms: Math.floor(100 + Math.random() * 80),
      packet_loss_pct: 0,
      jitter_ms: Math.floor(5 + Math.random() * 10),
    });

    const jobNodes = this.store.discoveryNodes.filter(
      (n) => n.discovery_job_id === jobId
    );
    job.status = 'completed';
    job.completed_at = new Date().toISOString();
    job.menu_depth =
      jobNodes.length > 0 ? Math.max(...jobNodes.map((n) => n.depth)) : 0;

    await this.mongo.storeTestEvent(sessionId, 'discovery', jobId, {
      type: 'complete',
      status: 'completed',
      message: `Discovery finished — ${job.nodes_discovered} nodes mapped`,
      progress: 100,
      nodes_discovered: job.nodes_discovered,
    });

    console.info('Discovery test completed', {
      job_id: jobId,
      session_id: sessionId,
      nodes: job.nodes_discovered,
    });
  }

  async runConnectTest(monitorId: number, sessionId: string): Promise<void> {
    const monitor = this.store.connectMonitors.find((m) => m.id === monitorId);
    if (!monitor) return;

    await this.mongo.storeTestEvent(sessionId, 'connect', monitorId, {
      type: 'status',
      status: 'running',
      message: 'Connect test started',
      progress: 0,
    });

    const reachable = Math.random() > 0.2;

    for (const step of CONNECT_STEPS) {
      await sleep(600 + Math.random() * 500);

      const stepPayload: Record<string, unknown> = {
        type: 'step',
        ...step,
        timestamp: new Date().toISOString(),
      };

      if (step.event === 'check_complete') {
        stepPayload.reachable = reachable;
        stepPayload.latency_ms = reachable
          ? Math.floor(180 + Math.random() * 300)
          : null;
      }

      await this.mongo.storeTestEvent(sessionId, 'connect', monitorId, stepPayload);
    }

    const latency = reachable ? Math.floor(180 + Math.random() * 300) : null;
    const check = {
      id: this.store.nextCheckId++,
      connect_monitor_id: monitorId,
      reachable,
      latency_ms: latency,
      carrier_route: monitor.carrier
        ? `${monitor.country_code} -> ${monitor.carrier} SIP`
        : null,
      failure_reason: reachable ? null : 'Carrier routing failure',
      checked_at: new Date().toISOString(),
    };
    this.store.connectChecks.unshift(check);

    // Use ReachabilityService for computation (AC-C03)
    const recent = this.store.connectChecks
      .filter((c) => c.connect_monitor_id === monitorId)
      .slice(0, ReachabilityService.WINDOW);

    const rate = this.reachabilityService.computeRate(recent);
    const status = this.reachabilityService.statusFromRate(rate);

    monitor.reachability_pct = rate;
    monitor.status = status;
    monitor.last_checked_at = check.checked_at;

    await this.mongo.storeTranscript('connect', monitorId, {
      event: reachable
        ? 'reachability_check_passed'
        : 'reachability_check_failed',
      session_id: sessionId,
      toll_free_number: monitor.toll_free_number,
      latency_ms: latency,
      failure_reason: check.failure_reason,
    });

    await this.mongo.storeDiagnostic('connect', monitorId, {
      session_id: sessionId,
      mos_score: reachable
        ? 3.8 + Math.random() * 0.8
        : 2.5 + Math.random() * 0.5,
      latency_ms: latency ?? 0,
      packet_loss_pct: reachable ? 0 : 2.5 + Math.random() * 2,
    });

    await this.mongo.storeTestEvent(sessionId, 'connect', monitorId, {
      type: 'complete',
      status: reachable ? 'reachable' : 'failed',
      message: reachable
        ? 'TFN is reachable'
        : 'TFN reachability check failed',
      progress: 100,
      reachable,
      latency_ms: latency,
      check_id: check.id,
    });

    console.info('Connect test completed', {
      monitor_id: monitorId,
      session_id: sessionId,
      reachable,
      latency_ms: latency,
    });
  }
}

export function createSession(): string {
  return randomUUID();
}
