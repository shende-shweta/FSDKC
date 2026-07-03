/**
 * Shared type definitions for Klearcom dev-API TypeScript migration
 * AC-C03: Establish TypeScript migration infrastructure
 */

// ── Core Database Records ──────────────────────────────────────────

export type JobStatus = 'pending' | 'running' | 'completed' | 'failed';
export type MonitorStatus = 'active' | 'paused' | 'alert';

export interface DiscoveryJobRecord {
  id: number;
  name: string;
  phone_number: string;
  country_code: string;
  status: JobStatus;
  menu_depth: number;
  nodes_discovered: number;
  languages: string[];
  started_at: string | null;
  completed_at: string | null;
}

export interface DiscoveryNodeRecord {
  id: number;
  discovery_job_id: number;
  parent_id: number | null;
  prompt_text: string;
  dtmf_option: string | null;
  node_type: string;
  depth: number;
  children?: DiscoveryNodeRecord[];
}

export interface ConnectMonitorRecord {
  id: number;
  name: string;
  toll_free_number: string;
  country_code: string;
  carrier: string | null;
  status: MonitorStatus;
  reachability_pct: number;
  last_checked_at: string | null;
}

export interface ConnectCheckRecord {
  id: number;
  connect_monitor_id: number;
  reachable: boolean;
  latency_ms: number | null;
  carrier_route: string | null;
  failure_reason: string | null;
  checked_at: string;
}

// ── Legacy Mapper Types ────────────────────────────────────────────

export interface ReportRow {
  label: string;
  metric: number;
  region: string;
  source: string;
}

export interface JobContext {
  job_name: string | null;
  phone: string | null;
  depth: number;
}

// ── Reachability Service Types ─────────────────────────────────────

export interface CheckLike {
  reachable: boolean;
  [key: string]: unknown;
}

// ── IVR Tree Builder Types ─────────────────────────────────────────

export interface NodeLike {
  id: number;
  parent_id: number | null;
  prompt_text: string;
  dtmf_option: string | null;
  node_type: string;
  depth: number;
}

// ── Store Types ────────────────────────────────────────────────────

export interface Store {
  discoveryJobs: DiscoveryJobRecord[];
  discoveryNodes: DiscoveryNodeRecord[];
  connectMonitors: ConnectMonitorRecord[];
  connectChecks: ConnectCheckRecord[];
  nextJobId: number;
  nextNodeId: number;
  nextMonitorId: number;
  nextCheckId: number;
}

// ── MongoDB Types (for realtime.ts integration) ────────────────────

export interface MongoFacade {
  storeTestEvent(sessionId: string, module: string, referenceId: number, payload: Record<string, unknown>): Promise<void>;
  storeTranscript(module: string, referenceId: number, payload: Record<string, unknown>): Promise<void>;
  storeDiagnostic(module: string, referenceId: number, payload: Record<string, unknown>): Promise<void>;
}
