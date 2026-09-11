<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConnectCheckResult;
use App\Models\ConnectMonitor;
use App\Models\DiscoveryJob;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function kpis(): JsonResponse
    {
        $discoveryTotal = DiscoveryJob::count();
        $discoveryCompleted = DiscoveryJob::where('status', 'completed')->count();
        $avgReachability = ConnectMonitor::avg('reachability_pct') ?? 0;
        $alerts = ConnectMonitor::where('status', 'alert')->count();

        // pluck fetches one column, avoiding 100 model hydrations.
        // orderByDesc('checked_at') will filesort until index T-13\u2013T-15 lands.
        $reachableFlags = ConnectCheckResult::orderByDesc('checked_at')->limit(100)->pluck('reachable');
        $callSuccessRate = $reachableFlags->isNotEmpty()
            ? round($reachableFlags->filter()->count() / $reachableFlags->count() * 100, 1)
            : null;

        return response()->json([
            'availability' => [
                'ivr_availability_pct' => $discoveryTotal > 0
                    ? round(($discoveryCompleted / $discoveryTotal) * 100, 1)
                    : 0,
                'number_reachability_pct' => round((float) $avgReachability, 1),
                'call_success_rate_pct' => $callSuccessRate,
                'transfer_success_rate_pct' => null,
            ],
            'operational' => [
                'active_discovery_jobs' => DiscoveryJob::where('status', 'running')->count(),
                'active_connect_monitors' => ConnectMonitor::where('status', 'active')->count(),
                'open_alerts' => $alerts,
                'countries_monitored' => ConnectMonitor::distinct('country_code')->count('country_code'),
            ],
            'modules' => ['discovery', 'connect'],
        ]);
    }
}
