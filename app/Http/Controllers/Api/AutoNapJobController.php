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
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'site' => ['required', 'string'],
            'fy' => ['required', 'string'],
            'nap_username' => ['required', 'string'],
            'nap_password' => ['required', 'string'],
            'callback_url' => ['required', 'url'],
            'ably_channel' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.source_id' => ['required'],
            'items.*.source' => ['required', 'string'],
            'items.*.id_card' => ['required', 'string', 'size:13'],
        ]);

        $jobId = 'autonap-'.bin2hex(random_bytes(8));

        // Dispatch background job
        ProcessAutoNapJob::dispatch(
            jobId: $jobId,
            site: $validated['site'],
            fy: $validated['fy'],
            credentials: [
                'username' => $validated['nap_username'],
                'password' => $validated['nap_password'],
            ],
            callbackUrl: $validated['callback_url'],
            ablyChannel: $validated['ably_channel'] ?? null,
            items: $validated['items'],
        );

        return response()->json([
            'status' => 'ok',
            'job_id' => $jobId,
            'total' => count($validated['items']),
            'message' => 'Job dispatched. Subscribe to Ably channel for progress.',
            'ably_channel' => $validated['ably_channel'],
        ]);
    }
}
