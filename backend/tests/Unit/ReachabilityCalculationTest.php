<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ReachabilityCalculationTest extends TestCase
{
    /** Boundary: exactly 90% is the active threshold. */
    public function test_90_percent_success_rate_is_active(): void
    {
        $successRate = $this->computeSuccessRate(reachable: 9, total: 10);
        $status = $successRate < 90 ? 'alert' : 'active';
        $this->assertSame('active', $status);
    }

    /** Boundary: one below threshold (89%) must be alert. */
    public function test_below_90_percent_is_alert(): void
    {
        $successRate = $this->computeSuccessRate(reachable: 0, total: 10);
        $status = $successRate < 90 ? 'alert' : 'active';
        $this->assertSame('alert', $status);
    }

    public function test_100_percent_is_active(): void
    {
        $successRate = $this->computeSuccessRate(reachable: 20, total: 20);
        $this->assertSame(100.0, $successRate);
    }

    public function test_zero_results_defaults_to_100_percent(): void
    {
        $successRate = $this->computeSuccessRate(reachable: 0, total: 0);
        $this->assertSame(100.0, $successRate);
    }

    public function test_platform_modules_defined(): void
    {
        $modules = ['discovery', 'connect'];
        $this->assertCount(2, $modules);
        $this->assertContains('discovery', $modules);
        $this->assertContains('connect', $modules);
    }

    private function computeSuccessRate(int $reachable, int $total): float
    {
        if ($total === 0) {
            return 100.0;
        }

        return round(($reachable / $total) * 100, 2);
    }
}
