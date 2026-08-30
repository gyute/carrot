<?php

namespace App\Jobs;

use App\Enums\ToolRunStatus;
use App\Models\ToolRun;
use App\Sandbox\RunSpec;
use App\Sandbox\SandboxRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Executes one queued run on the `sandbox` queue - the runner host's queue.
 * The source is re-read from the tool (or the submission for a test run)
 * and its hash checked against what the run was created with, so what
 * executes is exactly what was approved, even if the row changed meanwhile.
 */
class RunToolJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public ToolRun $run)
    {
        $this->onQueue('sandbox');
    }

    public function handle(SandboxRunner $runner): void
    {
        $run = $this->run;

        $run->forceFill(['status' => ToolRunStatus::Running, 'started_at' => now()])->save();

        try {
            $spec = $this->spec($run);
            $runner->ensureReady();
            $result = $runner->run($spec);
        } catch (Throwable $e) {
            Log::error('Tool run failed before the sandbox returned.', ['ulid' => $run->ulid, 'exception' => $e]);

            $run->forceFill([
                'status' => ToolRunStatus::Failed,
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ])->save();

            return;
        }

        $run->forceFill([
            'status' => $result->timedOut
                ? ToolRunStatus::TimedOut
                : ($result->succeeded() ? ToolRunStatus::Completed : ToolRunStatus::Failed),
            'exit_code' => $result->exitCode,
            'stdout' => $result->stdout,
            'stderr' => $result->stderr,
            'truncated' => $result->truncated,
            'duration_ms' => $result->durationMs,
            'finished_at' => now(),
        ])->save();
    }

    public function failed(?Throwable $e): void
    {
        $this->run->forceFill([
            'status' => ToolRunStatus::Failed,
            'error_message' => $e?->getMessage() ?? 'The job failed.',
            'finished_at' => now(),
        ])->save();
    }

    private function spec(ToolRun $run): RunSpec
    {
        if ($run->tool !== null) {
            $source = $run->tool->source;
            $config = $run->tool->config;
        } elseif ($run->submission !== null) {
            $source = $run->submission->source();
            $config = $run->submission->config();
        } else {
            throw new RuntimeException('The run points at neither a tool nor a submission.');
        }

        if ($source === null || hash('sha256', $source) !== $run->source_hash) {
            throw new RuntimeException('The source changed since this run was requested; refusing to execute unapproved code.');
        }

        return new RunSpec(
            id: $run->ulid,
            runtime: $run->runtime,
            source: $source,
            inputs: $run->inputs,
            timeoutSec: min((int) ($config['timeout_sec'] ?? 30), (int) config('sandbox.timeout_max')),
            memoryMb: min((int) ($config['memory_mb'] ?? 128), (int) config('sandbox.memory_max')),
            network: ($config['network'] ?? RunSpec::NETWORK_NONE) === RunSpec::NETWORK_INTERNET ? RunSpec::NETWORK_INTERNET : RunSpec::NETWORK_NONE,
        );
    }
}
