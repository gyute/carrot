<?php

namespace App\Console\Commands;

use App\Models\ToolRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Drops finished runs past the retention window and any work directory a
 * crashed worker left behind. Scheduled daily.
 */
class PruneToolRuns extends Command
{
    protected $signature = 'carrot:prune-runs {--days= : Override SANDBOX_RUN_RETENTION_DAYS}';

    protected $description = 'Delete old sandbox runs and stale work directories';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('sandbox.run_retention_days'));
        $cutoff = now()->subDays($days);

        $deleted = ToolRun::query()
            ->where('created_at', '<', $cutoff)
            ->whereNotIn('status', ['queued', 'running'])
            ->delete();

        $stale = 0;
        $base = (string) config('sandbox.workdir');

        if (File::isDirectory($base)) {
            foreach (File::directories($base) as $directory) {
                if (File::lastModified($directory) < now()->subHours(6)->getTimestamp()) {
                    File::deleteDirectory($directory);
                    $stale++;
                }
            }
        }

        $this->info("Deleted {$deleted} runs older than {$days} days and {$stale} stale work directories.");

        return self::SUCCESS;
    }
}
