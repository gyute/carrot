<?php

namespace App\Actions\Tools;

use App\Enums\ToolRunStatus;
use App\Models\ToolRun;
use Illuminate\Support\Facades\File;

/**
 * Drops finished runs past the retention window and any work directory a
 * crashed worker left behind. Shared by the scheduled command and the admin
 * screen so both mean exactly the same thing.
 */
class PruneToolRuns
{
    /**
     * @return array{runs: int, workdirs: int, days: int}
     */
    public function handle(?int $days = null): array
    {
        $days ??= (int) config('sandbox.run_retention_days');

        $runs = ToolRun::query()
            ->where('created_at', '<', now()->subDays($days))
            ->whereNotIn('status', [ToolRunStatus::Queued, ToolRunStatus::Running])
            ->delete();

        $workdirs = 0;
        $base = (string) config('sandbox.workdir');

        if (File::isDirectory($base)) {
            foreach (File::directories($base) as $directory) {
                if (File::lastModified($directory) < now()->subHours(6)->getTimestamp()) {
                    File::deleteDirectory($directory);
                    $workdirs++;
                }
            }
        }

        return ['runs' => $runs, 'workdirs' => $workdirs, 'days' => $days];
    }
}
