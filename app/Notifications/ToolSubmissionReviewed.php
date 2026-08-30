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
 * Tells the requester their request was approved or sent back.
 */
class ToolSubmissionReviewed extends Notification implements ShouldQueue
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
        $approved = $this->submission->status === SubmissionStatus::Approved;

        return [
            'title' => $approved ? '申請が承認されました' : '申請が差し戻されました',
            'body' => "「{$this->submission->displayName()}」の{$this->submission->action->label()}申請を {$this->submission->reviewer?->name} さんが".($approved ? '承認しました。' : '差し戻しました。'),
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
