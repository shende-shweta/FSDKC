<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// AC: T-18 — UUID validation on session_id; T-27 — SSE endpoint accessible via Bearer or ?token= query
class StreamControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private string $validUuid;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::create(['name' => 'Stream', 'email' => 'stream@example.com', 'password' => bcrypt('pass1234')]);
        $this->token = $user->createToken('test')->plainTextToken;
        $this->validUuid = '550e8400-e29b-41d4-a716-446655440000';
    }

    // T-18: discovery stream rejects non-UUID session_id
    public function test_discovery_stream_rejects_non_uuid_session_id(): void
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->get('/api/discovery/jobs/1/stream?session_id=not-a-uuid');

        $response->assertStatus(400);
    }

    // T-18: connect stream rejects non-UUID session_id
    public function test_connect_stream_rejects_non_uuid_session_id(): void
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->get('/api/connect/monitors/1/stream?session_id=12345');

        $response->assertStatus(400);
    }

    // T-18: discovery stream accepts valid UUID session_id (begins streaming)
    public function test_discovery_stream_accepts_valid_uuid(): void
    {
        // A valid UUID should pass validation and return 200 (streamed response)
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->get("/api/discovery/jobs/1/stream?session_id={$this->validUuid}");

        // 200 because the UUID is valid — stream starts (even if empty)
        $response->assertStatus(200);
    }

    // T-27: stream endpoint accepts token via ?token= query param (EventSource compatibility)
    public function test_stream_endpoint_accepts_token_from_query_param(): void
    {
        $response = $this->get("/api/discovery/jobs/1/stream?session_id={$this->validUuid}&token={$this->token}");

        // Should not be 401 — TokenFromQuery middleware injects the Bearer header
        $this->assertNotEquals(401, $response->status());
    }

    // T-03: stream endpoint without any auth returns 401
    public function test_stream_endpoint_without_auth_returns_401(): void
    {
        $response = $this->get("/api/discovery/jobs/1/stream?session_id={$this->validUuid}");

        $response->assertStatus(401);
    }
}
