<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('users are members by default and admins pass the admin gate', function () {
    $member = User::factory()->create();
    $admin = User::factory()->admin()->create();

    expect($member->role)->toBe(UserRole::Member)
        ->and($member->isAdmin())->toBeFalse()
        ->and(Gate::forUser($member)->allows('admin'))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('admin'))->toBeTrue()
        ->and(User::query()->admins()->pluck('id')->all())->toBe([$admin->id]);
});

test('the promote command grants and revokes the admin role', function () {
    $user = User::factory()->create(['username' => 'paku']);

    $this->artisan('carrot:promote', ['username' => 'PAKU'])->assertSuccessful();
    expect($user->fresh()?->role)->toBe(UserRole::Admin);

    $this->artisan('carrot:promote', ['username' => 'paku', '--revoke' => true])->assertSuccessful();
    expect($user->fresh()?->role)->toBe(UserRole::Member);

    $this->artisan('carrot:promote', ['username' => 'nobody'])->assertFailed();
});

test('a manager only endorses for their own department', function () {
    $manager = User::factory()->manager('開発')->create();
    $admin = User::factory()->admin()->create();

    expect($manager->isManagerOf('開発'))->toBeTrue()
        ->and($manager->isManagerOf('総務'))->toBeFalse()
        ->and($manager->isManagerOf(null))->toBeFalse()
        ->and($manager->isReviewer())->toBeTrue()
        ->and($admin->isReviewer())->toBeTrue()
        ->and(User::factory()->create()->isReviewer())->toBeFalse()
        ->and(Gate::forUser($manager)->allows('reviewer'))->toBeTrue()
        ->and(User::query()->managersOf('開発')->pluck('id')->all())->toBe([$manager->id]);
});

test('the promote command sets a manager with a department', function () {
    $user = User::factory()->create(['username' => 'mori']);

    $this->artisan('carrot:promote', ['username' => 'mori', '--role' => 'manager'])->assertFailed();

    $this->artisan('carrot:promote', ['username' => 'mori', '--role' => 'manager', '--department' => '開発'])
        ->assertSuccessful();

    expect($user->fresh()?->role)->toBe(UserRole::Manager)
        ->and($user->fresh()?->department)->toBe('開発');
});
