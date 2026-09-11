<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyDataMapper;
use App\Models\ConnectMonitor;
use App\Models\DiscoveryJob;
use App\Models\DiscoveryNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegacyReportController extends Controller
{
    public function carrierSummary(Request $request): JsonResponse
    {
        $countryCode = $request->query('country_code');
        $carrier = $request->query('carrier');

        $monitors = ConnectMonitor::query()
            ->when($countryCode, fn ($q) => $q->where('country_code', $countryCode))
            ->when($carrier, fn ($q) => $q->where('carrier', $carrier))
            ->orderByDesc('reachability_pct')
            ->get();

        $mapper = new LegacyDataMapper();

        $rows = $monitors->map(fn (ConnectMonitor $monitor) => array_merge(
            $mapper->mapReportRow([
                'name' => $monitor->name,
                'reachability_pct' => $monitor->reachability_pct,
                'country_code' => $monitor->country_code,
            ]),
            ['monitor_id' => $monitor->id, 'carrier' => $monitor->carrier]
        ))->all();

        return response()->json(['data' => $rows, 'total' => count($rows)]);
    }

    public function ivrDepthReport(int $jobId): JsonResponse
    {
        $job = DiscoveryJob::findOrFail($jobId);
        $nodes = DiscoveryNode::where('discovery_job_id', $jobId)->get();

        $tree = $this->buildTree($nodes);
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

    private function buildTree($nodes, ?int $parentId = null): array
    {
        return $nodes
            ->where('parent_id', $parentId)
            ->map(fn (DiscoveryNode $node) => [
                'id' => $node->id,
                'prompt_text' => $node->prompt_text,
                'dtmf_option' => $node->dtmf_option,
                'node_type' => $node->node_type,
                'depth' => $node->depth,
                'children' => $this->buildTree($nodes, $node->id),
            ])
            ->values()
            ->all();
    }
}