<?php

use App\Enums\UserRole;
use App\Models\User;

test('the user screen is for admins only', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs(User::factory()->manager('開発')->create())->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.users.index'))->assertOk();
});

test('the list searches and filters by role', function () {
    $admin = User::factory()->admin()->create(['username' => 'zadmin']);
    User::factory()->create(['username' => 'mori', 'name' => '森', 'department' => '経理']);
    User::factory()->manager('開発')->create(['username' => 'lead']);

    $this->actingAs($admin)->get(route('admin.users.index', ['q' => '経理']))
        ->assertInertia(fn ($page) => $page
            ->component('admin/users/index')
            ->has('users.data', 1)
            ->where('users.data.0.username', 'mori')
        );

    $this->actingAs($admin)->get(route('admin.users.index', ['role' => 'manager']))
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.username', 'lead')
            ->where('users.data.0.department', '開発')
        );
});

test('a user is addressed by ULID, never by the row id', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    expect(route('admin.users.update', $user))->toContain($user->ulid);

    $this->actingAs($admin)
        ->patch("/admin/users/{$user->id}", ['role' => 'manager', 'department' => '開発'])
        ->assertNotFound();

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertInertia(fn ($page) => $page->where('users.data.0.ulid', User::query()->orderBy('username')->first()?->ulid));
});

test('an admin sets a role and a department in place', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $user), ['role' => 'manager', 'department' => '開発'])
        ->assertRedirect();

    $user->refresh();

    expect($user->role)->toBe(UserRole::Manager)
        ->and($user->department)->toBe('開発')
        ->and($user->isManagerOf('開発'))->toBeTrue();
});

test('a manager without a department is rejected, and clearing one is allowed', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->manager('開発')->create();

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->patch(route('admin.users.update', $user), ['role' => 'manager', 'department' => ''])
        ->assertSessionHasErrors('department');

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $user), ['role' => 'member', 'department' => ''])
        ->assertRedirect();

    expect($user->fresh()?->role)->toBe(UserRole::Member)
        ->and($user->fresh()?->department)->toBeNull();
});

test('the department allowlist is enforced when one is configured', function () {
    config(['catalog.departments' => ['開発', '総務']]);

    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->patch(route('admin.users.update', $user), ['role' => 'manager', 'department' => '営業'])
        ->assertSessionHasErrors('department');

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $user), ['role' => 'manager', 'department' => '総務'])
        ->assertRedirect();

    expect($user->fresh()?->department)->toBe('総務');
});

test('a member cannot promote themselves', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('admin.users.update', $user), ['role' => 'admin'])
        ->assertForbidden();

    expect($user->fresh()?->role)->toBe(UserRole::Member);
});
