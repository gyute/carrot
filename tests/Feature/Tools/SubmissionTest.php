<?php

use App\Enums\SubmissionAction;
use App\Enums\SubmissionStatus;
use App\Models\Tool;
use App\Models\ToolSubmission;
use App\Models\User;

function linkPayload(array $overrides = []): array
{
    return [
        'kind' => 'link',
        'name' => 'サンプルツール',
        'summary' => 'テスト用のリンクツールです。',
        'icon' => 'scroll-text',
        'accent' => 'amber',
        'department' => '総務',
        'categories' => ['データ'],
        'config' => ['url' => 'https://tool.example/export'],
        ...$overrides,
    ];
}

test('a member saves a draft and it is not shown to admins yet', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('tools.submissions.store'), linkPayload())
        ->assertRedirect();

    $submission = ToolSubmission::query()->sole();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.approvals.index'))
        ->assertInertia(fn ($page) => $page->has('pending', 0));

    expect($submission->status)->toBe(SubmissionStatus::Draft)
        ->and($submission->action)->toBe(SubmissionAction::Create)
        ->and($submission->payload['config'])->toBe(['url' => 'https://tool.example/export'])
        ->and($submission->payload['categories'])->toBe(['データ']);
});

test('submitting a request moves it to pending', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('tools.submissions.store'), linkPayload(['submit' => true]))
        ->assertRedirect();

    $submission = ToolSubmission::query()->sole();

    expect($submission->status)->toBe(SubmissionStatus::Pending)
        ->and($submission->submitted_at)->not->toBeNull();
});

test('a pending new tool shows in the catalog for its requester and admins only', function () {
    $requester = User::factory()->create();
    ToolSubmission::factory()->for($requester)->pending()->create();

    $this->actingAs($requester)->get(route('tools.index'))
        ->assertInertia(fn ($page) => $page
            ->has('tools', 1)
            ->where('tools.0.status', 'pending')
            ->where('tagGroups.0.options.0.value', 'pending')
        );

    $this->actingAs(User::factory()->admin()->create())->get(route('tools.index'))
        ->assertInertia(fn ($page) => $page->has('tools', 1));

    $this->actingAs(User::factory()->create())->get(route('tools.index'))
        ->assertInertia(fn ($page) => $page->has('tools', 0));
});

test('an embed pointing at our own origin is rejected', function () {
    $own = 'https://'.parse_url(config('app.url'), PHP_URL_HOST).'/login';

    $this->actingAs(User::factory()->create())
        ->from(route('tools.submissions.create'))
        ->post(route('tools.submissions.store'), linkPayload(['kind' => 'embed', 'config' => ['url' => $own]]))
        ->assertSessionHasErrors('config.url');

    $this->actingAs(User::factory()->create())
        ->from(route('tools.submissions.create'))
        ->post(route('tools.submissions.store'), linkPayload(['kind' => 'embed', 'config' => ['url' => 'http://plain.example']]))
        ->assertSessionHasErrors('config.url');
});

test('a link may be a portal path but not a protocol-relative url', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('tools.submissions.store'), linkPayload(['config' => ['url' => '/tools']]))
        ->assertSessionDoesntHaveErrors();

    $this->actingAs($user)
        ->from(route('tools.submissions.create'))
        ->post(route('tools.submissions.store'), linkPayload(['config' => ['url' => '//evil.example']]))
        ->assertSessionHasErrors('config.url');
});

test('a script needs a runtime and source within the size limit', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('tools.submissions.create'))
        ->post(route('tools.submissions.store'), linkPayload(['kind' => 'script', 'config' => ['runtime' => 'php']]))
        ->assertSessionHasErrors(['source', 'config.timeout_sec', 'config.memory_mb']);

    $this->actingAs($user)
        ->from(route('tools.submissions.create'))
        ->post(route('tools.submissions.store'), linkPayload([
            'kind' => 'script',
            'config' => ['runtime' => 'shell', 'timeout_sec' => 10, 'memory_mb' => 64],
            'source' => str_repeat('x', 70000),
        ]))
        ->assertSessionHasErrors('source');

    $this->actingAs($user)
        ->post(route('tools.submissions.store'), linkPayload([
            'kind' => 'script',
            'config' => [
                'runtime' => 'shell',
                'timeout_sec' => 10,
                'memory_mb' => 64,
                'inputs' => [['key' => 'day', 'label' => '日付', 'type' => 'text', 'required' => true]],
            ],
            'source' => "echo hi\n",
        ]))
        ->assertSessionDoesntHaveErrors();

    expect(ToolSubmission::query()->sole()->payload['config']['inputs'][0])
        ->toBe(['key' => 'day', 'label' => '日付', 'type' => 'text', 'required' => true, 'options' => null]);
});

test('only the requester edits, submits or withdraws their draft', function () {
    $submission = ToolSubmission::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('tools.submissions.show', $submission))->assertForbidden();
    $this->actingAs($stranger)->patch(route('tools.submissions.update', $submission), linkPayload())->assertForbidden();
    $this->actingAs($stranger)->post(route('tools.submissions.submit', $submission))->assertForbidden();
    $this->actingAs($stranger)->delete(route('tools.submissions.destroy', $submission))->assertForbidden();

    $this->actingAs($submission->user)->get(route('tools.submissions.show', $submission))->assertOk();
    $this->actingAs($submission->user)->delete(route('tools.submissions.destroy', $submission))->assertRedirect(route('tools.submissions.index'));

    expect(ToolSubmission::query()->count())->toBe(0);
});

test('withdrawing a pending request keeps it as withdrawn', function () {
    $submission = ToolSubmission::factory()->pending()->create();

    $this->actingAs($submission->user)
        ->delete(route('tools.submissions.destroy', $submission))
        ->assertRedirect(route('tools.submissions.show', $submission));

    expect($submission->fresh()?->status)->toBe(SubmissionStatus::Withdrawn);

    $this->actingAs($submission->user)
        ->patch(route('tools.submissions.update', $submission), linkPayload())
        ->assertForbidden();
});

test('an owner requests a behaviour change and a deprecation on their tool', function () {
    $tool = Tool::factory()->link('https://old.example')->create();
    $owner = $tool->owner;

    $this->actingAs(User::factory()->create())
        ->post(route('tools.change.store', $tool), linkPayload(['submit' => true]))
        ->assertForbidden();

    $this->actingAs($owner)
        ->post(route('tools.change.store', $tool), linkPayload(['config' => ['url' => 'https://new.example'], 'submit' => true]))
        ->assertRedirect();

    $change = ToolSubmission::query()->where('action', SubmissionAction::Update)->sole();

    expect($change->tool_id)->toBe($tool->id)
        ->and($change->payload)->toBe(['config' => ['url' => 'https://new.example'], 'source' => null])
        ->and($change->status)->toBe(SubmissionStatus::Pending);

    $this->actingAs($owner)->post(route('tools.deprecate', $tool), ['note' => '後継に移行'])->assertRedirect();

    $retire = ToolSubmission::query()->where('action', SubmissionAction::Deprecate)->sole();

    expect($retire->status)->toBe(SubmissionStatus::Pending)->and($retire->note)->toBe('後継に移行');
});

test('an owner edits display fields in place without review', function () {
    $tool = Tool::factory()->create(['name' => 'Before']);

    $this->actingAs(User::factory()->create())
        ->patch(route('tools.update', $tool), ['name' => 'Nope', 'summary' => 's', 'icon' => 'wrench', 'accent' => 'slate'])
        ->assertForbidden();

    $this->actingAs($tool->owner)
        ->patch(route('tools.update', $tool), [
            'name' => 'After',
            'summary' => '新しい概要',
            'icon' => 'database',
            'accent' => 'violet',
            'department' => '経理',
            'categories' => ['会計', 'データ'],
        ])
        ->assertRedirect(route('tools.show', $tool));

    $tool->refresh();

    expect($tool->name)->toBe('After')
        ->and($tool->icon)->toBe('database')
        ->and($tool->categories())->toBe(['会計', 'データ'])
        ->and(ToolSubmission::query()->count())->toBe(0);
});

test('the tool page shows version, requester and approver', function () {
    $tool = Tool::factory()->create(['version' => '202608271037']);
    $approved = ToolSubmission::factory()->for($tool->owner)->approved()->create(['tool_id' => $tool->id]);
    $tool->forceFill(['requested_by' => $tool->owner_id, 'approved_by' => $approved->reviewer_id])->save();

    $this->actingAs(User::factory()->create())
        ->get(route('tools.show', $tool))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tools/show')
            ->where('tool.version', '202608271037')
            ->where('tool.requester', $tool->owner->name)
            ->where('tool.approver', $approved->reviewer->name)
            ->has('history', 1)
            ->where('can.updateMetadata', false)
        );
});

test('an embed tool frames its page inside its own screen, never our own origin', function () {
    $tool = Tool::factory()->embed('https://docs.example/')->create();
    $own = Tool::factory()->embed('https://'.parse_url(config('app.url'), PHP_URL_HOST).'/login')->create();
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('tools.show', $tool))
        ->assertInertia(fn ($page) => $page->where('tool.embedUrl', 'https://docs.example/')->where('tool.href', "/tools/{$tool->ulid}"));

    $this->actingAs($user)->get(route('tools.show', $own))
        ->assertInertia(fn ($page) => $page->where('tool.embedUrl', null));
});
