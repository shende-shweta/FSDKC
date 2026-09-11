<?php

namespace App\Services;

use App\Models\ConnectCheckResult;
use Illuminate\Database\Eloquent\Collection;

class ReachabilityService
{
    private const ALERT_THRESHOLD = 90.0;
    private const SAMPLE_SIZE = 20;

    public function calculate(int $monitorId): ?float
    {
        $recent = ConnectCheckResult::where('connect_monitor_id', $monitorId)
            ->orderByDesc('checked_at')
            ->limit(self::SAMPLE_SIZE)
            ->get();

        return $this->calculateFromResults($recent);
    }

    public function calculateFromResults(Collection $results): ?float
    {
        if ($results->isEmpty()) {
            return null;
        }

        return ($results->where('reachable', true)->count() / $results->count()) * 100;
    }

    public function statusFromRate(?float $rate): string
    {
        if ($rate === null) {
            return 'unknown';
        }

        return $rate < self::ALERT_THRESHOLD ? 'alert' : 'active';
    }
}
