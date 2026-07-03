<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * IvrTreeBuilder — Static utility for building a nested IVR tree from a flat Eloquent collection.
 * AC-A02: Extract Duplicate IVR Tree Builder
 *
 * Replaces private buildTree() methods in:
 *   - DiscoveryController::tree()
 *   - LegacyReportController::ivrDepthReport()
 */
class IvrTreeBuilder
{
    /**
     * Recursively constructs a nested tree from a flat node collection.
     *
     * @param Collection $nodes    Flat collection of DiscoveryNode models
     * @param int|null   $parentId Parent ID to filter by (null = root level)
     * @return array               Nested array of nodes with 'children' key
     */
    public static function build(Collection $nodes, ?int $parentId = null): array
    {
        return $nodes
            ->where('parent_id', $parentId)
            ->map(static function ($node) use ($nodes): array {
                return [
                    'id'          => $node->id,
                    'prompt_text' => $node->prompt_text,
                    'dtmf_option' => $node->dtmf_option,
                    'node_type'   => $node->node_type,
                    'depth'       => $node->depth,
                    'children'    => self::build($nodes, $node->id),
                ];
            })
            ->values()
            ->all();
    }
}
