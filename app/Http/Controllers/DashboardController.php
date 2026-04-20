<?php

namespace App\Http\Controllers;

use App\Models\AutonapRequest;
use App\Services\ExcelTemplateGenerator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboard', [
            'ablyKey' => config('services.ably.key', ''),
        ]);
    }

    /**
     * Realtime monitor — Inertia page that wraps the blade dashboard in AppLayout.
     */
    public function monitor(): Response
    {
        return Inertia::render('AutonapMonitor', [
            'embedUrl' => route('autonap.embed'),
        ]);
    }

    /**
     * Dashboard summary — Inertia page with stats, history, templates, CTA.
     */
    public function summary(Request $request): Response
    {
        $period = $request->input('period', 'month');
        [$startDate, $endDate] = $this->resolveDateRange(
            $period,
            $request->input('from'),
            $request->input('to')
        );

        $filtered = AutonapRequest::query()
            ->whereBetween('created_at', [$startDate, $endDate]);

        $summary = (clone $filtered)
            ->selectRaw('COUNT(*) as total_jobs')
            ->selectRaw('COALESCE(SUM(total), 0) as total_records')
            ->selectRaw('COALESCE(SUM(success), 0) as total_success')
            ->selectRaw('COALESCE(SUM(failed), 0) as total_failed')
            ->first();

        $successRate = $summary->total_records > 0
            ? round(($summary->total_success / $summary->total_records) * 100, 1)
            : 0;

        // Paginated history with search
        $historyQuery = AutonapRequest::query()
            ->orderByDesc('created_at');

        if ($search = $request->input('q')) {
            $historyQuery->where(function ($w) use ($search) {
                $w->where('site', 'LIKE', "%{$search}%")
                    ->orWhere('job_id', 'LIKE', "%{$search}%")
                    ->orWhere('form_type', 'LIKE', "%{$search}%");
            });
        }

        if ($formType = $request->input('form_type')) {
            $historyQuery->where('form_type', $formType);
        }

        if ($status = $request->input('status')) {
            $historyQuery->where('status', $status);
        }

        $history = $historyQuery->paginate(20)->withQueryString();

        $uniqueSites = AutonapRequest::distinct('site')->pluck('site')->filter()->values();
        $uniqueFormTypes = AutonapRequest::distinct('form_type')->pluck('form_type')->filter()->values();

        return Inertia::render('Overview', [
            'summary' => [
                'total_jobs' => (int) $summary->total_jobs,
                'total_records' => (int) $summary->total_records,
                'total_success' => (int) $summary->total_success,
                'total_failed' => (int) $summary->total_failed,
                'success_rate' => $successRate,
                'period' => $period,
                'date_range' => [
                    'from' => $startDate->format('Y-m-d'),
                    'to' => $endDate->format('Y-m-d'),
                ],
            ],
            'history' => $history,
            'facets' => [
                'sites' => $uniqueSites,
                'form_types' => $uniqueFormTypes,
            ],
            'filters' => [
                'q' => $request->input('q'),
                'form_type' => $request->input('form_type'),
                'status' => $request->input('status'),
                'period' => $period,
            ],
            'templates' => [
                ['name' => 'RR (Reach RR)', 'filename' => 'template_rr.xlsx', 'description' => 'บันทึก Reach / Outreach record (Excel, 2 sheets: คู่มือ + ข้อมูล)'],
                ['name' => 'VCT + Testing', 'filename' => 'template_vct.xlsx', 'description' => 'VCT + Lab + Result ในไฟล์เดียว (Excel, 2 sheets: คู่มือ + ข้อมูล)'],
            ],
        ]);
    }

    /**
     * Download an Excel template (.xlsx) with 2 sheets: Documentation + Data.
     */
    public function downloadTemplate(string $filename): BinaryFileResponse|HttpResponse
    {
        $generator = app(ExcelTemplateGenerator::class);

        $path = match ($filename) {
            'template_rr.xlsx' => $generator->generateRr(),
            'template_vct.xlsx' => $generator->generateVct(),
            // Legacy CSV fallback
            'template_rr.csv' => base_path('docs/csv_templates/template_rr.csv'),
            'template_vct.csv' => base_path('docs/csv_templates/template_vct.csv'),
            default => null,
        };

        if (! $path || ! file_exists($path)) {
            abort(404);
        }

        $contentType = str_ends_with($filename, '.xlsx')
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'text/csv; charset=UTF-8';

        return response()->download($path, $filename, [
            'Content-Type' => $contentType,
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
                    $elapsedSeconds = max(0, now('Asia/Bangkok')->getTimestamp() - Carbon::parse($startedAt)->getTimestamp());
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

        while (count($workers) < 4) {
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
    private function getStats(mixed $startDate, mixed $endDate): array
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

        $recentJobs = (clone $filtered)
            ->select('job_id', 'site', 'form_type', 'nap_user', 'total', 'success', 'failed', 'status', 'started_at', 'finished_at')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function ($job) {
                $duration = null;
                if ($job->started_at && $job->finished_at) {
                    $duration = $job->started_at->diffInSeconds($job->finished_at);
                }

                return [
                    'job_id' => $job->job_id,
                    'site' => $job->site,
                    'form_type' => $job->form_type,
                    'nap_user' => $job->nap_user,
                    'total' => $job->total,
                    'success' => $job->success,
                    'failed' => $job->failed,
                    'status' => $job->status,
                    'date' => $job->started_at?->format('d/m') ?? $job->created_at?->format('d/m'),
                    'started_at' => $job->started_at?->format('H:i:s'),
                    'finished_at' => $job->finished_at?->format('H:i:s'),
                    'duration_seconds' => $duration,
                ];
            });

        return [
            'summary' => [
                'total_jobs' => (int) ($summary->total_jobs ?? 0),
                'total_records' => (int) ($summary->total_records ?? 0),
                'total_success' => (int) ($summary->total_success ?? 0),
                'total_failed' => (int) ($summary->total_failed ?? 0),
                'avg_seconds_per_record' => round($avgPerRecord ?? 0, 1),
            ],
            'by_site' => $bySite,
            'recent_jobs' => $recentJobs,
        ];
    }
}
