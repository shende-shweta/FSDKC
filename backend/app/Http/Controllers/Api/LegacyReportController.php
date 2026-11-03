<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyDataMapper;
use App\Models\ConnectMonitor;
use App\Models\DiscoveryJob;
use App\Models\DiscoveryNode;
use App\Services\DiscoveryService;
use App\Services\ReachabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegacyReportController extends Controller
{
    public function __construct(
        private readonly DiscoveryService $discovery,
        private readonly ReachabilityService $reachability
    ) {}

    public function carrierSummary(Request $request): JsonResponse
    {
        $countryCode = $request->string('country_code')->toString() ?: null;
        $carrier = $request->string('carrier')->toString() ?: null;

        // Eager-load the 20 most-recent check results per monitor to eliminate N+1.
        $monitors = ConnectMonitor::query()
            ->when($countryCode !== null, fn ($q) => $q->where('country_code', $countryCode))
            ->when($carrier !== null, fn ($q) => $q->where('carrier', $carrier))
            ->with(['checkResults' => fn ($q) => $q->orderByDesc('checked_at')->limit(20)])
            ->orderByDesc('reachability_pct')
            ->get();

        $mapper = new LegacyDataMapper();

        $rows = $monitors->map(function (ConnectMonitor $monitor) use ($mapper): array {
            $pct = $this->reachability->calculatePct($monitor->checkResults);

            return array_merge(
                $mapper->mapReportRow([
                    'name' => $monitor->name,
                    'reachability_pct' => $pct,
                    'country_code' => $monitor->country_code,
                ]),
                ['monitor_id' => $monitor->id, 'carrier' => $monitor->carrier]
            );
        })->all();

        return response()->json(['data' => $rows, 'total' => count($rows)]);
    }

    public function ivrDepthReport(int $jobId): JsonResponse
    {
        $job = DiscoveryJob::findOrFail($jobId);
        $nodes = DiscoveryNode::where('discovery_job_id', $jobId)->get();

        return response()->json([
            'job_id' => $job->id,
            'job_name' => $job->name,
            'tree' => $this->discovery->buildTree($nodes),
            'stats' => [
                'max_depth' => $nodes->max('depth') ?? 0,
                'transfer_nodes' => $nodes->where('node_type', 'transfer')->count(),
                'total_nodes' => $nodes->count(),
            ],
        ]);
    }
}
