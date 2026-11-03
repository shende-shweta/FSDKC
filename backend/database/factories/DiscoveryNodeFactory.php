<?php

namespace Database\Factories;

use App\Models\DiscoveryJob;
use App\Models\DiscoveryNode;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiscoveryNodeFactory extends Factory
{
    protected $model = DiscoveryNode::class;

    public function definition(): array
    {
        return [
            'discovery_job_id' => DiscoveryJob::factory(),
            'parent_id' => null,
            'prompt_text' => $this->faker->sentence(),
            'dtmf_option' => null,
            'node_type' => 'menu',
            'depth' => 0,
        ];
    }
}
