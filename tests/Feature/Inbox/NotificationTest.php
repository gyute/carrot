<?php

use App\Enums\MessageKind;
use App\Models\Message;
use App\Models\ToolSubmission;
use App\Models\User;
use App\Notifications\ToolSubmissionRequested;
use App\Notifications\ToolSubmissionReviewed;

test('submitting a request messages and notifies every admin', function () {
    $admins = User::factory()->admin()->count(2)->create();
    User::factory()->create();
    $submission = ToolSubmission::factory()->create(['note' => '急ぎです']);

    $this->actingAs($submission->user)
        ->post(route('tools.submissions.submit', $submission))
        ->assertRedirect();

    expect(Message::query()->count())->toBe(2);

    $message = Message::query()->where('recipient_id', $admins[0]->id)->sole();

    expect($message->kind)->toBe(MessageKind::SubmissionRequested)
        ->and($message->subject)->toContain('新しいツール')
        ->and($message->body)->toContain('急ぎです')
        ->and($message->action_url)->toBe("/admin/approvals/{$submission->ulid}")
        ->and($message->about?->is($submission))->toBeTrue();

    foreach ($admins as $admin) {
        expect($admin->unreadNotifications()->count())->toBe(1);
        $data = $admin->notifications()->first()?->data;
        expect($data['url'])->toBe('/inbox/'.Message::query()->where('recipient_id', $admin->id)->sole()->ulid)
            ->and($data['submission'])->toBe($submission->ulid);
    }
});

test('the bell prop carries the unread count and recent items', function () {
    $admin = User::factory()->admin()->create();
    $submission = ToolSubmission::factory()->create();

    $this->actingAs($submission->user)->post(route('tools.submissions.submit', $submission));

    $this->actingAs($admin)
        ->get(route('tools.index'))
        ->assertInertia(fn ($page) => $page
            ->where('notifications.unread', 1)
            ->has('notifications.recent', 1)
            ->where('notifications.recent.0.title', 'ツールの承認申請が届きました')
            ->where('notifications.recent.0.read', false)
            ->where('pendingApprovals', 1)
        );
});

test('opening a message reads it and the notification pointing at it', function () {
    $admin = User::factory()->admin()->create();
    $submission = ToolSubmission::factory()->create();
    $this->actingAs($submission->user)->post(route('tools.submissions.submit', $submission));

    $message = Message::query()->where('recipient_id', $admin->id)->sole();

    $this->actingAs($admin)
        ->get(route('inbox.show', $message))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inbox/show')
            ->where('message.actionLabel', '承認画面を開く')
            ->where('message.read', true)
        );

    expect($message->fresh()?->isRead())->toBeTrue()
        ->and($admin->unreadNotifications()->count())->toBe(0);
});

test('a message is only visible to its recipient', function () {
    $message = Message::factory()->create();

    $this->actingAs(User::factory()->create())->get(route('inbox.show', $message))->assertNotFound();
    $this->actingAs(User::factory()->create())->patch(route('inbox.read', $message))->assertNotFound();
    $this->actingAs($message->recipient)->get(route('inbox.show', $message))->assertOk();
});

test('the inbox lists messages, filters unread and marks all read', function () {
    $user = User::factory()->create();
    Message::factory()->for($user, 'recipient')->count(2)->create();
    Message::factory()->for($user, 'recipient')->read()->create();

    $this->actingAs($user)
        ->get(route('inbox.index'))
        ->assertInertia(fn ($page) => $page
            ->component('inbox/index')
            ->has('messages.data', 3)
            ->where('unreadCount', 2)
        );

    $this->actingAs($user)
        ->get(route('inbox.index', ['unread' => 1]))
        ->assertInertia(fn ($page) => $page->has('messages.data', 2)->where('unreadOnly', true));

    $this->actingAs($user)->post(route('inbox.read-all'))->assertRedirect();

    expect(Message::query()->where('recipient_id', $user->id)->unread()->count())->toBe(0);
});

test('bell notifications can be read one at a time or all at once', function () {
    $admin = User::factory()->admin()->create();
    foreach (ToolSubmission::factory()->count(2)->create() as $submission) {
        $this->actingAs($submission->user)->post(route('tools.submissions.submit', $submission));
    }

    $first = $admin->notifications()->first();

    $this->actingAs($admin)->patch(route('notifications.read', $first->id))->assertRedirect();
    expect($admin->unreadNotifications()->count())->toBe(1);

    $this->actingAs(User::factory()->create())->patch(route('notifications.read', $first->id))->assertNotFound();

    $this->actingAs($admin)->post(route('notifications.read-all'))->assertRedirect();
    expect($admin->unreadNotifications()->count())->toBe(0);
});

test('a decision messages the requester with a link to the result', function () {
    $admin = User::factory()->admin()->create();
    $submission = ToolSubmission::factory()->pending()->create();

    $this->actingAs($admin)->post(route('admin.approvals.approve', $submission), ['comment' => 'いいですね']);

    $approved = Message::query()->where('recipient_id', $submission->user_id)->sole();

    expect($approved->kind)->toBe(MessageKind::SubmissionApproved)
        ->and($approved->body)->toContain('いいですね')
        ->and($approved->action_url)->toBe('/tools/'.$submission->fresh()?->tool?->ulid)
        ->and($submission->user->notifications()->first()?->type)->toBe(ToolSubmissionReviewed::class);

    $rejected = ToolSubmission::factory()->pending()->create();
    $this->actingAs($admin)->post(route('admin.approvals.reject', $rejected), ['comment' => 'URL が違います']);

    $message = Message::query()->where('recipient_id', $rejected->user_id)->sole();

    expect($message->kind)->toBe(MessageKind::SubmissionRejected)
        ->and($message->action_url)->toBe("/tools/submissions/{$rejected->ulid}")
        ->and($admin->notifications()->where('type', ToolSubmissionRequested::class)->count())->toBe(0);
});
