<?php

namespace App\Actions\Tools;

use App\Enums\ToolRequestPriority;
use App\Enums\ToolRequestStatus;
use App\Events\ToolRequestTriaged;
use App\Models\ToolRequest;
use App\Models\User;

/**
 * The development team's side of a request: taking it on, starting it,
 * turning it down or folding it into another one. Every path stamps who
 * decided and tells the requester.
 *
 * Delivering is not here - that is DeliverToolRequest, because approving a
 * submission does it too.
 */
class TriageToolRequest
{
    public function accept(
        ToolRequest $toolRequest,
        User $decider,
        ?string $comment = null,
        ?ToolRequestPriority $priority = null,
        ?User $assignee = null,
    ): void {
        $this->decide($toolRequest, $decider, ToolRequestStatus::Accepted, $comment, [
            'priority' => $priority ?? $toolRequest->priority ?? ToolRequestPriority::Normal,
            'assignee_id' => $assignee === null ? $toolRequest->assignee_id : $assignee->id,
        ]);
    }

    public function start(ToolRequest $toolRequest, User $decider, ?string $comment = null): void
    {
        $this->decide($toolRequest, $decider, ToolRequestStatus::InProgress, $comment);
    }

    public function decline(ToolRequest $toolRequest, User $decider, string $comment): void
    {
        $this->decide($toolRequest, $decider, ToolRequestStatus::Declined, $comment);
    }

    public function duplicate(ToolRequest $toolRequest, User $decider, ToolRequest $original, ?string $comment = null): void
    {
        $this->decide($toolRequest, $decider, ToolRequestStatus::Duplicate, $comment, [
            'duplicate_of_id' => $original->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function decide(
        ToolRequest $toolRequest,
        User $decider,
        ToolRequestStatus $status,
        ?string $comment,
        array $extra = [],
    ): void {
        $toolRequest->forceFill([
            ...$extra,
            'status' => $status,
            'decided_by' => $decider->id,
            'decision_comment' => $comment === '' ? null : $comment,
            'decided_at' => now(),
        ])->save();

        ToolRequestTriaged::dispatch($toolRequest);
    }
}
