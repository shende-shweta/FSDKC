<?php

namespace App\Services;

use Illuminate\Support\Collection;

class DiscoveryTreeBuilder
{
    public static function build(Collection $nodes, ?int $parentId = null): array
    {
        // Pre-group all nodes by parent_id into a PHP array (O(n)) to avoid repeated collection scans.
        // Null parent_id is stored under the empty-string key to keep array access consistent.
        $grouped = [];
        foreach ($nodes as $node) {
            $key = $node->parent_id === null ? '' : (string) $node->parent_id;
            $grouped[$key][] = $node;
        }

        return self::buildFromMap($grouped, $parentId);
    }

    private static function buildFromMap(array $grouped, ?int $parentId): array
    {
        $key = $parentId === null ? '' : (string) $parentId;
        $children = $grouped[$key] ?? [];

        return array_values(array_map(fn ($node) => [
            'id' => $node->id,
            'prompt_text' => $node->prompt_text,
            'dtmf_option' => $node->dtmf_option,
            'node_type' => $node->node_type,
            'depth' => $node->depth,
            'children' => self::buildFromMap($grouped, $node->id),
        ], $children));
    }
}
