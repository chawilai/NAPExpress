<?php

namespace App\Jobs;

use App\Models\ReportingJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class ProcessReportingJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public ReportingJob $reportingJob)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->reportingJob->update(['status' => 'processing']);

        try {
            if ($this->reportingJob->method === 'Playwright') {
                $this->runPlaywrightAutomation();
            } else {
                $this->runApiAutomation();
            }

            $this->reportingJob->update(['status' => 'completed']);
            $this->reportingJob->user->notify(new \App\Notifications\JobCompletedNotification($this->reportingJob));
        } catch (\Exception $e) {
            Log::error('Reporting Job Failed: ' . $e->getMessage());
            $this->reportingJob->update(['status' => 'failed']);
            $this->reportingJob->user->notify(new \App\Notifications\JobCompletedNotification($this->reportingJob));
        }
    }

    protected function runPlaywrightAutomation(): void
    {
        // Example of calling node script
        // We pass the job ID so the script can fetch data and update status
        $process = new Process(['node', base_path('automation/report_reach_rr.js'), '--jobId=' . $this->reportingJob->id]);
        $process->setTimeout(3600); // 1 hour timeout
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \Exception('Playwright process failed: ' . $process->getErrorOutput());
        }
    }

    protected function runApiAutomation(): void
    {
        $service = new \App\Services\LabApiService();
        $service->processJob($this->reportingJob);
    }
}
