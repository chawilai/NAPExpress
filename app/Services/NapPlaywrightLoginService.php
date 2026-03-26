<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class NapPlaywrightLoginService
{
    /**
     * Start Playwright browser, navigate to NAP login, click ThaiD,
     * capture QR code, and return it. Browser stays open waiting for scan.
     *
     * Returns process handle + data file path for communication.
     *
     * @return array{process: Process, dataFile: string, jobId: string}
     */
    public static function startLoginSession(string $jobId): array
    {
        $dataFile = storage_path("app/private/thaid_session_{$jobId}.json");

        $process = new Process([
            'node',
            base_path('automation/thaid_login_and_record.cjs'),
            '--jobId='.$jobId,
            '--dataFile='.$dataFile,
        ]);

        $process->setTimeout(600); // 10 minutes max for login + recording
        $process->start();

        return [
            'process' => $process,
            'dataFile' => $dataFile,
            'jobId' => $jobId,
        ];
    }

    /**
     * Read session data from the Playwright script output file.
     *
     * @return array<string, mixed>|null
     */
    public static function readSessionData(string $dataFile): ?array
    {
        if (! file_exists($dataFile)) {
            return null;
        }

        $data = json_decode(file_get_contents($dataFile), true);

        return is_array($data) ? $data : null;
    }
}
