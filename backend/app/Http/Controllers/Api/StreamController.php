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
        abort_if($sessionId === '', 400, 'session_id required');

        return $this->streamSession($sessionId);
    }

    public function connectEvents(Request $request, int $id): StreamedResponse
    {
        $sessionId = $request->string('session_id')->toString();
        abort_if($sessionId === '', 400, 'session_id required');

        return $this->streamSession($sessionId);
    }

    private function streamSession(string $sessionId): StreamedResponse
    {
        return response()->stream(function () use ($sessionId): void {
            $sent = 0;
            $emptyPolls = 0;

            while ($emptyPolls < 60) {
                $events = $this->mongo->getTestEvents($sessionId, $sent);

                foreach ($events as $event) {
                    echo 'data: '.json_encode($event)."\n\n";
                    ob_flush();
                    flush();
                    $sent++;

                    if (($event['event']['type'] ?? '') === 'complete') {
                        return;
                    }
                }

                if (empty($events)) {
                    usleep(500_000);
                    $emptyPolls++;
                } else {
                    $emptyPolls = 0;
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}