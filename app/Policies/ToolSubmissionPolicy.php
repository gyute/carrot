<?php

namespace App\Policies;

use App\Enums\SubmissionStatus;
use App\Models\ToolSubmission;
use App\Models\User;
use App\Support\Features;

class ToolSubmissionPolicy
{
    /**
     * Whether this deployment lets this person register a tool at all. In
     * `admin` submission mode only the development team may.
     */
    public function create(User $user): bool
    {
        return Features::maySubmit($user);
    }

    public function view(User $user, ToolSubmission $submission): bool
    {
        return $user->isAdmin()
            || $submission->user_id === $user->id
            || $user->isManagerOf($submission->department());
    }

    public function update(User $user, ToolSubmission $submission): bool
    {
        return $submission->user_id === $user->id && $submission->status->isEditable();
    }

    public function submit(User $user, ToolSubmission $submission): bool
    {
        return $this->update($user, $submission);
    }

    public function withdraw(User $user, ToolSubmission $submission): bool
    {
        return $submission->user_id === $user->id && $submission->status->isOpen();
    }

    /**
     * First stage: the department's manager (or an admin). Second stage:
     * an admin only.
     */
    public function review(User $user, ToolSubmission $submission): bool
    {
        return match ($submission->status) {
            SubmissionStatus::Pending => $user->isAdmin() || $user->isManagerOf($submission->department()),
            SubmissionStatus::Endorsed => $user->isAdmin(),
            default => false,
        };
    }

    /**
     * Whether this reviewer's approval publishes the tool outright, rather
     * than passing it on to the system administrators.
     */
    public function finalize(User $user, ToolSubmission $submission): bool
    {
        return $user->isAdmin() && $this->review($user, $submission);
    }
}
