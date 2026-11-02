<?php

namespace Tests\Unit;

use App\Models\ConnectCheckResult;
use App\Models\ConnectMonitor;
use App\Models\DiscoveryJob;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private DashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DashboardService();
    }

    public function test_all_zeros_with_empty_database(): void
    {
        $kpis = $this->service->getKpis();

        $this->assertSame(0.0, $kpis['ivr_availability_pct']);
        $this->assertSame(0.0, $kpis['number_reachability_pct']);
        $this->assertNull($kpis['call_success_rate_pct']);
        $this->assertNull($kpis['transfer_success_rate_pct']);
    }

    public function test_10_completed_of_10_total_jobs_gives_100_pct(): void
    {
        for ($i = 0; $i < 10; $i++) {
            DiscoveryJob::create([
                'name' => "Job {$i}", 'phone_number' => '+1800555000'.$i,
                'country_code' => 'US', 'status' => 'completed', 'languages' => ['en'],
            ]);
        }

        $kpis = $this->service->getKpis();

        $this->assertSame(100.0, $kpis['ivr_availability_pct']);
    }

    public function test_80_reachable_of_100_check_results(): void
    {
        $monitor = ConnectMonitor::create([
            'name' => 'Test', 'toll_free_number' => '18005551234',
            'country_code' => 'US', 'status' => 'active', 'reachability_pct' => 80,
        ]);

        for ($i = 0; $i < 100; $i++) {
            ConnectCheckResult::create([
                'connect_monitor_id' => $monitor->id,
                'reachable' => $i < 80,
                'checked_at' => now()->subSeconds(100 - $i),
            ]);
        }

        $kpis = $this->service->getKpis();

        $this->assertSame(80.0, $kpis['call_success_rate_pct']);
    }

    public function test_transfer_success_rate_computed_correctly(): void
    {
        $monitor = ConnectMonitor::create([
            'name' => 'Test', 'toll_free_number' => '18005551234',
            'country_code' => 'US', 'status' => 'active', 'reachability_pct' => 100,
        ]);

        // 50 results with carrier_route set, 40 of them reachable
        for ($i = 0; $i < 50; $i++) {
            ConnectCheckResult::create([
                'connect_monitor_id' => $monitor->id,
                'reachable' => $i < 40,
                'carrier_route' => 'US -> Carrier',
                'checked_at' => now()->subSeconds(50 - $i),
            ]);
        }

        // 10 results without carrier_route (should not affect transfer rate)
        for ($i = 0; $i < 10; $i++) {
            ConnectCheckResult::create([
                'connect_monitor_id' => $monitor->id,
                'reachable' => false,
                'carrier_route' => null,
                'checked_at' => now()->subSeconds(100 + $i),
            ]);
        }

        $kpis = $this->service->getKpis();

        $this->assertSame(80.0, $kpis['transfer_success_rate_pct']);
        // Confirm hardcoded values are gone
        $this->assertNotEquals(94.2, $kpis['call_success_rate_pct']);
        $this->assertNotEquals(97.8, $kpis['transfer_success_rate_pct']);
    }
}
