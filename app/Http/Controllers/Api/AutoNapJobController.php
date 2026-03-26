<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAutoNapJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $validated = $request->validate([
            'site' => ['required', 'string'],
            'fy' => ['required', 'string'],
            'method' => ['nullable', 'string', 'in:DirectHTTP,ThaiID'],
            'nap_username' => ['nullable', 'string'],
            'nap_password' => ['nullable', 'string'],
            'callback_url' => ['required', 'url'],
            'ably_channel' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.source_id' => ['required'],
            'items.*.source' => ['required', 'string'],
            'items.*.id_card' => ['required', 'string', 'size:13'],
            'items.*.rr_form' => ['required', 'array'],
            'items.*.rr_form.pid' => ['required', 'string'],
            'items.*.rr_form.rrttrDate' => ['required', 'string'],
        ]);

        $method = $validated['method'] ?? 'ThaiID';

        // DirectHTTP requires credentials
        if ($method === 'DirectHTTP' && (empty($validated['nap_username']) || empty($validated['nap_password']))) {
            return response()->json([
                'status' => 'error',
                'message' => 'nap_username and nap_password required for DirectHTTP method',
            ], 422);
        }

        $jobId = 'autonap-'.bin2hex(random_bytes(8));

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
            items: $validated['items'],
            method: $method,
        );

        return response()->json([
            'status' => 'ok',
            'job_id' => $jobId,
            'method' => $method,
            'total' => count($validated['items']),
            'message' => $method === 'ThaiID'
                ? 'Job dispatched. QR code will be sent via Ably — scan with ThaiD app.'
                : 'Job dispatched. Subscribe to Ably channel for progress.',
            'ably_channel' => $validated['ably_channel'],
        ]);
    }
}
