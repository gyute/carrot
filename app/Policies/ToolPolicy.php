<?php

namespace App\Policies;

use App\Enums\ToolKind;
use App\Models\Tool;
use App\Models\User;

class ToolPolicy
{
    /**
     * Running tools are public; a deprecated one is still visible so its page
     * can explain what replaced it.
     */
    public function view(User $user, Tool $tool): bool
    {
        return true;
    }

    public function run(User $user, Tool $tool): bool
    {
        return $tool->isRunning() && $tool->kind === ToolKind::Script;
    }

    /**
     * Display fields (name, summary, description, icon, tags) change without
     * review, so the owner edits them in place.
     */
    public function updateMetadata(User $user, Tool $tool): bool
    {
        return $this->owns($user, $tool);
    }

    /**
     * Behaviour changes go through a submission; the owner may open one.
     */
    public function submitChange(User $user, Tool $tool): bool
    {
        return $this->owns($user, $tool);
    }

    /**
     * Admins change a tool immediately, without a submission.
     */
    public function manage(User $user, Tool $tool): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Tool $tool): bool
    {
        return $user->isAdmin();
    }

    private function owns(User $user, Tool $tool): bool
    {
        return $user->isAdmin() || $tool->owner_id === $user->id;
    }
}
