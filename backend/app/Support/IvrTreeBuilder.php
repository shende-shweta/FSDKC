<?php

namespace App\Support;

use App\Models\DiscoveryNode;
use Illuminate\Support\Collection;

/**
 * Centralised IVR tree builder.
 *
 * Previously the buildTree() method was copy-pasted identically into both
 * DiscoveryController and LegacyReportController. Extracted here so both
 * callers use a single, tested implementation.
 */
class IvrTreeBuilder
{
    /**
     * Build a nested tree array from a flat collection of DiscoveryNode models.
     *
     * @param  Collection<int, DiscoveryNode>  $nodes
     * @param  int|null  $parentId
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
