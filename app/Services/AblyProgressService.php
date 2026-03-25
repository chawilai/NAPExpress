<?php

namespace App\Services;

use Ably\AblyRest;
use Illuminate\Support\Facades\Log;

class AblyProgressService
{
    private AblyRest $ably;

    private string $channelName;

    public function __construct(string $ablyKey, string $channelName)
    {
        $this->ably = new AblyRest($ablyKey);
        $this->channelName = $channelName;
    }

    /**
     * Publish an event to the Ably channel with optional delay.
     *
     * @param  array<string, mixed>  $data
     */
    public function publish(string $event, array $data, int $delayMs = 0): void
    {
        try {
            $channel = $this->ably->channel($this->channelName);
            $channel->publish($event, $data);

            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        } catch (\Exception $e) {
            Log::warning("Ably publish failed [{$event}]: ".$e->getMessage());
        }
    }

    // ============================================================
    // Job Lifecycle Events
    // ============================================================

    /**
     * Job started.
     */
    public function jobStart(int $jobId, int $total, string $siteName): void
    {
        $this->publish('job:start', [
            'jobId' => $jobId,
            'total' => $total,
            'siteName' => $siteName,
            'message' => "🔐 เริ่มบันทึก NAP RR ({$total} records) — {$siteName}",
        ], 500);
    }

    /**
     * Connecting to NAP Plus.
     */
    public function connecting(int $jobId): void
    {
        $this->publish('job:connecting', [
            'jobId' => $jobId,
            'message' => '🌐 กำลังเชื่อมต่อ NAP Plus...',
        ], 1500);
    }

    /**
     * Login in progress.
     */
    public function loginStart(int $jobId): void
    {
        $this->publish('job:login', [
            'jobId' => $jobId,
            'message' => '🔑 กำลัง Login NAP Plus...',
        ], 2000);
    }

    /**
     * Login success.
     */
    public function loginSuccess(int $jobId): void
    {
        $this->publish('job:login:success', [
            'jobId' => $jobId,
            'success' => true,
            'message' => '✅ Login สำเร็จ',
        ], 500);
    }

    /**
     * Login failed.
     */
    public function loginFailed(int $jobId, string $error): void
    {
        $this->publish('job:login:failed', [
            'jobId' => $jobId,
            'success' => false,
            'error' => $error,
            'message' => "❌ Login ล้มเหลว: {$error}",
        ]);
    }

    /**
     * Preparing data.
     */
    public function preparing(int $jobId, int $total): void
    {
        $this->publish('job:preparing', [
            'jobId' => $jobId,
            'total' => $total,
            'message' => "📋 กำลังเตรียมข้อมูล {$total} รายการ...",
        ], 1000);
    }

    // ============================================================
    // Per-Record Events
    // ============================================================

    /**
     * Record processing started.
     */
    public function recordProcessing(int $jobId, int $index, int $total, string $pidMasked, string $uic = ''): void
    {
        $this->publish('job:record:processing', [
            'jobId' => $jobId,
            'index' => $index,
            'total' => $total,
            'pidMasked' => $pidMasked,
            'uic' => $uic,
            'message' => "📄 กำลังบันทึก ({$index}/{$total}) | {$uic} | PID: {$pidMasked}",
        ], 300);
    }

    /**
     * Searching for person.
     */
    public function recordSearching(int $jobId, int $index, int $total): void
    {
        $this->publish('job:record:searching', [
            'jobId' => $jobId,
            'index' => $index,
            'total' => $total,
            'message' => "🔍 กำลังค้นหาข้อมูลบุคคล... ({$index}/{$total})",
        ], 800);
    }

    /**
     * Filling form.
     */
    public function recordFilling(int $jobId, int $index, int $total): void
    {
        $this->publish('job:record:filling', [
            'jobId' => $jobId,
            'index' => $index,
            'total' => $total,
            'message' => "✏️ กำลังกรอกข้อมูลแบบฟอร์ม... ({$index}/{$total})",
        ], 500);
    }

    /**
     * Submitting form.
     */
    public function recordSubmitting(int $jobId, int $index, int $total): void
    {
        $this->publish('job:record:submitting', [
            'jobId' => $jobId,
            'index' => $index,
            'total' => $total,
            'message' => "💾 กำลังบันทึก... ({$index}/{$total})",
        ], 500);
    }

    /**
     * Record success.
     */
    public function recordSuccess(int $jobId, int $index, int $total, string $napCode, string $uic = ''): void
    {
        $this->publish('job:record:success', [
            'jobId' => $jobId,
            'index' => $index,
            'total' => $total,
            'napCode' => $napCode,
            'uic' => $uic,
            'message' => "✅ สำเร็จ ({$index}/{$total}) | {$napCode}",
        ], 300);
    }

    /**
     * Record failed.
     */
    public function recordFailed(int $jobId, int $index, int $total, string $error, string $uic = ''): void
    {
        $this->publish('job:record:failed', [
            'jobId' => $jobId,
            'index' => $index,
            'total' => $total,
            'error' => $error,
            'uic' => $uic,
            'message' => "❌ ล้มเหลว ({$index}/{$total}) | {$error}",
        ], 300);
    }

    // ============================================================
    // Job Complete Events
    // ============================================================

    /**
     * Summarizing results.
     */
    public function summarizing(int $jobId): void
    {
        $this->publish('job:summarizing', [
            'jobId' => $jobId,
            'message' => '📊 กำลังสรุปผล...',
        ], 1000);
    }

    /**
     * Job completed.
     */
    public function jobComplete(int $jobId, int $total, int $success, int $failed): void
    {
        $this->publish('job:complete', [
            'jobId' => $jobId,
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'message' => "📊 สรุป: สำเร็จ {$success} / ล้มเหลว {$failed} / ทั้งหมด {$total}",
        ]);
    }
}
