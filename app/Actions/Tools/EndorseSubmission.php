<?php

namespace App\Actions\Tools;

use App\Enums\SubmissionStatus;
use App\Events\ToolSubmissionEndorsed;
use App\Models\ToolSubmission;
use App\Models\User;

/**
 * The department stage: the manager vouches for the request and it moves on
 * to the system administrators, who publish it.
 */
class EndorseSubmission
{
    public function handle(ToolSubmission $submission, User $manager, ?string $comment = null): void
    {
        $submission->forceFill([
            'status' => SubmissionStatus::Endorsed,
            'endorsed_by' => $manager->id,
            'endorse_comment' => $comment,
            'endorsed_at' => now(),
        ])->save();

        ToolSubmissionEndorsed::dispatch($submission);
    }
}
