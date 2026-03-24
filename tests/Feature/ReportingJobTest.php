<?php

use App\Models\User;
use App\Models\Organization;
use App\Models\ReportingJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

test('authenticated users can create a reporting job with file', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id, 'email_verified_at' => now()]);

    // Create a fake excel file
    $content = "PID,Name,Date,Result,Remarks\n1234567890123,John Doe,2026-01-01,Positive,None\n2234567890123,Jane Doe,2026-01-02,Negative,None";
    $file = UploadedFile::fake()->createWithContent('test.csv', $content);

    $this->actingAs($user)
        ->from('/dashboard')
        ->post(route('jobs.store'), [
            'form_type' => 'Reach RR',
            'method' => 'Playwright',
            'file' => $file,
        ])
        ->assertRedirect('/dashboard');

    $this->assertDatabaseHas('reporting_jobs', [
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'form_type' => 'Reach RR',
        // 'status' => 'processing', // Controller sets it to processing
    ]);

    $job = ReportingJob::first();
    $this->assertEquals(2, $job->counts['total']);
    $this->assertDatabaseCount('job_rows', 2);
});
