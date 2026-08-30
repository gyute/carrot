<?php

namespace App\Notifications;

use App\Models\Message;
use App\Models\ToolRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * The one-line event behind the bell for the development team: somebody asked
 * for a tool. Its link goes to the message, which links to the triage screen.
 */
class ToolRequestFiled extends Notification implements ShouldQueue
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
        return [
            'title' => 'ツールの依頼が届きました',
            'body' => "{$this->toolRequest->user->name} さんが「{$this->toolRequest->title}」を依頼しました。",
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
