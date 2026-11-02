<?php

namespace Tests\Unit;

use App\Models\DiscoveryNode;
use App\Services\DiscoveryTreeBuilder;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class DiscoveryTreeBuilderTest extends TestCase
{
    public function test_empty_collection_returns_empty_array(): void
    {
        $result = DiscoveryTreeBuilder::build(collect());
        $this->assertSame([], $result);
    }

    public function test_single_root_node_with_no_children(): void
    {
        $nodes = collect([$this->makeNode(1, null, 'Root', 0)]);
        $result = DiscoveryTreeBuilder::build($nodes);

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['id']);
        $this->assertSame([], $result[0]['children']);
    }

    public function test_flat_list_of_root_nodes(): void
    {
        $nodes = collect([
            $this->makeNode(1, null, 'Root 1', 0),
            $this->makeNode(2, null, 'Root 2', 0),
            $this->makeNode(3, null, 'Root 3', 0),
        ]);
        $result = DiscoveryTreeBuilder::build($nodes);

        $this->assertCount(3, $result);
        foreach ($result as $node) {
            $this->assertSame([], $node['children']);
        }
    }

    public function test_two_level_tree(): void
    {
        $nodes = collect([
            $this->makeNode(1, null, 'Root', 0),
            $this->makeNode(2, 1, 'Child 1', 1),
            $this->makeNode(3, 1, 'Child 2', 1),
            $this->makeNode(4, 1, 'Child 3', 1),
        ]);
        $result = DiscoveryTreeBuilder::build($nodes);

        $this->assertCount(1, $result);
        $this->assertCount(3, $result[0]['children']);
    }

    public function test_three_level_deep_tree(): void
    {
        $nodes = collect([
            $this->makeNode(1, null, 'Root', 0),
            $this->makeNode(2, 1, 'Level 2', 1),
            $this->makeNode(3, 2, 'Level 3', 2),
        ]);
        $result = DiscoveryTreeBuilder::build($nodes);

        $this->assertCount(1, $result);
        $this->assertCount(1, $result[0]['children']);
        $this->assertCount(1, $result[0]['children'][0]['children']);
        $this->assertSame(3, $result[0]['children'][0]['children'][0]['id']);
    }

    public function test_100_node_collection_has_correct_total(): void
    {
        $nodes = collect();
        // 1 root + 99 children
        $nodes->push($this->makeNode(1, null, 'Root', 0));
        for ($i = 2; $i <= 100; $i++) {
            $nodes->push($this->makeNode($i, 1, "Child {$i}", 1));
        }

        $result = DiscoveryTreeBuilder::build($nodes);

        $this->assertCount(1, $result);
        $this->assertCount(99, $result[0]['children']);
    }

    private function makeNode(int $id, ?int $parentId, string $promptText, int $depth): object
    {
        return (object) [
            'id' => $id,
            'parent_id' => $parentId,
            'prompt_text' => $promptText,
            'dtmf_option' => null,
            'node_type' => 'menu',
            'depth' => $depth,
        ];
    }
}
