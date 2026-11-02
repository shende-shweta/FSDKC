<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConnectRequest;
use App\Models\ConnectCheckResult;
use App\Models\ConnectMonitor;
use App\Services\ConnectService;
use App\Services\MongoService;
use App\Services\RealTimeTestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConnectController extends Controller
{
    public function __construct(
        private readonly MongoService $mongo,
        private readonly RealTimeTestService $realtime,
        private readonly ConnectService $connectService
    ) {}

    public function index(Request $request): JsonResponse
    {
        // TODO: lower per_page to 25 once SPA implements pagination UI
        $monitors = ConnectMonitor::orderByDesc('last_checked_at')->paginate($request->integer('per_page', 1000));

        return response()->json($monitors);
    }

    public function store(ConnectRequest $request): JsonResponse
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

        $reachability = $this->connectService->calculateReachability($id);

        return response()->json([
            'monitor' => $monitor->only(['id', 'name', 'toll_free_number', 'country_code']),
            'data' => $checks,
            'computed' => [
                'reachability_pct' => $reachability['pct'],
                'status' => $reachability['status'],
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
