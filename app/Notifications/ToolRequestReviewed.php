<?php

namespace App\Notifications;

use App\Enums\ToolRequestStatus;
use App\Models\Message;
use App\Models\ToolRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * The requester's bell: their request moved. Delivery says so outright,
 * since that is the answer they were waiting for.
 */
class ToolRequestReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ToolRequest $toolRequest, public Message $message) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array{title: string, body: string, url: string, request: string, message: string}
     */
    public function toArray(object $notifiable): array
    {
        $delivered = $this->toolRequest->status === ToolRequestStatus::Delivered;

        return [
            'title' => $delivered ? '依頼したツールが公開されました' : '依頼の状況が変わりました',
            'body' => "「{$this->toolRequest->title}」は{$this->toolRequest->status->label()}になりました。",
            'url' => route('inbox.show', $this->message, absolute: false),
            'request' => $this->toolRequest->ulid,
            'message' => $this->message->ulid,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
