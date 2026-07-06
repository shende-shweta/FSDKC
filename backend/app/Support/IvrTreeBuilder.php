<?php

namespace App\Support;

use App\Models\DiscoveryNode;
use Illuminate\Support\Collection;

/**
 * Static utility for building a nested IVR tree from a flat Eloquent collection.
 *
 * AC-A02 — Extract Duplicate IVR Tree Builder
 * Replaces two identical private buildTree() methods in:
 *   - DiscoveryController
 *   - LegacyReportController
 */
class IvrTreeBuilder
{
    /**
     * Recursively build a nested tree array from a flat collection of DiscoveryNode records.
     *
     * @param  Collection<int, DiscoveryNode>  $nodes
     * @param  int|null  $parentId  Null for root-level nodes.
     * @return array<int, array<string, mixed>>
     */
    public static function build(Collection $nodes, ?int $parentId = null): array
    {
        return $nodes
            ->where('parent_id', $parentId)
            ->map(static fn (DiscoveryNode $node): array => [
                'id'          => $node->id,
                'prompt_text' => $node->prompt_text,
                'dtmf_option' => $node->dtmf_option,
                'node_type'   => $node->node_type,
                'depth'       => $node->depth,
                'children'    => self::build($nodes, $node->id),
            ])
            ->values()
            ->all();
    }
}
