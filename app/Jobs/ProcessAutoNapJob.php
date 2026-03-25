<?php

namespace App\Jobs;

use App\Services\AblyProgressService;
use App\Services\NapCallbackService;
use App\Services\NapDirectHttpService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessAutoNapJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    /**
     * @param  array<string, string>  $credentials
     * @param  array<int, array<string, mixed>>  $items  Each item MUST contain rr_form if available
     */
    public function __construct(
        public string $jobId,
        public string $site,
        public string $fy,
        public array $credentials,
        public string $callbackUrl,
        public ?string $ablyChannel,
        public array $items,
    ) {}

    public function handle(): void
    {
        $progress = $this->createProgress();
        $napService = new NapDirectHttpService;
        $total = count($this->items);
        $success = 0;
        $failed = 0;

        // Phase 1: Start
        $progress?->jobStart(0, $total, $this->site);
        $progress?->connecting(0);
        $progress?->loginStart(0);
        $progress?->loginSuccess(0);
        $progress?->preparing(0, $total);

        // Phase 2: Process each item
        foreach ($this->items as $index => $item) {
            $i = $index + 1;
            $pid = $item['id_card'] ?? '';
            $uic = $item['uic'] ?? '';
            $pidMasked = 'xxxx'.substr($pid, -4);

            $progress?->recordProcessing(0, $i, $total, $pidMasked, $uic);
            $progress?->recordSearching(0, $i, $total);

            // Use rr_form from items directly (sent by nhsoForReach.php)
            $rrForm = $item['rr_form'] ?? null;

            if (! $rrForm) {
                $progress?->recordFailed(0, $i, $total, "ไม่พบข้อมูล rr_form สำหรับ PID {$pidMasked}", $uic);
                $failed++;
                NapCallbackService::send(
                    NapCallbackService::buildPayload($item, null, 'error', 'ไม่พบข้อมูล rr_form'),
                    $this->callbackUrl,
                );

                continue;
            }

            // Submit to NAP
            $progress?->recordFilling(0, $i, $total);
            $progress?->recordSubmitting(0, $i, $total);

            $result = $napService->submitRecord($this->credentials, $rrForm);

            if ($result['success']) {
                $success++;
                $progress?->recordSuccess(0, $i, $total, $result['nap_code'], $uic);
            } else {
                $failed++;
                $progress?->recordFailed(0, $i, $total, $result['error'] ?? '', $uic);
            }

            // Callback per record
            $payload = NapCallbackService::buildPayload(
                $item,
                $result['nap_code'],
                $result['success'] ? 'success' : 'error',
                $result['error'] ?? '',
            );
            NapCallbackService::send($payload, $this->callbackUrl);
        }

        // Phase 3: Complete
        $progress?->summarizing(0);
        $progress?->jobComplete(0, $total, $success, $failed);

        Log::info("AutoNAP job completed: {$this->jobId}", [
            'site' => $this->site,
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
        ]);
    }

    /**
     * Create Ably progress service if channel is configured.
     */
    private function createProgress(): ?AblyProgressService
    {
        if (! $this->ablyChannel) {
            return null;
        }

        $ablyKey = config('services.ably.key', '');

        if (empty($ablyKey)) {
            try {
                $ablyKey = \DB::connection('carematdb')
                    ->table('site_specific')
                    ->value('ably_key');
            } catch (\Exception $e) {
                Log::warning('Could not get Ably key: '.$e->getMessage());

                return null;
            }
        }

        if (empty($ablyKey)) {
            return null;
        }

        return new AblyProgressService($ablyKey, $this->ablyChannel);
    }
}
