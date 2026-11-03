<?php

namespace Tests\Unit;

use App\Services\ReachabilityService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class ReachabilityServiceTest extends TestCase
{
    private ReachabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReachabilityService();
    }

    public function test_empty_collection_returns_100(): void
    {
        $pct = $this->service->calculatePct(new Collection([]));
        $this->assertSame(100.0, $pct);
    }

    public function test_all_reachable_returns_100(): void
    {
        $results = new Collection([
            ['reachable' => true],
            ['reachable' => true],
            ['reachable' => true],
        ]);
        $this->assertSame(100.0, $this->service->calculatePct($results));
    }

    public function test_none_reachable_returns_0(): void
    {
        $results = new Collection([
            ['reachable' => false],
            ['reachable' => false],
        ]);
        $this->assertSame(0.0, $this->service->calculatePct($results));
    }

    public function test_mixed_results_rounded_to_2_dp(): void
    {
        // 1 out of 3 reachable = 33.33%
        $results = new Collection([
            ['reachable' => true],
            ['reachable' => false],
            ['reachable' => false],
        ]);
        $this->assertSame(33.33, $this->service->calculatePct($results));
    }

    public function test_80_percent_is_alert_status(): void
    {
        $this->assertSame('alert', $this->service->statusFromPct(80.0));
    }

    public function test_89_99_percent_is_alert_status(): void
    {
        $this->assertSame('alert', $this->service->statusFromPct(89.99));
    }

    public function test_90_percent_is_active_status(): void
    {
        $this->assertSame('active', $this->service->statusFromPct(90.0));
    }

    public function test_100_percent_is_active_status(): void
    {
        $this->assertSame('active', $this->service->statusFromPct(100.0));
    }

    public function test_custom_field_name(): void
    {
        $results = new Collection([
            ['is_up' => true],
            ['is_up' => true],
            ['is_up' => false],
            ['is_up' => false],
        ]);
        $this->assertSame(50.0, $this->service->calculatePct($results, 'is_up'));
    }
}
