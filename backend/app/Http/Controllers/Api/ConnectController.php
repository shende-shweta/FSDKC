<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConnectMonitorRequest;
use App\Models\ConnectCheckResult;
use App\Models\ConnectMonitor;
use App\Services\MongoService;
use App\Services\ReachabilityService;
use App\Services\RealTimeTestService;
use Illuminate\Http\JsonResponse;

class ConnectController extends Controller
{
    public function __construct(
        private readonly MongoService $mongo,
        private readonly RealTimeTestService $realtime,
        private readonly ReachabilityService $reachability
    ) {}

    public function index(): JsonResponse
    {
        $monitors = ConnectMonitor::orderByDesc('last_checked_at')->get();

        return response()->json(['data' => $monitors]);
    }

    public function store(StoreConnectMonitorRequest $request): JsonResponse
    {
        $monitor = ConnectMonitor::create([
            ...$request->validated(),
            'status' => 'active',
            'reachability_pct' => 100.0,
        ]);

        return response()->json(['data' => $monitor], 201);
    }

    public function show(int $id): JsonResponse
    {
        $monitor = ConnectMonitor::with(['checkResults' => fn ($q) => $q->orderByDesc('checked_at')->limit(10)])
            ->findOrFail($id);

        return response()->json([
            'data' => $monitor,
            'transcripts' => $this->mongo->getTranscripts('connect', $id),
            'diagnostics' => $this->mongo->getDiagnostics('connect', $id),
        ]);
    }

    public function checks(int $id): JsonResponse
    {
        $monitor = ConnectMonitor::findOrFail($id);

        $checks = ConnectCheckResult::where('connect_monitor_id', $id)
            ->orderByDesc('checked_at')
            ->limit(50)
            ->get();

        $recent = ConnectCheckResult::where('connect_monitor_id', $id)
            ->orderByDesc('checked_at')
            ->limit(20)
            ->get();

        $pct = $this->reachability->calculatePct($recent);

        return response()->json([
            'monitor' => $monitor->only(['id', 'name', 'toll_free_number', 'country_code']),
            'data' => $checks,
            'computed' => [
                'reachability_pct' => $pct,
                'status' => $this->reachability->statusFromPct($pct),
            ],
        ]);
    }

    public function runCheck(int $id): JsonResponse
    {
        ConnectMonitor::findOrFail($id);

        $sessionId = $this->realtime->createSession();

        dispatch(function () use ($id, $sessionId): void {
            app(RealTimeTestService::class)->runConnectTest($id, $sessionId);
        })->afterResponse();

        return response()->json([
            'session_id' => $sessionId,
            'message' => 'Connect test started — connect to stream endpoint',
        ]);
    }
}
