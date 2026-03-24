<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use App\Models\ReportingJob;
use App\Models\JobRow;
use App\Models\ApiKey;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create an organization
        $org = Organization::factory()->create([
            'name' => 'CAREMAT Foundation',
            'hcode' => '41936',
            'verified' => true,
        ]);

        // Create an admin user
        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@caremat.org',
            'organization_id' => $org->id,
            'role' => 'admin',
        ]);

        // Create a regular user
        $user = User::factory()->create([
            'name' => 'Clinic Staff',
            'email' => 'staff@caremat.org',
            'organization_id' => $org->id,
            'role' => 'user',
        ]);

        // Create some sample jobs
        ReportingJob::factory()->count(5)->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
        ])->each(function ($job) {
            JobRow::factory()->count(10)->create([
                'reporting_job_id' => $job->id,
            ]);
        });

        // Add an API key
        ApiKey::factory()->create([
            'organization_id' => $org->id,
        ]);
    }
}
