<?php

namespace App\Jobs;

use App\Mail\AutoNapJobReport;
use App\Models\AutonapRecord;
use App\Models\AutonapRequest;
use App\Services\AblyProgressService;
use App\Services\NapCallbackService;
use App\Services\NapDirectHttpService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Process\Process;

class ProcessAutoNapJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    /**
     * @param  array<string, string>  $credentials  NAP credentials (empty for ThaiID)
     * @param  array<int, array<string, mixed>>  $items  Each item must contain rr_form
     */
    public function __construct(
        public string $jobId,
        public string $site,
        public string $fy,
        public array $credentials,
        public string $callbackUrl,
        public ?string $ablyChannel,
        public array $items,
        public string $method = 'ThaiID',
        public bool $dryRun = false,
        public string $formType = 'RR',
        public string $staffName = '',
    ) {}

    public function handle(): void
    {
        match ($this->method) {
            'DirectHTTP' => $this->handleDirectHttpFlow(),
            default => $this->handleThaiIdFlow(),
        };
    }

    /**
     * ThaiID flow:
     * 1. Playwright (headed) → ThaiD login → QR scan
     * 2. Playwright (headless) → use cookies → fill forms via DOM → submit
     *
     * All handled by thaid_login_and_record.cjs — PHP reads results + sends callbacks.
     */
    protected function handleThaiIdFlow(): void
    {
        $startedAt = Carbon::now('Asia/Bangkok');
        $ablyKey = $this->getAblyKey();
        $total = count($this->items);

        // Prepare data file for Playwright script
        $dataFile = storage_path("app/private/thaid_{$this->jobId}.json");
        file_put_contents($dataFile, json_encode([
            'ablyKey' => $ablyKey,
            'ablyChannel' => $this->ablyChannel,
            'items' => $this->items,
            'dryRun' => $this->dryRun,
            'formType' => $this->formType,
            'callbackUrl' => $this->callbackUrl,
            'fy' => $this->fy,
            'staffName' => $this->staffName,
        ], JSON_UNESCAPED_UNICODE));

        $process = new Process([
            'node',
            base_path('automation/thaid_login_and_record.cjs'),
            '--jobId='.$this->jobId,
            '--dataFile='.$dataFile,
        ]);
        $process->setTimeout($this->timeout);

        Log::info("ThaiID: Starting {$this->formType} for job {$this->jobId}", [
            'total' => $total,
            'dryRun' => $this->dryRun,
            'formType' => $this->formType,
        ]);

        $process->run(function ($type, $buffer) {
            Log::info("ThaiID [{$type}]: {$buffer}");
        });

        // Read results from Playwright
        $resultsFile = str_replace('.json', '_results.json', $dataFile);

        if (! file_exists($resultsFile)) {
            Log::error("ThaiID: No results file — script failed for job {$this->jobId}");
            Cache::forget("autonap:{$this->site}:{$this->formType}");
            $this->cleanup($dataFile);

            return;
        }

        $resultsData = json_decode(file_get_contents($resultsFile), true);
        $results = $resultsData['results'] ?? [];
        $napDisplayName = $resultsData['napDisplayName'] ?? '';
        $napSiteName = $resultsData['napSiteName'] ?? '';

        // Send callbacks to CAREMAT for each result
        $success = 0;
        $failed = 0;

        foreach ($results as $index => $result) {
            $item = $this->items[$index] ?? [];
            $item['fy'] = $this->fy;

            $isSuccess = $result['success'] ?? false;
            $napCode = $result['nap_code'] ?? null;
            $napLabCode = $result['nap_lab_code'] ?? null;
            $error = $result['error'] ?? '';

            if ($isSuccess) {
                $success++;
            } else {
                $failed++;
            }

            // VCT sends callbacks directly from Playwright script (2-step: VCT then Lab)
            // RR still sends callback from PHP
            if ($this->formType !== 'VCT') {
                NapCallbackService::send(
                    NapCallbackService::buildPayload(
                        $item,
                        $napCode,
                        'success',
                        $error,
                        $napLabCode,
                        $this->formType,
                        $this->staffName ?: $napDisplayName,
                    ),
                    $this->callbackUrl,
                );
            }
        }

        $finishedAt = Carbon::now('Asia/Bangkok');

        // Check if this is just a login timeout (not a real failure)
        $isLoginTimeout = $success === 0 && ! empty($results)
            && str_contains($results[0]['error'] ?? '', 'Login failed');

        Log::info("AutoNAP {$this->formType} job completed: {$this->jobId}", compact('total', 'success', 'failed'));

        // Release site lock so next job can run
        Cache::forget("autonap:{$this->site}:{$this->formType}");

        if ($isLoginTimeout) {
            // Login timeout — skip database + email (not a real job)
            Log::info("AutoNAP: Login timeout — skipping report for {$this->jobId}");
            AutonapRequest::where('job_id', $this->jobId)->delete();
        } else {
            // Save to database
            $this->saveToDatabase($napDisplayName, $napSiteName, $startedAt, $finishedAt, $success, $failed, $results);

            // Send email report
            $this->sendReport([
                'napDisplayName' => $napDisplayName,
                'napSiteName' => $napSiteName,
                'startedAt' => $startedAt,
                'finishedAt' => $finishedAt,
                'total' => $total,
                'success' => $success,
                'failed' => $failed,
                'results' => $results,
            ]);
        }

        $this->cleanup($dataFile, $resultsFile);
    }

    /**
     * DirectHTTP flow: PHP handles login (username/password) + form POST.
     */
    protected function handleDirectHttpFlow(): void
    {
        $progress = $this->createProgress();
        $napService = new NapDirectHttpService;

        $napService->processJob(
            job: null,
            credentials: $this->credentials,
            callbackMode: 'realtime',
            progress: $progress,
            items: $this->items,
            callbackUrl: $this->callbackUrl,
        );
    }

    protected function getAblyKey(): string
    {
        $key = config('services.ably.key', '');

        if (empty($key)) {
            try {
                $key = \DB::connection('carematdb')->table('site_specific')->value('ably_key') ?? '';
            } catch (\Exception $e) {
                Log::warning('Could not get Ably key: '.$e->getMessage());
            }
        }

        return $key;
    }

    /**
     * Save job results to database.
     *
     * @param  array<int, array<string, mixed>>  $results
     */
    protected function saveToDatabase(
        string $napDisplayName,
        string $napSiteName,
        Carbon $startedAt,
        Carbon $finishedAt,
        int $success,
        int $failed,
        array $results,
    ): void {
        try {
            $request = AutonapRequest::where('job_id', $this->jobId)->first();

            if (! $request) {
                return;
            }

            $request->update([
                'nap_user' => $napDisplayName ?: null,
                'nap_site' => $napSiteName ?: null,
                'success' => $success,
                'failed' => $failed,
                'status' => $failed === count($results) ? 'failed' : 'completed',
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
            ]);

            foreach ($results as $i => $result) {
                $item = $this->items[$i] ?? [];

                AutonapRecord::create([
                    'request_id' => $request->id,
                    'seq' => $i + 1,
                    'uic' => $result['uic'] ?? $item['uic'] ?? null,
                    'pid_masked' => isset($result['id_card']) ? 'xxxx'.substr($result['id_card'], -4) : null,
                    'form_type' => $this->formType,
                    'success' => $result['success'] ?? false,
                    'nap_code' => $result['nap_code'] ?? null,
                    'nap_lab_code' => $result['nap_lab_code'] ?? null,
                    'hiv_result' => $result['hiv_result'] ?? null,
                    'comment' => $result['error'] ?? null,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning("Failed to save job history: {$e->getMessage()}");
        }
    }

    protected function createProgress(): ?AblyProgressService
    {
        if (! $this->ablyChannel) {
            return null;
        }

        $ablyKey = $this->getAblyKey();

        return ! empty($ablyKey) ? new AblyProgressService($ablyKey, $this->ablyChannel) : null;
    }

    /**
     * Send email report after job completion.
     *
     * @param  array<string, mixed>  $data
     */
    protected function sendReport(array $data): void
    {
        $to = config('mail.to.address');

        if (empty($to)) {
            return;
        }

        try {
            $durationSeconds = $data['startedAt']->diffInSeconds($data['finishedAt']);

            $report = [
                'jobId' => $this->jobId,
                'site' => $this->site,
                'formType' => $this->formType,
                'napDisplayName' => $data['napDisplayName'],
                'napSiteName' => $data['napSiteName'] ?? '',
                'startedAt' => $data['startedAt']->format('d/m/Y H:i:s'),
                'finishedAt' => $data['finishedAt']->format('d/m/Y H:i:s'),
                'durationSeconds' => $durationSeconds,
                'avgSecondsPerRecord' => $data['total'] > 0 ? round($durationSeconds / $data['total'], 1) : 0,
                'total' => $data['total'],
                'success' => $data['success'],
                'failed' => $data['failed'],
                'results' => $data['results'],
            ];

            Mail::to($to)->send(new AutoNapJobReport($report));

            Log::info("AutoNAP report email sent to {$to}");
        } catch (\Exception $e) {
            Log::warning("AutoNAP report email failed: {$e->getMessage()}");
        }
    }

    /**
     * Handle job failure — release cache lock and notify via Ably.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error("AutoNAP {$this->formType} job failed: {$this->jobId}", [
            'error' => $exception?->getMessage(),
        ]);

        AutonapRequest::where('job_id', $this->jobId)->update([
            'status' => 'failed',
            'finished_at' => now('Asia/Bangkok'),
        ]);

        Cache::forget("autonap:{$this->site}:{$this->formType}");

        $progress = $this->createProgress();
        $progress?->publish('job:failed', [
            'jobId' => $this->jobId,
            'error' => $exception?->getMessage() ?? 'Unknown error',
            'message' => "❌ Job ล้มเหลว: {$this->jobId}",
        ]);
    }

    protected function cleanup(string ...$files): void
    {
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}
