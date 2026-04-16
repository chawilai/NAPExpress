<?php

use App\Models\AutonapRequest;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('unauthenticated user redirected from dashboard', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('authenticated user can view dashboard overview', function () {
    $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Overview')
            ->has('summary')
            ->has('history.data')
            ->has('templates', 2)
        );
});

test('dashboard summary shows zero stats when no requests exist', function () {
    $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.total_jobs', 0)
            ->where('summary.total_records', 0)
            ->where('summary.success_rate', 0)
        );
});

test('dashboard summary aggregates existing requests', function () {
    AutonapRequest::create([
        'job_id' => 'test-1',
        'site' => 'test_site',
        'form_type' => 'RR',
        'fy' => '2026',
        'total' => 100,
        'success' => 95,
        'failed' => 5,
        'status' => 'completed',
    ]);
    AutonapRequest::create([
        'job_id' => 'test-2',
        'site' => 'test_site',
        'form_type' => 'VCT',
        'fy' => '2026',
        'total' => 50,
        'success' => 50,
        'failed' => 0,
        'status' => 'completed',
    ]);

    $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.total_jobs', 2)
            ->where('summary.total_records', 150)
            ->where('summary.total_success', 145)
            ->where('summary.total_failed', 5)
            ->where('summary.success_rate', 96.7)
        );
});

test('dashboard history filter by search query works', function () {
    AutonapRequest::create([
        'job_id' => 'abc-123',
        'site' => 'alpha_site',
        'form_type' => 'RR',
        'fy' => '2026',
        'total' => 10,
        'success' => 10,
        'failed' => 0,
        'status' => 'completed',
    ]);
    AutonapRequest::create([
        'job_id' => 'xyz-789',
        'site' => 'beta_site',
        'form_type' => 'VCT',
        'fy' => '2026',
        'total' => 20,
        'success' => 20,
        'failed' => 0,
        'status' => 'completed',
    ]);

    $this->actingAs($this->user)
        ->get('/dashboard?q=alpha')
        ->assertInertia(fn (Assert $page) => $page
            ->has('history.data', 1)
            ->where('history.data.0.site', 'alpha_site')
        );
});

test('dashboard history filter by form_type works', function () {
    AutonapRequest::create([
        'job_id' => 'rr-1',
        'site' => 's1',
        'form_type' => 'RR',
        'fy' => '2026',
        'total' => 10,
        'success' => 10,
        'failed' => 0,
        'status' => 'completed',
    ]);
    AutonapRequest::create([
        'job_id' => 'vct-1',
        'site' => 's1',
        'form_type' => 'VCT',
        'fy' => '2026',
        'total' => 10,
        'success' => 10,
        'failed' => 0,
        'status' => 'completed',
    ]);

    $this->actingAs($this->user)
        ->get('/dashboard?form_type=RR')
        ->assertInertia(fn (Assert $page) => $page
            ->has('history.data', 1)
            ->where('history.data.0.form_type', 'RR')
        );
});

test('dashboard can download template_rr.xlsx', function () {
    $this->actingAs($this->user)
        ->get('/dashboard/templates/template_rr.xlsx')
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

test('dashboard can download template_vct.xlsx', function () {
    $this->actingAs($this->user)
        ->get('/dashboard/templates/template_vct.xlsx')
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

test('dashboard can still download legacy CSV templates', function () {
    $this->actingAs($this->user)
        ->get('/dashboard/templates/template_rr.csv')
        ->assertOk();
});

test('dashboard rejects unknown template filename', function () {
    $this->actingAs($this->user)
        ->get('/dashboard/templates/malicious.xlsx')
        ->assertNotFound();
});

test('dashboard templates route is protected by auth', function () {
    $this->get('/dashboard/templates/template_rr.xlsx')
        ->assertRedirect('/login');
});

test('facets include sites and form_types from existing requests', function () {
    AutonapRequest::create([
        'job_id' => '1',
        'site' => 'site_a',
        'form_type' => 'RR',
        'fy' => '2026',
        'total' => 10,
        'success' => 10,
        'failed' => 0,
        'status' => 'completed',
    ]);
    AutonapRequest::create([
        'job_id' => '2',
        'site' => 'site_b',
        'form_type' => 'VCT',
        'fy' => '2026',
        'total' => 5,
        'success' => 5,
        'failed' => 0,
        'status' => 'completed',
    ]);

    $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->has('facets.sites', 2)
            ->has('facets.form_types', 2)
        );
});
