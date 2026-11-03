<?php

// AC-02: N+1 query fix in carrierSummary — reachability must be computed from eager-loaded results.
// AC-05: ReachabilityService is used consistently; no inline duplication.

namespace Tests\Feature;

use App\Models\ConnectCheckResult;
use App\Models\ConnectMonitor;
use App\Models\DiscoveryJob;
use App\Models\DiscoveryNode;
use Tests\TestCase;

class LegacyReportApiTest extends TestCase
{
    // --- GET /api/legacy/reports/carriers ---

    public function test_carrier_summary_returns_all_monitors(): void
    {
        ConnectMonitor::factory()->count(3)->create();

        $this->getJson('/api/legacy/reports/carriers')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('total', 3);
    }

    public function test_carrier_summary_returns_empty_when_no_monitors(): void
    {
        $this->getJson('/api/legacy/reports/carriers')
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_carrier_summary_filters_by_country_code(): void
    {
        ConnectMonitor::factory()->create(['country_code' => 'US']);
        ConnectMonitor::factory()->create(['country_code' => 'GB']);

        $response = $this->getJson('/api/legacy/reports/carriers?country_code=US');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.region', 'US');
    }

    public function test_carrier_summary_filters_by_carrier(): void
    {
        ConnectMonitor::factory()->create(['carrier' => 'Verizon', 'country_code' => 'US']);
        ConnectMonitor::factory()->create(['carrier' => 'BT', 'country_code' => 'GB']);

        $this->getJson('/api/legacy/reports/carriers?carrier=Verizon')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_carrier_summary_computes_reachability_from_eager_loaded_checks(): void
    {
        // AC-02: The static reachability_pct on the model must be ignored;
        //        the response metric must reflect actual check results.
        $monitor = ConnectMonitor::factory()->create(['reachability_pct' => 100.0]);
        ConnectCheckResult::factory()->count(8)->create([
            'connect_monitor_id' => $monitor->id,
            'reachable' => true,
        ]);
        ConnectCheckResult::factory()->count(2)->create([
            'connect_monitor_id' => $monitor->id,
            'reachable' => false,
        ]);

        $response = $this->getJson('/api/legacy/reports/carriers');

        $response->assertOk();
        // 8/10 = 80% via ReachabilityService (not the 100.0 stored on the model)
        $this->assertEquals(80.0, $response->json('data.0.metric'));
    }

    public function test_carrier_summary_monitor_with_no_checks_defaults_to_100(): void
    {
        // AC-05: ReachabilityService returns 100.0 for empty collection
        ConnectMonitor::factory()->create();

        $response = $this->getJson('/api/legacy/reports/carriers');

        $response->assertOk();
        $this->assertEquals(100.0, $response->json('data.0.metric'));
    }

    public function test_carrier_summary_row_contains_expected_keys(): void
    {
        ConnectMonitor::factory()->create([
            'name' => 'Test TFN',
            'country_code' => 'CA',
            'carrier' => 'Rogers',
        ]);

        $this->getJson('/api/legacy/reports/carriers')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['label', 'metric', 'region', 'source', 'monitor_id', 'carrier'],
                ],
            ]);
    }

    public function test_carrier_summary_source_field_is_legacy_mapper(): void
    {
        ConnectMonitor::factory()->create();

        $response = $this->getJson('/api/legacy/reports/carriers');

        $response->assertOk()
            ->assertJsonPath('data.0.source', 'legacy_mapper');
    }

    // --- GET /api/legacy/reports/ivr/{jobId} ---

    public function test_ivr_depth_report_returns_tree_and_stats(): void
    {
        // AC-06: tree returned via DiscoveryService::buildTree()
        $job = DiscoveryJob::factory()->create();
        $root = DiscoveryNode::factory()->create([
            'discovery_job_id' => $job->id,
            'parent_id' => null,
            'depth' => 0,
            'node_type' => 'menu',
        ]);
        DiscoveryNode::factory()->create([
            'discovery_job_id' => $job->id,
            'parent_id' => $root->id,
            'depth' => 1,
            'node_type' => 'transfer',
        ]);

        $response = $this->getJson("/api/legacy/reports/ivr/{$job->id}");

        $response->assertOk()
            ->assertJsonPath('job_id', $job->id)
            ->assertJsonPath('job_name', $job->name)
            ->assertJsonCount(1, 'tree')
            ->assertJsonPath('stats.max_depth', 1)
            ->assertJsonPath('stats.transfer_nodes', 1)
            ->assertJsonPath('stats.total_nodes', 2);
    }

    public function test_ivr_depth_report_returns_zero_stats_for_empty_job(): void
    {
        $job = DiscoveryJob::factory()->create();

        $this->getJson("/api/legacy/reports/ivr/{$job->id}")
            ->assertOk()
            ->assertJsonPath('stats.max_depth', 0)
            ->assertJsonPath('stats.transfer_nodes', 0)
            ->assertJsonPath('stats.total_nodes', 0)
            ->assertJsonCount(0, 'tree');
    }

    public function test_ivr_depth_report_returns_404_for_missing_job(): void
    {
        $this->getJson('/api/legacy/reports/ivr/99999')
            ->assertNotFound();
    }
}
