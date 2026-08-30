<?php

namespace App\Support\Presenters;

use App\Enums\ToolKind;
use App\Models\ToolRequest;
use App\Support\Departments;

/**
 * The request shapes handed to the requester's and the development team's
 * screens. Both sides render the same request, so both read the same arrays.
 */
class ToolRequestPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function summary(ToolRequest $toolRequest): array
    {
        return [
            'ulid' => $toolRequest->ulid,
            'status' => $toolRequest->status->value,
            'statusLabel' => $toolRequest->status->label(),
            'title' => $toolRequest->title,
            'requester' => $toolRequest->user->name,
            'department' => $toolRequest->department,
            'categories' => $toolRequest->categories,
            'desiredKind' => $toolRequest->desired_kind?->value,
            'desiredKindLabel' => $toolRequest->desired_kind?->label(),
            'neededBy' => $toolRequest->needed_by?->toDateString(),
            'priority' => $toolRequest->priority?->value,
            'priorityLabel' => $toolRequest->priority?->label(),
            'assignee' => $toolRequest->assignee?->name,
            'decider' => $toolRequest->decider?->name,
            'decisionComment' => $toolRequest->decision_comment,
            'decidedAt' => $toolRequest->decided_at?->toIso8601String(),
            'createdAt' => $toolRequest->created_at?->toIso8601String() ?? '',
            'tool' => $toolRequest->tool === null ? null : [
                'ulid' => $toolRequest->tool->ulid,
                'name' => $toolRequest->tool->name,
            ],
            'duplicateOf' => $toolRequest->duplicateOf === null ? null : [
                'ulid' => $toolRequest->duplicateOf->ulid,
                'title' => $toolRequest->duplicateOf->title,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(ToolRequest $toolRequest): array
    {
        return [
            ...$this->summary($toolRequest),
            'body' => $toolRequest->body,
        ];
    }

    /**
     * What the form offers. The department is not here: it is stamped from
     * the requester, so the form only reports which one it will be.
     *
     * @return array<string, mixed>
     */
    public function limits(?string $department): array
    {
        return [
            'department' => $department,
            'departments' => Departments::all(),
            'kinds' => array_map(
                fn (ToolKind $kind): array => ['value' => $kind->value, 'label' => $kind->label()],
                ToolKind::cases(),
            ),
        ];
    }
}
