<?php

namespace App\Services;

/**
 * Single source of truth for reachability percentage calculation.
 *
 * AC-A01 — Centralise Reachability Calculation
 * Replaces three identical inline calculation blocks in:
 *   - ConnectController::checks()
 *   - LegacyReportController::carrierSummary()
 *   - RealTimeTestService::runConnectTest()
 */
class ReachabilityService
{
    /** Number of most-recent check results to consider. */
    public const WINDOW = 20;

    /**
     * Alert threshold (strict less-than). A rate of exactly 90.0 is 'active'.
     * @see statusFromRate()
     */
    public const ALERT_THRESHOLD = 90.0;

    /**
     * Compute the reachability percentage from an iterable of check records.
     *
     * Each element must expose a truthy 'reachable' property or array key.
     * Returns 100.0 for an empty collection (no data = no failures).
     *
     * @param  iterable<mixed> $checks
     * @return float  0–100, rounded to 2 decimal places
     */
    public function computeRate(iterable $checks): float
    {
        $items = is_array($checks) ? $checks : iterator_to_array($checks, false);

        $total = count($items);
        if ($total === 0) {
            return 100.0;
        }

        $successes = array_reduce($items, static function (int $carry, mixed $item): int {
            $reachable = is_array($item) ? ($item['reachable'] ?? false) : ($item->reachable ?? false);
            return $carry + ($reachable ? 1 : 0);
        }, 0);

        return round(($successes / $total) * 100, 2);
    }

    /**
     * Derive the monitor status string from a computed rate.
     *
     * @param  float  $rate  Value returned by computeRate()
     * @return string 'alert' when $rate < ALERT_THRESHOLD, 'active' otherwise
     */
    public function statusFromRate(float $rate): string
    {
        return $rate < self::ALERT_THRESHOLD ? 'alert' : 'active';
    }
}
