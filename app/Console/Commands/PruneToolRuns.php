<?php

namespace App\Console\Commands;

use App\Actions\Tools\PruneToolRuns as PruneAction;
use Illuminate\Console\Command;

/**
 * Scheduled daily. The admin system screen runs the same action.
 */
class PruneToolRuns extends Command
{
    protected $signature = 'carrot:prune-runs {--days= : Override SANDBOX_RUN_RETENTION_DAYS}';

    protected $description = 'Delete old sandbox runs and stale work directories';

    public function handle(PruneAction $prune): int
    {
        $days = $this->option('days');
        $result = $prune->handle($days === null ? null : (int) $days);

        $this->info("Deleted {$result['runs']} runs older than {$result['days']} days and {$result['workdirs']} stale work directories.");

        return self::SUCCESS;
    }
}
