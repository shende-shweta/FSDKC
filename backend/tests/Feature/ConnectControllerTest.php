<?php

namespace Tests\Feature;

use App\Models\ConnectCheckResult;
use App\Models\ConnectMonitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// AC: T-16, T-17, T-23, T-24, T-28, T-42 — ConnectRequest validation, reachability wiring, pagination, no double-query
class ConnectControllerTest extends TestCase
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

    // T-16: store rejects invalid toll-free number format
    public function test_store_rejects_invalid_toll_free_number(): void
    {
        $response = $this->withHeaders($this->auth())->postJson('/api/connect/monitors', [
            'name' => 'Bad TFN',
            'toll_free_number' => 'abc',
            'country_code' => 'US',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('toll_free_number');
    }

    // T-16: store rejects unsupported country_code
    public function test_store_rejects_invalid_country_code(): void
    {
        $response = $this->withHeaders($this->auth())->postJson('/api/connect/monitors', [
            'name' => 'Bad Country',
            'toll_free_number' => '18005551234',
            'country_code' => 'ZZ',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('country_code');
    }

    // T-17: store creates monitor with valid payload
    public function test_store_creates_monitor_with_valid_payload(): void
    {
        $response = $this->withHeaders($this->auth())->postJson('/api/connect/monitors', [
            'name' => 'US Sales TFN',
            'toll_free_number' => '18005551234',
            'country_code' => 'US',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'US Sales TFN');
        $this->assertDatabaseHas('connect_monitors', ['name' => 'US Sales TFN']);
    }

    // T-03: unauthenticated request returns 401
    public function test_store_requires_auth(): void
    {
        $this->postJson('/api/connect/monitors', [
            'name' => 'No Auth',
            'toll_free_number' => '18005551234',
            'country_code' => 'US',
        ])->assertStatus(401);
    }

    // T-24/T-42: checks endpoint returns computed reachability using ConnectService
    public function test_checks_returns_computed_reachability(): void
    {
        $monitor = ConnectMonitor::create([
            'name' => 'TFN Monitor', 'toll_free_number' => '18005551234',
            'country_code' => 'US', 'status' => 'active', 'reachability_pct' => 100,
        ]);
        for ($i = 0; $i < 10; $i++) {
            ConnectCheckResult::create([
                'connect_monitor_id' => $monitor->id, 'reachable' => true,
                'checked_at' => now()->subMinutes(10 - $i),
            ]);
        }

        $response = $this->withHeaders($this->auth())->getJson("/api/connect/monitors/{$monitor->id}/checks");

        $response->assertStatus(200);
        $response->assertJsonPath('computed.reachability_pct', 100.0);
        $response->assertJsonPath('computed.status', 'active');
    }

    // T-42: checks endpoint performs single reachability query (no double-query)
    public function test_checks_endpoint_does_not_double_query_reachability(): void
    {
        $monitor = ConnectMonitor::create([
            'name' => 'TFN Monitor', 'toll_free_number' => '18005551234',
            'country_code' => 'US', 'status' => 'active', 'reachability_pct' => 100,
        ]);

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->withHeaders($this->auth())->getJson("/api/connect/monitors/{$monitor->id}/checks");
        $queries = \Illuminate\Support\Facades\DB::getQueryLog();
        \Illuminate\Support\Facades\DB::disableQueryLog();

        // 1 auth + 1 monitor lookup + 1 checks + 1 reachability = ≤ 4
        $this->assertLessThanOrEqual(4, count($queries), 'Expected ≤ 4 queries, got: '.count($queries));
    }

    // T-28: index returns paginated response
    public function test_index_returns_paginated_response(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            ConnectMonitor::create([
                'name' => "Monitor {$i}", 'toll_free_number' => "1800555000{$i}",
                'country_code' => 'US', 'status' => 'active', 'reachability_pct' => 100,
            ]);
        }

        $response = $this->withHeaders($this->auth())->getJson('/api/connect/monitors?per_page=2');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'total', 'per_page', 'current_page']);
        $this->assertCount(2, $response->json('data'));
    }

    // T-16: store rejects missing name field
    public function test_store_rejects_missing_name(): void
    {
        $response = $this->withHeaders($this->auth())->postJson('/api/connect/monitors', [
            'toll_free_number' => '18005551234',
            'country_code' => 'US',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }
}
