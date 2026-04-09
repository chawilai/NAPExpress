<?php

namespace App\Http\Controllers;

use App\Models\AutonapRequest;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboard', [
            'ablyKey' => config('services.ably.key', ''),
        ]);
    }

    public function api(Request $request): JsonResponse
    {
        $period = $request->input('period', 'today');
        $from = $request->input('from');
        $to = $request->input('to');

        [$startDate, $endDate] = $this->resolveDateRange($period, $from, $to);

        return response()->json([
            'workers' => $this->getWorkers(),
            'queue' => $this->getQueue(),
            'stats' => $this->getStats($startDate, $endDate),
            'period' => $period,
            'date_range' => [
                'from' => $startDate->format('Y-m-d'),
                'to' => $endDate->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * @return array{Carbon, Carbon}
     */
    private function resolveDateRange(string $period, ?string $from, ?string $to): array
    {
        $tz = 'Asia/Bangkok';

        return match ($period) {
            'today' => [now($tz)->startOfDay(), now($tz)->endOfDay()],
            'yesterday' => [now($tz)->subDay()->startOfDay(), now($tz)->subDay()->endOfDay()],
            'week' => [now($tz)->startOfWeek(), now($tz)->endOfDay()],
            'month' => [now($tz)->startOfMonth(), now($tz)->endOfDay()],
            'all' => [Carbon::parse('2026-01-01', $tz), now($tz)->endOfDay()],
            'custom' => [
                $from ? Carbon::parse($from, $tz)->startOfDay() : now($tz)->startOfDay(),
                $to ? Carbon::parse($to, $tz)->endOfDay() : now($tz)->endOfDay(),
            ],
            default => [now($tz)->startOfDay(), now($tz)->endOfDay()],
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getWorkers(): array
    {
        $workers = [];

        $locks = DB::table('cache')
            ->where('key', 'like', '%autonap:%')
            ->where('key', 'not like', '%cache_lock%')
            ->get();

        foreach ($locks as $lock) {
            $data = @unserialize($lock->value);

            if (! is_array($data) || ! isset($data['job_id'])) {
                continue;
            }

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
        $activeLocks = DB::table('cache')
            ->where('key', 'like', '%autonap:%')
            ->where('key', 'not like', '%cache_lock%')
            ->count();
        $waiting = max(0, $pendingJobs - $activeLocks);

        $pendingRequests = AutonapRequest::where('status', 'pending')->get(['job_id', 'site', 'form_type', 'total']);

        return [
            'waiting' => $waiting,
            'jobs' => $pendingRequests,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getStats(Carbon $startDate, Carbon $endDate): array
    {
        $filtered = AutonapRequest::query()
            ->whereBetween('created_at', [$startDate, $endDate]);

        $summary = (clone $filtered)
            ->selectRaw('COUNT(*) as total_jobs')
            ->selectRaw('SUM(total) as total_records')
            ->selectRaw('SUM(success) as total_success')
            ->selectRaw('SUM(failed) as total_failed')
            ->first();

        $bySite = (clone $filtered)
            ->select('site', 'form_type')
            ->selectRaw('COUNT(*) as jobs')
            ->selectRaw('SUM(total) as records')
            ->selectRaw('SUM(success) as success')
            ->selectRaw('SUM(failed) as failed')
            ->groupBy('site', 'form_type')
            ->orderByDesc('jobs')
            ->get();

        $avgPerRecord = (clone $filtered)
            ->where('status', 'completed')
            ->where('total', '>', 0)
            ->whereNotNull('started_at')
            ->whereNotNull('finished_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, started_at, finished_at) / total) as avg')
            ->value('avg');

        return [
            'summary' => [
                'total_jobs' => (int) ($summary->total_jobs ?? 0),
                'total_records' => (int) ($summary->total_records ?? 0),
                'total_success' => (int) ($summary->total_success ?? 0),
                'total_failed' => (int) ($summary->total_failed ?? 0),
                'avg_seconds_per_record' => round($avgPerRecord ?? 0, 1),
            ],
            'by_site' => $bySite,
        ];
    }
}
