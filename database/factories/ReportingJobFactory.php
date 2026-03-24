<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReportingJob>
 */
class ReportingJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'form_type' => $this->faker->randomElement(['Reach RR', 'Lab CD4/VL', 'VCT', 'PrEP']),
            'method' => $this->faker->randomElement(['Playwright', 'API']),
            'status' => 'completed',
            'counts' => [
                'total' => 100,
                'success' => 95,
                'failed' => 5,
            ],
            'ably_channel' => 'job-' . $this->faker->uuid(),
        ];
    }
}
