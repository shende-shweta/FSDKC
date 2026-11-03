<?php

namespace App\Services;

use App\Models\DiscoveryNode;
use Illuminate\Support\Collection;

class DiscoveryService
{
    public function buildTree(Collection $nodes, ?int $parentId = null): array
    {
        return $nodes
            ->where('parent_id', $parentId)
            ->map(fn (DiscoveryNode $node) => [
                'id' => $node->id,
                'prompt_text' => $node->prompt_text,
                'dtmf_option' => $node->dtmf_option,
                'node_type' => $node->node_type,
                'depth' => $node->depth,
                'children' => $this->buildTree($nodes, $node->id),
            ])
            ->values()
            ->all();
    }
}
