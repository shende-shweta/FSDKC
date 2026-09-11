<?php

namespace Tests\Unit;

use App\Models\ConnectCheckResult;
use App\Services\ReachabilityService;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

class ReachabilityCalculationTest extends TestCase
{
    private ReachabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReachabilityService();
    }

    private function makeResults(array $reachableFlags): Collection
    {
        $items = array_map(function (bool $flag) {
            $result = new ConnectCheckResult();
            $result->reachable = $flag;
            return $result;
        }, $reachableFlags);

        return new Collection($items);
    }

    public function test_full_success_rate_is_100(): void
    {
        $results = $this->makeResults(array_fill(0, 20, true));
        $this->assertSame(100.0, $this->service->calculateFromResults($results));
    }

    public function test_partial_success_rate_calculation(): void
    {
        $results = $this->makeResults(array_merge(array_fill(0, 15, true), array_fill(0, 5, false)));
        $this->assertSame(75.0, $this->service->calculateFromResults($results));
    }

    public function test_zero_checks_returns_null(): void
    {
        $this->assertNull($this->service->calculateFromResults(new Collection()));
    }

    public function test_below_threshold_produces_alert_status(): void
    {
        $this->assertSame('alert', $this->service->statusFromRate(85.0));
        $this->assertSame('alert', $this->service->statusFromRate(89.9));
    }

    public function test_at_or_above_threshold_produces_active_status(): void
    {
        $this->assertSame('active', $this->service->statusFromRate(90.0));
        $this->assertSame('active', $this->service->statusFromRate(100.0));
    }

    public function test_null_rate_produces_unknown_status(): void
    {
        $this->assertSame('unknown', $this->service->statusFromRate(null));
    }
}
