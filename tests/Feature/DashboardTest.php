<?php

use App\Models\User;
use App\Models\Organization;
use App\Models\ReportingJob;
use Inertia\Testing\AssertableInertia as Assert;

test('dashboard displays jobs from the user organization', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id]);
    
    ReportingJob::factory()->count(3)->create([
        'organization_id' => $org->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('jobs.data', 3)
        );
});

test('dashboard does not display jobs from other organizations', function () {
    $org1 = Organization::factory()->create();
    $org2 = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org1->id]);
    
    ReportingJob::factory()->count(2)->create([
        'organization_id' => $org2->id,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('jobs.data', 0)
        );
});