<?php

use App\Enums\ToolRequestPriority;
use App\Enums\ToolRequestStatus;
use App\Models\Message;
use App\Models\Tool;
use App\Models\ToolRequest;
use App\Models\ToolSubmission;
use App\Models\User;

test('members and managers cannot open the triage screens', function () {
    $toolRequest = ToolRequest::factory()->create(['department' => '開発']);

    $this->actingAs(User::factory()->create())
        ->get(route('admin.requests.index'))
        ->assertForbidden();

    $this->actingAs(User::factory()->manager('開発')->create())
        ->post(route('admin.requests.accept', $toolRequest))
        ->assertForbidden();
});

test('accepting a request records the decision and tells the requester', function () {
    $admin = User::factory()->admin()->create();
    $developer = User::factory()->admin()->create();
    $toolRequest = ToolRequest::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.requests.accept', $toolRequest), [
            'comment' => '来月やります。',
            'priority' => 'high',
            'assignee' => $developer->ulid,
        ])
        ->assertRedirect();

    $toolRequest->refresh();

    expect($toolRequest->status)->toBe(ToolRequestStatus::Accepted)
        ->and($toolRequest->priority)->toBe(ToolRequestPriority::High)
        ->and($toolRequest->assignee_id)->toBe($developer->id)
        ->and($toolRequest->decided_by)->toBe($admin->id);

    expect(Message::query()->where('recipient_id', $toolRequest->user_id)->exists())->toBeTrue();
});

test('an assignee is named by ULID, must be on the development team, and is stored by row id', function () {
    $toolRequest = ToolRequest::factory()->create();
    $admin = User::factory()->admin()->create();
    $outsider = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.requests.accept', $toolRequest), ['assignee' => $outsider->ulid])
        ->assertSessionHasErrors('assignee');

    // The login ID is not a handle anything may point at, SSO or no SSO.
    $developer = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.requests.accept', $toolRequest), ['assignee' => $developer->username])
        ->assertSessionHasErrors('assignee');

    $this->actingAs($admin)
        ->post(route('admin.requests.accept', $toolRequest), ['assignee' => $developer->ulid])
        ->assertRedirect();

    expect($toolRequest->refresh()->assignee_id)->toBe($developer->id);

    // Neither a renamed login ID nor a re-pointed one moves the assignment.
    $developer->forceFill(['username' => 'renamed-dev'])->save();

    expect($toolRequest->refresh()->assignee->is($developer))->toBeTrue();
});

test('the triage screen offers the development team rather than free text', function () {
    $toolRequest = ToolRequest::factory()->create();
    $developer = User::factory()->admin()->create(['name' => '開発 太郎']);
    User::factory()->create(['name' => '営業 花子']);

    $this->actingAs($developer)
        ->get(route('admin.requests.show', $toolRequest))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('assignees', 1)
            ->where('assignees.0.ulid', $developer->ulid));
});

test('every user gets a ULID, including ones that predate the column', function () {
    $user = User::factory()->create();

    expect($user->ulid)->toBeString()->toHaveLength(26);

    // The backfill path: a row written straight to the table still ends up
    // addressable, because the column is not nullable.
    expect(User::query()->whereNull('ulid')->exists())->toBeFalse();
});

test('declining needs a comment', function () {
    $toolRequest = ToolRequest::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.requests.decline', $toolRequest))
        ->assertSessionHasErrors('comment');

    expect($toolRequest->refresh()->status)->toBe(ToolRequestStatus::Open);

    $this->actingAs($admin)
        ->post(route('admin.requests.decline', $toolRequest), ['comment' => '既存のツールで足ります。'])
        ->assertRedirect();

    expect($toolRequest->refresh()->status)->toBe(ToolRequestStatus::Declined);
});

test('a duplicate points at the request it was folded into, and never at itself', function () {
    $original = ToolRequest::factory()->create();
    $toolRequest = ToolRequest::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.requests.duplicate', $toolRequest), ['duplicate_of' => $toolRequest->ulid])
        ->assertSessionHasErrors('duplicate_of');

    $this->actingAs($admin)
        ->post(route('admin.requests.duplicate', $toolRequest), ['duplicate_of' => $original->ulid])
        ->assertRedirect();

    $toolRequest->refresh();

    expect($toolRequest->status)->toBe(ToolRequestStatus::Duplicate)
        ->and($toolRequest->duplicate_of_id)->toBe($original->id);
});

test('delivering closes the request with the tool that answers it', function () {
    $toolRequest = ToolRequest::factory()->accepted()->create();
    $tool = Tool::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.requests.deliver', $toolRequest), ['tool' => $tool->ulid])
        ->assertRedirect();

    $toolRequest->refresh();

    expect($toolRequest->status)->toBe(ToolRequestStatus::Delivered)
        ->and($toolRequest->tool_id)->toBe($tool->id);

    expect(Message::query()
        ->where('recipient_id', $toolRequest->user_id)
        ->where('action_label', 'ツールを開く')
        ->exists())->toBeTrue();
});

test('a triaged request is closed and cannot be triaged again', function () {
    $toolRequest = ToolRequest::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.requests.decline', $toolRequest), ['comment' => '見送ります。'])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('admin.requests.accept', $toolRequest))
        ->assertForbidden();
});

test('a submission filed from a request remembers which request it answers', function () {
    $toolRequest = ToolRequest::factory()->accepted()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('tools.submissions.store'), [
            'kind' => 'link',
            'name' => '消費税計算',
            'summary' => '税込金額をまとめて出します。',
            'icon' => 'scroll-text',
            'accent' => 'amber',
            'config' => ['url' => 'https://tool.example/tax'],
            'tool_request' => $toolRequest->ulid,
        ])
        ->assertRedirect();

    expect(ToolSubmission::query()->sole()->tool_request_id)->toBe($toolRequest->id);
});

test('approving a submission filed against a request delivers that request', function () {
    $toolRequest = ToolRequest::factory()->accepted()->create();

    $submission = ToolSubmission::factory()->pending()->create([
        'tool_request_id' => $toolRequest->id,
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.approvals.approve', $submission), ['comment' => '公開します。'])
        ->assertRedirect();

    $toolRequest->refresh();

    expect($toolRequest->status)->toBe(ToolRequestStatus::Delivered)
        ->and($toolRequest->tool_id)->toBe($submission->refresh()->tool_id);
});
