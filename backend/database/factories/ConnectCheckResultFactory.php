<?php

namespace Database\Factories;

use App\Models\ConnectCheckResult;
use App\Models\ConnectMonitor;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConnectCheckResultFactory extends Factory
{
    protected $model = ConnectCheckResult::class;

    public function definition(): array
    {
        $reachable = $this->faker->boolean(80);

        return [
            'connect_monitor_id' => ConnectMonitor::factory(),
            'reachable' => $reachable,
            'latency_ms' => $reachable ? $this->faker->numberBetween(100, 500) : null,
            'carrier_route' => 'US -> Verizon SIP',
            'failure_reason' => $reachable ? null : 'Carrier routing failure',
            'checked_at' => now(),
        ];
    }
}
