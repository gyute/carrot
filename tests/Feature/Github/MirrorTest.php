<?php

use App\Actions\Users\RetireUser;
use App\Jobs\MirrorToolToRepo;
use App\Models\Tool;
use App\Models\User;
use App\Support\Github\GitHub;
use App\Support\Github\ToolDocument;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

/**
 * Turns the mirror on for a test and answers the Git Data API with the shapes
 * the client reads, so the assertions are about what we send.
 */
beforeEach(function (): void {
    // A fake that does not match must fail the test rather than reach GitHub -
    // it already did once, and only a 401 gave it away.
    Http::preventStrayRequests();

    // Nothing renders through a server-side renderer here.
    config(['inertia.ssr.enabled' => false]);
});

function mirrorOn(string $newTree = 'tree-new'): void
{
    config([
        'github.repository' => 'acme/carrot-tools',
        'github.token' => 'ghp_test',
        'github.branch' => 'main',
        'github.path' => 'tools',
    ]);

    Http::fake([
        '*/git/ref/heads/main' => Http::response(['object' => ['sha' => 'head-sha']]),
        '*/git/commits/head-sha' => Http::response(['tree' => ['sha' => 'tree-base']]),
        '*/git/blobs' => Http::response(['sha' => 'blob-sha']),
        '*/git/trees' => Http::response(['sha' => $newTree]),
        '*/git/commits' => Http::response(['sha' => 'commit-sha']),
        '*/git/refs/heads/main' => Http::response(['object' => ['sha' => 'commit-sha']]),
    ]);
}

test('the mirror is off until a repository and a token are both set', function () {
    Bus::fake();

    Tool::factory()->create();

    Bus::assertNothingDispatched();

    config(['github.repository' => 'acme/carrot-tools', 'github.token' => null]);
    Tool::factory()->create();

    Bus::assertNothingDispatched();
});

test('every way a tool changes reaches the mirror', function () {
    $saved = Tool::factory()->create();
    $deleted = Tool::factory()->create();
    $restored = Tool::factory()->create();
    $purged = Tool::factory()->create();
    $restored->delete();

    Bus::fake();
    config(['github.repository' => 'acme/carrot-tools', 'github.token' => 'ghp_test']);

    $saved->forceFill(['summary' => '変えました'])->save();
    $deleted->delete();
    $restored->restore();
    $purged->forceDelete();

    foreach ([$saved, $deleted, $restored, $purged] as $tool) {
        Bus::assertDispatched(
            MirrorToolToRepo::class,
            fn (MirrorToolToRepo $job): bool => $job->ulid === $tool->ulid,
        );
    }
});

test('handing a departing owner\'s tools on reaches the mirror too', function () {
    $leaver = User::factory()->create(['department' => '営業']);
    $successor = User::factory()->manager('営業')->create();
    $tool = Tool::factory()->create(['owner_id' => $leaver->id]);

    Bus::fake();
    config(['github.repository' => 'acme/carrot-tools', 'github.token' => 'ghp_test']);

    app(RetireUser::class)->handle($leaver, $successor);

    // A query-builder update would have written the row without an event.
    Bus::assertDispatched(
        MirrorToolToRepo::class,
        fn (MirrorToolToRepo $job): bool => $job->ulid === $tool->ulid,
    );
});

test('a change lands as one commit carrying ULIDs, never names or a department', function () {
    $owner = User::factory()->create(['name' => '森', 'department' => '経理']);
    $tool = Tool::factory()->create([
        'slug' => 'tax',
        'owner_id' => $owner->id,
        'requested_by' => $owner->id,
        'department' => '経理',
        'source' => "<?php\necho 'hi';\n",
        'config' => ['runtime' => 'php', 'timeout_sec' => 10, 'memory_mb' => 64],
    ]);

    // Only from here on, so the fixtures above are not counted: the test
    // queue runs jobs inline, so creating the tool would mirror it already.
    mirrorOn();

    (new MirrorToolToRepo($tool->ulid, $tool->slug))->handle(app(GitHub::class));

    $blobs = collect(Http::recorded())
        ->filter(fn (array $pair): bool => str_contains($pair[0]->url(), '/git/blobs'))
        ->map(fn (array $pair): string => base64_decode($pair[0]->data()['content']));

    expect($blobs)->toHaveCount(2);

    $written = $blobs->implode("\n");

    expect($written)->toContain($owner->ulid)
        ->not->toContain('森')
        ->not->toContain('経理');

    // One round trip each: read the ref, read its commit, a blob per file,
    // build the tree, write the commit, move the branch.
    Http::assertSentCount(7);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/git/commits')
        && $request->method() === 'POST'
        && str_contains($request->data()['message'], 'Publish tax')
        && str_contains($request->data()['message'], "Requested-by: {$owner->ulid}"));
});

test('nothing is committed when the repository already matches', function () {
    $tool = Tool::factory()->create();

    // The tree the API builds comes back identical to the one we started from.
    mirrorOn(newTree: 'tree-base');

    (new MirrorToolToRepo($tool->ulid, $tool->slug))->handle(app(GitHub::class));

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/git/refs/heads/'));
});

test('a purged tool takes its directory with it', function () {
    mirrorOn();

    (new MirrorToolToRepo('01gone', 'retired-tool'))->handle(app(GitHub::class));

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/git/trees')) {
            return false;
        }

        $entry = $request->data()['tree'][0];

        return $entry['path'] === 'tools/retired-tool'
            && $entry['type'] === 'tree'
            && $entry['sha'] === null;
    });
});

test('a tool lives at the ULID the portal addresses it by', function () {
    config(['github.path' => 'tools']);

    // Str::slug drops Japanese entirely, so slugs would have made every tool
    // here `tool`, `tool-2`, `tool-3`. The ULID is also what /tools/{ulid}
    // uses, so a directory and a page are the same identifier.
    $tool = Tool::factory()->create(['config' => ['runtime' => 'shell'], 'source' => "echo hi\n"]);

    expect(array_keys((new ToolDocument($tool))->files()))
        ->toBe(["tools/{$tool->ulid}/tool.json", "tools/{$tool->ulid}/source.sh"]);
});

test('the system screen says whether the mirror is on and reachable', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.system.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('status.mirror.enabled', false)
            ->where('status.features.requests', true));

    config(['github.repository' => 'acme/carrot-tools', 'github.token' => 'ghp_test']);
    cache()->forget('github:check');

    // A public repository would publish the organisation's internal tooling.
    Http::fake(['*/repos/acme/carrot-tools' => Http::response(['private' => false, 'permissions' => ['push' => true]])]);

    $this->actingAs($admin)
        ->get(route('admin.system.index'))
        ->assertInertia(fn ($page) => $page
            ->where('status.mirror.enabled', true)
            ->where('status.mirror.ok', false)
            ->where('status.mirror.repository', 'acme/carrot-tools'));
});

test('a branch that is not there is refused, not created', function () {
    $tool = Tool::factory()->create();

    config([
        'github.repository' => 'acme/carrot-tools',
        'github.token' => 'ghp_test',
        'github.branch' => 'mian',
    ]);

    // 404: the repository has other branches, so this is a typo in
    // GITHUB_BRANCH. Starting one would bury it somewhere nobody looks.
    Http::fake(['*/git/ref/heads/mian' => Http::response(status: 404)]);

    expect(fn () => (new MirrorToolToRepo($tool->ulid, $tool->slug))->handle(app(GitHub::class)))
        ->toThrow(RuntimeException::class, 'no branch named `mian`');

    Http::assertSentCount(1);
    Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
});

test('a repository with no commits is started rather than refused', function () {
    $tool = Tool::factory()->create(['slug' => 'first', 'source' => null]);

    config([
        'github.repository' => 'acme/carrot-tools',
        'github.token' => 'ghp_test',
        'github.branch' => 'main',
        'github.path' => 'tools',
    ]);

    Http::fake([
        // 409 is GitHub saying the repository holds nothing at all, which no
        // typo can produce - so there is nothing to bury and no ambiguity.
        '*/git/ref/heads/main' => Http::response(['message' => 'Git Repository is empty.'], 409),
        '*/contents/README.md' => Http::response(['commit' => ['sha' => 'readme-sha']]),
        // Built on the sha the Contents API just gave back, not on one read
        // from a ref that may not be visible yet.
        '*/git/commits/readme-sha' => Http::response(['tree' => ['sha' => 'tree-base']]),
        '*/git/blobs' => Http::response(['sha' => 'blob-sha']),
        '*/git/trees' => Http::response(['sha' => 'tree-new']),
        '*/git/commits' => Http::response(['sha' => 'commit-sha']),
        '*/git/refs/heads/main' => Http::response([]),
    ]);

    (new MirrorToolToRepo($tool->ulid, $tool->slug))->handle(app(GitHub::class));

    // The Git Data API cannot write into an empty repository - even a blob is
    // refused - so the first commit goes through the Contents API.
    Http::assertSent(fn ($r) => $r->method() === 'PUT' && str_contains($r->url(), '/contents/README.md'));
    Http::assertSent(fn ($r) => $r->method() === 'POST' && str_contains($r->url(), '/git/trees'));
});

test('the system screen names a missing branch before any tool changes', function () {
    config(['github.repository' => 'acme/carrot-tools', 'github.token' => 'ghp_test', 'github.branch' => 'mian']);
    cache()->forget('github:check');

    Http::fake([
        '*/repos/acme/carrot-tools' => Http::response(['private' => true, 'permissions' => ['push' => true]]),
        '*/git/ref/heads/mian' => Http::response(status: 404),
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.system.index'))
        ->assertInertia(fn ($page) => $page
            ->where('status.mirror.ok', false)
            ->where('status.mirror.message', 'GitHub: the repository has no branch named `mian`. Point GITHUB_BRANCH at one that exists.'));
});

test('a repository name without an owner is named as such, not left as a 404', function () {
    // Built first: the test queue runs jobs inline, so the mirror would fire
    // on create and throw before the expectation below could catch it.
    $tool = Tool::factory()->create();

    config(['github.repository' => 'mirror', 'github.token' => 'ghp_test']);

    // No call is made: the answer is in the configuration, and GitHub would
    // only have said "Not Found", which reads like a missing repository or a
    // bad token rather than the typo it is.
    expect(app(GitHub::class)->check())
        ->toBe(['ok' => false, 'message' => 'GITHUB_REPOSITORY は owner/name の形式で指定してください（現在: mirror）。']);

    expect(fn () => (new MirrorToolToRepo($tool->ulid, $tool->slug))->handle(app(GitHub::class)))
        ->toThrow(RuntimeException::class, 'has to be owner/name');
});

test('a burst of saves in one transaction does not poison it', function () {
    Bus::fake();
    config(['github.repository' => 'acme/carrot-tools', 'github.token' => 'ghp_test']);

    $tool = Tool::factory()->create();

    // ApproveSubmission saves a tool twice inside one transaction. A unique
    // lock would take the same cache row twice; the second insert fails on the
    // duplicate key and Postgres ends the transaction, so the approval fails.
    DB::transaction(function () use ($tool): void {
        $tool->forceFill(['summary' => 'まず'])->save();
        $tool->forceFill(['version' => '202609011257'])->save();
    });

    expect($tool->fresh()->version)->toBe('202609011257');

    Bus::assertDispatched(MirrorToolToRepo::class);
});
