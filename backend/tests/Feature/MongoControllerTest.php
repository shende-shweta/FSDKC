<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// AC: T-19 — module allow-list (discovery|connect only); T-03 — auth guard
class MongoControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('pass1234')]);
        $this->token = $user->createToken('test')->plainTextToken;
    }

    // T-19: diagnostics with invalid module returns 400
    public function test_diagnostics_rejects_disallowed_module(): void
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/mongodb/diagnostics/billing/1');

        $response->assertStatus(400);
    }

    // T-19: diagnostics with 'discovery' module is allowed
    public function test_diagnostics_allows_discovery_module(): void
    {
        // Response may fail due to no Mongo in test env, but auth + module validation must pass (not 400/401)
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/mongodb/diagnostics/discovery/1');

        $this->assertNotEquals(400, $response->status());
        $this->assertNotEquals(401, $response->status());
    }

    // T-19: diagnostics with 'connect' module is allowed
    public function test_diagnostics_allows_connect_module(): void
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/mongodb/diagnostics/connect/1');

        $this->assertNotEquals(400, $response->status());
        $this->assertNotEquals(401, $response->status());
    }

    // T-03: diagnostics without auth returns 401
    public function test_diagnostics_requires_auth(): void
    {
        $this->getJson('/api/mongodb/diagnostics/discovery/1')->assertStatus(401);
    }

    // T-19: transcripts endpoint validates module and reference_id are present
    public function test_transcripts_requires_module_and_reference_id(): void
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/mongodb/transcripts');

        $response->assertStatus(422);
    }
}
