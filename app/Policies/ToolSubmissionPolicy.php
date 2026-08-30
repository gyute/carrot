<?php

namespace App\Policies;

use App\Models\ToolSubmission;
use App\Models\User;

class ToolSubmissionPolicy
{
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
}
