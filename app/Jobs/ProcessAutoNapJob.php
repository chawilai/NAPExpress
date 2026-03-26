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
     * 1. Playwright (headed) → ThaiD login → QR scan → export cookies
     * 2. DirectHTTP (PHP) → use cookies → submit forms (fast ~1s/record)
     */
    protected function handleThaiIdFlow(): void
    {
        $ablyKey = $this->getAblyKey();
        $progress = $this->createProgress();
        $total = count($this->items);

        // Step 1: Playwright login — export cookies
        $dataFile = storage_path("app/private/thaid_{$this->jobId}.json");
        file_put_contents($dataFile, json_encode([
            'ablyKey' => $ablyKey,
            'ablyChannel' => $this->ablyChannel,
            'jobId' => $this->jobId,
        ], JSON_UNESCAPED_UNICODE));

        $process = new Process([
            'node',
            base_path('automation/thaid_login_only.cjs'),
            '--dataFile='.$dataFile,
        ]);
        $process->setTimeout(90); // 90s max (60s QR wait + 30s buffer)

        Log::info("ThaiID: Starting login for job {$this->jobId}");

        $process->run(function ($type, $buffer) {
            Log::info("ThaiID [{$type}]: {$buffer}");
        });

        // Read cookies
        $cookieFile = str_replace('.json', '_cookies.json', $dataFile);

        if (! file_exists($cookieFile)) {
            Log::error('ThaiID: No cookies file — login failed');
            $progress?->publish('job:error', ['jobId' => $this->jobId, 'message' => '❌ Login ล้มเหลว — ไม่ได้สแกน ThaiD']);
            $this->cleanup($dataFile, $cookieFile);

            return;
        }

        $cookies = json_decode(file_get_contents($cookieFile), true);
        $count = count($cookies);
        Log::info("ThaiID: Got {$count} cookies");

        // Step 2: DirectHTTP with cookies — fast form submission
        $progress?->preparing($this->jobId, $total);

        $napService = new NapDirectHttpService;
        $callbackUrl = $this->callbackUrl;
        $success = 0;
        $failed = 0;

        foreach ($this->items as $index => $item) {
            $i = $index + 1;
            $rrForm = $item['rr_form'] ?? [];
            $uic = $item['uic'] ?? '';
            $pidMasked = 'xxxx'.substr($item['id_card'] ?? '', -4);

            $progress?->recordProcessing($this->jobId, $i, $total, $pidMasked, $uic);
            $progress?->recordSearching($this->jobId, $i, $total);
            $progress?->recordFilling($this->jobId, $i, $total);

            if (! $this->dryRun) {
                $progress?->recordSubmitting($this->jobId, $i, $total);
            }

            $result = $napService->submitWithCookies($cookies, $rrForm, $this->dryRun);

            if ($this->dryRun) {
                if ($result['success']) {
                    $success++;
                    $summary = NapDirectHttpService::summarizeRrForm($rrForm);
                    $progress?->publish('job:record:report', [
                        'jobId' => $this->jobId,
                        'index' => $i,
                        'total' => $total,
                        'uic' => $uic,
                        'pid' => $pidMasked,
                        'summary' => $summary,
                        'message' => "✅ พร้อมบันทึก ({$i}/{$total}) | {$uic} | PID: {$pidMasked}\n"
                            ."  วันที่: {$summary['date']}\n"
                            ."  กลุ่มเสี่ยง: {$summary['risk_behaviors']}\n"
                            ."  กลุ่มเป้าหมาย: {$summary['target_groups']}\n"
                            ."  อาชีพ: {$summary['occupation']}\n"
                            ."  ถุงยาง: {$summary['condom']}\n"
                            ."  ส่งต่อ: {$summary['forwards']}\n"
                            ."  แหล่งเงิน: {$summary['pay_by']}",
                    ], 300);
                } else {
                    $failed++;
                    $progress?->recordFailed($this->jobId, $i, $total, $result['error'] ?? '', $uic);
                }

                continue;
            }

            if ($result['success']) {
                $success++;
                $progress?->recordSuccess($this->jobId, $i, $total, $result['nap_code'], $uic);
            } else {
                $failed++;
                $progress?->recordFailed($this->jobId, $i, $total, $result['error'] ?? '', $uic);
            }

            // Callback per record
            NapCallbackService::send(
                NapCallbackService::buildPayload(
                    $item,
                    $result['nap_code'],
                    $result['success'] ? 'success' : 'error',
                    $result['error'] ?? '',
                ),
                $callbackUrl,
            );
        }

        // Summary
        $progress?->summarizing($this->jobId);
        $progress?->jobComplete($this->jobId, $total, $success, $failed);

        Log::info("AutoNAP job completed: {$this->jobId}", compact('total', 'success', 'failed'));

        $this->cleanup($dataFile, $cookieFile);
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
