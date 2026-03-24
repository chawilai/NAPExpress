<?php

namespace App\Http\Controllers;

use App\Models\ReportingJob;
use App\Models\JobRow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ReportingJobController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $organization = Auth::user()->organization;
        
        if (!$organization) {
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
    public function store(Request $request)
    {
        $request->validate([
            'form_type' => 'required|string',
            'method' => 'required|string',
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $user = Auth::user();
        $organization = $user->organization;

        // Create the job record
        $job = $organization->reportingJobs()->create([
            'user_id' => $user->id,
            'form_type' => $request->form_type,
            'method' => $request->method,
            'status' => 'processing',
            'counts' => [
                'total' => 0,
                'success' => 0,
                'failed' => 0,
            ],
            'ably_channel' => 'job-' . bin2hex(random_bytes(8)),
        ]);

        // Parse Excel and create initial rows
        $data = Excel::toArray([], $request->file('file'));
        $rows = $data[0] ?? [];
        
        // Remove header row
        array_shift($rows);

        $recordCount = 0;
        foreach ($rows as $index => $row) {
            if (empty($row[0])) continue; // Skip empty rows

            JobRow::create([
                'reporting_job_id' => $job->id,
                'row_number' => $index + 2, // +2 because array_shift moved index and we want 1-based original row
                'pid_masked' => $row[0],
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

        // Dispatch the background worker
        dispatch(new \App\Jobs\ProcessReportingJob($job));

        return redirect()->route('dashboard')->with('success', 'Job created successfully with ' . $recordCount . ' records.');
    }

    /**
     * Display the specified resource.
     */
    public function show(ReportingJob $job)
    {
        // Ensure user belongs to the same organization
        if ($job->organization_id !== Auth::user()->organization_id) {
            abort(403);
        }

        return inertia('Jobs/Show', [
            'job' => $job->load(['jobRows' => function($query) {
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
        $service = new \App\Services\ExcelTemplateService();
        
        return $service->generateTemplate($formType);
    }
}
