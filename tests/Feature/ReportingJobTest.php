<?php

use App\Jobs\ProcessReportingJob;
use App\Models\Organization;
use App\Models\ReportingJob;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;

test('authenticated users can create a reporting job with file', function () {
    Queue::fake();

    $org = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id, 'email_verified_at' => now()]);

    $content = implode("\n", [
        'pid,uic,kp,service_date,occupation,access_type,condom_49,condom_52,condom_53,condom_54,condom_56,female_condom,lubricant,next_hcode,hiv_forward,sti_forward,tb_forward',
        '1234567890123,TESTUSER020785,MSM,2025-07-02,รับจ้าง,2,10,0,0,0,0,0,5,41936,1,3,3',
        '2345678901234,TESTUSER150371,FSW,2025-07-03,นักเรียน,1,0,5,0,0,0,0,10,41936,2,3,3',
    ]);
    $file = UploadedFile::fake()->createWithContent('test.csv', $content);

    $this->actingAs($user)
        ->from('/dashboard')
        ->post(route('jobs.store'), [
            'form_type' => 'Reach RR',
            'method' => 'Playwright',
            'file' => $file,
            'nap_username' => 'testuser',
            'nap_password' => 'testpass',
        ])
        ->assertRedirect('/dashboard');

    $this->assertDatabaseHas('reporting_jobs', [
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'form_type' => 'Reach RR',
    ]);

    $job = ReportingJob::first();

    expect($job->counts['total'])->toBe(2);
    $this->assertDatabaseCount('job_rows', 2);

    Queue::assertPushed(ProcessReportingJob::class);
});
