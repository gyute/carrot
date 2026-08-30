<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $username
 * @property string $email
 * @property UserRole $role
 * @property string|null $department
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'username', 'email', 'password', 'role', 'department'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUlids, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * The ULID is the handle other rows, forms and URLs point at; `id` stays
     * the primary key, as on every other model here. The login ID is not a
     * handle: an identity provider owns it once SSO is in place.
     */
    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'role' => UserRole::class,
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isManager(): bool
    {
        return $this->role === UserRole::Manager;
    }

    /**
     * Whether this user gives the department-stage approval for `$department`.
     */
    public function isManagerOf(?string $department): bool
    {
        return $this->isManager() && $department !== null && $this->department === $department;
    }

    /**
     * Whether this user reviews anything at all: the approvals tab shows for them.
     */
    public function isReviewer(): bool
    {
        return $this->isAdmin() || $this->isManager();
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeManagersOf(Builder $query, ?string $department): void
    {
        $query->where('role', UserRole::Manager)->where('department', $department ?? '');
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeAdmins(Builder $query): void
    {
        $query->where('role', UserRole::Admin);
    }

    /**
     * Who triages tool requests and may be assigned one. Today that is the
     * administrators; when a `developer` role arrives this scope and
     * ToolRequestPolicy::triage() are the pair that changes.
     *
     * @param  Builder<User>  $query
     */
    public function scopeDevelopmentTeam(Builder $query): void
    {
        $query->admins();
    }
}
