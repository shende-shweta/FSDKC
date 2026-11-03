<?php

// KPI aggregation — ensures DashboardController::kpis() computes correctly against live DB data.

namespace Tests\Feature;

use App\Models\ConnectMonitor;
use App\Models\DiscoveryJob;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    public function test_kpis_returns_expected_structure(): void
    {
        $this->getJson('/api/dashboard/kpis')
            ->assertOk()
            ->assertJsonStructure([
                'availability' => [
                    'ivr_availability_pct',
                    'number_reachability_pct',
                    'call_success_rate_pct',
                    'transfer_success_rate_pct',
                ],
                'operational' => [
                    'active_discovery_jobs',
                    'active_connect_monitors',
                    'open_alerts',
                    'countries_monitored',
                ],
                'modules',
            ]);
    }

    public function test_kpis_returns_zero_ivr_availability_when_no_jobs(): void
    {
        // Guard: division-by-zero path returns 0, not an error
        $this->getJson('/api/dashboard/kpis')
            ->assertOk()
            ->assertJsonPath('availability.ivr_availability_pct', 0);
    }

    public function test_kpis_computes_ivr_availability_pct(): void
    {
        // 3 completed out of 4 total = 75.0%
        // Use assertEquals (loose) — JSON encodes whole-number floats as integers
        DiscoveryJob::factory()->count(3)->create(['status' => 'completed']);
        DiscoveryJob::factory()->count(1)->create(['status' => 'pending']);

        $response = $this->getJson('/api/dashboard/kpis')->assertOk();
        $this->assertEquals(75.0, $response->json('availability.ivr_availability_pct'));
    }

    public function test_kpis_computes_average_reachability_pct(): void
    {
        ConnectMonitor::factory()->create(['reachability_pct' => 80.0]);
        ConnectMonitor::factory()->create(['reachability_pct' => 100.0]);

        // avg(80.0, 100.0) = 90.0
        $response = $this->getJson('/api/dashboard/kpis')->assertOk();
        $this->assertEquals(90.0, $response->json('availability.number_reachability_pct'));
    }

    public function test_kpis_returns_zero_reachability_when_no_monitors(): void
    {
        $response = $this->getJson('/api/dashboard/kpis')->assertOk();
        $this->assertEquals(0.0, $response->json('availability.number_reachability_pct'));
    }

    public function test_kpis_counts_open_alerts(): void
    {
        ConnectMonitor::factory()->count(2)->create(['status' => 'alert']);
        ConnectMonitor::factory()->count(1)->create(['status' => 'active']);

        $this->getJson('/api/dashboard/kpis')
            ->assertOk()
            ->assertJsonPath('operational.open_alerts', 2);
    }

    public function test_kpis_counts_active_connect_monitors(): void
    {
        ConnectMonitor::factory()->count(2)->create(['status' => 'active']);
        ConnectMonitor::factory()->count(1)->create(['status' => 'alert']);

        $this->getJson('/api/dashboard/kpis')
            ->assertOk()
            ->assertJsonPath('operational.active_connect_monitors', 2);
    }

    public function test_kpis_counts_active_discovery_jobs(): void
    {
        DiscoveryJob::factory()->count(2)->create(['status' => 'running']);
        DiscoveryJob::factory()->count(1)->create(['status' => 'pending']);

        $this->getJson('/api/dashboard/kpis')
            ->assertOk()
            ->assertJsonPath('operational.active_discovery_jobs', 2);
    }

    public function test_kpis_counts_distinct_countries_monitored(): void
    {
        // Two monitors in US and one in GB = 2 distinct countries
        ConnectMonitor::factory()->count(2)->create(['country_code' => 'US']);
        ConnectMonitor::factory()->count(1)->create(['country_code' => 'GB']);

        $this->getJson('/api/dashboard/kpis')
            ->assertOk()
            ->assertJsonPath('operational.countries_monitored', 2);
    }

    public function test_kpis_modules_array_contains_discovery_and_connect(): void
    {
        $response = $this->getJson('/api/dashboard/kpis')->assertOk();

        $modules = $response->json('modules');
        $this->assertContains('discovery', $modules);
        $this->assertContains('connect', $modules);
    }
}
