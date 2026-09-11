export interface DiscoveryJob {
  id: number;
  name: string;
  phone_number: string;
  country_code: string;
  status: 'pending' | 'running' | 'completed' | 'failed';
  menu_depth: number;
  nodes_discovered: number;
  languages: string[];
  started_at: string | null;
  completed_at: string | null;
}

export interface DiscoveryNode {
  id: number;
  prompt_text: string;
  dtmf_option: string | null;
  node_type: string;
  depth: number;
  children?: DiscoveryNode[];
}

export interface ConnectMonitor {
  id: number;
  name: string;
  toll_free_number: string;
  country_code: string;
  carrier: string | null;
  status: 'active' | 'paused' | 'alert' | 'unknown';
  reachability_pct: number | null;
  last_checked_at: string | null;
}

export interface ConnectCheckResult {
  id: number;
  reachable: boolean;
  latency_ms: number | null;
  carrier_route: string | null;
  failure_reason: string | null;
  checked_at: string;
}

export interface MongoHealth {
  connected: boolean;
  mode?: string;
  database?: string;
  collections?: {
    transcripts: number;
    test_events: number;
    diagnostics: number;
  };
  error?: string;
}

export interface TestEventPayload {
  type?: string;
  status?: string;
  message?: string;
  event?: string;
  progress?: number;
  transcript?: string;
  reachable?: boolean;
  latency_ms?: number;
}

export interface TestEvent {
  _id?: string;
  session_id?: string;
  module?: string;
  reference_id?: number;
  event?: TestEventPayload;
  created_at?: string;
}

export interface Transcript {
  _id?: string;
  module: string;
  reference_id: number;
  payload: Record<string, unknown>;
  created_at?: string;
}

export interface DashboardKpis {
  availability: {
    ivr_availability_pct: number;
    number_reachability_pct: number;
    call_success_rate_pct: number | null;
    transfer_success_rate_pct: number | null;
  };
  operational: {
    active_discovery_jobs: number;
    active_connect_monitors: number;
    open_alerts: number;
    countries_monitored: number;
  };
  modules: string[];
  mongodb?: MongoHealth;
}
