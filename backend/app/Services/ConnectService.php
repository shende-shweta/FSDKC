<?php

namespace App\Services;

use App\Models\ConnectCheckResult;

class ConnectService
{
    public function calculateReachability(int $monitorId): array
    {
        $recent = ConnectCheckResult::where('connect_monitor_id', $monitorId)
            ->orderByDesc('checked_at')
            ->limit(20)
            ->get();

        if ($recent->count() === 0) {
            return ['pct' => 100.0, 'status' => 'active'];
        }

        $pct = round(($recent->where('reachable', true)->count() / $recent->count()) * 100, 2);
        $status = $pct < 90.0 ? 'alert' : 'active';

        return ['pct' => $pct, 'status' => $status];
    }
}
