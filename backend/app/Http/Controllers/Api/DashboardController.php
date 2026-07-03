<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConnectMonitor;
use App\Models\DiscoveryJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * DashboardController
 * AC-C01: Wrap all 7 KPI queries in Cache::remember (30-second TTL).
 */
class DashboardController extends Controller
{
    private const CACHE_TTL = 30;
    private const CACHE_KEY = 'dashboard.kpis';

    public function kpis(): JsonResponse
    {
        $data = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, static function (): array {
            $discoveryTotal     = DiscoveryJob::count();
            $discoveryCompleted = DiscoveryJob::where('status', 'completed')->count();
            $avgReachability    = ConnectMonitor::avg('reachability_pct') ?? 0;
            $alerts             = ConnectMonitor::where('status', 'alert')->count();

            return [
                'availability' => [
                    'ivr_availability_pct' => $discoveryTotal > 0
                        ? round(($discoveryCompleted / $discoveryTotal) * 100, 1)
                        : 0,
                    'number_reachability_pct' => round((float) $avgReachability, 1),
                    // TODO: replace with real computed values when call-result data is available.
                    'call_success_rate_pct'     => 94.2,
                    'transfer_success_rate_pct' => 97.8,
                ],
                'operational' => [
                    'active_discovery_jobs'  => DiscoveryJob::where('status', 'running')->count(),
                    'active_connect_monitors' => ConnectMonitor::where('status', 'active')->count(),
                    'open_alerts'             => $alerts,
                    'countries_monitored'     => ConnectMonitor::distinct('country_code')->count('country_code'),
                ],
                'modules' => ['discovery', 'connect'],
            ];
        });

        return response()->json($data);
    }
}
