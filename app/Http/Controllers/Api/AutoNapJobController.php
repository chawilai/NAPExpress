<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAutoNapJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AutoNapJobController extends Controller
{
    /**
     * Accept a batch of items from nhsoForReach.php and dispatch background processing.
     *
     * Supports 2 methods:
     * - "DirectHTTP": requires nap_username + nap_password (old login)
     * - "ThaiID": no credentials needed, Playwright shows QR via Ably for user to scan
     */
    public function store(Request $request): JsonResponse
    {
        $formType = strtoupper($request->input('form_type', 'RR'));

        // Base validation rules (shared by all form types)
        $rules = [
            'site' => ['required', 'string'],
            'fy' => ['required', 'string'],
            'form_type' => ['nullable', 'string', 'in:RR,VCT,rr,vct'],
            'method' => ['nullable', 'string'],
            'dry_run' => ['nullable', 'boolean'],
            'nap_username' => ['nullable', 'string'],
            'nap_password' => ['nullable', 'string'],
            'callback_url' => ['required', 'url'],
            'ably_channel' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.source_id' => ['required'],
            'items.*.source' => ['required', 'string'],
            'items.*.id_card' => ['required', 'string', 'size:13'],
        ];

        // Form-type-specific item validation
        if ($formType === 'VCT') {
            $rules['items.*.service_date'] = ['required', 'string'];
            $rules['items.*.kp'] = ['required', 'string'];
            $rules['items.*.cbs'] = ['required', 'string'];
        } else {
            $rules['items.*.rr_form'] = ['required', 'array'];
            $rules['items.*.rr_form.pid'] = ['required', 'string'];
            $rules['items.*.rr_form.rrttrDate'] = ['required', 'string'];
        }

        $validated = $request->validate($rules);

        // Normalize method: accept any case
        $rawMethod = strtolower($validated['method'] ?? 'thaid');
        $method = match (true) {
            str_contains($rawMethod, 'direct'), str_contains($rawMethod, 'http') => 'DirectHTTP',
            str_contains($rawMethod, 'playwright') => 'ThaiID',
            default => 'ThaiID',
        };

        // DirectHTTP requires credentials
        if ($method === 'DirectHTTP' && (empty($validated['nap_username']) || empty($validated['nap_password']))) {
            return response()->json([
                'status' => 'error',
                'message' => 'nap_username and nap_password required for DirectHTTP method',
            ], 422);
        }

        $jobId = 'autonap-'.bin2hex(random_bytes(8));

        // Use full items from request (not $validated which strips extra rr_form fields)
        $items = $request->input('items');

        // Store full request as JSON log file for audit/debugging
        $this->storeRequestLog($jobId, $formType, $validated, $items);

        ProcessAutoNapJob::dispatch(
            jobId: $jobId,
            site: $validated['site'],
            fy: $validated['fy'],
            credentials: [
                'username' => $validated['nap_username'] ?? '',
                'password' => $validated['nap_password'] ?? '',
            ],
            callbackUrl: $validated['callback_url'],
            ablyChannel: $validated['ably_channel'] ?? null,
            items: $items,
            method: $method,
            dryRun: (bool) ($validated['dry_run'] ?? false),
            formType: $formType,
        );

        return response()->json([
            'status' => 'ok',
            'job_id' => $jobId,
            'form_type' => $formType,
            'method' => $method,
            'total' => count($validated['items']),
            'message' => $method === 'ThaiID'
                ? 'Job dispatched. QR code will be sent via Ably — scan with ThaiD app.'
                : 'Job dispatched. Subscribe to Ably channel for progress.',
            'ably_channel' => $validated['ably_channel'] ?? null,
        ]);
    }

    /**
     * Store incoming request as JSON file for audit/debugging.
     *
     * Files saved to: storage/app/private/autonap_logs/{date}/{jobId}.json
     *
     * @param  array<string, mixed>  $validated
     * @param  array<int, array<string, mixed>>  $items
     */
    private function storeRequestLog(string $jobId, string $formType, array $validated, array $items): void
    {
        try {
            $date = now()->format('Y-m-d');
            $dir = storage_path("app/private/autonap_logs/{$date}");

            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $logData = [
                'job_id' => $jobId,
                'form_type' => $formType,
                'site' => $validated['site'],
                'fy' => $validated['fy'],
                'method' => $validated['method'] ?? 'ThaiID',
                'callback_url' => $validated['callback_url'],
                'ably_channel' => $validated['ably_channel'] ?? null,
                'dry_run' => $validated['dry_run'] ?? false,
                'items_count' => count($items),
                'items' => $items,
                'received_at' => now()->toIso8601String(),
                'ip' => request()->ip(),
            ];

            file_put_contents(
                "{$dir}/{$jobId}.json",
                json_encode($logData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            );

            Log::info("AutoNAP request logged: {$jobId}", [
                'form_type' => $formType,
                'site' => $validated['site'],
                'items_count' => count($items),
            ]);
        } catch (\Exception $e) {
            Log::warning("Failed to store request log: {$e->getMessage()}");
        }
    }
}
