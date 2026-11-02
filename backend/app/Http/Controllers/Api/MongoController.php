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
            'module' => 'required|string',
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
        abort_if(! in_array($module, ['discovery', 'connect'], true), 400, 'module must be discovery or connect');

        return response()->json([
            'data' => $this->mongo->getDiagnostics($module, $referenceId),
        ]);
    }
}
