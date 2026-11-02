<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_200_with_ok_status(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'ok');
    }

    public function test_health_endpoint_is_publicly_accessible_without_auth(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);
    }
}
