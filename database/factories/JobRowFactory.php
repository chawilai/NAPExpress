<?php

namespace Database\Factories;

use App\Models\ReportingJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JobRow>
 */
class JobRowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reporting_job_id' => ReportingJob::factory(),
            'row_number' => $this->faker->numberBetween(1, 100),
            'pid_masked' => 'xxxx' . $this->faker->numerify('####'),
            'nap_response_code' => 'RR-' . $this->faker->numerify('##-######'),
            'error_message' => null,
        ];
    }
}
