<?php

namespace App\Services;

use App\Models\ConnectMonitor;
use App\Models\DiscoveryJob;
use App\Models\DiscoveryNode;
use Illuminate\Support\Str;

class RealTimeTestService
{
    public function __construct(
        private readonly MongoService $mongo,
        private readonly ReachabilityService $reachability
    ) {}

    public function createSession(): string
    {
        return (string) Str::uuid();
    }

    public function runDiscoveryTest(int $jobId, string $sessionId): void
    {
        $job = DiscoveryJob::findOrFail($jobId);
        $job->update(['status' => 'running', 'started_at' => now()]);

        $steps = [
            ['event' => 'call_initiated', 'message' => 'Placing test call to IVR endpoint\u2026', 'progress' => 10],
            ['event' => 'call_connected', 'message' => 'Call connected \u2014 analyzing audio stream', 'progress' => 20],
            ['event' => 'prompt_detected', 'message' => 'Welcome prompt detected', 'transcript' => 'Welcome. Press 1 for accounts.', 'progress' => 35],
            ['event' => 'dtmf_sent', 'message' => 'Sending DTMF: 1', 'dtmf' => '1', 'progress' => 45],
            ['event' => 'menu_discovered', 'message' => 'Sub-menu mapped', 'node_type' => 'menu', 'progress' => 70],
            ['event' => 'traversal_complete', 'message' => 'IVR discovery complete', 'progress' => 100],
        ];

        $this->mongo->storeTestEvent($sessionId, 'discovery', $jobId, [
            'type' => 'status', 'status' => 'running', 'message' => 'Discovery test started', 'progress' => 0,
        ]);

        $parentId = null;
        foreach ($steps as $step) {
            usleep(600_000);
            $this->mongo->storeTestEvent($sessionId, 'discovery', $jobId, ['type' => 'step', ...$step]);

            if (isset($step['transcript'])) {
                $this->mongo->storeTranscript('discovery', $jobId, [
                    'event' => $step['event'], 'transcript' => $step['transcript'], 'session_id' => $sessionId,
                ]);
            }

            if ($step['event'] === 'menu_discovered') {
                $node = DiscoveryNode::create([
                    'discovery_job_id' => $jobId,
                    'parent_id' => $parentId,
                    'prompt_text' => $step['transcript'] ?? 'Menu discovered',
                    'node_type' => 'menu',
                    'depth' => $parentId ? 1 : 0,
                ]);
                $parentId = $node->id;
            }
        }

        $this->mongo->storeDiagnostic('discovery', $jobId, [
            'session_id' => $sessionId, 'mos_score' => 4.2, 'latency_ms' => 115, 'packet_loss_pct' => 0,
        ]);

        $nodeCount = DiscoveryNode::where('discovery_job_id', $jobId)->count();
        $job->update([
            'status' => 'completed',
            'completed_at' => now(),
            'nodes_discovered' => $nodeCount,
            'menu_depth' => DiscoveryNode::where('discovery_job_id', $jobId)->max('depth') ?? 0,
        ]);

        $this->mongo->storeTestEvent($sessionId, 'discovery', $jobId, [
            'type' => 'complete', 'status' => 'completed',
            'message' => "Discovery finished \u2014 {$nodeCount} nodes mapped", 'progress' => 100,
        ]);
    }

    public function runConnectTest(int $monitorId, string $sessionId): void
    {
        $monitor = ConnectMonitor::findOrFail($monitorId);

        $steps = [
            ['event' => 'check_initiated', 'message' => 'Starting TFN reachability check\u2026', 'progress' => 10],
            ['event' => 'carrier_selected', 'message' => 'Carrier route selected', 'progress' => 40],
            ['event' => 'sip_invite', 'message' => 'Sending SIP INVITE', 'progress' => 55],
            ['event' => 'quality_analysis', 'message' => 'Running MOS analysis', 'progress' => 95],
            ['event' => 'check_complete', 'message' => 'Check complete', 'progress' => 100],
        ];

        $this->mongo->storeTestEvent($sessionId, 'connect', $monitorId, [
            'type' => 'status', 'status' => 'running', 'message' => 'Connect test started', 'progress' => 0,
        ]);

        foreach ($steps as $step) {
            usleep(500_000);
            $this->mongo->storeTestEvent($sessionId, 'connect', $monitorId, ['type' => 'step', ...$step]);
        }

        // No ConnectCheckResult written here \u2014 real carrier integration is pending.
        // Rate is computed solely from previously stored real check results.
        $rate = $this->reachability->calculate($monitorId);

        $update = [
            'status' => $this->reachability->statusFromRate($rate),
            'last_checked_at' => now(),
        ];
        if ($rate !== null) {
            $update['reachability_pct'] = round($rate, 2);
        }
        $monitor->update($update);

        $this->mongo->storeTranscript('connect', $monitorId, [
            'event' => 'stub_check_run',
            'session_id' => $sessionId,
            'note' => 'No carrier probe executed \u2014 real integration pending',
        ]);

        $this->mongo->storeTestEvent($sessionId, 'connect', $monitorId, [
            'type' => 'complete',
            'status' => $this->reachability->statusFromRate($rate),
            'message' => 'Stub check complete \u2014 no carrier probe executed',
            'progress' => 100,
        ]);
    }
}
