<?php

namespace Database\Factories;

use App\Models\ConnectMonitor;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConnectMonitorFactory extends Factory
{
    protected $model = ConnectMonitor::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company().' TFN',
            'toll_free_number' => '+1800'.$this->faker->numerify('#######'),
            'country_code' => $this->faker->randomElement(['US', 'GB', 'CA', 'AU']),
            'carrier' => $this->faker->randomElement(['Verizon', 'AT&T', 'BT', null]),
            'status' => 'active',
            'reachability_pct' => 100.0,
            'last_checked_at' => null,
        ];
    }
}
