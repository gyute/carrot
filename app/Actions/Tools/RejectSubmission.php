<?php

namespace App\Actions\Tools;

use App\Enums\SubmissionStatus;
use App\Events\ToolSubmissionReviewed;
use App\Models\ToolSubmission;
use App\Models\User;

class RejectSubmission
{
    public function handle(ToolSubmission $submission, User $reviewer, string $comment): void
    {
        $submission->forceFill([
            'status' => SubmissionStatus::Rejected,
            'reviewer_id' => $reviewer->id,
            'review_comment' => $comment,
            'reviewed_at' => now(),
        ])->save();

        ToolSubmissionReviewed::dispatch($submission);
    }
}
