<?php

use App\Enums\SubmissionStatus;
use App\Enums\ToolStatus;
use App\Enums\UserRole;
use App\Models\Tool;
use App\Models\ToolSubmission;
use App\Models\User;

/**
 * A smoke test for `demo:seed`, not a check on the demo's contents: it reads
 * demo/tools.php and asserts what must hold whatever anyone puts in there.
 * Adding or renaming a demo tool must never break this file.
 *
 * @return array{department: string, tools: array<int, array<string, mixed>>}
 */
function demoDefinition(): array
{
    return require base_path('demo/tools.php');
}

test('the demo catalog is published through the real approval flow', function () {
    $definition = demoDefinition();

    $this->artisan('demo:seed')->assertSuccessful();

    $manager = User::query()->where('username', 'demo-manager')->sole();
    $admin = User::query()->where('username', 'demo-admin')->sole();

    expect(User::query()->where('username', 'demo')->sole()->role)->toBe(UserRole::Member)
        ->and($manager->role)->toBe(UserRole::Manager)
        ->and($manager->department)->toBe($definition['department'])
        ->and($admin->role)->toBe(UserRole::Admin);

    foreach ($definition['tools'] as $entry) {
        $name = $entry['name'];

        if (($entry['state'] ?? 'published') !== 'published') {
            expect(Tool::withTrashed()->where('name', $name)->exists())->toBeFalse();

            $waiting = ToolSubmission::query()->where('payload->name', $name)->sole();

            expect($waiting->status->isAwaitingReview())->toBeTrue();

            continue;
        }

        $tool = Tool::query()->where('name', $name)->sole();

        // Published means both stages actually ran, not that a row was written.
        expect($tool->status)->toBe(ToolStatus::Running)
            ->and($tool->kind->value)->toBe($entry['kind'])
            ->and($tool->department)->toBe($definition['department'])
            ->and($tool->version)->not->toBeNull()
            ->and($tool->endorsed_by)->toBe($manager->id)
            ->and($tool->approved_by)->toBe($admin->id)
            ->and($tool->categories())->toBe($entry['categories'] ?? []);

        if (isset($entry['source'])) {
            $source = file_get_contents(base_path('demo/'.$entry['source']));

            expect($tool->source)->toBe($source)
                ->and($tool->source_hash)->toBe(hash('sha256', (string) $source));
        }
    }
});

test('every demo entry ends up in the catalog or in the approval queue', function () {
    $definition = demoDefinition();

    $this->artisan('demo:seed')->assertSuccessful();

    $published = collect($definition['tools'])->filter(fn (array $entry): bool => ($entry['state'] ?? 'published') === 'published');

    expect(Tool::query()->count())->toBe($published->count())
        ->and(ToolSubmission::query()->count())->toBe(count($definition['tools']))
        ->and($published)->not->toBeEmpty();

    $this->actingAs(User::factory()->create())
        ->get(route('tools.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('tools', $published->count()));
});

test('seeding twice changes nothing, and --fresh starts over', function () {
    $this->artisan('demo:seed')->assertSuccessful();

    $before = Tool::query()->pluck('id')->sort()->values();

    $this->artisan('demo:seed')->assertSuccessful();

    expect(Tool::query()->pluck('id')->sort()->values())->toEqual($before);

    $this->artisan('demo:seed', ['--fresh' => true])->assertSuccessful();

    // Same catalog, brand new rows: --fresh really did delete and republish.
    expect(Tool::query()->count())->toBe($before->count())
        ->and(Tool::query()->pluck('id')->intersect($before)->all())->toBe([]);
});

test('a demo script tool can be run once it is published', function () {
    $this->artisan('demo:seed')->assertSuccessful();

    $script = Tool::query()->where('kind', 'script')->first();

    expect($script)->not->toBeNull();

    $this->actingAs(User::query()->where('username', 'demo')->sole())
        ->post(route('tools.runs.store', $script))
        ->assertRedirect();
});

test('the demo refuses to seed a production install', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('demo:seed')->assertFailed();

    expect(Tool::query()->count())->toBe(0);

    $this->artisan('demo:seed', ['--force' => true])->assertSuccessful();

    expect(Tool::query()->count())->toBeGreaterThan(0);
});

test('a pending demo entry gives the approval screens something to show', function () {
    $this->artisan('demo:seed')->assertSuccessful();

    $waiting = ToolSubmission::query()->whereIn('status', [SubmissionStatus::Pending, SubmissionStatus::Endorsed])->count();

    $this->actingAs(User::query()->where('username', 'demo-admin')->sole())
        ->get(route('admin.approvals.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('pending', $waiting));
});
