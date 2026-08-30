<?php

namespace App\Support\Presenters;

use App\Http\Requests\Tools\ToolSubmissionRequest;
use App\Models\Tool;
use App\Models\ToolSubmission;

/**
 * The submission shapes handed to the requester's and the admin's screens.
 * Both sides render the same request, so both read the same arrays.
 */
class SubmissionPresenter
{
    /**
     * @return array{ulid: string, action: string, actionLabel: string, status: string, statusLabel: string, name: string, kind: string|null, requester: string, department: string|null, tool: array{ulid: string, name: string, slug: string}|null, note: string|null, endorser: string|null, endorseComment: string|null, endorsedAt: string|null, reviewer: string|null, reviewComment: string|null, submittedAt: string|null, reviewedAt: string|null, createdAt: string}
     */
    public function summary(ToolSubmission $submission): array
    {
        $kind = $submission->payload['kind'] ?? $submission->tool?->kind->value;

        return [
            'ulid' => $submission->ulid,
            'action' => $submission->action->value,
            'actionLabel' => $submission->action->label(),
            'status' => $submission->status->value,
            'statusLabel' => $submission->status->label(),
            'name' => $submission->displayName(),
            'kind' => is_string($kind) ? $kind : null,
            'requester' => $submission->user->name,
            'department' => $submission->department(),
            'tool' => $submission->tool === null ? null : [
                'ulid' => $submission->tool->ulid,
                'name' => $submission->tool->name,
                'slug' => $submission->tool->slug,
            ],
            'note' => $submission->note,
            'endorser' => $submission->endorser?->name,
            'endorseComment' => $submission->endorse_comment,
            'endorsedAt' => $submission->endorsed_at?->toIso8601String(),
            'reviewer' => $submission->reviewer?->name,
            'reviewComment' => $submission->review_comment,
            'submittedAt' => $submission->submitted_at?->toIso8601String(),
            'reviewedAt' => $submission->reviewed_at?->toIso8601String(),
            'createdAt' => $submission->created_at?->toIso8601String() ?? '',
        ];
    }

    /**
     * The summary plus the payload and, for a change request, what the tool
     * currently does so the two can be compared.
     *
     * @return array<string, mixed>
     */
    public function detail(ToolSubmission $submission): array
    {
        return [
            ...$this->summary($submission),
            'payload' => $submission->payload,
            'current' => $submission->tool === null ? null : $this->payloadFromTool($submission->tool),
            'runtimes' => $this->runtimes(),
        ];
    }

    /**
     * What the configured images run scripts with, for showing to people who
     * write or read them.
     *
     * @return array<string, string>
     */
    private function runtimes(): array
    {
        return array_map('strval', (array) config('sandbox.runtimes', []));
    }

    /**
     * @return array{ulid: string, name: string, slug: string, kind: string, status: string}
     */
    public function toolSummary(Tool $tool): array
    {
        return [
            'ulid' => $tool->ulid,
            'name' => $tool->name,
            'slug' => $tool->slug,
            'kind' => $tool->kind->value,
            'status' => $tool->status->value,
        ];
    }

    /**
     * A tool expressed as a submission payload: what a change request starts
     * from, and what an admin compares a change against.
     *
     * @return array<string, mixed>
     */
    public function payloadFromTool(Tool $tool): array
    {
        return [
            'kind' => $tool->kind->value,
            'name' => $tool->name,
            'summary' => $tool->summary,
            'description' => $tool->description,
            'icon' => $tool->icon,
            'accent' => $tool->accent,
            'department' => $tool->department,
            'categories' => $tool->categories(),
            'config' => $tool->config,
            'source' => $tool->source,
        ];
    }

    /**
     * @return array{icons: array<int, string>, accents: array<int, string>, departments: array<int, string>, runtimes: array<string, string>, timeoutMax: int, memoryMax: int, sourceBytes: int}
     */
    public function limits(): array
    {
        return [
            'icons' => Tool::ICONS,
            'accents' => Tool::ACCENTS,
            'departments' => array_values((array) config('catalog.departments', [])),
            'runtimes' => $this->runtimes(),
            'timeoutMax' => (int) config('sandbox.timeout_max'),
            'memoryMax' => (int) config('sandbox.memory_max'),
            'sourceBytes' => ToolSubmissionRequest::MAX_SOURCE_BYTES,
        ];
    }
}
