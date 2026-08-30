<?php

use App\Enums\ToolRequestStatus;
use App\Enums\ToolStatus;
use App\Enums\UserRole;
use App\Models\Tool;
use App\Models\ToolRequest;
use App\Models\ToolSubmission;
use App\Models\User;

/**
 * A smoke test for `demo:seed`, not a check on the demo's contents: it reads
 * demo/tools.php and asserts what holds whatever anyone puts in there, so
 * adding or renaming a demo tool never breaks this file.
 *
 * @return array{department: string, tools: array<int, array<string, mixed>>, requests?: array<int, array<string, mixed>>}
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
        ->and($admin->role)->toBe(UserRole::Admin)
        ->and(ToolSubmission::query()->count())->toBe(count($definition['tools']));

    foreach ($definition['tools'] as $entry) {
        $name = $entry['name'];

        if (($entry['state'] ?? 'published') !== 'published') {
            expect(Tool::withTrashed()->where('name', $name)->exists())->toBeFalse()
                ->and(ToolSubmission::query()->where('payload->name', $name)->sole()->status->isAwaitingReview())->toBeTrue();

            continue;
        }

        $tool = Tool::query()->where('name', $name)->sole();

        // Published means both stages ran, not that a row was written.
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

    expect(Tool::query()->count())->toBeGreaterThan(0);
});

test('the demo asks are filed, and the tool answering one closes it', function () {
    $definition = demoDefinition();
    $requests = $definition['requests'] ?? [];

    $this->artisan('demo:seed')->assertSuccessful();

    $requester = User::query()->where('username', 'demo')->sole();

    expect(ToolRequest::query()->count())->toBe(count($requests));

    foreach ($requests as $entry) {
        $toolRequest = ToolRequest::query()->where('title', $entry['title'])->sole();

        expect($toolRequest->user_id)->toBe($requester->id)
            ->and($toolRequest->department)->toBe($definition['department']);
    }

    // A tool that names an ask delivers it by being approved, rather than
    // being wired to it by hand - the same path a real request takes.
    foreach ($definition['tools'] as $entry) {
        if (! isset($entry['answers'])) {
            continue;
        }

        $toolRequest = ToolRequest::query()->where('title', $entry['answers'])->sole();

        expect($toolRequest->status)->toBe(ToolRequestStatus::Delivered)
            ->and($toolRequest->tool?->name)->toBe($entry['name']);
    }
});

test('seeding twice changes nothing, and --fresh starts over', function () {
    $this->artisan('demo:seed')->assertSuccessful();

    $before = Tool::query()->pluck('id')->sort()->values();
    $askCount = ToolRequest::query()->count();

    $this->artisan('demo:seed')->assertSuccessful();

    expect(Tool::query()->pluck('id')->sort()->values())->toEqual($before)
        ->and(ToolRequest::query()->count())->toBe($askCount);

    $this->artisan('demo:seed', ['--fresh' => true])->assertSuccessful();

    // Same catalog, brand new rows: --fresh really did delete and republish.
    expect(Tool::query()->count())->toBe($before->count())
        ->and(Tool::query()->pluck('id')->intersect($before)->all())->toBe([])
        ->and(ToolRequest::query()->count())->toBe($askCount);
});

test('the demo refuses to seed a production install', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('demo:seed')->assertFailed();

    expect(Tool::query()->count())->toBe(0);

    $this->artisan('demo:seed', ['--force' => true])->assertSuccessful();

    expect(Tool::query()->count())->toBeGreaterThan(0);
});
