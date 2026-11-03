<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDiscoveryJobRequest;
use App\Models\DiscoveryJob;
use App\Models\DiscoveryNode;
use App\Services\DiscoveryService;
use App\Services\MongoService;
use App\Services\RealTimeTestService;
use Illuminate\Http\JsonResponse;

class DiscoveryController extends Controller
{
    public function __construct(
        private readonly MongoService $mongo,
        private readonly RealTimeTestService $realtime,
        private readonly DiscoveryService $discovery
    ) {}

    public function index(): JsonResponse
    {
        $jobs = DiscoveryJob::orderByDesc('created_at')->get();

        return response()->json(['data' => $jobs]);
    }

    public function store(StoreDiscoveryJobRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $job = DiscoveryJob::create([
            ...$validated,
            'status' => 'pending',
            'languages' => $validated['languages'] ?? ['en'],
        ]);

        return response()->json(['data' => $job], 201);
    }

    public function show(int $id): JsonResponse
    {
        $job = DiscoveryJob::with('nodes')->findOrFail($id);

        return response()->json([
            'data' => $job,
            'transcripts' => $this->mongo->getTranscripts('discovery', $id),
            'diagnostics' => $this->mongo->getDiagnostics('discovery', $id),
        ]);
    }

    public function tree(int $id): JsonResponse
    {
        $job = DiscoveryJob::findOrFail($id);
        $nodes = DiscoveryNode::where('discovery_job_id', $id)->get();

        return response()->json([
            'job_id' => $job->id,
            'job_name' => $job->name,
            'tree' => $this->discovery->buildTree($nodes),
        ]);
    }

    public function start(int $id): JsonResponse
    {
        $job = DiscoveryJob::findOrFail($id);

        if ($job->status === 'running') {
            return response()->json(['message' => 'Job already running'], 409);
        }

        $sessionId = $this->realtime->createSession();

        dispatch(function () use ($id, $sessionId): void {
            app(RealTimeTestService::class)->runDiscoveryTest($id, $sessionId);
        })->afterResponse();

        return response()->json([
            'session_id' => $sessionId,
            'message' => 'Discovery test started — connect to stream endpoint',
        ]);
    }
}
