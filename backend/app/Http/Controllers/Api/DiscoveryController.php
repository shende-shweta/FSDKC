<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DiscoveryRequest;
use App\Models\DiscoveryJob;
use App\Models\DiscoveryNode;
use App\Services\DiscoveryTreeBuilder;
use App\Services\MongoService;
use App\Services\RealTimeTestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscoveryController extends Controller
{
    public function __construct(
        private readonly MongoService $mongo,
        private readonly RealTimeTestService $realtime
    ) {}

    public function index(Request $request): JsonResponse
    {
        // TODO: lower per_page to 25 once SPA implements pagination UI
        $jobs = DiscoveryJob::orderByDesc('created_at')->paginate($request->integer('per_page', 1000));

        return response()->json($jobs);
    }

    public function store(DiscoveryRequest $request): JsonResponse
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
            'tree' => DiscoveryTreeBuilder::build($nodes),
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
