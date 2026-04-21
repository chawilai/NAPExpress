<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Writes a JSON snapshot of each /api/jobs request to
 * storage/app/autonap_audit/YYYY-MM-DD/{job_id}.json so we can trace back
 * what CAREMAT sent for each field (access_type, rr_form, staff_name, ...).
 *
 * Temporary — toggle off via AUTONAP_AUDIT_ENABLED=false once data is verified.
 */
class AutoNapAuditLogger
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public static function record(
        string $jobId,
        string $site,
        string $formType,
        ?string $staffName,
        array $items,
        ?string $callbackUrl = null,
    ): void {
        if (! config('services.autonap.audit_enabled', true)) {
            return;
        }

        try {
            $date = now('Asia/Bangkok')->format('Y-m-d');
            $path = "autonap_audit/{$date}/{$jobId}.json";

            $payload = [
                'job_id' => $jobId,
                'received_at' => now('Asia/Bangkok')->toIso8601String(),
                'site' => $site,
                'form_type' => $formType,
                'staff_name' => $staffName,
                'callback_url' => $callbackUrl,
                'item_count' => count($items),
                'items' => array_map([self::class, 'sanitizeItem'], $items),
            ];

            Storage::disk('local')->put(
                $path,
                json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            );
        } catch (\Exception $e) {
            Log::warning("AutoNapAudit failed for job {$jobId}: {$e->getMessage()}");
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected static function sanitizeItem(array $item): array
    {
        if (config('services.autonap.audit_full_pid', false)) {
            return $item;
        }

        if (isset($item['id_card']) && is_string($item['id_card']) && strlen($item['id_card']) >= 4) {
            $item['id_card'] = 'xxxxxxxxx'.substr($item['id_card'], -4);
        }

        if (isset($item['rr_form']['pid']) && is_string($item['rr_form']['pid']) && strlen($item['rr_form']['pid']) >= 4) {
            $item['rr_form']['pid'] = 'xxxxxxxxx'.substr($item['rr_form']['pid'], -4);
        }

        return $item;
    }
}
