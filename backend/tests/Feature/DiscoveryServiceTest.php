<?php

// AC-06: DiscoveryService::buildTree() — extracted service replacing 3× duplicated algorithm.

namespace Tests\Feature;

use App\Models\DiscoveryJob;
use App\Models\DiscoveryNode;
use App\Services\DiscoveryService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class DiscoveryServiceTest extends TestCase
{
    private DiscoveryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DiscoveryService();
    }

    public function test_empty_collection_returns_empty_array(): void
    {
        // AC-06: empty input → empty tree
        $result = $this->service->buildTree(new Collection([]));
        $this->assertSame([], $result);
    }

    public function test_single_root_node_has_no_children(): void
    {
        // AC-06: root-only tree — children key must be an empty array
        $job = DiscoveryJob::factory()->create();
        $root = DiscoveryNode::factory()->create([
            'discovery_job_id' => $job->id,
            'parent_id' => null,
            'prompt_text' => 'Welcome',
            'dtmf_option' => null,
            'node_type' => 'menu',
            'depth' => 0,
        ]);

        $nodes = DiscoveryNode::where('discovery_job_id', $job->id)->get();
        $result = $this->service->buildTree($nodes);

        $this->assertCount(1, $result);
        $this->assertSame($root->id, $result[0]['id']);
        $this->assertSame('Welcome', $result[0]['prompt_text']);
        $this->assertSame([], $result[0]['children']);
    }

    public function test_child_node_is_nested_under_parent(): void
    {
        // AC-06: two-level nesting
        $job = DiscoveryJob::factory()->create();
        $root = DiscoveryNode::factory()->create([
            'discovery_job_id' => $job->id, 'parent_id' => null, 'depth' => 0,
        ]);
        $child = DiscoveryNode::factory()->create([
            'discovery_job_id' => $job->id, 'parent_id' => $root->id, 'depth' => 1,
        ]);

        $nodes = DiscoveryNode::where('discovery_job_id', $job->id)->get();
        $result = $this->service->buildTree($nodes);

        $this->assertCount(1, $result);
        $this->assertCount(1, $result[0]['children']);
        $this->assertSame($child->id, $result[0]['children'][0]['id']);
        $this->assertSame([], $result[0]['children'][0]['children']);
    }

    public function test_three_level_nesting_is_resolved(): void
    {
        // AC-06: recursive resolution — grandchild must appear under child
        $job = DiscoveryJob::factory()->create();
        $root = DiscoveryNode::factory()->create([
            'discovery_job_id' => $job->id, 'parent_id' => null, 'depth' => 0,
        ]);
        $child = DiscoveryNode::factory()->create([
            'discovery_job_id' => $job->id, 'parent_id' => $root->id, 'depth' => 1,
        ]);
        $grandchild = DiscoveryNode::factory()->create([
            'discovery_job_id' => $job->id, 'parent_id' => $child->id, 'depth' => 2,
        ]);

        $nodes = DiscoveryNode::where('discovery_job_id', $job->id)->get();
        $result = $this->service->buildTree($nodes);

        $this->assertSame(
            $grandchild->id,
            $result[0]['children'][0]['children'][0]['id']
        );
    }

    public function test_multiple_root_nodes_all_returned(): void
    {
        // AC-06: flat tree with 3 roots
        $job = DiscoveryJob::factory()->create();
        DiscoveryNode::factory()->count(3)->create([
            'discovery_job_id' => $job->id, 'parent_id' => null, 'depth' => 0,
        ]);

        $nodes = DiscoveryNode::where('discovery_job_id', $job->id)->get();
        $result = $this->service->buildTree($nodes);

        $this->assertCount(3, $result);
    }

    public function test_node_fields_are_mapped_correctly(): void
    {
        // AC-06: output shape — all five scalar fields forwarded verbatim
        $job = DiscoveryJob::factory()->create();
        DiscoveryNode::factory()->create([
            'discovery_job_id' => $job->id,
            'parent_id' => null,
            'prompt_text' => 'Press 1 for sales',
            'dtmf_option' => '1',
            'node_type' => 'menu',
            'depth' => 0,
        ]);

        $nodes = DiscoveryNode::where('discovery_job_id', $job->id)->get();
        $result = $this->service->buildTree($nodes);

        $this->assertSame('Press 1 for sales', $result[0]['prompt_text']);
        $this->assertSame('1', $result[0]['dtmf_option']);
        $this->assertSame('menu', $result[0]['node_type']);
        $this->assertSame(0, $result[0]['depth']);
        $this->assertArrayHasKey('children', $result[0]);
    }

    public function test_sibling_nodes_do_not_cross_contaminate(): void
    {
        // AC-06: two children of same parent — each gets its own subtree
        $job = DiscoveryJob::factory()->create();
        $root = DiscoveryNode::factory()->create([
            'discovery_job_id' => $job->id, 'parent_id' => null, 'depth' => 0,
        ]);
        $childA = DiscoveryNode::factory()->create([
            'discovery_job_id' => $job->id, 'parent_id' => $root->id, 'depth' => 1,
        ]);
        $childB = DiscoveryNode::factory()->create([
            'discovery_job_id' => $job->id, 'parent_id' => $root->id, 'depth' => 1,
        ]);

        $nodes = DiscoveryNode::where('discovery_job_id', $job->id)->get();
        $result = $this->service->buildTree($nodes);

        $childIds = array_column($result[0]['children'], 'id');
        $this->assertContains($childA->id, $childIds);
        $this->assertContains($childB->id, $childIds);
        $this->assertCount(2, $result[0]['children']);
    }
}
