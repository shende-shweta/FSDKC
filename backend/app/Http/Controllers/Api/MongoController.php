<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MongoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MongoController extends Controller
{
    public function __construct(
        private readonly MongoService $mongo
    ) {}

    public function status(): JsonResponse
    {
        return response()->json($this->mongo->health());
    }

    public function transcripts(Request $request): JsonResponse
    {
        $request->validate([
            'module' => 'required|string|in:discovery,connect',
            'reference_id' => 'required|integer',
        ]);

        return response()->json([
            'data' => $this->mongo->getTranscripts(
                $request->string('module')->toString(),
                $request->integer('reference_id')
            ),
        ]);
    }

    public function diagnostics(string $module, int $referenceId): JsonResponse
    {
        if (! in_array($module, ['discovery', 'connect'], true)) {
            return response()->json(['error' => 'Invalid module. Allowed: discovery, connect'], 422);
        }

        return response()->json([
            'data' => $this->mongo->getDiagnostics($module, $referenceId),
        ]);
    }
}