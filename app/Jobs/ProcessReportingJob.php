<?php

namespace App\Jobs;

use App\Models\ReportingJob;
use App\Notifications\JobCompletedNotification;
use App\Services\LabApiService;
use App\Services\NapDirectHttpService;
use App\Services\ReachRrMapper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class ProcessReportingJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct(public ReportingJob $reportingJob) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->reportingJob->update(['status' => 'processing']);

        try {
            match ($this->reportingJob->method) {
                'Playwright' => $this->runPlaywrightAutomation(),
                'DirectHTTP' => $this->runDirectHttpAutomation(),
                default => $this->runApiAutomation(),
            };

            $this->reportingJob->update(['status' => 'completed']);
        } catch (\Exception $e) {
            Log::error('Reporting Job Failed: '.$e->getMessage());
            $this->reportingJob->update(['status' => 'failed']);
        } finally {
            $this->cleanupCredentials();
            $this->reportingJob->user->notify(new JobCompletedNotification($this->reportingJob));
        }
    }

    /**
     * Prepare a temporary JSON file with job data for the Playwright script.
     */
    public function prepareJobDataFile(): string
    {
        $credentials = Cache::get("job:{$this->reportingJob->id}:credentials", [
            'username' => '',
            'password' => '',
        ]);

        $rows = $this->reportingJob->jobRows()
            ->where('status', 'pending')
            ->orderBy('row_number')
            ->get()
            ->map(function ($row) {
                $rowData = $row->row_data ?? [];

                // If row_data has rr_form (from CAREMAT API), use it directly
                // Otherwise fall back to ReachRrMapper for Excel-uploaded data
                if (isset($rowData['rr_form'])) {
                    return [
                        'id' => $row->id,
                        'row_number' => $row->row_number,
                        'row_data' => $rowData,
                    ];
                }

                return [
                    'id' => $row->id,
                    'row_number' => $row->row_number,
                    'row_data' => $rowData,
                    'form_data' => ReachRrMapper::buildFormData($rowData),
                ];
            })
            ->all();

        $data = [
            'job_id' => $this->reportingJob->id,
            'credentials' => $credentials,
            'rows' => array_values($rows),
        ];

        $filePath = storage_path("app/private/job_{$this->reportingJob->id}_data.json");
        file_put_contents($filePath, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $filePath;
    }

    protected function runPlaywrightAutomation(): void
    {
        $dataFile = $this->prepareJobDataFile();

        try {
            $process = new Process([
                'node',
                base_path('automation/report_reach_rr.cjs'),
                '--dataFile='.$dataFile,
            ]);
            $process->setTimeout($this->timeout);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new \Exception('Playwright process failed: '.$process->getErrorOutput());
            }

            $this->processPlaywrightResults($dataFile);
        } finally {
            if (file_exists($dataFile)) {
                unlink($dataFile);
            }
        }
    }

    protected function runDirectHttpAutomation(): void
    {
        $credentials = Cache::get("job:{$this->reportingJob->id}:credentials", [
            'username' => '',
            'password' => '',
        ]);

        $service = new NapDirectHttpService;
        $service->processJob($this->reportingJob, $credentials);
    }

    protected function runApiAutomation(): void
    {
        $service = new LabApiService;
        $service->processJob($this->reportingJob);
    }

    /**
     * Read Playwright results from the data file and update job rows.
     */
    protected function processPlaywrightResults(string $dataFile): void
    {
        $resultsFile = str_replace('_data.json', '_results.json', $dataFile);

        if (! file_exists($resultsFile)) {
            return;
        }

        $results = json_decode(file_get_contents($resultsFile), true);
        $success = 0;
        $failed = 0;

        foreach ($results['rows'] ?? [] as $result) {
            $row = $this->reportingJob->jobRows()->find($result['id']);

            if (! $row) {
                continue;
            }

            $status = $result['success'] ? 'success' : 'failed';
            $row->update([
                'status' => $status,
                'nap_response_code' => $result['nap_code'] ?? null,
                'error_message' => $result['error'] ?? null,
            ]);

            if ($result['success']) {
                $success++;
            } else {
                $failed++;
            }
        }

        $this->reportingJob->update([
            'counts' => [
                'total' => $this->reportingJob->counts['total'],
                'success' => $success,
                'failed' => $failed,
            ],
        ]);

        unlink($resultsFile);
    }

    /**
     * Remove cached credentials after job finishes.
     */
    protected function cleanupCredentials(): void
    {
        Cache::forget("job:{$this->reportingJob->id}:credentials");
    }
}
