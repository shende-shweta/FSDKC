<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiscoveryJob;
use App\Models\DiscoveryNode;
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

    public function index(): JsonResponse
    {
        $jobs = DiscoveryJob::orderByDesc('created_at')->get();

        return response()->json(['data' => $jobs]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone_number' => ['required', 'string', 'max:50', 'regex:/^\+?[1-9]\d{1,14}$/'],
            'country_code' => ['required', 'string', 'max:5', 'regex:/^[A-Z]{2,3}$/'],
            'languages' => 'array',
            'languages.*' => 'string|regex:/^[a-z]{2,3}$/',
        ]);

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
            'tree' => $this->buildTree($nodes),
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