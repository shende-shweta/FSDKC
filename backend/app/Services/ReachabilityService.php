<?php

namespace App\Services;

/**
 * Single source of truth for reachability percentage calculation.
 * Previously this logic was copy-pasted in ConnectController, LegacyReportController,
 * and RealTimeTestService. Centralised here to eliminate duplication.
 */
class ReachabilityService
{
    /**
     * Threshold below which a monitor is considered in alert state.
     */
    public const ALERT_THRESHOLD = 90.0;

    /**
     * How many recent checks to use when computing the rate.
     */
    public const WINDOW = 20;

    /**
     * Compute a reachability percentage from a collection of check results.
     *
     * @param  iterable<object>  $checks  Items must expose a truthy `reachable` property.
     * @return float  0.0 – 100.0, rounded to 2 decimal places.
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
     * Derive a status string from a reachability rate.
     */
    public function statusFromRate(float $rate): string
    {
        return $rate < self::ALERT_THRESHOLD ? 'alert' : 'active';
    }
}
