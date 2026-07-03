<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyDataMapper;
use App\Models\ConnectCheckResult;
use App\Models\ConnectMonitor;
use App\Models\DiscoveryJob;
use App\Models\DiscoveryNode;
use App\Services\ReachabilityService;
use App\Support\IvrTreeBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * LegacyReportController
 * AC-A01: Uses ReachabilityService instead of inline calculation.
 * AC-A02: Uses IvrTreeBuilder instead of private buildTree() duplicate.
 * AC-A03: Replaces extract($request->all()) with explicit $request->string() calls.
 */
class LegacyReportController extends Controller
{
    public function __construct(
        private readonly ReachabilityService $reachability
    ) {}

    public function carrierSummary(Request $request): JsonResponse
    {
        // AC-A03: explicit field reads — no extract() on request data
        $countryCode = $request->string('country_code')->toString() ?: null;
        $carrier     = $request->string('carrier')->toString() ?: null;

        $monitors = ConnectMonitor::query()
            ->when($countryCode !== null, fn ($q) => $q->where('country_code', $countryCode))
            ->when($carrier !== null, fn ($q) => $q->where('carrier', $carrier))
            ->orderByDesc('reachability_pct')
            ->get();

        $rows   = [];
        $mapper = new LegacyDataMapper();

        foreach ($monitors as $monitor) {
            // AC-A01: delegate to ReachabilityService
            $recent = ConnectCheckResult::where('connect_monitor_id', $monitor->id)
                ->orderByDesc('checked_at')
                ->limit(ReachabilityService::WINDOW)
                ->get();

            $rate = $this->reachability->computeRate($recent);

            $rows[] = array_merge(
                $mapper->mapReportRow([
                    'name'              => $monitor->name,
                    'reachability_pct'  => $rate,
                    'country_code'      => $monitor->country_code,
                ]),
                ['monitor_id' => $monitor->id, 'carrier' => $monitor->carrier]
            );
        }

        return response()->json(['data' => $rows, 'total' => count($rows)]);
    }

    public function ivrDepthReport(int $jobId): JsonResponse
    {
        $job   = DiscoveryJob::findOrFail($jobId);
        $nodes = DiscoveryNode::where('discovery_job_id', $jobId)->get();

        // AC-A02: delegate to IvrTreeBuilder, remove duplicate private buildTree()
        $tree          = IvrTreeBuilder::build($nodes);
        $maxDepth      = $nodes->max('depth') ?? 0;
        $transferCount = $nodes->where('node_type', 'transfer')->count();

        return response()->json([
            'job_id'   => $job->id,
            'job_name' => $job->name,
            'tree'     => $tree,
            'stats'    => [
                'max_depth'      => $maxDepth,
                'transfer_nodes' => $transferCount,
                'total_nodes'    => $nodes->count(),
            ],
        ]);
    }
}
