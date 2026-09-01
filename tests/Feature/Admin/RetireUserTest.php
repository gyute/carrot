<?php

use App\Actions\Users\RetireUser;
use App\Models\Message;
use App\Models\Tool;
use App\Models\ToolSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('retiring keeps what the person did and removes what was theirs', function () {
    $leaver = User::factory()->create([
        'department' => '営業',
        'name' => '森',
        'catalog_filters' => ['department' => ['営業']],
    ]);
    $successor = User::factory()->manager('営業')->create();
    $tool = Tool::factory()->create(['owner_id' => $leaver->id]);
    $submission = ToolSubmission::factory()->approved()->create(['user_id' => $leaver->id, 'tool_id' => $tool->id]);
    Message::factory()->create(['recipient_id' => $leaver->id]);

    app(RetireUser::class)->handle($leaver, $successor);

    $retired = User::withTrashed()->find($leaver->id);

    // The person is gone from the row, but the row is still there.
    expect($retired->trashed())->toBeTrue()
        ->and($retired->name)->toBe(RetireUser::NAME)
        ->and($retired->username)->not->toBe('森')
        ->and($retired->email)->toEndWith('@invalid')
        ->and($retired->two_factor_secret)->toBeNull()
        ->and($retired->catalog_filters)->toBeNull();

    // What the organisation needs to read back survives, and still names someone.
    expect($submission->fresh()->user->name)->toBe(RetireUser::NAME)
        ->and(Tool::query()->find($tool->id)->owner->is($successor))->toBeTrue();

    // What was private does not.
    expect(Message::query()->where('recipient_id', $leaver->id)->count())->toBe(0)
        ->and(DB::table('notifications')->where('notifiable_id', $leaver->id)->count())->toBe(0);
});

test('a retired account cannot sign in and is not offered as a reviewer', function () {
    $leaver = User::factory()->admin()->create();
    User::factory()->admin()->create();

    app(RetireUser::class)->handle($leaver);

    $provider = auth()->guard('web')->getProvider();

    expect($provider->retrieveById($leaver->id))->toBeNull()
        ->and(User::query()->admins()->pluck('id')->contains($leaver->id))->toBeFalse();
});

test('tools fall to the department manager when no successor is named', function () {
    $manager = User::factory()->manager('経理')->create();
    $leaver = User::factory()->create(['department' => '経理']);
    $tool = Tool::factory()->create(['owner_id' => $leaver->id]);

    expect(app(RetireUser::class)->handle($leaver)?->is($manager))->toBeTrue()
        ->and($tool->fresh()->owner->is($manager))->toBeTrue();
});

test('an admin retires someone from the user screen', function () {
    $admin = User::factory()->admin()->create();
    $leaver = User::factory()->create();
    $successor = User::factory()->admin()->create();
    $tool = Tool::factory()->create(['owner_id' => $leaver->id]);

    $this->actingAs($admin)
        ->delete(route('admin.users.retire', $leaver), ['successor' => $successor->ulid])
        ->assertRedirect();

    expect(User::withTrashed()->find($leaver->id)->trashed())->toBeTrue()
        ->and($tool->fresh()->owner->is($successor))->toBeTrue();

    // And they stay visible to an admin, marked as gone.
    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertInertia(fn ($page) => $page->where(
            'users.data.'.User::withTrashed()->orderBy('username')->pluck('id')->search($leaver->id).'.retired',
            true,
        ));
});

test('an admin cannot retire their own account from the user screen', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.users.retire', $admin))
        ->assertStatus(422);

    $this->actingAs($admin)
        ->delete(route('admin.users.retire', $other), ['successor' => 'not-a-ulid'])
        ->assertSessionHasErrors('successor');

    expect(User::query()->count())->toBe(2);
});

test('the last admin cannot close their own account either', function () {
    $sole = User::factory()->admin()->create(['password' => 'password']);

    $this->actingAs($sole)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertStatus(422);

    expect(User::query()->admins()->count())->toBe(1);

    // With a second admin in place it goes through.
    User::factory()->admin()->create();

    $this->actingAs($sole)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect('/');

    expect(User::query()->admins()->count())->toBe(1)
        ->and(User::withTrashed()->find($sole->id)->trashed())->toBeTrue();
});

test('members cannot retire anyone', function () {
    $this->actingAs(User::factory()->create())
        ->delete(route('admin.users.retire', User::factory()->create()))
        ->assertForbidden();
});
