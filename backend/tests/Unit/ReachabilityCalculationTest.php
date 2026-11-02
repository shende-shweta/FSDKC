<?php

namespace Tests\Unit;

use App\Models\ConnectCheckResult;
use App\Models\ConnectMonitor;
use App\Services\ConnectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReachabilityCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_reachability_returns_active_status(): void
    {
        $service = new ConnectService();
        $monitor = ConnectMonitor::create([
            'name' => 'Test', 'toll_free_number' => '18005551234',
            'country_code' => 'US', 'status' => 'active', 'reachability_pct' => 100,
        ]);

        for ($i = 0; $i < 20; $i++) {
            ConnectCheckResult::create([
                'connect_monitor_id' => $monitor->id, 'reachable' => true,
                'checked_at' => now()->subMinutes(20 - $i),
            ]);
        }

        $result = $service->calculateReachability($monitor->id);

        $this->assertSame(100.0, $result['pct']);
        $this->assertSame('active', $result['status']);
    }

    public function test_below_threshold_returns_alert_status(): void
    {
        $service = new ConnectService();
        $monitor = ConnectMonitor::create([
            'name' => 'Alert Test', 'toll_free_number' => '18005550001',
            'country_code' => 'US', 'status' => 'active', 'reachability_pct' => 100,
        ]);

        for ($i = 0; $i < 20; $i++) {
            ConnectCheckResult::create([
                'connect_monitor_id' => $monitor->id,
                'reachable' => $i < 10,
                'checked_at' => now()->subMinutes(20 - $i),
            ]);
        }

        $result = $service->calculateReachability($monitor->id);

        $this->assertSame(50.0, $result['pct']);
        $this->assertSame('alert', $result['status']);
    }
}
