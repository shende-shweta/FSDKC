<?php

namespace Tests\Feature;

use App\Models\ConnectCheckResult;
use App\Models\ConnectMonitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LegacyReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_carrier_summary_returns_200_with_correct_shape(): void
    {
        $user = User::create([
            'name' => 'Test User', 'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->seedMonitorsWithChecks(5, 5);

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/legacy/reports/carriers');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => [['label', 'metric', 'region', 'monitor_id']], 'total']);
        $this->assertCount(5, $response->json('data'));
    }

    public function test_carrier_summary_executes_at_most_2_db_queries(): void
    {
        $user = User::create([
            'name' => 'Test User', 'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->seedMonitorsWithChecks(5, 5);

        DB::enableQueryLog();

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/legacy/reports/carriers');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Sanctum token lookup + monitors + eager-loaded check results = ≤ 3 queries
        // (Sanctum adds 1 auth query, then monitors + eager-load = 2)
        $this->assertLessThanOrEqual(3, count($queries), 'Expected ≤ 3 queries (1 auth + 2 data), got: '.count($queries));
    }

    private function seedMonitorsWithChecks(int $monitorCount, int $checksPerMonitor): void
    {
        for ($m = 1; $m <= $monitorCount; $m++) {
            $monitor = ConnectMonitor::create([
                'name' => "Monitor {$m}", 'toll_free_number' => "180055500{$m}",
                'country_code' => 'US', 'status' => 'active', 'reachability_pct' => 95.0,
            ]);

            for ($c = 0; $c < $checksPerMonitor; $c++) {
                ConnectCheckResult::create([
                    'connect_monitor_id' => $monitor->id,
                    'reachable' => true,
                    'checked_at' => now()->subMinutes($checksPerMonitor - $c),
                ]);
            }
        }
    }
}
