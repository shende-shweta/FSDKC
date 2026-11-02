<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyDataMapper;
use App\Models\ConnectMonitor;
use App\Models\DiscoveryJob;
use App\Models\DiscoveryNode;
use App\Services\DiscoveryTreeBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegacyReportController extends Controller
{
    public function carrierSummary(Request $request): JsonResponse
    {
        $countryCode = $request->string('country_code')->value();
        $carrier = $request->string('carrier')->value();

        $monitors = ConnectMonitor::with(['checkResults' => fn ($q) => $q->orderByDesc('checked_at')->limit(20)])
            ->when($countryCode !== '', fn ($q) => $q->where('country_code', $countryCode))
            ->when($carrier !== '', fn ($q) => $q->where('carrier', $carrier))
            ->orderByDesc('reachability_pct')
            ->get();

        $rows = [];
        $mapper = new LegacyDataMapper();

        foreach ($monitors as $monitor) {
            $recent = $monitor->checkResults;
            $successRate = $recent->count() > 0
                ? ($recent->where('reachable', true)->count() / $recent->count()) * 100
                : 100;

            $rows[] = array_merge(
                $mapper->mapReportRow([
                    'name' => $monitor->name,
                    'reachability_pct' => round($successRate, 2),
                    'country_code' => $monitor->country_code,
                ]),
                ['monitor_id' => $monitor->id, 'carrier' => $monitor->carrier]
            );
        }

        return response()->json(['data' => $rows, 'total' => count($rows)]);
    }

    public function ivrDepthReport(int $jobId): JsonResponse
    {
        $job = DiscoveryJob::findOrFail($jobId);
        $nodes = DiscoveryNode::where('discovery_job_id', $jobId)->get();

        $tree = DiscoveryTreeBuilder::build($nodes);
        $maxDepth = $nodes->max('depth') ?? 0;
        $transferCount = $nodes->where('node_type', 'transfer')->count();

        return response()->json([
            'job_id' => $job->id,
            'job_name' => $job->name,
            'tree' => $tree,
            'stats' => [
                'max_depth' => $maxDepth,
                'transfer_nodes' => $transferCount,
                'total_nodes' => $nodes->count(),
            ],
        ]);
    }
}
