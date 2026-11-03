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

    public function test_run_check_returns_session_id_for_valid_monitor(): void
    {
        // AC-04: throttle route is wired; async job is queued, not blocked
        $monitor = ConnectMonitor::factory()->create();

        $response = $this->postJson("/api/connect/monitors/{$monitor->id}/run-check");

        $response->assertOk()
            ->assertJsonStructure(['session_id', 'message']);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $response->json('session_id')
        );
    }

    public function test_run_check_returns_404_for_missing_monitor(): void
    {
        $this->postJson('/api/connect/monitors/99999/run-check')
            ->assertNotFound();
    }

    public function test_checks_response_includes_monitor_identity_fields(): void
    {
        $monitor = ConnectMonitor::factory()->create([
            'name' => 'Identity Check TFN',
            'toll_free_number' => '+18005550000',
            'country_code' => 'US',
        ]);

        $this->getJson("/api/connect/monitors/{$monitor->id}/checks")
            ->assertOk()
            ->assertJsonStructure(['monitor' => ['id', 'name', 'toll_free_number', 'country_code']]);
    }

    public function test_store_accepts_optional_carrier_as_null(): void
    {
        $response = $this->postJson('/api/connect/monitors', [
            'name' => 'No Carrier TFN',
            'toll_free_number' => '+18005551111',
            'country_code' => 'US',
        ]);

        $response->assertCreated();
        $this->assertNull($response->json('data.carrier'));
    }
}
