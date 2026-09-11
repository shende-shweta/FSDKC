<?php

namespace App\Services;

use MongoDB\Client;
use MongoDB\Collection;

class MongoService
{
    private ?Client $client = null;

    private ?Collection $transcripts = null;

    private ?Collection $testEvents = null;

    private ?Collection $diagnostics = null;

    public function __construct()
    {
        $uri = config('database.mongodb.uri');

        if ($uri) {
            $this->client = new Client($uri);
            $db = $this->client->selectDatabase('klearcom');
            $this->transcripts = $db->selectCollection('transcripts');
            $this->testEvents = $db->selectCollection('test_events');
            $this->diagnostics = $db->selectCollection('call_diagnostics');
        }
    }

    public function isConnected(): bool
    {
        if ($this->client === null) {
            return false;
        }

        try {
            $result = $this->client->selectDatabase('klearcom')->command(['ping' => 1]);

            return ($result->toArray()[0]['ok'] ?? 0) === 1;
        } catch (\Throwable) {
            return false;
        }
    }

    public function health(): array
    {
        if (! $this->isConnected()) {
            return ['connected' => false];
        }

        return [
            'connected' => true,
            'collections' => [
                'transcripts' => $this->transcripts?->countDocuments() ?? 0,
                'test_events' => $this->testEvents?->countDocuments() ?? 0,
                'diagnostics' => $this->diagnostics?->countDocuments() ?? 0,
            ],
        ];
    }

    public function storeTranscript(string $module, int $referenceId, array $payload): ?string
    {
        if ($this->transcripts === null) {
            return null;
        }

        $result = $this->transcripts->insertOne([
            'module' => $module,
            'reference_id' => $referenceId,
            'payload' => $payload,
            'created_at' => new \MongoDB\BSON\UTCDateTime,
        ]);

        return (string) $result->getInsertedId();
    }

    public function storeTestEvent(string $sessionId, string $module, int $referenceId, array $event): ?string
    {
        if ($this->testEvents === null) {
            return null;
        }

        $result = $this->testEvents->insertOne([
            'session_id' => $sessionId,
            'module' => $module,
            'reference_id' => $referenceId,
            'event' => $event,
            'created_at' => new \MongoDB\BSON\UTCDateTime,
        ]);

        return (string) $result->getInsertedId();
    }

    public function storeDiagnostic(string $module, int $referenceId, array $data): ?string
    {
        if ($this->diagnostics === null) {
            return null;
        }

        $result = $this->diagnostics->insertOne([
            'module' => $module,
            'reference_id' => $referenceId,
            ...$data,
            'created_at' => new \MongoDB\BSON\UTCDateTime,
        ]);

        return (string) $result->getInsertedId();
    }

    public function getTranscripts(string $module, int $referenceId): array
    {
        if ($this->transcripts === null) {
            return [];
        }

        $cursor = $this->transcripts->find(
            ['module' => $module, 'reference_id' => $referenceId],
            ['sort' => ['created_at' => -1], 'limit' => 50]
        );

        return array_map([$this, 'serializeDocument'], iterator_to_array($cursor));
    }

    public function getTestEvents(string $sessionId, int $skip = 0): array
    {
        if ($this->testEvents === null) {
            return [];
        }

        $cursor = $this->testEvents->find(
            ['session_id' => $sessionId],
            ['sort' => ['created_at' => 1], 'skip' => $skip]
        );

        return array_map([$this, 'serializeDocument'], iterator_to_array($cursor));
    }

    public function getDiagnostics(string $module, int $referenceId): array
    {
        if ($this->diagnostics === null) {
            return [];
        }

        $cursor = $this->diagnostics->find(
            ['module' => $module, 'reference_id' => $referenceId],
            ['sort' => ['created_at' => -1], 'limit' => 10]
        );

        return array_map([$this, 'serializeDocument'], iterator_to_array($cursor));
    }

    private function serializeDocument($doc): array
    {
        $arr = $doc instanceof \MongoDB\Model\BSONDocument ? $doc->getArrayCopy() : (array) $doc;
        if (isset($arr['_id'])) {
            $arr['_id'] = (string) $arr['_id'];
        }
        if (isset($arr['created_at']) && $arr['created_at'] instanceof \MongoDB\BSON\UTCDateTime) {
            $arr['created_at'] = $arr['created_at']->toDateTime()->format(\DateTimeInterface::ATOM);
        }

        return $arr;
    }
}