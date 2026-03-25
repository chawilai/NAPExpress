<?php

use App\Jobs\ProcessReportingJob;
use App\Models\Organization;
use App\Models\ReportingJob;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;

function reachRrCsvContent(): string
{
    return implode("\n", [
        'pid,uic,kp,service_date,occupation,access_type,condom_49,condom_52,condom_53,condom_54,condom_56,female_condom,lubricant,next_hcode,hiv_forward,sti_forward,tb_forward',
        '1234567890123,TESTUSER020785,MSM,2025-07-02,รับจ้าง,2,10,0,0,0,0,0,5,41936,1,3,3',
        '2345678901234,TESTUSER150371,FSW,2025-07-03,นักเรียน,1,0,5,0,0,0,0,10,41936,2,3,3',
    ]);
}

it('creates a reach rr job with validated row data', function () {
    Queue::fake();

    $org = Organization::factory()->create();
    $user = User::factory()->create([
        'organization_id' => $org->id,
        'email_verified_at' => now(),
    ]);

    $file = UploadedFile::fake()->createWithContent('reach_rr.csv', reachRrCsvContent());

    $this->actingAs($user)
        ->post(route('jobs.store'), [
            'form_type' => 'Reach RR',
            'method' => 'Playwright',
            'file' => $file,
            'nap_username' => 'testuser',
            'nap_password' => 'testpass',
        ])
        ->assertRedirect('/dashboard');

    $job = ReportingJob::first();

    expect($job)
        ->form_type->toBe('Reach RR')
        ->method->toBe('Playwright')
        ->status->toBe('pending')
        ->and($job->counts['total'])->toBe(2);

    $this->assertDatabaseCount('job_rows', 2);

    $firstRow = $job->jobRows()->where('row_number', 2)->first();

    expect($firstRow)
        ->pid_masked->toBe('xxxx7890123')
        ->status->toBe('pending')
        ->and($firstRow->row_data)->toBeArray()
        ->and($firstRow->row_data['pid'])->toBe('1234567890123')
        ->and($firstRow->row_data['kp'])->toBe('MSM');

    Queue::assertPushed(ProcessReportingJob::class);
});

it('stores nap credentials in cache not database', function () {
    Queue::fake();

    $org = Organization::factory()->create();
    $user = User::factory()->create([
        'organization_id' => $org->id,
        'email_verified_at' => now(),
    ]);

    $file = UploadedFile::fake()->createWithContent('reach_rr.csv', reachRrCsvContent());

    $this->actingAs($user)
        ->post(route('jobs.store'), [
            'form_type' => 'Reach RR',
            'method' => 'Playwright',
            'file' => $file,
            'nap_username' => 'napuser123',
            'nap_password' => 'napsecret',
        ]);

    $job = ReportingJob::first();

    // Credentials should NOT be stored in the job record
    expect($job->toArray())->not->toHaveKey('nap_username')
        ->and($job->toArray())->not->toHaveKey('nap_password');

    // Credentials should be in cache for the job
    $cached = cache()->get("job:{$job->id}:credentials");

    expect($cached)->toBeArray()
        ->and($cached['username'])->toBe('napuser123')
        ->and($cached['password'])->toBe('napsecret');
});

it('requires nap credentials for playwright method', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create([
        'organization_id' => $org->id,
        'email_verified_at' => now(),
    ]);

    $file = UploadedFile::fake()->createWithContent('reach_rr.csv', reachRrCsvContent());

    $this->actingAs($user)
        ->post(route('jobs.store'), [
            'form_type' => 'Reach RR',
            'method' => 'Playwright',
            'file' => $file,
            // Missing nap_username and nap_password
        ])
        ->assertSessionHasErrors(['nap_username', 'nap_password']);
});

it('rejects invalid reach rr rows and returns validation errors', function () {
    Queue::fake();

    $org = Organization::factory()->create();
    $user = User::factory()->create([
        'organization_id' => $org->id,
        'email_verified_at' => now(),
    ]);

    $badCsv = implode("\n", [
        'pid,uic,kp,service_date,occupation,access_type,condom_49,condom_52,condom_53,condom_54,condom_56,female_condom,lubricant,next_hcode,hiv_forward,sti_forward,tb_forward',
        '12345,SHORT,INVALID,not-a-date,รับจ้าง,2,10,0,0,0,0,0,5,41936,1,3,3',
    ]);
    $file = UploadedFile::fake()->createWithContent('bad.csv', $badCsv);

    $this->actingAs($user)
        ->post(route('jobs.store'), [
            'form_type' => 'Reach RR',
            'method' => 'Playwright',
            'file' => $file,
            'nap_username' => 'testuser',
            'nap_password' => 'testpass',
        ])
        ->assertSessionHasErrors('validation_errors');

    Queue::assertNothingPushed();
    $this->assertDatabaseCount('reporting_jobs', 0);
});

it('masks pid to show only last 7 digits', function () {
    Queue::fake();

    $org = Organization::factory()->create();
    $user = User::factory()->create([
        'organization_id' => $org->id,
        'email_verified_at' => now(),
    ]);

    $file = UploadedFile::fake()->createWithContent('reach_rr.csv', reachRrCsvContent());

    $this->actingAs($user)
        ->post(route('jobs.store'), [
            'form_type' => 'Reach RR',
            'method' => 'Playwright',
            'file' => $file,
            'nap_username' => 'testuser',
            'nap_password' => 'testpass',
        ]);

    $row = ReportingJob::first()->jobRows()->first();

    // PID 1234567890123 → xxxx7890123 (show last 7, mask first 6 with xxxx)
    expect($row->pid_masked)->toStartWith('xxxx');
    expect($row->pid_masked)->not->toContain('123456');
});

it('guests cannot create jobs', function () {
    $file = UploadedFile::fake()->createWithContent('reach_rr.csv', reachRrCsvContent());

    $this->post(route('jobs.store'), [
        'form_type' => 'Reach RR',
        'method' => 'Playwright',
        'file' => $file,
        'nap_username' => 'testuser',
        'nap_password' => 'testpass',
    ])->assertRedirect('/login');
});
