<?php

namespace App\Support\Presenters;

use App\Models\ToolRun;
use App\Sandbox\RuntimeLabels;

class ToolRunPresenter
{
    public function __construct(private RuntimeLabels $runtimes) {}

    /**
     * @return array{ulid: string, status: string, statusLabel: string, finished: bool, runtime: string, runtimeLabel: string, inputs: array<string, mixed>, exitCode: int|null, stdout: string|null, stderr: string|null, truncated: bool, durationMs: int|null, errorMessage: string|null, requestedBy: string, createdAt: string, startedAt: string|null, finishedAt: string|null}
     */
    public function present(ToolRun $run): array
    {
        return [
            'ulid' => $run->ulid,
            'status' => $run->status->value,
            'statusLabel' => $run->status->label(),
            'finished' => $run->isFinished(),
            'runtime' => $run->runtime,
            'runtimeLabel' => $this->runtimes->for($run->runtime),
            'inputs' => $run->inputs,
            'exitCode' => $run->exit_code,
            'stdout' => $run->stdout,
            'stderr' => $run->stderr,
            'truncated' => $run->truncated,
            'durationMs' => $run->duration_ms,
            'errorMessage' => $run->error_message,
            'requestedBy' => $run->user->name,
            'createdAt' => $run->created_at?->toIso8601String() ?? '',
            'startedAt' => $run->started_at?->toIso8601String(),
            'finishedAt' => $run->finished_at?->toIso8601String(),
        ];
    }
}
