<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MongoService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamController extends Controller
{
    /**
     * Maximum number of half-second polling cycles before giving up.
     * 60 × 0.5 s = 30 seconds timeout (unchanged from original).
     */
    private const MAX_POLL_CYCLES = 60;

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
            // Flush any events already stored before the client connected.
            $events = $this->mongo->getTestEvents($sessionId);
            $sent   = 0;

            foreach ($events as $event) {
                $this->sendSseEvent($event);
                $sent++;
                if (($event['event']['type'] ?? '') === 'complete') {
                    return;
                }
            }

            // Poll for new events until the test completes or the timeout is reached.
            $attempts = 0;
            while ($attempts < self::MAX_POLL_CYCLES) {
                usleep(500_000);
                $events = $this->mongo->getTestEvents($sessionId);

                foreach (array_slice($events, $sent) as $event) {
                    $this->sendSseEvent($event);
                    $sent++;
                    if (($event['event']['type'] ?? '') === 'complete') {
                        return;
                    }
                }

                $attempts++;
            }
        }, 200, [
            'Content-Type'     => 'text/event-stream',
            'Cache-Control'    => 'no-cache',
            'Connection'       => 'keep-alive',
            'X-Accel-Buffering'=> 'no',
        ]);
    }

    /**
     * Write a single SSE data frame and flush output buffers.
     */
    private function sendSseEvent(array $event): void
    {
        echo 'data: ' . json_encode($event) . "\n\n";
        ob_flush();
        flush();
    }
}
