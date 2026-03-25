<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReportingJobRequest;
use App\Jobs\ProcessReportingJob;
use App\Models\JobRow;
use App\Models\ReportingJob;
use App\Services\ExcelTemplateService;
use App\Services\ReachRrValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;

class ReportingJobController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $organization = Auth::user()->organization;

        if (! $organization) {
            return redirect()->route('home')->with('error', 'You must be part of an organization to access the dashboard.');
        }

        $jobs = $organization->reportingJobs()
            ->with('user')
            ->latest()
            ->paginate(10);

        return inertia('Dashboard', [
            'jobs' => $jobs,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreReportingJobRequest $request)
    {
        $user = Auth::user();
        $organization = $user->organization;

        $data = Excel::toArray([], $request->file('file'));
        $rows = $data[0] ?? [];

        $headers = array_shift($rows);
        $parsedRows = $this->parseRowsWithHeaders($headers, $rows);

        if ($request->form_type === 'Reach RR') {
            $validationResults = ReachRrValidator::validateRows($parsedRows);
            $errors = collect($validationResults)->filter(fn ($r) => ! $r->isValid());

            if ($errors->isNotEmpty()) {
                $errorMessages = $errors->map(fn ($r) => "Row {$r->rowNumber}: ".implode(', ', $r->errors))->values()->all();

                return back()->withErrors(['validation_errors' => $errorMessages])->withInput();
            }
        }

        $job = $organization->reportingJobs()->create([
            'user_id' => $user->id,
            'form_type' => $request->form_type,
            'method' => $request->method,
            'status' => 'pending',
            'counts' => [
                'total' => 0,
                'success' => 0,
                'failed' => 0,
            ],
            'ably_channel' => 'job-'.bin2hex(random_bytes(8)),
        ]);

        $recordCount = 0;

        foreach ($parsedRows as $index => $row) {
            $pid = $row['pid'] ?? '';

            if (empty($pid)) {
                continue;
            }

            JobRow::create([
                'reporting_job_id' => $job->id,
                'row_number' => $index + 2,
                'pid_masked' => $this->maskPid($pid),
                'row_data' => $row,
                'status' => 'pending',
                'nap_response_code' => null,
                'error_message' => null,
            ]);
            $recordCount++;
        }

        $job->update([
            'counts' => [
                'total' => $recordCount,
                'success' => 0,
                'failed' => 0,
            ],
        ]);

        if ($request->method === 'Playwright' && $request->filled('nap_username')) {
            Cache::put("job:{$job->id}:credentials", [
                'username' => $request->nap_username,
                'password' => $request->nap_password,
            ], now()->addHours(2));
        }

        dispatch(new ProcessReportingJob($job));

        return redirect()->route('dashboard')->with('success', "Job created successfully with {$recordCount} records.");
    }

    /**
     * Display the specified resource.
     */
    public function show(ReportingJob $job)
    {
        if ($job->organization_id !== Auth::user()->organization_id) {
            abort(403);
        }

        return inertia('Jobs/Show', [
            'job' => $job->load(['jobRows' => function ($query) {
                $query->latest()->limit(50);
            }, 'user']),
        ]);
    }

    /**
     * Download the reporting template.
     */
    public function downloadTemplate(Request $request)
    {
        $formType = $request->query('form_type', 'Reach RR');
        $service = new ExcelTemplateService;

        return $service->generateTemplate($formType);
    }

    /**
     * Parse raw Excel rows into associative arrays using header row as keys.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function parseRowsWithHeaders(array $headers, array $rows): array
    {
        $normalizedHeaders = array_map(fn ($h) => strtolower(trim((string) $h)), $headers);
        $stringFields = ['pid', 'uic', 'next_hcode'];

        $parsed = array_map(
            fn ($row) => array_combine($normalizedHeaders, array_pad($row, count($normalizedHeaders), null)),
            array_filter($rows, fn ($row) => ! empty(array_filter($row)))
        );

        return array_map(function ($row) use ($stringFields) {
            foreach ($stringFields as $field) {
                if (isset($row[$field])) {
                    $row[$field] = (string) $row[$field];
                }
            }

            return $row;
        }, $parsed);
    }

    /**
     * Mask PID to show only last 7 digits.
     */
    private function maskPid(string $pid): string
    {
        $pid = trim($pid);

        if (strlen($pid) <= 7) {
            return $pid;
        }

        return 'xxxx'.substr($pid, -7);
    }
}
