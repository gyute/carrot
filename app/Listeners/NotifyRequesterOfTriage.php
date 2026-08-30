<?php

namespace App\Listeners;

use App\Enums\MessageKind;
use App\Enums\ToolRequestStatus;
use App\Events\MessageReceived;
use App\Events\ToolRequestTriaged;
use App\Models\Message;
use App\Models\ToolRequest;
use App\Notifications\ToolRequestReviewed;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyRequesterOfTriage implements ShouldQueue
{
    public function handle(ToolRequestTriaged $event): void
    {
        $toolRequest = $event->toolRequest->load(['user', 'decider', 'duplicateOf']);

        $lines = [
            "「{$toolRequest->title}」を {$toolRequest->decider?->name} さんが確認し、{$toolRequest->status->label()}になりました。",
        ];

        if ($toolRequest->status === ToolRequestStatus::Duplicate && $toolRequest->duplicateOf !== null) {
            $lines[] = "同じ内容の「{$toolRequest->duplicateOf->title}」にまとめました。そちらで進みます。";
        }

        if ($toolRequest->decision_comment !== null && $toolRequest->decision_comment !== '') {
            $lines[] = '';
            $lines[] = 'コメント:';
            $lines[] = $toolRequest->decision_comment;
        }

        $message = Message::query()->create([
            'recipient_id' => $toolRequest->user_id,
            'sender_id' => $toolRequest->decided_by,
            'kind' => MessageKind::RequestTriaged,
            'subject' => "【{$toolRequest->status->label()}】{$toolRequest->title}",
            'body' => implode("\n", $lines),
            'action_url' => route('tools.requests.show', $toolRequest, absolute: false),
            'action_label' => 'リクエストを開く',
            'subject_type' => ToolRequest::class,
            'subject_id' => $toolRequest->id,
        ]);

        MessageReceived::dispatch($message);

        $toolRequest->user->notify(new ToolRequestReviewed($toolRequest, $message));
    }
}
