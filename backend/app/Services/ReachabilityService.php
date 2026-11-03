<?php

namespace App\Services;

use Illuminate\Support\Collection;

class ReachabilityService
{
    public function calculatePct(Collection $results, string $field = 'reachable'): float
    {
        if ($results->isEmpty()) {
            return 100.0;
        }

        return round(
            ($results->where($field, true)->count() / $results->count()) * 100,
            2
        );
    }

    public function statusFromPct(float $pct): string
    {
        return $pct < 90.0 ? 'alert' : 'active';
    }
}
