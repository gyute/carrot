<?php

namespace App\Actions\Tools;

use App\Enums\ToolRequestStatus;
use App\Events\ToolRequestDelivered;
use App\Models\Tool;
use App\Models\ToolRequest;
use App\Models\User;

/**
 * Closes a request with the tool that answers it. Called from the triage
 * screen, and from ApproveSubmission when the approved submission was filed
 * against a request - so publishing the tool is what closes it.
 */
class DeliverToolRequest
{
    public function handle(ToolRequest $toolRequest, Tool $tool, ?User $decider = null): void
    {
        if ($toolRequest->status === ToolRequestStatus::Delivered) {
            return;
        }

        $toolRequest->forceFill([
            'status' => ToolRequestStatus::Delivered,
            'tool_id' => $tool->id,
            'decided_by' => $decider === null ? $toolRequest->decided_by : $decider->id,
            'decided_at' => now(),
        ])->save();

        ToolRequestDelivered::dispatch($toolRequest);
    }
}
