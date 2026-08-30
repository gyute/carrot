<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Roles are not editable from the UI yet, so reviewers are made from the
 * shell: `carrot:promote paku --role=manager --department=開発`.
 */
class PromoteUser extends Command
{
    protected $signature = 'carrot:promote {username : The login ID}
                            {--role=admin : member, manager or admin}
                            {--department= : The department a manager approves for (also set on any role)}
                            {--revoke : Shorthand for --role=member}';

    protected $description = 'Set a user\'s role (member / manager / admin) and department';

    public function handle(): int
    {
        $user = User::query()->where('username', mb_strtolower(trim($this->argument('username'))))->first();

        if ($user === null) {
            $this->error('No user with that username.');

            return self::FAILURE;
        }

        $role = $this->option('revoke') ? UserRole::Member : UserRole::tryFrom((string) $this->option('role'));

        if ($role === null) {
            $this->error('Role must be member, manager or admin.');

            return self::FAILURE;
        }

        $department = $this->option('department');

        if ($role === UserRole::Manager && ($department === null || $department === '') && $user->department === null) {
            $this->error('A manager needs --department.');

            return self::FAILURE;
        }

        $user->role = $role;

        if (is_string($department) && $department !== '') {
            $user->department = $department;
        }

        $user->save();

        $this->info("{$user->username} is now {$user->role->value}".($user->department ? " ({$user->department})" : '').'.');

        return self::SUCCESS;
    }
}
