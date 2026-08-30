<?php

use App\Enums\SubmissionStatus;
use App\Enums\ToolStatus;
use App\Events\ToolSubmissionReviewed;
use App\Models\Message;
use App\Models\Tool;
use App\Models\ToolSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Event;

test('members cannot open the approval screens', function () {
    $submission = ToolSubmission::factory()->pending()->create();

    $this->actingAs(User::factory()->create())->get(route('admin.approvals.index'))->assertForbidden();
    $this->actingAs($submission->user)->post(route('admin.approvals.approve', $submission))->assertForbidden();
});

test('the approval list puts pending requests first and counts them in shared props', function () {
    ToolSubmission::factory()->pending()->count(2)->create();
    ToolSubmission::factory()->approved()->create();
    ToolSubmission::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.approvals.index'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/approvals/index')
            ->has('pending', 2)
            ->has('decided', 1)
            ->where('pendingApprovals', 2)
        );
});

test('approving a new tool publishes it with a version and the reviewers recorded', function () {
    Event::fake([ToolSubmissionReviewed::class]);
    $this->travelTo('2026-08-27 10:37');

    $submission = ToolSubmission::factory()->script()->pending()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.approvals.approve', $submission), ['comment' => 'OK'])
        ->assertRedirect(route('admin.approvals.show', $submission));

    $tool = Tool::query()->sole();
    $submission->refresh();

    expect($tool->name)->toBe('新しいツール')
        ->and($tool->slug)->toBe('tool')
        ->and($tool->status)->toBe(ToolStatus::Running)
        ->and($tool->version)->toBe('202608271037')
        ->and($tool->owner_id)->toBe($submission->user_id)
        ->and($tool->requested_by)->toBe($submission->user_id)
        ->and($tool->approved_by)->toBe($admin->id)
        ->and($tool->approved_submission_id)->toBe($submission->id)
        ->and($tool->source_hash)->toBe(hash('sha256', (string) $tool->source))
        ->and($tool->categories())->toBe(['データ'])
        ->and($submission->status)->toBe(SubmissionStatus::Approved)
        ->and($submission->tool_id)->toBe($tool->id)
        ->and($submission->review_comment)->toBe('OK');

    Event::assertDispatched(ToolSubmissionReviewed::class);

    // A second approval within the same minute bumps the counter instead of repeating the stamp.
    $change = ToolSubmission::factory()->for($submission->user)->updating($tool)->pending()->create();
    $this->actingAs($admin)->post(route('admin.approvals.approve', $change))->assertRedirect();

    expect($tool->fresh()?->version)->toBe('202608271037.2')
        ->and($tool->fresh()?->config)->toBe(['url' => 'https://changed.example/']);
});

test('slugs stay unique when two tools share a name', function () {
    Tool::factory()->create(['slug' => 'tool']);
    $admin = User::factory()->admin()->create();
    $submission = ToolSubmission::factory()->pending()->create();

    $this->actingAs($admin)->post(route('admin.approvals.approve', $submission))->assertRedirect();

    expect(Tool::query()->where('slug', 'tool-2')->exists())->toBeTrue();
});

test('rejecting needs a comment and leaves the tool untouched', function () {
    $tool = Tool::factory()->link('https://old.example')->create();
    $submission = ToolSubmission::factory()->for($tool->owner)->updating($tool)->pending()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('admin.approvals.show', $submission))
        ->post(route('admin.approvals.reject', $submission))
        ->assertSessionHasErrors('comment');

    $this->actingAs($admin)
        ->post(route('admin.approvals.reject', $submission), ['comment' => 'URL が社外です'])
        ->assertRedirect();

    expect($submission->fresh()?->status)->toBe(SubmissionStatus::Rejected)
        ->and($tool->fresh()?->url())->toBe('https://old.example');

    // Decided requests cannot be decided again.
    $this->actingAs($admin)->post(route('admin.approvals.approve', $submission))->assertForbidden();
});

test('approving a deprecation retires the tool and the admin can restore it', function () {
    $tool = Tool::factory()->create();
    $submission = ToolSubmission::factory()->for($tool->owner)->deprecating($tool)->pending()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.approvals.approve', $submission))->assertRedirect();

    expect($tool->fresh()?->status)->toBe(ToolStatus::Deprecated)
        ->and($tool->fresh()?->deprecated_at)->not->toBeNull();

    $this->actingAs($admin)->post(route('admin.tools.restore', $tool))->assertRedirect();

    expect($tool->fresh()?->status)->toBe(ToolStatus::Running);

    $this->actingAs($admin)->delete(route('admin.tools.destroy', $tool))->assertRedirect(route('tools.index'));

    expect(Tool::query()->count())->toBe(0)
        ->and(Tool::withTrashed()->count())->toBe(1);
});

test('a department manager endorses first, then a system admin publishes', function () {
    $manager = User::factory()->manager('開発')->create();
    $otherManager = User::factory()->manager('総務')->create();
    $admin = User::factory()->admin()->create();
    $submission = ToolSubmission::factory()->pending()->create();

    // Only the department's own manager sees and can act on it.
    $this->actingAs($otherManager)->get(route('admin.approvals.index'))
        ->assertInertia(fn ($page) => $page->has('pending', 0)->where('stage', 'manager'));
    $this->actingAs($otherManager)->post(route('admin.approvals.approve', $submission))->assertForbidden();

    $this->actingAs($manager)->get(route('admin.approvals.index'))
        ->assertInertia(fn ($page) => $page->has('pending', 1)->where('pendingApprovals', 1));

    $this->actingAs($manager)
        ->post(route('admin.approvals.approve', $submission), ['comment' => '部署として問題なし'])
        ->assertRedirect();

    $submission->refresh();

    expect($submission->status)->toBe(SubmissionStatus::Endorsed)
        ->and($submission->endorsed_by)->toBe($manager->id)
        ->and($submission->endorse_comment)->toBe('部署として問題なし')
        ->and(Tool::query()->count())->toBe(0);

    // Endorsed: the manager is done, the admins are told, the requester too.
    $this->actingAs($manager)->post(route('admin.approvals.approve', $submission))->assertForbidden();
    expect($admin->unreadNotifications()->count())->toBe(1)
        ->and(Message::query()->where('recipient_id', $submission->user_id)->count())->toBe(1);

    $this->actingAs($admin)->get(route('admin.approvals.index'))
        ->assertInertia(fn ($page) => $page->has('pending', 1)->where('stage', 'admin'));

    $this->actingAs($admin)->post(route('admin.approvals.approve', $submission))->assertRedirect();

    $tool = Tool::query()->sole();

    expect($submission->fresh()?->status)->toBe(SubmissionStatus::Approved)
        ->and($tool->endorsed_by)->toBe($manager->id)
        ->and($tool->approved_by)->toBe($admin->id);
});

test('a manager can reject at the first stage and members still cannot review', function () {
    $manager = User::factory()->manager('開発')->create();
    $submission = ToolSubmission::factory()->pending()->create();

    $this->actingAs($submission->user)->get(route('admin.approvals.index'))->assertForbidden();

    $this->actingAs($manager)
        ->post(route('admin.approvals.reject', $submission), ['comment' => '要件不明'])
        ->assertRedirect();

    expect($submission->fresh()?->status)->toBe(SubmissionStatus::Rejected);
});

test('a submission goes to the department managers, or to admins when there are none', function () {
    $manager = User::factory()->manager('開発')->create();
    $admin = User::factory()->admin()->create();

    $withManager = ToolSubmission::factory()->create();
    $this->actingAs($withManager->user)->post(route('tools.submissions.submit', $withManager));

    expect($manager->unreadNotifications()->count())->toBe(1)
        ->and($admin->unreadNotifications()->count())->toBe(0);

    $orphan = ToolSubmission::factory()->create(['payload' => ['kind' => 'link', 'name' => 'x', 'summary' => 'y', 'icon' => 'link', 'accent' => 'sky', 'department' => '総務', 'categories' => [], 'config' => ['url' => 'https://a.example/'], 'source' => null]]);
    $this->actingAs($orphan->user)->post(route('tools.submissions.submit', $orphan));

    expect($admin->unreadNotifications()->count())->toBe(1);
});
