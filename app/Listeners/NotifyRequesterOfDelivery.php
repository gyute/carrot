<?php

namespace App\Listeners;

use App\Enums\MessageKind;
use App\Events\MessageReceived;
use App\Events\ToolRequestDelivered;
use App\Models\Message;
use App\Models\ToolRequest;
use App\Notifications\ToolRequestReviewed;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The answer the requester was waiting for: the tool exists, here it is.
 */
class NotifyRequesterOfDelivery implements ShouldQueue
{
    public function handle(ToolRequestDelivered $event): void
    {
        $toolRequest = $event->toolRequest->load(['user', 'tool']);
        $tool = $toolRequest->tool;

        if ($tool === null) {
            return;
        }

        $message = Message::query()->create([
            'recipient_id' => $toolRequest->user_id,
            'sender_id' => $toolRequest->decided_by,
            'kind' => MessageKind::RequestDelivered,
            'subject' => "【公開】{$toolRequest->title}",
            'body' => "リクエストいただいた「{$toolRequest->title}」に対して、ツール「{$tool->name}」を公開しました。\nご確認ください。",
            'action_url' => route('tools.show', $tool, absolute: false),
            'action_label' => 'ツールを開く',
            'subject_type' => ToolRequest::class,
            'subject_id' => $toolRequest->id,
        ]);

        MessageReceived::dispatch($message);

        $toolRequest->user->notify(new ToolRequestReviewed($toolRequest, $message));
    }
}
