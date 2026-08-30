<?php

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Models\Message;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Retires an account: the person goes, the record of what they did stays.
 *
 * Deleting the row outright used to take the approval history with it - and
 * refused outright once a tool pointed at one of their submissions - so this
 * splits the two. What is personal is deleted; what the organisation needs to
 * be able to read back keeps pointing at a row that is still there, now
 * carrying nobody's name.
 */
class RetireUser
{
    /** What the history shows in place of the person. */
    public const NAME = '退職したユーザー';

    /**
     * Hands the tools they looked after to `$successor`, deletes what was
     * private to them, and scrubs the row.
     *
     * @return User|null the successor the tools ended up with
     */
    public function handle(User $user, ?User $successor = null): ?User
    {
        $successor ??= $this->successorFor($user);

        DB::transaction(function () use ($user, $successor): void {
            // A tool nobody owns can only be touched by an administrator, so
            // every departure would otherwise leave one more behind.
            //
            // Saved one at a time on purpose: a query-builder update writes
            // the rows without raising a model event, and the mirror listens
            // for those. A departure moves a handful of tools at most.
            Tool::query()->where('owner_id', $user->id)->each(
                fn (Tool $tool) => $tool->forceFill(['owner_id' => $successor?->id])->save()
            );

            $this->forget($user);

            $user->forceFill([
                'name' => self::NAME,
                // Frees the login ID for reuse and keeps the unique index happy.
                'username' => 'retired-'.$user->id,
                // .invalid is reserved by RFC 2606: it can never be delivered to.
                'email' => 'retired-'.$user->id.'@invalid',
                'password' => Hash::make(Str::random(64)),
                'remember_token' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'email_verified_at' => null,
                'department' => null,
                'role' => UserRole::Member,
            ])->save();

            $user->delete();
        });

        return $successor;
    }

    /**
     * The half that belongs to the person rather than to the company: their
     * inbox, their bell, and the credentials that would let them back in.
     */
    private function forget(User $user): void
    {
        // Notifications hang off no foreign key, so nothing else would.
        DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->delete();

        Message::query()->where('recipient_id', $user->id)->delete();
        $user->passkeys()->delete();
    }

    /**
     * Who looks after their tools now: the manager of their department, or an
     * administrator. Only used when the caller did not name one.
     */
    private function successorFor(User $user): ?User
    {
        return User::query()->managersOf($user->department)->whereKeyNot($user->id)->first()
            ?? User::query()->admins()->whereKeyNot($user->id)->first();
    }
}
