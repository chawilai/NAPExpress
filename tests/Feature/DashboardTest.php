<?php

use App\Models\Organization;
use App\Models\ReportingJob;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

// Note: legacy reporting jobs dashboard moved from /dashboard to /jobs
// /dashboard now renders the AutoNAP Overview (see DashboardOverviewTest.php)

test('jobs index displays jobs from the user organization', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id]);

    ReportingJob::factory()->count(3)->create([
        'organization_id' => $org->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get('/jobs')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('jobs.data', 3)
        );
});

test('jobs index does not display jobs from other organizations', function () {
    $org1 = Organization::factory()->create();
    $org2 = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org1->id]);

    ReportingJob::factory()->count(2)->create([
        'organization_id' => $org2->id,
    ]);

    $this->actingAs($user)
        ->get('/jobs')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('jobs.data', 0)
        );
});
