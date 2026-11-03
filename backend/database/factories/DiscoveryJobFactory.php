<?php

namespace Database\Factories;

use App\Models\DiscoveryJob;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiscoveryJobFactory extends Factory
{
    protected $model = DiscoveryJob::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company().' IVR Discovery',
            'phone_number' => '+1800'.$this->faker->numerify('#######'),
            'country_code' => $this->faker->randomElement(['US', 'GB', 'CA']),
            'status' => 'pending',
            'menu_depth' => 0,
            'nodes_discovered' => 0,
            'languages' => ['en'],
            'started_at' => null,
            'completed_at' => null,
        ];
    }
}
