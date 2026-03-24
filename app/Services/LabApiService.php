<?php

namespace App\Services;

use App\Models\ReportingJob;
use App\Models\JobRow;
use Illuminate\Support\Facades\Log;

class LabApiService
{
    /**
     * Process a reporting job using the Lab API.
     */
    public function processJob(ReportingJob $job): void
    {
        $job->update(['status' => 'processing']);

        $rows = $job->jobRows;
        $success = 0;
        $failed = 0;

        foreach ($rows as $row) {
            try {
                // Mock API call to NHSO Lab API
                // $response = Http::withHeaders(['X-API-KEY' => $job->organization->apiKeys()->first()->key])
                //     ->post('https://api.nhso.go.th/lab/report', [
                //         'pid' => $row->pid_masked,
                //         ...
                //     ]);
                
                // Simulate success
                usleep(200000); // 200ms
                
                $row->update([
                    'nap_response_code' => '200 OK',
                    'error_message' => null,
                ]);
                
                $success++;
            } catch (\Exception $e) {
                $row->update([
                    'nap_response_code' => '500 Error',
                    'error_message' => $e->getMessage(),
                ]);
                $failed++;
            }

            // Update job counts
            $job->update([
                'counts' => [
                    'total' => $job->counts['total'],
                    'success' => $success,
                    'failed' => $failed,
                ],
            ]);
        }

        $job->update(['status' => 'completed']);
    }
}
