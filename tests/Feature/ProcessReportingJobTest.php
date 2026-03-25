<?php

use App\Jobs\ProcessReportingJob;
use App\Models\JobRow;
use App\Models\Organization;
use App\Models\ReportingJob;
use App\Models\User;
use App\Notifications\JobCompletedNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

it('writes job data file with credentials and row data', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id]);

    $job = ReportingJob::factory()->create([
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'form_type' => 'Reach RR',
        'method' => 'Playwright',
        'status' => 'pending',
        'counts' => ['total' => 1, 'success' => 0, 'failed' => 0],
    ]);

    JobRow::factory()->create([
        'reporting_job_id' => $job->id,
        'row_number' => 2,
        'pid_masked' => 'xxxx7890123',
        'row_data' => [
            'pid' => '1234567890123',
            'uic' => 'TESTUSER020785',
            'kp' => 'MSM',
            'service_date' => '2025-07-02',
            'occupation' => 'รับจ้าง',
            'access_type' => 2,
            'condom_49' => 10,
            'condom_52' => 0,
            'condom_53' => 0,
            'condom_54' => 0,
            'condom_56' => 0,
            'female_condom' => 0,
            'lubricant' => 5,
            'next_hcode' => '41936',
            'hiv_forward' => 1,
            'sti_forward' => 3,
            'tb_forward' => 3,
        ],
        'status' => 'pending',
    ]);

    Cache::put("job:{$job->id}:credentials", [
        'username' => 'napuser',
        'password' => 'nappass',
    ], now()->addHours(2));

    $queueJob = new ProcessReportingJob($job);
    $dataFile = $queueJob->prepareJobDataFile();

    expect($dataFile)->toBeString()
        ->and(file_exists($dataFile))->toBeTrue();

    $data = json_decode(file_get_contents($dataFile), true);

    expect($data)
        ->toHaveKey('credentials')
        ->toHaveKey('rows')
        ->and($data['credentials']['username'])->toBe('napuser')
        ->and($data['credentials']['password'])->toBe('nappass')
        ->and($data['rows'])->toHaveCount(1)
        ->and($data['rows'][0]['row_data']['pid'])->toBe('1234567890123');

    unlink($dataFile);
});

it('cleans up credentials from cache after job completes', function () {
    Notification::fake();

    $org = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id]);

    $job = ReportingJob::factory()->create([
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'form_type' => 'Reach RR',
        'method' => 'API',
        'status' => 'pending',
        'counts' => ['total' => 1, 'success' => 0, 'failed' => 0],
    ]);

    JobRow::factory()->create([
        'reporting_job_id' => $job->id,
        'row_number' => 2,
        'pid_masked' => 'xxxx7890123',
        'row_data' => ['pid' => '1234567890123'],
        'status' => 'pending',
    ]);

    Cache::put("job:{$job->id}:credentials", [
        'username' => 'napuser',
        'password' => 'nappass',
    ], now()->addHours(2));

    $queueJob = new ProcessReportingJob($job);
    $queueJob->handle();

    expect(Cache::has("job:{$job->id}:credentials"))->toBeFalse();
});

it('sends notification when job completes', function () {
    Notification::fake();

    $org = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id]);

    $job = ReportingJob::factory()->create([
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'form_type' => 'Reach RR',
        'method' => 'API',
        'status' => 'pending',
        'counts' => ['total' => 1, 'success' => 0, 'failed' => 0],
    ]);

    JobRow::factory()->create([
        'reporting_job_id' => $job->id,
        'row_number' => 2,
        'pid_masked' => 'xxxx7890123',
        'row_data' => ['pid' => '1234567890123'],
        'status' => 'pending',
    ]);

    $queueJob = new ProcessReportingJob($job);
    $queueJob->handle();

    Notification::assertSentTo($user, JobCompletedNotification::class);
});

it('updates job status to completed after processing', function () {
    Notification::fake();

    $org = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id]);

    $job = ReportingJob::factory()->create([
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'form_type' => 'Reach RR',
        'method' => 'API',
        'status' => 'pending',
        'counts' => ['total' => 1, 'success' => 0, 'failed' => 0],
    ]);

    JobRow::factory()->create([
        'reporting_job_id' => $job->id,
        'row_number' => 2,
        'pid_masked' => 'xxxx7890123',
        'row_data' => ['pid' => '1234567890123'],
        'status' => 'pending',
    ]);

    $queueJob = new ProcessReportingJob($job);
    $queueJob->handle();

    $job->refresh();

    expect($job->status)->toBe('completed');
});
