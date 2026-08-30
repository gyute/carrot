<?php

namespace App\Policies;

use App\Models\ToolRequest;
use App\Models\User;
use App\Support\Features;

class ToolRequestPolicy
{
    /**
     * A request is its department's business, not the whole company's: what
     * someone writes about their own work should not be a notice board.
     * A request with no department has no colleagues to share it with, so it
     * stays with its requester and the development team.
     *
     * Kept in step with ToolRequest::scopeVisibleTo, which does the same job
     * for lists.
     */
    public function view(User $user, ToolRequest $toolRequest): bool
    {
        return $user->isAdmin()
            || $toolRequest->user_id === $user->id
            || ($toolRequest->department !== null && $toolRequest->department === $user->department);
    }

    public function create(User $user): bool
    {
        return Features::requests();
    }

    public function update(User $user, ToolRequest $toolRequest): bool
    {
        return Features::requests()
            && $toolRequest->user_id === $user->id
            && $toolRequest->status->isEditable();
    }

    public function withdraw(User $user, ToolRequest $toolRequest): bool
    {
        return $toolRequest->user_id === $user->id && $toolRequest->status->isLive();
    }

    /**
     * The development team's side. Admins stand in for it today; when a
     * `developer` role arrives, this is the only method that changes.
     */
    public function triage(User $user, ToolRequest $toolRequest): bool
    {
        return $user->isAdmin() && $toolRequest->status->isLive();
    }
}
