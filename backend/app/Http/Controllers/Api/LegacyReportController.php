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
 * Legacy report controller.
 * Now delegates reachability math to ReachabilityService and tree-building to IvrTreeBuilder.
 * extract() anti-pattern removed from LegacyDataMapper.
 */
class LegacyReportController extends Controller
{
    public function __construct(
        private readonly ReachabilityService $reachability
    ) {}

    public function carrierSummary(Request $request): JsonResponse
    {
        // Use explicit validated inputs instead of extract() on $request->all().
        $countryCode = $request->string('country_code')->toString() ?: null;
        $carrier     = $request->string('carrier')->toString() ?: null;

        $monitors = ConnectMonitor::query()
            ->when($countryCode, fn ($q) => $q->where('country_code', $countryCode))
            ->when($carrier, fn ($q) => $q->where('carrier', $carrier))
            ->orderByDesc('reachability_pct')
            ->get();

        $mapper = new LegacyDataMapper();
        $rows   = [];

        foreach ($monitors as $monitor) {
            $recent = ConnectCheckResult::where('connect_monitor_id', $monitor->id)
                ->orderByDesc('checked_at')
                ->limit(ReachabilityService::WINDOW)
                ->get();

            $successRate = $this->reachability->computeRate($recent);

            $rows[] = array_merge(
                $mapper->mapReportRow([
                    'name'             => $monitor->name,
                    'reachability_pct' => $successRate,
                    'country_code'     => $monitor->country_code,
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
