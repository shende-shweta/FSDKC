<?php

namespace App\Services;

use App\Models\ConnectCheckResult;
use App\Models\ConnectMonitor;
use App\Models\DiscoveryJob;

class DashboardService
{
    public function getKpis(): array
    {
        $discoveryTotal = DiscoveryJob::count();
        $discoveryCompleted = DiscoveryJob::where('status', 'completed')->count();
        $ivrAvailability = $discoveryTotal > 0
            ? round(($discoveryCompleted / $discoveryTotal) * 100, 1)
            : 0.0;

        $avgReachability = round((float) (ConnectMonitor::avg('reachability_pct') ?? 0), 1);

        $last100 = ConnectCheckResult::orderByDesc('checked_at')->limit(100)->get();
        $callSuccessRate = $last100->count() > 0
            ? round(($last100->where('reachable', true)->count() / $last100->count()) * 100, 1)
            : null;

        $withCarrier = ConnectCheckResult::whereNotNull('carrier_route')->orderByDesc('checked_at')->limit(100)->get();
        $transferSuccessRate = $withCarrier->count() > 0
            ? round(($withCarrier->where('reachable', true)->count() / $withCarrier->count()) * 100, 1)
            : null;

        return [
            'ivr_availability_pct' => $ivrAvailability,
            'number_reachability_pct' => $avgReachability,
            'call_success_rate_pct' => $callSuccessRate,
            'transfer_success_rate_pct' => $transferSuccessRate,
        ];
    }
}
