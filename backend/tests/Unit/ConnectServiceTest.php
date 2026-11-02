<?php

namespace Tests\Unit;

use App\Models\ConnectCheckResult;
use App\Models\ConnectMonitor;
use App\Services\ConnectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectServiceTest extends TestCase
{
    use RefreshDatabase;

    private ConnectService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ConnectService();
    }

    public function test_zero_results_returns_100_active(): void
    {
        $monitor = ConnectMonitor::create([
            'name' => 'Test', 'toll_free_number' => '18005551234',
            'country_code' => 'US', 'status' => 'active', 'reachability_pct' => 100,
        ]);

        $result = $this->service->calculateReachability($monitor->id);

        $this->assertSame(100.0, $result['pct']);
        $this->assertSame('active', $result['status']);
    }

    public function test_all_20_reachable_returns_100_active(): void
    {
        $monitor = $this->createMonitorWithChecks(20, 20);
        $result = $this->service->calculateReachability($monitor->id);

        $this->assertSame(100.0, $result['pct']);
        $this->assertSame('active', $result['status']);
    }

    public function test_18_of_20_reachable_returns_90_active(): void
    {
        $monitor = $this->createMonitorWithChecks(18, 20);
        $result = $this->service->calculateReachability($monitor->id);

        $this->assertSame(90.0, $result['pct']);
        $this->assertSame('active', $result['status']);
    }

    public function test_17_of_20_reachable_returns_85_alert(): void
    {
        $monitor = $this->createMonitorWithChecks(17, 20);
        $result = $this->service->calculateReachability($monitor->id);

        $this->assertSame(85.0, $result['pct']);
        $this->assertSame('alert', $result['status']);
    }

    public function test_0_of_20_reachable_returns_0_alert(): void
    {
        $monitor = $this->createMonitorWithChecks(0, 20);
        $result = $this->service->calculateReachability($monitor->id);

        $this->assertSame(0.0, $result['pct']);
        $this->assertSame('alert', $result['status']);
    }

    public function test_only_last_20_of_21_results_used(): void
    {
        $monitor = ConnectMonitor::create([
            'name' => 'Test', 'toll_free_number' => '18005551234',
            'country_code' => 'US', 'status' => 'active', 'reachability_pct' => 100,
        ]);

        // The oldest record (21st) is not reachable — if it were included, pct would be < 100
        ConnectCheckResult::create([
            'connect_monitor_id' => $monitor->id, 'reachable' => false,
            'checked_at' => now()->subMinutes(25),
        ]);

        // 20 most recent are all reachable
        for ($i = 20; $i >= 1; $i--) {
            ConnectCheckResult::create([
                'connect_monitor_id' => $monitor->id, 'reachable' => true,
                'checked_at' => now()->subMinutes($i),
            ]);
        }

        $result = $this->service->calculateReachability($monitor->id);

        $this->assertSame(100.0, $result['pct']);
        $this->assertSame('active', $result['status']);
    }

    private function createMonitorWithChecks(int $reachableCount, int $total): ConnectMonitor
    {
        $monitor = ConnectMonitor::create([
            'name' => 'Test', 'toll_free_number' => '18005551234',
            'country_code' => 'US', 'status' => 'active', 'reachability_pct' => 100,
        ]);

        for ($i = 0; $i < $total; $i++) {
            ConnectCheckResult::create([
                'connect_monitor_id' => $monitor->id,
                'reachable' => $i < $reachableCount,
                'checked_at' => now()->subMinutes($total - $i),
            ]);
        }

        return $monitor;
    }
}
