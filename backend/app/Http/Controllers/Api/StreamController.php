<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MongoService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamController extends Controller
{
    public function __construct(
        private readonly MongoService $mongo
    ) {}

    public function discoveryEvents(Request $request, int $id): StreamedResponse
    {
        $sessionId = $request->string('session_id')->toString();
        abort_if(
            ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $sessionId),
            400,
            'session_id must be a valid UUID'
        );

        return $this->streamSession($sessionId);
    }

    public function connectEvents(Request $request, int $id): StreamedResponse
    {
        $sessionId = $request->string('session_id')->toString();
        abort_if(
            ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $sessionId),
            400,
            'session_id must be a valid UUID'
        );

        return $this->streamSession($sessionId);
    }

    private function streamSession(string $sessionId): StreamedResponse
    {
        return response()->stream(function () use ($sessionId): void {
            $sent = 0;

            $attempts = 0;
            while ($attempts < 60) {
                $events = $this->mongo->getTestEventsSince($sessionId, $sent);
                foreach ($events as $event) {
                    echo 'data: '.json_encode($event)."\n\n";
                    ob_flush();
                    flush();
                    $sent++;
                    if (($event['event']['type'] ?? '') === 'complete') {
                        return;
                    }
                }
                usleep(500_000);
                $attempts++;
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
