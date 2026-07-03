/**
 * In-memory relational store (MariaDB equivalent for local dev)
 * AC-C03: TypeScript migration — typed store with buildIvrTree integration
 * 
 * Replaces dev-api/src/store.js with typed implementation
 */

import type { Store, DiscoveryNodeRecord } from './types.js';
import { buildIvrTree } from './ivrTree.js';

export const store: Store = {
  discoveryJobs: [
    {
      id: 1,
      name: 'Bank IVR Discovery - US',
      phone_number: '+18005551234',
      country_code: 'US',
      status: 'completed',
      menu_depth: 4,
      nodes_discovered: 12,
      languages: ['en'],
      started_at: new Date(Date.now() - 7200000).toISOString(),
      completed_at: new Date(Date.now() - 3600000).toISOString(),
    },
    {
      id: 2,
      name: 'Healthcare IVR - UK',
      phone_number: '+448001234567',
      country_code: 'GB',
      status: 'pending',
      menu_depth: 0,
      nodes_discovered: 0,
      languages: ['en'],
      started_at: null,
      completed_at: null,
    },
    {
      id: 3,
      name: 'Retail Multi-lang IVR',
      phone_number: '+33123456789',
      country_code: 'FR',
      status: 'pending',
      menu_depth: 0,
      nodes_discovered: 0,
      languages: ['fr', 'en'],
      started_at: null,
      completed_at: null,
    },
  ],
  discoveryNodes: [
    {
      id: 1,
      discovery_job_id: 1,
      parent_id: null,
      prompt_text:
        'Welcome to Acme Bank. Press 1 for accounts, 2 for loans, 3 for support.',
      dtmf_option: null,
      node_type: 'menu',
      depth: 0,
    },
    {
      id: 2,
      discovery_job_id: 1,
      parent_id: 1,
      prompt_text: 'Accounts menu. Press 1 for balance, 2 for transactions.',
      dtmf_option: '1',
      node_type: 'menu',
      depth: 1,
    },
    {
      id: 3,
      discovery_job_id: 1,
      parent_id: 2,
      prompt_text: 'Your balance is being retrieved. Please hold.',
      dtmf_option: '1',
      node_type: 'prompt',
      depth: 2,
    },
    {
      id: 4,
      discovery_job_id: 1,
      parent_id: 1,
      prompt_text: 'Transferring to loans department.',
      dtmf_option: '2',
      node_type: 'transfer',
      depth: 1,
    },
  ],
  connectMonitors: [
    {
      id: 1,
      name: 'US Sales TFN',
      toll_free_number: '18005559999',
      country_code: 'US',
      carrier: 'Verizon',
      status: 'active',
      reachability_pct: 99.8,
      last_checked_at: new Date(Date.now() - 300000).toISOString(),
    },
    {
      id: 2,
      name: 'India Support Line',
      toll_free_number: '180018001800',
      country_code: 'IN',
      carrier: 'Airtel',
      status: 'alert',
      reachability_pct: 72.5,
      last_checked_at: new Date(Date.now() - 600000).toISOString(),
    },
    {
      id: 3,
      name: 'Brazil Customer Care',
      toll_free_number: '08001234567',
      country_code: 'BR',
      carrier: 'Vivo',
      status: 'active',
      reachability_pct: 98.2,
      last_checked_at: new Date(Date.now() - 900000).toISOString(),
    },
  ],
  connectChecks: [
    {
      id: 1,
      connect_monitor_id: 1,
      reachable: true,
      latency_ms: 245,
      carrier_route: 'US-East -> Verizon SIP',
      failure_reason: null,
      checked_at: new Date(Date.now() - 300000).toISOString(),
    },
    {
      id: 2,
      connect_monitor_id: 2,
      reachable: false,
      latency_ms: null,
      carrier_route: 'Mumbai -> Airtel',
      failure_reason: 'Carrier routing failure',
      checked_at: new Date(Date.now() - 600000).toISOString(),
    },
    {
      id: 3,
      connect_monitor_id: 3,
      reachable: true,
      latency_ms: 380,
      carrier_route: 'Sao Paulo -> Vivo',
      failure_reason: null,
      checked_at: new Date(Date.now() - 900000).toISOString(),
    },
  ],
  nextJobId: 4,
  nextNodeId: 5,
  nextMonitorId: 4,
  nextCheckId: 4,
};

/**
 * Builds a nested tree structure from flat nodes collection.
 * Uses buildIvrTree utility (AC-C03).
 * 
 * @param nodes - Array of discovery nodes
 * @param parentId - Parent ID filter (null for roots)
 * @returns Nested tree structure
 */
export function buildTree(
  nodes: DiscoveryNodeRecord[],
  parentId: number | null = null
): DiscoveryNodeRecord[] {
  return buildIvrTree(nodes, parentId);
}
