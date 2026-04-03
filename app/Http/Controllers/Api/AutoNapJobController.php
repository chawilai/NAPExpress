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
        // Log incoming request for debugging
        Log::info('AutoNAP job request received', [
            'site' => $request->input('site'),
            'form_type' => $request->input('form_type', 'RR'),
            'items_count' => count($request->input('items', [])),
            'items' => $request->input('items'),
        ]);

        $formType = strtoupper($request->input('form_type', 'RR'));

        // Base validation rules (shared by all form types)
        $rules = [
            'site' => ['required', 'string'],
            'fy' => ['required', 'string'],
            'form_type' => ['nullable', 'string'],
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
            'ably_channel' => $validated['ably_channel'],
        ]);
    }
}
