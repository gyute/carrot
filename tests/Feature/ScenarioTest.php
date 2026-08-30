<?php

use App\Enums\SubmissionStatus;
use App\Enums\ToolKind;
use App\Enums\ToolRunStatus;
use App\Enums\ToolStatus;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\ToolSubmission;
use App\Models\User;
use App\Sandbox\FakeSandboxRunner;
use App\Sandbox\SandboxRunner;

/**
 * The whole platform from an empty database, walked the way a department
 * would: one member registers a tool of each kind, their manager endorses,
 * an admin publishes, and the catalog ends up with what a seeded demo used
 * to ship. Every URL here is a documentation domain (example.com / .example)
 * or a portal path - nothing points at a real site.
 */
const DEPARTMENT = '開発';

/**
 * @return array<string, mixed>
 */
function toolPayload(string $kind, string $name, array $config, array $overrides = []): array
{
    return [
        'kind' => $kind,
        'name' => $name,
        'summary' => "{$name}のサンプルです。",
        'icon' => 'wrench',
        'accent' => 'sky',
        'department' => DEPARTMENT,
        'categories' => ['サンプル'],
        'config' => $config,
        'submit' => true,
        ...$overrides,
    ];
}

/**
 * Register, endorse, publish. Returns the tool the approval created.
 */
function publishTool(object $test, User $requester, User $manager, User $admin, array $payload): Tool
{
    $test->actingAs($requester)
        ->post(route('tools.submissions.store'), $payload)
        ->assertRedirect();

    $submission = ToolSubmission::query()->latest('id')->firstOrFail();

    expect($submission->status)->toBe(SubmissionStatus::Pending);

    $test->actingAs($manager)
        ->post(route('admin.approvals.approve', $submission), ['comment' => '部署として問題なし'])
        ->assertRedirect();

    expect($submission->fresh()?->status)->toBe(SubmissionStatus::Endorsed);

    $test->actingAs($admin)
        ->post(route('admin.approvals.approve', $submission))
        ->assertRedirect();

    return Tool::query()->latest('id')->firstOrFail();
}

beforeEach(function () {
    $this->runner = new FakeSandboxRunner;
    $this->app->instance(SandboxRunner::class, $this->runner);

    $this->requester = User::factory()->create(['name' => '申請者', 'department' => DEPARTMENT]);
    $this->manager = User::factory()->manager(DEPARTMENT)->create(['name' => '部署管理者']);
    $this->admin = User::factory()->admin()->create(['name' => 'システム管理者']);
});

test('a department fills an empty catalog with a link, an embed and a script tool', function () {
    expect(Tool::query()->count())->toBe(0);

    $this->actingAs($this->requester)->get(route('tools.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('tools', 0));

    // 1. A link tool pointing back into the portal.
    $link = publishTool($this, $this->requester, $this->manager, $this->admin, toolPayload(
        'link',
        '申請一覧へのリンク',
        ['url' => '/tools/submissions'],
        ['icon' => 'scroll-text', 'accent' => 'amber', 'categories' => ['データ']],
    ));

    // 2. An embed tool framing an external sample page.
    $embed = publishTool($this, $this->requester, $this->manager, $this->admin, toolPayload(
        'embed',
        'サンプルページ',
        ['url' => 'https://example.com/'],
        ['icon' => 'app-window'],
    ));

    // 3. A script tool that greets whoever runs it.
    $source = <<<'PHP_SOURCE'
    <?php
    $inputs = json_decode((string) file_get_contents(getenv('TOOL_INPUTS')), true) ?? [];
    printf("こんにちは、%s さん。\n", $inputs['name'] ?? 'world');
    PHP_SOURCE;

    $script = publishTool($this, $this->requester, $this->manager, $this->admin, toolPayload(
        'script',
        'サンドボックス動作確認',
        [
            'runtime' => 'php',
            'timeout_sec' => 10,
            'memory_mb' => 64,
            'network' => 'none',
            'inputs' => [['key' => 'name', 'label' => '名前', 'type' => 'text', 'required' => true]],
        ],
        ['icon' => 'terminal', 'accent' => 'violet', 'source' => $source],
    ));

    expect(Tool::query()->count())->toBe(3)
        ->and($link->kind)->toBe(ToolKind::Link)
        ->and($embed->kind)->toBe(ToolKind::Embed)
        ->and($script->kind)->toBe(ToolKind::Script)
        ->and($script->source_hash)->toBe(hash('sha256', $source));

    // Every one of them is published, versioned and credited to the two reviewers.
    foreach ([$link, $embed, $script] as $tool) {
        expect($tool->status)->toBe(ToolStatus::Running)
            ->and($tool->version)->not->toBeNull()
            ->and($tool->owner_id)->toBe($this->requester->id)
            ->and($tool->endorsed_by)->toBe($this->manager->id)
            ->and($tool->approved_by)->toBe($this->admin->id)
            ->and($tool->department)->toBe(DEPARTMENT);
    }

    // The catalog now carries all three, filterable by the tags they were given.
    $this->actingAs(User::factory()->create())->get(route('tools.index'))
        ->assertInertia(fn ($page) => $page
            ->has('tools', 3)
            ->where('tagGroups.2.key', 'department')
            ->where('tagGroups.2.options', [['value' => DEPARTMENT, 'count' => 3]])
        );

    // A link tool opens where it points; an embed tool is framed in the portal.
    $this->actingAs($this->requester)->get(route('tools.show', $embed))
        ->assertInertia(fn ($page) => $page
            ->where('tool.embedUrl', 'https://example.com/')
            ->where('tool.href', "/tools/{$embed->ulid}")
        );

    // And the script actually runs, with its inputs handed over as a file.
    $this->actingAs($this->requester)
        ->post(route('tools.runs.store', $script), ['inputs' => ['name' => '森']])
        ->assertRedirect();

    $run = ToolRun::query()->sole();

    expect($run->status)->toBe(ToolRunStatus::Completed)
        ->and($run->source_hash)->toBe($script->source_hash)
        ->and($this->runner->lastSpec()?->inputs)->toBe(['name' => '森'])
        ->and($this->runner->lastSpec()?->source)->toBe($source);
});

test('the reviewers are told at each stage and the requester hears back', function () {
    $this->actingAs($this->requester)
        ->post(route('tools.submissions.store'), toolPayload('link', 'お知らせ確認用', ['url' => 'https://tool.example/']))
        ->assertRedirect();

    $submission = ToolSubmission::query()->sole();

    // The department manager is the one told first - not the admins.
    expect($this->manager->unreadNotifications()->count())->toBe(1)
        ->and($this->admin->unreadNotifications()->count())->toBe(0);

    $this->actingAs($this->manager)->post(route('admin.approvals.approve', $submission))->assertRedirect();

    // Endorsed: now it is the admins' turn, and the requester is kept posted.
    expect($this->admin->unreadNotifications()->count())->toBe(1);

    $this->actingAs($this->requester)->get(route('inbox.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('inbox/index')->has('messages.data', 1));

    $this->actingAs($this->admin)->post(route('admin.approvals.approve', $submission))->assertRedirect();

    $this->actingAs($this->requester)->get(route('inbox.index'))
        ->assertInertia(fn ($page) => $page->has('messages.data', 2));
});

test('a rejected request never reaches the catalog and can be reworked', function () {
    $this->actingAs($this->requester)
        ->post(route('tools.submissions.store'), toolPayload('embed', '差し戻される申請', ['url' => 'https://example.com/']))
        ->assertRedirect();

    $submission = ToolSubmission::query()->sole();

    $this->actingAs($this->manager)
        ->post(route('admin.approvals.reject', $submission), ['comment' => '用途を書いてください'])
        ->assertRedirect();

    expect($submission->fresh()?->status)->toBe(SubmissionStatus::Rejected)
        ->and(Tool::query()->count())->toBe(0);

    $this->actingAs($this->requester)->get(route('tools.index'))
        ->assertInertia(fn ($page) => $page->has('tools', 0));

    // Rework means a fresh request; the rejected one stays as the record.
    $this->actingAs($this->requester)
        ->post(route('tools.submissions.store'), toolPayload('embed', '書き直した申請', ['url' => 'https://example.com/']))
        ->assertRedirect();

    expect(ToolSubmission::query()->count())->toBe(2);
});

test('an admin can publish alone when the department has no manager', function () {
    $submission = ToolSubmission::factory()
        ->for(User::factory()->create())
        ->pending()
        ->create(['payload' => toolPayload('link', '管理者だけで承認', ['url' => 'https://tool.example/'], ['department' => '総務'])]);

    $this->actingAs($this->admin)->post(route('admin.approvals.approve', $submission))->assertRedirect();

    $tool = Tool::query()->sole();

    // The admin stood in for the department, so both stamps are theirs.
    expect($submission->fresh()?->status)->toBe(SubmissionStatus::Approved)
        ->and($tool->endorsed_by)->toBe($this->admin->id)
        ->and($tool->approved_by)->toBe($this->admin->id);
});
