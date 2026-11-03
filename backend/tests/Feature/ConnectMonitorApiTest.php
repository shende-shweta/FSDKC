<?php

namespace Tests\Feature;

use App\Models\ConnectCheckResult;
use App\Models\ConnectMonitor;
use Tests\TestCase;

class ConnectMonitorApiTest extends TestCase
{
    public function test_index_returns_monitors(): void
    {
        ConnectMonitor::factory()->count(3)->create();

        $response = $this->getJson('/api/connect/monitors');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_store_creates_monitor_with_valid_data(): void
    {
        $payload = [
            'name' => 'US Sales TFN',
            'toll_free_number' => '+18005551234',
            'country_code' => 'US',
            'carrier' => 'Verizon',
        ];

        $response = $this->postJson('/api/connect/monitors', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'US Sales TFN')
            ->assertJsonPath('data.status', 'active');
        $this->assertEquals(100.0, $response->json('data.reachability_pct'));

        $this->assertDatabaseHas('connect_monitors', ['name' => 'US Sales TFN', 'country_code' => 'US']);
    }

    public function test_store_rejects_missing_name(): void
    {
        $response = $this->postJson('/api/connect/monitors', [
            'toll_free_number' => '+18005551234',
            'country_code' => 'US',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_rejects_invalid_country_code(): void
    {
        $response = $this->postJson('/api/connect/monitors', [
            'name' => 'Test',
            'toll_free_number' => '+18005551234',
            'country_code' => 'USA',  // 3-letter — should fail
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['country_code']);
    }

    public function test_store_rejects_invalid_toll_free_number(): void
    {
        $response = $this->postJson('/api/connect/monitors', [
            'name' => 'Test',
            'toll_free_number' => 'not-a-number',
            'country_code' => 'US',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['toll_free_number']);
    }

    public function test_show_returns_monitor(): void
    {
        $monitor = ConnectMonitor::factory()->create();

        $response = $this->getJson("/api/connect/monitors/{$monitor->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $monitor->id);
    }

    public function test_show_returns_404_for_missing_monitor(): void
    {
        $this->getJson('/api/connect/monitors/99999')
            ->assertNotFound();
    }

    public function test_checks_returns_computed_reachability(): void
    {
        $monitor = ConnectMonitor::factory()->create();

        ConnectCheckResult::factory()->count(8)->create([
            'connect_monitor_id' => $monitor->id,
            'reachable' => true,
        ]);
        ConnectCheckResult::factory()->count(2)->create([
            'connect_monitor_id' => $monitor->id,
            'reachable' => false,
        ]);

        $response = $this->getJson("/api/connect/monitors/{$monitor->id}/checks");

        $response->assertOk()
            ->assertJsonPath('computed.status', 'alert');
        $this->assertEquals(80.0, $response->json('computed.reachability_pct'));
    }
}
