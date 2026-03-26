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
     * @param  array<string, string>  $credentials  NAP credentials (may be empty for ThaiID flow)
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
        public string $method = 'DirectHTTP',
    ) {}

    public function handle(): void
    {
        if ($this->method === 'ThaiID') {
            $this->handleThaiIdFlow();
        } else {
            $this->handleDirectHttpFlow();
        }
    }

    /**
     * ThaiID flow: Playwright handles login (QR scan) + form filling.
     * All events published via Node.js Ably client.
     */
    protected function handleThaiIdFlow(): void
    {
        $ablyKey = $this->getAblyKey();

        // Write data file for the Playwright script
        $dataFile = storage_path("app/private/thaid_{$this->jobId}.json");
        file_put_contents($dataFile, json_encode([
            'ablyKey' => $ablyKey,
            'ablyChannel' => $this->ablyChannel,
            'callbackUrl' => $this->callbackUrl,
            'items' => $this->items,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        // Run Playwright script
        $process = new Process([
            'node',
            base_path('automation/thaid_login_and_record.cjs'),
            '--jobId='.$this->jobId,
            '--dataFile='.$dataFile,
        ]);
        $process->setTimeout($this->timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            Log::error("ThaiID Playwright failed: {$process->getErrorOutput()}");
        }

        // Read results and send callbacks
        $resultsFile = str_replace('.json', '_results.json', $dataFile);

        if (file_exists($resultsFile)) {
            $results = json_decode(file_get_contents($resultsFile), true);

            foreach ($results['results'] ?? [] as $result) {
                $item = collect($this->items)->firstWhere('id_card', $result['id_card']) ?? [];
                NapCallbackService::send(
                    NapCallbackService::buildPayload(
                        $item,
                        $result['nap_code'] ?? null,
                        $result['success'] ? 'success' : 'error',
                        $result['error'] ?? '',
                    ),
                    $this->callbackUrl,
                );
            }

            unlink($resultsFile);
        }

        // Cleanup
        if (file_exists($dataFile)) {
            unlink($dataFile);
        }
    }

    /**
     * DirectHTTP flow: PHP handles login (username/password) + form POST.
     * Events published via PHP Ably client.
     */
    protected function handleDirectHttpFlow(): void
    {
        $progress = $this->createProgress();
        $napService = new NapDirectHttpService;
        $total = count($this->items);

        $napService->processJob(
            job: null,
            credentials: $this->credentials,
            callbackMode: 'realtime',
            progress: $progress,
            items: $this->items,
            callbackUrl: $this->callbackUrl,
        );
    }

    /**
     * Get Ably key from carematdb or config.
     */
    protected function getAblyKey(): string
    {
        $key = config('services.ably.key', '');

        if (empty($key)) {
            try {
                $key = \DB::connection('carematdb')
                    ->table('site_specific')
                    ->value('ably_key') ?? '';
            } catch (\Exception $e) {
                Log::warning('Could not get Ably key: '.$e->getMessage());
            }
        }

        return $key;
    }

    /**
     * Create Ably progress service.
     */
    protected function createProgress(): ?AblyProgressService
    {
        if (! $this->ablyChannel) {
            return null;
        }

        $ablyKey = $this->getAblyKey();

        if (empty($ablyKey)) {
            return null;
        }

        return new AblyProgressService($ablyKey, $this->ablyChannel);
    }
}
