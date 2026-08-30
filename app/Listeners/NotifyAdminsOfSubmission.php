<?php

namespace App\Listeners;

use App\Enums\MessageKind;
use App\Events\MessageReceived;
use App\Events\ToolSubmissionSubmitted;
use App\Models\Message;
use App\Models\ToolSubmission;
use App\Models\User;
use App\Notifications\ToolSubmissionRequested;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * First stage: the managers of the requester's department get a message with
 * a link to the approval screen, and a notification pointing at that message.
 * A department with no manager falls through to the admins so nothing is
 * ever stuck.
 */
class NotifyAdminsOfSubmission implements ShouldQueue
{
    public function handle(ToolSubmissionSubmitted $event): void
    {
        $submission = $event->submission->load(['user', 'tool']);

        $reviewers = User::query()->managersOf($submission->department())->get();

        if ($reviewers->isEmpty()) {
            $reviewers = User::query()->admins()->get();
        }

        $reviewers->each(function (User $admin) use ($submission): void {
            $message = Message::query()->create([
                'recipient_id' => $admin->id,
                'sender_id' => $submission->user_id,
                'kind' => MessageKind::SubmissionRequested,
                'subject' => "【承認申請】{$submission->displayName()}（{$submission->action->label()}）",
                'body' => $this->body($submission),
                'action_url' => route('admin.approvals.show', $submission, absolute: false),
                'action_label' => '承認画面を開く',
                'subject_type' => ToolSubmission::class,
                'subject_id' => $submission->id,
            ]);

            MessageReceived::dispatch($message);

            $admin->notify(new ToolSubmissionRequested($submission, $message));
        });
    }

    private function body(ToolSubmission $submission): string
    {
        $department = $submission->payload['department'] ?? $submission->tool?->department;
        $who = is_string($department) && $department !== ''
            ? "{$department} の {$submission->user->name} さん"
            : "{$submission->user->name} さん";

        $lines = [
            "{$who}が「{$submission->displayName()}」の{$submission->action->label()}を申請しました。",
            '内容を確認して承認または差し戻してください。',
        ];

        if ($submission->note !== null && $submission->note !== '') {
            $lines[] = '';
            $lines[] = '申請メモ:';
            $lines[] = $submission->note;
        }

        return implode("\n", $lines);
    }
}
