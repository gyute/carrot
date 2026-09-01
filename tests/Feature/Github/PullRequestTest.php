<?php

use App\Actions\Tools\ApproveSubmission;
use App\Actions\Tools\RejectSubmission;
use App\Enums\SubmissionStatus;
use App\Jobs\SyncSubmissionPullRequest;
use App\Models\Tool;
use App\Models\ToolSubmission;
use App\Models\User;
use App\Support\Github\GitHub;
use App\Support\Github\SubmissionDocument;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Http::preventStrayRequests();
    config(['inertia.ssr.enabled' => false]);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function reviewOn(array $overrides = []): void
{
    config([
        'github.repository' => 'acme/carrot-tools',
        'github.token' => 'ghp_test',
        'github.branch' => 'main',
        'github.path' => 'tools',
    ]);

    Http::fake([
        // Not there when it is looked for, there once it has been created.
        '*/git/ref/heads/submission/*' => Http::sequence()
            ->push('', 404)
            ->push(['object' => ['sha' => 'branch-sha']])
            ->whenEmpty(Http::response(['object' => ['sha' => 'branch-sha']])),
        '*/git/ref/heads/main' => Http::response(['object' => ['sha' => 'head-sha']]),
        '*/git/commits/head-sha' => Http::response(['tree' => ['sha' => 'tree-base']]),
        '*/git/commits/branch-sha' => Http::response(['tree' => ['sha' => 'tree-base']]),
        '*/git/refs/heads/submission/*' => Http::response([]),
        '*/git/refs' => Http::response(['ref' => 'refs/heads/submission/x']),
        '*/git/blobs' => Http::response(['sha' => 'blob-sha']),
        '*/git/trees' => Http::response(['sha' => 'tree-new']),
        '*/git/commits' => Http::response(['sha' => 'commit-sha']),
        '*/pulls?state=open*' => Http::response([]),
        '*/pulls/*/merge' => Http::response(['sha' => 'merge-sha']),
        '*/pulls/*' => Http::response(['number' => 42, 'state' => 'closed']),
        '*/pulls' => Http::response(['number' => 42]),
        ...$overrides,
    ]);
}

test('a draft is nobody else\'s business yet', function () {
    $submission = ToolSubmission::factory()->create();

    reviewOn();

    (new SyncSubmissionPullRequest($submission->ulid))->handle(app(GitHub::class));

    Http::assertNothingSent();
});

test('submitting puts the change up as a pull request on its own branch', function () {
    $submission = ToolSubmission::factory()->pending()->create();

    reviewOn();

    (new SyncSubmissionPullRequest($submission->ulid))->handle(app(GitHub::class));

    // A branch of its own, off the mirrored branch.
    Http::assertSent(fn ($r) => $r->method() === 'POST'
        && str_ends_with($r->url(), '/git/refs')
        && $r->data()['ref'] === "refs/heads/submission/{$submission->ulid}");

    // And a pull request describing it by ULID, never by name.
    Http::assertSent(function ($r) use ($submission) {
        if ($r->method() !== 'POST' || ! str_ends_with($r->url(), '/pulls')) {
            return false;
        }

        return $r->data()['head'] === "submission/{$submission->ulid}"
            && $r->data()['base'] === 'main'
            && str_contains($r->data()['body'], "Requested-by: {$submission->user->ulid}")
            && ! str_contains($r->data()['body'], $submission->user->name);
    });

    expect($submission->fresh()->github_pr_number)->toBe(42);
});

test('approving merges it and records the commit on the tool', function () {
    $tool = Tool::factory()->create();
    $submission = ToolSubmission::factory()->pending()->updating($tool)->create(['github_pr_number' => 42]);
    $submission->forceFill(['status' => SubmissionStatus::Approved])->saveQuietly();

    reviewOn();

    (new SyncSubmissionPullRequest($submission->ulid))->handle(app(GitHub::class));

    Http::assertSent(fn ($r) => $r->method() === 'PUT'
        && str_contains($r->url(), '/pulls/42/merge')
        && $r->data()['merge_method'] === 'squash');

    expect($tool->fresh()->mirror_commit_sha)->toBe('merge-sha');
});

test('rejecting closes it, and so does withdrawing - which raises no event of its own', function () {
    foreach ([SubmissionStatus::Rejected, SubmissionStatus::Withdrawn] as $status) {
        $submission = ToolSubmission::factory()->create(['github_pr_number' => 42]);
        $submission->forceFill(['status' => $status])->saveQuietly();

        reviewOn();

        (new SyncSubmissionPullRequest($submission->ulid))->handle(app(GitHub::class));

        Http::assertSent(fn ($r) => $r->method() === 'PATCH'
            && str_contains($r->url(), '/pulls/42')
            && $r->data()['state'] === 'closed');
    }
});

test('a merge GitHub will not take is not an error - the portal already decided', function () {
    $tool = Tool::factory()->create();
    $submission = ToolSubmission::factory()->pending()->updating($tool)->create(['github_pr_number' => 42]);
    $submission->forceFill(['status' => SubmissionStatus::Approved])->saveQuietly();

    reviewOn(['*/pulls/*/merge' => Http::response(['message' => 'Merge conflict'], 405)]);

    (new SyncSubmissionPullRequest($submission->ulid))->handle(app(GitHub::class));

    // No commit recorded, and the branch is left for somebody to look at.
    expect($tool->fresh()->mirror_commit_sha)->toBeNull();
    Http::assertNotSent(fn ($r) => $r->method() === 'DELETE');
});

test('every status change reaches the review side, withdrawal included', function () {
    $tool = Tool::factory()->create();
    $submission = ToolSubmission::factory()->pending()->updating($tool)->create();
    $reviewer = User::factory()->admin()->create();

    Bus::fake();
    config(['github.repository' => 'acme/carrot-tools', 'github.token' => 'ghp_test']);

    app(RejectSubmission::class)->handle($submission, $reviewer, '差し戻します。');

    Bus::assertDispatched(
        SyncSubmissionPullRequest::class,
        fn (SyncSubmissionPullRequest $job): bool => $job->ulid === $submission->ulid,
    );
});

test('the document projects a create submission that has no tool yet', function () {
    config(['github.path' => 'tools']);

    $submission = ToolSubmission::factory()->create(['payload' => [
        ...ToolSubmission::factory()->make()->payload,
        'name' => 'Tax calculator',
    ]]);

    $document = new SubmissionDocument($submission);

    expect($document->directory())->toBe('tools/tax-calculator')
        ->and(array_keys($document->files()))->toBe(['tools/tax-calculator/tool.json'])
        ->and($document->branch())->toBe('submission/'.$submission->ulid);
});

test('a name with nothing sluggable in it still lands somewhere', function () {
    config(['github.path' => 'tools']);

    // Str::slug drops Japanese entirely, so every such tool would otherwise
    // want the same directory. ApproveSubmission numbers them; the mirror
    // follows whatever slug the row ended up with.
    $submission = ToolSubmission::factory()->create();

    expect(Str::slug($submission->payload['name']))->toBe('')
        ->and((new SubmissionDocument($submission))->directory())->toBe('tools/tool');
});
