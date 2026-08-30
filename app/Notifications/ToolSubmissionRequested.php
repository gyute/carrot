<?php

namespace App\Notifications;

use App\Enums\SubmissionStatus;
use App\Models\Message;
use App\Models\ToolSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * The one-line event behind the bell for admins: a request arrived. Its link
 * goes to the message, which in turn links to the approval screen.
 */
class ToolSubmissionRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ToolSubmission $submission, public Message $message) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array{title: string, body: string, url: string, submission: string, message: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->submission->status === SubmissionStatus::Endorsed ? 'システム確認の依頼が届きました' : 'ツールの承認申請が届きました',
            'body' => "{$this->submission->user->name} さんが「{$this->submission->displayName()}」の{$this->submission->action->label()}を申請しました。",
            'url' => route('inbox.show', $this->message, absolute: false),
            'submission' => $this->submission->ulid,
            'message' => $this->message->ulid,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
