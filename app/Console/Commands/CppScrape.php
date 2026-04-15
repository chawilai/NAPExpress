<?php

namespace App\Console\Commands;

use App\Models\CppProvider;
use App\Models\CppScrapeQueue;
use Illuminate\Console\Command;

class CppScrape extends Command
{
    protected $signature = 'cpp:scrape
                            {action : seed | worker | status | reset | import-list}
                            {--file= : JSONL file for import-list}
                            {--worker-id=w1 : Worker identifier}
                            {--api=http://localhost:8000 : API base URL}
                            {--headless=true : Run browser headless}
                            {--delay-ms=3000 : Delay between requests}';

    protected $description = 'Scrape cpp.nhso.go.th provider data';

    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'seed' => $this->seedFromKnown(),
            'import-list' => $this->importList(),
            'worker' => $this->runWorker(),
            'status' => $this->showStatus(),
            'reset' => $this->reset(),
            default => $this->error("Unknown action: {$action}") ?: self::FAILURE,
        };
    }

    /**
     * Seed queue with known hcodes — e.g., from 17 production sites.
     * For full enumeration, use `import-list` with a JSONL file from scrape_cpp_list.cjs.
     */
    private function seedFromKnown(): int
    {
        $knownHcodes = ['41936']; // Caremat as example starter

        $inserted = 0;

        foreach ($knownHcodes as $hcode) {
            $created = CppScrapeQueue::firstOrCreate(
                ['hcode' => $hcode],
                ['status' => 'pending', 'phase' => 'profile']
            );

            if ($created->wasRecentlyCreated) {
                $inserted++;
            }
        }

        $this->info("Seeded {$inserted} hcodes to queue");

        return self::SUCCESS;
    }

    /**
     * Import hcodes from a JSONL file produced by scrape_cpp_list.cjs.
     * Each line should contain { hcode: "...", ... }.
     */
    private function importList(): int
    {
        $file = $this->option('file');

        if (! $file || ! file_exists($file)) {
            $this->error('--file=PATH required (JSONL from scrape_cpp_list.cjs)');

            return self::FAILURE;
        }

        $count = 0;
        $dupes = 0;
        $handle = fopen($file, 'r');

        while (($line = fgets($handle)) !== false) {
            $data = json_decode($line, true);

            if (! $data || empty($data['hcode'])) {
                continue;
            }

            try {
                CppScrapeQueue::create([
                    'hcode' => $data['hcode'],
                    'status' => 'pending',
                    'phase' => 'profile',
                ]);
                $count++;
            } catch (\Exception) {
                $dupes++;
            }

            if ($count % 500 === 0) {
                $this->info("... imported {$count} so far");
            }
        }

        fclose($handle);

        $this->info("Imported {$count} new hcodes ({$dupes} duplicates skipped)");

        return self::SUCCESS;
    }

    /**
     * Run a Node.js Playwright worker that polls the API for pending hcodes.
     */
    private function runWorker(): int
    {
        $workerId = $this->option('worker-id');
        $api = $this->option('api');
        $headless = $this->option('headless');
        $delay = $this->option('delay-ms');

        $this->info("Starting worker {$workerId} (API: {$api})");

        $script = base_path('automation/scrape_cpp_profile.cjs');
        $cmd = sprintf(
            'node %s --worker --worker-id=%s --api=%s --headless=%s --delay-ms=%s',
            escapeshellarg($script),
            escapeshellarg($workerId),
            escapeshellarg($api),
            escapeshellarg($headless),
            escapeshellarg($delay)
        );

        $this->line("Running: {$cmd}");
        passthru($cmd, $exitCode);

        return $exitCode;
    }

    private function showStatus(): int
    {
        $total = CppScrapeQueue::count();
        $pending = CppScrapeQueue::where('status', 'pending')->count();
        $claimed = CppScrapeQueue::where('status', 'claimed')->count();
        $done = CppScrapeQueue::where('status', 'done')->count();
        $failed = CppScrapeQueue::where('status', 'failed')->count();
        $notFound = CppScrapeQueue::where('status', 'not_found')->count();
        $providers = CppProvider::count();

        $pct = $total > 0 ? round(($done / $total) * 100, 1) : 0;

        $this->table(
            ['Metric', 'Value'],
            [
                ['Queue total', number_format($total)],
                ['Pending', number_format($pending)],
                ['Claimed (in progress)', number_format($claimed)],
                ['Done', number_format($done)],
                ['Failed', number_format($failed)],
                ['Not found', number_format($notFound)],
                ['Progress', "{$pct}%"],
                ['Providers in DB', number_format($providers)],
            ]
        );

        // Stuck workers (claimed > 10 min ago)
        $stuck = CppScrapeQueue::where('status', 'claimed')
            ->where('claimed_at', '<', now()->subMinutes(10))
            ->count();

        if ($stuck > 0) {
            $this->warn("{$stuck} items stuck (claimed >10 min ago) — run `cpp:scrape reset --stuck` to requeue");
        }

        return self::SUCCESS;
    }

    private function reset(): int
    {
        if (! $this->confirm('Reset stuck claims (claimed >10 min) back to pending?', true)) {
            return self::FAILURE;
        }

        $reset = CppScrapeQueue::where('status', 'claimed')
            ->where('claimed_at', '<', now()->subMinutes(10))
            ->update([
                'status' => 'pending',
                'claimed_by' => null,
                'claimed_at' => null,
            ]);

        $this->info("Reset {$reset} stuck claims");

        return self::SUCCESS;
    }
}
