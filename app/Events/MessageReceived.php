<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the recipient's open tabs a message landed, so the bell and the
 * inbox refresh without waiting for the next poll.
 */
class MessageReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->message->recipient_id)];
    }

    /**
     * @return array{ulid: string, subject: string}
     */
    public function broadcastWith(): array
    {
        return [
            'ulid' => $this->message->ulid,
            'subject' => $this->message->subject,
        ];
    }
}
