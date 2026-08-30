<?php

namespace App\Listeners;

use App\Enums\MessageKind;
use App\Enums\SubmissionStatus;
use App\Events\MessageReceived;
use App\Events\ToolSubmissionReviewed;
use App\Models\Message;
use App\Models\ToolSubmission;
use App\Notifications\ToolSubmissionReviewed as ReviewedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyRequesterOfReview implements ShouldQueue
{
    public function handle(ToolSubmissionReviewed $event): void
    {
        $submission = $event->submission->load(['user', 'tool', 'reviewer']);
        $approved = $submission->status === SubmissionStatus::Approved;

        $lines = [
            "「{$submission->displayName()}」の{$submission->action->label()}申請を {$submission->reviewer?->name} さんが".($approved ? '承認しました。' : '差し戻しました。'),
        ];

        if ($approved && $submission->tool !== null) {
            $lines[] = "ツールは v{$submission->tool->version} として公開されています。";
        }

        if ($submission->review_comment !== null && $submission->review_comment !== '') {
            $lines[] = '';
            $lines[] = 'コメント:';
            $lines[] = $submission->review_comment;
        }

        $message = Message::query()->create([
            'recipient_id' => $submission->user_id,
            'sender_id' => $submission->reviewer_id,
            'kind' => $approved ? MessageKind::SubmissionApproved : MessageKind::SubmissionRejected,
            'subject' => ($approved ? '【承認】' : '【差し戻し】').$submission->displayName(),
            'body' => implode("\n", $lines),
            'action_url' => $approved && $submission->tool !== null
                ? route('tools.show', $submission->tool, absolute: false)
                : route('tools.submissions.show', $submission, absolute: false),
            'action_label' => $approved && $submission->tool !== null ? 'ツールを開く' : '申請を開く',
            'subject_type' => ToolSubmission::class,
            'subject_id' => $submission->id,
        ]);

        MessageReceived::dispatch($message);

        $submission->user->notify(new ReviewedNotification($submission, $message));
    }
}
