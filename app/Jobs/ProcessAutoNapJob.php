<?php

namespace App\Jobs;

use App\Services\AblyProgressService;
use App\Services\NapCallbackService;
use App\Services\NapDirectHttpService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class ProcessAutoNapJob implements ShouldQueue
{
    use Queueable;

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
        $ablyKey = $this->getAblyKey();
        $total = count($this->items);

        // Prepare data file for Playwright script
        $dataFile = storage_path("app/private/thaid_{$this->jobId}.json");
        file_put_contents($dataFile, json_encode([
            'ablyKey' => $ablyKey,
            'ablyChannel' => $this->ablyChannel,
            'items' => $this->items,
            'dryRun' => $this->dryRun,
        ], JSON_UNESCAPED_UNICODE));

        $process = new Process([
            'node',
            base_path('automation/thaid_login_and_record.cjs'),
            '--jobId='.$this->jobId,
            '--dataFile='.$dataFile,
        ]);
        $process->setTimeout($this->timeout);

        Log::info("ThaiID: Starting login+record for job {$this->jobId}", [
            'total' => $total,
            'dryRun' => $this->dryRun,
        ]);

        $process->run(function ($type, $buffer) {
            Log::info("ThaiID [{$type}]: {$buffer}");
        });

        // Read results from Playwright
        $resultsFile = str_replace('.json', '_results.json', $dataFile);

        if (! file_exists($resultsFile)) {
            Log::error("ThaiID: No results file — script failed for job {$this->jobId}");
            $this->cleanup($dataFile);

            return;
        }

        $resultsData = json_decode(file_get_contents($resultsFile), true);
        $results = $resultsData['results'] ?? [];

        // Send callbacks to CAREMAT for each result
        $success = 0;
        $failed = 0;

        foreach ($results as $index => $result) {
            $item = $this->items[$index] ?? [];
            $item['fy'] = $this->fy;

            $isSuccess = $result['success'] ?? false;
            $napCode = $result['nap_code'] ?? null;
            $error = $result['error'] ?? '';

            if ($isSuccess) {
                $success++;
            } else {
                $failed++;
            }

            // Send callback per record
            NapCallbackService::send(
                NapCallbackService::buildPayload(
                    $item,
                    $napCode,
                    $isSuccess ? 'success' : 'error',
                    $error,
                ),
                $this->callbackUrl,
            );
        }

        Log::info("AutoNAP job completed: {$this->jobId}", compact('total', 'success', 'failed'));

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

    protected function createProgress(): ?AblyProgressService
    {
        if (! $this->ablyChannel) {
            return null;
        }

        $ablyKey = $this->getAblyKey();

        return ! empty($ablyKey) ? new AblyProgressService($ablyKey, $this->ablyChannel) : null;
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
