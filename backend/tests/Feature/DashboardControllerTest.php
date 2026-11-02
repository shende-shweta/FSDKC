<?php

namespace Tests\Feature;

use App\Models\ConnectCheckResult;
use App\Models\ConnectMonitor;
use App\Models\DiscoveryJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// AC: T-25, T-26 — live KPI computation, no hardcoded 94.2/97.8
class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('pass1234')]);
        $this->token = $user->createToken('test')->plainTextToken;
    }

    // T-25: kpis endpoint returns expected JSON shape
    public function test_kpis_returns_correct_shape(): void
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/dashboard/kpis');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'availability' => ['ivr_availability_pct', 'number_reachability_pct', 'call_success_rate_pct', 'transfer_success_rate_pct'],
            'operational' => ['active_discovery_jobs', 'active_connect_monitors', 'open_alerts', 'countries_monitored'],
            'modules',
        ]);
    }

    // T-25: hardcoded 94.2 / 97.8 must not appear in live KPI response
    public function test_kpis_does_not_return_hardcoded_values(): void
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/dashboard/kpis');

        $availability = $response->json('availability');
        $this->assertNotEquals(94.2, $availability['call_success_rate_pct']);
        $this->assertNotEquals(97.8, $availability['transfer_success_rate_pct']);
    }

    // T-25: ivr_availability_pct is computed from real jobs
    public function test_ivr_availability_pct_computed_from_real_jobs(): void
    {
        for ($i = 0; $i < 8; $i++) {
            DiscoveryJob::create(['name' => "Job {$i}", 'phone_number' => "+1800555000{$i}", 'country_code' => 'US', 'status' => 'completed', 'languages' => ['en']]);
        }
        for ($i = 0; $i < 2; $i++) {
            DiscoveryJob::create(['name' => "Failed {$i}", 'phone_number' => "+1800555001{$i}", 'country_code' => 'US', 'status' => 'failed', 'languages' => ['en']]);
        }

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/dashboard/kpis');

        $this->assertSame(80.0, $response->json('availability.ivr_availability_pct'));
    }

    // T-25: operational counts reflect live database state
    public function test_operational_counts_reflect_database_state(): void
    {
        DiscoveryJob::create(['name' => 'Running', 'phone_number' => '+18005551234', 'country_code' => 'US', 'status' => 'running', 'languages' => ['en']]);
        ConnectMonitor::create(['name' => 'Active', 'toll_free_number' => '18005551234', 'country_code' => 'US', 'status' => 'active', 'reachability_pct' => 100]);
        ConnectMonitor::create(['name' => 'Alert', 'toll_free_number' => '18005550001', 'country_code' => 'GB', 'status' => 'alert', 'reachability_pct' => 50]);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/dashboard/kpis');

        $op = $response->json('operational');
        $this->assertSame(1, $op['active_discovery_jobs']);
        $this->assertSame(1, $op['active_connect_monitors']);
        $this->assertSame(1, $op['open_alerts']);
        $this->assertSame(2, $op['countries_monitored']);
    }

    // T-03: kpis requires authentication
    public function test_kpis_requires_authentication(): void
    {
        $this->getJson('/api/dashboard/kpis')->assertStatus(401);
    }
}
