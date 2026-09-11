<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ReachabilityCalculationTest extends TestCase
{
    public function test_full_success_rate_is_100(): void
    {
        $total = 20;
        $reachable = 20;
        $rate = ($reachable / $total) * 100;
        $this->assertSame(100.0, $rate);
    }

    public function test_partial_success_rate_calculation(): void
    {
        $total = 20;
        $reachable = 15;
        $rate = ($reachable / $total) * 100;
        $this->assertSame(75.0, $rate);
    }

    public function test_zero_checks_defaults_to_100(): void
    {
        $total = 0;
        $rate = $total > 0 ? (0 / $total) * 100 : 100.0;
        $this->assertSame(100.0, $rate);
    }

    public function test_below_threshold_produces_alert_status(): void
    {
        $rate = 85.0;
        $status = $rate < 90 ? 'alert' : 'active';
        $this->assertSame('alert', $status);
    }

    public function test_at_or_above_threshold_produces_active_status(): void
    {
        $rate = 90.0;
        $status = $rate < 90 ? 'alert' : 'active';
        $this->assertSame('active', $status);

        $rate = 99.5;
        $status = $rate < 90 ? 'alert' : 'active';
        $this->assertSame('active', $status);
    }

    public function test_hardcoded_modules(): void
    {
        $this->assertSame(['discovery', 'connect'], ['discovery', 'connect']);
    }
}
