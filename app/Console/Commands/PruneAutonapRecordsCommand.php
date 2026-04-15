<?php

namespace App\Console\Commands;

use App\Models\AutonapRecord;
use Illuminate\Console\Command;

class PruneAutonapRecordsCommand extends Command
{
    protected $signature = 'autonap:prune
                            {--days=90 : Retention period in days}
                            {--dry-run : Report how many rows would be deleted without deleting}';

    protected $description = 'Delete autonap_records older than retention window (default 90 days) — PDPA data minimization';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');

        $cutoff = now()->subDays($days);

        $query = AutonapRecord::where('created_at', '<', $cutoff);
        $count = $query->count();

        if ($count === 0) {
            $this->info("No records older than {$days} days.");

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("[DRY RUN] Would delete {$count} records older than {$days} days (before {$cutoff})");

            return self::SUCCESS;
        }

        $deleted = $query->delete();

        $this->info("Deleted {$deleted} records older than {$days} days (before {$cutoff})");

        return self::SUCCESS;
    }
}
