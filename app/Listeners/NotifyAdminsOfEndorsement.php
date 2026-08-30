<?php

namespace App\Listeners;

use App\Enums\MessageKind;
use App\Events\MessageReceived;
use App\Events\ToolSubmissionEndorsed;
use App\Models\Message;
use App\Models\ToolSubmission;
use App\Models\User;
use App\Notifications\ToolSubmissionRequested;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Second stage: once a department has endorsed a request, every system
 * administrator is asked to confirm it, and the requester is told it moved on.
 */
class NotifyAdminsOfEndorsement implements ShouldQueue
{
    public function handle(ToolSubmissionEndorsed $event): void
    {
        $submission = $event->submission->load(['user', 'tool', 'endorser']);

        $body = "{$submission->endorser?->name} さんが部署として「{$submission->displayName()}」の{$submission->action->label()}申請を承認しました。\nシステム管理者として内容を確認し、公開の可否を判断してください。";

        if ($submission->endorse_comment !== null && $submission->endorse_comment !== '') {
            $body .= "\n\n部署管理者のコメント:\n{$submission->endorse_comment}";
        }

        User::query()->admins()->each(function (User $admin) use ($submission, $body): void {
            $message = Message::query()->create([
                'recipient_id' => $admin->id,
                'sender_id' => $submission->endorsed_by,
                'kind' => MessageKind::SubmissionRequested,
                'subject' => "【システム確認のお願い】{$submission->displayName()}（{$submission->action->label()}）",
                'body' => $body,
                'action_url' => route('admin.approvals.show', $submission, absolute: false),
                'action_label' => '承認画面を開く',
                'subject_type' => ToolSubmission::class,
                'subject_id' => $submission->id,
            ]);

            MessageReceived::dispatch($message);

            $admin->notify(new ToolSubmissionRequested($submission, $message));
        });

        $toRequester = Message::query()->create([
            'recipient_id' => $submission->user_id,
            'sender_id' => $submission->endorsed_by,
            'kind' => MessageKind::SubmissionRequested,
            'subject' => "【部署承認】{$submission->displayName()}",
            'body' => "「{$submission->displayName()}」の申請が部署で承認され、システム管理者の確認に進みました。",
            'action_url' => route('tools.submissions.show', $submission, absolute: false),
            'action_label' => '申請を開く',
            'subject_type' => ToolSubmission::class,
            'subject_id' => $submission->id,
        ]);

        MessageReceived::dispatch($toRequester);
    }
}
