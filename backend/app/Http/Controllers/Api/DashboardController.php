<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConnectMonitor;
use App\Models\DiscoveryJob;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {}

    public function kpis(): JsonResponse
    {
        $kpis = $this->dashboardService->getKpis();

        return response()->json([
            'availability' => [
                'ivr_availability_pct' => $kpis['ivr_availability_pct'],
                'number_reachability_pct' => $kpis['number_reachability_pct'],
                'call_success_rate_pct' => $kpis['call_success_rate_pct'],
                'transfer_success_rate_pct' => $kpis['transfer_success_rate_pct'],
            ],
            'operational' => [
                'active_discovery_jobs' => DiscoveryJob::where('status', 'running')->count(),
                'active_connect_monitors' => ConnectMonitor::where('status', 'active')->count(),
                'open_alerts' => ConnectMonitor::where('status', 'alert')->count(),
                'countries_monitored' => ConnectMonitor::distinct('country_code')->count('country_code'),
            ],
            'modules' => ['discovery', 'connect'],
        ]);
    }
}
