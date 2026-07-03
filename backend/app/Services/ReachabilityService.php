<?php

namespace App\Services;

/**
 * ReachabilityService — Single source of truth for reachability percentage calculation.
 * AC-A01: Centralise Reachability Calculation
 *
 * Callers: ConnectController::checks(), LegacyReportController::carrierSummary(),
 *          RealTimeTestService::runConnectTest()
 */
class ReachabilityService
{
    public const ALERT_THRESHOLD = 90.0;
    public const WINDOW = 20;

    /**
     * Computes reachability percentage from a collection of check results.
     *
     * @param iterable $checks  Iterable of ConnectCheckResult models or arrays with 'reachable' key
     * @return float            Percentage 0–100, rounded to 2 decimal places. Returns 100.0 for empty input.
     */
    public function computeRate(iterable $checks): float
    {
        $items = is_array($checks) ? $checks : iterator_to_array($checks);

        if (count($items) === 0) {
            return 100.0;
        }

        $successes = array_reduce($items, static function (int $carry, $item): int {
            $reachable = is_array($item) ? ($item['reachable'] ?? false) : $item->reachable;
            return $reachable ? $carry + 1 : $carry;
        }, 0);

        return round(($successes / count($items)) * 100, 2);
    }

    /**
     * Derives monitor status from a computed reachability rate.
     *
     * @param float $rate  Reachability percentage (0–100)
     * @return string      'alert' if rate < ALERT_THRESHOLD, otherwise 'active'
     */
    public function statusFromRate(float $rate): string
    {
        return $rate < self::ALERT_THRESHOLD ? 'alert' : 'active';
    }
}
