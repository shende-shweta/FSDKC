<?php

namespace Tests\Feature;

use App\Models\DiscoveryJob;
use App\Models\DiscoveryNode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// AC: T-15, T-17, T-20, T-21, T-28 — DiscoveryRequest validation, DiscoveryTreeBuilder wiring, pagination
class DiscoveryControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::create(['name' => 'Tester', 'email' => 'tester@example.com', 'password' => bcrypt('pass1234')]);
        $this->token = $user->createToken('test')->plainTextToken;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    // T-17: store validates phone with E.164 regex
    public function test_store_rejects_invalid_phone_number(): void
    {
        $response = $this->withHeaders($this->auth())->postJson('/api/discovery/jobs', [
            'name' => 'Bad Phone Job',
            'phone_number' => 'not-a-phone',
            'country_code' => 'US',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('phone_number');
    }

    // T-15: store rejects unsupported country_code
    public function test_store_rejects_unsupported_country_code(): void
    {
        $response = $this->withHeaders($this->auth())->postJson('/api/discovery/jobs', [
            'name' => 'Bad Country',
            'phone_number' => '+18005551234',
            'country_code' => 'XX',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('country_code');
    }

    // T-15: store rejects unsupported language element
    public function test_store_rejects_unsupported_language(): void
    {
        $response = $this->withHeaders($this->auth())->postJson('/api/discovery/jobs', [
            'name' => 'Bad Lang',
            'phone_number' => '+18005551234',
            'country_code' => 'US',
            'languages' => ['xx'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('languages.0');
    }

    // T-17: store creates job with valid payload
    public function test_store_creates_job_with_valid_payload(): void
    {
        $response = $this->withHeaders($this->auth())->postJson('/api/discovery/jobs', [
            'name' => 'Bank IVR',
            'phone_number' => '+18005551234',
            'country_code' => 'US',
            'languages' => ['en'],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Bank IVR');
        $this->assertDatabaseHas('discovery_jobs', ['name' => 'Bank IVR']);
    }

    // T-03: unauthenticated request to store returns 401
    public function test_store_requires_auth(): void
    {
        $this->postJson('/api/discovery/jobs', [
            'name' => 'No Auth',
            'phone_number' => '+18005551234',
            'country_code' => 'US',
        ])->assertStatus(401);
    }

    // T-21: tree endpoint returns DiscoveryTreeBuilder output for existing job
    public function test_tree_returns_nested_structure(): void
    {
        $job = DiscoveryJob::create([
            'name' => 'IVR Tree', 'phone_number' => '+18005551234',
            'country_code' => 'US', 'status' => 'completed', 'languages' => ['en'],
        ]);
        DiscoveryNode::create(['discovery_job_id' => $job->id, 'parent_id' => null, 'prompt_text' => 'Root', 'node_type' => 'menu', 'depth' => 0]);
        DiscoveryNode::create(['discovery_job_id' => $job->id, 'parent_id' => 1, 'prompt_text' => 'Child', 'node_type' => 'menu', 'depth' => 1]);

        $response = $this->withHeaders($this->auth())->getJson("/api/discovery/jobs/{$job->id}/tree");

        $response->assertStatus(200);
        $response->assertJsonStructure(['job_id', 'job_name', 'tree']);
        $tree = $response->json('tree');
        $this->assertIsArray($tree);
    }

    // T-28: index returns paginated response
    public function test_index_returns_paginated_response(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            DiscoveryJob::create(['name' => "Job {$i}", 'phone_number' => "+1800555000{$i}", 'country_code' => 'US', 'status' => 'pending', 'languages' => ['en']]);
        }

        $response = $this->withHeaders($this->auth())->getJson('/api/discovery/jobs?per_page=3');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'total', 'per_page', 'current_page']);
        $this->assertCount(3, $response->json('data'));
    }

    // T-17: store rejects missing name
    public function test_store_rejects_missing_name(): void
    {
        $response = $this->withHeaders($this->auth())->postJson('/api/discovery/jobs', [
            'phone_number' => '+18005551234',
            'country_code' => 'US',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }
}
