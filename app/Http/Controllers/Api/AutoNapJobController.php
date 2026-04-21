<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAutoNapJob;
use App\Models\AutonapRequest;
use App\Services\AutoNapAuditLogger;
use App\Services\HcodeValidatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
            'staff_name' => ['nullable', 'string'],
            'callback_url' => ['required', 'url'],
            'ably_channel' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
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

        // Reject if same site + form_type already has a job running
        $site = $validated['site'];
        $lockKey = "autonap:{$site}:{$formType}";

        if (Cache::has($lockKey)) {
            $running = Cache::get($lockKey);
            $runningJobId = is_array($running) ? $running['job_id'] : $running;

            return response()->json([
                'status' => 'error',
                'message' => "Site {$site} มี job {$formType} กำลังทำงานอยู่ ({$runningJobId}) กรุณารอให้เสร็จก่อน",
                'running_job_id' => $runningJobId,
            ], 429);
        }

        $jobId = 'autonap-'.bin2hex(random_bytes(8));
        $ablyChannel = $validated['ably_channel'] ?? null;

        // Lock for 1 hour (max job duration) — released when job completes
        Cache::put($lockKey, [
            'job_id' => $jobId,
            'form_type' => $formType,
            'ably_channel' => $ablyChannel,
            'total' => count($request->input('items')),
            'started_at' => now('Asia/Bangkok')->toIso8601String(),
        ], 3600);

        // Use full items from request (not $validated which strips extra rr_form fields)
        $items = $request->input('items');

        // Temporary audit log — snapshot incoming request for traceback
        AutoNapAuditLogger::record(
            $jobId,
            $site,
            $formType,
            $validated['staff_name'] ?? null,
            $items,
            $validated['callback_url'] ?? null,
        );

        // Soft hcode validation — log any next_hcode not found in CPP registry (non-fatal)
        if ($formType === 'RR') {
            $this->logInvalidHcodes($items, $jobId);
        }

        // Save request to database
        AutonapRequest::create([
            'job_id' => $jobId,
            'site' => $site,
            'form_type' => $formType,
            'method' => $method,
            'fy' => $validated['fy'],
            'total' => count($items),
            'status' => 'pending',
        ]);

        // Store full request as JSON log file for audit/debugging
        $this->storeRequestLog($jobId, $formType, $validated, $items);

        $staffName = $validated['staff_name'] ?? '';

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
            staffName: $staffName,
        );

        // Check queue depth and estimate wait time
        $queueDepth = DB::table('jobs')->count();
        $queued = $queueDepth > 1;
        $estimatedWaitMinutes = null;

        if ($queued) {
            // Count records ahead in queue (pending/running jobs excluding this one)
            $recordsAhead = AutonapRequest::whereIn('status', ['pending', 'running'])
                ->where('job_id', '!=', $jobId)
                ->sum('total');

            // Calculate avg seconds per record from completed jobs
            $avgPerRecord = AutonapRequest::where('status', 'completed')
                ->whereNotNull('started_at')
                ->whereNotNull('finished_at')
                ->where('total', '>', 0)
                ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, started_at, finished_at) / total) as avg')
                ->value('avg');

            $avgPerRecord = $avgPerRecord ?: 40; // default 40s if no data yet
            $estimatedWaitMinutes = (int) ceil(($recordsAhead * $avgPerRecord) / 60);
        }

        return response()->json([
            'status' => 'ok',
            'job_id' => $jobId,
            'form_type' => $formType,
            'method' => $method,
            'total' => count($validated['items']),
            'queued' => $queued,
            'estimated_wait_minutes' => $estimatedWaitMinutes,
            'message' => $queued
                ? "Job อยู่ในคิว (มี {$queueDepth} งานรอ ประมาณ {$estimatedWaitMinutes} นาที) — QR code จะแสดงเมื่อถึงคิว"
                : ($method === 'ThaiID'
                    ? 'Job dispatched. QR code will be sent via Ably — scan with ThaiD app.'
                    : 'Job dispatched. Subscribe to Ably channel for progress.'),
            'ably_channel' => $validated['ably_channel'] ?? null,
        ]);
    }

    /**
     * Log any RR items whose next_hcode is not found in the CPP registry.
     * Non-fatal — just a warning to help surface data quality issues.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function logInvalidHcodes(array $items, string $jobId): void
    {
        $validator = app(HcodeValidatorService::class);
        $invalid = [];

        foreach ($items as $item) {
            $hcode = $item['rr_form']['next_hcode'] ?? null;

            if (! $hcode) {
                continue;
            }

            if (! $validator->exists((string) $hcode)) {
                $invalid[] = [
                    'source_id' => $item['source_id'] ?? null,
                    'next_hcode' => $hcode,
                ];
            }
        }

        if (! empty($invalid)) {
            Log::warning('AutoNAP: invalid next_hcode detected', [
                'job_id' => $jobId,
                'count' => count($invalid),
                'invalid' => array_slice($invalid, 0, 10),
            ]);
        }
    }

    /**
     * Check if a job is currently running for a given site + form_type.
     *
     * GET /api/jobs/status?site=rsat_pte&form_type=VCT
     */
    public function status(Request $request): JsonResponse
    {
        $request->validate([
            'site' => ['required', 'string'],
            'form_type' => ['nullable', 'string'],
        ]);

        $site = $request->input('site');
        $formType = strtoupper($request->input('form_type', 'VCT'));
        $lockKey = "autonap:{$site}:{$formType}";

        $running = Cache::get($lockKey);

        if (! $running) {
            return response()->json(['running' => false]);
        }

        // Support old format (just jobId string) and new format (array)
        if (is_string($running)) {
            return response()->json([
                'running' => true,
                'job_id' => $running,
            ]);
        }

        return response()->json([
            'running' => true,
            ...$running,
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
