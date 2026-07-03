/**
 * IVR Tree Builder — Constructs nested tree from flat node collection
 * AC-C03: TypeScript migration — replaces duplicate buildTree() in store.js
 * 
 * Mirrors PHP backend/app/Support/IvrTreeBuilder.php logic
 */

import type { NodeLike, DiscoveryNodeRecord } from './types.js';

/**
 * Recursively builds a nested tree structure from a flat array of nodes.
 * 
 * @param nodes - Flat array of node-like objects with id and parent_id
 * @param parentId - Parent ID to filter by (null for root nodes)
 * @returns Array of tree nodes with children property
 */
export function buildIvrTree(
  nodes: NodeLike[],
  parentId: number | null = null
): DiscoveryNodeRecord[] {
  return nodes
    .filter((n) => n.parent_id === parentId)
    .map((n) => ({
      id: n.id,
      discovery_job_id: 0, // Caller must populate if needed
      parent_id: n.parent_id,
      prompt_text: n.prompt_text,
      dtmf_option: n.dtmf_option,
      node_type: n.node_type,
      depth: n.depth,
      children: buildIvrTree(nodes, n.id),
    }));
}
