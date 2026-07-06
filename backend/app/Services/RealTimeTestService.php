<?php

namespace App\Services;

use App\Models\ConnectCheckResult;
use App\Models\ConnectMonitor;
use App\Models\DiscoveryJob;
use App\Models\DiscoveryNode;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * AC-B02 — Injected ReachabilityService; removed inline reachability calculation.
 *           Added structured Log::info() calls on test completion.
 */
class RealTimeTestService
{
    public function __construct(
        private readonly MongoService $mongo,
        private readonly ReachabilityService $reachability  // AC-B02: injected
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
            ['event' => 'call_initiated',   'message' => 'Placing test call to IVR endpoint…',     'progress' => 10],
            ['event' => 'call_connected',   'message' => 'Call connected — analyzing audio stream', 'progress' => 20],
            ['event' => 'prompt_detected',  'message' => 'Welcome prompt detected',                  'transcript' => 'Welcome. Press 1 for accounts.', 'progress' => 35],
            ['event' => 'dtmf_sent',        'message' => 'Sending DTMF: 1',                          'dtmf' => '1', 'progress' => 45],
            ['event' => 'menu_discovered',  'message' => 'Sub-menu mapped',                          'node_type' => 'menu', 'progress' => 70],
            ['event' => 'traversal_complete','message' => 'IVR discovery complete',                  'progress' => 100],
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
                    'event'      => $step['event'],
                    'transcript' => $step['transcript'],
                    'session_id' => $sessionId,
                ]);
            }

            if ($step['event'] === 'menu_discovered') {
                $node = DiscoveryNode::create([
                    'discovery_job_id' => $jobId,
                    'parent_id'        => $parentId,
                    'prompt_text'      => $step['transcript'] ?? 'Menu discovered',
                    'node_type'        => 'menu',
                    'depth'            => $parentId ? 1 : 0,
                ]);
                $parentId = $node->id;
            }
        }

        $this->mongo->storeDiagnostic('discovery', $jobId, [
            'session_id'      => $sessionId,
            'mos_score'       => 4.2,
            'latency_ms'      => 115,
            'packet_loss_pct' => 0,
        ]);

        $nodeCount = DiscoveryNode::where('discovery_job_id', $jobId)->count();
        $job->update([
            'status'          => 'completed',
            'completed_at'    => now(),
            'nodes_discovered' => $nodeCount,
            'menu_depth'      => DiscoveryNode::where('discovery_job_id', $jobId)->max('depth') ?? 0,
        ]);

        $this->mongo->storeTestEvent($sessionId, 'discovery', $jobId, [
            'type'    => 'complete',
            'status'  => 'completed',
            'message' => "Discovery finished — {$nodeCount} nodes mapped",
            'progress' => 100,
        ]);

        // AC-B02: structured logging on completion
        Log::info('Discovery test completed', [
            'job_id'     => $jobId,
            'session_id' => $sessionId,
            'nodes'      => $nodeCount,
        ]);
    }

    public function runConnectTest(int $monitorId, string $sessionId): void
    {
        $monitor   = ConnectMonitor::findOrFail($monitorId);
        $reachable = random_int(1, 100) > 20;

        $steps = [
            ['event' => 'check_initiated', 'message' => 'Starting TFN reachability check…', 'progress' => 10],
            ['event' => 'carrier_selected','message' => 'Carrier route selected',           'progress' => 40],
            ['event' => 'sip_invite',      'message' => 'Sending SIP INVITE',               'progress' => 55],
            ['event' => 'quality_analysis','message' => 'Running MOS analysis',             'progress' => 95],
            ['event' => 'check_complete',  'message' => 'Check complete',                   'progress' => 100],
        ];

        $this->mongo->storeTestEvent($sessionId, 'connect', $monitorId, [
            'type' => 'status', 'status' => 'running', 'message' => 'Connect test started', 'progress' => 0,
        ]);

        foreach ($steps as $step) {
            usleep(500_000);
            $payload = ['type' => 'step', ...$step];
            if ($step['event'] === 'check_complete') {
                $payload['reachable'] = $reachable;
            }
            $this->mongo->storeTestEvent($sessionId, 'connect', $monitorId, $payload);
        }

        $latency = $reachable ? random_int(180, 450) : null;
        ConnectCheckResult::create([
            'connect_monitor_id' => $monitorId,
            'reachable'          => $reachable,
            'latency_ms'         => $latency,
            'carrier_route'      => $monitor->carrier ? "{$monitor->country_code} -> {$monitor->carrier} SIP" : null,
            'failure_reason'     => $reachable ? null : 'Carrier routing failure',
            'checked_at'         => now(),
        ]);

        // AC-B02: delegate to ReachabilityService (removed inline calculation)
        $recent = ConnectCheckResult::where('connect_monitor_id', $monitorId)
            ->orderByDesc('checked_at')
            ->limit(ReachabilityService::WINDOW)
            ->get();

        $rate   = $this->reachability->computeRate($recent);
        $status = $this->reachability->statusFromRate($rate);

        $monitor->update([
            'reachability_pct' => $rate,
            'status'           => $status,
            'last_checked_at'  => now(),
        ]);

        $this->mongo->storeTranscript('connect', $monitorId, [
            'event'      => $reachable ? 'reachability_check_passed' : 'reachability_check_failed',
            'session_id' => $sessionId,
            'latency_ms' => $latency,
        ]);

        $this->mongo->storeTestEvent($sessionId, 'connect', $monitorId, [
            'type'       => 'complete',
            'status'     => $reachable ? 'reachable' : 'failed',
            'message'    => $reachable ? 'TFN is reachable' : 'TFN check failed',
            'progress'   => 100,
            'reachable'  => $reachable,
            'latency_ms' => $latency,
        ]);

        // AC-B02: structured logging on completion
        Log::info('Connect test completed', [
            'monitor_id' => $monitorId,
            'session_id' => $sessionId,
            'reachable'  => $reachable,
            'latency_ms' => $latency,
        ]);
    }
}
