<?php

namespace App\Http\Controllers;

use App\Models\AutonapRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $ablyKey = config('services.ably.key', '');

        // For client-side Ably, use subscribe-only key (strip publish capability)
        // Format: appId.keyId:keySecret → we pass the full key, Ably JS handles it
        return view('dashboard', [
            'ablyKey' => $ablyKey,
        ]);
    }

    public function api(): JsonResponse
    {
        return response()->json([
            'workers' => $this->getWorkers(),
            'queue' => $this->getQueue(),
            'stats' => $this->getStats(),
        ]);
    }

    /**
     * Get active workers — running jobs from cache locks.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getWorkers(): array
    {
        $workers = [];

        // Find all active autonap cache locks
        $locks = DB::table('cache')
            ->where('key', 'like', '%autonap:%')
            ->where('key', 'not like', '%cache_lock%')
            ->get();

        foreach ($locks as $lock) {
            $data = @unserialize($lock->value);

            // Skip old format (string) or invalid data
            if (! is_array($data) || ! isset($data['job_id'])) {
                continue;
            }

            // Get progress from autonap_requests table
            $request = AutonapRequest::where('job_id', $data['job_id'])->first();

            $startedAt = $data['started_at'] ?? $request?->started_at?->toIso8601String();
            $elapsedSeconds = 0;

            if ($startedAt) {
                try {
                    $elapsedSeconds = max(0, now('Asia/Bangkok')->diffInSeconds($startedAt, false));
                } catch (\Exception) {
                    $elapsedSeconds = 0;
                }
            }

            $total = $data['total'] ?? $request?->total ?? 0;

            $workers[] = [
                'job_id' => $data['job_id'],
                'site' => $request?->site ?? $data['site'] ?? '?',
                'form_type' => $data['form_type'] ?? $request?->form_type ?? '?',
                'ably_channel' => $data['ably_channel'] ?? null,
                'total' => $total,
                'elapsed_seconds' => $elapsedSeconds,
                'started_at' => $startedAt,
                'status' => 'active',
            ];
        }

        // Pad to show 2 worker slots
        while (count($workers) < 2) {
            $workers[] = ['status' => 'idle'];
        }

        return $workers;
    }

    /**
     * @return array<string, mixed>
     */
    private function getQueue(): array
    {
        $pendingJobs = DB::table('jobs')->count();
        $activeWorkers = count(array_filter($this->getWorkers(), fn ($w) => $w['status'] === 'active'));
        $waiting = max(0, $pendingJobs - $activeWorkers);

        $pendingRequests = AutonapRequest::where('status', 'pending')->get(['job_id', 'site', 'form_type', 'total']);

        return [
            'waiting' => $waiting,
            'jobs' => $pendingRequests,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getStats(): array
    {
        $overall = AutonapRequest::query()
            ->selectRaw('COUNT(*) as total_jobs')
            ->selectRaw('SUM(total) as total_records')
            ->selectRaw('SUM(success) as total_success')
            ->selectRaw('SUM(failed) as total_failed')
            ->first();

        $bySite = AutonapRequest::query()
            ->select('site', 'form_type')
            ->selectRaw('COUNT(*) as jobs')
            ->selectRaw('SUM(total) as records')
            ->selectRaw('SUM(success) as success')
            ->selectRaw('SUM(failed) as failed')
            ->groupBy('site', 'form_type')
            ->orderByDesc('jobs')
            ->get();

        $today = AutonapRequest::query()
            ->whereDate('created_at', today())
            ->selectRaw('COUNT(*) as jobs')
            ->selectRaw('SUM(total) as records')
            ->selectRaw('SUM(success) as success')
            ->selectRaw('SUM(failed) as failed')
            ->first();

        $avgPerRecord = AutonapRequest::where('status', 'completed')
            ->where('total', '>', 0)
            ->whereNotNull('started_at')
            ->whereNotNull('finished_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, started_at, finished_at) / total) as avg')
            ->value('avg');

        return [
            'overall' => [
                'total_jobs' => (int) ($overall->total_jobs ?? 0),
                'total_records' => (int) ($overall->total_records ?? 0),
                'total_success' => (int) ($overall->total_success ?? 0),
                'total_failed' => (int) ($overall->total_failed ?? 0),
                'avg_seconds_per_record' => round($avgPerRecord ?? 0, 1),
            ],
            'today' => [
                'jobs' => (int) ($today->jobs ?? 0),
                'records' => (int) ($today->records ?? 0),
                'success' => (int) ($today->success ?? 0),
                'failed' => (int) ($today->failed ?? 0),
            ],
            'by_site' => $bySite,
        ];
    }
}
